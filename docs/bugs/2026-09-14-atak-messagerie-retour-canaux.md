# Messagerie ATAK — impossible de revenir aux canaux

## Contexte

Dans le téléphone ATAK, l’app **Messagerie** affiche d’abord la liste des canaux
(Air, Commandement, Général, Groupe, JTAC). Un opérateur ouvre un canal, puis
ne peut plus revenir à cette liste. L’écran mélange liste et fil (Créer et
Envoyer en même temps). Le bouton **Retour** du bas est recouvert par
**Live Feed** / **Tourelle**. Un plantage du jeu a été observé dans la même
session.

## Symptôme

- Une fois un canal ouvert, aucun moyen fiable de retrouver la liste.
- L’onglet IceMan « Canaux » et le bandeau **Retour** ne ramènent pas à la liste.
- Bouton interne **Retour aux canaux** invisible ou injoignable.
- Le jeu peut se fermer pendant l’usage de Messagerie.

## Cause

1. Reconstruire la liste des canaux relance la sélection : le fil se rouvre
   tout seul, comme si l’opérateur n’avait jamais quitté le canal.
2. Le bouton **Retour** du téléphone quitte l’application au lieu de revenir
   aux canaux. Il est en plus recouvert par Live Feed / Tourelle.
3. L’écran IceMan Groups reste dessiné par-dessus Messagerie (onglets Canaux /
   Canal de l’équipe), ce qui bloque le vrai bouton de retour.
4. Un basculement IceMan relançait toute l’ouverture du téléphone pendant
   l’affichage déjà en cours — double écran et risque de plantage.

## Correctif

- Revenir aux canaux ne relance plus le fil tout seul.
- Bouton **Retour aux canaux** en pleine largeur dans le fil ; le titre
  Messagerie et le bandeau bas **Canaux** font la même action.
- Live Feed / Tourelle masqués sur cet écran ; un seul bouton bas utilisable.
- Écran IceMan Groups masqué tant que Messagerie est ouverte.
- Basculement IceMan sans relancer l’ouverture complète du téléphone.

## Fichiers touchés

- `atak_athena/ui/comms_page.hpp`
- `atak_athena/functions/fn_athena_commsBack.sqf`
- `atak_athena/functions/fn_athena_commsSelectChannel.sqf`
- `atak_athena/functions/fn_athena_updateComms.sqf`
- `atak_athena/functions/fn_athena_commsOnOpened.sqf`
- `atak_athena/functions/fn_athena_commsFooter.sqf`
- `atak_athena/functions/fn_athena_commsApplyChrome.sqf`
- `atak_athena/functions/fn_athena_hideForeignPages.sqf`
- `atak_athena/XEH_postInitClient.sqf`
- `atak_athena/config.cpp` (Athena 1.0.124)

## Vérification

1. Relancer Arma complètement, pack Athena 1.0.124.
2. Ouvrir Messagerie : liste des canaux seule, bandeau **Retour** cliquable
   (pas de Live Feed).
3. Ouvrir Commandement : bouton **Retour aux canaux** visible.
4. Clic Retour aux canaux / titre Messagerie / bandeau Canaux → liste, sans
   réouverture immédiate du fil.
5. Depuis la liste, **Retour** ramène au tiroir des applications.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
