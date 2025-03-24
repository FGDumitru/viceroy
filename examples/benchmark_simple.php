<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\llamacppOAICompatibleConnection;

$llmConnection = new llamacppOAICompatibleConnection();

// Timeout usage example, wait 5 minutes before timing out.
$llmConnection->setGuzzleConnectionTimeout(PHP_INT_MAX);




$models = $llmConnection->getAvailableModels();
var_dump($models);

$benchmarkData = [
    // === General Knowledge (20) ===
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

    // === Math & Logic (20) ===
    ['q' => '15²? Single word.', 'a' => '225'],
    ['q' => 'Prime numbers between 30-40? Count.', 'a' => '2'],
    ['q' => 'Solve: 8! ÷ 7!. Single word.', 'a' => '8'],
    ['q' => 'Area of circle (r=3)? Use π=3.14. Single word.', 'a' => '28.26'],
    ['q' => 'Next Fibonacci number: 0,1,1,2,3,5. Single word.', 'a' => '8'],
    ['q' => 'If x² = 81, x > 0. Answer.', 'a' => '9'],
    ['q' => 'Sum of angles in a triangle. Single word.', 'a' => '180'],
    ['q' => 'Decimal for 3/8? Single word.', 'a' => '0.375'],
    ['q' => 'Volume of cube (side=4). Single word.', 'a' => '64'],
    ['q' => '√256? Single word.', 'a' => '16'],
    ['q' => 'Number of sides on a dodecagon. Single word.', 'a' => '12'],
    ['q' => 'Value of π to 3 decimals. Single word.', 'a' => '3.142'],
    ['q' => '15% of 200? Single word.', 'a' => '30'],
    ['q' => 'Derivative of x³. Single word.', 'a' => '3x²'],
    ['q' => 'Binary for 10. Single word.', 'a' => '1010'],
    ['q' => 'Solve: 2x + 3 = 11. Single word.', 'a' => '4'],
    ['q' => 'Number of hours in a week. Single word.', 'a' => '168'],
    ['q' => '1/4 + 1/8. Simplify. Single word.', 'a' => '3/8'],
    ['q' => 'Sum of first 5 odd numbers. Single word.', 'a' => '25'],
    ['q' => 'Integral of 2x dx. Single word.', 'a' => 'x²'],

    // === Language Translations (20) ===
    ['q' => 'Translate "Good morning" to Romanian. Single word.', 'a' => 'Bună'],
    ['q' => '"Cat" in Spanish. Single word.', 'a' => 'Gato'],
    ['q' => '"Thank you" in Japanese. Single word.', 'a' => 'Arigatou'],
    ['q' => '"Red" in French. Single word.', 'a' => 'Rouge'],
    ['q' => '"Water" in German. Single word.', 'a' => 'Wasser'],
    ['q' => '"Monday" in Italian. Single word.', 'a' => 'Lunedì'],
    ['q' => '"Friend" in Romanian. Single word.', 'a' => 'Prieten'],
    ['q' => '"Book" in Russian (transliterated). Single word.', 'a' => 'Kniga'],
    ['q' => '"Sun" in Arabic (transliterated). Single word.', 'a' => 'Shams'],
    ['q' => '"Coffee" in Turkish. Single word.', 'a' => 'Kahve'],
    ['q' => '"Tree" in Dutch. Single word.', 'a' => 'Boom'],
    ['q' => '"Moon" in Romanian. Single word.', 'a' => 'Lună'],
    ['q' => '"Peace" in Hebrew (transliterated). Single word.', 'a' => 'Shalom'],
    ['q' => '"House" in Swedish. Single word.', 'a' => 'Hus'],
    ['q' => '"Dog" in Korean (transliterated). Single word.', 'a' => 'Gae'],
    ['q' => '"Love" in Greek (transliterated). Single word.', 'a' => 'Agapi'],
    ['q' => '"Bread" in Portuguese. Single word.', 'a' => 'Pão'],
    ['q' => '"Mountain" in Romanian. Single word.', 'a' => 'Munte'],
    ['q' => '"Star" in Hindi (transliterated). Single word.', 'a' => 'Tara'],
    ['q' => '"School" in Indonesian. Single word.', 'a' => 'Sekolah'],

    // === Grammar & Syntax (15) ===
    ['q' => 'Plural of "mouse"? Single word.', 'a' => 'Mice'],
    ['q' => 'Past tense of "swim"? Single word.', 'a' => 'Swam'],
    ['q' => 'Adjective in "The quick fox". Single word.', 'a' => 'Quick'],
    ['q' => 'Antonym of "begin"? Single word.', 'a' => 'End'],
    ['q' => 'Correct: "She ___ happy." (to be). Single word.', 'a' => 'is'],
    ['q' => 'Past participle of "eat"? Single word.', 'a' => 'Eaten'],
    ['q' => 'Plural of "cactus"? Single word.', 'a' => 'Cacti'],
    ['q' => 'Synonym for "angry"? Single word.', 'a' => 'Furious'],
    ['q' => 'Comparative of "bad"? Single word.', 'a' => 'Worse'],
    ['q' => 'Preposition in "The cat is under the table." Single word.', 'a' => 'under'],
    ['q' => 'Present participle of "run"? Single word.', 'a' => 'Running'],
    ['q' => 'Contraction for "they are"? Single word.', 'a' => 'They’re'],
    ['q' => 'Plural of "deer"? Single word.', 'a' => 'Deer'],
    ['q' => 'Adverb in "He spoke softly." Single word.', 'a' => 'softly'],
    ['q' => 'Correct: "Fewer" or "Less" people? Single word.', 'a' => 'Fewer'],

    // === Science & Tech (15) ===
    ['q' => 'Chemical symbol for silver. Single word.', 'a' => 'Ag'],
    ['q' => 'Unit of electrical resistance. Single word.', 'a' => 'Ohm'],
    ['q' => 'Nearest star to Earth. Single word.', 'a' => 'Sun'],
    ['q' => 'Gas absorbed by plants. Single word.', 'a' => 'CO2'],
    ['q' => 'Hardest natural substance. Single word.', 'a' => 'Diamond'],
    ['q' => 'Inventor of the telescope. Single word.', 'a' => 'Galileo'],
    ['q' => 'Atomic number of oxygen. Single word.', 'a' => '8'],
    ['q' => 'Main component of air. Single word.', 'a' => 'Nitrogen'],
    ['q' => 'Largest moon of Saturn. Single word.', 'a' => 'Titan'],
    ['q' => 'Speed of light (m/s). Approximate. Single word.', 'a' => '3e8'],
    ['q' => 'pH of pure water. Single word.', 'a' => '7'],
    ['q' => 'Force = mass × ___. Single word.', 'a' => 'acceleration'],
    ['q' => 'Metal liquid at room temperature. Single word.', 'a' => 'Mercury'],
    ['q' => 'Study of fossils. Single word.', 'a' => 'Paleontology'],
    ['q' => 'Humanity’s first satellite. Single word.', 'a' => 'Sputnik'],

    // === Spatial & Logic (10) ===
    ['q' => 'Facing North, turn 270° clockwise. Direction?', 'a' => 'West'],
    ['q' => 'Starting at (2,3), move West 4. Coordinates? X,Y.', 'a' => '-2,3'],
    ['q' => 'How many edges on a tetrahedron? Single word.', 'a' => '6'],
    ['q' => 'You overtake 2nd place in a race. Your position?', 'a' => '2nd'],
    ['q' => 'Mirror image of "b" (letter). Single word.', 'a' => 'd'],
    ['q' => 'Cube with side=2. Volume? Single word.', 'a' => '8'],
    ['q' => 'Fold a square diagonally. Shape? Single word.', 'a' => 'Triangle'],
    ['q' => '3D shape of a soccer ball. Single word.', 'a' => 'Sphere'],
    ['q' => 'Rotate "N" 180°. Result? Single word.', 'a' => 'N'],
    ['q' => 'Facing East, turn left 90°. Direction?', 'a' => 'North'],

    // === Advanced Language (20) ===
    ['q' => 'Translate "Where is the station?" to Romanian. Three words.', 'a' => 'Unde este gara'],
    ['q' => 'Conjugate "to sing" in French for "nous". Single word.', 'a' => 'chantons'],
    ['q' => 'Translate "I am reading" to Spanish. Two words.', 'a' => 'Estoy leyendo'],
    ['q' => '"We have" in German. Single word.', 'a' => 'haben'],
    ['q' => 'Past tense of "go" in Italian. Single word.', 'a' => 'andò'],
    ['q' => 'Translate "See you tomorrow" to Romanian. Three words.', 'a' => 'Ne vedem mâine'],
    ['q' => 'Formal "you" in Spanish. Single word.', 'a' => 'usted'],
    ['q' => 'Plural of "crisis" in English. Single word.', 'a' => 'crises'],
    ['q' => 'Translate "How old are you?" to French. Four words.', 'a' => 'Quel âge as-tu'],
    ['q' => 'Conjugate "to write" in Russian (present, я). Transliterated.', 'a' => 'pišu'],
];


