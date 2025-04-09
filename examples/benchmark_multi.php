<?php
/**
 * benchmark_multi.php - Multi-Model Performance Benchmark
 *
 * This script provides a comprehensive benchmarking framework for LLM models
 * to evaluate their accuracy and performance across multiple questions.
 *
 * Key Features:
 * 1. Model Filtering: Supports pattern-based inclusion/exclusion of models
 * 2. Multi-Attempt Validation: Tracks multiple response attempts per question
 * 3. Detailed Metrics: Captures timing data and correctness statistics
 * 4. Output Formats: Produces both human-readable CSV and machine-readable JSON
 * 5. Error Handling: Gracefully manages API timeouts and exceptions
 *
 * Usage:
 * php benchmark_multi.php [options]
 *
 * Options:
 * --model=pattern       Filter models using shell-style wildcards (e.g. "*-13b")
 * --ignore=pattern      Exclude models matching pattern (e.g. "llama*")
 * --reset               Clear all previous benchmark data
 * --total-required-answers=N  Number of attempts per question (default:1)
 * --required-correct-answers=N Minimum correct answers required (default:1)
 *
 * Output Files:
 * - benchmark_results.json: Full model performance details
 * - benchmark_stats.csv: Summary table for quick comparison
 * - benchmark_runs.json: Raw attempt history for debugging
 */
require_once '../vendor/autoload.php';
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

// Maximum number of tokens per response.
const MAX_OUTPUT_CONTEXT = 8192;

// ======================= Configuration =======================
// Initialize connection with maximum possible timeout
$llmConnection = new OpenAICompatibleEndpointConnection();
$llmConnection->setConnectionTimeout(3600 * 4); // 4h timeout for a single response.

$results = [];
$benchmarkJsonFile = 'benchmark_runs.json'; // Changed from INI to JSON
$resetBenchmark = in_array('--reset', $argv);
$filterModels = [];
$ignoredModels = [];

$totalRequiredAnswersPerQuestion = 1;
$requiredCorrectAnswers = 1;

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
}


// Debug output for --model parameter
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
 * Generates a visual progress bar with emoji indicators
 *
 * @param int $current Current question number
 * @param int $total Total questions
 * @param int $correct Correct answers count
 * @param int $wrong Wrong answers count
 * @return string Formatted progress bar
 */
function generateProgressBar($current, $total, $correct, $wrong) {
    // Calculate progress metrics
    $done = $correct + $wrong;
    $remaining = $total - $done;

    // Build visual progress bar with emojis
    $bar = "[$done/$total ";
    $bar .= str_repeat('👍', $correct);   // Correct answers
    $bar .= str_repeat('🚫', $wrong);     // Incorrect answers
    $bar .= str_repeat('-', $remaining);  // Remaining questions
    $bar .= "]";

    return $bar;
}

/**
 * Prepares question data for LLM processing
 *
 * - Shuffles MCQ options
 * - Builds full prompt string
 *
 * @param array &$entry Question data (modified in-place)
 */
