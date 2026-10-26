/*
    Dépannage liaison : contrôles immédiats, sans rien couper.
    Reprend les détections existantes : DLL (fn_extensionStatus), contrôle d'installation de l'ATAK natif
    (fn_integrityCheck : PBO en double, versions mélangées, DLL périmée, mise à jour publiée), service Athéna,
    état de la liaison, compte, terminal, pertes, dernier pas à pas interrompu.
    Retour : [_rows, _verdict, _fix]
      _rows    [[niveau ("ok" | "info" | "warn" | "ko"), titre, valeur, cause et action, correction], ...]
      _verdict [niveau, libellé]
      _fix     correction proposée en premier : "reconnect" | "link" | "enable" | "athena" | "bug" | ""
    Textes déjà traduits (comspec_overwatch_connect_fnc_tr).
*/
private _T = { _this call comspec_overwatch_connect_fnc_tr };
private _rows = [];
private _add = { _rows pushBack _this; };

private _native = !isNil "comspec_atak_native_fnc_integrityCheck";
private _mode = [] call comspec_overwatch_connect_fnc_athenaMode;
private _enabled = missionNamespace getVariable ["comspec_overwatch_enabled", true];
private _iso = missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false];

// 1. DLL COMSPEC Link.
private _ext = [] call comspec_overwatch_connect_fnc_extensionStatus;
if (isNil "_ext" || {!(_ext isEqualType [])}) then { _ext = [false, "not_loaded", ""]; };
_ext params [["_extOk", false], ["_extCode", "not_loaded"], ["_ping", ""]];
if (!(_ping isEqualType "")) then { _ping = str _ping; };
private _dllVer = "";
if (_extOk) then {
    private _w = (_ping select [3]) splitString " |";
    _dllVer = _w param [(count _w) - 1, ""];
    ["ok", "DLL COMSPEC Link" call _T, (format ["chargée, version %1", _dllVer]) call _T, "", ""] call _add;
} else {
    if (_extCode isEqualTo "not_loaded") then {
        ["ko", "DLL COMSPEC Link" call _T, "ne répond pas" call _T,
            "Sans la DLL, pas de liaison ni de journal. Cause probable : BattlEye actif, DLL absente ou bloquée par l'antivirus. Lancez Arma sans BattlEye et vérifiez COMSPECExtension_x64.dll dans le dossier @COMSPEC_ATAK_Native." call _T, ""] call _add;
    } else {
        ["ko", "DLL COMSPEC Link" call _T, (format ["réponse invalide (%1)", _ping select [0, 40]]) call _T,
            "La DLL chargée ne correspond pas au mod. Mettez le mod à jour sur Steam (ou recompilez avec build_mod.bat), puis relancez Arma." call _T, ""] call _add;
    };
};

