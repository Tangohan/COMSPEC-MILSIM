# Réseau maillé du téléphone ATAK : plan technique

Statut : plan, révision 2 (ajouts : réalisme radio, guerre électronique, données ATAK, outils ; voir sections 3.6, 4 bis et 6 bis, lots 9 à 12, décisions 11 à 17 ; TFAR vérifié dans son code source). Tous les lots codés, aucun testé en jeu (section 10). Périmètre : `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/` (`network/`, `core/fn_linkQuality.sqf`, `pages/fn_pageWaveRelay.sqf`, `pages/fn_waveRelayAction.sqf`).

Objectif du propriétaire : un réseau plus poussé, façon MPU5 / Wave Relay, un chiffrement simulé comme un vrai ATAK, et un maillage voix TFAR / ACRE via le téléphone et les relais.

Le chiffrement décrit ici est une mécanique de jeu (étiquettes de clés, états, conséquences). Il n'utilise aucune cryptographie réelle, ne protège rien, et ne doit jamais être présenté comme une sécurité.

## 1. En bref

- Le socle existe déjà et marche : bilan radio en dB, relais, batterie, brouillage, maillage par canal, pont voix ACRE / TFAR (opt-in, désactivé par défaut).
- Ce qui manque : un seul graphe partagé par l'écran, les données et la voix ; des coupures nettes selon la qualité des liens ; de vrais groupes de conversation ; du chiffrement ; des lectures de variables d'autres joueurs avec type et bornes.
- ACRE2 : les points d'accroche utilisés sont vérifiés dans le code de la version installée (2.14.0.1064).
- TFAR : vérifié dans le code source (dépôt GitHub michail-nikolaev/task-force-arma-3-radio, branche master, commit fce977f du 23/09/2026, version 1.-1.0.341). TFAR n'est toujours pas installé sur la machine d'étude : le comportement en jeu reste à confirmer, mais les points d'accroche existent (section 5).

## 2. État actuel

### Ce qui est simulé pour de vrai

| Brique | Fichier | Ce qu'elle fait |
|---|---|---|
| Bilan radio | `fn_radioLink` | Marge en dB entre deux antennes : espace libre, relief et zone de Fresnel, feuillage, bâtiments, météo. Trois profils de précision (fast, coarse, full). |
| Relais | `fn_relayNet`, `fn_relayCond`, `fn_relayTower*` | Liste des relais (batterie, dégâts, brouillage, canal parmi 8, interférences, répéteurs en chaîne, saturation, santé), meilleur relais reçu, modulation adaptative à 8 paliers (MCS 0 à 7), carte de couverture, triangulation. |
| Radio de données | `fn_mpuLink` (réglage `mpu_real`, désactivé par défaut) | MPU5 allumée : batterie, canal 1 à 16, puissance 1 / 3 / 6 W, câble ou Wi-Fi vers le téléphone. Maillage du « chemin le plus large » vers une passerelle (relais, sinon MPU5 du chef, sinon soi-même), recalculé au plus toutes les 4 s dans un script planifié, lignes de vue en cache 30 s, brouilleurs. Le résultat devient le débit, la latence et les pertes du téléphone. |
| Qualité de liaison | `fn_linkQuality`, `fn_netCond` | Agrège tout (relais, MPU5, bâtiment, véhicule, météo, surcharge) en barres, kbit/s, latence, pertes. Ne lit que le dernier état du maillage. |
| Voix par le maillage | `fn_mpuVoice` (réglage `mpu_voice`, désactivé par défaut) | Pont radio (une radio par joueur reliée à sa MPU5). ACRE : fonction de signal chaînée. TFAR : multiplicateur de réception. Cache par paire 2 s. |
| Radio MPU5 / ACRE | `fn_mpu5` | Détecte l'objet MPU5, publie ses variables toutes les 2 s, lit radios, canaux, fréquences et émissions ACRE. |
| Messages | `fn_p2p*`, `fn_netSend` | Le SMS part selon le réseau de l'expéditeur, puis arrive par événement CBA ciblé au joueur nommé ; la remise dépend du réseau du destinataire (sinon mis en attente). Aucun chemin A vers B n'est évalué. |

### Ce qui n'est que de l'affichage

- Talkgroups de l'app Wave Relay : `COMSPEC_ATAK_Talkgroup` est posée et affichée. Une recherche dans tout le mod ne la trouve que dans `fn_pageWaveRelay` et `fn_waveRelayAction` : rien ne filtre par groupe.
- « Connectés » et « En émission » (onglet voix) : liste ce que publient les téléphones. `fn_mpu5` le dit lui-même : la portée radio n'est pas vérifiée.
- Chiffrement : n'existe pas. Seule occurrence : le libellé décoratif « COFFRE CHIFFRÉ · AES-256 » d'un thème du module de nœud de données.

### Défauts constatés

