<?php
// This migration adds an "enabled" column to the payment_methods table and sets all values to 1.
// It allows the user to disable payment methods without deleting them.

/** @noinspection PhpUndefinedVariableInspection */
$db->exec('ALTER TABLE payment_methods ADD COLUMN enabled BOOLEAN DEFAULT 1');
$db->exec('UPDATE payment_methods SET enabled = 1');