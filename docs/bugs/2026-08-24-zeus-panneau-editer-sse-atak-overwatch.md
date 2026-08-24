# SSE / ATAK / OVERWATCH absents du panneau Zeus « Éditer »

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Le menu Zeus vanilla / ZEN « Éditer l’objet » (double-clic, sliders compétence / santé, boutons Garage, Inventaire, OK) ne montrait aucun réglage SSE, ATAK ou Overwatch. Les modules ZEN et le clic droit existaient déjà, mais pas dans **ce** panneau.

## Symptôme

Sur un UH-80 ou une personne, le panneau d’édition n’avait aucun bouton pour l’identité SSE, le suivi carte (téléphone / GPS) ou la liaison Overwatch.

## Cause

Les attributs Eden (`eden_sse_attributes.hpp`) ne sont pas repris par le dialogue Zeus. ZEN réécrit ce panneau ; `zen_attributes_fnc_addAttribute` n’est pas fiable (souvent un stub). Sans injection SQF au chargement de l’affichage, rien n’apparaît.

## Correctif

Trois boutons (SSE, ATAK, OVERWATCH) sont injectés au-dessus de OK, même style que le bouton Overwatch du menu Échap. Chaque bouton ouvre un dialogue Zeus Enhanced adapté à la cible (personne, équipage, véhicule).

- **SSE** : identité / dossier ; option de remettre un terminal de recueil à un joueur.
- **ATAK** : véhicule = balise GPS + nom + équipage IA ; personne = téléphone + données visibles + IA alliée.
- **OVERWATCH** : véhicule vide = nom Athena + envoi de position ; personne = état de liaison ; joueur = resynch + rétablir le terminal.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesTarget.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesPerson.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesInject.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZeusAttributeButtons.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesSse.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesAtak.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesOverwatch.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.58, CfgFunctions, CfgRemoteExec `forceSyncData`)
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/CfgEventHandlers.hpp`

## Vérification

Rebuild `connect.pbo` 1.4.58. En Zeus : double-clic un hélico → boutons SSE / ATAK / OVERWATCH au-dessus de OK. ATAK règle la balise GPS. Double-clic une personne → ATAK téléphone + cases de données ; SSE identité ; OVERWATCH liaison (resynch si joueur). Relancer Arma après copie du PBO.
