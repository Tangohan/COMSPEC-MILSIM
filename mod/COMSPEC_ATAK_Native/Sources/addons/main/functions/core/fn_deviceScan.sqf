/*
    Objets des mods chargés dont le nom de classe contient un des motifs (téléphones d'autres mods), en minuscules.
    Le parcours de tout CfgWeapons (des dizaines de milliers de classes avec un gros modset) figeait l'arrivée en
    mission : il est fait une fois au lancement du jeu (XEH_preStart) et gardé dans uiNamespace par jeu de motifs,
    pour toutes les missions de la session. Sans dépendance : appelé aussi par XEH_preStart avant les fonctions.
      [motifs] call comspec_atak_native_fnc_deviceScan  -> ["classe", ...]
*/
params [["_patterns", []]];
private _key = _patterns joinString ",";
private _store = uiNamespace getVariable ["COMSPEC_ATAK_DeviceScan", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_DeviceScan", _store];
if (_key in _store) exitWith { +(_store get _key) };
private _t0 = diag_tickTime;
private _skip = ["hcam", "helmetcam", "helmet_cam", "_base", "battery", "batterie"]; // batterie ATAK : pas un téléphone
private _out = [];
if ((count _patterns) > 0) then {
    {
        private _cls = toLower (configName _x);
        if ((_patterns findIf { (_cls find _x) >= 0 }) >= 0 && {(_skip findIf { (_cls find _x) >= 0 }) < 0} && {(_cls find "comspec_atak_") isNotEqualTo 0} && {(getNumber (_x >> "scope")) >= 2}) then { _out pushBackUnique _cls; };
    } forEach ("isClass _x" configClasses (configFile >> "CfgWeapons"));
};
_store set [_key, _out];
diag_log format ["[COMSPEC ATAK NATIVE][PERF] Recherche des téléphones dans CfgWeapons (%1) : %2 ms, %3 objet(s)", _key, round ((diag_tickTime - _t0) * 1000), count _out];
+_out
