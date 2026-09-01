# Journal radio — bulles pas alignées

## Contexte

Poste ATAK, onglet Journal radio.

## Symptôme

Les messages de groupe, les messages du poste et la zone d’écriture ne partaient pas de la même marge à gauche. Ça donnait un décalage, difficile à lire.

## Cause

Les messages du poste étaient décalés vers la droite. Les messages de groupe avaient un cadre intérieur plus étroit, et des marges différentes de la zone d’écriture.

## Correctif

Toutes les bulles ont la même largeur et la même marge. Le texte du groupe n’est plus dans un second cadre. L’en-tête, la liste et le champ d’émission partagent la même gouttière.

## Fichiers touchés

- `public/assets/css/atak.css`

## Vérification

Recharger la page ATAK. Journal radio : une bulle de groupe et une bulle du poste doivent s’aligner à gauche, comme le champ d’émission.

## Statut

corrigé
