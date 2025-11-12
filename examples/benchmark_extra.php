#!/usr/bin/php
<?php

require "SQLiteDatabase.php";

// Check for help request
if (in_array('-h', $argv) || in_array('--help', $argv)) {
    echo <<<HELP
benchmark_extra.php - Extended Multi-Model Performance Benchmark

Extended version with additional testing capabilities and documentation

DESCRIPTION:
This script provides a comprehensive benchmarking framework for LLM models
to evaluate their accuracy and performance across multiple questions.

KEY FEATURES:
- Model Filtering: Supports pattern-based inclusion/exclusion of models
- Multi-Attempt Validation: Tracks multiple response attempts per question
- Detailed Metrics: Captures timing data and correctness statistics
- Output Formats: Produces both human-readable CSV and machine-readable JSON
- Error Handling: Gracefully manages API timeouts and exceptions

USAGE:
    php benchmark_extra.php [options]
or
    ./benchmark_extra.php [options] (make sure the file is marked as an executable via chmod +x benchmark_extra.php)

OPTIONS:
--model=pattern, -m=pattern      Filter models using shell-style wildcards (e.g. "*-13b")
--ignore=pattern, -i=pattern     Exclude models matching pattern (e.g. "llama*")
--reset                          Clear all previous benchmark data from database
--reset-model=name, -rm=name     Clear data for specific model only
--total-required-answers=N, -a=N Number of attempts per question (default:1, range:1-10)
--required-correct-answers=N, -c=N Minimum correct answers required (default:1)
--stats                          Display aggregated model statistics table and exit
--astats                         Show all stats parsed per category and subcategory, then exit
--cstats                         Show stats per main category only, then exit
                                  [NOTE: --stats, --astats, and --cstats are mutually exclusive and cannot be combined.]
--details, -d                    Show detailed performance breakdown by category/subcategory
--exclude-subcategories, -e      Show category-level stats only (must be used with -d)
--qcnt=N, -q=N                   Limit benchmark to first N questions (default: all)
--ignore-speed-limits, -isl      Skip speed limit validation (see below)
--max-context=N, -mc=N           Maximum output tokens per response (default:4096)
--min-prompt-speed=N, -ps=N      Minimum tokens/sec for prompt processing (default:3)
--min-token-speed=N, -ts=N       Minimum tokens/sec for generation (default:3)
--min-eval-attempts=N, -ea=N     Minimum attempts before speed evaluation (default:1)
--verbose, -v                    Show detailed question/response information
--endpoint=URL                   Specify the OpenAI-compatible endpoint URL to use for benchmarking.
--bearer=TOKEN                   Specify the bearer token for authentication with the endpoint.
--specific-models=LIST           Comma-delimited list of exact model names to benchmark (e.g., --specific-models=model1,model2). No wildcards allowed. Bypasses model discovery.
--sleep-seconds=N                Sleep for N seconds after each LLM queryPost (default: 0, i.e., no sleep).
--list-models                    List all available models from the selected endpoint and exit.
--help, -h                       Show this help message and exit
--include-reasoning              Used to specify reasoning effort for cloud based models.

SPEED LIMITS:
  "Speed limit" in this benchmark refers to the minimum required processing speeds for LLM models, measured in tokens per second (tokens/sec). Two types of speed are measured:
    - Prompt processing speed: How quickly the model processes the input prompt (tokens/sec).
    - Token generation speed: How quickly the model generates output tokens (tokens/sec).
  By default, the minimum thresholds are:
    --min-prompt-speed=3   (minimum 3 tokens/sec for prompt processing)
    --min-token-speed=3    (minimum 3 tokens/sec for token generation)
  If a model's average speed falls below either threshold (after the minimum number of attempts, see --min-eval-attempts), the model is automatically skipped from the benchmark and will not be evaluated further.
  You can override this behavior using the --ignore-speed-limits (or -isl) option, which disables speed checks and allows all models to be benchmarked regardless of their speed.

DETAILED USAGE:
- For basic benchmarking: php benchmark_extra.php
- To compare specific models: php benchmark_extra.php --model="model1" --model="model2"
- For category analysis: php benchmark_extra.php --details
- For category-only summary: php benchmark_extra.php --details --exclude-subcategories
- To limit questions: php benchmark_extra.php --qcnt=50
- To ignore speed limits: php benchmark_extra.php --ignore-speed-limits
- To specify a custom endpoint and bearer token: php benchmark_extra.php --endpoint="https://api.example.com/v1" --bearer="YOUR_TOKEN"
- To benchmark specific models only (no wildcards, comma-delimited): php benchmark_extra.php --specific-models=model1,model2
- To show overall stats: php benchmark_extra.php --stats
- To show all stats per category/subcategory: php benchmark_extra.php --astats
- To show stats per main category only: php benchmark_extra.php --cstats

EXAMPLES:
1. Basic benchmark run:
   php benchmark_extra.php

2. Benchmark specific models with 3 attempts per question:
   php benchmark_extra.php --model=*-13b --total-required-answers=3

3. Show overall statistics:
   php benchmark_extra.php --stats

4. Show all stats per category/subcategory:
   php benchmark_extra.php --astats

5. Show stats per main category only:
   php benchmark_extra.php --cstats

6. Benchmark first 10 questions, ignoring speed limits:
   php benchmark_extra.php --qcnt=10 --ignore-speed-limits

7. Benchmark multiple specific models using a custom endpoint and bearer token:
   php benchmark_extra.php --endpoint=https://api.openai-compatible.com/v1 --bearer="sk-xxxx" --specific-models="model1,model2"


HELP;
    exit(0);
}

require_once '../vendor/autoload.php';

use function PHPUnit\Framework\isNull;

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// Configurable limits
$maxOutputContext = 8192;  // Maximum number of tokens per response

// Speed monitoring thresholds (configurable via command line)
$minPromptSpeed = 0;    // Minimum tokens/sec for prompt processing
$minTokenGeneration = 0; // Minimum tokens/sec for generation
$minAnswersForEvaluation = 1; // Minimum attempts before speed evaluation


$totalRequiredAnswersPerQuestion = 1;
$requiredCorrectAnswers = 1;

// ======================= Configuration =======================
// Initialize connection with reasonable timeouts
$llmConnection = new OpenAICompatibleEndpointConnection('config.json');

