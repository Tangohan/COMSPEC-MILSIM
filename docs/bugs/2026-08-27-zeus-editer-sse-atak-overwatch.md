# Boutons SSE / ATAK / OVERWATCH sans effet dans l’édition Zeus

## Contexte

Zeus, panneau « Éditer » une personne / un véhicule / un objet. Les trois boutons verts SSE, ATAK et OVERWATCH s’affichent au-dessus de OK (OVERWATCH parfois coupé en OVERWAT).

## Symptôme

Un clic ne fait rien : aucune fenêtre, aucun message.

## Cause

Le panneau d’édition reste ouvert. Zeus Enhanced refuse d’ouvrir une seconde fenêtre par-dessus. Un seul nouvel essai silencieux, trop court, puis rien.

## Correctif

Fermer l’édition au clic, attendre un court instant, puis ouvrir la fenêtre. Si ça échoue encore : message visible.

## Fichiers touchés

- `mod/.../fn_zeusAttributesInject.sqf`
- `mod/.../fn_zeusAttributesSse.sqf`
- `mod/.../fn_zeusAttributesAtak.sqf`
- `mod/.../fn_zeusAttributesOverwatch.sqf`
- `mod/.../connect/config.cpp` (1.4.88)

## Vérification

Rebuild connect 1.4.88, relancer Arma. Zeus → double-clic une personne → SSE / ATAK / OVERWATCH doit ouvrir un panneau.

## Statut

corrigé (rebuild pack requis)
