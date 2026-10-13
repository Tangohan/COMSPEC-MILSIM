/*
    Code de la langue à afficher pour une valeur du réglage « Langue / Language » : "fr", ou le code d'une langue de
    fn_langList. Auto (ou une langue dont le mod de traduction n'est plus chargé) : français si Arma est en français,
    sinon la langue déclarée pour la langue du jeu (gameLanguage), sinon l'anglais.
*/
params [["_set", "auto"]];
if (_set isEqualTo "fr") exitWith { "fr" };
private _list = [] call comspec_atak_native_fnc_langList;
if ((_list findIf { (_x select 0) isEqualTo _set }) >= 0) exitWith { _set };
if (language isEqualTo "French") exitWith { "fr" };
private _i = _list findIf { language in (_x select 3) };
if (_i >= 0) exitWith { (_list select _i) select 0 };
["fr", "en"] select ((_list findIf { (_x select 0) isEqualTo "en" }) >= 0)
