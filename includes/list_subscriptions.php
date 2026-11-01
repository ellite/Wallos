<?php

require_once 'i18n/getlang.php';
require_once __DIR__ . '/currency_rates.php';

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
        case 5:
            return translate('One-time', $i18n);
    }
}

function getSubscriptionProgress($cycle, $frequency, $next_payment)
{
    if ($cycle === 5) {
        return 0;
    }

    $nextPaymentDate = new DateTime($next_payment);
    $currentDate = new DateTime((new DateTime('now'))->format('Y-m-d'));

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

    if ($paymentCycleDays <= 0) {
        return 0;
    }

    // next_payment can be many cycles away from today (a stale value, or
    // several missed renewal runs), so we can't always assume it's within a
    // single cycle of "now". Walk back however many whole cycles are needed
    // so the window we measure progress against is the one that actually
    // contains today.
    $daysUntilNextPayment = $currentDate->diff($nextPaymentDate)->days;
    $cyclesBack = $currentDate <= $nextPaymentDate
        ? max(1, (int) ceil($daysUntilNextPayment / $paymentCycleDays))
        : 1;

    $lastPaymentDate = clone $nextPaymentDate;
    $lastPaymentDate->modify('-' . ($cyclesBack * $paymentCycleDays) . ' days');

    $daysSinceLastPayment = $lastPaymentDate->diff($currentDate)->days;
    $subscriptionProgress = ($daysSinceLastPayment / $paymentCycleDays) * 100;

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
        case 5:
            return 0;
    }
}


function getPriceConverted($price, $currency, $database)
{
    return wallos_convert_price($price, $currency, $database);
}

/**
 * The value a subscription is ranked by when the list is sorted by price: its
 * price in the main currency, so AED 71.00 does not outrank GBP 55.00 just
 * because 71 > 55 (#861). Follows the monthly-price display setting, so the
 * order matches the figures that are shown.
 */
function getSortPrice($rawPrice, $currencyId, $mainCurrencyId, $cycle, $frequency, $settings, $database)
{
    $price = floatval($rawPrice);
    if ($currencyId != $mainCurrencyId) {
        $price = floatval(getPriceConverted($price, $currencyId, $database));
    }
    if (isset($settings['showMonthlyPrice']) && $settings['showMonthlyPrice'] === 'true') {
        $price = getPricePerMonth($cycle, $frequency, $price);
    }
    return $price;
}

/**
 * Orders the printable list by sort_price, highest first. usort() is stable,
 * so ties keep the order the database gave them.
 */
function sortSubscriptionsByPrice(&$print, $disabledToBottom)
{
    usort($print, function ($a, $b) use ($disabledToBottom) {
        if ($disabledToBottom) {
            $byInactive = ($a['inactive'] ?? 0) <=> ($b['inactive'] ?? 0);
            if ($byInactive !== 0) {
                return $byInactive;
            }
        }
        return ($b['sort_price'] ?? $b['price'] ?? 0) <=> ($a['sort_price'] ?? $a['price'] ?? 0);
    });
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

    // Try to create an IntlDateFormatter; if it fails, fallback to 'en'
    try {
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );

        if (!$formatter) {
            throw new Exception('Failed to create IntlDateFormatter with language: ' . $lang);
        }
    } catch (Throwable $e) {
        $lang = 'en'; // Fallback to English on error
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );
    }

    // Format the date
    $formattedDate = $formatter->format(new DateTime($date));

    return $formattedDate;
}

/**
 * Orders the list the way it is displayed and works out the section headings
 * that precede each subscription (lifetime purchases, or the category / payer /
 * payment-method group, depending on the sort). Shared by the server-rendered
 * list and the JSON endpoint so both stay in the same order.
 *
 * Each returned subscription gets a 'headings' array of plain-text titles.
 */
