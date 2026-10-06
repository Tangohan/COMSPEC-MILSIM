/*
    Qualité de la liaison radio-data du téléphone (simulation de débit), recalculée au plus toutes les 2 s.
    Renvoie un HashMap : bars (0-4), kbps, latency (ms), loss (%), label, factors [[nom, effet]...].
    Facteurs : liaison Athena / relais Overwatch, bâtiment au-dessus de la tête, masque du relief,
    véhicule, pluie et brouillard, zone radio, brouilleurs de mission (COMSPEC_ATAK_Jammers = [[pos, rayon]...]),
    état du téléphone. Réglage serveur comspec_atak_native_net_sim : coupé, débit plein et sans délai.
*/
// Mode avion (fn_airplaneMode) : aucun réseau, même simulation coupée ; les envois partent en file comme sans signal.
if (missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false]) exitWith {
    createHashMapFromArray [["bars", 0], ["kbps", 0], ["latency", 0], ["loss", 100], ["label", "Mode avion"], ["factors", [["Mode avion", "aucun réseau"]]], ["sim", true], ["airplane", true]]
};
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_LinkQ", []];
if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < 2}) exitWith { _cache select 1 };
private _q = createHashMap;
if !(missionNamespace getVariable ["comspec_atak_native_net_sim", true]) exitWith {
    _q = createHashMapFromArray [["bars", 4], ["kbps", 5000], ["latency", 40], ["loss", 0], ["label", "Simulation coupée"], ["factors", []], ["sim", false]];
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", [diag_tickTime, _q]];
    _q
};
private _f = [];
private _mul = 1;
private _lat = 60;
private _loss = 0;
private _kbps = 4000;
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
    if (!isNil "comspec_overwatch_connect_fnc_getNearestAtakRelay") then {
        private _r = [] call comspec_overwatch_connect_fnc_getNearestAtakRelay;
        if (_r isEqualType createHashMap && {(count _r) > 0}) then {
            if ((_r getOrDefault ["in_range", false]) && {_r getOrDefault ["alive", true]}) then {
                _kbps = ((_r getOrDefault ["throughput_mbps", 4]) * 1000) max 200;
                _f pushBack [format ["Relais %1 à %2 m", _r getOrDefault ["name", ""], round (_r getOrDefault ["dist", 0])], format ["%1 Mbit/s", _r getOrDefault ["throughput_mbps", 4]]];
            } else {
                if (missionNamespace getVariable ["COMSPEC_LinkViaRelays", false]) then { _mul = _mul * 0.1; _f pushBack ["Aucun relais à portée", "-90 %"]; };
            };
        };
    };
    private _zone = missionNamespace getVariable ["COMSPEC_ZoneEffects", createHashMap];
    if (_zone isEqualType createHashMap && {(count _zone) > 0}) then {
        _loss = _loss max (_zone getOrDefault ["packet_loss_floor", 0]);
        _lat = _lat + (_zone getOrDefault ["latency_add", 0]);
        if (_zone getOrDefault ["force_disconnect", false]) then { _mul = 0; };
        _f pushBack [format ["Zone %1", _zone getOrDefault ["name", "radio"]], format ["perte %1 %%", _loss]];
    };
};
private _unit = vehicle player;
private _eye = eyePos player;
// Intérieur : toit au-dessus de la tête, étages empilés, murs autour, sous-sol.
// Près d'une fenêtre ou d'une porte le signal passe encore ; au cœur du bâtiment ou en sous-sol, plus rien.
if (_unit isEqualTo player) then {
    private _roofs = count (lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 30], player, objNull, true, 4, "GEOM", "NONE"]);
    if (_roofs > 0) then {
        private _walls = 0;
        for "_a" from 0 to 315 step 45 do {
            if ((count (lineIntersectsSurfaces [_eye, _eye vectorAdd [8 * sin _a, 8 * cos _a, 0], player, objNull, true, 1, "GEOM", "NONE"])) > 0) then { _walls = _walls + 1; };
        };
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
if (_unit isNotEqualTo player) then { _mul = _mul * 0.8; _f pushBack ["Dans un véhicule", "-20 %"]; };
if ((_eye select 2) < 0) then { _mul = 0; _f pushBack ["Sous l'eau", "aucun signal"]; };
// Masque du relief : 8 directions vers un point haut à 1,5 km.
private _masked = 0;
for "_a" from 0 to 315 step 45 do {
    private _to = _eye vectorAdd [1500 * sin _a, 1500 * cos _a, 0];
    _to set [2, ((getTerrainHeightASL _to) max 0) + 40];
    if (terrainIntersectASL [_eye, _to]) then { _masked = _masked + 1; };
};
if (_masked >= 3) then {
    private _m = 1 - (_masked / 8) * 0.7;
    _mul = _mul * _m; _f pushBack [format ["Relief masquant (%1/8 directions)", _masked], format ["-%1 %%", round ((1 - _m) * 100)]];
};
if (rain > 0.3) then { _mul = _mul * (1 - rain * 0.3); _f pushBack ["Pluie", format ["-%1 %%", round (rain * 30)]]; };
if (fog > 0.5) then { _mul = _mul * 0.9; _f pushBack ["Brouillard dense", "-10 %"]; };
{
    _x params [["_pos", [0, 0, 0]], ["_rad", 500], ["_uid", ""], ["_side", ""], ["_until", 1e9], ["_exempt", false]];
    if (_until < ([time, serverTime] select isMultiplayer)) then { continue; };
    if (_exempt && {_side isEqualTo str side group player}) then { continue; };
    if (_pos isEqualType objNull) then { _pos = getPosATL _pos; };
    private _d = player distance2D _pos;
    if (_d < _rad) exitWith {
        private _m = (_d / _rad) ^ 2;
        _mul = _mul * _m; _loss = _loss max (round ((1 - _m) * 80)); _f pushBack ["Brouillage", format ["-%1 %%", round ((1 - _m) * 100)]];
    };
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]);
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _dmg = _hp getOrDefault ["damage", 0];
if (_dmg > 0.3) then { _mul = _mul * (1.15 - _dmg); _f pushBack ["Boîtier du téléphone abîmé", format ["-%1 %%", round ((_dmg - 0.15) * 100)]]; };
// Antenne touchée (fn_deviceDamage) : jusqu'à -75 % de débit.
private _ant = (_hp getOrDefault ["parts", createHashMap]) getOrDefault ["antenna", 0];
if (_ant > 0) then { private _m = 1 - 0.75 * _ant; _mul = _mul * _m; _f pushBack ["Antenne du téléphone endommagée", format ["-%1 %%", round ((1 - _m) * 100)]]; };
_mul = 0 max (_mul min 1);
_kbps = round (_kbps * _mul);
_lat = round (_lat + (1 - _mul) * 600);
_loss = round (_loss max ((1 - _mul) * 35));
private _bars = switch (true) do { case (_kbps <= 0): { 0 }; case (_kbps < 250): { 1 }; case (_kbps < 900): { 2 }; case (_kbps < 2200): { 3 }; default { 4 }; };
_q = createHashMapFromArray [
    ["bars", _bars], ["kbps", _kbps], ["latency", _lat], ["loss", _loss], ["factors", _f], ["sim", true],
    ["label", ["Aucun signal", "Très faible", "Faible", "Bon", "Excellent"] select _bars]
];
uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", [diag_tickTime, _q]];
_q
