/*
    Appel direct à COMSPEC Link qui respecte la coupure centrale (fn_uplinkHold) : liaison désactivée, mode local ou
    joueur sans téléphone → rien ne part, retour vide de même forme que callExtension ([résultat, code, erreur]).
    Remplace « "COMSPECExtension" callExtension [commande, args] » dans les envois directs (connect et ATAK natif).
    Les commandes locales (journal, empreinte, version…) passent toujours (fn_uplinkHold).
*/
params [["_ext", "COMSPECExtension"], ["_cmd", ""], ["_args", []]];
if ([_cmd] call comspec_overwatch_connect_fnc_uplinkHold) exitWith { ["", 0, 0] };
_ext callExtension [_cmd, _args]
