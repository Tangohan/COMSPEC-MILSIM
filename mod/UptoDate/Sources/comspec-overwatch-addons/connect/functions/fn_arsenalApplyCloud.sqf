/*
    Enfile une tenue de la communauté (par identifiant) sur le mannequin de l'arsenal. À appeler depuis un spawn.
    Params : [identifiant, empreinte du site (facultative, recharge la tenue si elle a changé sur le site)].
*/
params [["_id", "", [""]], ["_siteSha", "", [""]]];

if (!hasInterface) exitWith { false };
if (_id isEqualTo "") exitWith { false };

private _ext = [_id, _siteSha] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
if (_ext isEqualTo []) exitWith {
    private _err = missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadoutError", ""];
    private _msg = if (_err isEqualTo "too_large") then {
        "Cette tenue n'a pas pu être chargée en entier. Réessayez, ou ouvrez-la sur le poste (Mes tenues)."
    } else {
        "Cette tenue est introuvable ou inaccessible."
    };
    [_msg, "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _names = missionNamespace getVariable ["COMSPEC_ArsenalCloudNames", createHashMap];
[_ext, _names getOrDefault [_id, ""]] call comspec_overwatch_connect_fnc_arsenalApplyLoadout
