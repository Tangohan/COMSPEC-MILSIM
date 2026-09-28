# Noms de code des gros packs (villes / États US)

## Règle

Quand le **premier** chiffre de version change (`X` dans `X.Y.Z`) :

1. Choisir le prochain nom réservé dans `App\Support\PackCodenameCatalog` (ville ou État américain).
2. Renseigner `codename` dans `storage/app_version.json`.
3. L’afficher dans le changelog Steam (`Opération …`), la bannière « Nouveautés organisation », et le bulletin journal si besoin.
4. **Ne jamais réutiliser** un nom déjà attribué à un major précédent.

Les bumps de `Y` ou `Z` (ex. 1.6.13 → 1.6.14) restent sous le **même** nom de code.

## Ligne actuelle

| Major | Nom | Libellé |
| --- | --- | --- |
| 1 | Phoenix | Opération Phoenix |
| 2 | Denver | (réservé) |
| 3 | Austin | (réservé) |
| … | voir le catalogue PHP | |

## Checklist bump major (ex. 1.x → 2.0.0)

- [ ] Attribuer le nom réservé (`Denver` pour 2.x)
- [ ] Mettre à jour `storage/app_version.json` (`version`, `codename`, `pack.*`, `updated_at`)
- [ ] Changelog Steam : titre « Opération Denver — 2.0.0 »
- [ ] Rebuild PBO + bulletin DevDispatch
- [ ] Bannière back-office : vérifier le libellé « Opération … »
