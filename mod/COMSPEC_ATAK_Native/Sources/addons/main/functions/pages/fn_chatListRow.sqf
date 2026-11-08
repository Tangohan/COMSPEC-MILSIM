/*
    Liste des conversations : SMS reçu du contact déjà en tête de la liste : sa ligne change sur place (heure, dernier
    message, pastille des non-lus). Aucun contrôle créé ni supprimé.
    Autre contact : l'ordre de la liste change, l'appelant la redessine (fn_p2pReceive).
    Params : [nom du contact]. Retour : true si la ligne a été mise à jour.
*/
params [["_peer", "", [""]]];
disableSerialization;
if (_peer isEqualTo "" || {_peer isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_ChatListTop", ""])}) exitWith { false };
private _row = (uiNamespace getVariable ["COMSPEC_ATAK_ChatListRows", createHashMap]) getOrDefault [_peer, []];
if (_row isEqualTo []) exitWith { false };
_row params ["_cTime", "_cPrev", "_cBadge", "_tx", "_tw", "_y", "_rh", "_fs", "_ratio", "_pad"];
if (isNull _cTime || {isNull _cPrev} || {isNull _cBadge}) exitWith { false };
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
