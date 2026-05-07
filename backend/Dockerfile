FROM dunglas/frankenphp:php8.5.5-bookworm

# Installation des extensions PHP nécessaires
RUN install-php-extensions pdo_mysql intl ctype iconv

# Copier le code source
COPY . /app
WORKDIR /app

# Installer les dépendances Composer (sans dev)
RUN composer install --optimize-autoloader --no-interaction --no-dev

# Nettoyer le cache et préparer assets (ignorer les erreurs si besoins)
RUN php bin/console cache:clear --env=prod --no-debug --no-warmup || true
RUN php bin/console assets:install public --symlink --relative --env=prod || true
RUN php bin/console importmap:install || true

# Générer les clés JWT si elles n'existent pas
RUN mkdir -p config/jwt && \
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 && \
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem

# Port exposé (Railway utilise 8080)
EXPOSE 8080

# Commande de démarrage (serveur PHP intégré)
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]