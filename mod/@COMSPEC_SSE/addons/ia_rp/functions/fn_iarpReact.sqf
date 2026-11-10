/*
    Réactions des IA à ce qui se passe autour d'elles (SERVEUR), sur évènement seulement : aucune boucle sur toutes
    les IA. Seules les IA à qui l'on parle (fn_iarpServer) ou engagées par l'automate des IA réalistes (fn_idAiFsm)
    sont suivies.
      ["halt", [ia, joueur]]   « Stop » obéi : l'IA est relâchée seule (réglage « Durée d'un stop », joueur parti à plus
                               de « Distance de relâche », automate qui la reprend : fuite, hostilité, retour au calme).
                               Un autre ordre (suivez-moi, circulez, mains en l'air, descendez) annule le suivi.
    Suivi d'un stop : une fonction CBA toutes les 2 s par IA arrêtée, retirée dès qu'elle est relâchée.
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith {};
// État de l'automate des IA réalistes ("" : hors automate, ou identité SSE absente).
private _fsmState = {
    params ["_u"];
    if (isNil "comspec_sse_fnc_idAiFsm") exitWith { "" };
    private _s = ["state", [_u]] call comspec_sse_fnc_idAiFsm;
    if (isNil "_s" || {!(_s isEqualType "")}) then { "" } else { _s }
};

switch (_op) do {
    case "halt": {
        _a params [["_t", objNull, [objNull]], ["_by", objNull, [objNull]]];
        if (isNull _t) exitWith {};
        private _id = (_t getVariable ["comspec_sse_iarpHaltId", 0]) + 1;
        _t setVariable ["comspec_sse_iarpHaltId", _id];
        private _end = time + (missionNamespace getVariable ["comspec_sse_iarp_haltTime", 120]);
        [{
            params ["_args", "_h"];
            _args params ["_t", "_by", "_id", "_end", "_fsmState"];
            // "" : on attend ; "drop" : suivi fini sans rien faire ; "free" : l'IA repart.
            private _why = call {
                if (isNull _t || {!alive _t}) exitWith { "drop" };
                if ((_t getVariable ["comspec_sse_iarpHaltId", 0]) isNotEqualTo _id) exitWith { "drop" };
                private _st = [_t] call _fsmState;
                // L'automate la reprend : il donne lui-même ses ordres (fuite, hostilité, reddition) ; retour au calme : elle repart.
                if (_st in ["fuit", "agressif", "renforts", "rendu"]) exitWith { "drop" };
                if (_st isEqualTo "calme") exitWith { "free" };
                if (time > _end) exitWith { "free" };
                if (isNull _by || {!alive _by} || {(_by distance _t) > (missionNamespace getVariable ["comspec_sse_iarp_haltDist", 25])}) exitWith { "free" };
                ""
            };
            if (_why isEqualTo "") exitWith {};
            [_h] call CBA_fnc_removePerFrameHandler;
            if (_why isEqualTo "free") then {
                _t setVariable ["comspec_sse_iarpHaltId", _id + 1];
                ["comspec_sse_iarpAiDo", [_t, "unhalt", []], _t] call CBA_fnc_targetEvent;
            };
        }, 2, [_t, _by, _id, _end, _fsmState]] call CBA_fnc_addPerFrameHandler;
    };
};
