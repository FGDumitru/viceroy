<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

class ProductionUIDetector {
    private $llmConnection;
    private $rolesManager;

    // --- CONFIGURATION FLAGS ---
    private $enableDynamicGrid = false;
    private $enableVerification = false;
    private $enableSuccinct = false;
    private $enableLabelDrawing = true; // NEW: Toggle labels
    private $enableRecovery = false;    // NEW: Recovery pass

    // DEFAULT GRID SETTINGS
    private $tileWidth = 1000;
    private $tileHeight = 1000;
    private $overlap = 200;

    // STATE
    private $rollingContext = "Start of scan.";
    private $incompleteBuffer = [];

    public function __construct() {
        $this->llmConnection = new OpenAICompatibleEndpointConnection();
        $this->llmConnection->setConnectionTimeout(3600);

        // Parameters
//        $this->llmConnection->setParameter('temperature', 0.2);
//        $this->llmConnection->setParameter('top_k', 0.2);
        // $this->llmConnection->setParameter('max_tokens', 32768);

        $this->rolesManager = $this->llmConnection->getRolesManager();
    }

    public function setModel($modelName) {
        $this->llmConnection->setLLMmodelName($modelName);
    }

    /**
     * Updated Options Setter
     */
    public function setOptions($dynamicGrid, $verify, $succinct, $drawLabels, $recoverMissing) {
        $this->enableDynamicGrid = $dynamicGrid;
        $this->enableVerification = $verify;
        $this->enableSuccinct = $succinct;
        $this->enableLabelDrawing = $drawLabels;
        $this->enableRecovery = $recoverMissing;
    }

    public function execute($imagePath, $outputJsonPath, $outputImagePath) {
        if (!file_exists($imagePath)) die("File not found");

        $currentModel = $this->llmConnection->getLLMmodelName();
        list($fullW, $fullH) = getimagesize($imagePath);

        echo "\n[BENCHMARK] Model: {$currentModel}\n";
        echo "Processing Image: {$fullW}x{$fullH}\n";
        $startTime = microtime(true);

        // --- STEP 0: Dynamic Grid ---
        if ($this->enableDynamicGrid) {
            echo ">> Step 0: Analyzing image density for optimal grid...\n";
            $this->optimizeGridSettings($imagePath, $fullW, $fullH);
            echo "   -> New Settings: {$this->tileWidth}x{$this->tileHeight} (Overlap: {$this->overlap})\n";
        }

        // --- STEP 1: Global Context ---
        echo ">> Step 1: Global Context Analysis...\n";
        $globalInfo = $this->getGlobalContext($imagePath);
        echo "\n   OS: {$globalInfo['os']} | Apps: " . implode(", ", $globalInfo['apps']) . "\n";

        // --- STEP 2: Intelligent Grid Scan ---
        echo ">> Step 2: Context-Aware Grid Scan...\n";
        $this->rollingContext = "Scan started at top-left. No elements found yet.";
        $this->incompleteBuffer = [];

        $rawDetections = $this->processGrid($imagePath, $fullW, $fullH, $globalInfo);

        // --- STEP 3: Stitching ---
        echo ">> Step 3: Stitching Incomplete/Partial Elements...\n";
        $stitchedDetections = $this->resolveIncompleteItems($rawDetections, $fullW, $fullH);

        // --- STEP 4: Recovery (Missing Elements) ---
        if ($this->enableRecovery) {
            echo ">> Step 4: Recovery Pass (Finding missing elements)...\n";
            $recovered = $this->findMissingElements($imagePath, $stitchedDetections, $fullW, $fullH);
            $stitchedDetections = array_merge($stitchedDetections, $recovered);
            echo "   -> Added " . count($recovered) . " missed items.\n";
        } else {
            echo ">> Step 4: Recovery Pass Skipped.\n";
        }

        // --- STEP 5: Final NMS ---
        echo ">> Step 5: Final NMS Deduplication...\n";
        $finalDetections = $this->applyNMS($stitchedDetections);

        $duration = round(microtime(true) - $startTime, 2);
        echo "   Final Count: " . count($finalDetections) . " elements.\n";

        $outputData = [
            'meta' => [
                'model' => $currentModel,
                'timestamp' => date('c'),
                'duration_seconds' => $duration,
                'element_count' => count($finalDetections),
                'options' => [
                    'dynamic_grid' => $this->enableDynamicGrid,
                    'verified' => $this->enableVerification,
                    'succinct' => $this->enableSuccinct,
                    'recovery' => $this->enableRecovery
                ],
                'environment' => $globalInfo
            ],
            'elements' => $finalDetections
        ];

        file_put_contents($outputJsonPath, json_encode($outputData, JSON_PRETTY_PRINT));
        $this->drawHighFidelityBoxes($imagePath, $finalDetections, $outputImagePath, $currentModel);
    }

