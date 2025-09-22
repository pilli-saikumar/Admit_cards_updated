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

// Fetch all columns of admit_card_records (original names)
$columns = [];
$colRes = $conn->query("SHOW COLUMNS FROM admit_card_records");
$column = array('registration_number', 'first_name','father_name', 'emailaddress', 'mobileNumber', 'roll_number', 'dob');
if ($colRes) {
    while ($col = $colRes->fetch_assoc()) {
        if(!in_array($col['Field'], $column)){
            $columns[] = $col['Field'];
        }
    }
}
if (empty($columns)) {
    http_response_code(500);
    echo "No columns found to export.";
    exit;
}

// If there is any buffered output, clear it before sending headers
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { ob_end_clean(); }
}

// Prepare CSV headers
$filename = sprintf("users_project_%d_%s.csv", $project_id, date("Ymd_His"));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// IMPORTANT: First bytes of body must be the sep directive for Excel
// Do NOT print BOM before this line; Excel may ignore the separator otherwise.
echo "sep=,\r\n";

$out = fopen('php://output', 'w');

// Build human-friendly header labels: first_name -> First Name
$headerLabels = array_map(function ($name) {
    $name = str_replace('_', ' ', $name);
    $name = strtolower($name);
    return ucwords($name);
}, $columns);

// Write header row (explicit delimiter/enclosure)
fputcsv($out, $headerLabels, ',', '"');

// Build select query dynamically using ORIGINAL column names
$colList = '`' . implode('`,`', $columns) . '`';
$sql = "SELECT $colList FROM admit_card_records WHERE project_id = ? AND $COL ORDER BY id";
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
    foreach ($row as $k => $v) {
        if (is_array($v) || is_object($v)) {
            $row[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
    }
    fputcsv($out, $row, ',', '"');
}

fclose($out);
exit;
