# Reports ATAK — superposition Inbox / New / Tasks

## Contexte

Sur la tablette, l’app **Reports** affichait en même temps la liste Inbox
(FRAGO…) et le formulaire New (Eagle Down / BDA), avec la barre du bas
encore en mode Tasks (`Enter` / `Live Feed`).

## Cause

1. `PAGE_CTRL = "ATAK_Message"` partagé avec d’autres apps → `createSubPage`
   ne reset pas toujours quand on revient de Tasks / Messagerie.
2. Le détail Inbox n’avait pas de section `inbox` ; le filtre Inbox/New
   était incomplet.
3. `Iceman_Reports_Menu` n’avait pas d’`initButtons` → chrome Tasks conservé.

## Correctif

- Page dédiée `Iceman_ATAK_Reports`
- `alerts_initButtons` (Locate / Clear Local, pas Live Feed)
- Masquage des pages sœurs à l’ouverture + reset onglet Inbox
- `updatePanel` renforcé (enfants scroll + filet IDC)

## Fichiers

- `mod/UptoDate/_tmp_marker_probe/iceman/ATAK_Alerts/config.cpp`
- `.../ui/ReportsPage.hpp`
- `.../functions/fn_alerts_initButtons.sqf`
- `.../functions/fn_alerts_onopened.sqf`
- `.../functions/fn_alerts_updatepanel.sqf`

## Vérification

Rebuild PBO `ATAK_Alerts` (Iceman). Ouvrir Tasks puis Reports : une seule
vue. Basculer Inbox ↔ New : pas de chevauchement. Barre = Retour / Locate /
Clear Local.

## Statut

corrigé en sources probe — rebuild PBO Iceman `ATAK_Alerts` requis
