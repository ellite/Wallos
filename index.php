<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';
require_once 'includes/logo_theme_variant.php';
require_once 'includes/upcoming_payments.php';
require_once 'includes/upcoming_cancellations.php';

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

// Get the first name of the user
$stmt = $db->prepare("SELECT username, firstname FROM user WHERE id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);
$first_name = $user['firstname'] ?? $user['username'] ?? '';

// Fetch the enabled subscriptions up for payment using the user's dashboard setting.
$upcomingSubscriptions = get_upcoming_payments(
    $db,
    $userId,
    $settings['upcoming_payments_limit'] ?? 3
);

// Fetch enabled subscriptions with manual renewal that are overdue
$stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive, auto_renew FROM subscriptions WHERE user_id = :userId AND next_payment < date('now') AND auto_renew = 0 AND inactive = 0 AND cycle != 5 ORDER BY next_payment ASC");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$overdueSubscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $overdueSubscriptions[] = $row;
}
$hasOverdueSubscriptions = !empty($overdueSubscriptions);

// Fetch the subscriptions whose cancellation reminder is still ahead (shared with
// the statistics page, so both stay in step).
$upcomingCancellations = get_upcoming_cancellations($db, $userId);
$hasUpcomingCancellations = !empty($upcomingCancellations);

require_once 'includes/stats_calculations.php';
require_once 'includes/widgets.php';

// Get AI Recommendations for user
$stmt = $db->prepare("SELECT * FROM ai_recommendations WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$aiRecommendations = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $aiRecommendations[] = $row;
}

// Payment methods for multi-instance payment_method_budget widgets
$pmStmt = $db->prepare('SELECT id, name, icon, enabled, budget FROM payment_methods WHERE user_id = :userId ORDER BY `order` ASC');
$pmStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$pmResult = $pmStmt->execute();
$pmRows = [];
while ($pmResult && ($pmRow = $pmResult->fetchArray(SQLITE3_ASSOC))) {
    $pmRows[] = $pmRow;
}

$categoryCostRows = [];
if (!empty($categoryCost)) {
    $categoryCostRows = wallos_build_category_cost_rows($categoryCost, 5);
}

?>

<section class="contain dashboard">
    <?php
        if ($isAdmin && $settings['update_notification']) {
            if (!is_null($settings['latest_version'])) {
                $latestVersion = $settings['latest_version'];
                if (version_compare($version, $latestVersion) == -1) {
                    ?>
                    <div class="update-banner">
                        <div class="update-banner-icon">
                            <i class="fa-solid fa-arrow-up"></i>
                        </div>
                        <div class="update-banner-text">
                            <strong><?= translate('new_version_available', $i18n) ?></strong>
                            <span>
                                <?= translate('current_version', $i18n) ?>: <?= htmlspecialchars($version) ?>
                                <i class="fa-solid fa-arrow-right"></i>
                                <b><?= htmlspecialchars($latestVersion) ?></b>
                            </span>
                        </div>
                        <a class="update-banner-link"
                            href="https://github.com/ellite/Wallos/releases/tag/<?= htmlspecialchars($latestVersion) ?>"
                            target="_blank" title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                            <?= translate('release_notes', $i18n) ?>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </div>
                    <?php
                }
            }
        }
        if ($demoMode) {
            ?>
            <div class="demo-banner">
            Running in <b>Demo Mode</b>, certain actions and settings are disabled.<br>
            The database will be reset every 120 minutes.
            </div>
            <?php
        }
    ?>
    <div class="dashboard-header-row">
        <h1><?= translate('hello', $i18n) ?> <?= htmlspecialchars($first_name) ?></h1>
        <div class="dashboard-edit-controls">
            <button type="button"
                    id="editDashboardWidgets"
                    class="image-button medium dashboard-edit-toggle"
                    title="<?= translate('edit_widgets', $i18n) ?>"
                    aria-label="<?= translate('edit_widgets', $i18n) ?>">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
            </button>
            <button type="button"
                    id="doneDashboardWidgets"
                    class="image-button medium dashboard-edit-toggle"
                    title="<?= translate('done_editing_widgets', $i18n) ?>"
                    aria-label="<?= translate('done_editing_widgets', $i18n) ?>">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <p class="dashboard-edit-hint"><?= translate('edit_widgets_hint', $i18n) ?></p>

    <?php require_once 'includes/dashboard_widgets_view.php'; ?>

</section>

<?php
// Get all subscriptions for user details lookup
$query = 'SELECT * FROM subscriptions WHERE user_id = :userId';
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
}
require_once 'includes/subscription_details_popup.php';
?>

<script src="scripts/libs/sortable.min.js?<?= $version ?>"></script>
<script src="scripts/dashboard.js?<?= $version ?>"></script>

<?php
require_once 'includes/footer.php';
?>
