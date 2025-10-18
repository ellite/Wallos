<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$oidcName = isset($data['oidcName']) ? trim($data['oidcName']) : '';
$oidcClientId = isset($data['oidcClientId']) ? trim($data['oidcClientId']) : '';
$oidcClientSecret = isset($data['oidcClientSecret']) ? trim($data['oidcClientSecret']) : '';
$oidcAuthUrl = isset($data['oidcAuthUrl']) ? trim($data['oidcAuthUrl']) : '';
$oidcTokenUrl = isset($data['oidcTokenUrl']) ? trim($data['oidcTokenUrl']) : '';
$oidcUserInfoUrl = isset($data['oidcUserInfoUrl']) ? trim($data['oidcUserInfoUrl']) : '';
$oidcRedirectUrl = isset($data['oidcRedirectUrl']) ? trim($data['oidcRedirectUrl']) : '';
$oidcLogoutUrl = isset($data['oidcLogoutUrl']) ? trim($data['oidcLogoutUrl']) : '';
$oidcUserIdentifierField = isset($data['oidcUserIdentifierField']) ? trim($data['oidcUserIdentifierField']) : '';
$oidcScopes = isset($data['oidcScopes']) ? trim($data['oidcScopes']) : '';
$oidcAuthStyle = isset($data['oidcAuthStyle']) ? trim($data['oidcAuthStyle']) : '';
$oidcAutoCreateUser = isset($data['oidcAutoCreateUser']) ? (int) $data['oidcAutoCreateUser'] : 0;
$oidcPasswordLoginDisabled = isset($data['oidcPasswordLoginDisabled']) ? (int) $data['oidcPasswordLoginDisabled'] : 0;

$checkStmt = $db->prepare('SELECT COUNT(*) as count FROM oauth_settings WHERE id = 1');
$result = $checkStmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

if ($row['count'] > 0) {
    // Update existing row
    $stmt = $db->prepare('UPDATE oauth_settings SET 
            name = :oidcName, 
            client_id = :oidcClientId, 
            client_secret = :oidcClientSecret, 
            authorization_url = :oidcAuthUrl, 
            token_url = :oidcTokenUrl, 
            user_info_url = :oidcUserInfoUrl, 
            redirect_url = :oidcRedirectUrl, 
            logout_url = :oidcLogoutUrl, 
            user_identifier_field = :oidcUserIdentifierField, 
            scopes = :oidcScopes, 
            auth_style = :oidcAuthStyle,
            auto_create_user = :oidcAutoCreateUser,
            password_login_disabled = :oidcPasswordLoginDisabled
            WHERE id = 1');
} else {
    // Insert new row
    $stmt = $db->prepare('INSERT INTO oauth_settings (
            id, name, client_id, client_secret, authorization_url, token_url, user_info_url, redirect_url, logout_url, user_identifier_field, scopes, auth_style, auto_create_user, password_login_disabled
        ) VALUES (
            1, :oidcName, :oidcClientId, :oidcClientSecret, :oidcAuthUrl, :oidcTokenUrl, :oidcUserInfoUrl, :oidcRedirectUrl, :oidcLogoutUrl, :oidcUserIdentifierField, :oidcScopes, :oidcAuthStyle, :oidcAutoCreateUser, :oidcPasswordLoginDisabled 
        )');
}

$stmt->bindParam(':oidcName', $oidcName, SQLITE3_TEXT);
$stmt->bindParam(':oidcClientId', $oidcClientId, SQLITE3_TEXT);
$stmt->bindParam(':oidcClientSecret', $oidcClientSecret, SQLITE3_TEXT);
$stmt->bindParam(':oidcAuthUrl', $oidcAuthUrl, SQLITE3_TEXT);
$stmt->bindParam(':oidcTokenUrl', $oidcTokenUrl, SQLITE3_TEXT);
$stmt->bindParam(':oidcUserInfoUrl', $oidcUserInfoUrl, SQLITE3_TEXT);
$stmt->bindParam(':oidcRedirectUrl', $oidcRedirectUrl, SQLITE3_TEXT);
$stmt->bindParam(':oidcLogoutUrl', $oidcLogoutUrl, SQLITE3_TEXT);
$stmt->bindParam(':oidcUserIdentifierField', $oidcUserIdentifierField, SQLITE3_TEXT);
$stmt->bindParam(':oidcScopes', $oidcScopes, SQLITE3_TEXT);
$stmt->bindParam(':oidcAuthStyle', $oidcAuthStyle, SQLITE3_TEXT);
$stmt->bindParam(':oidcAutoCreateUser', $oidcAutoCreateUser, SQLITE3_INTEGER);
$stmt->bindParam(':oidcPasswordLoginDisabled', $oidcPasswordLoginDisabled, SQLITE3_INTEGER);
$stmt->execute();

if ($db->changes() > 0) {
    $db->close();
    die(json_encode([
        "success" => true,
        "message" => translate('success', $i18n)
    ]));
} else {
    $db->close();
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}