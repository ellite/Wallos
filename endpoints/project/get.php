<?php
// --- 引入必要的PHP文件 ---
// 引入数据库连接文件，确保能访问 $db 对象和 $userId 变量
require_once '../../includes/connect_endpoint.php';
// （可选）如果需要翻译错误信息，可以引入国际化文件
// require_once '../../i18n/getlang.php';

// --- 主逻辑：检查用户登录和参数 ---

// 检查用户会话是否存在且已登录
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {

    // 检查URL中是否提供了有效的项目ID (id)
    if (isset($_GET['id']) && !empty($_GET['id'])) {

        // --- 从数据库查询项目信息 ---

        // 将获取的ID转换为整数，防止SQL注入
        $projectId = intval($_GET['id']);

        // 准备SQL查询语句，只查询属于当前用户的特定项目
        $query = "SELECT * FROM projects WHERE id = :projectId AND user_id = :userId";

        // 准备SQL预处理语句
        $stmt = $db->prepare($query);

        // 绑定参数
        $stmt->bindParam(':projectId', $projectId, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

        // 执行查询
        $result = $stmt->execute();

        // --- 处理查询结果 ---

        // 尝试从结果中获取一行数据
        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {

            // 创建一个空数组用于存放项目数据
            $projectData = array();

            // 将从数据库查询到的数据填充到数组中
            // htmlspecialchars_decode 用于将已转义的HTML实体（如 &amp;）转换回普通字符（&），确保编辑时显示正确
            $projectData['id'] = $projectId;
            $projectData['name'] = htmlspecialchars_decode($row['name'] ?? "");
            $projectData['logo'] = $row['logo'];
            $projectData['category_id'] = $row['category_id'];
            $projectData['url'] = htmlspecialchars_decode($row['url'] ?? "");
            $projectData['notes'] = htmlspecialchars_decode($row['notes'] ?? "");


            // --- 输出JSON数据 ---

            // 将项目数据数组编码为JSON格式的字符串
            $projectJson = json_encode($projectData);

            // 设置HTTP响应头，告诉浏览器返回的是JSON内容
            header('Content-Type: application/json');

            // 输出JSON字符串
            echo $projectJson;

        } else {
            // 如果根据ID找不到项目，或项目不属于当前用户，则返回错误
            // （为安全起见，不明确指出是“找不到”还是“无权限”）
            header("HTTP/1.1 404 Not Found");
            echo json_encode(["error" => "Project not found"]);
        }
    } else {
        // 如果没有提供ID，返回错误
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(["error" => "Project ID not provided"]);
    }
} else {
    // 如果用户未登录，返回错误
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(["error" => "User not authenticated"]);
}

// 关闭数据库连接
$db->close();
?>