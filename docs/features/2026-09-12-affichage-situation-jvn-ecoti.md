# Affichage situation (JVN) — vague ECOTI Overwatch

Date : 2026-09-13  
Statut : livré (1.5.60 · Athena 1.0.108)

## Comportement

- Désactivé par défaut ; activation dans **ATAK → Paramètres → Affichage situation (JVN)**.
- **Couleurs situation** : thème JVN (cyan clair), Vert lime, Ambre, Blanc ou Bleu — badges, icônes, textes, contours et surbrillance.
- Badges contrastés sous jumelles (halo + plaque + contour de texte).
- **Surbrillance des personnes** alliées proches (contour + badge).
- Contour de l’objet regardé ; silhouette du bâtiment désigné plus collée à la géométrie (échantillonnage, pas seulement le gros cadre).
- **Découpage d’étage** + ACE **Changer d’étage** + ACE **Découper à la hauteur regardée** (pointe, désigne, coupe à l’étage regardé).
- Si F-PANO ECOTI est chargé, Overwatch laisse la place.

## Limites assumées

- **Pas d’ouverture réelle des murs** : silhouette / découpe visuelle uniquement.
- Le contour personne est une capsule approximative, pas un tracé anatomique.
- Le nombre d’étages reste estimé (~3 m).

## Vérification

1. Rebuild pack, quitter Arma.
2. Paramètres → Affichage situation ON + thème JVN (cyan clair).
3. Sous JVN : badges lisibles, contours personnes.
4. ACE → Découper à la hauteur regardée sur un bâtiment.
5. Changer le thème : couleurs mises à jour.
