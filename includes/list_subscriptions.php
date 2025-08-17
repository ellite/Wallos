<?php

require_once 'i18n/getlang.php';

function getBillingCycle($cycle, $frequency, $i18n)
{
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

function getSubscriptionProgress($cycle, $frequency, $next_payment)
{
    $nextPaymentDate = new DateTime($next_payment);
    $currentDate = new DateTime('now');

    $paymentCycleDays = 30; // Default to monthly
    if ($cycle === 1) {
        $paymentCycleDays = 1 * $frequency;
    } else if ($cycle === 2) {
        $paymentCycleDays = 7 * $frequency;
    } else if ($cycle === 3) {
        $paymentCycleDays = 30 * $frequency;
    } else if ($cycle === 4) {
        $paymentCycleDays = 365 * $frequency;
    }

    $lastPaymentDate = clone $nextPaymentDate;
    $lastPaymentDate->modify("-$paymentCycleDays days");

    $totalCycleDays = $lastPaymentDate->diff($nextPaymentDate)->days;
    $daysSinceLastPayment = $lastPaymentDate->diff($currentDate)->days;

    $subscriptionProgress = 0;
    if ($totalCycleDays > 0) {
        $subscriptionProgress = ($daysSinceLastPayment / $totalCycleDays) * 100;
    }

    return floor($subscriptionProgress);
}

function getPricePerMonth($cycle, $frequency, $price)
{
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

function getPricePerWeek($cycle, $frequency, $price)
{
    switch ($cycle) {
        case 1: // Days
            $numberOfPaymentsPerWeek = (7 / $frequency);
            return $price * $numberOfPaymentsPerWeek;
        case 2: // Weeks
            $numberOfPaymentsPerWeek = (1 / $frequency);
            return $price * $numberOfPaymentsPerWeek;
        case 3: // Months
            $numberOfWeeksPerMonth = 4.35;
            $numberOfPaymentsPerWeek = (1 / ($frequency * $numberOfWeeksPerMonth));
            return $price * $numberOfPaymentsPerWeek;
        case 4: // Years
            $numberOfWeeksPerYear = 52;
            $numberOfPaymentsPerWeek = (1 / ($frequency * $numberOfWeeksPerYear));
            return $price * $numberOfPaymentsPerWeek;
    }
}

function getPricePerYear($cycle, $frequency, $price)
{
    switch ($cycle) {
        case 1: // Days
            $numberOfPaymentsPerYear = (365 / $frequency);
            return $price * $numberOfPaymentsPerYear;
        case 2: // Weeks
            $numberOfPaymentsPerYear = (52 / $frequency);
            return $price * $numberOfPaymentsPerYear;
        case 3: // Months
            $numberOfPaymentsPerYear = (12 / $frequency);
            return $price * $numberOfPaymentsPerYear;
        case 4: // Years
            $numberOfPaymentsPerYear = (1 / $frequency);
            return $price * $numberOfPaymentsPerYear;
    }
}

function getCycleShortNotation($cycle, $frequency)
{
    switch ($cycle) {
        case 1:
            return $frequency == 1 ? 'd' : $frequency . 'd';
        case 2:
            return $frequency == 1 ? 'w' : $frequency . 'w';
        case 3:
            return $frequency == 1 ? 'm' : $frequency . 'm';
        case 4:
            return $frequency == 1 ? 'y' : $frequency . 'y';
    }
}


function getPriceConverted($price, $currency, $database)
{
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

function formatPrice($price, $currencyCode, $currencies)
{
    $formattedPrice = CurrencyFormatter::format($price, $currencyCode);
    if (strstr($formattedPrice, $currencyCode)) {
        $symbol = $currencyCode;
        
        foreach ($currencies as $currency) {

            if ($currency['code'] === $currencyCode) {
                if ($currency['symbol'] != "") {
                    $symbol = $currency['symbol'];
                }
                break;
            }
        }
        $formattedPrice = str_replace($currencyCode, $symbol, $formattedPrice);
    }

    return $formattedPrice;
}

function formatDate($date, $lang = 'en')
{
    $currentYear = date('Y');
    $dateYear = date('Y', strtotime($date));

    // Determine the date format based on whether the year matches the current year
    $dateFormat = ($currentYear == $dateYear) ? 'MMM d' : 'MMM yyyy';

    // Validate the locale and fallback to 'en' if unsupported
    if (!in_array($lang, ResourceBundle::getLocales(''))) {
        $lang = 'en'; // Fallback to English
    }

    // Create an IntlDateFormatter instance for the specified language
    $formatter = new IntlDateFormatter(
        $lang,
        IntlDateFormatter::SHORT,
        IntlDateFormatter::NONE,
        null,
        null,
        $dateFormat
    );

    // Format the date
    $formattedDate = $formatter->format(new DateTime($date));

    return $formattedDate;
}

function printSubscriptions($subscriptions, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang)
{
    if ($sort === "price") {
        usort($subscriptions, function ($a, $b) {
            return $a['price'] < $b['price'] ? 1 : -1;
        });
        if ($disabledToBottom === 'true') {
            usort($subscriptions, function ($a, $b) {
                return $a['inactive'] - $b['inactive'];
            });
        }
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
        <div class="subscription-container">
            <?php
            if ($mobileNavigation === 'true') {
                ?>
                <div class="mobile-actions" data-id="<?= $subscription['id'] ?>">
                    <button class="mobile-action-clone"></button>
                    <button class="mobile-action-clone" onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/clone.php"; ?>
                        Clone
                    </button>
                    <button class="mobile-action-delete" onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/delete.php"; ?>
                        Delete
                    </button>
                    <?php
                    if ($subscription['auto_renew'] != 1) {
                        ?>
                        <button class="mobile-action-renew" onClick="renewSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/mobile-menu/renew.php"; ?>
                            Renew
                        </button>
                        <?php
                    }
                    ?>
                    <button class="mobile-action-edit" onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/edit.php"; ?>
                        Edit
                    </button>
                </div>
                <?php
            }

            $subscriptionExtraClasses = "";
            if ($subscription['inactive']) {
                $subscriptionExtraClasses .= " inactive";
            }
            if ($subscription['auto_renew'] != 1) {
                $subscriptionExtraClasses .= " manual";
            }

            $hasLogo = false;
            if ($subscription['logo'] != "") {
                $hasLogo = true;
            }

            ?>

            <div class="subscription<?= $subscriptionExtraClasses ?>"
                onClick="toggleOpenSubscription(<?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>"
                data-name="<?= $subscription['name'] ?>">
                <div class="subscription-main">
                    <span class="logo <?= !$hasLogo ? 'hideOnMobile' : '' ?>">
                        <?php
                        if ($hasLogo) {
                            ?>
                            <img src="<?= $subscription['logo'] ?>">
                            <?php
                        } else {
                            include $imagePath . "images/siteicons/svg/logo.php";
                        }
                        ?>
                    </span>
                    <span class="name <?= $hasLogo ? 'hideOnMobile' : '' ?>"><?= $subscription['name'] ?></span>
                    <span class="cycle"
                        title="<?= $subscription['auto_renew'] ? translate("automatically_renews", $i18n) : translate("manual_renewal", $i18n) ?>">
                        <?php
                        if ($subscription['auto_renew']) {
                            include $imagePath . "images/siteicons/svg/automatic.php";
                        } else {
                            include $imagePath . "images/siteicons/svg/manual.php";
                        }
                        ?>
                        <?= $subscription['billing_cycle'] ?>
                    </span>
                    <span class="next"><?= formatDate($subscription['next_payment'], $lang) ?></span>
                    <span class="price">
                        <span class="value">
                            <?= number_format($subscription['price'], 2) ?>
                            <?php
                            if (isset($subscription['original_price']) && isset($subscription['display_period'])) {
                                // Check if we need to show original price
                                $originalCycleNotation = getCycleShortNotation($subscription['original_cycle'], $subscription['original_frequency']);
                                $selectedPeriodShort = substr($subscription['display_period'], 0, 1); // w, m, y
                                
                                $showOriginal = false;
                                $originalPriceText = '';
                                
                                // Show original if currency differs OR billing cycle differs from selected period
                                if ($subscription['original_currency_code'] != $subscription['currency_code'] || 
                                    $originalCycleNotation != $selectedPeriodShort) {
                                    $showOriginal = true;
                                    $originalPriceText = formatPrice($subscription['original_price'], $subscription['original_currency_code'], $currencies);
                                    
                                    // Add cycle notation if different from selected period
                                    if ($originalCycleNotation != $selectedPeriodShort) {
                                        $originalPriceText .= ' @ ' . $originalCycleNotation;
                                    }
                                }
                                
                                if ($showOriginal) {
                                ?>
                                <span class="original_price">(<?= $originalPriceText ?>)</span>
                                <?php
                                }
                            }
                            ?>
                        </span>

                    </span>
                    <span class="payment_method">
                        <img src="<?= $subscription['payment_method_icon'] ?>"
                            title="<?= translate('payment_method', $i18n) ?>: <?= $subscription['payment_method_name'] ?>" />
                    </span>
                    <?php
                    $desktopMenuButtonClass = ""; {
                    }
                    if ($mobileNavigation === "true") {
                        $desktopMenuButtonClass = "mobileNavigationHideOnMobile";
                    }
                    ?>
                    <button type="button" class="actions-expand <?= $desktopMenuButtonClass ?>"
                        onClick="expandActions(event, <?= $subscription['id'] ?>)">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="actions">
                        <li class="edit" title="<?= translate('edit_subscription', $i18n) ?>"
                            onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/edit.php"; ?>
                            <?= translate('edit_subscription', $i18n) ?>
                        </li>
                        <li class="delete" title="<?= translate('delete', $i18n) ?>"
                            onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/delete.php"; ?>
                            <?= translate('delete', $i18n) ?>
                        </li>
                        <li class="clone" title="<?= translate('clone', $i18n) ?>"
                            onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/clone.php"; ?>
                            <?= translate('clone', $i18n) ?>
                        </li>
                        <?php
                        if ($subscription['auto_renew'] != 1) {
                            ?>
                            <li class="renew" title="<?= translate('renew', $i18n) ?>"
                                onClick="renewSubscription(event, <?= $subscription['id'] ?>)">
                                <?php include $imagePath . "images/siteicons/svg/renew.php"; ?>
                                <?= translate('renew', $i18n) ?>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                </div>
                <div class="subscription-secondary">
                    <span
                        class="name"><?php include $imagePath . "images/siteicons/svg/subscription.php"; ?><?= $subscription['name'] ?></span>
                    <span class="payer_user"
                        title="<?= translate('paid_by', $i18n) ?>"><?php include $imagePath . "images/siteicons/svg/payment.php"; ?><?= $members[$subscription['payer_user_id']]['name'] ?></span>
                    <span class="category"
                        title="<?= translate('category', $i18n) ?>"><?php include $imagePath . "images/siteicons/svg/category.php"; ?><?= $categories[$subscription['category_id']]['name'] ?></span>
                    <?php
                    if ($subscription['url'] != "") {
                        $url = $subscription['url'];
                        if (!preg_match('/^https?:\/\//', $url)) {
                            $url = "https://" . $url;
                        }
                        ?>
                        <span class="url" title="<?= translate('external_url', $i18n) ?>"><a href="<?= $url ?>" target="_blank"
                                rel="noreferrer"><?php include $imagePath . "images/siteicons/svg/web.php"; ?></a></span>
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
            if ($showSubscriptionProgress === 'true') {
                $progress = $subscription['progress'] > 100 ? 100 : $subscription['progress'];
                ?>
                <div class="subscription-progress-container">
                    <span class="subscription-progress" style="width: <?= $progress ?>%;"></span>
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
if ($row !== false) {
    $mainCurrencyId = $row['main_currency'];
} else {
    $mainCurrencyId = $currencies[1]['id'];
}

?>