/*
    Ordre du poste web sur des charges ACE suivies, exécuté par le téléphone de leur propriétaire (fn_webCmdDispatch).
    Params : [id, action, ids de charge séparés par ",", args (HashMap)]
      arm      : raccorde la charge à ATAK (déclenchable par le téléphone et le poste ; le déclencheur local ne la tire plus) ;
      disarm   : la rend au déclencheur local (le poste ne peut plus la tirer). Neutraliser pour de bon reste un geste sur place ;
      fire     : mise à feu ;
      sequence : mise à feu en séquence, d = délai avant la première (s), g = intervalle (s).
    Réalisme de la mise à feu (téléphone ou exploseur du propriétaire, à portée) :
      charge ATAK  → téléphone allumé, en liaison, à moins de comspec_overwatch_web_explo_atak_range m (défaut 3000) ;
      autre charge → un exploseur ACE dans l'inventaire dont la portée (ace_explosives_range) couvre la distance.
      Minuterie lancée : jamais commandée à distance.
    Une charge qui n'est pas à ce joueur est ignorée en silence (un autre client répond). Retour : true si au moins une charge est à lui.
*/
params [["_id", ""], ["_act", ""], ["_ref", ""], ["_a", createHashMap]];
if (!isClass (configFile >> "CfgPatches" >> "ace_explosives")) exitWith { false };

private _uid = getPlayerUID player;
private _local = missionNamespace getVariable ["COMSPEC_ExplosiveLocalIds", []];
if (!(_local isEqualType [])) then { _local = []; };
private _myObjs = missionNamespace getVariable ["COMSPEC_ATAK_MyCharges", []];
if (!(_myObjs isEqualType [])) then { _myObjs = []; };

// Charges de la commande qui sont à ce joueur : [cid, objet].
private _mine = [];
{
    private _cid = _x;
    private _exp = [_cid] call comspec_overwatch_connect_fnc_findChargeObject;
    private _owner = if (isNull _exp) then { "" } else { _exp getVariable ["COMSPEC_chargeOwnerUid", ""] };
    private _isMine = switch (true) do {
        case (_owner isEqualType "" && {_owner isNotEqualTo ""}): { _owner isEqualTo _uid };
        case (_cid in _local): { true };
        case (!isNull _exp && {_exp in _myObjs}): { true };
        default { false };
    };
    if (_isMine) then { _mine pushBack [_cid, _exp]; };
} forEach (_ref splitString ",");
if ((count _mine) isEqualTo 0) exitWith { false };

private _say = {
    params ["_lvl", "_t"];
    if (!isNil "comspec_atak_native_fnc_notify") then { [_lvl, _t, 5, 50] call comspec_atak_native_fnc_notify; } else { [_t, "tactical", ["info", "warn"] select (_lvl isEqualTo "WARNING")] call comspec_overwatch_connect_fnc_announce; };
};
private _label = { params ["_e"]; private _l = getText (configOf _e >> "displayName"); if (_l isEqualTo "") then { "Charge" } else { _l } };
private _kindOf = { params ["_e"]; toLower (_e getVariable ["COMSPEC_triggerKind", ""]) };

// Peut-on mettre à feu cette charge d'ici ? "" = oui, sinon la raison.
private _canFire = {
    params ["_e"];
    if (isNull _e || {!alive _e} || {_e getVariable ["COMSPEC_detonateFired", false]}) exitWith { "charge introuvable ou déjà sautée" };
    if (!alive player) exitWith { "propriétaire hors de combat" };
    private _k = [_e] call _kindOf;
    if (_k isEqualTo "timer") exitWith { "minuterie lancée, elle sautera seule" };
    private _dist = round (player distance _e);
    if (_k isEqualTo "atak") exitWith {
        if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith { "pas de téléphone ATAK sur le propriétaire" };
        if !(([true] call comspec_overwatch_connect_fnc_canTransmit) getOrDefault ["can_transmit", true]) exitWith { "téléphone du propriétaire sans liaison" };
        if (!isNil "comspec_atak_native_fnc_hasDevice" && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0}) exitWith { "batterie du téléphone vide" };
        private _rng = missionNamespace getVariable ["comspec_overwatch_web_explo_atak_range", 3000];
        if (_dist > _rng) exitWith { format ["hors de portée du téléphone (%1 m, %2 m au plus)", _dist, _rng] };
        ""
    };
    // Exploseur ACE : la meilleure portée de l'inventaire.
    private _best = 0;
    {
        private _r = getNumber (configFile >> "CfgWeapons" >> _x >> "ace_explosives_range");
        if (_r > _best) then { _best = _r; };
    } forEach (items player);
    if (_best <= 0) exitWith { "pas d'exploseur sur le propriétaire" };
    if (_dist > _best) exitWith { format ["hors de portée de l'exploseur (%1 m, %2 m au plus)", _dist, _best] };
    ""
};

