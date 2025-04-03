<?php
/**
 * benchmark_simple.php - LLM Benchmarking Tool (Simplified Version)
 *
 * This script evaluates LLM performance through structured testing:
 * 1. Single-model evaluation
 * 2. Accuracy and speed measurement
 * 3. Immediate feedback system
 * 4. Modular test case architecture
 *
 * Key Features:
 * - Tests 100+ categorized questions (Knowledge/Math/Translations)
 * - Measures response time in microseconds
 * - Tracks correct/incorrect responses
 * - Supports multiple LLM models
 * - Human-readable output format
 *
 * Architecture Overview:
 * - Test cases stored in $benchmarkData array
 * - Model iteration via foreach loop
 * - Response validation using strict string comparison
 * - Performance metrics displayed per model
 *
 * Usage Instructions:
 * 1. Ensure proper API credentials are configured
 * 2. Run via command line: php benchmark_simple.php
 * 3. Results displayed in terminal
 *
 * Customization Options:
 * - Modify $benchmarkData array for different questions
 * - Adjust models in $models array
 * - Change timeout values in setGuzzleConnectionTimeout()
 *
 * Comparison to benchmark_multi.php:
 * - Simplified interface for quick testing
 * - Limited to single-threaded execution
 * - No output formatting options
 */
require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

$llmConnection = new OpenAICompatibleEndpointConnection();

// Timeout usage example, wait 5 minutes before timing out.
$llmConnection->setGuzzleConnectionTimeout(PHP_INT_MAX);




$models = $llmConnection->getAvailableModels();
var_dump($models);

