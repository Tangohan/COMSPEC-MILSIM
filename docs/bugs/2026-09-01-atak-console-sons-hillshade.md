# Console ATAK — sons et ombrage du relief

## Contexte

Console Edge sur la page ATAK du poste : échec de chargement des sons d’ordre / d’accusé, intervention « images chargées en différé », et ombrage du relief en erreur serveur.

## Symptôme

- Les sons d’ordre et d’accusé ne se jouent pas ; la console affiche un échec de chargement.
- L’ombrage du sol du théâtre ne s’affiche pas ; la requête correspondante échoue (erreur serveur).
- Avertissement navigateur sur des images chargées trop tard (tuiles et overlay).

## Cause

1. **Sons** : au premier geste, tous les extraits d’alerte étaient lancés en même temps. Le service de pages interceptait aussi les fichiers audio du dossier des sons, ce que le navigateur refuse pour les médias. Quatre extraits cités n’existaient pas sur le poste.
2. **Ombrage** : le dessin du relief sur une grille trop grande (ou une erreur interne) n’était pas rattrapé : la carte recevait une erreur serveur au lieu d’un simple « pas encore prêt ».
3. **Images** : Edge remplace les images sans chargement immédiat ; les tuiles Leaflet n’étaient pas marquées comme à afficher tout de suite.

## Correctif

- Ne débloquer qu’un son au premier geste ; charger les autres au moment où ils servent.
- Ne plus intercepter les fichiers audio ; déclarer le type son pour le serveur web.
- Pointer les événements manquants vers des extraits déjà présents.
- Dessiner l’ombrage en taille plafonnée ; en cas d’échec, répondre « non prêt » sans faire tomber la carte.
- Marquer tuiles et overlay comme à charger immédiatement.

## Fichiers touchés

- `public/sw.js`
- `public/.htaccess`
- `public/assets/js/atak-sounds.js`
- `public/assets/js/atak-terrain.js`
- `public/assets/js/atak-map.js`
- `app/Services/Tactical/AtakTerrainCartography.php`
- `app/Controllers/Api/AtakTerrainApiController.php`

## Vérification

- Tests unitaires : service de pages, sons, ombrage, catalogue UPDATE 341.
- Recharger ATAK (une fois, pour le service de pages) : plus d’échec sur les sons d’ordre ; si l’ombrage n’est pas prêt, la carte reste visible.

## Statut

corrigé
