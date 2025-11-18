<?php
session_start();
$slug = $_GET['project'] ?? $_SESSION['project_slug'] ?? 'default';
// $slug = basename(__DIR__);
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
  // Get current URL to redirect back after login
  header("Location: /Admit_Cards/$slug/index.php");
}





// Fetch project logo from DB
 include("../db_connect.php");

// $stmt = $conn->prepare("
//     SELECT logo_path, header, name
//     FROM projects
//     WHERE REPLACE(LOWER(name), ' ', '') = ? AND is_delete = 0
// ");
$stmt = $conn->prepare("
    SELECT id, logo_path, header, name
    FROM projects
    WHERE slug = ? AND is_delete = 0 ");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

if ($project) {
    $header = $project['header'];
    $logoPath = $project['logo_path'];

    // Optionally store original name back in session
    $_SESSION['project'] = $slug;
    $_SESSION['project_name'] = $project['name'];
} else {
    echo "❌ Project not found for: $slug";
    exit;
}


// $stmt = $conn->prepare("SELECT logo_path,header FROM projects WHERE name = ?");

// $stmt->bind_param("s", $slug);
// $stmt->execute();
// $result = $stmt->get_result();
// $project = $result->fetch_assoc();
// $header = $project['header'];
// print_r($project); die;

// $logoPath = $project['logo_path'] ?? 'default_logo.png';

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Candidate Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
   <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts - Inter -->
     
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>

 body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: white; /* Light gray background */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        /* Custom scrollbar for sidebar */
        .sidebar-scroll {
            scrollbar-width: thin; /* Firefox */
            scrollbar-color: #d1d5db #f3f4f6; /* Thumb color track color */
        }
        .sidebar-scroll::-webkit-scrollbar {
            width: 8px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: #f3f4f6;
            border-radius: 10px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border-radius: 10px;
            border: 2px solid #f3f4f6;
        }

    
        table { border-collapse: collapse; width: 90%; margin: 20px auto; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: center; }
        th { background-color: #f4f4f4; }
        img { max-width: 100px; }
        .btn { padding: 6px 12px; background-color: #007BFF; color: white; border: none; border-radius: 4px; text-decoration: none; }
        .btn:hover { background-color: #0056b3; }

 .row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px; /* Space between fields */
}

.col-md-3 {
    flex: 1 1 300px; /* Adjust width and responsiveness */
    min-width: 300px; /* Minimum width for each field */
}
  .existing-box:hover {
    outline: 2px dashed blue;
    z-index: 10;
}


    .custom-header {
      background: white;
      border-bottom: 1px solid #ddd;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      padding: 10px 20px;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 1000;
    }

    .custom-header img {
      height: 40px;
    }

    .custom-header h1 {
      font-size: 20px;
      margin-left: 15px;
      color: #333;
    }

    .logout-btn {
      background: #e53e3e;
      color: white;
      padding: 8px 16px;
      text-decoration: none;
      border-radius: 5px;
      font-weight: bold;
    }

    .logout-btn:hover {
      background: #c53030;
    }

    .logo-title {
      display: flex;
      align-items: center;
    }
      .content-container {
        margin-top: 50px;
  

    padding: 20px;
  }
  </style>
</head>
<body class="bg-gray-100">

<header class="bg-white border-b shadow-md px-6 py-3 flex items-center justify-between fixed top-0 left-0 right-0 z-50 h-16">
  <div class="flex items-center space-x-4">
    <img src="/Admit_Cards/<?= htmlspecialchars($logoPath) ?>" alt="Project Logo"
         class="h-10 w-auto object-contain" />
    <h1 class="text-xl font-semibold text-gray-800 tracking-wide"><?= htmlspecialchars($header) ?></h1>
  </div>
  <a href="../logout.php"
     class="text-white bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg font-semibold transition shadow-sm hover:shadow-md">
    Logout
  </a>
</header>

</body>
</html>