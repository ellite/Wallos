# Use the php:8.0.5-fpm-alpine base image
FROM php:8.0.5-fpm-alpine

# Set working directory to /var/www/html
WORKDIR /var/www/html

# Install SQLite3 and its dependencies
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && docker-php-ext-enable pdo pdo_sqlite

# Install additional PHP extensions and dependencies
RUN apk add --no-cache libpng libpng-dev libjpeg-turbo libjpeg-turbo-dev freetype freetype-dev curl autoconf libgomp \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Install Imagick extension
RUN apk add --no-cache imagemagick imagemagick-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del .build-deps

# Install Nginx and Cron
RUN apk add --no-cache nginx \
    && apk add --no-cache dcron

RUN apk add --no-cache tzdata    

# Copy your PHP application files into the container
COPY . .

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf
COPY nginx.default.conf /etc/nginx/http.d/default.conf

# Copy the custom crontab file
COPY cronjobs /etc/cron.d/cronjobs

# Convert the line endings
RUN dos2unix /etc/cron.d/cronjobs

# Allow read access to the cron file
RUN chmod 0644 /etc/cron.d/cronjobs
RUN /usr/bin/crontab /etc/cron.d/cronjobs

# Create cron log folder
RUN mkdir /var/log/cron

# Change ownership and permissions for SQLite database
RUN chown -R www-data:www-data /var/www/html

RUN chmod +x /var/www/html/startup.sh

# Expose port 80 for Nginx
EXPOSE 80

ARG SOFTWARE_VERSION=1.0.0

# Start both PHP-FPM, Nginx
CMD ["sh", "-c", "/var/www/html/startup.sh"]