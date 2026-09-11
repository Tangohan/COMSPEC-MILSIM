# Superposition Indicatif / Nom / Rôle absente + groupe BFT non synchronisé

## Contexte

11 septembre 2026. Après rétablissement de la liaison Athena, la carte ATAK montre le symbole joueur sans les trois lignes d’identité. Le panneau Zeus « Données techniques » n’expose que l’identifiant de groupe (ex. Alpha 2-2) sans le synchroniser vers le suivi d’effectif.

## Symptôme

- Sur la carte : pas d’Indicatif / Nom / Rôle visibles (triplet IceMan masqué, cartouche COMSPEC absent).
- Identifiant de groupe Zeus non repris comme groupe BFT côté poste / effectifs.

## Cause

1. Le HUD carte masquait les trois cases IceMan (2620–2622) dès qu’un cartouche COMSPEC était créé ; si ce cartouche n’apparaissait pas, l’identité disparaissait entièrement.
2. Le libellé de groupe pour la remontée préférait indicatif · affectation et ignorait un identifiant Arma tactique déjà renseigné (Alpha 2-2).
3. Valider Zeus ne posait pas de variable de sync ni ne forçait une remontée de position.

## Correctif

- Remplir et laisser visibles les trois lignes IceMan : Indicatif / Nom / Rôle.
- Prioriser l’identifiant de groupe Arma (ou `COMSPEC_BftGroup`) pour le suivi d’effectif.
- À la validation Zeus : afficher Indicatif / Nom / Rôle, mémoriser le groupe BFT, appliquer l’identifiant, forcer une remontée.
- Colonne `atak_units.group_name` + écriture à chaque position.

## Fichiers touchés

- `mod/.../atak_athena/functions/fn_athena_fillIdentityOverlay.sqf`
- `mod/.../atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/.../atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/.../atak_athena/functions/fn_athena_relabelBft.sqf`
- `mod/.../connect/functions/fn_inGameGroupLabel.sqf`
- `mod/.../connect/functions/fn_fillZeusGroupId.sqf`
- `migrations/20260911200000_atak_units_group_name.sql`
- `app/Repositories/AtakDataRepository.php`

## Vérification

Tests d’assets. En jeu (Athena 1.0.83 · Overwatch 1.5.27) : carte = trois lignes bas-droite ; Zeus OK sur Alpha 2-2 → groupe repris dans Effectifs.

## Statut

corrigé (pack à rebuild)
