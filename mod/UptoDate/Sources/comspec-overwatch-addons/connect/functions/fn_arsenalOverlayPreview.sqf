/*
    Fiche de la tenue choisie : nom, collection, auteur, date, badge d'état de synchro et sa phrase d'explication,
    pièces en icônes légendées (8 cases), contenu, boutons d'action adaptés à l'onglet et à l'état (Envoyer au site,
    Mettre à jour le site, Prendre la version du site, Importer…). Sans tenue : invite à choisir.
    Params : [display, loadout, titre, méta (texte structuré)] ; l'état vient de la ligne sélectionnée.
*/
params [["_display", displayNull, [displayNull]], ["_loadout", [], [[]]], ["_caption", "", [""]], ["_meta", "", [""]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
private _isCloud = (_grp getVariable ["COMSPEC_ArsenalTab", "local"]) isEqualTo "cloud";
private _has = _caption isNotEqualTo "";
private _row = (_grp getVariable ["COMSPEC_ArsenalShown", []]) param [_grp getVariable ["COMSPEC_ArsenalSelected", -1], []];
private _offline = _grp getVariable ["COMSPEC_ArsenalOffline", false];
private _state = switch (true) do {
    case (_row isEqualTo []): { "" };
    case (_isCloud): { switch (true) do { case (_row select 11): { "new" }; case ((_row select 10) in ["in", "diff"]): { _row select 10 }; case (_row select 8): { "mine" }; default { "out" }; } };
    default { _row select 4 };
};
private _info = [_state, _isCloud] call comspec_overwatch_connect_fnc_arsenalStateInfo;
_info params ["_label", "_rgba", "_note", "_hex"];

private _head = _grp getVariable ["COMSPEC_ArsenalDetailHead", controlNull];
if (!isNull _head) then {
    _head ctrlSetStructuredText parseText (if (_has) then {
        format ["<t size='1.2' font='%3' color='#eef7f1'>%1</t><br/><t size='0.8' color='#8fa596'>%2</t>", _caption, (_meta) call comspec_overwatch_connect_fnc_tr, (_grp getVariable ["COMSPEC_ArsenalFonts", ["", "RobotoCondensedBold"]]) select 1]
    } else {
        ("<t size='1.05' color='#b5c7bb'>Choisissez une tenue dans la liste</t><br/><t size='0.8' color='#8fa596'>Ses pièces, son contenu et son état sur le site s'affichent ici.</t>") call comspec_overwatch_connect_fnc_tr
    });
};

// Badge d'état, à la largeur de son texte, et la phrase qui dit quoi faire.
private _badge = _grp getVariable ["COMSPEC_ArsenalBadge", controlNull];
private _noteC = _grp getVariable ["COMSPEC_ArsenalBadgeNote", controlNull];
private _showBadge = _has && {_label isNotEqualTo ""};
if (!isNull _badge) then {
    _badge ctrlShow _showBadge;
    if (_showBadge) then {
        _badge ctrlSetText (" " + toUpper ((_label) call comspec_overwatch_connect_fnc_tr) + " ");
        _badge ctrlSetBackgroundColor _rgba;
        (ctrlPosition _badge) params ["_bx", "_by", "", "_bh"];
        _badge ctrlSetPosition [_bx, _by, (ctrlTextWidth _badge) + 0.012, _bh];
        _badge ctrlCommit 0;
    };
};
if (!isNull _noteC) then {
    _noteC ctrlSetStructuredText parseText (if (_has) then { format ["<t size='0.8' color='#c4d2c8'>%1</t>", (_note) call comspec_overwatch_connect_fnc_tr] } else { "" });
};

// Pièces : icône et légende (traduite) ; info-bulle avec le nom de l'objet.
private _icons = [_loadout] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
private _withPic = _icons select { (_x select 2) isNotEqualTo "" };
{
    _x params ["_cb", "_p", "_cap"];
    private _on = _forEachIndex < (count _withPic);
    { _x ctrlShow _on; } forEach [_cb, _p, _cap];
    if (_on) then {
        (_withPic select _forEachIndex) params ["_kind", "", "_pic", "_dn"];
        _p ctrlSetText _pic;
        _p ctrlSetTooltip format ["%1 : %2", (_kind) call comspec_overwatch_connect_fnc_tr, _dn];
        _cap ctrlSetText toUpper ((_kind) call comspec_overwatch_connect_fnc_tr);
        _cap ctrlSetTooltip _dn;
    };
} forEach (_grp getVariable ["COMSPEC_ArsenalPreviewPics", []]);

// Contenu, dans un groupe qui défile : la hauteur suit le texte.
private _names = _grp getVariable ["COMSPEC_ArsenalPreviewNames", controlNull];
if (!isNull _names) then {
    private _lines = (_icons select { (_x select 3) isNotEqualTo "" }) apply { format ["<t color='#8fa596'>%1</t>   %2", (_x select 0) call comspec_overwatch_connect_fnc_tr, _x select 3] };
    private _err = missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadoutError", ""];
    private _txt = switch (true) do {
        case (!_has): { "" };
        case (_loadout isEqualTo [] && {_meta find "Chargement" >= 0}): { format ["<t color='#8fa596'>%1</t>", ("Chargement de la tenue…") call comspec_overwatch_connect_fnc_tr] };
        case ((count _lines) > 0): { _lines joinString "<br/>" };
        case (_err isEqualTo "too_large"): { format ["<t color='#ffb080'>%1</t>", ("Tenue trop lourde pour l'aperçu en jeu : ouvrez-la sur le poste Athena.") call comspec_overwatch_connect_fnc_tr] };
        default { format ["<t color='#8fa596'>%1</t>", ("Équipement non disponible pour cette tenue.") call comspec_overwatch_connect_fnc_tr] };
    };
    _names ctrlSetStructuredText parseText format ["<t size='0.84' color='#d6e2da'>%1</t>", _txt];
    (ctrlPosition _names) params ["_nx", "_ny", "_nw"];
    _names ctrlSetPosition [_nx, _ny, _nw, (ctrlTextHeight _names) max 0.01];
    _names ctrlCommit 0;
};

// Actions selon l'onglet et l'état.
(_grp getVariable ["COMSPEC_ArsenalDetailBtns", []]) params [["_bApply", controlNull], ["_bKeep", controlNull], ["_bDel", controlNull]];
private _keep = if (_isCloud) then {
    switch (_state) do {
        case "in": { ["DÉJÀ DANS MON ARSENAL", false, "Cette tenue est déjà dans votre arsenal, identique."] };
        case "diff": { ["METTRE À JOUR MON ARSENAL", true, "Remplace la tenue du même nom de votre arsenal par celle-ci."] };
        default { ["IMPORTER DANS MON ARSENAL", true, "Ajoute cette tenue à votre arsenal ACE, sur cet ordinateur."] };
    }
} else {
    switch (_state) do {
        case "new";
        case "gone": { ["ENVOYER AU SITE", true, "Envoie cette tenue au site : elle sera partagée à l'organisation."] };
        case "local";
        case "diff": { ["METTRE À JOUR LE SITE", true, "Remplace la version du site par la vôtre."] };
        case "remote": { ["PRENDRE LA VERSION DU SITE", true, "Remplace la tenue de votre arsenal par la version du site."] };
        case "comm": { ["PARTAGER SOUS MON NOM", true, "Envoie cette tenue au site comme la vôtre."] };
        default { ["DÉJÀ SUR LE SITE", false, "Cette tenue est identique sur le site."] };
    }
};
_keep params ["_kText", "_kOn", "_kTip"];
if (!isNull _bKeep) then {
    _bKeep ctrlSetText ((_kText) call comspec_overwatch_connect_fnc_tr);
    _bKeep ctrlSetTooltip ((_kTip) call comspec_overwatch_connect_fnc_tr);
    _bKeep ctrlEnable (_has && {_kOn} && {!_offline});
};
if (!isNull _bDel) then {
    private _mineCloud = _isCloud && {(_row param [8, false]) || {!(_grp getVariable ["COMSPEC_ArsenalHasHash", false])}};
    _bDel ctrlSetText (((["SUPPRIMER DE MON ARSENAL", "RETIRER DU SITE"] select _isCloud)) call comspec_overwatch_connect_fnc_tr);
    _bDel ctrlSetTooltip ((([
        "Supprime cette tenue de votre arsenal ACE, sur cet ordinateur. Celle du site reste.",
        "Retire cette tenue des tenues de l'organisation. Seul son auteur ou un responsable peut le faire."
    ] select _isCloud)) call comspec_overwatch_connect_fnc_tr);
    _bDel ctrlEnable (_has && {!_isCloud || {_mineCloud && {!_offline}}});
};
if (!isNull _bApply) then { _bApply ctrlEnable _has; };
