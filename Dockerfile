# Use the php:8.0.5-fpm-alpine base image
FROM php:8.2-fpm-alpine

# Set working directory to /var/www/html
WORKDIR /var/www/html

# Update packages and install dependencies
RUN apk upgrade --no-cache && \
    apk add --no-cache sqlite-dev libpng libpng-dev libjpeg-turbo libjpeg-turbo-dev freetype freetype-dev curl autoconf libgomp icu-dev nginx dcron tzdata imagemagick imagemagick-dev libzip-dev sqlite libwebp-dev && \
    docker-php-ext-install pdo pdo_sqlite calendar && \
    docker-php-ext-enable pdo pdo_sqlite && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j$(nproc) gd intl zip && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    pecl install imagick && \
    docker-php-ext-enable imagick && \
    apk del .build-deps

# Copy your PHP application files into the container
COPY . .

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf
COPY nginx.default.conf /etc/nginx/http.d/default.conf

# Copy the custom crontab file
COPY cronjobs /etc/cron.d/cronjobs

# Convert the line endings, allow read access to the cron file, and create cron log folder
RUN dos2unix /etc/cron.d/cronjobs && \
    chmod 0644 /etc/cron.d/cronjobs && \
    /usr/bin/crontab /etc/cron.d/cronjobs && \
    mkdir /var/log/cron && \
    chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/startup.sh && \
    echo 'pm.max_children = 15' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.max_requests = 500' >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Expose port 80 for Nginx
EXPOSE 80

ARG SOFTWARE_VERSION=1.20.0

# Start both PHP-FPM, Nginx
CMD ["sh", "-c", "/var/www/html/startup.sh"]
