/*
    Surveille l'état médical local et déclenche les alertes KO / FC=0.
    Appelé depuis le PFH position et depuis les événements ACE.
    Au rétablissement (conscient / stable), clôture l’alerte active côté Athena.
*/
params [["_unit", player, [objNull]]];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (isNull _unit || {!local _unit}) exitWith {};
// Déconnexion / sortie mission : ne pas publier de fausse alerte critique.
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
if (isNull findDisplay 46) exitWith {};
if (isMultiplayer && {getClientStateNumber >= 11}) exitWith {};
if (!alive _unit) exitWith {};
// Mort puis REAPP immédiat : ne pas publier KIA / KO fantôme
if (missionNamespace getVariable ["COMSPEC_DeathThenRespawn", false]) exitWith {};
// Spawn / JIP / grâce respawn : attendre stabilisation
if !([] call comspec_overwatch_connect_fnc_isPlayerSpawnStable) exitWith {};

private _state = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
private _parts = _state splitString "|";
private _health = if (count _parts >= 1) then { _parts select 0 } else { "stable" };
private _hr = if (count _parts >= 4) then { parseNumber (_parts select 3) } else { 80 };
private _cardiac = (count _parts >= 5) && {(_parts select 4) == "1"};

if (_cardiac || {_health == "cardiac_arrest"}) then {
    [_unit, "cardiac_arrest"] call comspec_overwatch_connect_fnc_reportMedicalAlert;
} else {
    if (_health == "unconscious") then {
        [_unit, "unconscious"] call comspec_overwatch_connect_fnc_reportMedicalAlert;
    } else {
        // Rétabli (stable / blessé debout) : clôturer l’alerte KO restante.
        // Ne pas se contenter de resetter le verrou — sinon la bannière ATAK reste CRITIQUE.
        if (_health in ["stable", "wounded"]) then {
            private _last = missionNamespace getVariable ["COMSPEC_lastMedicalAlertKind", ""];
            private _own = [] call comspec_overwatch_connect_fnc_hasOwnActiveMedicalAlert;
            if (!(_last isEqualTo "") || {count _own > 0}) then {
                [true] call comspec_overwatch_connect_fnc_selfCancelMedicalAlert;
            };
        };
    };
};