    /**
     * NEW: Recovery Logic
     * Sends the full image + current detection list to LLM to find what was missed.
     */
    private function findMissingElements($imagePath, $currentDetections, $fullW, $fullH) {
        $this->rolesManager->clearMessages();

        // 1. Simplify detection list to save tokens (just label and bbox)
        $simplifiedList = array_map(function($d) {
            return ['label' => $d['label'], 'bbox' => $d['bbox']];
        }, $currentDetections);

        // If list is massive, maybe slice it or group it, but usually standard UI fits in context.
        $jsonContext = json_encode($simplifiedList);

        $succinctInstr = $this->enableSuccinct ? "Be extremely brief. Output JSON only." : "";

        $prompt = <<<EOT
Analyze this full UI image ({$fullW}x{$fullH}).
I have already detected the following elements:
{$jsonContext}

TASK: Identify any INTERACTIVE UI elements that are MISSING from the list above.
Focus on: Buttons, Inputs, Icons, or Links that I missed.
Ignore items that are already listed or overlap significantly with existing boxes.

{$succinctInstr}

REQUIRED JSON FORMAT:
###JSON_START###
[
  {
    "bbox_2d": [x1,y1,x2,y2], 
    "label": "name", 
    "text_content": "ocr",
    "type": "type", 
    "section": "section", 
    "app_name": "app"
  }
]
###JSON_END###
EOT;

        $this->rolesManager->addMessage('user', $prompt, $imagePath);

        $response = '';
        try {
            $this->llmConnection->queryPost(function ($c) use (&$response) { echo $c; $response .= $c; });
        } catch (Exception $e) {}

        $newItems = $this->extractJson($response);

        // Sanitize and Normalize
        $cleaned = [];
        foreach ($newItems as $item) {
            if (!isset($item['bbox_2d'])) continue;

            // Note: Recovery prompt asks for 0-1000 normalized. Convert to Pixels.
            // If LLM returned pixels directly, this logic might need adjustment based on specific model behavior.
            // Assuming model follows standard VLM normalization (0-1000):
            $b = $item['bbox_2d'];
            $gx1 = intval(($b[0] / 1000) * $fullW);
            $gy1 = intval(($b[1] / 1000) * $fullH);
            $gx2 = intval(($b[2] / 1000) * $fullW);
            $gy2 = intval(($b[3] / 1000) * $fullH);

            $cleaned[] = [
                'label'         => $item['label'] ?? 'Recovered Item',
                'text_content'  => $item['text_content'] ?? '',
                'type'          => 'recovered_element', // Mark special type for debugging
                'section'       => $item['section'] ?? 'Main Window',
                'app_name'      => $item['app_name'] ?? 'Unknown',
                'is_incomplete' => false,
                'bbox'          => [$gx1, $gy1, $gx2, $gy2],
                'area'          => ($gx2 - $gx1) * ($gy2 - $gy1)
            ];
        }

        return $cleaned;
    }

    private function optimizeGridSettings($path, $w, $h) {
        $this->rolesManager->clearMessages();
        $style = $this->enableSuccinct ? "CRITICAL: Be extremely brief. No explanation. Output JSON immediately." : "";
        $prompt = <<<EOT
Analyze this {$w}x{$h} UI screenshot.
TASK: Suggest the optimal Integer values for tile_width (no less than 512), tile_height (no less than 512), and overlap (between 64 and 256) based on UI elements density.
{$style}
Return JSON ONLY: Example: {"tile_width": 1000, "tile_height": 1000, "overlap": 200}
EOT;
        $this->rolesManager->addMessage('user', $prompt, $path);
        $response = '';
        try {
            $this->llmConnection->queryPost(function($c) use (&$response){ echo $c; $response .= $c; });
        } catch(Exception $e) {}
        $data = $this->extractJson($response);
        if ($data) {
            $this->tileWidth = intval($data['tile_width'] ?? 1000);
            $this->tileHeight = intval($data['tile_height'] ?? 1000);
            $this->overlap = intval($data['overlap'] ?? 200);
        }
    }

