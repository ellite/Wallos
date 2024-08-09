#!/bin/sh

echo "Startup script is running..." > /var/log/startup.log

# If the PUID or PGID environment variables are set, create a new user and group
if [ ! -z "$PUID" ] && [ ! -z "$PGID" ]; then
    addgroup -g $PGID appgroup
    adduser -D -u $PUID -G appgroup appuser
    chown -R appuser:appgroup /var/www/html
fi

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