<?php
ob_start();
session_start();

// Redirect if the user is not logged in
$user_role = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : null;
if (!isset($_SESSION["user_role"])) {
    header("Location: /Admit_Cards/index.php");
    exit();
}

// Include database connection
include("../db_connect.php");

// Include FPDI, which includes FPDF automatically
//require_once('../vendor/setasign/fpdi/src/autoload.php');
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
    die("No records found for this project.");
}

// Array to hold the content of each generated PDF
$pdf_contents = [];

// 3. Loop through each record, generate its PDF in memory, and store the content
foreach ($all_records as $data) {
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
            // ... (your existing logic for handling images, barcodes, text fields, etc.)
            // Make sure to use $data['column_name'] for dynamic values
            // ...
        }
    }
    
    // Store the generated PDF content as a string
    $pdf_contents[] = $pdf->Output('S');
    unset($pdf); // Free memory
}

// 4. Merge the in-memory PDF contents into a single FPDI document
$merged_pdf = new Fpdi();
foreach ($pdf_contents as $content) {
    try {
        $page_count = $merged_pdf->setSourceFile('data://application/pdf;base64,' . base64_encode($content));
        for ($i = 1; $i <= $page_count; $i++) {
            $tplId = $merged_pdf->importPage($i);
            $size = $merged_pdf->getTemplateSize($tplId);
            $merged_pdf->AddPage($size['orientation']);
            $merged_pdf->useTemplate($tplId);
        }
    } catch (Exception $e) {
        // Handle potential errors during import
        error_log("FPDI Merge Error: " . $e->getMessage());
    }
}

// 5. Output the single merged PDF directly to the browser for download
ob_end_clean(); // Clean any previous output
$merged_filename = 'All_Admit_Cards_' . date('Ymd_His') . '.pdf';
$merged_pdf->Output('D', $merged_filename);

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