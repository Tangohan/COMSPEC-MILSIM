# Réseau maillé MPU5 : notice joueur

Cette notice vaut avec la « Liaison téléphone-radio réaliste », un réglage du serveur, désactivé par défaut. Le chiffrement décrit est une mécanique de jeu : il ne protège rien.

## Se relier

1. Portez une MPU5 et reliez-la au téléphone par câble EUD, ou posez-la et passez en Wi-Fi.
2. Choisissez le canal (1 à 16). Seules les MPU5 du même canal et les relais forment votre réseau.
3. Regardez Wave Relay ou Réseau : nombre de sauts jusqu'à la passerelle, débit, latence.

## Ce qui change en jeu

- **Sauts :** au-delà du plafond (6 par défaut), vous êtes hors du maillage. Le débit baisse à chaque saut.
- **Voix :** reliez votre radio à la MPU5 par le menu ACE. Le signal suit le pire lien du chemin, puis s'arrête net : vous retrouvez la portée normale de la radio.
- **Carte :** un ami sans nouvelle position passe en gris après 30 s et disparaît après 3 min.
- **Fichiers :** une photo part par blocs et reprend après une coupure.
- **Messages :** un message passe de joueur à joueur par le maillage, même sans passerelle.

## Réglages de la MPU5 (app Réseau)

- **Puissance :** plus de portée, moins de batterie.
- **Canal étroit, normal, large :** étroit donne plus de portée et moins de débit ; large, l'inverse.
- **Émission normale, puissance réduite, écoute seule :** en écoute seule, vous recevez, mais vous ne relayez plus et vous n'êtes plus repérable. Le chef peut imposer le mode au groupe.
- **Mât :** une MPU5 posée peut déployer un mât en 30 s. Il porte plus loin et se voit de loin.

## Groupes et clés

- **Groupes :** 16 groupes, dans Wave Relay. Votre radio pontée vous abonne à celui de sa fréquence. Vous écoutez plusieurs groupes et émettez sur deux au plus.
- **Cadenas de la barre d'état :** fermé et vert, ENCRYPTED : liaison chiffrée. Fermé et orange : clé à renouveler. Fermé et rouge : clé compromise. Ouvert, EN CLAIR : liaison non chiffrée, le détail est dans l'info-bulle et dans l'app Wave Relay.
- **Clés (si le serveur les active) :** sans clé de réseau, vous êtes hors du maillage. Sans clé de groupe, le message s'affiche « [CHIFFRÉ · nom] » et la voix n'a pas de rallonge. ZÉROÏSER efface vos clés.
- **Compromission :** si l'ennemi ramasse une MPU5, tout le camp est prévenu : demandez une nouvelle époque au poste de commandement.

## Guerre électronique

Émettre, c'est se montrer. Le spécialiste a un goniomètre et un analyseur de spectre (app Guerre électronique, onglet GONIO). Deux relevés de positions différentes donnent un point estimé. Sous brouillage d'un canal, changez de canal.
