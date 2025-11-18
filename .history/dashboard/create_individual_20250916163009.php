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
//$record_id = intval($_POST['record_id']);

$project_name = $_SESSION['project_name'] ?? '';
if(!empty($project_id)){
   $get_slug = $conn->query("SELECT slug FROM projects WHERE id = $project_id"); 
   $project_slug = $get_slug->fetch_assoc();
   $project_slug = $project_slug['slug'] ?? null;

}

$recordsQuery = $conn->prepare("
    SELECT acr.id, acr.first_name, acr.registration_number, acr.project_id,acr.is_admit_card_live,
           acr.live_date, acr.unlive_date,
           acr.live_time,
           acr.unlive_time,
           p.name AS project_name, p.column_based,p.header
    FROM admit_card_records acr
    JOIN projects p ON acr.project_id = p.id
    WHERE project_id = ? AND acr.is_admit_card_live = 1
");

$recordsQuery->bind_param("i", $project_id);
$recordsQuery->execute();
$recordsResult = $recordsQuery->get_result();
$column_based = '';
$filter_column = '';
$records = [];
while ($row = $recordsResult->fetch_assoc()) {
    $records[] = $row;
    $column_based = $row['column_based'];
    if($column_based === 'all'){
        $filter_column = '';
    }else{
        $filter_column = $column_based;
    }

}
$recordsQuery->close();



if ($project_id === 0) {
    die("Project ID is required.");
}


// Create base directory for candidate folders
$base_directory = dirname(__DIR__) . "/$project_slug/candidate_pdfs/";

//$base_directory = "../uploads/candidate_pdfs/";
if (!file_exists($base_directory)) {
    mkdir($base_directory, 0777, true);
}



 $query = "SELECT a.*, p.* 
              FROM admit_card_records a
              LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
              WHERE a.project_id = ?  ";
    $dataQuery = $conn->prepare($query);
    $dataQuery->bind_param("i", $project_id);

$dataQuery->execute();
$dataResult = $dataQuery->get_result();
  
$data = $dataResult->fetch_all(MYSQLI_ASSOC); 

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
    //     
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
            
            }
       }

       
          if (!empty($value)) {
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
