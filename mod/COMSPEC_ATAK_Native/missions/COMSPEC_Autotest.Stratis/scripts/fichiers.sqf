// Passage aux fichiers : lien profond vers l'app Fichiers (téléphone rangé et verrouillé), « vérifier les papiers »,
// HIT / NO HIT sur un document déclaré volé, contrôle ID à une seule source d'état, analyses animées dans l'app.
// Groupe « sse » ; s'appuie sur AT_fnc_demande et AT_fnc_ia de scripts\sse.sqf (chargé avant).
if !("sse" call AT_fnc_groupe) exitWith {};
AT_fnc_fichiersDoc = {
    params ["_u"];
    private _doc = [_u, createHashMapFromArray [["type", "id_card"], ["materialize", true]]] call comspec_sse_fnc_idIssue;
    private _d = ["doc", [_doc]] call comspec_sse_fnc_idRegistry;
    [_doc, _d]
};
AT_fnc_fichiersReponse = {
    params ["_n0"];
    [{ (count ((uiNamespace getVariable ["comspec_sse_fileApp", createHashMap]) getOrDefault ["history", []])) > _n0
        && {((uiNamespace getVariable ["comspec_sse_fileApp", createHashMap]) getOrDefault ["pending", "x"]) isEqualTo ""} }, 15] call AT_fnc_wait
};
AT_fnc_fichiersDerniere = { ((uiNamespace getVariable ["comspec_sse_fileApp", createHashMap]) getOrDefault ["history", []]) param [-1, createHashMap] };

["fichiers_lien_profond_verrouille", {
    if (["comspec_atak_native_fnc_openApp", "comspec_sse_fnc_openApp", "comspec_sse_fnc_filePage"] call AT_fnc_nil) exitWith {};
    if !("COMSPEC_ATAK_Phone" in (items player)) then { player addItem "COMSPEC_ATAK_Phone"; };
    [] call comspec_atak_native_fnc_close;
    uiSleep 1;
    // Écran de verrouillage forcé à la prochaine sortie : avant, la navigation était refusée et l'app restait la dernière ouverte.
    profileNamespace setVariable ["COMSPEC_ATAK_LockScreen", true];
    missionNamespace setVariable ["COMSPEC_ATAK_LockPending", true];
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    private _n0 = count ((uiNamespace getVariable ["comspec_sse_fileApp", createHashMap]) getOrDefault ["history", []]);
    ["FICHIERS", createHashMapFromArray [["type", "QW"], ["last", "INCONNU"], ["first", "PERSONNE"], ["run", true]]] call comspec_sse_fnc_openApp;
    [[{ !isNull ([] call comspec_atak_native_fnc_display) }, 6] call AT_fnc_wait, "téléphone non sorti par le lien profond"] call AT_fnc_check;
    uiSleep 1.5;
    private _locked = uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false];
    (format ["écran de verrouillage affiché : %1", _locked]) call AT_fnc_info;
    if (_locked) then { ["fichiers_verrouille"] call AT_fnc_shot; ["unlock"] call comspec_atak_native_fnc_lockScreen; };
    private _onPage = { ((call AT_fnc_etat) getOrDefault ["activePage", ""]) isEqualTo "FICHIERS" };
    [[_onPage, 5] call AT_fnc_wait, "app Fichiers non ouverte après le déverrouillage"] call AT_fnc_check;
    [[_n0] call AT_fnc_fichiersReponse, "requête QW du lien profond sans réponse"] call AT_fnc_check;
    private _r = call AT_fnc_fichiersDerniere;
    [(_r getOrDefault ["type", ""]) isEqualTo "QW" && {!(_r getOrDefault ["hit", true])}, format ["QW sur une personne inconnue : NO HIT attendu, reçu %1", _r getOrDefault ["codes", []]]] call AT_fnc_check;
    ["fichiers_qw_no_hit"] call AT_fnc_shot;
    // Touche retour : écran d'avant (lanceur), pas de page vide.
    [] call comspec_atak_native_fnc_back;
    uiSleep 1;
    [((call AT_fnc_etat) getOrDefault ["activePage", ""]) isEqualTo "LAUNCHER", "retour : lanceur attendu"] call AT_fnc_check;
    call AT_fnc_done;
}, 60] call AT_fnc_run;

