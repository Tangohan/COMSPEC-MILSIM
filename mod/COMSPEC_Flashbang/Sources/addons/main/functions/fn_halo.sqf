/*
    comspec_fb_fnc_halo : halo lumineux d'UNE détonation à l'écran (calque COMSPEC_FB_Halo), pour qui VOIT le flash :
    couronne colorée (un des 8 modèles ui\fb_halo_<n>_ca.paa) + cœur blanc saturé, posés au point de l'écran où se
    trouve la grenade et suivis image par image.

    Hasard (réglage « Variation aléatoire ») : modèle tiré sans répéter deux fois de suite le même, rotation libre,
    taille +/-30 %, durée +/-25 %, étirement léger +/-15 %, couleur de fn_tint (couronne -> teinte de fin de flash).
    Rien si le flash est hors écran, caché par un mur, ou à plus de 400 m. De jour le halo est plus petit et plus
    pâle, et il rétrécit nettement au-delà de 30 m (pas de « soleil » visible de loin en plein jour).
    Budget : 4 halos à la fois sur ce poste ; en rafale la grenade garde SON halo et en change la forme, l'angle et la
    teinte à chaque coup (stroboscope vivant).
    [_ctx, _posASL, _k, _teinte, _rafale] call comspec_fb_fnc_halo -> numéro du modèle (0 = pas de halo)
      _k : force du flash (fn_fx) ; _teinte : retour de fn_tint ; _rafale : détonation rapprochée (halo plus bref)
*/
params ["_ctx", "_pos", ["_k", 1, [0]], ["_tintInfo", [], [[]]], ["_fast", false, [false]]];
private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _d = _cam vectorDistance _pos;
if (_d > 400) exitWith { 0 };
private _scr = worldToScreen (ASLToAGL _pos);
if (_scr isEqualTo []) exitWith { 0 };
if ((lineIntersectsSurfaces [_pos vectorAdd [0, 0, 0.3], _cam, _ctx getOrDefault ["device", objNull], vehicle (call CBA_fnc_currentUnit), true, 1, "VIEW", "GEOM"]) isNotEqualTo []) exitWith { 0 };

private _disp = uiNamespace getVariable ["comspec_fb_haloDisp", displayNull];
if (isNull _disp) then {
    ("comspec_fb_haloLayer" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_FB_Halo", "PLAIN", 0, false];
    _disp = uiNamespace getVariable ["comspec_fb_haloDisp", displayNull];
};
if (isNull _disp) exitWith { 0 };

// Emplacement : celui de la grenade s'il est encore le sien (rafale), sinon le premier libre.
private _now = diag_tickTime;
private _id = _ctx getOrDefault ["id", ""];
if (isNil "comspec_fb_haloSlots") then { comspec_fb_haloSlots = [["", 0, 0], ["", 0, 0], ["", 0, 0], ["", 0, 0]] };
private _slot = comspec_fb_haloSlots findIf { (_x select 0) isEqualTo _id && {(_x select 1) > _now} };
if (_slot < 0) then { _slot = comspec_fb_haloSlots findIf { (_x select 1) <= _now } };
if (_slot < 0) exitWith { 0 };

private _v = missionNamespace getVariable ["comspec_fb_variation", 0.6];
private _rnd = { 1 + (random 2 - 1) * _this * _v };
// Modèle : jamais deux fois de suite le même.
private _last = missionNamespace getVariable ["comspec_fb_lastHalo", 0];
private _model = 1 + floor random 8;
if (_model == _last) then { _model = 1 + ((_model + floor random 7) mod 8) };
comspec_fb_lastHalo = _model;

_tintInfo params [["_name", "blanc pur", [""]], ["_core", [1, 1, 1], [[]]], ["_corona", [1, 1, 1], [[]]], ["_late", [1, 1, 1], [[]]]];
private _sun = sunOrMoon * (1 - 0.3 * overcast);
// Taille apparente : ~1,1 m de rayon vu à la distance _d, bornée ; de jour x0,6, et x0,4 de plus vers 200 m.
private _h = safeZoneH * (((1.1 / (_d max 2.5)) max 0.035) min 0.42) * ((_k max 0.3) ^ 0.5) * (0.5 call _rnd)
    * (linearConversion [0, 1, _sun, 1, 0.6, true]) * (linearConversion [30, 200, _d, 1, linearConversion [0, 1, _sun, 0.8, 0.4, true], true]);
private _stretch = 0.25 call _rnd;
private _dur = ([0.16, 0.075] select _fast) * (0.42 call _rnd);
private _alpha = 0.85 * (linearConversion [0, 1, _sun, 1, 0.65, true]);
private _gen = ((comspec_fb_haloSlots select _slot) select 2) + 1;
comspec_fb_haloSlots set [_slot, [_id, _now + _dur, _gen]];

private _cCorona = _disp displayCtrl (201 + _slot);
private _cCore = _disp displayCtrl (211 + _slot);
_cCorona ctrlSetText format ["\z\comspec_flashbang\addons\main\ui\fb_halo_%1_ca.paa", _model];
_cCorona ctrlSetAngle [random 360, 0.5, 0.5, true];
_cCore ctrlSetAngle [random 360, 0.5, 0.5, true];

[{
    params ["_a", "_handle"];
    _a params ["_slot", "_gen", "_pos", "_t0", "_dur", "_h", "_stretch", "_alpha", "_corona", "_late", "_core", "_cCorona", "_cCore"];
    private _age = diag_tickTime - _t0;
    private _mine = ((comspec_fb_haloSlots select _slot) select 2) == _gen;
    if (!_mine || {_age > _dur} || {isNull _cCorona}) exitWith {
        [_handle] call CBA_fnc_removePerFrameHandler;
        // Emplacement repris par un autre flash : c'est lui qui dessine ; sinon on efface.
        if (_mine && {!isNull _cCorona}) then {
            _cCorona ctrlSetTextColor [1, 1, 1, 0];
            _cCore ctrlSetTextColor [1, 1, 1, 0];
        };
    };
    private _scr = worldToScreen (ASLToAGL _pos);
    if (_scr isEqualTo []) exitWith {
        _cCorona ctrlSetTextColor [1, 1, 1, 0];
        _cCore ctrlSetTextColor [1, 1, 1, 0];
    };
    private _f = _age / _dur;
    private _a1 = _alpha * (1 - _f * _f);
    // Le halo s'ouvre un peu en s'éteignant ; la couronne glisse vers la teinte de fin de flash.
    private _hh = _h * (1 + 0.25 * _f);
    private _w = _hh * (pixelW / pixelH) * _stretch;
    private _col = (_corona vectorMultiply (1 - _f)) vectorAdd (_late vectorMultiply _f);
    _cCorona ctrlSetPosition [(_scr select 0) - _w / 2, (_scr select 1) - _hh / 2, _w, _hh];
    _cCorona ctrlSetTextColor (_col + [_a1]);
    _cCorona ctrlCommit 0;
    private _hc = _h * 0.5;
    private _wc = _hc * (pixelW / pixelH);
    _cCore ctrlSetPosition [(_scr select 0) - _wc / 2, (_scr select 1) - _hc / 2, _wc, _hc];
    _cCore ctrlSetTextColor (_core + [(_a1 * 1.15) min 1]);
    _cCore ctrlCommit 0;
}, 0, [_slot, _gen, _pos, _now, _dur, _h, _stretch, _alpha, _corona, _late, _core, _cCorona, _cCore]] call CBA_fnc_addPerFrameHandler;
_ctx set ["halos", (_ctx getOrDefault ["halos", 0]) + 1];
_model
