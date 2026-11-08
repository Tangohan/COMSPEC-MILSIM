/*
    Note de service : suit le défilement, tient la barre de lecture et active « J'accepte » une fois la fin atteinte.
    Tourne en tâche planifiée, quelques fois par seconde, seulement tant que la note est ouverte.
    ctrlScrollValues donne 0 à 1, mais rien ne garantit la valeur en bas du texte : le maximum est relevé à l'ouverture et
    à chaque changement de langue (bas puis retour en haut). Texte plus court que la zone, ou valeur inutilisable :
    la note compte comme lue, pour ne jamais bloquer un joueur sur un bouton impossible à activer.
    Params : [display]
*/
disableSerialization;
params [["_d", displayNull]];
if (isNull _d) exitWith {};
private _grp = _d displayCtrl 9508;
private _body = _d displayCtrl 9500;
private _track = _d displayCtrl 9514;
private _fill = _d displayCtrl 9515;
private _label = _d displayCtrl 9516;
private _btn = _d displayCtrl 9501;
private _endAt = 1;
private _last = [];
private _setDone = {
    uiNamespace setVariable ["COMSPEC_NDA_ReadDone", true];
    private _pack = uiNamespace getVariable ["COMSPEC_NDA_Pack", []];
    _btn ctrlEnable true;
    _btn ctrlSetText ((_pack param [3, ""]) call comspec_overwatch_connect_fnc_tr);
};
while {!isNull _d} do {
    if (uiNamespace getVariable ["COMSPEC_NDA_Recal", true]) then {
        uiNamespace setVariable ["COMSPEC_NDA_Recal", false];
        _endAt = 1;
        if (((ctrlPosition _body) select 3) > ((ctrlPosition _grp) select 3)) then {
            _grp ctrlSetScrollValues [1, -1];
            uiSleep 0.2;
            if (!isNull _d) then {
                private _max = (ctrlScrollValues _grp) param [0, -1];
                _grp ctrlSetScrollValues [0, -1];
                if (_max > 0) then {
                    _endAt = _max * 0.97;
                } else {
                    ["WARN", "Licence", format ["Défilement de la note illisible (%1) : lecture comptée comme faite", _max]] call comspec_overwatch_connect_fnc_log;
                    if !(uiNamespace getVariable ["COMSPEC_NDA_ReadDone", false]) then { call _setDone; };
                };
                uiSleep 0.05;
            };
        } else {
            if !(uiNamespace getVariable ["COMSPEC_NDA_ReadDone", false]) then { call _setDone; };
        };
    };
    if (isNull _d) exitWith {};
    private _v = ((ctrlScrollValues _grp) param [0, 0]) max 0;
    private _pct = ((round (100 * _v / _endAt)) max 0) min 100;
    if (_v >= _endAt && {!(uiNamespace getVariable ["COMSPEC_NDA_ReadDone", false])}) then { call _setDone; };
    private _done = uiNamespace getVariable ["COMSPEC_NDA_ReadDone", false];
    private _pack = uiNamespace getVariable ["COMSPEC_NDA_Pack", []];
    private _now = [_pct, _done, _pack param [0, ""]];
    // Rien n'est réécrit si rien n'a changé.
    if (_now isNotEqualTo _last) then {
        _last = _now;
        (ctrlPosition _track) params ["_tx", "_ty", "_tw", "_th"];
        _fill ctrlSetPosition [_tx, _ty, _tw * (([_pct / 100, 1] select _done) max 0.002), _th];
        _fill ctrlCommit 0;
        private _licOk = ([] call comspec_overwatch_connect_fnc_licenceStatus) select 1;
        private _txt = switch (true) do {
            case _licOk: { _pack param [16, ""] };
            case _done: { _pack param [15, ""] };
            default { format [_pack param [14, "%1"], _pct] };
        };
        _label ctrlSetStructuredText parseText ((format ["<t size='0.68' color='%1'>%2</t>", ["#e8b84a", "#5cc76b"] select _done, _txt]) call comspec_overwatch_connect_fnc_tr);
    };
    uiSleep 0.12;
};
