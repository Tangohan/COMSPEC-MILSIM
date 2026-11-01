# ACE Medical — plaque d’identification → SSE

## Objectif

Quand un soigneur **vérifie la plaque** ACE (dog tag) sur un sujet KO / mort qui a un profil SSE, le nom et le code affichés viennent de l’**identité SSE**, pas du nom Arma générique.

## Prérequis

- `@COMSPEC_SSE` avec PBO `comspec_sse_compat_ace`
- ACE3 (`ace_dogtags` + `ace_medical` activé pour le flux médical habituel)
- Cible avec SSE (Eden / Zeus / lazy generation)

## Réglage CBA

**COMSPEC SSE → Compatibilité → Plaque ACE → identité SSE** (activé par défaut).

## Comportement

1. Génération / `setIdentity` → écrit `ace_dogtags_dogtagData` = `[nom SSE, n° ID, groupe sanguin]`.
2. ACE lit ce cache natif : aucune fonction ACE n’est remplacée (`fn_aceDogtagInstallHooks`, écriture seule).
3. La lecture de plaque n’alimente plus le dossier SSE (plus d’action `dogtag` dans `fn_revealFog.sqf`).

## Vérification in-game

1. PNJ SSE (ou unité générée) → le mettre KO.
2. ACE Interact → **Dog Tag → Check**.
3. Overlay ACE : nom = identité SSE (ex. « Karim Haddad »), pas le classname.

## Fichiers

- `addons/compat_ace/`
- Hooks depuis `generateData` / `setIdentity`
