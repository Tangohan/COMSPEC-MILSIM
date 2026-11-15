/*
    Taille et place du terminal SEEK II : mode réduit pour voir tout l'appareil (lecteur d'empreintes vert compris)
    ou laisser de la place au jeu.

    Les contrôles gardent leurs IDC et leurs positions d'origine (relevées au premier appel) : chacun est replacé
    autour du centre de l'appareil, puis réduit sur place avec ctrlSetScale. En grand format (1,0, centré) rien n'est
    touché : le terminal reste exactement celui d'avant. Le choix est mémorisé dans le profil du joueur.

    [_op, _arg] call comspec_overwatch_connect_fnc_seekScale
      "apply"            applique la taille et la place enregistrées (profil, sinon réglages CBA)
      "cycle" [dir]      taille suivante (dir 1) ou précédente (dir -1) : 100 %, 75 %, 55 %, 40 %
      "anchor"           place suivante : centre, gauche, droite
      "refresh"          texte des boutons de l'accueil (taille, place, interactions)
      "k"                -> échelle courante
    Réglages : comspec_seek_scale (0,4 à 1), comspec_seek_anchor (0 centre, 1 gauche, 2 droite).
*/
params [["_op", "apply", [""]], ["_arg", 1]];
if (!hasInterface) exitWith { 1 };

private _steps = [1, 0.75, 0.55, 0.4];
private _k = profileNamespace getVariable ["COMSPEC_SeekScale", missionNamespace getVariable ["comspec_seek_scale", 1]];
if (!(_k isEqualType 0)) then { _k = 1; };
_k = (_k max 0.4) min 1;
private _anchor = profileNamespace getVariable ["COMSPEC_SeekAnchor", missionNamespace getVariable ["comspec_seek_anchor", 0]];
if (!(_anchor isEqualType 0)) then { _anchor = 0; };
_anchor = (round _anchor) max 0 min 2;

private _save = {
    profileNamespace setVariable ["COMSPEC_SeekScale", _k];
    profileNamespace setVariable ["COMSPEC_SeekAnchor", _anchor];
    if (!isNil "comspec_overwatch_connect_fnc_saveProfile") then { [] call comspec_overwatch_connect_fnc_saveProfile; };
};

if (_op isEqualTo "k") exitWith { _k };

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };

switch (_op) do {
    case "cycle": {
        private _i = (_steps findIf { abs (_x - _k) < 0.04 });
        if (_i < 0) then { _i = 0; };
        _i = (_i + (_arg param [0, 1]) + (count _steps)) mod (count _steps);
        _k = _steps select _i;
        call _save;
        ["apply"] call comspec_overwatch_connect_fnc_seekScale;
    };
    case "anchor": {
        _anchor = (_anchor + 1) mod 3;
        call _save;
        ["apply"] call comspec_overwatch_connect_fnc_seekScale;
    };
};
if (_op in ["cycle", "anchor"]) exitWith { _k };
if (isNull _disp) exitWith { _k };

if (_op isEqualTo "refresh") exitWith {
    // Modèle et valeur traduits séparément, puis assemblés.
    private _txt = {
        params ["_idc", "_tpl", "_val"];
        private _c = _disp displayCtrl _idc;
        if (!isNull _c) then { _c ctrlSetText (format [_tpl call comspec_overwatch_connect_fnc_tr, _val call comspec_overwatch_connect_fnc_tr]); };
    };
    [9710, "TAILLE %1", format ["%1 %2", round (_k * 100), "%"]] call _txt;
    [9711, "PLACE : %1", ["CENTRE", "GAUCHE", "DROITE"] select _anchor] call _txt;
    private _m = ["mode"] call comspec_overwatch_connect_fnc_seekFx;
    [9712, "INTERACTIONS : %1", ["AUCUNE", "SIMPLES", "COMPLÈTES"] select _m] call _txt;
    _k
};

// Positions d'origine, une seule fois par affichage (avant toute réduction).
private _base = _disp getVariable ["COMSPEC_SeekBase", []];
if ((count _base) == 0) then {
    {
        if (!isNull _x) then { _base pushBack [_x, ctrlPosition _x]; };
    } forEach (allControls _disp);
    _disp setVariable ["COMSPEC_SeekBase", _base];
};
if ((count _base) == 0) exitWith { _k };
private _chassis = (_base select 0) select 1;
private _ocx = (_chassis select 0) + (_chassis select 2) / 2;
private _ocy = (_chassis select 1) + (_chassis select 3) / 2;
private _w = _chassis select 2;
private _h = _chassis select 3;

// Centre voulu : horizontal selon la place ; vertical centré dès que l'appareil entier tient à l'écran.
private _ncx = _ocx;
switch (_anchor) do {
    case 1: { _ncx = safezoneX + 0.02 + (_w * _k) / 2; };
    case 2: { _ncx = safezoneX + safezoneW - 0.02 - (_w * _k) / 2; };
    default { _ncx = safezoneX + safezoneW / 2; };
};
private _ncy = if (_h * _k <= safezoneH * 0.98) then { safezoneY + safezoneH / 2 } else { (_chassis select 1) + (_h * _k) / 2 };
if (_k >= 0.999 && {_anchor isEqualTo 0}) then { _ncx = _ocx; _ncy = _ocy; };

{
    _x params ["_c", "_p"];
    if (!isNull _c) then {
        _p params ["_x0", "_y0", "_pw", "_ph"];
        private _cx = _ncx + ((_x0 + _pw / 2) - _ocx) * _k;
        private _cy = _ncy + ((_y0 + _ph / 2) - _ocy) * _k;
        _c ctrlSetPosition [_cx - _pw / 2, _cy - _ph / 2, _pw, _ph];
        _c ctrlSetScale _k;
        _c ctrlCommit 0;
    };
} forEach _base;

uiNamespace setVariable ["COMSPEC_SeekRect", [_ncx - (_w * _k) / 2, _ncy - (_h * _k) / 2, _w * _k, _h * _k]];
["refresh"] call comspec_overwatch_connect_fnc_seekScale;
_k
