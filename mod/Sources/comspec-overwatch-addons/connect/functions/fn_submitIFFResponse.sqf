// Submit IFF response to C2. Params: [missionId, assetId, responseCode]
params [
    ["_missionId", "mission_1_map_1", [""]],
    ["_assetId", "", [""]],
    ["_responseCode", "", [""]]
];
if (_assetId isEqualTo "" || _responseCode isEqualTo "") exitWith { false };
private _payload = format ['{"missionId":"%1","assetId":"%2","responseCode":"%3"}', _missionId, _assetId, _responseCode];
"COMSPECExtension" callExtension ["IFF.Response", [_payload]];
true
