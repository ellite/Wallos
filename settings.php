<?php
    require_once 'includes/header.php';
?>

<script src="scripts/libs/sortable.min.js"></script>
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
                                <img src="<?= $userData['avatar'] ?>" alt="avatar" class="avatar" id="avatarImg" onClick="toggleAvatarSelect()"/>
                                <span class="edit-avatar" onClick="toggleAvatarSelect()">
                                    <img src="images/siteicons/editavatar.png" title="Change avatar" />
                                </span>
                            </div>

                            <input type="hidden" name="avatar" value="<?= $userData['avatar'] ?>" id="avatarUser"/>
                            <div class="avatar-select" id="avatarSelect">
                                <div class="avatar-list">
                                    <?php foreach (scandir('images/avatars') as $index => $image) :?>
                                        <?php if (! str_starts_with($image, '.')) :?>
                                            <img src="images/avatars/<?=$image?>" alt="<?=$image?>" class="avatar-option" data-src="images/avatars/<?=$image?>"/>
                                        <?php endif ?>
                                    <?php endforeach ?>
                                    <?php foreach (scandir('images/uploads/logos/avatars') as $index => $image) :?>
                                        <?php if (! str_starts_with($image, '.')) :?>
                                            <div class="avatar-container" data-src="<?=$image?>">
                                                <img src="images/uploads/logos/avatars/<?=$image?>" alt="<?=$image?>" class="avatar-option" data-src="images/uploads/logos/avatars/<?=$image?>"/>
                                                <div class="remove-avatar" onclick="deleteAvatar('<?=$image?>')" title="Delete avatar">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </div>
                                            </div>
                                        <?php endif ?>
                                    <?php endforeach ?>
                                    <label for="profile_pic" class="add-avatar" title="<?= translate('upload_avatar', $i18n) ?>">
                                        <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                    </label>
                                </div>
                                <input type="file" id="profile_pic"class="hidden-input" name="profile_pic" accept="image/jpeg, image/png, image/gif, image/webp" onChange="successfulUpload(this, '<?= translate('file_type_error', $i18n) ?>')" />
                            </div>
                        </div>
                        <div class="grow">
                            <div class="form-group">
                                <label for="username"><?= translate('username', $i18n) ?>:</label>
                                <input type="text" id="username" name="username" value="<?= $userData['username'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email"><?= translate('email', $i18n) ?>:</label>
                                <input type="email" id="email" name="email" value="<?= $userData['email'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="password"><?= translate('password', $i18n) ?>:</label>
                                <input type="password" id="password" name="password">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password"><?= translate('confirm_password', $i18n) ?>:</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                            <?php
                                $currencies = array();
                                $query = "SELECT * FROM currencies";
                                $result = $db->query($query);
                                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                    $currencyId = $row['id'];
                                    $currencies[$currencyId] = $row;
                                }
                            ?>
                            <div class="form-group">
                                <label for="currency"><?= translate('main_currency', $i18n) ?>:</label>
                                <select id="currency" name="main_currency" placeholder="Currency">
                                <?php
                                    foreach ($currencies as $currency) {
                                        $selected = ($currency['id'] == $userData['main_currency']) ? 'selected' : '';    
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
                                    foreach ($languages as $code => $name) {
                                        $selected = ($code === $lang) ? 'selected' : '';
                                ?>
                                        <option value="<?= $code ?>" <?= $selected ?>><?= $name ?></option>
                                <?php
                                    }
                                ?>
                                </select>
                            </div>
                        </div>  
                    </div>
                    <div class="buttons">
                        <input type="submit" value="<?= translate('save', $i18n) ?>" id="userSubmit" class="thin"/>
                    </div>
                </div>
            </form>

    </section>

    <?php
        $sql = "SELECT * FROM household";
        $result = $db->query($sql);

        if ($result) {
            $household = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $household[] = $row;
            }
        }
    ?>

    <section class="account-section">
        <header>
            <h2><?= translate('household', $i18n) ?></h2>
        </header>
        <div class="account-members">
            <div  id="householdMembers">
            <?php
                foreach ($household as $member) {
                    ?>
                    <div class="form-group-inline" data-memberid="<?= $member['id'] ?>">
                        <input type="text" name="member" value="<?= $member['name'] ?>" placeholder="Member">
                        <?php
                            if ($member['id'] !== 1) {
                        ?>
                            <input type="text" name="email" value="<?= $member['email'] ?? "" ?>" placeholder="<?= translate("email", $i18n) ?>">
                        <?php
                            }
                        ?>
                        <button class="image-button medium"  onClick="editMember(<?= $member['id'] ?>)" name="save">
                            <img src="images/siteicons/<?= $colorTheme ?>/save.png" title="<?= translate('save_member', $i18n) ?>">
                        </button>
                        <?php
                            if ($member['id'] != 1) {
                                ?>
                                    <button class="image-button medium" onClick="removeMember(<?= $member['id'] ?>)">
                                        <img src="images/siteicons/<?= $colorTheme ?>/delete.png" title="<?= translate('delete_member', $i18n) ?>">
                                    </button>
                                <?php
                            } else {
                                ?>
                                    <button class="image-button medium disabled">
                                        <img src="images/siteicons/<?= $colorTheme ?>/delete.png" title="<?= translate('cant_delete_member', $i18n) ?>">
                                    </button>
                                <?php
                            }
                        ?>
                    </div>
                    <?php
                }
            ?>
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i> <?= translate('household_info', $i18n) ?></p>
                <p>
            </div>
            <div class="buttons">
                <input type="submit" value="<?= translate('add', $i18n) ?>" id="addMember" onClick="addMemberButton()" class="thin"/>
            </div>
        </div>
    </section>

    <?php
        // Notification settings
        $sql = "SELECT * FROM notification_settings LIMIT 1";
        $result = $db->query($sql);

        $rowCount = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notifications = $row;
            $rowCount++;
        }
        
        if ($rowCount == 0) {
            $notifications['days'] = 1;
        }

        // Email notifications
        $sql = "SELECT * FROM email_notifications LIMIT 1";
        $result = $db->query($sql);

        $rowCount = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notificationsEmail['enabled'] = $row['enabled'];
            $notificationsEmail['smtp_address'] = $row['smtp_address'];
            $notificationsEmail['smtp_port'] = $row['smtp_port'];
            $notificationsEmail['encryption'] = $row['encryption'];
            $notificationsEmail['smtp_username'] = $row['smtp_username'];
            $notificationsEmail['smtp_password'] = $row['smtp_password'];
            $notificationsEmail['from_email'] = $row['from_email'];
            $rowCount++;
        }

        if ($rowCount == 0) {
            $notificationsEmail['enabled'] = 0;
            $notificationsEmail['smtp_address'] = "";
            $notificationsEmail['smtp_port'] = 587;
            $notificationsEmail['encryption'] = "tls";
            $notificationsEmail['smtp_username'] = "";
            $notificationsEmail['smtp_password'] = "";
            $notificationsEmail['from_email'] = "";
        }

        // Discord notifications
        $sql = "SELECT * FROM discord_notifications LIMIT 1";
        $result = $db->query($sql);
        
        $rowCount = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notificationsDiscord['enabled'] = $row['enabled'];
            $notificationsDiscord['webhook_url'] = $row['webhook_url'];
            $notificationsDiscord['bot_username'] = $row['bot_username'];
            $notificationsDiscord['bot_avatar'] = $row['bot_avatar_url'];
            $rowCount++;
        }

        if ($rowCount == 0) {
        $notificationsDiscord['enabled'] = 0;
        $notificationsDiscord['webhook_url'] = "";
        $notificationsDiscord['bot_username'] = "";
        $notificationsDiscord['bot_avatar'] = "";
        }

        // Pushover notifications
        $sql = "SELECT * FROM pushover_notifications LIMIT 1";
        $result = $db->query($sql);
        
        $rowCount = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notificationsPushover['enabled'] = $row['enabled'];
            $notificationsPushover['token'] = $row['token'];
            $notificationsPushover['user_key'] = $row['user_key'];
            $rowCount++;
        }

        if ($rowCount == 0) {
            $notificationsPushover['enabled'] = 0;
            $notificationsPushover['token'] = "";
            $notificationsPushover['user_key'] = "";
        }

        // Telegram notifications
        $sql = "SELECT * FROM telegram_notifications LIMIT 1";
        $result = $db->query($sql);
        
        $rowCount = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notificationsTelegram['enabled'] = $row['enabled'];
            $notificationsTelegram['bot_token'] = $row['bot_token'];
            $notificationsTelegram['chat_id'] = $row['chat_id'];
            $rowCount++;
        }

        if ($rowCount == 0) {
            $notificationsTelegram['enabled'] = 0;
            $notificationsTelegram['bot_token'] = "";
            $notificationsTelegram['chat_id'] = "";
        }

        // Webhook notifications
        $sql = "SELECT * FROM webhook_notifications LIMIT 1";
        $result = $db->query($sql);

        $rowCount = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notificationsWebhook['enabled'] = $row['enabled'];
            $notificationsWebhook['url'] = $row['url'];
            $notificationsWebhook['request_method'] = $row['request_method'];
            $notificationsWebhook['headers'] = $row['headers'];
            $notificationsWebhook['payload'] = $row['payload'];
            $notificationsWebhook['iterator'] = $row['iterator'];
            $rowCount++;
        }

        if ($rowCount == 0) {
            $notificationsWebhook['enabled'] = 0;
            $notificationsWebhook['url'] = "";
            $notificationsWebhook['request_method'] = "POST";
            $notificationsWebhook['headers'] = "";
            $notificationsWebhook['iterator'] = "";
            $notificationsWebhook['payload'] = '
{
    "days_until": "{{days_until}}",
    "{{subscriptions}}": [
        {
            "name": "{{subscription_name}}",
            "price": "{{subscription_price}}",
            "currency": "{{subscription_currency}}",
            "category": "{{subscription_category}}",
            "date": "{{subscription_date}}",
            "payer": "{{subscription_payer}}"
        }
    ]

}';
        }

        // Gotify notifications
        $sql = "SELECT * FROM gotify_notifications LIMIT 1";
        $result = $db->query($sql);

        $rowCount = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notificationsGotify['enabled'] = $row['enabled'];
            $notificationsGotify['url'] = $row['url'];
            $notificationsGotify['token'] = $row['token'];
            $rowCount++;
        }

        if ($rowCount == 0) {
            $notificationsGotify['enabled'] = 0;
            $notificationsGotify['url'] = "";
            $notificationsGotify['token'] = "";
        }

    ?>

    <section class="account-section">
        <header>
            <h2><?= translate('notifications', $i18n) ?></h2>
        </header>
        <div class="account-notifications">
            <section>
                <label for="days"><?= translate('notify_me', $i18n) ?>:</label>
                <div class="form-group-inline">
                    <select name="days" id="days">
                    <?php
                        for ($i = 1; $i <= 7; $i++) {
                            $dayText = $i > 1 ? translate('days_before', $i18n) : translate('day_before', $i18n);
                            $selected = $i == $notifications['days'] ? "selected" : "";
                            ?>
                                <option value="<?= $i ?>" <?= $selected ?>>
                                    <?= $i ?> <?= $dayText ?>
                                </option>
                            <?php
                        }
                    ?>
                    </select>
                    <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveNotifications" onClick="saveNotifications()"/>
                </div>
            </section>
            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('email')">
                    <h3>
                        <i class="fa-solid fa-envelope"></i>
                        <?= translate('email', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="email">
                    <div class="form-group-inline">
                        <input type="checkbox" id="emailenabled" name="emailenabled" <?= $notificationsEmail['enabled'] ? "checked" : "" ?>>
                        <label for="emailenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="smtpaddress" id="smtpaddress" placeholder="<?= translate('smtp_address', $i18n) ?>" value="<?= $notificationsEmail['smtp_address'] ?>" />
                        <input type="text" name="smtpport" id="smtpport" placeholder="<?= translate('port', $i18n) ?>" class="one-third"  value="<?= $notificationsEmail['smtp_port'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="radio" name="encryption" id="encryptiontls" value="tls" <?= $notificationsEmail['encryption'] == "tls" ? "checked" : "" ?> />
                        <label for="encryptiontls"><?= translate('tls', $i18n) ?></label>
                        <input type="radio" name="encryption" id="encryptionssl" value="ssl" <?= $notificationsEmail['encryption'] == "ssl" ? "checked" : "" ?> />
                        <label for="encryptionssl"><?= translate('ssl', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="smtpusername" id="smtpusername" placeholder="<?= translate('smtp_username', $i18n) ?>"  value="<?= $notificationsEmail['smtp_username'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="password" name="smtppassword" id="smtppassword" placeholder="<?= translate('smtp_password', $i18n) ?>"  value="<?= $notificationsEmail['smtp_password'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="fromemail" id="fromemail" placeholder="<?= translate('from_email', $i18n) ?>"  value="<?= $notificationsEmail['from_email'] ?>" />
                    </div>
                    <div class="settings-notes">
                        <p>
                            <i class="fa-solid fa-circle-info"></i> <?= translate('smtp_info', $i18n) ?></p>
                        <p>
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin" value="<?= translate('test', $i18n) ?>" id="testNotificationsEmail" onClick="testNotificationEmailButton()"/>
                        <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveNotificationsEmail" onClick="saveNotificationsEmailButton()"/>
                    </div>
                </div>
            </section>
            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('discord');">
                    <h3>
                        <i class="fa-brands fa-discord"></i>
                        <?= translate('discord', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="discord">
                    <div class="form-group-inline">
                        <input type="checkbox" id="discordenabled" name="discordenabled" <?= $notificationsDiscord['enabled'] ? "checked" : "" ?>>
                        <label for="discordenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="discordurl" id="discordurl" placeholder="<?= translate('webhook_url', $i18n) ?>"  value="<?= $notificationsDiscord['webhook_url'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="discordbotusername" id="discordbotusername" placeholder="<?= translate('discord_bot_username', $i18n) ?>"  value="<?= $notificationsDiscord['bot_username'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="discordbotavatar" id="discordbotavatar" placeholder="<?= translate('discord_bot_avatar_url', $i18n) ?>"  value="<?= $notificationsDiscord['bot_avatar'] ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin" value="<?= translate('test', $i18n) ?>" id="testNotificationsDiscord" onClick="testNotificationsDiscordButton()"/>
                        <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveNotificationsDiscord" onClick="saveNotificationsDiscordButton()"/>
                    </div>
                </div>
            </section>
            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('gotify');">
                    <h3>
                        <i class="fa-solid fa-envelopes-bulk"></i>
                        <?= translate('gotify', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="gotify">
                    <div class="form-group-inline">
                        <input type="checkbox" id="gotifyenabled" name="gotifyenabled" <?= $notificationsGotify['enabled'] ? "checked" : "" ?>>
                        <label for="gotifyenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="gotifyurl" id="gotifyurl" placeholder="<?= translate('url', $i18n) ?>"  value="<?= $notificationsGotify['url'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="gotifytoken" id="gotifytoken" placeholder="<?= translate('token', $i18n) ?>"  value="<?= $notificationsGotify['token'] ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin" value="<?= translate('test', $i18n) ?>" id="testNotificationsGotify" onClick="testNotificationsGotifyButton()"/>
                        <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveNotificationsGotify" onClick="saveNotificationsGotifyButton()"/>
                    </div>
                </div>
            </section>
            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('pushover');">
                    <h3>
                        <i class="fa-brands fa-pinterest-p"></i>
                        <?= translate('pushover', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="pushover">
                    <div class="form-group-inline">
                        <input type="checkbox" id="pushoverenabled" name="pushoverenabled" <?= $notificationsPushover['enabled'] ? "checked" : "" ?>>
                        <label for="pushoverenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="pushoveruserkey" id="pushoveruserkey" placeholder="<?= translate('pushover_user_key', $i18n) ?>"  value="<?= $notificationsPushover['user_key'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="pushovertoken" id="pushovertoken" placeholder="<?= translate('token', $i18n) ?>"  value="<?= $notificationsPushover['token'] ?>" />
                    </div>

                    <div class="buttons">
                        <input type="button" class="secondary-button thin" value="<?= translate('test', $i18n) ?>" id="testNotificationsPushover" onClick="testNotificationsPushoverButton()"/>
                        <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveNotificationsPushover" onClick="saveNotificationsPushoverButton()"/>
                    </div>
                </div>
            </section>
            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('telegram');">
                    <h3>
                        <i class="fa-solid fa-paper-plane"></i>
                        <?= translate('telegram', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="telegram">
                    <div class="form-group-inline">
                        <input type="checkbox" id="telegramenabled" name="telegramenabled" <?= $notificationsTelegram['enabled'] ? "checked" : "" ?>>
                        <label for="telegramenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="telegrambottoken" id="telegrambottoken" placeholder="<?= translate('telegram_bot_token', $i18n) ?>"  value="<?= $notificationsTelegram['bot_token'] ? $notificationsTelegram['bot_token']  : "" ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="telegramchatid" id="telegramchatid" placeholder="<?= translate('telegram_chat_id', $i18n) ?>"  value="<?= $notificationsTelegram['chat_id'] ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin" value="<?= translate('test', $i18n) ?>" id="testNotificationsTelegram" onClick="testNotificationsTelegramButton()"/>
                        <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveNotificationsTelegram" onClick="saveNotificationsTelegramButton()"/>
                    </div>
                </div>
            </section>
            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('webhook');">
                    <h3>
                        <i class="fa-solid fa-bolt"></i>
                        <?= translate('webhook', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="webhook">
                    <div class="form-group-inline">
                        <input type="checkbox" id="webhookenabled" name="webhookenabled" <?= $notificationsWebhook['enabled'] ? "checked" : "" ?>>
                        <label for="webhookenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div>
                        <label for="webhookrequestmethod" class="capitalize"><?= translate('request_method', $i18n) ?>:</label>
                        <div class="form-group-inline">
                            <select name="webhookrequestmethod" id="webhookrequestmethod">
                                <option value="GET" <?= $notificationsWebhook['request_method'] == 'GET' ? 'selected' : '' ?>>GET</option>
                                <option value="POST" <?= $notificationsWebhook['request_method'] == 'POST' ? 'selected' : '' ?>>POST</option>
                                <option value="PUT" <?= $notificationsWebhook['request_method'] == 'PUT' ? 'selected' : '' ?>>PUT</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="webhookurl" id="webhookurl" placeholder="<?= translate('webhook_url', $i18n) ?>"  value="<?= $notificationsWebhook['url'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <textarea class="thin" name="webhookcustomheaders" id="webhookcustomheaders" placeholder="<?= translate('custom_headers', $i18n) ?>"><?= $notificationsWebhook['headers'] ?></textarea>
                    </div>
                    <div class="form-group-inline">
                        <textarea name="webhookpayload" id="webhookpayload" placeholder="<?= translate('webhook_payload', $i18n) ?>"><?= $notificationsWebhook['payload'] ?></textarea>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="webhookiteratorkey" id="webhookiteratorkey" placeholder="<?= translate('webhook_iterator_key', $i18n) ?>"  value="<?= $notificationsWebhook['iterator'] ?>" />
                    </div>
                    <div class="settings-notes">
                        <p>
                            <i class="fa-solid fa-circle-info"></i> <?= translate('variables_available', $i18n)  ?>: {{days_until}}, {{subscription_name}}, {{subscription_price}}, {{subscription_currency}}, {{subscription_category}}, {{subscription_date}}, {{subscription_payer}}</p>
                        <p>
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin" value="<?= translate('test', $i18n) ?>" id="testNotificationsWebhook" onClick="testNotificationsWebhookButton()"/>
                        <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveNotificationsWebhook" onClick="saveNotificationsWebhookButton()"/>
                    </div>
                </div>
            </section>
        </div>
    </section>

    <?php
        $sql = "SELECT * FROM categories ORDER BY `order` ASC";
        $result = $db->query($sql);

        if ($result) {
            $categories = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $categories[] = $row;
            }
        }
    ?>

    <section class="account-section">
        <header>
            <h2><?= translate('categories', $i18n) ?></h2>
        </header>
        <div class="account-categories">
            <div  id="categories" class="sortable-list">
            <?php
                foreach ($categories as $category) {
                    if ($category['id'] != 1) {
                        $canDelete = true;

                        $query = "SELECT COUNT(*) as count FROM subscriptions WHERE category_id = :categoryId";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':categoryId', $category['id'], SQLITE3_INTEGER);
                        $result = $stmt->execute();
                        $row = $result->fetchArray(SQLITE3_ASSOC);
                        $isUsed = $row['count'];

                        if ($isUsed > 0) {
                            $canDelete = false;
                        }
                    ?>
                    <div class="form-group-inline" data-categoryid="<?= $category['id'] ?>">
                        <div class="drag-icon"></div>
                        <input type="text" name="category" value="<?= $category['name'] ?>" placeholder="Category">
                        <button class="image-button medium"  onClick="editCategory(<?= $category['id'] ?>)" name="save">
                            <img src="images/siteicons/<?= $colorTheme ?>/save.png" title="<?= translate('save_category', $i18n) ?>">
                        </button>
                        <?php
                            if ($canDelete) {
                            ?>
                                <button class="image-button medium" onClick="removeCategory(<?= $category['id'] ?>)">
                                    <img src="images/siteicons/<?= $colorTheme ?>/delete.png" title="<?= translate('delete_category', $i18n) ?>">
                                </button>
                            <?php
                            } else {
                            ?>
                                <button class="image-button medium disabled">
                                    <img src="images/siteicons/<?= $colorTheme ?>/delete.png" title="<?= translate('cant_delete_category_in_use', $i18n) ?>">
                                </button>
                            <?php
                            }
                        ?>
                    </div>
                    <?php
                    }
                }
            ?>
            </div>
            <div class="buttons">
                <input type="submit" value="<?= translate('add', $i18n) ?>" id="addCategory" onClick="addCategoryButton()" class="thin"/>
            </div>
        </div>
    </section>

    <?php
        $sql = "SELECT * FROM currencies";
        $result = $db->query($sql);

        if ($result) {
            $currencies = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $currencies[] = $row;
            }
        }

        $query = "SELECT main_currency FROM user WHERE id = 1";
        $stmt = $db->prepare($query);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $mainCurrencyId = $row['main_currency'];

        $query = "SELECT date FROM last_exchange_update";
        $exchange_rates_last_updated = $db->querySingle($query);

    ?>

    <section class="account-section">
        <header>
            <h2><?= translate('currencies', $i18n) ?></h2>
        </header>
        <div class="account-currencies">
            <div id="currencies">
            <?php
                foreach ($currencies as $currency) {
                    $canDelete = true;
                    $isMainCurrency = false;
                    if ($currency['id'] === $mainCurrencyId) {
                        $canDelete = false;
                        $isMainCurrency = true;
                    } else {
                        $query = "SELECT COUNT(*) as count FROM subscriptions WHERE currency_id = :currencyId";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':currencyId', $currency['id'], SQLITE3_INTEGER);
                        $result = $stmt->execute();
                        $row = $result->fetchArray(SQLITE3_ASSOC);
                        $isUsed = $row['count'];

                        if ($isUsed > 0) {
                            $canDelete = false;
                        }
                    }
                    ?>

                    <div class="form-group-inline" data-currencyid="<?= $currency['id'] ?>">
                        <input type="text" class="short" name="symbol" value="<?= $currency['symbol'] ?>" placeholder="$">
                        <input type="text" name="currency" value="<?= $currency['name'] ?>" placeholder="Currency Name">
                        <input type="text" name="code" value="<?= $currency['code'] ?>" placeholder="Currency Code" <?= !$canDelete ? 'disabled' : '' ?>>
                        <button class="image-button medium"  onClick="editCurrency(<?= $currency['id'] ?>)" name="save">
                            <img src="images/siteicons/<?= $colorTheme ?>/save.png" title="<?= translate('save_currency', $i18n) ?>">
                        </button>
                        <?php
                            if ($canDelete) {
                            ?>
                                <button class="image-button medium" onClick="removeCurrency(<?= $currency['id'] ?>)">
                                    <img src="images/siteicons/<?= $colorTheme ?>/delete.png" title="<?= translate('delete_currency', $i18n) ?>">
                                </button>
                            <?php
                            } else {
                                $cantDeleteMessage = $isMainCurrency ? translate('cant_delete_main_currency', $i18n) : translate('cant_delete_currency_in_use', $i18n);
                            ?>
                                <button class="image-button medium disabled">
                                    <img src="images/siteicons/<?= $colorTheme ?>/delete.png" title="<?= $cantDeleteMessage ?>">
                                </button>
                            <?php
                            }
                        ?>
                        
                    </div>
                    <?php
                }
            ?>
            </div>
            <div class="buttons">
                <input type="submit" value="<?= translate('add', $i18n) ?>" id="addCurrency" onClick="addCurrencyButton()" class="thin"/>
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    <?= translate('exchange_update', $i18n) ?>
                    <span>
                        <?= $exchange_rates_last_updated ?>
                    </span>
                </p>
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    <?= translate('currency_info', $i18n) ?>
                    <span>
                        fixer.io 
                        <a href="https://fixer.io/symbols" target="_blank" title="Currency codes">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </span>
                </p>
                <p>
                    <?= translate('currency_performance', $i18n) ?>
                </p>
            </div>
        </div>
    </section>

    <?php
        $apiKey = "";
        $sql = "SELECT api_key, provider FROM fixer";
        $result = $db->query($sql);
        if ($result) {
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row) {
                $apiKey = $row['api_key'];
                $provider = $row['provider'];
            } else {
                $provider = 0;
            }
        }
    ?>

    <section class="account-section">
        <header>
            <h2>Fixer API Key</h2>
        </header>
        <div class="account-fixer">
            <div class="form-group">
                <input type="text" name="fixer-key" id="fixerKey" value="<?= $apiKey ?>" placeholder="<?= translate('api_key', $i18n) ?>">
            </div>
            <div class="form-group">
                 <label for="fixerProvider"><?= translate('provider', $i18n) ?>:</label>
                <select name="fixer-provider" id="fixerProvider">
                    <option value="0" <?= $provider == 0 ? 'selected' : '' ?>>fixer.io</option>
                    <option value="1" <?= $provider == 1 ? 'selected' : '' ?>>apilayer.com</option>
                </select>
            </div>
            <div class="settings-notes">
                <p><i class="fa-solid fa-circle-info"></i><?= translate('fixer_info', $i18n) ?></p>
                <p><?= translate('get_key', $i18n) ?>: 
                    <span>
                        https://fixer.io/ 
                        <a href="https://fixer.io/#pricing_plan" title="<?= translate("get_free_fixer_api_key", $i18n) ?>" target="_blank">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </span>
                </p>
                <p>
                    <?= translate("get_key_alternative", $i18n) ?>
                    <span>
                        https://apilayer.com
                        <a href="https://apilayer.com/marketplace/fixer-api" title="Get free fixer api key" target="_blank">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </span>
                </p>
            </div>
            <div class="buttons">
                <input type="submit" value="<?= translate('save', $i18n) ?>" id="addFixerKey" onClick="addFixerKeyButton()" class="thin"/>
            </div>
        </div>
    </section>

    <?php
        $sql = "SELECT * FROM payment_methods ORDER BY `order` ASC";
        $result = $db->query($sql);

        if ($result) {
            $payments = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $payments[] = $row;
            }
        }
    ?>

    <section class="account-section">
        <header>
            <h2><?= translate('payment_methods', $i18n) ?></h2>
        </header>
        <div class="payments-list" id="payments-list">
            <?php
                $paymentsInUseQuery = $db->query('SELECT id FROM payment_methods WHERE id IN (SELECT DISTINCT payment_method_id FROM subscriptions)');
                $paymentsInUse = [];
                while ($row = $paymentsInUseQuery->fetchArray(SQLITE3_ASSOC)) {
                    $paymentsInUse[] = $row['id'];
                }

                foreach ($payments as $payment) {
                    $paymentIconFolder = $payment['id'] <= 31 ? 'images/uploads/icons/' : 'images/uploads/logos/';
                    $inUse = in_array($payment['id'], $paymentsInUse);
                    ?>
                        <div class="payments-payment"
                             data-enabled="<?= $payment['enabled']; ?>"
                             data-in-use="<?= $inUse ? 'yes' : 'no' ?>"
                             data-paymentid="<?= $payment['id'] ?>"
                             title="<?= $inUse ? translate('cant_delete_payment_method_in_use', $i18n) : ($payment['enabled'] ? translate('disable', $i18n) : translate('enable', $i18n)) ?>">
                            <div class="drag-icon" title=""></div>
                            <img src="<?= $paymentIconFolder.$payment['icon'] ?>"  alt="Logo" />
                            <span class="payment-name" contenteditable="true" title="<?= translate("rename_payment_method", $i18n) ?>"><?= $payment['name'] ?></span>
                            <?php
                                if (!$inUse) {
                                    ?>
                                        <div class="delete-payment-method" title="<?= translate('delete', $i18n) ?>" data-paymentid="<?= $payment['id'] ?>">x</div>
                                    <?php
                                } 
                            ?>
                        </div>
                    <?php
                } 
            ?>
        </div>
        <div class="settings-notes">
            <p>
                <i class="fa-solid fa-circle-info"></i>
                <?= translate('payment_methods_info', $i18n) ?>
            </p>
            <p>
                <i class="fa-solid fa-circle-info"></i>
                <?= translate('rename_payment_methods_info', $i18n) ?>
            </p>
        </div>
        <header>
            <h2 class="second-header"><?= translate("add_custom_payment", $i18n) ?></h2>
        </header>
        <div>
            <form id="payments-form">
                <div class="form-group-inline">
                    <input type="text" name="paymentname" id="paymentname" placeholder="<?= translate('payment_method_name', $i18n) ?>"  onchange="setSearchButtonStatus()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange();"/>
                    <label for="paymenticon" class="icon-preview">
                        <img src="" alt="<?= translate('logo_preview', $i18n) ?>" id="form-icon"> 
                    </label>
                    <div class="form-icon-search">
                        <input type="file" id="paymenticon" name="paymenticon" accept="image/jpeg, image/png, image/gif, image/webp" onchange="handleFileSelect(event)" class="hidden-input">
                        <input type="hidden" id="icon-url" name="icon-url">
                        <div id="icon-search-button" class="image-button medium disabled" title="<?= translate('search_logo', $i18n) ?>" onClick="searchPaymentIcon()">
                            <img src="images/siteicons/<?= $colorTheme ?>/websearch.png">
                        </div>
                        <div id="icon-search-results" class="icon-search">
                            <header>
                                <span class="fa-solid fa-xmark close-icon-search" onClick="closeIconSearch()"></span>
                            </header>
                            <div id="icon-search-images"></div>
                        </div>
                    </div>
                    
                    <input type="button" class="button thin" id="add-payment-button" value="+" title="<?= translate('add', $i18n) ?>" id="addPayment" onClick="addPaymentMethod()"/>
                </div>
            </form>    
        </div>
    </section>

    <section class="account-section">
        <header>
            <h2><?= translate('theme_settings', $i18n) ?></h2>
        </header>
        <div class="account-settings-theme">
        <div>
                <div class="theme-selector">
                    <div class="theme">
                        <label for="theme-blue" class="theme-preview blue <?= $settings['color_theme'] == 'blue' ? 'is-selected' : '' ?>">
                            <input type="radio" name="theme" id="theme-blue" value="blue" onClick="setTheme('blue')" <?= $settings['color_theme'] == 'blue' ? 'checked' : '' ?>>
                            <span class="main-color"></span>
                            <span class="accent-color"></span>
                            <span class="hover-color"></span>
                        </label>
                    </div>
                    <div class="theme">
                        <label for="theme-green" class="theme-preview green <?= $settings['color_theme'] == 'green' ? 'is-selected' : '' ?>">
                            <input type="radio" name="theme" id="theme-green" value="green" onClick="setTheme('green')" <?= $settings['color_theme'] == 'green' ? 'checked' : '' ?>>
                            <span class="main-color"></span>
                            <span class="accent-color"></span>
                            <span class="hover-color"></span>
                        </label>
                    </div>
                    <div class="theme">
                        <label for="theme-red" class="theme-preview red <?= $settings['color_theme'] == 'red' ? 'is-selected' : '' ?>">
                            <input type="radio" name="theme" id="theme-red" value="red" onClick="setTheme('red')" <?= $settings['color_theme'] == 'red' ? 'checked' : '' ?>>
                            <span class="main-color"></span>
                            <span class="accent-color"></span>
                            <span class="hover-color"></span>
                        </label>
                    </div>
                    <div class="theme">
                        <label for="theme-yellow" class="theme-preview yellow <?= $settings['color_theme'] == 'yellow' ? 'is-selected' : '' ?>">
                            <input type="radio" name="theme" id="theme-yellow" value="yellow" onClick="setTheme('yellow')" <?= $settings['color_theme'] == 'yellow' ? 'checked' : '' ?>>
                            <span class="main-color"></span>
                            <span class="accent-color"></span>
                            <span class="hover-color"></span>
                        </label>            
                    </div>
                </div>
            </div>
            <div>
                <h2><?= translate('custom_colors', $i18n) ?></h2>
                <div class="form-group-inline wrap">
                    <div class="color-picker-wrapper wrap">
                        <input type="color" id="mainColor" name="mainColor" value="<?= isset($settings['customColors']['main_color']) ? $settings['customColors']['main_color'] : '#FFFFFF' ?>" class="color-picker fa-solid fa-eye-dropper">
                        <input type="color" id="accentColor" name="accentColor" value="<?= isset($settings['customColors']['accent_color']) ? $settings['customColors']['accent_color'] : '#FFFFFF' ?>" class="color-picker fa-solid fa-eye-dropper">
                        <input type="color" id="hoverColor" name="hoverColor" value="<?= isset($settings['customColors']['hover_color']) ? $settings['customColors']['hover_color'] : '#FFFFFF' ?>" class="color-picker fa-solid fa-eye-dropper">
                    </div>
                    <div class="color-picker-wrapper wrap">
                        <input type="button" value="<?= translate('reset', $i18n) ?>" onClick="resetCustomColors()" class="secondary-button thin" id="reset-colors">
                        <input type="button" value="<?= translate('save', $i18n) ?>" onClick="saveCustomColors()" class="buton thin" id="save-colors">
                    </div>    
                </div>
            </div>
            <h2><?= translate('dark_theme', $i18n) ?></h2>
            <div>
                <input id="switchTheme" type="button" value="<?= translate('switch_theme', $i18n) ?>" onClick="switchTheme()" class="button thin">
            </div>
        </div>
    </section>

    <section class="account-section">
        <header>
            <h2><?= translate('display_settings', $i18n) ?></h2>
        </header>
        <div class="account-settings-list">
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="monthlyprice" name="monthlyprice" onChange="setShowMonthlyPrice()" <?php if ($settings['monthly_price']) echo 'checked'; ?>>
                    <label for="monthlyprice"><?= translate('calculate_monthly_price', $i18n) ?></label>
                </div>
            </div>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="convertcurrency" name="convertcurrency" onChange="setConvertCurrency()" <?php if ($settings['convert_currency']) echo 'checked'; ?>>
                    <label for="convertcurrency"><?= translate('convert_prices', $i18n) ?></label>
                </div>
            </div>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="hidedisabled" name="hidedisabled" onChange="setHideDisabled()" <?php if ($settings['hide_disabled']) echo 'checked'; ?>>
                    <label for="hidedisabled"><?= translate('hide_disabled_subscriptions', $i18n) ?></label>
                </div>
            </div>
        </div>
    </section>    

    <section class="account-section">
        <header>
            <h2><?= translate('experimental_settings', $i18n) ?></h2>
        </header>
        <div class="account-settings-list">
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="removebackground" name="removebackground" onChange="setRemoveBackground()" <?php if ($settings['remove_background']) echo 'checked'; ?>>
                    <label for="removebackground"><?= translate('remove_background', $i18n) ?></label>
                </div>
            </div>
        </div>
        <div class="settings-notes">
            <p>
                <i class="fa-solid fa-circle-info"></i>
                <?= translate('experimental_info', $i18n) ?>
            </p>
        </div>
    </section>

    <section class="account-section">
        <header>
            <h2><?= translate('backup_and_restore', $i18n) ?></h2>
        </header>
        <div class="form-group-inline">
            <div>
                <input type="button" class="button thin" value="<?= translate('backup', $i18n) ?>" id="backupDB" onClick="backupDB()"/>
            </div>     
            <div>
                <input type="button" class="secondary-button thin" value="<?= translate('restore', $i18n) ?>" id="restoreDB" onClick="openRestoreDBFileSelect()" />    
                <input type="file" name="restoreDBFile" id="restoreDBFile" style="display: none;" onChange="restoreDB()" accept=".zip">
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
<script src="scripts/settings.js?<?= $version ?>"></script>
<script src="scripts/notifications.js?<?= $version ?>"></script>

<?php
    require_once 'includes/footer.php';
?>