function prepareSubscriptionsForDisplay($subscriptions, $sort, $categories, $members, $i18n, $disabledToBottom)
{
    if ($sort === "price") {
        // Ranked by the main-currency value (sort_price), not the raw number (#861).
        sortSubscriptionsByPrice($subscriptions, $disabledToBottom === 'true');
    }

    // One-time purchases always go to the bottom regardless of sort order
    usort($subscriptions, fn($a, $b) => ($a['one_time'] ? 1 : 0) - ($b['one_time'] ? 1 : 0));

    $currentCategory = 0;
    $currentPayerUserId = 0;
    $currentPaymentMethodId = 0;
    $oneTimeSectionShown = false;
    foreach ($subscriptions as $index => $subscription) {
        $headings = [];
        if ($subscription['one_time'] && !$oneTimeSectionShown) {
            $headings[] = translate('lifetime_purchases', $i18n);
            $oneTimeSectionShown = true;
        }
        if ($sort == "category_id" && $subscription['category_id'] != $currentCategory) {
            $headings[] = $subscription['category_id'] == 1
                ? translate('no_category', $i18n)
                : ($categories[$subscription['category_id'] ?? ""]['name'] ?? "");
            $currentCategory = $subscription['category_id'];
        }
        if ($sort == "payer_user_id" && $subscription['payer_user_id'] != $currentPayerUserId) {
            $headings[] = $members[$subscription['payer_user_id'] ?? ""]['name'] ?? "";
            $currentPayerUserId = $subscription['payer_user_id'];
        }
        if ($sort == "payment_method_id" && $subscription['payment_method_id'] != $currentPaymentMethodId) {
            $headings[] = $subscription['payment_method_name'];
            $currentPaymentMethodId = $subscription['payment_method_id'];
        }
        $subscriptions[$index]['headings'] = $headings;
    }

    return $subscriptions;
}

/**
 * Names and labels reach the templates as HTML (they are stored HTML-escaped and
 * echoed as-is). JSON carries plain text, which the client escapes once itself.
 */
