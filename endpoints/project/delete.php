<?php
// --- 基础设置和文件包含 ---
require_once '../../includes/connect_endpoint.php';

// 检查用户是否已登录
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {

    // 检查请求方法是否为 DELETE
    if ($_SERVER["REQUEST_METHOD"] === "DELETE") {

        // 检查URL中是否提供了项目ID
        if (isset($_GET["id"]) && !empty($_GET["id"])) {

            $projectId = intval($_GET["id"]);

            // --- 开始数据库事务 ---
            // 事务可以确保所有SQL操作要么全部成功，要么全部失败
            $db->exec('BEGIN');

            try {
                // --- 第一步：删除该项目下的所有关联订阅 ---
                $deleteSubsQuery = "DELETE FROM subscriptions WHERE project_id = :projectId AND user_id = :userId";
                $deleteSubsStmt = $db->prepare($deleteSubsQuery);
                $deleteSubsStmt->bindParam(':projectId', $projectId, SQLITE3_INTEGER);
                $deleteSubsStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
                $deleteSubsStmt->execute();

                // --- 第二步：删除项目本身 ---
                $deleteProjectQuery = "DELETE FROM projects WHERE id = :projectId AND user_id = :userId";
                $deleteProjectStmt = $db->prepare($deleteProjectQuery);
                $deleteProjectStmt->bindParam(':projectId', $projectId, SQLITE3_INTEGER);
                $deleteProjectStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
                $result = $deleteProjectStmt->execute();

//                var_dump($deleteProjectQuery);
                // --- 关键检查：确认项目真的被删除了 ---
                // changes() 方法返回上一个操作影响的行数
                if ($db->changes() > 0) {
                    // 如果影响的行数大于0，说明删除成功，提交事务
                    $db->exec('COMMIT');
                    // 返回成功状态码
                    http_response_code(204);
                } else {
                    // 如果影响的行数为0，说明没有找到匹配的项目或删除失败，回滚事务
                    $db->exec('ROLLBACK');
                    http_response_code(404); // 404 Not Found 更为合适
                    echo json_encode(array("message" => "未找到要删除的项目或无权限。"));
                }

            } catch (Exception $e) {
                // 如果在事务过程中发生任何错误，回滚所有操作
                $db->exec('ROLLBACK');
                http_response_code(500);
                echo json_encode(array("message" => "删除过程中发生错误: " . $e->getMessage()));
            }

        } else {
            http_response_code(400);
            echo json_encode(array("message" => "未提供项目ID。"));
        }
    } else {
        http_response_code(405);
        echo json_encode(array("message" => "无效的请求方法。"));
    }
} else {
    http_response_code(401);
    echo json_encode(array("message" => "用户未登录。"));
}

// 关闭数据库连接
$db->close();
?>