<?php
session_start();

// Clear all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Delete remember me cookie if set
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Clear browser cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page with JavaScript to clear localStorage
echo "<script>
    localStorage.removeItem('offline_survey_access');
    window.location.href = 'index.php?logout=1';
</script>";
exit();
?>