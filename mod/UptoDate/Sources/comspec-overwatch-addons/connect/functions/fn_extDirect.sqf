/*
    Appel direct à COMSPEC Link qui respecte la coupure centrale (fn_uplinkHold) : liaison désactivée, mode local ou
    joueur sans téléphone → rien ne part, retour vide de même forme que callExtension ([résultat, code, erreur]).
    Remplace « "COMSPECExtension" callExtension [commande, args] » dans les envois directs (connect et ATAK natif).
    Les commandes locales (journal, empreinte, version…) passent toujours (fn_uplinkHold).
    Seules les commandes rapides (fn_extFast : mémoire, cache, mise en file) restent en direct. Une commande lente
    (HTTP, disque) passe par fn_extAwait dans un contexte suspendable ; hors contexte suspendable elle part en tâche
    de fond (spawn) et l'appel rend tout de suite ["", 0, 0] : jamais d'attente sur le fil du jeu.
*/
params [["_ext", "COMSPECExtension"], ["_cmd", ""], ["_args", []]];
if ([_cmd] call comspec_overwatch_connect_fnc_uplinkHold) exitWith { ["", 0, 0] };
if ([_cmd] call comspec_overwatch_connect_fnc_extFast) exitWith { _ext callExtension [_cmd, _args] };
if (canSuspend) exitWith { [_ext, _cmd, _args] call comspec_overwatch_connect_fnc_extAwait };
[_ext, _cmd, _args] spawn comspec_overwatch_connect_fnc_extAwait;
["", 0, 0]
