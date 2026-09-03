# Bureau effectifs — alerte et totaux illisibles sur fond sombre

## Contexte

Annuaire du bureau effectifs (`back-office/ressources/effectifs`), thème sombre LMS.

## Symptôme

Le bandeau des fiches jumelles apparaissait en jaune crème, les totaux en texte bleu nuit, les filtres et la barre d’actions en blanc. Les mentions « Fonction manquante » / « Sans unité » n’étaient qu’un texte or. L’affectation affichait toute la hiérarchie sur une ligne.

## Cause

Le tableur a reçu une couche sombre (`.eff-catalog--dark`) sans retirer les blocs clairs (Tailwind ambre, couleurs inline `#0f172a` / `#f8fafc`).

## Correctif

Bandeau ambre sombre, totaux blancs cliquables, champs et barre d’actions dans le même registre, pastilles d’alerte, unité = dernier maillon.

## Fichiers touchés

- `views/admin/effectifs_workspace/roster.php`
- `public/assets/css/effectifs_lms.css`
- `tests/Unit/EffectifsRosterDarkUiAssetTest.php`

## Vérification

Relecture HTML/CSS : plus de bandeau `bg-amber-50` ni de totaux `#0f172a`. Classes `.eff-banner`, `.eff-metrics--roster`, `.eff-bulkbar`.

## Statut

Corrigé (à déployer).
