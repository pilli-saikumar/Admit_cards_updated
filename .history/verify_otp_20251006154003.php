<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

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
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE user_name = ? AND password = ?");
    $stmt->bind_param("ss", $user_name, $password);
    $stmt->execute();
    $result = $stmt->get_result();
  

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
     
        $user_id = $user['id'];
        $session_otp = $_SESSION['login_otp'];
        $session_otp_expires = $_SESSION['login_otp_expires'];
        if(time() > $_SESSION['login_otp_expires']){
            unset($_SESSION['login_otp'], $_SESSION['login_otp_expires'], $_SESSION['otp_user']);
            echo json_encode(['success'=>false,'message'=>'OTP expired. Please request OTP again.']);
            exit;
        }
        if($otp !== $_SESSION['login_otp'] ||
        $user_name !== $_SESSION['otp_user']['user_name'] ||
        $password !== $_SESSION['otp_user']['password'] ?? ''){
            echo json_encode(['success'=>false,'message'=>'Invalid OTP or credentials']);
            exit;
        }
       
        unset($_SESSION['login_otp'], $_SESSION['login_otp_expires']);
        // if($otp === $session_otp && $session_otp_expires > time()){
        //             $_SESSION['user_id'] = $user_id;
        //     $_SESSION['user_name'] = $user_name;
        //     $_SESSION['user_role'] = $user['role'];
        //     echo json_encode(['success' => true, 'message' => 'Login successful']);
        //     exit;
        // }else{
        //     echo json_encode(['success' => false, 'message' => 'Invalid OTP or OTP expired']);
          
        // }
       
       
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Username or Password']);
        exit;
    }
    
}

