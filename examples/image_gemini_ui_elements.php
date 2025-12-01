<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

class ProductionUIDetector {
    private $llmConnection;
    private $rolesManager;

    // CONFIGURATION
    private $tileWidth = 1000;
    private $tileHeight = 1000;
    private $overlap = 200;
    private $nmsThreshold = 0.4;

    public function __construct() {
        $this->llmConnection = new OpenAICompatibleEndpointConnection();
        $this->llmConnection->setConnectionTimeout(2400); // 40 mins (large thinking models take time)

        // Parameters optimized for Thinking Models + Extraction
        $this->llmConnection->setParameter('temperature', 0.1);
        $this->llmConnection->setParameter('top_p', 0.5);
        $this->llmConnection->setParameter('top_k', 40);
        $this->llmConnection->setParameter('repetition_penalty', 1.05);
        // CRITICAL: Max out tokens to prevent JSON truncation after long thinking
//        $this->llmConnection->setParameter('max_tokens', 32768);

        $this->rolesManager = $this->llmConnection->getRolesManager();
    }

    public function setModel($modelName) {
        $this->llmConnection->setLLMmodelName($modelName);
    }

    public function execute($imagePath, $outputJsonPath, $outputImagePath) {
        $jsonExists = file_exists($outputJsonPath);
        $imgExists = file_exists($outputImagePath);

        if ($jsonExists && $imgExists) {
            echo " [SKIP] Results exist.\n";
            return;
        }

        if ($jsonExists && !$imgExists) {
            echo " [REGEN] Regenerating image from JSON...\n";
            $data = json_decode(file_get_contents($outputJsonPath), true);
            if ($data) $this->drawHighFidelityBoxes($imagePath, $data['elements'], $outputImagePath, $data['meta']['model'] ?? 'Unknown');
            return;
        }

        $this->runInference($imagePath, $outputJsonPath, $outputImagePath);
    }

    private function runInference($imagePath, $outputJsonPath, $outputImagePath) {
        if (!file_exists($imagePath)) die("File not found");

        $currentModel = $this->llmConnection->getLLMmodelName();
        list($fullW, $fullH) = getimagesize($imagePath);

        echo "\n[BENCHMARK] Model: {$currentModel}\n";
        echo "Processing Image: {$fullW}x{$fullH}\n";
        $startTime = microtime(true);

        // --- STEP 1: Global Context ---
        echo ">> Step 1: Global Context Analysis...\n";
        $globalContext = $this->getGlobalContext($imagePath);
        echo "   Context: " . $globalContext . "\n";

        // --- STEP 2: Deterministic Grid Sliding Window ---
        echo ">> Step 2: High-Recall Grid Scan ({$this->tileWidth}x{$this->tileHeight})...\n";
        $allDetections = $this->processGrid($imagePath, $fullW, $fullH, $globalContext);

        // --- STEP 3: Deduplication ---
        echo ">> Step 3: NMS Deduplication...\n";
        $finalDetections = $this->applyNMS($allDetections);

        $duration = round(microtime(true) - $startTime, 2);
        echo "   Final Count: " . count($finalDetections) . " elements.\n";

        $outputData = [
            'meta' => [
                'model' => $currentModel,
                'timestamp' => date('c'),
                'duration_seconds' => $duration,
                'element_count' => count($finalDetections),
                'global_context' => $globalContext
            ],
            'elements' => $finalDetections
        ];

        file_put_contents($outputJsonPath, json_encode($outputData, JSON_PRETTY_PRINT));
        $this->drawHighFidelityBoxes($imagePath, $finalDetections, $outputImagePath, $currentModel);
    }

