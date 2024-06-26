<?php
$query = "SELECT COUNT(*) as count FROM user";
$result = $db->query($query);
$row = $result->fetchArray(SQLITE3_ASSOC);
$userCount = $row['count'];
?>