/*
    Couleur d'un marqueur (RGBA), comme la carte d'Arma l'affiche, lue une fois par classe et par type.
    « Default » vaut {0,0,0,1} dans CfgMarkerColors : le moteur prend alors la couleur du type (CfgMarkers), par exemple
    celle du camp pour un symbole OTAN. La lire telle quelle dessinait ces marqueurs en noir sur tous les fonds.
    Params : [classe CfgMarkerColors, type CfgMarkers (""), couleur si rien n'est défini] -> copie modifiable
*/
params ["_cls", ["_type", ""], ["_def", [0, 0, 0, 1]]];
private _cache = uiNamespace getVariable "COMSPEC_ATAK_MkResolved";
if (isNil "_cache") then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MkResolved", _cache]; };
private _key = _cls + "|" + _type;
private _c = _cache getOrDefault [_key, -1];
if (_c isEqualTo -1) then {
    private _num = { _this apply { if (_x isEqualType "") then { call compile _x } else { _x } } };
    _c = (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) call _num;
    if ((toLower _cls) isEqualTo "default" || {(count _c) < 4}) then {
        private _t = (getArray (configFile >> "CfgMarkers" >> _type >> "color")) call _num;
        if ((count _t) >= 4) then { _c = _t; };
    };
    if ((count _c) < 4) then { _c = []; };
    _cache set [_key, _c];
};
if ((count _c) < 4) then { +_def } else { +_c }
