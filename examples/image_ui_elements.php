<?php

require_once '../vendor/autoload.php';

use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;

class UIButtonDetector {
    private $llmConnection;
    private $rolesManager;
    
    public function __construct() {
        $this->llmConnection = new OpenAICompatibleEndpointConnection();
        // Increase timeout for large image uploads or slow inference
        $this->llmConnection->setConnectionTimeout(600); 
        $this->llmConnection->setLLMmodelName('Qwen3-VL-235B-A22B-Instruct-Q6');
        $this->rolesManager = $this->llmConnection->getRolesManager();
    }
    
    /**
     * Detects buttons using the ORIGINAL resolution for maximum accuracy.
     */
    public function detectButtonsFromFile($imagePath, $outputFile = null) {
        $this->rolesManager->clearMessages();
        
        if (!file_exists($imagePath)) die("File not found: $imagePath");
        
        // 1. Get Original Dimensions to inform the LLM
        $dims = getimagesize($imagePath);
        if ($dims === false) die("Could not determine image size.");
        list($width, $height) = $dims;
        $fileSizeMB = filesize($imagePath) / 1024 / 1024;
        
        echo "Processing High-Res Image: $width x $height ({$fileSizeMB} MB)\n";

        // 2. Heavy File Handling
        // If file is > 10MB (common API limit) or is PNG (slow upload), 
        // convert to High-Quality JPEG (100%) to reduce bytes WITHOUT reducing resolution.
        $uploadPath = $imagePath;
        $isTemp = false;
        
        // Uncomment this block if you run into API payload size limits
        /*
        if ($fileSizeMB > 10 || strtolower(pathinfo($imagePath, PATHINFO_EXTENSION)) === 'png') {
            echo "Optimizing file format (preserving resolution)... ";
            $uploadPath = $this->createHighQualityJpeg($imagePath);
            $isTemp = true;
            echo "Done.\n";
        }
        */

        // 3. Prompt with EXPLICIT Dimensions
        // This fixes the alignment issue. By telling the model the aspect ratio,
        // it knows 0-1000 covers a wide rectangle, not a square.
        $prompt = <<<EOT
You are an expert UI Analysis Assistant for GUI Automation.
Your task is to detect and classify every interactive element in this screenshot.

IMAGE CONTEXT:
- Actual Dimensions: {$width} pixels width x {$height} pixels height.
- Aspect Ratio: The image is NOT necessarily square. It is likely a wide monitor screenshot.
- Coordinate Space: You must map the rectangular image space to a normalized 0-1000 scale. 
  (e.g., In this {$width}x{$height} image, X=500 is the horizontal center, not the diagonal center of a square).

DETECTION SCOPE - Identify all elements in these categories:
1.  **Window Management**: Minimize, Maximize, Close buttons (top-right of windows), resize handles, and Draggable Title Bars.
2.  **Navigation**: Browser tabs, Ribbon tabs (Office apps), Sidebar menu items, Breadcrumbs, Pagination links.
3.  **Form Controls**: Text inputs (detect placeholder text), Dropdowns (detect the arrow), Checkboxes (checked/unchecked), Radio buttons, Sliders, Toggles.
4.  **System Shell**: 
    - Taskbar items / Dock icons.
    - System Tray area (Clock, Network, Volume, Notifications).
    - Start Menu / App Launcher buttons.
5.  **Content Interactivity**: Hyperlinks inside text, clickable images/thumbnails, video player controls (Play/Pause/Scrub).
6.  **Scrollbars**: Vertical and horizontal scroll thumbs and arrows.

LABELING RULES:
- **Visible Text**: Use the exact text visible on the element (e.g., "Submit", "File").
- **Icons**: If no text, provide a semantic description (e.g., "Settings Gear", "User Avatar", "Search Icon").
- **Inputs**: Use the placeholder text or the nearest label to the left/top of the input field.

OUTPUT FORMAT:
Return a strictly valid JSON array of objects.
{
    "bbox_2d": [x1, y1, x2, y2],  // Normalized 0-1000
    "label": "String name of element",
    "app": "Inferred application name (e.g., 'Chrome', 'Word', 'System')",
    "type": "button | input | link | tab | icon | menu | window_control"
}

Do not include markdown formatting. Return ONLY the JSON array.
EOT;
        
        $this->rolesManager->addMessage('user', $prompt, $uploadPath);
        
        echo "Sending full-resolution data to LLM...\n";
        
        $response = '';
        $this->llmConnection->queryPost(function ($chunk) use (&$response) {
            echo $chunk;
            $response .= $chunk;
        });
        
        // Clean up temp file if we made one
        if ($isTemp && file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        
        $buttonData = $this->extractJsonFromResponse($response);
        
        if ($outputFile) {
            file_put_contents($outputFile, json_encode($buttonData, JSON_PRETTY_PRINT));
            echo "Results saved to $outputFile\n";
        }
        
        return $buttonData;
    }
    
    public function visualizeButtons($imagePath, $buttonData, $outputPath = null) {
        if (!extension_loaded('gd')) die("Error: GD extension required.\n");
        
        $image = $this->loadImage($imagePath);
        if (!$image) return null;
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // High-Visibility Lines for High-Res Images
        // For 2557px width, this sets line thickness to ~5-6px
        $thickness = max(1, intval($width / 400)); 
        imagesetthickness($image, $thickness);
        
        // Vibrant Colors
        $colors = [
            imagecolorallocate($image, 255, 0, 0),    // Red
            imagecolorallocate($image, 50, 205, 50),  // Lime Green
            imagecolorallocate($image, 0, 100, 255),  // Bright Blue
            imagecolorallocate($image, 255, 165, 0),  // Orange
            imagecolorallocate($image, 255, 0, 255),  // Magenta
        ];
        
        $white = imagecolorallocate($image, 255, 255, 255);
        
        // Semi-transparent background for text labels so they are readable over UI
        // Note: GD alpha is 0 (opaque) to 127 (transparent)
        $bgLabel = imagecolorallocatealpha($image, 0, 0, 0, 40); 
        
        foreach ($buttonData as $i => $item) {
            if (!isset($item['bbox_2d']) || count($item['bbox_2d']) < 4) continue;
            
            $bbox = $item['bbox_2d'];
            $color = $colors[$i % count($colors)];
            
            // --- LOGIC FIX: Normalized Detection ---
            // Default to normalized (true) as per prompt instructions.
            // Only switch to absolute if values are obviously outside 0-1000 range.
            // We use 1005 as a threshold to allow for minor model float rounding errors.
            $isNormalized = true;
            foreach ($bbox as $val) {
                if ($val > 1005) $isNormalized = false;
            }
            
            if ($isNormalized) {
                // Formula: (Value / 1000) * Dimension
                $x1 = ($bbox[0] / 1000) * $width;
                $y1 = ($bbox[1] / 1000) * $height;
                $x2 = ($bbox[2] / 1000) * $width;
                $y2 = ($bbox[3] / 1000) * $height;
            } else {
                // Absolute pixels
                $x1 = $bbox[0]; $y1 = $bbox[1];
                $x2 = $bbox[2]; $y2 = $bbox[3];
            }
            
            // --- LOGIC FIX: Safety Clamping ---
            // Ensure coordinates are integers and strictly within image bounds (0 to width-1)
            $x1 = (int)max(0, min($width - 1, $x1));
            $y1 = (int)max(0, min($height - 1, $y1));
            $x2 = (int)max(0, min($width - 1, $x2));
            $y2 = (int)max(0, min($height - 1, $y2));

            // Draw Box
            imagerectangle($image, $x1, $y1, $x2, $y2, $color);
            
            // Optional: Draw Label
            /*
            $label = $item['label'] ?? 'Element';
            $fontSize = 3; 
            $fontH = imagefontheight($fontSize);
            $ly = $y1 - $fontH - 5;
            if ($ly < 0) $ly = $y2 + 5; 
            imagestring($image, $fontSize, $x1 + 3, $ly, $label, $white);
            */
        }
        
        if (!$outputPath) {
            $pathInfo = pathinfo($imagePath);
            $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_annotated.' . $pathInfo['extension'];
        }
        
        // Save result
        if (strpos(strtolower($outputPath), '.png') !== false) {
            imagepng($image, $outputPath, 6);
        } else {
            imagejpeg($image, $outputPath, 95);
        }
        
        imagedestroy($image);
        echo "Annotated image saved to: $outputPath\n";
    }

    private function extractJsonFromResponse($text) {
        // Remove markdown code blocks if present
        $text = preg_replace('/```json\s*|\s*```/', '', $text);
        
        // Find the outer brackets
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        
        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($text, $start, ($end - $start) + 1);
            $data = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            } else {
                echo "JSON Decode Error: " . json_last_error_msg() . "\n";
            }
        }
        return [];
    }

    private function loadImage($path) {
        if (!file_exists($path)) return null;
        $type = exif_imagetype($path);
        switch ($type) {
            case IMAGETYPE_JPEG: return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG: return imagecreatefrompng($path);
            case IMAGETYPE_GIF: return imagecreatefromgif($path);
            default: return null;
        }
    }

    /**
     * Converts PNG to high-quality JPG to save file size 
     * without resizing the dimensions/resolution.
     */
    private function createHighQualityJpeg($path) {
        $img = $this->loadImage($path);
        if (!$img) return $path; 
        
        $w = imagesx($img);
        $h = imagesy($img);
        $temp = imagecreatetruecolor($w, $h);
        
        // White background for transparent PNGs
        $white = imagecolorallocate($temp, 255, 255, 255);
        imagefilledrectangle($temp, 0, 0, $w, $h, $white);
        imagecopy($temp, $img, 0, 0, 0, 0, $w, $h);
        
        $tempPath = sys_get_temp_dir() . '/full_res_' . uniqid() . '.jpg';
        imagejpeg($temp, $tempPath, 95);
        
        imagedestroy($img);
        imagedestroy($temp);
        
        return $tempPath;
    }
}

// --- Main Execution ---

$detector = new UIButtonDetector();

global $argv;
$imagePath = null;

// Simple argument parser to find the image file
foreach ($argv as $arg) {
    if (!str_starts_with($arg, '-') && preg_match('/\.(png|jpg|jpeg)$/i', $arg)) {
        $imagePath = $arg;
        break;
    }
}

if (!$imagePath) {
    // Default fallback
    $imagePath = __DIR__ . '/images/ui_screenshot.png';
    // Create dir if not exists for demo purposes
    if (!is_dir(__DIR__ . '/images')) mkdir(__DIR__ . '/images');
}

$pathInfo = pathinfo($imagePath);
$jsonOutput = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.json';
$imgOutput = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_annotated.jpg';
$data = $detector->detectButtonsFromFile($imagePath, $jsonOutput);
$detector->visualizeButtons($imagePath, $data, $imgOutput);
