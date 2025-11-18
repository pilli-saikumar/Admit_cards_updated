<?php
session_start();
ob_start();
set_time_limit(0); // No timeout
ini_set('memory_limit', '1024M'); // Increase memory for large Excel

require '../vendor/autoload.php';
include("../db_connect.php");

use PhpOffice\PhpSpreadsheet\IOFactory;
echo "<pre/>"; print_r($_POST)
// Collect form values
$mapping   = $_POST['mapping'] ?? [];
$headers   = $_POST['csv_headers'] ?? [];
$project_id = $_POST['project_id'] ?? 0;
$excel_path = $_POST['csv_path'] ?? '';

if (empty($mapping) || empty($headers) || empty($excel_path)) {
    $_SESSION['flash_error'] = "Invalid data provided for import.";
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}

// Build full path
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

// Counters
$totalRows     = 0;
$importedCount = 0;
$updatedCount  = 0;
$skippedCount  = 0;

// Date fields you want to normalize
$date_fields = ['dob', 'exam_date', 'trail_date', 'dv_date'];

// Start DB transaction (faster for large inserts)
$conn->begin_transaction();

foreach ($sheet->getRowIterator() as $rowIndex => $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);

    $rowData = [];
    foreach ($cellIterator as $cell) {
        $rowData[] = trim((string)$cell->getValue());
    }

    // Skip header row (first row)
    if ($rowIndex == 1) {
        continue;
    }

    // Skip empty rows
    if (count(array_filter($rowData)) == 0) {
        continue;
    }

    $totalRows++;

    // Build associative data
    $data = [];
    foreach ($mapping as $excel_idx => $db_col) {
        if ($db_col !== '') {
            $data[$db_col] = $rowData[$excel_idx] ?? null;
        }
    }

    $data['project_id'] = $project_id;

    // Convert date fields
    foreach ($data as $col => $val) {
        if (in_array($col, $date_fields) && !empty($val)) {
            $converted = null;
            $formats = ['d-m-Y', 'd.m.Y', 'd/m/Y', 'Y-m-d', 'Y.m.d', 'Y/m/d'];
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $val);
                if ($date && $date->format($format) === $val) {
                    $converted = $date->format('Y-m-d');
                    break;
                }
            }
            $data[$col] = $converted ?? null;
        }
    }

    // Generate row hash
    $hash_input = '';
    foreach ($data as $key => $val) {
        $hash_input .= trim((string)$val) . '|';
    }
    $row_hash = sha1($hash_input);
    $data['row_hash'] = $row_hash;

    $reg = $data['registration_number'] ?? null;

    // ✅ Check if record already exists (by registration + project_id)
    $check_stmt = $conn->prepare("SELECT id, row_hash FROM admit_card_records WHERE registration_number = ? AND project_id = ?");
    $check_stmt->bind_param('si', $reg, $project_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    $check_stmt->bind_result($existing_id, $existing_hash);

    if ($check_stmt->num_rows > 0) {
        $check_stmt->fetch();
        if ($existing_hash === $row_hash) {
            $skippedCount++; // No change
        } else {
            // Update record
            $updateCols = array_keys($data);
            $updateSet = implode(', ', array_map(fn($col) => "$col = ?", $updateCols));
            $types = str_repeat('s', count($data));
            $update_sql = "UPDATE admit_card_records SET $updateSet WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $types .= 'i';
            $params = [...array_values($data), $existing_id];
            $update_stmt->bind_param($types, ...$params);
            $update_stmt->execute();
            if ($update_stmt->affected_rows > 0) {
                $updatedCount++;
            }
            $update_stmt->close();
        }
    } else {
        // Insert new record
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $types = str_repeat('s', count($data));
        $insert_sql = "INSERT INTO admit_card_records ($columns) VALUES ($placeholders)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param($types, ...array_values($data));
        $insert_stmt->execute();
        if ($insert_stmt->affected_rows > 0) {
            $importedCount++;
        }
        $insert_stmt->close();
    }

    $check_stmt->close();
}

// Commit all DB operations
$conn->commit();

// ✅ Final summary
$_SESSION['success_message'] = "✅ Imported: $importedCount, Updated: $updatedCount, Skipped: $skippedCount of $totalRows rows.";
header("Location: upload_photos.php?project_id=$project_id");
exit;
