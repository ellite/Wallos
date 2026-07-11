<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user (for Wallos authentication).
- action: the action to perform ('add', 'edit', 'delete').
- name: (required for 'add', optional for 'edit') the name of the payment method.
- enabled: (optional for 'add' and 'edit'; '1' for enabled, '0' for disabled).
- icon_url: (optional for 'add' and 'edit') the URL of the icon to fetch.
- paymenticon: (optional for 'add' and 'edit') the uploaded image file.
- id / paymentId: (required for 'edit' and 'delete') the ID of the payment method.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).
- paymentId: (only for successful 'add' action) the ID of the newly created payment method (integer).

Example response:
{
  "success": true,
  "title": "Payment method added",
  "paymentId": 32,
  "message": "Payment method added successfully."
}
*/

error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/ssrf_helper.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid request method',
        'message' => 'Only POST requests are allowed.'
    ]);
    exit;
}

$apiKey = $_POST['api_key'] ?? $_POST['apiKey'] ?? null;

// Authenticate user first
if (!$apiKey) {
    echo json_encode([
        'success' => false,
        'title' => 'Missing API key',
        'message' => 'API key is required.'
    ]);
    exit;
}

$sql = "SELECT * FROM user WHERE api_key = :apiKey";
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    echo json_encode([
        'success' => false,
        'title' => 'Unauthorized',
        'message' => 'Invalid API key.'
    ]);
    exit;
}

$userId = $user['id'];
$action = $_POST['action'] ?? null;

if (!$action || !in_array($action, ['add', 'edit', 'delete'], true)) {
    echo json_encode([
        'success' => false,
        'title' => 'Invalid action',
        'message' => 'Action must be "add", "edit", or "delete".'
    ]);
    exit;
}

// Load user settings now that user is authenticated
require_once '../../includes/getsettings.php';

if (!file_exists('../../images/uploads/logos')) {
    mkdir('../../images/uploads/logos', 0777, true);
    mkdir('../../images/uploads/logos/avatars', 0777, true);
}

// Image Helper Functions
function sanitizeFilename($filename)
{
    $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
    $filename = str_replace(" ", "-", $filename);
    $filename = str_replace(".", "", $filename);
    return $filename;
}

function validateFileExtension($fileExtension)
{
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'jtif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

function getLogoFromUrl($url, $uploadDir, $name, $settings)
{
    $currentUrl = $url;
    $maxRedirects = 3;

    for ($i = 0; $i <= $maxRedirects; $i++) {
        if (!filter_var($currentUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $currentUrl)) {
            return ["success" => false, "message" => "Invalid URL format."];
        }

        $host = parse_url($currentUrl, PHP_URL_HOST);
        $port = parse_url($currentUrl, PHP_URL_PORT) ?: (parse_url($currentUrl, PHP_URL_SCHEME) === 'https' ? 443 : 80);
        $ip = gethostbyname($host);

        $is_private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false 
                      || is_cgnat_ip($ip);

        if ($is_private) {
            return ["success" => false, "message" => "Invalid IP Address."];
        }

        $ch = curl_init($currentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Wallos/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); 
        curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$ip}"]);

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 300 && $httpCode < 400) {
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            if (!$redirectUrl) break;
            $currentUrl = $redirectUrl;
            continue;
        }

        if ($imageData !== false && $httpCode === 200) {
            $timestamp = time();
            $fileName = $timestamp . '-payments-' . sanitizeFilename($name) . '.png';
            $uploadFile = rtrim($uploadDir, '/') . '/' . $fileName;

            if (saveLogo($imageData, $uploadFile, $name, $settings)) {
                unset($ch);
                return $fileName;
            }
        }

        unset($ch);
        break; 
    }

    return "";
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
            $imagick->trimImage(0);
            $imagick->setImagePage(0, 0, 0, 0);
            $imagick->borderImage(new ImagickPixel('transparent'), 2, 2);
            $imagick->setImageFormat('png');
            $imagick->writeImage($uploadFile);
            $imagick->clear();
            $imagick->destroy();
        } else {
            $newImage = imagecreatefrompng($tempFile);
            if ($removeBackground) {
                require_once __DIR__ . '/../../includes/gd_background_removal.php';
                gdRemoveBackgroundColor($newImage, 247, 247, 247);
            }
            require_once __DIR__ . '/../../includes/gd_background_removal.php';
            $newImage = gdCropTransparent($newImage, 2);
            imagepng($newImage, $uploadFile);
            imagedestroy($newImage);
        }
        unlink($tempFile);
        return true;
    }
    return false;
}

