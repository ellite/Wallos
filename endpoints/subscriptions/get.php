<?php
    require_once '../../includes/connect_endpoint.php';
    session_start();

    require_once '../../includes/currency_formatter.php';
    require_once '../../includes/getdbkeys.php';

    include_once '../../includes/list_subscriptions.php';

    require_once '../../includes/getsettings.php';

    $theme = "light";
    if (isset($settings['theme'])) {
      $theme = $settings['theme'];
    }

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        $sort = "next_payment";
        $sql = "SELECT * FROM subscriptions ORDER BY next_payment ASC";
        if (isset($_COOKIE['sortOrder']) && $_COOKIE['sortOrder'] != "") {
          $sort = $_COOKIE['sortOrder'];
          $allowedSortCriteria = ['name', 'id', 'next_payment', 'price', 'payer_user_id', 'category_id', 'payment_method_id'];
          $order = "ASC";
          if ($sort == "price" || $sort == "id") {
            $order = "DESC";
          }
          if (in_array($sort, $allowedSortCriteria)) {
            $sql = "SELECT * FROM subscriptions ORDER BY $sort $order";
          }
        }
        
        $result = $db->query($sql);
        if ($result) {
            $subscriptions = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $subscriptions[] = $row;
            }
        }

        $defaultLogo = $theme == "light" ? "images/wallos.png" : "images/walloswhite.png";
        foreach ($subscriptions as $subscription) {
          $id = $subscription['id'];
          $print[$id]['id'] = $id;
          $print[$id]['logo'] = $subscription['logo'] != "" ? "images/uploads/logos/".$subscription['logo'] : $defaultLogo;
          $print[$id]['name']= $subscription['name'];
          $cycle = $subscription['cycle'];
          $frequency = $subscription['frequency'];
          $print[$id]['billing_cycle'] = getBillingCycle($cycle, $frequency, $i18n);
          $paymentMethodId = $subscription['payment_method_id'];
          $print[$id]['currency_code'] = $currencies[$subscription['currency_id']]['code'];
          $currencyId = $subscription['currency_id'];
          $print[$id]['next_payment'] = date('M d, Y', strtotime($subscription['next_payment']));
          $print[$id]['payment_method_icon'] = "images/uploads/icons/" . $payment_methods[$paymentMethodId]['icon'];
          $print[$id]['payment_method_name'] = $payment_methods[$paymentMethodId]['name'];
          $print[$id]['payment_method_id'] = $paymentMethodId;
          $print[$id]['category_id'] = $subscription['category_id'];
          $print[$id]['payer_user_id'] = $subscription['payer_user_id'];
          $print[$id]['price'] = floatval($subscription['price']);
          $print[$id]['inactive'] = $subscription['inactive'];
          $print[$id]['url'] = $subscription['url'];
          $print[$id]['notes'] = $subscription['notes'];

          if (isset($settings['convertCurrency']) && $settings['convertCurrency'] === 'true' && $currencyId != $mainCurrencyId) {
            $print[$id]['price'] = getPriceConverted($print[$id]['price'], $currencyId, $db);
            $print[$id]['currency_code'] = $currencies[$mainCurrencyId]['code'];
          }
          if (isset($settings['showMonthlyPrice']) && $settings['showMonthlyPrice'] === 'true') {
            $print[$id]['price'] = getPricePerMonth($cycle, $frequency, $print[$id]['price']);
          }
        }

        if (isset($print)) {
          printSubscriptions($print, $sort, $categories, $members, $i18n);
        }
        
        if (count($subscriptions) == 0) {
            ?>
            <div class="empty-page">
                <img src="images/siteimages/empty.png" alt="<?= translate('empty_page', $i18n) ?>" />
                <p>
                  <?= translate('no_subscriptions_yet', $i18n) ?>
                </p>
                <button class="button" onClick="addSubscription()">
                  <img class="button-icon" src="images/siteicons/plusicon.png">
                  <?= translate('add_first_subscription', $i18n) ?>
                </button>
            </div>
            <?php
        }
    }

    $db->close();
?>