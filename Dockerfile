
# construction de l'image PHP/Apache une fois (extensions, docroot et mod_rewrite activé)

FROM php:8.2-apache

# Augmenter la taille max upload (PHP)
RUN { \
    echo "upload_max_filesize=20M"; \
    echo "post_max_size=25M"; \
    echo "memory_limit=256M"; \
    echo "max_execution_time=120"; \
    echo "max_input_time=120"; \
} > /usr/local/etc/php/conf.d/99-uploads.ini

#Installer les dépendances système AVANT d'installer les extensions PHP
RUN apt-get update && apt-get install -y git zip unzip libzip-dev && rm -rf /var/lib/apt/lists/*

# Installer l'extensions pour PHP + le zip
RUN docker-php-ext-install pdo pdo_mysql zip

# Apache: activer mod_rewrite et pointer vers/public
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Composer (binaire)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer 

WORKDIR /var/www/html