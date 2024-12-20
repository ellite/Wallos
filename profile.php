<?php
require_once 'includes/header.php';
?>

<script src="scripts/libs/sortable.min.js"></script>
<script src="scripts/libs/qrcode.min.js"></script>
<style>
    .logo-preview:after {
        content: '<?= translate('upload_logo', $i18n) ?>';
    }
</style>
<section class="contain settings">
    <section class="account-section">
        <header>
            <h2><?= translate('user_details', $i18n) ?></h2>
        </header>
        <form action="endpoints/user/saveuser.php" method="post" id="userForm" enctype="multipart/form-data">
            <div class="user-form">
                <div class="fields">
                    <div>
                        <div class="user-avatar">
                            <img src="<?= htmlspecialchars($userData['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="avatar" class="avatar" id="avatarImg"
                                onClick="toggleAvatarSelect()" />
                            <span class="edit-avatar" onClick="toggleAvatarSelect()" title="Change Avatar">
                                <i class="fa-solid fa-pencil"></i>
                            </span>
                        </div>

                        <input type="hidden" name="avatar" value="<?= htmlspecialchars($userData['avatar'], ENT_QUOTES, 'UTF-8') ?>" id="avatarUser" />
                        <div class="avatar-select" id="avatarSelect">
                            <div class="avatar-list">
                                <?php foreach (scandir('images/avatars') as $index => $image): ?>
                                    <?php if (!str_starts_with($image, '.')): ?>
                                        <img src="images/avatars/<?= $image ?>" alt="<?= $image ?>" class="avatar-option"
                                            data-src="images/avatars/<?= $image ?>">
                                    <?php endif ?>
                                <?php endforeach ?>
                                <?php foreach (scandir('images/uploads/logos/avatars') as $index => $image): ?>
                                    <?php if (!str_starts_with($image, '.')): ?>
                                        <div class="avatar-container" data-src="<?= $image ?>">
                                            <img src="images/uploads/logos/avatars/<?= $image ?>" alt="<?= $image ?>"
                                                class="avatar-option" data-src="images/uploads/logos/avatars/<?= $image ?>">
                                            <div class="remove-avatar" onclick="deleteAvatar('<?= $image ?>')"
                                                title="Delete avatar">
                                                <i class="fa-solid fa-xmark"></i>
                                            </div>
                                        </div>
                                    <?php endif ?>
                                <?php endforeach ?>
                                <label for="profile_pic" class="add-avatar"
                                    title="<?= translate('upload_avatar', $i18n) ?>">
                                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                </label>
                            </div>
                            <input type="file" id="profile_pic" class="hidden-input" name="profile_pic"
                                accept="image/jpeg, image/png, image/gif, image/webp"
                                onChange="successfulUpload(this, '<?= addslashes(translate('file_type_error', $i18n)) ?>')" />
                        </div>
                    </div>
                    <div class="grow">
                        <div class="form-group">
                            <label for="username"><?= translate('username', $i18n) ?>:</label>
                            <input type="text" id="username" name="username" value="<?= $userData['username'] ?>"
                                disabled>
                        </div>
                        <div class="form-group">
                            <label for="email"><?= translate('email', $i18n) ?>:</label>
                            <input type="email" id="email" name="email" value="<?= $userData['email'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="password"><?= translate('password', $i18n) ?>:</label>
                            <input type="password" id="password" name="password" <?= $demoMode ? 'disabled title="Not available on Demo Mode"' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><?= translate('confirm_password', $i18n) ?>:</label>
                            <input type="password" id="confirm_password" name="confirm_password" <?= $demoMode ? 'disabled title="Not available on Demo Mode"' : '' ?>>
                        </div>
                        <?php
                        $currencies = array();
                        $query = "SELECT * FROM currencies WHERE user_id = :userId";
                        $query = $db->prepare($query);
                        $query->bindValue(':userId', $userId, SQLITE3_INTEGER);
                        $result = $query->execute();
                        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                            $currencyId = $row['id'];
                            $currencies[$currencyId] = $row;
                        }
                        $userData['currency_symbol'] = "€";
                        ?>
                        <div class="form-group">
                            <label for="currency"><?= translate('main_currency', $i18n) ?>:</label>
                            <select id="currency" name="main_currency" placeholder="Currency">
                                <?php
                                foreach ($currencies as $currency) {
                                    $selected = "";
                                    if ($currency['id'] == $userData['main_currency']) {
                                        $selected = "selected";
                                        $userData['currency_symbol'] = $currency['symbol'];
                                    }
                                    ?>
                                    <option value="<?= $currency['id'] ?>" <?= $selected ?>><?= $currency['name'] ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="language"><?= translate('language', $i18n) ?>:</label>
                            <select id="language" name="language" placeholder="Language">
                                <?php
                                foreach ($languages as $code => $language) {
                                    $selected = ($code === $lang) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $code ?>" <?= $selected ?>><?= $language['name'] ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="buttons">
                    <input type="submit" value="<?= translate('save', $i18n) ?>" id="userSubmit"
                        class="thin mobile-grow" />
                </div>
            </div>
        </form>
    </section>

    <?php
    $sql = "SELECT login_disabled FROM admin";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $loginDisabled = $row['login_disabled'];

    $showTotpSection = true;
    if ($loginDisabled && !$userData['totp_enabled']) {
        $showTotpSection = false;
    }

    if ($showTotpSection) {
        ?>
        <section class="account-section">
            <header>
                <h2><?= translate("two_factor_authentication", $i18n) ?></h2>
            </header>
            <div class="account-2fa">
                <div class="buttons">
                    <?php
                    if (!$userData['totp_enabled']) {
                        ?>
                        <input type="button" value="<?= translate('enable_two_factor_authentication', $i18n) ?>" id="enableTotp"
                            onClick="enableTotp()" class="button thin mobile-grow"/>
                        <div class="totp-popup" id="totp-popup">
                            <header>
                                <h3><?= translate('enable_two_factor_authentication', $i18n) ?></h3>
                                <span class="fa-solid fa-xmark close-form" onclick="closeTotpPopup()"></span>
                            </header>
                            <div class="totp-popup-content">
                                <div class="totp-setup" id="totp-setup">
                                    <div class="totp-qrcode-container">
                                        <div id="totp-qr-code"></div>
                                    </div>
                                    <p class="totp-secret" id="totp-secret-code"></p>
                                    <div class="form-group-inline">
                                        <input type="hidden" name="totp-secret" id="totp-secret" value="" />
                                        <input type="text" id="totp" name="totp"
                                            placeholder="<?= translate("totp_code", $i18n) ?>" />
                                        <input type="button" value="<?= translate('enable', $i18n) ?>" id="enableTotpButton"
                                            onClick="submitTotp()" />
                                    </div>
                                </div>
                                <div class="totp-setup hide" id="totp-backup-codes">
                                    <h4><?= translate('backup_codes', $i18n) ?></h4>
                                    <ul class="totp-backup-codes" id="backup-codes"></ul>
                                    <div class="form-group-inline wrap">
                                        <input type="button" class="button secondary-button grow"
                                            value="<?= translate('copy_to_clipboard', $i18n) ?>" id="copyBackupCodes"
                                            onClick="copyBackupCodes()" />
                                        <input type="button" class="grow"
                                            value="<?= translate('download_backup_codes', $i18n) ?>" id="downloadBackupCodes"
                                            onClick="downloadBackupCodes()" />
                                    </div>
                                    <div class="settings-notes">
                                        <p>
                                            <i class="fa-solid fa-circle-info"></i>
                                            <?= translate('totp_backup_codes_info', $i18n) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else {
                        ?>
                        <input type="button" class="button secondary-button thin mobile-grow"
                            value="<?= translate('disable_two_factor_authentication', $i18n) ?>" id="disableTotp"
                            onClick="disableTotp()" />
                        <div class="totp-popup" id="totp-disable-popup">
                            <header>
                                <h3><?= translate('disable_two_factor_authentication', $i18n) ?></h3>
                                <span class="fa-solid fa-xmark close-form" onclick="closeTotpDisablePopup()"></span>
                            </header>
                            <div class="totp-popup-content">
                                <div class="form-group-inline">
                                    <input type="text" id="totp-disable" name="totp-disable" placeholder="totp" />
                                    <input type="button" value="<?= translate('disable', $i18n) ?>" id="disableTotpButton"
                                        onClick="submitDisableTotp()" />
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <div class="settings-notes">
                    <p>
                        <i class="fa-solid fa-circle-info"></i>
                        <?php
                        if (!$userData['totp_enabled']) {
                            echo translate('two_factor_info', $i18n);
                        } else {
                            echo translate('two_factor_enabled_info', $i18n);
                        }
                        ?>
                    </p>
                </div>
            </div>
        </section>
        <?php
    }

    ?>

    <section class="account-section">
        <header>
            <h2><?= translate('api_key', $i18n) ?></h2>
        </header>
        <div class="account-api-key">
            <div class="form-group-inline">
                <input type="text" id="apikey" name="apikey" value="<?= $userData['api_key'] ?>" placeholder="API Key" readonly>
                <input type="submit" value="<?= translate('regenerate', $i18n) ?>" id="regenerateApiKey" onClick="regenerateApiKey()" />
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i> <?= translate('api_key_info', $i18n) ?>
                </p>
            </div>
        </div>
    </section>

    <section class="account-section">
        <header>
            <h2><?= translate('account', $i18n) ?></h2>
        </header>
        <div class="account-list">
            <div>
                <h3><?= translate('export_subscriptions', $i18n) ?></h3>
                <div class="form-group-inline wrap">
                    <input type="button" value="<?= translate('export_as_json', $i18n) ?>" onClick="exportAsJson()"
                        class="secondary-button thin mobile-grow" id="export-json" <?= $demoMode ? 'disabled title="Not available on Demo Mode"' : '' ?>>
                    <input type="button" value="<?= translate('export_as_csv', $i18n) ?>" onClick="exportAsCsv()"
                        class="secondary-button thin mobile-grow" id="export-csv" <?= $demoMode ? 'disabled title="Not available on Demo Mode"' : '' ?>>
                </div>
            </div>
        </div>
        <div>
            <?php
            if ($userId != 1 && !$demoMode) {
                ?>
                <h3><?= translate('danger_zone', $i18n) ?></h3>
                <div class="form-group-inline">
                    <input type="button" value="<?= translate('delete_account', $i18n) ?>"
                        onClick="deleteAccount(<?= $userId ?>)" class="warning-button thin mobile-grow" id="delete-account">
                </div>
                <div class="settings-notes">
                    <p>
                        <i class="fa-solid fa-circle-info"></i>
                        <?= translate('delete_account_info', $i18n) ?>
                    </p>
                </div>
                <?php
            }
            ?>
        </div>
    </section>

   

</section>
<script src="scripts/profile.js?<?= $version ?>"></script>
<script src="scripts/theme.js?<?= $version ?>"></script>
<script src="scripts/notifications.js?<?= $version ?>"></script>

<?php
require_once 'includes/footer.php';
?>