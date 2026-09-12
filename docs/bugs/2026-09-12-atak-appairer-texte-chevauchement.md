# 2026-09-12 — ATAK layout : Appairer, panneau, fiche, Paramètres

## Contexte
Vague de retours opérateurs sur le téléphone ATAK (Samsung paysage / portrait) : textes illisibles, panneau qui déborde, fiche et Paramètres coupés.

## Symptômes
1. **Appairer** : l’aide « Sur le portail… » chevauchait le titre « 1 · Appairer » ; statut + indicatif trop serrés.
2. **Panneau / barre de données** : le tiroir d’apps débordait à droite du cadre téléphone ; icône bas-droite coupée.
3. **Bandeau NOK/sync** : pouvait chevaucher le header carte ou dépasser à droite.
4. **Fiche « Liaison OK »** : les lignes sous « Position remontée » restaient cachées derrière Rouvrir / Déconnecter / Envoyer le temps.
5. **Paramètres** : libellés (Rôle, Affichage sur la carte…) coupés en bas ; « Liaison au poste » à peine visible au-dessus de Retour.

## Causes
- Hauteurs fixes trop faibles pour `AuthHint` / `PairHint` une fois l’état READY (plusieurs lignes).
- `Check_Layout` recalculait la largeur du panneau avec `safeZoneW * 0.55` : sur le téléphone la carte entière est déjà plus étroite → panneau poussé hors cadre.
- Fiche liée : `RscStructuredText` plein écran sans zone scroll bornée au-dessus des boutons fixes.
- Paramètres : hauteur de libellés ~0,34 unité insuffisante vs police ; marge basse du scroll trop courte.

## Correctifs
- Appairer : textes raccourcis, hauteurs + gaps ; repositionnement dynamique dans `applyHomeLayout`.
- Panneau : seuil safeZone retiré ; clamp carte + tiroir dans le cadre.
- Bandeau liaison : sous le header, hauteur 2 lignes, largeur bornée à gauche du tiroir.
- Fiche liée : `StatusHost` (9698) scrollable ; boutons hors flux.
- Paramètres : libellés plus hauts, gaps, scroll plus court + spacer bas.

## Fichiers touchés
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_pageCtrl.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateLinkStrip.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.5.50)
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.95)

## Vérification
1. Athena non lié / Compte trouvé : titre Appairer et aide lisibles, champs non superposés.
2. Ouvrir une app (tiroir) en paysage : panneau entier dans le cadre, pas de coupe à droite.
3. Liaison OK : scroller la fiche jusqu’à « Temps de mission » au-dessus des boutons.
4. Paramètres : libellés complets ; scroller jusqu’à « Liaison au poste » lisible.

## Statut
Corrigé (après rebuild pack 1.5.50 · Athena 1.0.95)
