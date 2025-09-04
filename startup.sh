#!/bin/sh

set -euo pipefail

echo "Startup script is running..." > /var/log/startup.log

# Default the PUID and PGID environment variables to 82, otherwise
# set to the user defined ones.
PUID=${PUID:-82}
PGID=${PGID:-82}

# Change the www-data user id and group id to be the user-specified ones
groupmod -o -g "$PGID" www-data
usermod -o -u "$PUID" www-data
chown -R www-data:www-data /var/www/html
chown -R www-data:www-data /tmp
chmod -R 770 /tmp

# PIDs we’ll track
PHP_FPM_PID=
NGINX_PID=
CROND_PID=
shutdown_in_progress=0

shutdown_once() {
  exit_signal=$?
  kill_signal=$(kill -l "$exit_signal" 2>/dev/null || echo "$exit_signal")

  [ "$shutdown_in_progress" -eq 1 ] && return 0
  shutdown_in_progress=1

  echo "Got signal: $kill_signal - Shutting down gracefully... "
  # nginx wants QUIT for graceful
  nginx -s quit || true
  # php-fpm graceful quit as well
  [ -n "${PHP_FPM_PID}" ] && kill -QUIT "${PHP_FPM_PID}" 2>/dev/null || true
  # cron can just get TERM
  [ -n "${CROND_PID}" ] && kill -TERM "${CROND_PID}" 2>/dev/null || true
  echo "Graceful shutdown complete."
}

# Handle all common stop signals
trap 'shutdown_once' SIGTERM SIGINT SIGQUIT

# Start both PHP-FPM and Nginx
echo "Launching php-fpm"
php-fpm -F &
PHP_FPM_PID=$!

echo "Launching crond"
crond -f &
CROND_PID=$!

echo "Launching nginx"
nginx -g 'daemon off;' &
NGINX_PID=$!

touch ~/startup.txt

# Wait one second before running scripts
sleep 1

# Create database if it does not exist
/usr/local/bin/php /var/www/html/endpoints/cronjobs/createdatabase.php

# Perform any database migrations
/usr/local/bin/php /var/www/html/endpoints/db/migrate.php

# Change permissions on the database directory
chmod -R 755 /var/www/html/db/
chown -R www-data:www-data /var/www/html/db/

mkdir -p /var/www/html/images/uploads/logos/avatars

# Change permissions on the logos directory
chmod -R 755 /var/www/html/images/uploads/logos
chown -R www-data:www-data /var/www/html/images/uploads/logos

# Remove crontab for the user
crontab -d -u root

# Run updatenextpayment.php and wait for it to finish
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updatenextpayment.php

# Run updateexchange.php
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updateexchange.php

# Run checkforupdates.php
/usr/local/bin/php /var/www/html/endpoints/cronjobs/checkforupdates.php

# Essentially wait until all child processes exit
wait
