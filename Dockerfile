FROM docker.io/library/php:8.5-apache

RUN a2enmod rewrite headers expires deflate \
    && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/security.conf \
    && a2enconf security

COPY docker/php.ini /usr/local/etc/php/conf.d/99-searphp.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . .

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/health") !== false ? 0 : 1);'
