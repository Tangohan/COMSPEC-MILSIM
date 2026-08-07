# Menu ACE « Renseignement SSE » absent (corps / PNJ / joueurs)

## Contexte

Addon optionnel `sse_ace` — nœud ACE `COMSPEC_SSE` sur `CAManBase` (`ACE_MainActions`).
Les journaux montraient bien « Installation du menu SSE » / « Menu SSE installé », mais aucune entrée n’apparaissait en jeu sur un corps, un PNJ ou un joueur.

## Symptôme

- Interaction ACE (touche d’interaction) sur une personne : pas de « Renseignement SSE ».
- Reproduit sur corps, PNJ et joueurs.
- `sse_ace.pbo` était bien chargé (PreInit OK dans le RPT).

## Cause

1. **Condition trop stricte (cause principale)** — `fn_sseCanExploit` exigeait ATAK (`hasTerminal`) **et** le terminal SEEK (`sseHasTerminalItem`) avant même d’évaluer l’état de la cible. Sans SEEK en inventaire, le nœud était invisible partout, y compris sur un corps (cas SSE central). L’ouverture via `sseOpenTerminal` gérait déjà le message d’absence de SEEK.
2. **Garde module cassée** — `exitWith` placé dans un `then {}` ne sort pas de la fonction SQF ; le helper `isModModuleEnabled` n’était pas utilisé.
3. **ModifierFunction ACE incorrect** — ACE passe `[_target, _player, _actionParams, _actionData]` (4 args) ; le code traitait le 3ᵉ arg comme `_actionData`, donc le libellé contextuel ne s’appliquait jamais.
4. **Doublons de nœuds** — le flag `COMSPEC_SseAceMenuReady` était en `missionNamespace` alors que `addActionToClass` persiste pour la session Arma → ré-enregistrement à chaque mission.

## Correctif

- Visibilité ACE : état cible + distance + réglages / module uniquement ; inventaire SEEK contrôlé à l’ouverture (`sseOpenTerminal`).
- `doNotCheckLOS` + distance 4 m sur le nœud parent et l’action.
- Flag d’install en `uiNamespace` ; signature modifier ACE corrigée ; module via `isModModuleEnabled`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_sseCanExploit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_initSseAce.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/config.cpp` (version 1.4.15)
- Rebuild / déploiement de `sse_ace.pbo` vers `@COMSPECOverwatch` (Workshop local)

## Vérification

- [x] `sse_ace.pbo` 1.4.15 présent et SHA256 sync sur les 4 racines + `publisher` (2026-08-06)
- [ ] Relancer Arma avec `@COMSPECOverwatch` (PBO `sse_ace` à jour) — **redémarrer le jeu**, pas seulement la mission (ACE `addActionToClass` persiste en session)
- [ ] Sur un **corps** à &lt; 4 m : ACE interact → « Renseignement SSE » → « Ouvrir la fiche SSE (personne décédée) »
- [ ] Sur un PNJ **inconscient / menotté / non armé** : même nœud
- [ ] Sans objet SEEK : le menu apparaît ; au clic, message « Terminal SEEK absent… »
- [ ] Avec SEEK : la fiche s’ouvre préremplie
- [ ] Soldat armé conscient non menotté : nœud **absent** (comportement voulu)

## Statut

corrigé — `sse_ace.pbo` 1.4.15 déployé Workshop ; confirmation in-game à faire
