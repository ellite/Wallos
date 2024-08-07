<?php
require_once '../../includes/connect_endpoint.php';

require_once '../../includes/currency_formatter.php';
require_once '../../includes/getdbkeys.php';

include_once '../../includes/list_subscriptions.php';

require_once '../../includes/getsettings.php';

$theme = "light";
if (isset($settings['theme'])) {
  $theme = $settings['theme'];
}

$colorTheme = "blue";
if (isset($settings['color_theme'])) {
  $colorTheme = $settings['color_theme'];
}

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {


  $sort = "next_payment";
  $sortOrder = $sort;
  $order = "ASC";

  $params = array();
  $sql = "SELECT * FROM subscriptions WHERE user_id = :userId";

  if (isset($_GET['category']) && $_GET['category'] != "") {
    $sql .= " AND category_id = :category";
    $params[':category'] = $_GET['category'];
  }

  if (isset($_GET['payment']) && $_GET['payment'] != "") {
    $sql .= " AND payment_method_id = :payment";
    $params[':payment'] = $_GET['payment'];
  }

  if (isset($_GET['member']) && $_GET['member'] != "") {
    $sql .= " AND payer_user_id = :member";
    $params[':member'] = $_GET['member'];
  }

  if (isset($_GET['state']) && $_GET['state'] != "") {
    $sql .= " AND inactive = :inactive";
    $params[':inactive'] = $_GET['state'];
  }

  if (isset($_COOKIE['sortOrder']) && $_COOKIE['sortOrder'] != "") {
    $sort = $_COOKIE['sortOrder'];
    $allowedSortCriteria = ['name', 'id', 'next_payment', 'price', 'payer_user_id', 'category_id', 'payment_method_id', 'inactive', 'alphanumeric'];
    $order = ($sort == "price" || $sort == "id") ? "DESC" : "ASC";

    if ($sort == "alphanumeric") {
      $sort = "name";
    }

    if (!in_array($sort, $allowedSortCriteria)) {
      $sort = "next_payment";
    }

    $orderByClauses = [];

    if ($settings['disabledToBottom'] === 'true') {
      if (in_array($sort, ["payer_user_id", "category_id", "payment_method_id"])) {
        $orderByClauses[] = "$sort $order";
        $orderByClauses[] = "inactive ASC";
      } else {
        $orderByClauses[] = "inactive ASC";
        $orderByClauses[] = "$sort $order";
      }
    } else {
      $orderByClauses[] = "$sort $order";
      if ($sort != "inactive") {
        $orderByClauses[] = "inactive ASC";
      }
    }

    if ($sort != "next_payment") {
      $orderByClauses[] = "next_payment ASC";
    }

    $sql .= " ORDER BY " . implode(", ", $orderByClauses);
  }

  $stmt = $db->prepare($sql);
  $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }

  $result = $stmt->execute();
  if ($result) {
    $subscriptions = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      $subscriptions[] = $row;
    }
  }

  foreach ($subscriptions as $subscription) {
    if ($subscription['inactive'] == 1 && isset($settings['hideDisabledSubscriptions']) && $settings['hideDisabledSubscriptions'] === 'true') {
      continue;
    }
    $id = $subscription['id'];
    $print[$id]['id'] = $id;
    $print[$id]['logo'] = $subscription['logo'] != "" ? "images/uploads/logos/" . $subscription['logo'] : "";
    $print[$id]['name'] = htmlspecialchars_decode($subscription['name'] ?? "");
    $cycle = $subscription['cycle'];
    $frequency = $subscription['frequency'];
    $print[$id]['billing_cycle'] = getBillingCycle($cycle, $frequency, $i18n);
    $paymentMethodId = $subscription['payment_method_id'];
    $print[$id]['currency_code'] = $currencies[$subscription['currency_id']]['code'];
    $currencyId = $subscription['currency_id'];
    $print[$id]['next_payment'] = date('M d, Y', strtotime($subscription['next_payment']));
    $paymentIconFolder = (strpos($payment_methods[$paymentMethodId]['icon'], 'images/uploads/icons/') !== false) ? "" : "images/uploads/logos/";
    $print[$id]['payment_method_icon'] = $paymentIconFolder . $payment_methods[$paymentMethodId]['icon'];
    $print[$id]['payment_method_name'] = $payment_methods[$paymentMethodId]['name'];
    $print[$id]['payment_method_id'] = $paymentMethodId;
    $print[$id]['category_id'] = $subscription['category_id'];
    $print[$id]['payer_user_id'] = $subscription['payer_user_id'];
    $print[$id]['price'] = floatval($subscription['price']);
    $print[$id]['inactive'] = $subscription['inactive'];
    $print[$id]['url'] = htmlspecialchars_decode($subscription['url'] ?? "");
    $print[$id]['notes'] = htmlspecialchars_decode($subscription['notes'] ?? "");

    if (isset($settings['convertCurrency']) && $settings['convertCurrency'] === 'true' && $currencyId != $mainCurrencyId) {
      $print[$id]['price'] = getPriceConverted($print[$id]['price'], $currencyId, $db);
      $print[$id]['currency_code'] = $currencies[$mainCurrencyId]['code'];
    }
    if (isset($settings['showMonthlyPrice']) && $settings['showMonthlyPrice'] === 'true') {
      $print[$id]['price'] = getPricePerMonth($cycle, $frequency, $print[$id]['price']);
    }
  }

  if ($sortOrder == "alphanumeric") {
    usort($print, function ($a, $b) {
      return strnatcmp(strtolower($a['name']), strtolower($b['name']));
    });
    if ($settings['disabledToBottom'] === 'true') {
      usort($print, function ($a, $b) {
        return $a['inactive'] - $b['inactive'];
      });
    }
  }

  if (isset($print)) {
    printSubscriptions($print, $sort, $categories, $members, $i18n, $colorTheme, "../../", $settings['disabledToBottom']);
  }

  if (count($subscriptions) == 0) {
    ?>
    <div class="no-matching-subscriptions">
      <p>
        <?= translate('no_matching_subscriptions', $i18n) ?>
      </p>
      <button class="button" onClick="clearFilters()">
        <span clasS="fa-solid fa-minus-circle"></span>
        <?= translate('clear_filters', $i18n) ?>
      </button>
      <img src="images/siteimages/empty.png" alt="<?= translate('empty_page', $i18n) ?>" />
    </div>
    <?php
  }
}

$db->close();
?>