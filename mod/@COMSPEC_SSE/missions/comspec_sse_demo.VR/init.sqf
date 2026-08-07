// Mission démo minimale — VR
// Placez ce dossier dans vos missions utilisateur ou packez-le.
// init.sqf

if (hasInterface) then {
    waitUntil { !isNull player };
    player addItem "COMSPEC_SSE_EvidenceBag";
    player addItem "COMSPEC_SSE_Camera";
    player addItem "COMSPEC_SSE_FingerprintKit";
    player addItem "COMSPEC_SSE_SEEKII";
    hint "COMSPEC SSE Demo — ACE Self > COMSPEC SSE > Journal SSE";
};

if (isServer) then {
    [] spawn {
        sleep 2;
        private _g = createGroup civilian;
        private _u = _g createUnit ["C_man_1", [0,0,0], [], 0, "NONE"];
        _u setPosATL (getPosATL (allPlayers select 0) vectorAdd [0, 4, 0]);
        [_u, "INSURGENT", "DETAILED", "DEMO"] call comspec_sse_fnc_generateData;

        private _phone = createVehicle ["Land_MobilePhone_smart_F", [0,0,0], [], 0, "CAN_COLLIDE"];
        _phone setPosATL (getPosATL _u vectorAdd [1, 0, 0.1]);
        _phone setVariable ["comspec_sse_forcedType", "PHONE", true];
        [_phone, "INSURGENT", "DETAILED", "DEMO"] call comspec_sse_fnc_generateData;
        [_phone, _u, "OWNER"] call comspec_sse_fnc_linkEntities;
    };
};
