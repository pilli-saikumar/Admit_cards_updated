<?php
// Export all records and all fields for a project as CSV (Excel-compatible)
include("../db_connect.php");

// Validate input
$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    http_response_code(400);
    echo "Invalid project_id";
    exit;
}

// Fetch all columns of admit_card_records
$columns = [];
$colRes = $conn->query("SHOW COLUMNS FROM admit_card_records");
//$columns = array('registration_number', 'first_name','father_name', 'emailaddress', 'mobileNumber', 'roll_number', 'dob');
if ($colRes) {
    while ($col = $colRes->fetch_assoc()) {
        $columns[] =  $col['Field'];
    }
}
if (empty($columns)) {
    http_response_code(500);
    echo "No columns found to export.";
    exit;
}

// Prepare CSV headers
$filename = sprintf("users_project_%d_%s.csv", $project_id, date("Ymd_His"));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Optional: Excel UTF-8 BOM to ensure proper encoding in Excel
fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write header row

fputcsv($out, $columns);

// Build select query dynamically
$colList = '`' . implode('`,`', $columns) . '`';
$sql = "SELECT $colList FROM admit_card_records WHERE project_id = ? ORDER BY id";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    fclose($out);
    http_response_code(500);
    echo "Failed to prepare export query";
    exit;
}
$stmt->bind_param('i', $project_id);
$stmt->execute();
$result = $stmt->get_result();

// Stream rows
while ($row = $result->fetch_assoc()) {
    // Convert any arrays/objects to JSON strings (safety)
    foreach ($row as $k => $v) {
        if (is_array($v) || is_object($v)) {
            $row[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
    }
    fputcsv($out, $row);
}

fclose($out);
exit;
