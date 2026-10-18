# Noms de version du portail (villes US)

## Règle (depuis 1.7.0)

Chaque nouvelle version **X.Y** du portail Athena (`storage/app_version.json`) porte un nom de ville américaine :

1. Prendre le premier nom de `RESERVED` dans `App\Support\PackCodenameCatalog` et le déplacer dans `ASSIGNED` sous la clé `X.Y`.
2. Mettre à jour `storage/app_version.json` (`version`, `codename`, `updated_at`).
3. Ajouter un bulletin UPDATE dans `DevDispatchCatalog` (« Portail Athena X.Y.0 « Ville » »).
4. **Ne jamais réutiliser** un nom déjà attribué.

Les correctifs **X.Y.Z** gardent le nom de leur version. Toutes les versions antérieures à 1.7 restent « Phoenix ».

Le nom s’affiche seul (ex. « Denver »), sans « Opération », à côté du numéro dans la bannière « Production en ligne » (Nouveautés de l’organisation).

Les versions de COMSPEC ATAK et COMSPEC Link de la bannière sont lues dans `mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp` : rien à retoucher côté site à chaque release du mod. Leur numérotation suit `docs/VERSIONING.md`.

## Versions nommées

| Version | Ville | État |
| --- | --- | --- |
| ≤ 1.6 | Phoenix | Arizona |
| 1.7 | Denver | Colorado |
| 1.8 | Austin | Texas (réservé) |
| … | voir le catalogue PHP | |
