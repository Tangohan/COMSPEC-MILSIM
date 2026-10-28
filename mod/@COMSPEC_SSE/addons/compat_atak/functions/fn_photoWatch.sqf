/*
    Photos de preuve par l'app Photo de l'ATAK (plus d'action ACE « Photographier »).
    Pendant le déclenchement (COMSPEC_ATAK_PhotoShooting), la vue du joueur est celle de l'objectif :
    les entités SSE cadrées (axe ± demi-champ, 15 m au plus, sans obstacle) reçoivent une photo
    dans leur section « photos » (fusion serveur) et une ligne PHOTOGRAPHIE à leur journal de pièce.
    Une pièce photographiée avant d'être déplacée ou scellée est marquée « en place » (qualité de collecte).
    [] call comspec_sse_fnc_photoWatch    (boucle installée une fois, client)
*/
if (!hasInterface || {!isNil "comspec_sse_photoWatchPfh"}) exitWith {};

comspec_sse_photoWatchPfh = [{
    private _shooting = uiNamespace getVariable ["COMSPEC_ATAK_PhotoShooting", false];
    private _was = missionNamespace getVariable ["comspec_sse_photoWasShooting", false];
    missionNamespace setVariable ["comspec_sse_photoWasShooting", _shooting];
    if (!_shooting || {_was}) exitWith {};

    // Première image du cliché : la caméra de prise de vue est active.
    private _eye = AGLToASL (positionCameraToWorld [0, 0, 0]);
    private _dir = vectorNormalized ((AGLToASL (positionCameraToWorld [0, 0, 1])) vectorDiff _eye);
    private _zoom = ((uiNamespace getVariable ["COMSPEC_ATAK_PhotoSt", createHashMap]) getOrDefault ["camZoom", 1]) max 1;
    private _half = 30 / _zoom;
    private _range = 15 * (sqrt _zoom);

    private _framed = [];
    {
        private _e = _x;
        if (_e getVariable ["comspec_sse_enabled", false] && {_e != player}) then {
            private _c = (getPosASL _e) vectorAdd [0, 0, [0.15, 1.2] select (_e isKindOf "CAManBase")];
            private _v = _c vectorDiff _eye;
            private _d = vectorMagnitude _v;
            if (_d > 0.3 && {_d <= _range} && {(acos (((vectorNormalized _v) vectorDotProduct _dir) min 1)) <= _half}) then {
                private _hits = lineIntersectsSurfaces [_eye, _c, player, _e, true, 1, "VIEW", "FIRE"];
                if (_hits isEqualTo []) then { _framed pushBack [_e, _d]; };
            };
        };
    } forEach (nearestObjects [ASLToAGL _eye, [], _range]);
    if (_framed isEqualTo []) exitWith {};

    [_framed, _zoom] call comspec_sse_fnc_evidencePhoto;
}, 0.05] call CBA_fnc_addPerFrameHandler;
true
