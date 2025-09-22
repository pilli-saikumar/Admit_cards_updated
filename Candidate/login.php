<?php
session_start();
include_once ('../db_connect.php');

// Generate random alphanumeric captcha
function generateCaptcha($length = 5) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $captcha = '';
    for ($i = 0; $i < $length; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $captcha;
}

if (empty($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = generateCaptcha();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg_number = $_POST["registration_number"];
    $dob = $_POST["dob"];
    $captcha = $_POST["captcha"];

    if(empty($reg_number) || empty($dob) || empty($captcha)) {
        echo "<script>alert('All fields are required');</script>";
    } elseif ($_SESSION['captcha_code'] !== $captcha) {
        echo "<script>alert('Invalid Captcha');</script>";
        $_SESSION['captcha_code'] = generateCaptcha(); // regenerate on fail
    } else {
        $stmt = $conn->prepare("SELECT * FROM admit_card_records WHERE registration_number = ? AND dob = ?");
        $stmt->bind_param("ss", $reg_number, $dob);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
        //    $_SESSION["registration_number"] = $reg_number;
             $userData = $result->fetch_assoc();

    // Store registration number
    $_SESSION["registration_number"] = $userData["registration_number"];

    // Determine and store user name
    $firstName = trim($userData["first_name"]);
    $lastName = trim($userData["last_name"]);
    $_SESSION["user_name"] = !empty($firstName) ? $firstName : $lastName;

    // Store user role (default to 'student' if not set)
    $_SESSION["user_role"] = !empty($userData["role"]) ? $userData["role"] : 'candidate';

        header("Location: index.php");
            exit();
        } else {
            echo "<script>alert('Invalid Registration Number or DOB');</script>";
            $_SESSION['captcha_code'] = generateCaptcha(); // regenerate on fail
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Candidate Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Inter', sans-serif;
        }

        .login-card {
            background-color: #fff;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 500px;
        }

        .login-card h3 {
            font-weight: 600;
            margin-bottom: 25px;
            text-align: center;
            color: #2d2d2d;
        }

        .form-label {
            font-weight: 500;
            color: #444;
        }

        .captcha-box {
            font-weight: bold;
            font-size: 22px;
            letter-spacing: 3px;
            background: #f0f1f5;
            padding: 8px 16px;
            border-radius: 6px;
            display: inline-block;
            user-select: none;
        }

        .btn-primary {
            background-color: #4a6cf7;
            border: none;
            padding: 12px;
            font-size: 16px;
        }

        .btn-primary:hover {
            background-color: #3f5bdc;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 15px;
            display: block;
        }

        .refresh-link {
            font-size: 14px;
            cursor: pointer;
            color: #4a6cf7;
            text-decoration: underline;
            margin-left: 10px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="User Icon" class="brand-logo rounded-circle">
        <h3>Candidate Login</h3>
        <form action="" method="POST">
            <div class="mb-3">
                <label for="registration_number" class="form-label">Registration Number</label>
                <input type="text" name="registration_number" id="registration_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="dob" class="form-label">Date of Birth</label>
                <input type="date" name="dob" id="dob" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Captcha</label><br>
                <span class="captcha-box"><?= $_SESSION['captcha_code'] ?></span>
                <!-- <span class="refresh-link" onclick="location.reload()">Refresh</span> -->
                <input type="text" name="captcha" class="form-control mt-2" placeholder="Enter Captcha" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-2">Login</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