private _okList = [];
private _errList = [];
switch (_act) do {
    case "arm";
    case "disarm": {
        private _to = ["clacker", "atak"] select (_act isEqualTo "arm");
        {
            _x params ["_cid", "_e"];
            if (isNull _e || {!alive _e}) then { _errList pushBack format ["%1 : introuvable", _cid]; continue };
            if (([_e] call _kindOf) isEqualTo "timer") then { _errList pushBack format ["%1 : minuterie lancée", [_e] call _label]; continue };
            if ([_e, _to, player] call comspec_overwatch_connect_fnc_chargeSetTrigger) then { _okList pushBack ([_e] call _label); } else { _errList pushBack format ["%1 : refusé", [_e] call _label]; };
        } forEach _mine;
        if ((count _okList) > 0) then {
            ["INFO", format ["Poste de commandement : %1 %2", (["rendue(s) au déclencheur local", "raccordée(s) à ATAK"] select (_act isEqualTo "arm")), _okList joinString ", "]] call _say;
        };
    };
    case "fire": {
        {
            _x params ["_cid", "_e"];
            private _why = [_e] call _canFire;
            if (_why isNotEqualTo "") then { _errList pushBack _why; continue };
            if ([_cid] call comspec_overwatch_connect_fnc_detonateChargeById) then { _okList pushBack ([_e] call _label); } else { _errList pushBack "mise à feu refusée"; };
        } forEach _mine;
        if ((count _okList) > 0) then { ["WARNING", format ["Poste de commandement : mise à feu de %1", _okList joinString ", "]] call _say; };
    };
    case "sequence": {
        private _delay = 0 max (parseNumber (_a getOrDefault ["d", "5"])) min 120;
        private _gap = 0 max (parseNumber (_a getOrDefault ["g", "1"])) min 30;
        private _n = 0;
        {
            _x params ["_cid", "_e"];
            private _why = [_e] call _canFire;
            if (_why isNotEqualTo "") then { _errList pushBack format ["%1 : %2", [_e] call _label, _why]; continue };
            // Mise à feu à l'échéance, avec un nouveau contrôle de portée (le propriétaire a pu bouger).
            [{
                params ["_cid", "_e", "_canFire", "_kindOf"];
                if (([_e] call _canFire) isEqualTo "") then { [_cid] call comspec_overwatch_connect_fnc_detonateChargeById; };
            }, [_cid, _e, _canFire, _kindOf], _delay + _n * _gap] call CBA_fnc_waitAndExecute;
            _okList pushBack ([_e] call _label);
            _n = _n + 1;
        } forEach _mine;
        if (_n > 0) then { ["WARNING", format ["Poste de commandement : séquence de %1 charge(s), première dans %2 s", _n, _delay]] call _say; };
    };
    default { _errList pushBack "action inconnue"; };
};

private _verb = createHashMapFromArray [["arm", "Raccordée(s) à ATAK"], ["disarm", "Rendue(s) au déclencheur local"], ["fire", "Mise à feu"], ["sequence", "Séquence lancée"]];
private _msg = if ((count _okList) > 0) then {
    format ["%1 : %2%3", _verb getOrDefault [_act, _act], _okList joinString ", ", ["", format [" — échecs : %1", _errList joinString " ; "]] select ((count _errList) > 0)]
} else {
    format ["Échec : %1", _errList joinString " ; "]
};
[_id, ["failed", "done"] select ((count _okList) > 0), _msg, createHashMapFromArray [["ok", count _okList], ["failed", count _errList]]] call comspec_overwatch_connect_fnc_webCmdAck;
true
