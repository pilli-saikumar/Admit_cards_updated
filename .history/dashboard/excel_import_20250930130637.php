<?php
session_start();
ob_start();
set_time_limit(0);
ini_set('memory_limit', '1024M');

require '../vendor/autoload.php';
include("../db_connect.php");

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$start_time = microtime(true);

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

$full_path = dirname(__DIR__) . "/" . $excel_path;
if (!file_exists($full_path)) {
    $_SESSION['flash_error'] = "Excel file not found on server.";
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}

// Load Excel
$reader = IOFactory::createReaderForFile($full_path);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($full_path);
$sheet = $spreadsheet->getActiveSheet();

// Counters
$totalRows     = 0;
$importedCount = 0;
$updatedCount  = 0;
$skippedCount  = 0;

// Date fields
$date_fields = ['dob', 'exam_date', 'trail_date', 'dv_date'];

// Begin DB transaction
$conn->autocommit(false);

// ✅ Step 1: Preload existing records for this project_id
$existingRecords = [];
$check_stmt = $conn->prepare("SELECT registration_number, id, row_hash FROM admit_card_records WHERE project_id = ?");
$check_stmt->bind_param('i', $project_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $existingRecords[$row['registration_number']] = $row;
}
$check_stmt->close();

// ✅ Step 2: Prepare insert/update statements (placeholders will be replaced later)
$sampleData = array_filter($mapping, fn($col) => $col !== '' && $col !== 'id');
$insert_columns = array_values($sampleData);
$insert_columns[] = 'project_id';
$insert_columns[] = 'row_hash';

$placeholders = implode(',', array_fill(0, count($insert_columns), '?'));
$columns_str = implode(',', $insert_columns);
$insert_sql = "INSERT INTO admit_card_records ($columns_str) VALUES ($placeholders)";
$insert_stmt = $conn->prepare($insert_sql);

$update_set = implode(', ', array_map(fn($col) => "$col = ?", $insert_columns));
$update_sql = "UPDATE admit_card_records SET $update_set WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);

// ✅ Step 3: Process rows
foreach ($sheet->getRowIterator() as $rowIndex => $row) {
    if ($rowIndex == 1) continue;

    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);

    $rowData = [];
    foreach ($cellIterator as $cell) {
        $rowData[] = trim((string)$cell->getValue());
    }

    if (count(array_filter($rowData)) == 0) {
        continue; // Skip empty row
    }

    $totalRows++;

    // Build associative array
    $data = [];
    foreach ($mapping as $excel_idx => $db_col) {
        if ($db_col !== '' && $db_col !== 'id') {
            $data[$db_col] = $rowData[$excel_idx] ?? null;
        }
    }
    $data['project_id'] = $project_id;

    // Convert date fields
    foreach ($data as $col => $val) {
        if (in_array($col, $date_fields) && !empty($val)) {
            $converted = null;
            if (is_numeric($val)) {
                try {
                    $date = Date::excelToDateTimeObject($val);
                    $converted = $date->format('Y-m-d');
                } catch (Exception $e) {}
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

    // Generate row hash
    $hash_input = implode('|', array_map(fn($v) => trim((string)$v), $data));
    $row_hash = sha1($hash_input);
    $data['row_hash'] = $row_hash;

    $reg = $data['registration_number'] ?? null;
    $existing = $existingRecords[$reg] ?? null;

    if ($existing) {
        if ($existing['row_hash'] === $row_hash) {
            $skippedCount++;
        } else {
            $update_data = array_values($data);
            $update_data[] = $existing['id'];
            $types = str_repeat('s', count($data)) . 'i';
            $update_stmt->bind_param($types, ...$update_data);
            try {
                $update_stmt->execute();
                if ($update_stmt->affected_rows > 0) {
                    $updatedCount++;
                } else {
                    $skippedCount++;
                }
            } catch (mysqli_sql_exception $e) {
                error_log("Row $rowIndex update failed: " . $e->getMessage());
                $skippedCount++;
            }
        }
    } else {
        $insert_data = array_values($data);
        $types = str_repeat('s', count($insert_data));
        $insert_stmt->bind_param($types, ...$insert_data);
        try {
            $insert_stmt->execute();
            if ($insert_stmt->affected_rows > 0) {
                $importedCount++;
            } else {
                $skippedCount++;
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Row $rowIndex insert failed: " . $e->getMessage());
            $skippedCount++;
        }
    }

    // Free memory per row
    unset($rowData, $data, $insert_data, $update_data);
}

// Finalize DB
$conn->commit();
$insert_stmt->close();
$update_stmt->close();

$end_time = microtime(true);
$duration = round($end_time - $start_time, 2);

// Success message
$_SESSION['success_message'] = "✅ Imported: $importedCount, Updated: $updatedCount, Skipped: $skippedCount of $totalRows rows. ⏱ Time: {$duration}s.";
header("Location: upload_photos.php?project_id=$project_id");
exit;
?>
