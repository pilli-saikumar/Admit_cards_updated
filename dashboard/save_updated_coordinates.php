<?php
// disable output buffering for PDF generation
// ob_end_clean(); // This is often called when sending PDF, but ensure it's not before headers.
// ob_start(); // Don't start output buffering here if you want to output PDF directly

include("../db_connect.php"); // Assuming this establishes $conn for database connection
require_once("../vendor/setasign/fpdf/fpdf.php"); // Adjust path if FPDF is elsewhere

$project_id = intval($_POST['project_id']);
$template_id = intval($_POST['template_id']);

// 1. Get field mappings
$fieldQuery = $conn->prepare("SELECT column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style
                               FROM field_mappings
                               WHERE project_id = ? AND template_id = ?");
$fieldQuery->bind_param("ii", $project_id, $template_id);
$fieldQuery->execute();
$fieldResult = $fieldQuery->get_result();

$fields = [];
while ($row = $fieldResult->fetch_assoc()) {
    $fields[] = $row;
}
$fieldQuery->close();

// 2. Get data from admit_card_records
// Limiting to 1 record for preview purposes. You might want to get all or a specific one.
$dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ? LIMIT 1");
$dataQuery->bind_param("i", $project_id);
$dataQuery->execute();
$dataResult = $dataQuery->get_result();
$data = $dataResult->fetch_assoc(); // Now $data is just the row as an associative array
$dataQuery->close();

// 3. Get background template image
$templateQuery = $conn->prepare("SELECT template_image_path FROM project_templates WHERE id = ?"); // Removed project_id from WHERE for template table as ID should be unique
$templateQuery->bind_param("i", $template_id);
$templateQuery->execute();
$templateResult = $templateQuery->get_result();
$templateRow = $templateResult->fetch_assoc();
$templateImageRelativePath = $templateRow['template_image_path'] ?? '';
$templateQuery->close();

// Correct image path: Assume template_image_path is relative to the project root,
// and your generate_pdf.php is one level down from the root (e.g., in a 'pages' folder).
// Adjust this path based on your actual file structure.
// Example: If template_image_path is "uploads/templates/image.jpg" and generate_pdf.php is in 'pages',
// then you need to go up one level then down into uploads.
//$imagePath = "../" . $templateImageRelativePath; // Adjust this if your directory structure is different
$imagePath = "uploads/templates/" . basename($templateImageRelativePath);
if (!file_exists($imagePath)) {
    // Fallback or error message if image is not found
    // This is crucial for debugging image issues
    error_log("❌ Template image not found: " . realpath($imagePath));
    // Provide a placeholder or a blank PDF if image is critical
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Template image not found: ' . $imagePath, 0, 1, 'C');
    $pdf->Output();
    exit();
}

// Generate PDF with background image
$pdf = new FPDF();
$pdf->AddPage();
// A4 dimensions: 210mm x 297mm. Original template dimensions: 595px x 842px.
// Calculate scaling factors for X and Y based on A4 size and original image size
$original_image_width_px = 595;
$original_image_height_px = 842;
$a4_width_mm = 210;
$a4_height_mm = 297;

$scaleX = $a4_width_mm / $original_image_width_px;
$scaleY = $a4_height_mm / $original_image_height_px;

// Add the background image to the PDF, scaling to fill the A4 page
$pdf->Image($imagePath, 0, 0, $a4_width_mm, $a4_height_mm);

foreach ($fields as $field) {
    // Get the value for the current column name from the fetched data
    $value = $data[$field['column_name']] ?? 'N/A'; // Default to 'N/A' if column not found in data

    // Font family mapping for FPDF (lowercase 'arial', 'times', 'helvetica', 'courier')
    $fontType = strtolower($field['font_type']);
    $validFontTypes = ['arial', 'times', 'helvetica', 'courier'];
    if (!in_array($fontType, $validFontTypes)) {
        $fontType = 'arial'; // Default to Arial if invalid
    }

    // Font style mapping for FPDF ('B', 'I', 'U', 'BI', or empty string for normal)
    $fontStyle = '';
    if ($field['font_style'] === 'bold') {
        $fontStyle = 'B';
    } elseif ($field['font_style'] === 'italic') {
        $fontStyle = 'I';
    } elseif ($field['font_style'] === 'bold italic') {
        $fontStyle = 'BI';
    }

    $pdf->SetFont(ucfirst($fontType), $fontStyle, intval($field['font_size']));

    // Font color
    $rgb = hexToRGB($field['font_color']);
    if ($rgb) {
        $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    } else {
        $pdf->SetTextColor(0, 0, 0); // Default to black if color is invalid
    }

    // Position and dimensions are in pixels from the database.
    // Convert them to millimeters using the scaling factor.
    $x_mm = floatval($field['x_position']) * $scaleX;
    $y_mm = floatval($field['y_position']) * $scaleY;
    $width_mm = floatval($field['width']) * $scaleX;
    $height_mm = floatval($field['height']) * $scaleY;

    $pdf->SetXY($x_mm, $y_mm);

    // Ensure width and height are positive to avoid FPDF errors
    if ($width_mm <= 0) $width_mm = 0.1;
    if ($height_mm <= 0) $height_mm = 0.1;


    if ($field['cell_type'] === 'Cell') {
        $pdf->Cell($width_mm, $height_mm, $value, 0, 0, '', false); // Cell with no border, no line break, no fill
    } elseif ($field['cell_type'] === 'MultiCell') {
        $pdf->MultiCell($width_mm, $height_mm, $value, 0, 'L', false); // MultiCell with no border, left align, no fill
    }
}

// Check if download is requested
if (isset($_GET['download']) && $_GET['download'] === 'true') {
    $pdf->Output('D', 'generated_admit_card.pdf'); // 'D' forces download
} else {
    $pdf->Output('I', 'preview.pdf'); // 'I' for inline display in browser/iframe
}

exit();

// Helper function to convert Hex to RGB
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