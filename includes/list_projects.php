<?php

// 引入国际化(i18n)文件，用于支持多语言。它可能包含 translate() 函数。
require_once 'i18n/getlang.php';

/**
 * 将计费周期的代码转换为人类可读的字符串。
 * @param int $cycle 周期代码 (1:天, 2:周, 3:月, 4:年)
 * @param int $frequency 频率 (例如：每 3 天)
 * @param object $i18n 国际化对象
 * @return string 格式化后的周期字符串 (例如 "每日", "3 周")
 */
function getBillingCycle($cycle, $frequency, $i18n)
{
    // 使用 switch 语句判断周期类型
    switch ($cycle) {
        case 1: // 天
            // 如果频率为1，返回 "每日"；否则返回 "N 天"
            return $frequency == 1 ? translate('Daily', $i18n) : $frequency . " " . translate('days', $i18n);
        case 2: // 周
            return $frequency == 1 ? translate('Weekly', $i18n) : $frequency . " " . translate('weeks', $i18n);
        case 3: // 月
            return $frequency == 1 ? translate('Monthly', $i18n) : $frequency . " " . translate('months', $i18n);
        case 4: // 年
            return $frequency == 1 ? translate('Yearly', $i18n) : $frequency . " " . translate('years', $i18n);
    }
}

/**
 * 计算当前订阅周期的进度百分比。
 * @param int $cycle 周期代码
 * @param int $frequency 频率
 * @param string $next_payment 下次付款日期 (Y-m-d H:i:s)
 * @return int 进度百分比 (0-100)
 */
function getSubscriptionProgress($cycle, $frequency, $next_payment)
{
    $nextPaymentDate = new DateTime($next_payment);
    $currentDate = new DateTime('now');

    // 根据周期和频率计算一个周期的总天数（近似值）
    $paymentCycleDays = 30; // 默认为每月30天
    if ($cycle === 1) { // 天
        $paymentCycleDays = 1 * $frequency;
    } else if ($cycle === 2) { // 周
        $paymentCycleDays = 7 * $frequency;
    } else if ($cycle === 3) { // 月
        $paymentCycleDays = 30 * $frequency;
    } else if ($cycle === 4) { // 年
        $paymentCycleDays = 365 * $frequency;
    }

    // 从下次付款日期回溯，计算出上次付款的日期
    $lastPaymentDate = clone $nextPaymentDate;
    $lastPaymentDate->modify("-$paymentCycleDays days");

    // 计算周期的总天数和自上次付款以来经过的天数
    $totalCycleDays = $lastPaymentDate->diff($nextPaymentDate)->days;
    $daysSinceLastPayment = $lastPaymentDate->diff($currentDate)->days;

    $projectProgress = 0;
    // 计算进度百分比
    if ($totalCycleDays > 0) {
        $projectProgress = ($daysSinceLastPayment / $totalCycleDays) * 100;
    }

    // 返回整数百分比
    return floor($projectProgress);
}

/**
 * 将任意周期的价格统一换算成“每月价格”。
 * @param int $cycle 周期代码
 * @param int $frequency 频率
 * @param float $price 价格
 * @return float 每月价格
 */
