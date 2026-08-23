# Eden — attributs COMSPEC SSE

Catégories sur l’objet (unité, véhicule, objet contrôlable) :

## COMSPEC SSE

| Champ | Valeurs |
|-------|---------|
| SSE activé | oui / non |
| Profil | aléatoire, civil, insurgé, militaire, HVT, personnalisé |
| Génération | automatique / manuelle |
| Richesse | légère, standard, détaillée, haute valeur |
| Identité | automatique (Eden) / inventer / forcer le nom Eden |
| Rôle, nationalité, langue, numéro connu | texte libre (vide = génération) |
| Biométrie | générer / ne pas inclure |
| Notes Zeus, réseau, modèle, dataset, région | préparation de mission |

## COMSPEC SSE — Documents

Jusqu’à **3 pièces**, chacune avec intitulé, contenu, grille et mot de code.

- **Générer automatiquement** : le jeu compose les pièces ; un champ rempli **remplace** la pièce correspondante.
- **Personnaliser** : uniquement les pièces dont au moins un champ est rempli.
- **Ne pas inclure** : aucun document.

## COMSPEC SSE — Téléphone

Numéro, modèle, contacts (un par ligne), messages (`Expéditeur : texte`), notes, lieux (`Libellé \| grille`).

Même logique automatique / personnaliser / ne pas inclure.

## COMSPEC SSE — Ordinateur

Nom de la machine, compte, fichiers (un nom par ligne), courrier (objet + extrait), nom de réseau, indice d’accès.

En mode automatique, un ordinateur n’apparaît pas toujours (selon la richesse). **Personnaliser** impose une machine avec les champs saisis.

## Comportement

Si `SSE activé` et génération automatique → contenu appliqué à l’init serveur (y compris les champs des trois catégories).  
Génération manuelle → même contenu au premier examen.
