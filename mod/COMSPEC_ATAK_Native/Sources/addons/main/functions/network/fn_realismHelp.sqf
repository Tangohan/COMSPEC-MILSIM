/*
    Explications des réglages de réalisme (fenêtre « Réalisme et réseau du serveur » et assistant) : pour chaque réglage, ce que ça change en jeu
    et un exemple chiffré. Textes en français, traduits à l'affichage (i18n/en.txt). Aucune valeur ni aucun comportement n'est touché ici.
    Ligne : [réglage (variable sans le préfixe comspec_atak_native_), explication, exemple].
      ["html", variable, texte de la valeur par défaut] -> bloc de texte structuré à placer sous le nom du réglage ("" si aucune explication)
      ["has", variable] -> booléen ; ["keys"] -> liste des réglages expliqués
*/
params [["_act", "html"], ["_var", ""], ["_def", ""]];
private _T = { _this call comspec_atak_native_fnc_tr };
private _h = missionNamespace getVariable "COMSPEC_ATAK_RealismHelp";
if (isNil "_h") then {
    _h = createHashMapFromArray [
        ["realism_profile", ["Règle d'un coup toutes les simulations - Milsim très réaliste, Standard plus doux, Arcade sans contrainte, Personnalisé pour tout régler soi-même.", "En Arcade, le réseau est parfait et la batterie ne baisse jamais ; en Milsim, un joueur à couvert voit son signal et son GPS se dégrader."]],
        ["net_sim", ["Active le réseau réaliste - le terrain, la météo et les relais changent la vitesse et la fiabilité des envois.", "Actif, une photo envoyée depuis une vallée peut mettre du temps ou se perdre. Coupé, elle part tout de suite partout."]],
        ["net_realism", ["Choisit la précision du calcul de la portée radio.", "Réaliste tient compte du relief et des arbres ; Exigeant coupe encore plus fort en forêt et en ville ; Simplifié divise juste la portée par trois derrière une colline."]],
        ["net_weather", ["La pluie, les orages et le vent fort affaiblissent le signal du téléphone.", "Actif, sous un orage, des messages se perdent par moments. Coupé, le temps n'a aucun effet sur le réseau."]],
        ["net_weather_k", ["Force de l'effet de la météo sur le réseau.", "0 % = aucun effet ; 100 % = effet réaliste ; 200 % = une tempête deux fois plus gênante."]],
        ["net_congestion", ["Les téléphones branchés sur le même relais se partagent sa capacité.", "Actif, avec 20 joueurs sur un seul relais, tout ralentit et des envois se perdent. Coupé, chacun garde sa vitesse."]],
        ["net_bg_load", ["Part de chaque relais déjà utilisée par d'autres (utilisateurs fictifs).", "0 % = relais libre ; 50 % = la moitié de la capacité est déjà prise ; 90 % = réseau presque saturé."]],
        ["net_loss", ["Des envois se perdent selon la qualité du signal, puis repartent tout seuls.", "Actif, un message envoyé avec un signal faible peut arriver en retard. Coupé, rien ne se perd, seuls la vitesse et le délai comptent."]],
        ["net_loss_floor", ["Part d'envois perdus partout, même avec un excellent signal.", "0 % = aucune perte avec un bon signal ; 10 % = environ 1 envoi sur 10 est perdu, même en terrain dégagé."]],
        ["net_latency_add", ["Délai ajouté à chaque envoi.", "0 ms = instantané ; 600 ms = liaison satellite, chaque message arrive une demi-seconde plus tard."]],
        ["relay_only", ["Impose de passer par les relais de la mission pour avoir du réseau.", "Actif, loin de tout relais, plus aucun réseau - messages et photos attendent d'être à portée. Coupé, le téléphone garde un peu de réseau sans relais."]],
        ["vehicle_gateways", ["Permet à l'équipage de transformer un véhicule en relais ou en poste de commandement mobile.", "Actif, un camion devient relais (4 km) ou poste de commandement avec mât (10 km). Coupé, seuls les relais posés par la mission comptent."]],
        ["offline_hold", ["Sans réseau, le téléphone garde ses dernières infos et met les envois importants en attente.", "Actif, une alerte MEDEVAC saisie hors réseau part dès le retour du signal. Coupé, tout arrive même sans réseau."]],
        ["video_relays", ["La qualité des vidéos dépend de la distance aux relais.", "Actif, le flux d'un drone passe de 1080p à image par image quand on s'éloigne des relais. Coupé, la vidéo est toujours parfaite."]],
        ["net_pli", ["Chaque téléphone envoie sa position à intervalle régulier, avec l'erreur de son GPS.", "Actif, les alliés avancent par bonds sur la carte. Coupé, leurs positions sont exactes et en direct."]],
        ["net_pli_rate", ["Temps entre deux envois de position.", "5 s = carte plus vivante ; 60 s = un allié qui court peut être à près de 200 m de son point affiché."]],
        ["gps_sim", ["La précision du GPS dépend du ciel visible.", "Actif, quelques mètres à découvert, plus en forêt dense, aucun fix en sous-sol. Coupé, le GPS est toujours exact."]],
        ["gps_k", ["Taille des erreurs du GPS.", "50 % = erreurs deux fois plus petites ; 200 % = GPS deux fois plus capricieux."]],
        ["gps_fix", ["Au démarrage, le GPS met un moment à trouver les satellites.", "Actif, après avoir allumé le téléphone, la position est approximative pendant 5 à 40 s environ. Coupé, elle est exacte tout de suite."]],
        ["gps_loss", ["Part des satellites masqués par le décor qui compte vraiment.", "0 % = le fix tient même dans un bâtiment ; 100 % = comme un vrai téléphone ; 200 % = le fix se perd plus vite."]],
        ["gps_rel", ["Fréquence des décrochages et des sauts de position du GPS.", "Parfaite = jamais ; Bonne = environ une fois toutes les 20 min ; Médiocre = toutes les 2 min."]],
        ["battery_sim", ["La batterie du téléphone se vide selon l'usage.", "Actif, à 0 % le téléphone s'éteint et apparaît hors ligne sur le BFT. Coupé, la batterie ne baisse jamais."]],
        ["radio_source", ["Choisit quelle radio le mod suit pour calculer la portée du réseau.", "Détectée automatiquement prend ACRE2 ou TFAR s'il est chargé ; Aucune ne suit aucune radio, seule notre MPU5 compte."]],
        ["radio_cut", ["Coupe tout lien entre le mod et vos radios ACRE2 ou TFAR, sans redémarrer.", "Actif, la voix reste celle d'ACRE2 ou de TFAR, exactement comme sans le mod. Coupé, le mod garde le lien avec la radio."]],
        ["mpu_voice_needRelay", ["Coché par défaut. Sans aucun relais en service pour votre camp dans la mission, la voix ne passe pas par les MPU5 : les radios gardent leur portée normale.", "Coché, une carte sans pylône ni module « Poser un relais » : deux joueurs avec une MPU5 chacun ne s'entendent pas plus loin que leur radio. Avec un relais posé, ils s'entendent par le maillage. Décoché : la MPU5 du chef de groupe et les MPU5 voisines suffisent."]],
        ["mpu_voice", ["Décoché par défaut. Coché, la voix ACRE2 ou TFAR peut passer par les MPU5 au lieu d'être limitée par la distance - il faut aussi la liaison réaliste, et chaque joueur doit porter une MPU5 allumée, y relier sa radio (pont radio) et être sur la même fréquence que son correspondant.", "Coché, deux joueurs pontés à 8 km s'entendent par la MPU5 du chef de groupe ou par un relais. Coupé, portée radio normale. Un joueur qui n'entend rien ouvre l'app Réseau du téléphone, Voix radio par le maillage, DÉPANNAGE MPU : l'assistant dit quel maillon manque."]],
        ["mesh_tfar_ptt", ["TFAR - n'allonge la portée que pendant l'appui sur l'alternat.", "Actif, la portée est rallongée seulement quand on parle. Coupé, la réception reste allongée en permanence."]],
        ["radio_data", ["Ancien modèle - le téléphone relaie ses données par la radio personnelle (app Wave Relay).", "Actif, un joueur branche son téléphone sur sa radio et fait relayer les données de l'équipe. Sans effet avec la liaison téléphone-radio réaliste."]],
        ["mpu_real", ["Le téléphone passe par la radio du joueur (ACRE2, TFAR ou MPU5), avec sa portée réelle.", "Actif, deux joueurs sur le même canal forment un maillage avec les relais de la mission. Coupé, comportement d'avant."]],
        ["mpu_require", ["Sans radio de données, pas de réseau de données.", "Actif, un joueur sans radio ne peut plus envoyer de messages (le Bluetooth entre téléphones marche encore). Coupé, il garde son réseau d'avant."]],
        ["mpu_cable", ["Il faut un câble EUD dans l'inventaire pour relier le téléphone à la MPU5.", "Actif, sans câble dans le sac, pas de liaison filaire à la MPU5. Coupé, plus besoin du câble."]],
        ["mpu_wifi", ["Le téléphone peut se relier en Wi-Fi à une MPU5 proche.", "Actif, à quelques mètres d'une MPU5 posée au sol, le téléphone s'y relie sans câble. Coupé, le câble est la seule liaison."]],
        ["mesh_server", ["Le serveur calcule le réseau maillé pour tout le monde.", "Actif, tous les joueurs voient le même réseau. Coupé, chaque joueur calcule le sien, avec un risque d'écarts entre eux."]],
        ["mesh_hops", ["Nombre maximal de radios qu'un message peut traverser.", "Avec 6 sauts max, un message passe par 6 relais au plus ; une 7e radio est hors du maillage."]],
        ["mesh_gravity", ["Le signal suit le pire lien du chemin.", "Actif, un chemin avec un lien très faible dégrade tout le trajet, puis coupe net. Coupé, le signal reste fixe quel que soit le lien."]],
        ["mesh_hop_curve", ["Débit perdu à chaque radio traversée.", "Moitié puis tiers = le débit chute vite sur les premiers sauts ; x 0,8 par saut = il perd 20 % à chaque saut ; Selon le profil = le profil choisit."]],
        ["mesh_airtime", ["Les radios voisines d'un même canal se partagent le temps d'émission.", "Actif, 10 radios sur un canal se gênent, changer de canal aide. Coupé, le nombre de voisins ne compte pas."]],
        ["mesh_posture", ["La hauteur de l'antenne dépend de la posture (profil Milsim).", "Actif, un porteur couché derrière un talus proche perd la liaison. Debout, il la garde."]],
        ["mesh_body_mask", ["Le corps du porteur affaiblit le signal quand il tourne le dos (profil Milsim).", "Actif, tourner le dos à son correspondant coûte 3 dB de signal. Coupé, l'orientation ne compte pas."]],
        ["mesh_mast", ["Une MPU5 posée peut déployer un mât d'antenne.", "Actif, le mât se déploie en 30 s et monte à 8 m, visible de loin. Coupé, pas de mât."]],
        ["mesh_traffic_power", ["Une radio qui relaie beaucoup se vide plus vite.", "Actif, une MPU5 au centre du réseau peut s'user jusqu'à deux fois plus vite. Coupé, la batterie ne dépend pas du trafic."]],
        ["mesh_stale_s", ["Délai sans nouvelle position avant que l'ami passe en gris sur la carte.", "30 s = un ami sans nouvelle depuis 30 s devient gris ; Jamais = il reste affiché normalement."]],
        ["mesh_hide_s", ["Délai sans nouvelle avant que le marqueur disparaisse de la carte.", "3 min = un ami muet depuis 3 min disparaît de la carte ; Jamais = il reste affiché."]],
        ["mesh_pli_adaptive", ["Les positions sont envoyées moins souvent à l'arrêt ou sur un réseau dégradé.", "Actif, un joueur immobile envoie sa position plus rarement. Coupé, la cadence est fixe."]],
        ["mesh_chunks", ["Les photos et gros fichiers partent par blocs de 128 Ko.", "Actif, une photo coupée en route reprend là où elle s'était arrêtée, avec une barre de progression. Coupé, l'envoi se fait en une fois."]],
        ["mesh_p2p", ["Les messages passent de joueur à joueur par le maillage.", "Actif, deux joueurs voisins s'écrivent même sans passerelle ni relais proche. Coupé, il faut atteindre une passerelle."]],
        ["mesh_groups", ["16 groupes de conversation du maillage.", "Actif, un message de groupe n'arrive qu'aux abonnés de ce groupe (fréquence de la radio ou app Wave Relay). Coupé, pas de groupes."]],
        ["mesh_tools", ["Outils du maillage pour les Zeus et les joueurs.", "Actif, un Zeus a le clic droit ATAK Maillage et les joueurs voient topologie, ping et prévision de couverture dans Wave Relay."]],
        ["ew_open", ["Qui peut brouiller et localiser des émetteurs.", "Actif, tout joueur avec un téléphone le peut. Coupé, seuls les rôles de guerre électronique, brouilleur ou SIGINT."]],
        ["zeus_crows", ["Les brouilleurs du mod Crows Electronic Warfare brouillent aussi l'ATAK.", "Actif, un brouilleur posé par un Zeus coupe réseau, GPS et BFT dans son rayon. Sans ce mod chargé, sans effet."]],
        ["jam_tfar", ["Les brouilleurs TFAR brouillent aussi le téléphone.", "Actif, un joueur dont la portée radio TFAR est réduite voit aussi son téléphone ralentir (débit, GPS, BFT)."]],
        ["mesh_ew", ["Goniomètre et analyseur de spectre dans l'app Guerre électronique, onglet GONIO.", "Actif, on repère d'où vient un brouilleur. Coupé, ces outils n'existent pas."]],
        ["mesh_ew_role", ["Réserve le goniomètre à un spécialiste.", "Actif, seul un rôle de guerre électronique l'utilise. Coupé, tout le monde peut s'en servir."]],
        ["mesh_keys", ["Clés de réseau et de groupe simulées (mécanique de jeu, pas de vraie sécurité).", "Actif, un joueur sans la bonne clé est hors du réseau et voit un message chiffré à l'écran. Coupé, pas de clés."]],
        ["mesh_keys_auto", ["Distribution des clés au démarrage.", "Actif, chaque MPU5 reçoit les clés de son camp au départ. Coupé, il faut un chargeur de clés ou un poste de commandement."]],
        ["mesh_compromise_auto", ["Une clé est compromise quand l'ennemi s'empare du matériel.", "Actif, un ennemi qui ramasse une MPU5 compromet les clés qu'elle porte et son camp est prévenu ; une nouvelle époque l'exclut. Une MPU5 effacée à temps (ZÉROÏSER) ne compromet rien."]],
        ["mesh_tfar_code", ["TFAR - la radio reliée prend le code de la clé de son groupe.", "Actif, sans la clé du groupe on n'entend rien, même en direct. Peut gêner une mission qui gère déjà ses codes."]],
        ["mesh_backup_channel", ["Canal vers lequel la MPU5 bascule seule sous brouillage.", "8 = sous brouillage de son canal, la MPU5 passe seule sur le canal 8 ; Aucun = elle reste sur son canal brouillé."]],
        ["mesh_keys_fill", ["Charger les clés demande un vrai geste : un chargeur de clés dans l'inventaire (ou le poste de commandement) et 6 s d'action.", "Actif, un joueur sans chargeur ne recharge plus sa MPU5 seul : il va voir celui qui porte le chargeur. Coupé, CHARGER LES CLÉS marche partout, tout de suite."]],
        ["mesh_keys_period", ["Une clé chargée ne vit qu'un temps : il faut la recharger avant la fin.", "2 h = une alerte 10 min avant ; à 2 h le cadenas passe en PÉRIMÉE, les messages partent en clair et la MPU5 sort du maillage chiffré. Aucune = une clé ne périme jamais."]],
        ["mesh_keys_otar", ["Le chef de groupe ou le poste de commandement renouvelle à distance les clés des équipiers joints par le maillage.", "Actif, le chef touche RENOUVELER LE GROUPE (OTAR) : ses 4 équipiers dans le maillage repartent pour une période pleine, celui qui est hors maillage passe au chargeur. Coupé, seul le chargeur recharge."]],
        ["mesh_crypto_cost", ["Le chiffrement coûte un peu de débit, de délai et de batterie tant qu'il est actif.", "Discret = 5 % de débit en moins, 20 ms de plus et environ 4 % de batterie en plus ; Marqué = 15 %, 60 ms et 9 % ; Aucun = gratuit. Visible dans l'app Réseau."]],
        ["mesh_intercept", ["Un spécialiste ennemi avec un goniomètre intercepte les messages de groupe émis à portée.", "Actif, un message en clair lui donne la position de l'émetteur à 100 m près et une partie du texte ; chiffré, il ne voit qu'une émission et sa direction, jamais le contenu. Coupé, rien n'est intercepté."]],
        ["battery_hours", ["Durée d'une batterie pleine avec le téléphone en main, écran allumé.", "3 h = vide après 3 h d'usage actif, alors qu'en veille elle tient environ 14 h ; 24 h = très longue autonomie."]],
        ["battery_start", ["Niveau de batterie quand le joueur arrive en jeu.", "50 % = chacun arrive avec une batterie à moitié ; 100 % = pleine. Les batteries de rechange sont toujours pleines."]],
        ["battery_vehicle", ["Vitesse de recharge dans un véhicule moteur allumé.", "6 % par minute = 10 min de route rendent environ 60 % ; Aucune = pas de recharge en véhicule."]],
        ["battery_cold", ["Par temps froid, la batterie se vide plus vite.", "Actif, par grand froid la batterie perd jusqu'à 0,3 % de plus par minute. Coupé, la température est ignorée."]],
        ["mpu_hours", ["Durée d'une batterie pleine de MPU5 à pleine puissance.", "5 h = une MPU5 pleine tient 5 h à pleine puissance ; vide, la radio s'arrête et quitte le maillage."]],
        ["damage_sim", ["Le téléphone peut être abîmé par les balles, explosions, chutes et l'eau.", "Actif, une balle sur le gilet peut rayer ou fêler l'écran, voire détruire le téléphone. Coupé, le téléphone est indestructible."]],
        ["dirt_sim", ["L'écran se salit avec la poussière, les traces de doigts et le sang.", "Actif, la poussière et le sang salissent l'écran jusqu'à ce qu'on le nettoie (action ACE gratuite). Coupé, l'écran reste propre."]],
        ["phone_owner", ["Chaque téléphone a un propriétaire.", "Actif, celui qui ramasse le téléphone d'un autre doit connaître son code pour l'utiliser. Coupé, n'importe quel téléphone donne accès à l'ATAK."]],
        ["capture_enabled", ["Une IA ennemie peut récupérer les téléphones qu'elle trouve.", "Actif, une IA ennemie qui passe près d'un joueur à terre prend son téléphone. Coupé, l'IA ne ramasse rien."]],
        ["sim_sos", ["Un téléphone éteint par batterie vide reste repérable par sa carte SIM.", "Actif, un téléphone à 0 % se retrouve par son numéro, plus précisément quand plus de relais le captent. Coupé, éteint veut dire introuvable."]],
        ["geoloc_enabled", ["Localiser un téléphone avec son numéro, son IMEI ou son adresse MAC (roleplay).", "Actif, l'onglet GÉOLOC de l'app Guerre électronique retrouve un téléphone ciblé. Coupé, l'onglet ne fait rien."]],
        ["hvt_trace", ["Les apps civiles révèlent où une cible importante était active, avec du retard.", "Actif, une app civile montre qu'une cible était active dans une zone d'1 km, mais jamais sa position du moment. Coupé, aucune trace."]],
        ["hvt_trace_role", ["Réserve ces traces civiles aux rôles de renseignement.", "Actif, seuls les rôles de renseignement, le Zeus et l'admin les voient. Coupé, tout le monde."]],
        ["launcher_spaces", ["Le lanceur d'apps peut regrouper les apps en 5 grandes familles.", "Activé pour tous = 5 grandes apps regroupées ; Désactivé pour tous = liste classique ; Au choix du joueur = chacun décide dans Réglages."]],
        ["pilot_mode", ["Écran simplifié proposé en montant dans un aéronef.", "Proposé à tous = en montant dans un hélicoptère, l'écran simplifié est proposé ; Coupé pour tous = jamais ; Au choix du joueur = chacun l'active dans Réglages."]],
        ["mesh_dev", ["Outils de mise au point du maillage, réservés aux admins et Zeus.", "Actif, on voit des nœuds simulés et un tableau de performance. À laisser coupé en partie normale."]],
        ["net_required_items", ["Objet qu'il faut avoir sur soi pour faire partie du réseau.", "Si une MPU5 est exigée, un joueur sans MPU5 n'a ni données ni relais, mais son téléphone reste utilisable. Vide = aucun objet exigé."]],
        ["comspec_overwatch_atak_realism", ["Réglage de COMSPEC Overwatch, lecture seule ici - niveau de dégâts au téléphone.", "Aucun = jamais de dégât ; Peut s'éteindre = le téléphone peut seulement s'éteindre ; Écran destructible ; Téléphone destructible."]],
        ["comspec_overwatch_roleplay_enabled", ["Réglage de COMSPEC Overwatch, lecture seule ici - mode roleplay du serveur.", "Actif, le mode roleplay du serveur est en marche ; il se change dans les réglages d'Overwatch."]],
        ["COMSPEC_ATAK_DirtFx", ["Votre choix personnel - voir ou non la saleté sur votre écran.", "Actif, vous voyez la poussière et le sang sur votre écran. Coupé, votre écran reste propre."]]
    ];
    missionNamespace setVariable ["COMSPEC_ATAK_RealismHelp", _h];
};
private _k = _var regexReplace ["^comspec_atak_native_", ""];
switch (_act) do {
    case "keys": { keys _h };
    case "has": { _k in _h };
    default {
        private _e = _h getOrDefault [_k, []];
        if (_e isEqualTo []) exitWith { "" };
        _e params ["_d", "_x"];
        private _out = format ["<br/><t size='0.8' color='#B9C2BB'>%1</t><br/><t size='0.8' color='#8FB6D8'>%2</t> <t size='0.8' color='#B9C2BB'>%3</t>", _d call _T, "Ex." call _T, _x call _T];
        if (_def isNotEqualTo "") then { _out = _out + format ["<br/><t size='0.8' color='#9AA59C'>%1 %2</t>", "Par défaut" call _T, _def]; };
        _out
    };
};
