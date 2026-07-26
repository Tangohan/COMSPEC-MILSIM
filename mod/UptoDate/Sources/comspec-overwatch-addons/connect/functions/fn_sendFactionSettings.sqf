/*
    Author: COMSPEC
    Description:
        Envoie les réglages d’affichage des camps vers Athena (inspiré Athena Remastered
        sendSettings.sqf — ATH_showEast / ATH_showGuer / ATH_showCiv).
        Source : paramètres mission (si présents) sinon réglages CBA Overwatch.
*/
if (!hasInterface) exitWith {};

private _showEast = 1;
private _showGuer = 1;
private _showCiv = 1;

// Params mission (lobby serveur) — même noms qu’Athena Remastered pour compat mission makers
if (isClass (missionConfigFile >> "Params" >> "ATH_showEast")) then {
    _showEast = "ATH_showEast" call BIS_fnc_getParamValue;
};
if (isClass (missionConfigFile >> "Params" >> "ATH_showGuer")) then {
    _showGuer = "ATH_showGuer" call BIS_fnc_getParamValue;
};
if (isClass (missionConfigFile >> "Params" >> "ATH_showCiv")) then {
    _showCiv = "ATH_showCiv" call BIS_fnc_getParamValue;
};

// Sinon CBA (défauts Overwatch)
if (!isClass (missionConfigFile >> "Params" >> "ATH_showEast")) then {
    _showEast = if (missionNamespace getVariable ["comspec_overwatch_show_opfor", true]) then { 1 } else { 0 };
};
if (!isClass (missionConfigFile >> "Params" >> "ATH_showGuer")) then {
    _showGuer = if (missionNamespace getVariable ["comspec_overwatch_show_independent", true]) then { 1 } else { 0 };
};
if (!isClass (missionConfigFile >> "Params" >> "ATH_showCiv")) then {
    _showCiv = if (missionNamespace getVariable ["comspec_overwatch_show_civilian", true]) then { 1 } else { 0 };
};

missionNamespace setVariable ["COMSPEC_ShowEast", _showEast > 0, false];
missionNamespace setVariable ["COMSPEC_ShowGuer", _showGuer > 0, false];
missionNamespace setVariable ["COMSPEC_ShowCiv", _showCiv > 0, false];

// Via messagerie structurée (transport historique) — le portail applique sans publier au journal radio.
private _body = format [
    "REGLAGES AFFICHAGE|adversaire=%1|independants=%2|civils=%3",
    if (_showEast > 0) then { 1 } else { 0 },
    if (_showGuer > 0) then { 1 } else { 0 },
    if (_showCiv > 0) then { 1 } else { 0 }
];

// Évite le spam à chaque reconnexion / relance des boucles si rien n’a changé.
private _last = missionNamespace getVariable ["COMSPEC_LastFactionSettingsBody", ""];
if (_body isEqualTo _last) exitWith {};
missionNamespace setVariable ["COMSPEC_LastFactionSettingsBody", _body, false];

[player, "CHAT", _body, "", "INFANTRY", 0.5] call comspec_overwatch_connect_fnc_sendIntel;

[format [
    "[Athena] Affichage carte synchronisé (adversaire=%1, indépendants=%2, civils=%3) — hors journal radio.",
    _showEast > 0, _showGuer > 0, _showCiv > 0
]] call comspec_overwatch_connect_fnc_appendLinkLog;
