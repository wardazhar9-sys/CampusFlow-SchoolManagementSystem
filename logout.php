<?php
// logout.php - Safely destroys user session and logs out
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie in the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session on the server
session_destroy();

// Redirect to login page with a logout confirmation message
header("Location: login.php?logged_out=1");
exit();
?>