// Validate configuration is properly loaded
try {
    $config = $llmConnection->getConfiguration();
    if (!$config->configKeyExists('host') && !$config->configKeyExists('apiEndpoint')) {
        die("\033[1;31mError: API endpoint not configured. Please check your config.json file.\033[0m\n");
    }
} catch (Exception $e) {
    echo "\033[1;31mError loading configuration: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

// Set reasonable timeouts (5 minutes max for model loading, 30 seconds for operations)
$llmConnection->setConnectionTimeout(3000); // 5 minutes timeout for connection
$llmConnection->setStreamIdleTimeout(3000);  // 30 seconds idle timeout
$llmConnection->setStreamReadTimeout(6000);  // 60 seconds read timeout
$llmConnection->setDebugMode(false); // Enable debug mode for troubleshooting streaming issues

$results = [];


$useHardCodedDebugParameters = false;

if ($useHardCodedDebugParameters) {
    // $additionalParams = '--endpoint=https://openrouter.ai/api --specific-models=moonshotai/kimi-vl-a3b-thinking:free';
    $additionalParams = '--model="V_Magistral-Small-2509" --total-required-answers=600';
    $ex = explode(' ', $additionalParams);
    foreach ($ex as $item) {
        $argv[] = $item;
        $argc = count($argv);
    }
}

$resetBenchmark = in_array('--reset', $argv);
$resetModel = null;


if ($resetBenchmark) {
    unlink('benchmark.db');
}

// Initialize SQLite database
$db = new SQLiteDatabase('benchmark.db');

// Check and add category/subcategory columns if they don't exist
$pdo = $db->getPDO();
$columns = $pdo->query("PRAGMA table_info(benchmark_runs)")->fetchAll();
$hasCategory = false;
$hasSubcategory = false;

foreach ($columns as $col) {
    if ($col['name'] === 'category') $hasCategory = true;
    if ($col['name'] === 'subcategory') $hasSubcategory = true;
}

if (!$hasCategory) {
    $pdo->exec("ALTER TABLE benchmark_runs ADD COLUMN category TEXT");
}
if (!$hasSubcategory) {
    $pdo->exec("ALTER TABLE benchmark_runs ADD COLUMN subcategory TEXT");
}

$filterModels = [];
$ignoredModels = [];
$showStats = in_array('--show-stats', $argv);
$showDetails = in_array('--details', $argv) || in_array('-d', $argv);
$excludeSubcategories = in_array('--exclude-subcategories', $argv) || in_array('-e', $argv);

// New: Overhauled stats options
$showStats = in_array('--stats', $argv);
$showAStats = in_array('--astats', $argv);
$showCStats = in_array('--cstats', $argv);

// Enforce mutual exclusivity
$statsOptions = array_filter([
    $showStats ? 'stats' : null,
    $showAStats ? 'astats' : null,
    $showCStats ? 'cstats' : null,
]);
if (count($statsOptions) > 1) {
    fwrite(STDERR, "\n\033[1;31mError: --stats, --astats, and --cstats are mutually exclusive and cannot be combined.\033[0m\n");
    exit(1);
}

$verboseOutput = in_array('--verbose', $argv) || in_array('-v', $argv);

//DEBUG
//$showStats = TRUE;

$ignoreSpeedLimits = in_array('--ignore-speed-limits', $argv) || in_array('-isl', $argv);

if ($ignoreSpeedLimits) {
    echo "\n\t### Ignoring SPEED LIMITS!! ###\n";
}

$questionCountLimit = null;

/* --- BEGIN: CLI option enhancements for endpoint, bearer, single-model --- */
$endpoint = null;
$bearer = null;
$specificModels = null;
$useReasoning = null;
/* --- END: CLI option enhancements --- */

// Check for --list-models parameter
$listModels = in_array('--list-models', $argv);

// Parse command-line parameters
$sleepSeconds = 0; // Default: no sleep
foreach ($argv as $arg) {
    // Handle model filtering (--model or -m)
    if (str_starts_with($arg, '--model=') || str_starts_with($arg, '-m=')) {
        $filterModels[] = substr($arg, strpos($arg, '=') + 1);
    }

    elseif(str_starts_with($arg,'--include-reasoning')) {
        $useReasoning = TRUE;
    }
    // Set number of attempts per question (--total-required-answers or -a)
    elseif (str_starts_with($arg, '--total-required-answers=') || str_starts_with($arg, '-a=')) {
        $totalRequiredAnswersPerQuestion = (int) substr($arg, strpos($arg, '=') + 1);
    }
    // Set required correct answers threshold (--required-correct-answers or -c)
    elseif (str_starts_with($arg, '--required-correct-answers=') || str_starts_with($arg, '-c=')) {
        $requiredCorrectAnswers = (int) substr($arg, strpos($arg, '=') + 1);
    }
    // Handle model exclusions (--ignore or -i)
    elseif (str_starts_with($arg, '--ignore=') || str_starts_with($arg, '-i=')) {
        $ignoredModels[] = substr($arg, strpos($arg, '=') + 1);
    }
    // Limit questions (--qcnt or -q)
    elseif (str_starts_with($arg, '--qcnt=') || str_starts_with($arg, '-q=')) {
        $questionCountLimit = (int) substr($arg, strpos($arg, '=') + 1);
    }
    // Set minimum prompt speed (--min-prompt-speed or -ps)
    elseif (str_starts_with($arg, '--min-prompt-speed=') || str_starts_with($arg, '-ps=')) {
        $minPromptSpeed = (float) substr($arg, strpos($arg, '=') + 1);
    }
    // Handle model reset (--reset-model or -rm)
    elseif (str_starts_with($arg, '--reset-model=') || str_starts_with($arg, '-rm=')) {
        $resetModel = substr($arg, strpos($arg, '=') + 1);
    }
    // Set minimum token generation speed (--min-token-speed or -ts)
    elseif (str_starts_with($arg, '--min-token-speed=') || str_starts_with($arg, '-ts=')) {
        $minTokenGeneration = (float) substr($arg, strpos($arg, '=') + 1);
    }
    // Set minimum evaluation attempts (--min-eval-attempts or -ea)
    elseif (str_starts_with($arg, '--min-eval-attempts=') || str_starts_with($arg, '-ea=')) {
        $minAnswersForEvaluation = (int) substr($arg, strpos($arg, '=') + 1);
    }
    // Set maximum output context (--max-context or -mc)
    elseif (str_starts_with($arg, '--max-context=') || str_starts_with($arg, '-mc=')) {
        $maxOutputContext = (int) substr($arg, strpos($arg, '=') + 1);
    }
    // Handle model reset (--reset-model or -rm)
    elseif (str_starts_with($arg, '--reset-model=') || str_starts_with($arg, '-rm=')) {
        $resetModel = substr($arg, strpos($arg, '=') + 1);
    }
    // Handle endpoint option
    elseif (str_starts_with($arg, '--endpoint=')) {
        $endpoint = substr($arg, strlen('--endpoint='));
    }
    // Handle bearer token option
    elseif (str_starts_with($arg, '--bearer=')) {
        $bearer = substr($arg, strlen('--bearer='));
    }
    // Handle specific-models option
    elseif (str_starts_with($arg, '--specific-models=')) {
        $specificModels = array_map('trim', explode(',', substr($arg, strlen('--specific-models='))));
    }

        // Handle sleep-seconds option
        elseif (str_starts_with($arg, '--sleep-seconds=')) {
            $sleepSeconds = (int) substr($arg, strlen('--sleep-seconds='));
        }
        // DEBUG
        //$questionCountLimit = 100;
    }
if ($resetModel) {
    $db = new SQLiteDatabase('benchmark.db');
    $pdo = $db->getPDO();
    
    try {
        $pdo->beginTransaction();
        
        // Delete from benchmark_runs and get count
        $stmt1 = $pdo->prepare("DELETE FROM benchmark_runs WHERE model_id = ?");
        $stmt1->execute([$resetModel]);
        $benchmarkRunsDeleted = $stmt1->rowCount();
        
        // Delete from model_stats and get count
        $stmt2 = $pdo->prepare("DELETE FROM model_stats WHERE model_id = ?");
        $stmt2->execute([$resetModel]);
        $modelStatsDeleted = $stmt2->rowCount();
        
        $pdo->commit();
        
        echo "Reset data for model: $resetModel\n";
        echo "Deleted $benchmarkRunsDeleted records from benchmark_runs\n";
        echo "Deleted $modelStatsDeleted records from model_stats\n";
        exit(0);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error resetting model data: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// If only showing stats, display and exit
// Overhauled: handle --stats, --astats, --cstats (mutually exclusive)
if ($showStats || $showAStats || $showCStats) {
    try {
        $models = $db->getDistinctModels();
        foreach ($models as $modelId) {
            $db->updateModelStats($modelId);
        }
        if ($showStats) {
            // Overall stats, no details
            displayModelStats($db, false, false);
        } elseif ($showAStats) {
            // All stats per category and subcategory
            displayModelStats($db, true, false);
        } elseif ($showCStats) {
            // Stats per main category only
            displayModelStats($db, true, true);
        }
        exit(0);
    } catch (Exception $e) {
        echo "\n\033[1;31mError displaying statistics: " . $e->getMessage() . "\033[0m\n";
        exit(1);
    }
}

if (!empty($filterModels)) {
    echo "\n\033[1;33mFiltering models using patterns: " . implode(', ', $filterModels) . "\033[0m\n";
}

// ======================= Benchmark Data =======================
$benchmarkDataFile = 'benchmark_data.json';
if (!file_exists($benchmarkDataFile)) {
    die("\033[1;31mError: Benchmark data file '$benchmarkDataFile' not found.\033[0m\n");
}
$benchmarkDataJson = file_get_contents($benchmarkDataFile);
$benchmarkData = json_decode($benchmarkDataJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("\033[1;31mError decoding benchmark data: " . json_last_error_msg() . "\033[0m\n");
}

$totalQuestions = count($benchmarkData);

// ======================= Core Functions =======================
/**
 * Displays model statistics in a formatted table with optional category breakdown
 *
 * @param SQLiteDatabase $db Database connection
 * @param bool $showDetails Show category/subcategory breakdown when true
 * @param bool $excludeSubcategories Show only category-level stats when true (requires $showDetails)
 * @return void
 */
/**
 * Strips ANSI escape codes from a string.
 */
function strip_ansi_escape_codes(string $str): string {
    return preg_replace('/\033\[[0-9;]*m/', '', $str);
}

/**
 * Helper function to render a stats table with full borders and alignment.
 */
function renderStatsTable(array $header, array $rows, array $aligns = null): void {
    // Calculate column widths
    $widths = [];
    foreach ($header as $i => $title) {
        $maxValueLength = max(
            strlen(strip_ansi_escape_codes($title)),
            ...array_map(
                fn($row) => isset($row[$i]) ? strlen(strip_ansi_escape_codes((string)$row[$i])) : 0,
                $rows
            )
        );
        // Add extra width for token count columns if present
        $baseWidth = $maxValueLength + 1;
        if (in_array($title, ['AvgPromptTk', 'AvgGenTk'])) {
            $widths[$i] = max($baseWidth, 10);
        } else {
            $widths[$i] = $baseWidth;
        }
    }
    // Calculate total table width
    $totalWidth = array_sum($widths) + (count($widths) * 3) + 1;

    // Print horizontal line
    $hline = str_repeat('-', $totalWidth);

    // Print table header
    echo $hline . "\n";
    foreach ($header as $i => $title) {
        $align = $aligns[$i] ?? ((in_array($title, ['Model', 'Correct %'])) ? STR_PAD_RIGHT : STR_PAD_LEFT);
        echo '| ' . str_pad($title, $widths[$i], ' ', $align) . ' ';
    }
    echo "|\n";
    echo $hline . "\n";

    // Print table rows
    foreach ($rows as $row) {
        foreach ($header as $i => $title) {
            $value = isset($row[$i]) ? $row[$i] : '';
            $visibleLen = strlen(strip_ansi_escape_codes($value));
            $padLen = $widths[$i] + strlen($value) - $visibleLen;
            $align = $aligns[$i] ?? ((in_array($header[$i], ['Model', 'Correct %'])) ? STR_PAD_RIGHT : STR_PAD_LEFT);
            echo '| ' . str_pad($value, $padLen, ' ', $align) . ' ';
        }
        echo "|\n";
    }
    echo $hline . "\n";
}

function displayModelStats(SQLiteDatabase $db, bool $showDetails = false, bool $excludeSubcategories = false): void {

    // First display vetted questions
    $vettedQuery = "SELECT qid FROM valid_questions";
    $vettedQuestions = $db->getPDO()->query($vettedQuery)->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($vettedQuestions)) {
        echo "\n\033[1mThe following questions have been manually vetted: " . implode(', ', $vettedQuestions) . "\033[0m\n";
        echo str_repeat('-', 40) . "\n";
    }

    // Fetch and display questions with zero correct answers (excluding vetted questions)
    $query = "
        SELECT
            question_id,
            actual_question
        FROM
            benchmark_runs
        WHERE
            question_id NOT IN (" . implode(',', array_fill(0, count($vettedQuestions), '?')) . ")
        GROUP BY
            question_id
        HAVING
            SUM(correct) = 0
        ORDER BY
            question_id ASC;
    ";
    
    $stmt = $db->getPDO()->prepare($query);
    $stmt->execute($vettedQuestions);
    $questionsWithZeroCorrect = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($questionsWithZeroCorrect)) {
        echo "\n\033[1mQuestions with Zero Correct Answers (excluding vetted questions)\033[0m\n";
        echo str_repeat('-', 40) . "\n";
        foreach ($questionsWithZeroCorrect as $question) {
            echo "Question ID: {$question['question_id']}\n";
            echo "Question: {$question['actual_question']}\n";
            echo str_repeat('-', 40) . "\n";
        }
        echo str_repeat('-', 40) . "\n";
    } else {
        echo "\nAll questions have been answered correctly by at least one model.\n";
    }
    

    $stats = $db->getAllModelStats();
    
    if (empty($stats)) {
        echo "No benchmark statistics available yet.\n";
        return;
    }
    
    // Format table header (add index column at the start and end)
    $header = [
        '#',
        'Model',
        'Correct %',
        'A_OK',
        'A_FAIL',
        'Prompt Time',
        'Pred Time',
        'PromptTokens/s',
        'Tokens/s',
        'AvgPromptTk',
        'AvgGenTk',
        'Questions',
        '#' // Index as last column
    ];

    // Compute best correct % for deviation calculation
    $best_correct_pct = 0.0;
    foreach ($stats as $model) {
        if ($model['total_questions'] > 0 && $model['correct_answers'] >= 0) {
            $pct = ($model['correct_answers'] / max(1, $model['total_questions'])) * 100;
            if ($pct > $best_correct_pct) $best_correct_pct = $pct;
        }
    }

    // Format table rows with index and deviation
    $rows = [];
    $i = 1;
    foreach ($stats as $model) {
        $model_name = $model['model_id'];
        $pct = $model['total_questions'] > 0 && $model['correct_answers'] >= 0
            ? ($model['correct_answers'] / max(1, $model['total_questions'])) * 100
            : 0.0;
        $pct_str = number_format($pct, 1) . '%';

        // Deviation from best
        $deviation = $best_correct_pct - $pct;
        if ($deviation > 0.0001) {
            $deviation_str = sprintf(" (\033[1;91m-%.1f%%\033[0m)", $deviation);
        } else {
            $deviation_str = "";
        }
        $correct_col = $pct_str . $deviation_str;

        // Row matches header: index, model_name, correct_col, correct, incorrect, prompt_time, pred_time, prompt_eval/s, tokens/s, avg_prompt_tokens, avg_predicted_tokens, total_questions, index (again)
        $rows[] = [
            $i,
            $model_name,
            $correct_col,
            $model['correct_answers'],
            $model['incorrect_answers'],
            $model['avg_prompt_time'] >= 0 ? number_format($model['avg_prompt_time'], 3) . 's' : 'N/A',
            $model['avg_predicted_time'] >= 0 ? number_format($model['avg_predicted_time'], 3) . 's' : 'N/A',
            $model['avg_prompt_eval_per_second'] >= 0 ? number_format($model['avg_prompt_eval_per_second'], 1) : 'N/A',
            $model['avg_tokens_per_second'] >= 0 ? number_format($model['avg_tokens_per_second'], 1) : 'N/A',
            $model['avg_prompt_tokens'] >= 0 ? number_format($model['avg_prompt_tokens'], 0) : 'N/A',
            $model['avg_predicted_tokens'] >= 0 ? number_format($model['avg_predicted_tokens'], 0) : 'N/A',
            $model['total_questions'],
            $i // Index as last column
        ];
        $i++;
    }

    // Sort rows by Correct % descending
    usort($rows, function($a, $b) {
        // Remove % and compare as float
        // $a[2] and $b[2] are correct_col, which may contain deviation string, so extract the float
        preg_match('/([\d.]+)/', $a[2], $ma);
        preg_match('/([\d.]+)/', $b[2], $mb);
        return floatval($mb[1] ?? 0) <=> floatval($ma[1] ?? 0);
    });

    // Calculate column widths with extra padding for formatted numbers
    $widths = array_map(function($col) use ($rows, $header) {
        $maxValueLength = max(array_map(function($row) use ($col) {
            return isset($row[$col]) ? strlen(strip_ansi_escape_codes((string)$row[$col])) : 0;
        }, $rows));
        $headerLength = strlen(strip_ansi_escape_codes($header[$col]));
        // Add extra width for token count columns
        $baseWidth = max($maxValueLength, $headerLength) + 1;
        return in_array($header[$col], ['AvgPromptTk', 'AvgGenTk']) ?
            max($baseWidth, 10) : // Ensure minimum width of 10 for token columns
            $baseWidth;
    }, array_keys($header));
    
    // Calculate total table width
    $totalWidth = array_sum($widths) + (count($widths) * 3) + 1;
    
    // Print table header
    echo "\n\033[1mModel Performance Statistics\033[0m\n";
    echo str_repeat('-', $totalWidth) . "\n";
    foreach ($header as $i => $title) {
        $align = (in_array($title, ['Model', 'Correct %'])) ? STR_PAD_RIGHT : STR_PAD_LEFT;
        echo '| ' . str_pad($title, $widths[$i], ' ', $align) . ' ';
    }
    echo "|\n";
    echo str_repeat('-', $totalWidth) . "\n";
    
    // Print table rows
    foreach ($rows as $row) {
        foreach ($header as $i => $title) {
            $value = isset($row[$i]) ? $row[$i] : '';
            // Pad based on visible length (strip ANSI codes)
            $visibleLen = strlen(strip_ansi_escape_codes($value));
            $padLen = $widths[$i] + strlen($value) - $visibleLen;
            $align = (in_array($header[$i], ['Model', 'Correct %'])) ? STR_PAD_RIGHT : STR_PAD_LEFT;
            echo '| ' . str_pad($value, $padLen, ' ', $align) . ' ';
        }
        echo "|\n";
    }
    echo str_repeat('-', $totalWidth) . "\n";

    if ($showDetails) {
        // Get category breakdown stats
        $categoryQuery = "
            SELECT
                category,
                subcategory,
                model_id,
                COUNT(*) as total_questions,
                SUM(correct) as correct_answers,
                AVG(response_time) as avg_response_time,
                AVG(prompt_time) as avg_prompt_time,
                AVG(predicted_time) as avg_predicted_time,
                AVG(tokens_per_second) as avg_tokens_per_second,
                AVG(prompt_eval_per_second) as avg_prompt_eval_per_second
            FROM benchmark_runs
            GROUP BY category, subcategory, model_id
            ORDER BY category, subcategory, (SUM(correct)/COUNT(*)) DESC
        ";
        $categoryStats = $db->getPDO()->query($categoryQuery)->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($categoryStats)) {
            // Group by category
            $groupedStats = [];
            foreach ($categoryStats as $stat) {
                $groupedStats[$stat['category']][$stat['subcategory']][$stat['model_id']] = $stat;
            }

            // Display category breakdown
            echo "\n\033[1mDetailed Performance by Category\033[0m\n";
            foreach ($groupedStats as $category => $subcategories) {
                echo "\n\033[1;34mCategory: $category\033[0m\n";
                if ($excludeSubcategories) {
                    // Combine all subcategories for this category
                    $combinedModels = [];
                    foreach ($subcategories as $subcategory => $models) {
                        foreach ($models as $modelId => $modelStats) {
                            if (!isset($combinedModels[$modelId])) {
                                $combinedModels[$modelId] = $modelStats;
                            } else {
                                $combinedModels[$modelId]['total_questions'] += $modelStats['total_questions'];
                                $combinedModels[$modelId]['correct_answers'] += $modelStats['correct_answers'];
                            }
                        }
                    }
                    // Format table header (add index column at the start and end)
                    $header = [
                        '#',
                        'Model',
                        'Correct %',
                        'Correct',
                        'Total',
                        'Avg Time',
                        '#' // Index as last column
                    ];
                    // Compute best correct % for deviation calculation
                    $best_correct_pct = 0.0;
                    foreach ($combinedModels as $modelStats) {
                        if ($modelStats['total_questions'] > 0) {
                            $pct = ($modelStats['correct_answers'] / $modelStats['total_questions']) * 100;
                            if ($pct > $best_correct_pct) $best_correct_pct = $pct;
                        }
                    }
                    // Format table rows sorted by accuracy, with index, deviation, and full model name
                    $rows = [];
                    $idx = 1;
                    foreach ($combinedModels as $modelStats) {
                        $pct = $modelStats['total_questions'] > 0
                            ? ($modelStats['correct_answers'] / $modelStats['total_questions']) * 100
                            : 0.0;
                        $pct_str = number_format($pct, 1) . '%';
                        $deviation = $best_correct_pct - $pct;
                        if ($deviation > 0.0001) {
                            $deviation_str = sprintf(" (\033[1;91m-%.1f%%\033[0m)", $deviation);
                        } else {
                            $deviation_str = "";
                        }
                        $correct_col = $pct_str . $deviation_str;
                        $rows[] = [
                            $idx,
                            $modelStats['model_id'],
                            $correct_col,
                            $modelStats['correct_answers'],
                            $modelStats['total_questions'],
                            number_format($modelStats['avg_response_time'], 3) . 's',
                            $idx // Index as last column
                        ];
                        $idx++;
                    }
                    // Sort by Correct % descending
                    usort($rows, function($a, $b) {
                        // $a[2] and $b[2] are correct_col, which may contain deviation string, so extract the float
                        preg_match('/([\d.]+)/', $a[2], $ma);
                        preg_match('/([\d.]+)/', $b[2], $mb);
                        return floatval($mb[1] ?? 0) <=> floatval($ma[1] ?? 0);
                    });
                    // Use renderStatsTable for consistent table rendering
                    renderStatsTable($header, $rows);
                } else {
                    foreach ($subcategories as $subcategory => $models) {
                        echo "\n\033[1;33mCategory: $category > Subcategory: $subcategory\033[0m\n";
                        // Format table header (add index column at the start and end)
                        $header = [
                            '#',
                            'Model',
                            'Correct %',
                            'Correct',
                            'Total',
                            'Avg Time',
                            '#' // Index as last column
                        ];
                        // Compute best correct % for deviation calculation
                        $best_correct_pct = 0.0;
                        foreach ($models as $modelStats) {
                            if ($modelStats['total_questions'] > 0) {
                                $pct = ($modelStats['correct_answers'] / $modelStats['total_questions']) * 100;
                                if ($pct > $best_correct_pct) $best_correct_pct = $pct;
                            }
                        }
                        // Format table rows with index, deviation, and full model name
                        $rows = [];
                        $idx = 1;
                        foreach ($models as $modelStats) {
                            $pct = $modelStats['total_questions'] > 0
                                ? ($modelStats['correct_answers'] / $modelStats['total_questions']) * 100
                                : 0.0;
                            $pct_str = number_format($pct, 1) . '%';
                            $deviation = $best_correct_pct - $pct;
                            if ($deviation > 0.0001) {
                                $deviation_str = sprintf(" (\033[1;91m-%.1f%%\033[0m)", $deviation);
                            } else {
                                $deviation_str = "";
                            }
                            $correct_col = $pct_str . $deviation_str;
                            $rows[] = [
                                $idx,
                                $modelStats['model_id'],
                                $correct_col,
                                $modelStats['correct_answers'],
                                $modelStats['total_questions'],
                                number_format($modelStats['avg_response_time'], 3) . 's',
                                $idx // Index as last column
                            ];
                            $idx++;
                        }
                        // Sort by Correct % descending
                        usort($rows, function($a, $b) {
                            // $a[2] and $b[2] are correct_col, which may contain deviation string, so extract the float
                            preg_match('/([\d.]+)/', $a[2], $ma);
                            preg_match('/([\d.]+)/', $b[2], $mb);
                            return floatval($mb[1] ?? 0) <=> floatval($ma[1] ?? 0);
                        });
                        // Use renderStatsTable for consistent table rendering
                        renderStatsTable($header, $rows);
                    }
                }
            }
        }
    }



}

