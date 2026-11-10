/*
    Actions de l'app « Contrôle ID » (fn_idPage).
      "fold" clé        ouvre ou replie un bloc de la fiche (Contrôle, Signaler, Partager, détail)
      "tab" nom         onglet (check | history)          "select" i   rouvre la fiche i de l'historique
      "level" n         lecture N1 MRZ (2 s), N2 puce (2,5 s, 1,5 m), N3 base (2 à 3,4 s selon le signal) : animation dans l'app
                        (fn_scanAnim), rien de bloqué ; résultat ou refus portés par la fiche (fn_idHistory)
      "ask"             redemander les papiers (document plus présenté)   "files"  passage aux fichiers (app Fichiers)
      "view"            revoir le document (visionneuse)  "transmit"   envoi à Athena    "frm"  fiche FRM pré-remplie
      "photo"           photo du document (jointe à la FRM)    "salute" / "spotrep"   compte rendu pré-rempli
      "share" / "nfc"   fiche du contrôle partagée (Bluetooth, NFC)   "mobile"  QR « Sur mon téléphone »  (ATAK 3.0.2)
      "level" 4         N4 : vérification biométrique au SEEK II (ouvert sur la personne ; son verdict remplit N4)
      "pid"             confirmation PID de la personne contrôlée (HVT ou recherchée identifiée)
*/
params [["_act", ""], ["_arg", ""]];
private _st = uiNamespace getVariable ["comspec_sse_idApp", createHashMap];
uiNamespace setVariable ["comspec_sse_idApp", _st];
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then { ["IDCHECK"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
private _say = { params ["_type", "_msg"]; [_type, _msg call comspec_sse_fnc_tr, 4, 30, "IDCHECK"] call comspec_atak_native_fnc_notify; };
if (_act isEqualTo "fold") exitWith { private _k = "open_" + _arg; _st set [_k, !(_st getOrDefault [_k, false])]; call _render; true };
if (_act isEqualTo "tab") exitWith { _st set ["tab", _arg]; call _render; true };
if (_act isEqualTo "select") exitWith { ["select", [_arg]] call comspec_sse_fnc_idHistory; _st set ["tab", "check"]; call _render; true };
private _rec = ["current"] call comspec_sse_fnc_idHistory;
if ((count _rec) isEqualTo 0) exitWith { ["WARNING", "Aucun contrôle en cours"] call _say; false };

switch (_act) do {
    case "level": {
        private _lvl = (_arg max 1) min 4;
        private _h = _rec get "docId";
        private _subj = _rec getOrDefault ["subj", objNull];
        if (!(_subj isEqualType objNull) || {_subj isEqualTo player}) then { _subj = objNull; };
        if (["busy", ["idcheck"]] call comspec_sse_fnc_scanAnim) exitWith {};
        // N4 : la vérification biométrique se fait au SEEK II (le téléphone affiche son résultat, fn_idHistory « level » 4).
        if (_lvl isEqualTo 4) exitWith {
            if (isNull _subj) exitWith { ["fail", [_h, 4, "far", "Comparaison du visage : la personne doit être devant vous (3 m)."]] call comspec_sse_fnc_idHistory; };
            if !([player, "seek"] call comspec_sse_fnc_hasEquipment) exitWith { ["fail", [_h, 4, "gear", "Vérification biométrique : terminal SEEK II requis."]] call comspec_sse_fnc_idHistory; };
            _rec set ["seekWait", true];
            [_subj] call comspec_sse_fnc_openSeek;
        };
        // Analyse animée dans l'app (étapes du lecteur), sans barre ACE ni blocage ; la base dépend du signal réseau.
        private _bars = if (isNil "comspec_atak_native_fnc_linkQuality") then { 4 } else { ([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 4] };
        private _net = 0.35 * (4 - ((_bars max 0) min 4));
        private _steps = switch (_lvl) do {
            case 1: { [["Lecture de la bande MRZ…", 0.9], ["Contrôle des sommes…", 0.6], ["Comparaison avec le recto…", 0.5]] };
            case 2: { [["Connexion NFC à la puce…", 0.8], ["Lecture des données (DG1, DG2)…", 1], ["Vérification de la signature…", 0.7]] };
            default { [["Connexion au réseau…", 0.5 + _net], ["Interrogation du fichier…", 1 + _net], ["Réponse du fichier…", 0.5]] };
        };
        ["send", ["begin", [player, format ["check%1", _lvl], _h]]] call comspec_sse_fnc_idClient;
        ["busy", [_h, _lvl]] call comspec_sse_fnc_idHistory;
        ["start", ["idcheck", _steps, "comspec_sse_idScanCtrl", {
            params ["_h", "_lvl"];
            ["send", ["check", [player, _h, _lvl]]] call comspec_sse_fnc_idClient;
        }, [_h, _lvl], _subj, {
            params ["_args", "_why"];
            ["fail", [_args select 0, _args select 1, "far", _why]] call comspec_sse_fnc_idHistory;
        }]] call comspec_sse_fnc_scanAnim;
    };
    // Document plus présenté : nouvelle demande des papiers à la même personne (comme l'action ACE « Demander les papiers »).
    case "ask": {
        private _t = _rec getOrDefault ["subj", objNull];
        if (!(_t isEqualType objNull) || {isNull _t} || {!alive _t} || {(player distance _t) > 3}) exitWith {
            ["fail", [_rec get "docId", 0, "far", "Approchez-vous de la personne (3 m) pour lui redemander ses papiers."]] call comspec_sse_fnc_idHistory;
        };
        ["send", ["begin", [player, "ask", _t]]] call comspec_sse_fnc_idClient;
        [2, "Papiers, s'il vous plaît…", { params ["_t"]; ["send", ["ask", [player, _t]]] call comspec_sse_fnc_idClient; }, [_t], _t] call comspec_sse_fnc_progressAction;
    };
    // Passage aux fichiers : app Fichiers sur ce document, recherche lancée (lien profond, retour au contrôle par la touche retour).
    case "files": {
        private _d = _rec get "doc";
        ["FICHIERS", createHashMapFromArray [["type", "QA"], ["number", _d getOrDefault ["number", ""]], ["country", _d getOrDefault ["country", ""]],
            ["docType", _d getOrDefault ["type", ""]], ["last", _d getOrDefault ["last", ""]], ["first", _d getOrDefault ["first", ""]],
            ["dob", _d getOrDefault ["dob", ""]], ["source", "idcheck"], ["run", true]]] call comspec_sse_fnc_openApp;
    };
    case "view": { [_rec get "doc", false] call comspec_sse_fnc_idViewerOpen; };
    case "pid": {
        private _subj = _rec getOrDefault ["subj", objNull];
        if (!(_subj isEqualType objNull) || {isNull _subj}) exitWith { ["WARNING", "Personne contrôlée introuvable"] call _say; };
        _rec set ["pid", true];
        ["send", ["pid", [player, _subj]]] call comspec_sse_fnc_idClient;
        call _render;
    };
    case "transmit": { [_rec] call comspec_sse_fnc_idAthenaSubmit; call _render; };
    case "frm": { [_rec] call comspec_sse_fnc_idFrm; };
    case "photo": { ["app"] call comspec_sse_fnc_idPhoto; };
    case "salute": { ["SALUTE"] call comspec_sse_fnc_idReport; };
    case "spotrep": { ["SPOTREP"] call comspec_sse_fnc_idReport; };
    // ATAK 3.0.2 et plus : la fiche (_rec de fn_idHistory) est acceptée telle quelle par l'app Bluetooth.
    case "share": { if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) then { ["shareIdCheck", _rec] call comspec_atak_native_fnc_btAction; }; };
    case "nfc": { if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) then { ["shareIdCheckNfc", _rec] call comspec_atak_native_fnc_btAction; }; };
    case "mobile": { if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) then { ["open", "controle"] call comspec_atak_native_fnc_mobileQr; }; };
};
true
