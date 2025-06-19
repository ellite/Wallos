#!/bin/sh

echo "Startup script is running..."

# Default the PUID and PGID environment variables to 82, otherwise
# set to the user defined ones.
PUID=${PUID:-82}
PGID=${PGID:-82}

echo 'Update users'
# Change the www-data user id and group id to be the user-specified ones
groupmod -o -g "$PGID" www-data
usermod -o -u "$PUID" www-data

echo "Setting rights for /tmp"
chown -R www-data:www-data /tmp
chmod -R 770 /tmp

echo "Start the cron daemon"
crond

# Wait one second before running scripts
sleep 1

echo "Create database if it does not exist"
/usr/local/bin/php /var/www/html/endpoints/cronjobs/createdatabase.php

echo "Perform any database migrations"
/usr/local/bin/php /var/www/html/endpoints/db/migrate.php

echo "Change permissions on the database directory"
chmod -R 755 /var/www/html/db/
chown -R www-data:www-data /var/www/html/db/

mkdir -p /var/www/html/images/uploads/logos/avatars

echo "Change permissions on the logos directory"
chmod -R 755 /var/www/html/images/uploads/logos
chown -R www-data:www-data /var/www/html/images/uploads/logos

echo "Remove crontab for the user"
crontab -d -u root

echo "Run updatenextpayment.php and wait for it to finish"
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updatenextpayment.php

echo "Run updateexchange.php"
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updateexchange.php

echo "Run checkforupdates.php"
/usr/local/bin/php /var/www/html/endpoints/cronjobs/checkforupdates.php

echo "Start PHP-FPM"
php-fpm &

echo "Start NGINX"
nginx -g 'daemon off;' & 

echo 'Startup complete'

echo "Keep the container running indefinitely (this won't exit)"
tail -f /dev/null