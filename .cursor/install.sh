#!/usr/bin/env bash
# Bootstrap idempotent de l'environnement de dev Athena (COMSPEC-MILSIM) pour Cloud Agent.
# Rafraîchit les dépendances, construit les assets, prépare .env et la base de données.
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$PWD"

# --- 1. Outils système (installés si absents de l'image de base) ---
if ! command -v php >/dev/null 2>&1; then
  echo "[install] Installation de PHP 8.4 + MariaDB..."
  export DEBIAN_FRONTEND=noninteractive
  sudo apt-get update -qq
  # Prérequis pour ajouter le PPA ondrej/php (PHP 8.4 n'est pas dans les dépôts Ubuntu 24.04).
  sudo apt-get install -y --no-install-recommends software-properties-common ca-certificates curl gnupg
  sudo add-apt-repository -y ppa:ondrej/php
  sudo apt-get update -qq
  sudo apt-get install -y \
    php8.4-cli php8.4-common php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
    php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-gmp php8.4-sqlite3 \
    mariadb-server mariadb-client unzip
fi
if ! command -v composer >/dev/null 2>&1; then
  echo "[install] Installation de Composer..."
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
fi

# --- 2. Dépendances PHP ---
echo "[install] composer install..."
composer install --no-interaction --prefer-dist

# --- 3. Dépendances Node + assets ---
echo "[install] npm ci + build Tailwind..."
npm ci
npm run build:tailwind
if [ -f server/package.json ]; then
  ( cd server && npm ci )
fi

# --- 4. Dossiers storage ---
mkdir -p storage/logs storage/cache storage/sessions storage/uploads storage/mail-outbox

# --- 5. .env de développement ---
if [ ! -f .env ]; then
  echo "[install] Création de .env (profil développement local)..."
  cp .env.example .env
  JWT="$(php -r 'echo bin2hex(random_bytes(32));')"
  php -r '
    $f = ".env";
    $c = file_get_contents($f);
    $repl = [
      "/^APP_ENV=.*$/m"              => "APP_ENV=local",
      "/^APP_DEBUG=.*$/m"            => "APP_DEBUG=true",
      "#^APP_URL=.*$#m"              => "APP_URL=http://localhost:8000",
      "/^APP_BASE_PATH=.*$/m"        => "APP_BASE_PATH=",
      "/^SESSION_SECURE_COOKIE=.*$/m"=> "SESSION_SECURE_COOKIE=false",
      "/^DB_HOST=.*$/m"              => "DB_HOST=127.0.0.1",
      "/^DB_NAME=.*$/m"              => "DB_NAME=athena",
      "/^DB_USER=.*$/m"              => "DB_USER=root",
      "/^DB_PASSWORD=.*$/m"          => "DB_PASSWORD=athena_dev",
      "/^JWT_SECRET=.*$/m"           => "JWT_SECRET=" . getenv("JWT"),
    ];
    foreach ($repl as $re => $to) { $c = preg_replace($re, $to, $c); }
    file_put_contents($f, $c);
  '
fi

# --- 6. Base de données : démarrage + schéma + migrations + seed (idempotent) ---
bash .cursor/dev-db.sh
echo "[install] setup-database.php (schéma + migrations + seed)..."
php setup-database.php

echo "[install] Terminé. Admin par défaut : admin@athena.local / admin (OTP e-mail dans storage/mail-outbox)."
