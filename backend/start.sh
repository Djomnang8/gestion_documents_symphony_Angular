#!/bin/sh
set -e

echo "[start.sh] Nettoyage du cache stale..."
rm -rf var/cache/*

echo "[start.sh] Vérification des clés JWT..."
if [ ! -f config/jwt/private.pem ]; then
    mkdir -p config/jwt
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
    echo "[start.sh] Clés JWT générées."
fi

echo "[start.sh] Génération du cache Symfony pour APP_ENV=${APP_ENV:-dev}..."
php bin/console cache:clear --env=${APP_ENV:-dev} --no-warmup

echo "[start.sh] Démarrage du serveur PHP sur le port ${PORT}..."
exec php -S 0.0.0.0:${PORT} -t public