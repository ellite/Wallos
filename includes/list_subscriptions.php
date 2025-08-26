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

    $subscriptionProgress = 0;
    // 计算进度百分比
    if ($totalCycleDays > 0) {
        $subscriptionProgress = ($daysSinceLastPayment / $totalCycleDays) * 100;
    }

    // 返回整数百分比
    return floor($subscriptionProgress);
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
 * 格式化日期，当年内和跨年份显示不同格式。
 * @param string $date 日期字符串
 * @param string $lang 语言代码 (如 'en', 'zh')
 * @return string 格式化后的日期字符串
 */
function formatDate($date, $lang = 'en')
{
    $currentYear = date('Y');
    $dateYear = date('Y', strtotime($date));

    // 判断日期年份是否是当前年份，以决定格式
    $dateFormat = ($currentYear == $dateYear) ? 'MMM d' : 'MMM yyyy'; // 例如 "Aug 13" 或 "Aug 2024"

    // 验证服务器是否支持该语言，不支持则回退到英语
    if (!in_array($lang, ResourceBundle::getLocales(''))) {
        $lang = 'en'; // 回退到英语
    }

    // 使用PHP的IntlDateFormatter进行国际化日期格式化
    $formatter = new IntlDateFormatter(
        $lang,
        IntlDateFormatter::SHORT,
        IntlDateFormatter::NONE,
        null,
        null,
        $dateFormat
    );

    // 格式化日期
    $formattedDate = $formatter->format(new DateTime($date));

    return $formattedDate;
}

/**
 * 打印（渲染）所有订阅列表的HTML代码。
 * 这是此文件的核心函数，负责生成前端所见的列表。
 * @param array $subscriptions 经过处理的、用于显示的订阅数据数组
 * @param string $sort 当前的排序标准
 * @param array $categories 分类信息数组
 * ... (其他参数用于提供显示所需的数据和设置)
 */
