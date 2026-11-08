# Réseau maillé du téléphone ATAK : plan technique

Statut : plan, aucun code touché. Périmètre : `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/` (`network/`, `core/fn_linkQuality.sqf`, `pages/fn_pageWaveRelay.sqf`, `pages/fn_waveRelayAction.sqf`).

Objectif du propriétaire : un réseau plus poussé, façon MPU5 / Wave Relay, un chiffrement simulé comme un vrai ATAK, et un maillage voix TFAR / ACRE via le téléphone et les relais.

Le chiffrement décrit ici est une **mécanique de jeu** (étiquettes de clés, états, conséquences). Il n'utilise aucune cryptographie réelle, ne protège rien, et ne doit jamais être présenté comme une sécurité.

## 1. En bref

- Le socle existe déjà et marche : bilan radio en dB, relais, batterie, brouillage, maillage par canal, pont voix ACRE / TFAR (opt-in, désactivé par défaut).
- Ce qui manque : un **seul** graphe partagé par l'écran, les données et la voix ; des coupures nettes selon la qualité des liens ; de vrais groupes de conversation ; du chiffrement ; des lectures de variables d'autres joueurs avec type et bornes.
- ACRE2 : les points d'accroche utilisés sont **vérifiés dans le code de la version installée** (2.14.0.1064).
- TFAR : **non installé sur la machine d'étude**, donc ses points d'accroche ne sont pas vérifiés à la source (voir section 5). Un lot dédié commence par cette vérification.

## 2. État actuel

### Ce qui est simulé pour de vrai

| Brique | Fichier | Ce qu'elle fait |
|---|---|---|
| Bilan radio | `fn_radioLink` | Marge en dB entre deux antennes : espace libre, relief et zone de Fresnel, feuillage, bâtiments, météo. Trois profils de précision (`fast`, `coarse`, `full`). |
| Relais | `fn_relayNet`, `fn_relayCond`, `fn_relayTower*` | Liste des relais (batterie, dégâts, brouillage, canal parmi 8, interférences, répéteurs en chaîne, saturation, santé), meilleur relais reçu, modulation adaptative à 8 paliers (MCS 0 à 7), carte de couverture, triangulation. |
| Radio de données | `fn_mpuLink` (réglage `mpu_real`, désactivé par défaut) | MPU5 allumée : batterie, canal 1 à 16, puissance 1 / 3 / 6 W, câble ou Wi-Fi vers le téléphone. Maillage du « chemin le plus large » vers une passerelle (relais, sinon MPU5 du chef, sinon soi-même), recalculé au plus toutes les 4 s dans un script planifié, lignes de vue en cache 30 s, brouilleurs. Le résultat devient le débit, la latence et les pertes du téléphone. |
| Qualité de liaison | `fn_linkQuality`, `fn_netCond` | Agrège tout (relais, MPU5, bâtiment, véhicule, météo, surcharge) en barres, kbit/s, latence, pertes. Ne lit que le dernier état du maillage. |
| Voix par le maillage | `fn_mpuVoice` (réglage `mpu_voice`, désactivé par défaut) | Pont radio (une radio par joueur reliée à sa MPU5). ACRE : fonction de signal chaînée. TFAR : multiplicateur de réception. Cache par paire 2 s. |
| Radio MPU5 / ACRE | `fn_mpu5` | Détecte l'objet MPU5, publie ses variables toutes les 2 s, lit radios, canaux, fréquences et émissions ACRE. |
| Messages | `fn_p2p*`, `fn_netSend` | Le SMS part selon le réseau **de l'expéditeur**, puis arrive par événement CBA ciblé au joueur nommé ; la remise dépend du réseau du destinataire (sinon mis en attente). Aucun chemin A vers B n'est évalué. |

### Ce qui n'est que de l'affichage

- **Talkgroups de l'app Wave Relay** : `COMSPEC_ATAK_Talkgroup` est posée et affichée. Une recherche dans tout le mod ne la trouve que dans `fn_pageWaveRelay` et `fn_waveRelayAction` : rien ne filtre par groupe.
- **« Connectés » et « En émission »** (onglet voix) : liste ce que publient les téléphones. `fn_mpu5` le dit lui-même : la portée radio n'est pas vérifiée.
- **Chiffrement** : n'existe pas. Seule occurrence : le libellé décoratif « COFFRE CHIFFRÉ · AES-256 » d'un thème du module de nœud de données.

