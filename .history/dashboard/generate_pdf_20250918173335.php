<?php
ob_start();
session_start();

// Resolve project slug from multiple sources to build a correct redirect if session is missing
$projectSlug = $_GET['project']
    ?? ($_POST['project_slug'] ?? ($_POST['slug'] ?? ($_SESSION['project_slug'] ?? ($_COOKIE['last_project_slug'] ?? null))));

if (!$projectSlug && !empty($_SERVER['HTTP_REFERER'])) {
    if (preg_match('#/Admit_Cards/([^/]+)/#', $_SERVER['HTTP_REFERER'], $m)) {
        $projectSlug = $m[1];
    }
}

// If still no slug but we have project_id in POST, look it up
if (!$projectSlug && isset($_POST['project_id'])) {
    include_once("../db_connect.php");
    $pid = intval($_POST['project_id']);
    if (!empty($pid)) {
        if ($stmtSlug = $conn->prepare("SELECT slug FROM projects WHERE id = ?")) {
            $stmtSlug->bind_param("i", $pid);
            $stmtSlug->execute();
            $resSlug = $stmtSlug->get_result();
            if ($rowSlug = $resSlug->fetch_assoc()) {
                $projectSlug = $rowSlug['slug'] ?? null;
            }
            $stmtSlug->close();
        }
    }
}

// If user session is missing, redirect to appropriate candidate index if we have a slug, else admin index
if (empty($_SESSION["user_name"])) {
    if (!empty($projectSlug)) {
        header("Location: /Admit_Cards/{$projectSlug}/index.php");
    } else {
        header("Location: ../index.php");
    }
    exit();
}

include("../db_connect.php");
require_once("../vendor/setasign/fpdf/fpdf.php");

$project_id = intval($_POST['project_id']);
$record_id = intval($_POST['record_id']);
$column_based = $_POST['column_based'] ?? '';
$filter_column = $_POST['filter_column'] ?? '';
$project_name = $_SESSION['project_name'] ?? '';


// Step 1: Get the record data
// $dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE id = ? AND project_id = ?");
// $dataQuery->bind_param("ii", $record_id, $project_id);
// $dataQuery->execute();
// $dataResult = $dataQuery->get_result();
// $data = $dataResult->fetch_assoc();

// $dataQuery->close();
 $query = "SELECT a.*, p.* 
              FROM admit_card_records a
              LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
              WHERE a.project_id = ? AND a.id = ?";
    $dataQuery = $conn->prepare($query);
    $dataQuery->bind_param("is", $project_id, $record_id);

$dataQuery->execute();
$dataResult = $dataQuery->get_result();
  
$data = $dataResult->fetch_assoc(); 
$dataQuery->close();



// Step 2: Load matching templates based on column_based logic
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

$pdf = new FPDF();
// function drawBoundedMultiCell($pdf, $x, $y, $w, $h, $text, $lineHeight, $lineBreak) {
//     $pdf->SetXY($x, $y);
//     $startY = $y;

//     $lines = explode("\n", wordwrap($text, $lineBreak, "\n", true)); // dynamic wrap

//     foreach ($lines as $line) {
//         // if (($pdf->GetY() + $lineHeight) > ($startY + $h)) {
//         //     break;
//         // }
//         $pdf->MultiCell($w, $lineHeight, $line, 0, 'L');
//         $pdf->SetX($x);
//     }
// }
// function drawBoundedCell($pdf, $x, $y, $w, $h, $text) {
//     $pdf->SetXY($x, $y);

//     // Measure and truncate if needed
//     while ($pdf->GetStringWidth($text) > $w && strlen($text) > 0) {
//         $text = substr($text, 0, -1); // Remove last character
//     }

//     $pdf->Cell($w, $h, $text, 0, 0, 'L');
// }
  $template_ids = [];
  $pageNumber = 0;
  
