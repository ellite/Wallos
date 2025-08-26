<?php
// --- 基础设置和文件包含 ---
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/getsettings.php';

// --- 文件系统检查 (保持不变，因为Logo都存放在同一目录) ---
if (!file_exists('../../images/uploads/logos')) {
    mkdir('../../images/uploads/logos', 0777, true);
    mkdir('../../images/uploads/logos/avatars', 0777, true);
}

// ===================================================================
// =================== Logo处理函数 (直接复用) ===================
// ===================================================================
// 下面的所有辅助函数 (sanitizeFilename, validateFileExtension, getLogoFromUrl,
// saveLogo, resizeAndUploadLogo) 都是通用的，可以直接从原文件中复制过来使用。
// 为保持代码简洁，此处省略这些函数的具体实现，假设它们已存在。

function sanitizeFilename($filename)
{
    $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
    $filename = str_replace(" ", "-", $filename);
    $filename = str_replace(".", "", $filename);
    return $filename;
}

function validateFileExtension($fileExtension)
{
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

function getLogoFromUrl($url, $uploadDir, $name, $settings, $i18n)
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        $response = [
            "success" => false,
            "errorMessage" => "Invalid URL format."
        ];
        echo json_encode($response);
        exit();
    }

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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

    $imageData = curl_exec($ch);

    if ($imageData !== false) {
        $timestamp = time();
        $fileName = $timestamp . '-' . sanitizeFilename($name) . '.png';
        $uploadDir = '../../images/uploads/logos/';
        $uploadFile = $uploadDir . $fileName;

        if (saveLogo($imageData, $uploadFile, $name, $settings)) {
            curl_close($ch);
            return $fileName;
        } else {
            echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
            curl_close($ch);
            return "";
        }

    } else {
        echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
        curl_close($ch);
        return "";
    }
}


