<?php
  require_once 'connect.php';
  require_once 'checkuser.php';
  require_once 'checksession.php';
  require_once 'currency_formatter.php';

  require_once 'i18n/languages.php';
  require_once 'i18n/getlang.php';
  require_once 'i18n/' . $lang . '.php';

  require_once 'getsettings.php';

  require_once 'version.php';

  if ($userCount == 0) {
    $db->close();
    header("Location: registration.php");
    exit();
  }

  $theme = "automatic";
  if (isset($settings['theme'])) {
    $theme = $settings['theme'];
  }

  $updateThemeSettings = false;
  if (isset($settings['update_theme_setttings'])) {
    $updateThemeSettings = $settings['update_theme_setttings'];
  }

  $colorTheme = "blue";
  if (isset($settings['color_theme'])) {
    $colorTheme = $settings['color_theme'];
  }

  $customCss = "";
  if (isset($settings['customCss'])) {
    $customCss = $settings['customCss'];
  }

  $isAdmin = $_SESSION['userId'] == 1;

  function hex2rgb($hex) {
    $hex = str_replace("#", "", $hex);
    if(strlen($hex) == 3) {
      $r = hexdec(substr($hex,0,1).substr($hex,0,1));
      $g = hexdec(substr($hex,1,1).substr($hex,1,1));
      $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
      $r = hexdec(substr($hex,0,2));
      $g = hexdec(substr($hex,2,2));
      $b = hexdec(substr($hex,4,2));
    }
    return "$r, $g, $b";
  }

?>
<!DOCTYPE html>
<html dir="<?= $languages[$lang]['dir'] ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Wallos - Subscription Tracker</title>
  <meta name="apple-mobile-web-app-title" content="Wallos">
  <meta name="theme-color" content="<?= $theme == "light" ? "#FFFFFF" : "#222222" ?>" id="theme-color"/>
  <link rel="icon" type="image/png" href="images/icon/favicon.ico" sizes="16x16">
  <link rel="apple-touch-icon" href="images/icon/apple-touch-icon.png">
  <link rel="apple-touch-icon" sizes="152x152" href="images/icon/apple-touch-icon-152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="images/icon/apple-touch-icon-180.png">
  <link rel="manifest" href="manifest.json" crossorigin="use-credentials">
  <link rel="stylesheet" href="styles/theme.css?<?= $version ?>">
  <link rel="stylesheet" href="styles/styles.css?<?= $version ?>">
  <link rel="stylesheet" href="styles/dark-theme.css?<?= $version ?>" id="dark-theme" <?= $theme != "dark" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/red.css?<?= $version ?>" id="red-theme" <?= $colorTheme != "red" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/green.css?<?= $version ?>" id="green-theme" <?= $colorTheme != "green" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/yellow.css?<?= $version ?>" id="yellow-theme" <?= $colorTheme != "yellow" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/purple.css?<?= $version ?>" id="purple-theme" <?= $colorTheme != "purple" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/barlow.css">
  <link rel="stylesheet" href="styles/font-awesome.min.css">
  <link rel="stylesheet" href="styles/brands.css">
  <script type="text/javascript" src="scripts/all.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/common.js?<?= $version ?>"></script>
  <script type="text/javascript">
    window.theme = "<?= $theme ?>";
    window.update_theme_settings = "<?= $updateThemeSettings ?>";
    window.lang = "<?=$lang ?>";
    window.colorTheme = "<?= $colorTheme ?>";
  </script>
  <style>
    <?= htmlspecialchars($customCss, ENT_QUOTES, 'UTF-8') ?>
  </style>
  <?php
    if (isset($settings['customColors'])) {
      ?>
        <style id="custom_theme_colors">
          :root {
            <?php if (isset($settings['customColors']['main_color']) && !empty($settings['customColors']['main_color'])): ?>
              --main-color: <?= $settings['customColors']['main_color'] ?>;
              --main-color-rgb: <?= hex2rgb($settings['customColors']['main_color']) ?>;
            <?php endif; ?>
            <?php if (isset($settings['customColors']['accent_color']) && !empty($settings['customColors']['accent_color'])): ?>
              --accent-color: <?= $settings['customColors']['accent_color'] ?>;
              --accent-color-rgb: <?= hex2rgb($settings['customColors']['accent_color']) ?>;
            <?php endif; ?>
            <?php if (isset($settings['customColors']['hover_color']) && !empty($settings['customColors']['hover_color'])): ?>
              --hover-color: <?= $settings['customColors']['hover_color'] ?>;
              --hover-color-rgb: <?= hex2rgb($settings['customColors']['hover_color']) ?>;
            <?php endif; ?>
          }
        </style>
      <?php
    }
  ?>
  <script type="text/javascript" src="scripts/i18n/<?= $lang ?>.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/i18n/getlang.js?<?= $version ?>"></script>
</head>
<body class="<?= $theme ?> <?= $languages[$lang]['dir'] ?>">
  <header>
    <div class="contain">
      <div class="logo">
          <a href=".">
              <div class="logo-image" title="Wallos - Subscription Tracker">
              <?php include "images/siteicons/svg/logo.php"; ?>
              </div>
          </a>
      </div>
      <nav>
        <div class="dropdown">
          <button class="dropbtn" onClick="toggleDropdown()">
            <img src="<?= $userData['avatar'] ?>" alt="me" id="avatar">
            <span id="user"><?= $username ?></span>
          </button>
          <div class="dropdown-content">
            <a href="."><i class="fa-solid fa-list"></i><?= translate('subscriptions', $i18n) ?></a>
            <a href="calendar.php"><i class="fa-solid fa-calendar"></i><?= translate('calendar', $i18n) ?></a>
            <a href="stats.php"><i class="fa-solid fa-chart-simple"></i><?= translate('stats', $i18n) ?></a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i><?= translate('settings', $i18n) ?></a>
            <?php if ($isAdmin): ?>
              <a href="admin.php"><i class="fa-solid fa-user-tie"></i><?= translate('admin', $i18n) ?></a>
            <?php endif; ?>
            <a href="about.php"><i class="fa-solid fa-info-circle"></i><?= translate('about', $i18n) ?></a>
            <?php
              if ($settings['disableLogin'] == 0) {
                ?>
                  <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><?= translate('logout', $i18n) ?></a>
                <?php
              }
            ?>
          </div>
        </div>
      </nav>
    </div>
  </header>
  <main>
    