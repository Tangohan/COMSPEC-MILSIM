# Connexion DEV — re-liaison Steam immédiate

## Contexte

Pack `@COMSPECOverwatch_DEV`, écran HTML Connexion Athena dans IceMan.

## Symptôme

À l’ouverture, le terminal affiche déjà le compte Athena du joueur (ex. Tanguy TETARD). « Changer de compte Steam » y revient tout de suite. Le texte d’indicatif était illisible (`liÃ©`, `Ã`).

## Cause

Pas un cache HTML. Au démarrage de mission, la session enregistrée sur le PC est restaurée, puis Steam s’identifie tout seul. Ce Steam est déjà associé à ce compte sur le poste, donc la liaison réussit à chaque fois. Après déconnexion, le bouton relançait la même identification Steam. Les accents passés par ExecJS étaient mal encodés.

## Correctif

- « Changer de compte Steam » déconnecte et ouvre l’appariage, sans relancer Steam tout seul.
- Connexion à Athena, tant que ce mode est actif, demande un code du poste.
- Les noms et indicatifs sont transmis en UTF-8 (plus de caractères cassés).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginSwitchSteam.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginConnect.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginPushLinked.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginSetStatus.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginPairStart.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginRedeem.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/web/login.html`

## Vérification

Rebuild `build_mod_dev.bat`. Ouvrir Connexion : le compte actuel s’affiche si déjà lié. « Changer de compte Steam » reste sur le code d’appariage au lieu de rouvrir le même nom.

## Statut

corrigé
