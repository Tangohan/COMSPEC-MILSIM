/*
    État du réseau d'un coup d'œil pour l'app Wave Relay, tiré de fn_linkQuality : bon, dégradé ou coupé, cause principale et action à faire.
      ["read", qualité]  -> HashMap : level ("ok" | "warn" | "ko"), title, cause, action, rows (facteurs, du plus gênant au moins gênant, lignes "check"),
                            text (texte structuré de la carte d'état), sig (change seulement si l'état, la cause ou l'action changent)
      ["live"] (appelé par fn_mpu5 "tick", toutes les 2 s, seulement l'app ouverte sur MON NŒUD) : état changé, page redessinée ;
                            sinon seuls les chiffres de la carte changent, sans redessiner la page.
    Aucun chiffre écrit ici : gains et pertes viennent des facteurs de fn_linkQuality.
*/
params [["_act", "read"], ["_q", createHashMap]];
if (_act isEqualTo "live") exitWith {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "WAVERELAY" || {(_s getOrDefault ["wrTab", "NODE"]) isNotEqualTo "NODE"} || {(_s getOrDefault ["wrTuto", -1]) >= 0}) exitWith {};
    private _ctl = uiNamespace getVariable ["COMSPEC_ATAK_WrStatusCtl", controlNull];
    if (isNull _ctl) exitWith {};
    private _st = ["read", [] call comspec_atak_native_fnc_linkQuality] call comspec_atak_native_fnc_waveRelayStatus;
    if ((_st get "sig") isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_WrStatusSig", []])) exitWith {
        [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
    if ((_st get "text") isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_WrStatusText", ""])) then {
        uiNamespace setVariable ["COMSPEC_ATAK_WrStatusText", _st get "text"];
        _ctl ctrlSetStructuredText parseText ((_st get "text") call comspec_atak_native_fnc_tr);
    };
};
private _hex = { COMSPEC_ATAK_Th get _this };
// Gêne d'un facteur (0 : neutre, négatif : gain) et niveau de sa pastille, d'après le texte de son effet.
private _rate = {
    params ["_v"];
    private _l = toLower _v;
    private _n = 0;
    { private _t = parseNumber _x; if (_t != 0) exitWith { _n = abs _t; }; } forEach (_l splitString " ");
    private _first = _l select [0, 1];
    switch (true) do {
        case ((_l find "aucun effet") >= 0): { [-2, "off"] };
        case ((_l find "aucun") >= 0): { [100, "ko"] };
        case ((_l find "en file") >= 0): { [60, "warn"] };
        case ((_l find "rafales") >= 0): { [30, "warn"] };
        case ((_l find "pertes") >= 0 || {(_l find "perte ") >= 0}): { [_n, "warn"] };
        case (_first isEqualTo "-" && {(_l find " db") >= 0}): { [(_n * 2) min 60, "warn"] };
        case (_first isEqualTo "-"): { [_n, "warn"] };
        case (_first isEqualTo "+" && {(_l find " ms") >= 0}): { [5, "warn"] };
        case (_first isEqualTo "+" || {(_l find "bit/s") >= 0}): { [-1, "ok"] };
        default { [0, "off"] };
    }
};
private _actions = [
    ["Objet requis", "équipez-le, le réseau repart seul"],
    ["En sous-sol", "remontez à l'air libre"],
    ["Au cœur du bâtiment", "sortez à l'air libre"],
    ["Au fond du bâtiment", "approchez d'une ouverture ou sortez"],
    ["Dans un bâtiment", "sortez"],
    ["Sous abri", "sortez à découvert"],
    ["Sous l'eau", "remontez à la surface"],
    ["Relief masquant", "montez sur un point haut"],
    ["Brouillage", "éloignez-vous de la zone brouillée"],
    ["Aucun relais", "approchez d'un relais"],
    ["Hors de portée d'émission", "approchez du relais"],
    ["Relais saturé", "changez de relais ou limitez vos envois"],
    ["Surcharge du réseau", "limitez vos envois"],
    ["Maillage en limite", "approchez d'un nœud"],
    ["Sans MPU5", "reliez une MPU5 par câble EUD ou Wi-Fi"],
    ["MPU5 :", "vérifiez la MPU5 : allumée, batterie, canal"],
    ["Antenne du téléphone", "réparez le téléphone ou montez une antenne"],
    ["Boîtier du téléphone", "réparez le téléphone"],
    ["Batterie faible", "rechargez le téléphone"],
    ["Dans un véhicule", "sortez, ou passez par le terminal de bord"],
    ["En mouvement rapide", "ralentissez"],
    ["Zone ", "quittez la zone"],
    ["Athena hors ligne", "attendez le retour d'Athena (app Athena)"],
    ["Liaison Athena", "attendez le retour d'Athena (app Athena)"],
    ["Mode avion", "coupez le mode avion"]
];
private _rated = (_q getOrDefault ["factors", []]) apply {
    _x params ["_lb", "_val"];
    ([_val] call _rate) params ["_sev", "_lvl"];
    [_sev, _lb, _val, _lvl]
};
_rated sort false;
private _bars = _q getOrDefault ["bars", 0];
private _loss = _q getOrDefault ["loss", 0];
private _kbps = _q getOrDefault ["kbps", 0];
private _worst = if (_rated isEqualTo []) then { [] } else { _rated select 0 };
private _sev = _worst param [0, 0];
private _level = switch (true) do {
    case (_bars <= 0): { "ko" };
    case (_bars <= 2 || {_loss >= 20} || {_sev >= 50} || {(_q getOrDefault ["label", ""]) isEqualTo "Réception seule"}): { "warn" };
    default { "ok" };
};
private _cause = "";
private _action = "";
if (_sev > 0 && {_level isNotEqualTo "ok"}) then {
    _cause = format ["%1 : %2", _worst select 1, _worst select 2];
    private _lb = _worst select 1;
    { if ((_lb select [0, count (_x select 0)]) isEqualTo (_x select 0)) exitWith { _action = _x select 1; }; } forEach _actions;
};
private _title = switch (true) do {
    case (_q getOrDefault ["airplane", false]): { "Mode avion" };
    case (_level isEqualTo "ko"): { "Réseau coupé" };
    case (_level isEqualTo "warn"): { "Réseau dégradé" };
    default { "Réseau bon" };
};
private _col = createHashMapFromArray [["ok", "hexOk"], ["warn", "hexWarn"], ["ko", "hexAlert"]] get _level;
private _rate2 = [format ["%1 kbit/s", _kbps], format ["%1 Mbit/s", (_kbps / 1000) toFixed 1]] select (_kbps >= 1000);
private _txt = format ["<t size='1.25' font='COMSPEC_BarlowBold' color='%1'>● %2</t>%3<br/><t size='0.85' color='%4'>Latence %5 ms · perte %6 %%</t>%7%8",
    _col call _hex, _title, ["", format ["  <t size='1.1'>%1</t>", _rate2]] select (_kbps > 0), "hexTextMuted" call _hex, _q getOrDefault ["latency", 0], _loss,
    ["", format ["<br/><t size='0.95'>%1</t>", _cause]] select (_cause isNotEqualTo ""),
    ["", format ["<br/><t size='0.95' color='%1'>%2</t>", "hexWarn" call _hex, _action]] select (_action isNotEqualTo "")];
createHashMapFromArray [
    ["level", _level], ["title", _title], ["cause", _cause], ["action", _action], ["text", _txt], ["sig", [_level, _title, _cause, _action]],
    ["rows", _rated apply { ["check", _x select 3, _x select 1, _x select 2] }]
]
