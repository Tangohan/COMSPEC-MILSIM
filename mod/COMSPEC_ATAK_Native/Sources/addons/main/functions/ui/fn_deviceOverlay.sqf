/*
    Dégâts visibles sur l'écran : fêlures (3 niveaux), écran noir au redémarrage, scintillement d'un écran abîmé.
    Contrôles de page (hors zone de contenu) recréés à chaque rendu pour rester au-dessus ; mis à jour chaque seconde.
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _rect = _l get "device";
(uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) params [["_crack", controlNull], ["_black", controlNull], ["_txt", controlNull], ["_block", controlNull]];
if (isNull _crack) then {
    _crack = ["COMSPEC_RscPhone", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _crack ctrlEnable false;
    _black = ["COMSPEC_RscText", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _black ctrlSetBackgroundColor [0, 0, 0, 1];
    _black ctrlEnable false;
    _txt = ["COMSPEC_RscStructuredText", [_rect select 0, (_rect select 1) + (_rect select 3) * 0.4, _rect select 2, (_rect select 3) * 0.3], "", false] call comspec_atak_native_fnc_pageCtrl;
    _txt ctrlEnable false;
    // Écran éteint : les clics ne passent plus.
    _block = ["COMSPEC_RscButtonOverlay", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _block ctrlSetTooltip "Téléphone éteint";
    uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", [_crack, _black, _txt, _block]];
};
private _state = _hp get "state";
private _lvl = _hp get "crack";
_crack ctrlSetText (["", format ["\z\comspec_atak_native\addons\main\data\crack_%1%2.paa", _lvl, ["_port", "_land"] select (_l get "landscape")]] select (_lvl > 0));
_crack ctrlShow (_lvl > 0);
private _off = _state isEqualTo "OFF";
_block ctrlShow _off;
_txt ctrlShow _off;
if (_off) then {
    _black ctrlShow true; _black ctrlSetFade 0; _black ctrlCommit 0;
    _txt ctrlSetStructuredText parseText format ["<t align='center' size='1.4' color='#5cc76b'>ANDROID</t><br/><t align='center' color='#c9d4cf'>%1</t><br/><t align='center' size='0.85' color='#8a9a93'>Redémarrage %2</t>", _hp get "reason", ["en cours…", format ["dans %1 s", _hp get "offLeft"]] select ((_hp get "offLeft") > 0)];
} else {
    // Écran abîmé : il saute de temps en temps.
    if (_lvl >= 2 && {random 1 < ([0.06, 0.16] select (_lvl >= 3))}) then {
        _black ctrlShow true; _black ctrlSetFade 0.1; _black ctrlCommit 0;
        _black ctrlSetFade 1; _black ctrlCommit (0.25 + random 0.4);
    } else {
        if (ctrlFade _black >= 1 || {!ctrlShown _black}) then { _black ctrlShow false; } else { _black ctrlSetFade 1; _black ctrlCommit 0.3; };
    };
};
// Redémarrage terminé : on redessine la page.
private _wasOff = uiNamespace getVariable ["COMSPEC_ATAK_DevWasOff", false];
uiNamespace setVariable ["COMSPEC_ATAK_DevWasOff", _off];
if (_wasOff && {!_off}) then {
    ["INFO", "Téléphone redémarré", 3, 20] call comspec_atak_native_fnc_notify;
    [{ [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "LAUNCHER"]] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
true
