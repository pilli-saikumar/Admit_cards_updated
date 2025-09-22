<?php
ob_start();
session_start();
if (empty($_SESSION["user_name"])) {
    header("Location: ../index.php");
    exit();
}
include("../db_connect.php");
require_once("../vendor/setasign/fpdf/fpdf.php");

$project_id = intval($_POST['project_id'] ?? 0);
$project_name = $_SESSION['project_name'] ?? '';

if ($project_id === 0) {
    die("Project ID is required.");
}

// Create base directory for candidate folders
$base_directory = "../uploads/candidate_pdfs/";
if (!file_exists($base_directory)) {
    mkdir($base_directory, 0777, true);
}

// Get all candidate data for the project
$query = "SELECT a.*, p.* 
          FROM admit_card_records a
          LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
          WHERE a.project_id = ? AND a.registration_number IS NOT NULL AND a.registration_number != ''";

$dataQuery = $conn->prepare($query);
$dataQuery->bind_param("i", $project_id);
$dataQuery->execute();
$dataResult = $dataQuery->get_result();
$candidates = $dataResult->fetch_all(MYSQLI_ASSOC);
$dataQuery->close();

$total_candidates = count($candidates);
$processed_candidates = 0;
$failed_candidates = [];

// Get templates for the project
$templateQuery = $conn->prepare("
    SELECT id, template_image_path 
    FROM project_templates 
    WHERE project_id = ? 
    ORDER BY page_order ASC
");
$templateQuery->bind_param("i", $project_id);
$templateQuery->execute();
$templateResult = $templateQuery->get_result();

$templates = [];
while ($templateRow = $templateResult->fetch_assoc()) {
    $templates[] = $templateRow;
}
$templateQuery->close();

if (empty($templates)) {
    die("No templates found for this project.");
}

// Process each candidate
foreach ($candidates as $candidate) {
    $registration_number = $candidate['registration_number'];
    $candidate_id = $candidate['id'];
    
    // Create individual folder for candidate
    $candidate_folder = $base_directory . $registration_number . "/";
    if (!file_exists($candidate_folder)) {
        mkdir($candidate_folder, 0777, true);
    }
    
    try {
        // Generate PDF for this candidate
        $pdf = new FPDF();
        $pdf->SetAutoPageBreak(false);
        
        // Add pages for each template
        foreach ($templates as $template) {
            $template_id = $template['id'];
            $template_image_path = $template['template_image_path'];
            
            $pdf->AddPage();
            
            // Add template background image
            $imgPath = dirname(__DIR__) . '/' . $template_image_path;
            if (file_exists($imgPath)) {
                $pdf->Image($imgPath, 0, 0, 210, 297);
            }
            
            // Get field mappings for this template
            $fieldQuery = $conn->prepare("
                SELECT column_name, x_position, y_position, width, height, cell_type, 
                       font_size, font_type, font_color, font_style, line_break
                FROM field_mappings 
                WHERE project_id = ? AND template_id = ?
                ORDER BY id ASC
            ");
            $fieldQuery->bind_param("ii", $project_id, $template_id);
            $fieldQuery->execute();
            $fieldResult = $fieldQuery->get_result();
            
            $pdf->SetMargins(0, 0, 0);
            
            while ($field = $fieldResult->fetch_assoc()) {
                $column_name = $field['column_name'];
                $x = $field['x_position'];
                $y = $field['y_position'];
                $width = $field['width'];
                $height = $field['height'];
                $cell_type = $field['cell_type'];
                $font_size = $field['font_size'];
                $font_type = $field['font_type'];
                $font_color = $field['font_color'];
                $font_style = $field['font_style'];
                $line_break = $field['line_break'];
                
                $value = $candidate[$column_name] ?? '';
                $value = str_replace('\\', '/', trim($value));
                
                // Set font
                $fontType = strtolower($field['font_type']);
                if (!in_array($fontType, ['arial', 'helvetica', 'courier', 'times'])) {
                    $fontType = 'helvetica';
                }
                
                $pdf->SetFont($fontType, $font_style, $font_size);
                
                // Set font color
                if (!empty($font_color) && $font_color !== '#000000') {
                    $rgb = hexToRGB($font_color);
                    $pdf->SetTextColor($rgb['r'], $rgb['g'], $rgb['b']);
                } else {
                    $pdf->SetTextColor(0, 0, 0);
                }
                
                // Handle images
                if (!empty($value)) {
                    $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        $imgPath = dirname(__DIR__) . '/' . $value;
                        if (file_exists($imgPath)) {
                            $pdf->Image($imgPath, $x, $y, $width, $height);
                        }
                        continue;
                    }
                }
                
                // Handle text
                if ($line_break == 1) {
                    $pdf->MultiCell($width, $height, $value, 0, $cell_type, false);
                } else {
                    $pdf->SetXY($x, $y);
                    $pdf->Cell($width, $height, $value, 0, 0, $cell_type);
                }
            }
            
            $fieldQuery->close();
        }
        
        // Save PDF with registration number as filename
        $pdf_filename = $registration_number . ".pdf";
        $pdf_path = $candidate_folder . $pdf_filename;
        $pdf->Output($pdf_path, 'F');
        
        $processed_candidates++;
        
    } catch (Exception $e) {
        $failed_candidates[] = [
            'registration_number' => $registration_number,
            'error' => $e->getMessage()
        ];
    }
}

// Log the activity
$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
$timezone = "Asia/Calcutta";
date_default_timezone_set($timezone);
$current_time = date('Y-m-d H:i:s');

$log_message = "Generated PDFs for $processed_candidates out of $total_candidates candidates in project $project_name";
if (!empty($failed_candidates)) {
    $log_message .= ". Failed candidates: " . count($failed_candidates);
}

$logQuery = $conn->prepare("
    INSERT INTO activity_logs (project_id, user_id, action, details, ip_address, created_at) 
    VALUES (?, ?, 'generate_all_pdfs', ?, ?, ?)
");
$user_id = $_SESSION['user_id'] ?? 0;
$logQuery->bind_param("issss", $project_id, $user_id, $log_message, $ip, $current_time);
$logQuery->execute();
$logQuery->close();

$conn->close();

// Display results
echo "<!DOCTYPE html>
<html>
<head>
    <title>PDF Generation Results</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='card'>
            <div class='card-header bg-primary text-white'>
                <h4>PDF Generation Results</h4>
            </div>
            <div class='card-body'>
                <div class='alert alert-success'>
                    <strong>Successfully processed:</strong> $processed_candidates out of $total_candidates candidates
                </div>";
                
if (!empty($failed_candidates)) {
    echo "<div class='alert alert-danger'>
            <strong>Failed candidates:</strong> " . count($failed_candidates) . "
          </div>
          <div class='table-responsive'>
            <table class='table table-striped'>
                <thead>
                    <tr>
                        <th>Registration Number</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>";
                    
    foreach ($failed_candidates as $failed) {
        echo "<tr>
                <td>" . htmlspecialchars($failed['registration_number']) . "</td>
                <td>" . htmlspecialchars($failed['error']) . "</td>
              </tr>";
    }
    
    echo "    </tbody>
            </table>
          </div>";
}

echo "        <div class='mt-3'>
                    <a href='../dashboard/admitcards.php?project_id=$project_id' class='btn btn-primary'>Back to Dashboard</a>
                    <a href='$base_directory' class='btn btn-secondary' target='_blank'>View Generated PDFs</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";

// Helper function to convert hex color to RGB
function hexToRGB($hexColor) {
    $hexColor = ltrim($hexColor, '#');
    if (strlen($hexColor) == 3) {
        $hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
    }
    
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 4));
    
    return ['r' => $r, 'g' => $g, 'b' => $b];
}
?>
