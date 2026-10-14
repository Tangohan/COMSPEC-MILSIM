# Accès refusé par Athena (« unauthorized », « Session refusée ») — diagnostic et e-mail au joueur

**Date :** 2026-10-14
**Statut :** corrigé côté site (à vérifier en jeu)

## Contexte

Rapports automatiques sur /admin/atak-mod-reports, joueurs liés (Mod 1.6.16, Link 2.0.84) :
`ÉCHEC · fiche opérateur — unauthorized` (`["ERROR","unauthorized"]`) puis
`Session refusée par le poste — transmissions arrêtées`.

## Ce que dit le code

- La DLL renvoie `unauthorized` pour un 401 **ou** un 403 d’Athena, ou si elle n’a ni clé ni jeton
  (`HandleOperatorProfile`, Extension.cs). Après un 401, elle rejoue client-init ; si client-init est
  aussi refusé, elle coupe la liaison (`AuthInvalidated` → « Session refusée par le poste »).
- Côté Athena (`ComspecApiKeyAuth::requestPresentsValidKey`), une requête passe avec le jeton jeu
  (Bearer) **ou** la clé de communauté (X-COMSPEC-KEY). Un refus veut donc dire : jeton refusé
  **et** clé absente ou changée.
- Le jeton jeu est écrit en UTC (`gmdate(time() + 7200)`) mais relu par `strtotime()` sans fuseau,
  donc à l’heure du serveur PHP. Avec `date.timezone = Europe/Paris` (cf. le même défaut corrigé sur
  les codes d’appairage, `GameAtakPairingService::isExpiredRow`), un jeton de 2 h paraît expiré dès sa
  création en heure d’été : seuls les joueurs dont la clé de communauté est valable passent encore.
  Même défaut sur l’OTP (10 min, expiré d’office) et sur la date d’expiration renvoyée au mod.

## Correctif

- `App\Support\UtcDateTime` : lecture des dates UTC (`… UTC`) dans `GameAuthService` (OTP, refresh,
  `sessionFromBearer`, expiration renvoyée) et `ComspecApiKeyAuth::acceptGameSessionToken`.
- À la réception d’un rapport automatique d’accès refusé (`AtakAuthFailureNotifier`) : diagnostic
  serveur ajouté au détail du rapport (pas de session jeu, session révoquée, session de 30 jours
  terminée, jeton expiré non renouvelé, session valable, compte désactivé, pas de clé de communauté).
- E-mail automatique au joueur dont le Steam est lié (FR, traduit en anglais si son profil est en
  anglais) : cause et marche à suivre (mise à jour du mod, relancer Arma, Échap > Connexion Athena,
  Échap > Dépannage liaison, clé d’accès auprès du gestionnaire). Au plus un e-mail par joueur et
  par jour (table `atak_player_auto_notices`). L’e-mail est gardé comme réponse sur le rapport.

## Vérification

- Tests : `tests/Unit/AtakAuthFailureNotifierTest.php`.
- Sur le VPS : `php -r 'echo date_default_timezone_get();'` (CLI) et `php -i | grep date.timezone`
  côté FPM pour confirmer le fuseau. Les prochains rapports « unauthorized » affichent la ligne
  « Diagnostic Athena : … » dans le détail technique.
