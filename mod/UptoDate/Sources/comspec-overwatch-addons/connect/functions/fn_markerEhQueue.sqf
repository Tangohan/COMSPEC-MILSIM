/*
    File des marqueurs de carte touchés (gestionnaires MarkerCreated / MarkerUpdated / MarkerDeleted de XEH_postInit).
    Avant : chaque évènement lisait le marqueur, bâtissait son JSON et lançait un script d'envoi dans l'image même, et
    MarkerCreated le refaisait 0,4 s plus tard par un rappel CBA par marqueur. 200 marqueurs collés en Zeus (une
    création puis plusieurs modifications chacun) faisaient donc plusieurs centaines de lectures dans une image, puis
    autant d'un coup 0,4 s après.
    Ici le gestionnaire ne fait que noter le marqueur (temps constant, plusieurs évènements du même marqueur dans
    l'image = une seule lecture, celle de son dernier état) ; une boucle le relit à l'image suivante, 1,5 ms au plus
    par image. La relecture de 0,4 s après une création est gardée, dans la même file. Rien d'autre ne change : mêmes
    filtres dans les gestionnaires, même fn_syncMapMarker, mêmes envois.
    Params : [marqueur, supprimé, forcer (fn_isSyncableMapMarker, lu à l'évènement), relire 0,4 s plus tard]
*/
params [["_marker", "", [""]], ["_deleted", false, [true]], ["_force", false, [true]], ["_again", false, [true]]];
if (_marker isEqualTo "") exitWith {};

private _q = missionNamespace getVariable "COMSPEC_MarkerEhDirty";
if (isNil "_q") then {
    _q = createHashMap;
    missionNamespace setVariable ["COMSPEC_MarkerEhDirty", _q, false];
};
// [supprimé, forcer, à lire dès la prochaine image, heure de la relecture (-1 = aucune)]
private _e = _q getOrDefault [_marker, [false, false, false, -1]];
_e set [0, _deleted];
_e set [1, _force];
_e set [2, true];
if (_again) then { _e set [3, diag_tickTime + 0.4]; };
_q set [_marker, _e];

if ((missionNamespace getVariable ["COMSPEC_MarkerEhPfh", -1]) >= 0) exitWith {};
missionNamespace setVariable ["COMSPEC_MarkerEhPfh", [{
    params ["", "_handle"];
    private _q = missionNamespace getVariable ["COMSPEC_MarkerEhDirty", createHashMap];
    private _t0 = diag_tickTime;
    private _done = [];
    {
        _y params ["_del", "_force", "_now", "_at"];
        if (_now) then {
            _y set [2, false];
            [_x, _del, _force] call comspec_overwatch_connect_fnc_syncMapMarker;
        };
        // Relecture après une création (texte, couleur ou type posés juste après par le jeu ou un autre mod).
        if (_at >= 0 && {_t0 >= _at}) then {
            _y set [3, -1];
            if (
                (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) <= 0
                && {!([_x] call (missionNamespace getVariable ["COMSPEC_fnc_isOwnedMapMarker", { false }]))}
                && {(getMarkerColor _x) isNotEqualTo ""}
                && {missionNamespace getVariable ["comspec_overwatch_enabled", true]}
                && {["markers"] call comspec_overwatch_connect_fnc_diagIsolateAllows}
            ) then {
                [_x, false, [_x] call comspec_overwatch_connect_fnc_isSyncableMapMarker] call comspec_overwatch_connect_fnc_syncMapMarker;
            };
        };
        if (!(_y select 2) && {(_y select 3) < 0}) then { _done pushBack _x; };
        // Budget par image : le reste attend l'image suivante.
        if ((diag_tickTime - _t0) > 0.0015) exitWith {};
    } forEach _q;
    { _q deleteAt _x; } forEach _done;
    if (_done isNotEqualTo [] && {!isNil "comspec_atak_native_fnc_perfAcc"}) then { ["marqueurs : envoi", _t0] call comspec_atak_native_fnc_perfAcc; };
    if ((count _q) isEqualTo 0) then {
        [_handle] call CBA_fnc_removePerFrameHandler;
        missionNamespace setVariable ["COMSPEC_MarkerEhPfh", -1, false];
    };
}, 0] call CBA_fnc_addPerFrameHandler, false];
