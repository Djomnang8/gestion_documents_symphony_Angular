#!/bin/sh
set -e

# Nettoyer le cache de développement
php bin/console cache:clear --env=dev

# Générer les clés JWT si absentes
if [ ! -f config/jwt/private.pem ]; then
    mkdir -p config/jwt
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
fi

# Lancer le serveur PHP intégré
exec php -S 0.0.0.0:${PORT} -t public
