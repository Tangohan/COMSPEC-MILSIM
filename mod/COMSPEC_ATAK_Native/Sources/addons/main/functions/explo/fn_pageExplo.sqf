/*
    App Explosifs : charges du joueur et leur état d'un coup d'œil, mise à feu une par une ou en séquence, avec confirmation.
    Déclencheur réseau (fn_exploNet) : liste des charges liées, armer, désarmer, déclencher à distance, motif clair sans liaison.
    Page dessinée quand la structure change (charge ajoutée ou disparue, confirmation, séquence) ; le reste se met à jour
    sur place une fois par seconde, page ouverte seulement (fn_exploAction "live"). Sans ACE ou sans objet : ce qu'il manque.
*/
disableSerialization;
["init"] call comspec_atak_native_fnc_exploAction;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Explo", createHashMap];
private _th = [] call comspec_atak_native_fnc_theme;
private _surf = _th get "surface";
private _icon = "\z\comspec_atak_native\addons\main\data\app_explo.paa";
private _itemIcon = "\z\comspec_atak_native\addons\main\data\item_netdetonator.paa";
private _onAlert = ["hex", _th get "onState"] call comspec_atak_native_fnc_theme;
private _rows = [];
private _conf = _s getOrDefault ["confirm", createHashMap];

// Confirmation de mise à feu : écran à part, boutons loin de celui qui l'a ouverte ; FEU n'apparaît qu'après un court délai.
if ((count _conf) > 0) exitWith {
    _rows pushBack ["banner", format ["<t size='1.3' font='COMSPEC_BarlowBold' color='%1'>CONFIRMER LA MISE À FEU</t>", _onAlert], _th get "alert"];
    _rows pushBack ["text", _conf get "text", _surf];
    _rows pushBack ["text", "", _surf, "COMSPEC_ATAK_ExploConfirmT"];
    _rows pushBack ["big", [["ANNULER", { ["askNo"] call comspec_atak_native_fnc_exploAction; }, "", true, "Rien ne part"]]];
    _rows pushBack ["gap"];
    _rows pushBack ["buttons", [[_conf get "label", { ["askYes"] call comspec_atak_native_fnc_exploAction; }, "red"]], "exploFireRow"];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    ["exploFireRow", false] call comspec_atak_native_fnc_formSet;
    ["confirmShown"] call comspec_atak_native_fnc_exploAction;
    ["liveOn"] call comspec_atak_native_fnc_exploAction;
    true
};

private _list = [true] call comspec_atak_native_fnc_exploList;
private _sel = _s getOrDefault ["sel", []];
private _queue = _s getOrDefault ["queue", []];
private _hasAce = isClass (configFile >> "CfgPatches" >> "ace_explosives");
private _nItem = { _x isEqualTo "COMSPEC_ATAK_NetDetonator" } count (items player);
private _netList = _list select { (_x get "kind") isEqualTo "net" };
private _fireable = _list select { (_x get "kind") in ["net", "atak", "command", "vanilla"] };

_rows pushBack ["header", "Explosifs", format ["%1 charge(s) posée(s) · %2 sur le réseau", count _list, count _netList], []];
_rows pushBack ["text", ["linkText"] call comspec_atak_native_fnc_exploAction, _surf, "COMSPEC_ATAK_ExploLink"];

// Ce qu'il manque, en clair.
if (!_hasAce) then {
    _rows pushBack ["status", _itemIcon, _th get "warn", "ACE Explosifs non chargé", "Le déclencheur réseau passe par les charges d'ACE. Sans ce mod, seules vos charges télécommandées du jeu apparaissent ici.", "", {}];
} else {
    if (_nItem isEqualTo 0 && {_netList isEqualTo []}) then {
        _rows pushBack ["status", _itemIcon, _th get "info", "Aucun déclencheur réseau", "Pour commander une charge d'ici, sans clacker et à distance, il faut un déclencheur réseau dans l'inventaire.", "Posez la charge, puis ACE : charge > Déclencheur > Déclencheur réseau", {}];
    };
    if (_nItem > 0) then {
        _rows pushBack ["text", format ["<t size='0.85' color='%1'>Déclencheurs réseau : %2. À la pose, ACE : charge > Déclencheur > Déclencheur réseau. Déjà posée au clacker : Explosifs > Lier au réseau, à moins de 4 m.</t>", _th get "hexTextDim", _nItem]];
    };
};

