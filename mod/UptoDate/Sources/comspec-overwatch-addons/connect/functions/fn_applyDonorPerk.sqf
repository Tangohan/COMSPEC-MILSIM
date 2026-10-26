/*
    Récompense « Donateur Athena » (portail : Paiements et dons, paliers soutien / allie / pilier).

    Le palier arrive avec l'expérience communauté (GET /api/atak/experience, clé net_donor_tier) : la DLL actuelle
    transmet déjà toutes les clés net_*, aucune nouvelle version de COMSPEC Link n'est nécessaire.

    Effets, seulement pour le joueur local :
      - variable publique comspec_donor_tier sur l'unité (lue par l'overlay d'identité ATAK et par les autres joueurs) ;
      - écusson d'épaule COMSPEC_Donor_<palier> si le réglage « Écusson donateur » est coché, sans jamais remplacer
        un écusson posé par la mission ou par le joueur (seul l'écusson donateur précédent est remplacé ou retiré).
    BIS_fnc_setUnitInsignia est à effet global : les autres joueurs voient l'écusson (ils ont le mod, il est requis).

    Params : [_unit (défaut player)]
*/
params [["_unit", player, [objNull]]];

if (!hasInterface || {isNull _unit} || {!local _unit}) exitWith { false };

private _tiers = ["soutien", "allie", "pilier"];
private _map = missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap];
private _tier = "";
if (_map isEqualType createHashMap) then {
    _tier = toLower trim (_map getOrDefault ["net_donor_tier", ""]);
};
if !(_tier in _tiers) then { _tier = ""; };

missionNamespace setVariable ["COMSPEC_DonorTier", _tier, false];
if ((_unit getVariable ["comspec_donor_tier", ""]) isNotEqualTo _tier) then {
    _unit setVariable ["comspec_donor_tier", _tier, true];
};

private _want = missionNamespace getVariable ["comspec_overwatch_donor_insignia", true];
if !(_want isEqualType true) then { _want = true; };

private _current = _unit call BIS_fnc_getUnitInsignia;
if !(_current isEqualType "") then { _current = ""; };
private _ours = ((toLower _current) find "comspec_donor_") == 0;

if (_tier isEqualTo "" || {!_want}) exitWith {
    // Plus donateur ou réglage décoché : on retire uniquement notre écusson.
    if (_ours) then { [_unit, ""] call BIS_fnc_setUnitInsignia; };
    false
};

private _class = "COMSPEC_Donor_" + _tier;
if (_current isEqualTo "" || {_ours && {_current isNotEqualTo _class}}) then {
    [_unit, _class] call BIS_fnc_setUnitInsignia;
};

// Réappliquer après une réapparition (une seule fois par session).
if (isNil {missionNamespace getVariable "COMSPEC_DonorPerkRespawnEH"}) then {
    missionNamespace setVariable ["COMSPEC_DonorPerkRespawnEH", player addEventHandler ["Respawn", {
        params ["_new"];
        [_new] spawn {
            params ["_u"];
            sleep 1;
            [_u] call comspec_overwatch_connect_fnc_applyDonorPerk;
        };
    }], false];
};

true
