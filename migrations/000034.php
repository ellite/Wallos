<?php
// This migration changes all the notify_days_before from 0 to -1 to to support the added "on the day" option

$db->exec('UPDATE subscriptions SET `notify_days_before` = -1 WHERE `notify_days_before` = 0');