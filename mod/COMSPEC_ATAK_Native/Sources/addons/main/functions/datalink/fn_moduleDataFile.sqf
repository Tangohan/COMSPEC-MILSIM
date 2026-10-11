/*
    Module « Ajouter un fichier » (Zeus et Eden) : ajoute un fichier aux données d'un objet (posées ou non avant).
    Zeus : module lâché sur l'objet, formulaire. Eden : module synchronisé à l'objet.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _types = [["Texte", "texte"], ["Document", "document"], ["Identifiants (mot de passe d'un autre poste…)", "identifiants"], ["Coordonnées", "coord"], ["Image (chemin dans la mission)", "image"], ["Audio (CfgSounds ou chemin)", "audio"]];
private _cur = [_logic getVariable ["FileName", ""], _logic getVariable ["FileType", "texte"], _logic getVariable ["FileContent", ""]];
private _apply = {
    params ["_vals", "_args"];
    _args params ["_ctx"];
    _vals params ["_name", "_type", "_content"];
    private _targets = +(_ctx get "targets");
    if (_targets isEqualTo [] && {!isNull (_ctx get "near")}) then { _targets pushBack (_ctx get "near"); };
    if (_targets isEqualTo []) exitWith { ["comspec_atak_native_dataNote", ["Fichier : posez le module sur un objet."]] call CBA_fnc_localEvent; };
    {
        [_x, [["files", [[_name, _type, _content, 0]]]], !isNil { _x getVariable "COMSPEC_ATAK_Data" }] call comspec_atak_native_fnc_dataSetup;
    } forEach _targets;
    ["comspec_atak_native_dataNote", [format ["Fichier « %1 » ajouté à %2 objet(s).", _name, count _targets]]] call CBA_fnc_localEvent;
};
if (_ctx get "zeus") then {
    ["Ajouter un fichier (câble ATAK)", [
        ["EDIT", "Nom du fichier", _cur select 0],
        ["COMBO", "Type", _types, _cur select 1],
        ["MEMO", "Contenu", _cur select 2, 6]
    ], _apply, [_ctx], "Sur un objet sans données, des réglages par défaut sont posés (accès libre). Coord : carroyage (045123) ou x, y."] call comspec_atak_native_fnc_zeusForm;
} else {
    [_cur, [_ctx]] call _apply;
};
deleteVehicle _logic;
true