1. Deux calculs de maillage différents. L'app Wave Relay (`waveRelayAction "scan"`) fait son propre parcours en largeur avec la portée `MeshRange` ; les données et la voix utilisent `fn_mpuLink "mesh"` (chemin le plus large). L'écran peut dire autre chose que ce qui sert vraiment.
2. Les messages ne suivent pas le maillage. Entre deux joueurs, seul compte le réseau de chacun vers sa passerelle, pas un chemin A vers B.
3. Voix sans coupure. Dans le maillage, le signal est fixe : max(1 - 0,04 x sauts ; 0,6) et -60 - 3 x sauts dBm. Il ne dépend ni de la marge des liens, ni de la charge, et il n'y a pas de plafond de sauts.
4. Chaque client calcule depuis sa propre vue. Asymétries possibles (A entend B, B n'entend pas A) pendant quelques secondes. Au-delà de 30 nœuds, le parcours s'arrête à la première passerelle : les sauts des autres nœuds sont approximatifs.
5. Variables d'autres joueurs lues sans bornes. `COMSPEC_ATAK_MpuNode` (portée acceptée si nombre > 0, sans plafond), `COMSPEC_ATAK_Mpu5` (débit sans plafond ; portée et nom non typés dans `fn_relayNet`), `COMSPEC_ATAK_MpuVoice` (portée TFAR et sauts non typés dans `fn_mpuVoice`). Un client modifié ou bogué peut fausser le graphe de tout son camp. Cinq variables publiques par joueur au total (`Mpu5`, `VoiceNet`, `MpuNode`, `MpuVoice`, `Talkgroup`).
6. TFAR : un seul multiplicateur de réception par joueur, pour toutes ses radios. Une radio non pontée, sur la même fréquence, en profite aussi (limite déjà notée dans le code).
7. Une autre fonction de signal ACRE peut écraser la nôtre. `fn_mpuVoice` ne vérifie l'installation qu'une fois.

## 3. Modèle cible

### Ce que disent les sources publiques (et ce qui est un choix de jeu)

- MPU5 / Wave Relay (fiche technique Rev. R, page « capabilities ») : réseau maillé sans nœud maître, « rapid self-forming & self-healing », « no hop limits », vrai pair à pair, réseau de niveau 2, algorithmes multicast, 16 canaux voix surveillables, deux PTT actifs en même temps, 16 groupes de conversation en flux multicast (Opus), priorité de la voix de saut en saut, modulations OFDM de BPSK à 64-QAM.
- Présentation Wave Relay (cours JHU, ancienne) : pont de niveau 2 qui imite un commutateur, protocole de routage en arbre, métrique qui choisit les chemins de plus haut débit et les moins susceptibles de casser.
- Aucune de ces sources ne donne de perte par saut ni de plafond de sauts chiffrés. La baisse de qualité par saut et le plafond sont des choix de jeu, réglables. La coupure nette est cohérente avec une radio numérique à paliers de modulation, c'est une interprétation de notre part.

### Graphe de nœuds

- Nœud : joueur avec MPU5 allumée, MPU5 posée, relais, passerelle de véhicule, poste de commandement. Existe déjà (`fn_mpuLink "mesh"`).
- Lien : deux nœuds du même canal (et, plus tard, de la même clé réseau) dont le bilan radio `fn_radioLink "coarse"` donne une marge >= 0 dB. Le palier MCS existant donne le débit.
- Pas de maître : sans passerelle, le maillage d'équipe reste (modes lead et self existants). Chaque partition du graphe est un groupe connexe qui vit seul.
- Sauts multiples : chemin le plus large (existant), avec un plafond de sauts H (proposé : 6) et la perte de capacité par saut existante (x 0,8) et la latence par saut existante (`mpu_hop_ms`).
- Se reforme seul : un nœud qui tombe (mort, batterie vide, extinction, MPU5 retirée) marque le graphe « à refaire ». Reconstruction dans la seconde suivante, jamais plus d'une fois toutes les 2 s.

### Recalcul borné

- Un seul constructeur par client, un seul calcul à la fois (garde actuelle de 20 s conservée).
- Intervalle normal 4 s, 8 s au-delà de 40 nœuds, 2 s au plus quand ça bouge.
- Découpage en tranches (par exemple 150 tests de lien, puis `sleep 0`) : jamais de pic sur une image.
- Élagage par distance avant tout test de lien (déjà fait au-delà de 2 x la portée), cache des lignes de vue 30 s (existant), plafond de 64 nœuds (existant).
- Hystérésis : un lien ne tombe qu'après deux constats, il revient tout de suite. Évite le clignotement et les asymétries.
- Le résultat est écrit une fois dans un cache unique : par nœud, [sauts, marge minimale, gravité]. Écran, données et voix le lisent.

### Groupes de conversation

- 16 groupes, comme les groupes MPU5. Un groupe a un nom, et plus tard une clé.
- Abonnement : par la fréquence de la radio pontée (ACRE / TFAR : la fréquence donne le groupe, table définie par la mission) ou, sans mod radio, par choix explicite dans l'app Wave Relay. Écoute de plusieurs groupes, émission sur deux au plus.
- Voix : A entend B si un groupe est commun et si le chemin est assez bon et si les clés le permettent. Avec ACRE et TFAR, le filtre par fréquence est déjà fait par la radio : notre part se limite à la portée.
- Données de groupe (messages, plans partagés « GROUPE ») : livrées aux seuls abonnés joignables. Un envoi par émetteur avec la liste des destinataires, pas un envoi par destinataire.

### Gravité de coupure

Calculée par le constructeur pour chaque chemin, à partir de la marge du pire lien (seuils de marge = ceux des paliers MCS déjà dans le code, à recalibrer en jeu).

| Niveau | Condition | Voix | Données |
|---|---|---|---|
| 0 Nominal | marge >= 9 dB sur tous les liens, sauts <= H - 2 | signal fort | normal |
| 1 Dégradé | marge 4 à 9 dB, ou sauts H - 1 à H | signal réduit par paliers, latence en plus | débit réduit |
| 2 Limite | marge 0 à 4 dB | à-coups : passe / coupe par cycles de 1 à 3 s avec hystérésis (option) | pertes en rafales, envois en file |
| 3 Coupé | marge < 0 sur un lien, plus de H sauts, ou partition | comportement d'origine de la radio : portée normale, pas de souffle | hors réseau (existant) |

### 3.6 Réalisme supplémentaire (révision 2)

Tout ce qui suit est un choix de jeu inspiré du comportement général des radios maillées ; aucune valeur n'est tirée d'une fiche MPU5. Chaque point est un réglage, désactivable.

**Capacité et partage du canal**

- Perte par saut réaliste. Une radio à une seule voie radio émet et relaie sur le même canal : elle ne fait pas les deux en même temps. En pratique, la capacité d'un chemin sur un même canal chute d'environ moitié à chaque saut, puis se stabilise vers un tiers quand les liens éloignés ne se gênent plus. Proposition : remplacer le x 0,8 fixe par une courbe réglable capacité_chemin = min(débit_lien) x f(sauts) avec f = 1 ; 0,5 ; 0,33 ; 0,33…. Le x 0,8 reste disponible en profil « Arcade ».
- Temps d'antenne partagé. Les nœuds d'un même canal à portée les uns des autres se partagent le temps d'émission. Le constructeur compte les voisins par canal et divise la capacité disponible. Effet de jeu : 20 MPU5 dans la même cour saturent le canal ; changer de canal (déjà 1 à 16) a un vrai intérêt.
- Largeur de canal. Nouveau réglage par MPU5 : étroit / normal / large. Étroit = plus de portée (gain de marge de quelques dB, réglable), moins de débit ; large = l'inverse. Donne un vrai arbitrage au chef de section.
- Priorité voix. La voix passe avant les données : sous saturation, les données ralentissent d'abord ; la voix ne se dégrade qu'au-delà. Conforme à la « priorité de la voix de saut en saut » de la fiche, en version simplifiée.

**Antenne et position**

- Hauteur d'antenne. Debout, accroupi, couché changent la hauteur d'antenne passée à `fn_radioLink` (lecture de la posture, pas de calcul nouveau). Couché derrière un muret, on perd la liaison : c'est réaliste et lisible.
- Masque du corps. Une perte de quelques dB quand le porteur est entre son antenne et le correspondant (angle dos / correspondant). Réglable, désactivée par défaut.
- Mât et antenne de véhicule. Les passerelles de véhicule gagnent en hauteur ; un « mât télescopique » (objet à déployer, 30 s) augmente la hauteur d'un poste fixe. Le mât se voit de loin.

**Énergie**

- La consommation dépend de la puissance d'émission et du trafic relayé : un nœud central qui relaie toute la section se vide plus vite. L'app Wave Relay affiche l'autonomie estimée et la part de trafic relayé.

**Données ATAK (côté téléphone)**

- Positions périmées. Comme dans ATAK (heure de péremption des messages de position), un marqueur ami dont la dernière position date de plus de N secondes passe en gris, puis disparaît. Aujourd'hui une coupure réseau ne se voit pas sur la carte : c'est le point de réalisme le plus visible pour les joueurs.
- Cadence de position adaptative. Position envoyée plus souvent en mouvement, moins à l'arrêt ; ralentie automatiquement en niveau 1 ou 2. Économise le réseau et donne une carte qui « traîne » quand le lien est mauvais.
- Fichiers en morceaux. Les gros envois (photos, paquets de mission) partent par blocs, reprennent après coupure, et affichent leur progression. Une photo en niveau 2 met du temps ; en niveau 3 elle attend.
- Chemin A vers B pour les messages (corrige le défaut 2) : un message entre deux joueurs du même groupe connexe passe par le maillage, même sans passerelle.

**GPS**

- Erreur de position réglable (quelques mètres) et dégradation sous brouillage GPS (voir 4 bis) : le marqueur dérive, la précision affichée baisse. Sans brouillage, aucun changement.

## 4. Chiffrement simulé (mécanique de jeu)

Inspiré de la fiche publique de la MPU5 (chiffrement CTR-AES-256, authentification HMAC-SHA-256, changement de clé par radio, effacement des clés). Dans le jeu, une « clé » est une étiquette et un numéro d'époque, par exemple K-ALPHA, époque 3. Aucun secret, aucun calcul cryptographique, rien dans les logs.

### Deux couches

- Clé de réseau : décide qui est nœud du maillage. Remplace « le canal seul ». Sans la bonne clé de réseau, un téléphone n'est pas dans le graphe des autres (il ne relaie pas, il n'est pas relayé).
- Clé de groupe : décide qui comprend un groupe. Un nœud qui a la clé de réseau mais pas celle du groupe relaie, mais ne comprend pas.