// 2. Installation : contrôles de l'ATAK natif (#714).
if (_native) then {
    private _code = missionNamespace getVariable ["COMSPEC_ATAK_CodeVersion", ""];
    private _issues = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
    if (!(_issues isEqualType [])) then { _issues = []; };
    if (_issues isEqualTo []) then {
        (missionNamespace getVariable ["COMSPEC_ATAK_LatestRemote", ["", "", -1]]) params [["_lAtak", ""]];
        private _val = (format ["ATAK %1, DLL attendue %2", _code, missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", "?"]]) call _T;
        if (_lAtak isNotEqualTo "") then { _val = _val + " · " + ((format ["à jour (site : %1)", _lAtak]) call _T); };
        ["ok", "Installation du mod" call _T, _val, "", ""] call _add;
    } else {
        {
            _x params [["_key", ""], ["_lvl", "WARNING"], ["_txt", ""]];
            private _title = ["Installation du mod", "Mise à jour disponible"] select ((_key select [0, 7]) isEqualTo "update_");
            [["warn", "ko"] select (_lvl isEqualTo "ERROR"), _title call _T, (format ["ATAK %1", _code]) call _T, _txt call _T, ""] call _add;
        } forEach _issues;
    };
};

// 3. Service Athéna (choix du joueur).
switch (_mode) do {
    case "on": { ["ok", "Service Athéna" call _T, "oui" call _T, "", ""] call _add; };
    case "off": {
        ["info", "Service Athéna" call _T, "non : ATAK local" call _T,
            "Aucune liaison avec le poste tant que le service est refusé. Si votre unité utilise Athena, répondez Oui." call _T, "athena"] call _add;
    };
    default {
        ["warn", "Service Athéna" call _T, "pas encore choisi" call _T,
            "La liaison attend votre réponse « Votre unité utilise-t-elle Athena ? »." call _T, "athena"] call _add;
    };
};

// 4. Overwatch (interrupteur général, coupé aussi par le pas à pas).
if (_iso) then {
    ["info", "Overwatch" call _T, "pas à pas en cours" call _T,
        "Les fonctions reviennent une par une. Arrêtez le pas à pas pour tout rallumer." call _T, ""] call _add;
} else {
    if (!_enabled) then {
        ["warn", "Overwatch" call _T, "coupé" call _T,
            "Overwatch est coupé (réglage du mod ou pas à pas interrompu) : ni synchro ni messages." call _T, "enable"] call _add;
    } else {
        ["ok", "Overwatch" call _T, "actif" call _T, "", ""] call _add;
    };
};

if (_mode isNotEqualTo "off") then {
    // 5. Adresse du portail.
    private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
    if (!(_url isEqualType "")) then { _url = str _url; };
    _url = trim _url;
    private _host = _url;
    { if ((_host select [0, count _x]) isEqualTo _x) then { _host = _host select [count _x]; }; } forEach ["https://", "http://"];
    private _slash = _host find "/";
    if (_slash >= 0) then { _host = _host select [0, _slash]; };
    if (_url isEqualTo "") then {
        ["ko", "Adresse Athena" call _T, "aucune" call _T,
            "Le serveur n'a pas d'adresse Athena : l'administrateur du serveur doit la renseigner dans les réglages du mod." call _T, ""] call _add;
    } else {
        ["ok", "Adresse Athena" call _T, _host, "", ""] call _add;
    };

    // 6. Liaison.
    private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
    private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
    if (!(_detail isEqualType "")) then { _detail = str _detail; };
    private _notLinked = (toLower _detail) find "not linked" >= 0 || {(toLower _detail) find "non li" >= 0};
    switch (true) do {
        case (_link isEqualTo "linked"): {
            private _at = missionNamespace getVariable ["COMSPEC_LastHealthOk", -1];
            private _val = "en ligne" call _T;
            if (_at isEqualType 0 && {_at > 0}) then { _val = (format ["en ligne, contrôlée il y a %1 s", round (diag_tickTime - _at)]) call _T; };
            ["ok", "Liaison Athena" call _T, _val, "", ""] call _add;
        };
        case (_link in ["connecting", "degraded"]): {
            ["warn", "Liaison Athena" call _T, (["connexion en cours", "instable"] select (_link isEqualTo "degraded")) call _T,
                "La liaison se rétablit seule en général. Si cela dure plus d'une minute : Reconnecter Athena." call _T, "reconnect"] call _add;
        };
        case (_link isEqualTo "disabled"): {};
        // Clé refusée (fn_connect, ERR unauthorized) : clé du profil changée ou retirée côté Athena.
        // Reconnecter renverrait la même clé : seule une nouvelle liaison en enregistre une valide.
        case ((toLower _detail) find "cle athena invalide" >= 0): {
            ["ko", "Liaison Athena" call _T, "clé d'accès refusée" call _T,
                "Athena refuse la clé enregistrée dans votre profil : elle a été changée ou retirée. Refaites la liaison avec Lier mon compte (code affiché sur le site, page Mon compte) : une nouvelle clé est enregistrée toute seule." call _T, "link"] call _add;
        };
        case ((toLower _detail) find "communaute manquante" >= 0 || {(toLower _detail) find "steam invalide" >= 0}): {
            ["ko", "Liaison Athena" call _T, _detail call _T,
                "La liaison enregistrée dans votre profil est incomplète. Refaites-la avec Lier mon compte (code affiché sur le site, page Mon compte)." call _T, "link"] call _add;
        };
        case ((toLower _detail) find "non autorise" >= 0 || {(toLower _detail) find "acces mod refuse" >= 0}): {
            ["ko", "Liaison Athena" call _T, _detail call _T,
                "Athena bloque ce compte, cet identifiant Steam ou ce réseau. Une nouvelle liaison n'y changera rien : contactez un administrateur de votre communauté." call _T, ""] call _add;
        };
        case ((toLower _detail) find "steam requis" >= 0): {
            ["ko", "Liaison Athena" call _T, _detail call _T,
                "Cette version du mod n'envoie pas votre identifiant Steam. Mettez le mod à jour sur Steam, puis relancez Arma." call _T, ""] call _add;
        };
        case (_notLinked): {
            ["ko", "Liaison Athena" call _T, "compte non lié" call _T,
                "Le portail répond mais ce jeu n'est pas relié à votre compte Athena : Lier mon compte (code affiché sur le site, page Mon compte)." call _T, "link"] call _add;
        };
        default {
            private _why = [_detail, "hors ligne"] select (_detail isEqualTo "");
            ["ko", "Liaison Athena" call _T, _why call _T,
                "Causes probables : site Athena injoignable, connexion internet coupée ou session expirée. Essayez Reconnecter Athena ; si cela échoue, vérifiez que le site s'ouvre dans un navigateur." call _T, "reconnect"] call _add;
        };
    };

    // 7. Compte et session.
    private _linked = missionNamespace getVariable ["COMSPEC_OperatorLinked", false];
    private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
    if (_linked && {_ready}) then {
        private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
        if (isNil "_cs" || {!(_cs isEqualType "")} || {_cs isEqualTo ""}) then { _cs = name player; };
        ["ok", "Compte Athena" call _T, (format ["%1 · fiche %2", _cs, missionNamespace getVariable ["COMSPEC_OperatorProfileId", 0]]) call _T, "", ""] call _add;
    } else {
        if (!_linked) then {
            ["warn", "Compte Athena" call _T, "non identifié" call _T,
                "Sans compte lié, vos messages, repères et photos ne remontent pas à votre nom." call _T, "link"] call _add;
        } else {
            ["warn", "Compte Athena" call _T, "session pas encore ouverte" call _T,
                "Le compte est lié mais la session n'a pas démarré. Patientez quelques secondes, puis Reconnecter Athena." call _T, "reconnect"] call _add;
        };
    };

    // 8. Terminal ATAK porté.
    if (!isNull player) then {
        private _term = [player] call comspec_overwatch_connect_fnc_hasTerminal;
        if (_term isEqualTo true) then {
            ["ok", "Terminal ATAK" call _T, "porté" call _T, "", ""] call _add;
        } else {
            ["warn", "Terminal ATAK" call _T, "aucun" call _T,
                "Sans téléphone ATAK sur vous, la position et la synchro ne partent pas. Prenez un téléphone à l'arsenal." call _T, ""] call _add;
        };
    };

    // 9. Pertes (inclut le brouillage des zones roleplay).
    private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
    if (!isNil "_pkt" && {_pkt isEqualType createHashMap}) then {
        private _sent = _pkt getOrDefault ["packets_sent_total", 0];
        private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
        if (!(_sent isEqualType 0)) then { _sent = 0; };
        if (!(_loss isEqualType 0)) then { _loss = 0; };
        _loss = (round _loss) max 0 min 100;
        private _val = (format ["%1 %% de pertes, %2 envois", _loss, round _sent]) call _T;
        if (_loss >= 30) then {
            ["warn", "Échanges" call _T, _val,
                "Pertes élevées : brouillage d'une zone de la mission, relais trop loin ou réseau saturé. Changez de position ou rapprochez-vous d'un relais." call _T, ""] call _add;
        } else {
            ["ok", "Échanges" call _T, _val, "", ""] call _add;
        };
    };
};

// 10. Dernier pas à pas interrompu (jeu arrêté ou mission quittée pendant l'étape).
private _last = profileNamespace getVariable ["COMSPEC_DiagIsolateLast", []];
if (!_iso && {_last isEqualType []} && {(count _last) >= 2} && {((_last select 1) isEqualType "")} && {(_last select 1) isNotEqualTo ""}) then {
    ["warn", "Dernier pas à pas" call _T, (format ["interrompu à : %1", (_last select 1) call comspec_overwatch_connect_fnc_tr]) call _T,
        "Si le jeu s'est arrêté pendant ce pas à pas, cette fonction est suspecte. Signalez-le, puis relancez un pas à pas pour confirmer." call _T, "bug"] call _add;
};

// Verdict et correction proposée.
private _ko = _rows select { (_x select 0) isEqualTo "ko" };
private _warn = _rows select { (_x select 0) isEqualTo "warn" };
private _verdict = switch (true) do {
    case (_iso): { ["warn", "Pas à pas en cours" call _T] };
    case (_ko isNotEqualTo []): { ["ko", (format ["%1 problème(s)", count _ko]) call _T] };
    case (_warn isNotEqualTo []): { ["warn", "À surveiller" call _T] };
    case (_mode isEqualTo "off"): { ["info", "ATAK local" call _T] };
    default { ["ok", "Liaison OK" call _T] };
};
private _fix = "";
{
    private _lvl = _x;
    if (_fix isEqualTo "") then {
        private _i = _rows findIf { (_x select 0) isEqualTo _lvl && {(_x select 4) isNotEqualTo ""} };
        if (_i >= 0) then { _fix = (_rows select _i) select 4; };
    };
} forEach ["ko", "warn", "info"];

[_rows, _verdict, _fix]
