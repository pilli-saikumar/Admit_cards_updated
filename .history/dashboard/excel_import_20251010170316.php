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

// --- Input Validation ---
$mapping     = $_POST['mapping'] ?? [];
$headers     = $_POST['excel_headers'] ?? [];
$project_id  = (int)($_POST['project_id'] ?? 0);
$excel_path  = $_POST['excel_path'] ?? '';

if (empty($mapping) || empty($headers) || empty($excel_path)) {
    die("❌ Invalid input data. Please upload again.");
}

$full_path = dirname(__DIR__) . "/" . $excel_path;
if (!file_exists($full_path)) {
    die("❌ Excel file not found at $full_path");
}

// --- Load Excel ---
$reader = IOFactory::createReaderForFile($full_path);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($full_path);
$sheet = $spreadsheet->getActiveSheet();

$totalRows = 0;
$importedCount = 0;
$updatedCount = 0;
$skippedCount = 0;

$date_fields = ['dob', 'exam_date', 'trail_date', 'dv_date'];

// Begin transaction
$conn->begin_transaction();

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

        $data['project_id'] = $project_id;

        // Convert date fields
        foreach ($date_fields as $col) {
            if (!empty($data[$col])) {
                $val = $data[$col];
                $converted = null;
                if (is_numeric($val)) {
                    try {
                        $converted = Date::excelToDateTimeObject($val)->format('Y-m-d');
                    } catch (Exception $e) {
                        $converted = null;
                    }
                } else {
                    $formats = ['d-m-Y', 'd.m.Y', 'd/m/Y', 'Y-m-d', 'Y.m.d', 'Y/m/d'];
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

        // Hash for duplicate detection
        $hash_input = implode('|', array_map('trim', array_values($data)));
        $data['row_hash'] = sha1($hash_input);
        $reg = $data['registration_number'] ?? null;

        if (empty($reg)) {
            $skippedCount++;
            continue;
        }

        // --- Check Existing ---
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
                // Update record
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
                    echo "❌ Update failed at Row $rowIndex: " . $update_stmt->error . "<br>";
                    error_log("Row $rowIndex update failed: " . $update_stmt->error);
                    $skippedCount++;
                }

                $update_stmt->close();
            }
        } else {
            // Insert record
            $columns = implode(',', array_keys($data));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $insert_sql = "INSERT INTO admit_card_records ($columns) VALUES ($placeholders)";
            $insert_stmt = $conn->prepare($insert_sql);

            if (!$insert_stmt) {
                echo "❌ Prepare failed (insert): " . $conn->error . "<br>";
                continue;
            }

            $types = str_repeat('s', count($data));
            $insert_stmt->bind_param($types, ...array_values($data));

            if ($insert_stmt->execute()) {
                $importedCount++;
            } else {
                echo "❌ Insert failed at Row $rowIndex: " . $insert_stmt->error . "<br>";
                error_log("Row $rowIndex insert failed: " . $insert_stmt->error);
                $skippedCount++;
            }

            $insert_stmt->close();
        }

        $check_stmt->close();

        if ($rowIndex % 100 == 0) {
            echo "✅ Processed $rowIndex rows so far...<br>";
            ob_flush();
            flush();
        }
    }

    $conn->commit();
    echo "<hr>✅ Done! Imported: $importedCount, Updated: $updatedCount, Skipped: $skippedCount of $totalRows rows.<hr>";

} catch (Throwable $e) {
    $conn->rollback();
    echo "❌ Import failed: " . $e->getMessage();
    error_log("Import failed: " . $e->getMessage());
}