### Cycle de vie

- Registre sur le serveur : clé, nom, époque, état « compromise », liste des détenteurs. Défini par la mission (module ou script) ou par Zeus.
- Chargement : objet « chargeur de clés » (nouveau) ou action du poste de commandement. La clé arrive par événement ciblé vers le seul client concerné. Aucune clé dans une variable publique.
- Ce que chaque client publie : seulement un numéro de clé de réseau et son époque (petits entiers), dans son enregistrement de nœud.
- Changement de clé à distance : le poste de commandement ou Zeus passe l'époque à n+1. Un nœud ne l'applique que lorsqu'il est joint au maillage ; un nœud isolé garde l'ancienne époque et devient non autorisé jusqu'à son retour.
- Effacement : action « zéroïser » sur la MPU5, retire les clés du client.
- Compromission : une clé passe « compromise » quand un appareil qui la porte est ramassé par un joueur d'un autre camp, ou sur ordre de Zeus. Tant que l'époque n'est pas renouvelée, le détenteur ennemi est traité comme autorisé. Tout le camp voit l'alerte. Après le changement d'époque, il ne l'est plus.

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
- Voix : aucune « voix brouillée » n'est vérifiée côté TFAR. Pour ACRE, le système de langues (Babel) existe (voir section 5) ; il est optionnel et fait l'objet d'une décision. Sans lui, la voix chiffrée d'un nœud non autorisé est simplement sans rallonge.

### Limites assumées

Un client modifié peut tout contourner. C'est une mécanique de jeu (consignes, perte de matériel, rôle du poste de commandement), pas une protection.

## 4 bis. Guerre électronique (révision 2)

Le chiffrement cache le contenu, pas l'émission. C'est ce qui rend l'électronique intéressante en jeu : émettre, c'est se montrer.

- Signature d'émission. Chaque nœud a une « activité radio » (puissance x trafic), déjà presque disponible via le constructeur. Elle est lue par les outils ennemis ci-dessous, jamais affichée en clair aux amis comme une menace.
- Goniométrie. Objet « goniomètre » portable : donne une direction approximative (erreur de plusieurs degrés, plus grande à faible marge) vers les émetteurs actifs d'un canal. Deux relevés de positions différentes permettent une triangulation (la fonction de triangulation des relais existe déjà et peut être réutilisée).
- Analyseur de spectre. App ou objet : occupation des 16 canaux, niveau, présence d'un brouilleur. Ne révèle ni le camp, ni le contenu chiffré.
- Brouillage plus fin. Aujourd'hui un brouilleur gêne une zone. Ajouts : brouillage d'un canal (contrable en changeant de canal) ou large bande (puissant, court, très visible au goniomètre). Brouillage GPS séparé (voir 3.6).
- Discrétion d'émission. Modes de la MPU5 : normal, puissance réduite, écoute seule (ne relaie pas, n'émet pas, reçoit). Le chef peut imposer un mode à la section depuis l'app Wave Relay.
- Mitigation automatique. Option : sous brouillage d'un canal, le groupe connexe bascule ensemble sur un canal de secours prédéfini après quelques secondes (choix de jeu, fréquence et délai réglables).

Coût : la goniométrie et l'analyseur lisent le cache du constructeur et l'activité publiée ; aucun calcul de lien supplémentaire, aucune nouvelle variable publique hors une valeur d'activité arrondie dans `COMSPEC_ATAK_MeshNode`.

## 5. Intégration TFAR et ACRE

### ACRE2 : vérifié dans le code installé

Version : ACRE2 2.14.0.1064 (Workshop 751965892), PBO lus en SQF clair.

| Élément | Constat |
|---|---|
| `acre_api_fnc_setCustomSignalFunc` | Existe, publique. Prend du code ; `[{}]` remet le calcul d'origine. |
| `acre_sys_signal_fnc_getSignal` | Appelle `acre_sys_signal_customSignalFunc` si elle existe, sinon `acre_sys_signal_fnc_getSignalCore`. |
| Arguments de la fonction de signal | [fréquence MHz, puissance, ID radio réceptrice, ID radio émettrice]. |
| Retour attendu | [Px entre 0 et 1, signal en dBm]. -992 veut dire « pas encore calculé » et force un nouvel essai. |
| `acre_sys_signal_fnc_getSignalCore` | Existe, interne (« Public: No »). Mêmes 4 arguments, même retour. Le calcul réel est asynchrone et lu dans un cache. |
| `acre_sys_radio_fnc_getRadioObject` | Existe, interne (« Public: No »). ID de radio vers objet, ou nil si inconnu. |
| Qui appelle, et à quel rythme | Côté récepteur, `acre_sys_modes_fnc_sc_speaking`. Boucle ACRE toutes les 0,06 s, avec un cache de 0,2 s par couple (émetteur, radio réceptrice). Soit environ 5 appels par seconde et par couple, uniquement pendant qu'un émetteur parle. |
| Qui décide d'entendre ou non | ACRE, par fréquence et mode. Notre fonction ne change que la qualité du signal : elle ne peut pas faire entendre quelqu'un sur une autre fréquence. Les groupes ACRE sont donc des fréquences. |
| Événements CBA | `acre_startedSpeaking`, `acre_stoppedSpeaking`, `acre_remoteStartedSpeaking`, `acre_remoteStoppedSpeaking`. Le type de parole radio vaut 1 (constante `SPEAKING_TYPE_RADIO`). |
| Fonctions d'API utilisées par le mod | `getCurrentRadioList`, `getCurrentRadio`, `getBaseRadio`, `getRadioChannel`, `setRadioChannel`, `getDisplayName`, `getMultiPushToTalkAssignment`, `getPreset`, `getPresetChannelField` : toutes présentes. `acre_sys_data_fnc_dataEvent` présente (clés `frequencyTX`, `power` lues par ACRE lui-même). |
| Langues (Babel) | `acre_api_fnc_babelSetSpokenLanguages`, `babelSetSpeakingLanguage`, `babelAddLanguageType` existent ; `acre_sys_core_fnc_canUnderstand` marque la parole comme inintelligible. Sans langue déclarée, tout le monde comprend. À n'utiliser qu'avec accord : une mission qui a déjà son Babel serait perturbée. |

Réserve : les deux fonctions internes ne sont pas garanties stables d'une version d'ACRE à l'autre. Le code doit tester leur présence, comme il le fait déjà, et rendre le comportement d'origine sinon.

### TFAR : vérifié dans le code source (révision 2)

Source : dépôt GitHub michail-nikolaev/task-force-arma-3-radio, master, commit fce977f (23/09/2026), `script_version.hpp` = 1.-1.0 build 341 (le fichier `current_version.txt` dit encore 0.9.12, il est périmé). Lu en SQF ; l'extension TeamSpeak (C++) n'a pas été lue.

