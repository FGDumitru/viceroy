<?php
require_once '../vendor/autoload.php';
use Viceroy\Connections\llamacppOAICompatibleConnection;

// ======================= Configuration =======================
$llmConnection = new llamacppOAICompatibleConnection();
$llmConnection->setGuzzleConnectionTimeout(PHP_INT_MAX);

$results = [];
$benchmarkJsonFile = 'benchmark_runs.json'; // Changed from INI to JSON
$resetBenchmark = in_array('--reset', $argv);
$filterModels = [];
$totalRequiredAnswersPerQuestion = 1;
$requiredCorrectAnswers = 1;

// Parse command-line parameters
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--model=')) {
        $filterModels[] = substr($arg, 8);
    } elseif (str_starts_with($arg, '--total-required-answers=')) {
        $totalRequiredAnswersPerQuestion = (int) substr($arg, strlen('--total-required-answers='));
    } elseif (str_starts_with($arg, '--required-correct-answers=')) {
        $requiredCorrectAnswers = (int) substr($arg, strlen('--required-correct-answers='));
    }
}

// DEBUG
//$filterModels = ['*qwen*1M*'];

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
function generateProgressBar($current, $total, $correct, $wrong) {
    $done = $correct + $wrong;
    $remaining = $total - $done;
    $bar = "[$done/$total ";
    $bar .= str_repeat('👍', $correct);
    $bar .= str_repeat('🚫', $wrong);
    $bar .= str_repeat('-', $remaining) . "]";
    return $bar;
}

function prepareQuestion(&$entry) {
    if ($entry['type'] === 'mcq' && !empty($entry['options'])) {
        $options = $entry['options'];
        uksort($options, fn() => rand() - getrandmax()/2);
        $entry['shuffled_options'] = $options;
    }
    $entry['full_prompt'] = "Question: {$entry['q']}\nInstruction: {$entry['instruction']}";
    if (isset($entry['shuffled_options'])) {
        $entry['full_prompt'] .= "\nOptions:\n" . implode("\n",
                array_map(fn($k, $v) => "$k) $v",
                    array_keys($entry['shuffled_options']),
                    $entry['shuffled_options'])
            );
    }
}

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

    // Process each answer for non-MCQ types
    $processedAnswers = array_map(function($answer) {
        $cleaned = strtolower(trim($answer));
        $cleaned = trim($cleaned, '.');
        return str_replace(' ', '', $cleaned);
    }, $entry['answers']);

    foreach ($processedAnswers as $pa) {
        if (str_contains($clean, $pa)) {
            return true;
        }
    }

    return false;
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

$models = $llmConnection->getAvailableModels();

if (empty($models)) {
    echo "\n\tError retrieving models list. \n";
    die(1);
}

// Reorganize DeepSeek models
[$deepseek, $others] = array_reduce($models, function($carry, $model) {
    stripos($model, 'deepseek') !== false ? $carry[0][] = $model : $carry[1][] = $model;
    return $carry;
}, [[], []]);

usort($deepseek, fn($a, $b) => (stripos($a, '2bit') ? 1 : -1));

sort($others);

$models = array_merge($others, $deepseek);

