<?php
session_start();
include_once ('../db_connect.php');

$slug = basename(__DIR__);
 $candidate_login_settings = [];
$projects = [];
$project_id = null;
$project_name = null;
$project_logo = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; // Default logo
$current_date = date('Y-m-d');
$live_date = null;
$unlive_date = null;
$candidate_login_content = [];
$forgot_settings = [];
$live_url = null;
$project_header = null;
if (!empty($slug)) {
    // $stmt = $conn->prepare("
    //     SELECT id, logo_path, header, name,project_live_date, project_end_date
    //     FROM projects
    //     WHERE REPLACE(LOWER(name), ' ', '') = ? AND is_delete = 0
    // ");
    $stmt = $conn->prepare("
        SELECT id, logo_path, header, name, project_live_date, project_end_date,sub_header,forgot_label_name,live_url
        FROM projects
        WHERE slug = ? AND is_delete = 0  AND live_url = 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $projects[] = $row;
        $project_id = $row['id'];
        $project_name = $row['name'] ?? 'Candidate Login';
        $project_logo = $row['logo_path'] ?? 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
        $live_date = $row['project_live_date'] ?? null;
        $unlive_date = $row['project_end_date'] ?? null;
        $sub_header = $row['sub_header'] ?? null;
        $forgot_label_name = $row['forgot_label_name'] ?? null;
        $project_header = $row['header'] ?? null;
        $live_url = $row['live_url'] ?? null;


    }
 

    if ($project_id !== null) {
        $stmt = $conn->prepare("SELECT * FROM candidate_login_settings WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $candidate_login_settings[] = $row;
        }
        $stmt->close();
    }
    if ($project_id !== null) {
        $stmt = $conn->prepare("SELECT * FROM candidate_login_content WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // while ($row = $result->fetch_assoc()) {
        //     $candidate_login_content[] = $row;
        // }
        $candidate_login_content = $result->fetch_assoc();
        $stmt->close();
    }

    if($project_id !== null){
        $stmt = $conn->prepare("SELECT * FROM forgot_settings WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $forgot_settings = $result->fetch_assoc();
        $stmt->close();
    }

} else {
    echo "Error: Folder name (slug) is empty.";
}

  
 // Debugging line to check the fetched project data

// Generate random alphanumeric captcha
function generateCaptcha($length = 5) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $captcha = '';
    for ($i = 0; $i < $length; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $captcha;
}

// if (empty($_SESSION['captcha_code'])) {
//     $_SESSION['captcha_code'] = generateCaptcha();
// }
if (isset($_GET['refresh'])) {
    $_SESSION['captcha_code'] = generateCaptcha();
    echo $_SESSION['captcha_code'];
    exit;
}
// Always generate a new captcha for page load
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['captcha_code'] = generateCaptcha();
}

//$captcha = $_SESSION['captcha_code'];

                function logUserLogin($conn, $username, $role) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
                $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

                $stmt = $conn->prepare("INSERT INTO user_logs (username, user_role, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $username, $role, $ip, $agent);
                $stmt->execute();

                $_SESSION['log_id'] = $stmt->insert_id; // Save log ID for logout
                $stmt->close();
            }
    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        
        $inputData = [];
        $whereClause = [];
        $paramTypes = '';
        $paramValues = [];

        foreach ($candidate_login_settings as $setting) {
            $column = $setting['column_name'];

            if (isset($_POST[$column])) {
                $inputData[$column] = $_POST[$column];
                $whereClause[] = "$column = ?";
                $paramTypes .= 's'; // Assuming all inputs are strings; change if needed
                $paramValues[] = $_POST[$column];
            }
        }
           $captcha = $_POST["captcha"] ?? '';
            // if ($captcha !== $_SESSION['captcha_code']) {
            //     $errorMsg = "Invalid captcha.";
            //     // Show error and stop execution
            // }
        
            if ($captcha !== ($_SESSION['captcha_code'] ?? '')) {
                $_SESSION['captcha_code'] = generateCaptcha(); // regenerate
                echo "<script>
                        alert('Invalid captcha. Please try again.');
                        window.location.href = '" . $_SERVER['PHP_SELF'] . "';
                      </script>";
                exit;
            }
            
            
        // If no input data, show error
        if (empty($inputData)) {
            $_SESSION['captcha_code'] = generateCaptcha();
            echo "<script>alert('Please fill in the required fields.'); window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
            exit;
        }

        // If no where clause, show error
        if (empty($whereClause)) {
            $_SESSION['captcha_code'] = generateCaptcha();
            echo "<script>alert('No valid fields provided for login.'); window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
            exit;
        }
        
        $whereClauseStr = implode(' AND ', $whereClause);
        $query = "SELECT * FROM admit_card_records WHERE $whereClauseStr AND project_id = $project_id";
       // $query = "SELECT * FROM admit_card_records WHERE $whereClauseStr ";
   
        $stmt = $conn->prepare($query);
     
        

        if ($stmt === false) {
            echo "<script>alert('Error preparing statement: " . htmlspecialchars($conn->error) . "');</script>";
            exit;
        }

        // Bind parameters
        $stmt->bind_param($paramTypes, ...$paramValues);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $userData = $result->fetch_assoc();

            $_SESSION["registration_number"] = $userData["registration_number"];

            $firstName = trim($userData["first_name"]);
            $lastName = trim($userData["last_name"]);
            $_SESSION["user_name"] = !empty($firstName) ? $firstName : $lastName;
            $_SESSION["user_id"] = $userData["id"];

            $_SESSION["user_role"] = !empty($userData["role"]) ? $userData["role"] : 'candidate';
            $_SESSION["project_id"] = $userData["project_id"] ?? $project_id;

            $slug = basename(__DIR__);
            $_SESSION['project_slug'] = $slug;
            $username = $firstName.' '.$lastName;

              logUserLogin($conn, $username, $userData['role']); // Log the login

            header("Location: dashboard.php?project=$slug");
            exit;
        } else {
            // User found but admit card not live or invalid info
            // You can add a second check to give more precise message
            $_SESSION['captcha_code'] = generateCaptcha();
            echo "<script>alert('Invalid Credentials. Please try again.'); window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
            exit;
        }

   
}
//}
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

        /* .captcha-box {
            font-weight: bold;
            font-size: 22px;
            letter-spacing: 3px;
            background: #f0f1f5;
            padding: 8px 16px;
            border-radius: 6px;
            display: inline-block;
            user-select: none;
        } */

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
            width: 80px;
            height: 80px;
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
      
