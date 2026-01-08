<?php

namespace App\Classes\Justice;

use Illuminate\Support\Facades\Log;

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
    private $model;

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
        $this->model = env('OLLAMA_CHAT_MODEL', 'llama3.1');
        $this->pdo = $this->get_pdo();
    }

    private function get_pdo() {
        $dsn = "pgsql:host={$this->pg_host};port={$this->pg_port};dbname={$this->pg_db}";
        $pdo = new \PDO($dsn, $this->pg_user, $this->pg_pass, [
            \PDO::ATTR_PERSISTENT => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
        return $pdo;
    }

    public function ask_ollama(string $system, string $context, string $question, string $model = 'llama3.1'): string {
        $start_time = microtime(true);
        try {
            // Validate inputs to prevent injection
            if (empty($system) || empty($question)) {
                Log::warning('Justice::ask_ollama: Invalid input provided', ['system' => $system, 'question' => $question]);
                return json_encode(['answer' => 'Invalid input', 'answerHtml' => 'Invalid input']);
            }

            $prompt = $system . "\n\nContext:\n" . $context . "\n\nQuestion:\n" . $question;

            // Use HTTP API instead of shell_exec for security
            $ollama_url = env('OLLAMA_URL', 'http://localhost:11434');
            $timeout = intval(env('OLLAMA_TIMEOUT', 60));
            $data = [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false
            ];

            $ch = curl_init($ollama_url . '/api/generate');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // Timeout to prevent hanging

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($response === false || $http_code !== 200) {
                Log::error('Justice::ask_ollama: Ollama API call failed', [
                    'http_code' => $http_code,
                    'curl_error' => $curl_error,
                    'response' => $response,
                    'model' => $model
                ]);
                return json_encode(['answer' => '', 'answerHtml' => '']);
            }

            $result = json_decode($response, true);
            if (!isset($result['response'])) {
                Log::error('Justice::ask_ollama: Invalid response from Ollama', ['response' => $response]);
                return json_encode(['answer' => '', 'answerHtml' => '']);
            }

            $answer = trim($result['response']);

            // Remove ANSI escape codes from the answer
            $answer = preg_replace('/\x1B\[[0-9;?]*[A-Za-z]/u', '', $answer);
            $answer = preg_replace('/\x1B\][0-9]*;.*?(\x07|\x1B\\\\)/u', '', $answer); // OSC sequences
            $answer = preg_replace('/[\x00-\x1F\x7F]/u', '', $answer); // Control characters
            // Remove Braille patterns (U+2800-U+28FF range)
            $answer = preg_replace('/[\x{2800}-\x{28FF}]/u', '', $answer);

            // Create HTML version before normalizing whitespace
            $answerHtml = $this->format_answer_html($answer);

            // Now normalize whitespace for plain text answer
            $answer = preg_replace('/\s+/', ' ', $answer); // Normalize whitespace
            $answer = trim($answer);

            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::info('Justice::ask_ollama: Successful call', ['duration' => $duration, 'model' => $model]);

            return json_encode([
                'answer' => $answer,
                'answerHtml' => $answerHtml
            ]);
        } catch (\Exception $e) {
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::error('Justice::ask_ollama: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration' => $duration
            ]);
            return json_encode(['answer' => 'An error occurred while processing your request.', 'answerHtml' => 'An error occurred while processing your request.']);
        }
    }

    public function pg_upsert(array $embeddings, array $documents, array $metadatas): bool {
        $start_time = microtime(true);
        try {
            $this->pdo->exec("CREATE EXTENSION IF NOT EXISTS vector");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS vector_documents (id SERIAL PRIMARY KEY, content TEXT,origin TEXT,year_code TEXT, embedding VECTOR(" . intval($this->local_embed_dim) . "), metadata JSONB)");
            
            $this->pdo->beginTransaction();

            $batchSize = max(1, intval(env('PG_INSERT_BATCH', 256)));
            $values = [];
            $params = [];
            $count = 0;

            foreach ($embeddings as $i => $emb) {
                if ($emb === null) continue;
                $vec_str = '[' . implode(',', $emb) . ']';
                $meta_json = json_encode($metadatas[$i]);

                $values[] = '(?, ?::vector, ?, ?, ?)';
                $params[] = $documents[$i];
                $params[] = $vec_str;
                $params[] = $meta_json;
                $params[] = $metadatas[$i]['origin'] ?? null;
                $params[] = $metadatas[$i]['year'] ?? null;
                $count++;

                if ($count >= $batchSize) {
                    $sql = "INSERT INTO vector_documents (content, embedding, metadata,origin,year_code) VALUES " . implode(',', $values);
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    // reset
                    $values = [];
                    $params = [];
                    $count = 0;
                }
            }

            if ($count > 0) {
                $sql = "INSERT INTO vector_documents (content, embedding, metadata,origin,year_code) VALUES " . implode(',', $values);
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }

            $this->pdo->commit();
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::info('Justice::pg_upsert: Successful upsert', [
                'total_documents' => count($documents),
                'duration' => $duration
            ]);
            return true;
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::error('Justice::pg_upsert: Failed to upsert', [
                'message' => $e->getMessage(),
                'total_documents' => count($documents),
                'duration' => $duration
            ]);
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
        $start_time = microtime(true);
        try {
            $vec_str = '[' . implode(',', $query_embedding) . ']';
            if ($top_k > 0) {
                $stmt = $this->pdo->prepare("SELECT content, metadata FROM vector_documents ORDER BY embedding <=> ?::vector LIMIT ?");
                $stmt->execute([$vec_str, $top_k]);
            } else {
                $stmt = $this->pdo->prepare("SELECT content, metadata FROM vector_documents ORDER BY embedding <=> ?::vector");
                $stmt->execute([$vec_str]);
            }
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::info('Justice::vector_query: Successful query', [
                'top_k' => $top_k,
                'results_count' => count($results),
                'duration' => $duration
            ]);
            return $results;
        } catch (\Exception $e) {
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::error('Justice::vector_query: Query failed', [
                'message' => $e->getMessage(),
                'top_k' => $top_k,
                'duration' => $duration
            ]);
            return [];
        }
    }

    public function extract_metadata_from_file(string $file_path): array {
        // Security: Prevent path traversal
        $real_path = realpath($file_path);
        $allowed_dir = realpath(public_path('aidocuments'));
        if ($real_path === false || strpos($real_path, $allowed_dir) !== 0) {
            return ['source' => basename($file_path)];
        }

        $metadata = ['source' => basename($file_path)];

        

        // Extract from content: custom meta headers starting with **
        $content = file_get_contents($real_path);
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
        $start_time = microtime(true);
        try {
            if (empty($texts)) return [];

            // Use HTTP API with multi-curl for batching
            $ollama_url = env('OLLAMA_URL', 'http://localhost:11434');
            $timeout = intval(env('OLLAMA_TIMEOUT', 60));
            $embeddings = [];
            $mh = curl_multi_init();
            $handles = [];

            foreach ($texts as $i => $text) {
                $data = [
                    'model' => $this->ollama_embed_model,
                    'prompt' => $text
                ];

                $ch = curl_init($ollama_url . '/api/embeddings');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

                curl_multi_add_handle($mh, $ch);
                $handles[$i] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);

            $errors = 0;
            foreach ($handles as $i => $ch) {
                $response = curl_multi_getcontent($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                if ($response === false || $http_code !== 200) {
                    Log::error('Justice::embed_texts_ollama: Embedding call failed', [
                        'index' => $i,
                        'http_code' => $http_code,
                        'curl_error' => $curl_error,
                        'response' => $response
                    ]);
                    $errors++;
                    $embeddings[$i] = null;
                    continue;
                }

                $result = json_decode($response, true);
                if (isset($result['embedding']) && is_array($result['embedding'])) {
                    $embeddings[$i] = $result['embedding'];
                } else {
                    Log::error('Justice::embed_texts_ollama: Invalid embedding response', [
                        'index' => $i,
                        'response' => $response
                    ]);
                    $errors++;
                    $embeddings[$i] = null;
                }
            }

            curl_multi_close($mh);
            ksort($embeddings); // Ensure order matches input
            $result = array_values($embeddings);

            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::info('Justice::embed_texts_ollama: Completed', [
                'total_texts' => count($texts),
                'errors' => $errors,
                'duration' => $duration
            ]);

            return $result;
        } catch (\Exception $e) {
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::error('Justice::embed_texts_ollama: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration' => $duration
            ]);
            return [];
        }
    }

    private function get_embeddings_with_fallback(array $texts): array {
        $embeddings = $this->embed_texts_ollama($texts);
        if (count($embeddings) !== count($texts)) {
            return [ ];
        }
        return $embeddings;
    }

    public function insert_documents(): void {
        $start_time = microtime(true);
        try {
            $mdDir = public_path('aidocuments');
           
            $files = array_values(array_filter(scandir($mdDir), function($f) use ($mdDir) {
                if (in_array($f, ['.', '..'])) return false;
                $path = $mdDir . DIRECTORY_SEPARATOR . $f;
                return is_file($path) && preg_match('/\.md$/i', $f);
            }));

            $total_files = count($files);
            $processed_files = 0;
            $skipped_files = 0;
            $failed_files = 0;

            foreach ($files as $file) {
                $path = $mdDir . DIRECTORY_SEPARATOR . $file;
                fwrite(STDOUT, "Processing MD: $path\n");

                // Performance: Skip files larger than 10MB to prevent memory issues
                $maxFileSize = 10 * 1024 * 1024; // 10MB
                if (filesize($path) > $maxFileSize) {
                    fwrite(STDERR, "Skipping file $file: too large (>10MB)\n");
                    Log::warning('Justice::insert_documents: File too large', ['file' => $file, 'size' => filesize($path)]);
                    $skipped_files++;
                    continue;
                }

                $text = file_get_contents($path);
                if ($text === false || trim($text) === '') {
                    fwrite(STDERR, "Failed to read or empty MD file: $path\n");
                    Log::error('Justice::insert_documents: Failed to read file', ['file' => $file]);
                    $failed_files++;
                    continue;
                }

                $chunks = $this->chunk_text($text, $this->chunk_size, $this->chunk_overlap);
                if (count($chunks) === 0) {
                    Log::warning('Justice::insert_documents: No chunks generated', ['file' => $file]);
                    $skipped_files++;
                    continue;
                }
                // Use cached, batched embeddings helper to avoid redundant calls
                $embeddings = $this->get_or_compute_embeddings($chunks);
                // If any embedding is missing, skip this file (helper already falls back to local embedding when possible)
                if (count(array_filter($embeddings)) !== count($chunks)) {
                    fwrite(STDERR, "Some embeddings missing for $file; skipping.\n");
                    Log::error('Justice::insert_documents: Missing embeddings', ['file' => $file, 'chunks' => count($chunks), 'embeddings' => count(array_filter($embeddings))]);
                    $failed_files++;
                    continue;
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
                    Log::error('Justice::insert_documents: Upsert failed', ['file' => $file, 'chunks' => count($docs)]);
                    $failed_files++;
                } else {
                    fwrite(STDOUT, "Upserted " . count($docs) . " chunks from $file into PG\n");
                    $processed_files++;
                }
            }
           
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::info('Justice::insert_documents: Completed processing', [
                'total_files' => $total_files,
                'processed_files' => $processed_files,
                'skipped_files' => $skipped_files,
                'failed_files' => $failed_files,
                'duration' => $duration
            ]);
        } catch (\Exception $e) {
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::error('Justice::insert_documents: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration' => $duration
            ]);
            throw $e; // Re-throw to allow caller to handle
        }
    }

    public function get_conversation_history(string $session_id, int $limit = 10): array {
        $start_time = microtime(true);
        try {
            $stmt = $this->pdo->prepare("SELECT role, content FROM conversations WHERE session_id = ? ORDER BY created_at ASC LIMIT ?");
            $stmt->execute([$session_id, $limit]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::info('Justice::get_conversation_history: Successful retrieval', [
                'session_id' => $session_id,
                'limit' => $limit,
                'results_count' => count($results),
                'duration' => $duration
            ]);
            return $results;
        } catch (\Exception $e) {
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::error('Justice::get_conversation_history: Failed to load history', [
                'message' => $e->getMessage(),
                'session_id' => $session_id,
                'limit' => $limit,
                'duration' => $duration
            ]);
            return [];
        }
    }

    private function build_system_prompt(): string {
        $language_instruction = "IMPORTANT: Detect the language of the question and respond in the same language. If the question is Turkish, reply exclusively in natural, idiomatic Turkish. Do NOT include fragments, phrases, or characters from other languages (for example Chinese, English insertions, or emojis). If you cannot express something clearly in Turkish, ask a clarifying question in Turkish.";

        return "You are an expert research assistant. Maintain conversation context and answer based on the conversation history and the provided document context. If asked question is not relevant with the document context just say 'question is not relevant'.\n\n" . $language_instruction . "\n\n" .
            "NEVER mix languages in a single response: respond solely in the detected language for the user query.\n\n" .
            "IMPORTANT : When applicable, start the answer with the informations contains origin,id,Ülke,Karar tarihi,Karar numarası. After that info pass to new line, provide the answer.\n\n" .
            "IMPORTANT : At the very end of your answer pass to next line and always include **ID: [id_value]** where [id_value] is the exact ID from the context metadata.\n" .
            "Keep answers concise, user-friendly, and directly responsive to the question. Do not output JSON or any diagnostic commentary. If the user input is only a greeting, respond politely in the detected language without the header.";
    }

    private function enhance_question_with_history(string $question, ?string $session_id): string {
        if (!$session_id) return $question;

        $history = $this->get_conversation_history($session_id, 5);
        if (empty($history)) return $question;

        $conversation_context = "\n\nPrevious conversation:\n";
        foreach ($history as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            $content = strlen($msg['content']) > 500 ? substr($msg['content'], 0, 500) . '...' : $msg['content'];
            $conversation_context .= "$role: " . $content . "\n";
        }
        $conversation_context .= "\n";

        $last_assistant_msg = '';
        foreach (array_reverse($history) as $msg) {
            if ($msg['role'] === 'assistant') {
                $last_assistant_msg = substr($msg['content'], 0, 200);
                break;
            }
        }
        if ($last_assistant_msg) {
            return "Continuing our conversation about: " . $last_assistant_msg . ". Now: " . $question;
        }
        return $question;
    }

    private function prepare_context_and_meta(array $retrieved): array {
        $sources = [];
        foreach ($retrieved as $row) {
            $meta = json_decode($row['metadata'], true);
            if (isset($meta['source']) && !in_array($meta['source'], $sources)) {
                $sources[] = $meta['source'];
            }
        }

        $full_retrieved = $this->get_all_chunks_from_sources($sources);

        $meta = [];
        foreach ($full_retrieved as $row) {
            $row_meta = json_decode($row['metadata'], true);
            $id = $row_meta['id'] ?? null;
            if ($id && !isset($meta[$id])) {
                $meta[$id] = $row_meta;
            }
        }
        $meta = array_values($meta);

        $context_parts = [];
        foreach ($full_retrieved as $row) {
            $meta_data = json_decode($row['metadata'], true);
            $src = $meta_data['source'] ?? 'unknown';
            $chunk_idx = isset($meta_data['chunk_index']) ? ' (chunk ' . $meta_data['chunk_index'] . ')' : '';
            $extra = '';

            foreach ($meta_data as $key => $value) {
                $prefix = in_array($key, ['source', 'chunk_index', 'file_id', 'make', 'model']) ? '' : '**';
                $extra .= ' ' . $prefix . ucfirst($key) . ':' . $value;
            }

            if (isset($meta_data['id'])) $extra .= ' ContentID:' . $meta_data['id'];
            if (isset($meta_data['person_id'])) $extra .= ' PersonID:' . $meta_data['person_id'];
            if (isset($meta_data['file_id'])) $extra .= ' FileID:' . $meta_data['file_id'];
            $context_parts[] = "[source=" . $src . $chunk_idx . $extra . "]\n" . $row['content'];
        }

        $context = implode("\n\n---\n\n", $context_parts);

        return ['context' => $context, 'meta' => $meta];
    }

    public function answer_question(string $question, string $session_id = null) {
        $start_time = microtime(true);
        try {
            $system = $this->build_system_prompt();
            $enhanced_question = $this->enhance_question_with_history($question, $session_id);

            $question_embedding_arr = $this->get_embeddings_with_fallback([$enhanced_question]);
            if (empty($question_embedding_arr)) {
                Log::warning('Justice::answer_question: Failed to get question embedding', ['question' => $question, 'session_id' => $session_id]);
                return [
                    'answer' => 'Api is not available right now..',
                    'answerHtml' => 'Api is not available right now..',
                    'meta' => []
                ];
            }
            $question_embedding = $question_embedding_arr[0];

            $retrieved = $this->vector_query($question_embedding, $this->top_k);
            $context_and_meta = $this->prepare_context_and_meta($retrieved);
            $context = $context_and_meta['context'];
            $meta = $context_and_meta['meta'];

            $result = $this->ask_ollama($system, $context, $enhanced_question, $this->model);

            $response = json_decode($result, true);
            if ($response && isset($response['answer'])) {
                $response['meta'] = $this->filter_meta_by_id($meta, $response['answer']);
                $end_time = microtime(true);
                $duration = $end_time - $start_time;
                Log::info('Justice::answer_question: Successful response', [
                    'question_length' => strlen($question),
                    'session_id' => $session_id,
                    'retrieved_count' => count($retrieved),
                    'meta_count' => count($meta),
                    'filtered_meta_count' => count($response['meta']),
                    'duration' => $duration
                ]);
                return $response;
            } else {
                Log::error('Justice::answer_question: Invalid response from ask_ollama', ['result' => $result]);
                return [
                    'answer' => 'Error: Invalid response format',
                    'answerHtml' => 'Error: Invalid response format',
                    'meta' => []
                ];
            }
        } catch (\Exception $e) {
            $end_time = microtime(true);
            $duration = $end_time - $start_time;
            Log::error('Justice::answer_question: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'question' => $question,
                'session_id' => $session_id,
                'duration' => $duration
            ]);
            return [
                'answer' => 'An error occurred while processing your request.',
                'answerHtml' => 'An error occurred while processing your request.',
                'meta' => []
            ];
        }
    }

    public function process_documents_and_answer(string $question): void {
        $this->insert_documents();
        $this->answer_question($question);
    }

    private function get_or_compute_embeddings(array $texts): array {
        $embeddings = [];
        $to_fetch = [];
        $index_map = [];

        foreach ($texts as $i => $t) {
            
            $index_map[] = $i;
            $to_fetch[] = $t;
            
        }

        if (!empty($to_fetch)) {
            $batch_size = max(1, intval(env('OLLAMA_EMBED_BATCH', 64)));
            $total = count($to_fetch);
            for ($s = 0; $s < $total; $s += $batch_size) {
                $slice = array_slice($to_fetch, $s, $batch_size);
                $res = $this->get_embeddings_with_fallback($slice);

                foreach ($res as $j => $emb) {
                    $orig_index = $index_map[$s + $j];
                    // Normalize embedding length to configured local dimension
                    if (is_array($emb)) {
                        $len = count($emb);
                        if ($len !== $this->local_embed_dim) {
                            if ($len > $this->local_embed_dim) {
                                $emb = array_slice($emb, 0, $this->local_embed_dim);
                            } else {
                                $emb = array_merge($emb, array_fill(0, $this->local_embed_dim - $len, 0.0));
                            }
                        }
                        // re-normalize to unit length
                        $sum = 0.0;
                        foreach ($emb as $v) $sum += $v * $v;
                        $norm = sqrt(max($sum, 1e-12));
                        for ($k = 0; $k < $this->local_embed_dim; $k++) $emb[$k] = $emb[$k] / $norm;
                    }
                    $embeddings[$orig_index] = $emb;
                    
                }
            }
        }

        ksort($embeddings);
        // ensure returned array matches original order and count
        $out = [];
        foreach (range(0, count($texts) - 1) as $i) {
            $out[] = $embeddings[$i] ?? null;
        }
        return $out;
    }

    private function filter_meta_by_id(array $meta, string $answer): array {
        $referenced_id = null;
        if (preg_match('/\*\*ID:\s*([^\*]+)\*\*/u', $answer, $match)) {
            $referenced_id = trim($match[1]);
        }
        $filtered_meta = [];
        foreach ($meta as $m) {
            if (($m['id'] ?? '') === $referenced_id) {
                $filtered_meta[] = $m;
            }
        }
        return $filtered_meta;
    }

    private function format_answer_html(string $answer): string {
        $answerHtml = htmlspecialchars($answer, ENT_QUOTES, 'UTF-8');
        $answerHtml = nl2br($answerHtml);
        $answerHtml = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $answerHtml);
        $answerHtml = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $answerHtml);
        $answerHtml = preg_replace('/(Ülke\s*:)/', '<br>$1', $answerHtml);
        $answerHtml = preg_replace('/(Yıl\s*:)/', '<br>$1', $answerHtml);
        $answerHtml = preg_replace('/(Karar\s+Numarası\s*:)/', '<br>$1', $answerHtml);
        $answerHtml = preg_replace('/(Karar\s+Tarihi\s*:)/', '<br>$1', $answerHtml);
        $answerHtml = preg_replace('/(Kaynaklar?:)/', '<br><br>$1', $answerHtml);
        $answerHtml = preg_replace('/(\d+\.\s)/', '<br>$1', $answerHtml);
        $answerHtml = preg_replace('/(\*\s|\-\s)/', '<br>$1', $answerHtml);
        return '<div class="ai-response">' . $answerHtml . '</div>';
    }

    
}
