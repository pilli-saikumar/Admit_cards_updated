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

if(empty($user_name) ) {
    echo json_encode(['success' => false, 'message' => 'Username cannot be empty']);
    exit;
}else if(empty($password) ) {
    echo json_encode(['success' => false, 'message' => 'Password cannot be empty']);
    exit;
}else if(empty($otp) ) {
    echo json_encode(['success' => false, 'message' => 'OTP cannot be empty']);
    exit;
}else{
    
    
}