/**
 * Generates a visual progress bar with emoji indicators
 */
function generateProgressBar($current, $total, $correct, $wrong) {

    
    
    $done = $correct + $wrong;
    $remaining = $total - $done;

    $runNumber = intdiv($done - 1, $total) + 1;

    if ($done > $total) {
        $done = $done - ($runNumber - 1) * $total;
    }

    $bar = "[Loop $runNumber | $done/$total ";

    if ($correct > 0) {
        $bar .= str_repeat('👍', $correct);   // Correct answers
    }

    if ($wrong > 0) {
        $bar .= str_repeat('🚫', $wrong);     // Incorrect answers
    }

    if ($remaining > 0) {
        $bar .= str_repeat('-', $remaining);  // Remaining questions
    }

    $bar .= "]";

    return $bar;
}

/**
 * Prepares question data for LLM processing
 */
function prepareQuestion(&$entry) {
    if ($entry['type'] === 'mcq' && !empty($entry['options'])) {
        $entry['instruction'] = "Select the correct answer(s) by providing the corresponding letter(s) as a comma-separated list (e.g., \"h\" for a single answer or \"i, j\" for multiple answers).";
        $options = $entry['options'];
        uksort($options, fn() => rand() - getrandmax()/2);
        $entry['shuffled_options'] = $options;
    }

    $category = $entry['category'];
    $subcategory = $entry['subcategory'];


    $questionString = "The following question's main category is `$category` and its subcategory is `$subcategory`. Please answer the following question in this category and subcategory context.\n\n### Question:\n{$entry['q']}\n### Instruction:\n{$entry['instruction']}";
    
    $entry['full_prompt'] = $questionString;
    
    if (isset($entry['shuffled_options'])) {
        $entry['full_prompt'] .= "\nOptions:\n" .
            implode("\n", array_map(
                fn($k, $v) => "$k) $v",
                array_keys($entry['shuffled_options']),
                $entry['shuffled_options']
            ));
    }
}

