/*
    Qualité de la liaison radio-data du téléphone (simulation de débit), recalculée au plus toutes les 2 s (6 s téléphone fermé).
    Renvoie un HashMap : bars (0-4), kbps, latency (ms), loss (%), label, factors [[nom, effet]...].
    Facteurs : liaison Athena, meilleur relais reçu (fn_relayNet "best" : relais de mission, passerelle de véhicule, PC mobile), bâtiment au-dessus de la tête, masque du relief,
    véhicule, pluie et brouillard, zone radio, brouilleurs de mission (COMSPEC_ATAK_Jammers = [[pos, rayon]...], force en 7e élément),
    brouillage Zeus ciblé et brouilleurs Crows Electronic Warfare (fn_zeusJam),
    état du téléphone. Réglage serveur comspec_atak_native_net_sim : coupé, débit plein et sans délai.
    Avec un relais, le lien est un bilan radio en dB (fn_radioLink via fn_relayNet "best") : relief et zone de Fresnel,
    feuillage, bâtiments, évanouissements ; au-delà de la portée d'émission, frange « réception seule » (tx faux :
    fn_netSend met les envois en file). Brouilleurs : effet selon le rapport brouilleur / signal (un signal fort tout
    proche du relais résiste). Réglage serveur comspec_atak_native_net_realism : 0 simplifié (ancien modèle), 1 réaliste,
    2 exigeant. Clés en plus : tx (émission possible), margin (dB, -1 sans relais), jitter (ms).
    Réseau exclusivement par relais (fn_relayNet "only") : sans relais reçu, aucun réseau (même simulation coupée).
    Liaison réaliste téléphone <-> radio (fn_mpuLink, réglage comspec_atak_native_mpu_real) : le téléphone passe par sa
    radio de données, une MPU5 reliée par câble EUD ou Wi-Fi (sans MPU5 : pas de réseau de données) ; débit, latence et pertes
    sont ceux du maillage jusqu'à la passerelle (relief, sauts, brouilleurs déjà comptés), puis bâtiment, véhicule,
    brouillage sur le joueur, surcharge... comme avant. Radio obligatoire (comspec_atak_native_mpu_require) : sans radio
    reliée et en service, aucun réseau, même simulation coupée (Bluetooth et données locales restent possibles).
    Conditions réseau (fn_netCond : site, menu Échap, réglages CBA ; toutes actives par défaut) :
      - météo (fn_netWeather) : pluie, antennes mouillées, orage, vent ; dans le bilan radio avec un relais, en
        équivalent de débit sans relais ; orage et vent ajoutent pertes en rafales et gigue ;
      - surcharge (fn_netLoad) : trafic des téléphones à portée du même relais et trafic de fond du serveur ;
      - pertes de paquets : perte minimale imposée, ou aucune perte si la condition est coupée ; latence ajoutée.
*/
// Mode avion (fn_airplaneMode) : aucun réseau, même simulation coupée ; les envois partent en file comme sans signal.
if (missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false]) exitWith {
    createHashMapFromArray [["bars", 0], ["kbps", 0], ["latency", 0], ["loss", 100], ["label", "Mode avion"], ["factors", [["Mode avion", "aucun réseau"]]], ["sim", true], ["airplane", true]]
};
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_LinkQ", []];
// Téléphone fermé : recalcul toutes les 6 s au lieu de 2 (les boucles de fond, alertes BFT, file d'envoi, balise, le
// demandent sans cesse et chaque calcul lance une vingtaine de rayons contre les bâtiments et le relief).
if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < ([6, 2] select !isNull ([] call comspec_atak_native_fnc_display))}) exitWith { _cache select 1 };
// Cache vidé exprès (antenne, dégâts, mode avion, brouillage, test de débit…) : le meilleur relais est recalculé aussi.
if ((count _cache) isNotEqualTo 2) then { uiNamespace setVariable ["COMSPEC_ATAK_RelayBest", []]; };
private _q = createHashMap;
// Objet obligatoire pour le réseau (fn_netRequire) : aucun réseau sans lui, même simulation coupée ; les envois partent en file comme sans signal.
private _den = ["denied"] call comspec_atak_native_fnc_netRequire;
if (_den isNotEqualTo []) exitWith {
    _q = createHashMapFromArray [["bars", 0], ["kbps", 0], ["latency", 0], ["loss", 100], ["label", "Objet requis manquant"],
        ["factors", [[format ["Objet requis manquant : %1", ["labels", [_den]] call comspec_atak_native_fnc_netRequire], "aucun réseau"]]], ["sim", true], ["tx", false], ["margin", -1], ["jitter", 0]];
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", [diag_tickTime, _q]];
    _q
};
private _mpu = ["status"] call comspec_atak_native_fnc_mpuLink;
private _mesh = (_mpu get "active") && {_mpu get "ok"} && {(_mpu get "kind") isEqualTo "mpu"};
if ((_mpu get "active") && {!(_mpu get "ok")} && {missionNamespace getVariable ["comspec_atak_native_mpu_require", false]}) exitWith {
    private _why = _mpu getOrDefault ["why", ""];
    _q = createHashMapFromArray [["bars", 0], ["kbps", 0], ["latency", 0], ["loss", 100], ["label", ["Radio sans réseau", "Sans MPU5"] select ((_mpu get "kind") isEqualTo "")],
        ["factors", [[["MPU5 : " + _why, "Sans MPU5 : pas de réseau de données (reliez une MPU5 par câble EUD ou Wi-Fi)"] select ((_mpu get "kind") isEqualTo ""), "aucun réseau"]]], ["sim", true], ["tx", false], ["margin", -1], ["jitter", 0]];
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", [diag_tickTime, _q]];
    _q
};
if !(["sim"] call comspec_atak_native_fnc_netCond) exitWith {
    // Réseau exclusivement par relais : même sans simulation de débit, aucun relais reçu = aucun réseau.
    _q = if ((["only"] call comspec_atak_native_fnc_relayNet) && {(["best"] call comspec_atak_native_fnc_relayNet) isEqualTo []}) then {
        createHashMapFromArray [["bars", 0], ["kbps", 0], ["latency", 0], ["loss", 100], ["label", "Aucun relais"], ["factors", [["Aucun relais reçu (réseau exclusivement par relais)", "aucun réseau"]]], ["sim", true]]
    } else {
        createHashMapFromArray [["bars", 4], ["kbps", 5000], ["latency", 40], ["loss", 0], ["label", "Simulation coupée"], ["factors", []], ["sim", false]]
    };
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", [diag_tickTime, _q]];
    _q
};
private _f = [];
private _real = (["realism"] call comspec_atak_native_fnc_netCond);
if (!(_real isEqualType 0)) then { _real = 1; };
private _tx = true;
private _margin = -1;
private _mul = 1;
private _lat = 60;
private _loss = 0;
private _kbps = 4000;
// Meilleur relais reçu (fn_relayNet "best") : relais de la mission (module « Poser un relais » ou Overwatch), passerelle
// de véhicule ou PC mobile ; relief, batterie, brouillage, chaîne des répéteurs et saturation déjà comptés.
private _best = ["best"] call comspec_atak_native_fnc_relayNet;
// Relais obligatoires : case « Relais obligatoires » d'un module « Poser un relais » (presque plus de réseau sans relais).
// Réseau exclusivement par relais : réglage serveur, case du module ou mode réaliste d'Athena (aucun réseau sans relais).
private _only = ["only"] call comspec_atak_native_fnc_relayNet;
private _req = _only || {missionNamespace getVariable ["COMSPEC_ATAK_RelaysRequired", false]};
private _bridge = [] call comspec_atak_native_fnc_bridge;
if (_bridge) then {
    private _link = toLower (missionNamespace getVariable ["COMSPEC_LinkState", "offline"]);
    switch (_link) do {
        case "linked": {};
        case "degraded";
        case "connecting": { _mul = _mul * 0.35; _f pushBack ["Liaison Athena dégradée", "-65 %"]; };
        default { _mul = _mul * 0.15; _f pushBack ["Athena hors ligne (maillage local seul)", "-85 %"]; };
    };
    private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
    if (_ms isEqualType 0 && {_ms > 0}) then { _lat = _ms; };
    private _zone = missionNamespace getVariable ["COMSPEC_ZoneEffects", createHashMap];
    if (_zone isEqualType createHashMap && {(count _zone) > 0}) then {
        _loss = _loss max (_zone getOrDefault ["packet_loss_floor", 0]);
        _lat = _lat + (_zone getOrDefault ["latency_add", 0]);
        if (_zone getOrDefault ["force_disconnect", false]) then { _mul = 0; };
        _f pushBack [format ["Zone %1", _zone getOrDefault ["name", "radio"]], format ["perte %1 %%", _loss]];
    };
};
// Radio de données en service (fn_mpuLink) : les relais sont déjà dans son maillage (passerelles).
if (_mesh) then {
    _only = false;
    _req = false;
    _kbps = (_mpu get "kbps") max 1;
    _lat = _lat + (_mpu get "lat");
    _loss = _loss max (_mpu get "loss");
    // Gravité de coupure (fn_meshGraph) : chemin en limite de portée, pertes en rafales.
    if ((_mpu getOrDefault ["level", 0]) >= 2 && {missionNamespace getVariable ["comspec_atak_native_mesh_gravity", true]}) then {
        _loss = _loss max 25;
        _f pushBack ["Maillage en limite de portée", "pertes en rafales"];
    };
    private _ms = uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap];
    private _lk = _mpu get "link";
    // Nom de la radio réellement détectée (fn_mpu5 "detected", même source que la ligne « Radio » de l'app Wave Relay).
    private _rn = (["detected"] call comspec_atak_native_fnc_mpu5) param [0, "MPU5"];
    _f pushBack [format ["%1 · canal %2 (%3)", _rn, _ms getOrDefault ["chan", 1], ["câble EUD", "Wi-Fi"] select (_lk isEqualTo "wifi")],
        format ["%1 saut(s) · %2", (_mpu get "hops") max 0, [format ["%1 kbit/s", _mpu get "kbps"], format ["%1 Mbit/s", ((_mpu get "kbps") / 1000) toFixed 1]] select ((_mpu get "kbps") >= 1000)]];
    if (_lk isEqualTo "wifi") then { _f pushBack [format ["Wi-Fi de la MPU5 (%1 m)", round (_mpu get "wifiD")], format ["%1 %%", round (100 * (_mpu get "wifiQ"))]]; };
};
if (_req && {_best isEqualTo []}) then {
    if (_only) then { _mul = 0; _f pushBack ["Aucun relais reçu (réseau exclusivement par relais)", "aucun réseau"]; } else { _mul = _mul * 0.1; _f pushBack ["Aucun relais à portée", "-90 %"]; };
};
if (_best isNotEqualTo []) then {
    _best params ["", "_gn", "_gd", "_gmb", "_gl", "_gk", "", "_gu", "_gs", "", ["_gf", 0], ["_gh", ""], ["_glat", 0], ["_gmg", -1], ["_gtx", true], ["_gls", []], ["_gmcs", -1]];
    _lat = _lat + _glat;
    _tx = _gtx;
    _margin = _gmg;
    // Bilan radio : pertes du trajet (relief, feuillage, bâtiments) et marge restante.
    { _f pushBack [_x select 0, format ["-%1 dB", round (_x select 1)]]; } forEach _gls;
    if (_gmcs >= 0) then { _f pushBack [format ["Marge radio (modulation MCS %1)", _gmcs], format ["%1 dB", round _gmg]]; };
    if (!_gtx) then { _f pushBack ["Hors de portée d'émission : réception seule", "envois en file"]; };
    if (_gh in ["dégradé", "critique"]) then { _f pushBack [format ["Relais %1 (%2 MHz)", _gh, _gf], format ["perte %1 %%", _gl]]; };
    // Relais obligatoires : on a le débit du relais ; sinon il ne fait qu'améliorer le direct.
    if (_req) then { _kbps = (round (_gmb * 1000)) max 200; _loss = _loss max _gl; } else { _kbps = _kbps max round (_gmb * 1000); };
    _f pushBack [format ["%1 %2 à %3 m", createHashMapFromArray [["relay", "Relais"], ["gw", "Passerelle"], ["cp", "Poste de commandement"], ["mpu5", "Radio maillée"]] getOrDefault [_gk, "Relais"], _gn, round _gd], format ["%1 Mbit/s", _gmb toFixed 1]];
    if (_gs > 0 && {_gu > _gs}) then { _f pushBack [format ["Relais saturé (%1 téléphones pour %2 places)", _gu, _gs], format ["-%1 %%", round (100 * (1 - _gs / _gu))]]; };
};
private _unit = vehicle player;
private _eye = eyePos player;
// Intérieur : toit au-dessus de la tête, étages empilés, murs autour, sous-sol.
// Près d'une fenêtre ou d'une porte le signal passe encore ; au cœur du bâtiment ou en sous-sol, plus rien.
if (_unit isEqualTo player) then {
    // Toit et murs : rayons partagés avec fn_gpsFix / fn_linkQuality, relancés seulement après 3 m de déplacement (ou 10 s).
    private _ind = missionNamespace getVariable ["COMSPEC_ATAK_IndoorRays", []];
    if ((count _ind) < 4 || {((_ind select 0) distance _eye) >= 3} || {diag_tickTime - (_ind select 3) > 10}) then {
        private _r = count (lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 30], player, objNull, true, 4, "GEOM", "NONE"]);
        private _w = 0;
        if (_r > 0) then {
            for "_a" from 0 to 315 step 45 do {
                if ((count (lineIntersectsSurfaces [_eye, _eye vectorAdd [8 * sin _a, 8 * cos _a, 0], player, objNull, true, 1, "GEOM", "NONE"])) > 0) then { _w = _w + 1; };
            };
        };
        _ind = [_eye, _r, _w, diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_IndoorRays", _ind];
    };
    _ind params ["", "_roofs", "_walls"];
    if (_roofs > 0) then {
        private _under = (_eye select 2) < (getTerrainHeightASL _eye) - 0.5;
        switch (true) do {
            case (_under): { _mul = 0; _f pushBack ["En sous-sol", "aucun signal"]; };
            case (_walls >= 8 && {_roofs >= 2}): { _mul = 0; _f pushBack [format ["Au cœur du bâtiment (%1 étages au-dessus)", _roofs], "aucun signal"]; };
            case (_walls >= 7 || {_roofs >= 3}): { _mul = _mul * 0.12; _lat = _lat + 150; _f pushBack ["Au fond du bâtiment, loin des ouvertures", "-88 %"]; };
            case (_walls >= 5 || {_roofs >= 2}): { _mul = _mul * 0.3; _lat = _lat + 80; _f pushBack ["Dans un bâtiment", "-70 %"]; };
            default { _mul = _mul * 0.6; _lat = _lat + 30; _f pushBack ["Sous abri, près d'une ouverture", "-40 %"]; };
        };
    };
};
// Dans un véhicule : la carrosserie gêne, sauf sur son terminal de bord ou sa passerelle (antenne du toit).
if (_unit isNotEqualTo player && {([player] call comspec_atak_native_fnc_vehicleTerminal) isEqualTo ""} && {(["state", _unit] call comspec_atak_native_fnc_vehicleGateway) isEqualTo ""}) then { _mul = _mul * 0.8; _f pushBack ["Dans un véhicule", "-20 %"]; };
if ((_eye select 2) < 0) then { _mul = 0; _f pushBack ["Sous l'eau", "aucun signal"]; };
// Masque du relief : 8 directions vers un point haut à 1,5 km. Avec un relais reçu, le relief est déjà dans son
// bilan radio (modèle réaliste) : on ne le compte pas deux fois.
private _masked = 0;
if (!_mesh && {_real isEqualTo 0 || {_best isEqualTo []}}) then { for "_a" from 0 to 315 step 45 do {
    private _to = _eye vectorAdd [1500 * sin _a, 1500 * cos _a, 0];
    _to set [2, ((getTerrainHeightASL _to) max 0) + 40];
    if (terrainIntersectASL [_eye, _to]) then { _masked = _masked + 1; };
}; };
if (_masked >= 3) then {
    private _m = 1 - (_masked / 8) * 0.7;
    _mul = _mul * _m; _f pushBack [format ["Relief masquant (%1/8 directions)", _masked], format ["-%1 %%", round ((1 - _m) * 100)]];
};
// Météo : à 4,9 GHz la pluie n'affaiblit presque pas l'onde (moins de 0,1 dB/km) et le brouillard pas du tout ; seul le
// feuillage mouillé compte (bilan radio). Modèle simplifié : ancien malus de pluie et de brouillard.
if (_real isEqualTo 0) then {
    if (rain > 0.3) then { _mul = _mul * (1 - rain * 0.3); _f pushBack ["Pluie", format ["-%1 %%", round (rain * 30)]]; };
    if (fog > 0.5) then { _mul = _mul * 0.9; _f pushBack ["Brouillard dense", "-10 %"]; };
};
// Le brouilleur le plus fort parmi ceux qui couvrent le joueur compte (pas le premier de la liste : brouilleurs de
// joueurs et de Zeus publiés avant ceux des missions).
private _jamM = 1;
{
    _x params [["_pos", [0, 0, 0]], ["_rad", 500], ["_uid", ""], ["_side", ""], ["_until", 1e9], ["_exempt", false], ["_str", 1]];
    if (_until < ([time, serverTime] select isMultiplayer)) then { continue; };
    if (_exempt && {_side isEqualTo str side group player}) then { continue; };
    if (_pos isEqualType objNull) then { _pos = getPosATL _pos; };
    private _d = player distance2D _pos;
    if (_d < _rad) then {
        // Force du brouilleur (7e élément, brouilleurs de zone Zeus) : 1 par défaut.
        private _m = 1 - ((_str max 0) min 1) * (1 - (_d / _rad) ^ 2);
        // Rapport brouilleur / signal : le brouilleur gagne de 20 dB par décade en s'approchant de lui ; un signal de relais
        // fort (grande marge) passe encore à travers (burn-through), un signal faible tombe dès le bord de la zone.
        if (_real > 0) then {
            private _j = 20 * log (_rad / (_d max 1));
            private _sg = [10, _margin] select (_margin >= 0);
            _m = 1 - ((_str max 0) min 1) * ((((_j - _sg + 12) / 24) max 0) min 1);
        };
        _jamM = _jamM min _m;
    };
} forEach ((missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { (_x param [7, 0]) isEqualTo 0 && {(_x param [8, ""]) isNotEqualTo "gps"} });
if (_jamM < 1) then {
    _mul = _mul * _jamM; _loss = _loss max (round ((1 - _jamM) * 80)); _f pushBack ["Brouillage", format ["-%1 %%", round ((1 - _jamM) * 100)]];
};
// Brouillage Zeus ciblé sur ce joueur et brouilleurs du mod Crows Electronic Warfare (fn_zeusJam).
private _zj = [] call comspec_atak_native_fnc_zeusJam;
if (_zj > 0) then {
    private _m = 1 - _zj;
    _mul = _mul * _m; _loss = _loss max (round (_zj * 80)); _f pushBack ["Brouillage", format ["-%1 %%", round (_zj * 100)]];
};
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _dmg = _hp getOrDefault ["damage", 0];
if (_dmg > 0.3) then { _mul = _mul * (1.15 - _dmg); _f pushBack ["Boîtier du téléphone abîmé", format ["-%1 %%", round ((_dmg - 0.15) * 100)]]; };
// Antenne touchée (fn_deviceDamage) : jusqu'à -75 % de débit. Une antenne externe (fn_antenna) la remplace.
private _ext = ["get"] call comspec_atak_native_fnc_antenna;
private _ant = (_hp getOrDefault ["parts", createHashMap]) getOrDefault ["antenna", 0];
if (_ant > 0 && {_ext isEqualTo []} && {!(_mesh && {(_mpu get "link") isEqualTo "cable"})}) then { private _m = 1 - 0.75 * _ant; _mul = _mul * _m; _f pushBack ["Antenne du téléphone endommagée", format ["-%1 %%", round ((1 - _m) * 100)]]; };
// Téléphone presque vide (moins de 10 %) : Android réduit la puissance d'émission.
if (_real > 0 && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) < 10}) then { _mul = _mul * 0.8; _f pushBack ["Batterie faible : émission réduite", "-20 %"]; };
// En mouvement rapide (véhicule, aéronef) : évanouissements rapides, changements de relais, plus de pertes et de gigue.
private _spd = (abs speed vehicle player) / 3.6;
private _jit = 5;
if (_real > 0 && {_spd > 15}) then {
    _loss = _loss + round ((_spd / 6) min 12);
    _jit = _jit + round ((_spd * 2) min 120);
    _f pushBack [format ["En mouvement rapide (%1 km/h)", round (_spd * 3.6)], format ["+%1 %% de pertes", round ((_spd / 6) min 12)]];
};
// Météo : avec un relais, ses dB sont déjà dans le bilan radio (fn_radioLink) ; sans relais, la liaison directe perd
// l'équivalent (2 km de pluie compris). Orage et vent : pertes en rafales et gigue en plus dans tous les cas.
(["global"] call comspec_atak_native_fnc_netWeather) params ["_wxL", "_wxPl", "_wxJit"];
if (_real > 0) then {
    if (_best isEqualTo [] && {!_mesh}) then {
        private _db = ["path", 2000] call comspec_atak_native_fnc_netWeather;
        { _db = _db + (_x select 1); } forEach _wxL;
        if (_db >= 0.5) then {
            private _m = 10 ^ (-_db / 20);
            _mul = _mul * _m;
            _f pushBack ["Météo (pluie, orage, vent)", format ["-%1 dB", round _db]];
        };
    };
    if (_wxPl > 0) then { _loss = _loss + _wxPl; _jit = _jit + _wxJit; _f pushBack ["Météo : pertes en rafales", format ["+%1 %% de pertes", _wxPl]]; };
    if (fog > 0.5 && {["weather"] call comspec_atak_native_fnc_netCond}) then { _f pushBack ["Brouillard", "aucun effet à 4,9 GHz"]; };
};
// Surcharge : canal partagé par les téléphones à portée du relais utilisé (ou à 2 km sans relais), plus le trafic de fond.
if (_real > 0 && {["congestion"] call comspec_atak_native_fnc_netCond}) then {
    private _ant = getPosASL player;
    private _rng = 2000;
    private _cap = 4000;
    if (_best isNotEqualTo []) then {
        private _e = ["entry", _best select 0] call comspec_atak_native_fnc_relayNet;
        if (_e isNotEqualTo []) then { _ant = _e select 1; _rng = _e select 4; _cap = ((_e select 5) * 1000) max 200; };
    };
    if (_mesh) then { _cap = (_mpu get "kbps") max 50; };
    (["at", _ant, _rng, _cap] call comspec_atak_native_fnc_netLoad) params ["_u", "_np"];
    if (_u > 0.3) then {
        private _uq = _u min 0.95;
        private _m = (1 / (_u max 1)) * (1 - 0.3 * ((_u min 1) ^ 2));
        private _ql = round (20 * _uq / (1 - _uq) + 400 * ((_u - 1) max 0));
        private _cl = round ((20 * _u ^ 2) min 25);
        _mul = _mul * _m;
        _lat = _lat + _ql;
        _loss = _loss + _cl;
        _f pushBack [format ["Surcharge du réseau (canal occupé à %1 %%, %2 téléphones)", round (_u * 100), _np], format ["-%1 %% · +%2 ms", round ((1 - _m) * 100), _ql]];
    };
};
_mul = 0 max (_mul min 1);
// Antenne d'extension de débit : pertes atténuées et débit de base relevé (rien à faire sans aucun signal).
if (_ext isNotEqualTo [] && {_mul > 0} && {!_mesh}) then {
    (["boost", _mul] call comspec_atak_native_fnc_antenna) params ["_m2", "_gain", "_name", "_txt"];
    _mul = _m2;
    _kbps = _kbps * _gain;
    _f pushBack [_name, _txt];
};
_kbps = round (_kbps * _mul);
_lat = round (_lat + (1 - _mul) * 600);
_loss = round (_loss max ((1 - _mul) * 35));
// Coût du chiffrement (fn_meshKeys "cost", réglage mesh_crypto_cost) tant que la liaison est chiffrée : en-têtes et contrôle d'intégrité.
(["cost"] call comspec_atak_native_fnc_meshKeys) params [["_cryK", 0], ["_cryL", 0]];
if (_cryK > 0 && {_kbps > 0}) then {
    _kbps = round (_kbps * (1 - _cryK));
    _lat = _lat + _cryL;
    _f pushBack ["Chiffrement de la liaison", format ["-%1 %% · +%2 ms", round (_cryK * 100), _cryL]];
};
// Conditions du serveur : perte minimale et latence ajoutée (zone dégradée, liaison satellite) ; pertes coupées.
private _lf = ["lossFloor"] call comspec_atak_native_fnc_netCond;
if (_lf > 0 && {_loss < _lf}) then { _loss = _lf; _f pushBack ["Perte minimale imposée par le serveur", format ["%1 %%", _lf]]; };
private _la = ["latencyAdd"] call comspec_atak_native_fnc_netCond;
if (_la > 0) then { _lat = _lat + _la; _f pushBack ["Latence ajoutée par le serveur", format ["+%1 ms", _la]]; };
if !(["loss"] call comspec_atak_native_fnc_netCond) then { _loss = 0; };
private _bars = switch (true) do { case (_kbps <= 0): { 0 }; case (_kbps < 250): { 1 }; case (_kbps < 900): { 2 }; case (_kbps < 2200): { 3 }; default { 4 }; };
// Gigue : la latence varie d'un paquet à l'autre, d'autant plus que la liaison est mauvaise.
_jit = round (_jit + (1 - _mul) * 80);
private _label = ["Aucun signal", "Très faible", "Faible", "Bon", "Excellent"] select _bars;
// Réception seule : une barre au plus, rien ne part.
if (!_tx && {_bars > 0}) then { _bars = 1; _label = "Réception seule"; };
if (_bars isEqualTo 0) then { _tx = false; };
_q = createHashMapFromArray [
    ["bars", _bars], ["kbps", _kbps], ["latency", _lat], ["loss", _loss min 100], ["factors", _f], ["sim", true],
    ["label", _label], ["tx", _tx], ["margin", _margin], ["jitter", _jit]
];
uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", [diag_tickTime, _q]];
_q
