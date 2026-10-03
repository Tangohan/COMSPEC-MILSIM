/* Message quand le joueur n'a pas le terminal exigé par le serveur. */
private _catalog = [] call comspec_atak_native_fnc_deviceCatalog;
private _names = (_catalog select [0, 4]) apply { private _n = getText (configFile >> "CfgWeapons" >> _x >> "displayName"); [_n, _x] select (_n isEqualTo "") };
hintSilent parseText format ["<t size='1.1' color='#f2ab33'>Pas de terminal ATAK</t><br/>Il faut porter : %1", _names joinString ", "];
false