/**
 * Validates LLM response against expected answers.
 * This version handles both tagged and untagged responses.
 */
function validateResponse($entry, $response) {
    echo "\n\t## Expected response: " . json_encode($entry['answers']) . "\n";

    $finalResponse = '';

    // First try to extract content from <response> tags
    $last_open_tag_pos = strrpos($response, '<response>');

    if ($last_open_tag_pos !== false) {
        // Get the substring that starts *after* the opening tag
        $content_and_onward = substr($response, $last_open_tag_pos + 10);

        // Find the position of the first closing tag *in that remaining part*
        $first_close_tag_pos = strpos($content_and_onward, '</response>');

        if ($first_close_tag_pos !== false) {
            // If a closing tag exists, our content is everything before it
            $finalResponse = substr($content_and_onward, 0, $first_close_tag_pos);
        } else {
            // If no closing tag is found, the rest of the string is our content
            $finalResponse = $content_and_onward;
        }
    }

    // If no tagged content found, try to extract the last line or use the whole response
    if (empty($finalResponse)) {
        // Try to get the last meaningful line
        $lines = array_filter(array_map('trim', explode("\n", $response)));
        if (!empty($lines)) {
            $finalResponse = end($lines);
        } else {
            $finalResponse = $response;
        }
    }
    
    // Clean up the response - remove common prefixes/suffixes
    $finalResponse = trim($finalResponse);
    $finalResponse = preg_replace('/^(Answer:|Response:|Result:)\s*/i', '', $finalResponse);
    $finalResponse = preg_replace('/\s*(Answer|Response|Result)[\s:]*$/i', '', $finalResponse);
    
    echo "\nFinal Response: " . trim($finalResponse) . "\n";

    // The rest of your validation logic remains the same.
    $clean = strtolower(trim($finalResponse));
    $clean = trim($clean, '.');
    $clean = str_replace(' ', '', $clean);

    if ($entry['type'] === 'mcq') {
        // Handle empty responses more gracefully
        if ($clean === '') {
            echo "\n\tWARNING: Empty response received for MCQ question\n";
            echo "Selected Response: []\n";
            return false;
        }
        
        $selected = preg_split('/\s*,\s*/', $clean);
        
        // Filter out empty values that might result from splitting
        $selected = array_filter($selected, function($item) {
            return trim($item) !== '';
        });
        
        // Re-index array to ensure proper indexing
        $selected = array_values($selected);

        echo "Selected Response: ";
        print_r($selected);

        // Check if we have any valid selections
        if (empty($selected)) {
            echo "\n\tWARNING: No valid selections found after processing response\n";
            return false;
        }

        $correct = array_map('strtolower', $entry['answers']);

        if (count($selected) !== count($correct)) {
            echo "\n\tRESPONSE MISMATCH: Different count - Expected " . count($correct) . ", got " . count($selected) . "\n";
            return false;
        }
        
        // Use array_diff to check for differences in one go.
        // This checks if the arrays contain the same values, regardless of order.
        if (count(array_diff($selected, $correct)) === 0 && count(array_diff($correct, $selected)) === 0) {
             echo "\nI WILL RETURN *** TRUE *** \n";
             return true;
        }

        echo "\n\tRESPONSE MISMATCH: Content differs\n";
        echo "Expected: [" . implode(', ', $correct) . "]\n";
        echo "Got: [" . implode(', ', $selected) . "]\n";
        return false;
    }

    // This part remains for non-MCQ questions
    $processedAnswers = array_map(function($answer) {
        $cleaned = strtolower(trim($answer));
        $cleaned = trim($cleaned, '.');
        return str_replace(' ', '', $cleaned);
    }, $entry['answers']);

    foreach ($processedAnswers as $pa) {
        if (is_numeric($clean)) {
            if ($clean == $pa) {
                return true;
            }
        } else {
            if (str_contains($clean, $pa)) {
                return true;
            }

            if (function_exists('str_match_wildcard') && str_match_wildcard($clean, $pa)) {
                return true;
            }
        }
    }
    return false;
}

