# Tableau de bord — bande claire au milieu

## Contexte

Tableau de bord membre (`/public/dashboard`). En-tête sombre, tuile Connexion Steam, alertes, articles organisateur, signalement d’anomalie.

## Symptôme

La page se lisait sombre en haut, puis une large zone claire (« Organisation / Rédiger un article ») coupait le tableau de bord, puis les cartes sombres réapparaissaient en bas (signaler une anomalie, contacter l’administration). La connexion Steam était collée au haut sombre au lieu de marquer la transition vers la zone claire.

## Cause

L’ordre du flux plaçait Steam et les alertes en haut, le bloc articles au milieu, et les tuiles sombres de signalement après la zone claire. Le fond clair du tableau de bord devenait alors une bande blanche entre deux zones sombres.

## Correctif

Le flux suit désormais : zone sombre (briefing, alertes, signalements), puis Connexion Steam si Steam n’est pas encore associé, puis la zone claire (articles et le reste de la page). Rien de sombre n’apparaît après les articles.

## Fichiers touchés

- `views/partials/dashboard_command_center.php`
- `public/assets/css/dashboard-impact.css`
- `tests/Unit/DashboardBandOrderAssetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE #386)

## Vérification

Test unitaire d’ordre des blocs : briefing et alertes, puis signalements, puis Steam, puis articles. Contrôle visuel : pas de bande claire au milieu, pas de cartes sombres sous les articles.

## Statut

Corrigé.
