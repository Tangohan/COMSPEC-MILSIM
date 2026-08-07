# Alerte dashboards — recharger les images

## Contexte

Après la perte d’images au sync Git→FTP, les membres doivent redéposer leurs visuels.

## Symptôme

Photos de profil, bannières et logos absents ; besoin d’un rappel visible sur les tableaux de bord.

## Correctif

- Annonces ops injectées (`OpsDashboardNotices`) dans le fil d’alertes (tuiles + barre sous menu).
- Bandeau dédié masquable sur les dashboards (principal, effectifs, ATAK).
- CTA vers le compte ; lien logos communauté pour les admins.

## Fichiers touchés

- `app/Support/OpsDashboardNotices.php`
- `app/Services/Alerts/AlertPresentationService.php`
- `app/Controllers/Api/AlertDismissController.php`
- `views/partials/media_reupload_notice.php`
- `views/dashboard.php`, `dashboard_effectifs.php`, `dashboard_atak.php`
- `views/partials/dashboard_command_center.php`

## Vérification

- [ ] Connexion → bandeau « Merci de recharger vos images » sur le dashboard
- [ ] « J’ai compris » masque le bandeau (recharge : toujours masqué)
- [ ] Tuile / barre d’annonce présente tant que non dismissée côté alertes

## Statut

corrigé
