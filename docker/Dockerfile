FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libxml2-dev libicu-dev \
    libpq-dev unzip cron git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd zip xml intl opcache pdo pdo_mysql mysqli \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP config untuk Moodle
RUN echo "max_input_vars = 5000" >> /usr/local/etc/php/conf.d/moodle.ini \
 && echo "upload_max_filesize = 512M" >> /usr/local/etc/php/conf.d/moodle.ini \
 && echo "post_max_size = 512M" >> /usr/local/etc/php/conf.d/moodle.ini \
 && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/moodle.ini \
 && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/moodle.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy web app
COPY webapps/eujian-sync/ /var/www/html/eujian-sync/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
