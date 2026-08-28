# Carte vide après avoir rejoint une deuxième organisation

## Contexte

Un compte appartient à deux communautés. Après avoir rejoint la seconde, le poste ATAK reste vide (0 contact, liaison « en attente ») alors qu’un membre de la soirée voit bien l’opérateur.

## Symptôme

Carte vide, filtre « En liaison » sans personne, pastille Liaison en attente. L’autre poste (déjà sur la bonne communauté) affiche les positions.

## Cause

Le poste n’affiche que la **communauté active** de la session. Rejoindre une organisation n’y bascule pas toujours ; le jeu envoie les positions vers la communauté de la soirée, le navigateur continue de lire l’ancienne.

## Correctif

Nom de la communauté en tête du poste ; liste pour changer et rester sur la carte. Message d’aide si le compte a plusieurs organisations et qu’aucun contact n’apparaît.

Complément : le tableau de bord affiche « Vous êtes sur » + le nom actuel ; un clic sur une autre communauté ouvre le poste (plus seulement le tableau de bord). Les positions envoyées par le jeu restent visibles même si le théâtre à l’écran n’est pas la carte n°1.

## Fichiers touchés

- `app/Controllers/Web/AtakController.php`
- `app/Controllers/Web/CommunityController.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Repositories/UserRepository.php`
- `views/atak.php`
- `views/partials/dashboard_command_center.php`
- `public/assets/js/atak-units.js`
- `public/assets/css/atak.css`
- `public/assets/css/dashboard-impact.css`

## Vérification

En haut du poste : libellé « Communauté ». Avec deux organisations, changer la liste recharge la carte de l’autre. Sur le tableau de bord : pastille verte du nom actuel, clic sur une autre ouvre le poste. Tests `AtakCommunitySwitchAssetTest`.

## Statut

corrigé — déployer le portail (pas de pack jeu)