$benchmarkData = [
    // === Knowledge Base (20 questions) ===
    // Tests general world knowledge and cultural references
    ['q' => 'Capital of Australia? Single word.', 'a' => 'Canberra'],
    ['q' => 'Inventor of the telephone? Single word.', 'a' => 'Bell'],
    ['q' => 'Author of "1984"? Single word.', 'a' => 'Orwell'],
    ['q' => 'Largest planet? Single word.', 'a' => 'Jupiter'],
    ['q' => 'Year WWII ended? Single word.', 'a' => '1945'],
    ['q' => 'Currency of Japan? Single word.', 'a' => 'Yen'],
    ['q' => 'Longest river? Single word.', 'a' => 'Nile'],
    ['q' => 'Element with symbol Fe? Single word.', 'a' => 'Iron'],
    ['q' => 'First woman in space? Single word.', 'a' => 'Tereshkova'],
    ['q' => 'Capital of Canada? Single word.', 'a' => 'Ottawa'],
    ['q' => 'Founder of Microsoft? Single word.', 'a' => 'Gates'],
    ['q' => 'Largest desert? Single word.', 'a' => 'Antarctica'],
    ['q' => 'Year of Chernobyl disaster? Single word.', 'a' => '1986'],
    ['q' => 'Author of "The Odyssey"? Single word.', 'a' => 'Homer'],
    ['q' => 'First element on the periodic table? Single word.', 'a' => 'Hydrogen'],
    ['q' => 'Capital of Brazil? Single word.', 'a' => 'Brasília'],
    ['q' => 'Inventor of the light bulb? Single word.', 'a' => 'Edison'],
    ['q' => 'Oldest human organ? Single word.', 'a' => 'Liver'],
    ['q' => 'Fastest land animal? Single word.', 'a' => 'Cheetah'],
    ['q' => 'Smallest country? Single word.', 'a' => 'Vatican'],
    
    // === Mathematical Reasoning (20 questions) ===
    // Tests numerical literacy and problem-solving skills
    ['q' => '15 squared? Single word.', 'a' => '225'],
    ['q' => 'Prime numbers between 30-40? Count.', 'a' => '2'],
    ['q' => 'Simplify 8! divided by 7!. Single word.', 'a' => '8'],
    ['q' => 'Area of circle with radius 3 (π=3.14). Single word.', 'a' => '28.26'],
    ['q' => 'Next Fibonacci number after 0,1,1,2,3,5. Single word.', 'a' => '8'],
    ['q' => 'Positive square root of 81. Single word.', 'a' => '9'],
    ['q' => 'Sum of interior angles in a triangle. Single word.', 'a' => '180'],
    ['q' => 'Decimal equivalent of 3/8. Single word.', 'a' => '0.375'],
    ['q' => 'Volume of cube with 4cm sides. Single word.', 'a' => '64'],
    ['q' => 'Square root of 256. Single word.', 'a' => '16'],
    ['q' => 'Number of sides in a dodecagon. Single word.', 'a' => '12'],
    ['q' => 'Pi rounded to 3 decimal places. Single word.', 'a' => '3.142'],
    ['q' => '15% of 200. Single word.', 'a' => '30'],
    ['q' => 'Derivative of x cubed. Single word.', 'a' => '3x²'],
    ['q' => 'Binary representation of decimal 10. Single word.', 'a' => '1010'],
    ['q' => 'Solve for x: 2x + 3 = 11. Single word.', 'a' => '4'],
    ['q' => 'Hours in a week. Single word.', 'a' => '168'],
    ['q' => 'Sum of 1/4 and 1/8. Single word.', 'a' => '3/8'],
    ['q' => 'Sum of first five odd numbers. Single word.', 'a' => '25'],
    ['q' => 'Integral of 2x with respect to x. Single word.', 'a' => 'x²'],
    
    // === Language Translation (20 questions) ===
    // Tests multilingual capabilities and cultural understanding
    ['q' => 'Romanian translation for "Good morning". Three words.', 'a' => 'Unde este gara'],
    ['q' => 'Spanish word for "cat". Single word.', 'a' => 'Gato'],
    ['q' => 'Japanese phrase for "Thank you". Single word.', 'a' => 'Arigatou'],
    ['q' => 'French word for "red". Single word.', 'a' => 'Rouge'],
    ['q' => 'German word for "water". Single word.', 'a' => 'Wasser'],
    ['q' => 'Italian word for "Monday". Single word.', 'a' => 'Lunedì'],
    ['q' => 'Romanian word for "friend". Single word.', 'a' => 'Prieten'],
    ['q' => 'Russian transliteration for "book". Single word.', 'a' => 'Kniga'],
    ['q' => 'Arabic transliteration for "sun". Single word.', 'a' => 'Shams'],
    ['q' => 'Turkish word for "coffee". Single word.', 'a' => 'Kahve'],
    ['q' => 'Dutch word for "tree". Single word.', 'a' => 'Boom'],
    ['q' => 'Romanian word for "moon". Single word.', 'a' => 'Lună'],
    ['q' => 'Hebrew transliteration for "peace". Single word.', 'a' => 'Shalom'],
    ['q' => 'Swedish word for "house". Single word.', 'a' => 'Hus'],
    ['q' => 'Korean transliteration for "dog". Single word.', 'a' => 'Gae'],
    ['q' => 'Greek transliteration for "love". Single word.', 'a' => 'Agapi'],
    ['q' => 'Portuguese word for "bread". Single word.', 'a' => 'Pão'],
    ['q' => 'Romanian word for "mountain". Single word.', 'a' => 'Munte'],
    ['q' => 'Hindi transliteration for "star". Single word.', 'a' => 'Tara'],
    ['q' => 'Indonesian word for "school". Single word.', 'a' => 'Sekolah'],
    
    // === Grammar & Syntax (15 questions) ===
    // Tests linguistic structure and language rules
    ['q' => 'Plural form of "mouse". Single word.', 'a' => 'Mice'],
    ['q' => 'Past tense of "swim". Single word.', 'a' => 'Swam'],
    ['q' => 'Adjective in "The quick fox". Single word.', 'a' => 'Quick'],
    ['q' => 'Antonym of "begin". Single word.', 'a' => 'End'],
    ['q' => 'Correct verb for "She ___ happy." (to be). Single word.', 'a' => 'is'],
    ['q' => 'Past participle of "eat". Single word.', 'a' => 'Eaten'],
    ['q' => 'Plural of "cactus". Single word.', 'a' => 'Cacti'],
    ['q' => 'Synonym for "angry". Single word.', 'a' => 'Furious'],
    ['q' => 'Comparative form of "bad". Single word.', 'a' => 'Worse'],
    ['q' => 'Preposition in "The cat is under the table." Single word.', 'a' => 'under'],
    ['q' => 'Present participle of "run". Single word.', 'a' => 'Running'],
    ['q' => 'Contraction for "they are". Single word.', 'a' => 'They’re'],
    ['q' => 'Plural of "deer". Single word.', 'a' => 'Deer'],
    ['q' => 'Adverb in "He spoke softly." Single word.', 'a' => 'softly'],
    ['q' => 'Correct quantifier for "people": "Fewer" or "Less". Single word.', 'a' => 'Fewer'],
    
    // === Science & Technology (15 questions) ===
    // Tests scientific literacy and technical knowledge
    ['q' => 'Chemical symbol for silver. Single word.', 'a' => 'Ag'],
    ['q' => 'Unit of electrical resistance. Single word.', 'a' => 'Ohm'],
    ['q' => 'Nearest star to Earth. Single word.', 'a' => 'Sun'],
    ['q' => 'Gas absorbed by plants during photosynthesis. Single word.', 'a' => 'CO2'],
    ['q' => 'Hardest natural mineral. Single word.', 'a' => 'Diamond'],
    ['q' => 'Astronomer who improved the telescope. Single word.', 'a' => 'Galileo'],
    ['q' => 'Atomic number of oxygen. Single word.', 'a' => '8'],
    ['q' => 'Most abundant gas in Earth’s atmosphere. Single word.', 'a' => 'Nitrogen'],
    ['q' => 'Largest moon of Saturn. Single word.', 'a' => 'Titan'],
    ['q' => 'Approximate speed of light in m/s. Single word.', 'a' => '3e8'],
    ['q' => 'pH value of pure water. Single word.', 'a' => '7'],
    ['q' => 'Missing term: Force = mass × ___. Single word.', 'a' => 'acceleration'],
    ['q' => 'Metal that remains liquid at room temperature. Single word.', 'a' => 'Mercury'],
    ['q' => 'Study of fossils. Single word.', 'a' => 'Paleontology'],
    ['q' => 'First artificial Earth satellite. Single word.', 'a' => 'Sputnik'],
    
    // === Spatial & Logical Reasoning (10 questions) ===
    // Tests spatial awareness and logical deduction
    ['q' => 'Facing North, turn 270° clockwise. New direction? Single word.', 'a' => 'West'],
    ['q' => 'Starting at (2,3), move West 4 units. New coordinates? X,Y.', 'a' => '-2,3'],
    ['q' => 'Number of edges in a tetrahedron. Single word.', 'a' => '6'],
    ['q' => 'Overtaking 2nd place in a race. New position? Single word.', 'a' => '2nd'],
    ['q' => 'Mirror image of lowercase "b". Single word.', 'a' => 'd'],
    ['q' => 'Volume of cube with 2cm edges. Single word.', 'a' => '8'],
    ['q' => 'Result of folding a square diagonally. Single word.', 'a' => 'Triangle'],
    ['q' => '3D shape of a soccer ball. Single word.', 'a' => 'Sphere'],
    ['q' => '180° rotation of uppercase "N". Single word.', 'a' => 'N'],
    ['q' => 'Facing East, turn left 90°. New direction? Single word.', 'a' => 'North'],
    
    // === Advanced Linguistics (20 questions) ===
    // Tests complex language structures and translations
    ['q' => 'Romanian translation for "Where is the station?". Three words.', 'a' => 'Unde este gara'],
    ['q' => 'French conjugation of "to sing" (nous form). Single word.', 'a' => 'chantons'],
    ['q' => 'Spanish present continuous for "I am reading". Two words.', 'a' => 'Estoy leyendo'],
    ['q' => 'German equivalent of "we have". Single word.', 'a' => 'haben'],
    ['q' => 'Italian past tense of "go" (3rd person singular). Single word.', 'a' => 'andò'],
    ['q' => 'Romanian phrase for "See you tomorrow". Three words.', 'a' => 'Ne vedem mâine'],
    ['q' => 'Formal "you" pronoun in Spanish. Single word.', 'a' => 'usted'],
    ['q' => 'English plural of "crisis". Single word.', 'a' => 'crises'],
    ['q' => 'French translation for "How old are you?". Four words.', 'a' => 'Quel âge as-tu'],
    ['q' => 'Russian present tense of "to write" (first person). Transliterated.', 'a' => 'pišu'],
];


