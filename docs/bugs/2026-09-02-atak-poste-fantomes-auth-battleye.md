# Poste : opérateurs bloqués à l’authentification encore visibles

## Contexte

Poste `https://athena.ttrd.fr/public/atak`. Les opérateurs sont coincés à
l’écran « Authentification en cours » (protection anti-triche active :
pas de remontée de position). Pourtant le poste les montre encore, avec
d’anciens indicatifs superposés sur le relief.

## Symptôme

- Relief 3D : un point, deux libellés empilés (ex. YA… et N-10…).
- Onglet Terminaux : TA1 **En liaison** alors que la dernière activité
  date d’une heure ; YA1 / Bravo **Hors liaison** depuis deux heures.
- En jeu, personne n’est réellement en liaison.

## Cause

Le parc d’appareils garde le statut **actif** (appareil connu), distinct
d’un signal récent. Les fiches Terminaux prenaient ce statut pour
afficher **En liaison**. La carte classique masque déjà les contacts
sans signal ; la carte tactique et le relief dessinaient toutes les
dernières positions, y compris hors liaison, d’où les vieux indicatifs
au même endroit.

## Correctif

- Fiches Terminaux : **En liaison** seulement si un contact correspondant
  est encore reçu, ou si la dernière activité a moins de deux minutes.
- Carte tactique et relief : plus de symbole pour un opérateur hors
  liaison (sauf dernière position d’IA suivie). Libellé = indicatif
  affiché actuel, pas l’ancien.

## Fichiers touchés

- `public/assets/js/atak-terminals.js`
- `public/assets/js/map/atak-c2-bridge.js`
- `public/assets/js/atak-terrain3d-premium.js`
- `tests/Unit/AtakTerminalsAssetTest.php`
- `tests/Unit/AtakC2PlayerMarkerAppearanceAssetTest.php`
- `tests/Unit/AtakTerrain3dPremiumAssetTest.php`

## Vérification

Tests d’assets + UPDATE 367. Recharger le poste (Ctrl+F5). Sans signal
récent : fiches **Hors liaison**, plus de doubles indicatifs sur la
carte ni sur le relief.

## Statut

corrigé (recharger la page du poste)
