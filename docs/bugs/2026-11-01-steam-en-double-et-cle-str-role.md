# Steam en double sur deux comptes Athena, clé « STR_role_… » affichée brute

## Contexte

Parc ATAK (administration du site) : le joueur U-00231 affiche « Steam en double », « 2 fiches membres »,
« Jeu refusé : Steam en double », et sous son nom « STR_role_default_commander_role_name · Auxiliaire sanitaire ».

## Symptôme

1. Un même identifiant Steam est porté par les fiches de deux personnes (deux e-mails, donc deux comptes Athena).
   Le joueur n'est prévenu nulle part ; le parc ATAK compte aussi comme « doublon » une même personne inscrite
   dans deux communautés (même e-mail), ce qui n'en est pas un.
2. Le rôle de slot Arma s'affiche sous forme de clé de traduction brute.

## Cause

1. Doublons créés sans preuve : la page Préférences accepte n'importe quel Steam saisi à la main, et la liaison
   Steam (connexion Steam) ne vérifiait que la communauté courante. Côté jeu, `GameAuthService::steamExchange`
   ne refusait pas : `UserRepository::findBySteamId` prenait la fiche active la plus récente, donc parfois le
   compte d'une autre personne. Le parc ATAK comptait les fiches (`count($steamUsers) > 1`), pas les personnes.
2. `roleDescription` renvoie la clé `STR_…` quand la mission ne fournit pas sa traduction ; le site la stockait
   (`operator_game_profiles.role`, `atak_screen_time.item_label`) et l'affichait telle quelle.

## Correctif

- `SteamConflictService` : doublon = fiches vivantes de personnes différentes (e-mails distincts).
- Page imposée `/account/steam-doublon` (`AuthMiddleware::steamConflictStep`) à chaque page du site tant que ce
  n'est pas réglé ; trois choix : garder ici avec preuve Steam (connexion Steam), garder l'autre compte (Steam
  retiré de ses propres fiches puis déconnexion), « je ne reconnais pas l'autre compte » (Steam mis de côté sur
  ses fiches, signalement `steam_conflict_reports`). Audit à chaque étape, avis à l'autre compte.
- Jeu : `steamExchange` et `/api/atak/game-link/by-steam` refusent un Steam porté par deux personnes
  (`STEAM_NOT_LINKED`, raison `steam_duplicate`) : le mod repasse par l'e-mail ou le code.
- Préférences : un Steam déjà porté par une autre personne n'est plus accepté par simple saisie.
- Administration : « Résoudre » dans le parc ATAK et le dossier personne → `/admin/steam-doublons`.
- `ArmaText::label()` / `roleLabel()` : clés `STR_…` traduites (« Commandant ») ou réduites, à l'écriture et à
  l'affichage (parc ATAK, fiche ATAK, temps par rôle, débriefing, kits, bilan annuel, profil en jeu).

## Fichiers touchés

`app/Services/Account/SteamConflictService.php`, `app/Controllers/Web/SteamConflictController.php`,
`app/Controllers/Admin/AdminSteamConflictController.php`, `app/Middleware/AuthMiddleware.php`,
`app/Controllers/Web/AccountController.php`, `app/Services/Game/GameAuthService.php`,
`app/Controllers/Api/AtakApiController.php`, `app/Support/AtakFleetLink.php`, `app/Support/ArmaText.php`,
`bootstrap/steam_conflict_reports_migration.php` (table créée à la première requête), vues `account/steam_conflict.php`, `admin/steam-conflicts/index.php`.

## Vérification

`tests/Unit/SteamConflictTest.php` (détection, page imposée, choix a avec et sans preuve, b, c, audit, clé STR),
`tests/Unit/AtakAdminFleetTest.php`. À tester en jeu : connexion par Steam d'un Steam en double (refus puis
connexion e-mail/code), puis connexion par Steam après le choix « garder ici ».

## Statut

Corrigé (en attente de test en jeu).
