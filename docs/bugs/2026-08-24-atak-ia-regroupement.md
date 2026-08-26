# IA alliées regroupées / écrasées sur l’ATAK

## Contexte

Sur la carte ATAK web, plusieurs IA du même groupe (ex. Alpha 1-2) se
superposaient. Le fil d’activité montrait aussi l’indicatif opérateur
(N-10) écrit sur une IA nommée (Siaki Katalou).

## Symptôme

- Pastilles IA empilées ou fusionnées
- Journal : « Indicatif mis à jour — Alpha 1-2 - … → N-10 »
- Deux noms d’IA distincts rattachés au même groupe au même instant

## Cause

1. La clé de suivi BFT utilisait l’indicatif affiché (groupe / nom) au
   lieu d’un identifiant stable par unité.
2. Un renommage ou une liaison fiche pouvait remplacer cette clé par
   l’indicatif de l’opérateur : la pastille IA fusionnait avec celle du
   joueur.
3. La DLL pouvait mémoriser l’indicatif d’une IA relais comme indicatif
   opérateur.

## Correctif

- Remontée jeu : clé = identifiant stable ALLY-… ; libellé dans le nom
  affiché.
- Poste : en mise à jour d’une IA, le nouvel indicatif reste un libellé
  (jamais la clé).
- Extension : ne pas reprendre l’indicatif d’un contact relais comme
  indicatif opérateur.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportAllyPosition.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Repositories/AtakDataRepository.php`
- `app/Controllers/Api/AtakApiController.php`

## Vérification

- Deux IA du même groupe : deux pastilles aux bonnes positions
- Renommer une IA au poste : le libellé change, pas de fusion avec N-10
- Pack Overwatch 1.4.74 + portail 1.5.48

## Statut

corrigé
