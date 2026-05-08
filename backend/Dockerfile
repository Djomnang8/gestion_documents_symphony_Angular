FROM php:8.4-cli

# Système + extensions PHP
RUN apt-get update && apt-get install -y git unzip zip libicu-dev openssl \
    && docker-php-ext-install pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copier les dépendances
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Copier tout le code
COPY . /app

# Supprimer un éventuel vendor résiduel et réinstaller
RUN rm -rf vendor && composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Forcer l'installation du Runtime (si nécessaire)
RUN composer require symfony/runtime --no-interaction --no-scripts --optimize-autoloader

# Préparer le cache de développement
RUN rm -rf var/cache && php bin/console cache:clear --env=dev

# Générer les clés JWT
RUN mkdir -p config/jwt \
    && openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 \
    && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem

# Permissions
RUN mkdir -p var/cache var/log && chmod -R 777 var/

EXPOSE 8080

# Le serveur se lance directement
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]