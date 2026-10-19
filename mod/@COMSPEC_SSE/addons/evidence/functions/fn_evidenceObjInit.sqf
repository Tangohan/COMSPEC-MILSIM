/*
    Init d'une pièce de preuve posable (COMSPEC_SSE_Obj_*), appelée par XEH sur toutes les machines.
    [_object] call comspec_sse_fnc_evidenceObjInit
    Seul le serveur marque l'objet (fiche légère, contenu généré au premier examen) : la fiche
    publique part du greffier, les clients reçoivent fiche et menus par comspec_sse_recordChanged.
    Un objet posé par le moteur de compositions (comspec_sse_siteManaged) est laissé au moteur,
    qui le génère avec le cluster de son site.
*/
params [["_obj", objNull, [objNull]]];

if (!isServer || {isNull _obj} || {is3DEN}) exitWith {};

private _type = getText (configOf _obj >> "comspec_sse_type");
if (_type isEqualTo "") then { _type = "OBJECT"; };

// Différé : dans Eden et sous Zeus la position finale arrive après l'init, et la graine en dépend.
[{
    params ["_o", "_type"];
    if (isNull _o) exitWith {};
    if (_o getVariable ["comspec_sse_siteManaged", false]) exitWith {};
    if ((_o getVariable ["comspec_sse_forcedType", ""]) isEqualTo "") then {
        _o setVariable ["comspec_sse_forcedType", _type, true];
    };
    [
        _o,
        _type,
        _o getVariable ["comspec_sse_profile", "RANDOM"],
        _o getVariable ["comspec_sse_complexity", "STANDARD"]
    ] call comspec_sse_fnc_makeSearchable;
    // Pièce posée dans Eden : éditable par les Zeus comme celles posées en jeu.
    { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
}, [_obj, _type], 1] call CBA_fnc_waitAndExecute;
