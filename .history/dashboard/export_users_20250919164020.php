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
if`

// Fetch all columns of admit_card_records (for validation)
$tableColumns = [];
$colRes = $conn->query("SHOW COLUMNS FROM admit_card_records");
if ($colRes) {
    while ($col = $colRes->fetch_assoc()) {
        $tableColumns[] = $col['Field'];
    }
}
if (empty($tableColumns)) {
    http_response_code(500);
    echo "No columns found to export.";
    exit;
}

// Define ONLY the columns you want to export (in desired order)
$selectedColumns = [
    'registration_number',
    'first_name',
    'father_name',
    'emailaddress',
    'mobileNumber',
    'roll_number',
    'sex',
    'dob',
    'community',
    'address',
    'district',
    'state',
    'pincode',
    'photo_path',
    'signature_path',
    'post_applied',
    'exam_date',
    'reporting_time',
    'aadhaar_number',
    'permanent_address',
   


    
];

// Keep only those that exist in the table (avoid SQL errors), preserving order
$columns = array_values(array_filter($selectedColumns, function ($c) use ($tableColumns) {
    return in_array($c, $tableColumns, true);
}));

if (empty($columns)) {
    http_response_code(400);
    echo "None of the selected columns exist in admit_card_records.";
    exit;
}

// If there is any buffered output, clear it before sending headers
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { ob_end_clean(); }
}

// Prepare CSV headers
$filename = sprintf("admit_card_%d_%s.csv", $project_id, date("Ymd_His"));
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

// Build select query dynamically using the SELECTED column names only
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
    // Ensure the row matches the selected column order
    $ordered = [];
    foreach ($columns as $c) {
        $v = $row[$c] ?? '';
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        $ordered[] = $v;
    }
    fputcsv($out, $ordered, ',', '"');
}

fclose($out);
exit;
