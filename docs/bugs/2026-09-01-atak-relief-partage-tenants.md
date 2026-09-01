# Relief et ombrage d’un théâtre non partagés entre communautés

## Contexte

Deux comptes, deux communautés, même théâtre (Altis) sur la carte du poste. L’une a déjà relevé le sol ; l’autre ouvre le même île.

## Symptôme

- Communauté A : ombrage et relevé du sol « Présent — couverture 99 % », milliers de bâtiments et de forêts.
- Communauté B : « Pas encore sur le poste » pour l’ombrage et le relevé du sol, quelques centaines de volumes seulement.
- Les positions et les notes restent bien séparées (attendu). Le sol du théâtre, lui, devrait être le même pour tout le monde.

## Cause

Le poste rangeait le relief, l’ombrage et les volumes du jeu **par communauté**, pas par théâtre. Un relevé déjà fait sur Altis par une communauté n’était pas lu par les autres.

## Correctif

- Lecture du sol : on prend le relevé le plus complet déjà connu pour ce théâtre.
- Un nouveau relevé s’ajoute à cette même grille, au lieu d’en créer une vide à côté.
- Bâtiments et forêts : décompte et affichage pour le théâtre, sans doublon.
- Fichiers d’ombrage : un dossier commun par théâtre, plus un dossier par communauté.

Les positions, les notes et les effectifs restent propres à chaque communauté.

## Fichiers touchés

- `app/Repositories/AtakTerrainRepository.php`
- `app/Repositories/AtakSceneObjectRepository.php`
- `app/Services/Tactical/AtakTerrainCartography.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 322)
- `tests/Unit/AtakTerrainSharedMapTest.php`
- `tests/Unit/AtakSceneObjectCountTest.php`
- `tests/Unit/AtakSceneIngestAssetTest.php`
- `tests/Unit/DevDispatchCatalogTest.php`

## Vérification

Tests unitaires : une communauté avec 99 % de sol, l’autre lit les mêmes chiffres ; bâtiments dédupliqués sur le théâtre. Recette : recharger `/public/atak` sur la seconde communauté, même Altis — ombrage et totaux doivent coller à la première. Pas de nouveau pack jeu.

## Statut

corrigé (sources) — recharger la carte du poste