### Défauts constatés

1. **Deux calculs de maillage différents.** L'app Wave Relay (`waveRelayAction "scan"`) fait son propre parcours en largeur avec la portée `MeshRange` ; les données et la voix utilisent `fn_mpuLink "mesh"` (chemin le plus large). L'écran peut dire autre chose que ce qui sert vraiment.
2. **Les messages ne suivent pas le maillage.** Entre deux joueurs, seul compte le réseau de chacun vers sa passerelle, pas un chemin A vers B.
3. **Voix sans coupure.** Dans le maillage, le signal est fixe : `max(1 - 0,04 x sauts ; 0,6)` et `-60 - 3 x sauts` dBm. Il ne dépend ni de la marge des liens, ni de la charge, et il n'y a pas de plafond de sauts.
4. **Chaque client calcule depuis sa propre vue.** Asymétries possibles (A entend B, B n'entend pas A) pendant quelques secondes. Au-delà de 30 nœuds, le parcours s'arrête à la première passerelle : les sauts des autres nœuds sont approximatifs.
5. **Variables d'autres joueurs lues sans bornes.** `COMSPEC_ATAK_MpuNode` (portée acceptée si nombre > 0, sans plafond), `COMSPEC_ATAK_Mpu5` (débit sans plafond ; portée et nom non typés dans `fn_relayNet`), `COMSPEC_ATAK_MpuVoice` (portée TFAR et sauts non typés dans `fn_mpuVoice`). Un client modifié ou bogué peut fausser le graphe de tout son camp. Cinq variables publiques par joueur au total (`Mpu5`, `VoiceNet`, `MpuNode`, `MpuVoice`, `Talkgroup`).
6. **TFAR** : un seul multiplicateur de réception par joueur, pour toutes ses radios. Une radio non pontée, sur la même fréquence, en profite aussi (limite déjà notée dans le code).
7. **Une autre fonction de signal ACRE peut écraser la nôtre.** `fn_mpuVoice` ne vérifie l'installation qu'une fois.

## 3. Modèle cible

### Ce que disent les sources publiques (et ce qui est un choix de jeu)

- MPU5 / Wave Relay (fiche technique Rev. R, page « capabilities ») : réseau maillé sans nœud maître, « rapid self-forming & self-healing », « no hop limits », vrai pair à pair, réseau de niveau 2, algorithmes multicast, 16 canaux voix surveillables, deux PTT actifs en même temps, 16 groupes de conversation en flux multicast (Opus), priorité de la voix de saut en saut, modulations OFDM de BPSK à 64-QAM.
- Présentation Wave Relay (cours JHU, ancienne) : pont de niveau 2 qui imite un commutateur, protocole de routage en arbre, métrique qui choisit les chemins de plus haut débit et les moins susceptibles de casser.
- **Aucune de ces sources ne donne de perte par saut ni de plafond de sauts chiffrés.** La baisse de qualité par saut et le plafond sont des **choix de jeu**, réglables. La coupure nette est cohérente avec une radio numérique à paliers de modulation, c'est une interprétation de notre part.

### Graphe de nœuds

- **Nœud** : joueur avec MPU5 allumée, MPU5 posée, relais, passerelle de véhicule, poste de commandement. Existe déjà (`fn_mpuLink "mesh"`).
- **Lien** : deux nœuds du même canal (et, plus tard, de la même clé réseau) dont le bilan radio `fn_radioLink "coarse"` donne une marge >= 0 dB. Le palier MCS existant donne le débit.
- **Pas de maître** : sans passerelle, le maillage d'équipe reste (modes `lead` et `self` existants). Chaque partition du graphe est un groupe connexe qui vit seul.
- **Sauts multiples** : chemin le plus large (existant), avec un **plafond de sauts** `H` (proposé : 6) et la perte de capacité par saut existante (x 0,8) et la latence par saut existante (`mpu_hop_ms`).
- **Se reforme seul** : un nœud qui tombe (mort, batterie vide, extinction, MPU5 retirée) marque le graphe « à refaire ». Reconstruction dans la seconde suivante, jamais plus d'une fois toutes les 2 s.

### Recalcul borné

- Un seul constructeur par client, un seul calcul à la fois (garde actuelle de 20 s conservée).
- Intervalle normal 4 s, 8 s au-delà de 40 nœuds, 2 s au plus quand ça bouge.
- Découpage en tranches (par exemple 150 tests de lien, puis `sleep 0`) : jamais de pic sur une image.
- Élagage par distance avant tout test de lien (déjà fait au-delà de 2 x la portée), cache des lignes de vue 30 s (existant), plafond de 64 nœuds (existant).
- Hystérésis : un lien ne tombe qu'après deux constats, il revient tout de suite. Évite le clignotement et les asymétries.
- Le résultat est écrit **une fois** dans un cache unique : par nœud, `[sauts, marge minimale, gravité]`. Écran, données et voix le lisent.

### Groupes de conversation

- 16 groupes, comme les groupes MPU5. Un groupe a un nom, et plus tard une clé.
- **Abonnement** : par la fréquence de la radio pontée (ACRE / TFAR : la fréquence donne le groupe, table définie par la mission) ou, sans mod radio, par choix explicite dans l'app Wave Relay. Écoute de plusieurs groupes, émission sur deux au plus.
- **Voix** : A entend B si un groupe est commun **et** si le chemin est assez bon **et** si les clés le permettent. Avec ACRE et TFAR, le filtre par fréquence est déjà fait par la radio : notre part se limite à la portée.
- **Données de groupe** (messages, plans partagés « GROUPE ») : livrées aux seuls abonnés joignables. Un envoi par émetteur avec la liste des destinataires, pas un envoi par destinataire.

### Gravité de coupure

Calculée par le constructeur pour chaque chemin, à partir de la marge du **pire** lien (seuils de marge = ceux des paliers MCS déjà dans le code, à recalibrer en jeu).

| Niveau | Condition | Voix | Données |
|---|---|---|---|
| 0 Nominal | marge >= 9 dB sur tous les liens, sauts <= H - 2 | signal fort | normal |
| 1 Dégradé | marge 4 à 9 dB, ou sauts H - 1 à H | signal réduit par paliers, latence en plus | débit réduit |
| 2 Limite | marge 0 à 4 dB | à-coups : passe / coupe par cycles de 1 à 3 s avec hystérésis (option) | pertes en rafales, envois en file |
| 3 Coupé | marge < 0 sur un lien, plus de H sauts, ou partition | **comportement d'origine de la radio** : portée normale, pas de souffle | hors réseau (existant) |

## 4. Chiffrement simulé (mécanique de jeu)

Inspiré de la fiche publique de la MPU5 (chiffrement CTR-AES-256, authentification HMAC-SHA-256, changement de clé par radio, effacement des clés). Dans le jeu, une « clé » est **une étiquette et un numéro d'époque**, par exemple `K-ALPHA`, époque 3. Aucun secret, aucun calcul cryptographique, rien dans les logs.

### Deux couches

1. **Clé de réseau** : décide qui est nœud du maillage. Remplace « le canal seul ». Sans la bonne clé de réseau, un téléphone n'est pas dans le graphe des autres (il ne relaie pas, il n'est pas relayé).
2. **Clé de groupe** : décide qui comprend un groupe. Un nœud qui a la clé de réseau mais pas celle du groupe relaie, mais ne comprend pas.

