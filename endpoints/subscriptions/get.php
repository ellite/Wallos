<?php
    require_once '../../includes/connect_endpoint.php';
    session_start();

    require_once '../../includes/getdbkeys.php';

    include_once '../../includes/list_subscriptions.php';

    $theme = "light";
    if (isset($_COOKIE['theme'])) {
      $theme = $_COOKIE['theme'];
    }

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        $sort = "next_payment";
        $sql = "SELECT * FROM subscriptions ORDER BY next_payment ASC";
        if (isset($_COOKIE['sortOrder']) && $_COOKIE['sortOrder'] != "") {
          $sort = $_COOKIE['sortOrder'];
          $allowedSortCriteria = ['name', 'id', 'next_payment', 'price', 'payer_user_id', 'category_id'];
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
          $print[$id]['billing_cycle'] = getBillingCycle($cycle, $frequency);
          $paymentMethodId = $subscription['payment_method_id'];
          $print[$id]['currency'] = $currencies[$subscription['currency_id']]['symbol'];
          $currencyId = $subscription['currency_id'];
          $print[$id]['next_payment'] = date('M d, Y', strtotime($subscription['next_payment']));
          $print[$id]['payment_method_icon'] = "images/uploads/icons/" . $payment_methods[$paymentMethodId]['icon'];
          $print[$id]['payment_method_name'] = $payment_methods[$paymentMethodId]['name'];
          $print[$id]['category_id'] = $subscription['category_id'];
          $print[$id]['payer_user_id'] = $subscription['payer_user_id'];
          $print[$id]['price'] = floatval($subscription['price']);

          if (isset($_COOKIE['convertCurrency']) && $_COOKIE['convertCurrency'] === 'true' && $currencyId != $mainCurrencyId) {
            $print[$id]['price'] = getPriceConverted($print[$id]['price'], $currencyId, $db);
            $print[$id]['currency'] = $currencies[$mainCurrencyId]['symbol'];
          }
          if (isset($_COOKIE['showMonthlyPrice']) && $_COOKIE['showMonthlyPrice'] === 'true') {
            $print[$id]['price'] = getPricePerMonth($cycle, $frequency, $print[$id]['price']);
          }
          $print[$id]['price'] = number_format($print[$id]['price'], 2, ".", ""); 
        }

        if (isset($print)) {
          printSubscriptons($print, $sort, $categories, $members);
        }
        
        if (count($subscriptions) == 0) {
            ?>
            <div class="empty-page">
                <img src="images/siteimages/empty.png" alt="Empty page" />
                <p>
                  You don't have any subscriptions yet
                </p>
                <button class="button" onClick="addSubscription()">
                  <img class="button-icon" src="images/siteicons/plusicon.png">
                  Add First Subscription
                </button>
            </div>
            <?php
        }
    }

    $db->close();
?>