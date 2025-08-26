<?php
// 设置错误报告级别，只报告严重错误和解析错误，忽略警告和通知
error_reporting(E_ERROR | E_PARSE);

// --- 文件包含 ---
// 引入数据库连接文件，确保能访问 $db 对象
require_once '../../includes/connect_endpoint.php';
// 引入输入验证函数库，例如 validate() 函数
require_once '../../includes/inputvalidation.php';
// 引入获取用户设置的文件
require_once '../../includes/getsettings.php';

// --- 文件系统检查和准备 ---
// 检查用于上传Logo的目录是否存在，如果不存在则创建它
if (!file_exists('../../images/uploads/logos')) {
    // 递归创建目录，并设置权限为 0777 (完全可读写执行)
    mkdir('../../images/uploads/logos', 0777, true);
    mkdir('../../images/uploads/logos/avatars', 0777, true);
}


// --- 辅助函数定义 ---

/**
 * 清理文件名，移除特殊字符，并将空格替换为连字符。
 * @param string $filename 原始文件名
 * @return string 清理后的文件名
 */
function sanitizeFilename($filename)
{
    // 只保留字母、数字和空格
    $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
    // 将空格替换为连字符
    $filename = str_replace(" ", "-", $filename);
    // 移除点号
    $filename = str_replace(".", "", $filename);
    return $filename;
}

/**
 * 验证文件扩展名是否在允许的列表中。
 * @param string $fileExtension 文件扩展名
 * @return bool 是否有效
 */
function validateFileExtension($fileExtension)
{
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

/**
 * 从一个URL获取图片，并保存为Logo。
 * @param string $url 图片的URL
 * @param string $uploadDir 上传目录
 * @param string $name 订阅名称，用于生成文件名
 * @return string 成功则返回保存后的文件名，失败则返回空字符串
 */
function getLogoFromUrl($url, $uploadDir, $name, $settings, $i18n)
{
    // 验证URL格式和安全性
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        // ... 无效URL错误处理
        $response = [
            "success" => false,
            "errorMessage" => "Invalid URL format."
        ];
        echo json_encode($response);
        exit();
    }
    // 防止服务器请求伪造 (SSRF) 攻击，不允许访问私有或保留IP地址
    $host = parse_url($url, PHP_URL_HOST);
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $response = [
            "success" => false,
            "errorMessage" => "Invalid IP Address."
        ];
        echo json_encode($response);
        exit();
    }

    // 使用cURL获取图片数据
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 允许重定向
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

    $imageData = curl_exec($ch);

    if ($imageData !== false) {
        // 生成一个带时间戳的唯一文件名
        $timestamp = time();
        $fileName = $timestamp . '-' . sanitizeFilename($name) . '.png';
        $uploadDir = '../../images/uploads/logos/';
        $uploadFile = $uploadDir . $fileName;

        // 调用 saveLogo 函数处理并保存图片
        if (saveLogo($imageData, $uploadFile, $name, $settings)) {
            curl_close($ch);
            return $fileName;
        } else {
            // ... 错误处理
            echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
            curl_close($ch);
            return "";
        }

    } else {
        // ... cURL错误处理
        echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
        curl_close($ch);
        return "";
    }
}

/**
 * 处理并保存Logo图片数据，可选移除背景。
 * @param string $imageData 图片的二进制数据
 * @param string $uploadFile 完整的保存路径
 * @return bool 是否保存成功
 */
function saveLogo($imageData, $uploadFile, $name, $settings)
{
    $image = imagecreatefromstring($imageData); // 从字符串创建图像资源
    // 检查设置中是否开启了“移除背景”
    $removeBackground = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';
    if ($image !== false) {
        // 将图像资源保存为PNG到临时文件，以便后续处理
        $tempFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng($image, $tempFile);
        imagedestroy($image);

        // 如果服务器安装了 Imagick 扩展（功能更强大），则优先使用
        if (extension_loaded('imagick')) {
            $imagick = new Imagick($tempFile);
            if ($removeBackground) {
                // 移除与白色相近的背景色
                $fuzz = Imagick::getQuantum() * 0.1; // 10%的容差
                $imagick->transparentPaintImage("rgb(247, 247, 247)", 0, $fuzz, false);
            }
            $imagick->setImageFormat('png');
            $imagick->writeImage($uploadFile); // 写入最终文件

            $imagick->clear();
            $imagick->destroy();
        } else {
            // 如果没有 Imagick，使用PHP原生的GD库作为备用方案
            $newImage = imagecreatefrompng($tempFile);
            if ($newImage !== false) {
                if ($removeBackground) {
                    // GD库移除背景的方法（效果可能不如Imagick）
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                    $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                    imagefill($newImage, 0, 0, $transparent);
                    imagepng($newImage, $uploadFile);
                    imagedestroy($newImage);
                }
                imagepng($newImage, $uploadFile);
                imagedestroy($newImage);
            } else {
                unlink($tempFile);
                return false;
            }
        }
        unlink($tempFile); // 删除临时文件

        return true;
    } else {
        return false;
    }
}

