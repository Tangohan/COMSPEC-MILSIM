# Page Athena : « Serveur Athena injoignable » alors que le compte est connecté, et 401 en boucle

## Contexte
ATAK 2.9.28, COMSPEC Link 2.0.114, partie multijoueur (mission `__cur_mp`). Capture du joueur : app Athena du téléphone
« Connecté en tant que TA1 … canal du poste ouvert », mais « Reconnexion impossible : voir les étapes de la liaison »
et l'étape « Serveur Athena : injoignable — Connexion internet du jeu coupée, ou pare-feu » (5 étapes sur 6).
Journaux nginx du site (12:00–12:20) : aucune 5xx, mais beaucoup de 401 sur les envois du jeu
(`/api/atak/telemetry/batch` 437×401 pour 155×200, `flight-manifest` 288, `vehicles` 282, `position` 128, `marker` 103),
alors que les lectures (ordres, zones, points de passage) et les journaux d'appareil répondent 200.

## Symptôme
- Le téléphone annonce le serveur injoignable et l'échec de la reconnexion alors que la session est ouverte.
- Le journal du jeu note « Distance.Batch refusé (COMSPEC Link requis) » alors que COMSPEC Link est là.
- Côté site, des centaines de 401 par joueur et par mission.

## Cause
1. **État d'erreur jamais remis à zéro** : au lancement, le joueur n'avait pas encore de téléphone ATAK sur lui. La coupure
   centrale des envois (`connect/functions/fn_uplinkHold.sqf`) bloquait aussi la demande de code de connexion
   (`PairStart` / `PairStatus`, absents de la liste « connexion au compte ») : le RPT montre
   « Code de connexion impossible : uplink_hold » à 12:10:14. `fn_athenaLink` gardait l'état `["error", …, "uplink_hold"]`
   pour toute la partie, et `fn_athenaStatus.sqf:197` (`_netKo`) le classait « injoignable » même après la connexion
   par Steam (session READY).
2. **« Reconnexion impossible » à tort** : `fn_connect` (connect) met la liaison « offline / Compte non lié » dès qu'il
   n'y a pas de clé de communauté, alors qu'une session de jeu (code, Steam, e-mail) est ouverte ; `fn_athenaAction`
   « reconnect » ne regardait que cet état.
3. **401 en boucle** : COMSPEC Link envoyait les lots de télémétrie, positions, marqueurs, véhicules et manifestes même sans
   rien pour s'authentifier (ni clé de communauté, ni jeton de jeu valable : avant la connexion, hôte ou serveur sans
   compte, jeton périmé sans clé). `AttachApiKeyHeader` n'attachait alors aucun en-tête, `authArma()` du site répondait
   401, et `MaybeReauthAfter401` ne faisait rien faute d'identifiants : nouvel essai toutes les 2 s. Les lectures, elles,
   attendaient déjà une session (`HasPortalAuth`). Le site se comporte correctement (refus d'un envoi non authentifié).
4. **Faux « COMSPEC Link requis »** : `fn_distanceServer` prenait la réponse vide de la coupure centrale (pas de téléphone)
   pour une DLL absente.

## Correctif
- `connect/functions/fn_uplinkHold.sqf` : `PairStart` et `PairStatus` passent comme les autres commandes de connexion au compte.
- `connect/functions/fn_connect.sqf` : sans clé de communauté mais session READY, la liaison est « établie ».
- `functions/network/fn_athenaStatus.sqf` : un échec de demande de code est ignoré dès que la session est ouverte ;
  « injoignable » seulement sans session (sinon « réponses irrégulières ») ; demande coupée faute de téléphone dite telle
  quelle ; nouvelle étape « Envois vers Athena » (dernier refus de COMSPEC Link, `GetLastPostError`, relu toutes les 5 s) :
  « non autorisé : positions et état du téléphone » pour un 401, distinct de « sans réponse » (réseau) et de « ralentis ».
- `functions/network/fn_athenaLink.sqf` (« watch », toutes les 10 s) : l'état d'erreur du code est effacé une fois connecté.
- `functions/pages/fn_athenaAction.sqf` : reconnexion réussie aussi quand la session de jeu est ouverte.
- `functions/screen/fn_distanceServer.sqf` : envoi coupé par la coupure centrale = compteurs gardés, sans faux refus au journal.
- COMSPEC Link 2.0.115 (`Extension.cs`, `HasUsableAuthNow`) : sans clé ni jeton valable, `EnqueueOrSend` met en file et
  `DrainQueue` attend (lot de télémétrie compris) au lieu d'envoyer sans authentification.
- Overwatch connect 1.6.27, i18n (21 phrases).

## Fichiers touchés
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_uplinkHold.sqf`, `fn_connect.sqf`, `config.cpp`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_athenaStatus.sqf`, `fn_athenaLink.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_athenaAction.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/screen/fn_distanceServer.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt`
- `mod/UptoDate/COMSPECExtension/Extension.cs`, `COMSPECExtension.csproj`

## Vérification
- `hemtt check` : seulement les 2 erreurs connues `ctrlWebBrowserAction`, aucun avertissement sur les fichiers touchés.
- `dotnet build` de COMSPEC Link : réussi.
- À tester en jeu : démarrer sans téléphone, prendre le téléphone, se connecter (Steam ou code) ; l'app Athena doit montrer
  « Serveur Athena : athena-comspec.eu » et « Envois vers Athena : acceptés » ; RECONNECTER doit dire « Liaison Athena
  rétablie ». Côté site, plus de série de 401 sur `/api/atak/telemetry/batch` pendant la partie.

## Statut
Corrigé (à tester en jeu).
