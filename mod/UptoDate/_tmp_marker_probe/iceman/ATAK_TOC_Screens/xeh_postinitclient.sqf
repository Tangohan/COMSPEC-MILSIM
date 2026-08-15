#include "script_component.hpp"

if (!hasInterface) exitWith {};

if (isNil "Iceman_TOC_nextRenderTarget") then {
    Iceman_TOC_nextRenderTarget = 0;
};

if (isNil "Iceman_TOC_vehicleCams" || {typeName Iceman_TOC_vehicleCams != "ARRAY"}) then {
    Iceman_TOC_vehicleCams = [];
};

["Iceman_TOC_stream", {
    _this call Iceman_fnc_toc_startStream;
}] call CBA_fnc_addEventHandler;

["Iceman_TOC_stop", {
    _this call Iceman_fnc_toc_stopStream;
}] call CBA_fnc_addEventHandler;

["Iceman_TOC_zoom", {
    _this call Iceman_fnc_toc_applyZoomLocal;
}] call CBA_fnc_addEventHandler;

["Iceman_TOC_vision", {
    _this call Iceman_fnc_toc_applyVisionLocal;
}] call CBA_fnc_addEventHandler;

["Iceman_TOC_present", {
    _this call Iceman_fnc_toc_applyPresenterLocal;
}] call CBA_fnc_addEventHandler;

["Iceman_TOC_snapshot", {
    _this call Iceman_fnc_toc_addSnapshotLocal;
}] call CBA_fnc_addEventHandler;

if (isNil "Iceman_TOC_drawHandler") then {
    Iceman_TOC_drawHandler = addMissionEventHandler ["Draw3D", {
        if (isNil "Iceman_TOC_vehicleCams" || {typeName Iceman_TOC_vehicleCams != "ARRAY"}) then {
            Iceman_TOC_vehicleCams = [];
        };

        Iceman_TOC_vehicleCams = Iceman_TOC_vehicleCams select {
            private _target = objNull;
            private _vehicle = objNull;
            private _cam = objNull;
            private _posMem = "";
            private _dirMem = "";

            if ((count _x) >= 6) then {
                _x params ["_entryTarget", "_slot", "_entryVehicle", "_entryCam", "_entryPosMem", "_entryDirMem"];
                _target = _entryTarget;
                _vehicle = _entryVehicle;
                _cam = _entryCam;
                _posMem = _entryPosMem;
                _dirMem = _entryDirMem;
            } else {
                _x params ["_entryTarget", "_entryVehicle", "_entryCam", "_entryPosMem", "_entryDirMem"];
                _target = _entryTarget;
                _vehicle = _entryVehicle;
                _cam = _entryCam;
                _posMem = _entryPosMem;
                _dirMem = _entryDirMem;
            };

            if (isNull _target || {isNull _vehicle} || {isNull _cam} || {!alive _vehicle}) then {
                false
            } else {
                private _dir = (_vehicle selectionPosition _posMem) vectorFromTo (_vehicle selectionPosition _dirMem);
                if !(_dir isEqualTo [0,0,0]) then {
                    _cam setVectorDirAndUp [_dir, _dir vectorCrossProduct [-(_dir # 1), _dir # 0, 0]];
                };
                true
            };
        };
    }];
};

[] call Iceman_fnc_toc_installAce;