### Cycle de vie

- **Registre** sur le serveur : clé, nom, époque, état « compromise », liste des détenteurs. Défini par la mission (module ou script) ou par Zeus.
- **Chargement** : objet « chargeur de clés » (nouveau) ou action du poste de commandement. La clé arrive par événement ciblé vers le **seul** client concerné. Aucune clé dans une variable publique.
- **Ce que chaque client publie** : seulement un numéro de clé de réseau et son époque (petits entiers), dans son enregistrement de nœud.
- **Changement de clé à distance** : le poste de commandement ou Zeus passe l'époque à n+1. Un nœud ne l'applique que lorsqu'il est joint au maillage ; un nœud isolé garde l'ancienne époque et devient non autorisé jusqu'à son retour.
- **Effacement** : action « zéroïser » sur la MPU5, retire les clés du client.
- **Compromission** : une clé passe « compromise » quand un appareil qui la porte est ramassé par un joueur d'un autre camp, ou sur ordre de Zeus. Tant que l'époque n'est pas renouvelée, le détenteur ennemi est traité comme autorisé. Tout le camp voit l'alerte. Après le changement d'époque, il ne l'est plus.

### Nœud non autorisé : ce qui se passe

| Cas | Maillage | Voix | Messages de groupe |
|---|---|---|---|
| Sans clé de réseau valide | hors du graphe des autres | portée radio normale | non reçus |
| Clé de réseau, sans clé de groupe | relaie | pas de rallonge : portée normale | « [CHIFFRÉ · K-ALPHA] » |
| Époque périmée | traité comme sans clé de réseau | idem | idem |

