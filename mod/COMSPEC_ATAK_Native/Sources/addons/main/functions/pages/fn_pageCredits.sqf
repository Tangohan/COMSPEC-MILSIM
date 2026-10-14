/*
    App Crédits : équipe SOAR, développement NewPI, versions des composants chargés, remerciements et licence.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _patch = {
    params ["_cls"];
    private _c = configFile >> "CfgPatches" >> _cls;
    if !(isClass _c) exitWith { "<t color='#8a9a93'>non chargé</t>" };
    private _v = getText (_c >> "versionStr");
    if (_v isEqualTo "") then { _v = (getArray (_c >> "versionAr")) apply { str _x } joinString "."; };
    if (_v isEqualTo "") then { _v = str getNumber (_c >> "version"); };
    _v
};
private _ext = [] call comspec_atak_native_fnc_extVersion;
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _want = missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", ""];
// Version du code et de la config : différentes quand deux main.pbo sont chargés (fn_integrityCheck).
private _cfg = ["comspec_atak_native_main"] call _patch;
private _code = missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "?"];
private _dll = switch (true) do {
    case (_ext isEqualTo ""): { "<t color='#e5483a'>absente ou trop ancienne : relancez build_mod.bat</t>" };
    case (_bridge && {_want isNotEqualTo ""} && {_ext isNotEqualTo _want}): { format ["<t color='#f2ab33'>%1 (attendue : %2, relancez build_mod.bat)</t>", _ext, _want] };
    default { _ext };
};
private _game = productVersion;
private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\logo_soar.paa", format ["<t size='1.5' font='COMSPEC_BarlowBold'>COMSPEC ATAK</t><br/><t color='#5cc76b'>Version %1</t><br/><t size='0.9' color='#8a9a93'>Terminal tactique pour Arma 3</t>", missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "?"]]]],
    ["section", "Équipe", ""],
    ["info", "Équipe", "<t font='COMSPEC_BarlowBold'>SOAR</t>"],
    ["info", "Développeur", "<t font='COMSPEC_BarlowBold'>NewPI</t>"],
    ["section", "Versions", "Composants chargés sur ce poste"],
    ["info", "COMSPEC ATAK", [_code, format ["<t color='#e5483a'>%1 (config %2 : deux main.pbo chargés ?)</t>", _code, _cfg]] select (_code isNotEqualTo _cfg)],
    ["info", "Interface", missionNamespace getVariable ["COMSPEC_ATAK_UI_GENERATION", "?"]],
    ["info", ["DLL native", "COMSPEC Link (DLL)"] select _bridge, _dll],
    ["info", "Addon Overwatch connect", ["comspec_overwatch_connect"] call _patch],
    ["info", "CBA", ["cba_main"] call _patch],
    ["info", "ACE", ["ace_main"] call _patch],
    ["info", "ACRE2", ["acre_main"] call _patch],
    ["info", "Arma 3", format ["%1.%2", (_game select 2) / 100, _game select 3]],
    ["section", "Remerciements", ""],
    ["text", "<t size='0.9'>Better CAS Environment (BCE) par Aaren : registre d'applications, messages directs, groupe et tâches, réécrits pour ce terminal (licence APL-SA).<br/>Simple Map Tools (POLPOX) : comportement des outils carte, réécrit sans reprise de code.<br/>Symboles de carte d'Arma 3 (Bohemia Interactive).</t>"],
    ["section", "Licence", ""],
    ["text", "<t size='0.9' color='#8a9a93'>Distribué sous Arma Public License Share Alike (APL-SA) : gratuit, non commercial, crédit aux auteurs, même licence pour toute adaptation.</t>"]
];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
