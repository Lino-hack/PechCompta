FROM php:8.5-apache

RUN a2enmod rewrite headers

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    if ls /usr/local/lib/php/extensions/*/opcache.so >/dev/null 2>&1; then \
        printf 'zend_extension=opcache\n' > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini; \
    fi

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && composer dump-autoload --no-dev --optimize

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]