### Ce que le joueur voit

- Dans l'app Wave Relay, par groupe : état de la clé (valide, à renouveler, absente, compromise).
- Un cadenas dans la barre d'état du téléphone ; une notification à chaque changement, effacement ou compromission.
- Pour le poste de commandement : liste des appareils « inconnus ou non autorisés » à portée.
- Voix : aucune « voix brouillée » n'est vérifiée côté TFAR. Pour ACRE, le système de langues (Babel) existe (voir section 5) ; il est optionnel et fait l'objet d'une décision. Sans lui, la voix chiffrée d'un nœud non autorisé est simplement **sans rallonge**.

### Limites assumées

Un client modifié peut tout contourner. C'est une mécanique de jeu (consignes, perte de matériel, rôle du poste de commandement), pas une protection.

## 5. Intégration TFAR et ACRE

### ACRE2 : vérifié dans le code installé

Version : ACRE2 2.14.0.1064 (Workshop 751965892), PBO lus en SQF clair.

| Élément | Constat |
|---|---|
| `acre_api_fnc_setCustomSignalFunc` | Existe, publique. Prend du code ; `[{}]` remet le calcul d'origine. |
| `acre_sys_signal_fnc_getSignal` | Appelle `acre_sys_signal_customSignalFunc` si elle existe, sinon `acre_sys_signal_fnc_getSignalCore`. |
| Arguments de la fonction de signal | `[fréquence MHz, puissance, ID radio réceptrice, ID radio émettrice]`. |
| Retour attendu | `[Px entre 0 et 1, signal en dBm]`. `-992` veut dire « pas encore calculé » et force un nouvel essai. |
| `acre_sys_signal_fnc_getSignalCore` | Existe, **interne** (« Public: No »). Mêmes 4 arguments, même retour. Le calcul réel est asynchrone et lu dans un cache. |
| `acre_sys_radio_fnc_getRadioObject` | Existe, **interne** (« Public: No »). ID de radio vers objet, ou `nil` si inconnu. |
| Qui appelle, et à quel rythme | Côté récepteur, `acre_sys_modes_fnc_sc_speaking`. Boucle ACRE toutes les 0,06 s, avec un cache de 0,2 s par couple (émetteur, radio réceptrice). Soit environ 5 appels par seconde et par couple, **uniquement pendant qu'un émetteur parle**. |
| Qui décide d'entendre ou non | ACRE, par fréquence et mode. Notre fonction ne change que la qualité du signal : elle ne peut pas faire entendre quelqu'un sur une autre fréquence. Les groupes ACRE sont donc des fréquences. |
| Événements CBA | `acre_startedSpeaking`, `acre_stoppedSpeaking`, `acre_remoteStartedSpeaking`, `acre_remoteStoppedSpeaking`. Le type de parole radio vaut 1 (constante `SPEAKING_TYPE_RADIO`). |
| Fonctions d'API utilisées par le mod | `getCurrentRadioList`, `getCurrentRadio`, `getBaseRadio`, `getRadioChannel`, `setRadioChannel`, `getDisplayName`, `getMultiPushToTalkAssignment`, `getPreset`, `getPresetChannelField` : toutes présentes. `acre_sys_data_fnc_dataEvent` présente (clés `frequencyTX`, `power` lues par ACRE lui-même). |
| Langues (Babel) | `acre_api_fnc_babelSetSpokenLanguages`, `babelSetSpeakingLanguage`, `babelAddLanguageType` existent ; `acre_sys_core_fnc_canUnderstand` marque la parole comme inintelligible. Sans langue déclarée, tout le monde comprend. À n'utiliser qu'avec accord : une mission qui a déjà son Babel serait perturbée. |

