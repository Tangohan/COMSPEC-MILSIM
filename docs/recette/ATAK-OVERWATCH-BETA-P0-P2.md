# Recette ATAK Overwatch Beta — P0, P1, P2

## Recette automatisée

La commande suivante valide hors ligne les contrats P0/P1/P2 :

```bash
scripts/qa/atak-overwatch-beta-recipe.sh
```

Pour compléter avec la recette HTTP réelle, fournir l'URL de l'environnement et
un cookie jar `curl` d'un compte de recette autorisé :

```bash
ATAK_RECIPE_BASE_URL='https://athena.example.test' \
ATAK_RECIPE_COOKIE_JAR='/chemin/cookies.txt' \
  scripts/qa/atak-overwatch-beta-recipe.sh
```

Le script vérifie alors :

1. refus du stream sans authentification ;
2. chargement de la vraie page Overwatch sans contacts de démonstration ;
3. MIME SSE, heartbeat et snapshots `units`, `chat`, `alerts` avec la session ;
4. contrats statiques du fallback polling, du canal Support et des imports P2.

Aucun identifiant ni cookie ne doit être ajouté au dépôt. Le cookie jar reste un
fichier local éphémère.

## Recette manuelle COMSPEC

| Cas | Action | Résultat attendu |
|---|---|---|
| Liaison | Ouvrir Overwatch puis démarrer COMSPEC | `LINKED`, latence et dernier RX évoluent. |
| Coupure SSE | Bloquer `/api/atak/stream` | État `POLLING`, roster conservé, fréquence opérateur restaurée. |
| Reconnexion | Rétablir le stream | Retour `LINKED`, polling de sécurité à 30 s. |
| RBAC | Comparer deux comptes de fonctions différentes | Les réponses et actions restent celles autorisées par le serveur. |
| Chat | Envoyer dans Général puis Support technique | Historique persistant et canaux visuellement séparés. |
| P2 export | Ajouter AOI/tracé puis exporter | JSON versionné contenant snapshot unités et tracés. |
| P2 import | Importer le JSON sur une carte de recette | Confirmation, tracés recréés après validation serveur, aucune unité injectée. |
| P2 limites | Importer >2 Mio ou >50 tracés | Refus local avant écriture. |
| Impression | Cliquer IMPRIMER | Carte/annotations visibles, contrôles opérationnels masqués, attribution conservée. |
| Analyse | Ouvrir ROUTES, REPLAY et JOURNAL | ETA pied/véhicule, profil/LOS, historique et AAR accessibles. |

## Critère de clôture

La PR est fusionnable lorsque la recette automatisée live termine par
`OK recette Overwatch Beta P0/P1/P2 terminée` et que les cas COMSPEC/RBAC ont été
signés sur l'environnement cible. Une exécution hors ligne seule ne valide pas
PHP-FPM, le proxy, les données mission ni les rôles réels.