    private function processGrid($imagePath, $fullW, $fullH, $globalInfo) {
        $detections = [];
        $source = $this->loadImage($imagePath);

        $stepX = $this->tileWidth - $this->overlap;
        $stepY = $this->tileHeight - $this->overlap;
        $tilesToProcess = [];

        for ($y = 0; $y < $fullH; $y += $stepY) {
            for ($x = 0; $x < $fullW; $x += $stepX) {
                $w = min($this->tileWidth, $fullW - $x);
                $h = min($this->tileHeight, $fullH - $y);
                if (($w < 100 || $h < 100) && ($fullW > 200)) continue;
                $tilesToProcess[] = ['x'=>$x, 'y'=>$y, 'w'=>$w, 'h'=>$h];
                if ($x + $w >= $fullW) break;
            }
            if ($y + $h >= $fullH) break;
        }

        $total = count($tilesToProcess);

        foreach ($tilesToProcess as $i => $t) {
            $quadrant = $this->getQuadrantDescription($t['x'], $t['y'], $fullW, $fullH);
            echo "\n   > Tile " . ($i+1) . "/$total | $quadrant | Rolling Context Len: " . strlen($this->rollingContext) . "\n";
            $cropPath = $this->createCropFile($source, $t['x'], $t['y'], $t['w'], $t['h']);
            $localResults = $this->detectInTile($cropPath, $t['w'], $t['h'], $globalInfo, $quadrant);
            echo "\n   Found " . count($localResults) . " items.\n";
            $this->updateRollingContext($localResults);

            foreach ($localResults as $item) {
                if (!isset($item['bbox_2d'])) continue;
                $b = $item['bbox_2d'];
                $gx1 = intval(round((($b[0] / 1000) * $t['w']) + $t['x']));
                $gy1 = intval(round((($b[1] / 1000) * $t['h']) + $t['y']));
                $gx2 = intval(round((($b[2] / 1000) * $t['w']) + $t['x']));
                $gy2 = intval(round((($b[3] / 1000) * $t['h']) + $t['y']));

                $element = [
                    'label'         => $item['label'] ?? 'Unknown',
                    'text_content'  => $item['text_content'] ?? '',
                    'type'          => $item['type'] ?? 'unknown',
                    'section'       => $item['section'] ?? 'Main Window',
                    'app_name'      => $item['app_name'] ?? 'Unknown',
                    'is_incomplete' => $item['is_incomplete'] ?? false,
                    'bbox'          => [$gx1, $gy1, $gx2, $gy2],
                    'area'          => ($gx2 - $gx1) * ($gy2 - $gy1)
                ];

                if ($element['is_incomplete']) {
                    $this->incompleteBuffer[] = $element;
                } else {
                    $detections[] = $element;
                }
            }
            @unlink($cropPath);
        }
        imagedestroy($source);
        return $detections;
    }

    private function detectInTile($path, $w, $h, $globalInfo, $quadrantInfo) {
        $this->rolesManager->clearMessages();
        $appList = implode(', ', $globalInfo['apps']);
        $os = $globalInfo['os'];
        $prevSummary = substr($this->rollingContext, 0, 500);
        $succinctInstr = $this->enableSuccinct ? "CRITICAL INSTRUCTION: Minimize thinking process. Do not chat. Output the JSON block immediately." : "";

        $prompt = <<<EOT
Analyze this UI crop ({$w}x{$h}).
CONTEXT:
1. Global: OS is {$os}. Apps: [{$appList}].
2. Location: This tile is from the {$quadrantInfo}.
3. History: Previously detected: {$prevSummary}...

{$succinctInstr}

TASK: Detect ALL interactive UI elements. If you detect widgets or groups of elements the deconstruct and detect ALL elements from said widget or group.
CRITICAL: Identify if an element is CUT OFF by the edge of this image.

GUIDELINES:
1. **label**: Short descriptive name.
2. **text_content**: Literal OCR text.
3. **section**: Visual parent container.
4. **is_incomplete**: TRUE if the element is cut off visually, FALSE if fully visible.
5. **bbox_2d**: [x1, y1, x2, y2] normalized 0-1000. INTEGERS ONLY.
6. Do not output duplicate objects for the same element.

REQUIRED JSON FORMAT:
###JSON_START###
[
  {
    "bbox_2d": [x1,y1,x2,y2], 
    "label": "name", 
    "text_content": "ocr",
    "type": "type", 
    "section": "section", 
    "app_name": "app",
    "is_incomplete": boolean
  }
]
###JSON_END###
EOT;
        $this->rolesManager->addMessage('user', $prompt, $path);
        $response = '';
        try {
            $this->llmConnection->queryPost(function ($c) use (&$response) { echo $c; $response .= $c; });
        } catch (Exception $e) {}

        $initialJson = $this->extractJson($response);

        if (!empty($initialJson)) {
            $initialJson = array_map("unserialize", array_unique(array_map("serialize", $initialJson)));
            $initialJson = array_values($initialJson);
        }

        if ($this->enableVerification && !empty($initialJson)) {
            $this->rolesManager->addMessage('assistant', $response);
            echo "\n   [VERIFYING RESULTS]...\n";
            return $this->verifyResults();
        }
        return $initialJson;
    }

