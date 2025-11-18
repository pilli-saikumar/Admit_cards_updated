<?php
ob_start();
session_start();
set_time_limit(0); 
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 0);

// Redirect if the user is not logged in
$user_role = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : null;
if (!isset($_SESSION["user_role"])) {
    header("Location: /Admit_Cards/index.php");
    exit();
}

// Include database connection
include("../db_connect.php");

// Get parameters from POST or GET (for batch processing)
if (isset($_POST['project_id']) && isset($_POST['column_based'])) {
    // Initial POST request
    $project_id = intval($_POST['project_id']);
    $filter_column = $_POST['column_based'];
    $columns_name = $_POST['columns_name'];
    
    // Store in session for batch processing
    $_SESSION['batch_project_id'] = $project_id;
    $_SESSION['batch_filter_column'] = $filter_column;
    $_SESSION['batch_columns_name'] = $columns_name;
    
} elseif (isset($_SESSION['batch_project_id'])) {
    // Batch processing - get from session
    $project_id = intval($_SESSION['batch_project_id']);
    $filter_column = $_SESSION['batch_filter_column'];
    $columns_name = $_SESSION['batch_columns_name'];
} else {
    die("No project data found. Please submit the form again.");
}

// Debug: Show received data
error_log("Project ID: " . $project_id);
error_log("Filter Column: " . $filter_column);
error_log("Columns Name: " . $columns_name);

if (!empty($project_id)) {
    $get_slug = $conn->query("SELECT slug FROM projects WHERE id = $project_id");
    $project_slug = $get_slug->fetch_assoc()['slug'] ?? null;
}

if ($project_id === 0) {
    die("Project ID is required.");
}

// Check if we should process a specific range (pagination approach)
$records_per_batch = 4000; // Reduced batch size for testing
$current_batch = intval($_GET['batch'] ?? 1);
$offset = ($current_batch - 1) * $records_per_batch;

// 1. Count total records
if ($filter_column === 'all') {
    $countQuery = "SELECT COUNT(*) as total FROM admit_card_records WHERE project_id = ?";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("i", $project_id);
} else {
    $countQuery = "SELECT COUNT(*) as total FROM admit_card_records WHERE project_id = ? AND `$filter_column` = ?";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("is", $project_id, $columns_name);
}

$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Debug: Show count result
error_log("Total Records Found: " . $totalRecords);

if ($totalRecords == 0) {
    die("No records found for this project with the given filters. Project ID: $project_id, Filter: $filter_column = $columns_name");
}

$total_batches = ceil($totalRecords / $records_per_batch);

// If this is a request for a specific batch, process only that batch
if (isset($_GET['batch']) && $_GET['batch'] <= $total_batches) {
    processBatch($conn, $project_id, $filter_column, $offset, $records_per_batch, $current_batch, $columns_name);
    exit();
}

// If no batch specified, show the batch selection interface
showBatchInterface($total_batches, $totalRecords, $records_per_batch, $project_id, $columns_name, $filter_column);
exit();

