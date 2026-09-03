# Cockpit Hatchet — mise en route bloquée

## Contexte

Pack Hatchet (H-60) avec Overwatch et/ou SSE.

## Symptôme

Dans le cockpit, le clic et la molette ne commandent plus le tableau de bord. Batterie, groupe auxiliaire et démarreurs restent inertes. Sans Overwatch ni SSE, l’appareil démarre.

## Cause

Ce n’est pas un calque Zeus. Le menu d’actions personnelles ACE (Overwatch SSE ACE et SSE Interaction) reste ouvert ou vise l’équipage à moins de quatre mètres. Hatchet a besoin du clic et de la molette pour les interrupteurs.

## Correctif

À l’embarquement, le menu ACE se ferme. Tant que l’opérateur est aux commandes d’un appareil Hatchet, le recueil SSE et les actions sur l’équipage restent masqués. Le suivi d’engin ignore un organe manquant.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isHatchetVehicle.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_hideAceMenu.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_sseCanExploit.sqf`
- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_canInspect.sqf`

## Vérification

Relecture du code : détection `hct`. Menu refermé à l’embarquement. Conditions ACE fausses en Hatchet.

Non vérifié ici : pas de client Arma dans cet atelier.

## Statut

Corrigé (à déployer — relancer Arma complètement).
