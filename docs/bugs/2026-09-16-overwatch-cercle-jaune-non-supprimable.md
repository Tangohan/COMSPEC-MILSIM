# Cercle jaune impossible à supprimer (Overwatch Beta)

## Contexte

Sur la carte du poste, un petit anneau jaune à centre sombre restait collé au théâtre. Clic droit : pas de **Supprimer**.

## Symptôme

Pastille jaune isolée, surtout près des hangars / pistes, sans libellé. L’opérateur ne sait pas ce que c’est et ne peut pas la retirer.

## Cause

Le dessin correspond au **relais ATAK** (anneau doré, fond sombre). Ces points viennent du jeu, le calque « Relais ATAK » est coché par défaut, et ils n’étaient pas branchés sur le menu contextuel ni sur les calques.

Même type de pastille : repère rapide et compte rendu, posés sans être enregistrés comme élément supprimable.

## Correctif

- Libellé **Relais** à côté du point, infobulle au survol.
- Clic droit → Supprimer retire le relais du poste.
- Calques : liste des relais avec Retirer.
- Repères rapides et comptes rendus : même menu Supprimer.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/atak-overwatch-ops.js`
- `public/assets/css/atak-overwatch-beta.css`
- `app/Repositories/AtakRelayRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `routes/web.php`

## Vérification

Clic droit sur le point jaune : le menu propose **Supprimer** et le libellé Relais. Après retrait, la pastille disparaît.

## Statut

corrigé
