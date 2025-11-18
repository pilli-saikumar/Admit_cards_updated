<?php
session_start();
header('Content-Type: application/json');
include("../db_connect.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}  

$user_name = trim($_POST['user_name'] ?? '');
$password  = trim($_POST['password'] ?? '');
$otp       = trim($_POST['otp'] ?? '');

if(empty($user_name) || empty($password) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Username, Password and OTP cannot be empty']);
    exit;
}

