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

  $theme = "light";
  if (isset($settings['theme'])) {
    $theme = $settings['theme'];
  }

  $colorTheme = "blue";
  if (isset($settings['color_theme'])) {
    $colorTheme = $settings['color_theme'];
  }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Wallos - Subscription Tracker</title>
  <meta name="theme-color" content="<?= $theme == "light" ? "#FFFFFF" : "#222222" ?>"/>
  <link rel="icon" type="image/png" href="images/icon/favicon.ico" sizes="16x16">
  <link rel="apple-touch-icon" sizes="180x180" href="images/icon/apple-touch-icon.png">
  <link rel="manifest" href="manifest.json" crossorigin="use-credentials">
  <link rel="stylesheet" href="styles/theme.css?<?= $version ?>">
  <link rel="stylesheet" href="styles/styles.css?<?= $version ?>">
  <link rel="stylesheet" href="styles/dark-theme.css?<?= $version ?>" id="dark-theme" <?= $theme == "light" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/red.css?<?= $version ?>" id="red-theme" <?= $colorTheme != "red" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/green.css?<?= $version ?>" id="green-theme" <?= $colorTheme != "green" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/yellow.css?<?= $version ?>" id="yellow-theme" <?= $colorTheme != "yellow" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/barlow.css">
  <link rel="stylesheet" href="styles/font-awesome.min.css">
  <link rel="stylesheet" href="styles/brands.css">
  <script type="text/javascript" src="scripts/all.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/common.js?<?= $version ?>"></script>
  <script type="text/javascript">
    window.theme = "<?= $theme ?>";
    window.lang = "<?=$lang ?>";
    window.colorTheme = "<?= $colorTheme ?>";
  </script>
  <?php
    if (isset($settings['customColors'])) {
      ?>
        <style id="custom_theme_colors">
          :root {
            <?php if (isset($settings['customColors']['main_color']) && !empty($settings['customColors']['main_color'])): ?>
              --main-color: <?= $settings['customColors']['main_color'] ?>;
            <?php endif; ?>
            <?php if (isset($settings['customColors']['accent_color']) && !empty($settings['customColors']['accent_color'])): ?>
              --accent-color: <?= $settings['customColors']['accent_color'] ?>;
            <?php endif; ?>
            <?php if (isset($settings['customColors']['hover_color']) && !empty($settings['customColors']['hover_color'])): ?>
              --hover-color: <?= $settings['customColors']['hover_color'] ?>;
            <?php endif; ?>
          }
        </style>
      <?php
    }
  ?>
  <script type="text/javascript" src="scripts/i18n/<?= $lang ?>.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/i18n/getlang.js?<?= $version ?>"></script>
</head>
<body class="<?= $theme ?>">
  <header>
    <div class="contain">
      <div class="logo">
          <a href=".">
              <div class="logo-image"></div>
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
            <a href="stats.php"><i class="fa-solid fa-chart-simple"></i><?= translate('stats', $i18n) ?></a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i><?= translate('settings', $i18n) ?></a>
            <a href="about.php"><i class="fa-solid fa-info-circle"></i><?= translate('about', $i18n) ?></a>
            <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i><?= translate('logout', $i18n) ?></a>
          </div>
        </div>
      </nav>
    </div>
  </header>
  <main>
    