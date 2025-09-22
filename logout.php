<?php
session_start();

// Strong no-cache headers to prevent cached pages after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

include("db_connect.php");

$user_role   = $_SESSION['user_role'] ?? null;
$project_slug_session = $_SESSION['project_slug'] ?? $_SESSION['project'] ?? null;

// Resolve project slug from multiple sources (priority: GET > session > cookie > referer)
$project_slug = $_GET['project']
    ?? $project_slug_session
    ?? ($_COOKIE['last_project_slug'] ?? null);

if (!$project_slug && !empty($_SERVER['HTTP_REFERER'])) {
    if (preg_match('#/Admit_Cards/([^/]+)/#', $_SERVER['HTTP_REFERER'], $m)) {
        $project_slug = $m[1];
    }
}

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

// Compute redirect BEFORE destroying session
$redirectUrl = "index.php"; // default (admin/fallback)
if ($user_role === 'admin') {
    $redirectUrl = "index.php";
} else if (!empty($project_slug)) {
    // Candidate or unknown role but we have a project slug
    $redirectUrl = "/Admit_Cards/{$project_slug}/index.php";
}

// Clear session completely
session_unset();
session_destroy();

// Delete the session cookie to fully invalidate client-side session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect
header("Location: $redirectUrl");
exit;
?>
