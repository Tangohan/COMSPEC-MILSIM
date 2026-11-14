/*
    SERVEUR. Garde des postes protégés.
      []        passe toutes les 5 s (XEH_postInit) : un joueur sur un poste dont le code est configuré, qui n'a pas été déverrouillé
                dans le délai (réglage « Délai pour saisir le code », 45 s), est écarté ; un joueur déverrouillé qui change de poste
                perd son marquage et doit refaire le code.
      [unité]   écarte tout de suite cette unité (blocage après trop d'essais).
    Écarter, c'est : lui dire de quitter la mission (comspec_lobby_pinResult : le client affiche l'écran de bilan), puis, 8 s plus
    tard si elle est toujours là, l'expulser avec #kick (réglage « Expulser en cas d'échec ») ; si le serveur ne peut pas lancer
    #kick, l'unité est gelée et cachée (jamais laissée à un joueur qui n'a pas le code).
    Hôte (propriétaire 2) : jamais touché.
*/
params [["_only", objNull, [objNull]]];
if (!isServer || {!(missionNamespace getVariable ["comspec_lobby_enabled", true])}) exitWith {};

private _expel = {
    params ["_u"];
    if (isNull _u || {(owner _u) isEqualTo 2}) exitWith {};
    ["comspec_lobby_pinResult", [false, 0], _u] call CBA_fnc_targetEvent;
    [{
        params ["_u"];
        if (isNull _u || {!isPlayer _u}) exitWith {};
        if ((_u getVariable ["COMSPEC_LOBBY_Ok", ""]) isNotEqualTo "") exitWith {};
        if (missionNamespace getVariable ["comspec_lobby_kick", true]) then {
            if (serverCommandAvailable "#kick") then {
                serverCommand format ["#kick %1", name _u];
                (format ["[COMSPEC LOBBY] %1 expulsé (code du poste absent ou refusé)", name _u]) call { diag_log _this };
            } else {
                _u enableSimulationGlobal false;
                _u hideObjectGlobal true;
                (format ["[COMSPEC LOBBY] %1 gelé : #kick indisponible sur ce serveur", name _u]) call { diag_log _this };
            };
        };
    }, [_u], 8] call CBA_fnc_waitAndExecute;
};

if (!isNull _only) exitWith { [_only] call _expel; };

private _table = missionNamespace getVariable ["comspec_lobby_pins", createHashMap];
if (count _table isEqualTo 0) exitWith {};
private _grace = (missionNamespace getVariable ["comspec_lobby_grace", 45]) max 10;
{
    private _u = _x;
    if (alive _u && {(owner _u) isNotEqualTo 2}) then {
        private _tag = toUpper (([roleDescription _u] call comspec_lobby_fnc_tagParse) select 0);
        private _needs = _tag isNotEqualTo "" && {_tag in _table};
        private _ok = _u getVariable ["COMSPEC_LOBBY_Ok", ""];
        if (!_needs) then {
            _u setVariable ["COMSPEC_LOBBY_Seen", nil];
        } else {
            if (_ok isEqualTo _tag) then {
                _u setVariable ["COMSPEC_LOBBY_Seen", nil];
            } else {
                // Changement de poste : l'ancien déverrouillage ne vaut plus.
                if (_ok isNotEqualTo "") then { _u setVariable ["COMSPEC_LOBBY_Ok", "", true]; };
                private _seen = _u getVariable ["COMSPEC_LOBBY_Seen", -1];
                if (_seen < 0) then {
                    _u setVariable ["COMSPEC_LOBBY_Seen", serverTime];
                } else {
                    if (serverTime - _seen > _grace) then {
                        _u setVariable ["COMSPEC_LOBBY_Seen", nil];
                        [_u] call _expel;
                    };
                };
            };
        };
    };
} forEach allPlayers;
