<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

if (!isset($data["days"]) || $data['days'] == "") {
    $response = [
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ];
    echo json_encode($response);
} else {
    $days = $data["days"];
    $periodSummaryAtPeriodStart = isset($data["period_summary_at_period_start"]) ? (int) $data["period_summary_at_period_start"] : 0;

    $hasPeriodSummaryColumn = false;
    $columnResult = $db->query("SELECT * FROM pragma_table_info('notification_settings') WHERE name='period_summary_at_period_start'");
    if ($columnResult && $columnResult->fetchArray(SQLITE3_ASSOC)) {
        $hasPeriodSummaryColumn = true;
    }

    $query = "SELECT COUNT(*) FROM notification_settings WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":userId", $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result === false) {
        $response = [
            "success" => false,
            "message" => translate('error_saving_notifications', $i18n)
        ];
        echo json_encode($response);
    } else {
        $row = $result->fetchArray();
        $count = $row[0];
        if ($count == 0) {
            if ($hasPeriodSummaryColumn) {
                $query = "INSERT INTO notification_settings (days, period_summary_at_period_start, user_id)
                                  VALUES (:days, :periodSummaryAtPeriodStart, :userId)";
            } else {
                $query = "INSERT INTO notification_settings (days, user_id)
                                  VALUES (:days, :userId)";
            }
        } else {
            if ($hasPeriodSummaryColumn) {
                $query = "UPDATE notification_settings
                          SET days = :days, period_summary_at_period_start = :periodSummaryAtPeriodStart
                          WHERE user_id = :userId";
            } else {
                $query = "UPDATE notification_settings SET days = :days WHERE user_id = :userId";
            }
        }

        $stmt = $db->prepare($query);
        $stmt->bindValue(':days', $days, SQLITE3_INTEGER);
        if ($hasPeriodSummaryColumn) {
            $stmt->bindValue(':periodSummaryAtPeriodStart', $periodSummaryAtPeriodStart, SQLITE3_INTEGER);
        }
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

        if ($stmt->execute()) {
            $response = [
                "success" => true,
                "message" => translate('notifications_settings_saved', $i18n)
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "message" => translate('error_saving_notifications', $i18n)
            ];
            echo json_encode($response);
        }
    }
}
