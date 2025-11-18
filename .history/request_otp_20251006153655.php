<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
unset($_SESSION['login_otp'], $_SESSION['login_otp_expires'], $_SESSION['otp_user']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_name = trim($_POST['user_name'] ?? '');
$password  = trim($_POST['password'] ?? '');


if ($user_name === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and Password cannot be empty']);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT id, user_name, role FROM admin_users WHERE user_name = ? AND password = ? LIMIT 1');
    $stmt->bind_param('ss', $user_name, $password);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        unset($_SESSION['login_otp'], $_SESSION['login_otp_expires'], $_SESSION['otp_user']);
        echo json_encode(['success' => false, 'message' => 'Invalid Username or Password']);
       
        exit;
    }

    $user = $res->fetch_assoc();
    $stmt->close();

    // Generate 6-digit OTP and store with 5 minute expiry

  
    
    
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['login_otp'] = $otp;
    
    $_SESSION['login_otp_expires'] = time() + (5 * 60);
    
    // $_SESSION['otp_user'] = [
    //     'id' => $user['id'],
    //     'user_name' => $user['user_name'],
    //     'role' => $user['role']
    // ];


    // TODO: Integrate Email/SMS delivery here. For now, return dev_otp for testing.
    echo json_encode([
        'success' => true,
        'message' => 'OTP generated and sent. Please check your registered channel.',
        'dev_otp' => $otp
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while generating OTP']);
}
