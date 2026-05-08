FROM php:8.4-cli

RUN apt-get update && apt-get install -y git unzip zip libicu-dev openssl \
    && docker-php-ext-install pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copie des fichiers de dépendances
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Copie du reste du code
COPY . /app

# Forcer l'installation de symfony/runtime (modifie composer.json si absent)
RUN composer require symfony/runtime --no-interaction --no-scripts --optimize-autoloader --update-no-dev

# Réinstaller proprement
RUN composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Générer les clés JWT (le cache:clear sera fait au démarrage)
RUN mkdir -p config/jwt \
    && openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 \
    && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem

RUN mkdir -p var/cache var/log && chmod -R 777 var/
RUN chmod +x start.sh

EXPOSE 8080
CMD ["./start.sh"]