function processBatch($conn, $project_id, $filter_column, $offset, $records_per_batch, $current_batch, $columns_name) {
    require_once('../vendor/autoload.php'); // Make sure FPDF is loaded
    
    // Debug batch processing
    error_log("Processing Batch: $current_batch, Project: $project_id, Filter: $filter_column = $columns_name");
    
    // Fetch templates
    if ($filter_column === 'all') {
        $templateQuery = $conn->prepare("SELECT id, template_image_path FROM project_templates WHERE project_id = ?");
        $templateQuery->bind_param("i", $project_id);
    } else {
        $templateQuery = $conn->prepare("SELECT id, template_image_path FROM project_templates WHERE project_id = ? AND columns_name = ?");
        $templateQuery->bind_param("is", $project_id, $columns_name);
    }
    
    $templateQuery->execute();
    $all_templates = $templateQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $templateQuery->close();
    
    // Debug templates
    error_log("Templates found: " . count($all_templates));
    
    if (empty($all_templates)) {
        die("No templates found for this project and filter combination.");
    }

    // Pre-fetch field mappings
    $field_mappings = [];
    foreach ($all_templates as $template) {
        $template_id = $template['id'];
        $fieldQuery = $conn->prepare("SELECT column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style
                                      FROM field_mappings WHERE project_id = ? AND template_id = ?");
        $fieldQuery->bind_param("ii", $project_id, $template_id);
        $fieldQuery->execute();
        $field_mappings[$template_id] = $fieldQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        $fieldQuery->close();
    }

    // Fetch records for this batch
    if ($filter_column === 'all') {
        $query = "SELECT a.*, p.photo_path_type,p.column_based,p.path,p.prefix,p.extension,p.photo_type FROM admit_card_records a
                  LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
                  WHERE a.project_id = ? LIMIT ? OFFSET ?";
        $dataQuery = $conn->prepare($query);
        $dataQuery->bind_param("iii", $project_id, $records_per_batch, $offset);
    } else {
        $query = "SELECT a.*, p.photo_path_type,p.column_based,p.path,p.prefix,p.extension,p.photo_type FROM admit_card_records a
                  LEFT JOIN photo_path_settings p ON a.project_id = p.project_id
                  WHERE a.project_id = ? AND a.`$filter_column` = ? LIMIT ? OFFSET ?";
        $dataQuery = $conn->prepare($query);
        $dataQuery->bind_param("isii", $project_id, $columns_name, $records_per_batch, $offset);
    }

    $dataQuery->execute();
    $records = $dataQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    $dataQuery->close();

    // Debug records
    error_log("Records found for batch: " . count($records));

    if (empty($records)) {
        die("No records found for this batch. Batch: $current_batch, Project: $project_id, Filter: $filter_column = $columns_name");
    }

    // Create single PDF for this batch only
    $pdf = new FPDF();

    foreach ($records as $data) {
        echo "<pre>";
       
        foreach ($all_templates as $template) {
            $template_id = $template['id'];
            $templateImage = $template['template_image_path'] ?? '';
       //  $templateImage = "/uttarpradeshpoliceandrecruitmentpromotionboard/templates/68d11a4e7b140_HCMT UP - JPEG.jpg";
      //   uttarpradeshpoliceandrecruitmentpromotionboard\templates\68d11a4e7b140_HCMT UP - JPEG.jpg
         $imgPath = __DIR__ . '/../' . $templateImage;

            if (!file_exists($imgPath) || pathinfo($imgPath, PATHINFO_EXTENSION) == "") {
                error_log("Template image not found: " . $imgPath);
                continue;
            }

            $pdf->AddPage();
            
            // Check template size before loading
            if (filesize($imgPath) > 5 * 1024 * 1024) {
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Text(50, 50, 'Template too large to load');
                continue;
            }
            
            $pdf->Image($imgPath, 0, 0, 210, 297);

            $fields = $field_mappings[$template_id] ?? [];

            foreach ($fields as $field) {
                $value = $data[$field['column_name']] ?? '';
                
                if ($field['column_name'] === 'photo_path' || $field['column_name'] === 'signature_path') {
                    $column_based = $data['column_based'] ?? 'id';
                    $get_id = $data[$column_based] ?? $data['id'];
                    $value = ($data['path'] ?? '') . $get_id . ($data['prefix'] ?? '') . '.' . ($data['extension'] ?? '');
                }

                $bar_code = $field['column_name'] === 'bar_code';
                $value = str_replace('\\', '/', trim($value));
           
                // Font settings
                $fontType = strtolower($field['font_type']);
                if (!in_array($fontType, ['arial', 'helvetica', 'courier', 'times'])) {
                    $fontType = 'Arial';
                }

                $styleMap = ['bold' => 'B', 'italic' => 'I', 'underline' => 'U', 'bolditalic' => 'BI', 'bold italic' => 'BI', 'normal' => '', '' => ''];
                $fontStyle = $styleMap[strtolower(trim($field['font_style']))] ?? '';

                $pdf->SetFont(ucfirst($fontType), $fontStyle, intval($field['font_size']));
                $rgb = hexToRGB($field['font_color']);
                if ($rgb) {
                    $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
                }

                $scaleX = 210 / 595;
                $scaleY = 297 / 842;
                $x = floatval($field['x_position']) * $scaleX;
                $y = floatval($field['y_position']) * $scaleY;
                $width = max(0.1, floatval($field['width']) * $scaleX);
                $height = max(0.1, floatval($field['height']) * $scaleY);

                $pdf->SetXY($x, $y);

                if ($bar_code && !empty($value)) {
                    try {
                        $barcodeimage = "https://admitcards.iroams.com/Admit_Cards/dashboard/b/barcode.php?size=30&print=false&text=" . urlencode($value);
                        $pdf->Image($barcodeimage, $x, $y, $width, $height, 'PNG');
                    } catch (Exception $e) {
                        $pdf->SetTextColor(255, 0, 0);
                        $pdf->Cell($width, $height, 'Barcode Error', 0, 0, 'C');
                        $pdf->SetTextColor(0, 0, 0);
                    }
                    continue;
                }
                echo "<pre>"; print_r($data); 
                $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));

                if (in_array($ext, ['jpg', 'jpeg', 'png']) && !empty($value)) {
                    $path_type = $data['path_type'] ?? '';
                    $imageExists = false;
                    
                    if ($path_type === 'Local Path') {
                        $imgPath = dirname(__DIR__) . '/' . ltrim($value, '/');
                        if (file_exists($imgPath) && filesize($imgPath) < 2 * 1024 * 1024) { // Max 2MB
                            $imageExists = true;
                        }
                    }

                    if ($imageExists) {
                        try {
                            $pdf->Image($imgPath, $x, $y, $width, $height);
                        } catch (Exception $e) {
                            $pdf->SetTextColor(255, 0, 0);
                            $pdf->Cell($width, $height, 'Image Error', 0, 0, 'C');
                            $pdf->SetTextColor(0, 0, 0);
                        }
                    } else {
                        $pdf->SetTextColor(255, 0, 0);
                        $pdf->Cell($width, $height, 'No Image', 0, 0, 'C');
                        $pdf->SetTextColor(0, 0, 0);
                    }
                } else {
                    if ($field['cell_type'] === 'MultiCell') {
                        $pdf->MultiCell($width, 4, strtoupper($value), 0, 1, '');
                    } else {
                        $pdf->Cell($width, $height, $value, 0, 0, 'L');
                    }
                }
            }
        }
    }
    die;
    // Output this batch
    // ob_end_clean();
    // $batch_filename = 'Admit_Cards_' . $columns_name . '_Batch_' . $current_batch . '_' . date('Ymd_His') . '.pdf';
    // $pdf->Output('D', $batch_filename);
}

