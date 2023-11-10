#!/bin/sh

echo "Startup script is running..." > /var/log/startup.log

# Start both PHP-FPM and Nginx
php-fpm & nginx -g 'daemon off;' & touch ~/startup.txt

# Start the cron daemon
crond

# Wait one second before running scripts
sleep 1

# Create database if it does not exist
/usr/local/bin/php /var/www/html/endpoints/cronjobs/createdatabase.php

# Change permissions on the database directory
chmod -R 755 /var/www/html/db/

# Run updatenextpayment.php and wait for it to finish
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updatenextpayment.php

# Run updateexchange.php
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updateexchange.php

# Keep the container running indefinitely (this won't exit)
tail -f /dev/null