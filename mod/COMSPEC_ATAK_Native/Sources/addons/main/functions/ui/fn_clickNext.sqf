/*
    Clic de bouton exécuté à l'image suivante (comme les boutons de fn_formRender).
    Un bouton dont l'action redessine la page (ou ferme le téléphone) était supprimé pendant son propre gestionnaire
    ButtonClick : le moteur gardait la souris « prise » par un contrôle disparu et plus aucun clic ne passait jusqu'à ce
    que le téléphone soit rouvert (retour de TTRD, 25/10 : app Drone, croix, PLEIN ÉCRAN et boutons de la barre).
    Params : [contrôle, code (code ou texte), arguments passés au code (facultatif, le contrôle par défaut)]
*/
params ["_b", "_code", ["_args", nil]];
if (isNull _b) exitWith {};
if (_code isEqualType "") then { _code = compile _code; };
_b setVariable ["comspec_click", _code];
if (!isNil "_args") then { _b setVariable ["comspec_clickArgs", _args]; };
_b ctrlAddEventHandler ["ButtonClick", {
    params ["_c"];
    [{ params ["_c", "_code", "_a"]; _a call _code; }, [_c, _c getVariable ["comspec_click", {}], _c getVariable ["comspec_clickArgs", [_c]]]] call CBA_fnc_execNextFrame;
}];
