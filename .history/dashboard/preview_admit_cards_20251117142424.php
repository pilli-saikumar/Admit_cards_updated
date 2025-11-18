<?php
ob_start(); 

session_start();

$user_role = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : null;
if (!isset($_SESSION["user_role"])) {
    
        // Redirect to Admin login otherwise
        header("Location: /Admit_Cards/index.php");
    
    exit();
}


include("../db_connect.php");
require_once("../vendor/setasign/fpdf/fpdf.php");

$project_id = intval($_GET['project_id']);
$filter_column = $_GET['filter_column'] ;




// Fetch all templates for this project
//$templateQuery = $conn->prepare("SELECT id, template_image_path, template_width, template_height FROM project_templates WHERE project_id = ? AND columns_name = ?");

if ($filter_column === 'all') {
    $templateQuery = $conn->prepare("SELECT id, template_image_path, template_width, template_height FROM project_templates WHERE project_id = ?");
    $templateQuery->bind_param("i", $project_id);
} else {
    $templateQuery = $conn->prepare("SELECT id, template_image_path, template_width, template_height FROM project_templates WHERE project_id = ? AND columns_name = ?");
    $templateQuery->bind_param("is", $project_id, $filter_column);
}
$templateQuery->execute();
$templateResult = $templateQuery->get_result();

// $template = $templateResult->fetch_assoc();

$project_deatails = $conn->prepare("SELECT column_based FROM projects WHERE id = ?");
$project_deatails->bind_param("i",$project_id);
$project_deatails->execute();
$project_deatails = $project_deatails->get_result();
$column_based = $project_deatails->fetch_assoc();
$column_name = $column_based['column_based'];
$project_deatails->close();





 
$pdf = new FPDF();

// Fetch data once (just one sample record)
// if ($filter_column === 'all') {
//     $dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ?");
//     $dataQuery->bind_param("i", $project_id);
// } else {
//     $dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ? AND  `$column_name` = ?");
//     $dataQuery->bind_param("is", $project_id, $filter_column);
// }

if ($filter_column === 'all') {
    $query = "SELECT a.*, p.photo_path_type,p.column_based,p.path,p.prefix,p.extension,p.photo_type 
              FROM admit_card_records a
              LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
              WHERE a.project_id = ?  ";
    $dataQuery = $conn->prepare($query);
    $dataQuery->bind_param("i", $project_id);
} else {
    $query = "SELECT a.*, p.photo_path_type,p.column_based,p.path,p.prefix,p.extension,p.photo_type
              FROM admit_card_records a
              LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
              WHERE a.project_id = ? AND a.`$column_name` = ?";
    $dataQuery = $conn->prepare($query);
    $dataQuery->bind_param("is", $project_id, $filter_column);
}
// $dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ? AND ");
// $dataQuery->bind_param("is", $project_id, $filter_column);
$dataQuery->execute();
$dataResult = $dataQuery->get_result();
  
$data = $dataResult->fetch_assoc(); 

$record_id = $data['id'];
//$record_id =2412;





$dataQuery->close();
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
// function drawBoundedMultiCell($pdf, $x, $y, $w, $h, $text, $lineHeight, $lineBreak) {
//     $pdf->SetXY($x, $y);
//     $startY = $y;

//     $lines = explode("\n", wordwrap($text, $lineBreak, "\n", true));

//     foreach ($lines as $line) {
//         // Stop drawing if going out of height box
//         if (($pdf->GetY() + $lineHeight) > ($startY + $h)) {
//             break;
//         }
//         $pdf->MultiCell($w, $lineHeight, $line, 0, 'L');
//         $pdf->SetX($x); // reset X after each line to stay in same column
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
// Loop through each template

 $pageNumber = 0;
 

  //  $pdf->AddPage();
    // $pdf->Image($absolutePath, 0, 0, 210, 297); // A4 size
  // 
    
     $registrationNumber = $data['registration_number'] ?? '';