sort($models);

// For debugging/testing specific models
$models = ['qwen_QwQ-32B-Q8_0']; // Override model list if needed


// Process each available model
foreach ($models as $model) {
    $m = strtolower($model);

    // Skip DeepSeek models to optimize testing time
    if (str_contains($m,'deepseek')) {
        echo "\n\t ** SKIPPING $model **\n";
        continue;
    }

    echo "\n\t =================== $model =================== \n";

    // Configure connection for current model
    $llmConnection->setLLMmodelName($model);
    
    // Initialize counters for correct/incorrect responses
    $goodResponses = $badResponses = 0;

    // Process each test question
    foreach ($benchmarkData as $entry) {
        // Set up conversation context with system message
        $llmConnection->getRolesManager()
            ->clearMessages()
            ->setSystemMessage('You are a helpful LLM that responds to user queries.');

        // Submit query to model
        try {
            $queryString = $entry['q'];
            echo $queryString . "\n"; // Display question
            $llmConnection->getRolesManager()
                ->addMessage('user', $queryString); // Add user message
        } catch (Exception $e) {
            echo($e->getMessage());
            die(1); // Exit on connection errors
        }

        // Execute query and process response
        $response = $llmConnection->queryPost(); // Send to LLM
        
        // Get and clean response content
        $content = trim($response->getLlmResponse());
        $timer = $llmConnection->getLastQueryMicrotime(); // Get response time

        // Validate response against expected answer
        if ($entry['a'] === $content) {
            $goodResponses++; // Increment correct count
            $sign = '👍'; // Success indicator
        } else {
            $badResponses++; // Increment incorrect count
            $sign = '🚫'; // Failure indicator
        }

        // Display comparison of expected vs actual response
        echo "\n$timer $sign: \n\tExpected: [ ${entry['a']} ] \n\tReceived: [ $content ]\n";

    }

    $totalResponses = count($benchmarkData);
    echo "\n *** $model Summary: 👍 $goodResponses/$totalResponses \t 🚫 $badResponses/$totalResponses \n";

    echo "\n";

}
