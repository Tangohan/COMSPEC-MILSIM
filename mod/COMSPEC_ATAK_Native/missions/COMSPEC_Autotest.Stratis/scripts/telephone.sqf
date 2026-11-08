// Téléphone : objet, écran de verrouillage, code, chaque app du lanceur, thèmes.
if !("telephone" call AT_fnc_groupe) exitWith {};
["tel_objet", {
    if (["comspec_atak_native_fnc_hasDevice", "comspec_atak_native_fnc_open"] call AT_fnc_nil) exitWith {};
    if ([player] call comspec_atak_native_fnc_hasDevice) then { "accès déjà ouvert sans l'objet (équipement de départ ou réglage)" call AT_fnc_info; };
    player addItem "COMSPEC_ATAK_Phone";
    [("COMSPEC_ATAK_Phone" in (items player)), "objet téléphone non donné"] call AT_fnc_check;
    [[player] call comspec_atak_native_fnc_hasDevice, "fn_hasDevice faux avec le téléphone sur soi"] call AT_fnc_check;
    call AT_fnc_done;
}] call AT_fnc_run;

["tel_verrouillage_code", {
    if (["comspec_atak_native_fnc_lockScreen", "comspec_atak_native_fnc_phoneOwn", "comspec_atak_native_fnc_phonePad"] call AT_fnc_nil) exitWith {};
    ["setPin", ["1234", "autotest"]] call comspec_atak_native_fnc_phoneOwn;
    missionNamespace setVariable ["COMSPEC_ATAK_PinOk", false];
    missionNamespace setVariable ["COMSPEC_ATAK_LockPending", true];
    [true] call comspec_atak_native_fnc_open;
    [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 8] call AT_fnc_wait, "le téléphone ne s'ouvre pas"] call AT_fnc_check;
    [[{ uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false] }, 10] call AT_fnc_wait, "pas d'écran de verrouillage à l'ouverture"] call AT_fnc_check;
    ["verrouillage"] call AT_fnc_shot;
    [["needPin"] call comspec_atak_native_fnc_phoneOwn, "code enregistré mais non demandé"] call AT_fnc_check;
    ["unlock"] call comspec_atak_native_fnc_lockScreen;
    uiSleep 1;
    [["up"] call comspec_atak_native_fnc_phonePad, "clavier du code absent"] call AT_fnc_check;
    ["code_clavier"] call AT_fnc_shot;
    { ["key", _x] call comspec_atak_native_fnc_phonePad; } forEach ["9", "9", "9", "9"];
    ["ok"] call comspec_atak_native_fnc_phonePad;
    uiSleep 0.5;
    [uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false], "code faux accepté"] call AT_fnc_check;
    ["code_faux"] call AT_fnc_shot;
    for "_i" from 1 to 4 do { ["del"] call comspec_atak_native_fnc_phonePad; };
    { ["key", _x] call comspec_atak_native_fnc_phonePad; } forEach ["1", "2", "3", "4"];
    ["ok"] call comspec_atak_native_fnc_phonePad;
    [[{ !(uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) }, 5] call AT_fnc_wait, "bon code refusé : toujours verrouillé"] call AT_fnc_check;
    ["deverrouille"] call AT_fnc_shot;
    ["setPin", ["", ""]] call comspec_atak_native_fnc_phoneOwn;
    uiSleep 0.5;
    [!(["hasPin"] call comspec_atak_native_fnc_phoneOwn), "code non retiré"] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

// Une étape par app : une app qui casse n'empêche pas la suivante.
{
    private _app = _x;
    if (_app getOrDefault ["wip", false]) then {
        [format ["app_%1", _app get "page"], { "app en chantier (wip)" call AT_fnc_skip; }] call AT_fnc_run;
    } else {
        [format ["app_%1", _app get "page"], {
            params ["_app"];
            private _p = _app get "page";
            private _ok = [_p, 2] call AT_fnc_page;
            [_ok, format ["%1 : page active « %2 », téléphone ouvert %3", _app get "name",
                (call AT_fnc_etat) getOrDefault ["activePage", ""], !isNull ([] call comspec_atak_native_fnc_display)]] call AT_fnc_check;
            [format ["app_%1", _p]] call AT_fnc_shot;
            call AT_fnc_done;
        }, 30, [_app]] call AT_fnc_run;
    };
} forEach (if (isNil "comspec_atak_native_fnc_appList") then { [] } else { [] call comspec_atak_native_fnc_appList });

["tel_themes", {
    if (["comspec_atak_native_fnc_theme"] call AT_fnc_nil) exitWith {};
    {
        ["set", _x] call comspec_atak_native_fnc_theme;
        uiSleep 1;
        [(([] call comspec_atak_native_fnc_theme) get "name") isEqualTo _x, format ["thème %1 non appliqué", _x]] call AT_fnc_check;
        private _t = toLower _x;
        ["LAUNCHER"] call AT_fnc_page;
        [format ["theme_%1_lanceur", _t]] call AT_fnc_shot;
        ["MAP", 2] call AT_fnc_page;
        [format ["theme_%1_carte", _t]] call AT_fnc_shot;
        ["SETTINGS"] call AT_fnc_page;
        [format ["theme_%1_reglages", _t]] call AT_fnc_shot;
    } forEach ["DARK", "DAY", "NVG"];
    ["set", "DARK"] call comspec_atak_native_fnc_theme;
    call AT_fnc_done;
}, 120] call AT_fnc_run;
