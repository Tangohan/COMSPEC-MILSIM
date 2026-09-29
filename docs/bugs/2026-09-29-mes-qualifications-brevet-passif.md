# Mes qualifications — brevet PDF sans action + validité absente

## Contexte

Page opérateur `Mes qualifications` (`/back-office/ma-situation/qualifications`).

## Symptôme

- Notice « Brevet PDF pas encore versé au dossier » sans bouton de génération.
- Icône « RH » identique sur toutes les cartes.
- Pas d’info d’expiration / grâce / permanente.
- Libellé d’émetteur générique « Communauté ».
- Aucune action propre à la carte (télécharger / détail).

## Cause

La génération de brevet existait côté référentiel admin, mais n’était pas exposée à l’opérateur. La vue n’utilisait ni `badge_media_path`, ni `QualificationTemporalStatusService`, ni `issuer_name` de façon utile.

## Correctif

- Route POST `…/qualifications/{awardId}/generer-brevet` (propriétaire uniquement, qualification obtenue).
- Carte : Générer le brevet / Télécharger le brevet / Voir le détail.
- Badge réel ou initiales de catégorie.
- Validité temporelle (jours restants, grâce, permanente).
- Émetteur réel + catégorie ; compteur au-dessus de la liste ; eyebrow aligné sur « Mes qualifications ».

## Fichiers touchés

- `app/Controllers/Admin/Organization/MemberSituationController.php`
- `views/admin/member_situation/qualifications.php`
- `routes/web.php`
- `public/assets/css/back-office-member-situation.css`
- `tests/Unit/MemberSituationBackOfficeAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE #734)

## Vérification

- Syntaxe PHP contrôleur.
- Assertions asset : `generateBrevet`, `Générer le brevet`, absence de la notice passive.
- Contrôle manuel : Ctrl+F5 puis génération d’un brevet sur une qualification obtenue.

## Statut

Corrigé.
