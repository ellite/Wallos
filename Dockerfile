# Use the php:8.2-fpm-alpine base image
FROM php:8.2-fpm-alpine

# Set working directory to /var/www/html
WORKDIR /var/www/html

# Update packages and install dependencies
RUN apk upgrade --no-cache && \
    apk add --no-cache sqlite-dev libpng libpng-dev libjpeg-turbo libjpeg-turbo-dev freetype freetype-dev curl autoconf libgomp icu-dev nginx dcron tzdata imagemagick imagemagick-dev libzip-dev sqlite libwebp-dev gettext && \
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

# Copy the main Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

# Ensure conf.d directory exists and copy the default configuration
RUN mkdir -p /etc/nginx/conf.d
COPY nginx.default.conf /etc/nginx/conf.d/default.template.conf

# Copy the custom crontab file
COPY cronjobs /etc/cron.d/cronjobs

# Prepare cron configuration and PHP-FPM settings
RUN dos2unix /etc/cron.d/cronjobs && \
    chmod 0644 /etc/cron.d/cronjobs && \
    /usr/bin/crontab /etc/cron.d/cronjobs && \
    mkdir /var/log/cron && \
    chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/startup.sh && \
    echo 'pm.max_children = 15' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.max_requests = 500' >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Default port
ENV PORT 80

# Expose the port
EXPOSE ${PORT}

# Substitute the ${PORT} variable in Nginx configs and start services
CMD ["sh", "-c", "envsubst '${PORT:-80}' < /etc/nginx/nginx.conf > /etc/nginx/nginx.conf && envsubst '${PORT:-80}' < /etc/nginx/conf.d/default.template.conf > /etc/nginx/conf.d/default.conf && /var/www/html/startup.sh"]
