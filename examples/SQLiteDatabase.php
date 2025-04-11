<?php

class SQLiteDatabase {
    private $db;
    private $dbPath;

    public function __construct(string $dbPath) {
        $this->dbPath = $dbPath;
        $this->connect();
        $this->initializeSchema();
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
                prompt_tokens INTEGER,
                prompt_time REAL,
                predicted_tokens INTEGER,
                predicted_time REAL,
                tokens_per_second REAL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(model_id, question_id, attempt_id)
            );
            
            CREATE TABLE IF NOT EXISTS benchmark_state (
                model_id TEXT PRIMARY KEY,
                results TEXT,
                correct_count INTEGER,
                incorrect_count INTEGER,
                current_question INTEGER,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
                percentage_correct REAL DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
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
    ): void {
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO benchmark_runs 
            (model_id, question_id, attempt_id, response, correct, reasoning, response_time,
             question_no, verbose, prompt_tokens, prompt_time, predicted_tokens,
             predicted_time, tokens_per_second, actual_question,possible_answers,correct_answers)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?)
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
            $correctAnswers
        ]);
        
        // Update aggregated stats
        $this->updateModelStats($modelId, $correct);
    }

    public function updateModelStats(string $modelId, bool $isCorrect): void {
        // Calculate new stats
        $stats = $this->calculateModelStats($modelId);
        
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO model_stats 
            (model_id, total_questions, correct_answers, incorrect_answers,
             avg_response_time, avg_prompt_time, avg_predicted_time,
             avg_tokens_per_second, percentage_correct)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $stats['percentage_correct']
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
        
        // Get timing averages
        $stmt = $this->db->prepare('
            SELECT
                AVG(response_time) as avg_response_time,
                AVG(prompt_time) as avg_prompt_time,
                AVG(predicted_time) as avg_predicted_time,
                AVG(tokens_per_second) as avg_tokens_per_second
            FROM benchmark_runs
            WHERE model_id = ?
        ');
        $stmt->execute([$modelId]);
        $timings = $stmt->fetch();
        
        return [
            'total_questions' => (int)$counts['total_questions'],
            'correct_answers' => (int)$counts['correct_answers'],
            'incorrect_answers' => (int)$counts['incorrect_answers'],
            'avg_response_time' => (float)$timings['avg_response_time'],
            'avg_prompt_time' => (float)$timings['avg_prompt_time'],
            'avg_predicted_time' => (float)$timings['avg_predicted_time'],
            'avg_tokens_per_second' => (float)$timings['avg_tokens_per_second'],
            'percentage_correct' => $counts['total_questions'] > 0
                ? round(($counts['correct_answers'] / $counts['total_questions']) * 100, 2)
                : 0,
        ];
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

    public function saveBenchmarkState(
        string $modelId,
        array $results,
        int $correctCount,
        int $incorrectCount,
        int $currentQuestion
    ): void {
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO benchmark_state 
            (model_id, results, correct_count, incorrect_count, current_question)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $modelId,
            json_encode($results),
            $correctCount,
            $incorrectCount,
            $currentQuestion
        ]);
    }

    public function getBenchmarkState(string $modelId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM benchmark_state WHERE model_id = ?');
        $stmt->execute([$modelId]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return null;
        }
        
        return [
            'results' => json_decode($result['results'], true),
            'correctCount' => $result['correct_count'],
            'incorrectCount' => $result['incorrect_count'],
            'currentQuestion' => $result['current_question']
        ];
    }

    public function resetBenchmarkData(): void {
        $this->db->exec('DELETE FROM benchmark_runs');
        $this->db->exec('DELETE FROM benchmark_state');
        $this->db->exec('DELETE FROM model_stats');
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
}