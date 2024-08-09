<picture>
  <source media="(prefers-color-scheme: dark)" srcset="./images/siteicons/walloswhite.png">
  <source media="(prefers-color-scheme: light)" srcset="./images/siteicons/wallos.png">
  <img alt="Wallos" src="./images/siteicons/wallos.png">
</picture>

Wallos: Open-Source Personal Subscription Tracker

## Table of Contents

- [Introduction](#introduction)
- [Features](#features)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
    - [Baremetal](#baremetal)
    - [Docker](#docker)
  - [Installation](#installation)
    - [Baremetal](#baremetal-1)
      - [Updating](#updating)
    - [Docker](#docker-1)
    - [Docker-Compose](#docker-compose)
- [Usage](#usage)
- [Contributing](#contributing)
  - [Contributors](#contributors)
  - [Translations](#translations)
- [Screenshots](#screenshots)
- [License](#license)
- [Links](#links)

## Introduction

Wallos is a powerful, open-source, and self-hostable web application designed to empower you in managing your finances with ease. Say goodbye to complicated spreadsheets and expensive financial software – Wallos simplifies the process of tracking expenses and helps you gain better control over your financial life.

## Features

- Subscription Management: Keep track of your recurring subscriptions and payments, ensuring you never miss a due date.
- Category Management: Organize your expenses into customizable categories, enabling you to gain insights into your spending habits.
- Multi-Currency support: Wallos supports multiple currencies, allowing you to manage your finances in the currency of your choice.
- Currency Conversion: Integrates with the Fixer API so you can get exchange rates and see all your subscriptions on your main currency.
- Data Privacy: As a self-hosted application, Wallos ensures that your financial data remains private and secure on your own server.
- Customization: Tailor Wallos to your needs with customizable categories, currencies, themes and other display options.
- Sorting Options: Allowing you to view your subscriptions from different perspectives.
- Logo Search: Wallos can search the web for the logo of your subscriptions if you don't have them available for upload.
- Mobile view: Wallos on the go.
- Statistics: Another perspective into your spendings.
- Notifications:  Wallos supports multiple notification methods (email, discord, pushover, telegram, gotify and webhooks). Get notified about your upcoming payments.
- Multi Language support.

## Getting Started

See instructions to run Wallos below.

### Prerequisites

#### Baremetal

- NGINX or APACHE websever running
- PHP 8.2 with the following modules enabled:
    - curl
    - gd
    - imagick
    - intl
    - openssl
    - sqlite3
    - zip

#### Docker

- Docker

### Installation

#### Baremetal

1. Download or clone this repo and move the files into your web root - usually `/var/www/html`
2. Rename `/db/wallos.empty.db` to `/db/wallos.db`
3. Run `http://domain.example/endpoints/db/migrate.php` on your browser
4. Add the following scripts to your cronjobs with `crontab -e`

```bash
0 1 * * * php /var/www/html/endpoints/cronjobs/updatenextpayment.php >> /var/log/cron/updatenextpayment.log 2>&1
0 2 * * * php /var/www/html/endpoints/cronjobs/updateexchange.php >> /var/log/cron/updateexchange.log 2>&1
0 8 * * * php /var/www/html/endpoints/cronjobs/sendcancellationnotifications.php >> /var/log/cron/sendcancellationnotifications.log 2>&1
0 9 * * * php /var/www/html/endpoints/cronjobs/sendnotifications.php >> /var/log/cron/sendnotifications.log 2>&1
*/2 * * * * php /var/www/html/endpoints/cronjobs/sendverificationemails.php >> /var/log/cron/sendverificationemail.log 2>&1
*/2 * * * * php /var/www/html/endpoints/cronjobs/sendresetpasswordemails.php >> /var/log/cron/sendresetpasswordemails.log 2>&1
0 */6 * * * php /var/www/html/endpoints/cronjobs/checkforupdates.php >> /var/log/cron/checkforupdates.log 2>&1
```

5. If your web root is not `/var/www/html/` adjust the cronjobs above accordingly.

#### Updating

1. Re-download the repo and move the files into the correct folder or do `git pull` (if you used git clone before)
2. Check the [Prerequisites](#baremetal) and install / enable the missing ones, if any.
3. Run `http://domain.example/endpoints/db/migrate.php`

#### Docker

```bash
docker run -d --name wallos -v /path/to/config/wallos/db:/var/www/html/db \
-v /path/to/config/wallos/logos:/var/www/html/images/uploads/logos \
-e TZ=Europe/Berlin -p 8282:80 --restart unless-stopped \
bellamy/wallos:latest
```

### Docker Compose

```
version: '3.0'

services:
  wallos:
    container_name: wallos
    image: bellamy/wallos:latest
    ports:
      - "8282:80/tcp"
    environment:
      TZ: 'America/Toronto'
    # Volumes store your data between container upgrades
    volumes:
      - './db:/var/www/html/db'
      - './logos:/var/www/html/images/uploads/logos'
    restart: unless-stopped
```

## Usage

Just open the browser and open `ip:port` of the machine running wallos.  
On the first time you run wallos a user account must be created.  
Go to settings and personalise your Avatar and add members of your household. While there add / remove any categories and currencies.  
Get a free API Key from [Fixer](https://fixer.io/#pricing_plan) and add it in the settings.  
If you want to trigger an Update of the exchange rates, change your main currency after adding the API Key, and then change it back to your prefered one.  

## Screenshots

![Screenshot](screenshots/wallos-dashboard-light.png)

![Screenshot](screenshots/wallos-dashboard-dark.png)

![Screenshot](screenshots/wallos-stats.png)

![Screenshot](screenshots/wallos-calendar.png)

![Screenshot](screenshots/wallos-form.png)

![Screenshot](screenshots/wallos-dashboard-mobile-light.png) ![Screenshot](screenshots/wallos-dashboard-mobile-dark.png)

## Contributing

Feel free to open Pull requests with bug fixes and features. I'll do my best to keep an eye on those.  
Feel free to open issues with bug reports or feature requests. Bug fixes will take priority.  
I welcome contributions from the community and look forward to working with you to improve this project.

### Contributors

<a href="https://github.com/ellite/wallos/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=ellite/wallos" />
</a>

### Translations

If you want to contribute with a translation of wallos:
- Add your language code to `includes/i18n/languages.php` in the format `"en" => "English"`. Please use the original language name and not the english translation.
- Create a copy of the file `includes/i18n/en.php` and rename it to the language code you used above. Example: pt.php for "pt" => "Português".
- Translate all the values on the language file to the new language. (Incomplete translations will not be accepted).
- Create a copy of the file `scripts/i18n/en.js` and rename it to the language code you used above. Example: pt.js for "pt" => "Português".
- Translate all the values on the language file to the new language. (Incomplete translations will not be accepted).

## License

This project is licensed under the [GNU General Public License, Version 3](LICENSE.md) - see the [LICENSE.md](LICENSE.md) file for details.

### Why GPLv3?

I chose the GNU General Public License version 3 (GPLv3) for this project because it ensures that the software remains open source and freely available to the community. GPLv3 mandates that any derivative works or modifications must also be released under the same license, promoting the principles of software freedom.

I strongly believe in the importance of open source software and the collaborative nature of development, and I invite contributors to help improve this project.

## Links

- The author: [henrique.pt](https://henrique.pt)
- Wallos Landinpage: [wallosapp.com](https://wallosapp.com)
- Join the conversation: [Discord Server](https://discord.gg/anex9GUrPW)