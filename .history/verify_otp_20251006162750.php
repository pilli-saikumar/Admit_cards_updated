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
        $mobileno 
        $session_otp = $_SESSION['login_otp'];
        $session_otp_expires = $_SESSION['login_otp_expires'];
        if(time() > $_SESSION['login_otp_expires']){
            unset($_SESSION['login_otp'], $_SESSION['login_otp_expires'], $_SESSION['otp_user']);
            echo json_encode(['success'=>false,'message'=>'OTP expired. Please request OTP again.']);
            exit;
        }
       
        if($otp === $session_otp ){
                    $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $user_name;
            $_SESSION['user_role'] = $user['role'];
            unset($_SESSION['login_otp'], $_SESSION['login_otp_expires']);
            echo json_encode(['success' => true, 'message' => 'Login successful']);
            exit;
        }else{
            echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
          
        }
       
       
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Username or Password']);
        exit;
    }
    
}

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