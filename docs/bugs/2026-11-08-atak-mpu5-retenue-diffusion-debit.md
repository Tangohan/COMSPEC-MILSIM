# MPU5 : la retenue de diffusion du débit ne s'appliquait jamais

## Contexte

`fn_mpu5` « tick » publie `COMSPEC_ATAK_Mpu5` toutes les 2 s si la valeur change. Un débit qui varie seul devait attendre deux tours avant d'être diffusé.

## Symptôme

Constaté à la lecture du code, non vu en jeu : chaque variation du débit partait tout de suite à tous les clients.

## Cause

Le test `_soft` comparait `count` à 2, alors que l'enregistrement a 4 éléments depuis l'ajout de la portée et du nom.

## Correctif

Le test compare `count` à 4, et exige des bonds, une portée et un nom inchangés.

## Fichiers touchés

`mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_mpu5.sqf`

## Vérification

`hemtt check`. En jeu : le débit affiché d'un équipier change avec un retard de 4 à 6 s au plus quand seul le débit varie ; un changement de bonds part tout de suite.

## Statut

corrigé à vérifier en jeu
