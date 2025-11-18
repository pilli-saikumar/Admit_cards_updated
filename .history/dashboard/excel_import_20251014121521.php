<?php
session_start();
ob_start();
set_time_limit(0); // No timeout
ini_set('memory_limit', '1024M'); // Increase memory for large Excel

require '../vendor/autoload.php';
include("../db_connect.php");

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Collect form values
$mapping   = $_POST['mapping'] ?? [];
$headers   = $_POST['excel_headers'] ?? [];
$project_id = $_POST['project_id'] ?? 0;
$excel_path = $_POST['excel_path'] ?? '';

// Debug toggle
$DEBUG = isset($_GET['debug']);
if ($DEBUG) {
    header('Content-Type: text/plain');
    echo "Reached excel_import.php\n";
    echo "Method: " . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n";
    echo "mapping_count=" . count($mapping) . "\n";
    echo "headers_count=" . count($headers) . "\n";
    echo "project_id=$project_id\n";
    echo "excel_path=$excel_path\n";
    $fp = dirname(__DIR__) . "/" . $excel_path;
    echo "full_path=$fp exists=" . (file_exists($fp) ? 'yes' : 'no') . "\n";
}

if (empty($mapping) || empty($headers) || empty($excel_path)) {
    if ($DEBUG) {
        echo "Invalid data provided for import.\n";
        print_r($_POST);
        exit;
    }
    $_SESSION['flash_error'] = "Invalid data provided for import.";
    header("Location: upload_excel.php?project_id=" . urlencode($project_id));
    exit;
}

// Build full path
$full_path = dirname(__DIR__) . "/" . $excel_path;
if (!file_exists($full_path)) {
    if ($DEBUG) {
        echo "Excel file not found: $full_path\n";
        exit;
    }
    $_SESSION['flash_error'] = "Excel file not found: $full_path";
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

try {
    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $rowData = [];
        foreach ($cellIterator as $cell) {
            // Only process mapped columns
            if (isset($mapped_columns[$colIndex])) {
                $db_column = $mapped_columns[$colIndex];
                $value = trim((string)$cell->getValue());
                $data[$db_column] = $value;
            }
            $colIndex++;
        }
        if ($DEBUG) {
            echo "Mapped row #$rowNumber\n";
            print_r($data);
            exit;
        }
        // Skip empty rows
        if (count(array_filter($data)) == 0) {
            continue;
        }