Réserve : les deux fonctions internes ne sont pas garanties stables d'une version d'ACRE à l'autre. Le code doit tester leur présence, comme il le fait déjà, et rendre le comportement d'origine sinon.

### TFAR : non vérifié à la source

TFAR (1.0 et 0.9.12, identifiants Workshop 894678801 et 620019431 à ma connaissance, absents du dossier Workshop) **n'est pas installé** sur la machine d'étude : seuls des modules tiers qui s'y rattachent y sont. Rien ci-dessous n'est donc lu dans TFAR lui-même.

Vu **indirectement** dans le code d'un module tiers (MapTac compat TFAR, Workshop 3818494613), qui écrit « as TFAR does » :
- `TFAR_fnc_getSwFrequency`, `TFAR_fnc_getLrFrequency`, `TFAR_fnc_getLrRadioProperty` (avec `"tf_range"`), `TFAR_fnc_radiosList`, `TFAR_fnc_lrRadiosList`, `TFAR_fnc_getChannelFrequency`, `TFAR_currentUnit`, `TFAR_globalRadioRangeCoef` (versions 1.x) ;
- les variables d'unité `tf_sendingDistanceMultiplicator` et `tf_receivingDistanceMultiplicator`, avec la règle « distance x facteur de réception <= portée » (facteur < 1 : entendu de plus loin). C'est bien le sens utilisé par `fn_mpuVoice`.
- `TFAR_fnc_activeSwRadio` et `TFAR_fnc_activeLrRadio` apparaissent aussi dans d'autres modules tiers.

**Non trouvé et non vérifié** : tout point d'accroche **par paire de radios** (qui permettrait de remplacer le multiplicateur global), tout événement de début d'émission côté TFAR, toute notion de « voix brouillée ». Le lot TFAR commence par installer TFAR et lire son code.

### Repli sans mod radio ou en cas d'échec

- Ni ACRE ni TFAR : pas de pont voix ; le maillage de données fonctionne ; les groupes se règlent dans l'app (déjà le cas pour l'onglet TALKGROUPS).
- Fonction de signal : toute valeur inattendue (type, nombre d'éléments, paire inconnue, radio non pontée, hors réglage) rend **exactement** le résultat d'ACRE. Déjà le cas, à conserver et à tester.
- TFAR : valeur d'origine du multiplicateur rendue dès la sortie du maillage, et une valeur posée par un autre script est gardée (déjà le cas).
- Réglages désactivés : comportement d'avant, sans effet de bord.

### L'exemple d'un joueur (idée seulement, code non repris)

Principe d'un joueur, présenté par lui comme non testé : remplacer le calcul d'ACRE, filtrer par groupe abonné, liaison directe sous 1 800 m, sinon un relais unique parmi les joueurs, -15 % de qualité par saut, coupure nette, retour au calcul d'origine si les unités sont introuvables.

Idées retenues : filtre par abonnement, coupure nette, retour à l'origine.

Défauts évités :
- **un seul saut** : le plan prévoit des chemins multiples avec plafond ;
- **parcours de tous les joueurs à chaque calcul de signal** : ACRE appelle environ 5 fois par seconde et par couple ; ici le graphe est précalculé et le signal ne fait qu'une lecture de cache ;
- **noms de fonctions non vérifiés** : `acre_sys_signal_fnc_getSignalCore` et `acre_sys_radio_fnc_getRadioObject` existent bien, mais sont internes ; testés avant usage ;
- **`_transmitterPower` non cité** : ACRE passe quatre arguments, on ne déclare que ceux qu'on lit, avec leur type.

## 6. Coût en performance et en multijoueur

### Performance

| Zone | Coût visé | Comment |
|---|---|---|
| Chemin de signal ACRE | une lecture de table (O(1)), environ 5 fois par seconde et par couple en émission | le signal ne calcule plus rien ; le constructeur remplit le cache |
| Constructeur du graphe | à mesurer ; objectif : moins de 3 ms de moyenne par tick à 60 nœuds, jamais plus de 1 image de retard | tranches, élagage, caches, un calcul à la fois, intervalle adaptatif |
| Écran Wave Relay | aucun calcul propre | lit le même cache que les données et la voix (supprime le second calcul de `scan`) |
| TFAR | une écriture de variable d'unité, seulement quand elle change | comme aujourd'hui |

Les compteurs existants (`fn_perfAcc` : « radio de données », « voix par le maillage ») servent de mesure. Aucune mesure n'a été faite : ce sont des objectifs à confirmer en jeu.

### Multijoueur

- **Variables publiques** : de cinq par joueur à **une** (`COMSPEC_ATAK_MeshNode`), à valeurs arrondies (classes de portée et de puissance plutôt que des mètres), publiée seulement sur changement et au plus une fois toutes les 2 s. Les anciennes restent le temps de la transition (lot 8 pour les retirer).
- **Lecture sûre** : une seule fonction lit l'enregistrement d'un autre joueur, vérifie type et taille, borne chaque valeur (portée 0 à 20 000 m, débit 0 à 100 Mbit/s, sauts 0 à H, texte tronqué), et rend un enregistrement vide sinon. Tous les consommateurs passent par elle.
- **Clés** : événements ciblés, jamais de diffusion. Table d'époques (au plus 16 entrées) diffusée seulement quand elle change.
- **Serveur** : registre de clés et table d'époques, aucun calcul par image. Un serveur dédié ne calcule pas le signal ACRE (c'est côté client).
- **Cohérence** : chaque client calcule son graphe à partir des mêmes données publiées ; l'hystérésis limite les écarts. Un calcul d'autorité sur le serveur est une alternative (décision 4).
- **Arrivée en cours de partie** : à vérifier en test (variables relues, clés renvoyées par le serveur à la connexion).

