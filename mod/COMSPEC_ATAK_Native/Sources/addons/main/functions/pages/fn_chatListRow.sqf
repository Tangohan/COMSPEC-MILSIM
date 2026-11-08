/*
    Liste des conversations : SMS reçu d'un contact déjà listé : sa ligne change sur place (heure, dernier message,
    pastille des non-lus) et remonte en tête ; les lignes qu'elle dépasse descendent d'un rang. Aucun contrôle créé ni
    supprimé. Tous les contacts ont la même hauteur de ligne (fn_pageChat, _row).
    Contact absent de la liste, ou choix d'un destinataire en cours : l'appelant la redessine (fn_p2pReceive).
    Params : [nom du contact]. Retour : true si la ligne a été mise à jour.
*/
params [["_peer", "", [""]]];
disableSerialization;
private _order = uiNamespace getVariable ["COMSPEC_ATAK_ChatListOrder", []];
private _rank = _order find _peer;
if (_peer isEqualTo "" || {_rank < 0}) exitWith { false };
private _rows = uiNamespace getVariable ["COMSPEC_ATAK_ChatListRows", createHashMap];
private _row = _rows getOrDefault [_peer, []];
if (_row isEqualTo []) exitWith { false };
if (isNull (_row select 0) || {isNull (_row select 1)} || {isNull (_row select 2)}) exitWith { false };
// Remontée en tête : chaque contrôle garde sa place dans sa ligne, seule la ligne change de hauteur.
if (_rank > 0) then {
    private _above = _order select [0, _rank];
    if ((_above findIf { (_rows getOrDefault [_x, []]) isEqualTo [] }) >= 0) exitWith { _rank = -1; };
    private _move = {
        params ["_r", "_dy"];
        { (ctrlPosition _x) params ["_px", "_py", "_pw", "_ph"]; _x ctrlSetPosition [_px, _py + _dy, _pw, _ph]; _x ctrlCommit 0; } forEach (_r param [10, []]);
        _r set [5, (_r select 5) + _dy];
    };
    private _topY = (_rows get (_above select 0)) select 5;
    { private _r = _rows get _x; [_r, _r select 6] call _move; } forEach _above;
    [_row, _topY - (_row select 5)] call _move;
    _order deleteAt _rank;
    _order = [_peer] + _order;
    uiNamespace setVariable ["COMSPEC_ATAK_ChatListOrder", _order];
};
if (_rank < 0) exitWith { false };
_row params ["_cTime", "_cPrev", "_cBadge", "_tx", "_tw", "_y", "_rh", "_fs", "_ratio", "_pad"];
private _conv = ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["p2p", []]) select { (_x getOrDefault ["peer", ""]) isEqualTo _peer };
if (_conv isEqualTo []) exitWith { false };
private _unread = { (_x getOrDefault ["dir", ""]) isEqualTo "in" && {!(_x getOrDefault ["read", false])} } count _conv;
private _m = _conv select ((count _conv) - 1);
([_m getOrDefault ["body", ""], ""] call comspec_atak_native_fnc_chatParse) params ["", "", "_ptext"];
private _last = [_ptext, format ["%1 : %2", ("Vous") call comspec_atak_native_fnc_tr, _ptext]] select ((_m getOrDefault ["dir", ""]) isEqualTo "out");
private _on = _unread > 0;
private _acc = ([] call comspec_atak_native_fnc_accent) select 0;
private _dim = COMSPEC_ATAK_Th get "textDim";
_cTime ctrlSetText (_m getOrDefault ["time", ""]); // i18n:brut
_cTime ctrlSetTextColor ([_dim, _acc] select _on);
_cTime ctrlSetFont (COMSPEC_ATAK_FontSet select ([0, 1] select _on));
private _bdW = [0, ((_fs * 1.25) / _ratio) max ((_fs * 0.55 * (count str _unread) + _fs * 0.6) / _ratio)] select _on;
_cPrev ctrlSetText (_last select [0, 90]); // i18n:brut
_cPrev ctrlSetTextColor ([_dim, [0.78, 0.84, 0.81, 1]] select _on);
_cPrev ctrlSetPosition [_tx, _y + _rh * 0.52, _tw - _bdW - _pad * 0.4, _rh * 0.38];
_cPrev ctrlCommit 0;
_cBadge ctrlShow _on;
if (_on) then {
    _cBadge ctrlSetText (str (_unread min 99)); // i18n:brut
    _cBadge ctrlSetBackgroundColor _acc;
    _cBadge ctrlSetPosition [_tx + _tw - _bdW, _y + _rh * 0.53, _bdW, _fs * 1.15];
    _cBadge ctrlCommit 0;
};
true