function showBatchInterface($total_batches, $totalRecords, $records_per_batch, $project_id, $columns_name, $filter_column) {
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Download Admit Cards in Batches</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .batch-info { background: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
            .batch-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
            .batch-item { background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px; text-align: center; }
            .batch-item a { text-decoration: none; color: #007cba; font-weight: bold; }
            .batch-item a:hover { color: #005a87; }
            .download-all { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin: 20px 0; cursor: pointer; }
            .download-all:hover { background: #005a87; }
            .filter-info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1>Download Admit Cards</h1>
<!--         
        <div class="filter-info">
            <h3>🔍 Filter Information</h3>
            <p><strong>Project ID:</strong> <?= htmlspecialchars($project_id) ?></p>
            <p><strong>Filter Column:</strong> <?= htmlspecialchars($filter_column) ?></p>
            <p><strong>Filter Value:</strong> <?= htmlspecialchars($columns_name) ?></p>
        </div> -->
        
        <div class="batch-info">
            <h3>📊 Processing Information</h3>
            <p><strong>Total Records:</strong> <?= $totalRecords ?></p>
            <p><strong>Records per Batch:</strong> <?= $records_per_batch ?></p>
            <p><strong>Total Batches:</strong> <?= $total_batches ?></p>
         
        </div>

        <div class="batch-grid">
            <?php for ($i = 1; $i <= $total_batches; $i++): ?>
                <?php 
                    $start_record = ($i - 1) * $records_per_batch + 1;
                    $end_record = min($i * $records_per_batch, $totalRecords);
                ?>
                <div class="batch-item">
                    <h4>Batch <?= $i ?></h4>
                    <p>Records <?= $start_record ?> - <?= $end_record ?></p>
                    <a href="?batch=<?= $i ?>" target="_blank">Download Batch <?= $i ?></a>
                </div>
            <?php endfor; ?>
        </div>

        <button class="download-all" onclick="downloadAll()">📥 Download All Batches</button>
        
        <div style="margin-top: 30px;">
            <a href="javascript:history.back()" style="background: #666; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Form</a>
        </div>

        <script>
            function downloadAll() {
                const totalBatches = <?= $total_batches ?>;
                let downloaded = 0;
                
                function downloadNext() {
                    if (downloaded < totalBatches) {
                        downloaded++;
                        const link = document.createElement('a');
                        link.href = '?batch=' + downloaded;
                        link.download = '';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        // Wait 2 seconds before next download to avoid overwhelming the server
                        setTimeout(downloadNext, 2000);
                    }
                }
                
                downloadNext();
            }
        </script>
    </body>
    </html>
    <?php
}

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