["fichiers_verifier_papiers", {
    if (["comspec_sse_fnc_fileQuery", "comspec_sse_fnc_idViewerOnLoad", "AT_fnc_ia", "AT_fnc_demande"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_ia;
    uiSleep 2;
    ([_u] call AT_fnc_fichiersDoc) params ["_doc", "_d"];
    _u setVariable ["ace_captives_isSurrendering", true, true];
    [_u] call AT_fnc_demande;
    uiSleep 3;
    private _jetons = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
    private _h = ((keys _jetons) select { ((_jetons get _x) select 0) isEqualTo _u }) param [0, _doc];
    ["send", ["begin", [player, "view", _h]]] call comspec_sse_fnc_idClient;
    [2, "Examen du document…", { params ["_h"]; ["send", ["view", [player, _h]]] call comspec_sse_fnc_idClient; }, [_h], _u] call comspec_sse_fnc_progressAction;
    private _v = { !isNull (uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull]) };
    if !([[_v, 8] call AT_fnc_wait, "visionneuse non ouverte"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    private _n0 = count ((uiNamespace getVariable ["comspec_sse_fileApp", createHashMap]) getOrDefault ["history", []]);
    // Bouton « SCANNER (ATAK) » de la fenêtre du document : app Fichiers, numéro déjà saisi, recherche lancée.
    ["scan"] call comspec_sse_fnc_idViewerOnLoad;
    if (uiNamespace getVariable ["COMSPEC_ATAK_LockUp", false]) then { uiSleep 1; ["unlock"] call comspec_atak_native_fnc_lockScreen; };
    [[{ ((call AT_fnc_etat) getOrDefault ["activePage", ""]) isEqualTo "FICHIERS" }, 6] call AT_fnc_wait, "« Vérifier les papiers » n'ouvre pas l'app Fichiers"] call AT_fnc_check;
    uiSleep 0.6;
    private _scan = uiNamespace getVariable ["comspec_sse_fileScanCtrl", controlNull];
    [!isNull _scan, "animation de la requête absente"] call AT_fnc_check;
    ["fichiers_animation"] call AT_fnc_shot;
    [[_n0] call AT_fnc_fichiersReponse, "requête QA sans réponse"] call AT_fnc_check;
    private _r = call AT_fnc_fichiersDerniere;
    private _form = (uiNamespace getVariable ["comspec_sse_fileApp", createHashMap]) getOrDefault ["form", createHashMap];
    [(_form getOrDefault ["number", ""]) isEqualTo (_d getOrDefault ["number", "?"]), format ["numéro pré-rempli %1, attendu %2", _form getOrDefault ["number", ""], _d getOrDefault ["number", "?"]]] call AT_fnc_check;
    [(_r getOrDefault ["hit", false]) && {(_r getOrDefault ["source", ""]) isEqualTo "idcheck"}, format ["QA du document présenté : HIT attendu, reçu %1 %2", _r getOrDefault ["hit", "?"], _r getOrDefault ["codes", []]]] call AT_fnc_check;
    (format ["réponse %1 : %2 fiche(s), mentions %3", _r getOrDefault ["qrn", ""], count (_r getOrDefault ["records", []]), _r getOrDefault ["codes", []]]) call AT_fnc_info;
    ["fichiers_qa_hit"] call AT_fnc_shot;
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

["fichiers_document_vole", {
    if (["comspec_sse_fnc_fileQuery", "AT_fnc_ia"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_ia;
    uiSleep 1;
    ([_u] call AT_fnc_fichiersDoc) params ["_doc", "_d"];
    // Document déclaré volé dans la base locale du pays (comme un signalement du paquet Athena).
    private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
    private _k = format ["%1|%2|%3", _d get "country", _d get "type", _d get "number"];
    private _e = (_reg get "issued") getOrDefault [_k, []];
    [_e isNotEqualTo [], format ["document %1 absent de la base locale", _k]] call AT_fnc_check;
    if (_e isNotEqualTo []) then { _e set [3, "vole"]; };
    if !(["FICHIERS", 1] call AT_fnc_page) exitWith { deleteVehicle _u; call AT_fnc_done; };
    private _st = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
    _st set ["type", "QA"];
    _st set ["form", createHashMapFromArray [["number", _d get "number"]]];
    private _n0 = count (_st getOrDefault ["history", []]);
    ["run", "deep"] call comspec_sse_fnc_fileAction;
    [[_n0] call AT_fnc_fichiersReponse, "requête QA (volé) sans réponse"] call AT_fnc_check;
    private _r = call AT_fnc_fichiersDerniere;
    [(_r getOrDefault ["hit", false]) && {"stolen" in (_r getOrDefault ["codes", []])}, format ["document volé : HIT et mention « stolen » attendus, reçu %1", _r getOrDefault ["codes", []]]] call AT_fnc_check;
    ["fichiers_qa_vole_hit"] call AT_fnc_shot;
    // Numéro jamais délivré : NO HIT.
    _st set ["form", createHashMapFromArray [["number", "ZZ0000000"]]];
    _n0 = count (_st getOrDefault ["history", []]);
    ["run", "deep"] call comspec_sse_fnc_fileAction;
    [[_n0] call AT_fnc_fichiersReponse, "requête QA (inconnu) sans réponse"] call AT_fnc_check;
    _r = call AT_fnc_fichiersDerniere;
    [!(_r getOrDefault ["hit", true]), "numéro inconnu : NO HIT attendu"] call AT_fnc_check;
    ["fichiers_qa_no_hit"] call AT_fnc_shot;
    ["tab", "journal"] call comspec_sse_fnc_fileAction;
    uiSleep 1;
    ["fichiers_journal"] call AT_fnc_shot;
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

["controle_id_etat_unique", {
    if (["comspec_sse_fnc_scanAnim", "AT_fnc_ia", "AT_fnc_demande"] call AT_fnc_nil) exitWith {};
    private _u = call AT_fnc_ia;
    uiSleep 2;
    ([_u] call AT_fnc_fichiersDoc) params ["_doc"];
    _u setVariable ["ace_captives_isSurrendering", true, true];
    [_u] call AT_fnc_demande;
    uiSleep 3;
    private _jetons = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
    private _h = ((keys _jetons) select { ((_jetons get _x) select 0) isEqualTo _u }) param [0, _doc];
    ["send", ["begin", [player, "view", _h]]] call comspec_sse_fnc_idClient;
    [2, "Examen du document…", { params ["_h"]; ["send", ["view", [player, _h]]] call comspec_sse_fnc_idClient; }, [_h], _u] call comspec_sse_fnc_progressAction;
    uiSleep 4;
    closeDialog 0;
    private _rec = ["current"] call comspec_sse_fnc_idHistory;
    if !([(count _rec) > 0, "aucun contrôle en cours"] call AT_fnc_check) exitWith { deleteVehicle _u; call AT_fnc_done; };
    if !(["IDCHECK", 1.5] call AT_fnc_page) exitWith { deleteVehicle _u; call AT_fnc_done; };
    // N0 seul : pas de « conforme ».
    ["controle_n0_seulement"] call AT_fnc_shot;
    // N1 : animation du lecteur dans l'app, puis résultat (pas de barre ACE).
    ["level", 1] call comspec_sse_fnc_idAction;
    uiSleep 0.8;
    [!isNull (uiNamespace getVariable ["comspec_sse_idScanCtrl", controlNull]), "animation de la lecture MRZ absente"] call AT_fnc_check;
    ["controle_animation_mrz"] call AT_fnc_shot;
    [[{ 1 in ((["current"] call comspec_sse_fnc_idHistory) getOrDefault ["levels", createHashMap]) }, 10] call AT_fnc_wait, "N1 sans résultat après l'animation"] call AT_fnc_check;
    // Document plus présenté (jetons du serveur vidés) : refus porté par la fiche, même texte que la notification.
    { ((missionNamespace getVariable ["comspec_sse_idReg", createHashMap]) get "tokens") deleteAt _x; } forEach (keys ((missionNamespace getVariable ["comspec_sse_idReg", createHashMap]) get "tokens"));
    ["level", 3] call comspec_sse_fnc_idAction;
    [[{ ((["current"] call comspec_sse_fnc_idHistory) getOrDefault ["notice", []]) isNotEqualTo [] }, 10] call AT_fnc_wait, "refus du N3 (document plus présenté) non porté par la fiche"] call AT_fnc_check;
    private _n = (["current"] call comspec_sse_fnc_idHistory) getOrDefault ["notice", []];
    (format ["avis : %1", _n]) call AT_fnc_info;
    [(_n param [0, ""]) isEqualTo "gone", format ["code « gone » attendu, reçu %1", _n param [0, ""]]] call AT_fnc_check;
    uiSleep 1;
    ["controle_document_plus_presente"] call AT_fnc_shot;
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;

// SEEK II : contrôle rapide sur une IA connue (CONCORDANT), puis IA sans identité : ENROLL, SIGNER ET ENVOYER,
// retrouvée par QUERY et par la recherche QB de l'app Fichiers.
AT_fnc_seekVerdictAttendu = {
    params ["_u", ["_max", 40]];
    uiNamespace setVariable ["COMSPEC_SeekVerdict", nil];
    ["start", [_u]] call comspec_overwatch_connect_fnc_seekQuick;
    [{ !isNil { uiNamespace getVariable "COMSPEC_SeekVerdict" } }, _max] call AT_fnc_wait;
    uiNamespace getVariable ["COMSPEC_SeekVerdict", ""]
};
["seek_controle_rapide_connue", {
    if (["comspec_overwatch_connect_fnc_seekQuick", "comspec_sse_fnc_openSeek", "AT_fnc_ia"] call AT_fnc_nil) exitWith {};
    if !("COMSPEC_SSE_SEEKII" in (items player)) then { player addItem "COMSPEC_SSE_SEEKII"; };
    private _u = call AT_fnc_ia;
    _u setVariable ["COMSPEC_SSE_BioDb", "enrole", true];
    uiSleep 2;
    [_u] call comspec_sse_fnc_openSeek;
    uiSleep 2;
    ["seek_controle_ouvert"] call AT_fnc_shot;
    private _v = [_u] call AT_fnc_seekVerdictAttendu;
    (format ["verdict : %1", _v]) call AT_fnc_info;
    [str _v find "CONCORDANT" >= 0 || {(toLower str _v) find "concordant" >= 0}, format ["IA enrôlée : CONCORDANT attendu, reçu %1", _v]] call AT_fnc_check;
    ["seek_concordant"] call AT_fnc_shot;
    closeDialog 0;
    deleteVehicle _u;
    call AT_fnc_done;
}, 90] call AT_fnc_run;
["seek_enroll_signer_envoyer", {
    if (["comspec_overwatch_connect_fnc_seekQuick", "comspec_overwatch_connect_fnc_seekEnroll", "comspec_overwatch_connect_fnc_seekSend", "comspec_sse_fnc_fileQuery"] call AT_fnc_nil) exitWith {};
    if !("COMSPEC_SSE_SEEKII" in (items player)) then { player addItem "COMSPEC_SSE_SEEKII"; };
    private _u = call AT_fnc_ia;
    _u setVariable ["COMSPEC_SSE_BioDb", "absent", true];
    uiSleep 2;
    [_u] call comspec_sse_fnc_openSeek;
    uiSleep 2;
    private _v = [_u] call AT_fnc_seekVerdictAttendu;
    [(toLower str _v) find "inconnu" >= 0, format ["IA sans identité : INCONNU attendu, reçu %1", _v]] call AT_fnc_check;
    ["seek_inconnu"] call AT_fnc_shot;
    ["start"] call comspec_overwatch_connect_fnc_seekEnroll;
    uiSleep 1.5;
    ["seek_enroll"] call AT_fnc_shot;
    ["send"] call comspec_overwatch_connect_fnc_seekSend;
    [[{ (_u getVariable ["comspec_seek_rec", ""]) isNotEqualTo "" }, 25] call AT_fnc_wait, "SIGNER ET ENVOYER sans n° REC"] call AT_fnc_check;
    private _rec = _u getVariable ["comspec_seek_rec", ""];
    (format ["REC : %1", _rec]) call AT_fnc_info;
    uiSleep 2;
    ["seek_accuse"] call AT_fnc_shot;
    // Recherche ultérieure : QUERY du SEEK, puis app Fichiers (QB sur le REC).
    ["query"] call comspec_overwatch_connect_fnc_seekQuick;
    uiSleep 6;
    closeDialog 0;
    uiSleep 1;
    if (["FICHIERS", 1] call AT_fnc_page) then {
        private _st = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
        _st set ["type", "QB"];
        _st set ["form", createHashMapFromArray [["rec", _rec]]];
        private _n0 = count (_st getOrDefault ["history", []]);
        ["run", "deep"] call comspec_sse_fnc_fileAction;
        [[_n0] call AT_fnc_fichiersReponse, "requête QB sans réponse"] call AT_fnc_check;
        private _r = call AT_fnc_fichiersDerniere;
        [_r getOrDefault ["hit", false], format ["fiche SEEK %1 non retrouvée par QB (%2)", _rec, _r getOrDefault ["codes", []]]] call AT_fnc_check;
        ["fichiers_qb_seek"] call AT_fnc_shot;
    };
    deleteVehicle _u;
    call AT_fnc_done;
}, 150] call AT_fnc_run;
