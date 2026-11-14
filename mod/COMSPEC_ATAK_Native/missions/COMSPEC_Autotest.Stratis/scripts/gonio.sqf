// Groupe « gonio » : recoupement des relèvements, diffusion des zones à l'entrée, alerte au TOC, liaison faible.
// Logique seule : aucune IA, aucun clic ; les états du joueur touchés (relèvements, file d'envoi, liaison) sont rendus.
if !("gonio" call AT_fnc_groupe) exitWith {};

["gonio_recoupement", {
    if (["comspec_atak_native_fnc_ewAction"] call AT_fnc_nil) exitWith {};
    private _save = [missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []], missionNamespace getVariable ["COMSPEC_ATAK_EwFixes", []]];
    // Émetteur à 3 000 m / 2 000 m de deux relevés pris de points différents : le point doit retomber à moins de 150 m.
    private _t = [4000, 4000, 0];
    private _a = [3000, 3500, 0];
    private _b = [4800, 3300, 0];
    private _mk = { params ["_from", "_code", ["_kind", "TÉLÉPHONE"]]; [_from, _from getDir _t, 0, 5000, _kind, time, 3, "00:00", _code] };
    missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", [[_a, 1234] call _mk, [_b, 1234] call _mk]];
    private _fx = ["fixes"] call comspec_atak_native_fnc_ewAction;
    [(count _fx) isEqualTo 1, format ["un point attendu, %1 obtenu(s)", count _fx]] call AT_fnc_check;
    if ((count _fx) > 0) then {
        private _err = ((_fx select 0) select 0) distance2D _t;
        (format ["point estimé à %1 m de l'émetteur, cercle de %2 m, %3 recoupement(s)", round _err, round ((_fx select 0) select 1), (_fx select 0) select 4]) call AT_fnc_info;
        [_err < 150, format ["point trop loin : %1 m", round _err]] call AT_fnc_check;
        [((_fx select 0) select 1) >= 50, "cercle d'erreur sous 50 m"] call AT_fnc_check;
    };
    // Deux émetteurs différents (codes différents) : jamais de point fantôme.
    missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", [[_a, 1111] call _mk, [_b, 2222] call _mk]];
    [(count (["fixes"] call comspec_atak_native_fnc_ewAction)) isEqualTo 0, "deux émetteurs différents ne doivent pas se croiser"] call AT_fnc_check;
    // Code inconnu (0) : croise avec tout.
    missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", [[_a, 0] call _mk, [_b, 2222] call _mk]];
    [(count (["fixes"] call comspec_atak_native_fnc_ewAction)) isEqualTo 1, "un code inconnu doit se croiser"] call AT_fnc_check;
    // Deux relevés du même endroit, ou presque parallèles : rien.
    missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", [[_a, 5] call _mk, [_a vectorAdd [50, 0, 0], 5] call _mk]];
    [(count (["fixes"] call comspec_atak_native_fnc_ewAction)) isEqualTo 0, "relevés trop proches : pas de point"] call AT_fnc_check;
    // Types différents : jamais croisés.
    missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", [[_a, 7, "TÉLÉPHONE"] call _mk, [_b, 7, "BROUILLEUR"] call _mk]];
    [(count (["fixes"] call comspec_atak_native_fnc_ewAction)) isEqualTo 0, "types différents : pas de point"] call AT_fnc_check;
    missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", _save select 0];
    missionNamespace setVariable ["COMSPEC_ATAK_EwFixes", _save select 1];
    call AT_fnc_done;
}, 30] call AT_fnc_run;

