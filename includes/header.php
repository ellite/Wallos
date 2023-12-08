<?php
  require_once 'connect.php';
  require_once 'checkuser.php';
  require_once 'checksession.php';
  require_once 'currency_formatter.php';

  if ($userCount == 0) {
    $db->close();
    header("Location: registration.php");
    exit();
  }

  $theme = "light";
  if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
  }

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Wallos - Subscription Tracker</title>
  <link rel="icon" type="image/png" href="images/icon/favicon.ico" sizes="16x16">
  <link rel="apple-touch-icon" sizes="180x180" href="images/icon/apple-touch-icon.png">
  <link rel="manifest" href="images/icon/site.webmanifest">
  <link rel="stylesheet" href="styles/styles.css">
  <link rel="stylesheet" href="styles/dark-theme.css" id="dark-theme" <?= $theme == "light" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Barlow:300,400,500,600,700">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script type="text/javascript" src="scripts/common.js"></script>
  <script type="text/javascript">
    window.theme = "<?= $theme ?>";
  </script>
</head>
<body>
  <header>
    <div class="contain">
      <div class="logo">
          <a href="/">
              <div class="logo-image"></div>
          </a>
      </div>
      <nav>
        <div class="dropdown">
          <button class="dropbtn" onClick="toggleDropdown()">
            <img src="images/avatars/<?= $userData['avatar'] ?>.svg" alt="me" id="avatar">
            <span id="user"><?= $username ?></span>
          </button>
          <div class="dropdown-content">
            <a href="stats.php"><i class="fa-solid fa-chart-simple"></i>Stats</a>
            <a href="settings.php"><i class="fa-solid fa-gear"></i>Settings</a>
            <a href="about.php"><i class="fa-solid fa-info-circle"></i>About</a>
            <a href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i>Logout</a>
          </div>
        </div>
      </nav>
    </div>
  </header>
  <main>
    