/**
 * 调整上传的Logo图片尺寸并保存。
 * @param array $uploadedFile PHP的$_FILES['logo']数组
 * @return string 成功则返回保存后的文件名，失败则返回空字符串
 */
function resizeAndUploadLogo($uploadedFile, $uploadDir, $name, $settings)
{
    $targetWidth = 135; // 目标宽度
    $targetHeight = 42; // 目标高度

    // 生成唯一文件名
    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    // 验证并修正扩展名
    $fileExtension = validateFileExtension($fileExtension) ? $fileExtension : 'png';
    $fileName = $timestamp . '-' . sanitizeFilename($name) . '.' . $fileExtension;
    $uploadFile = $uploadDir . $fileName;

    // 将上传的临时文件移动到最终目录
    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadFile)) {
        $fileInfo = getimagesize($uploadFile);

        if ($fileInfo !== false) {
            $width = $fileInfo[0];
            $height = $fileInfo[1];

            // 根据文件类型创建图像资源
            if ($fileExtension === 'png') {
                $image = imagecreatefrompng($uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $image = imagecreatefromjpeg($uploadFile);
            } elseif ($fileExtension === 'gif') {
                $image = imagecreatefromgif($uploadFile);
            } elseif ($fileExtension === 'webp') {
                $image = imagecreatefromwebp($uploadFile);
            } else {
                // 如果格式不支持，则返回空
                return "";
            }

            // 为PNG图片启用透明通道
            if ($fileExtension === 'png') {
                imagesavealpha($image, true);
            }

            // 计算新的尺寸，保持宽高比
            $newWidth = $width;
            $newHeight = $height;

            if ($width > $targetWidth) {
                $newWidth = (int) $targetWidth;
                $newHeight = (int) (($targetWidth / $width) * $height);
            }

            if ($newHeight > $targetHeight) {
                $newWidth = (int) (($targetHeight / $newHeight) * $newWidth);
                $newHeight = (int) $targetHeight;
            }

            // 创建一个新的真彩色图像画布用于缩放
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagesavealpha($resizedImage, true);
            $transparency = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
            imagefill($resizedImage, 0, 0, $transparency);
            // 将原图重采样到新画布上
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // 根据文件类型保存缩放后的图片
            if ($fileExtension === 'png') {
                imagepng($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                imagejpeg($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'gif') {
                imagegif($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'webp') {
                imagewebp($resizedImage, $uploadFile);
            } else {
                return "";
            }

            // 释放内存
            imagedestroy($image);
            imagedestroy($resizedImage);

            return $fileName;
        }
    }

    return "";
}


// --- 主逻辑：处理POST请求 ---

// 检查用户是否已登录
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // 检查请求方法是否为POST
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // --- 获取并清理表单数据 ---
        // 判断是编辑操作还是添加操作。如果POST请求中包含有效的'id'，则为编辑模式。
        $isEdit = isset($_POST['id']) && $_POST['id'] != "";
        // 从POST请求中获取订阅名称，并使用validate()函数进行清理，防止XSS等攻击。
        $name = validate($_POST["name"]);
        // 获取价格。
//        $price = $_POST['price'];
        // 获取货币ID。
//        $currencyId = $_POST["currency_id"];
        // 获取计费频率（例如，每 “3” 天/周/月/年）。
//        $frequency = $_POST["frequency"];
        // 获取计费周期（1:天, 2:周, 3:月, 4:年）。
//        $cycle = $_POST["cycle"];
        // 获取下次付款日期。
//        $nextPayment = $_POST["next_payment"];
        // 检查“自动续费”复选框是否被勾选。如果勾选了，值为true，否则为false。
//        $autoRenew = isset($_POST['auto_renew']) ? true : false;
        // 获取订阅开始日期。
//        $startDate = $_POST["start_date"];
        // 获取支付方式ID。
//        $paymentMethodId = $_POST["payment_method_id"];
        // 获取付款人用户ID。
//        $payerUserId = $_POST["payer_user_id"];
        // 获取分类ID。
        $categoryId = $_POST['category_id'];
        // 获取备注信息，并进行安全清理。
        $notes = validate($_POST["notes"]);
        // 获取订阅相关的网址，并进行安全清理。
        $url = validate($_POST['url']);
        // 获取从网络搜索并选中的Logo图片的URL，并进行安全清理。
        $logoUrl = validate($_POST['logo-url']);
        // 初始化$logo变量，用于后续存储处理过的Logo文件名。
        $logo = "";
        // 检查“启用通知”复选框是否被勾选。
//        $notify = isset($_POST['notifications']) ? true : false;
        // 获取提前通知的天数。
//        $notifyDaysBefore = $_POST['notify_days_before'];
        // 检查“设为非活动”复选框是否被勾选。
//        $inactive = isset($_POST['inactive']) ? true : false;
        // 获取取消日期，如果未设置则为null。
//        $cancellationDate = $_POST['cancellation_date'] ?? null;
        // 获取用于替换此订阅的新订阅ID。
//        $replacementSubscriptionId = $_POST['replacement_subscription_id'];

        // 如果“替换为”字段为0或未勾选“停用”，则将其设为null
//        if ($replacementSubscriptionId == 0 || $inactive == 0) {
//            $replacementSubscriptionId = null;
//        }

        // --- Logo处理逻辑 ---
        if ($logoUrl !== "") {
            // 如果是通过URL提供的Logo，则调用getLogoFromUrl
            $logo = getLogoFromUrl($logoUrl, '../../images/uploads/logos/', $name, $settings, $i18n);
        } else {
            // 如果是本地上传的Logo文件
            if (!empty($_FILES['logo']['name'])) {
                // 验证文件类型
                $fileType = mime_content_type($_FILES['logo']['tmp_name']);
                if (strpos($fileType, 'image') === false) {
                    echo translate("fill_all_fields", $i18n);
                    exit();
                }
                // 调用resizeAndUploadLogo处理上传
                $logo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $name, $settings);
            }
        }

        // --- 构建SQL语句 ---
        if (!$isEdit) {
            // 如果是添加操作，构建INSERT语句
            $sql = "INSERT INTO projects (
                        name, logo,user_id, notes , category_id, url
                    ) VALUES (
                    	:name, :logo, :userId, :notes,  :categoryId, :url
                    )";
        } else {
            // 如果是编辑操作，构建UPDATE语句
            $id = $_POST['id'];
            $sql = "UPDATE projects SET 
                        name = :name, 
                        notes = :notes, 
                        category_id = :categoryId, 
                        url = :url";

            // 如果有新的Logo上传，才更新logo字段
            if ($logo != "") {
                $sql .= ", logo = :logo";
            }

            $sql .= " WHERE id = :id AND user_id = :userId";
        }

        // --- 准备并执行SQL语句 ---

        $stmt = $db->prepare($sql);

        // 绑定所有参数到SQL语句中，防止SQL注入
        $stmt->bindParam(':name', $name, SQLITE3_TEXT);
        if ($logo != "") {
            $stmt->bindParam(':logo', $logo, SQLITE3_TEXT);
        }
        $stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
        $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
        $stmt->bindParam(':url', $url, SQLITE3_TEXT);
        if ($isEdit) {
            $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
        }
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

        // 执行预处理语句
        if ($stmt->execute()) {
            // 如果执行成功，返回一个成功的JSON响应
            $success['status'] = "Success";
            $text = $isEdit ? "updated" : "added";
            $success['message'] = translate('subscription_' . $text . '_successfuly', $i18n);
            $json = json_encode($success);
            header('Content-Type: application/json');
            echo $json;
            exit();
        } else {
            // 如果失败，返回数据库错误信息
            echo translate('error', $i18n) . ": " . $db->lastErrorMsg();
        }
    }
}
// 关闭数据库连接
$db->close();
?>