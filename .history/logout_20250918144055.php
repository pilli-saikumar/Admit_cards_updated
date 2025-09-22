<?php
session_start();
include("db_connect.php");

$user_role   = $_SESSION['user_role'] ?? null;
$project_slug = $_SESSION['project_slug'] ?? null;

// Log user logout
function logUserLogout($conn) {
    if (!isset($_SESSION['log_id'])) return;

    $stmt = $conn->prepare("UPDATE user_logs SET logout_time = NOW() WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['log_id']);
    $stmt->execute();
    $stmt->close();
    unset($_SESSION['log_id']);
}
logUserLogout($conn);

// Save before destroy
$redirectUrl = "index.php"; // default
if ($user_role === 'candidate' && $project_slug) {
    $redirectUrl = "/Admit_Cards/$project_slug/index.php";
}
echo $redirectUrl;DIE;
// Clear session completely
session_unset();
session_destroy();

// Redirect
header("Location: $redirectUrl");
exit;
?>
