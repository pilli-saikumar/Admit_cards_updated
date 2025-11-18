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
    $stmt = $conn->prepare('SELECT id, user_name, role, mobile FROM admin_users WHERE user_name = ? AND password = ? LIMIT 1');
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
    $userID = $user['id'];
    $mobileno = $user['mobile'];
    if($userID){
        $otp = rand ( 10000 , 99999 );
        //$output = sendSMS($userID,$message);
        echo $otp.",".$userID;
        }
        $message = "Your one time password is $otp. Please do not share this password with anyone-RECTDV";
         
        sendSMS($userID,$message);
        function sendSMS($mobileno,$message){
            $serverUrl="http://boancomm.net/boansms/boansmsinterface.aspx";
            $postin['uname'] = 'rsgreensms';
            $postin['pwd'] = 'rsgreen14sms';
            $postin['pid'] = '105';
            $postin['mobileno'] = $mobileno;
            $postin['smsmsg'] = $message;
         
            $ch = curl_init(); 
            $postvars = '';
            $mobile = isset($mobileno) ? $mobileno :  '';
            //$mobile = 9664224772;
            if ( $mobile != '' ) {
                // 387 RRC MAS  752 JkPolice 950 BPSSC
                 $postvars = "uname=rsgreensms&pwd=rsgreen14sms&pid=105&smsmsg=".$message."&mobileno=". $mobile;
                 //echo $postvars;die;
                $url = "http://boancomm.net/boansms/boansmsinterface.aspx";
                $header_array[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
                curl_setopt( $ch, CURLOPT_HTTPHEADER, $header_array);
                curl_setopt($ch,CURLOPT_URL,$url);
                curl_setopt($ch,CURLOPT_POST, 1);                //0 for a get request
                curl_setopt($ch,CURLOPT_POSTFIELDS,$postvars);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
                curl_setopt($ch,CURLOPT_TIMEOUT, 0);
                 $response = curl_exec($ch);
                curl_close ($ch);
                //return $response;
                //echo $otp.",".$mobile;
            // $updateq="update registration_details_total_all set sms_sent_id='".$response."'  where registraionid='".$_SESSION['jk_conuser']."'";
            // mysqli_query($dbhandle,$updateq);
            }
        }

    // Generate 6-digit OTP and store with 5 minute expiry

  
    
    
  //  $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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
