<?php
ob_start();
session_start();
set_time_limit(0); 
ini_set('memory_limit', '2048M');

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

// 1. Fetch all templates for this project once
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

// 2. Count total records first to determine batch processing
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

// 3. Process records in batches to avoid memory issues
$batchSize = 50; // Adjust based on your server capacity
$merged_pdf = new Fpdi();

for ($offset = 0; $offset < $totalRecords; $offset += $batchSize) {
    // Fetch batch of records
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
    $batch_records = $dataQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $dataQuery->close();

    // Process each record in the batch
    foreach ($batch_records as $data) {
        $pdf = new FPDF();

        foreach ($all_templates as $template) {
            $template_id = $template['id'];
            $templateImage = $template['template_image_path'] ?? '';
            $imgPath = __DIR__ . '/../' . $templateImage;

            if (!file_exists($imgPath) || pathinfo($imgPath, PATHINFO_EXTENSION) == "") {
                continue;
            }

            $pdf->AddPage();
            
            // Optimize image loading - check file size and resize if needed
            $imageSize = filesize($imgPath);
            if ($imageSize > 5 * 1024 * 1024) { // If image is larger than 5MB
                // Consider resizing or using a compressed version
                error_log("Large template image detected: " . $imgPath . " (" . number_format($imageSize/1024/1024, 2) . "MB)");
            }
            
            $pdf->Image($imgPath, 0, 0, 210, 297);

            // Fetch field mappings for this template (use prepared statement to avoid repeated queries)
            if (!isset($fieldQueries[$template_id])) {
                $fieldQuery = $conn->prepare("SELECT column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style
                                              FROM field_mappings WHERE project_id = ? AND template_id = ?");
                $fieldQuery->bind_param("ii", $project_id, $template_id);
                $fieldQuery->execute();
                $fieldQueries[$template_id] = $fieldQuery->get_result()->fetch_all(MYSQLI_ASSOC);
                $fieldQuery->close();
            }
            
            $fields = $fieldQueries[$template_id];

            // Loop through fields and add content to the PDF
            foreach ($fields as $field) {
                $value = $data[$field['column_name']] ?? '';
                
                if ($field['column_name'] === 'photo_path' || $field['column_name'] === 'signature_path') {
                    $column_based = $data['column_based'] ?? 'id';
                    $get_id = $data[$column_based] ?? $data['id'];
                    $value = ($data['path'] ?? '') . $get_id . ($data['prefix'] ?? '') . '.' . ($data['extension'] ?? '');
                    $photoType = $data['photo_type'] ?? '';
                }

                $bar_code = $field['column_name'] === 'bar_code';
                $value = str_replace('\\', '/', trim($value));

                // Font handling
                $fontType = strtolower($field['font_type']);
                if (!in_array($fontType, ['arial', 'helvetica', 'courier', 'times'])) {
                    $fontType = 'Arial';
                }

                $styleMap = [
                    'bold' => 'B', 'italic' => 'I', 'underline' => 'U',
                    'bolditalic' => 'BI', 'bold italic' => 'BI', 
                    'normal' => '', '' => ''
                ];
                $fontStyle = strtolower(trim($field['font_style']));
                $fontStyle = $styleMap[$fontStyle] ?? '';

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
                        error_log("Barcode generation failed: " . $e->getMessage());
                    }
                    continue;
                }

                $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));

                // Handle images
                if (in_array($ext, ['jpg', 'jpeg', 'png']) && !empty($value)) {
                    $path_type = $data['path_type'] ?? '';
                    $imageExists = false;
                    
                    if ($path_type === 'Local Path') {
                        $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
                        $imageExists = file_exists($imgPath);
                        
                        // Check image file size before loading
                        if ($imageExists && filesize($imgPath) > 10 * 1024 * 1024) { // 10MB limit
                            error_log("Large image detected: " . $imgPath . " (" . number_format(filesize($imgPath)/1024/1024, 2) . "MB)");
                            $imageExists = false; // Skip large images
                        }
                    } else {
                        $imgPath = $value;
                        if (filter_var($imgPath, FILTER_VALIDATE_URL)) {
                            $headers = @get_headers($imgPath, 1);
                            if ($headers && strpos($headers[0], '200') !== false) {
                                $imageSize = @getimagesize($imgPath);
                                $imageExists = ($imageSize !== false);
                            }
                        }
                    }

                    if ($imageExists) {
                        try {
                            $pdf->Image($imgPath, $x, $y, $width, $height);
                        } catch (Exception $e) {
                            $pdf->SetTextColor(255, 0, 0);
                            $pdf->SetXY($x, $y);
                            $pdf->Cell($width, $height, 'Image Error', 0, 0, 'C');
                            $pdf->SetTextColor(0, 0, 0);
                        }
                    } else {
                        $pdf->SetTextColor(255, 0, 0);
                        $pdf->SetXY($x, $y);
                        $pdf->Cell($width, $height, 'Image not found', 0, 0, 'C');
                        $pdf->SetTextColor(0, 0, 0);
                    }
                } else {
                    // Handle text
                    if ($field['cell_type'] === 'MultiCell') {
                        $pdf->MultiCell($width, 4, strtoupper($value), 0, 1, '');
                    } else {
                        $pdf->SetXY($x, $y);
                        $pdf->Cell($width, $height, $value, 0, 0, 'L');
                    }
                }
            }
        }
        
        // Import the generated PDF pages directly into the merged PDF
        try {
            $pdfContent = $pdf->Output('S');
            $pageCount = $merged_pdf->setSourceFile('data://application/pdf;base64,' . base64_encode($pdfContent));
            
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $merged_pdf->importPage($i);
                $size = $merged_pdf->getTemplateSize($tplId);
                $merged_pdf->AddPage($size['orientation']);
                $merged_pdf->useTemplate($tplId);
            }
        } catch (Exception $e) {
            error_log("FPDI Merge Error: " . $e->getMessage());
        }
        
        // Free memory immediately after processing each record
        unset($pdf, $pdfContent);
    }
    
    // Clear batch data and force garbage collection
    unset($batch_records);
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    // Log memory usage for monitoring
    $currentMemory = memory_get_usage(true) / 1024 / 1024;
    $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
    error_log("Batch " . ($offset/$batchSize + 1) . " completed. Current memory: {$currentMemory}MB, Peak: {$peakMemory}MB");
}

// Output the single merged PDF directly to the browser for download
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