<?php

// --- 文件包含和初始化 ---

// 引入页头文件，通常包含HTML的<head>部分、CSS链接和数据库连接
require_once 'includes/header.php';
// 引入一个文件，用于从数据库获取一些键值对数据，如分类、成员、支付方式等
require_once 'includes/getdbkeys.php';

// 引入包含核心函数的文件，例如 printSubscriptions()
include_once 'includes/list_subscriptions.php';

// --- 排序和过滤逻辑 ---

// 设置默认排序方式为“下次付款日期”
$sort = "next_payment";
$sortOrder = $sort;

// 这是一个旧的、可能已废弃的排序逻辑，新的逻辑在下面会覆盖它
if ($settings['disabledToBottom'] === 'true') {
    $sql = "SELECT * FROM subscriptions WHERE user_id = :userId ORDER BY inactive ASC, next_payment ASC";
} else {
    $sql = "SELECT * FROM subscriptions WHERE user_id = :userId ORDER BY next_payment ASC, inactive ASC";
}

// 初始化用于SQL预处理语句的参数数组
$params = array();

// 检查Cookie中是否存有用户自定义的排序方式，如果有则使用它
if (isset($_COOKIE['sortOrder']) && $_COOKIE['sortOrder'] != "") {
    $sort = $_COOKIE['sortOrder'] ?? 'next_payment';
}

// 记录最终使用的排序方式，用于后续的PHP排序
$sortOrder = $sort;
// 定义一个允许的排序标准白名单，防止SQL注入
$allowedSortCriteria = ['name', 'id', 'next_payment', 'price', 'payer_user_id', 'category_id', 'payment_method_id', 'inactive', 'alphanumeric', 'renewal_type'];
// 根据排序标准决定是升序(ASC)还是降序(DESC)
$order = ($sort == "price" || $sort == "id") ? "DESC" : "ASC";

// “alphanumeric”是一个特殊的PHP排序，SQL层面按“name”排序
if ($sort == "alphanumeric") {
    $sort = "name";
}

// 确保排序标准在白名单内，否则强制使用默认值
if (!in_array($sort, $allowedSortCriteria)) {
    $sort = "next_payment";
}

// “renewal_type”在数据库中对应“auto_renew”字段
if ($sort == "renewal_type") {
    $sort = "auto_renew";
}


// --- 动态构建SQL查询语句 ---

// SQL查询的基础部分
$sql = "SELECT * FROM subscriptions WHERE user_id = :userId";

// 如果URL参数中有'member'，则添加成员过滤条件
if (isset($_GET['member'])) {
    $memberIds = explode(',', $_GET['member']); // 允许多个成员ID
    // 创建占位符，如 :member0, :member1
    $placeholders = array_map(function ($key) {
        return ":member{$key}";
    }, array_keys($memberIds));
    // 将过滤条件添加到SQL中
    $sql .= " AND payer_user_id IN (" . implode(',', $placeholders) . ")";
    // 绑定参数
    foreach ($memberIds as $key => $memberId) {
        $params[":member{$key}"] = $memberId;
    }
}

// 如果URL参数中有'category'，则添加分类过滤条件
if (isset($_GET['category'])) {
    $categoryIds = explode(',', $_GET['category']);
    $placeholders = array_map(function ($key) {
        return ":category{$key}";
    }, array_keys($categoryIds));
    $sql .= " AND category_id IN (" . implode(',', $placeholders) . ")";
    foreach ($categoryIds as $key => $categoryId) {
        $params[":category{$key}"] = $categoryId;
    }
}

// 如果URL参数中有'payment'，则添加支付方式过滤条件
if (isset($_GET['payment'])) {
    $paymentIds = explode(',', $_GET['payment']);
    $placeholders = array_map(function ($key) {
        return ":payment{$key}";
    }, array_keys($paymentIds));
    $sql .= " AND payment_method_id IN (" . implode(',', $placeholders) . ")";
    foreach ($paymentIds as $key => $paymentId) {
        $params[":payment{$key}"] = $paymentId;
    }
}