while ($templateRow = $templateResult->fetch_assoc()) {
    $template_id = $templateRow['id'];
    $template_ids[] = $template_id; // Collect template IDs for logging
      $pageNumber++;
  
         // echo "Template Image Path: " . $templateRow['template_image_path'] . "<br>";
         // echo "Project ID: $project_id<br>";
         // echo "Record ID: $record_id<br>";
         // echo "Column Based: $column_based<br>";
         // echo "Filter Column: $filter_column<br>";

         // Check if the template image path is set
         if (empty($templateRow['template_image_path'])) {
             echo "❌ Template image path is empty for template ID: $template_id<br>";
             continue;
         }
    $templateImage = $templateRow['template_image_path'];
         $registrationNumber = $_SESSION['registration_number'] ?? '';
     $pdf->AddPage();

    // $pdf->Image("https://admitcards.iroams.com/upprb_sportsadmitcards/b/barcode.php?size=30&print=false&text=".$row['registraionid'],167,47,25,10,'PNG');
   

   $photo_path_settings = $conn->query("SELECT * FROM photo_path_settings WHERE project_id = $project_id")->fetch_assoc();

    // Get field mappings only for this template
    $fieldQuery = $conn->prepare("SELECT column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style,line_break
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

    // Add page and draw template image
    //  $pdf->AddPage();
    // $imagePath = "uploads/templates/" . basename($templateImage);
    // if (file_exists($imagePath)) {
    //     $pdf->Image($imagePath, 0, 0, 210, 297);
    // }
    $absolutePath = __DIR__ . '/../' . $templateImage;

    if (!file_exists($absolutePath) || pathinfo($absolutePath, PATHINFO_EXTENSION) == "") {
        echo "❌ Image not found or missing extension: $templateImage<br>";
        continue;
    }

    $pdf->Image($absolutePath, 0, 0, 210, 297); // A4 size
    //        if ($pageNumber === 1 && !empty($registrationNumber)) {
             
    //     $pdf->Image(
    //         "https://admitcards.iroams.com/upprb_sportsadmitcards/b/barcode.php?size=30&print=false&text=" . $registrationNumber,
    //         167, // X position in mm (adjust to your layout)
    //         47,  // Y position in mm
    //         25,  // Width
    //         10,  // Height
    //         'PNG'
    //     );
    // }
         
    foreach ($fields as $field) {
    
      //  $value = $data[$field['column_name']] ?? '';
         $value = $data[$field['column_name']] ?? '';
        if($field['column_name'] === 'photo_path' || $field['column_name'] === 'signature_path'){
            $column_based = $data['column_based']; // Default to 'id' if not set
            $get_id = "SELECT `$column_based` FROM admit_card_records WHERE project_id = $project_id";
            $get_id = $conn->query($get_id)->fetch_assoc();
            $get_id = $get_id[$column_based];
            
            $value = $data['path'].$get_id.$data['prefix'].'.'.$data['extension'];  
          
            $photoType = $data['photo_type'] ?? '';
        }else{
            $value = $data[$field['column_name']] ?? '';
            $photoPath = '';
            $photoType = '';
        }
  
    $bar_code = $field['column_name'] === 'bar_code' ;
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


        if ($width <= 0.1) $width = 0.1; // Minimum width for visibility, avoids width=0 behavior in Cell
        if ($height <= 0.1) $height = 0.1;
        $pdf->SetXY($x, $y);
          if(!empty($bar_code) ) {
           

        if ($pageNumber === 1 && !empty($registrationNumber)) {
            $barcodeimage = "https://admitcards.iroams.com/Admit_Cards/dashboard/b/barcode.php?size=30&print=false&text=" . $registrationNumber;
            $pdf->Image($barcodeimage,$x, $y, 25, 10, 'PNG');
            
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

        // if (in_array($field['column_name'], ['photo_path', 'signature_path'])) {
        //     if (!empty($value)) {
        //         $imgPath = dirname(__DIR__) . '/' . $value;
        //         if (file_exists($imgPath)) {
        //             $pdf->Image($imgPath, $x, $y, $width, $height);
        //         } else {
        //             $pdf->SetTextColor(255, 0, 0);
        //             $pdf->Cell($width, $height, 'Image not found');
        //         }
        //     }
        // } else {

        //      $cellType = strtolower(trim($field['cell_type']));

        //   if ($field['cell_type'] === 'MultiCell') {
        //             $fontSize = intval($field['font_size']);
        //             $lineHeight = ($fontSize / 72) * 25.4 * 1.2; // Convert pt to mm

        //                 if ($lineHeight < 2) $lineHeight = 2; // min line height
        //                 drawBoundedMultiCell($pdf, $x, $y, $width, $height, $value, $lineHeight);
        //         } else {
        //             // $pdf->Cell($width, $height, $value, 0, 0, 'L');
        //             $lineHeight = $height; // or calculate based on font size
        //             if ($lineHeight < 2) $lineHeight = 2;

        //             drawBoundedCell($pdf, $x, $y, $width, $height, $value); // Use Option 1
        //         }
        // }
          if (!empty($value)) {
            $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                // if (!empty($value)) {
                //     $path_type = $data['path_type'] ?? '';
               
                    
                //     if($path_type === 'Local Path'){
                //      $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
                //     }else{
                //      $imgPath = $value;
                //     }
                //     echo $imgPath;
                //    //  $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
                //       if($imgPath){
                //         $pdf->Image($imgPath, $x, $y, $width, $height);
                //      }else{
                //         $pdf->SetTextColor(255, 0, 0);
                //         $pdf->Cell($width, $height, 'Image not found');
                //     }
                // }
                if (!empty($value)) {
                    $path_type = $data['path_type'] ?? '';
                    
                    if($path_type === 'Local Path'){
                     $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
                     $imageExists = file_exists($imgPath);
                    }else{
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
                
                    if($imageExists){
                         try {
                             $pdf->Image($imgPath, $x, $y, $width, $height);
                         } catch (Exception $e) {
                             // If image still fails, show error message
                             $pdf->SetTextColor(255, 0, 0);
                             $pdf->SetXY($x, $y);
                             $pdf->Cell($width, $height, 'Image Error', 0, 0, 'C');
                             $pdf->SetTextColor(0, 0, 0); // Reset text color
                         }
                      }else{
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
                //  $pdf->MultiCell(140,4,strtoupper($value),0,1,'');
                $pdf->MultiCell($width,4,strtoupper($value),0,1,'');
                } else {
                    $pdf->SetXY($x, $y); // Reset position before each Cell
                    $pdf->Cell($width, $height, $value, 0, 0, 'L'); // Add border (1)
                //  $pdf->Cell($width, $height, $value, 0, 0); // no border, no line break
                }
            }
        }
    }
}

$templateQuery->close();
$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
$timezone = "Asia/Calcutta";
if (function_exists('date_default_timezone_set')) date_default_timezone_set($timezone);
$t = time();
$d = date("Y-m-d H:i:s", $t);

$template_ids = implode(',',$template_ids);

//$updateq = "insert into candidate_logs (candidate_logs,user_id,register_id,login_time,logout_time,download_time,ip_address) values ('" . $row['RegNo'] . "','" . $d . "','" . $ip . "')";
$updateq = "INSERT INTO candidate_logs 
(username, user_id, register_id, download_time, ip_address,project_id,template_id,project_name,column_type) 
VALUES (
    '" . $_SESSION['user_name'] . "',
    '" . $record_id . "',
    '" . $_SESSION['registration_number'] . "',
    '" . $d . "',
    '" . $ip . "',
    '" . $project_id . "',
    '" . $template_ids . "',
    '" . $project_name . "',
    '" . $filter_column . "'  
)";
 $result_update = mysqli_query($conn, $updateq);
ob_end_clean();
$pdf->Output();
// $pdf->Output('D', 'AdmitCard_' . $_SESSION['registration_number']. '.pdf');

exit();
// 🔄 Hex to RGB helper
function hexToRGB($hexColor)
{
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
