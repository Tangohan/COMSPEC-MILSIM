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
private _ext = missionNamespace getVariable ["COMSPEC_ExtensionVersion", ""];
private _game = productVersion;
private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\logo_soar.paa", format ["<t size='1.5' font='RobotoCondensedBold'>COMSPEC ATAK</t><br/><t color='#5cc76b'>Version %1</t><br/><t size='0.9' color='#8a9a93'>Terminal tactique pour Arma 3</t>", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "?"]]],
    ["section", "Équipe", ""],
    ["info", "Équipe", "<t font='RobotoCondensedBold'>SOAR</t>"],
    ["info", "Développeur", "<t font='RobotoCondensedBold'>NewPI</t>"],
    ["section", "Versions", "Composants chargés sur ce poste"],
    ["info", "ATAK natif", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "?"]],
    ["info", "Interface", missionNamespace getVariable ["COMSPEC_ATAK_UI_GENERATION", "?"]],
    ["info", "Extension (DLL)", [_ext, "<t color='#8a9a93'>inconnue</t>"] select (_ext in ["", "unknown"])],
    ["info", "Overwatch connect", ["comspec_overwatch_connect"] call _patch],
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
