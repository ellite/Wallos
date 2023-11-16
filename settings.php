<?php
    require_once 'includes/header.php';
?>
<section class="contain settings">
    <section class="account-section">
        <header>
            <h2>User details</h2>
        </header>
            <form action="endpoints/user/saveuser.php" method="post" id="userForm">
                <div class="user-form">
                    <div class="fields">
                        <div>
                            <div class="user-avatar">
                                <img src="images/avatars/<?= $userData['avatar'] ?>.svg" alt="avatar" class="avatar" id="avatarImg" onClick="toggleAvatarSelect()"/>
                                <span class="edit-avatar" onClick="toggleAvatarSelect()">
                                    <img src="images/siteicons/editavatar.png" title="Change avatar" />
                                </span>
                            </div>
                            
                            <input type="hidden" name="avatar" value="<?= $userData['avatar'] ?>" id="avatarUser"/>
                            <div class="avatar-select" id="avatarSelect">
                                <div class="avatar-list">
                                    <img src="images/avatars/0.svg" onClick="changeAvatar(0)" />
                                    <img src="images/avatars/1.svg" onClick="changeAvatar(1)" />
                                    <img src="images/avatars/2.svg" onClick="changeAvatar(2)" />
                                    <img src="images/avatars/3.svg" onClick="changeAvatar(3)" />
                                    <img src="images/avatars/4.svg" onClick="changeAvatar(4)" />
                                    <img src="images/avatars/5.svg" onClick="changeAvatar(5)" />
                                    <img src="images/avatars/6.svg" onClick="changeAvatar(6)" />
                                    <img src="images/avatars/7.svg" onClick="changeAvatar(7)" />
                                    <img src="images/avatars/8.svg" onClick="changeAvatar(8)" />
                                    <img src="images/avatars/9.svg" onClick="changeAvatar(9)" />
                                </div>
                            </div>
                        </div>
                        <div class="grow">
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" value="<?= $userData['username'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?= $userData['email'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password:</label>
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
                            <label for="currency">Main Currency:</label>
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
                        </div>  
                    </div>
                    <div class="buttons">
                        <input type="submit" value="Save" id="userSubmit"/>
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
            <h2>Household</h2>
        </header>
        <div class="account-members">
            <div  id="householdMembers">
            <?php
                foreach ($household as $member) {
                    ?>
                    <div class="form-group-inline" data-memberid="<?= $member['id'] ?>">
                        <input type="text" name="member" value="<?= $member['name'] ?>" placeholder="Member">
                        <button class="image-button medium"  onClick="editMember(<?= $member['id'] ?>)" name="save">
                            <img src="images/siteicons/save.png" title="Save Member">
                        </button>
                        <?php
                            if ($member['id'] != 1) {
                                ?>
                                    <button class="image-button medium" onClick="removeMember(<?= $member['id'] ?>)">
                                        <img src="images/siteicons/delete.png" title="Delete Member">
                                    </button>
                                <?php
                            } else {
                                ?>
                                    <button class="image-button medium disabled">
                                        <img src="images/siteicons/delete.png" title="Can't delete main member">
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
                <input type="submit" value="Add" id="addMember" onClick="addMemberButton()"/>
            </div>
        </div>
    </section>

    <?php
        $sql = "SELECT * FROM notifications LIMIT 1";
        $result = $db->query($sql);

        $rowCount = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notifications = $row;
            $rowCount++;
        }
        
        if ($rowCount == 0) {
            $notifications['enabled'] = false;
            $notifications['days'] = 1;
            $notifications['smtp_address'] = "";
            $notifications['smtp_port'] = "";
            $notifications['smtp_username'] = "";
            $notifications['smtp_password'] = "";
        }

    ?>

    <section class="account-section">
        <header>
            <h2>Notifications</h2>
        </header>
        <div class="account-notifications">
            <div class="form-group-inline">
                <input type="checkbox" id="notifications" name="notifications" <?= $notifications['enabled'] ? "checked" : "" ?>>
                <label for="notifications">Enable email notifications</label>
            </div>
            <div class="form-group">
                <label for="days">Notify me: </label>
                <select name="days" id="days">
                <?php
                    for ($i = 1; $i <= 7; $i++) {
                        $dayText = $i > 1 ? "days" : "day";
                        $selected = $i == $notifications['days'] ? "selected" : "";
                        ?>
                            <option value="<?= $i ?>" <?= $selected ?>>
                                <?= $i ?> <?= $dayText ?> before
                            </option>
                        <?php
                    }
                ?>
                </select>
            </div>
            <div class="form-group-inline">
                <input type="text" name="smtpaddress" id="smtpaddress" placeholder="SMTP Address" value="<?= $notifications['smtp_address'] ?>" />
                <input type="text" name="smtpport" id="smtpport" placeholder="Port" class="one-third"  value="<?= $notifications['smtp_port'] ?>" />
            </div>
            <div class="form-group-inline">
                <input type="text" name="smtpusername" id="smtpusername" placeholder="SMTP Username"  value="<?= $notifications['smtp_username'] ?>" />
            </div>
            <div class="form-group-inline">
                <input type="password" name="smtppassword" id="smtppassword" placeholder="SMTP Password"  value="<?= $notifications['smtp_password'] ?>" />
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i> SMTP Password is transmitted and stored in plaintext. 
                    For security, please create an account just for this.</p>
                <p>
            </div>
            <div class="buttons">
                <input type="button" class="secondary-button" value="Test" id="testNotifications" onClick="testNotificationButton()"/>
                <input type="submit" value="Save" id="saveNotifications" onClick="saveNotificationsButton()"/>
            </div>
        </div>
    </section>

    <?php
        $sql = "SELECT * FROM categories";
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
            <h2>Categories</h2>
        </header>
        <div class="account-categories">
            <div  id="categories">
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
                        <input type="text" name="category" value="<?= $category['name'] ?>" placeholder="Category">
                        <button class="image-button medium"  onClick="editCategory(<?= $category['id'] ?>)" name="save">
                            <img src="images/siteicons/save.png" title="Save Category">
                        </button>
                        <?php
                            if ($canDelete) {
                            ?>
                                <button class="image-button medium" onClick="removeCategory(<?= $category['id'] ?>)">
                                    <img src="images/siteicons/delete.png" title="Delete Category">
                                </button>
                            <?php
                            } else {
                            ?>
                                <button class="image-button medium disabled">
                                    <img src="images/siteicons/delete.png" title="Can't delete category in use in subscription">
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
                <input type="submit" value="Add" id="addCategory" onClick="addCategoryButton()"/>
            </div>
        </div>
    </section>

    <?php
        $sql = "SELECT * FROM payment_methods";
        $result = $db->query($sql);

        if ($result) {
            $payments = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $payments[] = $row;
            }
        }
    ?>

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
            <h2>Currencies</h2>
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
                        <input type="text" name="code" value="<?= $currency['code'] ?>" placeholder="Currency Code">
                        <button class="image-button medium"  onClick="editCurrency(<?= $currency['id'] ?>)" name="save">
                            <img src="images/siteicons/save.png" title="Save Currency">
                        </button>
                        <?php
                            if ($canDelete) {
                            ?>
                                <button class="image-button medium" onClick="removeCurrency(<?= $currency['id'] ?>)">
                                    <img src="images/siteicons/delete.png" title="Delete Currency">
                                </button>
                            <?php
                            } else {
                                $cantDeleteMessage = $isMainCurrency ? "main currency" : "used currency";
                            ?>
                                <button class="image-button medium disabled">
                                    <img src="images/siteicons/delete.png" title="Can't delete <?= $cantDeleteMessage ?>">
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
                <input type="submit" value="Add" id="addCurrency" onClick="addCurrencyButton()"/>
            </div>
            <div class="settings-notes">
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    Exchange rates last updated on 
                    <span>
                        <?= $exchange_rates_last_updated ?>
                    </span>
                </p>
                <p>
                    <i class="fa-solid fa-circle-info"></i>
                    Find the supported currencies and correct currency codes on 
                    <span>
                        fixer.io 
                        <a href="https://fixer.io/symbols" target="_blank" title="Currency codes">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </span>
                </p>
                <p>
                    For improved performance keep only the currencies you use.
                </p>
            </div>
        </div>
    </section>

    <?php
        $apiKey = "";
        $sql = "SELECT api_key FROM fixer";
        $result = $db->query($sql);
        if ($result) {
            $row = $result->fetchArray(SQLITE3_ASSOC);
            if ($row) {
                $apiKey = $row['api_key'];
            }
        }
    ?>

    <section class="account-section">
        <header>
            <h2>Fixer API Key</h2>
        </header>
        <div class="account-fixer">
            <div class="form-group">
                <input type="text" name="fixer-key" id="fixerKey" value="<?= $apiKey ?>" placeholder="ApiKey">
            </div>
            <div class="settings-notes">
                <p><i class="fa-solid fa-circle-info"></i> If you use multiple currencies, and want accurate statistics and sorting on the subscriptions, 
                    a FREE API Key from Fixer is necessary.</p>
                <p>Get your key at: 
                    <span>
                        https://fixer.io/ 
                        <a href="https://fixer.io/#pricing_plan" title="Get free fixer api key" target="_blank">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </span>
                </p>    
            </div>
            <div class="buttons">
                <input type="submit" value="Save" id="addFixerKey" onClick="addFixerKeyButton()"/>
            </div>
        </div>
    </section>    

    <section class="account-section">
        <header>
            <h2>Display settings</h2>
        </header>
        <div class="account-settings-list">
            <div>
                <input type="button" value="Switch Light / Dark Theme" onClick="switchTheme()">
            </div>
            <?php
                $hidename = isset($_COOKIE['hideNameOnMobile']) && $_COOKIE['hideNameOnMobile'] === 'true';
                $monthlyprice = isset($_COOKIE['showMonthlyPrice']) && $_COOKIE['showMonthlyPrice'] === 'true';
                $convertcurrency = isset($_COOKIE['convertCurrency']) && $_COOKIE['convertCurrency'] === 'true';
                $removebackground = isset($_COOKIE['removeBackground']) && $_COOKIE['removeBackground'] === 'true';
            ?>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="hidename" name="hidename" onChange="setHideNameOnMobileCookie()"  <?= $hidename ? "checked" : "" ?>>
                    <label for="hidename">Hide subscripton name on mobile</label>
                </div>
            </div>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="monthlyprice" name="monthlyprice" onChange="setShowMonthlyPriceCookie()" <?php if ($monthlyprice) echo 'checked'; ?>>
                    <label for="monthlyprice">Calculate and show monthly price for all subscriptions</label>
                </div>
            </div>
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="convertcurrency" name="convertcurrency" onChange="setConvertCurrencyCookie()" <?php if ($convertcurrency) echo 'checked'; ?>>
                    <label for="convertcurrency">Always convert and show prices on my main currency (slower).</label>
                </div>
            </div>
        </div>
    </section>    

    <section class="account-section">
        <header>
            <h2>Experimental settings</h2>
        </header>
        <div class="account-settings-list">
            <div>
                <div class="form-group-inline">
                    <input type="checkbox" id="removebackground" name="removebackground" onChange="setRemoveBackgroundCookie()" <?php if ($removebackground) echo 'checked'; ?>>
                    <label for="removebackground">Attempt to remove background of logos from image search (experimental).</label>
                </div>
            </div>
        </div>
        <div class="settings-notes">
            <p>
                <i class="fa-solid fa-circle-info"></i>
                Experimental settings will probably not work perfectly.
            </p>
        </div>
    </section>    

    <section class="account-section">
        <header>
            <h2>Payment Methods</h2>
        </header>
        <div class="payments-list">
            <?php
                $paymentsInUseQuery = $db->query('SELECT id FROM payment_methods WHERE id IN (SELECT DISTINCT payment_method_id FROM subscriptions)');
                $paymentsInUse = [];
                while ($row = $paymentsInUseQuery->fetchArray(SQLITE3_ASSOC)) {
                    $paymentsInUse[] = $row['id'];
                }

                foreach ($payments as $payment) {
                    $inUse = in_array($payment['id'], $paymentsInUse);
                    ?>
                        <div class="payments-payment"
                             data-enabled="<?= $payment['enabled']; ?>"
                             data-in-use="<?= $inUse ? 'yes' : 'no' ?>"
                             data-paymentid="<?= $payment['id'] ?>"
                             title="<?= $inUse ? 'Can\'t delete used payment method' : '' ?>"
                             onClick="togglePayment(<?= $payment['id'] ?>)">
                            <img src="images/uploads/icons/<?= $payment['icon'] ?>"  alt="Logo" />
                            <span class="payment-name">
                                <?= $payment['name'] ?>
                            </span>
                        </div>
                    <?php
                } 
            ?>
        </div>
    </section>

    <section class="account-section">
        <header>
            <h2>About and Credits</h2>
        </header>
        <div class="credits-list">
            <p>Wallos v1.0</p>
            <p>License: 
                <span>
                    GPLv3
                    <a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="_blank" title="Visit external url">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            <p>
                The author: 
                <span>
                    https://henrique.pt
                    <a href="https://henrique.pt/" target="_blank" title="Visit external url">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
            <p>
                Icons: 
                <span>
                    https://www.streamlinehq.com/freebies/plump-flat-free
                    <a href="https://www.streamlinehq.com/freebies/plump-flat-free" target="_blank" title="Visit external url">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
            <p>
                Payment Icons: 
                <span>
                    https://www.figma.com/file/5IMW8JfoXfB5GRlPNdTyeg/Credit-Cards-and-Payment-Methods-Icons-(Community)
                    <a href="https://www.figma.com/file/5IMW8JfoXfB5GRlPNdTyeg/Credit-Cards-and-Payment-Methods-Icons-(Community)" target="_blank" title="Visit external url">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
        </div>
    </section>

</section>
<script src="scripts/settings.js"></script>

<?php
    require_once 'includes/footer.php';
?>