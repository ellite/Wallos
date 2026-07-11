<?php
/*
This API Endpoint accepts POST requests only.
It receives the following parameters:
- api_key: the API key of the user (for Wallos authentication).
- action: the action to perform ('add', 'edit', 'delete').
- id / subscription_id: (required for 'edit' and 'delete') the ID of the subscription.

For 'add' and 'edit' actions (all optional for 'edit'):
- name: the name of the subscription.
- price: the price of the subscription (float).
- currency_id: the currency ID of the subscription (integer).
- frequency: the payment frequency (integer).
- cycle: the payment cycle (integer: 1-days, 2-weeks, 3-months, 4-years).
- next_payment: the next payment date (YYYY-MM-DD).
- start_date: the start date of the subscription (YYYY-MM-DD).
- auto_renew: whether the subscription auto renews (1 or 0, default 1).
- payment_method_id: the payment method ID (integer).
- payer_user_id: the household member payer ID (integer).
- category_id: the category ID (integer).
- notes: subscription notes (string).
- url: subscription URL (string).
- logo_url: an image URL to download as the logo (string).
- logo: direct image file upload for the logo.
- notify / notifications: whether to send payment notifications (1 or 0).
- notify_days_before: how many days before to send notification (integer).
- inactive: whether the subscription is inactive (1 or 0).
- cancellation_date: the cancellation date (YYYY-MM-DD).
- replacement_subscription_id: the ID of the replacement subscription (integer).

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- message: detailed information or error message (string).
- subscriptionId: (only for successful 'add' action) the ID of the newly created subscription (integer).

Example response:
{
  "success": true,
  "title": "Subscription added",
  "subscriptionId": 55,
  "message": "Subscription added successfully."
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
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

function getLogoFromUrl($url, $uploadDir, $name, $settings)
{
    $maxRedirects = 3;
    $currentUrl = $url;

    for ($i = 0; $i <= $maxRedirects; $i++) {
        if (!filter_var($currentUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $currentUrl)) {
            return ['success' => false, 'message' => 'Invalid URL format.'];
        }

        $parts = parse_url($currentUrl);
        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $ip = gethostbyname($host);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false 
            || is_cgnat_ip($ip)) {
            return ['success' => false, 'message' => 'Invalid IP Address.'];
        }

        $ch = curl_init($currentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RESOLVE, ["$host:$port:$ip"]);

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 300 && $httpCode < 400) {
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            unset($ch);
            if (!$redirectUrl) {
                break;
            }
            $currentUrl = $redirectUrl;
            continue; 
        }

        if ($imageData !== false && $httpCode === 200) {
            $timestamp = time();
            $fileName = $timestamp . '-' . sanitizeFilename($name) . '.png';
            $uploadFile = $uploadDir . $fileName;

            if (saveLogo($imageData, $uploadFile, $name, $settings)) {
                unset($ch);
                return ['success' => true, 'filename' => $fileName];
            }
        }

        unset($ch);
        break;
    }

    return ['success' => false, 'message' => 'Failed to fetch image.'];
}

function saveLogo($imageData, $uploadFile, $name, $settings)
{
    $image = imagecreatefromstring($imageData);
    $removeBackground = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';

    if ($image !== false) {
        $tempFile = tempnam(sys_get_temp_dir(), 'logo');
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image, $tempFile);
        imagedestroy($image);

        if (extension_loaded('imagick')) {
            $imagick = new Imagick($tempFile);

            if ($removeBackground) {
                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
                $pixel = $imagick->getImagePixelColor(0, 0);
                $color = $pixel->getColor();
                if ($color['a'] > 0) {
                    $bgColor = "rgb({$color['r']},{$color['g']},{$color['b']})";
                    $fuzz = Imagick::getQuantum() * 0.1;
                    $imagick->transparentPaintImage($bgColor, 0, $fuzz, false);
                }
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
            if ($newImage !== false) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);

                if ($removeBackground) {
                    require_once __DIR__ . '/../../includes/gd_background_removal.php';
                    if (!imageistruecolor($newImage)) {
                        imagepalettetotruecolor($newImage);
                        imagealphablending($newImage, false);
                        imagesavealpha($newImage, true);
                    }
                    $corner = imagecolorat($newImage, 0, 0);
                    if ((($corner >> 24) & 0x7F) !== 127) {
                        gdRemoveBackgroundColor($newImage, ($corner >> 16) & 0xFF, ($corner >> 8) & 0xFF, $corner & 0xFF);
                    }
                }

                require_once __DIR__ . '/../../includes/gd_background_removal.php';
                $newImage = gdCropTransparent($newImage, 2);
                imagepng($newImage, $uploadFile);
                imagedestroy($newImage);
            } else {
                unlink($tempFile);
                return false;
            }
        }

        unlink($tempFile);
        return true;
    }
    return false;
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

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

switch ($action) {
    case 'add':
        $name = $_POST['name'] ?? null;
        $price = $_POST['price'] ?? null;
        $currencyId = $_POST['currency_id'] ?? null;
        $frequency = $_POST['frequency'] ?? null;
        $cycle = $_POST['cycle'] ?? null;
        $nextPayment = $_POST['next_payment'] ?? null;
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        
        $autoRenew = $_POST['auto_renew'] ?? '1';
        $autoRenew = ($autoRenew === '0' || $autoRenew === 0) ? 0 : 1;

        $paymentMethodId = $_POST['payment_method_id'] ?? null;
        $payerUserId = $_POST['payer_user_id'] ?? null;
        $categoryId = $_POST['category_id'] ?? null;
        
        $notes = isset($_POST['notes']) ? validate($_POST['notes']) : '';
        $url = isset($_POST['url']) ? validate($_POST['url']) : '';
        
        $notify = $_POST['notify'] ?? $_POST['notifications'] ?? '0';
        $notify = ($notify === '1' || $notify === 1) ? 1 : 0;
        $notifyDaysBefore = isset($_POST['notify_days_before']) ? ($_POST['notify_days_before'] === '' ? null : intval($_POST['notify_days_before'])) : null;
        
        $inactive = $_POST['inactive'] ?? '0';
        $inactive = ($inactive === '1' || $inactive === 1) ? 1 : 0;
        
        $cancellationDate = $_POST['cancellation_date'] ?? null;
        if ($cancellationDate === '') {
            $cancellationDate = null;
        }

        $replacementSubscriptionId = $_POST['replacement_subscription_id'] ?? null;
        if ($replacementSubscriptionId === '0' || $replacementSubscriptionId === 0 || $replacementSubscriptionId === '' || $inactive === 0) {
            $replacementSubscriptionId = null;
        }

        // Validate required fields
        if (!$name || trim($name) === '' || $price === null || !$currencyId || !$frequency || !$cycle || !$nextPayment) {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameters',
                'message' => 'Parameters "name", "price", "currency_id", "frequency", "cycle", and "next_payment" are required.'
            ]);
            exit;
        }

        $name = validate($name);
        $price = floatval($price);
        $currencyId = intval($currencyId);
        $frequency = intval($frequency);
        $cycle = intval($cycle);

        if (!in_array($cycle, [1, 2, 3, 4], true)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid parameter',
                'message' => 'Parameter "cycle" must be 1 (Days), 2 (Weeks), 3 (Months), or 4 (Years).'
            ]);
            exit;
        }

        // Validate Dates
        if (!validateDate($nextPayment)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid date',
                'message' => 'Parameter "next_payment" must be a valid date in YYYY-MM-DD format.'
            ]);
            exit;
        }
        if ($startDate && !validateDate($startDate)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid date',
                'message' => 'Parameter "start_date" must be a valid date in YYYY-MM-DD format.'
            ]);
            exit;
        }
        if ($cancellationDate && !validateDate($cancellationDate)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid date',
                'message' => 'Parameter "cancellation_date" must be a valid date in YYYY-MM-DD format.'
            ]);
            exit;
        }

        // Validate Foreign Keys
        // Currency
        $currStmt = $db->prepare("SELECT id FROM currencies WHERE id = :id AND user_id = :userId");
        $currStmt->bindValue(':id', $currencyId, SQLITE3_INTEGER);
        $currStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $currRes = $currStmt->execute()->fetchArray();
        if (!$currRes) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid currency ID',
                'message' => 'The specified currency does not exist or does not belong to you.'
            ]);
            exit;
        }

        // Category
        if ($categoryId !== null) {
            $categoryId = intval($categoryId);
            $catStmt = $db->prepare("SELECT id FROM categories WHERE id = :id AND user_id = :userId");
            $catStmt->bindValue(':id', $categoryId, SQLITE3_INTEGER);
            $catStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $catRes = $catStmt->execute()->fetchArray();
            if (!$catRes) {
                echo json_encode([
                    'success' => false,
                    'title' => 'Invalid category ID',
                    'message' => 'The specified category does not exist or does not belong to you.'
                ]);
                exit;
            }
        }

        // Payer user
        if ($payerUserId !== null) {
            $payerUserId = intval($payerUserId);
            $payerStmt = $db->prepare("SELECT id FROM household WHERE id = :id AND user_id = :userId");
            $payerStmt->bindValue(':id', $payerUserId, SQLITE3_INTEGER);
            $payerStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $payerRes = $payerStmt->execute()->fetchArray();
            if (!$payerRes) {
                echo json_encode([
                    'success' => false,
                    'title' => 'Invalid payer ID',
                    'message' => 'The specified household member does not exist or does not belong to you.'
                ]);
                exit;
            }
        }

        // Payment Method
        if ($paymentMethodId !== null) {
            $paymentMethodId = intval($paymentMethodId);
            $pmStmt = $db->prepare("SELECT id FROM payment_methods WHERE id = :id AND (user_id = :userId OR user_id = 0 OR user_id IS NULL)");
            $pmStmt->bindValue(':id', $paymentMethodId, SQLITE3_INTEGER);
            $pmStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $pmRes = $pmStmt->execute()->fetchArray();
            if (!$pmRes) {
                echo json_encode([
                    'success' => false,
                    'title' => 'Invalid payment method ID',
                    'message' => 'The specified payment method does not exist or does not belong to you.'
                ]);
                exit;
            }
        }

        // Replacement Subscription
        if ($replacementSubscriptionId !== null) {
            $replacementSubscriptionId = intval($replacementSubscriptionId);
            $repStmt = $db->prepare("SELECT id FROM subscriptions WHERE id = :id AND user_id = :userId");
            $repStmt->bindValue(':id', $replacementSubscriptionId, SQLITE3_INTEGER);
            $repStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $repRes = $repStmt->execute()->fetchArray();
            if (!$repRes) {
                $replacementSubscriptionId = null;
            }
        }

        // Process Logo
        $logo = '';
        $logoUrl = $_POST['logo_url'] ?? $_POST['logo-url'] ?? '';
        if ($logoUrl !== "") {
            $resLogo = getLogoFromUrl($logoUrl, '../../images/uploads/logos/', $name, $settings);
            if ($resLogo['success']) {
                $logo = $resLogo['filename'];
            }
        } elseif (!empty($_FILES['logo']['name'])) {
            $fileType = mime_content_type($_FILES['logo']['tmp_name']);
            if (strpos($fileType, 'image') !== false) {
                $logo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $name, $settings);
            }
        }

        // Insert
        $sqlInsert = "INSERT INTO subscriptions (
                            name, logo, price, currency_id, next_payment, cycle, frequency, notes, 
                            payment_method_id, payer_user_id, category_id, notify, inactive, url, 
                            notify_days_before, user_id, cancellation_date, replacement_subscription_id,
                            auto_renew, start_date
                        ) VALUES (
                            :name, :logo, :price, :currencyId, :nextPayment, :cycle, :frequency, :notes, 
                            :paymentMethodId, :payerUserId, :categoryId, :notify, :inactive, :url, 
                            :notifyDaysBefore, :userId, :cancellationDate, :replacement_subscription_id,
                            :autoRenew, :startDate
                        )";
        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $name, SQLITE3_TEXT);
        $stmtInsert->bindParam(':logo', $logo, SQLITE3_TEXT);
        $stmtInsert->bindParam(':price', $price, SQLITE3_FLOAT);
        $stmtInsert->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':nextPayment', $nextPayment, SQLITE3_TEXT);
        $stmtInsert->bindParam(':cycle', $cycle, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':frequency', $frequency, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':notes', $notes, SQLITE3_TEXT);
        $stmtInsert->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':payerUserId', $payerUserId, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':notify', $notify, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':inactive', $inactive, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':url', $url, SQLITE3_TEXT);
        $stmtInsert->bindParam(':notifyDaysBefore', $notifyDaysBefore, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':cancellationDate', $cancellationDate, SQLITE3_TEXT);
        $stmtInsert->bindParam(':replacement_subscription_id', $replacementSubscriptionId, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':autoRenew', $autoRenew, SQLITE3_INTEGER);
        $stmtInsert->bindParam(':startDate', $startDate, SQLITE3_TEXT);

        if ($stmtInsert->execute()) {
            echo json_encode([
                'success' => true,
                'title' => 'Subscription added',
                'subscriptionId' => $db->lastInsertRowID(),
                'message' => 'Subscription added successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to add subscription: ' . $db->lastErrorMsg()
            ]);
        }
        break;

    case 'edit':
        $subscriptionId = $_POST['subscriptionId'] ?? $_POST['id'] ?? $_POST['subscription_id'] ?? null;
        if (!$subscriptionId) {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameter',
                'message' => 'Parameter "id" (or "subscriptionId") is required.'
            ]);
            exit;
        }
        $subscriptionId = intval($subscriptionId);

        // Fetch current subscription
        $subSql = "SELECT * FROM subscriptions WHERE id = :subId AND user_id = :userId";
        $subStmt = $db->prepare($subSql);
        $subStmt->bindValue(':subId', $subscriptionId, SQLITE3_INTEGER);
        $subStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $subResult = $subStmt->execute();
        $subscription = $subResult->fetchArray(SQLITE3_ASSOC);

        if (!$subscription) {
            echo json_encode([
                'success' => false,
                'title' => 'Subscription not found',
                'message' => 'Subscription not found or does not belong to you.'
            ]);
            exit;
        }

        // Merge existing values with incoming POST parameters
        $name = isset($_POST['name']) ? validate($_POST['name']) : $subscription['name'];
        $price = isset($_POST['price']) ? floatval($_POST['price']) : $subscription['price'];
        $currencyId = isset($_POST['currency_id']) ? intval($_POST['currency_id']) : $subscription['currency_id'];
        $frequency = isset($_POST['frequency']) ? intval($_POST['frequency']) : $subscription['frequency'];
        $cycle = isset($_POST['cycle']) ? intval($_POST['cycle']) : $subscription['cycle'];
        $nextPayment = isset($_POST['next_payment']) ? $_POST['next_payment'] : $subscription['next_payment'];
        
        $autoRenew = $subscription['auto_renew'];
        if (isset($_POST['auto_renew'])) {
            $autoRenew = ($_POST['auto_renew'] === '0' || $_POST['auto_renew'] === 0) ? 0 : 1;
        }
        
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : $subscription['start_date'];
        
        $paymentMethodId = $subscription['payment_method_id'];
        if (isset($_POST['payment_method_id'])) {
            $paymentMethodId = $_POST['payment_method_id'] === '' ? null : intval($_POST['payment_method_id']);
        }

        $payerUserId = $subscription['payer_user_id'];
        if (isset($_POST['payer_user_id'])) {
            $payerUserId = $_POST['payer_user_id'] === '' ? null : intval($_POST['payer_user_id']);
        }

        $categoryId = $subscription['category_id'];
        if (isset($_POST['category_id'])) {
            $categoryId = $_POST['category_id'] === '' ? null : intval($_POST['category_id']);
        }

        $notes = isset($_POST['notes']) ? validate($_POST['notes']) : $subscription['notes'];
        $url = isset($_POST['url']) ? validate($_POST['url']) : $subscription['url'];

        $notify = $subscription['notify'];
        if (isset($_POST['notify']) || isset($_POST['notifications'])) {
            $valNotify = $_POST['notify'] ?? $_POST['notifications'];
            $notify = ($valNotify === '1' || $valNotify === 1) ? 1 : 0;
        }

        $notifyDaysBefore = $subscription['notify_days_before'];
        if (isset($_POST['notify_days_before'])) {
            $notifyDaysBefore = $_POST['notify_days_before'] === '' ? null : intval($_POST['notify_days_before']);
        }

        $inactive = $subscription['inactive'];
        if (isset($_POST['inactive'])) {
            $inactive = ($_POST['inactive'] === '1' || $_POST['inactive'] === 1) ? 1 : 0;
        }

        $cancellationDate = $subscription['cancellation_date'];
        if (isset($_POST['cancellation_date'])) {
            $cancellationDate = $_POST['cancellation_date'] === '' ? null : $_POST['cancellation_date'];
        }

        $replacementSubscriptionId = $subscription['replacement_subscription_id'];
        if (isset($_POST['replacement_subscription_id'])) {
            $replacementSubscriptionId = ($_POST['replacement_subscription_id'] === '' || $_POST['replacement_subscription_id'] === '0' || $_POST['replacement_subscription_id'] === 0) ? null : intval($_POST['replacement_subscription_id']);
        }

        if ($replacementSubscriptionId == 0 || $inactive == 0) {
            $replacementSubscriptionId = null;
        }

        // Validate values
        if (trim($name) === '') {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid name',
                'message' => 'Subscription name cannot be empty.'
            ]);
            exit;
        }

        if (!in_array($cycle, [1, 2, 3, 4], true)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid cycle',
                'message' => 'Parameter "cycle" must be 1 (Days), 2 (Weeks), 3 (Months), or 4 (Years).'
            ]);
            exit;
        }

        // Validate Dates
        if (!validateDate($nextPayment)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid date',
                'message' => 'Parameter "next_payment" must be a valid date in YYYY-MM-DD format.'
            ]);
            exit;
        }
        if ($startDate && !validateDate($startDate)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid date',
                'message' => 'Parameter "start_date" must be a valid date in YYYY-MM-DD format.'
            ]);
            exit;
        }
        if ($cancellationDate && !validateDate($cancellationDate)) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid date',
                'message' => 'Parameter "cancellation_date" must be a valid date in YYYY-MM-DD format.'
            ]);
            exit;
        }

        // Validate Foreign Keys
        // Currency
        $currStmt = $db->prepare("SELECT id FROM currencies WHERE id = :id AND user_id = :userId");
        $currStmt->bindValue(':id', $currencyId, SQLITE3_INTEGER);
        $currStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $currRes = $currStmt->execute()->fetchArray();
        if (!$currRes) {
            echo json_encode([
                'success' => false,
                'title' => 'Invalid currency ID',
                'message' => 'The specified currency does not exist or does not belong to you.'
            ]);
            exit;
        }

        // Category
        if ($categoryId !== null) {
            $catStmt = $db->prepare("SELECT id FROM categories WHERE id = :id AND user_id = :userId");
            $catStmt->bindValue(':id', $categoryId, SQLITE3_INTEGER);
            $catStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $catRes = $catStmt->execute()->fetchArray();
            if (!$catRes) {
                echo json_encode([
                    'success' => false,
                    'title' => 'Invalid category ID',
                    'message' => 'The specified category does not exist or does not belong to you.'
                ]);
                exit;
            }
        }

        // Payer
        if ($payerUserId !== null) {
            $payerStmt = $db->prepare("SELECT id FROM household WHERE id = :id AND user_id = :userId");
            $payerStmt->bindValue(':id', $payerUserId, SQLITE3_INTEGER);
            $payerStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $payerRes = $payerStmt->execute()->fetchArray();
            if (!$payerRes) {
                echo json_encode([
                    'success' => false,
                    'title' => 'Invalid payer ID',
                    'message' => 'The specified household member does not exist or does not belong to you.'
                ]);
                exit;
            }
        }

        // Payment Method
        if ($paymentMethodId !== null) {
            $pmStmt = $db->prepare("SELECT id FROM payment_methods WHERE id = :id AND (user_id = :userId OR user_id = 0 OR user_id IS NULL)");
            $pmStmt->bindValue(':id', $paymentMethodId, SQLITE3_INTEGER);
            $pmStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $pmRes = $pmStmt->execute()->fetchArray();
            if (!$pmRes) {
                echo json_encode([
                    'success' => false,
                    'title' => 'Invalid payment method ID',
                    'message' => 'The specified payment method does not exist or does not belong to you.'
                ]);
                exit;
            }
        }

        // Replacement Subscription
        if ($replacementSubscriptionId !== null) {
            if ($replacementSubscriptionId === $subscriptionId) {
                echo json_encode([
                    'success' => false,
                    'title' => 'Invalid replacement ID',
                    'message' => 'A subscription cannot be replaced by itself.'
                ]);
                exit;
            }
            $repStmt = $db->prepare("SELECT id FROM subscriptions WHERE id = :id AND user_id = :userId");
            $repStmt->bindValue(':id', $replacementSubscriptionId, SQLITE3_INTEGER);
            $repStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $repRes = $repStmt->execute()->fetchArray();
            if (!$repRes) {
                $replacementSubscriptionId = null;
            }
        }

        // Process Logo
        $logo = $subscription['logo'];
        $logoUrl = $_POST['logo_url'] ?? $_POST['logo-url'] ?? '';
        if ($logoUrl !== "") {
            $resLogo = getLogoFromUrl($logoUrl, '../../images/uploads/logos/', $name, $settings);
            if ($resLogo['success']) {
                $logo = $resLogo['filename'];
            }
        } elseif (!empty($_FILES['logo']['name'])) {
            $fileType = mime_content_type($_FILES['logo']['tmp_name']);
            if (strpos($fileType, 'image') !== false) {
                $logo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $name, $settings);
            }
        }

        // Update
        $sqlUpdate = "UPDATE subscriptions SET 
                            name = :name, 
                            logo = :logo,
                            price = :price, 
                            currency_id = :currencyId,
                            next_payment = :nextPayment, 
                            auto_renew = :autoRenew,
                            start_date = :startDate,
                            cycle = :cycle, 
                            frequency = :frequency, 
                            notes = :notes, 
                            payment_method_id = :paymentMethodId,
                            payer_user_id = :payerUserId, 
                            category_id = :categoryId, 
                            notify = :notify, 
                            inactive = :inactive, 
                            url = :url, 
                            notify_days_before = :notifyDaysBefore, 
                            cancellation_date = :cancellationDate, 
                            replacement_subscription_id = :replacement_subscription_id
                       WHERE id = :id AND user_id = :userId";
        
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':name', $name, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':logo', $logo, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':price', $price, SQLITE3_FLOAT);
        $stmtUpdate->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':nextPayment', $nextPayment, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':autoRenew', $autoRenew, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':startDate', $startDate, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':cycle', $cycle, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':frequency', $frequency, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':notes', $notes, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':payerUserId', $payerUserId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':notify', $notify, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':inactive', $inactive, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':url', $url, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':notifyDaysBefore', $notifyDaysBefore, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':cancellationDate', $cancellationDate, SQLITE3_TEXT);
        $stmtUpdate->bindParam(':replacement_subscription_id', $replacementSubscriptionId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':id', $subscriptionId, SQLITE3_INTEGER);
        $stmtUpdate->bindParam(':userId', $userId, SQLITE3_INTEGER);

        if ($stmtUpdate->execute()) {
            echo json_encode([
                'success' => true,
                'title' => 'Subscription updated',
                'message' => 'Subscription updated successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to update subscription: ' . $db->lastErrorMsg()
            ]);
        }
        break;

    case 'delete':
        $subscriptionId = $_POST['subscriptionId'] ?? $_POST['id'] ?? $_POST['subscription_id'] ?? null;
        if (!$subscriptionId) {
            echo json_encode([
                'success' => false,
                'title' => 'Missing parameter',
                'message' => 'Parameter "id" (or "subscriptionId") is required.'
            ]);
            exit;
        }
        $subscriptionId = intval($subscriptionId);

        // Check ownership
        $checkSql = "SELECT * FROM subscriptions WHERE id = :subId AND user_id = :userId";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindValue(':subId', $subscriptionId, SQLITE3_INTEGER);
        $checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $checkResult = $checkStmt->execute();
        $subscription = $checkResult->fetchArray(SQLITE3_ASSOC);

        if (!$subscription) {
            echo json_encode([
                'success' => false,
                'title' => 'Subscription not found',
                'message' => 'Subscription not found or does not belong to you.'
            ]);
            exit;
        }

        // Delete
        $sqlDelete = "DELETE FROM subscriptions WHERE id = :subId AND user_id = :userId";
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->bindParam(':subId', $subscriptionId, SQLITE3_INTEGER);
        $stmtDelete->bindParam(':userId', $userId, SQLITE3_INTEGER);

        if ($stmtDelete->execute()) {
            // Nullify replacement_subscription_id references pointing to this subscription
            $queryCascade = "UPDATE subscriptions SET replacement_subscription_id = NULL WHERE replacement_subscription_id = :subId AND user_id = :userId";
            $stmtCascade = $db->prepare($queryCascade);
            $stmtCascade->bindParam(':subId', $subscriptionId, SQLITE3_INTEGER);
            $stmtCascade->bindParam(':userId', $userId, SQLITE3_INTEGER);
            $stmtCascade->execute();

            echo json_encode([
                'success' => true,
                'title' => 'Subscription deleted',
                'message' => 'Subscription deleted successfully.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'title' => 'Database error',
                'message' => 'Failed to delete subscription.'
            ]);
        }
        break;
}

$db->close();
?>
