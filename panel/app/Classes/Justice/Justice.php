<?php

namespace App\Classes\Justice;

class Justice extends \App\Classes\Utils
{
    private $pdo;
    private $pg_host;
    private $pg_port;
    private $pg_db;
    private $pg_user;
    private $pg_pass;
    private $ollama_embed_model;
    private $local_embed_dim;
    private $chunk_size;
    private $chunk_overlap;
    private $top_k;

    public function __construct() {
        $this->pg_host = env('DB_HOST', 'localhost');
        $this->pg_port = env('DB_PORT', 5432);
        $this->pg_db = env('DB_DATABASE', 'postgres');
        $this->pg_user = env('DB_USERNAME', 'postgres');
        $this->pg_pass = env('DB_PASSWORD', 'password');
        $this->ollama_embed_model = env('OLLAMA_EMBED_MODEL', 'nomic-embed-text');
        $this->local_embed_dim = intval(env('LOCAL_EMBED_DIM', 768));
        $this->chunk_size = intval(env('CHUNK_SIZE', 1000));
        $this->chunk_overlap = intval(env('CHUNK_OVERLAP', 200));
        $this->top_k = intval(env('CHROMA_TOP_K', 0)); // 0 means no limit
        $this->pdo = $this->get_pdo();
    }

    private function get_pdo() {
        $dsn = "pgsql:host={$this->pg_host};port={$this->pg_port};dbname={$this->pg_db}";
        $pdo = new PDO($dsn, $this->pg_user, $this->pg_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public function ask_ollama(string $system, string $context, string $question, string $model = 'llama3.1'): string {
        $prompt = $system . "\n\nContext:\n" . $context . "\n\nQuestion:\n" . $question;
        $tmp = tempnam(sys_get_temp_dir(), 'ollama_prompt_');
        file_put_contents($tmp, $prompt);
        $cmd = 'ollama run ' . escapeshellarg($model) . ' < ' . escapeshellarg($tmp) . ' 2>&1';
        $output = shell_exec($cmd);
        @unlink($tmp);
        if ($output === null) {
            return '';
        }
        return trim($output);
    }

    public function pg_upsert(array $embeddings, array $documents, array $metadatas): bool {
        try {
            $this->pdo->exec("CREATE EXTENSION IF NOT EXISTS vector");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS vector_documents (id SERIAL PRIMARY KEY, content TEXT,origin TEXT,year_code TEXT, embedding VECTOR(768), metadata JSONB)");
            $stmt = $this->pdo->prepare("INSERT INTO vector_documents (content, embedding, metadata,origin,year_code) VALUES (?, ?::vector, ?, ?, ?)");
            foreach ($embeddings as $i => $emb) {
                $vec_str = '[' . implode(',', $emb) . ']';
                $meta_json = json_encode($metadatas[$i]);
                $stmt->execute([$documents[$i], $vec_str, $meta_json, $metadatas[$i]['origin'] ?? null, $metadatas[$i]['year'] ?? null]);
            }
            return true;
        } catch (Exception $e) {
            fwrite(STDERR, "PG upsert failed: " . $e->getMessage() . "\n");
            return false;
        }
    }

    public function get_all_chunks_from_sources(array $sources): array {
        if (empty($sources)) return [];
        $placeholders = str_repeat('?,', count($sources) - 1) . '?';
        $stmt = $this->pdo->prepare("SELECT content, metadata FROM vector_documents WHERE metadata->>'source' IN ($placeholders) ORDER BY metadata->>'source', (metadata->>'chunk_index')::int");
        $stmt->execute($sources);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function vector_query(array $query_embedding, int $top_k = 5): array {
        try {
            $vec_str = '[' . implode(',', $query_embedding) . ']';
            if ($top_k > 0) {
                $stmt = $this->pdo->prepare("SELECT content, metadata FROM vector_documents ORDER BY embedding <=> ?::vector LIMIT ?");
                $stmt->execute([$vec_str, $top_k]);
            } else {
                $stmt = $this->pdo->prepare("SELECT content, metadata FROM vector_documents ORDER BY embedding <=> ?::vector");
                $stmt->execute([$vec_str]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            fwrite(STDERR, "PG query failed: " . $e->getMessage() . "\n");
            return [];
        }
    }

    public function extract_metadata_from_file(string $file_path): array {
        $metadata = ['source' => basename($file_path)];

        

        // Extract from content: custom meta headers starting with **
        $content = file_get_contents($file_path);
        if ($content !== false) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^\*\*([^:]+)\s*:\s*(.+)$/', $line, $m)) {
                    $key = strtolower(trim($m[1]));
                    $value = trim($m[2]);
                    $metadata[$key] = $value;
                }
            }
        }

        return $metadata;
    }

    public function chunk_text(string $text, int $chunk_size = 1000, int $overlap = 200): array {
        $len = mb_strlen($text);
        if ($len <= $chunk_size) return array($text);
        $chunks = [];
        $start = 0;
        while ($start < $len) {
            $chunk = mb_substr($text, $start, $chunk_size);
            $chunks[] = trim($chunk);
            $start += ($chunk_size - $overlap);
        }
        return $chunks;
    }

    public function embed_texts_ollama(array $texts): array {
        $variants_to_try = [];
        $variants_to_try[] = ['type' => 'plain', 'payload' => implode("\n\n", array_values($texts))];
        $variants_to_try[] = ['type' => 'json_array', 'payload' => json_encode(['texts' => array_values($texts)], JSON_UNESCAPED_UNICODE)];
        $lines = array_map(function($t){ return json_encode(['text' => $t], JSON_UNESCAPED_UNICODE); }, array_values($texts));
        $variants_to_try[] = ['type' => 'jsonl', 'payload' => implode("\n", $lines)];
        $instr = "EMBEDDING_REQUEST: Return a JSON object with key \"embeddings\" containing an array of numeric vectors (one per input). No explanation.";
        $variants_to_try[] = ['type' => 'instr_plain', 'payload' => $instr . "\n\n" . implode("\n\n", array_values($texts))];

        $last_out = '';
        foreach ($variants_to_try as $v) {
            $tmp = tempnam(sys_get_temp_dir(), 'ollama_embed_');
            file_put_contents($tmp, $v['payload']);
            $cmds = [
                'ollama embed ' . escapeshellarg($this->ollama_embed_model) . ' < ' . escapeshellarg($tmp) . ' 2>&1',
                'ollama embed --model ' . escapeshellarg($this->ollama_embed_model) . ' < ' . escapeshellarg($tmp) . ' 2>&1',
                'ollama run ' . escapeshellarg($this->ollama_embed_model) . ' < ' . escapeshellarg($tmp) . ' 2>&1',
            ];
            foreach ($cmds as $cmd) {
                $out = shell_exec($cmd);
                if ($out === null) continue;
                $last_out = $out;
                $clean = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/u', '', $out);
                $clean = preg_replace('/^\xEF\xBB\xBF/u', '', $clean);
                $clean = preg_replace('/^```(?:json)?\s*/u', '', $clean);
                $clean = preg_replace('/\s*```$/u', '', $clean);
                $clean = trim($clean);
                $firstBrace = strpos($clean, '{');
                $firstBracket = strpos($clean, '[');
                if ($firstBrace !== false) $clean = substr($clean, $firstBrace);
                elseif ($firstBracket !== false) $clean = substr($clean, $firstBracket);
                $dec = json_decode($clean, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($dec['embeddings']) && is_array($dec['embeddings'])) {
                    @unlink($tmp);
                    return $dec['embeddings'];
                } elseif (json_last_error() === JSON_ERROR_NONE && is_array($dec) && count($dec) > 0 && is_numeric($dec[0] ?? null)) {
                    @unlink($tmp);
                    return [$dec];
                }
                if (preg_match('/"embeddings"\s*:\s*(\[[\s\S]*\])/i', $clean, $m)) {
                    $try = '{"embeddings":' . $m[1] . '}';
                    $dec2 = json_decode($try, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($dec2['embeddings'])) {
                        @unlink($tmp);
                        return $dec2['embeddings'];
                    }
                }
            }
            @unlink($tmp);
        }
        if ($last_out !== '') {
            fwrite(STDERR, "Could not parse embeddings from ollama output. Last raw output:\n" . $last_out . "\n");
        } else {
            fwrite(STDERR, "ollama did not return any output for embedding attempts.\n");
        }
        return [];
    }

    public function embed_texts_local(array $texts): array {
        $out = [];
        foreach ($texts as $t) {
            $t = (string)$t;
            $h = hash('sha256', $t, true);
            $bytes = array_values(unpack('C*', $h));
            $nb = count($bytes);
            if ($nb === 0) $nb = 1;
            $vec = [];
            for ($i = 0; $i < $this->local_embed_dim; $i++) {
                $b = $bytes[$i % $nb];
                $v = ($b / 255.0) * 2.0 - 1.0;
                $vec[] = $v;
            }
            $sum = 0.0;
            foreach ($vec as $v) $sum += $v * $v;
            $norm = sqrt(max($sum, 1e-12));
            for ($i = 0; $i < $this->local_embed_dim; $i++) $vec[$i] /= $norm;
            $out[] = $vec;
        }
        return $out;
    }

    public function insert_documents(): void {
        $mdDir = __DIR__ . DIRECTORY_SEPARATOR . 'files_md';
       
        $files = array_values(array_filter(scandir($mdDir), function($f) use ($mdDir) {
            if (in_array($f, ['.', '..'])) return false;
            $path = $mdDir . DIRECTORY_SEPARATOR . $f;
            return is_file($path) && preg_match('/\.md$/i', $f);
        }));

        foreach ($files as $file) {
            $path = $mdDir . DIRECTORY_SEPARATOR . $file;
            fwrite(STDOUT, "Processing MD: $path\n");
            $text = file_get_contents($path);
            if ($text === false || trim($text) === '') {
                fwrite(STDERR, "Failed to read or empty MD file: $path\n");
                continue;
            }

            $chunks = $this->chunk_text($text, $this->chunk_size, $this->chunk_overlap);
            if (count($chunks) === 0) continue;

            $embeddings = $this->embed_texts_ollama($chunks);
            if (count($embeddings) !== count($chunks)) {
                fwrite(STDOUT, "Ollama embedding failed for $file (got " . count($embeddings) . "); using local fallback.\n");
                $embeddings = $this->embed_texts_local($chunks);
                if (count($embeddings) !== count($chunks)) {
                    fwrite(STDERR, "Local fallback embedding failed for $file\n");
                    continue;
                }
            }

            $base_meta = $this->extract_metadata_from_file($path);

            $docs = [];
            $metas = [];
            foreach ($chunks as $i => $chunk) {
                $docs[] = $chunk;
                $metas[] = array_merge($base_meta, ['chunk_index' => $i]);
            }

            $ok = $this->pg_upsert($embeddings, $docs, $metas);
            if (!$ok) {
                fwrite(STDERR, "Failed to upsert chunks for $file into PG\n");
            } else {
                fwrite(STDOUT, "Upserted " . count($docs) . " chunks from $file into PG\n");
            }
        }
       
    }

    public function answer_question(string $question): void {
        $system = "You are an expert research assistant will analyze, correlate, and extract relevant information from the given context and answer questions asked by the user. Your output should be precise and accurate. Include references to the sources (headers,meta data including date or number ,file names) where the information comes from, and mention any unique codes, IDs, or metadata from the source labels if relevant. Provide the answer in natural language, not JSON also you always answer with question's local language while returning answer.Do not forget to mention the source of the information in your answer.";

        $question_embedding_arr = $this->embed_texts_ollama([$question]);
        if (count($question_embedding_arr) === 0) {
            fwrite(STDOUT, "Ollama failed to embed question; using local fallback.\n");
            $question_embedding_arr = $this->embed_texts_local([$question]);
            if (count($question_embedding_arr) === 0) {
                fwrite(STDERR, "Failed to produce embedding for the question even with local fallback.\n");
                return;
            }
        }
        $question_embedding = $question_embedding_arr[0];

        $retrieved = $this->vector_query($question_embedding, $this->top_k);

        // Extract unique sources from retrieved documents
        $sources = [];
        foreach ($retrieved as $row) {
            $meta = json_decode($row['metadata'], true);
            if (isset($meta['source']) && !in_array($meta['source'], $sources)) {
                $sources[] = $meta['source'];
            }
        }

        // Get all chunks from these sources to ensure connected context
        $full_retrieved = $this->get_all_chunks_from_sources($sources);

        $context_parts = [];
        foreach ($full_retrieved as $row) {
            $meta = json_decode($row['metadata'], true);
            $src = isset($meta['source']) ? $meta['source'] : 'unknown';
            $chunk_idx = isset($meta['chunk_index']) ? ' (chunk ' . $meta['chunk_index'] . ')' : '';
            $extra = '';

            foreach ($meta as $key => $value) {
                $prefix = in_array($key, ['source', 'chunk_index', 'file_id', 'make', 'model']) ? '' : '**';
                $extra .= ' ' . $prefix . ucfirst($key) . ':' . $value;
            }


            if (isset($meta['id'])) $extra .= ' ContentID:' . $meta['id'];
            if (isset($meta['person_id'])) $extra .= ' PersonID:' . $meta['person_id'];
            if (isset($meta['file_id'])) $extra .= ' FileID:' . $meta['file_id'];
            $context_parts[] = "[source=" . $src . $chunk_idx . $extra . "]\n" . $row['content'];
        }

        $context = implode("\n\n---\n\n", $context_parts);

        $result = $this->ask_ollama($system, $context, $question);

        echo $result . "\n";
    }

    public function process_documents_and_answer(string $question): void {
        $this->insert_documents();
        $this->answer_question($question);
    }

    
}
