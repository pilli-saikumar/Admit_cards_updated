<?php
ob_start();
session_start();
set_time_limit(0); 
ini_set('memory_limit', '1024M'); // Reduced from 2048M
ini_set('max_execution_time', 0);

// Redirect if the user is not logged in
$user_role = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : null;
if (!isset($_SESSION["user_role"])) {
    header("Location: /Admit_Cards/index.php");
    exit();
}

// Include database connection
include("../db_connect.php");

// Include FPDI, which includes FPDF automatically
require_once('../vendor/autoload.php');
use setasign\Fpdi\Fpdi;

$project_id = intval($_POST['project_id']);
$filter_column = "all";

if (!empty($project_id)) {
    $get_slug = $conn->query("SELECT slug FROM projects WHERE id = $project_id");
    $project_slug = $get_slug->fetch_assoc()['slug'] ?? null;
}

if ($project_id === 0) {
    die("Project ID is required.");
}

// 1. Create temporary directory for individual PDFs
$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'admit_cards_' . $project_id . '_' . time();
if (!mkdir($temp_dir, 0755, true)) {
    die("Cannot create temporary directory");
}

// 2. Fetch templates once and cache them
if ($filter_column === 'all') {
    $templateQuery = $conn->prepare("SELECT id, template_image_path FROM project_templates WHERE project_id = ?");
    $templateQuery->bind_param("i", $project_id);
} else {
    $templateQuery = $conn->prepare("SELECT id, template_image_path FROM project_templates WHERE project_id = ? AND columns_name = ?");
    $templateQuery->bind_param("is", $project_id, $filter_column);
}
$templateQuery->execute();
$templateResult = $templateQuery->get_result();
$all_templates = $templateResult->fetch_all(MYSQLI_ASSOC);
$templateQuery->close();

