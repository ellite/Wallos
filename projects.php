<?php

// --- 文件包含和初始化 ---

// 引入页头文件，通常包含HTML的<head>部分、CSS链接和数据库连接
require_once 'includes/header.php';
// 引入一个文件，用于从数据库获取一些键值对数据，如分类、成员、支付方式等
require_once 'includes/getdbkeys.php';
// 引入包含核心函数的文件，例如 printSubscriptions()
include_once 'includes/list_projects.php';

// --- 排序和过滤逻辑 ---

// 设置默认排序方式为“下次付款日期”
$sort = "id";

// 检查Cookie中是否存有用户自定义的排序方式，如果有则使用它
if (isset($_COOKIE['sortOrder']) && $_COOKIE['sortOrder'] != "") {
    $sort = $_COOKIE['sortOrder'] ?? 'id';
}

// 初始化用于SQL预处理语句的参数数组
$params = array();


// 记录最终使用的排序方式，用于后续的PHP排序

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
    $sort = "id";
}


$sortOrder = $sort;



// --- 动态构建SQL查询语句 ---

// SQL查询的基础部分
$sql = "SELECT * FROM projects WHERE user_id = :userId";

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

// --- 构建SQL的ORDER BY子句 ---

$orderByClauses = []; // 用于存放多个排序条件的数组

$orderByClauses[] = "id ASC";
// 将所有排序条件合并到SQL语句中
$sql .= " ORDER BY " . implode(", ", $orderByClauses);


// --- 执行数据库查询 ---
//var_dump($sql);
// 准备SQL语句,查询项目
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
// 如果查询成功，将所有结果行提取到$projects数组中
if ($result) {
    $projects = array();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $projects[] = $row;
    }
}
//var_dump($projects);

//获取主货币代码
//$mainCurrencyCode = $currencies[$mainCurrencyId]['code'] ?? 'USD'; // 获取主货币代码

// 准备SQL语句，查询订阅
// 准备SQL语句，查询订阅
// 准备SQL语句，查询订阅
// 准备SQL语句，查询订阅
$sql_sub = "SELECT * FROM subscriptions WHERE user_id = :userId ORDER BY id ASC";
$stmt_sub = $db->prepare($sql_sub);
// 绑定当前用户ID
$stmt_sub->bindValue(':userId', $userId, SQLITE3_INTEGER);
// 执行查询
$result_sub = $stmt_sub->execute();
// 如果查询成功，将所有结果行提取到$subscriptions数组中
if ($result_sub) {
    $subscriptions = array();
    while ($row_sub = $result_sub->fetchArray(SQLITE3_ASSOC)) {
        $subscriptions[] = $row_sub;
    }
}


// --- 数据预处理：将订阅按项目ID分组 ---
$subscriptionsByProject = [];
foreach ($subscriptions as $subscription) {
    // 假设 subscriptions 表中有一个 project_id 字段来关联项目
    $projectId = $subscription['project_id'];
    if (!isset($subscriptionsByProject[$projectId])) {
        $subscriptionsByProject[$projectId] = [];
    }
    $subscriptionsByProject[$projectId][] = $subscription;
}


//var_dump($subscriptionsByProject);
// --- 数据预处理：为每个项目计算聚合数据（总金额、下次付款、是否有效） ---

// 为了效率，在循环开始前只获取一次当前时间
$now = new DateTime();


