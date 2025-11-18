<?php
session_start();
include_once ('db_connect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   $username = $_POST["user_name"];
   $password = $_POST["password"];
   function logUserLogin($conn, $username, $role) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $stmt = $conn->prepare("INSERT INTO user_logs (username, user_role, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $role, $ip, $agent);
    $stmt->execute();

    $_SESSION['log_id'] = $stmt->insert_id; // Save log ID for logout
    $stmt->close();
}

   if(empty($username) || empty($password)) {
         echo "<script>alert('Username and Password cannot be empty');</script>";
   } else {
         $stmt = $conn->prepare("SELECT * FROM admin_users WHERE user_name = ? AND password = ?");
         $stmt->bind_param("ss", $username, $password);
         $stmt->execute();
         $result = $stmt->get_result();

         if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION["user_id"] = $user['id'];
            $_SESSION['user_name'] = $username;
            $_SESSION['user_role'] = $user['role'];
              logUserLogin($conn, $username, $user['role']); // ✅ Log the login
            header("Location: dashboard/dashboard.php");
            exit();
         } else {
              echo "<script>alert('Invalid Username or Password');</script>";
         }
   }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom Styles -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            background: white;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0px 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
      max-width: 500px;
            animation: fadeIn 1s ease-in-out;
        }

        .login-container h2 {
            font-weight: 700;
            margin-bottom: 30px;
            color: #333;
        }

        .form-label {
            font-weight: 500;
        }

        .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
        }

        .btn-primary:hover {
            background-color: #5a67d8;
            border-color: #5a67d8;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: block;
        }
    </style>
</head>
<body>

<div class="login-container">
    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="brand-logo rounded-circle" alt="Admin">
    <h2 class="text-center">Admin Login</h2>
    <form action="" method="POST">
        <div class="mb-3">
            <label for="user_name" class="form-label">User Name</label>
            <input type="text" name="user_name" id="user_name" class="form-control" placeholder="Enter your username" required>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
        </div>
     
        <!-- <div id="otpSection" class="mb-3" style="display:none;">
            <label for="otp" class="form-label">Enter OTP</label>
            <input type="text" id="otp" class="form-control" placeholder="6-digit OTP" maxlength="6" input="numeric" pattern="\d{6}">
            <div class="form-text">OTP is valid for 5 minutes.</div>
        </div> -->
        <div id="otpSection" class="mb-3" style="display:none;">
            <label class="form-label">Enter OTP</label>
            <div id="otpContainer" style="display:flex; gap:8px;">
                <input type="text" maxlength="1" class="otp-input form-control" inputmode="numeric" pattern="\d*" />
                <input type="text" maxlength="1" class="otp-input form-control" inputmode="numeric" pattern="\d*" />
                <input type="text" maxlength="1" class="otp-input form-control" inputmode="numeric" pattern="\d*" />
                <input type="text" maxlength="1" class="otp-input form-control" inputmode="numeric" pattern="\d*" />
                <input type="text" maxlength="1" class="otp-input form-control" inputmode="numeric" pattern="\d*" />
                <input type="text" maxlength="1" class="otp-input form-control" inputmode="numeric" pattern="\d*" />
            </div>
            <div class="form-text mt-2">OTP is valid for 5 minutes.</div>
        </div>
        <!-- <button type="submit" class="btn btn-primary w-100">Get OTP</button> -->
        <div class="d-grid gap-2">
            <button type="button" id="getOtpBtn" class="btn btn-primary w-100">Get OTP</button>
            <button type="button" id="verifyOtpBtn" class="btn btn-primary w-100" style="display:none;">Verify & Login</button>
        </div>

        <!-- <button type="submit" class="btn btn-primary w-100">Login</button> -->
    </form>
</div>
<style>
.otp-input {
    width: 40px;
    height: 50px;
    text-align: center;
    font-size: 24px;
    border: 1px solid #ccc;
    border-radius: 5px;
}
.otp-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 5px rgba(0,123,255,0.5);
    outline: none;
}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const otpInputs = document.querySelectorAll('.otp-input');

otpInputs.forEach((input, idx) => {
    input.addEventListener('input', () => {
        if(input.value.length && idx < otpInputs.length - 1) otpInputs[idx+1].focus();
    });
    input.addEventListener('keydown', (e) => {
        if(e.key === "Backspace" && !input.value && idx > 0) otpInputs[idx-1].focus();
    });
});
function getOTP() {
    return Array.from(otpInputs).map(input => input.value).join('');
}
    document.getElementById('getOtpBtn').addEventListener('click', function() {
        let user_name = document.getElementById('user_name').value;
        let password = document.getElementById('password').value;

        if (user_name === "" || password === "") {
            alert("User Name and Password cannot be empty");
            return;
        }

        $.ajax({
            url: "request_otp.php",
            type: "POST",
            // dataType: "json", // ✅ always specify dataType
            data: {
                user_name: user_name,
                password: password
            },
            success: function(response) {
                if (response.success === true) {
                   
                    // document.getElementById('getOtpBtn').style.display = "none";
                    // document.getElementById('verifyOtpBtn').style.display = "block";
                    // document.getElementById('otpSection').show();
                    $('#getOtpBtn').hide();
                    $('#verifyOtpBtn').show();
                    $('#otpSection').show();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                alert("Something went wrong. Please try again.");
            }
        });
    });

    document.getElementById('verifyOtpBtn').addEventListener('click', function() {

        let user_name = document.getElementById('user_name').value;
        let password = document.getElementById('password').value;
        // let otp = document.getElementById('otp').value;
        let otp = getOTP();
      

        if (otp === "") {
            alert("OTP cannot be empty");
            return;
        }

        $.ajax({
            url: "verify_otp.php",
            type: "POST",
            // dataType: "json", // ✅ always specify dataType
            data: {
                otp: otp,
                user_name: user_name,
                password: password
            },
            success: function(response) {
                if (response.success === true) {
                    alert("Login successful");
                    // Redirect to dashboard
                    window.location.href = "dashboard/dashboard.php";
                } else {
                    alert(response.message);
                    $('#getOtpBtn').show();
                    $('#verifyOtpBtn').hide();
                    $('#otpSection').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                alert("Something went wrong. Please try again.");
            }
        });
    });
</script>



</body>
</html>