// 3. Pre-fetch and cache field mappings to avoid repeated queries
$field_mappings = [];
foreach ($all_templates as $template) {
    $template_id = $template['id'];
    $fieldQuery = $conn->prepare("SELECT column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style
                                  FROM field_mappings WHERE project_id = ? AND template_id = ?");
    $fieldQuery->bind_param("ii", $project_id, $template_id);
    $fieldQuery->execute();
    $field_mappings[$template_id] = $fieldQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $fieldQuery->close();
}

// 4. Count total records and process in very small batches
if ($filter_column === 'all') {
    $countQuery = "SELECT COUNT(*) as total FROM admit_card_records WHERE project_id = ?";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("i", $project_id);
} else {
    $project_details = $conn->prepare("SELECT column_based FROM projects WHERE id = ?");
    $project_details->bind_param("i", $project_id);
    $project_details->execute();
    $column_based = $project_details->get_result()->fetch_assoc();
    $column_name = $column_based['column_based'];
    $project_details->close();

    $countQuery = "SELECT COUNT(*) as total FROM admit_card_records WHERE project_id = ? AND `$column_name` = ?";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("is", $project_id, $filter_column);
}

$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

if ($totalRecords == 0) {
    die("No records found for this project.");
}

// 5. Process ONE record at a time and save to individual files
$batchSize = 1; // Process one at a time to minimize memory usage
$temp_files = [];

for ($offset = 0; $offset < $totalRecords; $offset += $batchSize) {
    // Fetch single record
    if ($filter_column === 'all') {
        $query = "SELECT a.*, p.* FROM admit_card_records a
                  LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
                  WHERE a.project_id = ? LIMIT ? OFFSET ?";
        $dataQuery = $conn->prepare($query);
        $dataQuery->bind_param("iii", $project_id, $batchSize, $offset);
    } else {
        $query = "SELECT a.*, p.* FROM admit_card_records a
                  LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
                  WHERE a.project_id = ? AND a.`$column_name` = ? LIMIT ? OFFSET ?";
        $dataQuery = $conn->prepare($query);
        $dataQuery->bind_param("isii", $project_id, $filter_column, $batchSize, $offset);
    }

    $dataQuery->execute();
    $record = $dataQuery->get_result()->fetch_assoc();
    $dataQuery->close();

    if (!$record) continue;

    // Create individual PDF for this single record
    $pdf = new FPDF();
    
    foreach ($all_templates as $template) {
        $template_id = $template['id'];
        $templateImage = $template['template_image_path'] ?? '';
        $imgPath = __DIR__ . '/../' . $templateImage;

        if (!file_exists($imgPath) || pathinfo($imgPath, PATHINFO_EXTENSION) == "") {
            continue;
        }

        $pdf->AddPage();
        
        // Load template image with size check
        $templateSize = filesize($imgPath);
        if ($templateSize > 10 * 1024 * 1024) { // Skip templates larger than 10MB
            error_log("Skipping large template: " . $imgPath);
            continue;
        }
        
        $pdf->Image($imgPath, 0, 0, 210, 297);

        // Use cached field mappings
        $fields = $field_mappings[$template_id] ?? [];

        foreach ($fields as $field) {
            $value = $record[$field['column_name']] ?? '';
            
            // Handle photo/signature paths
            if ($field['column_name'] === 'photo_path' || $field['column_name'] === 'signature_path') {
                $column_based = $record['column_based'] ?? 'id';
                $get_id = $record[$column_based] ?? $record['id'];
                $value = ($record['path'] ?? '') . $get_id . ($record['prefix'] ?? '') . '.' . ($record['extension'] ?? '');
            }

            $bar_code = $field['column_name'] === 'bar_code';
            $value = str_replace('\\', '/', trim($value));

            // Font settings
            $fontType = strtolower($field['font_type']);
            if (!in_array($fontType, ['arial', 'helvetica', 'courier', 'times'])) {
                $fontType = 'Arial';
            }

            $styleMap = ['bold' => 'B', 'italic' => 'I', 'underline' => 'U', 'bolditalic' => 'BI', 'bold italic' => 'BI', 'normal' => '', '' => ''];
            $fontStyle = $styleMap[strtolower(trim($field['font_style']))] ?? '';

            $pdf->SetFont(ucfirst($fontType), $fontStyle, intval($field['font_size']));
            $rgb = hexToRGB($field['font_color']);
            if ($rgb) {
                $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
            }

            // Position calculations
            $scaleX = 210 / 595;
            $scaleY = 297 / 842;
            $x = floatval($field['x_position']) * $scaleX;
            $y = floatval($field['y_position']) * $scaleY;
            $width = max(0.1, floatval($field['width']) * $scaleX);
            $height = max(0.1, floatval($field['height']) * $scaleY);

            $pdf->SetXY($x, $y);

            // Handle barcode
            if ($bar_code && !empty($value)) {
                $barcodeimage = "https://admitcards.iroams.com/Admit_Cards/dashboard/b/barcode.php?size=30&print=false&text=" . urlencode($value);
                try {
                    $pdf->Image($barcodeimage, $x, $y, $width, $height, 'PNG');
                } catch (Exception $e) {
                    error_log("Barcode error: " . $e->getMessage());
                }
                continue;
            }

            $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));

            // Handle images with strict size limits
            if (in_array($ext, ['jpg', 'jpeg', 'png']) && !empty($value)) {
                $path_type = $record['path_type'] ?? '';
                $imageExists = false;
                
                if ($path_type === 'Local Path') {
                    $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
                    if (file_exists($imgPath)) {
                        $imageSize = filesize($imgPath);
                        if ($imageSize < 5 * 1024 * 1024) { // Only load images smaller than 5MB
                            $imageExists = true;
                        }
                    }
                } else {
                    $imgPath = $value;
                    if (filter_var($imgPath, FILTER_VALIDATE_URL)) {
                        // Skip URL images to avoid network delays and memory issues
                        $imageExists = false;
                    }
                }

                if ($imageExists) {
                    try {
                        $pdf->Image($imgPath, $x, $y, $width, $height);
                    } catch (Exception $e) {
                        $pdf->SetTextColor(255, 0, 0);
                        $pdf->Cell($width, $height, 'Image Error', 0, 0, 'C');
                        $pdf->SetTextColor(0, 0, 0);
                    }
                } else {
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell($width, $height, 'Image not found', 0, 0, 'C');
                    $pdf->SetTextColor(0, 0, 0);
                }
            } else {
                // Handle text
                if ($field['cell_type'] === 'MultiCell') {
                    $pdf->MultiCell($width, 4, strtoupper($value), 0, 1, '');
                } else {
                    $pdf->Cell($width, $height, $value, 0, 0, 'L');
                }
            }
        }
    }
    
    // Save individual PDF to temporary file
    $temp_file = $temp_dir . DIRECTORY_SEPARATOR . 'admit_card_' . ($offset + 1) . '.pdf';
    $pdf->Output('F', $temp_file);
    $temp_files[] = $temp_file;
    
    // Immediately free memory
    unset($pdf, $record);
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // Log progress every 10 records
    if (($offset + 1) % 10 == 0) {
        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        error_log("Processed " . ($offset + 1) . "/$totalRecords records. Memory: {$currentMemory}MB, Peak: {$peakMemory}MB");
    }
}

// 6. Merge all temporary PDFs using FPDI in small batches
$merged_pdf = new Fpdi();
$merge_batch_size = 10; // Merge 10 PDFs at a time

for ($i = 0; $i < count($temp_files); $i += $merge_batch_size) {
    $batch_files = array_slice($temp_files, $i, $merge_batch_size);
    
    foreach ($batch_files as $temp_file) {
        if (!file_exists($temp_file)) continue;
        
        try {
            $pageCount = $merged_pdf->setSourceFile($temp_file);
            for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                $tplId = $merged_pdf->importPage($pageNum);
                $size = $merged_pdf->getTemplateSize($tplId);
                $merged_pdf->AddPage($size['orientation']);
                $merged_pdf->useTemplate($tplId);
            }
        } catch (Exception $e) {
            error_log("Error merging PDF: " . $e->getMessage() . " - File: " . $temp_file);
        }
        
        // Delete temporary file immediately after merging
        unlink($temp_file);
    }
    
    // Force garbage collection after each batch
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}

// 7. Clean up temporary directory
rmdir($temp_dir);

// 8. Output the merged PDF
ob_end_clean();
$merged_filename = 'All_Admit_Cards_' . date('Ymd_His') . '.pdf';
$merged_pdf->Output('D', $merged_filename);

exit();

// Hex to RGB helper function
function hexToRGB($hexColor) {
    $hex = str_replace("#", "", $hexColor);
    if (strlen($hex) === 3) {
        $r = hexdec(str_repeat($hex[0], 2));
        $g = hexdec(str_repeat($hex[1], 2));
        $b = hexdec(str_repeat($hex[2], 2));
    } elseif (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    } else {
        return null;
    }
    return [$r, $g, $b];
}
?>