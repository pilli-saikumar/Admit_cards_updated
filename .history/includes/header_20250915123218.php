<?php
session_start();
// Starts or resumes a session
$user_role = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : null;
if (!isset($_SESSION["user_role"])) {
    $current_url = $_SERVER['REQUEST_URI'];

    if (stripos($current_url, '/candidate/') !== false) {
        // Redirect to Candidate login if accessing candidate area without login
        header("Location: /admit_cards/Candidate/login.php");
    } else {
        // Redirect to Admin login otherwise
        header("Location: /admit_cards/index.php");
    }
    exit();
}

ob_start(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<!-- Add this in your <head> tag -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Google Fonts - Inter -->
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css"> -->
 <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"> -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
.coordinateField {
    border-radius: 10px;
    padding: 5px;
    border: 1px solid #ccc;
    margin-bottom: 15px;
}
.back-button {
    /* display: inline-block; */
    margin-bottom: 15px;
    padding: 5px 10px;
    background-color: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-size: 1em;
    transition: background-color 0.3s ease;
    float: right;
}

.back-button:hover {
    background-color: #0056b3;
}
.selectionBox {
    position: absolute;
    border: 2px dashed #4caf50; /* green outline */
    background-color: rgba(76, 175, 80, 0.3); /* light green semi-transparent */
    pointer-events: none;
    z-index: 10;
    display: none;
}
        h3.Genderh {
            color: #fff;
            font-size: 18px;
        }

        .cat {
            text-align: center;
            color: #607d8b;
        }

        .gen {
            text-align: center;
        }

        .tile_count {
            /* margin-bottom: 20px; */
            /* margin-top: 20px; */
            padding: 20px 0px;
            border: 1px solid #dadada;
            margin: 0px 0px 20px 0px;
            box-shadow: 2px 2px 2px #dadada;
        }

        .green {
            color: #607d8b;
        }

        .count {
            color: #da870c;


        }

    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Header -->
     <!-- <header class =" bg-indigo-700 text-white p-4 shadow-md flex items-center justify-between fixed top-0 left-0 right-0 z-50 h-16">
  
        <div class="flex items-center space-x-3">
            <svg class="h-8 w-8 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m0 0l7 7m-9 2v4a1 1 0 001 1h3m-6-6v4a1 1 0 001 1h3m-6-6v4a1 1 0 001 1h3" />
            </svg>
            <h1 class="text-2xl font-bold">Dashboard</h1>
        </div>
        <button class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition duration-300 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-opacity-75">
           <a href="../logout.php"><i class="fa fa-sign-out" aria-hidden="true"></i>   Logout </a>
        </button>
    </header> -->
    <header class="bg-indigo-700 text-white p-2 flex justify-between items-center fixed w-full top-0 z-50">
    <div class="flex items-center space-x-3">
        <!-- Hamburger (mobile only) -->
        <button id="menu-btn" class="md:hidden block focus:outline-none">
            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <h1 class="text-xl font-bold">Dashboard</h1>
    </div>
    <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white py-2 px-3 rounded-lg text-sm flex items-center gap-1">
        <i class="fa fa-sign-out" aria-hidden="true"></i>
        <span class="hidden sm:inline">Logout</span>
    </a>
</header>

   <div class="flex pt-14">
    
       <aside class="w-64 bg-gray-800 text-gray-200 p-4 shadow-lg h-screen fixed left-0 top-12 overflow-y-auto">
        <nav class="space-y-2">
             <?php if($user_role === "admin") { ?>
            <a href="../dashboard/dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-indigo-600 hover:text-white transition">
                <svg class="h-6 w-6 mr-3 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3" />
                </svg>
                <span>Home</span>
            </a>

           
           <a href="../dashboard/addproject.php" class="flex items-center p-3 rounded-lg hover:bg-indigo-600 hover:text-white transition">
                <svg class="h-6 w-6 mr-3 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4v16m8-8H4" />
                </svg>
                <span>Create Project</span>
            </a>
         
            <!-- <a href="../dashboard/admitcards.php" class="flex items-center p-3 rounded-lg hover:bg-indigo-600 hover:text-white transition">
    <svg class="h-6 w-6 mr-3 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 9V7a4 4 0 10-8 0v2m-2 4h12m-6 4h.01" />
    </svg>
    <span>Admit Cards</span>
</a> -->
    <?php } ?>
        </nav>
    </aside>
    
</div>