foreach ($projects as &$project) { // 使用引用&来直接修改数组中的项目
    $totalAmount = 0;
    $nextPaymentDate = null;
    $isActive = false; // 新增：默认为无效状态
    // 此处可能会有bug，项目的货币为该项目最后一个查询到的订阅货币

    // 检查是否存在与当前项目关联的订阅
    if (isset($subscriptionsByProject[$project['id']])) {

        foreach ($subscriptionsByProject[$project['id']] as $subscription) {

            // 累加有效订阅的金额
            if (!$subscription['inactive']) {
                // 注意: 这里假设所有金额都是同一种货币，或已转换为统一货币
                $totalAmount += $subscription['price'];

            }

            // 寻找所有有效订阅中最早的“下次付款日期”
            if (!$subscription['inactive']) {
                $currentNextPayment = new DateTime($subscription['next_payment']);
                if ($nextPaymentDate === null || $currentNextPayment < $nextPaymentDate) {
                    $nextPaymentDate = $currentNextPayment;
                }
            }

            // 【新增逻辑】检查订阅是否未过期
            $subscriptionDueDate = new DateTime($subscription['next_payment']);
            if ($subscriptionDueDate > $now) {
                // 只要找到任意一个未过期的订阅，就将项目状态设为有效
                $isActive = true;

            }
        }
    }

    // 将计算出的结果存入项目数组
    $project['total_amount'] = $totalAmount;
    $project['next_payment_date'] = $nextPaymentDate ? $nextPaymentDate->format('Y-m-d') : null;
    $project['is_active'] = $isActive; // 新增：将计算出的有效状态存入项目数组
//    $project['currency_code'] = $mainCurrencyCode;
//    $project['currency_id'] = $projectCurrencyId; // 使用新的键名 currency_id
    $project['currency_id'] = $userData['main_currency'];

}

unset($project); // 解除最后一个元素的引用
//
//
//
//


// --- 数据后处理：为过滤器菜单计算各项数量 ---

// 1. 安全地重置所有分类和成员的 count (如果存在的话)
foreach ($categories as &$category) {
    if (isset($category['count'])) {
        $category['count'] = 0;
    }
}
unset($category);

foreach ($members as &$member) {
    if (isset($member['count'])) {
        $member['count'] = 0;
    }
}
unset($member);

// 2. 根据当前已筛选出的【项目】来计算其分类的数量
foreach ($projects as $project) {
    $categoryId = $project['category_id'];
    if (isset($categories[$categoryId])) {
        // 安全地增加计数
        if (!isset($categories[$categoryId]['count'])) {
            $categories[$categoryId]['count'] = 0;
        }
        $categories[$categoryId]['count']++;
    }
}

// 3. 根据页面加载的【所有订阅】来计算成员的数量
// (因为过滤器菜单通常需要显示所有可能的成员选项，而不仅仅是当前项目下的)
foreach ($subscriptions as $subscription) {
    $memberId = $subscription['payer_user_id'];
    if (isset($members[$memberId])) {
        // 安全地增加计数
        if (!isset($members[$memberId]['count'])) {
            $members[$memberId]['count'] = 0;
        }
        $members[$memberId]['count']++;
    }
}

// 对于某些需要自定义顺序的排序（不能直接在SQL中完成），在这里使用PHP的usort进行处理
if ($sortOrder == "category_id") {
    usort($projects, function ($a, $b) use ($categories) {
        return $categories[$a['category_id']]['order'] - $categories[$b['category_id']]['order'];
    });
}

if ($sortOrder == "payment_method_id") {
    usort($projects, function ($a, $b) use ($payment_methods) {
        return $payment_methods[$a['payment_method_id']]['order'] - $payment_methods[$b['payment_method_id']]['order'];
    });
}

