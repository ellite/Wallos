<?php
require_once 'includes/header.php';

if ($isAdmin != 1) {
    header('Location: index.php');
    exit;
}

// get admin settings from admin table
$stmt = $db->prepare('SELECT * FROM admin');
$result = $stmt->execute();
$settings = $result->fetchArray(SQLITE3_ASSOC);

// get user accounts
$stmt = $db->prepare('SELECT id, username, email FROM user ORDER BY id ASC');
$result = $stmt->execute();

$users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}
$userCount = is_array($users) ? count($users) : 0;

$loginDisabledAllowed = $userCount == 1 && $settings['registrations_open'] == 0;
?>

<section class="contain settings">

    <section class="account-section">
        <header>
            <h2><?= translate('registrations', $i18n) ?></h2>
        </header>
        <div class="admin-form">
            <div class="form-group-inline">
                <input type="checkbox" id="registrations" <?= $settings['registrations_open'] ? 'checked' : '' ?> />
                <label for="registrations"><?= translate('enable_user_registrations', $i18n) ?></label>
            </div>
            <div class="form-group">
                <label for="maxUsers"><?= translate('maximum_number_users', $i18n) ?></label>
                <input type="number" id="maxUsers" value="<?= $settings['max_users'] ?>" />
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    <?= translate('max_users_info', $i18n) ?>
                </p>
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    By enabling user registrations, the setting to disable login will be unavailable.
                </p>
            </div>
            <div class="form-group-inline">
                <input type="checkbox" id="requireEmail" <?= $settings['require_email_verification'] ? 'checked' : '' ?>
                    <?= empty($settings['smtp_address']) ? 'disabled' : '' ?> />
                <label for="requireEmail">
                    <?= translate('require_email_verification', $i18n) ?>
                </label>
            </div>
            <?php
            if (empty($settings['smtp_address'])) {
                ?>
                <div class="settings-notes">
                    <p>
                        <i class="fa-solid fa-circle-info"></i>
                        <?= translate('configure_smtp_settings_to_enable', $i18n) ?>
                    </p>
                </div>
                <?php
            }
            ?>
            <div class="form-group">
                <label for="serverUrl"><?= translate('server_url', $i18n) ?></label>
                <input type="text" id="serverUrl" value="<?= $settings['server_url'] ?>" />
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    <?= translate('server_url_info', $i18n) ?>
                </p>
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    <?= translate('server_url_password_reset', $i18n) ?>
                </p>
            </div>
            <hr>
            <div class="form-group-inline">
                <input type="checkbox" id="disableLogin" <?= $settings['login_disabled'] ? 'checked' : '' ?>
                    <?= $loginDisabledAllowed ? '' : 'disabled' ?> />
                <label for="disableLogin"><?= translate('disable_login', $i18n) ?></label>
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                    <?= translate('disable_login_info', $i18n) ?>
                </p>
                <p>
                    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                    <?= translate('disable_login_info2', $i18n) ?>
                </p>
            </div>
            <div class="buttons">
                <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveAccountRegistrations"
                    onClick="saveAccountRegistrationsButton()" />
            </div>
        </div>
    </section>

    <?php
    if ($userCount >= 0) {
        ?>

        <section class="account-section">
            <header>
                <h2><?= translate('user_management', $i18n) ?></h2>
            </header>
            <div class="user-list">
                <?php
                foreach ($users as $user) {
                    $userIcon = $user['id'] == 1 ? 'fa-user-tie' : 'fa-id-badge';
                    ?>
                    <div class="form-group-inline" data-userid="<?= $user['id'] ?>">
                        <div class="user-list-row">
                            <div title="<?= translate('username', $i18n) ?>"><i
                                    class="fa-solid <?= $userIcon ?>"></i><?= $user['username'] ?></div>
                            <div title="<?= translate('email', $i18n) ?>"><i class="fa-solid fa-envelope"></i>
                                <a href="mailto:<?= $user['email'] ?>"><?= $user['email'] ?></a>
                            </div>
                        </div>
                        <div>
                            <?php
                            if ($user['id'] != 1) {
                                ?>
                                <button class="image-button medium" onClick="removeUser(<?= $user['id'] ?>)">
                                    <img src="images/siteicons/<?= $colorTheme ?>/delete.png"
                                        title="<?= translate('delete_user', $i18n) ?>">
                                </button>
                                <?php
                            } else {
                                ?>
                                <button class="image-button medium disabled" disabled>
                                    <img src="images/siteicons/<?= $colorTheme ?>/delete.png"
                                        title="<?= translate('delete_user', $i18n) ?>">
                                </button>
                                <?php
                            }
                            ?>

                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    <?= translate('delete_user_info', $i18n) ?>
                </p>
            </div>
            <h2><?= translate('create_user', $i18n) ?></h2>
            <div class="form-group">
                <input type="text" id="newUsername" placeholder="<?= translate('username', $i18n) ?>" />
            </div>
            <div class="form-group">    
                <input type="email" id="newEmail" placeholder="<?= translate('email', $i18n) ?>" />
            </div>
            <div class="form-group-inline">
                <input type="password" id="newPassword" placeholder="<?= translate('password', $i18n) ?>" />
                <input type="submit" class="thin" value="<?= translate('add', $i18n) ?>" id="addUserButton"
                    onClick="addUserButton()" />
            </div>
        </section>

        <?php
    }
    ?>

    <section class="account-section">
        <header>
            <h2><?= translate('smtp_settings', $i18n) ?></h2>
        </header>
        <div class="admin-form">
            <div class="form-group-inline">
                <input type="text" name="smtpaddress" id="smtpaddress"
                    placeholder="<?= translate('smtp_address', $i18n) ?>" value="<?= $settings['smtp_address'] ?>" />
                <input type="text" name="smtpport" id="smtpport" placeholder="<?= translate('port', $i18n) ?>"
                    class="one-third" value="<?= $settings['smtp_port'] ?>" />
            </div>
            <div class="form-group-inline">
                <input type="radio" name="encryption" id="encryptiontls" value="tls" <?= empty($settings['encryption']) || $settings['encryption'] == "tls" ? "checked" : "" ?> />
                <label for="encryptiontls"><?= translate('tls', $i18n) ?></label>
                <input type="radio" name="encryption" id="encryptionssl" value="ssl" <?= $settings['encryption'] == "ssl" ? "checked" : "" ?> />
                <label for="encryptionssl"><?= translate('ssl', $i18n) ?></label>
            </div>
            <div class="form-group-inline">
                <input type="text" name="smtpusername" id="smtpusername"
                    placeholder="<?= translate('smtp_username', $i18n) ?>" value="<?= $settings['smtp_username'] ?>" />
            </div>
            <div class="form-group-inline">
                <input type="password" name="smtppassword" id="smtppassword"
                    placeholder="<?= translate('smtp_password', $i18n) ?>" value="<?= $settings['smtp_password'] ?>" />
            </div>
            <div class="form-group-inline">
                <input type="text" name="fromemail" id="fromemail" placeholder="<?= translate('from_email', $i18n) ?>"
                    value="<?= $settings['from_email'] ?>" />
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i> <?= translate('smtp_info', $i18n) ?>
                </p>
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    <?= translate('smtp_usage_info', $i18n) ?>
                </p>
            </div>
            <div class="buttons">
                <input type="button" class="secondary-button thin" value="<?= translate('test', $i18n) ?>"
                    id="testSmtpSettingsButton" onClick="testSmtpSettingsButton()" />
                <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveSmtpSettingsButton"
                    onClick="saveSmtpSettingsButton()" />
            </div>
        </div>
    </section>


    <section class="account-section">
        <header>
            <h2><?= translate('backup_and_restore', $i18n) ?></h2>
        </header>
        <div class="form-group-inline">
            <div>
                <input type="button" class="button thin" value="<?= translate('backup', $i18n) ?>" id="backupDB"
                    onClick="backupDB()" />
            </div>
            <div>
                <input type="button" class="secondary-button thin" value="<?= translate('restore', $i18n) ?>"
                    id="restoreDB" onClick="openRestoreDBFileSelect()" />
                <input type="file" name="restoreDBFile" id="restoreDBFile" style="display: none;" onChange="restoreDB()"
                    accept=".zip">
            </div>
        </div>
        <div class="settings-notes">
            <p>
                <i class="fa-solid fa-circle-info"></i>
                <?= translate('restore_info', $i18n) ?>
            </p>
        </div>
    </section>

</section>
<script src="scripts/admin.js?<?= $version ?>"></script>

<?php
require_once 'includes/footer.php';
?>