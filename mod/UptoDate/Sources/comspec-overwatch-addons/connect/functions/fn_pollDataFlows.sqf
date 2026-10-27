/*
    Données remontées choisies par la communauté sur Athena (Configuration ATAK > Données remontées) → COMSPEC_DataFlows.
    La DLL (GetDataFlows, COMSPEC Link 2.0.94+) lit GET /api/atak/data-flows et ne met plus en file les flux coupés ;
    ici, le jeu arrête aussi de calculer ce qui ne partirait pas (véhicules, drones, aéronefs, miroir des notifications,
    pertes du téléphone natif). Appelé au démarrage des boucles puis toutes les 30 s (fn_startSyncLoops).
    Clés : positions, vehicles, combat, phone_mirror, context (true = envoyé). DLL plus ancienne : rien ne change.
    Retour : la HashMap des flux.
*/
if (!hasInterface) exitWith { createHashMap };
private _cur = missionNamespace getVariable ["COMSPEC_DataFlows", createHashMap];
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { _cur };
private _raw = ["COMSPECExtension" callExtension ["GetDataFlows", []]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {(_raw select [0, 3]) isNotEqualTo "OK|"}) exitWith { _cur };
private _payload = _raw select [3];
if (_payload isEqualTo (missionNamespace getVariable ["COMSPEC_DataFlowsRaw", ""])) exitWith { _cur };
missionNamespace setVariable ["COMSPEC_DataFlowsRaw", _payload];
private _flows = createHashMap;
private _preset = "";
{
    private _kv = _x splitString "=";
    if ((count _kv) >= 1) then {
        private _k = _kv select 0;
        private _v = _kv param [1, ""];
        if (_k in ["positions", "vehicles", "combat", "phone_mirror", "context"]) then { _flows set [_k, _v isNotEqualTo "0"]; };
        if (_k isEqualTo "preset") then { _preset = _v; };
    };
} forEach (_payload splitString (toString [9]));
missionNamespace setVariable ["COMSPEC_DataFlows", _flows];
private _off = (keys _flows) select { !(_flows get _x) };
["INFO", "DataFlows", format ["Données remontées : %1 · coupées : %2", [_preset, "personnalisé"] select (_preset isEqualTo ""), [_off joinString ", ", "aucune"] select ((count _off) isEqualTo 0)]] call comspec_overwatch_connect_fnc_log;
_flows
