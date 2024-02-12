<?php

    require_once 'i18n/getlang.php';

    function getBillingCycle($cycle, $frequency, $i18n) {
        switch ($cycle) {
        case 1:
            return $frequency == 1 ? translate('Daily', $i18n) : $frequency . " " . translate('days', $i18n);
            break;
        case 2:
            return $frequency == 1 ? translate('Weekly', $i18n) : $frequency . " " . translate('weeks', $i18n);
            break;
        case 3:
            return $frequency == 1 ? translate('Monthly', $i18n) : $frequency . " " . translate('months', $i18n);
            break;
        case 4:
            return $frequency == 1 ? translate('Yearly', $i18n) : $frequency . " " . translate('years', $i18n);
            break;  
        }
    }

    function getPricePerMonth($cycle, $frequency, $price) {
        switch ($cycle) {
        case 1:
            $numberOfPaymentsPerMonth = (30 / $frequency); 
            return $price * $numberOfPaymentsPerMonth;
            break;
        case 2:
            $numberOfPaymentsPerMonth = (4.35 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
            break;
        case 3:
            $numberOfPaymentsPerMonth = (1 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
            break;
        case 4:
            $numberOfMonths = (12 * $frequency);
            return $price / $numberOfMonths;
            break;  
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

    function printSubscriptions($subscriptions, $sort, $categories, $members, $i18n) {
        if ($sort === "price") {
            usort($subscriptions, function($a, $b) {
                return $a['price'] < $b['price'] ? 1 : -1;
            });
        }

        usort($subscriptions, function ($a, $b) {
           if ($a['activated'] == $b['activated']) {
               return 0;
           }
           return $a['activated'] ? -1 : 1;
        });

        $currentCategory = 0;
        $currentPayerUserId = 0;
        $currentPaymentMethodId = 0;
        foreach ($subscriptions as $subscription) {
            if ($sort == "category_id" && $subscription['category_id'] != $currentCategory) {
                ?>
                    <div class="subscription-list-title">
                        <?= $categories[$subscription['category_id']]['name'] ?>
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
            <div class="subscription<?= $subscription['activated'] ? '' : ' inactive' ?>" onClick="toggleOpenSubscription(<?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                <div class="subscription-main">
                    <span class="logo"><img src="<?= $subscription['logo'] ?>"></span>
                    <span class="name"><?= $subscription['name'] ?></span>
                    <span class="cycle"><?= $subscription['billing_cycle'] ?></span>
                    <span class="next"><?= $subscription['next_payment'] ?></span>
                    <span class="price">
                    <img src="<?= $subscription['payment_method_icon'] ?>" title="<?= translate('payment_method', $i18n) ?>: <?= $subscription['payment_method_name'] ?>"/>
                    <?= CurrencyFormatter::format($subscription['price'], $subscription['currency_code']) ?>
                    </span>
                    <span class="actions">
                    <button class="image-button medium" onClick="openEditSubscription(event, <?= $subscription['id'] ?>)" name="edit">
                        <img src="images/siteicons/edit.png" title="<?= translate('edit_subscription', $i18n) ?>">
                    </button>
                    </span>
                </div>
                <div class="subscription-secondary">
                    <span class="name"><img src="images/siteicons/subscription.png" alt="<?= translate('subscription', $i18n) ?>" /><?= $subscription['name'] ?></span>
                    <span class="payer_user" title="<?= translate('paid_by', $i18n) ?>"><img src="images/siteicons/payment.png" alt="<?= translate('paid_by', $i18n) ?>" /><?= $members[$subscription['payer_user_id']]['name'] ?></span>
                    <?php
                    if (strlen($subscription['notes']) > 0) {
                        ?>
                        <span class="notes" title="<?= translate('notes', $i18n) ?>"><img src="images/siteicons/notepad-text.png" alt="<?= translate('notes', $i18n) ?>" /><?= $subscription['notes'] ?></span>
                        <?php
                    }
                    ?>
                    <span class="category" title="<?= translate('category', $i18n) ?>" ><img src="images/siteicons/category.png" alt="<?= translate('category', $i18n) ?>" /><?= $categories[$subscription['category_id']]['name'] ?></span>
                    <?php
                        if ($subscription['url'] != "") {
                            $url = $subscription['url'];
                            if (!preg_match('/^https?:\/\//', $url)) {
                                $url = "https://" . $url;
                            }
                            ?>
                                <span class="url" title="<?= translate('external_url', $i18n) ?>"><a href="<?= $url ?>" target="_blank"><img src="images/siteicons/web.png" alt="<?= translate('url', $i18n) ?>" /></a></span>
                            <?php
                        }
                    ?>
                </div>
            </div>
        <?php
        }
    }

    $query = "SELECT main_currency FROM user WHERE id = 1";
    $stmt = $db->prepare($query);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $mainCurrencyId = $row['main_currency'];

?>