// 如果URL参数中有'state'（活动/非活动），则添加状态过滤条件
if (!isset($settings['hideDisabledSubscriptions']) || $settings['hideDisabledSubscriptions'] !== 'true') {
    if (isset($_GET['state']) && $_GET['state'] != "") {
        $sql .= " AND inactive = :inactive";
        $params[':inactive'] = $_GET['state'];
    }
}

// --- 构建SQL的ORDER BY子句 ---

$orderByClauses = []; // 用于存放多个排序条件的数组

// 根据用户设置（是否将禁用的订阅置于底部）和当前的排序标准，构建复杂的排序逻辑
if ($settings['disabledToBottom'] === 'true') {
    // 如果按分类、付款人或支付方式排序，则先按这些排，再按是否禁用排
    if (in_array($sort, ["payer_user_id", "category_id", "payment_method_id"])) {
        $orderByClauses[] = "$sort $order";
        $orderByClauses[] = "inactive ASC";
    } else { // 否则，总是先把禁用的放到底部
        $orderByClauses[] = "inactive ASC";
        $orderByClauses[] = "$sort $order";
    }
} else {
    // 正常排序逻辑
    $orderByClauses[] = "$sort $order";
    if ($sort != "inactive") {
        $orderByClauses[] = "inactive ASC";
    }
}

// 总是将“下次付款日期”作为次要排序标准，以确保排序稳定性
if ($sort != "next_payment") {
    $orderByClauses[] = "next_payment ASC";
}

// 将所有排序条件合并到SQL语句中
$sql .= " ORDER BY " . implode(", ", $orderByClauses);


// --- 执行数据库查询 ---



// 准备SQL语句
$stmt = $db->prepare($sql);
// 绑定当前用户ID
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

// 绑定所有过滤条件的参数
if (!empty($params)) {
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, SQLITE3_INTEGER);
    }
}

// 执行查询
$result = $stmt->execute();
// 如果查询成功，将所有结果行提取到$subscriptions数组中
if ($result) {
    $subscriptions = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $subscriptions[] = $row;
    }
}

// --- 新增：查询所有项目用于表单下拉菜单 ---
$projectsQuery = "SELECT id, name FROM projects WHERE user_id = :userId ORDER BY name ASC";
$projectsStmt = $db->prepare($projectsQuery);
$projectsStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$projectsResult = $projectsStmt->execute();

$projectsForForm = array(); // 创建一个新数组来存储项目
if ($projectsResult) {
    while ($projectRow = $projectsResult->fetchArray(SQLITE3_ASSOC)) {
        $projectsForForm[] = $projectRow;
    }
}

// --- 数据后处理 ---

// 循环遍历查询结果，为分类、成员等计算订阅数量
foreach ($subscriptions as $subscription) {
    $memberId = $subscription['payer_user_id'];
    $members[$memberId]['count']++;
    $categoryId = $subscription['category_id'];
    $categories[$categoryId]['count']++;
    $paymentMethodId = $subscription['payment_method_id'];
    $payment_methods[$paymentMethodId]['count']++;
}

// 对于某些需要自定义顺序的排序（不能直接在SQL中完成），在这里使用PHP的usort进行处理
if ($sortOrder == "category_id") {
    usort($subscriptions, function ($a, $b) use ($categories) {
        return $categories[$a['category_id']]['order'] - $categories[$b['category_id']]['order'];
    });
}

if ($sortOrder == "payment_method_id") {
    usort($subscriptions, function ($a, $b) use ($payment_methods) {
        return $payment_methods[$a['payment_method_id']]['order'] - $payment_methods[$b['payment_method_id']]['order'];
    });
}