    private function verifyResults() {
        $reviewPrompt = <<<EOT
CRITICAL REVIEW:
Check the JSON you just generated above.
1. Are any coordinates incorrect?
2. Did you miss any text in the OCR?
3. Are incomplete items marked "is_incomplete": true correctly?
If errors exist, output the CORRECTED JSON block.
If everything is perfect, output the SAME JSON block.
EOT;
        $this->rolesManager->addMessage('user', $reviewPrompt);
        $response = '';
        try {
            $this->llmConnection->queryPost(function ($c) use (&$response) { echo $c; $response .= $c; });
        } catch (Exception $e) {}
        $fixed = $this->extractJson($response);
        return empty($fixed) ? [] : $fixed;
    }

    private function resolveIncompleteItems($fullList, $fullW, $fullH) {
        if (empty($this->incompleteBuffer)) return $fullList;
        echo "\n   [STITCHING] Analyzing " . count($this->incompleteBuffer) . " partial elements...\n";

        $minBuffer = array_map(function($item, $k) {
            return ['id' => $k, 'label' => $item['label'], 'section' => $item['section'], 'bbox' => $item['bbox']];
        }, $this->incompleteBuffer, array_keys($this->incompleteBuffer));
        $bufferJson = json_encode($minBuffer);

        $this->rolesManager->clearMessages();
        $prompt = <<<EOT
I have a list of UI elements detected in tiles that were marked as 'incomplete' (cut off).
Your task is to identify which parts belong to the same element and merge them.
CANVAS SIZE: {$fullW}x{$fullH}
PARTIAL ELEMENTS: {$bufferJson}

INSTRUCTIONS:
1. Look for items with similar labels/sections that are spatially adjacent or overlapping.
2. If ID 1 and ID 5 form a single button, merge them.
3. Output a JSON list of the FINAL COMPLETE elements.
4. Calculate the new bounding box (min_x, min_y, max_x, max_y) for the merged item.

OUTPUT FORMAT:
###JSON_START###
[
  {"ids_merged": [1, 5], "label": "Save", "type": "button", "bbox": [100, 100, 200, 150]}
]
###JSON_END###
EOT;
        $this->rolesManager->addMessage('user', $prompt);
        $response = '';
        try {
            $this->llmConnection->queryPost(function ($c) use (&$response) { echo $c; $response .= $c; });
        } catch (Exception $e) {}

        $merges = $this->extractJson($response);
        $usedIds = [];
        foreach ($merges as $m) {
            $mergedItem = [
                'label' => $m['label'],
                'type' => $m['type'] ?? 'stitched_element',
                'bbox' => $m['bbox'],
                'section' => 'Stitched',
                'text_content' => $m['label'],
                'app_name' => 'Unknown',
                'is_incomplete' => false,
                'area' => ($m['bbox'][2]-$m['bbox'][0]) * ($m['bbox'][3]-$m['bbox'][1])
            ];
            if (isset($m['ids_merged'][0]) && isset($this->incompleteBuffer[$m['ids_merged'][0]])) {
                $parent = $this->incompleteBuffer[$m['ids_merged'][0]];
                $mergedItem['section'] = $parent['section'];
                $mergedItem['app_name'] = $parent['app_name'];
            }
            $fullList[] = $mergedItem;
            foreach ($m['ids_merged'] as $id) $usedIds[$id] = true;
            echo "   -> Merged IDs [" . implode(',', $m['ids_merged']) . "] into '{$mergedItem['label']}'\n";
        }
        foreach ($this->incompleteBuffer as $k => $item) {
            if (!isset($usedIds[$k])) {
                $item['is_incomplete'] = true;
                $fullList[] = $item;
            }
        }
        return $fullList;
    }