function printSubscriptions($subscriptions, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang)
{
    // 如果按价格排序，先在PHP层面进行排序
    if ($sort === "price") {
        usort($subscriptions, function ($a, $b) {
            return $a['price'] < $b['price'] ? 1 : -1;
        });
        // 如果需要，将已停用的项目置于底部
        if ($disabledToBottom === 'true') {
            usort($subscriptions, function ($a, $b) {
                return $a['inactive'] - $b['inactive'];
            });
        }
    }

    // 初始化用于跟踪分组的变量
    $currentCategory = 0;
    $currentPayerUserId = 0;
    $currentPaymentMethodId = 0;

    // --- 开始循环遍历每个订阅并生成HTML ---
    foreach ($subscriptions as $subscription) {

        // --- 分组标题 ---
        // 如果是按分类排序，并且当前分类与上一个不同，则打印分类标题
        if ($sort == "category_id" && $subscription['category_id'] != $currentCategory) {
            ?>
            <div class="subscription-list-title">
                <?php
                if ($subscription['category_id'] == 1) {
                    echo translate('no_category', $i18n); // 未分类
                } else {
                    echo $categories[$subscription['category_id']]['name'];
                }
                ?>
            </div>
            <?php
            $currentCategory = $subscription['category_id']; // 更新当前分类
        }
        // 如果按付款人排序，并且当前付款人与上一个不同，则打印付款人标题
        if ($sort == "payer_user_id" && $subscription['payer_user_id'] != $currentPayerUserId) {
            ?>
            <div class="subscription-list-title">
                <?= $members[$subscription['payer_user_id']]['name'] ?>
            </div>
            <?php
            $currentPayerUserId = $subscription['payer_user_id'];
        }
        // 如果按支付方式排序...
        if ($sort == "payment_method_id" && $subscription['payment_method_id'] != $currentPaymentMethodId) {
            ?>
            <div class="subscription-list-title">
                <?= $subscription['payment_method_name'] ?>
            </div>
            <?php
            $currentPaymentMethodId = $subscription['payment_method_id'];
        }
        ?>

        <div class="subscription-container">
            <?php
            // 如果开启了移动端导航，则显示移动端专属的操作按钮
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
                    // 如果不是自动续费，则显示“续费”按钮
                    if ($subscription['auto_renew'] != 1) {
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

            // 根据订阅状态动态添加CSS类
            $subscriptionExtraClasses = "";
            if ($subscription['inactive']) {
                $subscriptionExtraClasses .= " inactive"; // 已停用
            }
            if ($subscription['auto_renew'] != 1) {
                $subscriptionExtraClasses .= " manual"; // 手动续费
            }

            // 检查是否有Logo
            $hasLogo = false;
            if ($subscription['logo'] != "") {
                $hasLogo = true;
            }

            ?>

            <div class="subscription<?= $subscriptionExtraClasses ?>"
                 onClick="toggleOpenSubscription(<?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>"
                 data-name="<?= $subscription['name'] ?>">

                <div class="subscription-main">
                    <span class="logo <?= !$hasLogo ? 'hideOnMobile' : '' ?>">
                        <?php
                        if ($hasLogo) {
                            ?>
                            <img src="<?= $subscription['logo'] ?>">
                            <?php
                        } else {
                            // 无Logo则显示默认图标
                            include $imagePath . "images/siteicons/svg/logo.php";
                        }
                        ?>
                    </span>
                    <span class="name <?= $hasLogo ? 'hideOnMobile' : '' ?>"><?= $subscription['name'] ?></span>
                    <span class="cycle"
                          title="<?= $subscription['auto_renew'] ? translate("automatically_renews", $i18n) : translate("manual_renewal", $i18n) ?>">
                        <?php
                        // 根据是否自动续费显示不同图标
                        if ($subscription['auto_renew']) {
                            include $imagePath . "images/siteicons/svg/automatic.php";
                        } else {
                            include $imagePath . "images/siteicons/svg/manual.php";
                        }
                        ?>
                        <?= $subscription['billing_cycle'] // 显示格式化后的周期文本 ?>
                    </span>
                    <span class="next"><?= formatDate($subscription['next_payment'], $lang) ?></span>
                    <span class="price">
                        <span class="value">
                            <?= formatPrice($subscription['price'], $subscription['currency_code'], $currencies) ?>
                            <?php
                            // 如果设置了显示原始价格，且价格被转换过，则显示
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
                        <img src="<?= $subscription['payment_method_icon'] ?>"
                             title="<?= translate('payment_method', $i18n) ?>: <?= $subscription['payment_method_name'] ?>" />
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
                            onClick="expandActions(event, <?= $subscription['id'] ?>)">
                        <i class="fas fa-ellipsis-v"></i> </button>
                    <ul class="actions">
                        <li class="edit" title="<?= translate('edit_subscription', $i18n) ?>"
                            onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/edit.php"; ?>
                            <?= translate('edit_subscription', $i18n) ?>
                        </li>
                        <li class="delete" title="<?= translate('delete', $i18n) ?>"
                            onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/delete.php"; ?>
                            <?= translate('delete', $i18n) ?>
                        </li>
                        <li class="clone" title="<?= translate('clone', $i18n) ?>"
                            onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/clone.php"; ?>
                            <?= translate('clone', $i18n) ?>
                        </li>
                        <?php
                        // 如果不是自动续费，显示“续费”选项
                        if ($subscription['auto_renew'] != 1) {
                            ?>
                            <li class="renew" title="<?= translate('renew', $i18n) ?>"
                                onClick="renewSubscription(event, <?= $subscription['id'] ?>)">
                                <?php include $imagePath . "images/siteicons/svg/renew.php"; ?>
                                <?= translate('renew', $i18n) ?>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                </div>

                <div class="subscription-secondary">
                    <span
                            class="name"><?php include $imagePath . "images/siteicons/svg/subscription.php"; ?><?= $subscription['name'] ?></span>
                    <span class="payer_user"
                          title="<?= translate('paid_by', $i18n) ?>"><?php include $imagePath . "images/siteicons/svg/payment.php"; ?><?= $members[$subscription['payer_user_id']]['name'] ?></span>
                    <span class="category"
                          title="<?= translate('category', $i18n) ?>"><?php include $imagePath . "images/siteicons/svg/category.php"; ?><?= $categories[$subscription['category_id']]['name'] ?></span>
                    <?php
                    // 如果有URL，则显示一个可点击的链接图标
                    if ($subscription['url'] != "") {
                        $url = $subscription['url'];
                        // 确保URL以 http(s):// 开头
                        if (!preg_match('/^https?:\/\//', $url)) {
                            $url = "https://" . $url;
                        }
                        ?>
                        <span class="url" title="<?= translate('external_url', $i18n) ?>"><a href="<?= $url ?>" target="_blank"
                                                                                             rel="noreferrer"><?php include $imagePath . "images/siteicons/svg/web.php"; ?></a></span>
                        <?php
                    }
                    ?>
                </div>
                <?php
                // 如果有备注，则显示备注区域
                if ($subscription['notes'] != "") {
                    ?>
                    <div class="subscription-notes">
                        <span class="notes">
                            <?php include $imagePath . "images/siteicons/svg/notes.php"; ?>
                            <?= $subscription['notes'] ?>
                        </span>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
            // 如果设置了显示订阅进度条
            if ($showSubscriptionProgress === 'true') {
                // 进度最大为100%
                $progress = $subscription['progress'] > 100 ? 100 : $subscription['progress'];
                ?>
                <div class="subscription-progress-container">
                    <span class="subscription-progress" style="width: <?= $progress ?>%;"></span>
                </div>
                <?php
            }
            ?>
        </div> <?php
    } // --- end foreach ---
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