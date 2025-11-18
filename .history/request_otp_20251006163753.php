<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

// Remove any old OTP session data
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
    $stmt = $conn->prepare('SELECT id, user_name, role, mobile FROM admin_users WHERE user_name = ? AND password = ? LIMIT 1');
    $stmt->bind_param('ss', $user_name, $password);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Username or Password']);
        exit;
    }

    $user = $res->fetch_assoc();
    $stmt->close();

    $userID   = $user['id'];
    $mobileNo = $user['mobile'];

    if (empty($mobileNo)) {
        echo json_encode(['success' => false, 'message' => 'Mobile number not found for this user']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = rand(100000, 999999);
    $message = "Your one time password is $otp. Please do not share this password with anyone - RECTDV";

    // Send SMS
    $smsResponse = sendSMS($mobileNo, $message);

    if ($smsResponse === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP SMS.']);
        exit;
    }

    // Store OTP in session
    $_SESSION['login_otp'] = $otp;
    $_SESSION['login_otp_expires'] = time() + (5 * 60);
    // $_SESSION['otp_user'] = [
    //     'id' => $user['id'],
    //     'user_name' => $user['user_name'],
    //     'role' => $user['role']
    // ];

    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'OTP generated and sent successfully.',
        'dev_otp' => $otp // ✅ For testing only
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// ========================
// ✅ SMS FUNCTION
// ========================
function sendSMS($mobileNo, $message)
{
    $url = "http://boancomm.net/boansms/boansmsinterface.aspx";
    $postFields = [
        'uname' => 'rsgreensms',
        'pwd' => 'rsgreen14sms',
        'pid' => '105',
        'mobileno' => $mobileNo,
        'smsmsg' => $message
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log('SMS CURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return $response;
}
?>