function extractCleanResponse($response) {
    // Handle null or empty input
    if (is_null($response) || $response === '') {
        echo "\n\tWARNING: extractCleanResponse received null or empty response\n";
        return '';
    }
    
    $finalResponse = '';
    
    // Try to extract content from <response> tags first
    preg_match_all('/<response>(.*?)<\/response>/s', $response, $matches);
    if (!empty($matches[1])) {
        $finalResponse = end($matches[1]);
    }
    
    // If no tagged content found, try to extract the last meaningful line
    if (empty($finalResponse)) {
        $lines = array_filter(array_map('trim', explode("\n", $response)));
        if (!empty($lines)) {
            $finalResponse = end($lines);
        } else {
            $finalResponse = $response;
        }
    }
    
    // Clean up the response
    $finalResponse = trim($finalResponse);
    $finalResponse = preg_replace('/^(Answer:|Response:|Result:)\s*/i', '', $finalResponse);
    $finalResponse = preg_replace('/\s*(Answer|Response|Result)[\s:]*$/i', '', $finalResponse);
    
    $clean = strtolower(trim($finalResponse));
    $clean = trim($clean, '.');
    $clean = str_replace(' ', '', $clean);

    // Additional check for completely empty response after cleaning
    if ($clean === '') {
        echo "\n\tWARNING: Response became empty after cleaning\n";
        echo "Original response: " . var_export($response, true) . "\n";
        echo "Final response: " . var_export($finalResponse, true) . "\n";
    }

    return $clean;
}

function str_match_wildcard(string $haystack, string $pattern, bool $caseInsensitive = false): bool {
    $regex = preg_replace_callback('/[^*?^$]+/', function ($matches) {
        return preg_quote($matches[0], '/');
    }, $pattern);

    $regex = str_replace(['*', '?'], ['.*', '.'], $regex);
    $flags = $caseInsensitive ? 'i' : '';
    return preg_match("/{$regex}/{$flags}", $haystack) === 1;
}

