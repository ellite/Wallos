<?php
require_once 'includes/header.php';
?>

<section class="contain">

    <section class="account-section">
        <header>
            <h2><?= translate('about_and_credits', $i18n) ?></h2>
        </header>
        <div class="credits-list">
            <h3><?= translate('about', $i18n) ?></h3>
            <p>Wallos <?= $version ?></p>
            <p><?= translate('license', $i18n) ?>:
                <span>
                    GPLv3
                    <a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="_blank"
                        title="<?= translate('external_url', $i18n) ?>">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
            <p>
                <?= translate('issues_and_requests', $i18n) ?>:
                <span>
                    GitHub
                    <a href="https://github.com/ellite/Wallos/issues" target="_blank"
                        title="<?= translate('external_url', $i18n) ?>">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
            <p>
                <?= translate('the_author', $i18n) ?>:
                <span>
                    https://henrique.pt
                    <a href="https://henrique.pt/" target="_blank" title="<?= translate('external_url', $i18n) ?>">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
            <h3><?= translate('credits', $i18n) ?></h3>
            <p>
                <?= translate('icons', $i18n) ?>:
                <span>
                    https://www.streamlinehq.com/freebies/plump-flat-free
                    <a href="https://www.streamlinehq.com/freebies/plump-flat-free" target="_blank"
                        title="<?= translate('external_url', $i18n) ?>">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
            <p>
                <?= translate('payment_icons', $i18n) ?>:
                <span>
                    https://www.figma.com/file/5IMW8JfoXfB5GRlPNdTyeg/Credit-Cards-and-Payment-Methods-Icons-(Community)
                    <a href="https://www.figma.com/file/5IMW8JfoXfB5GRlPNdTyeg/Credit-Cards-and-Payment-Methods-Icons-(Community)"
                        target="_blank" title="<?= translate('external_url', $i18n) ?>">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
            <p>
                Chart.js:
                <span>
                    https://www.chartjs.org/
                    <a href="https://www.chartjs.org/" target="_blank" title="<?= translate('external_url', $i18n) ?>">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </span>
            </p>
        </div>
    </section>

</section>

<?php
require_once 'includes/footer.php';
?>