## 7. Lots livrables

Un lot = une PR, sur main, jamais fusionnée sans le propriétaire. Le numéro de version et `main.pbo` sont partagés (`/livrer-version`) : une PR native à la fois, dans l'ordre. Chaque PR liste ses tests en jeu. Je ne peux pas lancer Arma 3 : les tests ci-dessous sont à faire par l'équipe, **TFAR d'abord, ACRE ensuite** (sauf lots sans effet sur la voix).

| Lot | Contenu | Fichiers principaux | Tests en jeu à plusieurs |
|---|---|---|---|
| 1. Lecture sûre | Fonction unique de lecture des variables d'autres joueurs, types et bornes ; aucun changement visible. | `fn_mpu5`, `fn_mpuLink`, `fn_mpuVoice`, `fn_relayNet`, nouveau `fn_meshRead` | 2 à 4 joueurs : maillage et voix inchangés (TFAR puis ACRE). Un client publie des valeurs absurdes avec la console : les autres ne bougent pas. |
| 2. Graphe unique et borné | Un constructeur pour l'écran, les données et la voix ; tranches, élagage, hystérésis, plafond de sauts ; compteurs de performance. | `fn_mpuLink`, `fn_waveRelayAction`, nouveau `fn_meshGraph` | 8 à 10 joueurs en chaîne A-B-C-D : éteindre ou tuer B, reconstruction en moins de 8 s ; l'app Wave Relay et l'app Réseau donnent les mêmes sauts ; pas de pic d'image ; avec TFAR puis ACRE puis sans mod radio. |
| 3. Gravité de coupure (ACRE) | Niveaux 0 à 3 selon la marge ; remplace le signal fixe ; réinstallation si une autre fonction de signal nous écrase. | `fn_mpuVoice`, `fn_linkQuality` | ACRE, 3 joueurs hors portée directe : s'éloigner pas à pas, niveaux puis coupure nette, retour à la portée normale ; 2 émetteurs en même temps ; autre mod de signal présent si possible. TFAR : aucune régression. |
| 4. TFAR | **D'abord** installer TFAR et lire son code. Si un point par paire existe, l'utiliser ; sinon garder le multiplicateur mais l'appliquer seulement pendant une émission pontée, et documenter la limite. | `fn_mpuVoice` | TFAR 4 joueurs, radio portative et longue portée, chaîne hors portée directe, une radio non pontée sur la même fréquence (limite connue), valeur d'origine rendue en sortie. ACRE : non-régression. |
| 5. Groupes de conversation | 16 groupes, abonnement par fréquence pontée ou par l'app, données de groupe aux abonnés joignables. Remplace le talkgroup décoratif. | nouveau `fn_meshGroups`, `fn_pageWaveRelay`, `fn_waveRelayAction`, `fn_p2pSend` | 6 joueurs, 3 groupes, abonnements changés en cours de partie ; TFAR puis ACRE : fréquence vers groupe, hors abonnement pas de rallonge ; message de groupe reçu par les seuls abonnés. |
| 6. Chiffrement, données | Registre serveur, chargeur de clés, zéroïsation, états à l'écran, messages « [CHIFFRÉ] ». | nouveau `fn_meshKeys`, app Wave Relay, objets | 4 joueurs dont 1 sans clé : absent du graphe ou message chiffré ; zéroïsation ; reconnexion en cours de partie ; hébergé et dédié. |
| 7. Chiffrement, voix | Voix chiffrée sans rallonge pour les non autorisés (ACRE : option Babel selon décision), changement de clé à distance, compromission. | `fn_mpuVoice`, `fn_meshKeys`, actions Zeus / poste | 2 camps ; clé changée pendant qu'un nœud est isolé ; appareil ramassé par l'autre camp puis nouveau changement de clé ; TFAR puis ACRE. |
| 8. Nettoyage et doc | Retrait des anciennes variables publiques, réglages et profils (Milsim, Standard, Arcade), notice joueur, en-tête du changelog. | réglages, `CHANGELOG-ATAK.md` | Serveur dédié, 10 joueurs, 30 minutes : voix, données, clés, arrivées et départs ; mesure de performance. |

