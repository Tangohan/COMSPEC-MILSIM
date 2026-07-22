/*
    Sonde COMSPECExtension (Ping). Distingue chargement OK / réponse invalide / non chargée.
    Retourne: [_ok (bool), _code (string), _ping (string)]
      _code: "ok" | "not_loaded" | "bad_response"
*/
private _ping = ["COMSPECExtension" callExtension ["Ping", []]] call comspec_overwatch_connect_fnc_extResult;
private _err = missionNamespace getVariable ["COMSPEC_LastExtError", 0];

if (_ping isEqualTo "") exitWith {
    [false, "not_loaded", _ping, _err]
};

if ((_ping select [0, 3]) != "OK|") exitWith {
    [false, "bad_response", _ping, _err]
};

[true, "ok", _ping, _err]
