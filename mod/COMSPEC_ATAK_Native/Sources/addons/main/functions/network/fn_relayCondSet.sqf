/*
    Condition d'activation EN CODE d'un relais ou d'une passerelle de véhicule (fn_relayCond), posée par le serveur seul.
    Le code est rangé dans un registre du serveur que le réseau ne synchronise jamais (localNamespace
    COMSPEC_ATAK_RelayCondSrc, paires [objet, code]) : aucun client ne peut l'écrire, ni par un setVariable diffusé sur
    l'objet, ni par publicVariable. fn_relayCond ne compile que ce registre (boucle « srv » de XEH_postInitServer, 2 s).
    Appelants : module « ATAK · Relais » posé dans Eden (fn_moduleRelay, sur le serveur) et scripts de mission exécutés
    sur le serveur (initServer.sqf, init d'un objet dans Eden sous « if (isServer) »). Jamais en liste blanche
    CfgRemoteExec : un client ne doit pas pouvoir l'appeler.
    Params : [objet, code SQF en texte (_this = l'objet, rend vrai quand le relais doit marcher ; "" retire le code)]
      -> vrai si le registre est mis à jour ; faux hors du serveur, pour un appel distant venu d'un client ou sans objet.
    La variable publique COMSPEC_AtakRelayCond de l'objet dit seulement quelle condition s'applique (préréglage, ou tout
    autre texte = ce code) : la poser aussi, sur le serveur.
      if (isServer) then {
          [_veh, "_this animationSourcePhase ""mast_src"" > 0.9"] call comspec_atak_native_fnc_relayCondSet;
          _veh setVariable ["COMSPEC_AtakRelayCond", "code", true];
      };
*/
params [["_o", objNull, [objNull]], ["_src", "", [""]]];
// Serveur seul ; un remoteExec venu d'un client est refusé même si une mission déclarait la fonction (ou en mode 2).
if (!isServer || {isRemoteExecuted && {!(remoteExecutedOwner in [2, clientOwner])}}) exitWith {
    ["WARNING", "RELAY", "Condition de relais en code refusée : elle se pose sur le serveur seul (comspec_atak_native_fnc_relayCondSet)."] call comspec_atak_native_fnc_log;
    false
};
if (isNull _o) exitWith { false };
// Une entrée par objet ; les objets supprimés sont retirés au passage.
private _reg = (localNamespace getVariable ["COMSPEC_ATAK_RelayCondSrc", []]) select { !isNull (_x select 0) && {(_x select 0) isNotEqualTo _o} };
if ((trim _src) isNotEqualTo "") then { _reg pushBack [_o, trim _src]; };
localNamespace setVariable ["COMSPEC_ATAK_RelayCondSrc", _reg];
true
