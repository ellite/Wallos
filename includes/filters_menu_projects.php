<div class="filtermenu-content">
    <?php
    if (count($members) > 1) {
        ?>
        <div class="filtermenu-submenu">
            <div class="filter-title" onClick="toggleSubMenu('member')"><?= translate("member", $i18n) ?></div>
            <div class="filtermenu-submenu-content" id="filter-member">
                <?php
                foreach ($members as $member) {
                    if ($member['count'] == 0) {
                        continue;
                    }
                    $selectedClass = '';
                    if (isset($_GET['member'])) {
                        $memberIds = explode(',', $_GET['member']);
                        if (in_array($member['id'], $memberIds)) {
                            $selectedClass = 'selected';
                        }
                    }
                    ?>
                    <div class="filter-item <?= $selectedClass ?>" data-memberid="<?= $member['id'] ?>"><?= $member['name'] ?>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }
    ?>
    <?php
    if (count($categories) > 1) {
        ?>
        <div class="filtermenu-submenu">
            <div class="filter-title" onClick="toggleSubMenu('category')"><?= translate("category", $i18n) ?></div>
            <div class="filtermenu-submenu-content" id="filter-category">
                <?php
                foreach ($categories as $category) {
                    if ($category['count'] == 0) {
                        continue;
                    }
                    if ($category['name'] == "No category") {
                        $category['name'] = translate("no_category", $i18n);
                    }
                    $selectedClass = '';
                    if (isset($_GET['category'])) {
                        $categoryIds = explode(',', $_GET['category']);
                        if (in_array($category['id'], $categoryIds)) {
                            $selectedClass = 'selected';
                        }
                    }
                    ?>
                    <div class="filter-item <?= $selectedClass ?>" data-categoryid="<?= $category['id'] ?>">
                        <?= $category['name'] ?>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }
    ?>


    <div class="filtermenu-submenu hide" id="clear-filters">
        <div class="filter-title filter-clear" onClick="clearFilters()">
            <i class="fa-solid fa-times-circle"></i> <?= translate("clear", $i18n) ?>
        </div>
    </div>
</div>