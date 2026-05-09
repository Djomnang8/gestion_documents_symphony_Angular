FROM php:8.4-cli

# System dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip zip libicu-dev openssl \
    && docker-php-ext-install pdo_mysql intl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copy source code
COPY . /app

# Install dependencies (--no-scripts retiré : nécessaire pour symfony/runtime)
RUN rm -rf vendor && composer install --optimize-autoloader --no-interaction

# Generate JWT keys
RUN mkdir -p config/jwt \
    && openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 \
    && openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem

# Ensure Symfony can write to var/
RUN mkdir -p var/cache var/log && chmod -R 777 var/

RUN chmod +x start.sh

EXPOSE 8080

CMD ["./start.sh"]
