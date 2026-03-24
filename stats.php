<?php
require_once 'includes/header.php';


// Get code of main currency to display on statistics
$query = "SELECT c.code
          FROM currencies c
          INNER JOIN user u ON c.id = u.main_currency
          WHERE u.id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$code = $row['code'];

require_once 'includes/stats_calculations.php';

?>
<section class="contain">
  <?php
  if ($showCantConverErrorMessage) {
    ?>
    <div class="error-box">
      <div class="error-message">
        <i class="fa-solid fa-exclamation-circle"></i>
        <?= translate('cant_convert_currency', $i18n) ?>
      </div>
    </div>
    <?php
  }
  ?>
  <div class="split-header">
    <h2>
      <?= translate('general_statistics', $i18n) ?> <span class="header-subtitle"><?= $statsSubtitle ?></span>
    </h2>
    <div class="filtermenu">
      <button class="button secondary-button" id="filtermenu-button">
        <i class="fa-solid fa-filter"></i>
        <?= translate("filter", $i18n) ?>
      </button>
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
                if (isset($_GET['member']) && $_GET['member'] == $member['id']) {
                  $selectedClass = 'selected';
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
          // sort categories by order
          usort($categories, function ($a, $b) {
            return $a['order'] - $b['order'];
          });
          ?>
          <div class="filtermenu-submenu">
            <div class="filter-title" onClick="toggleSubMenu('category')"><?= translate("category", $i18n) ?></div>
            <div class="filtermenu-submenu-content" id="filter-category">
              <?php
              foreach ($categories as $category) {
                if ($category['count'] > 0) {
                  if ($category['name'] == "No category") {
                    $category['name'] = translate("no_category", $i18n);
                  }
                  $selectedClass = '';
                  if (isset($_GET['category']) && $_GET['category'] == $category['id']) {
                    $selectedClass = 'selected';
                  }
                  ?>
                  <div class="filter-item <?= $selectedClass ?>" data-categoryid="<?= $category['id'] ?>">
                    <?= $category['name'] ?>
                  </div>
                  <?php
                }
              }
              ?>
            </div>
          </div>
          <?php
        }
        ?>
        <?php
        if (count($paymentMethods) > 1) {

          usort($paymentMethods, function ($a, $b) {
            return $a['order'] <=> $b['order'];
          });
          ?>
          <div class="filtermenu-submenu">
            <div class="filter-title" onClick="toggleSubMenu('payment')"><?= translate("payment_method", $i18n) ?></div>
            <div class="filtermenu-submenu-content" id="filter-payment">
              <?php
              foreach ($paymentMethods as $payment) {
                if ($payment['count'] == 0) {
                  continue;
                }
                $selectedClass = '';
                if (isset($_GET['payment']) && $_GET['payment'] == $payment['id']) {
                  $selectedClass = 'selected';
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
        if ($showVsMonthlyBudgetGraph || $showVsPeriodBudgetGraph) {
          ?>
          <div class="filtermenu-submenu">
            <div class="filter-title" onClick="toggleSubMenu('budget')"><?= translate("budget_type", $i18n) ?></div>
            <div class="filtermenu-submenu-content" id="filter-budget">
              <?php if ($showVsMonthlyBudgetGraph) { ?>
                <div class="filter-item <?= (isset($_GET['budget']) && $_GET['budget'] === 'monthly') ? 'selected' : '' ?>" data-budgettype="monthly"><?= translate('monthly_budget', $i18n) ?></div>
              <?php } ?>
              <?php if ($showVsPeriodBudgetGraph) { ?>
                <div class="filter-item <?= (isset($_GET['budget']) && $_GET['budget'] === 'period') ? 'selected' : '' ?>" data-budgettype="period"><?= translate('period_budget', $i18n) ?></div>
              <?php } ?>
            </div>
          </div>
          <?php
        }
        ?>
        <?php
        if (isset($_GET['member']) || isset($_GET['category']) || isset($_GET['payment']) || isset($_GET['budget'])) {
          ?>
          <div class="filtermenu-submenu">
            <div class="filter-title filter-clear" onClick="clearFilters()">
              <i class="fa-solid fa-times-circle"></i> <?= translate("clear", $i18n) ?>
            </div>
          </div>
          <?php
        }
        ?>
      </div>
    </div>
  </div>
  </div>
  </div>
  <div class="statistics">
    <div class="statistic">
      <span><?= $activeSubscriptions ?></span>
      <div class="title"><?= translate('active_subscriptions', $i18n) ?></div>
    </div>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($totalCostPerMonth, $code) ?></span>
      <div class="title"><?= translate('monthly_cost', $i18n) ?></div>
    </div>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($totalCostPerYear, $code) ?></span>
      <div class="title"><?= translate('yearly_cost', $i18n) ?></div>
    </div>
    <div class="statistic">
      <span><?= CurrencyFormatter::format($averageSubscriptionCost, $code) ?></span>
      <div class="title"><?= translate('average_monthly', $i18n) ?></div>
    </div>
    <div class="statistic short">
      <span><?= CurrencyFormatter::format($mostExpensiveSubscription['price'], $code) ?></span>
      <div class="title"><?= translate('most_expensive', $i18n) ?></div>
      <?php
      if (isset($mostExpensiveSubscription['logo']) && $mostExpensiveSubscription['logo'] != '') {
        ?>
        <div class="subtitle">
          <img src="images/uploads/logos/<?= $mostExpensiveSubscription['logo'] ?>"
            alt="<?= $mostExpensiveSubscription['name'] ?>" title="<?= $mostExpensiveSubscription['name'] ?>" />
        </div>
        <?php
      } else if (isset($mostExpensiveSubscription['name']) && $mostExpensiveSubscription['name'] != '') {
        ?>
          <div class="subtitle"><?= $mostExpensiveSubscription['name'] ?></div>
        <?php
      }
      ?>
    </div>
    <?php
    $showMonthlyStats = !isset($_GET['budget']) || $_GET['budget'] === 'monthly';
    $showPeriodStats = !isset($_GET['budget']) || $_GET['budget'] === 'period';

    if ($showMonthlyStats && isset($monthlyBudgetUsed)) {
      ?>
      <div class="statistic">
        <span><?= number_format($monthlyBudgetUsed, 2) ?>%</span>
        <div class="title"><?= translate('monthly_budget', $i18n) ?> - <?= translate('percentage_budget_used', $i18n) ?></div>
      </div>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($monthlyBudgetLeft, $code) ?></span>
        <div class="title"><?= translate('monthly_budget', $i18n) ?> - <?= translate('budget_remaining', $i18n) ?></div>
      </div>
      <?php
      if (isset($monthlyOverBudgetAmount)) {
        ?>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($monthlyOverBudgetAmount, $code) ?></span>
          <div class="title"><?= translate('monthly_budget', $i18n) ?> - <?= translate('amount_over_budget', $i18n) ?></div>
        </div>
        <?php
      }
    }

    if ($showPeriodStats) {
      ?>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($amountNeededThisPeriod, $code) ?></span>
        <div class="title"><?= translate('amount_needed_this_period', $i18n) ?></div>
      </div>
      <?php
      if (isset($periodBudgetUsed)) {
        ?>
      <div class="statistic">
        <span><?= number_format($periodBudgetUsed, 2) ?>%</span>
        <div class="title"><?= translate('period_budget', $i18n) ?> - <?= translate('percentage_budget_used', $i18n) ?></div>
      </div>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($periodBudgetLeft, $code) ?></span>
        <div class="title"><?= translate('period_budget', $i18n) ?> - <?= translate('budget_remaining', $i18n) ?></div>
      </div>
      <?php
      if (isset($periodOverBudgetAmount)) {
        ?>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($periodOverBudgetAmount, $code) ?></span>
          <div class="title"><?= translate('period_budget', $i18n) ?> - <?= translate('amount_over_budget', $i18n) ?></div>
        </div>
        <?php
      }
      }
    }
    ?>
    <?php if ($inactiveSubscriptions > 0) { ?>
      <div class="statistic">
        <span><?= $inactiveSubscriptions ?></span>
        <div class="title"><?= translate('inactive_subscriptions', $i18n) ?></div>
      </div>
      <?php
      if ($totalSavingsPerMonth > 0) {
        ?>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($totalSavingsPerMonth, $code) ?></span>
          <div class="title"><?= translate('monthly_savings', $i18n) ?></div>
        </div>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($totalSavingsPerMonth * 12, $code) ?></span>
          <div class="title"><?= translate('yearly_savings', $i18n) ?></div>
        </div>
        <?php
      }
    } ?>
  </div>
  <?php if ($showPeriodStats && isset($budgetPeriodLabel)) { ?>
    <div class="header-subtitle"><?= translate('current_period', $i18n) ?>: <?= htmlspecialchars($budgetPeriodLabel, ENT_QUOTES, 'UTF-8') ?></div>
  <?php } ?>
  <?php
  $categoryDataPoints = [];
  if (isset($categoryCost)) {
    foreach ($categoryCost as $category) {
      if ($category['cost'] != 0) {
        $categoryDataPoints[] = [
          "label" => html_entity_decode($category['name']),
          "y" => $category["cost"],
        ];
      }
    }
  }

  $showCategoryCostGraph = count($categoryDataPoints) > 1;

  $memberDataPoints = [];
  if (isset($memberCost)) {
    foreach ($memberCost as $member) {
      if ($member['cost'] != 0) {
        $memberDataPoints[] = [
          "label" => html_entity_decode($member['name']),
          "y" => $member["cost"],
        ];

      }
    }
  }

  $showMemberCostGraph = count($memberDataPoints) > 1;

  $paymentMethodDataPoints = [];
  foreach ($paymentMethodsCount as $paymentMethod) {
    if ($paymentMethod['count'] != 0) {
      $paymentMethodDataPoints[] = [
        "label" => html_entity_decode($paymentMethod['name']),
        "y" => $paymentMethod["count"],
      ];
    }
  }

  $showPaymentMethodsGraph = count($paymentMethodDataPoints) > 1;
  $showAnyBudgetGraph = ($showMonthlyStats && $showVsMonthlyBudgetGraph) || ($showPeriodStats && $showVsPeriodBudgetGraph);
  if ($showCategoryCostGraph || $showMemberCostGraph || $showPaymentMethodsGraph || $showTotalMonthlyCostGraph || $showAnyBudgetGraph) {
    ?>
    <h2><?= translate('split_views', $i18n) ?></h2>
    <div class="graphs">
      <?php

      if ($showTotalMonthlyCostGraph) {
        ?>
        <section class="graph x2">
          <header>
            <?= translate('total_cost_trend', $i18n) ?>
            <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
          </header>
          <canvas id="totalMonthlyCostChart" style="height: 370px; width: 100%; max-height: 370px;"></canvas>
        </section>
        <?php
      }

      if ($showMemberCostGraph) {
        ?>
        <section class="graph">
          <header>
            <?= translate('household_split', $i18n) ?>
            <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
          </header>
          <canvas id="memberSplitChart"></canvas>
        </section>
        <?php
      }

      if ($showCategoryCostGraph) {
        ?>
        <section class="graph">
          <header>
            <?= translate('category_split', $i18n) ?>
            <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
          </header>
          <canvas id="categorySplitChart" style="height: 370px; width: 100%;"></canvas>
        </section>
        <?php
      }

      if ($showPaymentMethodsGraph) {
        ?>
        <section class="graph">
          <header>
            <?= translate('payment_method_split', $i18n) ?>
          </header>
          <canvas id="paymentMethidSplitChart" style="height: 370px; width: 100%;"></canvas>
        </section>
        <?php
      }

      if ($showMonthlyStats && $showVsMonthlyBudgetGraph) {
        ?>
        <section class="graph">
          <header>
            <?= translate('cost_vs_monthly_budget', $i18n) ?> (<?= CurrencyFormatter::format($monthlyBudget, $code) ?>)
            <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
          </header>
          <canvas id="monthlyBudgetVsCostChart" style="height: 370px; width: 100%;"></canvas>
        </section>
        <?php
      }

      if ($showPeriodStats && $showVsPeriodBudgetGraph) {
        ?>
        <section class="graph">
          <header>
            <?= translate('cost_vs_period_budget', $i18n) ?> (<?= CurrencyFormatter::format($periodBudget, $code) ?>)
            <div class="sub-header">(<?= translate('amount_needed_this_period', $i18n) ?>)</div>
          </header>
          <canvas id="periodBudgetVsCostChart" style="height: 370px; width: 100%;"></canvas>
        </section>
        <?php
      }

      ?>
    </div>
    <?php
  }
  ?>

</section>
<?php
if ($showCategoryCostGraph || $showMemberCostGraph || $showPaymentMethodsGraph || $showTotalMonthlyCostGraph || $showAnyBudgetGraph) {
  ?>
  <script src="scripts/libs/chart.js"></script>
  <script type="text/javascript">
    window.onload = function () {
      loadLineGraph("totalMonthlyCostChart", <?php echo json_encode($totalMonthlyCostDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", "<?= $showTotalMonthlyCostGraph ?>");
      loadGraph("categorySplitChart", <?php echo json_encode($categoryDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showCategoryCostGraph ?>);
      loadGraph("memberSplitChart", <?php echo json_encode($memberDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showMemberCostGraph ?>);
      loadGraph("paymentMethidSplitChart", <?php echo json_encode($paymentMethodDataPoints, JSON_NUMERIC_CHECK); ?>, "", <?= $showPaymentMethodsGraph ?>);
      <?php if ($showMonthlyStats && $showVsMonthlyBudgetGraph) { ?>
      loadGraph("monthlyBudgetVsCostChart", <?php echo json_encode($vsMonthlyBudgetDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", true);
      <?php } ?>
      <?php if ($showPeriodStats && $showVsPeriodBudgetGraph) { ?>
      loadGraph("periodBudgetVsCostChart", <?php echo json_encode($vsPeriodBudgetDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", true);
      <?php } ?>
    }
  </script>
  <?php
}
?>
<script src="scripts/stats.js?<?= $version ?>"></script>
<?php
require_once 'includes/footer.php';
?>