function saveLogo($imageData, $uploadFile, $name, $settings)
{
    $image = imagecreatefromstring($imageData);
    $removeBackground = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';
    if ($image !== false) {
        $tempFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng($image, $tempFile);
        imagedestroy($image);

        if (extension_loaded('imagick')) {
            $imagick = new Imagick($tempFile);
            if ($removeBackground) {
                $fuzz = Imagick::getQuantum() * 0.1; // 10%
                $imagick->transparentPaintImage("rgb(247, 247, 247)", 0, $fuzz, false);
            }
            $imagick->setImageFormat('png');
            $imagick->writeImage($uploadFile);

            $imagick->clear();
            $imagick->destroy();
        } else {
            // Alternative method if Imagick is not available
            $newImage = imagecreatefrompng($tempFile);
            if ($newImage !== false) {
                if ($removeBackground) {
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                    $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                    imagefill($newImage, 0, 0, $transparent);  // Fill the entire image with transparency
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
        unlink($tempFile);

        return true;
    } else {
        return false;
    }
}

function resizeAndUploadLogo($uploadedFile, $uploadDir, $name, $settings)
{
    $targetWidth = 135;
    $targetHeight = 42;

    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $fileExtension = validateFileExtension($fileExtension) ? $fileExtension : 'png';
    $fileName = $timestamp . '-' . sanitizeFilename($name) . '.' . $fileExtension;
    $uploadFile = $uploadDir . $fileName;

    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadFile)) {
        $fileInfo = getimagesize($uploadFile);

        if ($fileInfo !== false) {
            $width = $fileInfo[0];
            $height = $fileInfo[1];

            // Load the image based on its format
            if ($fileExtension === 'png') {
                $image = imagecreatefrompng($uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $image = imagecreatefromjpeg($uploadFile);
            } elseif ($fileExtension === 'gif') {
                $image = imagecreatefromgif($uploadFile);
            } elseif ($fileExtension === 'webp') {
                $image = imagecreatefromwebp($uploadFile);
            } else {
                // Handle other image formats as needed
                return "";
            }

            // Enable alpha channel (transparency) for PNG images
            if ($fileExtension === 'png') {
                imagesavealpha($image, true);
            }

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

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagesavealpha($resizedImage, true);
            $transparency = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
            imagefill($resizedImage, 0, 0, $transparency);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

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

            imagedestroy($image);
            imagedestroy($resizedImage);

            return $fileName;
        }
    }

    return "";
}

// ===================================================================
// =================== 主逻辑：处理项目表单的POST请求 ===================
// ===================================================================

// 检查用户是否已登录
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // 检查请求方法是否为POST
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        // --- 获取并清理项目表单数据 ---

        // 判断是编辑操作还是添加操作 (通过 'project-id' 字段)
        $isEdit = isset($_POST['id']) && !empty($_POST['id']);

        // 获取项目相关的字段
        $name = validate($_POST["name"]);
        $categoryId = $_POST['category_id'];
        $notes = validate($_POST["notes"]);
        $url = validate($_POST['url']);
        $logoUrl = validate($_POST['logo-url']);
        $logo = "";

        // 检查必需字段是否为空
        if (empty($name) || empty($categoryId)) {
            // 返回错误信息
            header("HTTP/1.1 400 Bad Request");
            echo json_encode(['status' => 'Error', 'message' => '项目名称和分类不能为空。']);
            exit();
        }

        // --- Logo处理逻辑 (与原版类似) ---
        if ($logoUrl !== "") {
            $logo = getLogoFromUrl($logoUrl, '../../images/uploads/logos/', $name, $settings, $i18n);
        } else {
            if (!empty($_FILES['logo']['name'])) {
                $fileType = mime_content_type($_FILES['logo']['tmp_name']);
                if (strpos($fileType, 'image') === false) {
                    echo json_encode(['status' => 'Error', 'message' => '上传的文件不是有效的图片。']);
                    exit();
                }
                $logo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $name, $settings);
            }
        }

        // --- 构建SQL语句 ---
        if (!$isEdit) {
            // 如果是添加操作，构建INSERT语句
            $sql = "INSERT INTO projects (name, category_id, notes, url, user_id, logo) 
                    VALUES (:name, :categoryId, :notes, :url, :userId, :logo)";
        } else {
            // 如果是编辑操作，构建UPDATE语句
            $id = $_POST['id'];
            $sql = "UPDATE projects SET 
                        name = :name, 
                        category_id = :categoryId, 
                        notes = :notes, 
                        url = :url";

            // 只有当有新Logo上传或通过URL指定时，才更新logo字段
            if (!empty($logo)) {
                $sql .= ", logo = :logo";
            }

            $sql .= " WHERE id = :id AND user_id = :userId";
        }

        // --- 准备并执行SQL语句 ---
        $stmt = $db->prepare($sql);

        // 绑定所有参数到SQL语句中，防止SQL注入
        $stmt->bindParam(':name', $name, SQLITE3_TEXT);
        $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
        $stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
        $stmt->bindParam(':url', $url, SQLITE3_TEXT);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

        // Logo是可选的，只有在$logo不为空时才绑定
        if (!empty($logo)) {
            $stmt->bindParam(':logo', $logo, SQLITE3_TEXT);
        } else if (!$isEdit) {
            // 如果是新增操作且没有logo，绑定一个NULL值
            $stmt->bindValue(':logo', null, SQLITE3_NULL);
        }

        if ($isEdit) {
            $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
        }

        // 执行预处理语句
        if ($stmt->execute()) {
            // 如果执行成功，返回一个成功的JSON响应
            $success['status'] = "Success";
            $text = $isEdit ? "更新" : "添加";
            $success['message'] = "项目已成功" . $text;

            header('Content-Type: application/json');
            echo json_encode($success);
            exit();
        } else {
            // 如果失败，返回数据库错误信息
            header("HTTP/1.1 500 Internal Server Error");
            echo json_encode(['status' => 'Error', 'message' => '数据库操作失败: ' . $db->lastErrorMsg()]);
        }
    }
}
// 关闭数据库连接
$db->close();
?>