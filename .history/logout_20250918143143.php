<!-- <?php
session_start();
include("db_connect.php");

$user_role = $_SESSION['user_role'] ?? null;
$project_slug = $_SESSION['project_slug'] ?? null;
function logUserLogout($conn) {
    if (!isset($_SESSION['log_id'])) return;

    $stmt = $conn->prepare("UPDATE user_logs SET logout_time = NOW() WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['log_id']);
    $stmt->execute();
    $stmt->close();

    unset($_SESSION['log_id']); // Clean up
}
logUserLogout($conn);
// Clear session
session_unset();
session_destroy();

// Redirect based on role
if ($user_role === 'candidate' && $project_slug) {
    header("Location: /Admit_Cards/$project_slug/index.php");
} else {
    // Admin or fallback
    header("Location: index.php");
}
exit;
?> -->
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

// Clear session completely
session_unset();
session_destroy();

// Redirect
header("Location: $redirectUrl");
exit;
?>
