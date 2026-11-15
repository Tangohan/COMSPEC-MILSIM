/*
    SERVEUR : file des initialisations par personne (InitPost de CAManBase, identity/config.cpp).
      [unité, "kit"]   papiers du kit délivrés à son nom 1 s après l'apparition (fn_idRegistry « kit »)
      [unité, "lore"]  lore de session rattaché 1,5 s après l'apparition (fn_loreServer « attach »)
    Avant : chaque personne programmait ses deux tâches par CBA_fnc_waitAndExecute. Au départ d'une mission, toutes les
    unités apparaissent dans la même image : leurs 2 x N tâches tombaient donc toutes dans la même image, 1 s puis
    1,5 s plus tard (200 IA = 200 puis 200 appels d'un bloc). Ici : mêmes délais, même ordre, mais 8 unités au plus par
    image ; le surplus passe aux images suivantes (200 IA = 25 images, moins d'une demi-seconde). La boucle s'arrête
    quand les deux files sont vides.
*/
params [["_u", objNull, [objNull]], ["_kind", "kit", [""]]];
if (!isServer || {isNull _u}) exitWith {};
private _q = missionNamespace getVariable "comspec_sse_idInitQ";
if (isNil "_q") then {
    _q = createHashMapFromArray [["kit", []], ["lore", []]];
    missionNamespace setVariable ["comspec_sse_idInitQ", _q];
};
if !(_kind in _q) exitWith {};
(_q get _kind) pushBack [CBA_missionTime + ([1, 1.5] select (_kind isEqualTo "lore")), _u];
if (missionNamespace getVariable ["comspec_sse_idInitQOn", false]) exitWith {};
missionNamespace setVariable ["comspec_sse_idInitQOn", true];
[{
    params ["_q", "_pfh"];
    private _left = 8;
    {
        private _kind = _x;
        private _list = _q get _kind;
        // Files dans l'ordre d'arrivée, donc d'échéance : la tête est toujours la plus ancienne.
        while { _left > 0 && {_list isNotEqualTo []} && {((_list select 0) select 0) <= CBA_missionTime} } do {
            private _u = (_list deleteAt 0) select 1;
            _left = _left - 1;
            if (!isNull _u) then {
                if (_kind isEqualTo "kit") then { ["kit", [_u]] call comspec_sse_fnc_idRegistry; } else { ["attach", [_u]] call comspec_sse_fnc_loreServer; };
            };
        };
    } forEach ["kit", "lore"];
    if ((_q get "kit") isEqualTo [] && {(_q get "lore") isEqualTo []}) then {
        missionNamespace setVariable ["comspec_sse_idInitQOn", false];
        [_pfh] call CBA_fnc_removePerFrameHandler;
    };
}, 0, _q] call CBA_fnc_addPerFrameHandler;
