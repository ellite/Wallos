#!/bin/sh

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

# Start both PHP-FPM and Nginx
php-fpm & nginx -g 'daemon off;' & touch ~/startup.txt

# Start the cron daemon
crond

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

# Keep the container running indefinitely (this won't exit)
tail -f /dev/null