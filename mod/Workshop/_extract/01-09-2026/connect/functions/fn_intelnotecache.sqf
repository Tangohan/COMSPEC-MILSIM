/*
    Mémoire des champs de saisie du rédacteur de fiche.

    Les trois volets partagent un seul dialog : les champs du volet inactif sont
    masqués (ctrlShow false). Or, sur certaines configurations, ctrlText d'un
    RscEdit masqué renvoie une chaîne vide — le terminal SEEK a déjà rencontré
    exactement ce piège. Validée depuis le volet de rédaction, une fiche perdrait
    donc son lieu, son repère et son code dossier, tous logés dans le volet
    contexte. Chaque valeur est donc recopiée ici tant qu'elle est lisible.

    Args: [_mode, _key]
      "capture"          mémorise les champs actuellement visibles
      "restore"          réinjecte la mémoire dans les champs vides
      "clear"            oublie tout (nouvelle fiche)
      "value", _key      valeur du champ, mémoire en repli — retourne une chaîne

    Clés : body · date · place · grid · case
*/
params [["_mode", "value", [""]], ["_key", "", [""]]];

if (!hasInterface) exitWith { "" };

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };

private _fields = [["body", 9616], ["date", 9652], ["place", 9653], ["grid", 9654], ["case", 9655]];

private _cache = uiNamespace getVariable ["COMSPEC_IntelNote_Cache", createHashMap];
if (!(_cache isEqualType createHashMap)) then { _cache = createHashMap; };

switch (toLower _mode) do {
    case "clear": {
        uiNamespace setVariable ["COMSPEC_IntelNote_Cache", createHashMap];
        ""
    };
    case "capture": {
        if (isNull _disp) exitWith { "" };
        {
            _x params ["_k", "_idc"];
            private _ctrl = _disp displayCtrl _idc;
            if (isNull _ctrl) then { continue };
            // Un champ masqué ne prouve rien : le lire écraserait la mémoire
            // avec du vide, ce qui est précisément ce qu'on veut éviter.
            if (!(ctrlShown _ctrl)) then { continue };
            _cache set [_k, ctrlText _ctrl];
        } forEach _fields;
        uiNamespace setVariable ["COMSPEC_IntelNote_Cache", _cache];
        ""
    };
    case "restore": {
        if (isNull _disp) exitWith { "" };
        {
            _x params ["_k", "_idc"];
            private _ctrl = _disp displayCtrl _idc;
            if (isNull _ctrl) then { continue };
            if ((ctrlText _ctrl) isNotEqualTo "") then { continue };
            private _value = _cache getOrDefault [_k, ""];
            if ((_value isEqualType "") && {_value isNotEqualTo ""}) then {
                _ctrl ctrlSetText _value;
            };
        } forEach _fields;
        ""
    };
    default {
        private _idc = -1;
        {
            if ((_x select 0) isEqualTo _key) exitWith { _idc = _x select 1; };
        } forEach _fields;

        // Un champ visible fait foi, même vidé : sinon effacer le cadre de
        // rédaction laisserait le compteur figé sur l'ancienne longueur.
        private _value = "";
        private _resolved = false;
        if (_idc > 0 && {!isNull _disp}) then {
            private _ctrl = _disp displayCtrl _idc;
            if (!isNull _ctrl && {ctrlShown _ctrl}) then {
                _value = ctrlText _ctrl;
                _resolved = true;
            };
        };
        if (!_resolved) then {
            _value = _cache getOrDefault [_key, ""];
        };
        if (!(_value isEqualType "")) then { _value = ""; };
        _value
    };
};
