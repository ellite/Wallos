<?php

// Deletes a logo file from the logos directory unless a subscription (logo or
// logo_variant) or a payment method (icon) still names it. clone.php copies a
// logo filename into a second row, so a file detached from one row can still
// be in use; the check runs across all users since the directory is shared.
// $filename is the bare name as stored (e.g. "1712-netflix.png"); $logosDir
// may or may not have a trailing slash.
function deleteLogoFileIfUnused($db, $filename, $logosDir)
{
    $filename = trim((string) $filename);
    if ($filename === '') {
        return;
    }

    // Never step outside the logos directory, whatever the stored value is.
    $filename = basename($filename);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $stmt = $db->prepare(
        'SELECT 1 FROM subscriptions WHERE logo = :name OR logo_variant = :name
         UNION ALL
         SELECT 1 FROM payment_methods WHERE icon = :name
         LIMIT 1'
    );
    $stmt->bindValue(':name', $filename, SQLITE3_TEXT);
    $result = $stmt->execute();

    if ($result && $result->fetchArray(SQLITE3_ASSOC)) {
        return; // still in use
    }

    $path = rtrim($logosDir, '/') . '/' . $filename;
    if (is_file($path)) {
        unlink($path);
    }
}
