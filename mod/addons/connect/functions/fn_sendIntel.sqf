/*
    Author: COMSPEC
    Description:
    Envoie un intel vers la DLL.
    Types supportés:
    - PING
    - CHAT
    - PHOTO
    - REPORT
*/
params ["_unit", "_type", "_data", ["_extra", ""]];

switch (_type) do {
    case "PING": {
        "COMSPECExtension" callExtension ["SendPing", [name _unit, str (_data select 0), str (_data select 1), _extra]];
    };
    case "CHAT": {
        "COMSPECExtension" callExtension ["SendChat", [name _unit, _data]];
    };
    case "PHOTO": {
        "COMSPECExtension" callExtension ["UploadImage", [_data, _extra]];
    };
    case "REPORT": {
        /*
            _data attendu:
            [
                target_type,
                position,
                missionId,
                source_callsign(optionnel)
            ]
        */
        _data params [
            ["_targetType", "", [""]],
            ["_pos", [], [[]]],
            ["_missionId", "", [""]],
            ["_sourceCallsign", "", [""]]
        ];
        [_unit, _targetType, _pos, _missionId, _sourceCallsign] call comspec_overwatch_connect_fnc_reportIntel;
    };
};
