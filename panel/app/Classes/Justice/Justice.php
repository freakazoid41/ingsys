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
        $pdo = new \PDO($dsn, $this->pg_user, $this->pg_pass);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function get_cached_embedding(string $text): ?array {
        $cache_key = 'embedding_' . md5($text);
        $stmt = $this->pdo->prepare("SELECT embedding FROM embedding_cache WHERE cache_key = ? AND created_at > NOW() - INTERVAL '24 hours'");
        $stmt->execute([$cache_key]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result) {
            return json_decode($result['embedding'], true);
        }
        return null;
    }

    private function cache_embedding(string $text, array $embedding): void {
        $cache_key = 'embedding_' . md5($text);
        $embedding_json = json_encode($embedding);
        $stmt = $this->pdo->prepare("INSERT INTO embedding_cache (cache_key, text_content, embedding, created_at) VALUES (?, ?, ?, NOW()) ON CONFLICT (cache_key) DO UPDATE SET embedding = EXCLUDED.embedding, created_at = NOW()");
        $stmt->execute([$cache_key, $text, $embedding_json]);
    }

    public function ask_ollama(string $system, string $context, string $question, string $model = 'llama3.1:8b'): string {
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
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
        // Use the run command with nomic-embed-text model
        $payload = 'EMBEDDING_REQUEST: Return a JSON object with key "embeddings" containing an array of numeric vectors (one per input). No explanation.' . "\n\n" . json_encode(['texts' => array_values($texts)], JSON_UNESCAPED_UNICODE);
        $tmp = tempnam(sys_get_temp_dir(), 'ollama_embed_');
        file_put_contents($tmp, $payload);

        $cmd = 'ollama run ' . escapeshellarg($this->ollama_embed_model) . ' < ' . escapeshellarg($tmp) . ' 2>&1';
        $out = shell_exec($cmd);

        if ($out !== null) {
            $clean = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/u', '', $out);
            $clean = trim($clean);

            // Try to parse as JSON first
            $dec = json_decode($clean, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($dec['embeddings']) && is_array($dec['embeddings'])) {
                @unlink($tmp);
                return $dec['embeddings'];
            }

            // If not JSON, try to parse as direct array output
            if (preg_match('/^\[[\s\S]*\]$/', $clean)) {
                $dec = json_decode($clean, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                    @unlink($tmp);
                    return [$dec]; // Single embedding
                }
            }

            // Try to extract array from mixed output
            if (preg_match('/\[[\d\.\-\,\s]+\]/', $clean, $matches)) {
                $dec = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                    @unlink($tmp);
                    return [$dec];
                }
            }
        }

        @unlink($tmp);
        return []; // Return empty to trigger fallback
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

    public function save_message(string $session_id, string $role, string $content): bool {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO conversations (session_id, role, content, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            return $stmt->execute([$session_id, $role, $content]);
        } catch (Exception $e) {
            fwrite(STDERR, "Failed to save message: " . $e->getMessage() . "\n");
            return false;
        }
    }

    public function get_conversation_history(string $session_id, int $limit = 10): array {
        try {
            $stmt = $this->pdo->prepare("SELECT role, content FROM conversations WHERE session_id = ? ORDER BY created_at ASC LIMIT ?");
            $stmt->execute([$session_id, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            fwrite(STDERR, "Failed to load conversation history: " . $e->getMessage() . "\n");
            return [];
        }
    }

    public function reset_conversation(string $session_id): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM conversations WHERE session_id = ?");
            return $stmt->execute([$session_id]);
        } catch (Exception $e) {
            fwrite(STDERR, "Failed to reset conversation: " . $e->getMessage() . "\n");
            return false;
        }
    }

    public function insert_documents(): void {
        $mdDir = public_path('aidocuments');
       
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

    public function answer_question(string $question, string $session_id = null): void {
        // Detect question language and enforce response language
        $language_instruction = " IMPORTANT : detect the language of the question and respond in the same language.";

        $system = "You are an expert research assistant engaged in a conversation with a user. You must maintain context from the previous conversation and answer questions based on both the conversation history and the provided document context.

            $language_instruction

            IMPORTANT: When answering follow-up questions, reference and build upon what was discussed previously in the conversation. Do not ignore the conversation history.

            IMPORTANT: For each answer, you must start giving information with origin,id,Ülke,Karar tarihi, Karar numarası then Include references to the sources (headers,karar numarası , ülke,karar tarihi,meta data including date or number ,file names,origin,date) where the information comes from, and mention any unique codes, IDs,origin,date, or metadata from the source labels if relevant.
        Provide the answer in natural language, not JSON. Do not forget to mention the source of the information in your answer.";

        // Load conversation history if session_id is provided (limit to last 5 messages for performance)
        $conversation_context = "";
        $enhanced_question = $question;
        if ($session_id) {
            $history = $this->get_conversation_history($session_id, 5); // Limit to 5 recent messages
            if (!empty($history)) {
                $conversation_context = "\n\nPrevious conversation:\n";
                foreach ($history as $msg) {
                    $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
                    // Truncate long messages to keep context manageable
                    $content = strlen($msg['content']) > 500 ? substr($msg['content'], 0, 500) . '...' : $msg['content'];
                    $conversation_context .= "$role: " . $content . "\n";
                }
                $conversation_context .= "\n";

                // Create a more concise enhanced question
                $last_assistant_msg = '';
                foreach (array_reverse($history) as $msg) {
                    if ($msg['role'] === 'assistant') {
                        $last_assistant_msg = substr($msg['content'], 0, 200);
                        break;
                    }
                }
                if ($last_assistant_msg) {
                    $enhanced_question = "Continuing our conversation about: " . $last_assistant_msg . ". Now: " . $question;
                }
            }
        }

        $question_embedding_arr = $this->get_cached_embedding($question);
        if ($question_embedding_arr === null) {
            $question_embedding_arr = $this->embed_texts_ollama([$question]);
            if (count($question_embedding_arr) === 0) {
                fwrite(STDOUT, "Ollama failed to embed question; using local fallback.\n");
                $question_embedding_arr = $this->embed_texts_local([$question]);
                if (count($question_embedding_arr) === 0) {
                    fwrite(STDERR, "Failed to produce embedding for the question even with local fallback.\n");
                    return;
                }
            }
            $this->cache_embedding($question, $question_embedding_arr[0]);
        } else {
            $question_embedding_arr = [$question_embedding_arr];
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

        $full_context = $conversation_context . $context;

        $result = $this->ask_ollama($system, $full_context, $enhanced_question);

        echo $result . "\n";

        // Save to conversation history if session_id is provided
        if ($session_id) {
            $this->save_message($session_id, 'user', $question);
            $this->save_message($session_id, 'assistant', $result);
        }
    }

    public function process_documents_and_answer(string $question, string $session_id = null): void {
        $this->insert_documents();
        $this->answer_question($question, $session_id);
    }

    
}
