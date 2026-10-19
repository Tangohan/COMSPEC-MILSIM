/*
    Fond de carte PHOTO : la capture de la carte (fn_mapCapture puis tools/map_stitch.py --pyramid --addon) livrée en
    mod optionnel @COMSPEC_ATAK_Photo (une PBO par carte, classe COMSPEC_ATAK_PhotoMaps, tuiles z/x/y de 256 px en
    mètres Arma, côté « extent », tuile y = 0 au nord).
    Params :
      "info"         → [extent, tileSize, minZoom, maxZoom, chemin, extension] pour la carte en cours, [] sans fond
      [contrôle carte] dessine les tuiles visibles (appelé par fn_mapOnDraw, sous tous les symboles)
    Niveau choisi pour qu'un pixel de tuile fasse au plus un pixel d'écran ; une tuile absente (zone capturée moins
    finement) est remplacée par son ancêtre le plus proche, dessiné en entier sous les tuiles fines.
    Taille des icônes : la largeur à l'écran divisée par pixelW, corrigée par COMSPEC_ATAK_PhotoPx (profil, défaut 1)
    si les tuiles se chevauchent ou laissent des joints.
*/
params [["_arg", "info"]];
private _info = uiNamespace getVariable [format ["COMSPEC_ATAK_PhotoInfo_%1", worldName], nil];
if (isNil "_info") then {
    _info = [];
    private _cfg = ("(toLower getText (_x >> 'world')) isEqualTo (toLower worldName)" configClasses (configFile >> "COMSPEC_ATAK_PhotoMaps")) param [0, configNull];
    if (!isNull _cfg && {getNumber (_cfg >> "extent") > 0}) then {
        _info = [getNumber (_cfg >> "extent"), (getNumber (_cfg >> "tileSize")) max 1, getNumber (_cfg >> "minZoom"), getNumber (_cfg >> "maxZoom"), getText (_cfg >> "path"), getText (_cfg >> "ext")];
    };
    uiNamespace setVariable [format ["COMSPEC_ATAK_PhotoInfo_%1", worldName], _info];
};
if (_arg isEqualType "") exitWith { _info };
private _map = _arg;
if (isNull _map || {_info isEqualTo []}) exitWith {};
_info params ["_ext", "_ts", "_zMin", "_zMax", "_path", "_fx"];
(ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
private _tl = _map ctrlMapScreenToWorld [_cx, _cy];
private _br = _map ctrlMapScreenToWorld [_cx + _cw, _cy + _ch];
private _wM = ((_br select 0) - (_tl select 0)) max 1;
// Mètres par pixel d'écran, puis niveau : tuile de _ts px au moins aussi fine que l'écran.
private _mpp = _wM / ((_cw / pixelW) max 1);
private _z = ceil (log (_ext / (_ts * _mpp)) / log 2);
_z = (_z max _zMin) min _zMax;
// Au plus une soixantaine de tuiles par image (carte en HUD comprise).
private _range = {
    params ["_zz"];
    private _T = _ext / (2 ^ _zz);
    private _n = 2 ^ _zz;
    [_T, (floor ((_tl select 0) / _T)) max 0, (floor (((_br select 0) - 0.001) / _T)) min (_n - 1),
        (floor ((_ext - (_tl select 1)) / _T)) max 0, (floor ((_ext - (_br select 1) - 0.001) / _T)) min (_n - 1)]
};
private _r = [_z] call _range;
while { _z > _zMin && {(((_r select 2) - (_r select 1) + 1) * ((_r select 4) - (_r select 3) + 1)) > 64} } do {
    _z = _z - 1;
    _r = [_z] call _range;
};
_r params ["_T", "_x0", "_x1", "_y0", "_y1"];
if (_x1 < _x0 || {_y1 < _y0}) exitWith {};
private _have = uiNamespace getVariable ["COMSPEC_ATAK_PhotoHave", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_PhotoHave", _have];
private _file = {
    params ["_zz", "_tx", "_ty"];
    _path + str _zz + "\" + str _tx + "\" + str _ty + _fx
};
private _exists = {
    private _f = _this call _file;
    private _k = _have get _f;
    if (isNil "_k") then { _k = fileExists _f; _have set [_f, _k]; };
    _k
};
private _k = (profileNamespace getVariable ["COMSPEC_ATAK_PhotoPx", 1]) max 0.1;
private _fine = [];
private _coarse = createHashMap;
for "_ty" from _y0 to _y1 do {
    for "_tx" from _x0 to _x1 do {
        if ([_z, _tx, _ty] call _exists) then { _fine pushBack [_z, _tx, _ty]; continue };
        // Ancêtre le plus proche (une seule fois par image).
        for "_up" from 1 to (_z - _zMin) do {
            private _a = [_z - _up, floor (_tx / (2 ^ _up)), floor (_ty / (2 ^ _up))];
            if (_a call _exists) exitWith { _coarse set [str _a, _a]; };
        };
    };
};
private _draw = {
    params ["_zz", "_tx", "_ty"];
    private _S = _ext / (2 ^ _zz);
    private _px = _k * _S / _mpp;
    _map drawIcon [[_zz, _tx, _ty] call _file, [1, 1, 1, 1], [(_tx + 0.5) * _S, _ext - (_ty + 0.5) * _S, 0], _px, _px, 0, "", 0];
};
{ _y call _draw; } forEach _coarse;
{ _x call _draw; } forEach _fine;
