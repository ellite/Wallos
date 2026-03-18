<?php
require_once 'includes/header.php';

$currencies = array();
$query = "SELECT * FROM currencies WHERE user_id = :userId";
$query = $db->prepare($query);
$query->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $query->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $currencyId = $row['id'];
    $currencies[$currencyId] = $row;
}
$userData['currency_symbol'] = $currencies[$main_currency]['symbol'];

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
            <h2><?= translate('monthly_budget', $i18n) ?></h2>
        </header>
        <div class="account-budget">
            <div class="form-group-inline">
                <label for="budget"><?= $userData['currency_symbol'] ?></label>
                <input type="number" id="budget" name="budget" autocomplete="off" value="<?= $userData['budget'] ?>"
                    placeholder="Budget">
                <input type="submit" value="<?= translate('save', $i18n) ?>" id="saveBudget" onClick="saveBudget()" />
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i> <?= translate('budget_info', $i18n) ?>
                </p>
            </div>
        </div>
    </section>

    <?php
    $sql = "SELECT * FROM household WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

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
            <div id="householdMembers">
                <?php
                foreach ($household as $index => $member) {
                    ?>
                    <div class="form-group-inline" data-memberid="<?= $member['id'] ?>">
                        <input type="text" name="member" autocomplete="off" value="<?= $member['name'] ?>"
                            placeholder="Member">
                        <?php
                        if ($index !== 0) {
                            ?>
                            <input type="text" name="email" autocomplete="off" value="<?= $member['email'] ?? "" ?>"
                                placeholder="<?= translate("email", $i18n) ?>">
                            <?php
                        }
                        ?>
                        <button class="image-button medium" onClick="editMember(<?= $member['id'] ?>)" name="save"
                            title="<?= translate('save_member', $i18n) ?>">
                            <?php include "images/siteicons/svg/save.php"; ?>
                        </button>
                        <?php
                        if ($index !== 0) {
                            ?>
                            <button class="image-button medium" onClick="removeMember(<?= $member['id'] ?>)"
                                title="<?= translate('delete_member', $i18n) ?>">
                                <?php include "images/siteicons/svg/delete.php"; ?>
                            </button>
                            <?php
                        } else {
                            ?>
                            <button class="image-button medium disabled" title="<?= translate('cant_delete_member', $i18n) ?>">
                                <?php include "images/siteicons/svg/delete.php"; ?>
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
                <input type="submit" value="<?= translate('add', $i18n) ?>" id="addMember" onClick="addMemberButton()"
                    class="thin mobile-grow" />
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i> <?= translate('household_info', $i18n) ?>
                </p>
                <p>
            </div>
        </div>
    </section>

    <?php
    // Notification settings
    $sql = "SELECT * FROM notification_settings WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rowCount = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notifications = $row;
        $rowCount++;
    }

    if ($rowCount == 0) {
        $notifications['days'] = 1;
    }

    // Email notifications
    $sql = "SELECT * FROM email_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rowCount = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notificationsEmail['enabled'] = $row['enabled'];
        $notificationsEmail['smtp_address'] = $row['smtp_address'];
        $notificationsEmail['smtp_port'] = $row['smtp_port'];
        $notificationsEmail['encryption'] = $row['encryption'];
        $notificationsEmail['smtp_username'] = $row['smtp_username'];
        $notificationsEmail['smtp_password'] = $row['smtp_password'];
        $notificationsEmail['from_email'] = $row['from_email'];
        $notificationsEmail['other_emails'] = $row['other_emails'];
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
        $notificationsEmail['other_emails'] = "";
    }

    // Discord notifications
    $sql = "SELECT * FROM discord_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

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
    $sql = "SELECT * FROM pushover_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

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
    $sql = "SELECT * FROM telegram_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

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


    // PushPlus notifications
    $sql = "SELECT * FROM pushplus_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rowCount = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notificationsPushPlus['enabled'] = $row['enabled'];
        $notificationsPushPlus['token'] = $row['token'];
        $rowCount++;
    }

    if ($rowCount == 0) {
        $notificationsPushPlus['enabled'] = 0;
        $notificationsPushPlus['token'] = "";
    }

    // Mattermost notifications
    $sql = "SELECT * FROM mattermost_notifications WHERE user_id = :userID LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userID', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rowCount = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notificationsMattermost['enabled'] = $row['enabled'];
        $notificationsMattermost['webhook_url'] = $row['webhook_url'];
        $notificationsMattermost['bot_username'] = $row['bot_username'];
        $notificationsMattermost['bot_icon_emoji'] = $row['bot_icon_emoji'];
        $rowCount++;
    }

    if ($rowCount == 0) {
        $notificationsMattermost['enabled'] = 0;
        $notificationsMattermost['webhook_url'] = "";
        $notificationsMattermost['bot_username'] = "";
        $notificationsMattermost['bot_icon_emoji'] = "";
    }

    // Serverchan notifications
    $sql = "SELECT * FROM serverchan_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rowCount = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notificationsServerchan['enabled'] = $row['enabled'];
        $notificationsServerchan['sendkey'] = $row['sendkey'];
        $rowCount++;
    }

    if ($rowCount == 0) {
        $notificationsServerchan['enabled'] = 0;
        $notificationsServerchan['sendkey'] = "";
    }

    // Ntfy notifications
    $sql = "SELECT * FROM ntfy_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rowCount = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notificationsNtfy['enabled'] = $row['enabled'];
        $notificationsNtfy['host'] = $row['host'];
        $notificationsNtfy['topic'] = $row['topic'];
        $notificationsNtfy['headers'] = $row['headers'];
        $notificationsNtfy['ignore_ssl'] = $row['ignore_ssl'];
        $rowCount++;
    }

    if ($rowCount == 0) {
        $notificationsNtfy['enabled'] = 0;
        $notificationsNtfy['host'] = "";
        $notificationsNtfy['topic'] = "";
        $notificationsNtfy['headers'] = "";
        $notificationsNtfy['ignore_ssl'] = 0;
    }

    // Webhook notifications
    $sql = "SELECT * FROM webhook_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $rowCount = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notificationsWebhook['enabled'] = $row['enabled'];
        $notificationsWebhook['url'] = $row['url'];
        $notificationsWebhook['request_method'] = $row['request_method'];
        $notificationsWebhook['headers'] = $row['headers'];
        $notificationsWebhook['payload'] = $row['payload'];
        $notificationsWebhook['cancelation_payload'] = $row['cancelation_payload'];
        $notificationsWebhook['ignore_ssl'] = $row['ignore_ssl'];
        $rowCount++;
    }

    if ($rowCount == 0) {
        $notificationsWebhook['enabled'] = 0;
        $notificationsWebhook['url'] = "";
        $notificationsWebhook['request_method'] = "POST";
        $notificationsWebhook['headers'] = "";
        $notificationsWebhook['payload'] = '
{
    "name": "{{subscription_name}}",
    "price": "{{subscription_price}}",
    "currency": "{{subscription_currency}}",
    "category": "{{subscription_category}}",
    "date": "{{subscription_date}}",
    "payer": "{{subscription_payer}}",
    "days": "{{subscription_days_until_payment}}",
    "notes": "{{subscription_notes}}",
    "url": "{{subscription_url}}"
}';
        $notificationsWebhook['cancelation_payload'] = "";
        $notificationsWebhook['ignore_ssl'] = 0;
    }

    // Gotify notifications
    $sql = "SELECT * FROM gotify_notifications WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $rowCount = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notificationsGotify['enabled'] = $row['enabled'];
        $notificationsGotify['url'] = $row['url'];
        $notificationsGotify['token'] = $row['token'];
        $notificationsGotify['ignore_ssl'] = $row['ignore_ssl'];
        $rowCount++;
    }

    if ($rowCount == 0) {
        $notificationsGotify['enabled'] = 0;
        $notificationsGotify['url'] = "";
        $notificationsGotify['token'] = "";
        $notificationsGotify['ignore_ssl'] = 0;
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
                        <option value="0" <?= $notifications['days'] == 0 ? "selected" : "" ?>>
                            <?= translate('on_due_date', $i18n) ?>
                        </option>
                        <option value="1" <?= $notifications['days'] == 1 ? "selected" : "" ?>>
                            1 <?= translate('day_before', $i18n) ?>
                        </option>
                        <?php
                        for ($i = 2; $i <= 7; $i++) {
                            $selected = $i == $notifications['days'] ? "selected" : "";
                            ?>
                            <option value="<?= $i ?>" <?= $selected ?>>
                                <?= $i ?>     <?= translate('day_before', $i18n) ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                    <input type="submit" class="thin" value="<?= translate('save', $i18n) ?>" id="saveNotifications"
                        onClick="saveNotifications()" />
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
                        <input type="text" name="smtpaddress" id="smtpaddress" autocomplete="off"
                            placeholder="<?= translate('smtp_address', $i18n) ?>"
                            value="<?= $notificationsEmail['smtp_address'] ?>" />
                        <input type="text" name="smtpport" id="smtpport" autocomplete="off"
                            placeholder="<?= translate('port', $i18n) ?>" class="one-third"
                            value="<?= $notificationsEmail['smtp_port'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <div>
                            <input type="radio" name="encryption" id="encryptionnone" value="none"
                                <?= $notificationsEmail['encryption'] == "none" ? "checked" : "" ?> />
                            <label for="encryptionnone"><?= translate('none', $i18n) ?></label>
                        </div>
                        <div>
                            <input type="radio" name="encryption" id="encryptiontls" value="tls"
                                <?= $notificationsEmail['encryption'] == "tls" ? "checked" : "" ?> />
                            <label for="encryptiontls"><?= translate('tls', $i18n) ?></label>
                        </div>
                        <div>
                            <input type="radio" name="encryption" id="encryptionssl" value="ssl"
                                <?= $notificationsEmail['encryption'] == "ssl" ? "checked" : "" ?> />
                            <label for="encryptionssl"><?= translate('ssl', $i18n) ?></label>
                        </div>


                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="smtpusername" id="smtpusername" autocomplete="off"
                            placeholder="<?= translate('smtp_username', $i18n) ?>"
                            value="<?= $notificationsEmail['smtp_username'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="password" name="smtppassword" id="smtppassword" autocomplete="off"
                            placeholder="<?= translate('smtp_password', $i18n) ?>"
                            value="<?= $notificationsEmail['smtp_password'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="fromemail" id="fromemail" autocomplete="off"
                            placeholder="<?= translate('from_email', $i18n) ?>"
                            value="<?= $notificationsEmail['from_email'] ?>" />
                    </div>
                    <label for="otheremails"><?= translate('send_to_other_emails', $i18n) ?></label>
                    <div class="form-group-inline">
                        <input type="text" name="otheremails" id="otheremails" autocomplete="off"
                            placeholder="<?= translate('other_emails_placeholder', $i18n) ?>"
                            value="<?= $notificationsEmail['other_emails'] ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsEmail"
                            onClick="testNotificationEmailButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsEmail" onClick="saveNotificationsEmailButton()" />
                    </div>
                    <div class="settings-notes">
                        <p>
                            <i class="fa-solid fa-circle-info"></i> <?= translate('smtp_info', $i18n) ?>
                        </p>
                        <p>
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
                        <input type="checkbox" id="discordenabled" name="discordenabled"
                            <?= $notificationsDiscord['enabled'] ? "checked" : "" ?>>
                        <label for="discordenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="discordurl" id="discordurl" autocomplete="off"
                            placeholder="<?= translate('webhook_url', $i18n) ?>"
                            value="<?= $notificationsDiscord['webhook_url'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="discordbotusername" id="discordbotusername" autocomplete="off"
                            placeholder="<?= translate('discord_bot_username', $i18n) ?>"
                            value="<?= $notificationsDiscord['bot_username'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="discordbotavatar" id="discordbotavatar" autocomplete="off"
                            placeholder="<?= translate('discord_bot_avatar_url', $i18n) ?>"
                            value="<?= $notificationsDiscord['bot_avatar'] ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsDiscord"
                            onClick="testNotificationsDiscordButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsDiscord" onClick="saveNotificationsDiscordButton()" />
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
                        <input type="checkbox" id="gotifyenabled" name="gotifyenabled"
                            <?= $notificationsGotify['enabled'] ? "checked" : "" ?>>
                        <label for="gotifyenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="gotifyurl" id="gotifyurl" autocomplete="off"
                            placeholder="<?= translate('url', $i18n) ?>" value="<?= $notificationsGotify['url'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="gotifytoken" id="gotifytoken" autocomplete="off"
                            placeholder="<?= translate('token', $i18n) ?>"
                            value="<?= $notificationsGotify['token'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="checkbox" id="gotifyignoressl" name="gotifyignoressl"
                            <?= $notificationsGotify['ignore_ssl'] ? "checked" : "" ?>>
                        <label for="gotifyignoressl"><?= translate('ignore_ssl_errors', $i18n) ?></label>
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsGotify"
                            onClick="testNotificationsGotifyButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsGotify" onClick="saveNotificationsGotifyButton()" />
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
                        <input type="checkbox" id="pushoverenabled" name="pushoverenabled"
                            <?= $notificationsPushover['enabled'] ? "checked" : "" ?>>
                        <label for="pushoverenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="pushoveruserkey" id="pushoveruserkey" autocomplete="off"
                            placeholder="<?= translate('pushover_user_key', $i18n) ?>"
                            value="<?= $notificationsPushover['user_key'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="pushovertoken" id="pushovertoken" autocomplete="off"
                            placeholder="<?= translate('token', $i18n) ?>"
                            value="<?= $notificationsPushover['token'] ?>" />
                    </div>

                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsPushover"
                            onClick="testNotificationsPushoverButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsPushover" onClick="saveNotificationsPushoverButton()" />
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
                        <input type="checkbox" id="telegramenabled" name="telegramenabled"
                            <?= $notificationsTelegram['enabled'] ? "checked" : "" ?>>
                        <label for="telegramenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="telegrambottoken" id="telegrambottoken" autocomplete="off"
                            placeholder="<?= translate('telegram_bot_token', $i18n) ?>"
                            value="<?= $notificationsTelegram['bot_token'] ? $notificationsTelegram['bot_token'] : "" ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="telegramchatid" id="telegramchatid" autocomplete="off"
                            placeholder="<?= translate('telegram_chat_id', $i18n) ?>"
                            value="<?= $notificationsTelegram['chat_id'] ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsTelegram"
                            onClick="testNotificationsTelegramButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsTelegram" onClick="saveNotificationsTelegramButton()" />
                    </div>
                </div>
            </section>

            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('pushplus');">
                    <h3>
                        <i class="fa-solid fa-bell"></i>
                        <?= translate('pushplus', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="pushplus">
                    <div class="form-group-inline">
                        <input type="checkbox" id="pushplusenabled" name="pushplusenabled"
                            <?= $notificationsPushPlus['enabled'] ? "checked" : "" ?>>
                        <label for="pushplusenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="pushplustoken" id="pushplustoken" autocomplete="off"
                            placeholder="<?= translate('pushplus_token', $i18n) ?>"
                            value="<?= $notificationsPushPlus['token'] ? $notificationsPushPlus['token'] : '' ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsPushPlus"
                            onClick="testNotificationsPushPlusButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsPushPlus" onClick="saveNotificationsPushPlusButton()" />
                    </div>
                </div>
            </section>

            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('mattermost');">
                    <h3>
                        <i class="fa-solid fa-gauge-simple-high"></i>
                        <?= translate('mattermost', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="mattermost">
                    <div class="form-group-inline">
                        <input type="checkbox" id="mattermostenabled" name="mattermostenabled"
                            <?= $notificationsMattermost['enabled'] ? "checked" : "" ?>>
                        <label for="mattermostenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="mattermostwebhookurl" id="mattermostwebhookurl"
                            placeholder="<?= translate('mattermost_webhook_url', $i18n) ?>"
                            value="<?= $notificationsMattermost['webhook_url'] ? $notificationsMattermost['webhook_url'] : '' ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="mattermostbotusername" id="mattermostbotusername"
                            placeholder="<?= translate('mattermost_bot_username', $i18n) ?>"
                            value="<?= $notificationsMattermost['bot_username'] ? $notificationsMattermost['bot_username'] : '' ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="mattermostboticonemoji" id="mattermostboticonemoji"
                            placeholder="<?= translate('mattermost_bot_icon_emoji', $i18n) ?>"
                            value="<?= $notificationsMattermost['bot_icon_emoji'] ? $notificationsMattermost['bot_icon_emoji'] : '' ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsMattermost"
                            onClick="testNotificationsMattermostButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsMattermost" onClick="saveNotificationsMattermostButton()" />
                    </div>
                </div>
            </section>

            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('ntfy');">
                    <h3>
                        <i class="fa-solid fa-terminal"></i> Ntfy
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="ntfy">
                    <div class="form-group-inline">
                        <input type="checkbox" id="ntfyenabled" name="ntfyenabled" <?= $notificationsNtfy['enabled'] ? "checked" : "" ?>>
                        <label for="ntfyenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="ntfyhost" id="ntfyhost" autocomplete="off"
                            placeholder="<?= translate('host', $i18n) ?>" value="<?= $notificationsNtfy['host'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="ntfytopic" id="ntfytopic" autocomplete="off"
                            placeholder="<?= translate('topic', $i18n) ?>" value="<?= $notificationsNtfy['topic'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <textarea class="thin" name="ntfyheaders" id="ntfyheaders"
                            placeholder="<?= translate('custom_headers', $i18n) ?>"><?= $notificationsNtfy['headers'] ?></textarea>
                    </div>
                    <div class="form-grpup-inline">
                        <input type="checkbox" id="ntfyignoressl" name="ntfyignoressl"
                            <?= $notificationsNtfy['ignore_ssl'] ? "checked" : "" ?>>
                        <label for="ntfyignoressl"><?= translate('ignore_ssl_errors', $i18n) ?></label>
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsNtfy"
                            onClick="testNotificationsNtfyButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsNtfy" onClick="saveNotificationsNtfyButton()" />
                    </div>
            </section>

            <section class="account-notifications-section">
                <header class="account-notification-section-header" onclick="openNotificationsSettings('serverchan');">
                    <h3>
                        <i class="fa-solid fa-code"></i>
                        <?= translate('serverchan', $i18n) ?>
                    </h3>
                </header>
                <div class="account-notification-section-settings" data-type="serverchan">
                    <div class="form-group-inline">
                        <input type="checkbox" id="serverchanenabled" name="serverchanenabled"
                            <?= $notificationsServerchan['enabled'] ? "checked" : "" ?>>
                        <label for="serverchanenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="serverchansendkey" id="serverchansendkey" autocomplete="off"
                            placeholder="<?= translate('serverchan_sendkey', $i18n) ?>"
                            value="<?= $notificationsServerchan['sendkey'] ? $notificationsServerchan['sendkey'] : '' ?>" />
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsServerchan"
                            onClick="testNotificationsServerchanButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsServerchan" onClick="saveNotificationsServerchanButton()" />
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
                        <input type="checkbox" id="webhookenabled" name="webhookenabled"
                            <?= $notificationsWebhook['enabled'] ? "checked" : "" ?>>
                        <label for="webhookenabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
                    </div>
                    <div>
                        <label for="webhookrequestmethod"
                            class="capitalize"><?= translate('request_method', $i18n) ?>:</label>
                        <div class="form-group-inline">
                            <select name="webhookrequestmethod" id="webhookrequestmethod">
                                <option value="GET" <?= $notificationsWebhook['request_method'] == 'GET' ? 'selected' : '' ?>>GET</option>
                                <option value="POST" <?= $notificationsWebhook['request_method'] == 'POST' ? 'selected' : '' ?>>POST</option>
                                <option value="PUT" <?= $notificationsWebhook['request_method'] == 'PUT' ? 'selected' : '' ?>>PUT</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group-inline">
                        <input type="text" name="webhookurl" id="webhookurl" autocomplete="off"
                            placeholder="<?= translate('webhook_url', $i18n) ?>"
                            value="<?= $notificationsWebhook['url'] ?>" />
                    </div>
                    <div class="form-group-inline">
                        <textarea class="thin" name="webhookcustomheaders" id="webhookcustomheaders"
                            placeholder="<?= translate('custom_headers', $i18n) ?>"><?= $notificationsWebhook['headers'] ?></textarea>
                    </div>
                    <div class="form-group-inline">
                        <textarea name="webhookpayload" id="webhookpayload"
                            placeholder="<?= translate('payment_notifications_payload', $i18n) ?>"><?= $notificationsWebhook['payload'] ?></textarea>
                    </div>
                    <div class="form-group-inline">
                        <textarea name="webhookcancelationpayload" id="webhookcancelationpayload"
                            placeholder="<?= translate('cancelation_notification_payload', $i18n) ?>"><?= $notificationsWebhook['cancelation_payload'] ?></textarea>
                    </div>
                    <div class="form-group-inline">
                        <input type="checkbox" id="webhookignoressl" name="webhookignoressl"
                            <?= $notificationsWebhook['ignore_ssl'] ? "checked" : "" ?>>
                        <label for="webhookignoressl"><?= translate('ignore_ssl_errors', $i18n) ?></label>
                    </div>
                    <div class="buttons">
                        <input type="button" class="secondary-button thin mobile-grow"
                            value="<?= translate('test', $i18n) ?>" id="testNotificationsWebhook"
                            onClick="testNotificationsWebhookButton()" />
                        <input type="submit" class="thin mobile-grow" value="<?= translate('save', $i18n) ?>"
                            id="saveNotificationsWebhook" onClick="saveNotificationsWebhookButton()" />
                    </div>
                    <div class="settings-notes">
                        <p>
                            <i class="fa-solid fa-circle-info"></i> <?= translate('variables_available', $i18n) ?>:
                            {{days_until}}, {{subscription_name}}, {{subscription_price}}, {{subscription_currency}},
                            {{subscription_category}}, {{subscription_date}}, {{subscription_payer}},
                            {{subscription_days_until_payment}}, {{subscription_notes}}, {{subscription_url}}
                        </p>
                        <p>
                    </div>
                </div>
            </section>
        </div>
    </section>

    <?php
    $sql = "SELECT * FROM categories WHERE user_id = :userId ORDER BY `order` ASC";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

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
            <div id="categories" class="sortable-list">
                <?php
                foreach ($categories as $category) {
                    if ($category['id'] != 1) {
                        $canDelete = true;

                        $query = "SELECT COUNT(*) as count FROM subscriptions WHERE category_id = :categoryId AND user_id = :userId";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':categoryId', $category['id'], SQLITE3_INTEGER);
                        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
                        $result = $stmt->execute();
                        $row = $result->fetchArray(SQLITE3_ASSOC);
                        $isUsed = $row['count'];

                        if ($isUsed > 0) {
                            $canDelete = false;
                        }
                        ?>
                        <div class="form-group-inline" data-categoryid="<?= $category['id'] ?>">
                            <div class=" drag-icon"><i class="fa-solid fa-grip-vertical"></i></div>
                            <input type="text" name="category" autocomplete="off" value="<?= $category['name'] ?>"
                                placeholder="Category">
                            <button class="image-button medium" onClick="editCategory(<?= $category['id'] ?>)" name="save"
                                title="<?= translate('save_category', $i18n) ?>">
                                <?php include "images/siteicons/svg/save.php"; ?>
                            </button>
                            <?php
                            if ($canDelete) {
                                ?>
                                <button class="image-button medium" onClick="removeCategory(<?= $category['id'] ?>)"
                                    title="<?= translate('delete_category', $i18n) ?>">
                                    <?php include "images/siteicons/svg/delete.php"; ?>
                                </button>
                                <?php
                            } else {
                                ?>
                                <button class="image-button medium disabled"
                                    title="<?= translate('cant_delete_category_in_use', $i18n) ?>">
                                    <?php include "images/siteicons/svg/delete.php"; ?>
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
                <input type="submit" value="<?= translate('add', $i18n) ?>" id="addCategory"
                    onClick="addCategoryButton()" class="thin mobile-grow" />
            </div>
        </div>
    </section>

    <?php
    $sql = "SELECT * FROM currencies WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result) {
        $currencies = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $currencies[] = $row;
        }
    }

    $query = "SELECT main_currency FROM user WHERE id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
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
                        <input type="text" class="short" name="symbol" autocomplete="off" value="<?= $currency['symbol'] ?>"
                            placeholder="$">
                        <input type="text" name="currency" autocomplete="off" value="<?= $currency['name'] ?>"
                            placeholder="Currency Name">
                        <input type="text" name="code" autocomplete="off" value="<?= $currency['code'] ?>"
                            placeholder="Currency Code" <?= !$canDelete ? 'disabled' : '' ?>>
                        <button class="image-button medium" onClick="editCurrency(<?= $currency['id'] ?>)" name="save"
                            title="<?= translate('save_currency', $i18n) ?>">
                            <?php include "images/siteicons/svg/save.php"; ?>
                        </button>
                        <?php
                        if ($canDelete) {
                            ?>
                            <button class="image-button medium" onClick="removeCurrency(<?= $currency['id'] ?>)"
                                title="<?= translate('delete_currency', $i18n) ?>">
                                <?php include "images/siteicons/svg/delete.php"; ?>
                            </button>
                            <?php
                        } else {
                            $cantDeleteMessage = $isMainCurrency ? translate('cant_delete_main_currency', $i18n) : translate('cant_delete_currency_in_use', $i18n);
                            ?>
                            <button class="image-button medium disabled" title="<?= $cantDeleteMessage ?>">
                                <?php include "images/siteicons/svg/delete.php"; ?>
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
                <input type="submit" value="<?= translate('add', $i18n) ?>" id="addCurrency"
                    onClick="addCurrencyButton()" class="thin mobile-grow" />
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
    $sql = "SELECT api_key, provider FROM fixer WHERE user_id = :userId";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
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
                <input type="text" name="fixer-key" id="fixerKey" autocomplete="off" value="<?= $apiKey ?>"
                    placeholder="<?= translate('api_key', $i18n) ?>" <?= $demoMode ? 'disabled title="Not available on Demo Mode"' : '' ?>>
            </div>
            <div class="form-group">
                <label for="fixerProvider"><?= translate('provider', $i18n) ?>:</label>
                <select name="fixer-provider" id="fixerProvider">
                    <option value="0" <?= $provider == 0 ? 'selected' : '' ?>>fixer.io</option>
                    <option value="1" <?= $provider == 1 ? 'selected' : '' ?>>apilayer.com</option>
                </select>
            </div>
            <div class="buttons">
                <input type="submit" value="<?= translate('save', $i18n) ?>" id="addFixerKey"
                    onClick="addFixerKeyButton()" class="thin mobile-grow" />
            </div>
            <div class="settings-notes">
                <p><i class="fa-solid fa-circle-info"></i><?= translate('fixer_info', $i18n) ?></p>
                <p><?= translate('get_key', $i18n) ?>:
                    <span>
                        https://fixer.io/
                        <a href="https://fixer.io/#pricing_plan"
                            title="<?= translate("get_free_fixer_api_key", $i18n) ?>" target="_blank">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </span>
                </p>
                <p>
                    <?= translate("get_key_alternative", $i18n) ?>
                    <span>
                        https://apilayer.com
                        <a href="https://apilayer.com/marketplace/fixer-api" title="Get free fixer api key"
                            target="_blank">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </span>
                </p>
            </div>
        </div>
    </section>

    <?php
    $sql = "SELECT * FROM ai_settings WHERE user_id = :userId LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $aiSettings = [];
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $aiSettings = $row;
    }
    ?>

    <section class="account-section">
        <header>
            <h2><?= translate('ai_recommendations', $i18n) ?></h2>
        </header>
        <div class="account-ai-settings">
            <div class="form-group-inline">
                <input type="checkbox" id="ai_enabled" name="ai_enabled" <?= isset($aiSettings['enabled']) && $aiSettings['enabled'] ? "checked" : "" ?>>
                <label for="ai_enabled" class="capitalize"><?= translate('enabled', $i18n) ?></label>
            </div>
            <div class="form-group">
                <label for="ai_type"><?= translate('provider', $i18n) ?>:</label>
                <select id="ai_type" name="ai_type" onchange="toggleAiInputs()">
                    <option value="chatgpt" <?= (isset($aiSettings['type']) && $aiSettings['type'] == 'chatgpt') ? 'selected' : '' ?>>ChatGPT</option>
                    <option value="gemini" <?= (isset($aiSettings['type']) && $aiSettings['type'] == 'gemini') ? 'selected' : '' ?>>Gemini</option>
                    <option value="openrouter" <?= (isset($aiSettings['type']) && $aiSettings['type'] == 'openrouter') ? 'selected' : '' ?>>OpenRouter</option>
                    <option value="ollama" <?= (isset($aiSettings['type']) && $aiSettings['type'] == 'ollama') ? 'selected' : '' ?>>Local Ollama</option>
                </select>
            </div>
            <div class="form-group-inline">
                <input type="password" id="ai_api_key" name="ai_api_key" autocomplete="off"
                    class="<?= (isset($aiSettings['type']) && $aiSettings['type'] == 'ollama') ? 'hidden' : '' ?>"
                    placeholder="<?= translate('api_key', $i18n) ?>"
                    value="<?= isset($aiSettings['api_key']) ? htmlspecialchars($aiSettings['api_key']) : '' ?>" />
                <button type="button" id="toggleAiApiKey" class="button tiny <?= (isset($aiSettings['type']) && $aiSettings['type'] == 'ollama') ? 'hidden' : '' ?>" onclick="toggleAiApiKeyVisibility()" aria-label="Toggle API key visibility">
                    <i class="fa-solid fa-eye"></i>
                </button>
                <input type="text" id="ai_ollama_host" name="ai_ollama_host" autocomplete="off"
                    class="<?= (!isset($aiSettings['type']) || $aiSettings['type'] != 'ollama') ? 'hidden' : '' ?>"
                    placeholder="<?= translate('host', $i18n) ?>"
                    value="<?= isset($aiSettings['url']) ? htmlspecialchars($aiSettings['url']) : '' ?>" />

                <button type="button" id="fetchModelsButton" class="button thin" onclick="fetch_ai_models()">
                    <?= translate('test', $i18n) ?>
                </button>
            </div>
            <div class="form-group">
                <label for="ai_model"><?= translate('ai_model', $i18n) ?>:</label>
                <select id="ai_model" name="ai_model">
                    <option value=""><?= translate('select_ai_model', $i18n) ?></option>
                    <?php if (!empty($aiSettings['model'])): ?>
                        <option value="<?= htmlspecialchars($aiSettings['model']) ?>" selected>
                            <?= htmlspecialchars($aiSettings['model']) ?>
                        </option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="ai_run_schedule" class="flex"><?= translate('run_schedule', $i18n) ?>: <span
                        class="info-badge"><?= translate("coming_soon", $i18n) ?></span></span></label>
                <select id="ai_run_schedule" name="ai_run_schedule" disabled>
                    <option value="manual" <?= (isset($aiSettings['run_schedule']) && $aiSettings['run_schedule'] == 'manual') ? 'selected' : '' ?>><?= translate('manually', $i18n) ?>
                    </option>
                    <option value="weekly" <?= (isset($aiSettings['run_schedule']) && $aiSettings['run_schedule'] == 'weekly') ? 'selected' : '' ?>><?= translate('Weekly', $i18n) ?>
                    </option>
                    <option value="monthly" <?= (isset($aiSettings['run_schedule']) && $aiSettings['run_schedule'] == 'monthly') ? 'selected' : '' ?>><?= translate('Monthly', $i18n) ?>
                    </option>
                </select>
            </div>
            <div class="buttons wrap mobile-reverse">
                <?php
                $canBeExecuted = !empty($aiSettings['model']) && !empty($aiSettings['enabled']) && $aiSettings['enabled'] == 1;
                ?>
                <input type="button" id="runAiRecommendations"
                    class="secondary-button thin mobile-grow-force <?= !$canBeExecuted ? 'hidden' : '' ?>"
                    onclick="runAiRecommendations()" value="<?= translate('generate_recommendations', $i18n) ?>" />
                <div id="aiSpinner" class="spinner ai-spinner hidden"></div>

                <input type="submit" class="thin mobile-grow-force" value="<?= translate('save', $i18n) ?>"
                    id="saveAiSettings" onClick="saveAiSettingsButton()" />
            </div>
            <div class="settings-notes">
                <p><i class="fa-solid fa-circle-info"></i><?= translate('ai_recommendations_info', $i18n) ?></p>
                <p><i class="fa-solid fa-circle-info"></i><?= translate('may_take_time', $i18n) ?></p>
                <p><i
                        class="fa-solid fa-circle-info"></i><?= translate('recommendations_visible_on_dashboard', $i18n) ?>
                </p>
            </div>
        </div>
    </section>

    <?php
    $sql = "SELECT * FROM payment_methods WHERE user_id = :userId ORDER BY `order` ASC";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

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
            $paymentsInUseQuery = $db->prepare('SELECT id FROM payment_methods WHERE user_id = :userId AND id IN (SELECT DISTINCT payment_method_id FROM subscriptions WHERE user_id = :userId)');
            $paymentsInUseQuery->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $result = $paymentsInUseQuery->execute();

            $paymentsInUse = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $paymentsInUse[] = $row['id'];
            }

            foreach ($payments as $payment) {
                $paymentIconFolder = (strpos($payment['icon'], 'images/uploads/icons/') !== false) ? "" : "images/uploads/logos/";

                $inUse = in_array($payment['id'], $paymentsInUse);
                ?>
                <div class="payments-payment" data-enabled="<?= $payment['enabled']; ?>"
                    data-in-use="<?= $inUse ? 'yes' : 'no' ?>" data-paymentid="<?= $payment['id'] ?>"
                    title="<?= $inUse ? translate('cant_delete_payment_method_in_use', $i18n) : ($payment['enabled'] ? translate('disable', $i18n) : translate('enable', $i18n)) ?>">
                    <div class="drag-icon" title="">
                        <i class="fa-solid fa-grip-vertical"></i>
                    </div>
                    <img src="<?= $paymentIconFolder . $payment['icon'] ?>" alt="Logo" />
                    <span class="payment-name" contenteditable="true"
                        title="<?= translate("rename_payment_method", $i18n) ?>"><?= $payment['name'] ?></span>
                    <?php
                    if (!$inUse) {
                        ?>
                        <div class="delete-payment-method" title="<?= translate('delete', $i18n) ?>"
                            data-paymentid="<?= $payment['id'] ?>">x</div>
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
                    <input type="text" name="paymentname" id="paymentname" autocomplete="off"
                        placeholder="<?= translate('payment_method_name', $i18n) ?>" onchange="setSearchButtonStatus()"
                        onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange();" />
                    <label for="paymenticon" class="icon-preview">
                        <img src="" alt="<?= translate('logo_preview', $i18n) ?>" id="form-icon">
                    </label>
                    <div class="form-icon-search">
                        <input type="file" id="paymenticon" name="paymenticon"
                            accept="image/jpeg, image/png, image/gif, image/webp" onchange="handleFileSelect(event)"
                            class="hidden-input">
                        <input type="hidden" id="icon-url" name="icon-url">
                        <div id="icon-search-button" class="image-button medium disabled"
                            title="<?= translate('search_logo', $i18n) ?>" onClick="searchPaymentIcon()">
                            <?php include "images/siteicons/svg/websearch.php"; ?>
                        </div>
                        <div id="icon-search-results" class="icon-search">
                            <header>
                                <span class="fa-solid fa-xmark close-icon-search" onClick="closeIconSearch()"></span>
                            </header>
                            <div id="icon-search-images"></div>
                        </div>
                    </div>

                    <input type="button" class="button thin" id="add-payment-button" value="+"
                        title="<?= translate('add', $i18n) ?>" id="addPayment" onClick="addPaymentMethod()" />
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
                <h3><?= translate('theme', $i18n) ?></h3>
                <div class="form-group-inline wrap">
                    <button type="button"
                        class="dark-theme-button capitalize <?= $settings['dark_theme'] == '0' ? 'selected' : '' ?>"
                        onClick="setDarkTheme('0')" id="theme-light">
                        <i class="fa-solid fa-sun"></i> <?= translate('light_theme', $i18n) ?>
                    </button>
                    <button type="button"
                        class="dark-theme-button capitalize <?= $settings['dark_theme'] == '1' ? 'selected' : '' ?>"
                        onClick="setDarkTheme('1')" id="theme-dark">
                        <i class="fa-solid fa-moon"></i> <?= translate('dark_theme', $i18n) ?>
                    </button>
                    <button type="button"
                        class="dark-theme-button capitalize <?= $settings['dark_theme'] == '2' ? 'selected' : '' ?>"
                        onClick="setDarkTheme('2')" id="theme-automatic">
                        <i class="fa-solid fa-circle-half-stroke"></i> <?= translate('automatic', $i18n) ?>
                    </button>
                </div>
            </div>
            <div>
                <form class="theme-selector">
                    <h3><?= translate('colors', $i18n) ?></h3>
                    <div class="form-group-inline wrap">
                        <div class="theme">
                            <input type="radio" name="theme" id="theme-blue" value="blue" onClick="setTheme('blue')"
                                <?= $settings['color_theme'] == 'blue' ? 'checked' : '' ?>>
                            <label for="theme-blue"
                                class="theme-preview blue <?= $settings['color_theme'] == 'blue' ? 'is-selected' : '' ?>">
                                <span class="main-color"></span>
                                <span class="accent-color"></span>
                                <span class="hover-color"></span>
                            </label>
                        </div>
                        <div class="theme">
                            <input type="radio" name="theme" id="theme-green" value="green" onClick="setTheme('green')"
                                <?= $settings['color_theme'] == 'green' ? 'checked' : '' ?>>
                            <label for="theme-green"
                                class="theme-preview green <?= $settings['color_theme'] == 'green' ? 'is-selected' : '' ?>">
                                <span class="main-color"></span>
                                <span class="accent-color"></span>
                                <span class="hover-color"></span>
                            </label>
                        </div>
                        <div class="theme">
                            <input type="radio" name="theme" id="theme-red" value="red" onClick="setTheme('red')"
                                <?= $settings['color_theme'] == 'red' ? 'checked' : '' ?>>
                            <label for="theme-red"
                                class="theme-preview red <?= $settings['color_theme'] == 'red' ? 'is-selected' : '' ?>">
                                <span class="main-color"></span>
                                <span class="accent-color"></span>
                                <span class="hover-color"></span>
                            </label>
                        </div>
                        <div class="theme">
                            <input type="radio" name="theme" id="theme-yellow" value="yellow"
                                onClick="setTheme('yellow')" <?= $settings['color_theme'] == 'yellow' ? 'checked' : '' ?>>
                            <label for="theme-yellow"
                                class="theme-preview yellow <?= $settings['color_theme'] == 'yellow' ? 'is-selected' : '' ?>">
                                <span class="main-color"></span>
                                <span class="accent-color"></span>
                                <span class="hover-color"></span>
                            </label>
                        </div>
                        <div class="theme">
                            <input type="radio" name="theme" id="theme-purple" value="purple"
                                onClick="setTheme('purple')" <?= $settings['color_theme'] == 'purple' ? 'checked' : '' ?>>
                            <label for="theme-purple"
                                class="theme-preview purple <?= $settings['color_theme'] == 'purple' ? 'is-selected' : '' ?>">
                                <span class="main-color"></span>
                                <span class="accent-color"></span>
                                <span class="hover-color"></span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div>
                <h3><?= translate('custom_colors', $i18n) ?></h3>
                <div class="custom-colors wrap">
                    <div class="form-group-inline mobile-grow color-picker-button">
                        <input type="color" id="mainColor" name="mainColor"
                            value="<?= isset($settings['customColors']['main_color']) ? $settings['customColors']['main_color'] : '#FFFFFF' ?>"
                            class="color-picker fa-solid fa-eye-dropper">
                        <label for="mainColor"><?= translate('main_color', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline mobile-grow color-picker-button">
                        <input type="color" id="accentColor" name="accentColor"
                            value="<?= isset($settings['customColors']['accent_color']) ? $settings['customColors']['accent_color'] : '#FFFFFF' ?>"
                            class="color-picker fa-solid fa-eye-dropper">
                        <label for="accentColor"><?= translate('accent_color', $i18n) ?></label>
                    </div>
                    <div class="form-group-inline mobile-grow color-picker-button">
                        <input type="color" id="hoverColor" name="hoverColor"
                            value="<?= isset($settings['customColors']['hover_color']) ? $settings['customColors']['hover_color'] : '#FFFFFF' ?>"
                            class="color-picker fa-solid fa-eye-dropper">
                        <label for="hoverColor"><?= translate('hover_color', $i18n) ?></label>
                    </div>
                </div>
                <div class="custom-colors wrap">
                    <input type="button" value="<?= translate('reset_custom_colors', $i18n) ?>"
                        onClick="resetCustomColors()" class="secondary-button thin mobile-grow" id="reset-colors">
                    <input type="button" value="<?= translate('save_custom_colors', $i18n) ?>"
                        onClick="saveCustomColors()" class="buton thin mobile-grow" id="save-colors">
                </div>
            </div>
            <?php
            if (!$demoMode) {
                ?>
                <div>
                    <h3><?= translate('custom_css', $i18n) ?></h3>
                    <div class="form-group">
                        <div class="form-group-inline">
                            <textarea name="customCss" id="customCss" placeholder="<?= translate('custom_css', $i18n) ?>"
                                class="thin"><?= $settings['customCss'] ?? "" ?></textarea>
                        </div>
                        <div class="form-group-inline">
                            <input type="button" value="<?= translate('save_custom_css', $i18n) ?>"
                                onClick="saveCustomCss()" class="buton thin mobile-grow" id="save-css">
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
    </section>

    <section class="account-section">
        <header>
            <h2><?= translate('display_settings', $i18n) ?></h2>
        </header>
        <div class="account-settings-list">
            <h3><?= translate('price', $i18n) ?></h3>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="monthlyprice" name="monthlyprice" onChange="setShowMonthlyPrice()" <?php if ($settings['monthly_price'])
                        echo 'checked'; ?>>
                    <label for="monthlyprice"><?= translate('calculate_monthly_price', $i18n) ?></label>
                </div>
            </div>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="convertcurrency" name="convertcurrency" onChange="setConvertCurrency()"
                        <?php
                        if ($settings['convert_currency'])
                            echo ' checked';
                        if ($apiKey == "")
                            echo ' disabled';
                        ?>>
                    <label for="convertcurrency"><?= translate('convert_prices', $i18n) ?></label>
                </div>
            </div>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="showoriginalprice" name="showoriginalprice"
                        onChange="setShowOriginalPrice()" <?= $settings['show_original_price'] ? 'checked' : '' ?>>
                    <label for="showoriginalprice"><?= translate('show_original_price', $i18n) ?></label>
                </div>
            </div>
            <h3><?= translate('experience', $i18n) ?></h3>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="mobilenavigation" name="mobilenavigation"
                        onChange="setMobileNavigation()" <?= $settings['mobile_nav'] ? 'checked' : '' ?>>
                    <label for="mobilenavigation"><?= translate('use_mobile_navigation_bar', $i18n) ?></label>
                </div>
                <div class="mobile-nav-image">
                </div>
            </div>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="showsubscriptionprogress" name="showsubscriptionprogress"
                        onChange="setShowSubscriptionProgress()" <?= $settings['show_subscription_progress'] ? 'checked' : '' ?>>
                    <label for="showsubscriptionprogress"><?= translate('show_subscription_progress', $i18n) ?></label>
                </div>
            </div>
            <h3><?= translate('disabled_subscriptions', $i18n) ?></h3>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="disabledtobottom" name="disabledtobottom"
                        onChange="setDisabledToBottom()" <?= $settings['disabled_to_bottom'] ? 'checked' : '' ?>>
                    <label
                        for="disabledtobottom"><?= translate('show_disabled_subscriptions_at_the_bottom', $i18n) ?></label>
                </div>
            </div>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="hidedisabled" name="hidedisabled" onChange="setHideDisabled()"
                        <?= $settings['hide_disabled'] ? 'checked' : '' ?>>
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
                    <input type="checkbox" id="removebackground" name="removebackground"
                        onChange="setRemoveBackground()" <?= $settings['remove_background'] ? 'checked' : '' ?>>
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

</section>
<script src="scripts/settings.js?<?= $version ?>"></script>
<script src="scripts/theme.js?<?= $version ?>"></script>
<script src="scripts/notifications.js?<?= $version ?>"></script>

<?php
require_once 'includes/footer.php';
?>