function resizeAndUploadLogo($uploadedFile, $uploadDir, $name)
{
    $targetWidth = 70;
    $targetHeight = 48;

    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $fileExtension = validateFileExtension($fileExtension) ? $fileExtension : 'png';
    $fileName = $timestamp . '-payments-' . sanitizeFilename($name) . '.' . $fileExtension;
    $uploadFile = $uploadDir . $fileName;

    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadFile)) {
        $fileInfo = getimagesize($uploadFile);

        if ($fileInfo !== false) {
            $width = $fileInfo[0];
            $height = $fileInfo[1];

            if ($fileExtension === 'png') {
                $image = imagecreatefrompng($uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $image = imagecreatefromjpeg($uploadFile);
            } elseif ($fileExtension === 'gif') {
                $image = imagecreatefromgif($uploadFile);
            } elseif ($fileExtension === 'webp') {
                $image = imagecreatefromwebp($uploadFile);
            } else {
                return "";
            }

            if ($fileExtension === 'png') {
                imagesavealpha($image, true);
            }

            require_once __DIR__ . '/../../includes/gd_background_removal.php';
            $image = gdCropTransparent($image, 2);
            $width = imagesx($image);
            $height = imagesy($image);

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

switch ($action) {
    case 'add':
        $name = $_POST['name'] ?? null;
        $enabled = $_POST['enabled'] ?? '1';
        $iconUrl = $_POST['icon_url'] ?? $_POST['icon-url'] ?? '';

        if (!$name || trim($name) === '') {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameter',
                'message' => 'Parameter "name" is required and cannot be empty.'
            ]);
            exit;
        }

        $name = validate($name);
        $enabled = ($enabled === '0' || $enabled === 0) ? 0 : 1;

        $icon = "";
        if ($iconUrl !== "") {
            $icon = getLogoFromUrl($iconUrl, '../../images/uploads/logos/', $name, $settings);
        } elseif (!empty($_FILES['paymenticon']['name'])) {
            $fileType = mime_content_type($_FILES['paymenticon']['tmp_name']);
            if (strpos($fileType, 'image') === false) {
                echo json_encode([
                    "success" => false,
                    "title" => "Invalid file type",
                    "message" => "The uploaded file must be an image."
                ]);
                exit();
            }
            $icon = resizeAndUploadLogo($_FILES['paymenticon'], '../../images/uploads/logos/', $name);
        }

        // Get the maximum existing ID
        $stmtMax = $db->prepare("SELECT MAX(id) as maxID FROM payment_methods");
        $resultMax = $stmtMax->execute();
        $rowMax = $resultMax->fetchArray(SQLITE3_ASSOC);
        $maxID = $rowMax['maxID'] ?? 0;

        // Ensure custom ID is >= 32
        $newID = max($maxID + 1, 32);

        // Insert
        $sqlInsert = "INSERT INTO payment_methods (id, name, icon, enabled, user_id) VALUES (:id, :name, :icon, :enabled, :userId)";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':id', $newID, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':name', $name, SQLITE3_TEXT);
        $stmtInsert->bindParam(':icon', $icon, SQLITE3_TEXT);
        $stmtInsert->bindParam(':enabled', $enabled, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);

        if ($stmtInsert->execute()) {
            echo json_encode([
                'success' => true,
                'title' => 'Payment method added',
                'paymentId' => $newID,
                'message' => 'Payment method added successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to add payment method: ' . $db->lastErrorMsg()
            ]);
        }
        break;

    case 'edit':
        $paymentId = $_POST['paymentId'] ?? $_POST['id'] ?? null;
        if (!$paymentId) {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameter',
                'message' => 'Parameter "id" (or "paymentId") is required.'
            ]);
            exit;
        }
        $paymentId = intval($paymentId);

        // Check ownership
        $checkSql = "SELECT * FROM payment_methods WHERE id = :paymentId AND user_id = :userId";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindValue(':paymentId', $paymentId, SQLITE3_INTEGER);
        $checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $paymentMethod = $checkResult->fetchArray(SQLITE3_ASSOC);

        if (!$paymentMethod) {
            echo json_encode([
                'success' => false,
                'title' => 'Unauthorized or Not Found',
                'message' => 'Payment method not found or does not belong to you.'
            ]);
            exit;
        }

        // Get current values
        $name = $_POST['name'] ?? $paymentMethod['name'];
        $name = validate($name);

        $enabled = $paymentMethod['enabled'];
        if (isset($_POST['enabled'])) {
            $enabled = ($_POST['enabled'] === '0' || $_POST['enabled'] === 0) ? 0 : 1;
        }

        $icon = $paymentMethod['icon'];
        $iconUrl = $_POST['icon_url'] ?? $_POST['icon-url'] ?? '';

        if ($iconUrl !== "") {
            $icon = getLogoFromUrl($iconUrl, '../../images/uploads/logos/', $name, $settings);
        } elseif (!empty($_FILES['paymenticon']['name'])) {
            $fileType = mime_content_type($_FILES['paymenticon']['tmp_name']);
            if (strpos($fileType, 'image') === false) {
                echo json_encode([
                    "success" => false,
                    "title" => "Invalid file type",
                    "message" => "The uploaded file must be an image."
                ]);
                exit();
            }
            $icon = resizeAndUploadLogo($_FILES['paymenticon'], '../../images/uploads/logos/', $name);
        }

        // Update
        $sqlUpdate = "UPDATE payment_methods SET name = :name, icon = :icon, enabled = :enabled WHERE id = :paymentId AND user_id = :userId";
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':name', $name, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':icon', $icon, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':enabled', $enabled, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':paymentId', $paymentId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':userId', $userId, SQLITE3_INTEGER);

        if ($stmtUpdate->execute()) {
            echo json_encode([
                'success' => true,
                'title' => 'Payment method updated',
                'message' => 'Payment method updated successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to update payment method: ' . $db->lastErrorMsg()
            ]);
        }
        break;

    case 'delete':
        $paymentId = $_POST['paymentId'] ?? $_POST['id'] ?? null;
        if (!$paymentId) {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameter',
                'message' => 'Parameter "id" (or "paymentId") is required.'
            ]);
            exit;
        }
        $paymentId = intval($paymentId);

        // Check ownership
        $checkSql = "SELECT * FROM payment_methods WHERE id = :paymentId AND user_id = :userId";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindValue(':paymentId', $paymentId, SQLITE3_INTEGER);
        $checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $paymentMethod = $checkResult->fetchArray(SQLITE3_ASSOC);

        if (!$paymentMethod) {
            echo json_encode([
                'success' => false,
                'title' => 'Unauthorized or Not Found',
                'message' => 'Payment method not found or does not belong to you.'
            ]);
            exit;
        }

        // Check if in use in subscriptions
        $checkUseSql = "SELECT COUNT(*) FROM subscriptions WHERE payment_method_id = :paymentId AND user_id = :userId";
        $checkUseStmt = $db->prepare($checkUseSql);
        $checkUseStmt->bindParam(':paymentId', $paymentId, SQLITE3_INTEGER);
        $checkUseStmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $checkUseResult = $checkUseStmt->execute();
        $row = $checkUseResult->fetchArray();
        $count = $row[0] ?? 0;

        if ($count > 0) {
            echo json_encode([
                'success' => false,
                'title' => 'Payment method in use',
                'message' => 'This payment method cannot be deleted because it is in use by one or more subscriptions.'
            ]);
            exit;
        }

        // Delete
        $sqlDelete = "DELETE FROM payment_methods WHERE id = :paymentId AND user_id = :userId";
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->bindParam(':paymentId', $paymentId, SQLITE3_INTEGER);
        $stmtDelete->bindParam(':userId', $userId, SQLITE3_INTEGER);

        if ($stmtDelete->execute()) {
            echo json_encode([
                'success' => true,
                'title' => 'Payment method deleted',
                'message' => 'Payment method deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to delete payment method: ' . $db->lastErrorMsg()
            ]);
        }
        break;
}

$db->close();
?>
