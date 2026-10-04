/*
    Carte : couche Détecteur de drones. Appelé depuis fn_mapOnDraw. Params : [contrôle carte]
    Pour chaque détection récente (COMSPEC_ATAK_DroneDetect) : cône d'erreur tracé depuis le point où j'ai relevé,
    jusqu'à la bande de distance estimée si le signal était fort, sinon jusqu'à la portée nominale du type de liaison.
    Rouge = INCONNU, bleu = AMI. Le cône pâlit puis disparaît 60 s après le dernier relevé. Jamais de position du drone.
*/
params ["_map"];
if (isNull _map) exitWith {};
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _none = "#(argb,8,8,3)color(0,0,0,0)";
private _now = time;
{
    _x params ["", "_band", "_bars", "_brg", "_err", "_rb", "", "_last", "_friend", ["_from", []], "", ["_range", 2000]];
    private _age = _now - _last;
    if (_age > 60) then { continue; };
    if ((count _from) < 2) then { _from = getPosATL player; };
    _from = [_from select 0, _from select 1, 0];
    private _a = 1 - (_age / 60) * 0.75;
    private _rgb = [[0.9, 0.28, 0.23], [0.3, 0.6, 1]] select _friend;
    private _c = _rgb + [_a];
    private _cf = _rgb + [_a * 0.45];
    private _len = switch (_rb) do { case "< 300 m": { 300 }; case "300-800 m": { 800 }; case "> 800 m": { _range max 1200 }; default { _range }; };
    // Cône : bords pâles, axe plein, remplissage léger en éventail.
    private _l = _from getPos [_len, _brg - _err];
    private _r = _from getPos [_len, _brg + _err];
    _map drawLine [_from, _l, _cf];
    _map drawLine [_from, _r, _cf];
    _map drawLine [_from, _from getPos [_len, _brg], _c];
    private _steps = (ceil (_err / 3)) max 2;
    private _prev = _l;
    for "_i" from 1 to _steps do {
        private _p = _from getPos [_len, (_brg - _err) + 2 * _err * _i / _steps];
        _map drawTriangle [[_from, _prev, _p], _rgb + [_a * 0.12], _fill];
        _map drawLine [_prev, _p, _cf];
        _prev = _p;
    };
    // Bande de distance connue : arc intérieur en plus.
    if (_rb in ["300-800 m", "> 800 m"]) then {
        private _in = [300, 800] select (_rb isEqualTo "> 800 m");
        _map drawLine [_from getPos [_in, _brg - _err], _from getPos [_in, _brg + _err], _cf];
    };
    _map drawIcon [_none, _c, _from getPos [_len, _brg], 0, 0, 0,
        format ["DRONE %1 %2° ±%3°%4", ["INCONNU", "AMI"] select _friend, round _brg, round _err, ["", format [" · %1", _rb]] select (_rb isNotEqualTo "")],
        1, 0.024, "RobotoCondensedBold", "right"];
    _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _c, _from, 8, 8, 0, "", 0];
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_DroneDetect", []]);
