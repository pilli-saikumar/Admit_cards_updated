<?php
ob_start();
session_start();
set_time_limit(0); 
ini_set('memory_limit', '2048M'); // Increase memory limit

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

// Create temporary directory for PDF files
$temp_dir = sys_get_temp_dir() . '/admit_cards_' . uniqid();
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0777, true);
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

// 2. Fetch all records (candidates) once
if ($filter_column === 'all') {
    $query = "SELECT a.*, p.* FROM admit_card_records a
              LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
              WHERE a.project_id = ?";
    $dataQuery = $conn->prepare($query);
    $dataQuery->bind_param("i", $project_id);
} else {
    $project_deatails = $conn->prepare("SELECT column_based FROM projects WHERE id = ?");
    $project_deatails->bind_param("i", $project_id);
    $project_deatails->execute();
    $column_based = $project_deatails->get_result()->fetch_assoc();
    $column_name = $column_based['column_based'];
    $project_deatails->close();

    $query = "SELECT a.*, p.* FROM admit_card_records a
              LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
              WHERE a.project_id = ? AND a.`$column_name` = ?";
    $dataQuery = $conn->prepare($query);
    $dataQuery->bind_param("is", $project_id, $filter_column);
}

$dataQuery->execute();
$all_records = $dataQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$dataQuery->close();

if (empty($all_records)) {
    // Clean up temp directory
    if (file_exists($temp_dir)) {
        array_map('unlink', glob("$temp_dir/*.*"));
        rmdir($temp_dir);
    }
    die("No records found for this project.");
}

// Array to hold temporary PDF file paths
$temp_pdf_files = [];
$batch_size = 50; // Process records in batches to avoid memory issues
$total_records = count($all_records);