// Séquence en cours : bandeau et compte à rebours mis à jour sur place.
if ((count _queue) > 0) then {
    _rows pushBack ["banner", format ["<t size='1.3' font='COMSPEC_BarlowBold' color='%1'>MISE À FEU EN COURS</t>", _onAlert], _th get "alert"];
    _rows pushBack ["text", ["seqText"] call comspec_atak_native_fnc_exploAction, _surf, "COMSPEC_ATAK_ExploSeqT"];
    _rows pushBack ["big", [["ANNULER LA SÉQUENCE", { ['cancel'] call comspec_atak_native_fnc_exploAction; }, "red"]]];
};

// Charges, dans l'ordre de pose (numéros repris sur la carte).
if (_list isEqualTo []) then {
    _rows pushBack ["empty", _icon, "Aucune charge posée", ["Vos charges apparaîtront ici dès la pose.", "Posez un explosif avec ACE : il apparaîtra ici dès la pose."] select _hasAce];
} else {
    _rows pushBack ["section", "Charges", "C1 = première posée, même numéro sur la carte"];
    if ((count _netList) > 1) then {
        _rows pushBack ["buttons", [
            ["TOUT ARMER", { ["armAll"] call comspec_atak_native_fnc_exploAction; }],
            ["TOUT DÉSARMER", { ["safeAll"] call comspec_atak_native_fnc_exploAction; }]
        ]];
    };
    {
        ([_x, _forEachIndex + 1, _sel] call comspec_atak_native_fnc_exploRow) params ["_txt", "_btns"];
        _rows pushBack ["text", _txt, _surf, format ["COMSPEC_ATAK_ExploCard%1", _forEachIndex]];
        _rows pushBack ["buttons", _btns apply {
            _x params ["_lb", "_code", "_style", "_en", "_tip"];
            [_lb, _code, [_style isEqualTo "main", "red"] select (_style isEqualTo "red"), _en, _tip]
        }, format ["exploBtn%1", _forEachIndex]];
        _rows pushBack ["gap"];
    } forEach _list;
};

// Séquence : plusieurs charges cochées, délai et intervalle choisis, sécurité à lever.
if (_fireable isNotEqualTo [] && {(count _queue) isEqualTo 0}) then {
    private _delay = _s getOrDefault ["delay", 5];
    private _gap = _s getOrDefault ["gap", 1];
    _rows pushBack ["section", "Séquence", "Plusieurs charges, une à la fois"];
    _rows pushBack ["switch", "Lever la sécurité de mise à feu", _s getOrDefault ["armed", false], { ['safety'] call comspec_atak_native_fnc_exploAction; }, "Remise automatiquement après chaque séquence", false, "exploSafety"];
    _rows pushBack ["segment", "Délai avant la première", [0, 3, 5, 10, 30] apply { [format ["%1 s", _x], compile format ["['delay', %1] call comspec_atak_native_fnc_exploAction;", _x], _x isEqualTo _delay, "", _x] }, "", "exploDelay"];
    _rows pushBack ["segment", "Intervalle entre charges", [0, 0.5, 1, 2, 5] apply { [format ["%1 s", _x], compile format ["['gap', %1] call comspec_atak_native_fnc_exploAction;", _x], _x isEqualTo _gap, "", _x] }, "", "exploGap"];
    _rows pushBack ["text", ["seqInfo"] call comspec_atak_native_fnc_exploAction, [], "COMSPEC_ATAK_ExploSeqInfo"];
    private _seqB = ["seqBtns"] call comspec_atak_native_fnc_exploAction;
    _rows pushBack ["buttons", [
        ["TOUT COCHER", { ['selAll'] call comspec_atak_native_fnc_exploAction; }],
        ["DÉCOCHER", { ['selNone'] call comspec_atak_native_fnc_exploAction; }],
        [_seqB select 0, { ['askSeq'] call comspec_atak_native_fnc_exploAction; }, ["", "red"] select (_seqB select 1), _seqB select 1]
    ], "exploSeqRow"];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
["sigStore"] call comspec_atak_native_fnc_exploAction;
["liveOn"] call comspec_atak_native_fnc_exploAction;
true
