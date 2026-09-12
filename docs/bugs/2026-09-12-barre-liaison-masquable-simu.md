# Barre de liaison masquable + simulation désactivable

## Contexte

12 septembre 2026. Overwatch 1.5.59 / Athena ATAK 1.0.102. La barre OK/NOK sous la barre d’état du téléphone masquait la boussole ; la simulation réseau existait surtout sous Roleplay CBA, peu visible pour l’opérateur.

## Symptôme

- La barre de liaison occupe trop de place sur la carte et cache la boussole.
- Impossible de la masquer depuis les Paramètres du téléphone.
- La simulation de perte / fiabilité n’est pas clairement désactivable pour une opération « propre ».

## Cause

- Bandeau trop large/haut, ancré sur toute la largeur utile.
- Pas de réglage opérateur dédié.
- Les coupures simulées dépendaient surtout du couple Roleplay + Simulations réseau.

## Correctif

- Bandeau plus petit (hauteur et largeur réduites, aligné à gauche) pour laisser la boussole.
- Réglage **Afficher la barre de liaison** (Paramètres ATAK + Options CBA), ON par défaut.
- Réglage **Simulation de liaison dégradée** (Paramètres + CBA), OFF par défaut : pertes/fiab. simulées, soft-block d’envoi, coupures brèves ; OFF = état réel sans fausse dégradation.

## Fichiers touchés

- `fn_athena_updateLinkStrip.sqf`
- `settings_page.hpp`, `fn_athena_updateSettings.sqf`, `fn_athena_settingsSave.sqf`
- `fn_athena_linkStripSave.sqf`, `fn_athena_linkDegradeSimSave.sqf`
- `fn_linkStripApplySetting.sqf`, `fn_linkDegradeSimApplySetting.sqf`, `fn_isLinkDegradeSimActive.sqf`
- `fn_simulateNetworkDisconnect.sqf`, `fn_isNetworkDisconnected.sqf`, `fn_canTransmit.sqf`, `fn_getPacketLossStats.sqf`
- `XEH_preInit.sqf`, `XEH_postInit.sqf`, `config.cpp` (connect + atak_athena)

## Vérification

1. Quitter Arma, pack 1.5.59.
2. Ouvrir le téléphone : barre plus courte, boussole visible.
3. Paramètres → Afficher la barre de liaison → Masquée : barre disparait.
4. Simulation de liaison dégradée → Activée : fiab./perte se dégradent, éventuelles coupures brèves ; Désactivée : retour à l’état réel.

## Statut

corrigé — Overwatch 1.5.59