    private function processGrid($imagePath, $fullW, $fullH, $parentContext) {
        $detections = [];
        $source = $this->loadImage($imagePath);

        $stepX = $this->tileWidth - $this->overlap;
        $stepY = $this->tileHeight - $this->overlap;
        $tilesToProcess = [];

        // 1. Calculate Grid
        for ($y = 0; $y < $fullH; $y += $stepY) {
            for ($x = 0; $x < $fullW; $x += $stepX) {
                $w = min($this->tileWidth, $fullW - $x);
                $h = min($this->tileHeight, $fullH - $y);
                // Skip small slivers unless it's the only tile
                if (($w < 100 || $h < 100) && ($fullW > 200)) continue;
                $tilesToProcess[] = ['x'=>$x, 'y'=>$y, 'w'=>$w, 'h'=>$h];
                if ($x + $w >= $fullW) break;
            }
            if ($y + $h >= $fullH) break;
        }

        // 2. Process Tiles
        $total = count($tilesToProcess);
        foreach ($tilesToProcess as $i => $t) {
            echo "   > Tile " . ($i+1) . "/$total at ({$t['x']}, {$t['y']}) [{$t['w']}x{$t['h']}]... ";

            // Crop Physical Image
            $cropPath = $this->createCropFile($source, $t['x'], $t['y'], $t['w'], $t['h']);

            // Detect
            $localResults = $this->detectInTile($cropPath, $t['w'], $t['h'], $parentContext);

            echo "Found " . count($localResults) . " items.\n";

            // Normalize Coordinates
            foreach ($localResults as $item) {
                if (!isset($item['bbox_2d'])) continue;

                $b = $item['bbox_2d'];

                // Use round() for better pixel precision
                $tx1 = ($b[0] / 1000) * $t['w'];
                $ty1 = ($b[1] / 1000) * $t['h'];
                $tx2 = ($b[2] / 1000) * $t['w'];
                $ty2 = ($b[3] / 1000) * $t['h'];

                $gx1 = $tx1 + $t['x'];
                $gy1 = $ty1 + $t['y'];
                $gx2 = $tx2 + $t['x'];
                $gy2 = $ty2 + $t['y'];

                $detections[] = [
                    'label' => $item['label'] ?? 'Unknown',
                    'type'  => $item['type'] ?? 'unknown',
                    'bbox'  => [round($gx1), round($gy1), round($gx2), round($gy2)],
                    'area'  => ($gx2 - $gx1) * ($gy2 - $gy1)
                ];
            }
            @unlink($cropPath);
        }

        imagedestroy($source);
        return $detections;
    }

    private function detectInTile($path, $w, $h, $context) {
        $this->rolesManager->clearMessages();

        // Prompt designed to force the JSON to the end
        $prompt = <<<EOT
Analyze this UI crop ({$w}x{$h}).
Context: {$context}

TASK: Detect ALL interactive UI elements (buttons, inputs, icons, links, tabs, text labels).
Be extremely thorough.

Instructions:
1. You may use a Chain of Thought to analyze the layout first.
2. At the VERY END, output the JSON block.
3. Use 0-1000 normalized coordinates.

REQUIRED FINAL OUTPUT FORMAT:
###JSON_START###
[
  {"bbox_2d": [x1,y1,x2,y2], "label": "name", "type": "button|input|icon|link|text"}
]
###JSON_END###
EOT;

        $this->rolesManager->addMessage('user', $prompt, $path);

        $response = '';
        try {
            $this->llmConnection->queryPost(function ($c) use (&$response) { echo $c; $response .= $c; });
        } catch (Exception $e) {}

        return $this->extractJson($response);
    }