| Élément | Constat (fichier) |
|---|---|
| Événements | `TFAR_fnc_addEventHandler` (public) : [id, nom, code, unité facultative]. Événements utiles : `OnBeforeTangent`, `OnTangent`, `OnSpeak`, `OnRadioReceive`, `OnRadiosReceived`, `OnSWchannelSet`, `OnLRchannelSet`, `OnFrequencyChanged`. |
| Début / fin d'émission | `OnBeforeTangent` puis `OnTangent`, arguments [unité, radio, type (0 portative, 1 longue portée), canal additionnel, appui (vrai) / relâche (faux)] (`fnc_doSRTransmit`, `fnc_doLRTransmit` et leurs `…End`). Le point « aucun événement de début d'émission » du plan est levé. |
| Portée de l'émetteur | Calculée à chaque appui : `tf_range` de la radio x `TFAR_fnc_getTransmittingDistanceMultiplicator` (= variable d'unité `tf_sendingDistanceMultiplicator`), puis envoyée au plugin dans la commande `TANGENT PRESSED`. `OnBeforeTangent` est déclenché avant ce calcul (`fnc_doSRTransmit` l. 44 puis l. 61). |
| Multiplicateur de réception | `tf_receivingDistanceMultiplicator` x (1 / `TFAR_globalRadioRangeCoef`), une valeur par unité pour toutes ses radios, envoyée par `TFAR_fnc_sendFrequencyInfo` toutes les 2 s environ (plus vite après un changement de réglage). |
| Code de chiffrement | `TFAR_fnc_setSwRadioCode` / `setLrRadioCode` (publics, « encryption code » par radio). Le code est collé à la fréquence envoyée au plugin (%1%2 = fréquence + code) : deux radios de codes différents ne s'entendent pas du tout, même sur la même fréquence. Codes par camp par défaut : `tf_west_radio_code`, etc. |
| Calcul par paire | Aucun en SQF : la décision d'entendre se fait dans le plugin TeamSpeak, à partir de la distance, de la portée de l'émetteur, du multiplicateur du récepteur et du relief. Pas de fonction de signal par paire comme ACRE. |

**Conséquences pour le plan**

- Côté émetteur, par radio et par émission : possible. Dans `OnBeforeTangent`, si la radio utilisée est pontée et que le nœud est dans le maillage, on pose `tf_sendingDistanceMultiplicator` ; sinon on remet la valeur d'origine. La portée est figée pour la durée de l'émission. Cela règle la moitié du défaut 6 : une radio non pontée de l'émetteur n'en profite plus.
- Côté récepteur : reste global. Une radio non pontée du récepteur, sur la même fréquence, profite toujours du multiplicateur. Limite conservée et documentée. On peut la réduire en n'appliquant le multiplicateur de réception que lorsqu'un émetteur ponté joignable parle (`OnSpeak` / `OnRadioReceive`), avec un retard possible d'environ 2 s dû à l'envoi périodique.
- Même portée pour tous les auditeurs. Le multiplicateur d'émission vaut pour tous les récepteurs ; la gravité de coupure (section 3) ne peut donc être appliquée côté émetteur qu'en prenant le meilleur chemin vers le groupe, puis affinée côté récepteur par son propre multiplicateur. Moins fin qu'ACRE, mais cohérent.
- Chiffrement de groupe sous TFAR : un vrai levier. Clé de groupe → code TFAR de la radio pontée (étiquette + époque, par exemple K-ALPHA-3). Un nœud sans la bonne clé ou d'époque périmée n'entend rien sur ce groupe, ce qui est plus fort que « pas de rallonge ». Attention : cela coupe aussi la portée radio directe, pas seulement le maillage, et peut entrer en conflit avec une mission qui gère déjà ses codes (décision 17).

Vu aussi indirectement, avant la lecture du code, dans le code d'un module tiers (MapTac compat TFAR, Workshop 3818494613), qui écrit « as TFAR does » :

- `TFAR_fnc_getSwFrequency`, `TFAR_fnc_getLrFrequency`, `TFAR_fnc_getLrRadioProperty` (avec `"tf_range"`), `TFAR_fnc_radiosList`, `TFAR_fnc_lrRadiosList`, `TFAR_fnc_getChannelFrequency`, `TFAR_currentUnit`, `TFAR_globalRadioRangeCoef` (versions 1.x) ;
- les variables d'unité `tf_sendingDistanceMultiplicator` et `tf_receivingDistanceMultiplicator`, avec la règle « distance x facteur de réception <= portée » (facteur < 1 : entendu de plus loin). C'est bien le sens utilisé par `fn_mpuVoice`.
- `TFAR_fnc_activeSwRadio` et `TFAR_fnc_activeLrRadio` apparaissent aussi dans d'autres modules tiers.

Ces noms sont confirmés par le code source lu ci-dessus.

Toujours non trouvé : un point d'accroche par paire de radios (il n'existe pas en SQF) et une « voix brouillée » (le code TFAR coupe, il ne brouille pas). Toujours non vérifié en jeu : le comportement réel du plugin, et la version publiée sur le Workshop par rapport à master.

### Repli sans mod radio ou en cas d'échec

- Ni ACRE ni TFAR : pas de pont voix ; le maillage de données fonctionne ; les groupes se règlent dans l'app (déjà le cas pour l'onglet TALKGROUPS).
- Fonction de signal : toute valeur inattendue (type, nombre d'éléments, paire inconnue, radio non pontée, hors réglage) rend exactement le résultat d'ACRE. Déjà le cas, à conserver et à tester.
- TFAR : valeur d'origine du multiplicateur rendue dès la sortie du maillage, et une valeur posée par un autre script est gardée (déjà le cas).
- Réglages désactivés : comportement d'avant, sans effet de bord.

### L'exemple d'un joueur (idée seulement, code non repris)

Principe d'un joueur, présenté par lui comme non testé : remplacer le calcul d'ACRE, filtrer par groupe abonné, liaison directe sous 1 800 m, sinon un relais unique parmi les joueurs, -15 % de qualité par saut, coupure nette, retour au calcul d'origine si les unités sont introuvables.

Idées retenues : filtre par abonnement, coupure nette, retour à l'origine.

Défauts évités :

- un seul saut : le plan prévoit des chemins multiples avec plafond ;
- parcours de tous les joueurs à chaque calcul de signal : ACRE appelle environ 5 fois par seconde et par couple ; ici le graphe est précalculé et le signal ne fait qu'une lecture de cache ;
- noms de fonctions non vérifiés : `acre_sys_signal_fnc_getSignalCore` et `acre_sys_radio_fnc_getRadioObject` existent bien, mais sont internes ; testés avant usage ;
- `_transmitterPower` non cité : ACRE passe quatre arguments, on ne déclare que ceux qu'on lit, avec leur type.

## 6. Coût en performance et en multijoueur

### Performance

| Zone | Coût visé | Comment |
|---|---|---|
| Chemin de signal ACRE | une lecture de table (O(1)), environ 5 fois par seconde et par couple en émission | le signal ne calcule plus rien ; le constructeur remplit le cache |
| Constructeur du graphe | à mesurer ; objectif : moins de 3 ms de moyenne par tick à 60 nœuds, jamais plus de 1 image de retard | tranches, élagage, caches, un calcul à la fois, intervalle adaptatif |
| Écran Wave Relay | aucun calcul propre | lit le même cache que les données et la voix (supprime le second calcul de scan) |
| TFAR | une écriture de variable d'unité, seulement quand elle change | comme aujourd'hui |

Les compteurs existants (`fn_perfAcc` : « radio de données », « voix par le maillage ») servent de mesure. Aucune mesure n'a été faite : ce sont des objectifs à confirmer en jeu.

### Multijoueur

- Variables publiques : de cinq par joueur à une (`COMSPEC_ATAK_MeshNode`), à valeurs arrondies (classes de portée et de puissance plutôt que des mètres), publiée seulement sur changement et au plus une fois toutes les 2 s. Les anciennes restent le temps de la transition (lot 8 pour les retirer).
- Lecture sûre : une seule fonction lit l'enregistrement d'un autre joueur, vérifie type et taille, borne chaque valeur (portée 0 à 20 000 m, débit 0 à 100 Mbit/s, sauts 0 à H, texte tronqué), et rend un enregistrement vide sinon. Tous les consommateurs passent par elle.
- Clés : événements ciblés, jamais de diffusion. Table d'époques (au plus 16 entrées) diffusée seulement quand elle change.
- Serveur : registre de clés et table d'époques, aucun calcul par image. Un serveur dédié ne calcule pas le signal ACRE (c'est côté client).
- Cohérence : chaque client calcule son graphe à partir des mêmes données publiées ; l'hystérésis limite les écarts. Un calcul d'autorité sur le serveur est une alternative (décision 4).
- Arrivée en cours de partie : à vérifier en test (variables relues, clés renvoyées par le serveur à la connexion).

