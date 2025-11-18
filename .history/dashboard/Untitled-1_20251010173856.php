<?php
session_start();
ob_start();
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
// Assuming db_connect.php initializes $conn as a mysqli object
include("../db_connect.php"); 

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// --- Input Validation ---
$mapping     = $_POST['mapping'] ?? [];
$headers     = $_POST['excel_headers'] ?? [];
$project_id  = (int)($_POST['project_id'] ?? 0);
$excel_path  = $_POST['excel_path'] ?? '';

if (empty($mapping) || empty($headers) || empty($excel_path) || $project_id === 0) {
    die("❌ Invalid input data. Please ensure mapping, headers, file path, and project ID are provided.");
}

$full_path = dirname(__DIR__) . "/" . $excel_path;
if (!file_exists($full_path)) {
    die("❌ Excel file not found at $full_path");
}

// --- Load Excel ---
try {
    $reader = IOFactory::createReaderForFile($full_path);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($full_path);
    $sheet = $spreadsheet->getActiveSheet();
} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    die("❌ Error reading Excel file: " . $e->getMessage());
}

$totalRows = 0;
$importedCount = 0;
$updatedCount = 0;
$skippedCount = 0;
$date_fields = ['dob', 'exam_date', 'trail_date', 'dv_date'];

// --- Performance and Transaction Settings ---
$CHUNK_SIZE = 1000; // Commit transaction every 1000 rows
$chunkCounter = 0;

// Get a list of DB columns to be imported (excluding 'id')
$db_columns = array_filter(array_values($mapping), fn($col) => !empty($col) && $col !== 'id');
$column_count = count($db_columns);

// Pre-define SQL fragments
$columns_sql = implode(',', $db_columns);
$placeholders_sql = implode(',', array_fill(0, $column_count + 2, '?')); // +2 for project_id and row_hash

// Prepare the core statements once outside the loop
$check_sql = "SELECT id, row_hash FROM admit_card_records WHERE registration_number = ? AND project_id = ?";
$check_stmt = $conn->prepare($check_sql);

// This is just a performance helper, the actual UPDATE set is built dynamically
$update_cols = array_merge($db_columns, ['project_id', 'row_hash']); 
$updateSet_sql = implode(', ', array_map(fn($col) => "$col = ?", $update_cols));
$update_sql = "UPDATE admit_card_records SET $updateSet_sql WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);

$insert_sql = "INSERT INTO admit_card_records ($columns_sql, project_id, row_hash) VALUES ($placeholders_sql)";
$insert_stmt = $conn->prepare($insert_sql);

if (!$check_stmt || !$update_stmt || !$insert_stmt) {
    die("❌ Database prepare failed: " . $conn->error);
}

