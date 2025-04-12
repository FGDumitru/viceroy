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

OPTIONS:
--model=pattern           Filter models using shell-style wildcards (e.g. "*-13b")
--ignore=pattern          Exclude models matching pattern (e.g. "llama*")
--reset                   Clear all previous benchmark data from database
--total-required-answers=N Number of attempts per question (default:1, range:1-10)
--required-correct-answers=N Minimum correct answers required (default:1)
--show-stats              Display aggregated model statistics table and exit
--qcnt=N                 Limit benchmark to first N questions (default:all)
--ignore-speed-limits     Skip token generation speed validation (-isl shortcut)
--help, -h               Show this help message and exit

EXAMPLES:
1. Basic benchmark run:
   php benchmark_extra.php

2. Benchmark specific models with 3 attempts per question:
   php benchmark_extra.php --model=*-13b --total-required-answers=3

3. Show statistics from previous runs:
   php benchmark_extra.php --show-stats

4. Benchmark first 10 questions, ignoring speed limits:
   php benchmark_extra.php --qcnt=10 --ignore-speed-limits

HELP;
    exit(0);
}

/**
 * benchmark_extra.php - Extended Multi-Model Performance Benchmark
 *
 * Extended version with additional testing capabilities and documentation
 *
 * This script provides a comprehensive benchmarking framework for LLM models
 * to evaluate their accuracy and performance across multiple questions.
 *
 * Key Features:
 * 1. Model Filtering: Supports pattern-based inclusion/exclusion of models
 * 2. Multi-Attempt Validation: Tracks multiple response attempts per question
 * 3. Detailed Metrics: Captures timing data
 * 4. Output Formats: Produces both human-readable CSV and machine-readable JSON
 * 5. Error Handling: Gracefully manages API timeouts and exceptions
 *
 * Usage:
 * php benchmark_extra.php [options]
 *
 * Examples:
 * 1. Basic benchmark run:
 *    php benchmark_extra.php
 * 2. Benchmark specific models with 3 attempts per question:
 *    php benchmark_extra.php --model=*-13b --total-required-answers=3
 * 3. Show statistics from previous runs:
 *    php benchmark_extra.php --show-stats
 * 4. Benchmark first 10 questions, ignoring speed limits:
 *    php benchmark_extra.php --qcnt=10 --ignore-speed-limits
 *
 * Options:
 * --model=pattern       Filter models using shell-style wildcards (e.g. "*-13b")
 * --ignore=pattern      Exclude models matching pattern (e.g. "llama*")
 * --reset               Clear all previous benchmark data from database
 * --total-required-answers=N  Number of attempts per question (default:1, range:1-10)
 * --required-correct-answers=N Minimum correct answers required to pass (default:1)
 * --show-stats          Display aggregated model statistics table and exit
 * --qcnt=N             Limit benchmark to first N questions (default:all)
 * --ignore-speed-limits Skip token generation speed validation (-isl shortcut)
 */

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// Maximum number of tokens per response.
const MAX_OUTPUT_CONTEXT = 8192;

// Speed monitoring thresholds
const MIN_PROMPT_SPEED = 3;    // Minimum tokens/sec for prompt processing
const MIN_TOKEN_GENERATION = 3; // Minimum tokens/sec for generation
const MIN_ANSWERS_FOR_EVALUATION = 1; // Minimum attempts before speed evaluation


$totalRequiredAnswersPerQuestion = 1;
$requiredCorrectAnswers = 1;

// ======================= Configuration =======================
// Initialize connection with maximum possible timeout
$llmConnection = new OpenAICompatibleEndpointConnection();
$llmConnection->setConnectionTimeout(3600 * 4); // 4h timeout for a single response.

$results = [];

$resetBenchmark = in_array('--reset', $argv);

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

//DEBUG
//$showStats = TRUE;

$ignoreSpeedLimits = in_array('--ignore-speed-limits', $argv) || in_array('-isl', $argv);