    /**
     * ROBUST JSON EXTRACTION & REPAIR
     * Handles cases where the model cuts off mid-stream.
     */
    private function extractJson($text) {
        $jsonCandidate = null;

        // 1. Explicit Delimiters (Complete)
        if (preg_match_all('/###JSON_START###(.*?)###JSON_END###/s', $text, $matches)) {
            $jsonCandidate = end($matches[1]);
        }
        // 2. Explicit Start (Truncated/Missing End)
        elseif (preg_match('/###JSON_START###(.*)$/s', $text, $matches)) {
            $jsonCandidate = $matches[1];
        }
        // 3. Fallback: Strip <think> and look for markdown or arrays
        else {
            $clean = preg_replace('/<think>.*?<\/think>/s', '', $text);
            if (preg_match_all('/```json\s*(.*?)\s*```/s', $clean, $matches)) {
                $jsonCandidate = end($matches[1]);
            } elseif (preg_match_all('/\[\s*\{.*?\}\s*\]/s', $clean, $matches)) {
                $jsonCandidate = end($matches[0]);
            } elseif (preg_match('/\[\s*\{.*/s', $clean, $matches)) {
                // Raw array start that got truncated
                $jsonCandidate = $matches[0];
            }
        }

        if (!$jsonCandidate) return [];
        $jsonCandidate = trim($jsonCandidate);

        // Try standard decode
        $data = json_decode($jsonCandidate, true);
        if ($data) return $data;

        // Try to Repair Truncated JSON
        return $this->repairTruncatedJson($jsonCandidate);
    }

    /**
     * Repairs a JSON array that was cut off mid-stream.
     * Strategy: Find the last valid object closure '}', cut off everything after, close the array.
     */
    private function repairTruncatedJson($str) {
        // Find position of the last '}'
        $lastBracket = strrpos($str, '}');
        if ($lastBracket === false) return [];

        // Substring up to that point
        $validPart = substr($str, 0, $lastBracket + 1);

        // Append closing bracket
        $fixed = $validPart . ']';

        $data = json_decode($fixed, true);
        return $data ? $data : [];
    }

    private function createCropFile($source, $x, $y, $w, $h) {
        $dest = imagecreatetruecolor($w, $h);
        imagecopy($dest, $source, 0, 0, $x, $y, $w, $h);
        $tmp = sys_get_temp_dir() . "/crop_" . uniqid() . ".jpg";
        imagejpeg($dest, $tmp, 100);
        imagedestroy($dest);
        return $tmp;
    }

    private function getGlobalContext($path) {
        $this->rolesManager->clearMessages();
        $prompt = "Identify the Operating System and list the names of all visible open Applications windows.";
        $this->rolesManager->addMessage('user', $prompt, $path);

        $response = '';
        try {
            $this->llmConnection->queryPost(function($c) use (&$response){ echo $c; $response .= $c; });
        } catch(Exception $e) {}

        return trim(str_replace(["\n", '"', "{", "}"], " ", substr($response, 0, 200)));
    }

    private function applyNMS($detections) {
        if (empty($detections)) return [];
        usort($detections, fn($a, $b) => $a['area'] <=> $b['area']);
        $active = array_fill(0, count($detections), true);
        $merged = [];

        for ($i = 0; $i < count($detections); $i++) {
            if (!$active[$i]) continue;
            $base = $detections[$i];
            $cluster = ['x1'=>[$base['bbox'][0]], 'y1'=>[$base['bbox'][1]], 'x2'=>[$base['bbox'][2]], 'y2'=>[$base['bbox'][3]]];

            for ($j = $i + 1; $j < count($detections); $j++) {
                if (!$active[$j]) continue;
                $cand = $detections[$j];
                $iou = $this->calculateIoU($base['bbox'], $cand['bbox']);

                $isSimilarLabel = false;
                if ($iou > 0.2) {
                    similar_text($base['label'], $cand['label'], $perc);
                    if ($perc > 70) $isSimilarLabel = true;
                }

                if ($iou > 0.5 || $isSimilarLabel) {
                    $cluster['x1'][] = $cand['bbox'][0]; $cluster['y1'][] = $cand['bbox'][1];
                    $cluster['x2'][] = $cand['bbox'][2]; $cluster['y2'][] = $cand['bbox'][3];
                    $active[$j] = false;
                }
            }
            $cnt = count($cluster['x1']);
            $base['bbox'] = [array_sum($cluster['x1'])/$cnt, array_sum($cluster['y1'])/$cnt, array_sum($cluster['x2'])/$cnt, array_sum($cluster['y2'])/$cnt];
            $merged[] = $base;
        }
        return $merged;
    }

