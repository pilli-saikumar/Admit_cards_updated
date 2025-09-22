<?php
ob_start(); 
// Don't start output buffering here if you want to output PDF directly
session_start();

include("../db_connect.php");
require_once("../vendor/setasign/fpdf/fpdf.php");
function wordwrap_with_max_chars($text, $charLimit) {
    if (empty($text) || $charLimit <= 0) {
        return $text;
    }

    $lines = [];
    $currentLine = '';
    $words = explode(' ', $text); // Split by space

    foreach ($words as $word) {
        // Use mb_strlen for multi-byte safe string length, just in case
        if (mb_strlen($currentLine . ' ' . $word) <= $charLimit) {
            // If adding the word (and a space) doesn't exceed the limit, add it
            if (!empty($currentLine)) {
                $currentLine .= ' '; // Add space before next word
            }
            $currentLine .= $word;
        } else {
            // Adding this word would exceed the limit.
            // Start a new line.
            if (!empty($currentLine)) {
                $lines[] = $currentLine;
            }
            // If the current word itself is longer than charLimit, break it.
            if (mb_strlen($word) > $charLimit) {
                // Break the long word into chunks
                $chunks = str_split($word, $charLimit);
                foreach ($chunks as $i => $chunk) {
                    if ($i === 0) {
                        $currentLine = $chunk; // Start the first chunk on the new line
                    } else {
                        $lines[] = $currentLine; // Add previous chunk to lines
                        $currentLine = $chunk; // Start next chunk on a fresh line
                    }
                }
            } else {
                $currentLine = $word; // Start the word on a new line
            }
        }
    }
    // Add the last accumulated line
    if (!empty($currentLine)) {
        $lines[] = $currentLine;
    }

    return implode("\n", $lines);
}


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

$pdf = new FPDF();

// Fetch data once (just one sample record)
if ($filter_column === 'all') {
    $dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ?");
    $dataQuery->bind_param("i", $project_id);
} else {
    $dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ? AND community = ?");
    $dataQuery->bind_param("is", $project_id, $filter_column);
}
// $dataQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ? AND ");
// $dataQuery->bind_param("is", $project_id, $filter_column);
$dataQuery->execute();
$dataResult = $dataQuery->get_result();

$data = $dataResult->fetch_assoc();



$dataQuery->close();

// Loop through each template
while ($template = $templateResult->fetch_assoc()) {
 
    
    $template_id = $template['id'];
    $templateImage = $template['template_image_path'] ?? '';
    $imagePath = "uploads/templates/" . basename($templateImage);

    if (!file_exists($imagePath) || pathinfo($imagePath, PATHINFO_EXTENSION) == "") {
        echo "❌ Image not found or missing extension: $imagePath<br>";
        continue; // Skip this template
    }

    $pdf->AddPage();
    $pdf->Image($imagePath, 0, 0, 210, 297); // A4 size

    // Get field mappings for this template
    if ($filter_column === 'all') {
    $fieldQuery = $conn->prepare("SELECT column_name, column_based,x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style 
                                  FROM field_mappings 
                                  WHERE project_id = ? AND template_id = ? ");
    $fieldQuery->bind_param("ii", $project_id, $template_id);

    }else{  
    $fieldQuery = $conn->prepare("SELECT column_name, column_based,x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style 
                                  FROM field_mappings 
                                  WHERE project_id = ? AND template_id = ? AND column_based = ?");
    $fieldQuery->bind_param("iis", $project_id, $template_id,$filter_column);
    }
    $fieldQuery->execute();
    $fieldResult = $fieldQuery->get_result();

   

    while ($field = $fieldResult->fetch_assoc()) {
      
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

            $fontSize = intval($field['font_size']);
            if ($fontSize <= 0) {
                $fontSize = 10; // Ensure a minimum readable font size
                error_log("WARNING: Invalid font size '{$field['font_size']}' for column '{$column_name}'. Defaulting to 10.");
            }
            $pdf->SetFont(ucfirst($fontType), $fontStyle, $fontSize);
      //  $pdf->SetFont(ucfirst($fontType), $fontStyle, intval($field['font_size']));
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


        $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
    if (!empty($value)) {
        $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
        if (file_exists($imgPath)) {
            $pdf->Image($imgPath, $x, $y, $width, $height);
        } else {
            $pdf->SetTextColor(255, 0, 0);
            $pdf->Cell($width, $height, 'Image not found');
        }
    }
} else {
   
        // if ($field['cell_type'] === 'MultiCell') {
        //     $pdf->MultiCell($width, $height, $value, 1, 'L'); // Add border (1)
        // //   /  $pdf->MultiCell($width, $height, $value);
        // } else {
        //     $pdf->SetXY($x, $y); // Reset position before each Cell
        //     $pdf->Cell($width, $height, $value, 1, 0, 'L'); // Add border (1)
        //   //  $pdf->Cell($width, $height, $value, 0, 0); // no border, no line break
        // }
        // if ($field['cell_type'] === 'MultiCell') {
               
        //         $lineHeight = $height; // Use the provided height as the line height
        //         if ($fontSize > 0) {
                   
        //             $minimumLineHeightBasedOnFont = ($fontSize / 72) * 25.4 * 1.2; // Convert points to inches, then inches to mm, then add 20% padding
        //             if ($lineHeight < $minimumLineHeightBasedOnFont) {
        //                 $lineHeight = $minimumLineHeightBasedOnFont;
                    
        //             }
        //         }

                
        //         $pdf->MultiCell($width, $lineHeight, $value, 0, 'L'); // 0 for no border
        //     } else { // cell_type is 'Cell' or anything else
              
        //         $pdf->Cell($width, $height, $value, 0, 0, 'L'); // Keep border for debugging
        //     }
       $lineHeight = $height;
                if ($fontSize > 0) {
                    $minimumLineHeightBasedOnFont = ($fontSize / 72) * 25.4 * 1.2; // Convert points to inches, then inches to mm, then add 20% padding
                    if ($lineHeight < $minimumLineHeightBasedOnFont) {
                        $lineHeight = $minimumLineHeightBasedOnFont;
                        error_log("WARNING: MultiCell height for '{$column_name}' ({$height}mm) was too small for font size {$fontSize}pt. Auto-adjusted to {$lineHeight}mm.");
                    }
                }

                // --- Dynamic Line Breaking Logic for MultiCell ---
                $dynamicCharLimit = intval($field['line_break_char_limit'] ?? 0); // Get the limit from DB, default to 0

                if ($field['cell_type'] === 'MultiCell') {
                    if ($dynamicCharLimit > 0) {
                        // If a dynamic character limit is set in the DB, apply custom word-wrapping
                        $value = wordwrap_with_max_chars($value, $dynamicCharLimit);
                        
                        echo ("DEBUG: Applied dynamic wordwrap for '{$column_name}' with limit {$dynamicCharLimit}. Value after wrap: " . str_replace("\n", "[NL]", $value)); // Log newlines for clarity
                    }
                    // If $dynamicCharLimit is 0 or not set, MultiCell will use its default width-based wrapping.

                    $pdf->MultiCell($width, $lineHeight, $value, 0, 'L'); // No border (0), Left aligned ('L')
                } else { // cell_type is 'Cell' or anything else
                    // Cell for single-line text: (width, height, text, border, line_break, alignment, fill, link)
                    // If text is longer than $width, it will be truncated.
                    $pdf->Cell($width, $height, $value, 0, 0, 'L'); // No border (0), no line break (0), Left aligned ('L')
                }

            
}

    }

    $fieldQuery->close();
}

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
