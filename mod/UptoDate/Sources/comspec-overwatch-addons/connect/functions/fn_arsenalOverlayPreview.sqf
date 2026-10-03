/*
    Fiche de la tenue choisie : nom, collection, auteur, date, icônes d'équipement (8 cases) et liste,
    boutons d'action adaptés à l'onglet. Sans tenue : invite à choisir.
    Params : [display, loadout, titre, méta (texte structuré)]
*/
params [["_display", displayNull, [displayNull]], ["_loadout", [], [[]]], ["_caption", "", [""]], ["_meta", "", [""]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
private _isCloud = (_grp getVariable ["COMSPEC_ArsenalTab", "cloud"]) isEqualTo "cloud";
private _has = _caption isNotEqualTo "";

private _head = _grp getVariable ["COMSPEC_ArsenalDetailHead", controlNull];
if (!isNull _head) then {
    _head ctrlSetStructuredText parseText (if (_has) then {
        format ["<t size='1.05' font='PuristaSemibold' color='#e2f7ef'>%1</t><br/><t size='0.78' color='#7f9c94'>%2</t>", _caption, _meta]
    } else {
        "<t size='0.95' color='#9bb0aa'>Choisissez une tenue dans la liste</t><br/><t size='0.78' color='#7f9c94'>Son équipement s'affiche ici.</t>"
    });
};
private _icons = [_loadout] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
private _withPic = _icons select { (_x select 2) isNotEqualTo "" };
{
    _x params ["_cb", "_p"];
    private _on = _forEachIndex < (count _withPic);
    _cb ctrlShow _on;
    _p ctrlShow _on;
    if (_on) then {
        (_withPic select _forEachIndex) params ["_kind", "", "_pic", "_dn"];
        _p ctrlSetText _pic;
        _p ctrlSetTooltip format ["%1 : %2", _kind, _dn];
    };
} forEach (_grp getVariable ["COMSPEC_ArsenalPreviewPics", []]);

private _names = _grp getVariable ["COMSPEC_ArsenalPreviewNames", controlNull];
if (!isNull _names) then {
    private _lines = (_icons select { (_x select 3) isNotEqualTo "" }) apply { format ["<t color='#7f9c94'>%1</t>  %2", _x select 0, _x select 3] };
    private _err = missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadoutError", ""];
    private _txt = switch (true) do {
        case (!_has): { "" };
        case (_caption isNotEqualTo "" && {_loadout isEqualTo []} && {_meta find "Chargement" >= 0}): { "<t color='#7f9c94'>Chargement de la tenue…</t>" };
        case ((count _lines) > 0): { _lines joinString "<br/>" };
        case (_err isEqualTo "too_large"): { "<t color='#ffb080'>Tenue trop lourde pour l'aperçu en jeu : ouvrez-la sur le poste Athena.</t>" };
        default { "<t color='#7f9c94'>Équipement non disponible pour cette tenue.</t>" };
    };
    _names ctrlSetStructuredText parseText format ["<t size='0.82' color='#d0dcd8'>%1</t>", _txt];
};
(_grp getVariable ["COMSPEC_ArsenalDetailBtns", []]) params [["_bApply", controlNull], ["_bKeep", controlNull], ["_bDel", controlNull]];
if (!isNull _bKeep) then { _bKeep ctrlSetText (["PARTAGER À L'ORGANISATION", "IMPORTER DANS MON ARSENAL"] select _isCloud); };
if (!isNull _bDel) then { _bDel ctrlSetText (["SUPPRIMER DE MON ARSENAL", "RETIRER DE LA COMMUNAUTÉ"] select _isCloud); };
{ if (!isNull _x) then { _x ctrlEnable _has; }; } forEach [_bApply, _bKeep, _bDel];