## 6 bis. Outils (révision 2)

Le plan dit que l'équipe doit tester à 8, 10 joueurs ou plus. C'est le principal frein. Les outils de développement passent donc avant les lots qui en ont besoin.

### Outils de développement et de test

| Outil | Ce qu'il fait | Pourquoi |
|---|---|---|
| Nœuds simulés | Mode développeur : des unités IA (ou de simples objets) portent une MPU5 virtuelle et publient un enregistrement de nœud comme un joueur. Placement en chaîne, en grille ou au hasard ; mouvement scripté. | Tester un graphe de 60 nœuds seul, sans réunir 60 joueurs. Valide les lots 2, 3 et 9 sans attendre une soirée de test. |
| Superposition 3D du graphe | Lignes entre nœuds dans le monde, couleur selon la marge (vert / jaune / orange / rouge = niveaux 0 à 3), épaisseur selon le débit, chiffre de sauts au-dessus de chaque nœud. | Voir d'un coup d'œil pourquoi un lien tombe (relief, bâtiment, distance). |
| Inspecteur de lien | Viser deux nœuds : détail du bilan `fn_radioLink` (espace libre, relief, Fresnel, feuillage, bâtiments, météo, hauteur d'antenne), palier MCS, historique de l'hystérésis. | Recalibrer les seuils en jeu (le plan dit que tout est « à recalibrer »). |
| Tableau de performance | Affiche les compteurs `fn_perfAcc` existants, durée de chaque passe du constructeur, nombre de tests de lien, taux de cache. | Vérifier l'objectif de moins de 3 ms par tick à 60 nœuds. |
| Export du graphe | Commande console : écrit le graphe courant (nœuds, liens, marges, sauts) dans le journal sous forme lisible. Deux clients peuvent comparer leurs exports. | Mesurer les asymétries entre clients (décision 4). |
| Tests de fonctions pures | Les fonctions sans effet (chemin le plus large, gravité, lecture sûre, table fréquence → groupe) sont testées hors partie par une mission de test qui affiche « réussi / échoué ». | Éviter les régressions entre lots. |
| Mission de banc d'essai | Une mission fixe (relief, ville, forêt) avec scénarios rejouables : chaîne A-B-C-D, partition, brouilleur, clé compromise. | Les mêmes conditions à chaque test, comparables d'une version à l'autre. |

Ces outils sont réservés à l'admin ou au mode développeur, et n'existent pas pour les joueurs ordinaires (aucune information sur l'ennemi).

### Outils Zeus et créateurs de missions

- Modules Zeus : poser un brouilleur (canal ou large bande), couper un nœud, compromettre / renouveler une clé, forcer la météo radio, voir le graphe d'un camp (superposition 3D en lecture seule).
- Modules Eden : registre de clés et groupes au démarrage, table fréquence → groupe (décision 7), canaux de secours, profil de réalisme (Milsim / Standard / Arcade) par mission.
- Journal de réseau : événements horodatés (partition, reconstruction, brouillage, changement de clé, compromission) consultables par Zeus et exportables pour le débriefing.

### Outils pour les joueurs (dans le téléphone)

- Vue topologie dans l'app Wave Relay : schéma des nœuds de son groupe connexe, sauts, état des liens. Lit le cache unique, aucun calcul propre.
- Ping / chemin : « joindre X » affiche le nombre de sauts, la latence estimée et le maillon faible (« lien B → C, marge 2 dB »). Aide à décider où poser un relais.
- Planification de couverture : la carte de couverture existante, enrichie d'une prévision « si je pose un relais ici » (calcul ponctuel à la demande, limité).
- Analyseur de spectre et mode discrétion (section 4 bis), si la mission les autorise.

## 7. Lots livrables

Un lot = une PR, sur main, jamais fusionnée sans le propriétaire. Le numéro de version et main.pbo sont partagés (/livrer-version) : une PR native à la fois, dans l'ordre. Chaque PR liste ses tests en jeu. Je ne peux pas lancer Arma 3 : les tests ci-dessous sont à faire par l'équipe, TFAR d'abord, ACRE ensuite (sauf lots sans effet sur la voix).

| Lot | Contenu | Fichiers principaux | Tests en jeu à plusieurs |
|---|---|---|---|
| 1. Lecture sûre | Fonction unique de lecture des variables d'autres joueurs, types et bornes ; aucun changement visible. | `fn_mpu5`, `fn_mpuLink`, `fn_mpuVoice`, `fn_relayNet`, nouveau `fn_meshRead` | 2 à 4 joueurs : maillage et voix inchangés (TFAR puis ACRE). Un client publie des valeurs absurdes avec la console : les autres ne bougent pas. |
| 2. Graphe unique et borné | Un constructeur pour l'écran, les données et la voix ; tranches, élagage, hystérésis, plafond de sauts ; compteurs de performance. | `fn_mpuLink`, `fn_waveRelayAction`, nouveau `fn_meshGraph` | 8 à 10 joueurs en chaîne A-B-C-D : éteindre ou tuer B, reconstruction en moins de 8 s ; l'app Wave Relay et l'app Réseau donnent les mêmes sauts ; pas de pic d'image ; avec TFAR puis ACRE puis sans mod radio. |
| 3. Gravité de coupure (ACRE) | Niveaux 0 à 3 selon la marge ; remplace le signal fixe ; réinstallation si une autre fonction de signal nous écrase. | `fn_mpuVoice`, `fn_linkQuality` | ACRE, 3 joueurs hors portée directe : s'éloigner pas à pas, niveaux puis coupure nette, retour à la portée normale ; 2 émetteurs en même temps ; autre mod de signal présent si possible. TFAR : aucune régression. |
| 4. TFAR | Code lu (section 5). Multiplicateur d'émission posé dans `OnBeforeTangent` selon la radio et le chemin, remis à l'origine sinon ; multiplicateur de réception appliqué seulement pendant la réception d'un émetteur ponté joignable ; limite côté récepteur documentée. Installer TFAR reste nécessaire pour tester. | `fn_mpuVoice` | TFAR 4 joueurs, radio portative et longue portée, chaîne hors portée directe, une radio non pontée sur la même fréquence (limite connue), valeur d'origine rendue en sortie. ACRE : non-régression. |
| 5. Groupes de conversation | 16 groupes, abonnement par fréquence pontée ou par l'app, données de groupe aux abonnés joignables. Remplace le talkgroup décoratif. | nouveau `fn_meshGroups`, `fn_pageWaveRelay`, `fn_waveRelayAction`, `fn_p2pSend` | 6 joueurs, 3 groupes, abonnements changés en cours de partie ; TFAR puis ACRE : fréquence vers groupe, hors abonnement pas de rallonge ; message de groupe reçu par les seuls abonnés. |
| 6. Chiffrement, données | Registre serveur, chargeur de clés, zéroïsation, états à l'écran, messages « [CHIFFRÉ] ». | nouveau `fn_meshKeys`, app Wave Relay, objets | 4 joueurs dont 1 sans clé : absent du graphe ou message chiffré ; zéroïsation ; reconnexion en cours de partie ; hébergé et dédié. |
| 7. Chiffrement, voix | Voix chiffrée sans rallonge pour les non autorisés (ACRE : option Babel selon décision), changement de clé à distance, compromission. | `fn_mpuVoice`, `fn_meshKeys`, actions Zeus / poste | 2 camps ; clé changée pendant qu'un nœud est isolé ; appareil ramassé par l'autre camp puis nouveau changement de clé ; TFAR puis ACRE. |
| 0. Outils de développement (nouveau, avant le lot 2) | Nœuds simulés, superposition 3D, inspecteur de lien, tableau de performance, export du graphe, mission de banc d'essai. Mode développeur uniquement. | nouveaux `fn_devMeshSim`, `fn_devMeshOverlay`, `fn_devLinkInspect` | Un seul joueur + 30 nœuds simulés : graphe cohérent, superposition lisible, aucun effet hors mode développeur. Puis 2 joueurs : aucun outil visible côté joueur ordinaire. |
| 9. Réalisme radio | Capacité par saut réaliste, temps d'antenne partagé, largeur de canal, priorité voix, hauteur d'antenne par posture, mât, consommation selon trafic. | `fn_mpuLink`, `fn_radioLink` (paramètre de hauteur), `fn_meshGraph` | Nœuds simulés puis 6 joueurs : couché derrière un muret, lien perdu ; 20 nœuds sur un canal saturent, changement de canal rétablit ; profil Arcade identique à avant. |
| 10. Données ATAK | Positions périmées, cadence adaptative, fichiers en morceaux avec reprise, messages par chemin A vers B. | `fn_p2pSend`, `fn_netSend`, couche carte | 4 joueurs : coupure, marqueurs gris puis disparus ; photo envoyée en niveau 2 puis reprise après coupure ; message reçu sans passerelle dans le même groupe connexe. |
| 11. Guerre électronique | Activité d'émission, goniomètre, analyseur de spectre, brouillage par canal / large bande / GPS, modes discrétion, canal de secours. | nouveau `fn_meshEW`, objets, `fn_relayTower*` (triangulation) | 2 camps : localiser un émetteur par deux relevés ; écoute seule invisible au goniomètre ; brouillage d'un canal puis bascule ; GPS brouillé, marqueur qui dérive. TFAR puis ACRE : aucune régression voix. |
| 12. Outils Zeus, Eden et joueur | Modules Zeus et Eden, journal de réseau, vue topologie, ping / chemin, prévision de couverture. | modules, app Wave Relay | Zeus : chaque module, en hébergé et en dédié ; créateur de mission : table fréquence → groupe chargée au démarrage ; joueur : ping / chemin cohérent avec la superposition développeur. |
| 8. Nettoyage et doc | Retrait des anciennes variables publiques, réglages et profils (Milsim, Standard, Arcade), notice joueur, en-tête du changelog. | réglages, `CHANGELOG-ATAK.md` | Serveur dédié, 10 joueurs, 30 minutes : voix, données, clés, arrivées et départs ; mesure de performance. |

## 8. Décisions à prendre par le propriétaire

1. Compromission des clés : automatique quand un appareil est ramassé par l'autre camp, ou seulement sur ordre de Zeus / du poste de commandement ? (recommandé : automatique, avec possibilité de la désactiver)
2. Voix chiffrée sous ACRE : simple absence de rallonge, ou voix brouillée via Babel (risque de conflit avec les missions qui l'utilisent) ? TFAR n'aura que l'absence de rallonge tant qu'aucun équivalent n'est vérifié. (recommandé : absence de rallonge d'abord)
3. Plafond de sauts et perte par saut : 6 sauts et la perte actuelle (x 0,8 en débit) conviennent-ils ? Les sources publiques ne chiffrent rien, c'est un réglage de jeu.
4. Où calculer le graphe : sur chaque client (proposé, aucun coût serveur, léger risque d'écarts) ou sur le serveur (une seule vérité, coût serveur et diffusion) ?
5. Valeur par défaut : `mpu_real` et `mpu_voice` restent-ils désactivés tant que les lots 1 à 5 ne sont pas validés en jeu ?
6. Coupure par à-coups (niveau 2) : voulue pour la voix, ou passage direct de dégradé à coupé ?
7. Groupes : 16 comme la MPU5 ? Table fréquence vers groupe fournie par la mission, ou déduite automatiquement ?
8. Portée du filtre de groupe côté données : quelles apps (messages, plans partagés, marqueurs, position) ?
9. TFAR : le code est lu, le lot 4 peut être écrit. Il faut quand même installer TFAR 1.0 (même version que le serveur) sur une machine de test pour le valider.
10. Ordre des lots : le lot 3 (ACRE) passe-t-il avant le lot 4 (TFAR), comme proposé, puisque TFAR dépend d'une vérification préalable ?
11. Lot 0 (outils de développement) : le faire avant le lot 2 ? (recommandé : oui, il réduit le besoin de soirées de test nombreuses)
12. Perte par saut : courbe « moitié puis tiers » (réaliste) en Milsim, x 0,8 en Arcade ? Remplace en partie la décision 3.
13. Positions périmées : délai avant gris et avant disparition (proposé : 30 s et 3 min, réglables) ?
14. Guerre électronique : goniomètre et analyseur ouverts à tous les camps, ou réservés à un rôle (spécialiste EW) ?
15. Masque du corps et hauteur par posture : actifs en Milsim seulement ?
16. Ordre des nouveaux lots : proposé 0, 1, 2, 3, 4, 9, 10, 5, 6, 7, 11, 12, 8. Le lot 10 (données ATAK) n'a pas d'effet voix et peut avancer si l'équipe TFAR / ACRE n'est pas disponible.
17. Chiffrement voix sous TFAR : utiliser le code de chiffrement TFAR des radios pontées (un non-autorisé n'entend rien, même en direct), ou seulement l'absence de rallonge ? (recommandé : option désactivée par défaut, car une mission peut déjà gérer ses codes)

## 9. Sources et ce qui n'a pas pu être lu

| Source | Résultat |
|---|---|
| persistentsystems.com/mpu5-capabilities/ | Lue. Pas de chiffrement ni de chiffres de débit ou de portée sur cette page. |
| Fiche technique MPU5 Rev. R (PDF) | Lue après extraction du texte. Chiffrement, changement de clé à distance, effacement, modulations, multicast. |
| Présentation Wave Relay de JHU (PDF) | Lue après extraction du texte. Ancienne (cartes 802.11b), partielle. |
| unmannedsystemstechnology.com (vidéo MPU5 Android) | Non lue : le serveur répond « 403 Forbidden ». Rien n'en est tiré. |
| Wiki ACRE2, traitement du signal personnalisé | Lue en complément ; cohérente avec le code installé. |
| Code ACRE2 2.14.0.1064 | Lu (PBO, SQF clair). |
| Code TFAR (GitHub, master fce977f, 23/09/2026) | Lu (SQF). Extension C++ du plugin non lue. Pas installé, donc non testé en jeu. |
| Code ACRE2 (GitHub, master c920bac, 12/08/2026) | Même version 2.14.0.1064 que celle installée ; `setCustomSignalFunc`, `getSignalCore`, `getRadioObject` toujours présents. |

## 10. Mise en œuvre

Décisions du propriétaire reçues : 1 automatique et désactivable ; 2 absence de rallonge d'abord ; 3 plafond de 6 sauts, réglable ; 4 graphe de référence sur le serveur, constructeur capable de tourner aussi sur le client ; 5 `mpu_real` et `mpu_voice` désactivés jusqu'à validation en jeu des lots 1 à 5 ; 6 pas d'à-coups, passage net ; 7 16 groupes, table de la mission sinon déduite ; 8 filtre de groupe d'abord sur messages et voix ; 10 ACRE avant TFAR ; 11 lot 0 avant le lot 2 ; 12 « moitié puis tiers » en Milsim, x 0,8 en Arcade ; 13 30 s puis 3 min, réglables ; 14 goniomètre et analyseur réservés à un rôle ; 15 masque du corps et hauteur par posture en Milsim ; 16 ordre 0, 1, 2, 3, 4, 9, 10, 5, 6, 7, 11, 12, 8 ; 17 code TFAR désactivé par défaut. Livraison groupée dans la PR #1395 (au lieu d'une PR par lot).

| Lot | Fichiers | État |
|---|---|---|
| 0 | `functions/devmesh/` (`fn_devMesh`, `fn_devMeshSim`, `fn_devMeshOverlay`, `fn_devLinkInspect`), `missions/COMSPEC_BancMaillage.Stratis`, réglage `mesh_dev` | codé, à tester |
| 1 | `fn_meshRead`, `fn_devMeshTest` ; tous les consommateurs passent par la lecture sûre | codé, à tester |
| 2 | `fn_meshGraph` ; `fn_mpuLink` « mesh », `fn_waveRelayAction` « scan » (liaison réaliste), `fn_mpuVoice` ; réglages `mesh_server`, `mesh_hops` | codé, à tester |
| 3 | `fn_mpuVoice` « sigMap », `fn_meshGraph` « view » → `voice`, `fn_linkQuality` ; `mesh_gravity` | codé, à tester |
| 4 | `fn_mpuVoice` « tfBefore », « tfAfter », « tfRx » ; `mesh_tfar_ptt` | codé d'après le source de TFAR, tout à confirmer en jeu |
| 9 | `fn_meshGraph` (courbe, temps d'antenne, posture, masque), `fn_mpuLink` (largeur, mât, batterie) ; `mesh_hop_curve`, `mesh_airtime`, `mesh_posture`, `mesh_body_mask`, `mesh_mast`, `mesh_traffic_power` | codé, à tester |
| 10 | `fn_localDataRefresh`, `fn_pli`, `fn_meshChunks`, `fn_netSend`, `fn_p2pSend`, `fn_p2pReceive` ; `mesh_stale_s`, `mesh_hide_s`, `mesh_pli_adaptive`, `mesh_chunks`, `mesh_p2p` | codé, à tester |
| 5 | `fn_meshGroups`, app Wave Relay ; `mesh_groups` | codé, à tester |
| 6, 7 | `fn_meshKeys`, chargeur `COMSPEC_ATAK_KeyLoader` ; `mesh_keys`, `mesh_keys_auto`, `mesh_compromise_auto`, `mesh_tfar_code` | codé, à tester |
| 11 | `fn_meshEW`, goniomètre `COMSPEC_ATAK_Goniometer` ; `mesh_ew`, `mesh_ew_role`, `mesh_backup_channel`, `mesh_backup_delay` | codé, à tester |
| 12 | `fn_meshTools`, `fn_moduleMesh`, module `COMSPEC_ATAK_Module_Mesh` ; `mesh_tools` | codé, à tester |
| 8 | une seule variable publique par joueur, `COMSPEC_ATAK_MeshNode` ; notice `docs/technical/reseau-mesh-notice-joueur.md` | codé, à tester |

Écarts assumés par rapport aux sections ci-dessus :

- Section 6, « chaque client calcule son graphe » : remplacé par la décision 4. Le serveur construit, le client ne fait plus que lire (chemin le plus large sur le graphe reçu, sans bilan radio). Réglage décoché, ou serveur sans le mod : le même constructeur tourne sur le client, sans diffusion.
- Bornes de lecture : 20 km pour un nœud MPU5, mais 30 km pour `COMSPEC_ATAK_Mpu5` (borne du réglage de radio longue portée) et 100 km pour la portée TFAR publiée, pour ne pas refuser une valeur légitime.
- Plafond de sauts appliqué aussi au total A + B quand la voix passe par le cœur de réseau.
- Liaison réaliste décochée : l'ancien `scan` de l'app Wave Relay reste jusqu'au lot 8 (décision 5 : aucun changement tant que le réalisme n'est pas validé).
- Niveau de gravité calculé pour chaque chemin, mais appliqué à la voix seulement au lot 3. Décision 6 : le niveau 2 n'a pas d'à-coups.
- Lot 5 : la voix ACRE2 et TFAR reste filtrée par fréquence ; le groupe ne retire la rallonge que sans groupe commun.
- Lot 6 : cadenas de la barre d'état non fait (`fn_statusUpdate`, hors périmètre réseau) ; notification et état dans l'app Wave Relay. Lot 7 : le détenteur d'une clé compromise relaie dans le graphe de l'autre camp, mais ne le lit pas pour lui-même.
- Lot 12 : ping donnant la marge du maillon faible, pas son nom ; prévision limitée au relief ; superposition Zeus limitée au graphe calculé sur le serveur.
- Lot 8 : les profils existants de `fn_netCond` (Milsim, Standard, Arcade) servent de profils de réalisme, sans nouveau réglage.
- Mouvement seul : graphe refait à l'intervalle normal. Seul un changement de nœud (apparu, tombé, canal, portée) déclenche le recalcul à 2 s.

À mesurer au banc d'essai pour trancher la décision 4 (aucun chiffre n'existe à ce jour) : durée de passe et pire tranche à 30 et 60 nœuds, images par seconde du serveur, nombre de diffusions et taille de `COMSPEC_ATAK_MeshRef`, écarts entre exports de deux clients avec le réglage décoché.

## 10 bis. Objet obligatoire et case RELAIS ATAK

Objet obligatoire (`fn_netRequire`, réglage serveur `comspec_atak_native_net_required_items`, vide par défaut) : syntaxe de `comspec_atak_native_kit_required`. `comspec_atak_native_mpu_require` ne vise que la MPU5 en liaison réaliste et `kit_required` ne coupe rien : réglage distinct. Vaut pour les deux modèles de réseau. Instances reconnues par la config : héritage, `acre_baseClass`, `tf_parent`. Le serveur lit les inventaires toutes les 2 s, publie `COMSPEC_ATAK_NetDenied` et écarte le joueur du graphe.

Case RELAIS ATAK (`fn_relayTxBadge`) : ACRE2 calcule le signal chez le récepteur et prend le meilleur du direct et du maillage (`fn_mpuVoice` « signal »). Seul le récepteur sait donc si le maillage a servi : il l'accuse à l'émetteur (`comspec_atak_native_meshVoiceAck`), au plus toutes les 2 s par paire. TFAR : la case suit l'allongement réel de la portée d'émission (`tfBefore`), à confirmer en jeu. Athena ne transporte pas la voix : la case ne le mentionne pas. Rien de tout cela n'est testé en jeu.

## 11. Antennes transportables (`fn_antennaKit`)

Objets d'inventaire à déployer et replier par ACE (Équipement > Déployer ; « Replier l'antenne » sur l'objet posé, moitié moins de temps). Le serveur crée l'objet et l'enregistre par `fn_relayConvert` « register » : c'est alors un relais ordinaire de `fn_relayNet`, réservé au camp de celui qui l'a posée, vu par la carte réseau et le maillage. Détruite, supprimée par le Zeus ou repliée : `fn_relayConvert` « remove », retirée du réseau tout de suite. Aucune boucle : événements CBA et gestionnaires `Killed` et `Deleted` sur le serveur. Sans ACE : objets présents, aucune action.

Portée du relais = portée de base × 10^(gain / 20) : `fn_radioLink` donne 0 dB de marge à la portée, donc 6 dB la doublent à peu près. Le bilan radio s'applique ensuite depuis la hauteur d'antenne.

| | Antenne satellite portable | Antenne omnidirectionnelle sur mât |
|---|---|---|
| Objet d'inventaire | `COMSPEC_ATAK_SatAntennaKit` | `COMSPEC_ATAK_MastAntennaKit` |
| Objet posé | `COMSPEC_ATAK_DeployedAntenna_Sat` | `COMSPEC_ATAK_DeployedAntenna_Omni` |
| Modèle posé (Arma) | `\A3\Props_F_Exp\Military\Camps\SatelliteAntenna_01_F.p3d` | `\A3\Props_F_Enoch\Military\Equipment\OmniDirectionalAntenna_01_F.p3d` |
| Modèle au sol (Arma) | `SatellitePhone_F.p3d` | `PortableLongRangeRadio_F.p3d` |
| Portée de base, gain, portée du relais | 1 000 m, 3 dB, 1 413 m | 1 500 m, 6 dB, 2 993 m |
| Hauteur d'antenne | 1,8 m | 4 m |
| Débit, places | 10 Mbit/s, 16 | 8 Mbit/s, 24 |
| Liaison | raccordée | répéteur (`antkit_mast_root` pour la raccorder) |
| Déploiement | 20 s | 45 s |

Valeurs de jeu plausibles, calées sur les types du module « ATAK · Relais », pas une étude réaliste. Toutes réglables : `comspec_atak_native_antkit_<sat | mast>_<range | gain | height | mbps | time>`, `comspec_atak_native_antkit_max` (12 par camp), `comspec_atak_native_antkit_mast_root`. Hauteurs par défaut : celles des modèles, lues dans `verticalOffset` de leur classe d'Arma.

Antennes MRH Satellite (mod facultatif) : `MRH_SatCom_Antenna_Base` et `MRH_MT_SatCom_Antenna_Base` dépliées deviennent des relais de type terminal satellite (`fn_relayConvert` « check », toutes les 15 s), retirées une fois repliées ou détruites. Réglage `comspec_atak_native_relay_mrh`, coché par défaut, indépendant de « Antennes du jeu = relais ATAK ». Sans MRH : rien n'est cherché, aucune dépendance.

Mini-antenne de bâtiment (`COMSPEC_ATAK_BuildingAntennaKit`, objet posé `COMSPEC_ATAK_DeployedAntenna_Mini`, modèle `PortableLongRangeRadio_F.p3d`, au sol `SurvivalRadio_F.p3d`) : répéteur de 500 m + 3 dB, soit 706 m ; 0,3 m au-dessus de sa position réelle ; 4 Mbit/s ; 6 places ; pose en 8 s. Mode de visée sur un mur ou un toit à moins de 4 m (`Building`), aperçu vert ou rouge, Espace pour poser, Échap pour annuler, 45 s au plus. Bâtiment détruit, remplacé ou supprimé : retirée sous 15 s (`fn_antennaKit` « hosts », appelé par `fn_relayConvert` « check »). Réglages `comspec_atak_native_antkit_mini_<range | gain | height | mbps | time>`.

Déploiement : ACE (Équipement) ou clic droit sur l'objet dans l'inventaire (menu d'objet de CBA). Chaque étape écrit une ligne `[COMSPEC ATAK NATIVE][INFO][ANTENNE]` au RPT, chaque refus s'affiche à l'écran.
## 12. Réalisme par défaut et garde-fous radio

Le propriétaire revient sur la décision 5 pour `mpu_real` : coché par défaut. `mpu_voice` reste décoché, ainsi que la décision 17 : la voix n'a jamais été jouée en jeu, et rien ne garantit sans test que l'isolation tienne.

| Point de contact | Garde-fou |
|---|---|
| Tout appel ACRE ou TFAR | `fn_radioGuard` "api" : fonction testée, valeur de repli ; aucun appel direct ailleurs (test `AtakRealismeDefautTest`) |
| `acre_api_fnc_setCustomSignalFunc` | Installée pendant un pont radio seulement, chaînée à la précédente, rendue au retrait si la nôtre est encore la dernière |
| Fonction de signal (`fn_mpuVoiceSig`) | Résultat d'origine d'abord, puis une lecture de table remplie par le tick. Aucun calcul ni appel réseau, plus de `acre_sys_radio_fnc_getRadioObject` |
| `mesh_gravity`, niveau de chemin | Maximum du signal d'origine et du maillage : ne retire jamais rien |
| `tf_sendingDistanceMultiplicator`, `tf_receivingDistanceMultiplicator` | Valeur d'avant gardée, ou son absence ; rendue seulement si personne d'autre ne l'a changée |
| Événements TFAR | Entrée unique "tfGuard", rien si la couche est coupée |
| `acre_startedSpeaking` et suivants | Nos seules variables, rien si tout lien est coupé |
| `acre_api_fnc_setRadioChannel` | Geste explicite dans l'app Wave Relay, seul réglage de radio changé par le mod |
| `mesh_tfar_code`, `TFAR_fnc_setSwRadioCode` | Retire la voix directe : décoché, valeur d'origine rendue par "tfRestore" |
| `mesh_keys`, `fn_meshGroups` | Retirent la rallonge seulement, la portée normale reste |

Coupe-circuit : SQF n'a pas de `try` pour une erreur de script. Chaque chemin radio compte ses entrées et ses sorties ; trois écarts ou trois valeurs invalides coupent la couche pour la session, avec une ligne `[WARN][RADIO]` et une alerte aux admins. Restauration à la mort, au changement d'unité, à la fin de mission et à la déconnexion. Coupure : réglage `comspec_atak_native_radio_cut`, ou bouton admin et Zeus.

Versions connues : ACRE2 2.14 (code lu), TFAR 1.x (dépôt lu, jamais joué). TFAR 0.9 est traité comme inconnu : couche voix coupée.

Portée de données : ACRE2 n'a pas de portée fixe. Longue portée `radio_lr_range` x racine (puissance / 20 W), portative `radio_sw_range` x racine (puissance / 5 W), 300 m au moins. TFAR : `tf_range` de la config, 20 km au plus.
