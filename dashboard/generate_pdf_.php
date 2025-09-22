<?php
ob_start(); 
session_start();
if (empty($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}
include("../db_connect.php");
require_once("../vendor/setasign/fpdf/fpdf.php");

$project_id = intval($_GET['project_id']);
//$template_id = intval($_GET['template_id']);
$record_id = intval($_GET['record_id']); 
$filter_column = $_GET['filter_column'] ?? '';
$filter_column = "OBC";
// 1. Get field mappings
$fieldQuery = $conn->prepare("SELECT column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style 
                              FROM field_mappings 
                              WHERE project_id = ? ");
$fieldQuery->bind_param("i", $project_id);
$fieldQuery->execute();
$fieldResult = $fieldQuery->get_result();

$fields = [];
while ($row = $fieldResult->fetch_assoc()) {
    $fields[] = $row;
}

$fieldQuery->close();


// $dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ? LIMIT 1");
// $dataQuery->bind_param("i", $project_id);
$dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE id = ? AND project_id = ?");
$dataQuery->bind_param("ii", $record_id, $project_id);
$dataQuery->execute();
$dataResult = $dataQuery->get_result();
$data = $dataResult->fetch_assoc();


// Now $data is just the row as an associative array
$dataQuery->close();

// Debugging: Check the data structure

// Debugging: Check the data structure
// 3. Get background template image
$templateQuery = $conn->prepare("SELECT id, template_image_path FROM project_templates WHERE project_id = ?  AND columns_name = ? " );
$templateQuery->bind_param("is", $project_id,$filter_column);
$templateQuery->execute();
$templateResult = $templateQuery->get_result();

$pdf = new FPDF();
function drawBoundedMultiCell($pdf, $x, $y, $w, $h, $text, $lineHeight) {
    $pdf->SetXY($x, $y);
    $startY = $y;
    $lines = explode("\n", wordwrap($text, 60, "\n", true)); // wrap every 60 chars

    foreach ($lines as $line) {
        if (($pdf->GetY() + $lineHeight) > ($startY + $h)) {
            break; // Stop if height exceeded
        }
        $pdf->MultiCell($w, $lineHeight, $line, 0, 'L');
        $pdf->SetX($x); // Reset X after each line
    }
}

function drawBoundedCell($pdf, $x, $y, $w, $h, $text) {
    $pdf->SetXY($x, $y);

    // Measure and truncate if needed
    while ($pdf->GetStringWidth($text) > $w && strlen($text) > 0) {
        $text = substr($text, 0, -1); // Remove last character
    }

    $pdf->Cell($w, $h, $text, 0, 0, 'L');
}

while ($templateRow = $templateResult->fetch_assoc()) {
   
    $template_id = $templateRow['id'];
    $templateImage = $templateRow['template_image_path'];

    // Load field mappings for this template
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

    // Add a new page for each template
    $pdf->AddPage();
    // $imagePath = "uploads/templates/" . basename($templateImage);
    // if (file_exists($imagePath)) {
    //     $pdf->Image($imagePath, 0, 0, 210, 297);
    // }
     $absolutePath = __DIR__ . '/../' . $templateImage;
  

    if (!file_exists($absolutePath) || pathinfo($absolutePath, PATHINFO_EXTENSION) == "") {
        echo "❌ Image not found or missing extension: $templateImage<br>";
        continue;
    }

    $pdf->AddPage();
    $pdf->Image($absolutePath, 0, 0, 210, 297); // A4 size
    foreach ($fields as $field) {
        $value = $data[$field['column_name']] ?? '';
        $value = str_replace('\\', '/', trim($value));

        $fontType = strtolower($field['font_type']);
        if (!in_array($fontType, ['arial', 'helvetica', 'courier', 'times'])) {
            $fontType = 'Arial';
        }

        $fontStyle = strtoupper($field['font_style']);
        if (!in_array($fontStyle, ['B', 'I', 'U', 'BI', ''])) {
            $fontStyle = '';
        }

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
        if ($width <= 0.1) $width = 0.1; // Minimum width for visibility, avoids width=0 behavior in Cell
        if ($height <= 0.1) $height = 0.1; // Minimum height for visibility, or as a base for line height

        $pdf->SetXY($x, $y);

        if (in_array($field['column_name'], ['photo_path', 'signature_path'])) {
            if (!empty($value)) {
                $imagePath = dirname(__DIR__) . '/' . $value;
                if (file_exists($imagePath)) {
                    $pdf->Image($imagePath, $x, $y, $width, $height);
                } else {
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell($width, $height, 'Image not found');
                }
            }
        } else {
            // if ($field['cell_type'] === 'Cell') {
            //     $pdf->Cell($width, $height, $value);
            // } elseif ($field['cell_type'] === 'MultiCell') {
            //     $pdf->MultiCell($width, $height, $value);
            // }
            if ($field['cell_type'] === 'MultiCell') {
                    $fontSize = intval($field['font_size']);
                    $lineHeight = ($fontSize / 72) * 25.4 * 1.2; // Convert pt to mm

                        if ($lineHeight < 2) $lineHeight = 2; // min line height
                        drawBoundedMultiCell($pdf, $x, $y, $width, $height, $value, $lineHeight);
                } else {
                    // $pdf->Cell($width, $height, $value, 0, 0, 'L');
                    $lineHeight = $height; // or calculate based on font size
                    if ($lineHeight < 2) $lineHeight = 2;

                    drawBoundedCell($pdf, $x, $y, $width, $height, $value); // Use Option 1
                }
        }
    }
}

$templateQuery->close();
ob_end_clean();
$pdf->Output();
exit();
// 🔄 Hex to RGB helper
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
