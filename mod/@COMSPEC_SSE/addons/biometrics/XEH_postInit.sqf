/*
    Moteur biométrique : canaux serveur / joueur, heure des décès, mémoire de campagne.
*/
if (isServer) then {
    ["comspec_sse_bioReq", { _this call comspec_sse_fnc_bioServerHandle }] call CBA_fnc_addEventHandler;
    // L'état d'un corps dépend du temps écoulé depuis la mort (rigidité, cornée).
    addMissionEventHandler ["EntityKilled", {
        params ["_u"];
        if (_u isKindOf "CAManBase") then {
            private _t = if (isNil "CBA_missionTime") then { time } else { CBA_missionTime };
            _u setVariable ["comspec_sse_bioDeathTime", _t, true];
        };
    }];
    if (missionNamespace getVariable ["comspec_sse_bio_campaignMemory", false]) then {
        private _seed = missionNamespace getVariable ["comspec_sse_bio_campaignSeed", ""];
        private _store = format ["comspec_sse_bioCampaign_%1", [_seed, missionName] select (_seed isEqualTo "")];
        private _saved = profileNamespace getVariable [_store, []];
        private _runtime = missionNamespace getVariable ["comspec_sse_bioRuntime", createHashMap];
        if (_saved isEqualType []) then {
            {
                _x params ["_k", "_packed"];
                private _e = [_packed] call comspec_sse_fnc_bioUnpack;
                if (_e isEqualType createHashMap) then { _runtime set [_k, _e]; };
            } forEach _saved;
        };
        missionNamespace setVariable ["comspec_sse_bioRuntime", _runtime];
    };
};
if (hasInterface) then {
    ["comspec_sse_bioRes", { _this call comspec_sse_fnc_bioClientResult }] call CBA_fnc_addEventHandler;
    ["comspec_sse_bioNotify", { _this call comspec_sse_fnc_bioNotify }] call CBA_fnc_addEventHandler;
};
