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

$demoMode = getenv('DEMO_MODE');

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

if (isset($themeValue)) {
  $cookieExpire = time() + (30 * 24 * 60 * 60);
  setcookie('theme', $themeValue, [
    'expires' => $cookieExpire,
    'samesite' => 'Strict'
  ]);
}

$isAdmin = $_SESSION['userId'] == 1;

$locale = isset($_COOKIE['user_locale']) ? $_COOKIE['user_locale'] : 'en_US';
$formatter = new IntlDateFormatter(
  $locale, 
  IntlDateFormatter::MEDIUM,
  IntlDateFormatter::NONE
);

function hex2rgb($hex)
{
  $hex = str_replace("#", "", $hex);
  if (strlen($hex) == 3) {
    $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
    $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
    $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
  } else {
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
  }
  return "$r, $g, $b";
}

$mobileNavigation = $settings['mobile_nav'] ? "mobile-navigation" : "";

?>
<!DOCTYPE html>
<html dir="<?= $languages[$lang]['dir'] ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Wallos - Subscription Tracker</title>
  <meta name="apple-mobile-web-app-title" content="Wallos">
  <meta name="theme-color" content="<?= $theme == "light" ? "#FFFFFF" : "#222222" ?>" id="theme-color" />
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
    window.lang = "<?= $lang ?>";
    window.colorTheme = "<?= $colorTheme ?>";
    window.mobileNavigation = "<?= $settings['mobileNavigation'] == "true" ?>";
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
          --main-color:
            <?= $settings['customColors']['main_color'] ?>
          ;
          --main-color-rgb:
            <?= hex2rgb($settings['customColors']['main_color']) ?>
          ;
        <?php endif; ?>
        <?php if (isset($settings['customColors']['accent_color']) && !empty($settings['customColors']['accent_color'])): ?>
          --accent-color:
            <?= $settings['customColors']['accent_color'] ?>
          ;
          --accent-color-rgb:
            <?= hex2rgb($settings['customColors']['accent_color']) ?>
          ;
        <?php endif; ?>
        <?php if (isset($settings['customColors']['hover_color']) && !empty($settings['customColors']['hover_color'])): ?>
          --hover-color:
            <?= $settings['customColors']['hover_color'] ?>
          ;
          --hover-color-rgb:
            <?= hex2rgb($settings['customColors']['hover_color']) ?>
          ;
        <?php endif; ?>
      }
    </style>
    <?php
  }
  ?>
  <script type="text/javascript" src="scripts/i18n/<?= $lang ?>.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/i18n/getlang.js?<?= $version ?>"></script>
</head>

<body class="<?= $theme ?> <?= $languages[$lang]['dir'] ?> <?= $mobileNavigation ?>">
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
            <img src="<?= htmlspecialchars($userData['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="me" id="avatar">
            <span id="user" class="mobileNavigationHideOnMobile"><?= $username ?></span>
          </button>
          <div class="dropdown-content">
            <a href="profile.php" class="mobileNavigationHideOnMobile">
              <?php include "images/siteicons/svg/mobile-menu/profile.php"; ?>
              <?= translate('profile', $i18n) ?></a>
            <a href="." class="mobileNavigationHideOnMobile">
              <?php include "images/siteicons/svg/mobile-menu/home.php"; ?>
              <?= translate('subscriptions', $i18n) ?></a>
            <a href="calendar.php" class="mobileNavigationHideOnMobile">
                <?php include "images/siteicons/svg/mobile-menu/calendar.php"; ?>
                <?= translate('calendar', $i18n) ?></a>
            <a href="stats.php" class="mobileNavigationHideOnMobile">
              <?php include "images/siteicons/svg/mobile-menu/statistics.php"; ?>
              <?= translate('stats', $i18n) ?></a>
            <a href="settings.php" class="mobileNavigationHideOnMobile">
              <?php include "images/siteicons/svg/mobile-menu/settings.php"; ?>
              <?= translate('settings', $i18n) ?></a>
            <?php if ($isAdmin): ?>
              <a href="admin.php">
                <?php include "images/siteicons/svg/mobile-menu/admin.php"; ?>
                <?= translate('admin', $i18n) ?>
              </a>
            <?php endif; ?>
            <a href="about.php">
              <?php include "images/siteicons/svg/mobile-menu/about.php"; ?>
              <?= translate('about', $i18n) ?>
            </a>
            <?php
            if ($settings['disableLogin'] == 0) {
              ?>
              <a href="logout.php">
                <?php include "images/siteicons/svg/mobile-menu/logout.php"; ?>
                <?= translate('logout', $i18n) ?></a>
              <?php
            }
            ?>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <?php
  // find out which page is being viewed
  $page = basename($_SERVER['PHP_SELF']);
  $subscriptionsClass = $page === 'index.php' ? 'active' : '';
  $calendarClass = $page === 'calendar.php' ? 'active' : '';
  $statsClass = $page === 'stats.php' ? 'active' : '';
  $settingsClass = $page === 'settings.php' ? 'active' : '';
  $profileClass = $page === 'profile.php' ? 'active' : '';
  ?>

  <?php
  if ($settings['mobile_nav'] == 1) {
    ?>
    <nav class="mobile-nav">
        <a href="." class="nav-link <?= $subscriptionsClass ?>" title="<?= translate('subscriptions', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/home.php"; ?>
          Home
        </a>
        <a href="calendar.php" class="nav-link <?= $calendarClass ?>" title="<?= translate('calendar', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/calendar.php"; ?>
          Calendar
        </a>
        <a href="stats.php" class="nav-link <?= $statsClass ?>" title="<?= translate('stats', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/statistics.php"; ?>
          Statistics
        </a>
        <a href="settings.php" class="nav-link <?= $settingsClass ?>" title="<?= translate('settings', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/settings.php"; ?>
          Settings
        </a>
        <a href="profile.php" class="nav-link <?= $profileClass ?>" title="<?= translate('profile', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/profile.php"; ?>
          Profile
        </a>
    </nav>
    <?php
  }
  ?>

  <main>