$questionCountLimit = null;

// Parse command-line parameters
foreach ($argv as $arg) {
    // Handle model filtering
    if (str_starts_with($arg, '--model=')) {
        $filterModels[] = substr($arg, 8); // Add to model filter list
    }
    // Set number of attempts per question
    elseif (str_starts_with($arg, '--total-required-answers=')) {
        $totalRequiredAnswersPerQuestion = (int) substr($arg, strlen('--total-required-answers='));
    }
    // Set required correct answers threshold
    elseif (str_starts_with($arg, '--required-correct-answers=')) {
        $requiredCorrectAnswers = (int) substr($arg, strlen('--required-correct-answers='));
    }
    // Handle model exclusions
    elseif (str_starts_with($arg, '--ignore=')) {
        $ignoredModels[] = substr($arg, 9); // Add to ignore list
    }
    elseif (str_starts_with($arg, '--qcnt=')) {
        $questionCountLimit = (int) substr($arg, strlen('--qcnt='));
    }

    // DEBUG
    //$questionCountLimit = 100;
}

// If only showing stats, display and exit
if ($showStats) {
    try {
        $models = $db->getDistinctModels();
        foreach ($models as $modelId) {
            $db->updateModelStats($modelId);
        }
        displayModelStats($db);
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
 * Displays model statistics in a formatted table
 */
function displayModelStats(SQLiteDatabase $db): void {
    $stats = $db->getAllModelStats();
    
    if (empty($stats)) {
        echo "No benchmark statistics available yet.\n";
        return;
    }
    
    // Format table header
    $header = [
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
        'Questions'
    ];
    

    // Format table rows
    $rows = array_map(function($model) {
        return [
            substr($model['model_id'], 0, 50),
            $model['total_questions'] > 0 && $model['correct_answers'] >= 0 ?
                number_format(($model['correct_answers'] / max(1, $model['total_questions'])) * 100, 1) . '%' :
                '0.0%',
            $model['correct_answers'],
            $model['incorrect_answers'],
            $model['avg_prompt_time'] >= 0 ? number_format($model['avg_prompt_time'], 3) . 's' : 'N/A',
            $model['avg_predicted_time'] >= 0 ? number_format($model['avg_predicted_time'], 3) . 's' : 'N/A',
            $model['avg_prompt_eval_per_second'] >= 0 ? number_format($model['avg_prompt_eval_per_second'], 1) : 'N/A',
            $model['avg_tokens_per_second'] >= 0 ? number_format($model['avg_tokens_per_second'], 1) : 'N/A',
            $model['avg_prompt_tokens'] >= 0 ? number_format($model['avg_prompt_tokens'], 0) : 'N/A',
            $model['avg_predicted_tokens'] >= 0 ? number_format($model['avg_predicted_tokens'], 0) : 'N/A',
            $model['total_questions']
        ];
    }, $stats);
    
    // Calculate column widths with extra padding for formatted numbers
    $widths = array_map(function($col) use ($rows, $header) {
        $maxValueLength = max(array_map('strlen', array_column($rows, $col)));
        $headerLength = strlen($header[$col]);
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
        echo '| ' . str_pad($title, $widths[$i], ' ', STR_PAD_RIGHT) . ' ';
    }
    echo "|\n";
    echo str_repeat('-', $totalWidth) . "\n";
    
    // Print table rows
    foreach ($rows as $row) {
        foreach ($row as $i => $value) {
            echo '| ' . str_pad($value, $widths[$i], ' ', STR_PAD_RIGHT) . ' ';
        }
        echo "|\n";
    }
    echo str_repeat('-', $totalWidth) . "\n";

    // Fetch and display questions with zero correct answers
    $query = "
        SELECT
            question_id,
            actual_question
        FROM
            benchmark_runs
        GROUP BY
            question_id, actual_question
        HAVING
            SUM(correct) = 0
        ORDER BY
            question_id ASC;
    ";
    $questionsWithZeroCorrect = $db->getPDO()->query($query)->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($questionsWithZeroCorrect)) {
        echo "\n\033[1mQuestions with Zero Correct Answers\033[0m\n";
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

    $entry['full_prompt'] = "Question: {$entry['q']}\nInstruction: {$entry['instruction']}";
    
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
 * Validates LLM response against expected answers
 */
function validateResponse($entry, $response) {
    echo "\n\t## Expected response: " . json_encode($entry['answers']) . "\n";
    preg_match_all('/<response>(.*?)<\/response>/s', $response, $matches);
    $finalResponse = $matches[1][0] ?? '';
    
    $clean = strtolower(trim($finalResponse));
    $clean = trim($clean, '.');
    $clean = str_replace(' ', '', $clean);

    if ($entry['type'] === 'mcq') {
        $selected = preg_split('/\s*,\s*/', $clean);
        $correct = array_map('strtolower', $entry['answers']);

        if (count($selected) !== count($correct)) {
            return false;
        }
        
        foreach ($selected as $s) {
            if (!in_array($s, $correct)) {
                return false;
            }
        }
        return true;
    }

    $processedAnswers = array_map(function($answer) {
        $cleaned = strtolower(trim($answer));
        $cleaned = trim($cleaned, '.');
        return str_replace(' ', '', $cleaned);
    }, $entry['answers']);

    foreach ($processedAnswers as $pa) {
        $responseNumber = intval($clean);
        if ($responseNumber == $clean) {
            if ($clean == $pa) {
                return true;
            }
        } else {
            if (str_contains($clean, $pa)) {
                return true;
            }

            if (str_match_wildcard($clean, $pa)) {
                return true;
            }
        }
    }
    return false;
}

function extractCleanResponse($response) {
    preg_match_all('/<response>(.*?)<\/response>/s', $response, $matches);
    $finalResponse = $matches[1][0] ?? '';
    
    $clean = strtolower(trim($finalResponse));
    $clean = trim($clean, '.');
    $clean = str_replace(' ', '', $clean);

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

// ======================= Main Execution =======================

$benchmarkJsonData = loadBenchmarkJson();

// Load a Bearer token if the file exists.
$llmConnection->readBearerTokenFromFile('.bearer_token');

$models = $llmConnection->getAvailableModels();
sort($models);


if (empty($models)) {
    echo "\n\tError retrieving models list. \n";
    die(1);
}

// Reorganize DeepSeek models
[$deepseek, $others] = array_reduce($models, function($carry, $model) {
    stripos($model['id'], 'deepseek') !== false ? $carry[0][] = $model : $carry[1][] = $model;
    return $carry;
}, [[], []]);

sort($others);
$models = array_merge($others, $deepseek);

// Convert API model objects to expected format
$modelsJson = @file_get_contents('http://127.0.0.1:8855/v1/models');


if ($modelsJson === false) {
    die("\033[1;31mError: Could not retrieve models from API endpoint\033[0m\n");
}
$modelsData = json_decode($modelsJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("\033[1;31mError decoding models data: " . json_last_error_msg() . "\033[0m\n");
}
$models = array_map(function($m) {
    return ['id' => $m['id']];
}, $modelsData['data']);

// Keep test model for initial validation
// array_unshift($models, ['id' => 'Qwen2.5-7B-Instruct-1M-Q8_0_CTX-750K']);

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

usort($models, function ($a, $b) {
    return strcmp($a['id'], $b['id']);
});


// DEBUG
//$models = [['id' => 'qwen_QwQ-32B-Q8_0']];
//$models = [['id' => 'za_DeepSeek-V3-0324-UD-Q2_K_XL-CTX_1024_benchmark']];

// Main benchmark loop - test each model
foreach ($models as $modelIndex => $model) {
    $modelId = $model['id'];
    
    // Check if model progress exists in database
    $modelState = $db->getBenchmarkState($modelId);
    if ($modelState !== null) {
        echo "\n\033[1;34mResuming model $modelId from saved state.\033[0m\n";
        $modelResults = $modelState['results'];
        $correctCount = $modelState['correctCount'];
        $incorrectCount = $modelState['incorrectCount'];
        $currentQuestion = $modelState['currentQuestion'];
    } else {
        $modelResults = [];
        $correctCount = 0;
        $incorrectCount = 0;
        $currentQuestion = 0;
    }

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

    echo "\n\033[1;34m=== Model " . ($modelIndex+1) . "/" . count($models) . ": $modelId ===\033[0m\n";
    echo "Rank: " . ($modelIndex+1) . "/" . count($models) . "\n";

    $startTime = microtime(true);
    $modelStartTime = $startTime;

    // DEBUG
    $questionCountLimit = 100;

    // Process each question for the current model
    $questionsToProcess = $questionCountLimit ? array_slice($benchmarkData, 0, $questionCountLimit, true) : $benchmarkData;
    foreach ($questionsToProcess as $qIndex => $entry) {
        $currentQuestion++;
        prepareQuestion($entry);

        $questionString = $benchmarkData[$qIndex]['q'];
        $instructionString = $entry['instruction'];
        $options = json_encode($entry['shuffled_options'] ?? null);
        $expectedAnswer = implode(' || ',$benchmarkData[$qIndex]['answers']);
        echo "\nQuestion #$currentQuestion: " . $questionString . "\nInstruction: " . $instructionString . "\nExpected answer: [" . $expectedAnswer . "]\n";
        if ('null' !== $options) {
            echo "Options: $options\n";
        }

        $existingAttempts = $benchmarkJsonData[$model['id']][$qIndex] ?? [];
        $numAttempts = count($existingAttempts);
        $remainingAttempts = $totalRequiredAnswersPerQuestion - $numAttempts;

        if ($numAttempts >= $totalRequiredAnswersPerQuestion) {
            $correctAttempts = count(array_filter($existingAttempts, fn($a) => $a['correct']));
            $isCorrectOverall = $correctAttempts >= $requiredCorrectAnswers;
            echo "\nModel ID: $modelId\n";
            echo "\tExpected response: " . json_encode($entry['answers']);

            $isCorrect = $existingAttempts[0]['correct'];

            if ($isCorrect) {
                $correctCount++;
            } else {
                $incorrectCount++;
            }

            $status = $isCorrect ? 'Correct! 👍' : 'INCORRECT! 🚫';

            echo "\n\tProvided response is $status: " . $existingAttempts[0]['clean_response'] . "\n";


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
You are a helpful AI assistant programmed for concise, factual answers. Respond to questions with the minimum information required for a correct and direct answer. Do not:
- Provide context or background unless specifically requested.
- Offer opinions, speculations, or deep analysis.
- Elaborate beyond the core answer.
- Use conversational filler.
- Stick strictly to the most direct and brief response possible.
SYSTEM_PROMPT;
                
                try {
                    $llmConnection->getRolesManager()
                        ->clearMessages()
                        ->setSystemMessage('')
                        ->addMessage('user', "$systemPrompt Please answer the following question and encapsulate your final answer between <response> and </response> tags followed by <done></done> tags. If you need to reason or explain you may do that BEFORE the response tags. Inside the response tags include only the actual, direct, response without any explanations. Be as concise as possible.\nE.G. <response>Your answer to the question here without any explanations.</response><done></done>\n\n{$entry['full_prompt']}");

                    $parameters = $llmConnection->getDefaultParameters();
                    $llmConnection->setParameter('n_predict', MAX_OUTPUT_CONTEXT);
                    $stopWords = $llmConnection->getParameter("stop") ?? [];
                    $stopWords[] = '<done>';
                    $stopWords[] = '/<done>';
                    $llmConnection->setParameter('stop', $stopWords);

                    $response = $llmConnection->queryPost([], function($chunk) {
                        echo $chunk;
                    });

                    if (FALSE === $response) {
                        $content = 'Guzzle timeout ERROR: ';
                        $reasoning = '';
                        $isCorrect = false;
                        $responseTime = 0;
                        $verboseResponse = '';
                        $timingData = [];
                    } else {
                        $content = trim($response->getLlmResponse());
                        $rawContent = json_decode($llmConnection->getResponse()->getRawContent(), TRUE);
                        $verboseResponse = $rawContent['__verbose'] ?? [];
                        
                        // Parse timing data
                        $timingData = $llmConnection->getQuerytimings() ?? [];
                        echo PHP_EOL . json_encode($timingData) . "\n";
                        
                        // Calculate token generation speeds
                        $promptSpeed = 0;
                        $genSpeed = 0;
                        
                        $promptSpeed = $timingData['prompt_per_second'];
                      
                        $genSpeed = $timingData['predicted_per_second'];
                        
                        
                        $reasoning = $llmConnection->getThinkContent();
                        $isCorrect = validateResponse($entry, $content);
                        $responseTime = $llmConnection->getLastQueryMicrotime();
                        
                        $status = $isCorrect ? 'Correct! 👍' : 'INCORRECT! 🚫';
                        echo "\n\t### $status \n";

                        // Check speed limits after minimum attempts
                        $attemptCount = count($existingAttempts) + 1;
                        if ($attemptCount >= MIN_ANSWERS_FOR_EVALUATION && !$ignoreSpeedLimits) {
                            if ($promptSpeed < MIN_PROMPT_SPEED || $genSpeed < MIN_TOKEN_GENERATION) {
                                echo "\n\033[1;31mSpeed limit violation - prompt: {$promptSpeed}t/s, generation: {$genSpeed}t/s\033[0m\n";
                                echo "Skipping to next model...\n";
                                break 2; // Break out of both question and model loops
                            }
                        }
                    }

                } catch (Exception $e) {
                    $content = 'ERROR: ' . $e->getMessage();
                    $reasoning = '';
                    $isCorrect = false;
                    $responseTime = 0;
                    $timingData = [];
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
                            $timingData['prompt_n'] ?? null,
                            $timingData['prompt_ms'] ? $timingData['prompt_ms'] / 1000 : null,
                            $timingData['predicted_n'] ?? null,
                            $timingData['predicted_ms'] ? $timingData['predicted_ms'] / 1000 : null,
                            $timingData['predicted_per_second'] ?? null,
                            $questionString,
                            $options,
                            $expectedAnswer,
                            $cleanResponse,
                            isset($timingData['prompt_per_second']) && $timingData['prompt_per_second'] > 0
                                ? $timingData['prompt_per_second']
                                : null,
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
                    $db->saveBenchmarkState(
                        $modelId,
                        $modelResults,
                        $correctCount,
                        $incorrectCount,
                        $currentQuestion
                    );
                    
                    $db->commit();
                    
                    // Display updated stats after each question
                    $currentStats = $db->getModelStats($modelId);
                    if ($currentStats) {
                        echo "\n\033[1;34mCurrent Stats: \033[0m";
                        echo "\033[1;32mCorrect: {$currentStats['correct_answers']} (" . number_format($currentStats['percentage_correct'], 1) . "%)\033[0m, ";
                        echo "\033[1;31mFailed: {$currentStats['incorrect_answers']}\033[0m, ";
                        echo "\033[1;33mAvg Time: " . number_format($currentStats['avg_response_time'], 3) . "s, ";
                        echo "Tokens/s: " . number_format($currentStats['avg_tokens_per_second'], 1) . "\033[0m\n";
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    echo "\nError saving benchmark data: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

// Display final stats if any models were processed
if (count($models) > 0) {
    displayModelStats($db);
}
