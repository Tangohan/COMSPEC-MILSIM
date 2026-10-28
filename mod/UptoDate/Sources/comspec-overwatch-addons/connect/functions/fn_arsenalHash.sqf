/*
    Empreinte sha256 d'une tenue telle que SyncWardrobe l'envoie (texte « str loadout »), calculée par COMSPEC Link
    (HashText, 2.0.88) et comparée au payload_sha256 du site : dit si la tenue locale est déjà sur le site sans rien
    renvoyer. Cache par texte pour la session. DLL plus ancienne : "" (le panneau se contente alors du nom).
    Params : [texte]. Retour : sha256 hexadécimal minuscule, ou "".
*/
params [["_payload", "", [""]]];
if (_payload isEqualTo "") exitWith { "" };
if (uiNamespace getVariable ["COMSPEC_ArsenalHashOff", false]) exitWith { "" };
private _cache = uiNamespace getVariable ["COMSPEC_ArsenalHashCache", nil];
if (isNil "_cache") then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ArsenalHashCache", _cache]; };
private _hit = _cache getOrDefault [_payload, ""];
if (_hit isNotEqualTo "") exitWith { _hit };
private _raw = ["COMSPECExtension" callExtension ["HashText", [_payload]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {(_raw find "OK|") != 0} || {(count _raw) != 67}) exitWith {
    // Commande absente (DLL < 2.0.88) : ne plus essayer de la session.
    uiNamespace setVariable ["COMSPEC_ArsenalHashOff", true];
    ["INFO", "ARSENAL", format ["HashText indisponible (%1) : état de synchro des tenues par nom seulement", _raw]] call comspec_overwatch_connect_fnc_log;
    ""
};
private _sha = _raw select [3];
_cache set [_payload, _sha];
_sha
