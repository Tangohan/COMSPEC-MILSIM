/*
    Fait bouger tout le téléphone d'un bloc (vibration d'un message, petit sursaut d'un appui) : coque, écran, page,
    barre d'état, dock, calques du verre (reflet, coins, saleté, fêlures), touches physiques. Avant (4.2.1 et plus tôt),
    seule la coque (88509) bougeait : l'écran restait en place et se décollait du cadre.
    Chaque pas pose le même décalage, dans la même image, sur tous les contrôles de premier niveau affichés qui touchent
    la coque (les enfants suivent leur groupe ; la carte aussi, son centre interne la suit). Rien n'est recréé.
    Position de repos gardée par contrôle (cs_vibBase [repos, dernière position posée]) : un contrôle déplacé par
    quelqu'un d'autre pendant la secousse (mise en page, glisser) prend sa nouvelle place comme repos ; un contrôle en cours
    d'animation (ctrlCommit avec durée : onde du toucher, titre qui glisse) n'est pas touché. À la fin, chacun revient
    exactement à son repos. Défilement des groupes remis après chaque déplacement (le déplacer le remet en haut).
    Params : [durée (s), amplitude x en pixels, amplitude y en pixels, décalage fixe (true : un seul pas de +x, +y)]
*/
params [["_dur", 0.6], ["_ax", 3], ["_ay", 2], ["_fixed", false]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
if ((uiNamespace getVariable ["COMSPEC_ATAK_Vibrating", -1]) >= 0) exitWith { true };
private _step = {
    params ["_d", "_dx", "_dy", "_list"];
    private _l = [] call comspec_atak_native_fnc_layoutGet;
    (_l getOrDefault ["phone", [0, 0, 0, 0]]) params ["_px", "_py", "_pw", "_ph"];
    {
        if (isNull (ctrlParentControlsGroup _x) && {ctrlShown _x} && {ctrlCommitted _x} && {ctrlIDC _x isNotEqualTo 88543}) then {
            private _p = ctrlPosition _x;
            _p params ["_cx", "_cy", "_cw", "_ch"];
            private _st = _x getVariable ["cs_vibBase", []];
            // Contrôle hors de la coque (bandeau du jeu, cadre de la miniature, bouton hors écran) : pas touché.
            if (_st isEqualTo [] && {_cx > _px + _pw || {_cx + _cw < _px} || {_cy > _py + _ph} || {_cy + _ch < _py}}) then { continue };
            if (_st isEqualTo []) then { _list pushBack _x; };
            if (_st isEqualTo [] || {_p isNotEqualTo (_st select 1)}) then { _st = [_p, _p]; };
            private _b = _st select 0;
            private _n = [(_b select 0) + _dx, (_b select 1) + _dy, _b select 2, _b select 3];
            if (_n isNotEqualTo _p) then {
                private _sv = if ((ctrlType _x) isEqualTo 15) then { ctrlScrollValues _x } else { [] };
                _x ctrlSetPosition _n;
                _x ctrlCommit 0;
                if ((count _sv) isEqualTo 2 && {((_sv select 0) > 0) || {(_sv select 1) > 0}}) then { _x ctrlSetScrollValues _sv; };
            };
            _st set [1, ctrlPosition _x];
            _x setVariable ["cs_vibBase", _st];
        };
    } forEach (allControls _d);
};
private _restore = {
    params ["_list"];
    {
        if (!isNull _x) then {
            private _st = _x getVariable ["cs_vibBase", []];
            _x setVariable ["cs_vibBase", nil];
            // Déplacé par quelqu'un d'autre depuis le dernier pas : sa nouvelle place est la bonne.
            if ((count _st) isEqualTo 2 && {(ctrlPosition _x) isEqualTo (_st select 1)} && {ctrlCommitted _x}) then {
                private _sv = if ((ctrlType _x) isEqualTo 15) then { ctrlScrollValues _x } else { [] };
                _x ctrlSetPosition (_st select 0);
                _x ctrlCommit 0;
                if ((count _sv) isEqualTo 2 && {((_sv select 0) > 0) || {(_sv select 1) > 0}}) then { _x ctrlSetScrollValues _sv; };
            };
        };
    } forEach _list;
};
private _list = [];
private _h = [{
    params ["_args", "_handle"];
    _args params ["_step", "_restore", "_list", "_end", "_ax", "_ay", "_fixed"];
    private _d = [] call comspec_atak_native_fnc_display;
    if (isNull _d || {diag_tickTime > _end}) exitWith {
        [_list] call _restore;
        uiNamespace setVariable ["COMSPEC_ATAK_Vibrating", -1];
        uiNamespace setVariable ["COMSPEC_ATAK_ShakeOff", [0, 0]];
        [_handle] call CBA_fnc_removePerFrameHandler;
    };
    private _o = if (_fixed) then { [pixelW * _ax, pixelH * _ay] } else { [(random 2 - 1) * pixelW * _ax, (random 2 - 1) * pixelH * _ay] };
    uiNamespace setVariable ["COMSPEC_ATAK_ShakeOff", _o];
    [_d, _o select 0, _o select 1, _list] call _step;
}, [0.02, 0] select _fixed, [_step, _restore, _list, diag_tickTime + _dur, _ax, _ay, _fixed]] call CBA_fnc_addPerFrameHandler;
uiNamespace setVariable ["COMSPEC_ATAK_Vibrating", _h];
true
