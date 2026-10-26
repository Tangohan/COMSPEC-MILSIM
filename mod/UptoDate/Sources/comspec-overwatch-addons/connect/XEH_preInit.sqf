// Une seule PreInit par mission. Recocher Overwatch / relire l’addon
// ne doit pas ré-enregistrer réglages, raccourcis et événements CBA.
if (!isNil "COMSPEC_Overwatch_PreInitDone") exitWith {
    if (!isNil "comspec_overwatch_connect_fnc_log") then {
        ["WARN", "Boot", "PreInit ignoré — déjà exécuté cette mission"] call comspec_overwatch_connect_fnc_log;
    };
};
COMSPEC_Overwatch_PreInitDone = true;

// Compat modpack : si Mavic/mavik lit ses settings CBA avant enregistrement
// (ou si l'enregistrement a echoue), eviter le spam variable indefinie.
private _comspecCompatMavic = {
    if (isNil "mavic_setting_enableConnectionDistance") then {
        missionNamespace setVariable ["mavic_setting_enableConnectionDistance", false];
    };
    if (isNil "mavic_setting_maxConnectionDistance") then {
        missionNamespace setVariable ["mavic_setting_maxConnectionDistance", 6000];
    };
    if (isNil "mavic_setting_showInterface") then {
        missionNamespace setVariable ["mavic_setting_showInterface", true];
    };
    if (isNil "mavic_setting_vanillaInterface") then {
        missionNamespace setVariable ["mavic_setting_vanillaInterface", false];
    };
};
[] call _comspecCompatMavic;

if (!isNil "comspec_overwatch_connect_fnc_log") then {
    ["INFO", "Compat", format [
        "Mavic setting isNil=%1 | ZEN addAttribute isNil=%2",
        isNil "mavic_setting_enableConnectionDistance",
        isNil "zen_attributes_fnc_addAttribute"
    ]] call comspec_overwatch_connect_fnc_log;
};

// Stub ZEN attributes si ZEN incomplet dans le pack (évite zen_attributes_fnc_addAttribute nil)
if (isNil "zen_attributes_fnc_addAttribute") then {
    zen_attributes_fnc_addAttribute = {
        // no-op compat — ZEN partiel / mauvais ordre de chargement
        false
    };
    // Simple information (rien à corriger pour le joueur) : INFO et une seule fois par lancement d'Arma,
    // sinon chaque mission ajoutait une « Alerte · Compatibilité » au journal de l'appareil sur Athena.
    if (!isNil "comspec_overwatch_connect_fnc_log" && {!(uiNamespace getVariable ["COMSPEC_ZenStubLogged", false])}) then {
        uiNamespace setVariable ["COMSPEC_ZenStubLogged", true];
        ["INFO", "Compat", "Stub zen_attributes_fnc_addAttribute installé (ZEN incomplet ou absent)"] call comspec_overwatch_connect_fnc_log;
    };
};

[
    "comspec_overwatch_enabled", "CHECKBOX",
    ["Activer la liaison Athena", "Échanges avec Athena et le poste de commandement (positions, messages, ordres). Décochez pour tout couper : le téléphone reste utilisable hors ligne. Une session déjà ouverte reste affichée jusqu’à ce que vous quittiez Arma."],
    ["COMSPEC", "Overwatch"], true, false,
    {
        params ["_v"];
        // Recoché en mission alors qu'il était décoché au démarrage : fn_connect rejoue le bloc de démarrage.
        if (_v isEqualTo true && {hasInterface} && {!isNull findDisplay 46}
            && {missionNamespace getVariable ["COMSPEC_CbaSettingsBootFired", false]}
            && {!(missionNamespace getVariable ["COMSPEC_CbaSettingsBootArmed", false])}
            && {!isNil "comspec_overwatch_connect_fnc_connect"}) then {
            // Délai + recontrôle : le panneau Échap relance déjà fn_connect (0,2 s) après son propre réglage CBA.
            [] spawn {
                uiSleep 0.6;
                if (missionNamespace getVariable ["COMSPEC_CbaSettingsBootArmed", false]) exitWith {};
                if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
                [] call comspec_overwatch_connect_fnc_connect;
            };
        };
    }
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_donor_insignia", "CHECKBOX",
    ["Écusson « Donateur Athena »", "Si votre compte Athena a fait un don, un écusson à votre palier (Soutien, Allié, Pilier) est posé sur votre épaule. Il ne remplace jamais l’écusson donné par la mission. Décochez pour ne pas le porter."],
    ["COMSPEC", "Overwatch"], true, false,
    { if (!isNil "comspec_overwatch_connect_fnc_applyDonorPerk" && {hasInterface} && {!isNull player}) then { [player] call comspec_overwatch_connect_fnc_applyDonorPerk; }; }
] call comspec_overwatch_connect_fnc_addSetting;

// Adresse du portail : plus de réglage, toujours https://athena.ttrd.fr/public (fn_portalUrl).
// Une ancienne adresse enregistrée dans le profil est effacée.
missionNamespace setVariable ["comspec_overwatch_api_url", "https://athena.ttrd.fr/public"];
if (hasInterface && {!isNil {profileNamespace getVariable "comspec_overwatch_saved_api_url"}}) then {
    profileNamespace setVariable ["comspec_overwatch_saved_api_url", nil];
};

