#!/usr/bin/env bash
# Réconciliation par démarrage : garantit que MariaDB tourne avant les terminaux (serveur PHP).
set -euo pipefail
cd "$(dirname "$0")/.."
bash .cursor/dev-db.sh
