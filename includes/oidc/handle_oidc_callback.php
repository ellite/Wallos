<?php

function generate_username_from_email($email)
{
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    // Take the part before the @, remove non-alphanumeric characters, and lowercase
    $username = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', explode('@', $email)[0]));
    return $username;
}

// get OIDC settings
$stmt = $db->prepare('SELECT * FROM oauth_settings WHERE id = 1');
$result = $stmt->execute();
$oidcSettings = $result->fetchArray(SQLITE3_ASSOC);

$tokenUrl = $oidcSettings['token_url'];
$redirectUri = $oidcSettings['redirect_url'];

$postFields = [
    'grant_type' => 'authorization_code',
    'code' => $_GET['code'],
    'redirect_uri' => $redirectUri,
    'client_id' => $oidcSettings['client_id'],
    'client_secret' => $oidcSettings['client_secret'],
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!$tokenData || !isset($tokenData['access_token'])) {
    die("OIDC token exchange failed.");
}

$userInfoUrl = $oidcSettings['user_info_url'];

$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenData['access_token']
]);
$response = curl_exec($ch);
curl_close($ch);

$userInfo = json_decode($response, true);
if (!$userInfo || !isset($userInfo[$oidcSettings['user_identifier_field']])) {
    die("Failed to fetch OIDC user info.");
}

$oidcSub = $userInfo[$oidcSettings['user_identifier_field']];

// Check if sub matches an existing user
$stmt = $db->prepare('SELECT * FROM user WHERE oidc_sub = :oidcSub');
$stmt->bindValue(':oidcSub', $oidcSub, SQLITE3_TEXT);
$result = $stmt->execute();
$userData = $result->fetchArray(SQLITE3_ASSOC);

if ($userData) {
    // User exists, log the user in
    require_once('oidc_login.php');

} else {
    // Might be an existing user with the same email
    $email = $userInfo['email'] ?? null;

    if (!$email) {
        // Login failed, we have nothing to go on with, redirect to login page with error
        header("Location: login.php?error=oidc_user_not_found");
        exit();
    }

    $stmt = $db->prepare('SELECT * FROM user WHERE email = :email');
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    $userData = $result->fetchArray(SQLITE3_ASSOC);
    if ($userData) {
        // Update existing user with OIDC sub
        $stmt = $db->prepare('UPDATE user SET oidc_sub = :oidcSub WHERE id = :userId');
        $stmt->bindValue(':oidcSub', $oidcSub, SQLITE3_TEXT);
        $stmt->bindValue(':userId', $userData['id'], SQLITE3_INTEGER);
        $stmt->execute();

        // Log the user in
        require_once('oidc_login.php');
    } else {
        // Check if auto-create is enabled
        if ($oidcSettings['auto_create_user']) {
            // Create a new user

            //check if username is already taken
            $usernameBase = $userInfo['preferred_username'] ?? generate_username_from_email($email);
            $username = $usernameBase;
            $attempt = 1;

            while (true) {
                $stmt = $db->prepare('SELECT COUNT(*) as count FROM user WHERE username = :username');
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);

                if ($row['count'] == 0) {
                    break; // Username is available
                }

                $username = $usernameBase . $attempt;
                $attempt++;
            }

            require_once('oidc_create_user.php');


        } else {
            // Login failed, redirect to login page with error
            header("Location: login.php?error=oidc_user_not_found");
            exit();
        }
    }
}


?>