<?php

    require_once 'i18n/getlang.php';

    function getBillingCycle($cycle, $frequency, $i18n) {
        switch ($cycle) {
        case 1:
            return $frequency == 1 ? translate('Daily', $i18n) : $frequency . " " . translate('days', $i18n);
        case 2:
            return $frequency == 1 ? translate('Weekly', $i18n) : $frequency . " " . translate('weeks', $i18n);
        case 3:
            return $frequency == 1 ? translate('Monthly', $i18n) : $frequency . " " . translate('months', $i18n);
        case 4:
            return $frequency == 1 ? translate('Yearly', $i18n) : $frequency . " " . translate('years', $i18n);
        }
    }

    function getPricePerMonth($cycle, $frequency, $price) {
        switch ($cycle) {
        case 1:
            $numberOfPaymentsPerMonth = (30 / $frequency); 
            return $price * $numberOfPaymentsPerMonth;
        case 2:
            $numberOfPaymentsPerMonth = (4.35 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 3:
            $numberOfPaymentsPerMonth = (1 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 4:
            $numberOfMonths = (12 * $frequency);
            return $price / $numberOfMonths;
        }
    }

    
    function getPriceConverted($price, $currency, $database) {
        $query = "SELECT rate FROM currencies WHERE id = :currency";
        $stmt = $database->prepare($query);
        $stmt->bindParam(':currency', $currency, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
        if ($exchangeRate === false) {
            return $price;
        } else {
            $fromRate = $exchangeRate['rate'];
            return $price / $fromRate;
        }
    }

    function printSubscriptions($subscriptions, $sort, $categories, $members, $i18n, $colorTheme, $imagePath) {
        if ($sort === "price") {
            usort($subscriptions, function($a, $b) {
                return $a['price'] < $b['price'] ? 1 : -1;
            });
        }

        $currentCategory = 0;
        $currentPayerUserId = 0;
        $currentPaymentMethodId = 0;
        foreach ($subscriptions as $subscription) {
            if ($sort == "category_id" && $subscription['category_id'] != $currentCategory) {
                ?>
                    <div class="subscription-list-title">
                        <?php
                            if ($subscription['category_id'] == 1) {
                                echo translate('no_category', $i18n);
                            } else {
                                echo $categories[$subscription['category_id']]['name'];
                            }
                        ?>
                    </div>
                <?php
                $currentCategory = $subscription['category_id'];
            }
            if ($sort == "payer_user_id" && $subscription['payer_user_id'] != $currentPayerUserId) {
                ?>
                    <div class="subscription-list-title">
                        <?= $members[$subscription['payer_user_id']]['name'] ?>
                    </div>
                <?php
                $currentPayerUserId = $subscription['payer_user_id'];
            }
            if ($sort == "payment_method_id" && $subscription['payment_method_id'] != $currentPaymentMethodId) {
                ?>
                    <div class="subscription-list-title">
                        <?= $subscription['payment_method_name'] ?>
                    </div>
                <?php
                $currentPaymentMethodId = $subscription['payment_method_id'];
            }
        ?>
            <div class="subscription<?= $subscription['inactive'] ? ' inactive' : '' ?>" onClick="toggleOpenSubscription(<?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>" data-name="<?= $subscription['name'] ?>">
                <div class="subscription-main">
                    <span class="logo">
                        <?php
                            if ($subscription['logo'] != "") {
                                ?>
                                <img src="<?= $subscription['logo'] ?>">
                                <?php
                            } else {
                                include $imagePath . "images/siteicons/svg/logo.php";
                            }
                        ?>
                    </span>
                    <span class="name"><?= $subscription['name'] ?></span>
                    <span class="cycle"><?= $subscription['billing_cycle'] ?></span>
                    <span class="next"><?= $subscription['next_payment'] ?></span>
                    <span class="price">
                    <img src="<?= $subscription['payment_method_icon'] ?>" title="<?= translate('payment_method', $i18n) ?>: <?= $subscription['payment_method_name'] ?>"/>
                    <?= CurrencyFormatter::format($subscription['price'], $subscription['currency_code']) ?>
                    </span>
                    <button type="button" class="actions-expand" onClick="expandActions(event, <?= $subscription['id'] ?>)">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="actions">
                        <li class="edit" title="<?= translate('edit_subscription', $i18n) ?>" onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/edit.php"; ?>
                            <?= translate('edit_subscription', $i18n) ?>
                        </li>
                        <li class="delete" title="<?= translate('delete', $i18n) ?>" onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/delete.php"; ?>
                            <?= translate('delete', $i18n) ?>
                        </li>
                        <li class="clone" title="<?= translate('clone', $i18n) ?>" onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/clone.php"; ?>
                            <?= translate('clone', $i18n) ?>
                        </li>
                    </ul>
                </div>
                <div class="subscription-secondary">
                    <span class="name"><?php include $imagePath . "images/siteicons/svg/subscription.php"; ?><?= $subscription['name'] ?></span>
                    <span class="payer_user" title="<?= translate('paid_by', $i18n) ?>"><?php include $imagePath . "images/siteicons/svg/payment.php"; ?><?= $members[$subscription['payer_user_id']]['name'] ?></span>
                    <span class="category" title="<?= translate('category', $i18n) ?>" ><?php include $imagePath . "images/siteicons/svg/category.php"; ?><?= $categories[$subscription['category_id']]['name'] ?></span>
                    <?php
                        if ($subscription['url'] != "") {
                            $url = $subscription['url'];
                            if (!preg_match('/^https?:\/\//', $url)) {
                                $url = "https://" . $url;
                            }
                            ?>
                                <span class="url" title="<?= translate('external_url', $i18n) ?>"><a href="<?= $url ?>" target="_blank"><?php include $imagePath . "images/siteicons/svg/web.php"; ?></a></span>
                            <?php
                        }
                    ?>
                </div>
                <?php
                    if ($subscription['notes'] != "") {
                        ?>
                            <div class="subscription-notes">
                                <span class="notes">
                                    <?php include $imagePath . "images/siteicons/svg/notes.php"; ?>
                                    <?= $subscription['notes'] ?>
                                </span>
                            </div>
                        <?php
                    }
                ?>    
            </div>
        <?php
        }
    }

    $query = "SELECT main_currency FROM user WHERE id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $mainCurrencyId = $row['main_currency'];

?>