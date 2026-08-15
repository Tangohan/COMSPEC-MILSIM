/*
    Author: COMSPEC
    Description: Envoie un intel (PING, CHAT, PHOTO) vers la DLL.
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
};