// 根据是否有订阅数据，决定头部操作栏是否隐藏
$headerClass = count($projects) > 0 ? "main-actions" : "main-actions hidden";
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
        <button class="button" onClick="openAddProject()">
            <i class="fa-solid fa-circle-plus"></i>

            <?= translate('add_project', $i18n) ?>
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
                <?php include 'includes/filters_menu_projects.php'; // 引入过滤器菜单的HTML ?>
            </div>

            <div class="sort-container">
                <button class="button secondary-button" value="Sort" onClick="toggleSortOptions()" id="sort-button"
                        title="<?= translate('sort', $i18n) ?>">
                    <i class="fa-solid fa-arrow-down-wide-short"></i>
                </button>
                <?php include 'includes/sort_options_porjects.php'; // 引入排序选项菜单的HTML ?>
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
        // 再次循环遍历$projects，将原始数据处理成适合显示的$print数组
        foreach ($projects as $project) {
            // 如果设置了“隐藏禁用订阅”，则跳过不处理
//                if ($project['inactive'] == 1 && isset($settings['hideDisabledSubscriptions']) && $settings['hideDisabledSubscriptions'] === 'true') {
//                    continue;
//                }
            $id = $project['id'];
            $print[$id]['id'] = $id;
            // 处理Logo路径

            $print[$id]['logo'] = $project['logo'] != "" ? "images/uploads/logos/" . $project['logo'] : "";
            $print[$id]['name'] = $project['name'];
            $print[$id]['category_id'] = $project['category_id'];
            $print[$id]['url'] = $project['url'];
            $print[$id]['notes'] = $project['notes'];
            $print[$id]['total_amount'] =    $project['total_amount'] ;
            $print[$id]['next_payment_date'] =    $project['next_payment_date'] ;
            $print[$id]['is_active'] =    $project['is_active'] ;
            $print[$id]['currency_id'] =    $project['currency_id'] ;
        }


        if (isset($print)) {
            // 获取排序方向 (与订阅列表的逻辑一致)
            $order = ($sortOrder == "price" || $sortOrder == "id") ? "DESC" : "ASC";

            usort($print, function ($a, $b) use ($sortOrder, $order, $categories) {

                // 根据不同的排序标准，获取要比较的值
                switch ($sortOrder) {
                    case 'name':
                    case 'alphanumeric': // 将'name'和'alphanumeric'视为同一种自然语言排序
                        $valA = $a['name'];
                        $valB = $b['name'];
                        // 对于字符串，直接使用 strnatcasecmp 进行不区分大小写的自然排序
                        return ($order === 'ASC') ? strnatcasecmp($valA, $valB) : strnatcasecmp($valB, $valA);

                    case 'id': // 按创建时间排序
                        $valA = $a['id'];
                        $valB = $b['id'];
                        break;

                    case 'price': // 按总金额排序
                        $valA = $a['total_amount'];
                        $valB = $b['total_amount'];
                        break;

                    case 'next_payment': // 按下次支付时间排序
                        // 将日期转换为时间戳以便比较，null 或无效日期视为0
                        $valA = $a['next_payment_date'] ? strtotime($a['next_payment_date']) : 0;
                        $valB = $b['next_payment_date'] ? strtotime($b['next_payment_date']) : 0;

                        // 将没有日期的项目排在最后
                        if ($valA == 0 && $valB != 0) return 1;
                        if ($valB == 0 && $valA != 0) return -1;
                        break;

                    case 'category_id': // 按分类名称排序
                        $valA = $categories[$a['category_id']]['name'] ?? '';
                        $valB = $categories[$b['category_id']]['name'] ?? '';
                        return ($order === 'ASC') ? strnatcasecmp($valA, $valB) : strnatcasecmp($valB, $valA);

                    case 'inactive': // 按状态排序 (is_active 是布尔值)
                        $valA = $a['is_active'];
                        $valB = $b['is_active'];
                        break;

                    default:
                        return 0; // 如果排序标准未知，则不排序
                }

                // 对数字、日期和布尔值应用统一的排序方向
                if ($order === 'ASC') {
                    return $valA <=> $valB; // 升序
                } else {
                    return $valB <=> $valA; // 降序
                }
            });
        }

        // 关闭数据库连接
        $db->close();


        // 如果$print数组存在（即有数据需要显示）
        if (isset($print)) {
            // 调用核心函数，将处理好的$print数组渲染成HTML
            printProjects(
                $print,
                $subscriptionsByProject,
                $sort,
                $categories,
                $members,
                $i18n,
                $colorTheme,
                "",
                $settings['disabledToBottom'],
                $settings['mobileNavigation'],
                $settings['showSubscriptionProgress'],
                $currencies,
                $lang);
        }
        // 关闭数据库连接
        $db->close();

        // 如果没有任何订阅，则显示一个空状态页面
        if (count($projects) == 0) {
            ?>
            <div class="empty-page">
                <img src="images/siteimages/empty.png" alt="<?= translate('empty_page', $i18n) ?>" />
                <p>
                    <?= translate('no_subscriptions_yet', $i18n) ?>
                </p>
                <button class="button" onClick="openAddProject()">
                    <i class="fa-solid fa-circle-plus"></i>
                    <?= translate('add_first_project', $i18n) ?>
                </button>
            </div>
            <?php
        }
        ?>
    </div>
