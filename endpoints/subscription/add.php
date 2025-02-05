<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/getsettings.php';

if (!file_exists('../../images/uploads/logos')) {
    mkdir('../../images/uploads/logos', 0777, true);
    mkdir('../../images/uploads/logos/avatars', 0777, true);
}

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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

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
            curl_close($ch);
            echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
            return "";
        }

    } else {
        echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
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

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $isEdit = isset($_POST['id']) && $_POST['id'] != "";
        $name = validate($_POST["name"]);
        $price = $_POST['price'];
        $currencyId = $_POST["currency_id"];
        $frequency = $_POST["frequency"];
        $cycle = $_POST["cycle"];
        $nextPayment = $_POST["next_payment"];
        $autoRenew = isset($_POST['auto_renew']) ? true : false;
        $startDate = $_POST["start_date"];
        $paymentMethodId = $_POST["payment_method_id"];
        $payerUserId = $_POST["payer_user_id"];
        $categoryId = $_POST['category_id'];
        $notes = validate($_POST["notes"]);
        $url = validate($_POST['url']);
        $logoUrl = validate($_POST['logo-url']);
        $logo = "";
        $notify = isset($_POST['notifications']) ? true : false;
        $notifyDaysBefore = $_POST['notify_days_before'];
        $inactive = isset($_POST['inactive']) ? true : false;
        $cancellationDate = $_POST['cancellation_date'] ?? null;
        $replacementSubscriptionId = $_POST['replacement_subscription_id'];

        if ($replacementSubscriptionId == 0 || $inactive == 0) {
            $replacementSubscriptionId = null;
        }

        if ($logoUrl !== "") {
            $logo = getLogoFromUrl($logoUrl, '../../images/uploads/logos/', $name, $settings, $i18n);
        } else {
            if (!empty($_FILES['logo']['name'])) {
                $fileType = mime_content_type($_FILES['logo']['tmp_name']);
                if (strpos($fileType, 'image') === false) {
                    echo translate("fill_all_fields", $i18n);
                    exit();
                }
                $logo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $name, $settings);
            }
        }

        if (!$isEdit) {
            $sql = "INSERT INTO subscriptions (
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
        } else {
            $id = $_POST['id'];
            $sql = "UPDATE subscriptions SET 
                        name = :name, 
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
                        replacement_subscription_id = :replacement_subscription_id";

            if ($logo != "") {
                $sql .= ", logo = :logo";
            }

            $sql .= " WHERE id = :id AND user_id = :userId";
        }

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $name, SQLITE3_TEXT);
        if ($logo != "") {
            $stmt->bindParam(':logo', $logo, SQLITE3_TEXT);
        }
        $stmt->bindParam(':price', $price, SQLITE3_FLOAT);
        $stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
        $stmt->bindParam(':nextPayment', $nextPayment, SQLITE3_TEXT);
        $stmt->bindParam(':autoRenew', $autoRenew, SQLITE3_INTEGER);
        $stmt->bindParam(':startDate', $startDate, SQLITE3_TEXT);
        $stmt->bindParam(':cycle', $cycle, SQLITE3_INTEGER);
        $stmt->bindParam(':frequency', $frequency, SQLITE3_INTEGER);
        $stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
        $stmt->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
        $stmt->bindParam(':payerUserId', $payerUserId, SQLITE3_INTEGER);
        $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
        $stmt->bindParam(':notify', $notify, SQLITE3_INTEGER);
        $stmt->bindParam(':inactive', $inactive, SQLITE3_INTEGER);
        $stmt->bindParam(':url', $url, SQLITE3_TEXT);
        $stmt->bindParam(':notifyDaysBefore', $notifyDaysBefore, SQLITE3_INTEGER);
        $stmt->bindParam(':cancellationDate', $cancellationDate, SQLITE3_TEXT);
        if ($isEdit) {
            $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
        }
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $stmt->bindParam(':replacement_subscription_id', $replacementSubscriptionId, SQLITE3_INTEGER);

        if ($stmt->execute()) {
            $success['status'] = "Success";
            $text = $isEdit ? "updated" : "added";
            $success['message'] = translate('subscription_' . $text . '_successfuly', $i18n);
            $json = json_encode($success);
            header('Content-Type: application/json');
            echo $json;
            exit();
        } else {
            echo translate('error', $i18n) . ": " . $db->lastErrorMsg();
        }
    }
}
$db->close();
?>