    private function getQuadrantDescription($x, $y, $w, $h) {
        $hStr = ($x < $w/3) ? "Left" : (($x < 2*$w/3) ? "Center" : "Right");
        $vStr = ($y < $h/3) ? "Top" : (($y < 2*$h/3) ? "Middle" : "Bottom");
        return "{$vStr}-{$hStr} Area (coords: $x,$y)";
    }

    private function updateRollingContext($newItems) {
        $found = [];
        foreach ($newItems as $item) {
            $key = $item['section'] . ": " . $item['label'];
            $found[] = $key;
        }
        $summary = implode(", ", array_slice(array_unique($found), 0, 5));
        if (!empty($summary)) {
            $this->rollingContext .= " Found: [$summary].";
        }
        if (strlen($this->rollingContext) > 800) {
            $this->rollingContext = "..." . substr($this->rollingContext, -800);
        }
    }

    // --- UTILS ---

    private function getGlobalContext($path) {
        $this->rolesManager->clearMessages();
        $prompt = <<<EOT
Analyze this full screenshot.
1. Identify the Operating System.
2. List the names of all open Application windows visibly active.
Output JSON only: {"os": "Windows 11", "apps": ["Chrome"]}
EOT;
        $this->rolesManager->addMessage('user', $prompt, $path);
        $response = '';
        try {
            $this->llmConnection->queryPost(function($c) use (&$response){ echo $c; $response .= $c; });
        } catch(Exception $e) {}

        $clean = str_replace(['```json', '```'], '', preg_replace('/<think>.*?<\/think>/s', '', $response));
        $start = strpos($clean, '{'); $end = strrpos($clean, '}');
        if ($start !== false && $end !== false) {
            return json_decode(substr($clean, $start, $end-$start+1), true) ?: ['os'=>'Unk','apps'=>[]];
        }
        return ['os'=>'Unk','apps'=>[]];
    }

    private function extractJson($text) {
        $clean = preg_replace('/<think>.*?<\/think>/s', '', $text);
        $jsonCandidate = null;
        if (preg_match_all('/###JSON_START###(.*?)###JSON_END###/s', $text, $matches)) $jsonCandidate = end($matches[1]);
        elseif (preg_match('/###JSON_START###(.*)$/s', $text, $matches)) $jsonCandidate = $matches[1];
        elseif (preg_match_all('/```json\s*(.*?)\s*```/s', $clean, $matches)) $jsonCandidate = end($matches[1]);
        elseif (preg_match_all('/\[\s*\{.*?\}\s*\]/s', $clean, $matches)) $jsonCandidate = end($matches[0]);
        elseif (preg_match('/\{[^{}]+\}/s', $clean, $matches)) $jsonCandidate = $matches[0];
        if (!$jsonCandidate) return [];
        return json_decode(trim($jsonCandidate), true) ?: $this->repairTruncatedJson($jsonCandidate);
    }

    private function repairTruncatedJson($str) {
        $lastBracket = strrpos($str, '}');
        if ($lastBracket === false) return [];
        return json_decode(substr($str, 0, $lastBracket + 1) . ']', true) ?: [];
    }

    private function createCropFile($source, $x, $y, $w, $h) {
        $dest = imagecreatetruecolor($w, $h);
        imagecopy($dest, $source, 0, 0, $x, $y, $w, $h);
        $tmp = sys_get_temp_dir() . "/crop_" . uniqid() . ".jpg";
        imagejpeg($dest, $tmp, 100);
        imagedestroy($dest);
        return $tmp;
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
                $sameLabel = ($base['label'] == $cand['label']);

                if ($iou > 0.5 || ($iou > 0.3 && $sameLabel)) {
                    $cluster['x1'][] = $cand['bbox'][0]; $cluster['y1'][] = $cand['bbox'][1];
                    $cluster['x2'][] = $cand['bbox'][2]; $cluster['y2'][] = $cand['bbox'][3];
                    $active[$j] = false;

                    if (empty($base['text_content']) && !empty($cand['text_content'])) {
                        $base['label'] = $cand['label'];
                        $base['text_content'] = $cand['text_content'];
                        $base['section'] = $cand['section'];
                    }
                }
            }
            $cnt = count($cluster['x1']);
            $base['bbox'] = [
                intval(array_sum($cluster['x1'])/$cnt), intval(array_sum($cluster['y1'])/$cnt),
                intval(array_sum($cluster['x2'])/$cnt), intval(array_sum($cluster['y2'])/$cnt)
            ];
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

