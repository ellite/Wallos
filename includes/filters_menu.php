<div class="filtermenu-content">
  <?php
  if (count($members) > 1) {
    ?>
    <div class="filtermenu-submenu">
      <div class="filter-title" onClick="toggleSubMenu('member')"><?= translate("member", $i18n) ?></div>
      <div class="filtermenu-submenu-content" id="filter-member">
        <?php
        foreach ($members as $member) {
          if ($member['count'] == 0) {
            continue;
          }
          $selectedClass = '';
          if (isset($_GET['member'])) {
            $memberIds = explode(',', $_GET['member']);
            if (in_array($member['id'], $memberIds)) {
              $selectedClass = 'selected';
            }
          }
          ?>
          <div class="filter-item <?= $selectedClass ?>" data-memberid="<?= $member['id'] ?>"><?= $member['name'] ?>
          </div>
          <?php
        }
        ?>
      </div>
    </div>
    <?php
  }
  ?>
  <?php
  if (count($categories) > 1) {
    ?>
    <div class="filtermenu-submenu">
      <div class="filter-title" onClick="toggleSubMenu('category')"><?= translate("category", $i18n) ?></div>
      <div class="filtermenu-submenu-content" id="filter-category">
        <?php
        foreach ($categories as $category) {
          if ($category['count'] == 0) {
            continue;
          }
          if ($category['name'] == "No category") {
            $category['name'] = translate("no_category", $i18n);
          }
          $selectedClass = '';
          if (isset($_GET['category'])) {
            $categoryIds = explode(',', $_GET['category']);
            if (in_array($category['id'], $categoryIds)) {
              $selectedClass = 'selected';
            }
          }
          ?>
          <div class="filter-item <?= $selectedClass ?>" data-categoryid="<?= $category['id'] ?>">
            <?= $category['name'] ?>
          </div>
          <?php
        }
        ?>
      </div>
    </div>
    <?php
  }
  ?>
  <?php
  if (count($payment_methods) > 1) {
    ?>
    <div class="filtermenu-submenu">
      <div class="filter-title" onClick="toggleSubMenu('payment')"><?= translate("payment_method", $i18n) ?></div>
      <div class="filtermenu-submenu-content" id="filter-payment">
        <?php
        foreach ($payment_methods as $payment) {
          if ($payment['count'] == 0) {
            continue;
          }
          $selectedClass = '';
          if (isset($_GET['payment'])) {
            $paymentIds = explode(',', $_GET['payment']);
            if (in_array($payment['id'], $paymentIds)) {
              $selectedClass = 'selected';
            }
          }
          ?>
          <div class="filter-item <?= $selectedClass ?>" data-paymentid="<?= $payment['id'] ?>">
            <?= $payment['name'] ?>
          </div>
          <?php
        }
        ?>
      </div>
    </div>
    <?php
  }
  ?>
  <?php
  if (!isset($settings['hideDisabledSubscriptions']) || $settings['hideDisabledSubscriptions'] !== 'true') {
    ?>
    <div class="filtermenu-submenu">
      <div class="filter-title" onClick="toggleSubMenu('state')"><?= translate("state", $i18n) ?></div>
      <div class="filtermenu-submenu-content" id="filter-state">
        <div class="filter-item capitalize" data-state="0"><?= translate("enabled", $i18n) ?></div>
        <div class="filter-item capitalize" data-state="1"><?= translate("disabled", $i18n) ?></div>
      </div>
    </div>
    <?php
  }
  ?>

  <div class="filtermenu-submenu">
    <div class="filter-title" onClick="toggleSubMenu('renewal_type')"><?= translate("renewal_type", $i18n) ?></div>
    <div class="filtermenu-submenu-content" id="filter-renewal_type">
      <div class="filter-item capitalize" data-renewaltype="1"><?= translate("auto_renewal", $i18n) ?></div>
      <div class="filter-item capitalize" data-renewaltype="0"><?= translate("manual_renewal", $i18n) ?></div>
    </div>
  </div>

  <div class="filtermenu-submenu hide" id="clear-filters">
    <div class="filter-title filter-clear" onClick="clearFilters()">
      <i class="fa-solid fa-times-circle"></i> <?= translate("clear", $i18n) ?>
    </div>
  </div>
</div>