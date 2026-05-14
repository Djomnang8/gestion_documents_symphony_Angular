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

echo "[start.sh] Création des répertoires uploads..."
mkdir -p public/uploads/citoyens public/uploads/dossiers
chmod -R 775 public/uploads

echo "[start.sh] Démarrage du serveur PHP sur le port ${PORT}..."
exec php \
  -d upload_max_filesize=50M \
  -d post_max_size=50M \
  -d memory_limit=256M \
  #-d default_socket_timeout=5 \
  -S 0.0.0.0:${PORT} \
  -t public