function loadBenchmarkJson() {
    global $db;
    $results = [];
    
    $models = $db->getDistinctModels();
    
    foreach ($models as $modelId) {
        $questions = $db->getDistinctQuestionsForModel($modelId);
        
        foreach ($questions as $questionId) {
            $runs = $db->getBenchmarkRuns($modelId, $questionId);
            $results[$modelId][$questionId] = array_map(function($run) {
                return [
                    'response' => $run['response'],
                    'clean_response' => $run['clean_response'],
                    'correct' => (bool)$run['correct'],
                    'reasoning' => $run['reasoning'],
                    'response_time' => $run['response_time'],
                    'question_no' => $run['question_no'],
                    'verbose' => json_decode($run['verbose'], true)
                ];
            }, $runs);
        }
    }
    
    return $results;
}
/* --- BEGIN: Apply endpoint, bearer, and specific-models CLI options --- */
if ($endpoint !== null) {
    $llmConnection->setEndpointUri($endpoint);
}
if ($bearer !== null) {
    $llmConnection->setBearedToken($bearer);
}
$models = null;
if (is_array($specificModels) && count($specificModels) > 0) {
    $models = [];
    foreach ($specificModels as $modelName) {
        $llmConnection->setLLMmodelName($modelName); // Set for each, but will be set again in main loop
        $models[] = ['id' => $modelName];
    }
}
/* --- END: Apply endpoint, bearer, and specific-models CLI options --- */

// Handle --list-models: list models and exit before any benchmark logic
if ($listModels) {
    try {
        if ($bearer === null) {
            $llmConnection->readBearerTokenFromFile('.bearer_token');
        }
        $models = $llmConnection->getAvailableModels();
        
        sort($models);

        if (empty($models)) {
            echo "\033[1;31mNo models found at the specified endpoint.\033[0m\n";
            exit(1);
        }
        echo "\033[1mAvailable models at endpoint: " . ($endpoint ?? '[default]') . "\033[0m\n";
        echo str_repeat('-', 60) . "\n";
        foreach ($models as $model) {
            // Support both ['id'=>...] and string model names
            if (is_array($model) && isset($model['id'])) {
                echo " - " . $model['id'] . "\n";
            } else {
                echo " - " . (string)$model . "\n";
            }
        }
        echo str_repeat('-', 60) . "\n";
        echo "Total models: " . count($models) . "\n";
        exit(0);
    } catch (Throwable $e) {
        echo "\033[1;31mError retrieving models: " . $e->getMessage() . "\033[0m\n";
        exit(2);
    }
}

// ======================= Main Execution =======================
/*
 * Benchmark Execution Flow:
 * 1. Load benchmark data from JSON file
 * 2. Load authentication token if available
 * 3. Get available LLM models
 * 4. Filter models based on speed limits (unless disabled)
 * 5. Process each model through all questions:
 *    - Checks for existing attempts
 *    - Generates prompts
 *    - Validates responses
 *    - Records results
 * 6. Display final statistics
 */
 $benchmarkJsonData = loadBenchmarkJson();
 
 // Only perform model discovery if --specific-models was NOT used
 if (!is_array($specificModels) || count($specificModels) === 0) {
     try {
         $models = $llmConnection->getAvailableModels();
         if (empty($models)) {
             echo "\033[1;31mError: No models available from the endpoint.\033[0m\n";
             exit(1);
         }
         sort($models);
     } catch (Exception $e) {
         echo "\033[1;31mError retrieving models: " . $e->getMessage() . "\033[0m\n";
         exit(1);
     }
 }
 
 if ($bearer === null) {
    $llmConnection->readBearerTokenFromFile('.bearer_token');
}

if (!$ignoreSpeedLimits) {
    for ($i=0; $i<count($models); $i++) {
        $stats = $db->getModelStats($models[$i]['id']);

        if (isset($stats['avg_prompt_eval_per_second'])) {
            $modelPromptPerSecond = round($stats['avg_prompt_eval_per_second'], 2);
            $modelTokensPerSecond = round($stats['avg_tokens_per_second'], 2);

            if ($modelPromptPerSecond == 0) {
                $modelPromptPerSecond = PHP_INT_MAX;
            }

            if ($modelPromptPerSecond < $minPromptSpeed || $modelTokensPerSecond < $minTokenGeneration) {
                echo "\033[34;40m\nSlow model skipped: " . $models[$i]['id'] . " (Speeds - prompt eval: $modelPromptPerSecond/s | tokens per second: $modelTokensPerSecond/s)\033[0m\n";
                unset($models[$i]);
            }
        }
    }
}

if (empty($models)) {
    echo "\n\tError retrieving models list. \n";
    die(1);
}

// Reorganize DeepSeek models
[$deepseek, $others] = array_reduce($models, function($carry, $model) {
    stripos(strtolower($model['id']), 'deepseek') !== false ? $carry[0][] = $model : $carry[1][] = $model;
    return $carry;
}, [[], []]);

sort($others);
$models = array_merge($others, $deepseek);

// Initialize model performance tracking
$modelPerformance = array_fill_keys(array_column($models, 'id'), [
    'correct' => 0,
    'total' => 0,
    'prompt_speed' => 0,
    'gen_speed' => 0,
    'speed_samples' => 0
]);

// Initialize benchmark state from database
$benchmarkState = [];

/**
 * Main benchmark loop - processes each model through all questions
 *
 * For each model:
 * 1. Initializes tracking variables
 * 2. Applies model filters if specified
 * 3. Sets up LLM connection
 * 4. Processes each question:
 *    - Prepares question prompt
 *    - Makes API call
 *    - Validates response
 *    - Records attempt
 *    - Updates progress display
 * 5. Handles timeouts and errors
 */


