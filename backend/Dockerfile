FROM php:8.5-cli

# Installer les dépendances système et les extensions PHP nécessaires
RUN apt-get update && apt-get install -y git unzip zip libicu-dev && \
    docker-php-ext-install pdo_mysql intl

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Définir le répertoire de travail
WORKDIR /app

# Copier le code source
COPY . /app

# Exposer le port 8080 (celui utilisé par Railway)
EXPOSE 8080

# Commande par défaut (le serveur sera démarré par le Start Command de Railway)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