sort($models);

// DEBUG
$models = ['WizardLM-2-8x22B.Q6_K_CTX-32768'];
foreach ($models as $model) {

    $m = strtolower($model);

    if (str_starts_with($m,'deepseek')) {
        echo "\n\t ** SKIPPING $model **\n";
        continue;
    }

    echo "\n\t =================== $model =================== \n";

    $llmConnection->setLLMmodelName($model);

    $goodResponses = $badResponses = 0;

    foreach ($benchmarkData as $entry) {

        // Add a system message (if the model supports it).
        $llmConnection->getRolesManager()
            ->clearMessages()
            ->setSystemMessage('You are a helpful LLM that responds to user queries.');



        // Query the model as a User.
        try {
            $queryString = $entry['q'];
            echo $queryString . "\n";
            $llmConnection->getRolesManager()
                ->addMessage('user', $queryString);
        } catch (Exception $e) {
            echo($e->getMessage());
            die(1);
        }

        // Perform the actual LLM query.
        $response = $llmConnection->queryPost();

        $content = $response->getLlmResponse();
        $content = trim($content);
        $timer = $llmConnection->getLastQueryMicrotime();

        if ($entry['a'] === $content) {
            $goodResponses++;
            $sign = '👍';
        } else {
            $badResponses++;
            $sign = '🚫';
        }


        echo "\n$timer $sign: \n\tExpected: [ ${entry['a']} ] \n\tReceived: [ $content ]\n";

    }

    $totalResponses = count($benchmarkData);
    echo "\n *** $model Summary: 👍 $goodResponses/$totalResponses \t 🚫 $badResponses/$totalResponses \n";

    echo "\n";

}