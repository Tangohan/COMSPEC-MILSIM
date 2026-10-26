/*
    Téléphones ramassés par l'IA ennemie (fn_captureServer) : ordres du serveur exécutés sur la machine où l'objet est
    local (IA, corps ou joueur porteur du téléphone). Le serveur les envoie par remoteExecCall vers l'objet :
      ["strip", objet, classe]          retire le téléphone (emplacement ou inventaire)
      ["give", IA, classe]              donne le téléphone à l'IA ; sans place, il est posé au sol à côté d'elle (le serveur
                                        le constate lui-même et y reporte la capture)
      ["move", IA, position]            l'IA va chercher le téléphone
      ["hunt", chef, [cibles, traque]]  le groupe apprend les cibles ([unité, position]...) et, traque permise, part les
                                        chercher (point de passage « chercher et détruire »)
    Lot mpauth3 (SEC-023) : ces ordres passaient par l'événement CBA comspec_atak_native_capLocal (branche « local » de
    fn_captureServer), que tout client pouvait lever vers n'importe quelle machine : retirer ou donner un objet à un
    joueur ou à une IA locale à la machine visée, déplacer une IA, lui faire traquer qui il voulait. La fonction
    n'accepte plus qu'un ordre du serveur (CfgRemoteExec : allowedTargets = 0, l'objet peut être local au serveur ;
    jip = 0) ; appelée sur place (serveur, script de mission), elle agit comme avant.
*/
params [["_mode", ""], ["_o", objNull], ["_v", ""]];
if (isRemoteExecutedJIP || {isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2}}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][CAPTURE] Ordre « %1 » refusé : il ne vient pas du serveur ou il est rejoué depuis la file JIP (poste %2, file JIP : %3)", _mode, remoteExecutedOwner, isRemoteExecutedJIP];
};
if (!(_o isEqualType objNull) || {isNull _o} || {!local _o}) exitWith {};
switch (_mode) do {
    case "strip": {
        if (_v in (assignedItems _o)) then { _o unlinkItem _v; } else { _o removeItem _v; };
    };
    case "give": {
        // Plus de place (inventaire changé depuis la vérification du serveur) : le téléphone est posé au sol à côté de
        // l'IA plutôt que perdu. Le serveur le constate lui-même (« giveCheck ») et y reporte la capture (« giveFail »)
        // pour qu'il reste ramassable et que l'IA sans téléphone ne l'exploite plus. Cette machine n'envoie rien au
        // serveur.
        if (_o canAdd _v) then { _o addItem _v; } else {
            private _gh = createVehicle ["GroundWeaponHolder", getPosATL _o, [], 0.5, "CAN_COLLIDE"];
            _gh addItemCargoGlobal [_v, 1];
        };
        _o playActionNow "PutDown";
        _o doFollow (leader group _o);
    };
    case "move": { _o doMove _v; };
    case "hunt": {
        _v params [["_targets", []], ["_hunt", true]];
        private _g = group _o;
        { _g reveal [_x select 0, 2.5]; } forEach _targets;
        if (!_hunt || {_targets isEqualTo []} || {_g getVariable ["COMSPEC_ATAK_NoHunt", false]}) exitWith {};
        private _best = _targets apply { [(_x select 1) distance2D (leader _g), _x select 1] };
        _best sort true;
        private _p = (_best select 0) select 1;
        if (((_g getVariable ["COMSPEC_ATAK_HuntPos", [-1e5, -1e5, 0]]) distance2D _p) < 80) exitWith {};
        _g setVariable ["COMSPEC_ATAK_HuntPos", _p];
        [_g] call CBA_fnc_clearWaypoints;
        private _wp = _g addWaypoint [_p, 25];
        _wp setWaypointType "SAD";
        _wp setWaypointBehaviour "AWARE";
        _wp setWaypointCombatMode "RED";
        _wp setWaypointSpeed "FULL";
        _g setCurrentWaypoint _wp;
    };
};
