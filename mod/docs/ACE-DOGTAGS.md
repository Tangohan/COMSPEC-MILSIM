# ACE Medical — plaque d’identification → SSE

## Objectif

Quand un soigneur **vérifie la plaque** ACE (dog tag) sur un sujet KO / mort qui a un profil SSE, le nom et le code affichés viennent de l’**identité SSE**, pas du nom Arma générique. L’action alimente aussi le dossier SSE (brouillard d’identité).

## Prérequis

- `@COMSPEC_SSE` avec PBO `comspec_sse_compat_ace`
- ACE3 (`ace_dogtags` + `ace_medical` activé pour le flux médical habituel)
- Cible avec SSE (Eden / Zeus / lazy generation)

## Réglage CBA

**COMSPEC SSE → Compatibilité → Plaque ACE → identité SSE** (activé par défaut).

## Comportement

1. Génération / `setIdentity` → écrit `ace_dogtags_dogtagData` = `[nom SSE, n° ID, groupe sanguin]`.
2. Wrap `ace_dogtags_fnc_getDogtagData` : si SSE présent, renvoie toujours ces données.
3. Wrap `ace_dogtags_fnc_checkDogtag` : après l’overlay ACE, `revealFog` action `dogtag` + journal.

## Vérification in-game

1. PNJ SSE (ou unité générée) → le mettre KO.
2. ACE Interact → **Dog Tag → Check**.
3. Overlay ACE : nom = identité SSE (ex. « Karim Haddad »), pas le classname.
4. Hint / journal SSE : « Identité sur plaque… ».

## Fichiers

- `addons/compat_ace/`
- Hooks depuis `generateData` / `setIdentity`
- Action `dogtag` dans `fn_revealFog.sqf`
