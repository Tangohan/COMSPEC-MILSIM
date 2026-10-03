COMSPEC_ATAK_UI_GENERATION = "native-rsc-v1";
missionNamespace setVariable ["COMSPEC_ATAK_UI_GENERATION", COMSPEC_ATAK_UI_GENERATION, true];
missionNamespace setVariable ["COMSPEC_ATAK_NativeVersion", "1.3.0", true];
diag_log "[COMSPEC ATAK NATIVE][BOOT][CANARY] native_client_v1_3_0_loaded";
diag_log "[COMSPEC ATAK NATIVE][INFO][BOOT] UI generation: native-rsc-v1";
[] call comspec_atak_native_fnc_stateInit;

// Réglages serveur (forçables par l'admin dans les réglages CBA du serveur / de la mission).
private _cat = ["COMSPEC ATAK natif", "Accès au téléphone"];
private _recompute = { [true] call comspec_atak_native_fnc_deviceCatalog; };
["comspec_atak_native_require_item", "CHECKBOX",
    ["Item obligatoire pour avoir l'ATAK", "Activé par défaut : il faut porter un téléphone (ItemAndroid de cTab ou équivalent d'un autre mod) pour sortir ou prendre le téléphone. Décocher pour donner l'ATAK à tout le monde."],
    _cat, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_battery_items", "EDITBOX",
    ["Batteries de rechange", "Objets (classes, séparées par des virgules) qui rechargent le téléphone à 100 % quand on change la batterie. L'objet est consommé."],
    _cat, "ACE_UAVBattery", 1] call CBA_fnc_addSetting;
["comspec_atak_native_device_patterns", "EDITBOX",
    ["Détection des autres mods (motifs)", "Tout objet chargé dont le nom de classe contient un de ces mots compte comme téléphone. Séparer par des virgules. Par défaut : android,atak,smartphone."],
    _cat, "android,atak,smartphone", 1, _recompute] call CBA_fnc_addSetting;
["comspec_atak_native_device_items", "EDITBOX",
    ["Objets acceptés en plus", "Noms de classes exacts, séparés par des virgules (ex. un téléphone d'un pack d'équipement qui n'a pas « android » dans son nom)."],
    _cat, "", 1, _recompute] call CBA_fnc_addSetting;
["comspec_atak_native_device_tablets", "CHECKBOX",
    ["Les tablettes et DAGR comptent aussi", "ItemcTab, MicroDAGR (cTab) et MicroDAGR (ACE) ouvrent aussi le téléphone."],
    _cat, false, 1, _recompute] call CBA_fnc_addSetting;

private _sim = ["COMSPEC ATAK natif", "Simulation"];
["comspec_atak_native_damage_sim", "CHECKBOX",
    ["Dégâts du téléphone", "Balles au torse ou aux bras, explosions proches et eau fêlent l'écran, éteignent ou détruisent le téléphone. Réparation : trousse à outils ou nouvel appareil (actions ACE). Si le réalisme ATAK d'Overwatch est actif, c'est lui qui décide."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_ew_open", "CHECKBOX",
    ["Guerre électronique ouverte à tous", "Coché : tout porteur de téléphone peut brouiller et goniométrer. Décoché : réservé aux unités COMSPEC_ATAK_EwOperator ou au rôle « guerre électronique / brouilleur / SIGINT »."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_net_sim", "CHECKBOX",
    ["Débit réseau simulé", "Bâtiments, relief, véhicule, météo, brouilleurs, relais et dégâts réduisent le débit : messages et photos partent avec un délai, se perdent et repartent, ou attendent le retour du réseau."],
    _sim, true, 1] call CBA_fnc_addSetting;
["comspec_atak_native_civil_apps", "CHECKBOX",
    ["Apps civiles", "Ration Express (rations livrées par drone) et Rencard (rencontres entre joueurs). Décocher pour les retirer de tous les téléphones."],
    _sim, true, 1] call CBA_fnc_addSetting;
