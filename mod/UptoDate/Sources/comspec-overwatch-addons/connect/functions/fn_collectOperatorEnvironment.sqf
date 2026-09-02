/*
    Contexte mission / serveur observé (pas une identité joueur).
    Retour: HashMap
*/
private _out = createHashMap;
_out set ["server_name", ""];
_out set ["mission_name", ""];
_out set ["mission_id", ""];
_out set ["briefing_name", ""];
_out set ["world_name", ""];
_out set ["is_multiplayer", isMultiplayer];

private _srv = serverName;
if (_srv isEqualType "") then { _out set ["server_name", trim _srv]; };

private _mis = missionName;
if (_mis isEqualType "") then {
    _out set ["mission_name", _mis];
    _out set ["mission_id", _mis];
};

private _brief = briefingName;
if (!(_brief isEqualType "")) then { _brief = ""; };
if (_brief isEqualTo "") then {
    private _onLoad = getMissionConfigValue ["onLoadName", ""];
    if (_onLoad isEqualType "") then { _brief = _onLoad; };
};
if (_brief isEqualType "") then { _out set ["briefing_name", trim _brief]; };

private _world = worldName;
if (_world isEqualType "") then { _out set ["world_name", _world]; };

_out
