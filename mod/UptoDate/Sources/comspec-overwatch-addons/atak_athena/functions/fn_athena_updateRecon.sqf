/*
    Remplit le formulaire Reco du téléphone (grille, listes, journal).
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Recon_group", controlNull];
if (isNull _group) exitWith {};

["recon"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _pos = [] call comspec_overwatch_connect_fnc_reconLookPos;
if (!(_pos isEqualType []) || {(count _pos) < 3}) then { _pos = getPosASL player; };
uiNamespace setVariable ["COMSPEC_ReconNote_Pos", _pos];

private _grid = mapGridPosition (ASLToAGL _pos);
private _hint = _group controlsGroupCtrl 9781;
if (!isNull _hint) then {
    _hint ctrlSetStructuredText parseText format [
        "<t size='0.92'>Grille %1 · pointé sous le regard, sinon sous vos pieds.</t>",
        _grid
    ];
};

private _combo = _group controlsGroupCtrl 9784;
if (!isNull _combo) then {
    lbClear _combo;
    {
        _x params ["_label", "_code"];
        private _i = _combo lbAdd _label;
        _combo lbSetData [_i, _code];
    } forEach [
        ["Véhicule", "vehicle"],
        ["Groupe armé", "armed_group"],
        ["Position statique", "static"],
        ["Obstacle / mine", "mine"],
        ["Civil", "civilian"],
        ["Infrastructure", "infrastructure"],
        ["Autre", "other"]
    ];
    _combo lbSetCurSel 0;
};

private _conf = _group controlsGroupCtrl 9785;
if (!isNull _conf) then {
    lbClear _conf;
    private _i0 = _conf lbAdd "Vu direct";
    _conf lbSetData [_i0, "vu_direct"];
    private _i1 = _conf lbAdd "Rapporté";
    _conf lbSetData [_i1, "rapporte"];
    _conf lbSetCurSel 0;
};

private _edit = _group controlsGroupCtrl 9782;
if (!isNull _edit) then { _edit ctrlSetText ""; };

private _log = missionNamespace getVariable ["COMSPEC_ReconNoteLog", []];
if (!(_log isEqualType [])) then { _log = []; };
private _recent = _group controlsGroupCtrl 9786;
if (!isNull _recent) then {
    if ((count _log) < 1) then {
        _recent ctrlSetStructuredText parseText "<t size='0.82' color='#8aa0b4'>Aucune note envoyée depuis ce téléphone pour l’instant.</t>";
    } else {
        private _lines = ["<t size='0.78' color='#9ADCF5'>Dernières notes</t>"];
        {
            if (!(_x isEqualType []) || {(count _x) < 3}) then { continue };
            _x params ["_lab", "_txt", "_g"];
            private _tail = if (_txt isEqualTo "") then { "" } else { format [" — %1", _txt] };
            _lines pushBack format ["<t size='0.76'>%1 · %2%3</t>", _lab, _g, _tail];
        } forEach (_log select [0, 6]);
        _recent ctrlSetStructuredText parseText (_lines joinString "<br/>");
    };
};
