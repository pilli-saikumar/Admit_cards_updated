<?php
session_start();
include_once ('../db_connect.php');
$slug = basename(__DIR__);
 $forgot_settings = [];
$projects = [];
$project_id = null;
$project_name = null;
$project_logo = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; // Default logo
$current_date = date('Y-m-d');
$live_date = null;
$unlive_date = null;


if (!empty($slug)) {
   
    $stmt = $conn->prepare("
        SELECT id, logo_path, header, name, project_live_date, project_end_date,forgot_label_name
        FROM projects
        WHERE slug = ? AND is_delete = 0   ");
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


    }
    if(!empty($project_id)){

        $stmt = $conn->prepare("SELECT * FROM forgot_settings WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $forgot_settings[] = $row;
        }
        $stmt->close();
    }
    

   
} else {
    echo "Error: Folder name (slug) is empty.";
}

  $userrecord = [];
  $errorMsg = '';
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

if (empty($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = generateCaptcha();
}
// if (isset($_GET['refresh'])) {
//     $_SESSION['captcha_code'] = generateCaptcha();
//     echo $_SESSION['captcha_code'];
//     exit;
// }
if (isset($_GET['refresh'])) {
    $_SESSION['captcha_code'] = generateCaptcha();
    echo $_SESSION['captcha_code'];
    exit;
}
// Always generate a new captcha for each page load to ensure consistency

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        
        $inputData = [];
        $whereClause = [];
        $paramTypes = '';
        $paramValues = [];

        foreach ($forgot_settings as $setting) {
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
              
                echo "<script>
                        alert('Invalid captcha. Please try again.');
                        window.location.href = '" . $_SERVER['PHP_SELF'] . "';
                      </script>";
                exit;
            }
            
            
        // If no input data, show error
        if (empty($inputData)) {
            echo "<script>alert('Please fill in the required fields.');</script>";
            exit;
        }

        // If no where clause, show error
        if (empty($whereClause)) {
            echo "<script>alert('No valid fields provided for login.');</script>";
            exit;
        }
        
        $whereClauseStr = implode(' AND ', $whereClause);
        $query = "SELECT id, registration_number,first_name,last_name FROM admit_card_records WHERE $whereClauseStr AND project_id = $project_id";
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

        } else {
            $errorMsg = "No record found with the provided details.";
        }
    }
    
    

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Candidate Forget Registration Number</title>
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
        /* .captcha-box {
    background: linear-gradient(45deg, #f3f4f6, #e5e7eb);
    color: #1f2937;
    font-family: 'Courier New', monospace;
    font-weight: 700;
    font-size: 1.5rem;
    letter-spacing: 4px;
    padding: 8px 20px;
    border-radius: 8px;
    border: 2px dashed #9ca3af;
    min-width: 180px;
    text-align: center;
    user-select: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    transform: perspective(100px) rotateX(5deg);
    transition: all 0.3s ease;
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
} */
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
   
      
        <img src="/Admit_Cards/<?= htmlspecialchars($project_logo) ?>" alt="User Icon" class="brand-logo rounded-circle">
        <h3><?= $project_name ?></h3>
        <h4 class="text-center text-danger font-weight-bold">Forget Registration Number ?</h4>
        <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger text-center mt-2" id="errorMessage"><?= htmlspecialchars($errorMsg) ?></div>
         <?php endif; ?>
       
        <form action="" method="POST">
             <?php if(!empty($forgot_settings)){ ?>
             <?php foreach ($forgot_settings as $setting) { ?>
                <?php  
                    $column_name = $setting['column_name'];  
                    $label = ucwords(str_replace('_', ' ', $setting['label_name']));

                    // Define date field names
                    $dateFields = ['dob', 'date_of_birth', 'exam_date', 'trail_date']; 
                    $inputType = in_array($column_name, $dateFields) ? 'date' : 'text';
                ?>
                <div class="mb-3">
                    <label for="registration_number" class="form-label"><?= $label ?></label>
                    <input type="<?= $inputType ?>" name="<?= $setting['column_name'] ?>" id="<?= $setting['column_name'] ?>" class="form-control" value="" required>
                  
                </div>
                <?php } ?>
                <?php }else{ ?>
                <div class="mb-3">
                    <label for="registration_number" class="form-label">Mobile Number</label>
                    <input type="text" name="mobile" id="mobile" class="form-control" value="" required>
                </div>
                <div class="mb-3">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" name="dob" id="dob" class="form-control" value="" required>
                </div>
                <?php } ?>
           
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
            <?= $_SESSION['captcha_code'] ?>
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

            <button type="submit" class="btn btn-primary w-100 mt-2">Submit</button>
        </form>
       
         <?php if(isset($userData))  : ?>
   
         <?php if($userData){ ?>
         <div class="alert alert-success text-center mt-2">Registration Number: <?= $userData['registration_number'] ?>
        <br>
         <a href="../<?= $slug ?>/index.php" class=" text-danger fw-bold">Back to Login</a>
        </div>
         <?php }else{ ?>
         <div class="alert alert-danger text-center mt-2">No record found with the provided details.</div>
         <?php } ?>
         <?php endif; ?>
   
    </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    setTimeout(function() {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.classList.add('fade-out');
        // Remove the element after the fade-out animation completes
        setTimeout(() => errorDiv.remove(), 500);
    }
}, 3000);


function refreshCaptcha() {
            fetch('?refresh=1')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('captchaBox').innerText = data;

                    // little animation
                    const box = document.getElementById('captchaBox');
                    box.style.transform = 'scale(1.2)';
                    setTimeout(() => { box.style.transform = 'scale(1)'; }, 200);
                });
        }
</script>
</body>
</html>
