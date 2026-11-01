/*
    Enfile une tenue sur le mannequin de l'arsenal ACE ouvert (sinon sur le joueur), comme le bouton Charger
    de « Mes équipements » d'ACE : tenue vérifiée par ACE (objets absents de cet arsenal retirés, contenants
    récupérés), posée par CBA_fnc_setLoadout avec ses infos étendues, puis arsenal rafraîchi (ace_arsenal_fnc_refresh)
    et visage / voix / insigne retenus par ACE.
    Avant : setUnitLoadout seul. ACE ne savait pas que la tenue avait changé (sa liste d'objets portés restait
    l'ancienne) : l'arsenal affichait encore l'ancienne tenue et le clic suivant dans ACE la remettait en partie.
    Params : [tenue sous n'importe quel format, nom affiché]. Retour : true si la tenue est enfilée.
*/
params [["_data", []], ["_name", "", [""]]];

private _ext = ["extended", _data] call comspec_overwatch_connect_fnc_arsenalAceStore;
if (_ext isEqualTo []) exitWith {
    ["Cette tenue est illisible : elle ne peut pas être enfilée.", "arsenal", "warn", true] call comspec_overwatch_connect_fnc_announce;
    false
};

private _done = false;
// Bloc non interrompu (isNil) : ACE et CBA attendent ces appels hors ordonnanceur.
isNil {
    private _display = uiNamespace getVariable ["ace_arsenal_display", displayNull];
    private _inArsenal = !isNull _display && {!isNil "ace_arsenal_center"} && {!isNull ace_arsenal_center};
    private _unit = if (_inArsenal) then { ace_arsenal_center } else { player };
    if (isNull _unit || {!alive _unit}) exitWith {};
    private _lo = _ext;
    if (_inArsenal && {!isNil "ace_arsenal_fnc_verifyLoadout"}) then {
        private _v = [+_ext, true] call ace_arsenal_fnc_verifyLoadout;
        if (!isNil "ace_arsenal_fnc_recoverInvalidContainers") then { _v = [_unit, _v] call ace_arsenal_fnc_recoverInvalidContainers; };
        private _got = _v param [0, []];
        switch (true) do {
            case ((_got isEqualType []) && {(count _got) == 2} && {((_got select 0) isEqualType [])} && {(count (_got select 0)) == 10}): { _lo = _got; };
            case ((_got isEqualType []) && {(count _got) == 10}): { _lo = [_got, _ext select 1]; };
        };
    };
    if (isNil "CBA_fnc_setLoadout") then {
        _unit setUnitLoadout [_lo select 0, true];
    } else {
        [_unit, _lo, true] call CBA_fnc_setLoadout;
    };
    if (_inArsenal) then {
        if (!isNil "ace_arsenal_fnc_preventOverfilling") then { _unit call ace_arsenal_fnc_preventOverfilling; };
        if (!isNil "ace_arsenal_fnc_refresh") then { [true] call ace_arsenal_fnc_refresh; };
        // Comme ACE (fnc_buttonLoadoutsLoad) : l'arsenal réapplique ces trois choix à sa fermeture.
        private _info = _lo select 1;
        if !(_info isEqualType createHashMap) then { _info = createHashMap; };
        if ("ace_arsenal_insignia" in _info) then {
            ace_arsenal_currentInsignia = _info getOrDefault ["ace_arsenal_insignia", ""];
        } else {
            if (!isNil "ace_arsenal_currentInsignia") then {
                [_unit, ""] call BIS_fnc_setUnitInsignia;
                [_unit, ace_arsenal_currentInsignia] call BIS_fnc_setUnitInsignia;
            };
        };
        if ("ace_arsenal_face" in _info && {!isNil "ace_arsenal_currentFace"}) then { ace_arsenal_currentFace = _info getOrDefault ["ace_arsenal_face", ace_arsenal_currentFace]; };
        if ("ace_arsenal_voice" in _info && {!isNil "ace_arsenal_currentVoice"}) then { ace_arsenal_currentVoice = _info getOrDefault ["ace_arsenal_voice", ace_arsenal_currentVoice]; };
        // Mêmes événements que le chargement ACE : les autres mods qui les écoutent suivent.
        ["ace_arsenal_onLoadoutLoad", [_lo select 0, _name]] call CBA_fnc_localEvent;
        ["ace_arsenal_onLoadoutLoadExtended", [_lo, _name]] call CBA_fnc_localEvent;
    };
    _done = true;
};
if (!_done) exitWith { false };

[[format ["Tenue « %1 » enfilée.", _name], "Tenue enfilée."] select (_name isEqualTo ""), "arsenal", "ok", true] call comspec_overwatch_connect_fnc_announce;
if (!isNil "comspec_overwatch_connect_fnc_operatorProfileTick") then {
    [{ ["loadout_changed"] call comspec_overwatch_connect_fnc_operatorProfileTick; }, [], 1] call CBA_fnc_waitAndExecute;
};
true
