# Connexion Athena — e-mail / « Valider le code » trompeur

## Contexte

12 septembre 2026. Écran Athena ATAK : bannière « Compte trouvé », Steam non associé, formulaire Appairer + e-mail. L’opérateur signale que la connexion par mail ne marche pas, et que « Valider le code » ne semble servir que pour le code e-mail.

## Symptôme

- Après demande de code e-mail, le gros bouton vert affiche « Valider le code » alors que le champ mot de passe réapparaît au même endroit.
- Saisir un mot de passe puis cliquer « Valider le code » ne connecte pas (le bouton lit le champ code e-mail, souvent vide).
- Demander un code e-mail pouvait laisser l’extension en état « synchronisation », ce qui masquait le formulaire sur la fenêtre de connexion séparée.
- Confusion : Appairer (Lier) vs code e-mail (Valider) vs mot de passe (Se connecter) vs Steam.

## Cause

1. **Layout** (`fn_athena_applyHomeLayout`) : après `otp_ask`, le recalcul de position **forçait** le champ mot de passe visible tout en gardant « Valider le code ». L’opérateur tape au mauvais endroit ; la validation lit l’autre champ.
2. **Extension** (`GameRequestOtp`) : succès laissait l’état `AUTHENTICATING`. `pollAuth` traitait cela comme une synchro et masquait e-mail / code / boutons sur la fenêtre Athena séparée.
3. **UX** : un seul libellé « Valider le code » sans préciser qu’il ne concerne que le code reçu par e-mail, pas Appairer ni le mot de passe.

## Correctif

- Mode OTP exclusif (flag `comspec_overwatch_auth_otp_mode`) : mot de passe XOR code e-mail ; boutons « Se connecter » XOR « Valider le code reçu ».
- Retour au mot de passe via le même bouton « Code par e-mail » → « Revenir au mot de passe ».
- `GameRequestOtp` passe en `AWAITING_OTP` (hors liste de synchro) ; e-mail / mot de passe / code nettoyés via `ArmaString`.
- Libellés et textes d’aide clarifiés (Appairer = Lier ; e-mail = Se connecter / Valider le code reçu ; Steam distinct).
- `pollAuth` respecte le mode OTP et rafraîchit le panneau ATAK même sans fenêtre séparée.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/GameAuth.cs`
- `mod/UptoDate/COMSPECExtension/Extension.cs` (liaison 2.0.31)
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_authAction.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_pollAuth.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_requestOTP.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_athena_auth.hpp`
- Versions : Overwatch **1.5.50**, Athena ATAK **1.0.95**, liaison **2.0.31**

## Vérification

1. Rebuild pack (PBO + DLL).
2. Mot de passe : e-mail + mot de passe → **Se connecter** → Environnement prêt → Entrer.
3. Code e-mail : **Code par e-mail** → champ code seul → **Valider le code reçu** → prêt.
4. En mode code, le champ mot de passe ne doit plus réapparaître ; « Revenir au mot de passe » restaure le formulaire classique.
5. Appairer : code portail → **Lier** (indépendant de Valider le code reçu).
6. Steam : bouton dédié ; message clair si non associé.

## Statut

Corrigé (à valider en jeu sur pack 1.5.50).
