// Assistant de dépannage MPU (fn_mpuDiag, app Réseau, volet « DÉPANNAGE MPU »).
// États simulés par des faits imposés (["eval", [faits]], uiNamespace COMSPEC_ATAK_MpuDiagSim) : aucune fonction d'ACRE2 ni de TFAR n'est appelée pour eux,
// aucune radio réglée, la voix par le maillage reste coupée. Une seule lecture réelle (["run"]) : mêmes lectures que l'app Réseau, par fn_radioGuard.
if !("mpudiag" call AT_fnc_groupe) exitWith {};
["mpu_diag_etats", {
    if (["comspec_atak_native_fnc_mpuDiag"] call AT_fnc_nil) exitWith {};
    private _base = ["sample"] call comspec_atak_native_fnc_mpuDiag;
    // [faits changés, étape attendue en tête, état attendu, correction attendue ("-" : sans importance)]
    private _cas = [
        ["tout OK", [], "", "", "-"],
        ["réglage OFF", [["setOn", false]], "setting", "KO", "settings"],
        ["profil Arcade", [["linkOn", false], ["arcade", true]], "real", "KO", "settings"],
        ["pas de radio", [["acre", "absent"], ["tfar", "absent"], ["radios", []], ["tsMod", ""]], "radio", "KO", "-"],
        ["aucune radio portée", [["radios", []]], "radio", "KO", "-"],
        ["TeamSpeak fermé", [["tsConn", "no"]], "ts", "KO", "-"],
        ["MPU5 éteinte", [["mpuOn", false]], "mpu", "KO", "power"],
        ["radio non pontée", [["bridge", []], ["pub", []]], "bridge", "KO", "bridge"],
        ["hors maillage", [["meshOk", false], ["meshWhy", "aucune passerelle"]], "mesh", "KO", "wave"]
    ];
    {
        _x params ["_nom", "_chg", "_id", "_etat", "_fix"];
        private _f = +_base;
        { _f set [_x select 0, _x select 1]; } forEach _chg;
        private _steps = ["eval", [_f]] call comspec_atak_native_fnc_mpuDiag;
        [(count _steps) isEqualTo 11, format ["%1 : %2 étapes au lieu de 11", _nom, count _steps]] call AT_fnc_check;
        [(_steps findIf { !((_x select 1) in ["OK", "KO", "ATTENTION", "INCONNU"]) || {(count _x) isNotEqualTo 7} }) < 0, format ["%1 : étape mal formée", _nom]] call AT_fnc_check;
        private _first = ["first", [_steps]] call comspec_atak_native_fnc_mpuDiag;
        if (_id isEqualTo "") then {
            [_first isEqualTo [], format ["%1 : échec inattendu %2", _nom, _first]] call AT_fnc_check;
            [((["chain", [_steps]] call comspec_atak_native_fnc_mpuDiag) findIf { (_x select 1) isNotEqualTo "done" }) < 0, format ["%1 : chaîne pas toute verte", _nom]] call AT_fnc_check;
        } else {
            [(_first param [0, ""]) isEqualTo _id && {(_first param [1, ""]) isEqualTo _etat}, format ["%1 : première étape %2 (%3) au lieu de %4 (%5)", _nom, _first param [0, "aucune"], _first param [1, ""], _id, _etat]] call AT_fnc_check;
            if (_fix isNotEqualTo "-") then { [(_first param [6, ""]) isEqualTo _fix, format ["%1 : correction %2 au lieu de %3", _nom, _first param [6, ""], _fix]] call AT_fnc_check; };
            [(_first param [4, ""]) isNotEqualTo "", format ["%1 : aucune action proposée", _nom]] call AT_fnc_check;
        };
        (format ["%1 : %2", _nom, _steps apply { [_x select 0, _x select 1] }]) call AT_fnc_info;
    } forEach _cas;
    // Réglage OFF sans droits : jamais de réglage changé, la marche à suivre est « à demander à l'admin ».
    private _off = +_base;
    _off set ["setOn", false];
    private _a = (["first", [["eval", [_off]] call comspec_atak_native_fnc_mpuDiag]] call comspec_atak_native_fnc_mpuDiag) param [4, ""];
    [(_a find "À demander à l'admin") isEqualTo 0, format ["réglage OFF sans droits : « %1 »", _a]] call AT_fnc_check;
    // Mauvais canal TeamSpeak : attention, pas blocage.
    private _ts = +_base;
    _ts set ["tsWant", "ACRE Opération"];
    _ts set ["tsChan", "Accueil"];
    private _tsS = (["eval", [_ts]] call comspec_atak_native_fnc_mpuDiag) select { (_x select 0) isEqualTo "ts" };
    [((_tsS param [0, []]) param [1, ""]) isEqualTo "ATTENTION", format ["mauvais canal TeamSpeak : %1", _tsS]] call AT_fnc_check;
    // Camarade sur une autre fréquence.
    private _fq = +_base;
    private _pr = +(_fq get "peer");
    _pr set ["freq", "148.500 MHz"];
    _pr set ["freqN", 148.5];
    _fq set ["peer", _pr];
    private _fqS = (["eval", [_fq]] call comspec_atak_native_fnc_mpuDiag) select { (_x select 0) isEqualTo "freq" };
    [((_fqS param [0, []]) param [1, ""]) isEqualTo "KO", format ["autre fréquence : %1", _fqS]] call AT_fnc_check;
    // Rapport : version, radio, une ligne par étape.
    private _rep = ["report", [["eval", [_off]] call comspec_atak_native_fnc_mpuDiag, _off]] call comspec_atak_native_fnc_mpuDiag;
    [(_rep find "COMSPEC ATAK") >= 0 && {(count (_rep splitString endl)) >= 13}, "rapport incomplet"] call AT_fnc_check;
    // Lecture réelle : aucune erreur, 11 étapes, moins de 5 ms.
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagSim", nil];
    private _ms = [];
    for "_i" from 1 to 5 do {
        uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []];
        (["run", [""]] call comspec_atak_native_fnc_mpuDiag) params ["_st", "_fa"];
        [(count _st) isEqualTo 11, format ["lecture réelle : %1 étapes", count _st]] call AT_fnc_check;
        _ms pushBack (_fa getOrDefault ["ms", 99]);
    };
    _ms sort true;
    (format ["lecture réelle : %1 ms (médiane), %2", _ms select 2, (["steps", [""]] call comspec_atak_native_fnc_mpuDiag) apply { [_x select 0, _x select 1] }]) call AT_fnc_info;
    [(_ms select 2) < 5, format ["lecture réelle trop lente : %1 ms", _ms select 2]] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["mpu_diag_volet", {
    if (["comspec_atak_native_fnc_mpuDiag", "comspec_atak_native_fnc_formRender"] call AT_fnc_nil) exitWith {};
    if !([] call AT_fnc_tel) exitWith { "téléphone absent" call AT_fnc_skip; };
    private _base = ["sample"] call comspec_atak_native_fnc_mpuDiag;
    // Section voix de l'app Réseau : bouton DÉPANNAGE MPU, même réglage décoché.
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagSim", nil];
    (call AT_fnc_etat) set ["netDiag", false];
    [["NETWORK"] call AT_fnc_page, "app Réseau pas ouverte"] call AT_fnc_check;
    private _ctl = (uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]) select { !isNull _x && {(ctrlText _x) isEqualTo ("DÉPANNAGE MPU" call comspec_atak_native_fnc_tr)} };
    [_ctl isNotEqualTo [], "bouton DÉPANNAGE MPU absent de l'app Réseau"] call AT_fnc_check;
    // Volet ouvert, tout OK (faits simulés).
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagSim", _base];
    ["open"] call comspec_atak_native_fnc_mpuDiag;
    uiSleep 1.5;
    [((call AT_fnc_etat) getOrDefault ["netDiag", false]) isEqualTo true, "volet non ouvert"] call AT_fnc_check;
    [diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_MpuDiagDrawn", -100]) < 5, "volet non dessiné"] call AT_fnc_check;
    // Aucun texte coupé : textes structurés à leur hauteur, boutons à leur largeur.
    { if ((ctrlType _x) in [1, 13]) then { [_x, "volet DÉPANNAGE MPU"] call AT_fnc_tient; }; } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]) select { !isNull _x });
    "volet DÉPANNAGE MPU, tout OK : chaîne verte, carte « Tout est prêt », 11 étapes cochées, liste des camarades, « Faut-il un relais ? » (3 cas + votre cas), boutons COPIER LE RAPPORT et réglages ; rien de coupé ni de superposé en portrait" call AT_fnc_visuel;
    ["mpu_depannage"] call AT_fnc_shot;
    // Radio non pontée : première étape en échec en tête, avec son bouton ACTIVER LE PONT RADIO.
    private _ko = +_base;
    _ko set ["bridge", []];
    _ko set ["pub", []];
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagSim", _ko];
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []];
    ["NETWORK"] call comspec_atak_native_fnc_pageRender;
    uiSleep 1;
    private _fixB = (uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]) select { !isNull _x && {(ctrlText _x) isEqualTo ("ACTIVER LE PONT RADIO" call comspec_atak_native_fnc_tr)} };
    [_fixB isNotEqualTo [], "bouton ACTIVER LE PONT RADIO absent"] call AT_fnc_check;
    { if ((ctrlType _x) in [1, 13]) then { [_x, "volet DÉPANNAGE MPU (KO)"] call AT_fnc_tient; }; } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]) select { !isNull _x });
    "volet DÉPANNAGE MPU, radio non pontée : case PONT rouge « (cassé ici) », carte « À corriger d'abord : Pont radio » avec l'action, bouton ACTIVER LE PONT RADIO ; étape 6 en rouge" call AT_fnc_visuel;
    ["mpu_depannage_ko"] call AT_fnc_shot;
    // Fermeture : retour à l'app Réseau.
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagSim", nil];
    uiNamespace setVariable ["COMSPEC_ATAK_MpuDiagC", []];
    ["close"] call comspec_atak_native_fnc_mpuDiag;
    uiSleep 1;
    [!((call AT_fnc_etat) getOrDefault ["netDiag", false]), "volet non refermé"] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;
