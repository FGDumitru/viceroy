<?php
require_once '../vendor/autoload.php';
use Viceroy\Connections\llamacppOAICompatibleConnection;

// ======================= Configuration =======================
$llmConnection = new llamacppOAICompatibleConnection();
$llmConnection->setGuzzleConnectionTimeout(PHP_INT_MAX);

$results = [];
$benchmarkIniFile = 'benchmark.ini';
$resetBenchmark = in_array('--reset', $argv);
$filterModels = [];


// Parse --model parameters
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--model=')) {
        $filterModels[] = substr($arg, 8); // Extract the model pattern
    }
}

// DEBUG
//$filterModels = ['*qwen*'];

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

function loadBenchmarkIni() {
    global $benchmarkIniFile;
    if (!file_exists($benchmarkIniFile)) {
        return [];
    }
    return parse_ini_file($benchmarkIniFile, true);
}

function saveBenchmarkIni($data) {
    global $benchmarkIniFile;
    $content = '';
    foreach ($data as $model => $questions) {
        $content .= "[$model]\n";
        foreach ($questions as $qIndex => $result) {
            $content .= "$qIndex=" . json_encode($result) . "\n";
        }
        $content .= "\n";
    }
    file_put_contents($benchmarkIniFile, $content);
}

// ======================= Main Execution =======================
if ($resetBenchmark && file_exists($benchmarkIniFile)) {
    unlink($benchmarkIniFile);
}

$benchmarkIniData = loadBenchmarkIni();

$models = $llmConnection->getAvailableModels();

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



        // Check if already answered
        if (isset($benchmarkIniData[$model][$qIndex])) {
            echo "\n\033[1mQuestion $currentQuestion/$totalQuestions | Model $model\033[0m\n";
            $question = $benchmarkData[$qIndex]['q'];
            echo "\t$question\n\n";

            $result = json_decode($benchmarkIniData[$model][$qIndex], true);
            $isCorrect = $result['correct'];
            $content = $result['response'];
            $reasoning = $result['reasoning'] ?? '';
            $responseTime = $result['response_time'] ?? 0;
        } else {
            sleep(2);
            // Display progress
            $progress = generateProgressBar(
                $currentQuestion,
                $totalQuestions,
                $correctCount,
                $currentQuestion - $correctCount - 1
            );

            // Calculate time passed and ETA for the current model
            $currentTime = microtime(true);
            $timePassed = $currentTime - $modelStartTime;

            if ($currentQuestion > 1) {
                $averageTimePerQuestion = $timePassed / ($currentQuestion - 1);
            } else {
                $averageTimePerQuestion = 0;
            }

            $remainingQuestions = $totalQuestions - $currentQuestion + 1;
            $eta = $averageTimePerQuestion * $remainingQuestions;

            echo "\n\033[1mQuestion $currentQuestion/$totalQuestions | Model $model\033[0m\n";
            echo "Prompt: {$entry['full_prompt']}\n";
            echo "Progress: $progress\n";
            echo "Time Passed: " . gmdate("H:i:s", $timePassed) . "\n";
            echo "ETA: " . gmdate("H:i:s", $eta) . "\n";

            $question = "Please answer the following question and encapsulate your final answer between <response> and </response> tags.\n\n" . $entry['full_prompt'];

            try {
                $llmConnection->getRolesManager()
                    ->clearMessages()
                    ->setSystemMessage('Answer concisely and accurately.')
                    ->addMessage('user', $question);

                $response = $llmConnection->queryPost();
                $content = trim($response->getLlmResponse());

                try {
                    $reasoning = $llmConnection->getThinkContent();
                } catch (Exception $e) {
                    $reasoning = '';
                }

            } catch (Exception $e) {
                $content = 'ERROR: ' . $e->getMessage();
                $reasoning = '';
            }

            $isCorrect = validateResponse($entry, $content);
            $responseTime = $llmConnection->getLastQueryMicrotime();

            // Save result
            $benchmarkIniData[$model][$qIndex] = json_encode([
                'response' => $content,
                'correct' => $isCorrect,
                'reasoning' => $reasoning,
                'response_time' => $responseTime
            ]);
            saveBenchmarkIni($benchmarkIniData);
        }

        if ($isCorrect) {
            $correctCount++;
        } else {
            $incorrectCount++;
        }

        $correctAnswer = $isCorrect ? 'a correct 👍 ' : 'an incorrect 🚫 ';
        echo "\n\t ###  LLM gave $correctAnswer response: {$content} ### \n\n\t ### Correct count now: $correctCount Incorrect count now: $incorrectCount ###\n\n";

        // Store results
        $modelResults[] = [
            'question' => $entry['q'],
            'type' => $entry['type'],
            'response' => $content,
            'correct' => $isCorrect,
            'expected' => $entry['answers'],
            'options' => $entry['shuffled_options'] ?? null,
            'reasoning' => $reasoning,
            'response_time' => $responseTime
        ];

        // Clear previous progress line
        echo "\033[1A\033[K"; // Move up and clear line
        //sleep(1);
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
