# SPOTREP #00002

reported on August 24, 2026

TECHREP #00002

```
FROM:     État-major COMSPEC
TO:       Communautés Athena, opérateurs ATAK, cellule S1, Zeus
MATERIEL: Carte et doctrine
ACTIVITY: Relief lisible, carte des rôles, parc de terminaux, comptes rendus
SIZE:     Portail 1.5.38
```

# NOTES

- Le relevé de relief autour de l’équipe n’est plus un calque vide : l’ombrage et les courbes n’apparaissent que là où le sol a été relevé, et s’étendent au fil des déplacements.
- La carte des rôles n’est plus un nuage de points : elle montre qui relève de qui.
- Pas besoin d’un nouveau pack jeu pour le relief déjà relevé ni pour la carte des rôles.
- Le TOC peut tracer un itinéraire ou une visée : le profil du sol et le masque du relief s’affichent là où le relevé existe.

# CHANGELOG

## CARTE ATAK

- Added: Ombrage du relief (lumière du nord-ouest) superposé à la carte satellitaire
- Added: Courbes de niveau 10 m et 50 m, affichables séparément
- Added: Couche des pentes pour les véhicules (praticable à critique)
- Added: Altitude du sol au survol et couverture du relevé en pourcentage
- Tweaked: La carte se charge plus vite : elle n’attend plus tout le relief d’un coup
- Fixed: Un relevé de 4 km autour de l’équipe suffit pour voir le relief, sans attendre toute la carte
- Added: Analyse d’itinéraire : profil du sol, distance, montée et descente
- Added: Visée JTAC : observateur vers cible, verdict dégagé ou masqué par le relief

## POSTE DE COMMANDEMENT

- Added: Carte des rôles en organigramme (cartes, niveaux, flèches selon la nature du lien)
- Added: Comptes rendus d’après-action : modèles sur mesure (question courte, liste, cases, texte libre)
- Fixed: Parc de terminaux : plus de décalage de deux heures sur la dernière activité
- Fixed: Terminaux en double (même opérateur, deux versions) regroupés en une fiche
- Fixed: Édition des rapports d’après-action standard : les champs restent visibles
