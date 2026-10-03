/*
    App Musique : catalogue « serveur ». Renvoie [pistes, radio]
      pistes : [[classe, titre, durée s, catégorie]...]  (CfgMusic d'Arma, des mods chargés et de la mission, chargés par tous)
      radio  : [[titre, URL]...]  (réglage CBA « Radio de la mission » « Titre|URL;Titre|URL » + COMSPEC_ATAK_MusicRadio du créateur de mission)
*/
private _tracks = missionNamespace getVariable ["COMSPEC_ATAK_MusicSrv", []];
if ((count _tracks) isEqualTo 0) then {
    private _seen = createHashMap;
    {
        private _root = _x;
        {
            private _cls = configName _x;
            if !((toLower _cls) in _seen) then {
                private _snd = getArray (_x >> "sound");
                if ((count _snd) > 0 && {(_snd select 0) isNotEqualTo ""}) then {
                    _seen set [toLower _cls, true];
                    private _name = getText (_x >> "name");
                    if (_name isEqualTo "") then { _name = _cls; };
                    private _mc = getText (_x >> "musicClass");
                    private _cat = getText (configFile >> "CfgMusicClasses" >> _mc >> "displayName");
                    if (_cat isEqualTo "") then { _cat = [_mc, "Autres"] select (_mc isEqualTo ""); };
                    if (_root isEqualTo missionConfigFile) then { _cat = "Mission"; };
                    _tracks pushBack [_cls, _name, getNumber (_x >> "duration"), _cat];
                };
            };
        } forEach ("true" configClasses (_root >> "CfgMusic"));
    } forEach [missionConfigFile, configFile];
    _tracks = _tracks apply { [toLower (_x select 1), _x] };
    _tracks sort true;
    _tracks = _tracks apply { _x select 1 };
    missionNamespace setVariable ["COMSPEC_ATAK_MusicSrv", _tracks];
};
private _radio = +(missionNamespace getVariable ["COMSPEC_ATAK_MusicRadio", []]);
{
    private _p = _x splitString "|";
    if ((count _p) >= 2) then { _radio pushBack [_p select 0, (_p select [1, 9]) joinString "|"]; }
    else { if ((count _p) isEqualTo 1 && {((_p select 0) find "http") isEqualTo 0}) then { _radio pushBack [_p select 0, _p select 0]; }; };
} forEach ((missionNamespace getVariable ["comspec_atak_native_music_radio", ""]) splitString ";" apply { _x trim [" ", 0] });
[_tracks, _radio]