// 根据是否有订阅数据，决定头部操作栏是否隐藏
$headerClass = count($subscriptions) > 0 ? "main-actions" : "main-actions hidden";
?>
    <style>
        /* 使用PHP动态设置CSS伪元素的内容，用于国际化 */
        .logo-preview:after {
            content: '<?= translate('upload_logo', $i18n) ?>';
        }
    </style>

    <section class="contain">
        <?php
        // 如果是管理员且开启了更新通知，则检查是否有新版本
        if ($isAdmin && $settings['update_notification']) {
            if (!is_null($settings['latest_version'])) {
                $latestVersion = $settings['latest_version'];
                if (version_compare($version, $latestVersion) == -1) {
                    ?>
                    <div class="update-banner">
                        <?= translate('new_version_available', $i18n) ?>:
                        <span><a href="https://github.com/ellite/Wallos/releases/tag/<?= htmlspecialchars($latestVersion) ?>"
                                 target="_blank" rel="noreferer">
              <?= htmlspecialchars($latestVersion) ?>
            </a></span>
                    </div>
                    <?php
                }
            }
        }

        // 如果是演示模式，显示一个提示横幅
        if ($demoMode) {
            ?>
            <div class="demo-banner">
                Running in <b>Demo Mode</b>, certain actions and settings are disabled.<br>
                The database will be reset every 120 minutes.
            </div>
            <?php
        }
        ?>

        <header class="<?= $headerClass ?>" id="main-actions">
            <button class="button" onClick="addSubscription()">
                <i class="fa-solid fa-circle-plus"></i>
                <?= translate('new_subscription', $i18n) ?>
            </button>
            <div class="top-actions">
                <div class="search">
                    <input type="text" autocomplete="off" name="search" id="search" placeholder="<?= translate('search', $i18n) ?>"
                           onkeyup="searchSubscriptions()" />
                    <span class="fa-solid fa-magnifying-glass search-icon"></span>
                    <span class="fa-solid fa-xmark clear-search" onClick="clearSearch()"></span>
                </div>

                <div class="filtermenu on-dashboard">
                    <button class="button secondary-button" id="filtermenu-button" title="<?= translate("filter", $i18n) ?>">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                    <?php include 'includes/filters_menu.php'; // 引入过滤器菜单的HTML ?>
                </div>

                <div class="sort-container">
                    <button class="button secondary-button" value="Sort" onClick="toggleSortOptions()" id="sort-button"
                            title="<?= translate('sort', $i18n) ?>">
                        <i class="fa-solid fa-arrow-down-wide-short"></i>
                    </button>
                    <?php include 'includes/sort_options.php'; // 引入排序选项菜单的HTML ?>
                </div>
            </div>
        </header>

        <div class="subscriptions" id="subscriptions">
            <?php
            // 创建一个日期格式化器，用于统一显示日期
            $formatter = new IntlDateFormatter(
                'en', // 强制使用英文格式以确保一致性，如需本地化可改为$lang
                IntlDateFormatter::SHORT,
                IntlDateFormatter::NONE,
                null,
                null,
                'MMM d, yyyy' // 格式：月 日, 年
            );

            // --- 准备用于显示的数据 ---
            // 再次循环遍历$subscriptions，将原始数据处理成适合显示的$print数组
            foreach ($subscriptions as $subscription) {
                // 如果设置了“隐藏禁用订阅”，则跳过不处理
                if ($subscription['inactive'] == 1 && isset($settings['hideDisabledSubscriptions']) && $settings['hideDisabledSubscriptions'] === 'true') {
                    continue;
                }
                $id = $subscription['id'];
                $print[$id]['id'] = $id;
                // 处理Logo路径
                $print[$id]['logo'] = $subscription['logo'] != "" ? "images/uploads/logos/" . $subscription['logo'] : "";
                $print[$id]['name'] = $subscription['name'];
                $cycle = $subscription['cycle'];
                $frequency = $subscription['frequency'];
                // 调用函数获取可读的计费周期文字
                $print[$id]['billing_cycle'] = getBillingCycle($cycle, $frequency, $i18n);
                $paymentMethodId = $subscription['payment_method_id'];
                $print[$id]['currency_code'] = $currencies[$subscription['currency_id']]['code'];
                $currencyId = $subscription['currency_id'];
                $print[$id]['auto_renew'] = $subscription['auto_renew'];
                $next_payment_timestamp = strtotime($subscription['next_payment']);
                // 格式化日期
                $formatted_date = $formatter->format($next_payment_timestamp);
                $print[$id]['next_payment'] = $formatted_date;
                // 处理支付方式图标路径
                $paymentIconFolder = (strpos($payment_methods[$paymentMethodId]['icon'], 'images/uploads/icons/') !== false) ? "" : "images/uploads/logos/";
                $print[$id]['payment_method_icon'] = $paymentIconFolder . $payment_methods[$paymentMethodId]['icon'];
                $print[$id]['payment_method_name'] = $payment_methods[$paymentMethodId]['name'];
                $print[$id]['payment_method_id'] = $paymentMethodId;
                $print[$id]['category_id'] = $subscription['category_id'];
                $print[$id]['payer_user_id'] = $subscription['payer_user_id'];
                $print[$id]['price'] = floatval($subscription['price']);
                // 调用函数计算当前订阅周期的进度
                $print[$id]['progress'] = getSubscriptionProgress($cycle, $frequency, $subscription['next_payment']);
                $print[$id]['inactive'] = $subscription['inactive'];
                $print[$id]['url'] = $subscription['url'];
                $print[$id]['notes'] = $subscription['notes'];
                $print[$id]['replacement_subscription_id'] = $subscription['replacement_subscription_id'];
                // 项目id
                $print[$id]['project_id'] = $subscription['project_id'];

                // 如果开启了“货币转换”且当前订阅货币与主货币不同
                if (isset($settings['convertCurrency']) && $settings['convertCurrency'] === 'true' && $currencyId != $mainCurrencyId) {
                    $print[$id]['price'] = getPriceConverted($print[$id]['price'], $currencyId, $db); // 转换价格
                    $print[$id]['currency_code'] = $currencies[$mainCurrencyId]['code']; // 更新货币代码
                }
                // 如果开启了“显示月度价格”
                if (isset($settings['showMonthlyPrice']) && $settings['showMonthlyPrice'] === 'true') {
                    $print[$id]['price'] = getPricePerMonth($cycle, $frequency, $print[$id]['price']); // 计算月度价格
                }
                // 如果开启了“显示原始价格”
                if (isset($settings['showOriginalPrice']) && $settings['showOriginalPrice'] === 'true') {
                    $print[$id]['original_price'] = floatval($subscription['price']);
                    $print[$id]['original_currency_code'] = $currencies[$subscription['currency_id']]['code'];
                }
            }

            // 对于字母排序，需要在这里用PHP的自然排序算法处理，比SQL的ORDER BY更准确
            if ($sortOrder == "alphanumeric") {
                usort($print, function ($a, $b) {
                    return strnatcmp(strtolower($a['name']), strtolower($b['name']));
                });
                // 如果需要，将禁用的订阅排在底部
                if ($settings['disabledToBottom'] === 'true') {
                    usort($print, function ($a, $b) {
                        return $a['inactive'] - $b['inactive'];
                    });
                }
            }

            // 如果$print数组存在（即有数据需要显示）
            if (isset($print)) {
                // 调用核心函数，将处理好的$print数组渲染成HTML
                printSubscriptions($print, $sort, $categories, $members, $i18n, $colorTheme, "", $settings['disabledToBottom'], $settings['mobileNavigation'], $settings['showSubscriptionProgress'], $currencies, $lang);
            }
            // 关闭数据库连接
            $db->close();

            // 如果没有任何订阅，则显示一个空状态页面
            if (count($subscriptions) == 0) {
                ?>
                <div class="empty-page">
                    <img src="images/siteimages/empty.png" alt="<?= translate('empty_page', $i18n) ?>" />
                    <p>
                        <?= translate('no_subscriptions_yet', $i18n) ?>
                    </p>
                    <button class="button" onClick="addSubscription()">
                        <i class="fa-solid fa-circle-plus"></i>
                        <?= translate('add_first_subscription', $i18n) ?>
                    </button>
                </div>
                <?php
            }
            ?>
        </div>
    </section>

    <section class="subscription-form" id="subscription-form">
        <header>
            <h3 id="form-title"><?= translate('add_subscription', $i18n) ?></h3>
            <span class="fa-solid fa-xmark close-form" onClick="closeAddSubscription()"></span>
        </header>
        <form action="endpoints/subscription/add.php" method="post" id="subs-form">

            <div class="form-group-inline">
                <input type="text" id="name" name="name" placeholder="<?= translate('subscription_name', $i18n) ?>"
                       onchange="setSearchButtonStatus()" onkeypress="this.onchange();" onpaste="this.onchange();"
                       oninput="this.onchange();" required>
                <label for="logo" class="logo-preview">
                    <img src="" alt="<?= translate('logo_preview', $i18n) ?>" id="form-logo">
                </label>
                <input type="file" id="logo" name="logo" accept="image/jpeg, image/png, image/gif, image/webp, image/svg+xml"
                       onchange="handleFileSelect(event)" class="hidden-input">
                <input type="hidden" id="logo-url" name="logo-url">
                <div id="logo-search-button" class="image-button medium disabled" title="<?= translate('search_logo', $i18n) ?>"
                     onClick="searchLogo()">
                    <?php include "images/siteicons/svg/websearch.php"; ?>
                </div>
                <input type="hidden" id="id" name="id"> <div id="logo-search-results" class="logo-search">
                    <header>
                        <?= translate('web_search', $i18n) ?>
                        <span class="fa-solid fa-xmark close-logo-search" onClick="closeLogoSearch()"></span>
                    </header>
                    <div id="logo-search-images"></div>
                </div>
            </div>

            <div class="form-group-inline">
                <input type="number" step="0.01" id="price" name="price" placeholder="<?= translate('price', $i18n) ?>" required>
                <select id="currency" name="currency_id" placeholder="<?= translate('add_subscription', $i18n) ?>">
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
                <div class="inline">
                    <div class="split66">
                        <label for="cycle"><?= translate('payment_every', $i18n) ?></label>
                        <div class="inline">
                            <select id="frequency" name="frequency" placeholder="<?= translate('frequency', $i18n) ?>">
                                <?php
                                for ($i = 1; $i <= 366; $i++) {
                                    ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                            <select id="cycle" name="cycle" placeholder="Cycle">
                                <?php
                                foreach ($cycles as $cycle) {
                                    ?>
                                    <option value="<?= $cycle['id'] ?>" <?= $cycle['id'] == 3 ? "selected" : "" ?>>
                                        <?= translate(strtolower($cycle['name']), $i18n) ?>
                                    </option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="split33">
                        <label><?= translate('auto_renewal', $i18n) ?></label>
                        <div class="inline height50">
                            <input type="checkbox" id="auto_renew" name="auto_renew" checked>
                            <label for="auto_renew"><?= translate('automatically_renews', $i18n) ?></label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <div class="inline">
                    <div class="split50">
                        <label for="start_date"><?= translate('start_date', $i18n) ?></label>
                        <div class="date-wrapper">
                            <input type="date" id="start_date" name="start_date">
                        </div>
                    </div>
                    <button type="button" id="autofill-next-payment-button"
                            class="button secondary-button autofill-next-payment hideOnMobile"
                            title="<?= translate('calculate_next_payment_date', $i18n) ?>" onClick="autoFillNextPaymentDate(event)">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </button>
                    <div class="split50">
                        <label for="next_payment" class="split-label">
                            <?= translate('next_payment', $i18n) ?>
                            <div id="autofill-next-payment-button" class="autofill-next-payment hideOnDesktop"
                                 title="<?= translate('calculate_next_payment_date', $i18n) ?>" onClick="autoFillNextPaymentDate(event)">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </div>
                        </label>
                        <div class="date-wrapper">
                            <input type="date" id="next_payment" name="next_payment" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="project_id"><?= translate('associated_project', $i18n) ?></label>
                <select id="project_id" name="project_id">
                    <option value=""><?= translate('no_project', $i18n) ?></option>
                    <?php
                    foreach ($projectsForForm as $project) {
                        echo '<option value="' . htmlspecialchars($project['id']) . '">' . htmlspecialchars($project['name']) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <div class="inline">
                    <div class="split50">
                        <label for="payment_method"><?= translate('payment_method', $i18n) ?></label>
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
                    <div class="split50">
                        <label for="payer_user"><?= translate('paid_by', $i18n) ?></label>
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
                </div>
            </div>

            <div class="form-group">
                <label for="category"><?= translate('category', $i18n) ?></label>
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

            <div class="form-group-inline grow">
                <input type="checkbox" id="notifications" name="notifications" onchange="toggleNotificationDays()">
                <label for="notifications" class="grow"><?= translate('enable_notifications', $i18n) ?></label>
            </div>

            <div class="form-group">
                <div class="inline">
                    <div class="split66 mobile-split-50">
                        <label for="notify_days_before"><?= translate('notify_me', $i18n) ?></label>
                        <select id="notify_days_before" name="notify_days_before" disabled>
                            <option value="-1"><?= translate('default_value_from_settings', $i18n) ?></option>
                            <option value="0"><?= translate('on_due_date', $i18n) ?></option>
                            <option value="1">1 <?= translate('day_before', $i18n) ?></option>
                            <?php
                            for ($i = 2; $i <= 90; $i++) {
                                ?>
                                <option value="<?= $i ?>"><?= $i ?>   <?= translate('days_before', $i18n) ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                    <div class="split33 mobile-split-50">
                        <label for="cancellation_date"><?= translate('cancellation_notification', $i18n) ?></label>
                        <div class="date-wrapper">
                            <input type="date" id="cancellation_date" name="cancellation_date">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <input type="text" id="url" name="url" placeholder="<?= translate('url', $i18n) ?>">
            </div>

            <div class="form-group">
                <input type="text" id="notes" name="notes" placeholder="<?= translate('notes', $i18n) ?>">
            </div>

            <div class="form-group">
                <div class="inline grow">
                    <input type="checkbox" id="inactive" name="inactive" onchange="toggleReplacementSub()">
                    <label for="inactive" class="grow"><?= translate('inactive', $i18n) ?></label>
                </div>
            </div>

            <?php
            // 创建一个按字母排序的订阅列表，用于“替换为”下拉菜单
            $orderedSubscriptions = $subscriptions;
            usort($orderedSubscriptions, function ($a, $b) {
                return strnatcmp(strtolower($a['name']), strtolower($b['name']));
            });
            ?>

            <div class="form-group hide" id="replacement_subscritpion">
                <label for="replacement_subscription_id"><?= translate('replaced_with', $i18n) ?>:</label>
                <select id="replacement_subscription_id" name="replacement_subscription_id">
                    <option value="0"><?= translate('none', $i18n) ?></option>
                    <?php
                    foreach ($orderedSubscriptions as $sub) {
                        if ($sub['inactive'] == 0) { // 只显示活动的订阅作为替换选项
                            ?>
                            <option value="<?= htmlspecialchars($sub['id']) ?>"><?= htmlspecialchars($sub['name']) ?>
                            </option>
                            <?php
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="buttons">
                <input type="button" value="<?= translate('delete', $i18n) ?>" class="warning-button left thin" id="deletesub"
                       style="display: none"> <input type="button" value="<?= translate('cancel', $i18n) ?>" class="secondary-button thin"
                                                     onClick="closeAddSubscription()">
                <input type="submit" value="<?= translate('save', $i18n) ?>" class="thin" id="save-button">
            </div>
        </form>
    </section>

    <script src="scripts/subscriptions.js?<?= $version ?>"></script>
<?php
// 如果URL中带有 ?add 参数，则页面加载后自动打开添加订阅的表单
if (isset($_GET['add'])) {
    ?>
    <script>
        addSubscription();
    </script>
    <?php
}

// 引入页脚文件，通常包含JS脚本链接和HTML的</body>、</html>标签
require_once 'includes/footer.php';
?>