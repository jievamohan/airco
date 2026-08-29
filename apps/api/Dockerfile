FROM php:8.4-apache

# Bewust Apache en geen `php artisan serve`: die laatste is PHP's ingebouwde
# ontwikkelserver, verwerkt verzoeken serieel en gaat anders om met
# omgevingsvariabelen dan een echte webserver. Zo lijkt lokaal op productie.

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip \
        libicu-dev libonig-dev libzip-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl mbstring zip gd bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Laravel serveert vanuit public/; de rest van de applicatie hoort niet
# bereikbaar te zijn via de webserver.
RUN a2enmod rewrite \
    && printf '%s\n' \
        '<VirtualHost *:80>' \
        '    DocumentRoot /app/public' \
        '' \
        '    <Directory /app/public>' \
        '        AllowOverride All' \
        '        Require all granted' \
        '    </Directory>' \
        '' \
        '    ErrorLog /proc/self/fd/2' \
        '    CustomLog /proc/self/fd/1 combined' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80

CMD ["sh", "/app/docker/start.sh", "api"]
