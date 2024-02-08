<?php
    error_reporting(E_ERROR | E_PARSE);
    require_once '../../includes/connect_endpoint.php';
    session_start();

    function sanitizeFilename($filename) {
        $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
        $filename = str_replace(" ", "-", $filename);
        return $filename;
    }

    function validate($value) {
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value);
        $value = htmlentities($value);
        return $value;
    }
    function getLogoFromUrl($url, $uploadDir, $name) {
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $imageData = curl_exec($ch);
        
        if ($imageData !== false) {
            $timestamp = time();
            $fileName = $timestamp . '-' . sanitizeFilename($name) . '.png';
            $uploadDir = '../../images/uploads/logos/';
            $uploadFile = $uploadDir . $fileName;
            
            if (saveLogo($imageData, $uploadFile, $name)) {
                return $fileName;
            } else {
                echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
                return "";
            }
            
            curl_close($ch);
        } else {
            echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
            return "";
        }
    }

    function saveLogo($imageData, $uploadFile, $name) {
        $image = imagecreatefromstring($imageData);
        $removeBackground = isset($_COOKIE['removeBackground']) && $_COOKIE['removeBackground'] === 'true';
        if ($image !== false) {
            $tempFile = tempnam(sys_get_temp_dir(), 'logo');
            imagepng($image, $tempFile);
            imagedestroy($image);

            $imagick = new Imagick($tempFile);
            if ($removeBackground) {
                $fuzz = Imagick::getQuantum() * 0.1; // 10%
                $imagick->transparentPaintImage("rgb(247, 247, 247)", 0, $fuzz, false);
            }
            $imagick->setImageFormat('png');
            $imagick->writeImage($uploadFile);

            $imagick->clear();
            $imagick->destroy();
            unlink($tempFile);

            return true;
        } else {
            return false;
        }
    }

    function resizeAndUploadLogo($uploadedFile, $uploadDir, $name) {
        $targetWidth = 135;
        $targetHeight = 42;
    
        $timestamp = time();
        $originalFileName = $uploadedFile['name'];
        $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
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
                    $newWidth = $targetWidth;
                    $newHeight = ($targetWidth / $width) * $height;
                }
    
                if ($newHeight > $targetHeight) {
                    $newWidth = ($targetHeight / $newHeight) * $newWidth;
                    $newHeight = $targetHeight;
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
            $paymentMethodId = $_POST["payment_method_id"];
            $payerUserId = $_POST["payer_user_id"];
            $categoryId = $_POST['category_id'];
            $notes = validate($_POST["notes"]);
            $url = validate($_POST['url']);
            $logoUrl = validate($_POST['logo-url']);
            $logo = "";
            $notify = isset($_POST['notifications']) ? true : false;

            if($logoUrl !== "") {
                $logo = getLogoFromUrl($logoUrl, '../../images/uploads/logos/', $name);
            } else {
                if (!empty($_FILES['logo']['name'])) {
                    $logo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $name);
                }
            }

            if (!$isEdit) {
                $sql = "INSERT INTO subscriptions (name, logo, price, currency_id, next_payment, cycle, frequency, notes, 
                        payment_method_id, payer_user_id, category_id, notify, url) 
                        VALUES (:name, :logo, :price, :currencyId, :nextPayment, :cycle, :frequency, :notes, 
                        :paymentMethodId, :payerUserId, :categoryId, :notify, :url)";
            } else {
                $id = $_POST['id'];
                if ($logo != "") {
                    $sql = "UPDATE subscriptions SET name = :name, logo = :logo, price = :price, currency_id = :currencyId, next_payment = :nextPayment, cycle = :cycle, frequency = :frequency, notes = :notes, payment_method_id = :paymentMethodId, payer_user_id = :payerUserId, category_id = :categoryId, notify = :notify, url = :url WHERE id = :id";
                } else {
                    $sql = "UPDATE subscriptions SET name = :name, price = :price, currency_id = :currencyId, next_payment = :nextPayment, cycle = :cycle, frequency = :frequency, notes = :notes, payment_method_id = :paymentMethodId, payer_user_id = :payerUserId, category_id = :categoryId, notify = :notify, url = :url WHERE id = :id";
                }
            }

            $stmt = $db->prepare($sql);
            if ($isEdit) {
                $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
            }
            $stmt->bindParam(':name', $name, SQLITE3_TEXT);
            if ($logo != "") {
                $stmt->bindParam(':logo', $logo, SQLITE3_TEXT);
            }
            $stmt->bindParam(':price', $price, SQLITE3_FLOAT);
            $stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
            $stmt->bindParam(':nextPayment', $nextPayment, SQLITE3_TEXT);
            $stmt->bindParam(':cycle', $cycle, SQLITE3_INTEGER);
            $stmt->bindParam(':frequency', $frequency, SQLITE3_INTEGER);
            $stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
            $stmt->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
            $stmt->bindParam(':payerUserId', $payerUserId, SQLITE3_INTEGER);
            $stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
            $stmt->bindParam(':notify', $notify, SQLITE3_INTEGER);
            $stmt->bindParam(':url', $url, SQLITE3_TEXT);
            
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