function htmlToText($value)
{
    return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * The list as plain data, for the JSON endpoint: the same order and headings as
 * printSubscriptions(), with display strings (formatted price and date, resolved
 * titles) already worked out so the client only has to lay them out.
 */
function buildSubscriptionsPayload($subscriptions, $sort, $categories, $members, $i18n, $disabledToBottom, $showSubscriptionProgress, $currencies, $lang)
{
    $items = [];
    foreach (prepareSubscriptionsForDisplay($subscriptions, $sort, $categories, $members, $i18n, $disabledToBottom) as $subscription) {
        $oneTime = (bool) $subscription['one_time'];
        $autoRenew = (int) $subscription['auto_renew'] === 1;
        $hasOriginal = isset($subscription['original_price']) && $subscription['original_price'] != $subscription['price'];

        $items[] = [
            'id' => (int) $subscription['id'],
            'name' => htmlToText($subscription['name']),
            'headings' => array_map('htmlToText', $subscription['headings']),
            'logo' => $subscription['logo'] !== "" ? $subscription['logo'] : null,
            'logo_variant' => $subscription['logo_variant'] ?? null,
            'logo_text_color' => $subscription['logo_text_color'] ?? null,
            'inactive' => (bool) $subscription['inactive'],
            'one_time' => $oneTime,
            'auto_renew' => $autoRenew,
            'can_renew' => !$autoRenew && !$oneTime,
            'billing_cycle' => htmlToText($subscription['billing_cycle']),
            'cycle_title' => htmlToText($oneTime
                ? $subscription['billing_cycle']
                : ($subscription['auto_renew'] ? translate("automatically_renews", $i18n) : translate("manual_renewal", $i18n))),
            'next_payment' => formatDate($subscription['next_payment'], $lang),
            'price' => formatPrice($subscription['price'], $subscription['currency_code'], $currencies),
            'original_price' => $hasOriginal
                ? formatPrice($subscription['original_price'], $subscription['original_currency_code'], $currencies)
                : null,
            'payment_method_icon' => $subscription['payment_method_icon'] !== "" ? $subscription['payment_method_icon'] : null,
            'payment_method_name' => htmlToText($subscription['payment_method_name']),
            'progress' => ($showSubscriptionProgress === 'true' && !$subscription['inactive'])
                ? min(100, $subscription['progress'])
                : null,
        ];
    }

    return $items;
}

function printSubscriptions($subscriptions, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang)
{
    $subscriptions = prepareSubscriptionsForDisplay($subscriptions, $sort, $categories, $members, $i18n, $disabledToBottom);

    foreach ($subscriptions as $subscription) {
        foreach ($subscription['headings'] as $heading) {
            ?>
            <div class="subscription-list-title">
                <?= $heading ?>
            </div>
            <?php
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
                    if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
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

            <label class="bulk-select">
                <input type="checkbox" class="bulk-select-checkbox" value="<?= $subscription['id'] ?>"
                    onChange="updateBulkSelection()">
            </label>
            <div class="subscription<?= $subscriptionExtraClasses ?>"
                onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>"
                data-name="<?= $subscription['name'] ?>">
                <div class="subscription-main">
                    <span class="logo <?= !$hasLogo ? 'hideOnMobile' : '' ?>">
                        <?php
                        if ($hasLogo) {
                            echo renderThemedLogoImg($subscription['logo'], $subscription['logo_variant'] ?? null, $subscription['logo_text_color'] ?? null);
                        } else {
                            include $imagePath . "images/siteicons/svg/logo.php";
                        }
                        ?>
                    </span>
                    <span class="name <?= $hasLogo ? 'hideOnMobile' : '' ?>"><?= $subscription['name'] ?></span>
                    <span class="cycle"
                        title="<?= $subscription['one_time'] ? $subscription['billing_cycle'] : ($subscription['auto_renew'] ? translate("automatically_renews", $i18n) : translate("manual_renewal", $i18n)) ?>">
                        <?php
                        if (!$subscription['one_time']) {
                            if ($subscription['auto_renew']) {
                                include $imagePath . "images/siteicons/svg/automatic.php";
                            } else {
                                include $imagePath . "images/siteicons/svg/manual.php";
                            }
                        }
                        ?>
                        <?= $subscription['billing_cycle'] ?>
                    </span>
                    <span class="next"><?= formatDate($subscription['next_payment'], $lang) ?></span>
                    <span class="price">
                        <span class="value">
                            <?= formatPrice($subscription['price'], $subscription['currency_code'], $currencies) ?>
                            <?php
                            if (isset($subscription['original_price']) && $subscription['original_price'] != $subscription['price']) {
                                ?>
                                <span
                                    class="original_price">(<?= formatPrice($subscription['original_price'], $subscription['original_currency_code'], $currencies) ?>)</span>
                                <?php
                            }
                            ?>
                        </span>

                    </span>
                    <span class="payment_method">
                        <?php if (!empty($subscription['payment_method_icon'])) { ?>
                        <img src="<?= $subscription['payment_method_icon'] ?>"
                            title="<?= translate('payment_method', $i18n) ?>: <?= $subscription['payment_method_name'] ?>" />
                        <?php } ?>
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
                            <i class="fa-solid fa-pen-to-square"></i>
                            <?= translate('edit_subscription', $i18n) ?>
                        </li>
                        <li class="delete" title="<?= translate('delete', $i18n) ?>"
                            onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-trash-can"></i>
                            <?= translate('delete', $i18n) ?>
                        </li>
                        <li class="clone" title="<?= translate('clone', $i18n) ?>"
                            onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-copy"></i>
                            <?= translate('clone', $i18n) ?>
                        </li>
                        <?php
                        if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
                            ?>
                            <li class="renew" title="<?= translate('renew', $i18n) ?>"
                                onClick="renewSubscription(event, <?= $subscription['id'] ?>)">
                                <i class="fa-solid fa-rotate-right"></i>
                                <?= translate('renew', $i18n) ?>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                </div>
                <div class="subscription-back" inert>
                    <div class="subscription-back-header">
                        <span class="subscription-back-title"><?= $subscription['name'] ?></span>
                        <button type="button" class="subscription-back-close"
                            onClick="event.stopPropagation(); unflipCard(<?= $subscription['id'] ?>)"
                            title="<?= translate('cancel', $i18n) ?>">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <button type="button" class="back-action"
                        onClick="unflipCard(<?= $subscription['id'] ?>); openEditSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <?= translate('edit', $i18n) ?>
                    </button>
                    <button type="button" class="back-action"
                        onClick="unflipCard(<?= $subscription['id'] ?>); cloneSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-copy"></i>
                        <?= translate('clone', $i18n) ?>
                    </button>
                    <?php
                    if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
                        ?>
                        <button type="button" class="back-action"
                            onClick="unflipCard(<?= $subscription['id'] ?>); renewSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-rotate-right"></i>
                            <?= translate('renew', $i18n) ?>
                        </button>
                        <?php
                    }
                    ?>
                    <button type="button" class="back-action delete"
                        onClick="unflipCard(<?= $subscription['id'] ?>); deleteSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-trash-can"></i>
                        <?= translate('delete', $i18n) ?>
                    </button>
                </div>
            </div>
            <?php
            if ($showSubscriptionProgress === 'true' && !$subscription['inactive']) {
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