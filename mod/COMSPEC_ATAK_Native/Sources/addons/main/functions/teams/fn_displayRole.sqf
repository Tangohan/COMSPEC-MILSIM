/*
    Rôle affiché d'un opérateur : seule source pour tous les affichages (profil, Athena, fiche, étiquettes, BFT,
    effectifs) et pour la remontée Athena (comspec_overwatch_connect_fnc_getUnitRole y renvoie).
    Joueur local, dans l'ordre :
      1. rôle choisi dans l'app Profil (« Rôle affiché » : profil COMSPEC_ATAK_RoleMode "custom" ou "slot") ;
      2. fonction de la communauté (DLL : comspec_profile_function, sinon l'ancien comspec_profile_role) ;
      3. rôle du slot de la mission (description du slot) ;
      4. rien (plus de « Operator » par défaut).
    Le réglage serveur comspec_atak_native_role_force_community impose l'étape 2 quand la fonction existe.
    Autres joueurs : rôle publié par leur téléphone (variable COMSPEC_Role), sinon rôle du slot, sinon rien.
    Le joueur local publie son rôle affiché (COMSPEC_Role, public) seulement quand il change.
    Params : [unité, "role" | "sources"]
      "sources" (joueur local) : [mode, personnalisé, fonction communauté, rôle du slot, imposé]
*/
params [["_u", objNull, [objNull]], ["_what", "role"]];
if (isNull _u) exitWith { ["", ["athena", "", "", "", false]] select (_what isEqualTo "sources") };
private _slot = roleDescription _u;
if !(_slot isEqualType "") then { _slot = ""; };
_slot = trim ((_slot splitString "@") param [0, ""]);
_slot = trim ((_slot splitString ":") joinString " - ");
// Écarter les classnames type B_Soldier_F laissés en description.
private _low = toLower _slot;
if ((_slot find " ") < 0 && {(_slot find "_") >= 0} && {(_low find "soldier") >= 0 || {(_low select [(count _low) - 2, 2]) isEqualTo "_f"}}) then { _slot = ""; };
if (_u isNotEqualTo player) exitWith {
    private _r = _u getVariable ["COMSPEC_Role", ""];
    if !(_r isEqualType "") then { _r = ""; };
    _r = trim _r;
    [_r, _slot] select (_r isEqualTo "")
};
private _str = { params ["_k"]; private _r = missionNamespace getVariable [_k, ""]; if (_r isEqualType "") then { trim _r } else { "" } };
private _fn = ["comspec_profile_function"] call _str;
if (_fn isEqualTo "") then { _fn = ["comspec_profile_role"] call _str; };
private _forced = missionNamespace getVariable ["comspec_atak_native_role_force_community", false];
private _mode = profileNamespace getVariable ["COMSPEC_ATAK_RoleMode", "athena"];
if !(_mode in ["athena", "slot", "custom"]) then { _mode = "athena"; };
private _custom = profileNamespace getVariable ["COMSPEC_ATAK_RoleCustom", ""];
if !(_custom isEqualType "") then { _custom = ""; };
_custom = trim _custom;
if (_what isEqualTo "sources") exitWith { [_mode, _custom, _fn, _slot, _forced && {_fn isNotEqualTo ""}] };
private _r = switch (true) do {
    case (_forced && {_fn isNotEqualTo ""}): { _fn };
    case (_mode isEqualTo "custom" && {_custom isNotEqualTo ""}): { _custom };
    case (_mode isEqualTo "slot" && {_slot isNotEqualTo ""}): { _slot };
    case (_fn isNotEqualTo ""): { _fn };
    default { _slot };
};
if ((_u getVariable ["COMSPEC_Role", ""]) isNotEqualTo _r) then { _u setVariable ["COMSPEC_Role", _r, true]; };
_r