.captcha-box {
        display: inline-block;
        font-family: 'Courier New', monospace;
        font-weight: bold;
        font-size: 22px;
        letter-spacing: 3px;
        background: repeating-linear-gradient(
            45deg,
            #f2f2f2,
            #f2f2f2 10px,
            #ddd 10px,
            #ddd 20px
        );
        padding: 8px 12px;
        border: 2px dashed #333;
        transform: rotate(-3deg);
        user-select: none;
    }
.captcha-box:hover {
    transform: perspective(100px) rotateX(0deg);
    border-color: #6b7280;
}

input[name="captcha"] {
    letter-spacing: 2px;
    font-size: 1.1rem;
    padding: 10px 15px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    transition: border-color 0.3s, box-shadow 0.3s;
}

input[name="captcha"]:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    outline: none;
}
    </style>
</head>
<body>
     
    <div class="login-card">
       <?php echo %
        <?php    if($live_date && $unlive_date  && ($current_date >= $live_date && $current_date <= $unlive_date ) ) { ?>
        <img src="/Admit_Cards/<?= htmlspecialchars($project_logo) ?>" alt="User Icon" class="brand-logo rounded-circle">
        <h3 style="font-size: 1.5rem;"><?= $project_header  ?><br> <small style="font-size: 13px; color: #666; "><?= $sub_header  ?></small>  </h3>
        <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger text-center mt-2"><?= htmlspecialchars($errorMsg) ?></div>
         <?php endif; ?>
        <form action="" method="POST">
          <?php    if(!empty($candidate_login_settings) && isset($candidate_login_settings)){ ?>

            <?php foreach ($candidate_login_settings as $setting): ?>
                <?php  
                    $column_name = $setting['column_name'];  
                    $label = ucwords(str_replace('_', ' ', $setting['label_name']));

                    // Define date field names
                    $dateFields = ['dob', 'date_of_birth', 'exam_date', 'trail_date']; 
                    $inputType = in_array($column_name, $dateFields) ? 'date' : 'text';
                ?>
                <div class="mb-3">  
                    <label for="<?= $column_name ?>" class="form-label"><?= htmlspecialchars($label) ?></label>
                    <input 
                        type="<?= $inputType ?>" 
                        name="<?= $column_name ?>" 
                        id="<?= $column_name ?>" 
                        class="form-control" 
                        required
                    >
                </div>
            <?php endforeach; ?>

            <?php }else{ ?>
            <div class="mb-3">
                <label for="registration_number" class="form-label">Registration Number</label>
                <input type="text" name="registration_number" id="registration_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="dob" class="form-label">Date of Birth</label>
                <input type="date" name="dob" id="dob" class="form-control" required>
            </div>
             <?php } ?>
            <!-- <div class="mb-3">
                <label class="form-label">Captcha</label><br>
                <span class="captcha-box"><?= $_SESSION['captcha_code'] ?></span>

                <input type="text" name="captcha" class="form-control mt-2" placeholder="Enter Captcha" required>
            </div> -->
            <!-- <div class="mb-3">
    <label class="form-label fw-bold mb-2">Captch Code</label>
    <div class="d-flex align-items-center gap-3">
        <div class="captcha-box d-flex align-items-center justify-content-center">
            <?= $_SESSION['captcha_code'] ?>
        </div>
       
    </div>
    <input type="text" 
           name="captcha" 
           class="form-control mt-3" 
           placeholder="Enter the code shown above" 
           style="letter-spacing: 2px;"
           required
           autocomplete="off">
   </div> -->
   <div class="mb-3">
    <label class="form-label fw-bold mb-2">Captcha Code</label>
    <div class="d-flex align-items-center gap-3">
        <!-- Captcha Box -->
        <div id="captchaBox" 
             class="captcha-box d-flex align-items-center justify-content-center px-3 py-2 border rounded fw-bold"
            >
           <span id="captchaCode"><?= $_SESSION['captcha_code'] ?></span>
        </div>

        <!-- Refresh Button -->
        <button type="button" 
                class="btn btn-outline-secondary btn-sm" 
                onclick="refreshCaptcha()">↻</button>
    </div>

    <!-- Captcha Input -->
    <input type="text" 
           name="captcha" 
           class="form-control mt-3" 
           placeholder="Enter the code shown above" 
           style="letter-spacing: 2px;"
           required
           autocomplete="off">
</div>
            <button type="submit" class="btn btn-primary w-100 mt-2">Login</button>
        </form>
        <div class=" text-center">
            <?php if(!empty($forgot_settings) && isset($forgot_settings)){ ?>
                <a href="../<?= $slug ?>/forgot_registration_number.php" class="btn btn-link btn-sm  font-weight-bold text-danger"><?= $forgot_label_name ?? '' ?></a><br>
            <?php } ?>
          <?php if(!empty($candidate_login_content) && isset($candidate_login_content['status']) && $candidate_login_content['status'] == 1){ ?>
            <?= $candidate_login_content['page_content'] ?>
          <?php } ?>
        
           
        </div>
    </div>
    <?php } else { ?>
    <div class="login-card">
        <img src="/Admit_Cards/<?= htmlspecialchars($project_logo) ?>" alt="User Icon" class="brand-logo rounded-circle">
        <h3><?= $project_header ?></h3>
     <?php if (!empty($live_date)): ?>
  <div class="alert alert-danger text-center mt-2">
    This project is not live yet. It will be available from <strong><?= date('d-m-Y', strtotime($live_date)) ?></strong>.
  </div>
<?php endif; ?>
    <!-- <?php if ($unlive_date): ?>
        and will close on <strong><?= date('d-m-Y', strtotime($unlive_date)) ?></strong>
    <?php endif; ?>. -->
</div>
    </div>
    <?php } ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
function refreshCaptcha() {
    fetch('<?= basename(__FILE__) ?>?refresh=1')
        .then(response => response.text())
        .then(data => {
            document.getElementById('captchaCode').innerText = data;
        });
}
</script>
</body>
</html>