while ($template = $templateResult->fetch_assoc()) {
  $pageNumber++;
 
    
    $template_id = $template['id'];
    $templateImage = $template['template_image_path'] ?? '';
   // echo $templateImage; die;
    //$templateImage = "/uttarpradeshpoliceandrecruitmentpromotionboard/templates/68d265d1a1c66_HCMT - 23 Admit card_page-0001 (1).jpg";
  //  $pdf->AddPage();
  // uttarpradeshpoliceandrecruitmentpromotionboard\templates\68d265d1a1c66_HCMT - 23 Admit card_page-0001 (1).jpg
   // $imagePath = "uploads/templates/" . basename($templateImage);
    // $imagePath = $templateImage;

     
    // if (!file_exists($imagePath) || pathinfo($imagePath, PATHINFO_EXTENSION) == "") {
    //     echo "❌ Image not found or missing extension: $imagePath<br>";
    //     continue; // Skip this template
    // }
     $imgPath = __DIR__ . '/../' . $templateImage;

    if (!file_exists($imgPath) || pathinfo($imgPath, PATHINFO_EXTENSION) == "") {
        echo "❌ Image not found or missing extension: $imgPath<br>";
        continue;
    }

    $pdf->AddPage();
    $pdf->Image($imgPath, 0, 0, 210, 297); // A4 size

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
   

    // Get field mappings for this template
    if ($filter_column === 'all') {
    $fieldQuery = $conn->prepare("SELECT column_name, column_based,barcode_based_value,x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style,line_break 
                                  FROM field_mappings 
                                  WHERE project_id = ? AND template_id = ? ");
    $fieldQuery->bind_param("ii", $project_id, $template_id);

    }else{  
    $fieldQuery = $conn->prepare("SELECT column_name, column_based,barcode_based_value,x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style,line_break
                                  FROM field_mappings 
                                  WHERE project_id = ? AND template_id = ? AND column_based = ?");
    $fieldQuery->bind_param("iis", $project_id, $template_id,$filter_column);
    }
    $fieldQuery->execute();
    $fieldResult = $fieldQuery->get_result();


    $photoSettings = [];
        $result = $conn->query("SELECT * FROM photo_path_settings WHERE project_id = $project_id");
        while ($row = $result->fetch_assoc()) {
            $photoSettings[$row['photo_type']] = $row;
        }



    while ($field = $fieldResult->fetch_assoc()) {

       echo "<pre/>"; print_r( $field['column_name']);
      

        $value = $data[$field['column_name']] ?? '';

       
        if (!empty($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if ($date) {
                $value = $date->format('d-m-Y'); // convert to DD-MM-YYYY
            }
        }
     
    
        /* if($data['path_type'] === 'External Path' && ($field['column_name'] === 'photo_path' || $field['column_name'] === 'signature_path' || $field['column_name'] === 'photo')){
            $column_based = $data['column_based']; // Default to 'id' if not set
            $get_id = "SELECT `$column_based` FROM admit_card_records WHERE project_id = $project_id AND id = $record_id";
            $get_id = $conn->query($get_id)->fetch_assoc();
            $get_id = $get_id[$column_based];
            
            $value = $data['path'].$get_id.$data['prefix'].'.'.$data['extension'];  
            
        
            $photoType = $data['photo_type'] ?? '';
        }else{
            $value = $data[$field['column_name']] ?? '';
            $photoPath = '';
            $photoType = '';
        } */

            $pathKey = null;
    if ($field['column_name'] === 'photo_path') {
        $pathKey = 'photo_path';
    } elseif ($field['column_name'] === 'signature_path') {
        $pathKey = 'signature_path';
    }

    if ($pathKey && isset($photoSettings[$pathKey]) && $photoSettings[$pathKey]['photo_path_type'] === 'External Path') {
        $setting = $photoSettings[$pathKey];
        $column_based = $setting['column_based'];

        $get_id = "SELECT `$column_based` FROM admit_card_records WHERE project_id = $project_id AND id = $record_id";
        $get_id = $conn->query($get_id)->fetch_assoc();
        $get_id = $get_id[$column_based];

        $value = $setting['path'] . $get_id . $setting['prefix'] . '.' . $data['extension'];
  

        $photoType = $setting['photo_type'] ?? '';
    } else {
        $value = $data[$field['column_name']] ?? '';
        $photoPath = '';
        $photoType = '';
    }


    
 
       // $value = $data[$field['column_name']] ?? '';
       // echo "<pre/>"; print_r($value);
       
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

// echo "<pre/>"; print_r($value . " width:" .$width ."height". $height ."");
        $pdf->SetXY($x, $y);
       
           if(!empty($bar_code) ) {
            $barcodeField = $field['barcode_based_value'];  // registration_number OR roll_number
          
   

        if ($pageNumber === 1 && !empty($registrationNumber)) {
            // $barcodeimage = "https://admitcards.iroams.com/upprb_sportsadmitcards/b/barcode.php?size=30&print=false&text=" . $registrationNumber;
            $barcodeimage = "https://admitcards.iroams.com/Admit_Cards/dashboard/b/barcode.php?size=30&print=false&text=" . $registrationNumber;
            $pdf->Image($barcodeimage,$x, $y, $width, $height, 'PNG');
            
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
        $pdf->MultiCell($width,4,strtoupper($value),0,1,'');
        } else {
            $pdf->SetXY($x, $y); // Reset position before each Cell
            $pdf->Cell($width, $height, $value, 0, 0, 'L'); // Add border (1)
          //  $pdf->Cell($width, $height, $value, 0, 0); // no border, no line break
        }
          
        
                
            
}

    }

    $fieldQuery->close();
}exit;

ob_end_clean();
$pdf->Output();
exit();

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