foreach ($models as $modelIndex => $model) {

    $modelId = $model['id'];
    
    // Initialize fresh state for each model
    $modelResults = [];
    $correctCount = 0;
    $incorrectCount = 0;
    $currentQuestion = 0;

    $modelId = $model['id'];

    // Apply model filtering if specified
    if (!empty($filterModels)) {
        $match = false;
        foreach ($filterModels as $filter) {
            if (fnmatch(strtolower($filter), strtolower($modelId))) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            echo "\n\033[1;33mSkipping model (does not match filter): $modelId\033[0m\n";
            continue;
        }
    }

    if (!empty($ignoredModels)) {
        $match = false;
        foreach ($ignoredModels as $filter) {
            if (fnmatch(strtolower($filter), strtolower($modelId))) {
                $match = true;
                break;
            }
        }
        if ($match) {
            echo "\n\033[1;33mIgnoring model (matched filter): $modelId\033[0m\n";
            continue;
        }
    }

    $llmConnection->setLLMmodelName($model['id']);
    $modelResults = [];
    $correctCount = 0;
    $incorrectCount = 0;
    $currentQuestion = 0;

    $allStats = $db->getAllModelStats();
    $rank = 0; $performance = 0; 
    foreach($allStats as $r => $stat) {
        if ($stat['model_id'] === $modelId) {
            $rank = $r;
            $performance = $stat['percentage_correct'];
        }
    }
    echo "\n\033[1;34m=== Model " . ($modelIndex+1) . "/" . count($models) . ": $modelId ===\033[0m\n";
    echo "Rank: " . ($rank + 1) . "/" . count($models) . ". Model accuracy: $performance%\n";

    $startTime = microtime(true);
    $modelStartTime = $startTime;

    // Process each question for the current model (limit to 5 questions per model)
    $maxQuestionsPerModel = 5;
    $questionsToProcess = $questionCountLimit
        ? array_slice($benchmarkData, 0, min($questionCountLimit, $maxQuestionsPerModel), true)
        : array_slice($benchmarkData, 0, $maxQuestionsPerModel, true);
    foreach ($questionsToProcess as $qIndex => $entry) {
        $currentQuestion++;
        prepareQuestion($entry);

        $questionString = $benchmarkData[$qIndex]['q'];

        $category = $entry['category'];
        $subcategory = $entry['subcategory'];
       
        $instructionString = $entry['instruction'];
        $options = json_encode($entry['shuffled_options'] ?? null);
        $expectedAnswer = implode(' || ',$benchmarkData[$qIndex]['answers']);

        $existingAttempts = $benchmarkJsonData[$model['id']][$qIndex] ?? [];
        $numAttempts = count($existingAttempts);
        $remainingAttempts = $totalRequiredAnswersPerQuestion - $numAttempts;

        if ($numAttempts >= $totalRequiredAnswersPerQuestion) {
            $correctAttempts = count(array_filter($existingAttempts, fn($a) => $a['correct']));
            $isCorrectOverall = $correctAttempts >= $requiredCorrectAnswers;

            $isCorrect = $existingAttempts[0]['correct'];

            if ($isCorrect) {
                $correctCount++;
            } else {
                $incorrectCount++;
            }

            $status = $isCorrect ? 'Correct! 👍' : 'INCORRECT! 🚫';

            if ($verboseOutput) {
                echo "\nModel ID: $modelId\n";
                echo "\tExpected response: " . json_encode($entry['answers']);
                echo "\n\tProvided response is $status: " . $existingAttempts[0]['clean_response'] . "\n";
            }


        } else {
            for ($i = 0; $i < $remainingAttempts; $i++) {
                $done = $correctCount + $incorrectCount;
                $progress = generateProgressBar(
                    $currentQuestion,
                    $questionCountLimit ?: $totalQuestions,
                    $correctCount,
                    $incorrectCount
                );
                //$runNumber = intdiv($currentQuestion - 1, $totalQuestions) + 1;
                //echo "Progress: [Question {$currentQuestion}/{$totalQuestions}] - Run #{$runNumber}\n";

                $currentTime = microtime(true);
                $timePassed = $currentTime - $modelStartTime;
                $averageTimePerQuestion = $done > 0 ? $timePassed / $done : 0;
                $remainingQuestions = $questionCountLimit ?
                    min($questionCountLimit, $totalQuestions) - $done :
                    $totalQuestions - $done;
                $eta = $averageTimePerQuestion * $remainingQuestions;

                echo "\n\033[1mQuestion $currentQuestion/$totalQuestions | Model $modelId\033[0m\n";
                echo "Progress: $progress\n";
                echo "Time Passed: " . gmdate("H:i:s", (int) $timePassed) . "\n";
                echo "ETA: " . gmdate("H:i:s", (int) $eta) . "\n";


                $systemPrompt = <<<SYSTEM_PROMPT
You are a helpful AI assistant.
SYSTEM_PROMPT;

                // Empty system prompt
                $systemPrompt = '';

                $category = '';
                
                $prefix = '';

                // Adaptation for Qwen3 thinking models.
                if (str_contains($modelId, 'NOTHINK')) {
                    $prefix = '/nothink ';
                }

                try {
                    $llmConnection->getRolesManager()
                        ->clearMessages()
                        ->setSystemMessage($systemPrompt) // Fix for Nemotron models
                        ->addMessage('user', "Please answer the following question. You may provide your reasoning before giving your final answer, but please provide your final answer clearly. If you use <response> tags, put only the actual answer inside them without explanations. Be as concise as possible.\n\n{$entry['full_prompt']}\n\nNOTE: For multiple choice questions, provide the letter(s) of your answer(s). For open-ended questions, provide a direct answer. Your response can be in any format - just make sure your final answer is clear and identifiable.$prefix");
                        
                    $parameters = $llmConnection->getDefaultParameters();
                    $llmConnection->setParameter('n_predict', $maxOutputContext);
                    $stopWords = $llmConnection->getParameter("stop") ?? [];
                    // $stopWords[] = '<done>'; // THIS does not work well for models that reason and contain this into their reasoning
                    // $stopWords[] = '/<done>'; // IDEM
                    $llmConnection->setParameter('stop', $stopWords);

                    if ($useReasoning) {
                        $llmConnection->setReasoningEffort();
                    }

                    echo PHP_EOL . str_repeat('-', 80) . PHP_EOL;
                    print_r($entry['full_prompt']) . PHP_EOL;
                    echo PHP_EOL . str_repeat('-', 80) . PHP_EOL;
                    echo "\tExpected answer: " . json_encode($entry['answers']) . PHP_EOL;
                    echo PHP_EOL . str_repeat('-', 80) . PHP_EOL;

                    $llmConnection->setParameter('user', 'Viceroy Library (0.1)- Benchmark example');
                    
                    // Add timeout protection for the queryPost call
                    $queryStartTime = microtime(true);
                    $maxQueryTime = 300; // 5 minutes maximum per query (300 seconds, not 14400)
                    
                    $streamedContent = '';
                    $response = null;
                    
                    try {
                        $response = $llmConnection->queryPost([], function($chunk) use ($queryStartTime, $maxQueryTime, &$streamedContent) {
                            // Check if we've exceeded the maximum query time
                            if (microtime(true) - $queryStartTime > $maxQueryTime) {
                                echo "\n\033[1;31mERROR: Query timeout exceeded ({$maxQueryTime}s). Aborting to prevent hanging.\033[0m\n";
                                throw new RuntimeException("Query timeout exceeded");
                            }
                            echo $chunk;
                            $streamedContent .= $chunk;
                        });
                        
                        // Additional check: ensure we have a valid response object
                        if (is_null($response)) {
                            throw new Exception("queryPost returned null response");
                        }
                        
                    } catch (Exception $e) {
                        echo "\n\033[1;31mError during LLM query: " . $e->getMessage() . "\033[0m\n";
                        $content = 'Query ERROR: ' . $e->getMessage();
                        $reasoning = '';
                        $isCorrect = false;
                        $responseTime = microtime(true) - $queryStartTime;
                        $verboseResponse = [];
                        $timingData = [];
                        $promptSpeed = 0;
                        $genSpeed = 0;
                        
                        // Log the error and continue to the next question
                        echo "\n\033[33mModel $modelId failed for question $currentQuestion. Continuing to next question...\033[0m\n";
                        continue 2; // Skip to next question
                    }

                    // Sleep for the specified number of seconds, if requested
                    if ($sleepSeconds > 0) {
                        echo "\nSleeping $sleepSeconds seconds before the next request...\n";
                        sleep($sleepSeconds);
                    }

                    if (FALSE === $response || is_null($response)) {
                        $content = 'Guzzle timeout ERROR: ';
                        $reasoning = '';
                        $isCorrect = false;
                        $responseTime = microtime(true) - $queryStartTime;
                        $verboseResponse = [];
                        $timingData = [];
                        $promptSpeed = 0;
                        $genSpeed = 0;
                        echo "\n\033[1;31mError during LLM query: Guzzle TIMEOUT or NULL response!\033[0m\n";
                        // Don't exit here, continue to the next question
                        continue 2;
                    } else {
                        try {
                            // Additional null check before calling methods on response object
                            if (is_null($response)) {
                                throw new Exception("Response object is null");
                            }
                            
                            // Check if response object has the required method
                            if (!method_exists($response, 'getLlmResponse')) {
                                throw new Exception("Response object does not have getLlmResponse method");
                            }
                            
                            $content = trim($response->getLlmResponse());
                            
                            // Validate content before proceeding
                            if (empty($content)) {
                                echo "\n\033[33mWARNING: Empty content received from getLlmResponse()\033[0m\n";
                                // Try to get raw content as fallback
                                try {
                                    $responseObj = $llmConnection->getResponse();
                                    if ($responseObj && method_exists($responseObj, 'getRawContent')) {
                                        $rawContent = $responseObj->getRawContent();
                                        echo "\n\033[33mRaw content for debugging: " . substr($rawContent, 0, 200) . "...\033[0m\n";
                                    }
                                } catch (Exception $e) {
                                    echo "\n\033[33mCould not get raw content for debugging: " . $e->getMessage() . "\033[0m\n";
                                }
                            }
                            
                            // Safely get raw content with null checks
                            try {
                                $responseObj = $llmConnection->getResponse();
                                if ($responseObj && method_exists($responseObj, 'getRawContent')) {
                                    $rawContent = json_decode($responseObj->getRawContent(), TRUE);
                                    $verboseResponse = $rawContent['__verbose'] ?? [];
                                } else {
                                    $verboseResponse = [];
                                }
                            } catch (Exception $e) {
                                echo "\n\033[33mWarning: Could not get raw content: " . $e->getMessage() . "\033[0m\n";
                                $verboseResponse = [];
                            }
                            
                            // Parse timing data with safe method calls
                            try {
                                $timingData = method_exists($llmConnection, 'getQuerytimings')
                                    ? $llmConnection->getQuerytimings() ?? []
                                    : [];
                                echo PHP_EOL . json_encode($timingData) . "\n";
                            } catch (Exception $e) {
                                echo "\n\033[33mWarning: Could not get timing data: " . $e->getMessage() . "\033[0m\n";
                                $timingData = [];
                            }
                            
                            // Calculate token generation speeds
                            $promptSpeed = 0;
                            $genSpeed = 0;
                            
                            // Safely get think content
                            try {
                                $reasoning = method_exists($llmConnection, 'getThinkContent')
                                    ? $llmConnection->getThinkContent()
                                    : '';
                            } catch (Exception $e) {
                                echo "\n\033[33mWarning: Could not get think content: " . $e->getMessage() . "\033[0m\n";
                                $reasoning = '';
                            }
                            
                            // Validate content before processing
                            if (empty($content)) {
                                throw new Exception("Response content is empty");
                            }
                            
                            $isCorrect = validateResponse($entry, $content);
                            
                            // Safely get response time
                            try {
                                $responseTime = method_exists($llmConnection, 'getLastQueryMicrotime')
                                    ? $llmConnection->getLastQueryMicrotime()
                                    : microtime(true) - $queryStartTime;
                            } catch (Exception $e) {
                                echo "\n\033[33mWarning: Could not get response time: " . $e->getMessage() . "\033[0m\n";
                                $responseTime = microtime(true) - $queryStartTime;
                            }

                            $promptSpeed = $timingData['prompt_per_second'] ?? 999;
                            $genSpeed = $timingData['predicted_per_second'] ?? 999;

                            $status = $isCorrect ? 'Correct! 👍' : 'INCORRECT! 🚫';
                            echo "\n\t### $status \n";

                            // Check speed limits after minimum attempts
                            $attemptCount = count($existingAttempts) + 1;
                            if ($attemptCount >= $minAnswersForEvaluation && !$ignoreSpeedLimits) {
                                if ($promptSpeed < $minPromptSpeed || $genSpeed < $minTokenGeneration) {
                                    echo "\n\033[1;31mSpeed limit violation - prompt: {$promptSpeed}t/s, generation: {$genSpeed}t/s\033[0m\n";
                                    echo "Skipping to next model...\n";
                                    break 2; // Break out of both question and model loops
                                }
                            }
                        } catch (Exception $e) {
                            // Handle any exceptions that occur during response processing
                            echo "\n\033[1;31mError processing LLM response: " . $e->getMessage() . "\033[0m\n";
                            $content = 'Response processing ERROR: ' . $e->getMessage();
                            $reasoning = '';
                            $isCorrect = false;
                            $responseTime = microtime(true) - $queryStartTime;
                            $verboseResponse = [];
                            $timingData = [];
                            $promptSpeed = 0;
                            $genSpeed = 0;
                            
                            // Log the error but still save the attempt
                            echo "\n\033[33mSaving failed attempt for model $modelId, question $currentQuestion...\033[0m\n";
                            
                            // Still save the failed attempt to continue
                            $existingAttempts[] = [
                                'response' => $content,
                                'correct' => $isCorrect,
                                'reasoning' => empty($reasoning) ? '""' : $reasoning,
                                'response_time' => $responseTime,
                                "question_no" => $currentQuestion,
                                'verbose' => json_encode($verboseResponse ?? ''),
                            ];
                            
                            // Continue to next attempt/question
                            continue;
                        }
                    }

                } catch (Exception $e) {
                    // Print the error but continue instead of exiting
                    echo "\n\033[1;31mError during LLM query: " . $e->getMessage() . "\033[0m\n";
                    $content = 'Query ERROR: ' . $e->getMessage();
                    $reasoning = '';
                    $isCorrect = false;
                    $responseTime = microtime(true) - $queryStartTime;
                    $verboseResponse = [];
                    $timingData = [];
                    $promptSpeed = 0;
                    $genSpeed = 0;
                    
                    // Still save the failed attempt to continue
                    $existingAttempts[] = [
                        'response' => $content,
                        'correct' => $isCorrect,
                        'reasoning' => empty($reasoning) ? '""' : $reasoning,
                        'response_time' => $responseTime,
                        "question_no" => $currentQuestion,
                        'verbose' => json_encode($verboseResponse ?? ''),
                    ];
                    
                    continue; // Continue to next attempt
                }

                $existingAttempts[] = [
                    'response' => $content,
                    'correct' => $isCorrect,
                    'reasoning' => empty($reasoning) ? '""' : $reasoning,
                    'response_time' => $responseTime,
                    "question_no" => $currentQuestion,
                    'verbose' => json_encode($verboseResponse ?? ''),
                ];

                // Save attempt to database with timing data
                $db->beginTransaction();
                try {
                    foreach ($existingAttempts as $attemptId => $attempt) {
                        $cleanResponse = extractCleanResponse($attempt['response']);
                        
                        // Safely extract timing parameters with proper validation
                        $promptN = isset($timingData['prompt_n']) ? $timingData['prompt_n'] : null;
                        $promptMs = isset($timingData['prompt_ms']) && is_numeric($timingData['prompt_ms'])
                            ? floatval($timingData['prompt_ms']) / 1000  // Convert to seconds
                            : null;
                        $predictedN = isset($timingData['predicted_n']) ? $timingData['predicted_n'] : null;
                        $predictedMs = isset($timingData['predicted_ms']) && is_numeric($timingData['predicted_ms'])
                            ? floatval($timingData['predicted_ms']) / 1000  // Convert to seconds
                            : null;
                        
                        $db->saveBenchmarkRun(
                            $modelId,
                            $qIndex,
                            $attemptId,
                            $attempt['response'],
                            $attempt['correct'],
                            $attempt['reasoning'],
                            $attempt['response_time'],
                            $currentQuestion,
                            json_encode($attempt['verbose']),
                            $promptN,
                            $promptMs,
                            $predictedN,
                            $predictedMs,
                            $genSpeed > 0 ? $genSpeed : null,
                            $questionString,
                            $options,
                            $expectedAnswer,
                            $cleanResponse,
                            $promptSpeed > 0 ? $promptSpeed : null,
                            $benchmarkData[$qIndex]['category'] ?? null,
                            $benchmarkData[$qIndex]['subcategory'] ?? null
                        );
                    }
                    
                    // Update model state
                    if ($isCorrect) {
                        $correctCount++;
                    } else {
                        $incorrectCount++;
                    }
                    
                    $modelResults[$qIndex] = $existingAttempts;
                    $db->commit();
                    
                    // Display updated stats after each question
                    try {
                        $currentStats = $db->getModelStats($modelId);
                        if ($currentStats) {
                            echo "\n\033[1;34mCurrent Stats: \033[0m";
                            echo "\033[1;32mCorrect: {$currentStats['correct_answers']} (" . number_format($currentStats['percentage_correct'], 1) . "%)\033[0m, ";
                            echo "\033[1;31mFailed: {$currentStats['incorrect_answers']}\033[0m, ";
                            echo "\033[1;33mAvg Time: " . number_format($currentStats['avg_response_time'], 3) . "s, ";
                            echo "Tokens/s: " . number_format($currentStats['avg_tokens_per_second'], 1) . "\033[0m\n";
                        }
                    } catch (Exception $e) {
                        echo "\n\033[33mWarning: Could not retrieve current stats: " . $e->getMessage() . "\033[0m\n";
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    echo "\n\033[1;31mError saving benchmark data: " . $e->getMessage() . "\033[0m\n";
                    echo "\033[33mContinuing to next question despite database error...\033[0m\n";
                    // Don't exit, continue to next question
                    continue;
                }
            }
        }
    }

}

// Display final stats if any models were processed
if (count($models) > 0) {
    displayModelStats($db, $showDetails, $excludeSubcategories);
}
