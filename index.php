<?php
  require_once 'includes/header.php';
  require_once 'includes/getdbkeys.php';

  include_once 'includes/list_subscriptions.php';

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

  $notificationsEnabled = false;
  $query = "SELECT enabled FROM notifications WHERE id = 1";
  $result = $db->query($query);
  if ($result) {
      $row = $result->fetchArray(SQLITE3_ASSOC);
      if ($row) {
          $notificationsEnabled = $row['enabled'];
      }
  }

  $headerClass = count($subscriptions) > 0 ? "main-actions" : "main-actions hidden";
  $defaultLogo = $theme == "light" ? "images/wallos.png" : "images/walloswhite.png";
?>
    <section class="contain">
      <header class="<?= $headerClass ?>" id="main-actions">
        <button class="button" onClick="addSubscription()">
          <img class="button-icon" src="images/siteicons/plusicon.png">
          New Subscription
        </button>
        <div class="sort-container">
          <button class="button" value="Sort" onClick="toggleSortOptions()" id="sort-button">
            <img src="images/siteicons/sort.png" class="button-icon" /> Sort
          </button>
          <div class="sort-options" id="sort-options">
            <ul>
              <li <?= $sort == "name" ? 'class="selected"' : "" ?> onClick="setSortOption('name')" id="sort-name">Name</li>
              <li <?= $sort == "id" ? 'class="selected"' : "" ?> onClick="setSortOption('id')" id="sort-id">Last Added</li>
              <li <?= $sort == "price" ? 'class="selected"' : "" ?> onClick="setSortOption('price')" id="sort-price">Price</li>
              <li <?= $sort == "next_payment" ? 'class="selected"' : "" ?> onClick="setSortOption('next_payment')" id="sort-next_payment">Next payment</li>
              <li <?= $sort == "payer_user_id" ? 'class="selected"' : "" ?> onClick="setSortOption('payer_user_id')" id="sort-payer_user_id">Member</li>
              <li <?= $sort == "category_id" ? 'class="selected"' : "" ?> onClick="setSortOption('category_id')" id="sort-category_id">Category</li>
              <li <?= $sort == "payment_method_id" ? 'class="selected"' : "" ?> onClick="setSortOption('payment_method_id')" id="sort-payment_method_id">Payment Method</li>
            </ul>
          </div>
        </div>
      </header>
      <div class="subscriptions" id="subscriptions">
        <?php
          foreach ($subscriptions as $subscription) {
            $id = $subscription['id'];
            $print[$id]['id'] = $id;
            $print[$id]['logo'] = $subscription['logo'] != "" ? "images/uploads/logos/".$subscription['logo'] : $defaultLogo;
            $print[$id]['name']= $subscription['name'];
            $cycle = $subscription['cycle'];
            $frequency = $subscription['frequency'];
            $print[$id]['billing_cycle'] = getBillingCycle($cycle, $frequency);
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
            $print[$id]['url'] = $subscription['url'];

            if (isset($_COOKIE['convertCurrency']) && $_COOKIE['convertCurrency'] === 'true' && $currencyId != $mainCurrencyId) {
              $print[$id]['price'] = getPriceConverted($print[$id]['price'], $currencyId, $db);
              $print[$id]['currency_code'] = $currencies[$mainCurrencyId]['code'];
            }
            if (isset($_COOKIE['showMonthlyPrice']) && $_COOKIE['showMonthlyPrice'] === 'true') {
              $print[$id]['price'] = getPricePerMonth($cycle, $frequency, $print[$id]['price']);
            }
          }

          if (isset($print)) {
            printSubscriptions($print, $sort, $categories, $members);
          }
          $db->close();

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
        ?>
      </div>

      <section class="subscription-form" id="subscription-form">
        <header>
          <h3 id="form-title">Add subscription</h3>
          <span class="fa-solid fa-xmark close-form" onClick="closeAddSubscription()"></span>
        </header>
        <form action="endpoints/subscription/add.php" method="post" id="subs-form">
          
          <div class="form-group-inline">
          <input type="text" id="name" name="name" placeholder="Subscription name" onchange="setSearchButtonStatus()" onkeypress="this.onchange();" onpaste="this.onchange();" oninput="this.onchange();" required>
            <label for="logo" class="logo-preview">
              <img src="" alt="Logo Preview" id="form-logo"> 
            </label>
            <input type="file" id="logo" name="logo" accept="image/jpeg, image/png" onchange="handleFileSelect(event)" class="hidden-input">
            <input type="hidden" id="logo-url" name="logo-url">
            <div id="logo-search-button" class="image-button medium disabled" title="Search logo on the web" onClick="searchLogo()">
              <img src="images/siteicons/websearch.png">
            </div>
            <input type="hidden" id="id" name="id">
            <div id="logo-search-results" class="logo-search">
              <header>
                Web search
                <span class="fa-solid fa-xmark close-logo-search" onClick="closeLogoSearch()"></span>
              </header>
              <div id="logo-search-images"></div>
            </div>
          </div>

          <div class="form-group-inline">
            <input type="number" step="0.01" id="price" name="price" placeholder="Price" required>
            <select id="currency" name="currency_id" placeholder="Currency">
              <?php
                foreach ($currencies as $currency) {
                  $selected = ($currency['id'] == $main_currency) ? 'selected' : '';
              ?>
                  <option value="<?= $currency['id'] ?>" <?= $selected ?>><?= $currency['name'] ?></option>
              <?php
                }
              ?>
            </select>
          </div>
          
          <div class="form-group">
            
          </div>
          
          <div class="form-group">
            <div class="inline">
              <div class="split66">
                <label for="cycle">Billing Cycle</label>
                <div class="inline">
                  <select id="frequency" name="frequency" placeholder="Frequency">
                  <?php
                      foreach ($frequencies as $frequency) {
                    ?>
                      <option value="<?= $frequency['id'] ?>"><?= $frequency['name'] ?></option>
                    <?php
                      }
                    ?>
                  </select>
                  <select id="cycle" name="cycle" placeholder="Cycle">
                  <?php
                      foreach ($cycles as $cycle) {
                    ?>
                      <option value="<?= $cycle['id'] ?>" <?= $cycle['id'] == 3 ? "selected" : "" ?>><?= $cycle['name'] ?></option>
                    <?php
                      }
                    ?>
                  </select>
                </div>
              </div>
              <div class="split33">
                <label for="next_payment">Next Payment</label>
                <input type="date" id="next_payment" name="next_payment" required>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="payment_method">Payment Method</label>
            <select id="payment_method" name="payment_method_id">
              <?php
                foreach ($payment_methods as $payment) {
              ?>
                <option value="<?= $payment['id'] ?>">
                  <?= $payment['name'] ?>
                </option>
              <?php
                }
              ?>
            </select>
          </div>

          <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category_id">
              <?php
                foreach ($categories as $category) {
              ?>
                <option value="<?= $category['id'] ?>">
                  <?= $category['name'] ?>
                </option>
              <?php
                }
              ?>
            </select>
          </div>
          

          <div class="form-group">
            <label for="payer_user">Paid by</label>
            <select id="payer_user" name="payer_user_id">
              <?php
                foreach ($members as $member) {
              ?>
                <option value="<?= $member['id'] ?>"><?= $member['name'] ?></option>
              <?php
                }
              ?>
            </select>
          </div>

          <div class="form-group">
            <input type="text" id="url" name="url" placeholder="URL">
          </div>

          <div class="form-group">
            <input type="text" id="notes" name="notes" placeholder="Notes">
          </div>

          <?php
            if ($notificationsEnabled) {
          ?>
          <div class="form-group-inline">
            <input type="checkbox" id="notifications" name="notifications">
            <label for="notifications">Enable Notifications for this subscription</label>
          </div>
          <?php
            }
          ?>

          <div class="buttons">
                <input type="button" value="Delete" class="warning-button left" id="deletesub" style="display: none">
                <input type="button" value="Cancel" class="secondary-button" onClick="closeAddSubscription()">
                <input type="submit" value="Save" id="save-button">
          </div>
        </form>
      </section>
    </section>
    <script src="scripts/dashboard.js"></script>

<?php
  require_once 'includes/footer.php';
?>
