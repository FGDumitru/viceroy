<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

class DeepDiveUIDetector {
    private $llmConnection;
    private $rolesManager;

    // Configuration
    private $leafTileSize = 1000; // Updated to 1000 to match Qwen optimal input
    private $overlap = 100;
    private $nmsThreshold = 0.4;

    public function __construct() {
        $this->llmConnection = new OpenAICompatibleEndpointConnection();
        $this->llmConnection->setConnectionTimeout(1200);

        // Deterministic Settings for JSON Extraction
        $this->llmConnection->setParameter('temperature', 0.0);
        $this->llmConnection->setParameter('top_p', 0.1);
        $this->llmConnection->setParameter('top_k', 20);
        $this->llmConnection->setParameter('repetition_penalty', 1.05);
        $this->llmConnection->setParameter('max_tokens', 8192);

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

        $this->runDeepInference($imagePath, $outputJsonPath, $outputImagePath);
    }

    private function runDeepInference($imagePath, $outputJsonPath, $outputImagePath) {
        if (!file_exists($imagePath)) die("File not found");

        $currentModel = $this->llmConnection->getLLMmodelName();
        list($fullW, $fullH) = getimagesize($imagePath);

        echo "\n[BENCHMARK] Model: {$currentModel}\n";
        echo "Processing Image: {$fullW}x{$fullH}\n";
        $startTime = microtime(true);

        // --- STEP 1: Global Context ---
        echo ">> Step 1: Establishing Global Context...\n";
        $globalContext = $this->getGlobalContext($imagePath);
        echo "   Context: " . $globalContext . "\n";

        // --- STEP 2: Recursive Quad-Tree Decomposition ---
        echo ">> Step 2: Recursive Quad-Tree Analysis...\n";
        $allDetections = $this->processRecursively($imagePath, 0, 0, $fullW, $fullH, $globalContext);

        // --- STEP 3: Deduplication ---
        echo ">> Step 3: Global Deduplication (NMS)...\n";
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

    /**
     * Recursive Logic:
     * 1. If region is small enough, crop, resize if needed, and detect.
     * 2. If region is too big, split into 4 and recurse.
     */
    private function processRecursively($fullImagePath, $x, $y, $w, $h, $parentContext) {
        $detections = [];

        // Base Case: Process if size is manageable
        if ($w <= $this->leafTileSize && $h <= $this->leafTileSize) {
            echo "   > Leaf Tile [{$w}x{$h}] at ($x, $y)... ";

            // 1. Create Crop (Resize logic inside)
            $crop = $this->cropImage($fullImagePath, [$x, $y, $x+$w, $y+$h]);
            if (!$crop) return [];

            // 2. Identify Local Context
            $localContext = $this->getLocalContext($crop['path'], $w, $h, $parentContext);

            // 3. Detect Elements
            $localResults = $this->detectInTile($crop['path'], $w, $h, $parentContext . " " . $localContext);

            echo "Found " . count($localResults) . " items. ($localContext)\n";

            // 4. Transform Coordinates to Global
            foreach ($localResults as $item) {
                if (!isset($item['bbox_2d'])) continue;
                $b = $item['bbox_2d'];

                // Qwen outputs 0-1000 relative to the image slice.
                // We normalize (val/1000) then multiply by the LOGICAL width ($w/$h).
                // This works even if cropImage() downscaled the physical JPG to 1000px.

                $tx1 = ($b[0] / 1000) * $w;
                $ty1 = ($b[1] / 1000) * $h;
                $tx2 = ($b[2] / 1000) * $w;
                $ty2 = ($b[3] / 1000) * $h;

                // Add Tile Offset to get Global Coordinates
                $gx1 = $tx1 + $x;
                $gy1 = $ty1 + $y;
                $gx2 = $tx2 + $x;
                $gy2 = $ty2 + $y;

                $detections[] = [
                    'label' => $item['label'] ?? 'Unknown',
                    'type'  => $item['type'] ?? 'unknown',
                    'parent_app' => $parentContext,
                    'section' => $localContext,
                    'bbox'  => [(int)$gx1, (int)$gy1, (int)$gx2, (int)$gy2],
                    'area'  => ($gx2 - $gx1) * ($gy2 - $gy1)
                ];
            }
            @unlink($crop['path']);
            return $detections;
        }

        // Recursive Step: Split into Quadrants
        echo "   > Splitting Large Region [{$w}x{$h}]...\n";

        $midX = floor($w / 2);
        $midY = floor($h / 2);

        $q1 = ['x' => $x, 'y' => $y, 'w' => $midX + $this->overlap, 'h' => $midY + $this->overlap];
        $q2 = ['x' => $x + $midX - $this->overlap, 'y' => $y, 'w' => ($w - $midX) + $this->overlap, 'h' => $midY + $this->overlap];
        $q3 = ['x' => $x, 'y' => $y + $midY - $this->overlap, 'w' => $midX + $this->overlap, 'h' => ($h - $midY) + $this->overlap];
        $q4 = ['x' => $x + $midX - $this->overlap, 'y' => $y + $midY - $this->overlap, 'w' => ($w - $midX) + $this->overlap, 'h' => ($h - $midY) + $this->overlap];

        $quadrants = [$q1, $q2, $q3, $q4];

        foreach ($quadrants as $q) {
            $subResults = $this->processRecursively($fullImagePath, $q['x'], $q['y'], $q['w'], $q['h'], $parentContext);
            $detections = array_merge($detections, $subResults);
        }

        return $detections;
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

    private function getLocalContext($path, $w, $h, $parentContext) {
        $this->rolesManager->clearMessages();
        $prompt = <<<EOT
You are analyzing a crop ({$w}x{$h}) of a screen. 
Global Context: {$parentContext}.
Briefly describe WHAT functional part of the UI this is. 
Examples: "File Explorer Sidebar", "Browser Tab Bar", "Code Editor Main View".
Keep it under 10 words.
EOT;
        $this->rolesManager->addMessage('user', $prompt, $path);
        $response = '';
        try {
            $this->llmConnection->queryPost(function($c) use (&$response){ echo $c; $response .= $c; });
        } catch(Exception $e) {}

        return trim(preg_replace('/\s+/', ' ', $response));
    }

    /**
     * Detection Prompt
     * UPDATED: Removed explicit "0-1000" instruction, relying on model defaults.
     */
    private function detectInTile($path, $w, $h, $context) {
        $this->rolesManager->clearMessages();

        $prompt = <<<EOT
Analyze this UI crop ({$w}x{$h}).
Context: {$context}

TASK: Detect all clickable UI elements (buttons, inputs, icons, links, tabs).

INSTRUCTIONS:
1. First, think line-by-line what you see.
2. Then, output the JSON.

REQUIRED JSON FORMAT:
###JSON_START###
[
  {"bbox_2d": [x1,y1,x2,y2], "label": "name", "type": "button|input|icon|link"}
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

    // --- Helpers ---

    /**
     * UPDATED: Resizes slice to max 1000px if it exceeds 1024px.
     */
    private function cropImage($sourcePath, $bbox) {
        $src = $this->loadImage($sourcePath);
        if (!$src) return null;
        $x = (int)max(0, $bbox[0]); $y = (int)max(0, $bbox[1]);
        $w = (int)($bbox[2] - $bbox[0]); $h = (int)($bbox[3] - $bbox[1]);

        $realW = imagesx($src); $realH = imagesy($src);
        if ($x + $w > $realW) $w = $realW - $x;
        if ($y + $h > $realH) $h = $realH - $y;
        if ($w <= 10 || $h <= 10) return null;

        $dst = imagecreatetruecolor($w, $h);
        imagecopy($dst, $src, 0, 0, $x, $y, $w, $h);

        // --- RESIZE LOGIC ---
        // If the slice is physically larger than 1024px, resize it to 1000px
        // Qwen works best at ~1000x1000.
        if ($w > 1024 || $h > 1024) {
            $scale = 1000 / max($w, $h);
            $newW = (int)($w * $scale);
            $newH = (int)($h * $scale);

            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $dst, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($dst);
            $dst = $resized;
        }

        $tmp = sys_get_temp_dir() . "/deep_crop_" . uniqid() . ".jpg";
        imagejpeg($dst, $tmp, 100);
        imagedestroy($dst);
        imagedestroy($src);

        // Return path. Note: We return logical W/H (original crop size) upstream
        // via processRecursively parameters, so math remains correct.
        return ['path' => $tmp, 'width' => $w, 'height' => $h];
    }

    private function applyNMS($detections) {
        if (empty($detections)) return [];
        usort($detections, fn($a, $b) => $a['area'] <=> $b['area']);
        $active = array_fill(0, count($detections), true);
        $merged = [];

        for ($i = 0; $i < count($detections); $i++) {
            if (!$active[$i]) continue;
            $base = $detections[$i];
            $cluster = [
                'x1' => [$base['bbox'][0]], 'y1' => [$base['bbox'][1]],
                'x2' => [$base['bbox'][2]], 'y2' => [$base['bbox'][3]]
            ];

            for ($j = $i + 1; $j < count($detections); $j++) {
                if (!$active[$j]) continue;
                $cand = $detections[$j];
                $iou = $this->calculateIoU($base['bbox'], $cand['bbox']);

                $match = ($iou > 0.5) ||
                    ($iou > 0.2 && $base['label'] == $cand['label'] && $base['type'] == $cand['type']);

                if ($match) {
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

    private function extractJson($text) {
        if (preg_match('/###JSON_START###(.*?)###JSON_END###/s', $text, $m)) {
            $json = json_decode(trim($m[1]), true);
            if ($json) return $json;
        }
        $clean = preg_replace('/<think>.*?<\/think>/s', '', $text);
        if (preg_match('/```json\s*(.*?)\s*```/s', $clean, $m)) {
            $json = json_decode($m[1], true);
            if ($json) return $json;
        }
        $start = strpos($clean, '['); $end = strrpos($clean, ']');
        if ($start !== false && $end !== false) return json_decode(substr($clean, $start, ($end - $start) + 1), true) ?? [];
        return [];
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
            'unknown'=> [128, 128, 128]
        ];
        $black = imagecolorallocate($img, 0, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagesetthickness($img, 2);

        foreach ($detections as $d) {
            $type = $d['type'] ?? 'unknown';
            $rgb = $colorDefs[$type] ?? $colorDefs['unknown'];
            $borderCol = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            $fillCol = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 90);

            $b = $d['bbox'];
            imagefilledrectangle($img, $b[0], $b[1], $b[2], $b[3], $fillCol);
            imagerectangle($img, $b[0], $b[1], $b[2], $b[3], $borderCol);

            $label = substr($d['section'], 0, 15) . " > " . $d['label'];
            $ly = ($b[1] - 16) < 0 ? $b[1] + 2 : $b[1] - 16;
            imagefilledrectangle($img, $b[0], $ly, $b[0] + (strlen($label)*7), $ly + 16, $black);
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

$detector = new DeepDiveUIDetector();

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

echo "Starting Deep Dive Benchmark on: $baseName\n";

foreach ($models as $modelName) {
    $safeName = str_replace(['/', ':', '\\', ' '], '_', $modelName);
    $json = $dir . '/' . $baseName . '-' . $safeName . '.json';
    $jpg  = $dir . '/' . $baseName . '_annotated-' . $safeName . '.jpg';

    $detector->setModel($modelName);
    $detector->execute($inputImage, $json, $jpg);
}