function getPricePerMonth($cycle, $frequency, $price)
{
    switch ($cycle) {
        case 1: // 日付 -> 月付
            $numberOfPaymentsPerMonth = (30 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 2: // 周付 -> 月付
            $numberOfPaymentsPerMonth = (4.35 / $frequency); // 平均每月4.35周
            return $price * $numberOfPaymentsPerMonth;
        case 3: // 月付 -> 月付
            $numberOfPaymentsPerMonth = (1 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 4: // 年付 -> 月付
            $numberOfMonths = (12 * $frequency);
            return $price / $numberOfMonths;
    }
}


/**
 * 根据数据库中的汇率进行货币换算。
 * @param float $price 原始价格
 * @param int $currency 货币ID
 * @param object $database 数据库连接对象
 * @return float 换算后的价格
 */
function getPriceConverted($price, $currency, $database)
{
    $query = "SELECT rate FROM currencies WHERE id = :currency";
    $stmt = $database->prepare($query);
    $stmt->bindParam(':currency', $currency, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
    if ($exchangeRate === false) {
        return $price; // 如果找不到汇率，返回原价
    } else {
        $fromRate = $exchangeRate['rate'];
        return $price / $fromRate; // 价格除以汇率
    }
}

/**
 * 将数字价格格式化为带货币符号的字符串。
 * @param float $price 价格
 * @param string $currencyCode 货币代码 (如 "USD")
 * @param array $currencies 包含所有货币信息的数组
 * @return string 格式化后的价格字符串 (如 "$10.50")
 */
function formatPrice($price, $currencyCode, $currencies)
{
    // 假设 CurrencyFormatter 是一个存在的类，用于格式化货币
    $formattedPrice = CurrencyFormatter::format($price, $currencyCode);
    // 如果格式化结果包含货币代码（如 "USD"），尝试替换为货币符号（如 "$"）
    if (strstr($formattedPrice, $currencyCode)) {
        $symbol = $currencyCode;

        // 遍历货币数组查找对应的符号
        foreach ($currencies as $currency) {
            if ($currency['code'] === $currencyCode) {
                if ($currency['symbol'] != "") {
                    $symbol = $currency['symbol'];
                }
                break;
            }
        }
        // 替换字符串
        $formattedPrice = str_replace($currencyCode, $symbol, $formattedPrice);
    }

    return $formattedPrice;
}

/**
 * 将日期字符串格式化为 YYYY-MM-DD 格式。
 * @param string $date 日期字符串
 * @param string $lang (此参数保留以兼容旧调用，但不再使用)
 * @return string 格式化后的日期字符串
 */
function formatDate($date, $lang = 'en')
{
    // 检查传入的日期是否为空或无效，如果是则返回 'N/A'
    if (empty($date) || strtotime($date) === false) {
        return 'N/A';
    }

    // 将日期字符串转换为时间戳，然后使用 'Y-m-d' 格式进行格式化
    return date('Y-m-d', strtotime($date));
}
/**
 * ===================================================================
 * 新函数1：打印单个项目下的所有订阅 (标准表格样式)
 * ===================================================================
 */
function printProjectSubscriptions_TableStyle($project,$projectSubscriptions, $categories, $members, $i18n, $currencies, $lang, $imagePath)
{
    if (empty($projectSubscriptions)) {
        ?>
        <div class="subscription-secondary no-subscriptions">
            <p><?= translate('no_sub_in_project', $i18n) ?></p>
        </div>
        <?php
        return;
    }
    ?>
    <div class="subscription-secondary has-children">


        <div class="secondary-desktop-table">

            <?php
            // --- 新增：项目备注显示区域 ---
            if (!empty($project['notes'])) {
                ?>
                <div class="project-notes">
                    <strong><?= translate('notes', $i18n) ?> : </strong> <?= htmlspecialchars($project['notes']) ?>
                </div>
                <?php
            }

            ?>

            <table>
                <thead>
                <tr>
                    <th><?= translate('start_date', $i18n) ?></th>
                    <th><?= translate('valid_until', $i18n) ?></th>
                    <th><?= translate('state', $i18n) ?></th>
                    <th><?= translate('payment_every', $i18n) ?></th>
                    <th><?= translate('price', $i18n) ?></th>
                    <th><?= translate('renewal_type', $i18n) ?></th>
                    <th><?= translate('notes', $i18n) ?></th>
                    <th><?= translate('settings', $i18n) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($projectSubscriptions as $subscription) : ?>
                    <tr class="<?= $subscription['inactive'] ? 'inactive-row' : '' ?>">
                        <td><?= formatDate($subscription['start_date'], $lang) ?></td>
                        <td><?= formatDate($subscription['next_payment'], $lang) ?></td>
                        <td><?= $subscription['inactive'] ? translate('status_disabled', $i18n) : translate('status_enabled', $i18n) ?></td>
                        <td><?= getBillingCycle($subscription['cycle'], $subscription['frequency'], $i18n) ?></td>
                        <td>
                            <?=
//                            var_dump($subscription['currency_id']);
//                            var_dump($currencies[$subscription['currency_id']]);
                            formatPrice($subscription['price'], $currencies[$subscription['currency_id']]['code'], $currencies);
                            ?>


                        </td>
                        <td><?= $subscription['auto_renew'] ? translate('automatically_renews', $i18n) : translate('manual_renewal', $i18n) ?></td>
                        <td class="notes-cell" title="<?= htmlspecialchars($subscription['notes']) ?>"><?= htmlspecialchars($subscription['notes']) ?></td>
                        <td class="actions-cell">
                            <button class="actions-icon" title="<?= translate('edit_subscription', $i18n) ?>" onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button class="actions-icon" title="<?= translate('delete', $i18n) ?>" onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
    <?php
}


/**
 * ===================================================================
 * 新函数2：打印所有项目的列表 (主函数)
 * ===================================================================
 */
function printProjects($projects, $subscriptionsByProject, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang)
{
    global $mainCurrencyCode;
    $projectExtraClasses = " inactive";
    foreach ($projects as $project) {

        // 检查是否有Logo
        $hasLogo = false;
        if ($project['logo'] != "") {
            $hasLogo = true;
        }
        ?>
        <div class="subscription-container">


            <div class="subscription<?= $projectExtraClasses ?>" onClick="toggleOpenSubscription(<?= $project['id'] ?>)" data-id="<?= $project['id'] ?>" data-name="<?= $project['name'] ?>">

                <div class="subscription-main">
                    <span class="logo <?= !$hasLogo ? 'hideOnMobile' : '' ?>">
                        <?php
                        if ($hasLogo) {
                            ?>
                            <img src="<?= $project['logo'] ?>">
                            <?php
                        } else {
                            // 无Logo则显示默认图标
                            include $imagePath . "images/siteicons/svg/logo.php";
                        }
                        ?>
                    </span>
                    <span class="name <?= $hasLogo ? 'hideOnMobile' : '' ?>"><?= $project['name'] ?></span>

                    <span class="cycle">
                            <?php
                            if ($project['category_id'] == 1) {
                                echo translate('no_category', $i18n); 
                            } else {
                                echo $categories[$project['category_id']]['name'];
                            }
                            ?>
                        </span>


                    <span class="name">  <?php
                        if ($project['is_active']){
                            echo translate('status_enabled', $i18n) ;
                        } else {
                            echo translate('status_disabled', $i18n) ;
                        }
                        ?>  </span>
                    <span class="name">
                        <span class="value">


                            <?php
                                if ($project['total_amount'] ){
//                                    echo $project['total_amount'];
//                                    var_dump($project['total_amount']);

                                    echo   formatPrice($project['total_amount'], $currencies[$project['currency_id']]['code'], $currencies);
                                }
                            ?>
                        </span>

                    </span>
                    <span class="next">
                        <?php
                        echo $project['next_payment_date'];
                        ?>
                    </span>
                    <?php
                    // 根据是否为移动端导航，决定是否隐藏桌面版的菜单按钮
                    $desktopMenuButtonClass = ""; {
                    }
                    if ($mobileNavigation === "true") {
                        $desktopMenuButtonClass = "mobileNavigationHideOnMobile";
                    }
                    ?>
                    <button type="button" class="actions-expand <?= $desktopMenuButtonClass ?>"
                            onClick="expandActions(event, <?= $project['id'] ?>)">
                        <i class="fas fa-ellipsis-v"></i> </button>
                    <ul class="actions">
                        <li class="edit" title="<?= translate('edit_subscription', $i18n) ?>"
                            onClick="openEditProject(event, <?= $project['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/edit.php"; ?>
                            <?= translate('edit_project', $i18n) ?>

                        </li>
                        <li class="edit" title="<?= translate('edit_subscription', $i18n) ?>"
                            onClick="addSubscription(event, <?= $project['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/edit.php"; ?>
                            <?= translate('add_subscription', $i18n) ?>
                        </li>
                        <li class="delete" title="<?= translate('delete', $i18n) ?>"
                            onClick="deleteProject(event, <?= $project['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/delete.php"; ?>
                            <?= translate('delete', $i18n) ?>
                        </li>


                    </ul>
                </div>



                <?php

                // --- 调用函数，打印该项目下的所有订阅 ---
                $projectSubscriptions = $subscriptionsByProject[$project['id']] ?? [];
                printProjectSubscriptions_TableStyle($project,$projectSubscriptions, $categories, $members, $i18n, $currencies, $lang, $imagePath);
                ?>

            </div>
        </div>
        <?php
    }
}

// --- 文件加载时执行的初始化代码 ---

// 查询数据库，获取当前用户设置的主货币ID
$query = "SELECT main_currency FROM user WHERE id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
if ($row !== false) {
    $mainCurrencyId = $row['main_currency'];
} else {
    // 如果查询失败，使用一个默认值
    $mainCurrencyId = $currencies[1]['id'];
}

?>