// Main benchmark loop
foreach ($models as $modelIndex => $model) {
    // Apply model filtering
    if (!empty($filterModels)) {
        $match = false;
        foreach ($filterModels as $filter) {
            if (fnmatch(strtolower($filter), strtolower($model))) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            echo "\n\033[1;33mSkipping model (does not match filter): $model\033[0m\n";
            continue;
        }
    }

    $llmConnection->setLLMmodelName($model);
    $modelResults = [];
    $correctCount = 0;
    $incorrectCount = 0;
    $currentQuestion = 0;

    echo "\n\033[1;34m=== Model " . ($modelIndex+1) . "/" . count($models) . ": $model ===\033[0m\n";

    $startTime = microtime(true);
    $modelStartTime = $startTime;

    foreach ($benchmarkData as $qIndex => $entry) {

        $currentQuestion++;
        prepareQuestion($entry);

        $questionString = $benchmarkData[$qIndex]['q'];

        echo "\nQuestion: " . $questionString . "\n";

        $existingAttempts = $benchmarkJsonData[$model][$qIndex] ?? []; // Now uses JSON data
        $numAttempts = count($existingAttempts);
        $remainingAttempts = $totalRequiredAnswersPerQuestion - $numAttempts;

        if ($numAttempts >= $totalRequiredAnswersPerQuestion) {
            $correctAttempts = count(array_filter($existingAttempts, fn($a) => $a['correct']));
            $isCorrectOverall = $correctAttempts >= $requiredCorrectAnswers;
            echo "\nExpected response: " . json_encode($entry['answers']);
            echo "\nProvided response: " . $existingAttempts[0]['response'] . "\n";

        } else {
            // We need a sleep in order to not lock up the GPU
            sleep(2);

            for ($i = 0; $i < $remainingAttempts; $i++) {
                // Display progress
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

                echo "\n\033[1mQuestion $currentQuestion/$totalQuestions | Model $model\033[0m\n";
                echo "Progress: $progress\n";
                echo "Time Passed: " . gmdate("H:i:s", $timePassed) . "\n";
                echo "ETA: " . gmdate("H:i:s", $eta) . "\n";

                try {
                    $llmConnection->getRolesManager()
                        ->clearMessages()
                        ->setSystemMessage('Answer concisely and accurately.')
                        ->addMessage('user', "Please answer the following question and encapsulate your final answer between <response> and </response> tags.\n\n{$entry['full_prompt']}");

                    $response = $llmConnection->queryPost();
                    $content = trim($response->getLlmResponse());
                    $reasoning = $llmConnection->getThinkContent();
                    $isCorrect = validateResponse($entry, $content);
                    $responseTime = $llmConnection->getLastQueryMicrotime();
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
                    'response_time' => $responseTime
                ];

                // After updating attempts
                $benchmarkJsonData[$model][$qIndex] = $existingAttempts;
                saveBenchmarkJson($benchmarkJsonData); // Save to JSON after each attempt
                //

                $status = $isCorrect ? '👍' : '🚫';
                echo "\n\t### Attempt " . ($numAttempts + $i + 1) . "/$totalRequiredAnswersPerQuestion $status\n";
                echo "\tResponse: $content\n";
            }

            $correctAttempts = count(array_filter($existingAttempts, fn($a) => $a['correct']));
            $isCorrectOverall = $correctAttempts >= $requiredCorrectAnswers;
        }

        if ($isCorrectOverall) {
            $correctCount++;
        } else {
            $incorrectCount++;
        }

        echo "\t### Question result: " . ($isCorrectOverall ? '👍 CORRECT' : '🚫 INCORRECT') . " ($correctAttempts/$totalRequiredAnswersPerQuestion correct attempts) ###\n";
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
    echo "\n\033[32mModel Complete! [ $model ]\033[0m";
    echo "\nFinal Score: $correctCount/$totalQuestions";
    echo "\nTime Passed: " . gmdate("H:i:s", $timePassed);
    echo "\n" . generateProgressBar($totalQuestions, $totalQuestions, $correctCount, $totalQuestions - $correctCount) . "\n";

    $results[$model] = [
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

foreach ($results as $model => $modelData) {
    $totalTime = 0;
    $totalAttempts = 0;

    foreach ($modelData['details'] as $question) {
        foreach ($question['attempts'] as $attempt) {
            $totalTime += $attempt['response_time'];
            $totalAttempts++;
        }
    }

    $avgTime = $totalAttempts > 0 ? $totalTime / $totalAttempts : 0;
    $correct = $modelData['score'];
    $incorrect = $totalQuestions - $correct;
    $percentage = $totalQuestions > 0 ? ($correct / $totalQuestions) * 100 : 0;

    // Format numbers
    $totalTimeFmt = number_format($totalTime, 2);
    $avgTimeFmt = number_format($avgTime, 2);
    $percentageFmt = number_format($percentage, 1) . '%';

    // Add to CSV
    $csvLines[] = implode("\t", [
        $model,
        $totalTimeFmt,
        $avgTimeFmt,
        $correct,
        $incorrect,
        $percentageFmt
    ]);

    // Add to terminal output
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