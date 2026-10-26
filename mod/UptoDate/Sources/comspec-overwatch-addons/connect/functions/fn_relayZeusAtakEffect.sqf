/*
    Relais serveur → client cible pour les effets Zeus ATAK (MP fiable).
    Params: [_target, _action, _durationSec]

    Le demandeur n'est jamais celui qu'annonce la demande : il est retrouvé par remoteExecutedOwner, le seul
    identifiant que le client ne choisit pas. Même modèle que, dans le mod natif,
    functions/network/fn_netConfig.sqf « server » (l. 86-95) et functions/vehicle/fn_vehicleGateway.sqf « srv »
    (l. 89-99). Un appel non distant ne peut venir que du code du serveur : il est accepté tel quel.

    Deux droits, parce qu'il y a deux usages réels :
      - effets Zeus (power_off, screen_break, device_destroy, crash, jam, compromise, clear_compromise, repair,
        clear) : curateur assigné, administrateur connecté (#login) ou hôte. Appelés depuis le poste d'un Zeus :
        fn_registerZenAtakPlayerActions (menu ZEN, modules ACE Zeus), fn_zeusShowPlayerAtak (panneau joueur),
        fn_zeusAttributesOverwatch (attributs ZEN) ;
      - « capture » : AUSSI ouvert à tout joueur, car ce n'est pas une action de Zeus mais une action ACE de terrain
        (« Saisir l'ATAK », fn_captureEnemyAtak, posée en fn_initACE.sqf:438-465 sur CAManBase). Les conditions de
        cette action sont rejouées ici au lieu d'être crues : joueur vivant, cible joueur, cible ≠ demandeur, à
        portée, et cible morte, inconsciente ou menottée. Marge de distance côté serveur comme pour
        fn_vehicleGateway (« can » à 6 m chez le client, 10 m au serveur) : la position d'une unité distante y est
        connue avec un temps de retard.
*/
if (!isServer) exitWith {};

params [
    ["_target", objNull, [objNull]],
    ["_action", "", [""]],
    ["_durationSec", 30, [0]]
];

if (isNull _target || {!isPlayer _target}) exitWith {
    diag_log format ["[COMSPEC] relayZeusAtakEffect: cible invalide (%1)", _target];
};

_action = toLower _action;

// Demandeur : remoteExecutedOwner, jamais un argument.
private _owner = remoteExecutedOwner;
private _remote = isMultiplayer && {isRemoteExecuted} && {_owner isNotEqualTo 2};
private _req = objNull;
if (_remote) then {
    if (hasInterface && {_owner isEqualTo clientOwner}) then {
        _req = player;
    } else {
        _req = (allPlayers select { (owner _x) isEqualTo _owner }) param [0, objNull];
    };
};
if (_remote && {isNull _req}) exitWith {
    diag_log format ["[COMSPEC] relayZeusAtakEffect: « %1 » refusé au poste %2 (demandeur introuvable)", _action, _owner];
};

private _ok = true;
if (_remote) then {
    // Zeus, administrateur connecté ou hôte.
    private _auth = !isNull (getAssignedCuratorLogic _req) || {(admin _owner) > 0} || {_owner isEqualTo clientOwner};
    // Action ACE « Saisir l'ATAK » : conditions de fn_initACE.sqf:447-465 rejouées côté serveur.
    private _grab = _action isEqualTo "capture"
        && {alive _req}
        && {_target isNotEqualTo _req}
        && {(_req distance _target) < 10}
        && {
            (!alive _target)
            || {(lifeState _target) isEqualTo "INCAPACITATED"}
            || {captive _target}
            || {_target getVariable ["ACE_isUnconscious", false]}
        };
    _ok = _auth || _grab;
};
if (!_ok) exitWith {
    diag_log format ["[COMSPEC] relayZeusAtakEffect: « %1 » sur %2 refusé à %3 (poste %4 : ni Zeus, ni admin, ni saisie à portée)",
        _action, name _target, name _req, _owner];
    // Le joueur doit comprendre le refus : message dans le téléphone (événement du mod natif, sans effet si absent),
    // jamais par-dessus l'écran du jeu.
    ["comspec_atak_native_note", ["Refusé par le serveur : les effets Zeus sur les ATAK sont réservés à Zeus et aux admins."], _req] call CBA_fnc_targetEvent;
};

// En solo, « owner » renvoie 0 pour toutes les unités : le contrôle de propriétaire
// rejetait donc tous les effets Zeus hors MP. On route sur la localité, qui est vraie
// dans les deux cas, et l’identifiant de propriétaire ne sert plus qu’au journal.
if (local _target) then {
    // Cible locale (solo, ou hôte non dédié qui joue l’unité) : appel direct, mais une image après, hors de la pile
    // du remoteExec du demandeur. Un appel imbriqué garderait son contexte d'exécution distante (isRemoteExecuted et
    // remoteExecutedOwner du client), et le contrôle « l'appel vient-il du serveur ? » de fn_applyZeusAtakEffect
    // refuserait alors l'effet pourtant légitime.
    [{ _this call comspec_overwatch_connect_fnc_applyZeusAtakEffect; }, [_action, _durationSec], 0] call CBA_fnc_waitAndExecute;
} else {
    private _tOwner = owner _target;
    if (_tOwner <= 0) then {
        diag_log format ["[COMSPEC] relayZeusAtakEffect: owner invalide pour %1", name _target];
    } else {
        [_action, _durationSec] remoteExecCall ["comspec_overwatch_connect_fnc_applyZeusAtakEffect", _target];
    };
};

diag_log format [
    "[COMSPEC Overwatch][INFO][Zeus] Relais serveur → %1 : %2 (%3 s)%4",
    name _target,
    _action,
    round _durationSec,
    ["", format [", demandé par %1", name _req]] select _remote
];