    private function calculateIoU($a, $b) {
        $xA = max($a[0], $b[0]); $yA = max($a[1], $b[1]);
        $xB = min($a[2], $b[2]); $yB = min($a[3], $b[3]);
        $inter = max(0, $xB - $xA) * max(0, $yB - $yA);
        $union = (($a[2]-$a[0])*($a[3]-$a[1])) + (($b[2]-$b[0])*($b[3]-$b[1])) - $inter;
        return $union == 0 ? 0 : $inter / $union;
    }

    private function loadImage($path) {
        return exif_imagetype($path) == IMAGETYPE_PNG ? imagecreatefrompng($path) : imagecreatefromjpeg($path);
    }

    private function drawHighFidelityBoxes($imagePath, $detections, $outputPath, $modelName) {
        $img = $this->loadImage($imagePath);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $colorDefs = [
            'button' => [50, 205, 50],
            'input'  => [0, 191, 255],
            'link'   => [255, 140, 0],
            'icon'   => [255, 0, 255],
            'tab'    => [255, 215, 0],
            'text'   => [200, 200, 200],
            'unknown'=> [128, 128, 128]
        ];
        $black = imagecolorallocate($img, 0, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagesetthickness($img, 2);

        foreach ($detections as $d) {
            $type = $d['type'] ?? 'unknown';
            $rgb = $colorDefs[$type] ?? $colorDefs['unknown'];
            $borderCol = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            // 50% opacity
            $fillCol = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 64);

            $b = $d['bbox'];
            imagefilledrectangle($img, $b[0], $b[1], $b[2], $b[3], $fillCol);
            imagerectangle($img, $b[0], $b[1], $b[2], $b[3], $borderCol);

            $label = substr($d['label'], 0, 20);
            $ly = ($b[1] - 16) < 0 ? $b[1] + 2 : $b[1] - 16;
            imagefilledrectangle($img, $b[0], $ly, $b[0] + (strlen($label)*8), $ly + 16, $black);
            imagestring($img, 2, $b[0] + 2, $ly + 1, $label, $borderCol);
        }

        $meta = "Model: $modelName | Count: " . count($detections);
        imagefilledrectangle($img, 0, imagesy($img)-30, imagesx($img), imagesy($img), $black);
        imagestring($img, 4, 10, imagesy($img)-24, $meta, $white);

        imagejpeg($img, $outputPath, 100);
        imagedestroy($img);
        echo "   Saved image: $outputPath\n";
    }
}

// --- RUNNER ---

$detector = new ProductionUIDetector();

$models = [
    'Qwen3-VL-30B-A3B-Instruct',
    'Qwen3-VL-32B-Instruct-UD-Q8_K_XL',
    'Qwen3-VL-235B-A22B-Instruct',
    'Qwen3-VL-235B-A22B-Instruct-Q6',

    'Qwen3-VL-30B-A3B-Thinking',
    'Qwen3-VL-32B-Thinking-UD-Q8_K_XL',
    'Qwen3-VL-235B-A22B-Thinking',
    'Qwen3-VL-235B-A22B-Thinking-Q6'
];

global $argv;
$inputImage = null;
foreach ($argv as $arg) {
    if (!str_starts_with($arg, '-') && preg_match('/\.(png|jpg|jpeg)$/i', $arg)) {
        $inputImage = $arg;
        break;
    }
}
if (!$inputImage) $inputImage = __DIR__ . '/images/ui_screenshot.png';

$pathInfo = pathinfo($inputImage);
$baseName = $pathInfo['filename'];
$dir = $pathInfo['dirname'];

echo "Starting High-Recall Benchmark on: $baseName\n";

foreach ($models as $modelName) {
    $safeName = str_replace(['/', ':', '\\', ' '], '_', $modelName);
    $json = $dir . '/' . $baseName . '-' . $safeName . '.json';
    $jpg  = $dir . '/' . $baseName . '_annotated-' . $safeName . '.jpg';

    $detector->setModel($modelName);
    $detector->execute($inputImage, $json, $jpg);
}