// Outils des étapes. Chaque étape tourne dans son propre script : une erreur ou un dépassement n'arrête pas la suite.
AT_NOk = 0;
AT_NKo = 0;
AT_NSkip = 0;
AT_Shots = 0;
AT_Echecs = [];
AT_Fin = "";
AT_Nom = "";

// Une vérification ratée : l'étape continue, elle finira en KO.
AT_fnc_check = {
    params ["_ok", "_motif"];
    if !(_ok isEqualTo true) then { AT_Echecs pushBack _motif; };
    _ok isEqualTo true
};
// Fin d'étape : OK, ou KO avec tous les motifs.
AT_fnc_done = { AT_Fin = "DONE"; };
AT_fnc_skip = { AT_Fin = "SKIP|" + _this; };
AT_fnc_info = { diag_log format ["AUTOTEST|INFO|%1|%2", AT_Nom, _this]; };
// À contrôler à l'œil sur la capture : rien à vérifier par script.
AT_fnc_visuel = { diag_log format ["AUTOTEST|VISUEL|%1|%2", AT_Nom, _this]; };

AT_fnc_shot = {
    params ["_nom"];
    uiSleep 0.5;
    AT_Shots = AT_Shots + 1;
    private _file = format ["CAT_%1_%2.png", [AT_Shots, 3] call CBA_fnc_formatNumber, _nom];
    private _ok = screenshot _file;
    diag_log format ["AUTOTEST|SHOT|%1|%2|%3", AT_Nom, _file, _ok];
    uiSleep 0.7;
};

AT_fnc_wait = {
    params ["_cond", ["_max", 8]];
    private _fin = diag_tickTime + _max;
    waitUntil { uiSleep 0.1; (call _cond) || {diag_tickTime > _fin} };
    call _cond
};

// Une fonction absente (branche pas encore fusionnée) : l'étape passe en SKIP.
AT_fnc_nil = {
    private _manque = _this select { isNil _x };
    if (_manque isEqualTo []) exitWith { false };
    (format ["absent de ce build : %1", _manque joinString ", "]) call AT_fnc_skip;
    true
};

AT_fnc_nettoie = {
    if (dialog) then { closeDialog 0; };
    if (!isNull findDisplay 312) then { (findDisplay 312) closeDisplay 2; };
    if (!isNil "comspec_atak_native_fnc_close") then { [] call comspec_atak_native_fnc_close; };
};

AT_fnc_groupe = { (AT_Groupes isEqualTo []) || {_this in AT_Groupes} };

AT_fnc_run = {
    params ["_nom", "_code", ["_max", 90], ["_args", []]];
    AT_Nom = _nom;
    AT_Echecs = [];
    AT_Fin = "";
    diag_log format ["AUTOTEST|BEGIN|%1", _nom];
    private _h = _args spawn _code;
    private _limite = diag_tickTime + _max;
    waitUntil { uiSleep 0.1; scriptDone _h || {diag_tickTime > _limite} };
    private _res = switch (true) do {
        case (!scriptDone _h): { terminate _h; ["KO", format ["dépassement de %1 s", _max]] };
        case (AT_Fin isEqualTo ""): { ["KO", "script interrompu (erreur SQF, voir le RPT)"] };
        case ((AT_Fin select [0, 4]) isEqualTo "SKIP"): { ["SKIP", AT_Fin select [5]] };
        case (AT_Echecs isNotEqualTo []): { ["KO", AT_Echecs joinString " ; "] };
        default { ["OK", ""] };
    };
    _res params ["_etat", "_motif"];
    switch (_etat) do {
        case "OK": { AT_NOk = AT_NOk + 1; diag_log format ["AUTOTEST|OK|%1", _nom]; };
        case "KO": { AT_NKo = AT_NKo + 1; diag_log format ["AUTOTEST|KO|%1|%2", _nom, _motif]; };
        default { AT_NSkip = AT_NSkip + 1; diag_log format ["AUTOTEST|SKIP|%1|%2", _nom, _motif]; };
    };
    call AT_fnc_nettoie;
    diag_log format ["AUTOTEST|END|%1", _nom];
    uiSleep 0.5;
};

// Téléphone ouvert en main, déverrouillé. Vrai si l'écran est là.
AT_fnc_tel = {
    if !("COMSPEC_ATAK_Phone" in (items player)) then { player addItem "COMSPEC_ATAK_Phone"; };
    if (isNull ([] call comspec_atak_native_fnc_display)) then {
        [true] call comspec_atak_native_fnc_open;
        [{ !isNull ([] call comspec_atak_native_fnc_display) }, 6] call AT_fnc_wait;
        uiSleep 1;
    };
    if (uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) then {
        ["unlock"] call comspec_atak_native_fnc_lockScreen;
        uiSleep 0.5;
    };
    !isNull ([] call comspec_atak_native_fnc_display)
};
// Va à une page de l'ATAK : vrai si elle est bien la page active, téléphone toujours ouvert.
AT_fnc_page = {
    params ["_page", ["_attente", 1.5]];
    if !(call AT_fnc_tel) exitWith { false };
    [_page] call comspec_atak_native_fnc_navigate;
    uiSleep _attente;
    !isNull ([] call comspec_atak_native_fnc_display)
        && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo _page}
};
AT_fnc_etat = { uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap] };
// Carte centrée sur le joueur, à cette échelle.
AT_fnc_centre = {
    params [["_echelle", 0.02]];
    [player, _echelle] call comspec_atak_native_fnc_mapCenter;
    uiSleep 1.2;
};
