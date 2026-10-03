/* Pages d'information (C2, BFT, SSE, renseignement, BDA, briefing, photos, réglages, statut) : texte défilant. */
params ["_page"];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _rowH = (_l get "font") * 1.6;
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _h = { params ["_t"]; format ["<t color='#5cc76b' size='0.85'>%1</t>", _t] };
private _body = "";
private _buttons = [];

switch (_page) do {
    case "C2": {
        _body = format ["%1<br/>Unités suivies : %2<br/>Ordres : %3<br/>Marqueurs : %4<br/><br/>La carte reste la vue opérationnelle principale.", ["SITUATION C2"] call _h, count (_data getOrDefault ["units", createHashMap]), count ([] call comspec_atak_native_fnc_tasksAll), count (_data getOrDefault ["markers", createHashMap])];
    };
    case "BFT": {
        private _lines = [["BLUE FORCE TRACKING"] call _h];
        {
            private _obj = _y getOrDefault ["object", objNull];
            _lines pushBack format ["%1   %2   %3", _y getOrDefault ["callsign", _x], _y getOrDefault ["freshness", "LIVE"], if (isNull _obj) then { "------" } else { mapGridPosition _obj }];
        } forEach (_data getOrDefault ["units", createHashMap]);
        _body = _lines joinString "<br/>";
    };
    case "SSE": {
        _body = format ["%1<br/>Entrées renseignement : %2<br/><br/>Personnes, objets, sites, documents, biométrie.", ["EXPLOITATION SSE"] call _h, count (_data getOrDefault ["intel", createHashMap])];
    };
    case "INTEL": {
        _body = format ["%1<br/>Signalements, sources, dates et niveaux de confiance.<br/>Sélectionnez un contact sur la carte pour l'inspecter.", ["RENSEIGNEMENT"] call _h];
    };
    case "BDA": {
        _body = format ["%1<br/>Les comptes rendus de dégâts importés d'Athena apparaissent ici et sur la carte.", ["BATTLE DAMAGE ASSESSMENT"] call _h];
    };
    case "BRIEFING": {
        private _b = _data getOrDefault ["briefing", createHashMap];
        _body = format ["%1<br/>Page %2 / %3<br/>%4", ["BRIEFING"] call _h, (_b getOrDefault ["index", 0]) + 1, _b getOrDefault ["total", 0], if ((_b getOrDefault ["path", ""]) isEqualTo "") then { "Aucune image locale disponible." } else { format ["<img image='%1' size='8'/>", _b get "path"] }];
        _buttons = [["PRÉCÉDENT", { [-1] call comspec_atak_native_fnc_briefingStep; }], ["SUIVANT", { [1] call comspec_atak_native_fnc_briefingStep; }]];
    };
    case "PHOTOS": {
        private _lines = [["PHOTOS / RECON"] call _h];
        { _lines pushBack format ["%1 — %2", _x getOrDefault ["name", "Capture"], _x getOrDefault ["path", "format non affichable"]]; } forEach (_data getOrDefault ["photos", []]);
        if ((count _lines) isEqualTo 1) then { _lines pushBack "Aucune photo pour l'instant."; };
        _body = _lines joinString "<br/>";
    };
    case "SETTINGS": {
        _body = format ["%1<br/>Affichage : %2<br/>Détail carte : %3<br/>Labels : %4<br/>Traces : %5<br/>Notifications : %6<br/>Rafraîchissement BFT : %7 s<br/><br/>Ctrl+U sort ou range le téléphone porté : il reste dans le coin de l'écran et vous continuez à jouer.<br/>Ctrl+Maj+U le prend en main (souris) ou le repose. En main, les boutons du haut basculent vertical / horizontal et mini / plein écran.<br/>Touches modifiables dans les réglages CBA.", ["PARAMÈTRES"] call _h,
            ["Plein écran", "Mini"] select ((([] call comspec_atak_native_fnc_layoutGet) get "mode") isEqualTo "MINI"),
            profileNamespace getVariable ["COMSPEC_ATAK_MapDetail", 2], profileNamespace getVariable ["COMSPEC_ATAK_Labels", true], profileNamespace getVariable ["COMSPEC_ATAK_Trails", true],
            profileNamespace getVariable ["COMSPEC_ATAK_Notifications", true], profileNamespace getVariable ["COMSPEC_ATAK_BftRefresh", 3]];
    };
    case "STATUS": {
        _body = format ["%1<br/>Athena : %2<br/>Extension : %3<br/>Mod : %4<br/>Dernière synchro : %5<br/>Attente réseau : %6 s<br/>Interface : native-rsc-v1<br/><br/>Le terminal et la carte restent disponibles hors ligne.", ["STATUT SYSTÈME"] call _h,
            _state getOrDefault ["networkState", "OFFLINE"], ["DLL native (sans Overwatch)", "DLL Overwatch partagée (session unique)"] select ([] call comspec_atak_native_fnc_bridge), missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "?"],
            if ([] call comspec_atak_native_fnc_bridge) then { ["en attente", "assurée par Overwatch"] select (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) } else { if ((_data getOrDefault ["lastNetworkUpdate", -1]) < 0) then { "jamais" } else { format ["il y a %1 s", round (diag_tickTime - (_data get "lastNetworkUpdate"))] } },
            missionNamespace getVariable ["COMSPEC_SendBackoffSec", 0]];
    };
    default { _body = "Page indisponible."; };
};

private _textH = _bh - 2 * _pad - ([0, _rowH + _pad] select ((count _buttons) > 0));
private _text = ["COMSPEC_RscStructuredText", [_pad, _pad, _bw - 2 * _pad - 0.012, _textH]] call comspec_atak_native_fnc_pageCtrl;
_text ctrlSetStructuredText parseText _body;
_text ctrlSetPosition [_pad, _pad, _bw - 2 * _pad - 0.012, (ctrlTextHeight _text) max _textH];
_text ctrlCommit 0;

private _n = count _buttons;
if (_n > 0) then {
    private _bw2 = (_bw - (_n + 1) * _pad) / _n;
    {
        _x params ["_label", "_code"];
        private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * (_bw2 + _pad), _bh - _rowH - _pad, _bw2, _rowH], _label] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight (_l get "fontSmall");
        _b ctrlAddEventHandler ["ButtonClick", _code];
    } forEach _buttons;
};
true