// 3. Generate individual PDF files and save them to temporary directory
foreach ($all_records as $index => $data) {
    $pdf = new FPDF();
    
    foreach ($all_templates as $template) {
        $template_id = $template['id'];
        $templateImage = $template['template_image_path'] ?? '';
        
        $imgPath = __DIR__ . '/../' . $templateImage;
        
        if (!file_exists($imgPath) || pathinfo($imgPath, PATHINFO_EXTENSION) == "") {
            continue;
        }
        
        $pdf->AddPage();
        $pdf->Image($imgPath, 0, 0, 210, 297);
        
        // Fetch field mappings for this template
        $fieldQuery = $conn->prepare("SELECT column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style
                                      FROM field_mappings WHERE project_id = ? AND template_id = ?");
        $fieldQuery->bind_param("ii", $project_id, $template_id);
        $fieldQuery->execute();
        $fields = $fieldQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $fieldQuery->close();
        
        // Loop through fields and add content to the PDF
        foreach ($fields as $field) {
            $value = $data[$field['column_name']] ?? '';
            if ($field['column_name'] === 'photo_path' || $field['column_name'] === 'signature_path') {
                $column_based = $data['column_based']; 
                $get_id = "SELECT `$column_based` FROM admit_card_records WHERE project_id = $project_id";
                $get_id = $conn->query($get_id)->fetch_assoc();
                $get_id = $get_id[$column_based];
                
                $value = $data['path'] . $get_id . $data['prefix'] . '.' . $data['extension'];  
                $photoType = $data['photo_type'] ?? '';
            } else {
                $value = $data[$field['column_name']] ?? '';
                $photoPath = '';
                $photoType = '';
            }
    
            $bar_code = $field['column_name'] === 'bar_code';
            $value = str_replace('\\', '/', trim($value));
    
            $fontType = strtolower($field['font_type']);
            if (!in_array($fontType, ['arial', 'helvetica', 'courier', 'times'])) {
                $fontType = 'Arial';
            }
    
            $styleMap = [
                'bold' => 'B',
                'italic' => 'I',
                'underline' => 'U',
                'bolditalic' => 'BI',
                'bold italic' => 'BI',
                'normal' => '',
                '' => ''
            ];
            $fontStyle = strtolower(trim($field['font_style']));
            $fontStyle = $styleMap[$fontStyle] ?? '';
    
            $pdf->SetFont(ucfirst($fontType), $fontStyle, intval($field['font_size']));
            $rgb = hexToRGB($field['font_color']);
            if ($rgb) {
                $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
            }
    
            $scaleX = 210 / 595;
            $scaleY = 297 / 842;
    
            $x = floatval($field['x_position']) * $scaleX;
            $y = floatval($field['y_position']) * $scaleY;
            $width = floatval($field['width']) * $scaleX;
            $height = floatval($field['height']) * $scaleY;
            if ($width <= 0.1) $width = 0.1;
            if ($height <= 0.1) $height = 0.1;
    
            $pdf->SetXY($x, $y);
            
            if (!empty($bar_code)) {
                $registrationNumber = $data['registration_number'] ?? '';
                if (!empty($registrationNumber)) {
                    $barcodeimage = "https://admitcards.iroams.com/Admit_Cards/dashboard/b/barcode.php?size=30&print=false&text=" . $registrationNumber;
                    $pdf->Image($barcodeimage, $x, $y, 25, 10, 'PNG');
                }
            }
            
            $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                if (!empty($value)) {
                    $path_type = $data['path_type'] ?? '';
                    
                    if($path_type === 'Local Path'){
                        $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
                        $imageExists = file_exists($imgPath);
                    } else {
                        $imgPath = $value;
                        $imageExists = false;
                        if (filter_var($imgPath, FILTER_VALIDATE_URL)) {
                            $headers = @get_headers($imgPath, 1);
                            if ($headers && strpos($headers[0], '200') !== false) {
                                $imageSize = @getimagesize($imgPath);
                                $imageExists = ($imageSize !== false);
                            }
                        }
                    }
                
                    if($imageExists){
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
                }
            } else {
                if ($field['cell_type'] === 'MultiCell') {
                    $pdf->MultiCell($width, 4, strtoupper($value), 0, 1, '');
                } else {
                    $pdf->SetXY($x, $y);
                    $pdf->Cell($width, $height, $value, 0, 0, 'L');
                }
            }
        }
    }
    
    // Save individual PDF to temporary file
    $temp_filename = $temp_dir . '/admit_card_' . $data['registration_number'] . '_' . $index . '.pdf';
    $pdf->Output('F', $temp_filename);
    $temp_pdf_files[] = $temp_filename;
    unset($pdf); // Free memory
    
    // Clear some memory every batch
    if (($index + 1) % $batch_size === 0) {
        gc_collect_cycles();
    }
}

// 4. Merge all temporary PDF files into a single PDF
$merged_pdf = new Fpdi();
$total_files = count($temp_pdf_files);

foreach ($temp_pdf_files as $file_index => $temp_file) {
    try {
        $page_count = $merged_pdf->setSourceFile($temp_file);
        for ($page_no = 1; $page_no <= $page_count; $page_no++) {
            $tplId = $merged_pdf->importPage($page_no);
            $size = $merged_pdf->getTemplateSize($tplId);
            $merged_pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merged_pdf->useTemplate($tplId);
        }
    } catch (Exception $e) {
        error_log("FPDI Merge Error for file $temp_file: " . $e->getMessage());
    }
    
    // Delete temporary file after merging to free up disk space
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }
    
    // Clear memory every batch
    if (($file_index + 1) % $batch_size === 0) {
        gc_collect_cycles();
    }
}

// 5. Output the single merged PDF directly to the browser for download
ob_end_clean(); // Clean any previous output
$merged_filename = 'All_Admit_Cards_' . date('Ymd_His') . '.pdf';
$merged_pdf->Output('D', $merged_filename);

// Clean up temporary directory
if (file_exists($temp_dir)) {
    array_map('unlink', glob("$temp_dir/*.*"));
    rmdir($temp_dir);
}

exit();

// 🔄 Hex to RGB helper function
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