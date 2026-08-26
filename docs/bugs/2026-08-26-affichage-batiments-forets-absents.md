# Affichage — bâtiments et forêts « pas encore sur le poste » alors qu’ils sont en base

## Contexte

Poste Athena, communauté 7, carte Altis (carte 1). Le relief est bien relevé (couverture 99 %). La table des volumes de théâtre contient 34 756 lignes (bâtiments et forêts), mises à jour le 26/08/2026 vers 19:53–20:00. Dans Affichage, l’ombrage et le relevé divers s’affichent « Présent · couverture 99 % », mais bâtiments et forêts restent « Pas encore sur le poste ».

## Symptôme

- En-tête : « Données terrain — couverture 99 % · Altis »
- Ombrage / relevé divers : présents
- Bâtiments / forêts : « Pas encore sur le poste »
- Dernier relevé : 26/08/2026 20:02 (heure du relief, plus récente que les volumes)

La case « Bâtiments et forêts du jeu » n’est pas en cause : c’est un choix d’affichage 3D, pas l’inventaire.

## Cause

1. **Le bandeau 99 % ne vient pas du compteur.** La couverture du relief est lue à part. Affichage s’en sert pour l’ombrage même si le décompte des volumes échoue.
2. **Un décompte manquant était traité comme zéro.** Si le poste ne répond pas (ancienne version, refus temporaire) ou s’il omet les totaux bâtiments / forêts, l’écran affichait « pas encore sur le poste » — comme si le relevé n’était pas arrivé.
3. **Un incident SQL était avalé en zéros.** Le comptage renvoyait 0 dès qu’une requête échouait, y compris pour autre chose qu’une table vraiment absente.

La communauté 7 et la carte 1 n’étaient pas exclues : c’est bien Altis. Les volumes y sont. C’est le chemin d’affichage du compteur qui mentait.

## Correctif

- Le poste compte les volumes déjà reçus, par type, pour la communauté et la carte en cours.
- Table absente : zéro réel (« pas encore sur le poste »).
- Table remplie : les nombres s’affichent (ex. 12 345 bâtiments, 22 411 forêts).
- Si le décompte n’est pas lisible : « Compte indisponible, réessayez » (refus temporaire) ou « Le décompte n’est pas encore disponible » (poste trop ancien) — plus de faux absent.
- La date du dernier relevé prend le plus récent entre relief et volumes.

## Fichiers touchés

- `app/Repositories/AtakSceneObjectRepository.php`
- `app/Controllers/Api/AtakSceneApiController.php`
- `public/assets/js/atak-terrain.js`
- `app/Support/DevDispatchCatalog.php` (UPDATE 237)
- `tests/Unit/AtakSceneObjectCountTest.php`
- `tests/Unit/AtakTerrainCoverageStatusAssetTest.php`
- `tests/Unit/DevDispatchCatalogTest.php`

## Vérification

- Tests unitaires : comptage par type pour la communauté 7 / carte 1 ; zéro seulement si la table manque ; l’écran distingue zéro, indisponible et poste trop ancien.
- Recette : ouvrir Affichage sur Altis après un relevé déjà en base — les bâtiments et forêts s’affichent en nombre, pas « pas encore sur le poste ».

## Statut

corrigé (sources) — recharger la carte du poste ; pas de nouveau pack jeu
