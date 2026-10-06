/*
    Diffuse l'activation SSE d'entités aux autres postes, pour que les menus
    ACE « objet » y soient installés aussi : makeSearchable / setData ne lèvent
    l'événement comspec_sse_entityEnabled qu'en local.

    Les modules ne s'exécutant plus que sur le poste du Zeus (ou le serveur),
    cette diffusion remplace l'ancienne exécution sur chaque client.

    [_entities, _delay] call comspec_sse_fnc_zeusBroadcastEnabled
      _entities : objet ou tableau d'objets
      _delay    : secondes avant diffusion (laisser finir la génération étalée)
    Limite connue : un joueur arrivant en cours de partie (JIP) ne reçoit pas
    l'événement ; il retrouve les menus dès qu'un poste réactive l'entité.
*/
params [
    ["_entities", [], [[], objNull]],
    ["_delay", 0, [0]]
];

if (_entities isEqualType objNull) then { _entities = [_entities]; };
_entities = _entities select { !isNull _x && {!(_x isKindOf "CAManBase")} };
if (_entities isEqualTo []) exitWith { false };
if (isNil "CBA_fnc_remoteEvent") exitWith { false };

private _send = {
    params ["_list"];
    {
        if (!isNull _x && {_x getVariable ["comspec_sse_enabled", false]}) then {
            ["comspec_sse_entityEnabled", [_x]] call CBA_fnc_remoteEvent;
        };
    } forEach _list;
};

if (_delay > 0 && {!isNil "CBA_fnc_waitAndExecute"}) then {
    [_send, [_entities], _delay] call CBA_fnc_waitAndExecute;
} else {
    [_entities] call _send;
};
true
