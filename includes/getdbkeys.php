<?php

    $currencies = array();
    $query = "SELECT * FROM currencies";
    $result = $db->query($query);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $currencyId = $row['id'];
        $currencies[$currencyId] = $row;
    }

    $members = array();
    $query = "SELECT * FROM household";
    $result = $db->query($query);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $memberId = $row['id'];
        $members[$memberId] = $row;
    }

    $payment_methods = array();
    $query = $db->prepare("SELECT * FROM payment_methods WHERE enabled=:enabled");
    $query->bindValue(':enabled', 1, SQLITE3_INTEGER);
    $result = $query->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $payment_methodId = $row['id'];
        $payment_methods[$payment_methodId] = $row;
    }

    $categories = array();
    $query = "SELECT * FROM categories";
    $result = $db->query($query);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $categoryId = $row['id'];
        $categories[$categoryId] = $row;
    }

    $cycles = array();
    $query = "SELECT * FROM cycles";
    $result = $db->query($query);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $cycleId = $row['id'];
        $cycles[$cycleId] = $row;
    }

    $frequencies = array();
    $query = "SELECT * FROM frequencies";
    $result = $db->query($query);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $frequencyId = $row['id'];
        $frequencies[$frequencyId] = $row;
    }

?>