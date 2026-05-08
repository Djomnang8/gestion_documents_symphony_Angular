FROM php:8.4-cli

RUN apt-get update && apt-get install -y git unzip zip libicu-dev openssl \
    && docker-php-ext-install pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

COPY . /app

# Supprimer l'éventuel dossier vendor et relancer l'installation
RUN rm -rf vendor && composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Forcer l'installation du composant Runtime (au cas où)
RUN composer require symfony/runtime --no-interaction --no-scripts --optimize-autoloader

RUN mkdir -p var/cache var/log && chmod -R 777 var/
RUN chmod +x start.sh

EXPOSE 8080
CMD ["./start.sh"]
