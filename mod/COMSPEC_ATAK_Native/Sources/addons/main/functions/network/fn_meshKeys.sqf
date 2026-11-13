/*
    Clés du maillage (lots 6 et 7, réglage serveur comspec_atak_native_mesh_keys, décoché par défaut). MÉCANIQUE DE JEU : une clé est une étiquette et
    un numéro d'époque (K-ALPHA, époque 3). Aucune cryptographie, aucun secret, rien de réel n'est protégé.
    Serveur : registre COMSPEC_ATAK_MeshKeyReg, table publique COMSPEC_ATAK_MeshEpochs = [[id, nom, époque, compromise, camp, est clé de réseau,
    heure du dernier changement]...], 16 lignes au plus, diffusée sur changement seulement. Une clé de réseau par camp, créée à la première demande.
    Client : COMSPEC_ATAK_MeshKeysMine, HashMap id -> époque, reçue par événement ciblé. Publié : un petit entier, id x 1000 + époque, dans COMSPEC_ATAK_MpuNode.
    Clé de réseau absente ou périmée : hors du graphe des autres (fn_meshGraph), portée radio normale. Clé de groupe absente ou périmée :
    message « [CHIFFRÉ · nom] », voix sans rallonge (décision 2). Après un changement de clé, 30 s pour renouveler ; ensuite un nœud isolé reste périmé
    jusqu'à son retour près d'un allié autorisé, ou jusqu'au chargeur de clés.
    Compromission (décision 1) : automatique quand un joueur d'un autre camp ramasse une MPU5 ou un chargeur (réglage mesh_compromise_auto), ou sur ordre.
    Tant que l'époque n'est pas renouvelée, sa clé de réseau reste valable pour lui : il relaie dans le graphe de l'autre camp.
    Limite : il ne lit pas ce graphe pour lui-même. Code TFAR (décision 17, réglage mesh_tfar_code, décoché) : ne s'entendent que les radios de même code, À CONFIRMER EN JEU.
      "on" ; "init" (serveur) ; "tick" (client, 2 s)
      "define" [nom, camp, réseau] (serveur) -> id ; "request" [auto] ; "zeroize" ; "rekey" [id] ; "compromise" [id] ; "taken" [objet d'où vient l'appareil]
      "nodeKey" -> entier à publier ; "authorized" [camp, entier] -> vrai si ce nœud est admis ; "compromisedNk" [camp] -> entier ou -9
      "state" [id] -> "valide" | "à renouveler" | "absente" | "compromise" ; "rows" -> lignes de l'app Wave Relay ; "unknown" -> appareils non autorisés à portée
      "link" -> état réel du chiffrement de ma liaison, pour la barre d'état et l'app Wave Relay : [code (enc, stale, comp, clear), libellé court, libellé, détail, image, couleur, jeton de couleur du thème]
      "stamp" [groupe] -> [id, époque] ou [] ; "open" [étiquette, texte] -> texte ou [CHIFFRÉ · nom] ; "voiceOk" -> vrai si ma clé couvre le groupe de ma radio
    Chiffrement plus réaliste (réglages décochés par défaut sauf le coût discret ; le profil Arcade les coupe tous) :
      chargeur de clés (mesh_keys_fill) : "fill" = 6 s d'action avec un chargeur de clés ou au poste de commandement, le serveur vérifie ;
      période cryptographique (mesh_keys_period, minutes, 0 aucune) : heure de chargement par clé dans COMSPEC_ATAK_MeshKeysAt ; alerte à 10 min,
        clé périmée = état "périmée" : hors du graphe, voix sans rallonge, messages reçus illisibles, envois en clair ("stampTx") ;
      rechargement à distance (mesh_keys_otar) : "otar" (chef de groupe ou poste de commandement, sa clé valide) vers les équipiers joints par le maillage ;
      effacement d'urgence : "zeroizeAsk" (2 s) puis "zeroize" ; clés portées publiées dans COMSPEC_ATAK_MeshKeysHeld : un appareil effacé ne compromet rien,
        un appareil ramassé compromet les clés qu'il porte (réglage mesh_compromise_auto) ;
      coût (mesh_crypto_cost) : "cost" -> [part de débit en moins, ms en plus, % de batterie par minute en plus] tant que la liaison est chiffrée ;
      interception (mesh_intercept) : "intercept" [groupe, étiquette, texte] (émetteur), "heard" [...] (récepteur : goniomètre et spécialiste),
        en clair : position et contenu partiel ; chiffré : émission et direction seulement. Relèvements dans COMSPEC_ATAK_EwBearings (app Guerre électronique) ;
      "diag" -> HashMap d'états en lecture seule (dépannage MPU) ; "aceInit" : actions ACE sous Téléphone ATAK > Radio de données.
*/
params [["_act", ""], ["_arg", []], ["_caller", objNull]];
private _on = (missionNamespace getVariable ["comspec_atak_native_mesh_keys", false]) isEqualTo true;
private _now = [time, serverTime] select isMultiplayer;
private _arcade = { (["profile"] call comspec_atak_native_fnc_netCond) isEqualTo 1 };
private _toggle = {
    params ["_var", "_def"];
    _on && {(missionNamespace getVariable [_var, _def]) isEqualTo true} && {!(call _arcade)}
};
// Période cryptographique en secondes (0 : aucune).
private _period = {
    if (!_on || {call _arcade}) exitWith { 0 };
    private _pm = missionNamespace getVariable ["comspec_atak_native_mesh_keys_period", 0];
    if !(_pm isEqualType 0) exitWith { 0 };
    (_pm max 0) * 60
};
// Secondes avant l'expiration de ma clé : -1 sans période ou sans heure de chargement, 0 si elle est périmée.
private _leftOf = {
    params ["_kid"];
    private _pd = call _period;
    if (_pd <= 0) exitWith { -1 };
    private _la = (missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysAt", createHashMap]) getOrDefault [_kid, -1];
    if (_la < 0) exitWith { -1 };
    (_pd - (_now - _la)) max 0
};
private _expired = { params ["_kid"]; ([_kid] call _leftOf) isEqualTo 0 };
private _rowOf = {
    params ["_id"];
    private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) findIf { (_x param [0, -1]) isEqualTo _id };
    if (_i < 0) exitWith { [] };
    (missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs") select _i
};
private _rowByName = {
    params ["_name"];
    private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) findIf { (_x param [1, ""]) isEqualTo _name };
    if (_i < 0) exitWith { [] };
    (missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs") select _i
};
private _netRow = {
    params ["_side"];
    private _i = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) findIf { (_x param [4, ""]) isEqualTo _side && {_x param [5, false]} };
    if (_i < 0) exitWith { [] };
    (missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs") select _i
};
private _say = {
    params ["_type", "_msg"];
    [_type, _msg, 6, 40] call comspec_atak_native_fnc_notify;
};
switch (_act) do {
    case "on": { _on };
    // ---- Lecture : sans le réglage, tout est admis et rien n'est chiffré.
    case "nodeKey": {
        if (!_on) exitWith { -2 };
        private _row = [str side group player] call _netRow;
        if (_row isEqualTo []) exitWith { -1 };
        // Clé compromise récupérée sur l'autre camp : on publie celle-là.
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        private _foreign = (keys _mine) select { private _r = [_x] call _rowOf; _r isNotEqualTo [] && {_r select 5} && {(_r select 4) isNotEqualTo str side group player} && {_r select 3} && {!([_x] call _expired)} };
        // Clé périmée (période cryptographique) : hors du graphe jusqu'au rechargement.
        if ((_row select 0) in _mine && {[_row select 0] call _expired}) exitWith { -1 };
        if ((_row select 0) in _mine) exitWith { (_row select 0) * 1000 + (_mine get (_row select 0)) };
        if (_foreign isNotEqualTo []) exitWith { (_foreign select 0) * 1000 + (_mine get (_foreign select 0)) };
        -1
    };
    case "authorized": {
        if (!_on) exitWith { true };
        _arg params [["_side", ""], ["_nk", -1]];
        private _row = [_side] call _netRow;
        // Registre pas encore prêt pour ce camp : personne n'est exclu.
        if (_row isEqualTo []) exitWith { true };
        private _t = (_row select 0) * 1000;
        private _now = [time, serverTime] select isMultiplayer;
        _nk isEqualTo (_t + (_row select 2)) || {_nk isEqualTo (_t + (_row select 2) - 1) && {_now - (_row param [6, -1000]) < 30}}
    };
    case "compromisedNk": {
        if (!_on) exitWith { -9 };
        private _row = [_arg param [0, ""]] call _netRow;
        if (_row isEqualTo [] || {!(_row select 3)}) exitWith { -9 };
        (_row select 0) * 1000 + (_row select 2)
    };
    case "state": {
        private _row = [_arg param [0, -1]] call _rowOf;
        if (_row isEqualTo []) exitWith { "absente" };
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        if !((_row select 0) in _mine) exitWith { "absente" };
        if (_row select 3) exitWith { "compromise" };
        if ((_mine get (_row select 0)) isNotEqualTo (_row select 2)) exitWith { "à renouveler" };
        if ([_row select 0] call _expired) exitWith { "périmée" };
        "valide"
    };
    case "link": {
        private _dir = "\z\comspec_atak_native\addons\main\data\";
        // 8e élément : un exemple concret pour l'infobulle du cadenas (barre d'état).
        private _clear = {
            params ["_why"];
            ["clear", "EN CLAIR", "NON CHIFFRÉ", _why, _dir + "net_lock_open_ca.paa", [0.62, 0.70, 0.66, 1], "hexTextMuted",
                ["un message de groupe envoyé maintenant part sans chiffrement de jeu", "un ennemi équipé d'un goniomètre peut lire une partie de vos messages de groupe et savoir d'où ils partent"] select ((missionNamespace getVariable ["comspec_atak_native_mesh_intercept", false]) isEqualTo true && {!(call _arcade)})]
        };
        // Détail lu dans l'infobulle du cadenas et dans l'app Wave Relay : pourquoi la liaison est en clair, et ce qu'il faut faire.
        if (!_on) exitWith { ["Chiffrement inactif par défaut, ce n'est pas une panne : un admin l'active avec le réglage « Clés du maillage »"] call _clear };
        if !(["active"] call comspec_atak_native_fnc_mpuLink) exitWith { ["Chiffrement inactif : il demande la liaison téléphone-radio réaliste (réglage admin)"] call _clear };
        private _row = [str side group player] call _netRow;
        if (_row isEqualTo []) exitWith { ["Clé de réseau pas encore créée : touchez CHARGER LES CLÉS dans l'app Wave Relay"] call _clear };
        _row params ["_id", "_name", "_ep"];
        private _st = ["state", [_id]] call comspec_atak_native_fnc_meshKeys;
        if (_st isEqualTo "absente") exitWith { [format ["Clé %1 non chargée : touchez CHARGER LES CLÉS dans l'app Wave Relay", _name]] call _clear };
        if (_st isEqualTo "compromise") exitWith { ["comp", "COMPROMISE", "CLÉ COMPROMISE", format ["Clé %1 compromise : changez-la", _name], _dir + "net_lock_closed_ca.paa", [0.88, 0.25, 0.22, 1], "hexAlert",
            "un ennemi a ramassé une MPU5 chargée : il entend votre réseau tant que la clé n'a pas changé d'époque"] };
        if (_st isEqualTo "à renouveler") exitWith { ["stale", "PÉRIMÉE", "CLÉ À RENOUVELER", format ["Clé %1 périmée : le réseau est à l'époque %2", _name, _ep], _dir + "net_lock_closed_ca.paa", [0.95, 0.67, 0.20, 1], "hexWarn",
            "après un changement d'époque, 30 s pour recharger, sinon la MPU5 sort du maillage"] };
        // Période cryptographique écoulée : envois en clair, hors du maillage chiffré jusqu'au rechargement.
        if (_st isEqualTo "périmée") exitWith { ["expired", "PÉRIMÉE", "CLÉ PÉRIMÉE", format ["Clé %1 périmée (période de %2 min écoulée) : vos envois partent en clair et la MPU5 est hors du maillage chiffré. Rechargez-la", _name, round ((call _period) / 60)],
            _dir + "net_lock_open_ca.paa", [0.95, 0.45, 0.20, 1], "hexWarn", "chargeur de clés dans l'inventaire : Téléphone ATAK, Radio de données, Charger les clés (6 s), ou votre chef de groupe par OTAR"] };
        if !((uiNamespace getVariable ["COMSPEC_ATAK_MpuMesh", createHashMap]) getOrDefault ["ok", false]) exitWith { ["Clé valide, mais hors du maillage"] call _clear };
        private _left = [_id] call _leftOf;
        if (_left > 0 && {_left <= 600}) exitWith { ["soon", "RENOUVELER", "CLÉ À RENOUVELER", format ["Clé %1 valable encore %2 min : rechargez-la avant l'expiration", _name, ceil (_left / 60)],
            _dir + "net_lock_closed_ca.paa", [0.95, 0.67, 0.20, 1], "hexWarn", "à 0 min, le cadenas passe en PÉRIMÉE et vos messages partent en clair"] };
        ["enc", "CHIFFRÉ", "CHIFFRÉ", [format ["Clé %1, époque %2. Mécanique de jeu, aucune sécurité réelle", _name, _ep], format ["Clé %1, époque %2, valable encore %3 min. Mécanique de jeu, aucune sécurité réelle", _name, _ep, ceil (_left / 60)]] select (_left > 0),
            _dir + "net_lock_closed_ca.paa", [0.36, 0.78, 0.42, 1], "hexOk", "l'ennemi peut voir qu'une radio émet et d'où, jamais ce qu'elle dit"]
    };
    // Coût du chiffrement tant que ma liaison est chiffrée : [part de débit en moins, ms en plus, % de batterie par minute en plus].
    case "cost": {
        private _lv = missionNamespace getVariable ["comspec_atak_native_mesh_crypto_cost", 1];
        if (!_on || {!(_lv isEqualType 0)} || {_lv < 1} || {call _arcade}) exitWith { [0, 0, 0] };
        if !(((["link"] call comspec_atak_native_fnc_meshKeys) param [0, ""]) in ["enc", "soon"]) exitWith { [0, 0, 0] };
        [[0.05, 20, 0.02], [0.15, 60, 0.05]] select (((round _lv) min 2) - 1)
    };
    // États pour le dépannage MPU (lecture seule) : rien n'est écrit.
    case "diag": {
        (["link"] call comspec_atak_native_fnc_meshKeys) params ["_dc", "", "_dl", "_dw"];
        private _row = [str side group player] call _netRow;
        createHashMapFromArray [
            ["on", _on], ["code", _dc], ["label", _dl], ["why", _dw], ["period", call _period],
            ["net", _row param [1, ""]], ["netState", ["absente", ["state", [_row param [0, -1]]] call comspec_atak_native_fnc_meshKeys] select (_row isNotEqualTo [])],
            ["left", [-1, [_row param [0, -1]] call _leftOf] select (_row isNotEqualTo [])],
            ["unknown", [0, count (["unknown"] call comspec_atak_native_fnc_meshKeys)] select _on],
            ["voiceOk", ["voiceOk"] call comspec_atak_native_fnc_meshKeys],
            ["fill", ["comspec_atak_native_mesh_keys_fill", false] call _toggle], ["otar", ["comspec_atak_native_mesh_keys_otar", false] call _toggle],
            ["intercept", (missionNamespace getVariable ["comspec_atak_native_mesh_intercept", false]) isEqualTo true && {!(call _arcade)}]
        ]
    };
    case "stamp": {
        if (!_on) exitWith { [] };
        private _kn = ["keyOf", [_arg param [0, 0]]] call comspec_atak_native_fnc_meshGroups;
        if (_kn isEqualTo "") exitWith { [] };
        private _row = [_kn] call _rowByName;
        if (_row isEqualTo []) exitWith { [] };
        [_row select 0, _row select 2]
    };
    // Étiquette d'un envoi : ma clé de ce groupe périmée (période cryptographique), je ne peux plus chiffrer, le message part en clair.
    case "stampTx": {
        private _kk = ["stamp", _arg] call comspec_atak_native_fnc_meshKeys;
        if (_kk isNotEqualTo [] && {[_kk select 0] call _expired}) exitWith { [] };
        _kk
    };
    case "open": {
        _arg params [["_kk", []], ["_text", ""]];
        if (!_on || {!(_kk isEqualType [])} || {(count _kk) isNotEqualTo 2}) exitWith { _text };
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        if ((_mine getOrDefault [_kk select 0, -1]) isEqualTo (_kk select 1) && {!([_kk select 0] call _expired)}) exitWith { _text };
        format ["[CHIFFRÉ · %1]", ([_kk select 0] call _rowOf) param [1, "?"]]
    };
    case "voiceOk": {
        if (!_on) exitWith { true };
        (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []]];
        if (_me isEqualTo []) exitWith { false };
        private _f = if ((_me select 0) isEqualTo "acre") then { (["freq", [_me select 1]] call comspec_atak_native_fnc_mpu5) param [0, 0] } else { _me select 2 };
        private _kk = ["stamp", [["ofFreq", [_f]] call comspec_atak_native_fnc_meshGroups]] call comspec_atak_native_fnc_meshKeys;
        if (_kk isEqualTo []) exitWith { true };
        ((missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap]) getOrDefault [_kk select 0, -1]) isEqualTo (_kk select 1) && {!([_kk select 0] call _expired)}
    };
    // ---- Client
    case "tick": {
        if (!hasInterface || {!_on}) exitWith {};
        // Porteur d'une clé : le corps garde son camp, pour la compromission (fn_meshKeys "taken").
        if ((player getVariable ["COMSPEC_ATAK_MeshOwnerSide", ""]) isNotEqualTo str side group player) then { player setVariable ["COMSPEC_ATAK_MeshOwnerSide", str side group player, true]; };
        if !(missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysTake", false]) then {
            missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysTake", true];
            ["comspec_atak_native_meshKeyGive", {
                params [["_id", -1], ["_epoch", 0], ["_how", ""]];
                private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
                private _new = (_mine getOrDefault [_id, -1]) isNotEqualTo _epoch;
                if (_new) then { ["INFO", format ["Clé du maillage chargée, époque %1", _epoch], 5, 30] call comspec_atak_native_fnc_notify; };
                // Même époque rechargée (chargeur, OTAR) : une seule notification pour toutes les clés du camp.
                if (!_new && {_how in ["fill", "otar"]} && {diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_MeshKeysSaidAt", -100]) > 3}) then {
                    uiNamespace setVariable ["COMSPEC_ATAK_MeshKeysSaidAt", diag_tickTime];
                    ["INFO", ["Clés rechargées au chargeur : nouvelle période cryptographique", "Clés renouvelées à distance (OTAR) : nouvelle période cryptographique"] select (_how isEqualTo "otar"), 5, 30] call comspec_atak_native_fnc_notify;
                };
                _mine set [_id, _epoch];
                missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysMine", _mine];
                // Heure de chargement : départ de la période cryptographique de cette clé.
                private _la = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysAt", createHashMap];
                _la set [_id, [time, serverTime] select isMultiplayer];
                missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysAt", _la];
                (uiNamespace getVariable ["COMSPEC_ATAK_MeshKeysWarned", createHashMap]) deleteAt _id;
                uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
            }] call CBA_fnc_addEventHandler;
            ["comspec_atak_native_meshKeyAlert", { params [["_msg", ""]]; ["WARNING", _msg, 8, 50] call comspec_atak_native_fnc_notify; }] call CBA_fnc_addEventHandler;
            player addEventHandler ["Take", { params ["", "_from", "_item"]; ["taken", [_from, _item]] call comspec_atak_native_fnc_meshKeys; }];
            player addEventHandler ["Respawn", { params ["_new"]; _new addEventHandler ["Take", { params ["", "_from", "_item"]; ["taken", [_from, _item]] call comspec_atak_native_fnc_meshKeys; }]; }];
        };
        private _mine = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        // Clés portées, publiques : un appareil effacé ne compromet rien quand l'ennemi le ramasse ("taken").
        private _held = keys _mine;
        _held sort true;
        if ((player getVariable ["COMSPEC_ATAK_MeshKeysHeld", -1]) isNotEqualTo _held) then { player setVariable ["COMSPEC_ATAK_MeshKeysHeld", _held, true]; };
        // Période cryptographique : clé sans heure de chargement (chargée avant la mise à jour) datée de maintenant ; alerte à 10 min, puis à l'expiration.
        private _loadAt = missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysAt", createHashMap];
        { if !(_x in _loadAt) then { _loadAt set [_x, _now]; }; } forEach _held;
        missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysAt", _loadAt];
        if ((call _period) > 0) then {
            private _warned = uiNamespace getVariable ["COMSPEC_ATAK_MeshKeysWarned", createHashMap];
            {
                private _kLeft = [_x] call _leftOf;
                private _kName = ([_x] call _rowOf) param [1, "?"];
                private _wSeen = _warned getOrDefault [_x, ""];
                if (_kLeft isEqualTo 0 && {_wSeen isNotEqualTo "exp"}) then {
                    _warned set [_x, "exp"];
                    ["WARNING", format ["Clé %1 périmée : vos envois partent en clair et la MPU5 quitte le maillage chiffré. Rechargez-la (chargeur de clés, poste de commandement ou chef de groupe)", _kName]] call _say;
                    [] call comspec_atak_native_fnc_vibrate;
                    uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
                };
                if (_kLeft > 0 && {_kLeft <= 600} && {_wSeen isEqualTo ""}) then {
                    _warned set [_x, "soon"];
                    ["WARNING", format ["Clé %1 : périmée dans %2 min. Rechargez-la avant (chargeur de clés ou chef de groupe)", _kName, ceil (_kLeft / 60)]] call _say;
                };
            } forEach _held;
            uiNamespace setVariable ["COMSPEC_ATAK_MeshKeysWarned", _warned];
        };
        private _auto = (missionNamespace getVariable ["comspec_atak_native_mesh_keys_auto", true]) isEqualTo true;
        private _at = uiNamespace getVariable ["COMSPEC_ATAK_MeshKeysAskAt", -100];
        if (diag_tickTime - _at < 10) exitWith {};
        // Clé périmée ou absente : demande au serveur, qui juge. Sans chargement automatique, rien tant que la clé n'a pas été chargée une fois.
        private _stale = (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) select { private _e = _mine getOrDefault [_x select 0, -1]; _e >= 0 && {_e isNotEqualTo (_x select 2)} };
        if ((_auto && {(count _mine) isEqualTo 0} && {!(uiNamespace getVariable ["COMSPEC_ATAK_MeshZeroized", false])}) || {_stale isNotEqualTo []}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MeshKeysAskAt", diag_tickTime];
            ["comspec_atak_native_meshKeysSrv", [player, "request", [false]]] call CBA_fnc_serverEvent;
        };
        // Code TFAR (décision 17) : radio pontée sur la clé de son groupe ; valeur d'origine rendue sinon.
        // Retire la voix directe sans la clé : décoché par défaut, coupé par le coupe-circuit, valeur d'origine rendue par "tfRestore".
        if ((missionNamespace getVariable ["comspec_atak_native_mesh_tfar_code", false]) isEqualTo true && {["voice"] call comspec_atak_native_fnc_radioGuard} && {["has", ["TFAR_fnc_setSwRadioCode"]] call comspec_atak_native_fnc_radioGuard}) then {
            (missionNamespace getVariable ["COMSPEC_ATAK_VoiceMe", []]) params [["_me", []]];
            private _want = "";
            if (_me isNotEqualTo [] && {(_me select 0) in ["sw", "lr"]}) then {
                private _kk = ["stamp", [["ofFreq", [_me select 2]] call comspec_atak_native_fnc_meshGroups]] call comspec_atak_native_fnc_meshKeys;
                if (_kk isNotEqualTo [] && {(_mine getOrDefault [_kk select 0, -1]) isEqualTo (_kk select 1)} && {!([_kk select 0] call _expired)}) then { _want = format ["%1-%2", ([_kk select 0] call _rowOf) param [1, ""], _kk select 1]; };
            };
            (uiNamespace getVariable ["COMSPEC_ATAK_MeshTfCode", []]) params [["_radio", ""], ["_code", ""], ["_orig", ""]];
            if (_code isNotEqualTo _want) then {
                private _isLr = (_me param [0, ""]) isEqualTo "lr";
                private _target = if (_want isEqualTo "") then { _radio } else { if (_isLr) then { ["api", ["TFAR_fnc_activeLrRadio", [], []]] call comspec_atak_native_fnc_radioGuard } else { _me select 1 } };
                if (_want isNotEqualTo "" && {_code isEqualTo ""}) then {
                    _orig = ["api", [["TFAR_fnc_getSwRadioCode", "TFAR_fnc_getLrRadioCode"] select _isLr, _target, ""]] call comspec_atak_native_fnc_radioGuard;
                };
                private _set = if (_target isEqualType "") then { "TFAR_fnc_setSwRadioCode" } else { "TFAR_fnc_setLrRadioCode" };
                ["api", [_set, [_target, [_want, _orig] select (_want isEqualTo "")], false]] call comspec_atak_native_fnc_radioGuard;
                uiNamespace setVariable ["COMSPEC_ATAK_MeshTfCode", [[[], _target] select (_want isNotEqualTo ""), _want, _orig]];
            };
        };
    };
    case "tfRestore": {
        // Code de la radio rendu tel qu'il était (fn_mpuVoice "release").
        (uiNamespace getVariable ["COMSPEC_ATAK_MeshTfCode", []]) params [["_radio", ""], ["_code", ""], ["_orig", ""]];
        if (_code isEqualTo "") exitWith {};
        private _set = if (_radio isEqualType "") then { "TFAR_fnc_setSwRadioCode" } else { "TFAR_fnc_setLrRadioCode" };
        ["api", [_set, [_radio, _orig], false]] call comspec_atak_native_fnc_radioGuard;
        uiNamespace setVariable ["COMSPEC_ATAK_MeshTfCode", []];
    };
    case "request": {
        // Client : demande de chargement (chargeur de clés, poste de commandement). Le serveur vérifie.
        if (isNull _caller) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_MeshZeroized", false];
            ["comspec_atak_native_meshKeysSrv", [player, "request", [true]]] call CBA_fnc_serverEvent;
        };
        if (!isServer || {!_on}) exitWith {};
        private _manual = _arg param [0, false];
        private _sideStr = str side group _caller;
        // Une clé de réseau par camp, créée à la première demande.
        if (([_sideStr] call _netRow) isEqualTo []) then { ["define", [format ["K-%1", _sideStr], _sideStr, true]] call comspec_atak_native_fnc_meshKeys; };
        private _hasLoader = "COMSPEC_ATAK_KeyLoader" in (items _caller);
        private _toc = (_caller getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo "";
        private _auto = (missionNamespace getVariable ["comspec_atak_native_mesh_keys_auto", true]) isEqualTo true;
        private _row = [_sideStr] call _netRow;
        private _nkOk = {
            params ["_who", "_rowN"];
            (["node", _who] call comspec_atak_native_fnc_meshRead) param [4, -1]
        };
        // Retour d'un nœud périmé : un allié à jour, à portée de MPU5.
        private _r = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        private _nearOk = (allPlayers findIf {
            _x isNotEqualTo _caller && {alive _x} && {str side group _x isEqualTo _sideStr} && {(_x distance _caller) < _r} && {([_x, _row] call _nkOk) isEqualTo ((_row select 0) * 1000 + (_row select 2))}
        }) >= 0;
        private _graceOk = (([time, serverTime] select isMultiplayer) - (_row param [6, -1000])) < 30;
        private _mine = ["node", _caller] call comspec_atak_native_fnc_meshRead;
        if (!(_hasLoader || {_toc} || {_auto && {(_mine param [4, -1]) < 0}} || {_nearOk} || {_graceOk})) exitWith {};
        if (_manual && {!(_hasLoader || {_toc} || {_auto})}) exitWith { ["comspec_atak_native_meshKeyAlert", ["Il faut un chargeur de clés ou le poste de commandement"], _caller] call CBA_fnc_targetEvent; };
        // Chargeur de clés (réglage mesh_keys_fill) : un rechargement à la main exige le chargeur ou le poste de commandement, même avec le chargement automatique.
        if (_manual && {["comspec_atak_native_mesh_keys_fill", false] call _toggle} && {!(_hasLoader || {_toc})}) exitWith {
            ["comspec_atak_native_meshKeyAlert", ["Il faut un chargeur de clés ou le poste de commandement"], _caller] call CBA_fnc_targetEvent;
        };
        {
            _x params ["_id", "", "_ep", "", "_s"];
            if (_s isEqualTo _sideStr) then { ["comspec_atak_native_meshKeyGive", [_id, _ep, ["", "fill"] select _manual], _caller] call CBA_fnc_targetEvent; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]);
    };
    // Chargeur de clés : 6 s d'action, chargeur dans l'inventaire ou poste de commandement. Sans le réglage : chargement direct, comme avant.
    case "fill": {
        if (!_on) exitWith {};
        if !(["comspec_atak_native_mesh_keys_fill", false] call _toggle) exitWith { ["request", [true]] call comspec_atak_native_fnc_meshKeys; };
        if (!("COMSPEC_ATAK_KeyLoader" in (items player)) && {(player getVariable ["COMSPEC_ATAK_TocOn", ""]) isEqualTo ""}) exitWith {
            ["WARNING", "Il faut un chargeur de clés dans l'inventaire, ou le poste de commandement"] call _say;
        };
        if (isNil "ace_common_fnc_progressBar") exitWith {
            ["INFO", "Chargement des clés dans la MPU5…"] call _say;
            [{ if (alive player) then { ["request", [true]] call comspec_atak_native_fnc_meshKeys; }; }, [], 6] call CBA_fnc_waitAndExecute;
        };
        [6, [], { ["request", [true]] call comspec_atak_native_fnc_meshKeys; }, { ["INFO", "Chargement des clés annulé", 3, 20] call comspec_atak_native_fnc_notify; },
            ("Chargement des clés dans la MPU5…" call comspec_atak_native_fnc_tr), { alive player }, ["isNotSwimming"]] call ace_common_fnc_progressBar;
    };
    // Rechargement à distance (OTAR) : le chef de groupe (ou le poste de commandement), sa propre clé valide, vers les équipiers joints par le maillage.
    case "otar": {
        if (isNull _caller) exitWith {
            if !(["comspec_atak_native_mesh_keys_otar", false] call _toggle) exitWith {};
            private _toc = (player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo "";
            if ((leader group player) isNotEqualTo player && {!_toc}) exitWith { ["WARNING", "Rechargement à distance réservé au chef de groupe ou au poste de commandement"] call _say; };
            private _row = [str side group player] call _netRow;
            if (_row isEqualTo [] || {(["state", [_row select 0]] call comspec_atak_native_fnc_meshKeys) isNotEqualTo "valide"}) exitWith {
                ["WARNING", "Votre propre clé doit être valide pour la transmettre : rechargez-la d'abord au chargeur de clés"] call _say;
            };
            private _sideStr = str side group player;
            private _pool = ([units group player, allPlayers select { str side group _x isEqualTo _sideStr }] select _toc) select { isPlayer _x && {alive _x} && {_x isNotEqualTo player} };
            private _reach = _pool select { (["peer", [_x]] call comspec_atak_native_fnc_meshGraph) isNotEqualTo [] };
            if (_reach isNotEqualTo []) then { ["comspec_atak_native_meshKeysSrv", [player, "otar", [_reach]]] call CBA_fnc_serverEvent; };
            [["INFO", "WARNING"] select ((count _reach) < (count _pool)), format ["Rechargement à distance (OTAR) : %1 équipier(s) joint(s) par le maillage, %2 hors maillage (au chargeur de clés)", count _reach, (count _pool) - (count _reach)]] call _say;
        };
        if (!isServer || {!(["comspec_atak_native_mesh_keys_otar", false] call _toggle)}) exitWith {};
        private _toc = (_caller getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo "";
        if ((leader group _caller) isNotEqualTo _caller && {!_toc}) exitWith {};
        private _sideStr = str side group _caller;
        // Jamais l'appelant lui-même : sa clé vient du chargeur.
        private _to = (_arg param [0, []]) select { _x isEqualType objNull && {!isNull _x} && {alive _x} && {_x isNotEqualTo _caller} && {str side group _x isEqualTo _sideStr} && {_toc || {(group _x) isEqualTo (group _caller)}} };
        if (_to isEqualTo []) exitWith {};
        {
            _x params ["_id", "", "_ep", "", "_s"];
            if (_s isEqualTo _sideStr) then { ["comspec_atak_native_meshKeyGive", [_id, _ep, "otar"], _to] call CBA_fnc_targetEvent; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]);
        ["log", ["key", format ["Rechargement à distance : %1 appareil(s), par %2", count _to, name _caller]]] call comspec_atak_native_fnc_meshGraph;
    };
    // Effacement d'urgence (action ACE) : 2 s, annulable.
    case "zeroizeAsk": {
        if (isNil "ace_common_fnc_progressBar") exitWith { ["zeroize"] call comspec_atak_native_fnc_meshKeys; };
        [2, [], { ["zeroize"] call comspec_atak_native_fnc_meshKeys; }, {}, ("Effacement des clés de la MPU5…" call comspec_atak_native_fnc_tr), { alive player }] call ace_common_fnc_progressBar;
    };
    case "zeroize": {
        missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshKeysAt", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_MeshKeysWarned", createHashMap];
        // Tout de suite public : ramassée maintenant, la MPU5 ne compromet plus rien.
        player setVariable ["COMSPEC_ATAK_MeshKeysHeld", [], true];
        uiNamespace setVariable ["COMSPEC_ATAK_MeshZeroized", true];
        uiNamespace setVariable ["COMSPEC_ATAK_MpuMeshAt", -100];
        ["WARNING", "Clés effacées : hors du maillage jusqu'au prochain chargement"] call _say;
        [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "taken": {
        // Client : j'ai pris un appareil. Un corps ou une radio d'un autre camp : compromission (décision 1, désactivable).
        _arg params [["_from", objNull], ["_item", ""]];
        if (!_on || {!((missionNamespace getVariable ["comspec_atak_native_mesh_compromise_auto", true]) isEqualTo true)}) exitWith {};
        private _l = toLower _item;
        if ((_l find "mpu5") < 0 && {_l isNotEqualTo "comspec_atak_keyloader"}) exitWith {};
        private _owner = _from getVariable ["COMSPEC_ATAK_MeshOwnerSide", ""];
        if (_owner isEqualTo "" || {_owner isEqualTo str side group player}) exitWith {};
        // Clés portées par l'appareil (-1 : inconnu, ancienne version : clé de réseau seule, comme avant). Effacées avant la prise : rien à compromettre.
        private _held = _from getVariable ["COMSPEC_ATAK_MeshKeysHeld", -1];
        if (_held isEqualTo []) exitWith {};
        ["comspec_atak_native_meshKeysSrv", [player, "compromiseSide", [_owner, [[], _held] select (_held isEqualType [])]]] call CBA_fnc_serverEvent;
    };
    // ---- Serveur
    case "init": {
        if (!isServer) exitWith {};
        ["comspec_atak_native_meshKeysSrv", {
            params [["_who", objNull], ["_a", ""], ["_xa", []]];
            if (isNull _who || {!(_a in ["request", "rekey", "compromise", "compromiseSide", "otar"])}) exitWith {};
            [_a, _xa, _who] call comspec_atak_native_fnc_meshKeys;
        }] call CBA_fnc_addEventHandler;
    };
    case "define": {
        if (!isServer) exitWith { -1 };
        _arg params [["_name", ""], ["_side", ""], ["_isNet", false]];
        private _rows = missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []];
        private _have = [_name] call _rowByName;
        if (_have isNotEqualTo []) exitWith { _have select 0 };
        if (_name isEqualTo "" || {(count _rows) >= 16}) exitWith { diag_log format ["[COMSPEC ATAK NATIVE][WARN][MESH] Clé refusée (16 au plus, nom requis) : %1", _name]; -1 };
        private _id = (count _rows) + 1;
        _rows pushBack [_id, _name select [0, 24], 1, false, _side, _isNet, [time, serverTime] select isMultiplayer];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshEpochs", _rows, true];
        ["log", ["key", format ["Clé définie : %1", _name]]] call comspec_atak_native_fnc_meshGraph;
        _id
    };
    case "rekey": {
        if (isNull _caller) exitWith { ["comspec_atak_native_meshKeysSrv", [player, "rekey", _arg]] call CBA_fnc_serverEvent; };
        if (!isServer || {!_on}) exitWith {};
        // Zeus, admin ou poste de commandement.
        if !([_caller] call comspec_atak_native_fnc_adminAllowed || {(_caller getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""} || {_caller getVariable ["COMSPEC_ATAK_MeshServerCall", false]}) exitWith {};
        private _row = [_arg param [0, -1]] call _rowOf;
        if (_row isEqualTo []) exitWith {};
        _row set [2, (_row select 2) + 1];
        _row set [3, false];
        _row set [6, [time, serverTime] select isMultiplayer];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshEpochs", missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs", true];
        ["log", ["key", format ["Changement de clé : %1, époque %2", _row select 1, _row select 2]]] call comspec_atak_native_fnc_meshGraph;
        ["comspec_atak_native_meshKeyAlert", [format ["Clé %1 : nouvelle époque (%2)", _row select 1, _row select 2]], (_row select 4) call { private _s = _this; allPlayers select { str side group _x isEqualTo _s } }] call CBA_fnc_targetEvent;
    };
    case "compromise": {
        if (isNull _caller) exitWith { ["comspec_atak_native_meshKeysSrv", [player, "compromise", _arg]] call CBA_fnc_serverEvent; };
        if (!isServer || {!_on}) exitWith {};
        if !([_caller] call comspec_atak_native_fnc_adminAllowed || {(_caller getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""}) exitWith {};
        private _row = [_arg param [0, -1]] call _rowOf;
        if (_row isEqualTo []) exitWith {};
        _row set [3, true];
        missionNamespace setVariable ["COMSPEC_ATAK_MeshEpochs", missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs", true];
        ["log", ["key", format ["Clé compromise : %1", _row select 1]]] call comspec_atak_native_fnc_meshGraph;
        ["comspec_atak_native_meshKeyAlert", [format ["Clé %1 compromise : changez-la", _row select 1]], (_row select 4) call { private _s = _this; allPlayers select { str side group _x isEqualTo _s } }] call CBA_fnc_targetEvent;
    };
    case "compromiseSide": {
        if (!isServer || {!_on}) exitWith {};
        private _owner = _arg param [0, ""];
        if !((missionNamespace getVariable ["comspec_atak_native_mesh_compromise_auto", true]) isEqualTo true) exitWith {};
        // Contrôle côté serveur : l'appelant est bien d'un autre camp.
        if (_owner isEqualTo "" || {_owner isEqualTo str side group _caller}) exitWith {};
        private _row = [_owner] call _netRow;
        if (_row isEqualTo []) exitWith {};
        // Clés portées connues : la clé de réseau seulement si l'appareil la portait, et ses clés de groupe ; inconnues : clé de réseau, comme avant.
        private _held = (_arg param [1, []]) select { _x isEqualType 0 };
        private _hit = if (_held isEqualTo []) then { [_row] } else { (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]) select { (_x select 0) in _held && {(_x select 4) isEqualTo _owner} } };
        if (_hit isEqualTo []) exitWith {};
        private _ownerSide = allPlayers select { str side group _x isEqualTo _owner };
        {
            _x set [3, true];
            ["comspec_atak_native_meshKeyGive", [_x select 0, _x select 2], _caller] call CBA_fnc_targetEvent;
            ["log", ["key", format ["Clé compromise (appareil ramassé) : %1", _x select 1]]] call comspec_atak_native_fnc_meshGraph;
            ["comspec_atak_native_meshKeyAlert", [format ["Clé %1 compromise : un appareil a été ramassé. Changez-la", _x select 1]], _ownerSide] call CBA_fnc_targetEvent;
        } forEach _hit;
        missionNamespace setVariable ["COMSPEC_ATAK_MeshEpochs", missionNamespace getVariable "COMSPEC_ATAK_MeshEpochs", true];
    };
    case "rows": {
        if (!_on) exitWith { [] };
        private _side = str side group player;
        private _rows = [["section", "Clés (mécanique de jeu)", "Aucune sécurité réelle"]];
        private _isAdm = [player] call comspec_atak_native_fnc_adminAllowed || {(player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""};
        {
            _x params ["_id", "_name", "_ep", "_cmp", "_s"];
            if (_s isNotEqualTo _side) then { continue };
            private _kLeft = [_id] call _leftOf;
            private _kSt = ["state", [_id]] call comspec_atak_native_fnc_meshKeys;
            _rows pushBack ["info", _name, [format ["époque %1 · %2 · périmée dans %3 min", _ep, _kSt, ceil (_kLeft / 60)], format ["époque %1 · %2", _ep, _kSt]] select (_kLeft <= 0)];
            if (_isAdm) then {
                _rows pushBack ["buttons", [
                    ["NOUVELLE ÉPOQUE", compile format ["['rekey', [%1]] call comspec_atak_native_fnc_meshKeys;", _id]],
                    ["COMPROMISE", compile format ["['compromise', [%1]] call comspec_atak_native_fnc_meshKeys;", _id]]
                ]];
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MeshEpochs", []]);
        _rows pushBack ["buttons", [["CHARGER LES CLÉS", { ["fill"] call comspec_atak_native_fnc_meshKeys; }, true], ["ZÉROÏSER", { ["zeroize"] call comspec_atak_native_fnc_meshKeys; }, "red"]]];
        if (["comspec_atak_native_mesh_keys_otar", false] call _toggle && {(leader group player) isEqualTo player || {(player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""}}) then {
            _rows pushBack ["buttons", [["RENOUVELER LE GROUPE (OTAR)", { ["otar"] call comspec_atak_native_fnc_meshKeys; }]]];
        };
        if (_isAdm) then {
            private _unk = ["unknown"] call comspec_atak_native_fnc_meshKeys;
            _rows pushBack ["info", "Appareils non autorisés à portée", [_unk joinString ", ", "aucun"] select (_unk isEqualTo [])];
        };
        _rows
    };
    case "unknown": {
        if (!_on) exitWith { [] };
        private _side = str side group player;
        private _r = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        ((allPlayers select { _x isNotEqualTo player && {alive _x} && {(_x distance player) < _r} }) select {
            private _n = ["node", _x] call comspec_atak_native_fnc_meshRead;
            _n isNotEqualTo [] && {!(["authorized", [_side, _n param [4, -1]]] call comspec_atak_native_fnc_meshKeys)}
        }) apply { private _h = player getDir _x; format ["appareil inconnu, %1°", round _h] }
    };
    // Actions ACE sous Téléphone ATAK > Radio de données (menu de fn_mpuLink) : chargeur, effacement d'urgence, OTAR.
    case "aceInit": {
        if (!hasInterface || {isNil "ace_interact_menu_fnc_createAction"}) exitWith { false };
        private _path = ["ACE_SelfActions", "COMSPEC_ATAK_Phone", "COMSPEC_ATAK_Mpu"];
        {
            _x params ["_aid", "_aname", "_acode", "_acond"];
            ["CAManBase", 1, _path, [_aid, _aname, "", _acode, _acond] call comspec_atak_native_fnc_aceAction, true] call ace_interact_menu_fnc_addActionToClass;
        } forEach [
            ["COMSPEC_ATAK_MeshFill", "Charger les clés", { ["fill"] call comspec_atak_native_fnc_meshKeys; },
                { ["on"] call comspec_atak_native_fnc_meshKeys && {"COMSPEC_ATAK_KeyLoader" in (items player) || {(player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""}} }],
            ["COMSPEC_ATAK_MeshZero", "Effacer les clés (urgence)", { ["zeroizeAsk"] call comspec_atak_native_fnc_meshKeys; },
                { ["on"] call comspec_atak_native_fnc_meshKeys && {(count (missionNamespace getVariable ["COMSPEC_ATAK_MeshKeysMine", createHashMap])) > 0} }],
            ["COMSPEC_ATAK_MeshOtar", "Renouveler les clés du groupe (OTAR)", { ["otar"] call comspec_atak_native_fnc_meshKeys; },
                { ["on"] call comspec_atak_native_fnc_meshKeys && {(missionNamespace getVariable ["comspec_atak_native_mesh_keys_otar", false]) isEqualTo true}
                    && {(leader group player) isEqualTo player || {(player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo ""}} }]
        ];
        true
    };
    // Émetteur (message de groupe) : les ennemis à portée qui portent un goniomètre reçoivent l'émission ; eux seuls jugent ce qu'ils en tirent ("heard").
    case "intercept": {
        if !((missionNamespace getVariable ["comspec_atak_native_mesh_intercept", false]) isEqualTo true) exitWith {};
        if (call _arcade) exitWith {};
        _arg params [["_g", 0], ["_kk", []], ["_text", ""]];
        private _r = (missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000]) * 1.5;
        private _side = side group player;
        private _ears = allPlayers select { _x isNotEqualTo player && {alive _x} && {(side group _x) isNotEqualTo _side} && {(_x distance player) < _r} && {"COMSPEC_ATAK_Goniometer" in (items _x)} };
        if (_ears isEqualTo []) exitWith {};
        private _ch = (uiNamespace getVariable ["COMSPEC_ATAK_MpuSt", createHashMap]) getOrDefault ["chan", 1];
        ["comspec_atak_native_meshIntercept", [player, getPosATL player, _ch, _kk, _text select [0, 200], ["name", [_g]] call comspec_atak_native_fnc_meshGroups], _ears] call CBA_fnc_targetEvent;
    };
    // Récepteur : spécialiste avec goniomètre. En clair (ou clé détenue, compromise) : position à 100 m près et contenu partiel.
    // Chiffré : émission et direction seulement, jamais le contenu. Relèvement ajouté à ceux de l'app Guerre électronique (carte et onglet GONIO).
    case "heard": {
        _arg params [["_from", objNull], ["_pos", [0, 0, 0]], ["_ch", 1], ["_kk", []], ["_text", ""], ["_gName", ""]];
        if (isNull _from || {!(_text isEqualType "")}) exitWith {};
        if !((missionNamespace getVariable ["comspec_atak_native_mesh_intercept", false]) isEqualTo true) exitWith {};
        if !(["allowed"] call comspec_atak_native_fnc_meshEW) exitWith {};
        if !("COMSPEC_ATAK_Goniometer" in (items player)) exitWith {};
        private _r = missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000];
        private _me = getPosATL player;
        _me = [_me select 0, _me select 1, 0];
        private _d = _me distance2D _pos;
        if (_d > _r * 1.5) exitWith {};
        private _clr = (_kk isEqualTo []) || {(["open", [_kk, _text]] call comspec_atak_native_fnc_meshKeys) isEqualTo _text};
        // Erreur angulaire : 3° tout près, 18° à la limite (même choix que le goniomètre).
        private _err = 3 + 15 * ((_d / _r) min 1);
        private _brg = ((_me getDir _pos) + (random [-_err, 0, _err]) + 360) mod 360;
        private _band = switch (true) do { case (_d < 500): { [0, 500] }; case (_d < 1000): { [500, 1000] }; case (_d < 2000): { [1000, 2000] }; case (_d < 3000): { [2000, 3000] }; default { [3000, 9000] }; };
        // En clair, l'en-tête dit d'où part le message : relèvement juste, bande de 200 m.
        if (_clr) then { _err = 2; _brg = _me getDir _pos; _band = [(_d - 100) max 0, _d + 100]; };
        private _hour = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        private _bear = missionNamespace getVariable ["COMSPEC_ATAK_EwBearings", []];
        _bear pushBack [_me, _brg, _band select 0, _band select 1, ["ÉMISSION CHIFFRÉE", "EN CLAIR"] select _clr, time, _err, _hour];
        while { (count _bear) > 15 } do { _bear deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_EwBearings", _bear];
        // Contenu partiel : plus on est loin, plus il manque de mots.
        private _lossP = 0.2 + 0.5 * ((_d / _r) min 1);
        private _part = ["", ((_text splitString " ") apply { [_x, "…"] select ((random 1) < _lossP) }) joinString " "] select _clr;
        private _log = uiNamespace getVariable ["COMSPEC_ATAK_MeshIntercepts", []];
        _log pushBack [_hour, _ch, _clr, round _brg, round _err, ["", [_pos, 6] call comspec_atak_native_fnc_gridRef] select _clr, _part, ["", _gName] select _clr];
        while { (count _log) > 10 } do { _log deleteAt 0; };
        uiNamespace setVariable ["COMSPEC_ATAK_MeshIntercepts", _log];
        [format [["Interception, canal %1 : émission chiffrée, contenu illisible", "Interception, canal %1 : message en clair"] select _clr, _ch], 4] call comspec_atak_native_fnc_toast;
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "EW") then { [{ ["EW"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
    };
    // Lignes de l'app Guerre électronique, onglet GONIO.
    case "interceptRows": {
        private _log = uiNamespace getVariable ["COMSPEC_ATAK_MeshIntercepts", []];
        if (_log isEqualTo []) exitWith { [] };
        private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        private _rows = [["section", "Interceptions", "En clair : position et contenu partiel. Chiffré : émission et direction seulement"]];
        for "_i" from ((count _log) - 1) to 0 step -1 do {
            (_log select _i) params ["_ih", "_ich", "_iclr", "_ibrg", "_ierr", "_igrid", "_ipart", "_ign"];
            _rows pushBack ["info", format ["%1 · canal %2", _ih, _ich], [format ["chiffré · %1° à %2° près", _ibrg, _ierr], format ["en clair · %1 · groupe %2", _igrid, [_ign] call _esc]] select _iclr];
            if (_iclr && {_ipart isNotEqualTo ""}) then { _rows pushBack ["text", format ["<t size='0.85'>« %1 »</t>", [_ipart] call _esc]]; };
        };
        _rows pushBack ["buttons", [["EFFACER LES INTERCEPTIONS", { uiNamespace setVariable ["COMSPEC_ATAK_MeshIntercepts", []]; ["EW"] call comspec_atak_native_fnc_pageRender; }]]];
        _rows
    };
};
