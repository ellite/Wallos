<?php

$timezone = date_default_timezone_get();
if ($timezone == '') {
    $timezone = 'UTC';
}
date_default_timezone_set($timezone);