        $colors = [
            'button' => [50, 205, 50],
            'input' => [0, 191, 255],
            'stitched_element' => [255, 0, 0],
            'recovered_element' => [148, 0, 211], // Violet for recovered
            'incomplete' => [255, 140, 0],
            'unknown'=> [128, 128, 128]
        ];
        $black = imagecolorallocate($img, 0, 0, 0);

        foreach ($detections as $d) {
            $colorKey = $d['type'] ?? 'unknown';
            if (!empty($d['is_incomplete'])) $colorKey = 'incomplete';
            if (($d['type'] ?? '') === 'recovered_element') $colorKey = 'recovered_element';

            $rgb = $colors[$colorKey] ?? $colors['unknown'];
            $borderCol = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            $fillCol = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 95);

            $b = $d['bbox'];
            imagefilledrectangle($img, $b[0], $b[1], $b[2], $b[3], $fillCol);
            imagerectangle($img, $b[0], $b[1], $b[2], $b[3], $borderCol);

            // UPDATED: Check if Labels should be drawn
            if ($this->enableLabelDrawing) {
                $label = substr($d['label'], 0, 30);
                if (!empty($d['is_incomplete'])) $label .= " (partial)";

                $ly = max(0, $b[1] - 14);
                imagefilledrectangle($img, $b[0], $ly, $b[0] + (strlen($label)*7), $ly + 14, $black);
                imagestring($img, 2, $b[0]+1, $ly, $label, $borderCol);
            }
        }

        imagejpeg($img, $outputPath, 100);
        imagedestroy($img);
        echo "   Saved image: $outputPath\n";
    }
}

// --- RUNNER ---

$detector = new ProductionUIDetector();

// ARGS: DynamicGrid, Verify, Succinct, DrawLabels, RecoverMissing
$useDynamic = true;
$useVerify = false;
$useSuccinct = true;
$drawLabels = false;     // NEW: Draw labels on output image?
$recoverMissing = false; // NEW: Ask LLM to find what it missed?

$detector->setOptions($useDynamic, $useVerify, $useSuccinct, $drawLabels, $recoverMissing);

$models = [
    'Qwen3-VL-8B-Instruct',
    'Qwen3-VL-30B-A3B-Instruct',
    'Qwen3-VL-32B-Instruct-UD-Q8_K_XL',

    'Qwen3-VL-8B-Thinking',
    'Qwen3-VL-30B-A3B-Thinking',
    'Qwen3-VL-32B-Thinking-UD-Q8_K_XL'
];

global $argv;
$inputImage = null;
foreach ($argv as $arg) {
    if (!str_starts_with($arg, '-') && preg_match('/\.(png|jpg|jpeg)$/i', $arg)) {
        $inputImage = $arg; break;
    }
}
if (!$inputImage) $inputImage = __DIR__ . '/images/ui_screenshot.png';

$pathInfo = pathinfo($inputImage);
$baseName = $pathInfo['filename'];
$dir = $pathInfo['dirname'];

$suffix = "";
if ($useDynamic) $suffix .= "_DG";
if ($useVerify) $suffix .= "_VF";
if ($useSuccinct) $suffix .= "_S";
if ($drawLabels) $suffix .= "_DL";
if ($recoverMissing) $suffix .= "_REC";

echo "Starting Enhanced Benchmark on: $baseName (Options: $suffix)\n";

foreach ($models as $modelName) {
    $safeName = str_replace(['/', ':', '\\', ' '], '_', $modelName);
    $json = $dir . '/' . $baseName . '-' . $safeName . $suffix . '.json';
    $jpg  = $dir . '/' . $baseName . '_annotated-' . $safeName . $suffix . '.jpg';

    $detector->setModel($modelName);
    $detector->execute($inputImage, $json, $jpg);
}