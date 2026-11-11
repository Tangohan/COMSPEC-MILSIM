/*
    Catalogue des réglages de réalisme : une seule liste pour le panneau du menu Échap (fn_netConfig) et l'assistant (fn_setupWizard).
    Ligne : [thème, libellé, variable, genre, valeurs, noms, condition fn_netCond ("" si aucune), clé de règle Athena ("" si aucune)].
    Genre : "bool" | "num" (modifiables), "text" (texte libre, lecture seule au menu), "pref" (choix du joueur), "ro" (réglage d'un autre mod, lecture seule).
      ["rows"] -> lignes ; ["vars"] -> variables modifiables au serveur (liste blanche de fn_adminServer "realismSet")
      ["state", ligne] -> [valeur, texte de la valeur, source, couleur, imposé par Athena, où le changer]
      ["apply", [[variable, valeur]...], demandeur, effacer les conditions] (serveur) : CBA_settings_fnc_set, droits vérifiés par fn_adminServer
*/
params [["_act", "rows"], ["_arg", []], ["_who", objNull], ["_clear", false]];
private _T = { _this call comspec_atak_native_fnc_tr };
switch (_act) do {
    case "rows": {
        private _c = missionNamespace getVariable "COMSPEC_ATAK_RealismRows";
        if (!isNil "_c") exitWith { _c };
        private _b = { params ["_th", "_l", "_v", ["_cond", ""], ["_rule", ""]]; [_th, _l, _v, "bool", [], [], _cond, _rule] };
        private _n = { params ["_th", "_l", "_v", "_vals", "_names", ["_cond", ""], ["_rule", ""]]; [_th, _l, _v, "num", _vals, _names, _cond, _rule] };
        private _p = "comspec_atak_native_";
        private _rows = [];
        // Réseau, positions, GPS, batterie du téléphone : conditions de fn_netCond, avec leurs valeurs et leurs règles de site.
        private _themes = createHashMapFromArray [
            ["profile", "Profil"], ["sim", "Réseau"], ["realism", "Réseau"], ["weather", "Réseau"], ["weatherK", "Réseau"], ["congestion", "Réseau"], ["bgLoad", "Réseau"], ["loss", "Réseau"], ["lossFloor", "Réseau"],
            ["latencyAdd", "Réseau"], ["relayOnly", "Réseau"], ["gateways", "Réseau"], ["offlineHold", "Réseau"], ["videoRelays", "Réseau"],
            ["pli", "Positions et GPS"], ["pliRate", "Positions et GPS"], ["gps", "Positions et GPS"], ["gpsK", "Positions et GPS"], ["gpsFix", "Positions et GPS"], ["gpsLoss", "Positions et GPS"], ["gpsRel", "Positions et GPS"],
            ["battery", "Batterie"]
        ];
        private _labels = createHashMapFromArray [
            ["sim", "Réseau réaliste"], ["realism", "Niveau de réalisme radio"], ["weather", "La météo gêne le réseau"],
            ["weatherK", "Intensité de la météo"], ["congestion", "Saturation du réseau"], ["bgLoad", "Réseau déjà occupé"],
            ["loss", "Envois perdus"], ["lossFloor", "Pertes minimum"], ["latencyAdd", "Délai ajouté"],
            ["relayOnly", "Réseau uniquement par les relais"], ["gateways", "Véhicules relais et PC mobile"],
            ["offlineHold", "Mode hors connexion"], ["videoRelays", "Vidéo dépendante des relais"],
            ["profile", "Profil de réalisme"], ["pli", "Positions BFT par points (PLI)"], ["pliRate", "Intervalle des positions BFT"],
            ["gps", "GPS réaliste"], ["gpsK", "Force des erreurs GPS"], ["gpsFix", "Attente des satellites"], ["gpsLoss", "Pertes de fix GPS"], ["gpsRel", "Fiabilité du GPS"], ["battery", "Batterie réaliste"]
        ];
        {
            _x params ["_key", "_cba", "_sk", "_kind", "", "_vals", "_names"];
            _rows pushBack [_themes getOrDefault [_key, "Réseau"], _labels getOrDefault [_key, _key], _cba, _kind, _vals, _names, _key, _sk];
        } forEach (["defs"] call comspec_atak_native_fnc_netCond);
        private _rd = "Radio et voix";
        private _ms = "Maillage";
        private _ge = "Brouillage et clés";
        private _ba = "Batterie";
        private _ph = "Téléphone et dégâts";
        private _hv = "Traces HVT";
        private _dv = "Développement";
        _rows append [
            [_rd, "Radio utilisée", _p + "radio_source", [0, 1, 2, 3], ["Détectée automatiquement", "ACRE2", "TFAR", "Aucune"]] call _n,
            [_rd, "Couper tout lien avec la radio", _p + "radio_cut"] call _b,
            [_rd, "TFAR : rallonge à l'émission seulement", _p + "mesh_tfar_ptt"] call _b,
            [_rd, "Données par radio (ancien modèle)", _p + "radio_data"] call _b,
            [_ms, "Liaison téléphone-radio réaliste", _p + "mpu_real"] call _b,
            [_ms, "Voix relayée par le maillage", _p + "mpu_voice"] call _b,
            [_ms, "Voix par le maillage : au moins un relais", _p + "mpu_voice_needRelay"] call _b,
            [_ms, "Radio de données obligatoire", _p + "mpu_require"] call _b,
            [_ms, "Câble EUD obligatoire (notre MPU5)", _p + "mpu_cable"] call _b,
            [_ms, "Wi-Fi de la MPU5", _p + "mpu_wifi"] call _b,
            [_ms, "Graphe calculé sur le serveur", _p + "mesh_server"] call _b,
            [_ms, "Plafond de sauts", _p + "mesh_hops", [2, 4, 6, 8, 12, 16], ["2", "4", "6", "8", "12", "16"]] call _n,
            [_ms, "Gravité de coupure", _p + "mesh_gravity"] call _b,
            [_ms, "Perte de débit par saut", _p + "mesh_hop_curve", [0, 1, 2], ["Selon le profil", "Moitié puis tiers", "x 0,8 par saut"]] call _n,
            [_ms, "Temps d'antenne partagé", _p + "mesh_airtime"] call _b,
            [_ms, "Antenne selon la posture", _p + "mesh_posture"] call _b,
            [_ms, "Masque du corps", _p + "mesh_body_mask"] call _b,
            [_ms, "Mât d'antenne de la MPU5", _p + "mesh_mast"] call _b,
            [_ms, "Batterie selon le trafic relayé", _p + "mesh_traffic_power"] call _b,
            [_ms, "Bâtiments dans le maillage", _p + "mesh_buildings", [0, 25, 50, 75, 100], ["Aucun effet", "25 %", "50 %", "75 %", "100 %"]] call _n,
            [_ms, "Reconvergence après la perte d'un relais", _p + "mesh_reconverge_s", [0, 2, 3, 5, 10], ["Immédiate", "2 s", "3 s", "5 s", "10 s"]] call _n,
            [_ms, "Lien manquant sur la carte", _p + "mesh_miss_map"] call _b,
            [_ms, "Position périmée : gris après", _p + "mesh_stale_s", [0, 15, 30, 60, 120], ["Jamais", "15 s", "30 s", "1 min", "2 min"]] call _n,
            [_ms, "Position périmée : retirée après", _p + "mesh_hide_s", [0, 60, 180, 300, 600], ["Jamais", "1 min", "3 min", "5 min", "10 min"]] call _n,
            [_ms, "Cadence de position adaptative", _p + "mesh_pli_adaptive"] call _b,
            [_ms, "Gros fichiers par blocs", _p + "mesh_chunks"] call _b,
            [_ms, "Messages par le maillage", _p + "mesh_p2p"] call _b,
            [_ms, "Groupes de conversation", _p + "mesh_groups"] call _b,
            [_ms, "Outils du maillage", _p + "mesh_tools"] call _b,
            [_ge, "Guerre électronique pour tous", _p + "ew_open"] call _b,
            [_ge, "Brouilleurs Crows Electronic Warfare", _p + "zeus_crows"] call _b,
            [_ge, "Brouillage des scripts TFAR", _p + "jam_tfar"] call _b,
            [_ge, "Goniomètre et analyseur de spectre", _p + "mesh_ew"] call _b,
            [_ge, "Goniomètre réservé au spécialiste", _p + "mesh_ew_role"] call _b,
            [_ge, "Clés du maillage", _p + "mesh_keys"] call _b,
            [_ge, "Clés chargées automatiquement", _p + "mesh_keys_auto"] call _b,
            [_ge, "Compromission automatique des clés", _p + "mesh_compromise_auto"] call _b,
            [_ge, "TFAR : clé de groupe en code de radio", _p + "mesh_tfar_code"] call _b,
            [_ge, "Canal de secours", _p + "mesh_backup_channel", [0, 8, 16], ["Aucun", "8", "16"]] call _n,
            [_ge, "Chargeur de clés : chargement manuel", _p + "mesh_keys_fill"] call _b,
            [_ge, "Période cryptographique", _p + "mesh_keys_period", [0, 30, 60, 120, 240, 480], ["Aucune", "30 min", "1 h", "2 h", "4 h", "8 h"]] call _n,
            [_ge, "Rechargement à distance (OTAR)", _p + "mesh_keys_otar"] call _b,
            [_ge, "Coût du chiffrement", _p + "mesh_crypto_cost", [0, 1, 2], ["Aucun", "Discret", "Marqué"]] call _n,
            [_ge, "Interception du trafic en clair", _p + "mesh_intercept"] call _b,
            [_ba, "Autonomie en main", _p + "battery_hours", [1, 3, 6, 12, 24], ["1 h", "3 h", "6 h", "12 h", "24 h"]] call _n,
            [_ba, "Batterie au début de la mission", _p + "battery_start", [25, 50, 75, 100], ["25 %", "50 %", "75 %", "100 %"]] call _n,
            [_ba, "Recharge en véhicule", _p + "battery_vehicle", [0, 3, 6, 10], ["Aucune", "3 % par minute", "6 % par minute", "10 % par minute"]] call _n,
            [_ba, "Froid : batterie moins efficace", _p + "battery_cold"] call _b,
            [_ba, "Autonomie de notre MPU5", _p + "mpu_hours", [2, 5, 10, 24], ["2 h", "5 h", "10 h", "24 h"]] call _n,
            [_ph, "Dégâts du téléphone", _p + "damage_sim"] call _b,
            [_ph, "Saleté et sang sur l'écran", _p + "dirt_sim"] call _b,
            [_ph, "Bris d'écran : réalisme", _p + "screen_fx_level", [0, 1, 2, 3], ["Désactivé", "Discret", "Normal", "Réaliste"]] call _n,
            [_ph, "Bris d'écran : pannes de dalle", _p + "panel_faults"] call _b,
            [_ph, "Bris d'écran : bugs d'affichage", _p + "screen_glitch"] call _b,
            [_ph, "Bris d'écran : gêne en jeu", _p + "screen_impact", [0, 1, 2], ["Visuel seulement", "Gêne légère", "Milsim"]] call _n,
            [_ph, "Téléphones personnels", _p + "phone_owner"] call _b,
            [_ph, "L'IA ennemie ramasse les téléphones", _p + "capture_enabled"] call _b,
            [_ph, "Carte SIM localisable téléphone éteint", _p + "sim_sos"] call _b,
            [_ph, "Géolocalisation des téléphones", _p + "geoloc_enabled"] call _b,
            [_ph, "Traçage : liaison ATAK exigée", _p + "trace_link", [0, 1, 2], ["Selon le profil de réalisme", "Toujours", "Jamais"]] call _n,
            [_ph, "Traçage : mandat du Zeus exigé", _p + "trace_warrant"] call _b,
            [_ph, "Traçage : réseau mobile civil", _p + "trace_civnet"] call _b,
            [_hv, "Traces civiles des HVT", _p + "hvt_trace"] call _b,
            [_hv, "Traces civiles : renseignement seul", _p + "hvt_trace_role"] call _b,
            [_ph, "Lanceur : apps regroupées", _p + "launcher_spaces", [0, 1, 2], ["Au choix du joueur", "Activé pour tous", "Désactivé pour tous"]] call _n,
            [_ph, "Mode pilote", _p + "pilot_mode", [0, 1, 2], ["Au choix du joueur", "Proposé à tous", "Coupé pour tous"]] call _n,
            [_dv, "Outils de développement du maillage", _p + "mesh_dev"] call _b
        ];
        _rows pushBack [_ms, "Objet obligatoire pour le réseau", _p + "net_required_items", "text", [], [], "", ""];
        // Réglages de COMSPEC Overwatch : lus, jamais écrits ici.
        _rows append [
            [_ph, "Dégâts au téléphone (Overwatch)", "comspec_overwatch_atak_realism", "ro", [0, 1, 2, 3], ["Aucun", "Peut s'éteindre", "Écran destructible", "Téléphone destructible"], "", "atak_realism"],
            [_ph, "Mode roleplay (Overwatch)", "comspec_overwatch_roleplay_enabled", "ro", [], [], "", "rp_enabled"],
            [_ph, "Saleté de l'écran : votre choix", "COMSPEC_ATAK_DirtFx", "pref", [], [], "", "native_dirt"]
        ];
        missionNamespace setVariable ["COMSPEC_ATAK_RealismRows", _rows];
        _rows
    };
    case "vars": {
        ((["rows"] call comspec_atak_native_fnc_realismCatalog) select { (_x select 3) in ["bool", "num", "text"] }) apply { _x select 2 }
    };
    case "state": {
        _arg params ["", "", "_var", "_kind", "_vals", "_names", "_cond", "_rule"];
        private _tenant = missionNamespace getVariable ["comspec_tenant_name", ""];
        if (_tenant isEqualTo "") then { _tenant = "votre communauté" call _T; };
        private _v = nil;
        private _src = "cba";
        if (_kind isEqualTo "pref") then {
            ([_var, true, _rule] call comspec_atak_native_fnc_pref) params ["_pv", "_lk"];
            _v = _pv;
            _src = ["choice", "site"] select _lk;
        } else {
            if (_cond isNotEqualTo "") then {
                _v = [_cond] call comspec_atak_native_fnc_netCond;
                _src = ["source", _cond] call comspec_atak_native_fnc_netCond;
            } else {
                _v = missionNamespace getVariable _var;
                if (isNil "_v") then { _v = ""; _src = "absent"; };
                if (_rule isNotEqualTo "") then {
                    private _r = [_rule] call comspec_atak_native_fnc_tenantRule;
                    if (_r isNotEqualTo "player") then {
                        _src = "site";
                        _v = if (_r in ["on", "off"]) then { _r isEqualTo "on" } else { parseNumber _r };
                    };
                };
            };
        };
        if (isNil "_v") then { _v = ""; };
        // Valeur d'usine connue : « par défaut » ou « réglé sur le serveur » : c'est ce qu'un serveur existant doit regarder après une mise à jour.
        if (_src isEqualTo "cba" && {!isNil "CBA_settings_fnc_get"}) then {
            private _d = [_var, "default"] call CBA_settings_fnc_get;
            if (!isNil "_d" && {_d isEqualTo _v}) then { _src = "default"; };
        };
        private _txt = switch (true) do {
            case (_v isEqualType true): { [("Coupé" call _T), ("Actif" call _T)] select _v };
            case (_v isEqualType "" && {_v isEqualTo ""}): { "Aucun" call _T };
            case (_v isEqualType ""): { _v };
            default {
                private _i = _vals find _v;
                if (_i >= 0) then { (_names select _i) call _T } else { str _v }
            };
        };
        // Valeur d'usine en clair (réglages modifiables seulement) : lue, jamais écrite.
        private _dtx = "";
        if (_kind in ["bool", "num"]) then {
            private _dv = nil;
            if (_cond isNotEqualTo "") then {
                _dv = ((["defs"] call comspec_atak_native_fnc_netCond) select { (_x select 0) isEqualTo _cond } apply { _x select 4 }) param [0, nil];
            } else {
                if (!isNil "CBA_settings_fnc_get") then { _dv = [_var, "default"] call CBA_settings_fnc_get; };
            };
            if (!isNil "_dv") then {
                _dtx = if (_dv isEqualType true) then { [("Coupé" call _T), ("Actif" call _T)] select _dv } else {
                    private _di = _vals find _dv;
                    if (_di >= 0) then { (_names select _di) call _T } else { str _dv }
                };
            };
        };
        private _s = createHashMapFromArray [
            ["site", [format ["Imposé par %1" call _T, _tenant], "#E0B050"]], ["server", ["Appliqué en jeu" call _T, "#7ED38A"]], ["profile", ["Valeur du profil" call _T, "#7FB6E6"]],
            ["cba", ["Réglage du serveur" call _T, "#D8C98A"]], ["default", ["Valeur par défaut" call _T, "#9AA59C"]], ["choice", ["Votre choix" call _T, "#7ED38A"]], ["absent", ["Mod absent" call _T, "#9AA59C"]]
        ];
        (_s get _src) params ["_st", "_sc"];
        [_v, _txt, _st, _sc, _src isEqualTo "site", ["", "Imposé sur Athena : à changer dans Contrôle serveur, sur le site." call _T] select (_src isEqualTo "site"), _src, _dtx]
    };
    case "apply": {
        if (!isServer || {isNil "CBA_settings_fnc_set"}) exitWith { 0 };
        private _allowed = ["vars"] call comspec_atak_native_fnc_realismCatalog;
        private _done = 0;
        {
            if !(_x isEqualType [] && {(count _x) isEqualTo 2}) then { continue };
            _x params ["_var", "_val"];
            if !(_var in _allowed) then { diag_log format ["[COMSPEC ATAK NATIVE][WARN][ADMIN] Réglage refusé (hors liste) : %1", _var]; continue };
            // Priorité d'origine gardée : un réglage forcé reste forcé.
            private _prio = [_var, "server"] call CBA_settings_fnc_priority;
            if (isNil "_prio") then { _prio = 0; };
            if ([_var, _val, _prio, "server", true] call CBA_settings_fnc_set) then { _done = _done + 1; };
        } forEach _arg;
        if (_clear) then { missionNamespace setVariable ["COMSPEC_ATAK_NetCond", [[], "", 0], true]; };
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][ADMIN] Réglages de réalisme changés par %1 : %2", [name _who, "?"] select (isNull _who), _arg];
        if (!isNull _who) then {
            ["SUCCESS", format ["Réglages du serveur enregistrés : %1 changement(s). Valeur forcée par la mission ou le site : inchangée.", _done], 8, 40] remoteExecCall ["comspec_atak_native_fnc_notify", _who];
        };
        _done
    };
};
