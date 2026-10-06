/*
    Bluetooth : sons que l'on peut envoyer à un appareil appairé. Renvoie [[kind, ref, titre, catégorie, durée s]...]
      kind "snd" : classe CfgSounds (mission, Arma et mods), joué par say3D sur l'unité du destinataire ;
      kind "mus" : piste CfgMusic de la bibliothèque du serveur (app Musique), jouée par playSound3D sur l'unité du destinataire.
    Calculé une fois par mission (missionNamespace COMSPEC_ATAK_BtSounds).
*/
private _list = missionNamespace getVariable ["COMSPEC_ATAK_BtSounds", []];
if ((count _list) > 0) exitWith { _list };
private _seen = createHashMap;
{
    private _root = _x;
    {
        private _cls = configName _x;
        private _snd = getArray (_x >> "sound");
        if !((toLower _cls) in _seen) then {
            if ((count _snd) > 0 && {(_snd select 0) isEqualType ""} && {(_snd select 0) isNotEqualTo ""}) then {
                _seen set [toLower _cls, true];
                private _name = getText (_x >> "name");
                if (_name isEqualTo "") then { _name = _cls; };
                _list pushBack ["snd", _cls, _name, ["Sons", "Mission"] select (_root isEqualTo missionConfigFile), 0];
            };
        };
    } forEach ("true" configClasses (_root >> "CfgSounds"));
} forEach [missionConfigFile, configFile];
_list = _list apply { [toLower (_x select 2), _x] };
_list sort true;
_list = _list apply { _x select 1 };
// Pistes de la bibliothèque Musique (CfgMusic), après les sons.
{
    _x params ["_cls", "_name", "_len", "_cat"];
    _list pushBack ["mus", _cls, _name, format ["Musique · %1", _cat], _len];
} forEach (([] call comspec_atak_native_fnc_musicServerList) select 0);
missionNamespace setVariable ["COMSPEC_ATAK_BtSounds", _list];
_list
