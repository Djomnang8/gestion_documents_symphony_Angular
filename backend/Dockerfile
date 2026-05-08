FROM php:8.4-cli

# Dépendances système + extensions PHP
RUN apt-get update && apt-get install -y git unzip zip libicu-dev openssl \
    && docker-php-ext-install pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer globalement
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copier d'abord composer.json et composer.lock pour le cache
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Copier le reste du code
COPY . /app

# Supprimer un éventuel dossier vendor résiduel et relancer l'installation des dépendances
RUN rm -rf vendor && composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Assurer les droits d'écriture sur var/
RUN mkdir -p var/cache var/log && chmod -R 777 var/

EXPOSE 8080
