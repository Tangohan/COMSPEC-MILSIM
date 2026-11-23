# Zeus : héliports invisibles et sphères dans la liste d'objets éditables

## Contexte
Liste d'objets éditables de l'interface Zeus, avec le TOC (écrans à plusieurs dalles), les antennes du décor converties en relais et le mode développeur du maillage.

## Symptôme
« Le TOC fait spawn des héliports invisibles » et « le réseau fait spawn des sphères » : des `Land_HelipadEmpty_F` et des `Sign_Sphere25cm_F` apparaissent dans la liste du Zeus, qui peut les sélectionner et les déplacer.

## Cause
Le mod crée ces objets côté serveur comme supports (porteur invisible d'un relais ou d'une dalle, nœud simulé du maillage) ; le Zeus ou un autre mod (Zeus Enhanced) inscrit seul les objets créés sur le serveur dans la liste éditable.

## Correctif
`fn_zeusHide` : retire l'objet de tous les curateurs à sa création (relais convertis, dalles TOC, nœuds simulés) et le mémorise ; un balayage serveur toutes les 30 s retire de nouveau ceux qui se réinscrivent. Depuis un client, la demande passe par un événement CBA vers le serveur.

## Fichiers touchés
`functions/zeus/fn_zeusHide.sqf` (nouveau), `fn_relayConvert.sqf`, `fn_tocScreen.sqf`, `fn_devMeshSim.sqf`, `config.cpp`, `XEH_postInitServer.sqf`, `tests/Unit/AtakZeusHideAssetTest.php`.

## Vérification
Test de présence du câblage. Rien vu en jeu.

## Statut
corrigé à vérifier en jeu
