/*
    Téléphone COMSPEC porté : le modèle et la couleur viennent de l'objet, pas d'un réglage.
    Plusieurs téléphones : le premier dans les poches (tenue, gilet, sac).
    Sans téléphone COMSPEC : le modèle encore enregistré dans le profil (les autres terminaux acceptés).
    Retour : [modèle, couleur, nom affiché] ; nom vide = aucun téléphone COMSPEC porté.
*/
params [["_unit", player]];
private _model = "";
private _tint = "";
private _name = "";
{
    if (_x isKindOf ["COMSPEC_ATAK_Phone", configFile >> "CfgWeapons"]) exitWith {
        private _cfg = configFile >> "CfgWeapons" >> _x;
        _model = getText (_cfg >> "comspec_phoneModel");
        _tint = getText (_cfg >> "comspec_phoneTint");
        _name = getText (_cfg >> "displayName");
    };
} forEach ((assignedItems _unit) + (items _unit));
if (_model isEqualTo "") then {
    _model = profileNamespace getVariable ["COMSPEC_ATAK_PhoneModel", "s7"];
    _tint = profileNamespace getVariable ["COMSPEC_ATAK_ShellTint", "coyote"];
};
if (_model in ["plate", "flip", "ribbed", ""]) then { _model = "s7"; };
if !(_tint in ["coyote", "od", "black"]) then { _tint = "coyote"; };
[_model, _tint, _name]
