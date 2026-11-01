# Pack non officiel annoncé comme un refus de compte

## Contexte
App Athena du téléphone ATAK, connexion par code (et reprise de session, appairage).

## Symptôme
Avec une copie locale ou modifiée du mod, l'app affiche « Athena n'a pas reconnu le compte qui a autorisé le code.
[HTTP_401] » : le joueur cherche un problème de compte qui n'existe pas.

## Cause
Athena répond 401 `{"error":"client_untrusted"}`. COMSPEC Link ne lisait ce corps que sur certaines routes ; PairStart,
PairStatus et RedeemRecoveryCode renvoyaient `ERR|http_401`, que `fn_athenaStatus` traduisait en refus de compte.

## Correctif
COMSPEC Link 2.0.113 renvoie `ERR|client_untrusted` sur ces routes ; `fn_athenaLink` le garde, `fn_athenaStatus` et les
messages d'Overwatch connect affichent : pack non reconnu comme version officielle, abonnement au Workshop, copies
locales à retirer, relance complète d'Arma. Le vrai 401 garde son message.

## Fichiers touchés
`Extension.cs`, `fn_athenaLink.sqf`, `fn_athenaStatus.sqf`, `fn_pageAthena.sqf`, connect `fn_pollAuth`,
`fn_accountLinkSubmit`, `fn_connect`, `fn_getPhoneConnectInfo`.

## Vérification
PHPUnit `GameAuthAssetTest`. En jeu : mod chargé depuis un dossier local non signé, app Athena, OBTENIR MON CODE.

## Statut
corrigé à vérifier en jeu (2.9.24)
