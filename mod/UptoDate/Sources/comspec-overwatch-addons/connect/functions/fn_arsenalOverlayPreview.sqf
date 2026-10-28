/*
    Fiche de la tenue choisie : nom, collection, auteur, date, badge d'état de synchro et sa phrase d'explication,
    pastilles (favori / mise en avant / obligatoire), portrait opérateur, pièces en icônes légendées (8 cases),
    contenu, état de la copie Documents, boutons d'action. Sans tenue : invite à choisir.
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

private _fav = if (_row isEqualTo []) then { false } else { if (_isCloud) then { _row select 6 } else { _row param [7, false] } };
private _feat = if (_row isEqualTo []) then { false } else { if (_isCloud) then { _row param [12, false] } else { _row param [8, false] } };
private _mand = if (_row isEqualTo []) then { false } else { if (_isCloud) then { _row param [13, false] } else { _row param [9, false] } };
private _badgeTxt = if (_row isEqualTo []) then { "" } else { if (_isCloud) then { _row param [14, ""] } else { _row param [10, ""] } };
private _avatarUrl = if (_row isEqualTo []) then { "" } else { if (_isCloud) then { _row param [15, ""] } else { _row param [11, ""] } };
private _author = if (_row isEqualTo []) then { "" } else { if (_isCloud) then { _row select 4 } else { _row param [6, ""] } };

private _head = _grp getVariable ["COMSPEC_ArsenalDetailHead", controlNull];
if (!isNull _head) then {
    _head ctrlSetStructuredText parseText (if (_has) then {
        format ["<t size='1.15' font='%3' color='#eef7f1'>%1</t><br/><t size='0.78' color='#8fa596'>%2</t>", _caption, (_meta) call comspec_overwatch_connect_fnc_tr, (_grp getVariable ["COMSPEC_ArsenalFonts", ["", "RobotoCondensedBold"]]) select 1]
    } else {
        ("<t size='1.05' color='#b5c7bb'>Choisissez une tenue dans la liste</t><br/><t size='0.8' color='#8fa596'>Ses pièces, son auteur, son portrait et son état s'affichent ici.</t>") call comspec_overwatch_connect_fnc_tr
    });
};

// Portrait opérateur (téléchargé comme sur ATAK).
(_grp getVariable ["COMSPEC_ArsenalAvatar", [controlNull, controlNull]]) params [["_avBg", controlNull], ["_avPic", controlNull]];
if (!isNull _avBg) then { _avBg ctrlShow _has; };
if (!isNull _avPic) then {
    _avPic ctrlShow false;
    _avPic ctrlSetText "";
    if (_has && {_avatarUrl isNotEqualTo ""}) then {
        private _cacheKey = format ["wardrobe_av_%1", [_avatarUrl] call comspec_overwatch_connect_fnc_arsenalHash];
        private _cached = missionNamespace getVariable ["COMSPEC_ArsenalAvatarCache", createHashMap];
        if (!(_cached isEqualType createHashMap)) then { _cached = createHashMap; };
        if (_cacheKey in _cached) then {
            private _local = _cached get _cacheKey;
            if (_local isNotEqualTo "") then { _avPic ctrlSetText _local; _avPic ctrlShow true; };
        } else {
            [_display, _avatarUrl, _cacheKey, _avPic] spawn {
                params ["_d", "_url", "_key", "_pic"];
                private _raw = [["COMSPECExtension", "DownloadBriefingSlideImage", [_url, _key]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
                private _local = "";
                if ((_raw isEqualType "") && {(_raw find "OK|") isEqualTo 0}) then { _local = _raw select [3]; };
                private _map = missionNamespace getVariable ["COMSPEC_ArsenalAvatarCache", createHashMap];
                if (!(_map isEqualType createHashMap)) then { _map = createHashMap; };
                _map set [_key, _local];
                missionNamespace setVariable ["COMSPEC_ArsenalAvatarCache", _map, false];
                if (isNull _d || {isNull _pic} || {_local isEqualTo ""}) exitWith {};
                private _g = _d getVariable ["COMSPEC_ArsenalOverlay", controlNull];
                if (isNull _g) exitWith {};
                private _r = (_g getVariable ["COMSPEC_ArsenalShown", []]) param [_g getVariable ["COMSPEC_ArsenalSelected", -1], []];
                private _still = if ((_g getVariable ["COMSPEC_ArsenalTab", "local"]) isEqualTo "cloud") then {
                    (_r param [15, ""]) isEqualTo _url
                } else {
                    (_r param [11, ""]) isEqualTo _url
                };
                if (!_still) exitWith {};
                _pic ctrlSetText _local;
                _pic ctrlShow true;
            };
        };
    };
};

// Badge d'état, à la largeur de son texte, et la phrase qui dit quoi faire.
private _badge = _grp getVariable ["COMSPEC_ArsenalBadge", controlNull];
private _noteC = _grp getVariable ["COMSPEC_ArsenalBadgeNote", controlNull];
private _flagsC = _grp getVariable ["COMSPEC_ArsenalDetailFlags", controlNull];
private _showBadge = _has && {_label isNotEqualTo ""};
if (!isNull _badge) then {
    _badge ctrlShow _showBadge;
    if (_showBadge) then {
        _badge ctrlSetText (" " + toUpper ((_label) call comspec_overwatch_connect_fnc_tr) + " ");
        _badge ctrlSetBackgroundColor _rgba;
        (ctrlPosition _badge) params ["_bx", "_by2", "", "_bh"];
        _badge ctrlSetPosition [_bx, _by2, (ctrlTextWidth _badge) + 0.012, _bh];
        _badge ctrlCommit 0;
    };
};
if (!isNull _flagsC) then {
    private _chips = [];
    if (_has && {_mand}) then { _chips pushBack format ["<t color='#f2ab33'>%1</t>", if (_badgeTxt isNotEqualTo "") then { _badgeTxt } else { ("Obligatoire") call comspec_overwatch_connect_fnc_tr }]; };
    if (_has && {_feat} && {!_mand}) then { _chips pushBack format ["<t color='#7ec8ff'>%1</t>", ("Mise en avant") call comspec_overwatch_connect_fnc_tr]; };
    if (_has && {_fav}) then { _chips pushBack format ["<t color='#f0d060'>%1</t>", ("Favori") call comspec_overwatch_connect_fnc_tr]; };
    if (_has && {_author isNotEqualTo ""}) then { _chips pushBack format ["<t color='#b5c7bb'>%1</t>", format [("Envoyée par %1") call comspec_overwatch_connect_fnc_tr, _author]]; };
    _flagsC ctrlSetStructuredText parseText (if (_chips isEqualTo []) then { "" } else { format ["<t size='0.78'>%1</t>", _chips joinString "  ·  "] });
};
if (!isNull _noteC) then {
    _noteC ctrlSetStructuredText parseText (if (_has) then { format ["<t size='0.78' color='#c4d2c8'>%1</t>", (_note) call comspec_overwatch_connect_fnc_tr] } else { "" });
};

// Copie locale Documents (liste site gardée sur ce PC).
private _syncC = _grp getVariable ["COMSPEC_ArsenalDetailSync", controlNull];
if (!isNull _syncC) then {
    (_grp getVariable ["COMSPEC_ArsenalLocalSync", [false, "", ""]]) params [["_syncOk", false], ["_syncPath", ""], ["_syncAt", ""]];
    private _txt = switch (true) do {
        case (!_has): { "" };
        case (_syncOk): {
            format ["<t size='0.72' color='#5cc76b'>%1</t>", format [
                ("Copie locale Documents prête%1") call comspec_overwatch_connect_fnc_tr,
                if (_syncAt isEqualTo "") then { "" } else { format [" · %1", _syncAt] }
            ]]
        };
        default {
            format ["<t size='0.72' color='#e0a050'>%1</t>", ("Pas encore de copie locale dans Documents (Actualiser pour la créer)") call comspec_overwatch_connect_fnc_tr]
        };
    };
    if (_has && {_syncPath isNotEqualTo ""}) then {
        _syncC ctrlSetTooltip format [("Emplacement : %1") call comspec_overwatch_connect_fnc_tr, _syncPath];
    } else {
        _syncC ctrlSetTooltip "";
    };
    _syncC ctrlSetStructuredText parseText _txt;
};

// Pièces : icône et légende (traduite) ; info-bulle avec le nom de l'objet.
// Afficher aussi sans image (mods absents) : la case reste utile avec le libellé.
private _icons = [_loadout] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
private _slots = _icons select { (_x select 1) isNotEqualTo "" || {(_x select 3) isNotEqualTo ""} };
{
    _x params ["_cb", "_p", "_cap"];
    private _on = _forEachIndex < (count _slots);
    { _x ctrlShow _on; } forEach [_cb, _p, _cap];
    if (_on) then {
        (_slots select _forEachIndex) params ["_kind", "", "_pic", "_dn"];
        if (_pic isNotEqualTo "") then { _p ctrlSetText _pic; } else { _p ctrlSetText ""; };
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
