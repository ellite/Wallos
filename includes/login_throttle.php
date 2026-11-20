<?php

/*
  Throttle for password logins.

  A failed attempt is remembered per client address. Too many failures for one
  username from one address, or too many failures of any kind from one address,
  and further attempts are refused until the window has passed, whether or not
  the password is right. Keying on the address as well as the username keeps a
  stranger from locking the owner out of their own account.

  The address is REMOTE_ADDR. Behind a reverse proxy that is the proxy, which
  makes the per-address limit shared by every client behind it.
*/

const WALLOS_LOGIN_WINDOW_SECONDS = 900;
const WALLOS_LOGIN_MAX_PER_USERNAME = 5;
const WALLOS_LOGIN_MAX_PER_ADDRESS = 20;

function wallos_login_client_ip()
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function wallos_login_is_throttled($db, $username, $ip)
{
    $since = time() - WALLOS_LOGIN_WINDOW_SECONDS;

    $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > :since');
    if ($stmt === false) {
        return false;
    }
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':since', $since, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_NUM) : false;
    if ($row && (int) $row[0] >= WALLOS_LOGIN_MAX_PER_ADDRESS) {
        return true;
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND username = :username AND attempted_at > :since');
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':username', strtolower((string) $username), SQLITE3_TEXT);
    $stmt->bindValue(':since', $since, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_NUM) : false;

    return $row && (int) $row[0] >= WALLOS_LOGIN_MAX_PER_USERNAME;
}

function wallos_login_record_failure($db, $username, $ip)
{
    $stmt = $db->prepare('INSERT INTO login_attempts (username, ip, attempted_at) VALUES (:username, :ip, :at)');
    if ($stmt === false) {
        return;
    }
    $stmt->bindValue(':username', strtolower((string) $username), SQLITE3_TEXT);
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':at', time(), SQLITE3_INTEGER);
    $stmt->execute();

    $prune = $db->prepare('DELETE FROM login_attempts WHERE attempted_at < :before');
    $prune->bindValue(':before', time() - 86400, SQLITE3_INTEGER);
    $prune->execute();
}

function wallos_login_clear_failures($db, $username, $ip)
{
    $stmt = $db->prepare('DELETE FROM login_attempts WHERE ip = :ip AND username = :username');
    if ($stmt === false) {
        return;
    }
    $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
    $stmt->bindValue(':username', strtolower((string) $username), SQLITE3_TEXT);
    $stmt->execute();
}
