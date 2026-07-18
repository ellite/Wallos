<?php
require_once 'includes/header.php';
require_once 'includes/logo_theme_variant.php';


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
require_once 'includes/stats_extra_calculations.php';

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
      <button class="button secondary-button" id="filtermenu-button" title="<?= translate("filter", $i18n) ?>">
        <i class="fa-solid fa-filter"></i>
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
                $isSelected = isset($_GET['member']) && in_array($member['id'], array_map('intval', explode(',', $_GET['member'])));
                if (($menuMemberCounts[$member['id']] ?? 0) == 0 && !$isSelected) {
                  continue;
                }
                $selectedClass = $isSelected ? 'selected' : '';
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
                $isSelected = isset($_GET['category']) && in_array($category['id'], array_map('intval', explode(',', $_GET['category'])));
                if (($menuCategoryCounts[$category['id']] ?? 0) == 0 && !$isSelected) {
                  continue;
                }
                $categoryName = $category['name'] == "No category" ? translate("no_category", $i18n) : $category['name'];
                $selectedClass = $isSelected ? 'selected' : '';
                ?>
                <div class="filter-item <?= $selectedClass ?>" data-categoryid="<?= $category['id'] ?>">
                  <?= $categoryName ?>
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
                $isSelected = isset($_GET['payment']) && in_array($payment['id'], array_map('intval', explode(',', $_GET['payment'])));
                if (($menuPaymentCounts[$payment['id']] ?? 0) == 0 && !$isSelected) {
                  continue;
                }
                $selectedClass = $isSelected ? 'selected' : '';
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
        if (isset($_GET['member']) || isset($_GET['category']) || isset($_GET['payment'])) {
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
  <?php
  // Graph datasets for the split views
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
  usort($categoryDataPoints, fn($a, $b) => $b['y'] <=> $a['y']);
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
  usort($memberDataPoints, fn($a, $b) => $b['y'] <=> $a['y']);
  $showMemberCostGraph = count($memberDataPoints) > 1;

  $paymentMethodDataPoints = [];
  foreach ($paymentMethodCost as $paymentMethodId => $cost) {
    if ($cost > 0 && isset($paymentMethodsCount[$paymentMethodId])) {
      $paymentMethodDataPoints[] = [
        "label" => html_entity_decode($paymentMethodsCount[$paymentMethodId]['name']),
        "y" => round($cost, 2),
      ];
    }
  }
  usort($paymentMethodDataPoints, fn($a, $b) => $b['y'] <=> $a['y']);
  $showPaymentMethodsGraph = count($paymentMethodDataPoints) > 1;

  $showMonthlyStats = !isset($_GET['budget']) || $_GET['budget'] === 'monthly';
  $showPeriodStats = !isset($_GET['budget']) || $_GET['budget'] === 'period';

  $showAnyGraph = $showCategoryCostGraph || $showMemberCostGraph || $showPaymentMethodsGraph || $showTotalMonthlyCostGraph
    || ($showMonthlyStats && $showVsMonthlyBudgetGraph) || ($showPeriodStats && $showVsPeriodBudgetGraph)
    || $showProjectionGraph || $showLifetimeGraph || $showCycleGraph || $showCurrencyGraph || $showHistogramGraph || $showYearlyNewGraph;

  $showTrendsSection = $showTotalMonthlyCostGraph || $showProjectionGraph || $monthOverMonthDelta !== null;
  $showBudgetSection = ($showMonthlyStats && (isset($monthlyBudgetUsed) || isset($monthlyBudgetLeft) || isset($monthlyOverBudgetAmount) || $showVsMonthlyBudgetGraph))
    || ($showPeriodStats && (isset($periodBudgetUsed) || isset($periodBudgetLeft) || isset($periodOverBudgetAmount) || $showVsPeriodBudgetGraph));
  $showSplitSection = $showMemberCostGraph || $showCategoryCostGraph || $showPaymentMethodsGraph || $showCycleGraph || $showCurrencyGraph || $showHistogramGraph;
  $showHistorySection = $totalLifetimeSpend > 0 || $oldestSubscription !== null || $averageSubscriptionAge !== null || $showLifetimeGraph || $showYearlyNewGraph;
  ?>

  <section class="stats-section">
    <h2><?= translate('overview', $i18n) ?></h2>
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
      <?php
      if ($totalCostPerMonth > 0) {
        ?>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($costPerDay, $code) ?></span>
          <div class="title"><?= translate('cost_per_day', $i18n) ?></div>
        </div>
        <?php
      }
      ?>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($averageSubscriptionCost, $code) ?></span>
        <div class="title"><?= translate('average_monthly', $i18n) ?></div>
      </div>
      <div class="statistic short">
        <span><?= CurrencyFormatter::format($mostExpensiveSubscription['price'], $code) ?></span>
        <div class="title"><?= translate('most_expensive', $i18n) ?></div>
        <?php
        if (isset($mostExpensiveSubscription['logo']) && $mostExpensiveSubscription['logo'] != '') {
          $mostExpensiveLogoSrc = "images/uploads/logos/" . $mostExpensiveSubscription['logo'];
          $mostExpensiveLogoVariantSrc = !empty($mostExpensiveSubscription['logo_variant']) ? "images/uploads/logos/" . $mostExpensiveSubscription['logo_variant'] : null;
          ?>
          <div class="subtitle">
            <?= renderThemedLogoImg($mostExpensiveLogoSrc, $mostExpensiveLogoVariantSrc, $mostExpensiveSubscription['logo_text_color'] ?? null, '', 'alt="' . $mostExpensiveSubscription['name'] . '" title="' . $mostExpensiveSubscription['name'] . '"') ?>
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
      if ($cheapestSubscription !== null) {
        ?>
        <div class="statistic short">
          <span><?= CurrencyFormatter::format($cheapestSubscription['price'], $code) ?></span>
          <div class="title"><?= translate('cheapest_subscription', $i18n) ?></div>
          <?php
          if (!empty($cheapestSubscription['logo'])) {
            $cheapestLogoSrc = "images/uploads/logos/" . $cheapestSubscription['logo'];
            $cheapestLogoVariantSrc = !empty($cheapestSubscription['logo_variant']) ? "images/uploads/logos/" . $cheapestSubscription['logo_variant'] : null;
            ?>
            <div class="subtitle">
              <?= renderThemedLogoImg($cheapestLogoSrc, $cheapestLogoVariantSrc, $cheapestSubscription['logo_text_color'] ?? null, '', 'alt="' . $cheapestSubscription['name'] . '" title="' . $cheapestSubscription['name'] . '"') ?>
            </div>
            <?php
          } else if (!empty($cheapestSubscription['name'])) {
            ?>
            <div class="subtitle"><?= $cheapestSubscription['name'] ?></div>
            <?php
          }
          ?>
        </div>
        <?php
      }
      ?>
      <div class="statistic">
        <span><?= CurrencyFormatter::format($amountDueThisMonth, $code) ?></span>
        <div class="title"><?= translate('amount_due', $i18n) ?></div>
      </div>
      <?php
      if ($manualRenewalsCount > 0) {
        ?>
        <div class="statistic">
          <span><?= $manualRenewalsCount ?></span>
          <div class="title"><?= translate('manual_renewals', $i18n) ?></div>
        </div>
        <?php
      }
      ?>
    </div>
  </section>

  <?php
  if ($showTrendsSection) {
    ?>
    <section class="stats-section">
      <h2><?= translate('trends_and_forecast', $i18n) ?></h2>
      <?php
      if ($monthOverMonthDelta !== null || ($showProjectionGraph && $heaviestMonth !== null) || $monthsOverBudget !== null) {
        ?>
        <div class="statistics">
          <?php
          if ($monthOverMonthDelta !== null) {
            $momSign = $monthOverMonthDelta >= 0 ? '+' : '-';
            ?>
            <div class="statistic short">
              <span><?= $momSign ?><?= CurrencyFormatter::format(abs($monthOverMonthDelta), $code) ?></span>
              <div class="title"><?= translate('vs_last_month', $i18n) ?></div>
              <?php
              if ($monthOverMonthPercentage !== null) {
                ?>
                <div class="subtitle"><?= $momSign ?><?= number_format(abs($monthOverMonthPercentage), 1) ?>%</div>
                <?php
              }
              ?>
            </div>
            <?php
          }
          if ($showProjectionGraph && $heaviestMonth !== null) {
            ?>
            <div class="statistic short">
              <span><?= CurrencyFormatter::format($heaviestMonth['total'], $code) ?></span>
              <div class="title"><?= translate('heaviest_month', $i18n) ?></div>
              <div class="subtitle capitalize"><?= $heaviestMonth['label'] ?></div>
            </div>
            <?php
          }
          if ($monthsOverBudget !== null) {
            ?>
            <div class="statistic">
              <span><?= $monthsOverBudget ?></span>
              <div class="title"><?= translate('months_over_budget', $i18n) ?></div>
            </div>
            <?php
          }
          ?>
        </div>
        <?php
      }
      if ($showTotalMonthlyCostGraph || $showProjectionGraph) {
        ?>
        <div class="graphs">
          <?php
          if ($showTotalMonthlyCostGraph) {
            ?>
            <section class="graph x2">
              <header>
                <?= translate('total_cost_trend', $i18n) ?>
                <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
              </header>
              <div id="totalMonthlyCostChart" style="width: 100%;"></div>
            </section>
            <?php
          }
          if ($showProjectionGraph) {
            ?>
            <section class="graph x2">
              <header>
                <?= translate('projected_cost', $i18n) ?>
              </header>
              <div id="projectionChart" style="width: 100%;"></div>
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
  }

  if ($showBudgetSection) {
    ?>
    <section class="stats-section">
      <h2><?= translate('budget', $i18n) ?></h2>
      <div class="statistics">
        <?php
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

        if ($showPeriodStats && isset($periodBudgetUsed)) {
          $periodRangeLabel = htmlspecialchars($budgetPeriodLabel, ENT_QUOTES, 'UTF-8');
          ?>
          <div class="statistic">
            <span><?= CurrencyFormatter::format($amountNeededThisPeriod, $code) ?></span>
            <div class="title"><?= translate('amount_needed_this_period', $i18n) ?></div>
            <div class="period-range"><?= $periodRangeLabel ?></div>
          </div>
          <div class="statistic">
            <span><?= number_format($periodBudgetUsed, 2) ?>%</span>
            <div class="title"><?= translate('period_budget', $i18n) ?> - <?= translate('percentage_budget_used', $i18n) ?></div>
            <div class="period-range"><?= $periodRangeLabel ?></div>
          </div>
          <div class="statistic">
            <span><?= CurrencyFormatter::format($periodBudgetLeft, $code) ?></span>
            <div class="title"><?= translate('period_budget', $i18n) ?> - <?= translate('budget_remaining', $i18n) ?></div>
            <div class="period-range"><?= $periodRangeLabel ?></div>
          </div>
          <?php
          if (isset($periodOverBudgetAmount)) {
            ?>
            <div class="statistic">
              <span><?= CurrencyFormatter::format($periodOverBudgetAmount, $code) ?></span>
              <div class="title"><?= translate('period_budget', $i18n) ?> - <?= translate('amount_over_budget', $i18n) ?></div>
              <div class="period-range"><?= $periodRangeLabel ?></div>
            </div>
            <?php
          }
        }
        ?>
      </div>
      <?php
      if (($showMonthlyStats && $showVsMonthlyBudgetGraph) || ($showPeriodStats && $showVsPeriodBudgetGraph)) {
        ?>
        <div class="graphs">
          <?php if ($showMonthlyStats && $showVsMonthlyBudgetGraph) { ?>
            <section class="graph">
              <header>
                <?= translate('cost_vs_monthly_budget', $i18n) ?> (<?= CurrencyFormatter::format($monthlyBudget, $code) ?>)
                <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
              </header>
              <div id="monthlyBudgetVsCostChart" style="width: 100%;"></div>
            </section>
          <?php } ?>
          <?php if ($showPeriodStats && $showVsPeriodBudgetGraph) { ?>
            <section class="graph">
              <header>
                <?= translate('cost_vs_period_budget', $i18n) ?> (<?= CurrencyFormatter::format($periodBudget, $code) ?>)
                <div class="sub-header">(<?= translate('amount_needed_this_period', $i18n) ?>)</div>
              </header>
              <div id="periodBudgetVsCostChart" style="width: 100%;"></div>
            </section>
          <?php } ?>
        </div>
        <?php
      }
      ?>
    </section>
    <?php
  }

  if ($showSplitSection) {
    ?>
    <section class="stats-section">
      <h2><?= translate('split_views', $i18n) ?></h2>
      <div class="graphs">
        <?php
        if ($showMemberCostGraph) {
          ?>
          <section class="graph">
            <header>
              <?= translate('household_split', $i18n) ?>
              <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
            </header>
            <div id="memberSplitChart" style="width: 100%;"></div>
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
            <div id="categorySplitChart" style="width: 100%;"></div>
          </section>
          <?php
        }
        if ($showPaymentMethodsGraph) {
          ?>
          <section class="graph">
            <header>
              <?= translate('payment_method_split', $i18n) ?>
              <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
            </header>
            <div id="paymentMethidSplitChart" style="width: 100%;"></div>
          </section>
          <?php
        }
        if ($showCycleGraph) {
          ?>
          <section class="graph">
            <header>
              <?= translate('billing_cycle_split', $i18n) ?>
              <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
            </header>
            <div id="cycleSplitChart" style="width: 100%;"></div>
          </section>
          <?php
        }
        if ($showCurrencyGraph) {
          ?>
          <section class="graph">
            <header>
              <?= translate('currency_split', $i18n) ?>
              <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>)</div>
            </header>
            <div id="currencySplitChart" style="width: 100%;"></div>
          </section>
          <?php
        }
        if ($showHistogramGraph) {
          ?>
          <section class="graph">
            <header>
              <?= translate('price_distribution', $i18n) ?>
              <div class="sub-header">(<?= translate('monthly_cost', $i18n) ?>, <?= $code ?>)</div>
            </header>
            <div id="priceHistogramChart" style="width: 100%;"></div>
          </section>
          <?php
        }
        ?>
      </div>
    </section>
    <?php
  }

  if ($showHistorySection) {
    ?>
    <section class="stats-section">
      <h2><?= translate('history_and_lifetime', $i18n) ?></h2>
      <?php
      if ($totalLifetimeSpend > 0 || $oldestSubscription !== null || $averageSubscriptionAge !== null) {
        ?>
        <div class="statistics">
          <?php
          if ($totalLifetimeSpend > 0) {
            ?>
            <div class="statistic">
              <span><?= CurrencyFormatter::format($totalLifetimeSpend, $code) ?></span>
              <div class="title"><?= translate('all_time_spend', $i18n) ?></div>
            </div>
            <?php
          }
          if ($oldestSubscription !== null) {
            ?>
            <div class="statistic short">
              <span><?= number_format($oldestSubscription['years'], 1) ?></span>
              <div class="title"><?= translate('oldest_subscription', $i18n) ?></div>
              <?php
              if (!empty($oldestSubscription['logo'])) {
                $oldestLogoSrc = "images/uploads/logos/" . $oldestSubscription['logo'];
                $oldestLogoVariantSrc = !empty($oldestSubscription['logo_variant']) ? "images/uploads/logos/" . $oldestSubscription['logo_variant'] : null;
                ?>
                <div class="subtitle">
                  <?= renderThemedLogoImg($oldestLogoSrc, $oldestLogoVariantSrc, $oldestSubscription['logo_text_color'] ?? null, '', 'alt="' . $oldestSubscription['name'] . '" title="' . $oldestSubscription['name'] . '"') ?>
                </div>
                <?php
              } else if (!empty($oldestSubscription['name'])) {
                ?>
                <div class="subtitle"><?= $oldestSubscription['name'] ?></div>
                <?php
              }
              ?>
            </div>
            <?php
          }
          if ($averageSubscriptionAge !== null) {
            ?>
            <div class="statistic">
              <span><?= number_format($averageSubscriptionAge, 1) ?></span>
              <div class="title"><?= translate('average_subscription_age', $i18n) ?></div>
            </div>
            <?php
          }
          ?>
        </div>
        <?php
      }
      if ($showLifetimeGraph || $showYearlyNewGraph) {
        ?>
        <div class="graphs">
          <?php
          if ($showYearlyNewGraph) {
            ?>
            <section class="graph">
              <header>
                <?= translate('new_subscriptions_per_year', $i18n) ?>
              </header>
              <div id="newPerYearChart" style="width: 100%;"></div>
            </section>
            <?php
          }
          if ($showLifetimeGraph) {
            ?>
            <section class="graph">
              <header>
                <?= translate('lifetime_spend', $i18n) ?>
                <div class="sub-header"><?= translate('estimated_from_current_prices', $i18n) ?></div>
              </header>
              <div id="lifetimeSpendChart" style="width: 100%;"></div>
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
  }

  if ($inactiveSubscriptions > 0) {
    ?>
    <section class="stats-section">
      <h2><?= translate('your_savings', $i18n) ?></h2>
      <div class="statistics">
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
        ?>
      </div>
    </section>
    <?php
  }
  ?>

</section>
<?php
if ($showAnyGraph) {
  ?>
  <script src="scripts/libs/apexcharts.min.js"></script>
  <script type="text/javascript">
    window.onload = function () {
      loadLineGraph("totalMonthlyCostChart", <?php echo json_encode($totalMonthlyCostDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showTotalMonthlyCostGraph ? 1 : 0 ?>);
      loadBarGraph("projectionChart", <?php echo json_encode($projectionDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showProjectionGraph ? 1 : 0 ?>, <?= isset($budget) && $budget > 0 ? $budget : 'null' ?>);
      loadGraph("categorySplitChart", <?php echo json_encode($categoryDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showCategoryCostGraph ? 1 : 0 ?>);
      loadGraph("memberSplitChart", <?php echo json_encode($memberDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showMemberCostGraph ? 1 : 0 ?>);
      loadGraph("paymentMethidSplitChart", <?php echo json_encode($paymentMethodDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showPaymentMethodsGraph ? 1 : 0 ?>);
      <?php if ($showMonthlyStats && $showVsMonthlyBudgetGraph) { ?>
      loadGraph("monthlyBudgetVsCostChart", <?php echo json_encode($vsMonthlyBudgetDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", 1);
      <?php } ?>
      <?php if ($showPeriodStats && $showVsPeriodBudgetGraph) { ?>
      loadGraph("periodBudgetVsCostChart", <?php echo json_encode($vsPeriodBudgetDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", 1);
      <?php } ?>
      loadGraph("cycleSplitChart", <?php echo json_encode($cycleDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showCycleGraph ? 1 : 0 ?>);
      loadGraph("currencySplitChart", <?php echo json_encode($currencyDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showCurrencyGraph ? 1 : 0 ?>);
      loadBarGraph("priceHistogramChart", <?php echo json_encode($histogramDataPoints, JSON_NUMERIC_CHECK); ?>, "", <?= $showHistogramGraph ? 1 : 0 ?>, null);
      loadBarGraph("newPerYearChart", <?php echo json_encode($newPerYearDataPoints, JSON_NUMERIC_CHECK); ?>, "", <?= $showYearlyNewGraph ? 1 : 0 ?>, null);
      loadHorizontalBarGraph("lifetimeSpendChart", <?php echo json_encode($lifetimeDataPoints, JSON_NUMERIC_CHECK); ?>, "<?= $code ?>", <?= $showLifetimeGraph ? 1 : 0 ?>);
    }
  </script>
  <?php
}
?>
<script src="scripts/stats.js?<?= $version ?>"></script>
<?php
require_once 'includes/footer.php';
?>