</section>


<!--新建项目表单-->
<section class="subscription-form" id="project-form">
    <header>
        <h3 id="project-form-title"><?= translate('add_project', $i18n) ?></h3>
        <span class="fa-solid fa-xmark close-form" onClick="closeAddProject()"></span>
    </header>
    <form action="endpoints/project/add_project.php" method="post" id="project-subs-form">

        <div class="form-group-inline">
            <input type="text" id="project-name" name="name" placeholder="<?= translate('project_name', $i18n) ?>"
                   onchange="setProjectSearchButtonStatus()" required>
            <label for="project-logo" class="logo-preview">
                <img src="" alt="<?= translate('logo_preview', $i18n) ?>" id="project-form-logo">
            </label>
            <input type="file" id="project-logo" name="logo" accept="image/jpeg, image/png, image/gif, image/webp, image/svg+xml"
                   onchange="handleProjectFileSelect(event)" class="hidden-input">
            <input type="hidden" id="project-logo-url" name="logo-url">
            <div id="project-logo-search-button" class="image-button medium disabled" title="<?= translate('search_logo', $i18n) ?>"
                 onClick="searchProjectLogo()">
                <?php include "images/siteicons/svg/websearch.php"; ?>
            </div>
            <input type="hidden" id="project-id" name="id">
            <div id="project-logo-search-results" class="logo-search">
                <header>
                    <?= translate('search_logo', $i18n) ?>
                    <span class="fa-solid fa-xmark close-logo-search" onClick="closeProjectLogoSearch()"></span>
                </header>
                <div id="project-logo-search-images"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="project-category"><?= translate('project_category', $i18n) ?></label>
            <select id="project-category" name="category_id">
                <?php
                // 复用PHP变量循环生成分类选项
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
            <input type="text" id="project-url" name="url" placeholder="<?= translate('project_website', $i18n) ?>">
        </div>

        <div class="form-group">
            <input type="text" id="project-notes" name="notes" placeholder="<?= translate('notes', $i18n) ?>">
        </div>

        <div class="buttons">
            <input type="button" value="<?= translate('delete', $i18n) ?>" class="warning-button left thin" id="delete-project-btn"
                   style="display: none">
            <input type="button" value="<?= translate('cancel', $i18n) ?>" class="secondary-button thin"
                   onClick="closeAddProject()">
            <input type="submit" value="<?= translate('save', $i18n) ?>" class="thin" id="save-project-btn">
        </div>
    </form>
</section>


<!--新建订阅表单-->

<section class="subscription-form" id="subscription-form">
    <header>
        <h3 id="form-title"><?= translate('add_subscription', $i18n) ?></h3>
        <span class="fa-solid fa-xmark close-form" onClick="closeAddSubscription()"></span>
    </header>
    <form action="endpoints/subscription/add.php" method="post" id="subs-form">
        <input type="hidden" id="project_id" name="project_id" value="">

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

<script src="scripts/projects.js?<?= $version ?>"></script>

<?php
// 如果URL中带有 ?add 参数，则页面加载后自动打开添加订阅的表单
if (isset($_GET['add'])) {
    ?>
    <script>
        openAddProject();
    </script>
    <?php
}

// 引入页脚文件，通常包含JS脚本链接和HTML的</body>、</html>标签
require_once 'includes/footer.php';
?>
