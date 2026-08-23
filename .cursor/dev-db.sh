#!/usr/bin/env bash
# Démarrage + provisionnement idempotent de MariaDB pour l'environnement de dev Cloud Agent.
# - initialise le répertoire de données si besoin ;
# - démarre le démon s'il ne tourne pas ;
# - crée la base `athena` et l'accès root TCP attendu par .env.
set -euo pipefail

DB_NAME="${DB_NAME:-athena}"
DB_PASSWORD="${DB_PASSWORD:-athena_dev}"

# Config : accès root en TCP (skip-name-resolve pour que 127.0.0.1 ne retombe pas sur le socket).
CONF=/etc/mysql/mariadb.conf.d/99-cloud-agent.cnf
if [ ! -f "$CONF" ]; then
  printf '[mysqld]\nskip-name-resolve\nbind-address=0.0.0.0\n' | sudo tee "$CONF" >/dev/null
fi

sudo mkdir -p /run/mysqld /var/lib/mysql
sudo chown -R mysql:mysql /run/mysqld /var/lib/mysql

if [ ! -d /var/lib/mysql/mysql ]; then
  echo "[dev-db] Initialisation du répertoire de données MariaDB..."
  sudo mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null
fi

# Démarrer le démon s'il n'écoute pas déjà.
if ! sudo mariadb-admin ping >/dev/null 2>&1; then
  echo "[dev-db] Démarrage de mariadbd..."
  sudo -b mariadbd --user=mysql >/tmp/mariadb.log 2>&1
  for _ in $(seq 1 30); do
    sudo mariadb-admin ping >/dev/null 2>&1 && break
    sleep 1
  done
fi
sudo mariadb-admin ping >/dev/null 2>&1 || { echo "[dev-db] MariaDB introuvable après démarrage"; cat /tmp/mariadb.log; exit 1; }

# Base + utilisateur root TCP (idempotent).
sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER 'root'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

echo "[dev-db] MariaDB prêt (base '${DB_NAME}')."
