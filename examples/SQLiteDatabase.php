<?php

class SQLiteDatabase {
    private $db;
    private $dbPath;

    public function __construct(string $dbPath) {
        $this->dbPath = $dbPath;
        $this->connect();
        $this->initializeSchema();
        
        if (!$this->verifySchema()) {
            throw new \RuntimeException("Database schema verification failed. Required tables or columns are missing.");
        }
        
    }

    public function getPDO(): PDO {
        return $this->db;
    }

    private function connect(): void {
        try {
            $this->db = new PDO("sqlite:{$this->dbPath}");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \RuntimeException("Failed to connect to SQLite database: " . $e->getMessage());
        }
    }

    private function initializeSchema(): void {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS benchmark_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                model_id TEXT NOT NULL,
                question_id INTEGER NOT NULL,
                attempt_id INTEGER NOT NULL,
                response TEXT NOT NULL,
                correct INTEGER NOT NULL,
                reasoning TEXT,
                response_time REAL,
                question_no INTEGER,
                verbose TEXT,
                actual_question TEXT,
                possible_answers TEXT,
                correct_answers TEXT,
                clean_response TEXT,
                category TEXT,
                subcategory TEXT,
                prompt_tokens INTEGER,
                prompt_time REAL,
                predicted_tokens INTEGER,
                predicted_time REAL,
                prompt_eval_per_second REAL,
                tokens_per_second REAL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(model_id, question_id, attempt_id)
            );
            
            CREATE TABLE IF NOT EXISTS model_stats (
                model_id TEXT PRIMARY KEY,
                total_questions INTEGER DEFAULT 0,
                correct_answers INTEGER DEFAULT 0,
                incorrect_answers INTEGER DEFAULT 0,
                avg_response_time REAL DEFAULT 0,
                avg_prompt_time REAL DEFAULT 0,
                avg_predicted_time REAL DEFAULT 0,
                avg_tokens_per_second REAL DEFAULT 0,
                avg_prompt_eval_per_second REAL DEFAULT 0,
                percentage_correct REAL DEFAULT 0,
                avg_prompt_tokens REAL DEFAULT 0,
                avg_predicted_tokens REAL DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS valid_questions (
	            qid	TEXT
            )
        ');
    }

    public function beginTransaction(): void {
        $this->db->beginTransaction();
    }

    public function commit(): void {
        $this->db->commit();
    }

    public function rollback(): void {
        $this->db->rollBack();
    }