## 8. Décisions à prendre par le propriétaire

1. **Compromission des clés** : automatique quand un appareil est ramassé par l'autre camp, ou seulement sur ordre de Zeus / du poste de commandement ? (recommandé : automatique, avec possibilité de la désactiver)
2. **Voix chiffrée sous ACRE** : simple absence de rallonge, ou voix brouillée via Babel (risque de conflit avec les missions qui l'utilisent) ? TFAR n'aura que l'absence de rallonge tant qu'aucun équivalent n'est vérifié. (recommandé : absence de rallonge d'abord)
3. **Plafond de sauts et perte par saut** : 6 sauts et la perte actuelle (x 0,8 en débit) conviennent-ils ? Les sources publiques ne chiffrent rien, c'est un réglage de jeu.
4. **Où calculer le graphe** : sur chaque client (proposé, aucun coût serveur, léger risque d'écarts) ou sur le serveur (une seule vérité, coût serveur et diffusion) ?
5. **Valeur par défaut** : `mpu_real` et `mpu_voice` restent-ils désactivés tant que les lots 1 à 5 ne sont pas validés en jeu ?
6. **Coupure par à-coups (niveau 2)** : voulue pour la voix, ou passage direct de dégradé à coupé ?
7. **Groupes** : 16 comme la MPU5 ? Table fréquence vers groupe fournie par la mission, ou déduite automatiquement ?
8. **Portée du filtre de groupe côté données** : quelles apps (messages, plans partagés, marqueurs, position) ?
9. **TFAR** : peut-on installer TFAR 1.0 sur une machine de développement pour le lot 4 ? Sans cela, le lot 4 ne peut pas démarrer.
10. **Ordre des lots** : le lot 3 (ACRE) passe-t-il avant le lot 4 (TFAR), comme proposé, puisque TFAR dépend d'une vérification préalable ?

## 9. Sources et ce qui n'a pas pu être lu

| Source | Résultat |
|---|---|
| `persistentsystems.com/mpu5-capabilities/` | Lue. Pas de chiffrement ni de chiffres de débit ou de portée sur cette page. |
| Fiche technique MPU5 Rev. R (PDF) | Lue après extraction du texte. Chiffrement, changement de clé à distance, effacement, modulations, multicast. |
| Présentation Wave Relay de JHU (PDF) | Lue après extraction du texte. Ancienne (cartes 802.11b), partielle. |
| `unmannedsystemstechnology.com` (vidéo MPU5 Android) | **Non lue** : le serveur répond « 403 Forbidden ». Rien n'en est tiré. |
| Wiki ACRE2, traitement du signal personnalisé | Lue en complément ; cohérente avec le code installé. |
| Code ACRE2 2.14.0.1064 | Lu (PBO, SQF clair). |
| Code TFAR | **Non lu : TFAR n'est pas installé.** Voir section 5. |
