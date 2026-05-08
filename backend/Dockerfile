FROM php:8.4-cli

RUN apt-get update && apt-get install -y git unzip zip libicu-dev openssl \
    && docker-php-ext-install pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

COPY . /app

# Relancer l'installation au cas où le dossier vendor aurait été écrasé
RUN rm -rf vendor && composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Assurer les droits
RUN mkdir -p var/cache var/log && chmod -R 777 var/

# Donner les droits d'exécution au script de démarrage
RUN chmod +x start.sh

EXPOSE 8080

CMD ["./start.sh"]