function prepareQuestion(&$entry) {
    // Shuffle multiple-choice options if present
    if ($entry['type'] === 'mcq' && !empty($entry['options'])) {

        // Override the instruction field for multiple case questions.
        $entry['instruction'] = "Select the correct answer(s) by providing the corresponding letter(s) as a comma-separated list (e.g., \"h\" for a single answer or \"i, j\" for multiple answers).";

        $options = $entry['options'];
        // Randomize option order using uksort with random comparator
        uksort($options, fn() => rand() - getrandmax()/2);
        $entry['shuffled_options'] = $options;
    }

    // Build complete prompt string
    $entry['full_prompt'] = "Question: {$entry['q']}\nInstruction: {$entry['instruction']}";
    
    // Add formatted options if present
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
 *
 * Handles both MCQ and free-form responses
 * Extracts content between <response> tags
 * Normalizes for comparison (lowercase, trim, etc.)
 *
 * @param array $entry Question data with expected answers
 * @param string $response LLM response string
 * @return bool True if response matches any expected answer
 */
function validateResponse($entry, $response) {
    // Display expected answers for debugging
    echo "\n\t## Expected response: " . json_encode($entry['answers']) . "\n";

    // Extract final response from XML tags
    preg_match_all('/<response>(.*?)<\/response>/s', $response, $matches);
    $finalResponse = $matches[1][0] ?? '';
    
    // Normalize response for comparison
    $clean = strtolower(trim($finalResponse));
    $clean = trim($clean, '.'); // Remove trailing punctuation
    $clean = str_replace(' ', '', $clean); // Remove whitespace

    // Handle multiple-choice questions
    if ($entry['type'] === 'mcq') {
        $selected = preg_split('/\s*,\s*/', $clean); // Split comma-separated answers
        $correct = array_map('strtolower', $entry['answers']);

        // Check answer count matches and all answers are correct
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

    // Handle free-form answers
    $processedAnswers = array_map(function($answer) {
        $cleaned = strtolower(trim($answer));
        $cleaned = trim($cleaned, '.');
        return str_replace(' ', '', $cleaned);
    }, $entry['answers']);

    // Check for numeric equality or substring match
    foreach ($processedAnswers as $pa) {
        $responseNumber = intval($clean);
        if ($responseNumber == $clean) { // Check if numeric response
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

function str_match_wildcard(string $haystack, string $pattern, bool $caseInsensitive = false): bool {
    // Convert wildcards to regex equivalents, but leave ^ and $ untouched
    $regex = preg_replace_callback('/[^*?^$]+/', function ($matches) {
        return preg_quote($matches[0], '/');
    }, $pattern);

    $regex = str_replace(['*', '?'], ['.*', '.'], $regex);

    // Final regex pattern
    $flags = $caseInsensitive ? 'i' : '';
    return preg_match("/{$regex}/{$flags}", $haystack) === 1;
}


function loadBenchmarkJson() {
    global $benchmarkJsonFile;
    if (!file_exists($benchmarkJsonFile)) {
        return [];
    }
    $jsonData = file_get_contents($benchmarkJsonFile);
    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("\033[1;31mError decoding benchmark runs: " . json_last_error_msg() . "\033[0m\n");
    }
    return $data;
}

function saveBenchmarkJson($data) {
    global $benchmarkJsonFile;
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($benchmarkJsonFile, $jsonData);
}

// ======================= Main Execution =======================
if ($resetBenchmark && file_exists($benchmarkJsonFile)) {
    unlink($benchmarkJsonFile);
}

$benchmarkJsonData = loadBenchmarkJson(); // Renamed from $benchmarkIniData

// Load a Bearer token if the file exists.
$llmConnection->readBearerTokenFromFile('.bearer_token');

$models = $llmConnection->getAvailableModels();

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

// Main benchmark loop - test each model
foreach ($models as $modelIndex => $model) {
    // Initialize model-specific counters and timers

    $modelId = $model['id'];

    // Apply model filtering if specified
    if (!empty($filterModels)) {
        $match = false;
        // Check if model matches any filter pattern
        foreach ($filterModels as $filter) {
            if (fnmatch(strtolower($filter), strtolower($modelId))) {
                $match = true;
                break;
            }
        }
        // Skip non-matching models
        if (!$match) {
            echo "\n\033[1;33mSkipping model (does not match filter): $modelId\033[0m\n";
            continue;
        }
    }

    // Apply model filtering
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

    $startTime = microtime(true);
    $modelStartTime = $startTime;

    // Process each question for the current model
    foreach ($benchmarkData as $qIndex => $entry) {
        $currentQuestion++; // Track question number
        prepareQuestion($entry); // Shuffle options and build full prompt

        $questionString = $benchmarkData[$qIndex]['q'];
        $instructionString = $entry['instruction'];
        $options = json_encode($entry['shuffled_options'] ?? null);
        $expectedAnswer = implode(' || ',$benchmarkData[$qIndex]['answers']);
        echo "\nQuestion #$currentQuestion: " . $questionString . "\nInstruction: " . $instructionString . "\nExpected answer: [" . $expectedAnswer . "]\n";
        if ('null' !== $options) {
            echo "Options: $options\n";
        }

        $existingAttempts = $benchmarkJsonData[$model['id']][$qIndex] ?? []; // Now uses JSON data
        $numAttempts = count($existingAttempts);
        $remainingAttempts = $totalRequiredAnswersPerQuestion - $numAttempts;

        if ($numAttempts >= $totalRequiredAnswersPerQuestion) {
            $correctAttempts = count(array_filter($existingAttempts, fn($a) => $a['correct']));
            $isCorrectOverall = $correctAttempts >= $requiredCorrectAnswers;
            echo "\nExpected response: " . json_encode($entry['answers']);
            echo "\nProvided response: " . $existingAttempts[0]['response'] . "\n";

        } else {
            // We need a sleep in order to not lock up the GPU
            // sleep(2);

            // Make required number of attempts per question
            for ($i = 0; $i < $remainingAttempts; $i++) {
                // Calculate and display progress metrics
                $done = $correctCount + $incorrectCount;
                $progress = generateProgressBar(
                    $currentQuestion,
                    $totalQuestions,
                    $correctCount,
                    $incorrectCount
                );

                $currentTime = microtime(true);
                $timePassed = $currentTime - $modelStartTime;
                $averageTimePerQuestion = $done > 0 ? $timePassed / $done : 0;
                $eta = $averageTimePerQuestion * ($totalQuestions - $done);

                echo "\n\033[1mQuestion $currentQuestion/$totalQuestions | Model $modelId\033[0m\n";
                echo "Progress: $progress\n";
                echo "Time Passed: " . gmdate("H:i:s", (int) $timePassed) . "\n";
                echo "ETA: " . gmdate("H:i:s", (int) $eta) . "\n";

                $systemPrompt = <<<SYSTEM_PROMPT
**System Prompt: High-Fidelity Benchmark Mode Engaged**

**Objective:** Maximum Accuracy and Strict Instruction Compliance.

**Operational Parameters:**
1.  **Deliberate Processing:** You are operating in a mode where time is not a primary constraint. Take sufficient time to fully process the request and formulate your response. Do not rush.
2.  **Deep Reasoning Required:** Engage in thorough, step-by-step thinking. Analyze the nuances of the request, consider potential interpretations, and evaluate information critically before generating an answer.
3.  **Accuracy is Paramount:** Prioritize correctness, precision, and factual accuracy above all else. Base your response on verified information within your knowledge base. If uncertain, state the limitations.
4.  **Meticulous Instruction Following:** Adhere *exactly* to all instructions provided in the subsequent task. Pay close attention to formatting, scope, constraints, and any specified requirements. Deviations are penalized in this mode.


SYSTEM_PROMPT;


                try {
                    // Configure the LLM conversation context
                    $llmConnection->getRolesManager()
                        ->clearMessages()
                        ->setSystemMessage('') // leave it empty, some models don't use it. e.g. Gemma
                        ->addMessage('user', "$systemPrompt Please answer the following question and encapsulate your final answer between <response> and </response> tags followed by <done></done> tags. If you need to reason or explain you may do that BEFORE the response tags. Inside the response tags include only the actual, direct, response without any explanations. Be as concise as possible.\nE.G. <response>Your answer to the question here without any explanations.</response><done></done>\n\n{$entry['full_prompt']}");

                    // Set deterministic parameters for reproducible results
                    $parameters = $llmConnection->getDefaultParameters();
                    $parameters['seed'] = 0.3; // Fixed seed for consistency
                    $llmConnection->setParameter('n_predict', MAX_OUTPUT_CONTEXT);
                    $llmConnection->setParameter('seed', 0.35);
                    $stopWords = $llmConnection->getParameter("stop") ?? [];
                    $stopWords[] = '<done>';
                    $stopWords[] = '/<done>';
                    $llmConnection->setParameter('stop', $stopWords);


                    // Execute the query and get response
                    $response = $llmConnection->queryPost([], function($chunk) {
                        echo $chunk;
                    });

                    if (FALSE === $response) { // we had an error

                        $content = 'Guzzle timeout ERROR: ';
                        $reasoning = '';
                        $isCorrect = false;
                        $responseTime = 0;
                        $verboseResponse = '';
                    } else {
                        $content = trim($response->getLlmResponse());
                        $rawContent = json_decode($llmConnection->getResponse()->getRawContent(), TRUE);

                        $verboseResponse = $rawContent['__verbose'] ?? [];
                        echo PHP_EOL . json_encode($llmConnection->getQuerytimings() ?? []) . "\n";
                        $reasoning = $llmConnection->getThinkContent();
                        $isCorrect = validateResponse($entry, $content);
                        $responseTime = $llmConnection->getLastQueryMicrotime();

                    }

                } catch (Exception $e) {
                    $content = 'ERROR: ' . $e->getMessage();
                    $reasoning = '';
                    $isCorrect = false;
                    $responseTime = 0;
                }

                $existingAttempts[] = [
                    'response' => $content,
                    'correct' => $isCorrect,
                    'reasoning' => empty($reasoning) ? '""' : $reasoning,
                    'response_time' => $responseTime,
                    "question_no" => $currentQuestion,
                    'verbose' => json_encode($verboseResponse ?? ''),
                ];

                // After updating attempts
                $benchmarkJsonData[$modelId][$qIndex] = $existingAttempts;
                saveBenchmarkJson($benchmarkJsonData); // Save to JSON after each attempt
                //

                $status = $isCorrect ? '👍' : '🚫';
                echo "\n\t### Attempt " . ($numAttempts + $i + 1) . "/$totalRequiredAnswersPerQuestion $status\n";
            }

            $correctAttempts = count(array_filter($existingAttempts, fn($a) => $a['correct']));
            $isCorrectOverall = $correctAttempts >= $requiredCorrectAnswers;
        }

        // Update overall correctness counters
        if ($isCorrectOverall) {
            $correctCount++; // Increment correct tally
        } else {
            $incorrectCount++; // Increment incorrect tally
        }

        echo "\t### Question result: " . ($isCorrectOverall ? '👍 CORRECT' : '🚫 INCORRECT') . " ($correctCount correct and $incorrectCount incorrect ) ###\n";
        echo "\tCurrent time: " . date('Y-m-d H:i:s') . "\n\n";

        $modelResults[] = [
            'question' => $entry['q'],
            'attempts' => $existingAttempts,
            'overall_correct' => $isCorrectOverall,
            'correct_attempts' => $correctAttempts,
            'required_attempts' => $totalRequiredAnswersPerQuestion,
            'required_correct' => $requiredCorrectAnswers
        ];


    }

    // Final model summary
    $endTime = microtime(true);
    $timePassed = $endTime - $modelStartTime;
    echo "\n\033[32mModel Complete! [ $modelId ]\033[0m";
    echo "\nFinal Score: $correctCount/$totalQuestions";
    echo "\nTime Passed: " . gmdate("H:i:s", $timePassed);
    echo "\n" . generateProgressBar($totalQuestions, $totalQuestions, $correctCount, $totalQuestions - $correctCount) . "\n";

    $results[$modelId] = [
        'score' => $correctCount,
        'details' => $modelResults
    ];
}

// Save results
file_put_contents('benchmark_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n\033[1;32mBenchmark complete! Results saved to benchmark_results.json\033[0m\n";


// ======================= Statistics Generation =======================
$totalQuestions = count($benchmarkData);
$csvFile = 'benchmark_stats.csv';

// Prepare CSV header
$csvHeader = [
    'Model',
    'Total Time (s)',
    'Avg Time per Answer (s)',
    'Correct Answers',
    'Incorrect Answers',
    'Correct Percentage'
];

// Prepare CSV content and terminal output
$csvLines = [implode("\t", $csvHeader)];
$terminalOutput = "\n\033[1;36m=== Benchmark Summary ===\033[0m\n";

// Generate summary data for each model
foreach ($results as $model => $modelData) {
    // Calculate total time and attempts
    $totalTime = 0;
    $totalAttempts = 0;
    
    foreach ($modelData['details'] as $question) {
        foreach ($question['attempts'] as $attempt) {
            $totalTime += $attempt['response_time'];
            $totalAttempts++;
        }
    }

    // Compute average time per attempt
    $avgTime = $totalAttempts > 0 ? $totalTime / $totalAttempts : 0;
    
    // Calculate correctness metrics
    $correct = $modelData['score'];
    $incorrect = $totalQuestions - $correct;
    $percentage = $totalQuestions > 0 ? ($correct / $totalQuestions) * 100 : 0;

    // Format numerical values for display
    $totalTimeFmt = number_format($totalTime, 2);
    $avgTimeFmt = number_format($avgTime, 2);
    $percentageFmt = number_format($percentage, 1) . '%';

    // Prepare CSV row
    $csvLines[] = implode("\t", [
        $model,
        $totalTimeFmt,
        $avgTimeFmt,
        $correct,
        $incorrect,
        $percentageFmt
    ]);

    // Format terminal output line
    $terminalOutput .= sprintf(
        "\033[1;33m%-40s\033[0m %8ss  %8ss  %3d/%-3d  %6s\n",
        $model,
        $totalTimeFmt,
        $avgTimeFmt,
        $correct,
        $totalQuestions,
        $percentageFmt
    );
}

// Save CSV file
file_put_contents($csvFile, implode(PHP_EOL, $csvLines));

// Display terminal summary
echo $terminalOutput;
echo "\033[1;36mStatistics saved to $csvFile\033[0m\n";

// Update benchmark results with expected answers
$finalResults = json_decode(file_get_contents('benchmark_results.json'), true);
foreach ($finalResults as $model => &$modelResults) {
    foreach ($modelResults['details'] as $idx => &$detail) {
        $detail['expected'] = $benchmarkData[$idx]['answers'];
    }
}
file_put_contents('benchmark_results.json', json_encode($finalResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\033[1;36mUpdated benchmark_results.json with expected answers\033[0m\n";
