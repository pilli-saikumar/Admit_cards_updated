<?php
session_start();
ob_start();
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require '../vendor/autoload.php';
include("../db_connect.php");

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Collect form values
$mapping     = $_POST['mapping'] ?? [];
$headers     = $_POST['excel_headers'] ?? [];
$project_id  = $_POST['project_id'] ?? 0;
$excel_path  = $_POST['excel_path'] ?? '';

error_log("Starting import - Project ID: $project_id, Excel path: $excel_path");

if (empty($mapping) || empty($headers) || empty($excel_path)) {
    $_SESSION['flash_error'] = "Invalid data provided for import.";
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}

// Build full file path
$full_path = dirname(__DIR__) . "/" . $excel_path;

if (!file_exists($full_path)) {
    $_SESSION['flash_error'] = "Excel file not found: $full_path";
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}

// Load Excel
try {
    $reader = IOFactory::createReaderForFile($full_path);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($full_path);
    $sheet = $spreadsheet->getActiveSheet();
    error_log("Excel file loaded successfully");
} catch (Exception $e) {
    $_SESSION['flash_error'] = "Failed to load Excel: " . $e->getMessage();
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}

// Counters
$totalRows     = 0;
$importedCount = 0;
$updatedCount  = 0;
$skippedCount  = 0;

// Date fields
$date_fields = ['dob', 'exam_date', 'trail_date', 'dv_date'];

$conn->begin_transaction();

try {
    $rowNumber = 0;
    
    foreach ($sheet->getRowIterator() as $row) {
        $rowNumber++;
        
        // Skip header row (first row)
        if ($rowNumber == 1) {
            continue;
        }
        
        // Get cells for this row
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        
        // Build rowData array with proper indices
        $rowData = [];
        $colIndex = 0;
        foreach ($cellIterator as $cell) {
            $rowData[$colIndex] = trim((string)$cell->getValue());
            $colIndex++;
        }
        
        // Skip empty rows
        if (count(array_filter($rowData)) == 0) {
            continue;
        }
        
        $totalRows++;
        
        // Map Excel columns to DB columns
        $data = [];
        foreach ($mapping as $excel_idx => $db_col) {
            if ($db_col !== '' && $db_col !== 'id') {
                $data[$db_col] = isset($rowData[$excel_idx]) ? $rowData[$excel_idx] : null;
            }
        }

        echo "<pre>";
        print_r($data);
        echo "</pre>";
        
        $data['project_id'] = $project_id;
        
        // Debug first row
        if ($totalRows == 1) {
            error_log("First row data: " . print_r($data, true));
        }
        
        // Convert date fields
        foreach ($data as $col => $val) {
            if (in_array($col, $date_fields) && !empty($val)) {
                $converted = null;
                
                // Handle Excel serial date numbers
                if (is_numeric($val) && $val > 25569) {
                    try {
                        $date = Date::excelToDateTimeObject($val);
                        $converted = $date->format('Y-m-d');
                    } catch (Exception $e) {
                        error_log("Date conversion failed for row $rowNumber, column $col: " . $e->getMessage());
                    }
                } else {
                    // Try common date formats
                    $formats = ['d-m-Y', 'd.m.Y', 'd/m/Y', 'Y-m-d', 'Y.m.d', 'Y/m/d', 'd-M-Y', 'd/M/Y'];
                    foreach ($formats as $format) {
                        $date = DateTime::createFromFormat($format, $val);
                        if ($date && $date->format($format) === $val) {
                            $converted = $date->format('Y-m-d');
                            break;
                        }
                    }
                }
                
                $data[$col] = $converted ?? null;
            }
        }
        
        // Generate row hash
        $hash_input = '';
        foreach ($data as $key => $val) {
            if ($key !== 'row_hash') {
                $hash_input .= trim((string)$val) . '|';
            }
        }
        $row_hash = sha1($hash_input);
        $data['row_hash'] = $row_hash;
        
        // Validate registration_number
        $reg = $data['registration_number'] ?? null;
        if (empty($reg)) {
            $skippedCount++;
            error_log("Row $rowNumber: Missing registration_number, skipped");
            continue;
        }
        
        // Check if record exists
        $check_stmt = $conn->prepare("SELECT id, row_hash FROM admit_card_records WHERE registration_number = ? AND project_id = ?");
        $check_stmt->bind_param('si', $reg, $project_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            // Record exists - check if update needed
            $check_stmt->bind_result($existing_id, $existing_hash);
            $check_stmt->fetch();
            
            if ($existing_hash === $row_hash) {
                // No changes
                $skippedCount++;
            } else {
                // Update record
                $updateData = $data;
                unset($updateData['id']);
                
                $updateCols = array_keys($updateData);
                $updateSet = implode(', ', array_map(fn($col) => "`$col` = ?", $updateCols));
                $types = str_repeat('s', count($updateData)) . 'i';
                $update_sql = "UPDATE admit_card_records SET $updateSet WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_sql);
                $params = [...array_values($updateData), $existing_id];
                $update_stmt->bind_param($types, ...$params);
                
                try {
                    $update_stmt->execute();
                    if ($update_stmt->affected_rows > 0) {
                        $updatedCount++;
                    }
                } catch (Exception $e) {
                    error_log("Row $rowNumber update failed: " . $e->getMessage());
                    $skippedCount++;
                }
                
                $update_stmt->close();
            }
        } else {
            // Insert new record
            $insertData = $data;
            unset($insertData['id']);
            
            $columns = implode(',', array_map(fn($col) => "`$col`", array_keys($insertData)));
            $placeholders = implode(',', array_fill(0, count($insertData), '?'));
            $types = str_repeat('s', count($insertData));
            $insert_sql = "INSERT INTO admit_card_records ($columns) VALUES ($placeholders)";
            
            $insert_stmt = $conn->prepare($insert_sql);
            
            try {
                $insert_stmt->bind_param($types, ...array_values($insertData));
                $insert_stmt->execute();
                
                if ($insert_stmt->affected_rows > 0) {
                    $importedCount++;
                    if ($totalRows == 1) {
                        error_log("First row inserted with ID: " . $insert_stmt->insert_id);
                    }
                }
            } catch (Exception $e) {
                error_log("Row $rowNumber insert failed: " . $e->getMessage());
                $skippedCount++;
            }
            
            $insert_stmt->close();
        }
        
        $check_stmt->close();
        
        // Progress logging
        if ($totalRows % 100 == 0) {
            error_log("Progress: $totalRows rows processed");
        }
    }
    
    // Commit transaction
    $conn->commit();
    error_log("Import completed - Imported: $importedCount, Updated: $updatedCount, Skipped: $skippedCount, Total: $totalRows");
    
    $_SESSION['success_message'] = "✅ Imported: $importedCount new records, Updated: $updatedCount records, Skipped: $skippedCount of $totalRows total rows.";
    header("Location: upload_photos.php?project_id=$project_id");
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Import failed: " . $e->getMessage());
    
    $_SESSION['flash_error'] = "Import failed: " . $e->getMessage();
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}
?>