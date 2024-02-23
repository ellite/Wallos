<?php
    require_once 'includes/connect.php';
    session_start();
    // get token from cookie to remove from DB
    if (isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
        $sql = "DELETE FROM login_tokens WHERE token = ?";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $token, SQLITE3_TEXT);
        $stmt->execute();
    }
    $_SESSION = array();
    session_destroy();
    $cookieExpire = time() - 3600;
    setcookie('wallos_login', '', $cookieExpire);
    $db->close();
    header("Location: .");
    exit();
?>