["gonio_zone_entree", {
    if (["comspec_atak_native_fnc_zoneAction"] call AT_fnc_nil) exitWith {};
    private _c = [3000, 3000, 0];
    private _plan = { params ["_area"]; createHashMapFromArray [["area", _area], ["zones", []]] };
    private _in = { params ["_a", "_p"]; ["inArea", [[_a] call _plan, _p]] call comspec_atak_native_fnc_zoneAction };
    private _rect = ["RECT", [[2900, 2900], [3100, 3100]]];
    [([_rect, [3000, 3000]] call _in) isEqualTo true, "RECT : le centre doit être dedans"] call AT_fnc_check;
    [([_rect, [3200, 3000]] call _in) isEqualTo false, "RECT : 200 m à l'est doit être dehors"] call AT_fnc_check;
    private _circ = ["CIRCLE", [[3000, 3000], 100]];
    [([_circ, [3050, 3050]] call _in) isEqualTo true, "CIRCLE : à 70 m du centre, dedans"] call AT_fnc_check;
    [([_circ, [3100, 3100]] call _in) isEqualTo false, "CIRCLE : à 141 m du centre, dehors"] call AT_fnc_check;
    private _poly = ["POLY", [[2900, 2900], [3100, 2900], [3000, 3100]]];
    [([_poly, [3000, 2950]] call _in) isEqualTo true, "POLY : dans le triangle"] call AT_fnc_check;
    [([_poly, [2900, 3100]] call _in) isEqualTo false, "POLY : hors du triangle"] call AT_fnc_check;
    call AT_fnc_done;
}, 20] call AT_fnc_run;

["gonio_liaison_faible", {
    if (["comspec_atak_native_fnc_netSend", "comspec_atak_native_fnc_linkQuality"] call AT_fnc_nil) exitWith {};
    private _saveQ = missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []];
    private _saveL = uiNamespace getVariable ["COMSPEC_ATAK_LinkQ", []];
    missionNamespace setVariable ["COMSPEC_ATAK_NetQueue", []];
    missionNamespace setVariable ["comspec_atak_native_net_degraded", true];
    // Liaison simulée : une barre, 50 kbit/s, émission possible.
    private _weak = createHashMapFromArray [["bars", 1], ["kbps", 50], ["latency", 80], ["loss", 0], ["factors", []], ["sim", true], ["label", "Faible"], ["tx", true], ["margin", -1], ["jitter", 0], ["net", "RELAIS"]];
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", [diag_tickTime + 60, _weak]];
    private _big = [{}, [], "Photo (essai)", 600, 1, "autotest:big"] call comspec_atak_native_fnc_netSend;
    [_big isEqualTo "QUEUED", format ["gros envoi : QUEUED attendu, %1 obtenu", _big]] call AT_fnc_check;
    [(count (missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []])) isEqualTo 1, "le gros envoi doit attendre en file"] call AT_fnc_check;
    private _small = [{}, [], "Message (essai)", 1, 1, ""] call comspec_atak_native_fnc_netSend;
    [_small in ["SENT", "DELAYED"], format ["petit envoi : SENT ou DELAYED attendu, %1 obtenu", _small]] call AT_fnc_check;
    // Réglage coupé : le gros envoi part.
    missionNamespace setVariable ["comspec_atak_native_net_degraded", false];
    private _off = [{}, [], "Photo (essai 2)", 600, 1, "autotest:big2"] call comspec_atak_native_fnc_netSend;
    [_off in ["SENT", "DELAYED"], format ["réglage coupé : le gros envoi doit partir, %1 obtenu", _off]] call AT_fnc_check;
    missionNamespace setVariable ["comspec_atak_native_net_degraded", true];
    // Bonne liaison : le gros envoi part.
    private _good = +_weak; _good set ["bars", 4]; _good set ["kbps", 2000];
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", [diag_tickTime + 60, _good]];
    private _ok = [{}, [], "Photo (essai 3)", 600, 1, "autotest:big3"] call comspec_atak_native_fnc_netSend;
    [_ok in ["SENT", "DELAYED"], format ["bonne liaison : le gros envoi doit partir, %1 obtenu", _ok]] call AT_fnc_check;
    missionNamespace setVariable ["COMSPEC_ATAK_NetQueue", _saveQ];
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", _saveL];
    call AT_fnc_done;
}, 30] call AT_fnc_run;
