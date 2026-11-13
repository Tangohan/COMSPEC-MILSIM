# Relais et app WAVE RELAY : audit d'usage (novembre 2026)

Branche `feat/atak-relais-ux`. Retour du propriétaire sur une capture en jeu : « Faut améliorer les relais ». Rien n'a été
lancé dans Arma : l'audit vient de la lecture du code et de la capture, les écrans cibles sont des maquettes
(`E:\Developpement\compsec.ttrd.fr\_relais_rendus\app_wave_relay\`, ce ne sont pas des captures en jeu).

## En bref

- **L'app disait tout, dans le désordre.** L'état du réseau, la radio détectée, l'état d'ACRE2 et un mode d'emploi de six
  lignes étaient posés à la suite, au même niveau. La réponse à « est-ce que j'ai du réseau, par où ? » demandait de lire
  quatre lignes clé / valeur.
- **Le moteur était sain et borné**, l'app n'en montrait qu'une liste. Le graphe du maillage (64 nœuds par camp, bilans
  en cache, hystérésis, reconvergence) et la liste des relais (santé, canal, charge, batterie) existaient déjà.
- **Deux règles de jeu manquaient** : le réseau marchait sans aucun relais (maillage d'équipe autour du chef de groupe,
  ou maillage autonome), et la voix relayée était coupée par défaut.

## 1. Ce qui existait

| Sujet | Fichiers | Constat |
|---|---|---|
| App Wave Relay | `fn_pageWaveRelay`, `fn_waveRelayStatus`, `fn_waveRelayAction`, `fn_waveRelayTuto` | 4 onglets, une seule colonne de lignes. Carte d'état correcte (bon, dégradé, coupé, cause, action) mais suivie de 8 blocs. |
| Liaison téléphone-radio | `fn_mpuLink`, `fn_mpu5` | Détection ACRE2 / TFAR / MPU5 fiable, une seule source (`fn_mpu5 "detected"`). |
| Graphe du maillage | `fn_meshGraph` | Calculé sur le serveur, diffusé sur changement, au plus toutes les 2 s. Aucune boucle par image. |
| Voix relayée | `fn_mpuVoice`, `fn_mpuVoiceSig`, `fn_relayTxBadge` | Signal rendu à ACRE2 par paliers selon le pire lien du chemin ; TFAR par la portée. Réglage décoché par défaut. |
| Relais | `fn_relayNet`, `fn_relayTower`, `fn_relayEden`, `fn_relayZeus` | Liste complète (état, santé, canal, perte, charge, batterie). Zeus : attributs ZEN et clic droit, un relais à la fois. |
| Carte | `fn_meshDraw` | Liens colorés par qualité, pastilles de relais colorées par état, étiquette de santé : déjà en place, non modifié. |
| Zeus « Voir le graphe de ce camp (3D) » | `fn_meshTools`, `fn_devMeshOverlay` | Superposition 3D d'outil de développement : traits dans le décor, illisible de loin. |
| Dépannage | `fn_mpuDiag`, `fn_netGuide` | Assistant complet, mais rangé dans l'app Réseau, pas dans Wave Relay. |

## 2. Problèmes d'usage relevés

1. **L'état n'est pas en premier.** « Passerelle », « Signal », « Radio », « Objet détecté » sont quatre lignes séparées ;
   la phrase utile (« relié via Pylône 150179, 1 saut ») n'existe nulle part.
2. **Mode d'emploi au milieu de la page.** « Pour l'activer, un admin ou un Zeus coche… » occupe six lignes entre deux
   blocs d'état, pour tous les joueurs, alors que la plupart ne peuvent rien y faire.
3. **Pas d'action suivante.** Deux boutons seulement (ACTUALISER, VOIR SUR LA CARTE). Rien pour tester, choisir son
   relais ou signaler un problème.
4. **Jargon et notations.** « 1 saut(s) de la passerelle », « cam_avs2_bag · radio TFAR », « 100 % · 26 m SO (235°) » :
   corrects mais bruts. Le nom de classe de l'objet est affiché au même rang que le nom de la radio.
5. **État ACRE2 / TFAR mêlé à l'état du réseau.** Le bloc « ACRE2 » parle de la voix dans un onglet qui parle des données.
6. **Aucune vue de la forme du réseau** dans le téléphone : seulement une liste, et un schéma en caractères dans RÉSEAU.
7. **Voisins non triables**, cause du lien faible noyée dans la ligne.
8. **États vides pauvres** : « Personne à portée » sans dire s'il manque un relais ou une radio.
9. **Zeus n'a pas de vue d'ensemble** des relais : il faut cliquer chaque relais.
10. **Voix** : impossible de savoir, en émettant, si la voix passe par le réseau ou par la radio seule (la case « RELAIS
    ATAK » le dit seulement quand c'est le réseau), ni avec quelle qualité.

## 3. Ce qui change

- **Onglet ÉTAT** : carte d'état (titre, débit, « via … · n saut(s) · signal … · portée … », cause et remède), carte
  « Voix », mini-graphe, cinq actions, voisins directs à jauge (tri qualité / distance / sauts, filtre liens faibles,
  étiquette de ce qui limite), zone sans réseau la plus proche, puis cinq cartes repliées avec une ligne d'état chacune.
- **Fiches** (`fn_waveRelaySheet`) à la place des paragraphes : voix par le réseau en six étapes cochées d'après l'état
  réel, choix de la passerelle avec les règles de bascule, diagnostic à signaler, état d'un relais.
- **Onglet RÉSEAU** : graphe (schéma par sauts ou positions réelles), fiche du nœud touché, tous les nœuds triés.
- **Onglet RELAIS** : relais à jauge, cliquables ; pour Zeus et les admins, tous les camps et les actions.
- **Onglet VOIX** : chemin de la voix en direct, qualité, priorité, journal des émissions.
- **Graphe du camp** (`fn_meshGraphView`) : même mise en page dans le téléphone, la fenêtre Zeus et, par l'API, le TOC.
- **Règle du relais exigé** (`fn_relayNet "gate"`), **maillage et MPU5 actifs par défaut**.

## 4. Voix relayée : ce qui est possible, ce qui ne l'est pas

- **Ce qui existait déjà** : entre deux radios reliées, le signal rendu à ACRE2 est le meilleur du direct et du maillage.
  Par le maillage il valait déjà 96 % à 1 saut, 60 % au plancher, 70 % sur un lien dégradé, 45 % sur un lien à la limite,
  rien au-delà. C'est ACRE2 qui en fait du grésillement et des coupures, avec son propre modèle. La dégradation
  entendue par le propriétaire vient de là.
- **Ce qui est ajouté** : ces paliers ont un nom et un pourcentage affichés (Excellent, Bon, Moyen, Mauvais, Perdu), un
  réglage (aucune, légère, réaliste : réaliste garde exactement les valeurs d'avant), un seuil de coupure, et le maillon
  faible avec le conseil.
- **Ce qui n'est pas faisable par un mod** : retarder la voix (la latence affichée est une estimation), appliquer un
  filtre audio (compression, bande étroite, voix robotique). ACRE2 et TFAR traitent le son dans leur greffon TeamSpeak ;
  l'API publique d'ACRE2 ne donne que la force du signal. TFAR n'a pas de signal par paire de radios : seule la portée
  y est allongée, sans palier de qualité.
- **Règle de sûreté gardée** : le réseau ne peut qu'améliorer le signal d'une radio, jamais le réduire. « Maillage
  d'abord » ne peut donc pas affaiblir la radio ; à l'oreille il donne le même résultat que « Les deux ». Seul « Radio
  d'abord » change ce qu'on entend (le maillage n'intervient que si la radio porte à moins de 50 %).

## 5. Coût

- Aucun calcul par image ajouté. La page lit les caches (maillage 3 s, relais 2 s, règle du relais 2 s) ; deux textes
  changent sur place (carte d'état, carte Voix).
- Chemin de la voix : événements d'émission seulement. Fonction de signal d'ACRE2 : trois lectures de variable de plus.
- Graphe : mise en page à l'ouverture, au clic et sur ACTUALISER. Liens en pointillés de contrôles (pas de primitive de
  trait dans un groupe) : 900 points au plus, 80 nœuds au plus, le reste regroupé en « + N ».
- Sans relais et règle active : le graphe ne cherche plus de chemin (sortie avant la résolution).

## 6. Limites connues

- Rien n'a été vérifié en jeu : mise en page réelle des lignes `meter` et `graph`, lisibilité en petite vue, fenêtre Zeus
  sur `RscDisplayEmpty`, événements TFAR (`OnTangent`, `OnSpeak`, TFAR absent de la machine d'étude).
- La priorité de la voix s'applique à ce que chaque joueur entend (ACRE2 calcule le signal chez l'auditeur), pas à ce
  qu'il émet.
- Pictogrammes de nœud par famille (relais, radio posée, téléphone) ; pas de pictogramme par modèle de pylône ou de caméra.
- Zoom du graphe Zeus : le dessin s'agrandit et le groupe défile ; pas de glisser à la souris.
- Recherche du graphe : par indicatif, choisit le premier nœud trouvé.
- Les tests des fonctions « sans effet » rejouent le calcul par un miroir PHP qui lit les constantes dans le SQF.
