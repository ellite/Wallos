<?php

    $currencies = array();
    $query = "SELECT * FROM currencies WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $currencyId = $row['id'];
        $currencies[$currencyId] = $row;
    }

    $members = array();
    $query = "SELECT * FROM household WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $memberId = $row['id'];
        $members[$memberId] = $row;
        $members[$memberId]['count'] = 0;
    }

    $payment_methods = array();
    $query = $db->prepare("SELECT * FROM payment_methods WHERE enabled=:enabled AND user_id = :userId ORDER BY `order` ASC");
    $query->bindValue(':enabled', 1, SQLITE3_INTEGER);
    $query->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $query->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $payment_methodId = $row['id'];
        $payment_methods[$payment_methodId] = $row;
        $payment_methods[$payment_methodId]['count'] = 0;
    }

    $categories = array();
    $query = "SELECT * FROM categories WHERE user_id = :userId ORDER BY `order` ASC";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $categoryId = $row['id'];
        $categories[$categoryId] = $row;
        $categories[$categoryId]['count'] = 0;
    }

    $cycles = array();
    $query = "SELECT * FROM cycles";
    $result = $db->query($query);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $cycleId = $row['id'];
        $cycles[$cycleId] = $row;
    }

    $frequencies = array();
    for ($i = 1; $i <= 366; $i++) {
        $frequencies[$i] = array('id' => $i, 'name' => $i);
    }

?>