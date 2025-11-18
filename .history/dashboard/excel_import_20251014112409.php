<?php
session_start();
ob_start();
set_time_limit(0); // No timeout
ini_set('memory_limit', '1024M'); // Handle large Excel
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

if (empty($mapping) || empty($headers) || empty($excel_path)) {
    $_SESSION['flash_error'] = "Invalid data provided for import.";
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}

// Build full file path
$full_path = dirname(__DIR__) . "/" . $excel_path;
if (!file_exists($full_path)) {
    $_SESSION['flash_error'] = "Excel file not found on server.";
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}

// ✅ Load Excel in read-only mode
$reader = IOFactory::createReaderForFile($full_path);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($full_path);
$sheet = $spreadsheet->getActiveSheet();

// ✅ Counters
$totalRows     = 0;
$importedCount = 0;
$updatedCount  = 0;
$skippedCount  = 0;

// ✅ Date fields
$date_fields = ['dob', 'exam_date', 'trail_date', 'dv_date'];

$conn->begin_transaction();

try {
    error_log("✅ Starting import for Project ID: $project_id");

    $highestRow = $sheet->getHighestDataRow();
    $highestCol = $sheet->getHighestDataColumn();
    for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {

        // Safely fetch row
        $rowRange = "A{$rowIndex}:{$highestCol}{$rowIndex}";
        $rowArray = $sheet->rangeToArray($rowRange, null, true, true, true);
    
        if (empty($rowArray) || !isset($rowArray[0]) || !is_array($rowArray[0])) {
            error_log("⚠️ Skipping row $rowIndex — empty or invalid data range ($rowRange)");
            continue;
        }
    
        $rowDataAssoc = $rowArray[0];
        $rowData = array_values($rowDataAssoc);
    
        // Skip blank rows
        if (count(array_filter($rowData)) == 0) {
            continue;
        }
    
        $totalRows++;
    
        // ✅ Map Excel columns to DB columns
        $data = [];
        foreach ($mapping as $excel_idx => $db_col) {
            if ($db_col !== '' && $db_col !== 'id') {
                $data[$db_col] = $rowData[$excel_idx] ?? null;
            }
        }
    
        $data['project_id'] = $project_id;

        // ✅ Convert date fields
        foreach ($data as $col => $val) {
            if (in_array($col, $date_fields) && !empty($val)) {
                $converted = null;

                if (is_numeric($val)) {
                    try {
                        $date = Date::excelToDateTimeObject($val);
                        $converted = $date->format('Y-m-d');
                    } catch (Exception $e) {
                        error_log("⚠️ Date conversion failed for Row $rowIndex ($col): " . $e->getMessage());
                    }
                } else {
                    $formats = ['d-m-Y', 'd.m.Y', 'd/m/Y', 'Y-m-d', 'Y.m.d', 'Y/m/d'];
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

        // ✅ Build row hash
        $hash_input = implode('|', array_map('trim', array_values($data)));
        $row_hash = sha1($hash_input);
        $data['row_hash'] = $row_hash;

        // ✅ Validate registration_number
        $reg = $data['registration_number'] ?? null;
        if (empty($reg)) {
            $skippedCount++;
            error_log("⚠️ Skipped row $rowIndex — registration_number missing");
            continue;
        }

        // ✅ Check existing record
        $check_stmt = $conn->prepare("SELECT id, row_hash FROM admit_card_records WHERE registration_number = ? AND project_id = ?");
        $check_stmt->bind_param('si', $reg, $project_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        $check_stmt->bind_result($existing_id, $existing_hash);

        if ($check_stmt->num_rows > 0) {
            $check_stmt->fetch();

            if ($existing_hash === $row_hash) {
                $skippedCount++;
            } else {
                // ✅ Update record
                $updateData = $data;
                unset($updateData['id']);

                $updateCols = array_keys($updateData);
                $updateSet  = implode(', ', array_map(fn($col) => "$col = ?", $updateCols));
                $types      = str_repeat('s', count($updateData)) . 'i';
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
                    error_log("⚠️ Row $rowIndex update failed: " . $e->getMessage());
                    $skippedCount++;
                }
                $update_stmt->close();
            }
        } else {
            // ✅ Insert new record
            $insertData = $data;
            unset($insertData['id']);

            $columns      = implode(',', array_keys($insertData));
            $placeholders = implode(',', array_fill(0, count($insertData), '?'));
            $types        = str_repeat('s', count($insertData));
            $insert_sql   = "INSERT INTO admit_card_records ($columns) VALUES ($placeholders)";
            $insert_stmt  = $conn->prepare($insert_sql);

            try {
                $insert_stmt->bind_param($types, ...array_values($insertData));
                $insert_stmt->execute();
                if ($insert_stmt->affected_rows > 0) {
                    $importedCount++;
                }
            } catch (Exception $e) {
                error_log("⚠️ Row $rowIndex insert failed: " . $e->getMessage());
                $skippedCount++;
            }
            $insert_stmt->close();
        }

        $check_stmt->close();

        // Optional limit for testing
        // if ($totalRows >= 10) break;
    }

    $conn->commit();
    error_log("✅ Import completed: Imported=$importedCount, Updated=$updatedCount, Skipped=$skippedCount / Total=$totalRows");

    $_SESSION['success_message'] = "✅ Imported: $importedCount, Updated: $updatedCount, Skipped: $skippedCount of $totalRows rows.";
    header("Location: upload_photos.php?project_id=$project_id");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("❌ Import failed: " . $e->getMessage());
    $_SESSION['flash_error'] = "Import failed: " . $e->getMessage();
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}
?>
