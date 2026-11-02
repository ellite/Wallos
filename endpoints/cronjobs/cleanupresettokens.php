<?php

require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

$deleted = $db->exec("DELETE FROM password_resets WHERE created_at <= datetime('now', '-1 hour')");

if ($deleted) {
    echo "Expired password reset tokens cleaned up successfully.\n";
} else {
    echo "No expired password reset tokens to clean up.\n";
}

$db->close();
?>