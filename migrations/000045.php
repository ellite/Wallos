<?php
// This migration corrects the Japanese language code from 'jp' to 'ja' in the user table.

$db->exec("UPDATE user SET language = 'ja' WHERE language = 'jp'");