try {
    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $rowData = [];
        foreach ($cellIterator as $cell) {
            $rowData[] = trim((string)$cell->getValue());
        }

        if ($rowIndex == 1) continue; // skip header
        if (count(array_filter($rowData)) == 0) continue; // skip empty

        $totalRows++;

        // Map Excel data to DB columns
        $data = [];
        foreach ($mapping as $excel_idx => $db_col) {
            if (!empty($db_col) && $db_col !== 'id') {
                $data[$db_col] = $rowData[$excel_idx] ?? null;
            }
        }

        // --- Data Pre-processing ---
        $data['project_id'] = $project_id;

        // Convert date fields (Improved: use DateTime for robustness)
        foreach ($date_fields as $col) {
            if (!empty($data[$col])) {
                $val = $data[$col];
                $converted = null;
                
                if (is_numeric($val) && (int)$val > 0) {
                    try {
                        // Check if it's a valid Excel date serial
                        if ($val > 25569) { // 25569 is Jan 1, 1970
                             $converted = Date::excelToDateTimeObject($val)->format('Y-m-d');
                        }
                    } catch (Exception $e) { /* ignore exception, fall through */ }
                } 
                
                // Fallback for string dates (d-m-Y, Y-m-d, etc.)
                if (!$converted) {
                    $formats = ['d-m-Y', 'd.m.Y', 'd/m/Y', 'Y-m-d', 'Y.m.d', 'Y/m/d', 'm/d/Y'];
                    foreach ($formats as $format) {
                        $date = DateTime::createFromFormat($format, $val);
                        if ($date) {
                            $converted = $date->format('Y-m-d');
                            break;
                        }
                    }
                }
                
                $data[$col] = $converted;
            }
        }

        // Prepare final data array for binding (must match prepared statement order)
        $final_data = array_map(fn($col) => $data[$col] ?? null, $db_columns);
        
        // Hash for duplicate detection and check for required field
        $hash_input = implode('|', array_map('trim', array_values($data)));
        $data['row_hash'] = sha1($hash_input);
        $reg = $data['registration_number'] ?? null;

        if (empty($reg)) {
            $skippedCount++;
            continue;
        }

        // --- Transaction Chunking Start ---
        if ($chunkCounter === 0) {
            $conn->begin_transaction();
        }

        // --- 1. Check Existing ---
        $check_stmt->bind_param('si', $reg, $project_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row_check = $result->fetch_assoc();

        $all_values_for_bind = array_merge($final_data, [$project_id, $data['row_hash']]);
        
        // Use 's' for all types to simplify (mysqli automatically casts when safe)
        $types = str_repeat('s', count($all_values_for_bind));

        if ($row_check) {
            // Record exists
            $existing_id = $row_check['id'];
            $existing_hash = $row_check['row_hash'];

            if ($existing_hash === $data['row_hash']) {
                $skippedCount++; // Data is identical
            } else {
                // --- 2. Update record ---
                $update_params = array_merge($all_values_for_bind, [$existing_id]);
                $update_types = $types . 'i'; // Add 'i' for the WHERE clause ID

                $update_stmt->bind_param($update_types, ...$update_params);

                if ($update_stmt->execute()) {
                    $updatedCount++;
                } else {
                    echo "❌ Update failed at Row $rowIndex: " . $update_stmt->error . "<br>";
                    error_log("Row $rowIndex update failed: " . $update_stmt->error);
                    $skippedCount++;
                }
            }
        } else {
            // --- 3. Insert record ---
            $insert_stmt->bind_param($types, ...$all_values_for_bind);

            if ($insert_stmt->execute()) {
                $importedCount++;
            } else {
                echo "❌ Insert failed at Row $rowIndex: " . $insert_stmt->error . "<br>";
                error_log("Row $rowIndex insert failed: " . $insert_stmt->error);
                $skippedCount++;
            }
        }
        
        // --- Transaction Chunking End ---
        $chunkCounter++;

        if ($chunkCounter >= $CHUNK_SIZE) {
            $conn->commit();
            $chunkCounter = 0; // Reset chunk counter
            
            // Output status and flush
            echo "✅ Committed $CHUNK_SIZE rows. Total Processed: $totalRows<br>";
            ob_flush();
            flush();
        }
    } // end of foreach loop

    // Commit any remaining rows (if the last chunk was incomplete)
    if ($chunkCounter > 0) {
        $conn->commit();
    }
    
    echo "<hr>✅ Done! Imported: $importedCount, Updated: $updatedCount, Skipped: $skippedCount of $totalRows rows.<hr>";

} catch (Throwable $e) {
    // If a transaction was open when the error occurred, roll it back
    if ($conn->in_transaction) { 
        $conn->rollback();
    }
    echo "❌ Import failed: " . $e->getMessage();
    error_log("Import failed: " . $e->getMessage());
} finally {
    // Close prepared statements after the loop/catch block
    $check_stmt->close();
    $update_stmt->close();
    $insert_stmt->close();
    
    // Clean up all output buffering at the very end
    ob_end_flush();
}