/*
    Doigt d'honneur libre : tant que la touche est maintenue (ou 6 s par l'action ACE), le bras gauche reste tendu,
    majeur levé, et suit sa cible comme dans GTA.
    Cible, revue toutes les 0,12 s : l'homme sous le curseur (100 m), sinon le joueur le plus proche du centre de
    l'écran (moins de 30° et 60 m, IA comprises), sinon simplement la direction du curseur.
    Le bras ne pivote pas librement (Arma ne laisse pas piloter un os par script) : une pose en boucle par case
    d'une grille cap -40..+60° (positif = vers la gauche) x site -20..+40°, la plus proche de la cible est jouée
    (gestures_aim.hpp, tools/gen_gestures.py). Au-delà, le bras reste au bord de la grille.
    Params : ["start" | "stop" | "tick" | "timed", durée pour "timed"] ; renvoie true si le geste démarre.
*/
params [["_mode", "start"], ["_arg", 6]];
if (!hasInterface) exitWith { false };
private _ok = {
    alive player && {vehicle player isEqualTo player} && {!(player getVariable ["ACE_isUnconscious", false])}
    && {(eyePos player) select 2 >= 0} && {!(uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false])}
};
switch (toLower _mode) do {
    case "start": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_FingerAim", false]) exitWith { false };
        if !(call _ok) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_FingerAim", true];
        missionNamespace setVariable ["COMSPEC_ATAK_FingerAimCell", ""];
        missionNamespace setVariable ["COMSPEC_ATAK_FingerAimAt", diag_tickTime + 0.33];
        player playActionNow "GestureComspecFingerAimUp";
        missionNamespace setVariable ["COMSPEC_ATAK_FingerAimPfh", [{ ["tick"] call comspec_atak_native_fnc_gestureFingerAim; }, 0.12] call CBA_fnc_addPerFrameHandler];
        true
    };
    case "timed": {
        if !(["start"] call comspec_atak_native_fnc_gestureFingerAim) exitWith { false };
        [{ ["stop"] call comspec_atak_native_fnc_gestureFingerAim; }, [], _arg] call CBA_fnc_waitAndExecute;
        true
    };
    case "stop": {
        if !(missionNamespace getVariable ["COMSPEC_ATAK_FingerAim", false]) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_FingerAim", false];
        [missionNamespace getVariable ["COMSPEC_ATAK_FingerAimPfh", -1]] call CBA_fnc_removePerFrameHandler;
        missionNamespace setVariable ["COMSPEC_ATAK_FingerAimPfh", -1];
        if (alive player && {(toLower (gestureState player)) find "comspecfingeraim" >= 0}) then { player playActionNow "GestureComspecFingerAimDown"; };
        true
    };
    case "tick": {
        if !(call _ok) exitWith { ["stop"] call comspec_atak_native_fnc_gestureFingerAim; };
        // Laisse la montée se finir avant de viser.
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_FingerAimAt", 0])) exitWith {};
        private _eye = eyePos player;
        private _camPos = AGLToASL (positionCameraToWorld [0, 0, 0]);
        private _camDir = vectorNormalized ((AGLToASL (positionCameraToWorld [0, 0, 1])) vectorDiff _camPos);
        private _tgt = objNull;
        private _cur = cursorObject;
        if (!isNull _cur && {_cur isKindOf "CAManBase"} && {alive _cur} && {_cur isNotEqualTo player} && {(_cur distance player) < 100}) then { _tgt = _cur; };
        if (isNull _tgt) then {
            private _best = 30;
            {
                private _v = (eyePos _x) vectorDiff _camPos;
                private _ang = acos ((((vectorNormalized _v) vectorDotProduct _camDir) max -1) min 1);
                if (_ang < _best) then { _best = _ang; _tgt = _x; };
            } forEach ((player nearEntities ["CAManBase", 60]) select { alive _x && {_x isNotEqualTo player} });
        };
        private _aim = if (isNull _tgt) then { _camPos vectorAdd (_camDir vectorMultiply 30) } else { eyePos _tgt };
        private _m = player vectorWorldToModelVisual (_aim vectorDiff _eye);
        _m params ["_mx", "_my", "_mz"];
        private _yaw = (-_mx) atan2 _my;
        private _pit = _mz atan2 (sqrt (_mx * _mx + _my * _my));
        // Case la plus proche de la grille (pas de 20°), bornée à la grille.
        private _snap = { params ["_v", "_lo", "_hi"]; (((round (_v / 20)) * 20) max _lo) min _hi };
        private _y = [_yaw, -40, 60] call _snap;
        private _p = [_pit, -20, 40] call _snap;
        private _tag = { params ["_v"]; format ["%1%2", ["m", "p"] select (_v >= 0), abs _v] };
        private _cell = format ["GestureComspecFingerAim_y%1_p%2", [_y] call _tag, [_p] call _tag];
        // Nouvelle case, ou geste retombé (autre geste joué entre-temps) : on rejoue la bonne pose.
        if (_cell isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_FingerAimCell", ""])
            || {(toLower (gestureState player)) find "comspecfingeraim_" < 0}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_FingerAimCell", _cell];
            player playActionNow _cell;
        };
    };
    default { false };
};
