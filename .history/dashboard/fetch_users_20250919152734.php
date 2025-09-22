<?php
include("../db_connect.php");

$project_id = intval($_GET['project_id'] ?? 0);
$draw = $_POST['draw'];
$start = $_POST['start'];
$length = $_POST['length'];
$searchValue = $_POST['search']['value'] ?? '';

$where = "WHERE project_id = ?";
$params = [$project_id];
$types = "i";

if (!empty($searchValue)) {
    $where .= " AND (registration_number LIKE ? OR first_name LIKE ? OR emailaddress LIKE ? OR mobileNumber LIKE ? OR roll_number LIKE ? OR dob LIKE ? )";
    $searchTerm = "%$searchValue%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "sssssss";
}

// Get total records count (without search)
$totalQuery = $conn->prepare("SELECT COUNT(*) as total FROM admit_card_records WHERE project_id = ?");
$totalQuery->bind_param("i", $project_id);
$totalQuery->execute();
$totalRecords = $totalQuery->get_result()->fetch_assoc()['total'];

// Get filtered data
$query = "SELECT id, registration_number, first_name, emailaddress, mobileNumber, roll_number, dob FROM admit_card_records $where LIMIT ?, ?";
$params[] = $start;
$params[] = $length;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['action'] = '<a href="edit_user.php?id=' . $row['id'] . '" class="text-blue-600">✏️ Edit</a>';
    $data[] = $row;
}

// Return JSON response
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => empty($searchValue) ? $totalRecords : count($data),
    "data" => $data
]);
