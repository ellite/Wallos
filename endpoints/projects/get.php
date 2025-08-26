<?php
// --- 基础设置和文件包含 ---
// 引入数据库连接、设置、辅助数据等必要文件


require_once '../../includes/connect_endpoint.php';
require_once '../../includes/getdbkeys.php';
include_once '../../includes/list_projects.php'; // 确保引入的是包含 printProjects() 的文件
include_once '../../includes/checksession.php';
require_once '../../includes/getsettings.php';
require_once '../../includes/currency_formatter.php';

// 检查用户是否已登录
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {

// 设置默认排序方式为“新建顺序”
    $sort = "id";

// 初始化用于SQL预处理语句的参数数组
    $params = array();

// 检查Cookie中是否存有用户自定义的排序方式，如果有则使用它
    if (isset($_COOKIE['sortOrder']) && $_COOKIE['sortOrder'] != "") {
        $sort = $_COOKIE['sortOrder'] ?? 'id';
    }



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
    // 记录最终使用的排序方式，用于后续的PHP排序
    $sortOrder = $sort;
//    var_dump($sortOrder);
//    exit();

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


// 如果URL参数中有'categories'，则添加分类过滤条件
    if (isset($_GET['categories'])) {
        $categoryIds = explode(',', $_GET['categories']);
        $placeholders = array_map(function ($key) {
            return ":categories{$key}";
        }, array_keys($categoryIds));
        $sql .= " AND category_id IN (" . implode(',', $placeholders) . ")";
        foreach ($categoryIds as $key => $categoryId) {
            $params[":categories{$key}"] = $categoryId;
        }

    }


// --- 构建SQL的ORDER BY子句 ---

    $orderByClauses = []; // 用于存放多个排序条件的数组

    $orderByClauses[] = "id ASC";
// 将所有排序条件合并到SQL语句中
    $sql .= " ORDER BY " . implode(", ", $orderByClauses);


// --- 执行数据库查询 ---
//var_dump($sql);
//var_dump($params);
//var_dump($userId);

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
//    var_dump($projects);
//    exit();
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




// --- 数据预处理：为每个项目计算聚合数据（总金额、下次付款、是否有效） ---
// 为了效率，在循环开始前只获取一次当前时间
    $now = new DateTime();

    foreach ($projects as &$project) { // 使用引用&来直接修改数组中的项目
        $totalAmount = 0;
        $nextPaymentDate = null;
        $isActive = false; // 新增：默认为无效状态

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
        $project['currency_id'] = $userData['main_currency'];
    }
    unset($project); // 解除最后一个元素的引用


//    var_dump($projects);
//    exit();

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

        // 调用函数计算当前订阅周期的进度
        $print[$id]['url'] = $project['url'];
        $print[$id]['notes'] = $project['notes'];
        $print[$id]['total_amount'] =    $project['total_amount'] ;
        $print[$id]['next_payment_date'] =    $project['next_payment_date'] ;
        $print[$id]['is_active'] =    $project['is_active'] ;
        $print[$id]['currency_id'] =    $project['currency_id'] ;

    }

    // 对于字母排序，需要在这里用PHP的自然排序算法处理，比SQL的ORDER BY更准确
//    if ($sortOrder == "name") {
//        usort($print, function ($a, $b) {
//            return strnatcmp(strtolower($a['name']), strtolower($b['name']));
//        });
//        // 如果需要，将禁用的订阅排在底部
//        if ($settings['disabledToBottom'] === 'true') {
//            usort($print, function ($a, $b) {
//                return $a['inactive'] - $b['inactive'];
//            });
//        }
//    }

// --- 【新增】根据用户选择，在PHP中对 $print 数组进行最终排序 ---

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

    // --- 最终渲染输出 ---
    if (!empty($print)) {
        // 调用主函数 printProjects 来渲染HTML
        // 注意 $imagePath 路径需要根据当前文件位置进行调整
        printProjects($print, $subscriptionsByProject, $sort, $categories, $members, $i18n, $settings['theme'], "../../", $settings['disabledToBottom'], $settings['mobileNavigation'], $settings['showSubscriptionProgress'], $currencies, $lang);
    } else {
        // 如果过滤后没有匹配的项目，显示提示信息
        ?>
        <div class="no-matching-subscriptions">
            <p><?= translate('no_matching_subscriptions', $i18n) ?></p>
            <button class="button" onClick="clearFilters()">
                <span clasS="fa-solid fa-minus-circle"></span>
                <?= translate('clear_filters', $i18n) ?>
            </button>
            <img src="../../images/siteimages/empty.png" alt="<?= translate('empty_page', $i18n) ?>" />
        </div>
        <?php
    }
}

// 关闭数据库连接
$db->close();
?>