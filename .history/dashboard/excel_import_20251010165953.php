<?php
session_start();
ob_start();
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
include("../db_connect.php");

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Input data
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
$totalRows = 0;
$importedCount = 0;
$updatedCount = 0;
$skippedCount = 0;

// Date fields to normalize
$date_fields = ['dob', 'exam_date', 'trail_date', 'dv_date'];

// Start DB transaction
$conn->begin_transaction();

try {
    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $rowData = [];
        foreach ($cellIterator as $cell) {
            $rowData[] = trim((string)$cell->getValue());
        }

        // Skip header row
        if ($rowIndex == 1) continue;
        // Skip empty rows
        if (count(array_filter($rowData)) == 0) continue;

        $totalRows++;

        // Build associative data
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
                    } catch (Exception $e) {
                        error_log("Date conversion failed: " . $e->getMessage());
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

        // Generate hash for duplicate detection
        $hash_input = implode('|', array_map('trim', array_values($data)));
        $data['row_hash'] = sha1($hash_input);
        $reg = $data['registration_number'] ?? null;

        if (empty($reg)) {
            $skippedCount++;
            continue;
        }

        // Check existing record
        $check_stmt = $conn->prepare("SELECT id, row_hash FROM admit_card_records WHERE registration_number = ? AND project_id = ?");
        $check_stmt->bind_param('si', $reg, $project_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $check_stmt->bind_result($existing_id, $existing_hash);
            $check_stmt->fetch();

            if ($existing_hash === $data['row_hash']) {
                $skippedCount++;
            } else {
                // Update existing record
                unset($data['id']);
                $updateCols = array_keys($data);
                $updateSet = implode(', ', array_map(fn($col) => "$col = ?", $updateCols));

                $update_sql = "UPDATE admit_card_records SET $updateSet WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);

                $types = str_repeat('s', count($data)) . 'i';
                $params = [...array_values($data), $existing_id];
                $update_stmt->bind_param($types, ...$params);

                if ($update_stmt->execute()) {
                    $updatedCount++;
                } else {
                    error_log("Row $rowIndex update failed: " . $update_stmt->error);
                    $skippedCount++;
                }

                $update_stmt->close();
            }
        } else {
            // Insert new record
            unset($data['id']);
            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));

            $insert_sql = "INSERT INTO admit_card_records ($columns) VALUES ($placeholders)";
            $insert_stmt = $conn->prepare($insert_sql);

            $types = str_repeat('s', count($data));
            $insert_stmt->bind_param($types, ...array_values($data));

            if ($insert_stmt->execute()) {
                $importedCount++;
            } else {
                error_log("Row $rowIndex insert failed: " . $insert_stmt->error);
                $skippedCount++;
            }

            $insert_stmt->close();
        }

        $check_stmt->close();

        // Progress feedback (for debugging)
        if ($rowIndex % 100 == 0) {
            echo "Processed $rowIndex rows<br>";
            ob_flush();
            flush();
        }
    }

    $conn->commit();

    $_SESSION['success_message'] = "✅ Imported: $importedCount, Updated: $updatedCount, Skipped: $skippedCount of $totalRows rows.";
    header("Location: upload_photos.php?project_id=$project_id");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = "Import failed: " . $e->getMessage();
    error_log("Import failed: " . $e->getMessage());
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}
