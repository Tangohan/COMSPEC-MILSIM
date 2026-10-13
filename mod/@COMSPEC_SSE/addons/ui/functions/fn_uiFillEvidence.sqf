/*
    Écran Preuves : registre des pièces saisies et leur chaîne de possession.
    Liste : toutes les pièces du registre (la pièce du record courant en tête).
    Détail : fiche de la pièce choisie, journal complet et vérification d'intégrité.
*/
private _ctx = ["evidence"] call comspec_sse_fnc_uiDisplayCtx;
_ctx params ["_disp", "_idcDetail", "_idcList"];
if (isNull _disp) exitWith { false };

private _lb = _disp displayCtrl _idcList;
private _detail = _disp displayCtrl _idcDetail;
private _reg = missionNamespace getVariable ["comspec_sse_custody", createHashMap];
private _rec = [] call comspec_sse_fnc_uiGetRecord;
private _recPiece = if (isNull _rec) then { "" } else { _rec getVariable ["COMSPEC_SSE_PieceId", ""] };

private _ids = keys _reg;
_ids sort true;
if (_recPiece in _reg) then { _ids = [_recPiece] + (_ids - [_recPiece]); };

lbClear _lb;
{
    private _p = _reg get _x;
    private _ok = if (isNil "comspec_sse_fnc_custodyVerify") then { true } else { ([_x] call comspec_sse_fnc_custodyVerify) select 0 };
    private _i = _lb lbAdd ((format ["%1 | %2 | %3", _x, _p getOrDefault ["state", "?"], _p getOrDefault ["desc", ""]]) call comspec_sse_fnc_tr);
    _lb lbSetData [_i, _x];
    _lb lbSetTooltip (([_i, format ["Scellé %1 · détenteur %2", [_p getOrDefault ["seal", ""], "non posé"] select ((_p getOrDefault ["seal", ""]) isEqualTo ""), _p getOrDefault ["holder", "?"]]]) call comspec_sse_fnc_tr);
    if (!_ok) then { _lb lbSetColor [_i, [1, 0.42, 0.42, 1]]; };
} forEach _ids;

if (_ids isEqualTo []) exitWith {
    _lb lbAdd (("(aucune pièce au registre)") call comspec_sse_fnc_tr);
    _detail ctrlSetStructuredText parseText (("<t color='#8f8'>REGISTRE DES SCELLÉS</t><br/>Aucune pièce saisie pour l'instant.<br/><br/><t color='#9aa'>Collecter un objet ouvre une pièce (numéro P-dossier-0001). La mettre sous scellé lui donne un numéro de scellé. Photographie, exploitation, analyse et remise à un équipier s'inscrivent au journal de la pièce, chaque ligne liée à la précédente : une ligne modifiée après coup est signalée.</t>") call comspec_sse_fnc_tr);
    true
};

// Sélection → détail (posé une fois par contrôle).
if (isNil { _lb getVariable "comspec_sse_custodySel" }) then {
    _lb setVariable ["comspec_sse_custodySel", true];
    _lb ctrlAddEventHandler ["LBSelChanged", {
        params ["_ctrl", "_i"];
        if ((missionNamespace getVariable ["comspec_sse_uiScreen", ""]) isNotEqualTo "evidence") exitWith {};
        private _id = _ctrl lbData _i;
        if (_id isEqualTo "" || {isNil "comspec_sse_fnc_custodyText"}) exitWith {};
        private _ctx = ["evidence"] call comspec_sse_fnc_uiDisplayCtx;
        ((_ctx select 0) displayCtrl (_ctx select 1)) ctrlSetStructuredText parseText ((([_id] call comspec_sse_fnc_custodyText)) call comspec_sse_fnc_tr);
        missionNamespace setVariable ["comspec_sse_uiEvidenceSel", _id];
    }];
};

private _want = missionNamespace getVariable ["comspec_sse_uiEvidenceSel", ""];
private _idx = (_ids find _want) max 0;
_lb lbSetCurSel _idx;
if (!isNil "comspec_sse_fnc_custodyText") then {
    _detail ctrlSetStructuredText parseText ((([_ids select _idx] call comspec_sse_fnc_custodyText)) call comspec_sse_fnc_tr);
};
missionNamespace setVariable ["comspec_sse_uiEvidenceStore", _ids];
true