    public function saveBenchmarkRun(
        string $modelId,
        int $questionId,
        int $attemptId,
        string $response,
        bool $correct,
        ?string $reasoning,
        float $responseTime,
        int $questionNo,
        ?string $verbose,
        ?int $promptTokens = null,
        ?float $promptTime = null,
        ?int $predictedTokens = null,
        ?float $predictedTime = null,
        ?float $tokensPerSecond = null,
        ?string $actualQuestion = null,
        ?string $possibleAnswers = null,
        ?string $correctAnswers = null,
        string $cleanResponse,
        ?float $predictedPerSecond = null,
        ?string $category = null,
        ?string $subcategory = null,
    ): void {
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO benchmark_runs
            (model_id, question_id, attempt_id, response, correct, reasoning, response_time,
             question_no, verbose, prompt_tokens, prompt_time, predicted_tokens,
             predicted_time, tokens_per_second, actual_question, possible_answers, correct_answers, clean_response, prompt_eval_per_second,
             category, subcategory)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $modelId,
            $questionId,
            $attemptId,
            $response,
            (int)$correct,
            $reasoning,
            $responseTime,
            $questionNo,
            $verbose,
            $promptTokens,
            $promptTime,
            $predictedTokens,
            $predictedTime,
            $tokensPerSecond,
            $actualQuestion,
            $possibleAnswers,
            $correctAnswers,
            $cleanResponse,
            $predictedPerSecond,
            $category,
            $subcategory
        ]);
        
        // Update aggregated stats
        $this->updateModelStats($modelId, $correct);
    }

    public function updateModelStats(string $modelId): void {
        // Calculate new stats
        $stats = $this->calculateModelStats($modelId);
        
        
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO model_stats
            (model_id, total_questions, correct_answers, incorrect_answers,
             avg_response_time, avg_prompt_time, avg_predicted_time,
             avg_tokens_per_second, avg_prompt_eval_per_second, percentage_correct,
             avg_prompt_tokens, avg_predicted_tokens)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $modelId,
            $stats['total_questions'],
            $stats['correct_answers'],
            $stats['incorrect_answers'],
            $stats['avg_response_time'],
            $stats['avg_prompt_time'],
            $stats['avg_predicted_time'],
            $stats['avg_tokens_per_second'],
            $stats['avg_prompt_eval_per_second'],
            $stats['percentage_correct'],
            $stats['avg_prompt_tokens'],
            $stats['avg_predicted_tokens']
        ]);
    }

    public function calculateModelStats(string $modelId): array {
        
        // Get basic counts
        $stmt = $this->db->prepare('
            SELECT
                COUNT(*) as total_questions,
                SUM(correct) as correct_answers,
                COUNT(*) - SUM(correct) as incorrect_answers
            FROM benchmark_runs
            WHERE model_id = ?
        ');
        $stmt->execute([$modelId]);
        $counts = $stmt->fetch();
        
        // Get timing averages with validation
        $stmt = $this->db->prepare('
            SELECT
                AVG(response_time) as avg_response_time,
                AVG(CASE WHEN prompt_time > 0 THEN prompt_time ELSE NULL END) as avg_prompt_time,
                AVG(CASE WHEN predicted_time > 0 THEN predicted_time ELSE NULL END) as avg_predicted_time,
                AVG(CASE WHEN tokens_per_second > 0 THEN tokens_per_second ELSE NULL END) as avg_tokens_per_second,
                AVG(CASE WHEN prompt_eval_per_second > 0 THEN prompt_eval_per_second ELSE NULL END) as avg_prompt_eval_per_second,
                AVG(CASE WHEN prompt_tokens > 0 THEN prompt_tokens ELSE NULL END) as avg_prompt_tokens,
                AVG(CASE WHEN predicted_tokens > 0 THEN predicted_tokens ELSE NULL END) as avg_predicted_tokens
            FROM benchmark_runs
            WHERE model_id = ?
        ');
        $stmt->execute([$modelId]);
        $timings = $stmt->fetch();
        
        $stats = [
            'total_questions' => (int)$counts['total_questions'],
            'correct_answers' => (int)$counts['correct_answers'],
            'incorrect_answers' => (int)$counts['incorrect_answers'],
            'avg_response_time' => (float)$timings['avg_response_time'],
            'avg_prompt_time' => (float)$timings['avg_prompt_time'],
            'avg_predicted_time' => (float)$timings['avg_predicted_time'],
            'avg_tokens_per_second' => (float)$timings['avg_tokens_per_second'],
            'avg_prompt_eval_per_second' => (float)$timings['avg_prompt_eval_per_second'],
            'avg_prompt_tokens' => (float)$timings['avg_prompt_tokens'],
            'avg_predicted_tokens' => (float)$timings['avg_predicted_tokens'],
            'percentage_correct' => $counts['total_questions'] > 0
                ? round(($counts['correct_answers'] / $counts['total_questions']) * 100, 2)
                : 0,
        ];


        return $stats;
    }

    public function getModelStats(string $modelId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM model_stats WHERE model_id = ?');
        $stmt->execute([$modelId]);
        return $stmt->fetch() ?: null;
    }

    public function getAllModelStats(): array {
        $stmt = $this->db->prepare('
            SELECT * FROM model_stats 
            ORDER BY percentage_correct DESC, avg_response_time ASC
        ');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getBenchmarkRuns(string $modelId, int $questionId): array {
        $stmt = $this->db->prepare('
            SELECT * FROM benchmark_runs 
            WHERE model_id = ? AND question_id = ?
            ORDER BY attempt_id ASC
        ');
        $stmt->execute([$modelId, $questionId]);
        return $stmt->fetchAll();
    }



    public function resetBenchmarkData(): void {
        unlink('benchmark.db');
    }

    public function getDistinctModels(): array {
        $stmt = $this->db->prepare('SELECT DISTINCT model_id FROM benchmark_runs');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDistinctQuestionsForModel(string $modelId): array {
        $stmt = $this->db->prepare('SELECT DISTINCT question_id FROM benchmark_runs WHERE model_id = ?');
        $stmt->execute([$modelId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function verifySchema(): bool {
        $requiredTables = [
            'benchmark_runs' => [
                'model_id', 'question_id', 'attempt_id', 'response', 'correct',
                'response_time', 'prompt_tokens', 'prompt_time', 'predicted_tokens',
                'predicted_time', 'tokens_per_second', 'prompt_eval_per_second'
            ],
            'model_stats' => [
                'model_id', 'avg_prompt_eval_per_second', 'avg_tokens_per_second',
                'avg_response_time', 'avg_prompt_time', 'avg_predicted_time'
            ]
        ];

        foreach ($requiredTables as $table => $columns) {
            try {
                // Check table exists
                $stmt = $this->db->prepare("SELECT 1 FROM $table LIMIT 1");
                $stmt->execute();

                // Check columns exist
                $stmt = $this->db->prepare("PRAGMA table_info($table)");
                $stmt->execute();
                $existingColumns = array_column($stmt->fetchAll(), 'name');

                $missingColumns = array_diff($columns, $existingColumns);
                if (!empty($missingColumns)) {
                    return false;
                }
            } catch (PDOException $e) {
                return false;
            }
        }

        return true;
    }
}