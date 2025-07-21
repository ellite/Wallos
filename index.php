<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';

include_once 'includes/list_subscriptions.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1><?= translate('hello', $i18n) ?></h1>
        </div>
    </div>
</div>

<script src="scripts/dashboard.js?<?= $version ?>"></script>

<?php
require_once 'includes/footer.php';
?>