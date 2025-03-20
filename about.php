<?php
require_once 'includes/header.php';

$wallosIsUpToDate = true;
if (!is_null($settings['latest_version'])) {
    $latestVersion = $settings['latest_version'];
    if (version_compare($version, $latestVersion) == -1) {
        $wallosIsUpToDate = false;
    }
}
?>

<section class="contain">

    <section class="account-section">
        <header>
            <h2><?= translate('about', $i18n) ?></h2>
        </header>
        <div class="credits-list">
            <div>
                <h3>
                    Wallos <?= $version ?> <?= $demoMode ? "Demo" : "" ?>
                </h3>
                <span>
                    <?= translate('release_notes', $i18n) ?>
                    <a href="https://github.com/ellite/Wallos/releases/tag/<?= $version ?>" target="_blank"
                        title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>
            <?php if (!$wallosIsUpToDate): ?>
                <div class="update-available">
                    <h3>
                        <i class="fa-solid fa-info-circle"></i>
                        <?= translate('update_available', $i18n) ?> <?= $latestVersion ?>
                    </h3>
                    <span>
                        <?= translate('release_notes', $i18n) ?>
                        <a href="https://github.com/ellite/Wallos/releases/tag/<?= $latestVersion ?>" target="_blank"
                            title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </span>
                </div>
            <?php endif; ?>
            <div>
                <h3><?= translate('license', $i18n) ?></h3>
                <span>
                    GPLv3
                    <a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="_blank"
                        title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>
            <div>
                <h3><?= translate('issues_and_requests', $i18n) ?></h3>
                <span>
                    GitHub
                    <a href="https://github.com/ellite/Wallos/issues" target="_blank"
                        title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>
            <div>
                <h3><?= translate('the_author', $i18n) ?></h3>
                <span>
                    https://henrique.pt
                    <a href="https://henrique.pt/" target="_blank" title="<?= translate('external_url', $i18n) ?>"
                        rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>

        </div>
    </section>

    <section class="account-section">
        <header>
            <h2><?= translate("credits", $i18n) ?></h2>
        </header>
        <div class="credits-list">
            <div>
                <h3><?= translate('icons', $i18n) ?></h3>
                <span>
                    https://www.streamlinehq.com/freebies/plump-flat-free
                    <a href="https://www.streamlinehq.com/freebies/plump-flat-free" target="_blank"
                        title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>
            <div>
                <h3><?= translate('payment_icons', $i18n) ?></h3>
                <span>
                    https://www.figma.com/file/5IMW8JfoXfB5GRlPNdTyeg/Credit-Cards-and-Payment-Methods-Icons-(Community)
                    <a href="https://www.figma.com/file/5IMW8JfoXfB5GRlPNdTyeg/Credit-Cards-and-Payment-Methods-Icons-(Community)"
                        target="_blank" title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>
            <div>
                <h3>Chart.js</h3>
                <span>
                    https://www.chartjs.org/
                    <a href="https://www.chartjs.org/" target="_blank" title="<?= translate('external_url', $i18n) ?>"
                        rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>
            <div>
                <h3>QRCode.js</h3>
                <span>
                    https://github.com/davidshimjs/qrcodejs
                    <a href="https://github.com/davidshimjs/qrcodejs" target="_blank"
                        title="<?= translate('external_url', $i18n) ?>" rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>
            <div>
                <h3>Icons by icons8</h3>
                <span>
                    https://icons8.com/
                    <a href="https://icons8.com/" target="_blank" title="<?= translate('external_url', $i18n) ?>"
                        rel="noreferrer">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </div>
        </div>
    </section>

</section>

<?php
require_once 'includes/footer.php';
?>