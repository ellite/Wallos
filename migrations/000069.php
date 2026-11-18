<?php
// Repair legacy schedules; safe to run repeatedly.
$db->exec("UPDATE subscriptions SET frequency = 1 WHERE frequency IS NULL OR frequency < 1");
$db->exec("UPDATE subscriptions SET auto_renew = 0 WHERE cycle = 5 AND auto_renew = 1");
