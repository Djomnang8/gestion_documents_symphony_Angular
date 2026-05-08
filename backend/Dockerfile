FROM php:8.4-cli

# System dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip zip libicu-dev openssl \
    && docker-php-ext-install pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copy only dependency files first (layer cache optimization)
COPY composer.json composer.lock ./

# Install PHP dependencies at BUILD TIME (not startup)
RUN composer install --optimize-autoloader --no-interaction --no-dev --no-scripts

# Copy the rest of the application
COPY . /app

# Ensure Symfony can write to var/
RUN mkdir -p var/cache var/log && chmod -R 777 var/

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
