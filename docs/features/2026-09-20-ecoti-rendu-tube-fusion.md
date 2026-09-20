# Affichage situation (JVN) — tube, boussole et halo thermique

Date : 2026-09-20  
Statut : livré (Overwatch 1.6.1, boussole seule 1.6.4)

## Comportement

Sous jumelles de vision nocturne, l’affichage situation reprend un tube à trois oculaires :

- grain et contraste du tube, plus un assombrissement si un véhicule éclaire vers vous ;
- boussole, ou au choix boussole seule (sans grille, distance ni heure) ;
- grille, distance regardée et heure dans le champ (si le tube est en affichage complet) ;
- pastilles en losange, texte avec ombre, intensité qui diminue avec la distance ;
- halo clair sur les alliés proches, les véhicules moteur allumé et le bâtiment désigné.

Si F-PANO ECOTI est chargé, Overwatch laisse la place. Si un module de fusion thermique est déjà chargé, Overwatch n’ajoute pas son halo.

Réglages : Options → Extensions → COMSPEC Overwatch → Affichage situation, ou ATAK → Paramètres (affichage, couleurs, pastilles, indications dans le tube).

## Vérification

1. Rebuild pack, quitter Arma complètement.
2. ATAK → Paramètres → Affichage situation activé.
3. Jumelles de vision nocturne : tube visible, boussole en haut, pastilles lisibles sans plaque pleine.
4. Phares d’un véhicule face à vous : le tube se ferme un instant.
5. Paramètres → Dans le tube → Boussole uniquement : seule la direction reste, grille et heure disparaissent.
6. Avec F-PANO ECOTI chargé : pas de double affichage.
