<?php
ob_start();
session_start();

if (empty($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}

include("../db_connect.php");
require_once("../vendor/setasign/fpdf/fpdf.php");

$project_id = intval($_POST['project_id']);
$project_name = $_SESSION['project_name'] ?? '';

if (!empty($project_id)) {
    $get_slug = $conn->query("SELECT slug FROM projects WHERE id = $project_id");
    $project_slug = $get_slug->fetch_assoc()['slug'] ?? null;
}

if ($project_id === 0) {
    die("Project ID is required.");
}

// Create base directory for candidate folders
$base_directory = dirname(__DIR__) . "/$project_slug/candidate_pdfs/";
if (!file_exists($base_directory)) {
    mkdir($base_directory, 0777, true);
}

// 1. Fetch all records that are live
$recordsQuery = $conn->prepare("
    SELECT acr.*, p.name AS project_name, p.column_based, p.header
    FROM admit_card_records acr
    JOIN projects p ON acr.project_id = p.id
    WHERE acr.project_id = ? AND acr.is_admit_card_live = 1
");
$recordsQuery->bind_param("i", $project_id);
$recordsQuery->execute();
$recordsResult = $recordsQuery->get_result();
$all_records = $recordsResult->fetch_all(MYSQLI_ASSOC);
$recordsQuery->close();

if (empty($all_records)) {
    die("No live records found for this project.");
}

$column_based = $all_records[0]['column_based'];
$filter_column = ($column_based === 'all') ? '' : $column_based;

// 2. Load templates outside the user loop
if ($column_based === 'all') {
    $templateQuery = $conn->prepare("
        SELECT id, template_image_path 
        FROM project_templates 
        WHERE project_id = ? 
        ORDER BY page_order ASC
    ");
    $templateQuery->bind_param("i", $project_id);
} else {
    $templateQuery = $conn->prepare("
        SELECT id, template_image_path 
        FROM project_templates 
        WHERE project_id = ? AND columns_name = ? 
        ORDER BY page_order ASC
    ");
    $templateQuery->bind_param("is", $project_id, $filter_column);
}
$templateQuery->execute();
$templateResult = $templateQuery->get_result();
$all_templates = $templateResult->fetch_all(MYSQLI_ASSOC);
$templateQuery->close();

if (empty($all_templates)) {
    die("No templates found for this project.");
}

// 3. Main loop: Iterate through each user record
foreach ($all_records as $userData) {

    $pdf = new FPDF();

    // Loop through each template to add a page and its fields
    foreach ($all_templates as $templateRow) {
        $template_id = $templateRow['id'];
        $templateImage = $templateRow['template_image_path'];
        $absolutePath = __DIR__ . '/../' . $templateImage;

        if (!file_exists($absolutePath)) {
            echo "❌ Template image not found: " . $absolutePath . "<br>";
            continue;
        }

        $pdf->AddPage();
        $pdf->Image($absolutePath, 0, 0, 210, 297);

        // Fetch field mappings for the current template
        $fieldQuery = $conn->prepare("
            SELECT column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style
            FROM field_mappings 
            WHERE project_id = ? AND template_id = ?
        ");
        $fieldQuery->bind_param("ii", $project_id, $template_id);
        $fieldQuery->execute();
        $fieldResult = $fieldQuery->get_result();
        $fields = $fieldResult->fetch_all(MYSQLI_ASSOC);
        $fieldQuery->close();
        
        // Loop through fields to draw content
        foreach ($fields as $field) {
            $column_name = $field['column_name'];
            $value = $userData[$column_name] ?? '';

            // Handle Photo/Signature Paths
            if ($column_name === 'photo_path' || $column_name === 'signature_path') {
                $photo_path_settings = $conn->query("SELECT * FROM photo_path_settings WHERE project_id = $project_id")->fetch_assoc();
                $identifier_column = $photo_path_settings['column_based'] ?? 'id';
                $identifier_value = $userData[$identifier_column] ?? '';
                $image_path = $photo_path_settings['path'] . $identifier_value . $photo_path_settings['prefix'] . '.' . $photo_path_settings['extension'];
                
                $path_type = $photo_path_settings['path_type'] ?? '';

                if ($path_type === 'Local Path') {
                    $imgPath = dirname(__DIR__) . '/' . ltrim($image_path, '/');
                    $imageExists = file_exists($imgPath);
                } else { // External URL
                    $imgPath = $image_path;
                    $imageExists = false;
                    if (filter_var($imgPath, FILTER_VALIDATE_URL)) {
                        $headers = @get_headers($imgPath, 1);
                        if ($headers && strpos($headers[0], '200') !== false) {
                            $imageExists = (@getimagesize($imgPath) !== false);
                        }
                    }
                }

                if ($imageExists) {
                    try {
                        $pdf->Image($imgPath, floatval($field['x_position']) * (210 / 595), floatval($field['y_position']) * (297 / 842), floatval($field['width']) * (210 / 595), floatval($field['height']) * (297 / 842));
                    } catch (Exception $e) {
                        // Handle image error
                    }
                }
            }
            // Handle Barcode
            else if ($column_name === 'bar_code') {
                $registrationNumber = $userData['registration_number'] ?? '';
                if (!empty($registrationNumber)) {
                    $barcode_url = "https://admitcards.iroams.com/Admit_Cards/dashboard/b/barcode.php?size=30&print=false&text=" . urlencode($registrationNumber);
                    $x = floatval($field['x_position']) * (210 / 595);
                    $y = floatval($field['y_position']) * (297 / 842);
                    $pdf->Image($barcode_url, $x, $y, 25, 10, 'PNG');
                }
            } 
            // Handle other text fields
            else {
                $x = floatval($field['x_position']) * (210 / 595);
                $y = floatval($field['y_position']) * (297 / 842);
                $width = floatval($field['width']) * (210 / 595);
                $height = floatval($field['height']) * (297 / 842);

                $fontType = strtolower($field['font_type']);
                $fontStyle = strtolower(trim($field['font_style']));
                $styleMap = ['bold' => 'B', 'italic' => 'I', 'underline' => 'U', 'bolditalic' => 'BI', 'bold italic' => 'BI', 'normal' => '', '' => ''];
                $pdf->SetFont(ucfirst($fontType), $styleMap[$fontStyle] ?? '', intval($field['font_size']));
                $rgb = hexToRGB($field['font_color']);
                if ($rgb) {
                    $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
                }
                $pdf->SetXY($x, $y);

                if (trim($field['cell_type']) === 'MultiCell') {
                    $pdf->MultiCell($width, 4, strtoupper($value), 0, 1, '');
                } else {
                    $pdf->Cell($width, $height, $value, 0, 0, 'L');
                }
            }
        }
    }

    // Save the PDF with a unique name
    $registration_number = $userData['registration_number'] ?? $userData['id']; // Use a unique identifier
    $file_name = 'AdmitCard_' . $registration_number . '.pdf';
    $file_path = $base_directory . $file_name;
    $pdf->Output('F', $file_path);
    echo "✅ Generated PDF for " . htmlspecialchars($userData['first_name'] ?? 'User') . ": " . htmlspecialchars($file_name) . "<br>";
}

ob_end_clean();

// 🔄 Hex to RGB helper
function hexToRGB($hexColor) {
    // ... (rest of the function is the same)
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