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
require_once('../vendor/setasign/fpdi/src/autoload.php');
//require_once('../vendor/autoload.php');
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
//$pdf_contents = [];
$merged_pdf = new Fpdi();
$merged_pdf->SetTitle('All Admit Cards');
// 3. Loop through each record, generate its PDF in memory, and store the content
foreach ($all_records as $data) {
   // $pdf = new FPDF();
   $single_user_pdf_content = '';
     $temp_pdf = new FPDF();
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
                $column_based = $data['column_based']; // Default to 'id' if not set
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


            // $value = $data[$field['column_name']] ?? '';
            // echo "<pre/>"; print_r($value);

            $bar_code = $field['column_name'] === 'bar_code';


            $value = str_replace('\\', '/', trim($value));


            $fontType = strtolower($field['font_type']);
            if (!in_array($fontType, ['arial', 'helvetica', 'courier', 'times'])) {
                $fontType = 'Arial';
            }

            $fontStyle = strtoupper($field['font_style']);
            if (!in_array($fontStyle, ['B', 'I', 'U', 'BI', ''])) {
                $fontStyle = '';
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
            if ($width <= 0.1) $width = 0.1; // Minimum width for visibility, avoids width=0 behavior in Cell
            if ($height <= 0.1) $height = 0.1; // Minimum height for visibility, or as a base for line height

            // echo "<pre/>"; print_r($value . " width:" .$width ."height". $height ."");
            $pdf->SetXY($x, $y);

            if (!empty($bar_code)) {


                if ($pageNumber === 1 && !empty($registrationNumber)) {
                    // $barcodeimage = "https://admitcards.iroams.com/upprb_sportsadmitcards/b/barcode.php?size=30&print=false&text=" . $registrationNumber;
                    $barcodeimage = "https://admitcards.iroams.com/Admit_Cards/dashboard/b/barcode.php?size=30&print=false&text=" . $registrationNumber;
                    $pdf->Image($barcodeimage, $x, $y, $width, $height, 'PNG');

                    // $pdf->Image(
                    //     "https://admitcards.iroams.com/upprb_sportsadmitcards/b/barcode.php?size=30&print=false&text=" . $registrationNumber,
                    //     167, // X position in mm (adjust to your layout)
                    //     47,  // Y position in mm
                    //     25,  // Width
                    //     10,  // Height
                    //     'PNG'
                    // );
                }
            }
            $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));


            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {


                if (!empty($value)) {

                    $path_type = $data['path_type'] ?? '';

                    if ($path_type === 'Local Path') {
                        $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
                        $imageExists = file_exists($imgPath);
                    } else {
                        $imgPath = $value;

                        // For external URLs, check if image is accessible
                        $imageExists = false;
                        if (filter_var($imgPath, FILTER_VALIDATE_URL)) {
                            // Use get_headers to check if URL is accessible without downloading
                            $headers = @get_headers($imgPath, 1);
                            if ($headers && strpos($headers[0], '200') !== false) {
                                // Additional check with getimagesize to ensure it's a valid image
                                $imageSize = @getimagesize($imgPath);
                                $imageExists = ($imageSize !== false);
                            }
                        }
                    }

                    if ($imageExists) {
                        try {
                            $pdf->Image($imgPath, $x, $y, $width, $height);
                        } catch (Exception $e) {
                            // If image still fails, show error message
                            $pdf->SetTextColor(255, 0, 0);
                            $pdf->SetXY($x, $y);
                            $pdf->Cell($width, $height, 'Image Error', 0, 0, 'C');
                            $pdf->SetTextColor(0, 0, 0); // Reset text color
                        }
                    } else {
                        $pdf->SetTextColor(255, 0, 0);
                        $pdf->SetXY($x, $y);
                        $pdf->Cell($width, $height, 'Image not found', 0, 0, 'C');
                        $pdf->SetTextColor(0, 0, 0); // Reset text color
                    }
                }
            } else {

                if ($field['cell_type'] === 'MultiCell') {
                    // $pdf->MultiCell($width, $height, $value, 0, 'L'); // Add border (1)
                    //  $pdf->MultiCell($width,$height,strtoupper($value),0,1,'');
                    // //   /  $pdf->MultiCell($width, $height, $value);
                    $pdf->MultiCell($width, 4, strtoupper($value), 0, 1, '');
                } else {
                    $pdf->SetXY($x, $y); // Reset position before each Cell
                    $pdf->Cell($width, $height, $value, 0, 0, 'L'); // Add border (1)
                    //  $pdf->Cell($width, $height, $value, 0, 0); // no border, no line break
                }
            }
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