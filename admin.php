<?php
    require_once 'includes/header.php';

    $settings = [];
    $settings['registrations'] = false;
?>

<section class="contain settings">

    <section class="account-section">
        <header>
            <h2><?= translate('backup_and_restore', $i18n) ?></h2>
        </header>
        <div class="form-group-inline">
            <div>
                <input type="button" class="button thin" value="<?= translate('backup', $i18n) ?>" id="backupDB" onClick="backupDB()"/>
            </div>     
            <div>
                <input type="button" class="secondary-button thin" value="<?= translate('restore', $i18n) ?>" id="restoreDB" onClick="openRestoreDBFileSelect()" />    
                <input type="file" name="restoreDBFile" id="restoreDBFile" style="display: none;" onChange="restoreDB()" accept=".zip">
            </div>
        </div>
        <div class="settings-notes">
            <p>
                <i class="fa-solid fa-circle-info"></i>
                <?= translate('restore_info', $i18n) ?>
            </p>
        </div>       
    </section>

</section>
<script src="scripts/admin.js?<?= $version ?>"></script>

<?php
    require_once 'includes/footer.php';
?>