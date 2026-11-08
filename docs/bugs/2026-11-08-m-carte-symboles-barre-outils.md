# Carte du téléphone (/m/carte) : symboles du jeu invisibles et boutons coupés à 390 px

- **Contexte** : relevé en faisant le rendu du style tactique de `/m/carte` dans un navigateur headless à 390 px.
- **Symptôme** : les marqueurs teintés par masque (triangle, ennemi, danger) n'affichaient que leur libellé. « Calques » et « + Marqueur » étaient coupés et se recouvraient.
- **Cause** : la règle `.arma-marker-paa-mask` vit dans `atak-overwatch-beta.css`, feuille non chargée par `/m/` : un `span` en ligne ignorait sa taille. La barre gardait six boutons d'au moins 56 px dans 374 px.
- **Correctif** : règle reprise dans `atak-m.css` pour la carte ; barre à 48 px minimum, « + Marqueur » sur deux lignes.
- **Fichiers touchés** : `public/assets/css/atak-m.css`.
- **Vérification** : rendu headless à 390 px, données de test, pas la production. Non vérifié sur un vrai téléphone.
- **Statut** : corrigé, à vérifier sur téléphone.
