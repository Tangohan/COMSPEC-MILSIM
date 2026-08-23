# Parade de fenêtres au chargement du mod

## Contexte

Au chargement d’une mission, plusieurs fenêtres Overwatch s’enchaînaient (note bêta Arma, MessageBox Windows « lier Athena », parfois la note à nouveau).

## Symptôme

Défilé de dialogues / alertes Windows pendant les premières dizaines de secondes en jeu, alors que le joueur veut simplement spawn.

## Cause

Deux spawns dans `XEH_postInit.sqf` relançaient :

1. la note bêta (`showBetaAccessNote` → dialogue Arma `COMSPEC_NDA_Dialog`) si le profil n’avait pas encore confirmé ;
2. le MessageBox Windows « Lier mon compte Athena » (~20 s après l’armement médical).

Le menu principal affichait déjà la note. En mission, les retries REAPP pouvaient la rouvrir encore.

## Correctif

- Une seule fenêtre Windows (CGU + disclaimer bêta) au **menu principal** Arma.
- Plus aucun popup automatique en mission (inscription bêta silencieuse si déjà acceptée).
- Rappel liaison Athena : Échap → gestion du mod (réglage Windows off par défaut).

## Fichiers touchés

- `connect/XEH_postInit.sqf`, `XEH_preInit.sqf`
- `connect/functions/fn_showBetaAccessNote.sqf`
- `COMSPECExtension/BetaNoticeWindow.cs`, `Extension.cs`

## Vérification

1. Rebuild DLL + PBO connect, quitter Arma.
2. Menu principal : **une** fenêtre Windows CGU/bêta (si pas encore acceptée).
3. Entrer en mission : plus de défilé de fenêtres.
4. Échap → gestion du mod reste disponible.

## Statut

`corrigé à vérifier en jeu`