// Clé et communauté : plus rien à saisir pour un joueur, la connexion au compte Athena (code ou Steam)
// donne la communauté. Gardées en option côté serveur (valeur imposée à tous par le serveur ou la mission).
[
    "comspec_overwatch_api_key", "EDITBOX",
    ["Clé de la communauté (serveur, optionnel)", "À laisser vide : chaque joueur se connecte avec son compte Athena, qui donne sa communauté. À remplir seulement si l’admin de votre serveur vous le demande. Le serveur peut l’imposer à tous en forçant le réglage."],
    ["COMSPEC", "Overwatch · Serveur (facultatif)"], ""
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_tenant_id", "EDITBOX",
    ["Identifiant de la communauté (serveur, optionnel)", "À laisser vide : la communauté vient du compte Athena du joueur. À remplir seulement pour imposer une communauté sur ce serveur (en forçant le réglage côté serveur)."],
    ["COMSPEC", "Overwatch · Serveur (facultatif)"], ""
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_server_hub", "CHECKBOX",
    ["Le serveur centralise les échanges", "Coché : le serveur (dédié ou hôte) interroge Athena une seule fois pour tout le monde au sujet des relais, zones roleplay et ordres aux IA, au lieu que chaque joueur le fasse. Allège la charge. Chaque joueur garde sa propre liaison pour sa position et son téléphone."],
    ["COMSPEC", "Overwatch"], true, true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_map_id", "SLIDER",
    ["Numéro de carte de l'opération", "Numéro de la carte Athena utilisée par cette mission. Changez-le si plusieurs opérations tournent en même temps, pour ne pas mélanger les positions."],
    ["COMSPEC", "Overwatch"], [1, 99, 1, 0],
    1,
    {
        private _id = round (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
        if (_id < 1) then { _id = 1; };
        missionNamespace setVariable ["COMSPEC_MapId", _id];
        missionNamespace setVariable ["comspec_overwatch_map_id", _id];
        if (!isNil "comspec_overwatch_connect_fnc_extResult") then {
            ["COMSPECExtension" callExtension ["SetMapId", [str _id]]] call comspec_overwatch_connect_fnc_extResult;
        };
    }
] call comspec_overwatch_connect_fnc_addSetting;


private _fnc_applyNet = {
    if (!isNil "comspec_overwatch_connect_fnc_applyNetworkProfile") then {
        [] call comspec_overwatch_connect_fnc_applyNetworkProfile;
    };
};

[
    "comspec_overwatch_network_authority", "LIST",
    ["Qui choisit le profil réseau", "Imposé par le serveur : tout le monde utilise le profil du serveur. Choix libre du joueur : chacun choisit le sien. Joueur plafonné : chacun choisit, sans dépasser le profil du serveur."],
    ["COMSPEC", "Overwatch · Réseau"],
    [
        [0, 1, 2],
        ["Imposé par le serveur", "Choix libre du joueur", "Joueur plafonné par le serveur"],
        0
    ],
    1,
    _fnc_applyNet
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_network_profile_server", "LIST",
    ["Profil réseau du serveur", "Fréquence des échanges avec Athena. Économie : le moins possible. Standard (recommandé) : bon équilibre. Tactique et Temps réel : très réactif, à réserver aux petites opérations. La charge estimée est affichée dans le journal de liaison."],
    ["COMSPEC", "Overwatch · Réseau"],
    [
        [0, 1, 2, 3, 4],
        [
            "Économie (peu d'échanges)",
            "Standard (recommandé)",
            "Tactique (beaucoup d'échanges)",
            "Temps réel (énormément d'échanges)",
            "Personnalisé"
        ],
        1
    ],
    1,
    _fnc_applyNet
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_network_profile", "LIST",
    ["Profil réseau du joueur", "Votre profil, utilisé si le serveur laisse le choix aux joueurs. S'il est plafonné, vous ne pouvez pas dépasser le profil du serveur."],
    ["COMSPEC", "Overwatch · Réseau"],
    [
        [0, 1, 2, 3, 4],
        [
            "Économie (peu d'échanges)",
            "Standard (recommandé)",
            "Tactique (beaucoup d'échanges)",
            "Temps réel (énormément d'échanges)",
            "Personnalisé"
        ],
        1
    ],
    0,
    _fnc_applyNet
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_network_policy", "LIST",
    ["Quand envoyer sa position", "À intervalle fixe : envoi régulier, même à l'arrêt. Quand ça change : seulement si vous bougez, tournez ou changez d'état. Mixte (recommandé) : quand ça change, plus un signal de présence de temps en temps."],
    ["COMSPEC", "Overwatch · Réseau"],
    [
        [0, 1, 2],
        ["À intervalle fixe", "Quand ça change", "Mixte (recommandé)"],
        2
    ],
    1,
    _fnc_applyNet
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_heartbeat_interval", "SLIDER",
    ["Signal de présence (s), profil Personnalisé", "Temps entre deux signaux « toujours là » envoyés à Athena quand vous ne bougez pas. Seulement avec le profil Personnalisé."],
    ["COMSPEC", "Overwatch · Réseau"],
    [8, 120, 30, 0],
    0,
    _fnc_applyNet
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_position_interval", "SLIDER",
    ["Envoi de position (s), profil Personnalisé", "Temps minimum entre deux envois de position. Seulement avec le profil Personnalisé."],
    ["COMSPEC", "Overwatch · Réseau"],
    [1, 60, 5, 0],
    0,
    _fnc_applyNet
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_batch_interval", "SLIDER",
    ["Regroupement des envois (s), profil Personnalisé", "Les positions sont regroupées pendant ce temps avant d'être envoyées ensemble. Seulement avec le profil Personnalisé."],
    ["COMSPEC", "Overwatch · Réseau"],
    [0.25, 5, 1, 2],
    0,
    _fnc_applyNet
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_position_threshold", "SLIDER",
    ["Distance avant envoi (m), profil Personnalisé", "La position est envoyée quand vous avez bougé de plus que cette distance à pied (calculée automatiquement en véhicule et en vol). Seulement avec le profil Personnalisé."],
    ["COMSPEC", "Overwatch · Réseau"],
    [1, 50, 3, 0],
    0,
    _fnc_applyNet
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_terminal_mode", "LIST",
    ["Item qui autorise l'envoi de position", "Quel item compte comme téléphone pour envoyer votre position. Emplacement : ItemAndroid équipé dans un emplacement (comme un GPS ou des JVN). Inventaire : ItemAndroidMisc rangé dans l'inventaire (item cTab). Les deux : l'un ou l'autre."],
    ["COMSPEC", "Overwatch"],
    [
        [0, 1, 2],
        ["Équipé seulement (ItemAndroid)", "Dans l'inventaire (ItemAndroidMisc)", "Les deux (par défaut)"],
        2
    ],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_playtime_enabled", "CHECKBOX",
    ["Enregistrer le temps de jeu", "Envoie à Athena le temps passé sur le serveur, en Zeus et dans l’éditeur Eden."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_playtime_report_interval", "SLIDER",
    ["Envoi du temps de jeu (min)", "Toutes les combien de minutes le temps de jeu est envoyé à Athena."],
    ["COMSPEC", "Overwatch"], [2, 60, 5, 0]
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_vehicle_mode", "CHECKBOX",
    ["Détails en véhicule", "Envoie aussi l’orientation et la vitesse quand le joueur est dans un véhicule."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_sync_map_markers", "CHECKBOX",
    ["Synchroniser les marqueurs", "Les marqueurs posés, modifiés ou supprimés en jeu apparaissent aussi sur Athena."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_profile_enabled", "CHECKBOX",
    ["Mesure des performances (debug)", "Mesure le temps pris par les tâches lourdes du mod (position, CAS, marqueurs). Résultat dans l'app Debug. Désactivé, ça ne coûte rien."],
    ["COMSPEC", "Overwatch"], false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_notif_sound", "LIST",
    ["Son des notifications", "Son joué avec les alertes (messages, ordres, connexion). Les urgences médicales ont leur propre son, même en « Vibreur seul ». Seul « Muet » coupe tout. Le mode discret ne coupe pas ces sons. Aussi réglable dans l’app Sons du téléphone."],
    ["COMSPEC", "Overwatch"],
    [
        ["silent_vib", "stalker", "health", "mute"],
        ["Vibreur seul", "Ambiance tendue", "Bip médical", "Muet (sans vibreur)"],
        0
    ],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_sound_master", "SLIDER",
    ["Volume général de l'ATAK", "Volume de tous les sons du téléphone (alertes, vibreur, effets de liaison), sans toucher au jeu, à ACRE ou aux autres mods. 0 = téléphone muet. Aussi réglable dans l’app Sons du téléphone."],
    ["COMSPEC", "Overwatch"],
    [0, 1, 1, 2],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_sound_notif_vol", "SLIDER",
    ["Volume des alertes", "Volume des sons de notification (connexion, ordres, alertes). Aussi réglable dans l’app Sons du téléphone."],
    ["COMSPEC", "Overwatch"],
    [0, 1, 1, 2],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_sound_vibrate_vol", "SLIDER",
    ["Volume du vibreur", "Volume du vibreur, par exemple quand le poste de commandement fait vibrer votre téléphone. Aussi réglable dans l’app Sons du téléphone."],
    ["COMSPEC", "Overwatch"],
    [0, 1, 1, 2],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_sound_fx_vol", "SLIDER",
    ["Volume des effets réseau", "Sons d'ambiance des zones, de coupure réseau, d'écran abîmé... Aussi réglable dans l’app Sons du téléphone."],
    ["COMSPEC", "Overwatch"],
    [0, 1, 0.8, 2],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_webbrowser_enabled", "CHECKBOX",
    ["Ancienne tablette Overwatch : écran web", "Ne concerne que l'ancienne tablette Overwatch (hors téléphone ATAK) : coché, elle s'ouvre avec l’écran web intégré ; décoché, en vue simple."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_screen_notifications", "CHECKBOX",
    ["Bandeaux d'alerte à l’écran", "Décoché par défaut. Coché : de petits bandeaux s'affichent en bas à gauche de l'écran (ex. « Alerte médicale transmise… »). Rien n'est jamais écrit dans le chat du jeu : les messages du poste restent dans le téléphone. Les sons suivent le réglage « Son des notifications »."],
    ["COMSPEC", "Overwatch"], false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_quiet_mode", "CHECKBOX",
    ["Mode discret : cacher les bandeaux", "Cache les bandeaux d'alerte même si « Bandeaux d'alerte à l’écran » est coché. Les sons continuent, sauf en « Muet ». Les alertes restent dans le téléphone."],
    ["COMSPEC", "Overwatch"], false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_milsim_ui", "CHECKBOX",
    ["Mode milsim : moins d'aides à l'écran", "Pour plus d'immersion : plus d'alertes de suivi (ex. « immobile »), plus de messages d'aide ni de bandeaux à l’écran (même si « Bandeaux d'alerte à l’écran » est coché). La liaison Athena et l'envoi de position continuent, et les alertes médicales et les ordres arrivent toujours dans le téléphone."],
    ["COMSPEC", "Overwatch"], false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_require_item", "CHECKBOX",
    ["Téléphone obligatoire pour la liaison Athena", "Coché par défaut : la liaison avec Athena et les menus ATAK ne marchent que si vous avez sur vous l'item choisi ci-dessous (téléphone ATAK, tablette cTab...). Ne décochez que pour tester sans équipement."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_required_item", "LIST",
    ["Item requis", "Item qu'il faut avoir sur soi quand « Téléphone obligatoire pour la liaison Athena » est coché."],
    ["COMSPEC", "Overwatch"],
    [
        ["ItemAndroid", "ItemcTab", "ItemMicroDAGR", "ACE_microDAGR", "ItemGPS", "ItemWatch"],
        ["Téléphone Android (cTab / ATAK)", "Tablette cTab", "MicroDAGR (cTab)", "MicroDAGR (ACE)", "GPS", "Montre"],
        0
    ],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_required_item_custom", "EDITBOX",
    ["Item personnalisé (optionnel)", "Laissez vide pour utiliser la liste ci-dessus. Sinon, mettez le nom de classe de l’item de votre pack d’équipement : il remplace le choix de la liste."],
    ["COMSPEC", "Overwatch"], ""
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_atak_ui_only", "CHECKBOX",
    [
        "Tout passe par le téléphone ATAK (recommandé)",
        "Coché par défaut : les touches Overwatch ouvrent le téléphone ATAK. Décochez seulement pour retrouver l'ancienne tablette Overwatch séparée. La liaison Athena marche dans les deux cas."
    ],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;


[
    "comspec_overwatch_reset_beta_nda", "CHECKBOX",
    [
        "Revoir le message de bienvenue de la bêta",
        "Cochez puis validez pour revoir le message de bienvenue de la bêta publique. La case se décoche toute seule et votre inscription à la bêta reste valable."
    ],
    ["COMSPEC", "Overwatch"],
    false,
    false,
    {
        params ["_value"];
        if (!_value) exitWith {};
        0 spawn {
            uiSleep 0.05;
            // Au chargement CBA (boot), la case peut être coincée à « true » dans le profil :
            // on la décoche SANS effacer l'acceptation NDA. L'effacement n'a lieu que si le
            // joueur coche la case après le démarrage (menu Options / mission).
            private _bootGuard = missionNamespace getVariable ["COMSPEC_NDA_ResetBootGuard", true];
            if (_bootGuard) then {
                if !(isNil "CBA_fnc_setSetting") then {
                    ["comspec_overwatch_reset_beta_nda", false, 0, "client", true] call CBA_fnc_setSetting;
                };
            } else {
                if !(isNil "comspec_overwatch_connect_fnc_resetBetaNdaAck") then {
                    [] call comspec_overwatch_connect_fnc_resetBetaNdaAck;
                };
                if !(isNil "CBA_fnc_setSetting") then {
                    ["comspec_overwatch_reset_beta_nda", false, 0, "client", true] call CBA_fnc_setSetting;
                };
            };
        };
    }
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ace_menus", "CHECKBOX",
    [
        "Menus ACE Overwatch complets",
        "Ajoute au menu ACE les rapports, la demande d’appui, le service véhicule et la réparation du téléphone. « Connexion Athena » reste toujours en haut du menu. Ne décochez que si votre pack de mods affiche des erreurs ACE au lancement (il peut falloir relancer la mission)."
    ],
    ["COMSPEC", "Overwatch"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_hud", "CHECKBOX",
    [
        "Infos tactiques dans les JVN",
        "Décoché par défaut. Coché : alliés, marqueurs et véhicules s'affichent dans votre vision quand vous portez des JVN, avec boussole et étiquettes, comme un casque connecté. Aussi réglable dans le téléphone (Réglages). Sans effet si le mod F-PANO ECOTI est chargé."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_nvg_only", "CHECKBOX",
    [
        "Seulement JVN allumées",
        "Coché : les infos tactiques n’apparaissent que quand vos JVN sont allumées."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_show_allies", "CHECKBOX",
    ["Afficher les alliés", "Montre les alliés proches et leur distance."],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_show_markers", "CHECKBOX",
    ["Afficher les marqueurs", "Montre les marqueurs de la carte dans votre champ de vision."],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_show_vehicles", "CHECKBOX",
    ["Afficher les véhicules", "Montre les véhicules et aéronefs alliés proches."],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_show_outline", "CHECKBOX",
    [
        "Encadrer ce que vous regardez",
        "Dessine un cadre autour de ce que vous regardez quand les infos tactiques sont actives."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_show_route", "CHECKBOX",
    [
        "Afficher l'itinéraire",
        "Montre sous JVN l’itinéraire envoyé par le poste et vos propres points de passage."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_building_cutaway", "CHECKBOX",
    [
        "Vue par étage d'un bâtiment",
        "Décoché par défaut, demande les infos tactiques JVN. Sur un bâtiment désigné : n'affiche son contour que jusqu'à l'étage choisi, met en évidence le sol de cet étage et ses points intérieurs. Les murs restent opaques (limite du jeu). Menu ACE : Changer d’étage ou Découper à la hauteur regardée."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    false,
    0,
    {
        params ["_value"];
        if (!(_value isEqualType true)) then { _value = false; };
        if (!_value) then {
            missionNamespace setVariable ["COMSPEC_EcotiCutawayFloor", 0, false];
        };
    }
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_theme", "LIST",
    [
        "Couleur des infos tactiques",
        "Couleur des étiquettes, icônes, textes, contours de bâtiments et silhouettes affichés sous JVN. « Cyan clair » est le plus lisible de nuit."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    [
        ["nvg", "lime", "amber", "white", "blue"],
        ["Cyan clair (JVN)", "Vert lime", "Ambre", "Blanc", "Bleu"],
        0
    ]
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_render_mode", "LIST",
    [
        "Position des étiquettes",
        "Accrochées dans le décor : les étiquettes restent collées à ce qu'elles désignent. À plat sur l’écran : elles s'affichent comme un HUD, se décalent pour ne pas se chevaucher et s'estompent avec la distance. Les contours de bâtiments restent en 3D dans les deux cas."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    [
        ["world3d", "screen2d"],
        ["Accrochées dans le décor (3D)", "À plat sur l’écran (sans chevauchement)"],
        0
    ]
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_show_unit_outline", "CHECKBOX",
    [
        "Silhouette des alliés",
        "Dessine le contour des alliés proches, en plus de leur nom et de leur distance."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_tube_fx", "CHECKBOX",
    [
        "Effet tube des JVN (grain, contraste)",
        "Ajoute du grain, un léger flou sur les bords et du contraste, comme dans de vraies JVN. Complète les JVN d’ACE sans les remplacer. Décochez si un autre mod gère déjà cet effet."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_autogate", "CHECKBOX",
    [
        "Protection contre les phares",
        "Quand un véhicule vous éclaire, l'image des JVN s'assombrit un instant pour éviter l’éblouissement. Les JVN améliorées d’ACE gèrent déjà en partie cet effet."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_gps_hud", "CHECKBOX",
    [
        "Boussole et coordonnées dans les JVN",
        "Affiche dans les JVN votre direction, vos coordonnées, la distance de ce que vous regardez et l’heure. Pour n’avoir que la direction, cochez aussi « Boussole uniquement »."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_compass_only", "CHECKBOX",
    [
        "Boussole uniquement",
        "N’affiche que votre direction dans les JVN, sans coordonnées, distance ni heure. Demande les infos tactiques JVN."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_fusion", "CHECKBOX",
    [
        "Halo sur les sources chaudes",
        "Ajoute un halo clair autour des alliés proches, des véhicules moteur allumé et du bâtiment désigné, comme une vision thermique. Ne s'ajoute pas si un autre mod fait déjà cet effet."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_badge_plate", "CHECKBOX",
    [
        "Fond derrière les étiquettes",
        "Étiquettes à plat sur l’écran uniquement : léger fond derrière le nom et la distance. Décochez pour du texte seul (recommandé sous JVN)."
    ],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_show_link_strip", "CHECKBOX",
    [
        "Barre d'infos sous la carte",
        "Ancien bandeau sous la carte du téléphone (vitesse, altitude, cap, coordonnées). Il n'est plus affiché : ce réglage n'a plus d'effet."
    ],
    ["COMSPEC", "Overwatch · Téléphone ATAK"],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_link_degrade_sim", "CHECKBOX",
    [
        "Liaison instable simulée",
        "Décoché par défaut. Coché : la liaison avec le poste perd des envois, devient peu fiable et coupe brièvement par moments. Aussi réglable dans le téléphone (Réglages). Décoché : l’état affiché est toujours celui de la vraie liaison."
    ],
    ["COMSPEC", "Overwatch · Téléphone ATAK"],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_max_dist", "SLIDER",
    ["Distance max des infos JVN (m)", "Au-delà de cette distance, rien n'est affiché dans les JVN."],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    [200, 2500, 1200, 0]
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_ecoti_max_icons", "SLIDER",
    ["Nombre max d’éléments affichés", "Limite le nombre d’icônes affichées en même temps, pour garder un jeu fluide."],
    ["COMSPEC", "Overwatch · Infos tactiques JVN"],
    [10, 80, 40, 0]
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_log_level", "LIST",
    [
        "Niveau de détail du journal (RPT)",
        "Quantité d'infos écrites dans le journal d'Arma (fichier RPT). « Détaillé » sert à dépanner un pack de mods ; laissez « Normal » le reste du temps."
    ],
    ["COMSPEC", "Overwatch · Diagnostic"],
    [
        [0, 1, 2, 3, 4],
        ["Désactivé", "Erreurs seulement", "Avertissements", "Normal", "Détaillé"],
        3
    ],
    false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_log_to_file", "CHECKBOX",
    [
        "Écrire aussi dans un fichier de log",
        "En plus du RPT, écrit un fichier de log par session dans %LOCALAPPDATA%\\Arma 3\\COMSPEC\\logs. Les 12 derniers sont gardés, les plus vieux supprimés. Sans effet si le niveau du journal est « Désactivé »."
    ],
    ["COMSPEC", "Overwatch · Diagnostic"],
    true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_radio_proximity_enabled", "CHECKBOX",
    ["Radios actives autour de vous", "Repère qui parle à la radio près de vous (ou du joueur suivi) et l'indique sur Athena. Demande ACRE2 ou TFAR ; sans eux, les indicateurs restent gris."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_radio_proximity_radius", "SLIDER",
    ["Rayon de détection radio (m)", "Les radios actives dans ce rayon autour du joueur sont listées."],
    ["COMSPEC", "Overwatch"], [10, 300, 75, 0]
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_radio_proximity_interval", "SLIDER",
    ["Mise à jour de la détection radio (s)", "Temps entre deux mises à jour de la liste des radios proches (calcul local, rien n'est envoyé sur le réseau)."],
    ["COMSPEC", "Overwatch"], [1, 10, 2, 1]
] call comspec_overwatch_connect_fnc_addSetting;

// Affichage camps sur Tacmap (inspiré Athena Remastered ATH_showEast/Guer/Civ)
[
    "comspec_overwatch_show_opfor", "CHECKBOX",
    ["Montrer l’ennemi sur la carte Athena", "Les observateurs sur Athena voient les positions du camp ennemi."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_show_independent", "CHECKBOX",
    ["Montrer les indépendants sur la carte Athena", "Les observateurs sur Athena voient les positions des indépendants."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_show_civilian", "CHECKBOX",
    ["Montrer les civils sur la carte Athena", "Les observateurs sur Athena voient les positions des civils."],
    ["COMSPEC", "Overwatch"], true
] call comspec_overwatch_connect_fnc_addSetting;

// Raccourcis (CBA — personnalisables dans Options > Contrôles > Extension Addon)
// Pas de défaut sur K : la boussole native Arma utilise K.
// Ctrl+K / Ctrl+Shift+K restent proposés (messagerie / apps).
// Une fois par profil : écrase l’ancien défaut K pour rendre la boussole.
private _hubOverwrite = !(profileNamespace getVariable ["COMSPEC_KeybindHubKFreed_v1", false]);
if (_hubOverwrite) then {
    profileNamespace setVariable ["COMSPEC_KeybindHubKFreed_v1", true];
    [] call comspec_overwatch_connect_fnc_saveProfile;
};
[
    "COMSPEC Overwatch", "comspec_menu_hub",
    ["Ouvrir le téléphone ATAK", "Ouvre le téléphone ATAK (ou l'ancienne tablette Overwatch si « Tout passe par le téléphone ATAK » est décoché). Pas de touche par défaut : K reste la boussole du jeu."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {
            0 spawn { [] call comspec_overwatch_connect_fnc_openAtakEnhanced; };
            true
        };
        if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith { false };
        0 spawn { [] call comspec_overwatch_connect_fnc_webBrowserShow; };
        true
    },
    "",
    [], // plus de DIK_K — boussole native
    false, 0, _hubOverwrite
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_menu_chat",
    ["Ouvrir la messagerie", "Ouvre la messagerie Athena dans le téléphone ATAK (Ctrl+K)."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {
            0 spawn { ["messages"] call comspec_overwatch_connect_fnc_openAthenaFeature; };
            true
        };
        if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith { false };
        0 spawn { ["chat"] call comspec_overwatch_connect_fnc_openTabletView; };
        true
    },
    "",
    [0x25, [false, true, false]], // Ctrl+DIK_K  — [shift, ctrl, alt]
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_menu_hub_csk",
    ["Ouvrir l'app Athena", "Ouvre l'app Athena dans le téléphone ATAK (Ctrl+Maj+K)."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {
            0 spawn { ["all"] call comspec_overwatch_connect_fnc_openAthenaFeature; };
            true
        };
        if !([false] call comspec_overwatch_connect_fnc_canOpenOverwatchUi) exitWith { false };
        0 spawn { ["apps"] call comspec_overwatch_connect_fnc_openTabletView; };
        true
    },
    "",
    [0x25, [true, true, false]], // Ctrl+Shift+DIK_K
    false, 0, false
] call CBA_fnc_addKeybind;

// === ATAK Tactique - Raccourcis configurables ===

[
    "comspec_overwatch_frs_size", "SLIDER",
    ["Taille de la fiche FRS / FRM", "Taille de la fenêtre de rédaction des fiches dans l’ATAK. 100 % = tout l'écran du téléphone. En dessous, la fiche reste centrée et on voit le téléphone autour."],
    ["COMSPEC", "Overwatch · Fiches FRS / FRM"],
    [0.55, 1, 1, 0, true],
    false,
    {
        if (!hasInterface) exitWith {};
        if (isNull (uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull])) exitWith {};
        [] call comspec_overwatch_connect_fnc_intelNoteApplyGeometry;
        [] call comspec_overwatch_connect_fnc_intelNoteRefresh;
    }
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_frs_ui", "SLIDER",
    ["Texte et boutons de la fiche", "Agrandit ou réduit le texte et les boutons de la fiche, sans changer sa taille. Pratique si c'est trop petit ou trop chargé."],
    ["COMSPEC", "Overwatch · Fiches FRS / FRM"],
    [0.75, 1.45, 1.2, 0, true],
    false,
    {
        if (!hasInterface) exitWith {};
        if (isNull (uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull])) exitWith {};
        [] call comspec_overwatch_connect_fnc_intelNoteApplyGeometry;
        [] call comspec_overwatch_connect_fnc_intelNoteRefresh;
    }
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_atak_enable_shortcuts", "CHECKBOX",
    ["Touches de rapport rapide", "Active les touches de rapport rapide (contact, point d'intérêt, MEDEVAC, renfort). Décochez en cas de conflit avec cTab ou d'autres mods."],
    ["COMSPEC", "Overwatch · ATAK Tactique"], false  // Désactivé par défaut
] call comspec_overwatch_connect_fnc_addSetting;

[
    "COMSPEC Overwatch", "comspec_atak_quick_report", ["Rapport de contact rapide", "Envoie tout de suite un rapport de contact ennemi à votre position."],
    {
        if (!(missionNamespace getVariable ["comspec_atak_enable_shortcuts", false])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        ["CONTACT", "IMMEDIATE", "Contact ennemi", "Ennemi détecté"] call comspec_overwatch_connect_fnc_submitTacticalReport;
        true
    },
    "",
    [], // Pas de touche par défaut - utilisateur doit configurer
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_atak_quick_poi", ["Point d'intérêt rapide", "Marque un point d'intérêt à votre position."],
    {
        if (!(missionNamespace getVariable ["comspec_atak_enable_shortcuts", false])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        ["POI observé", "OTHER", "UNKNOWN", "POSSIBLE", "Marqué depuis terrain"] call comspec_overwatch_connect_fnc_createPOI;
        true
    },
    "",
    [], // Pas de touche par défaut - utilisateur doit configurer
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_atak_quick_medevac", ["MEDEVAC rapide", "Demande une évacuation sanitaire urgente."],
    {
        if (!(missionNamespace getVariable ["comspec_atak_enable_shortcuts", false])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        // ATAK natif : l'app MEDICAL du téléphone, pas l'ancienne fenêtre MEDEVAC plein écran.
        if (["MEDICAL", "Équipez un téléphone ATAK pour demander une évacuation sanitaire."] call comspec_overwatch_connect_fnc_openNativePage) exitWith { true };
        [] call comspec_overwatch_connect_fnc_medevacDialogShow;
        true
    },
    "",
    [], // Pas de touche par défaut
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_atak_quick_qrf", ["Demande de QRF rapide", "Demande des renforts en urgence."],
    {
        if (!(missionNamespace getVariable ["comspec_atak_enable_shortcuts", false])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        ["TROOPS_IN_CONTACT", "IMMEDIATE", "Besoin renfort immédiat", "SQUAD", 0, "ENGAGED", getPosWorld player] call comspec_overwatch_connect_fnc_requestQRF;
        true
    },
    "",
    [], // Pas de touche par défaut

    false, 0, false
] call CBA_fnc_addKeybind;



// Cache position / tracking tactique (local client — jamais public depuis le dédié)
if (hasInterface) then {
    missionNamespace setVariable ["COMSPEC_lastPos", [0,0,0], false];
    missionNamespace setVariable ["COMSPEC_lastName", "", false];
    missionNamespace setVariable ["COMSPEC_lastRole", "", false];
    missionNamespace setVariable ["COMSPEC_lastRadio", "", false];
    missionNamespace setVariable ["COMSPEC_lastMedical", "", false];
    missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
    missionNamespace setVariable ["COMSPEC_lastMedicalAlertAt", -1e9, false];
    missionNamespace setVariable ["COMSPEC_IcemanMedicalPushed", createHashMap, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertsSeen", [], false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertsBootstrapped", false, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlerts", [], false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", false, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertBusy", false, false];
    missionNamespace setVariable ["COMSPEC_RespawnGraceUntil", -1e9, false];
    missionNamespace setVariable ["COMSPEC_SuppressWinMessageBoxUntil", -1e9, false];
    missionNamespace setVariable ["COMSPEC_CancelPendingAthenaHelp", false, false];
    missionNamespace setVariable ["COMSPEC_DeathThenRespawn", false, false];
    missionNamespace setVariable ["COMSPEC_VehicleTrackingInited", false, false];
    missionNamespace setVariable ["COMSPEC_lastSendTime", 0, false];
    missionNamespace setVariable ["COMSPEC_lastHeading", -1, false];
    missionNamespace setVariable ["COMSPEC_lastVehSig", "", false];
    missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
    missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
    missionNamespace setVariable ["COMSPEC_SendBackoffSec", 0, false];
    missionNamespace setVariable ["COMSPEC_PositionTrail", [], false];
    missionNamespace setVariable ["COMSPEC_ImmobileSince", 0, false];
    missionNamespace setVariable ["COMSPEC_ImmobileAlerted", false, false];
    missionNamespace setVariable ["COMSPEC_IncoherentAlertAt", -1e9, false];
};



// Badges UI — liaison Athena
missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
missionNamespace setVariable ["COMSPEC_LastPositionSync", -1, false];
missionNamespace setVariable ["COMSPEC_LastHealthOk", -1, false];
missionNamespace setVariable ["COMSPEC_LastLatencyMs", -1, false];
missionNamespace setVariable ["COMSPEC_LastLatencyAt", -1, false];

// Bus d'évènements + C2 + Intel Engine
// EventBus / caches locaux : pas de broadcast depuis le dédié.
// Orders / OrderLog : locaux uniquement (un HashMap public plante le moteur).
if (hasInterface || {isServer}) then {
    if (isNil {missionNamespace getVariable "COMSPEC_EventBus"}) then {
        missionNamespace setVariable ["COMSPEC_EventBus", createHashMap, false];
    };
    if (isNil {missionNamespace getVariable "COMSPEC_Orders"}) then {
        missionNamespace setVariable ["COMSPEC_Orders", [], isServer];
    };
    if (isNil {missionNamespace getVariable "COMSPEC_OrderLog"}) then {
        missionNamespace setVariable ["COMSPEC_OrderLog", [], isServer];
    };
};
if (hasInterface) then {
    missionNamespace setVariable ["COMSPEC_IntelStore", [], false];
    missionNamespace setVariable ["COMSPEC_IntelHeatmap", createHashMap, false];
    missionNamespace setVariable ["COMSPEC_RadioReplay", [], false];
    missionNamespace setVariable ["COMSPEC_Comms_Channel", "general", false];
    missionNamespace setVariable ["COMSPEC_Comms_Priority", "ROUTINE", false];
    missionNamespace setVariable ["COMSPEC_Comms_View", "list", false];
    missionNamespace setVariable ["COMSPEC_Comms_Unread", createHashMap, false];
    missionNamespace setVariable ["COMSPEC_Comms_Messages", [], false];
    missionNamespace setVariable ["COMSPEC_OrdersSeen", [], false];
};

// Restaure l’indicatif profil dès le preInit (avant les lectures CAS / recon)
private _savedCallsign = trim (profileNamespace getVariable ["COMSPEC_Callsign", ""]);
if (!(_savedCallsign isEqualTo "")) then {
    missionNamespace setVariable ["COMSPEC_Callsign", _savedCallsign, false];
};

// Rôle tactique + file d’alertes tablette
private _savedRole = trim (profileNamespace getVariable ["COMSPEC_Role", ""]);
if (!(_savedRole isEqualTo "")) then {
    missionNamespace setVariable ["COMSPEC_Role", _savedRole, false];
};
private _savedTeam = toUpper (trim (profileNamespace getVariable ["COMSPEC_AssignedTeam", ""]));
if (_savedTeam in ["RED", "GREEN", "BLUE", "YELLOW", "MAIN"]) then {
    missionNamespace setVariable ["COMSPEC_AssignedTeam", _savedTeam, false];
};
private _savedFt = profileNamespace getVariable ["COMSPEC_FireTeamId", 0];
if (_savedFt isEqualType 0 && {_savedFt > 0}) then {
    missionNamespace setVariable ["COMSPEC_FireTeamId", _savedFt, false];
};
private _savedBftMode = profileNamespace getVariable ["COMSPEC_BftLabelMode", "cs"];
if (_savedBftMode in ["cs", "cs_role"]) then {
    missionNamespace setVariable ["COMSPEC_BftLabelMode", _savedBftMode, false];
};
missionNamespace setVariable ["COMSPEC_HtmlAlerts", [], false];

// Petit modèle (vue classique idd 9973) — désactivé temporairement (bascule auto coupait la tablette Athena).
missionNamespace setVariable ["comspec_overwatch_classic_tablet_enabled", false, false];

// ────────────────────────────────────────────────────────────────────────────
// Roleplay et simulation
// ────────────────────────────────────────────────────────────────────────────

[
    "comspec_overwatch_roleplay_enabled", "CHECKBOX",
    ["Activer le mode roleplay", "Active les pannes simulées (réseau, capteurs). Le détail se règle sur Athena (Configuration ATAK > Expérience en jeu), comme le « mode troll » de la communauté."],
    ["COMSPEC", "Overwatch · Roleplay"], false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_roleplay_network_failures", "CHECKBOX",
    ["Pannes réseau simulées", "Retards, envois perdus et coupures brèves, comme « Liaison instable simulée ». L'intensité se règle sur Athena."],
    ["COMSPEC", "Overwatch · Roleplay"], false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_roleplay_sensor_failures", "CHECKBOX",
    ["Pannes des capteurs médicaux", "Le capteur de pouls affiché sur la carte Athena tombe parfois en panne (valeur absente, fausse ou à zéro). La fréquence des pannes se règle sur Athena."],
    ["COMSPEC", "Overwatch · Roleplay"], false
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_roleplay_visual_effects", "CHECKBOX",
    ["Écran qui grésille", "Parasites, bugs d'affichage et messages d'erreur à l'écran quand la liaison faiblit."],
    ["COMSPEC", "Overwatch · Roleplay"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_atak_realism", "LIST",
    [
        "Réalisme ATAK (dégâts au téléphone)",
        "Limite les dégâts que le téléphone peut subir quand on est touché. Niveau 1 : il peut seulement s'éteindre (réparable). Niveau 2 : l'écran peut casser, la connexion reste. Niveau 3 : le téléphone peut être détruit et la connexion coupée."
    ],
    ["COMSPEC", "Overwatch · Roleplay"],
    [[0, 1, 2, 3], ["Désactivé", "Niveau 1 : extinction", "Niveau 2 : écran cassé", "Niveau 3 : téléphone détruit"], 0],
    1
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_overwatch_order_compose_enabled", "CHECKBOX",
    ["Rédiger des ordres et FRAGO en jeu", "Les chefs d’unité peuvent rédiger et envoyer un ordre ou un FRAGO directement en jeu, sans passer par Athena."],
    ["COMSPEC", "Ordres"], true
] call comspec_overwatch_connect_fnc_addSetting;

[
    "comspec_sse_require_item", "CHECKBOX",
    [
        "Terminal SEEK obligatoire",
        "Il faut un terminal biométrique (SEEK, BII-10 ou téléphone ATAK) pour ouvrir une fiche. Décochez pour y accéder sans rien."
    ],
    ["COMSPEC", "SSE"], true
] call comspec_overwatch_connect_fnc_addSetting;

private _sseKeyDefault = [0x1F, [true, true, false]]; // Ctrl+Shift+S
if (isClass (configFile >> "CfgPatches" >> "comspec_sse_biometrics")) then {
    // Le mod SSE possede deja ce raccourci : eviter deux ouvertures superposees.
    _sseKeyDefault = [];
};

[
    "COMSPEC Overwatch", "comspec_open_sse_fiche",
    ["Ouvrir une fiche SSE (FRS / SEEK)", "Ouvre le terminal de fiche renseignement. Par défaut : Ctrl+Maj+S (si le mod SSE n'est pas chargé)."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        private _ok = [] call comspec_overwatch_connect_fnc_sseOpenFromKeybind;
        if (_ok isEqualType true) exitWith { _ok };
        // Ouverture tentée sans booléen explicite : consommer la touche seulement si pas d’échec clair
        !(_ok isEqualTo false)
    },
    "",
    _sseKeyDefault,
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_order_compose_key",
    ["Ouvrir rédaction d’ordre / FRAGO", "Ouvre la mini-fenêtre contextuelle pour écrire et envoyer un ordre (chefs d’unité)."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        0 spawn { [""] call comspec_overwatch_connect_fnc_orderComposeShow; };
        true
    },
    "",
    [], // Pas de touche par défaut — à définir dans Options > Contrôles > Extension Addon
    false, 0, false
] call CBA_fnc_addKeybind;

[
    "COMSPEC Overwatch", "comspec_frago_compose_key",
    ["Ouvrir rédaction FRAGO", "Ouvre directement le formulaire d’ordre fragmentaire (Situation · Mission · Exécution · Soutien · Commandement)."],
    {
        if (!(missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true])) exitWith { false };
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
        0 spawn { ["FRAGO"] call comspec_overwatch_connect_fnc_orderComposeShow; };
        true
    },
    "",
    [],
    false, 0, false
] call CBA_fnc_addKeybind;

["INFO", "Boot", format [
    "PreInit OK — connect v%1 | enabled=%2 | logLevel=%3",
    getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr"),
    missionNamespace getVariable ["comspec_overwatch_enabled", true],
    missionNamespace getVariable ["comspec_overwatch_log_level", 3]
]] call comspec_overwatch_connect_fnc_log;

if (isNil "COMSPEC_NDA_ResetBootEh") then {
    COMSPEC_NDA_ResetBootEh = ["CBA_settingsInitialized", {
        [{ missionNamespace setVariable ["COMSPEC_NDA_ResetBootGuard", false, false]; }, [], 8] call CBA_fnc_waitAndExecute;
    }] call CBA_fnc_addEventHandler;
};

if (isServer) then {
    [] call comspec_overwatch_connect_fnc_initCrashRecovery;
};