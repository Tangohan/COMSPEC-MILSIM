/*
    Qualité de la liaison radio-data du téléphone (simulation de débit), recalculée au plus toutes les 2 s.
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
*/
// Mode avion (fn_airplaneMode) : aucun réseau, même simulation coupée ; les envois partent en file comme sans signal.
if (missionNamespace getVariable ["COMSPEC_ATAK_Airplane", false]) exitWith {
    createHashMapFromArray [["bars", 0], ["kbps", 0], ["latency", 0], ["loss", 100], ["label", "Mode avion"], ["factors", [["Mode avion", "aucun réseau"]]], ["sim", true], ["airplane", true]]
};
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_LinkQ", []];
if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < 2}) exitWith { _cache select 1 };
private _q = createHashMap;
if !(missionNamespace getVariable ["comspec_atak_native_net_sim", true]) exitWith {
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
private _real = missionNamespace getVariable ["comspec_atak_native_net_realism", 1];
if (!(_real isEqualType 0)) then { _real = 1; };
private _tx = true;
private _margin = -1;
private _mul = 1;
private _lat = 60;
private _loss = 0;
private _kbps = 4000;
// Meilleur relais reçu (fn_relayNet "best") : relais de la mission (module « ATAK · Relais » ou Overwatch), passerelle
// de véhicule ou PC mobile ; relief, batterie, brouillage, chaîne des répéteurs et saturation déjà comptés.
private _best = ["best"] call comspec_atak_native_fnc_relayNet;
// Relais obligatoires : case « Relais obligatoires » d'un module « ATAK · Relais » (presque plus de réseau sans relais).
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
    _f pushBack [format ["%1 %2 à %3 m", createHashMapFromArray [["relay", "Relais"], ["gw", "Passerelle"], ["cp", "Poste de commandement"]] getOrDefault [_gk, "Relais"], _gn, round _gd], format ["%1 Mbit/s", _gmb toFixed 1]];
    if (_gs > 0 && {_gu > _gs}) then { _f pushBack [format ["Relais saturé (%1 téléphones pour %2 places)", _gu, _gs], format ["-%1 %%", round (100 * (1 - _gs / _gu))]]; };
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
// Dans un véhicule : la carrosserie gêne, sauf sur son terminal de bord ou sa passerelle (antenne du toit).
if (_unit isNotEqualTo player && {([player] call comspec_atak_native_fnc_vehicleTerminal) isEqualTo ""} && {(["state", _unit] call comspec_atak_native_fnc_vehicleGateway) isEqualTo ""}) then { _mul = _mul * 0.8; _f pushBack ["Dans un véhicule", "-20 %"]; };
if ((_eye select 2) < 0) then { _mul = 0; _f pushBack ["Sous l'eau", "aucun signal"]; };
// Masque du relief : 8 directions vers un point haut à 1,5 km. Avec un relais reçu, le relief est déjà dans son
// bilan radio (modèle réaliste) : on ne le compte pas deux fois.
private _masked = 0;
if (_real isEqualTo 0 || {_best isEqualTo []}) then { for "_a" from 0 to 315 step 45 do {
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
{
    _x params [["_pos", [0, 0, 0]], ["_rad", 500], ["_uid", ""], ["_side", ""], ["_until", 1e9], ["_exempt", false], ["_str", 1]];
    if (_until < ([time, serverTime] select isMultiplayer)) then { continue; };
    if (_exempt && {_side isEqualTo str side group player}) then { continue; };
    if (_pos isEqualType objNull) then { _pos = getPosATL _pos; };
    private _d = player distance2D _pos;
    if (_d < _rad) exitWith {
        // Force du brouilleur (7e élément, brouilleurs de zone Zeus) : 1 par défaut.
        private _m = 1 - ((_str max 0) min 1) * (1 - (_d / _rad) ^ 2);
        // Rapport brouilleur / signal : le brouilleur gagne de 20 dB par décade en s'approchant de lui ; un signal de relais
        // fort (grande marge) passe encore à travers (burn-through), un signal faible tombe dès le bord de la zone.
        if (_real > 0) then {
            private _j = 20 * log (_rad / (_d max 1));
            private _sg = [10, _margin] select (_margin >= 0);
            _m = 1 - ((_str max 0) min 1) * ((((_j - _sg + 12) / 24) max 0) min 1);
        };
        _mul = _mul * _m; _loss = _loss max (round ((1 - _m) * 80)); _f pushBack ["Brouillage", format ["-%1 %%", round ((1 - _m) * 100)]];
    };
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]);
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
if (_ant > 0 && {_ext isEqualTo []}) then { private _m = 1 - 0.75 * _ant; _mul = _mul * _m; _f pushBack ["Antenne du téléphone endommagée", format ["-%1 %%", round ((1 - _m) * 100)]]; };
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
_mul = 0 max (_mul min 1);
// Antenne d'extension de débit : pertes atténuées et débit de base relevé (rien à faire sans aucun signal).
if (_ext isNotEqualTo [] && {_mul > 0}) then {
    (["boost", _mul] call comspec_atak_native_fnc_antenna) params ["_m2", "_gain", "_name", "_txt"];
    _mul = _m2;
    _kbps = _kbps * _gain;
    _f pushBack [_name, _txt];
};
_kbps = round (_kbps * _mul);
_lat = round (_lat + (1 - _mul) * 600);
_loss = round (_loss max ((1 - _mul) * 35));
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
