/*
    Diffusion des marqueurs posés depuis le téléphone (palette, éditeur, traits, LZ), réglage serveur
    comspec_atak_native_marker_net :
      "arma" (par défaut) : marqueur Arma sur le canal choisi, comme la carte d'Arma. Il est aussi sur la carte d'Arma de
             tous les joueurs du canal, avec ou sans ATAK, avec ou sans réseau.
      "atak" : réseau ATAK uniquement. Marqueur LOCAL (createMarkerLocal) : rien n'arrive sur la carte d'Arma des autres.
             Il part par le réseau du téléphone (fn_netSend : sans signal, l'envoi attend le retour du réseau), le serveur
             le garde (joueurs qui rejoignent) et le remet aux joueurs du canal ; chez eux il n'apparaît que si leur
             téléphone reçoit (fn_p2pReachable : téléphone porté, batterie, signal), sinon il attend au relais et arrive
             au retour du signal. Les canaux d'Arma n'ont plus besoin d'être ouverts aux marqueurs.
             Canaux : Global et Camp = le camp (le réseau ATAK est celui du camp) ; Commandement = chefs de groupe du
             camp ; Groupe ; Véhicule = équipage du véhicule de l'auteur (son groupe s'il est à pied) ; Direct = 50 m.
             Modifications (éditeur, durée écoulée, suppression, même depuis la carte d'Arma) suivent le même chemin.
    Heure et auteur : ceux de la pose, identiques chez tous (COMSPEC_ATAK_MarkerMeta, fiche du marqueur).
    Actions :
      ["on"]                                -> vrai en mode "atak"
      ["create", [nom, position, canal]]    -> nom du marqueur créé (global ou local selon le mode), "" si refusé
      ["touch", [nom, supprimé]]            le téléphone a posé ou modifié ce marqueur (appelé par fn_markerWeb) : envoi
      ["deleted", nom]                      EH MarkerDeleted : marqueur du réseau ATAK effacé ici, effacé partout
      ["recv", [op, nom, état, auteur, heure]]  (client, événement comspec_atak_native_tacMk ; op "bulk" : liste)
      ["flush"]                             (client, toutes les 2 s) livre ce qui attendait le retour du signal
      ["srv", [op, nom, état, portée, auteur, heure]]  (serveur, événement comspec_atak_native_tacMkSrv ; op "sync" :
                                            nom = joueur arrivé, renvoie les marqueurs de ses canaux et l'heure / l'auteur
                                            des marqueurs posés avant son arrivée, op "meta" côté client)
      ["srv", ["note", nom, [texte, auteur] | []]]  (serveur) description du marqueur, seul à diffuser la table des notes
      ["srvMeta", [nom, auteur]]            (serveur, EH MarkerCreated / MarkerDeleted) : heure et auteur des marqueurs Arma
    Table locale COMSPEC_ATAK_TacMk : nom -> [signature du dernier état connu, canal] (pas de renvoi en boucle).
*/
params [["_act", ""], ["_arg", []]];
private _on = (missionNamespace getVariable ["comspec_atak_native_marker_net", "arma"]) isEqualTo "atak";
private _tab = missionNamespace getVariable "COMSPEC_ATAK_TacMk";
if (isNil "_tab") then { _tab = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_TacMk", _tab]; };
private _meta = missionNamespace getVariable "COMSPEC_ATAK_MarkerMeta";
if (isNil "_meta") then { _meta = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MarkerMeta", _meta]; };
private _hms = { (([dayTime, "HH:MM:SS"] call BIS_fnc_timeToString) splitString ":") joinString "" };
private _stateOf = {
    params ["_m"];
    [markerPos _m, markerShape _m, markerType _m, markerText _m, markerColor _m, markerDir _m, markerAlpha _m, markerSize _m,
        markerBrush _m, [[], markerPolyline _m] select ((markerShape _m) isEqualTo "POLYLINE")]
};
private _send = {
    params ["_op", "_m", "_st"];
    private _ch = (_tab getOrDefault [_m, ["", 1]]) select 1;
    private _mt = _meta getOrDefault [_m, []];
    private _args = [_op, _m, _st, [str side group player, _ch, group player, objectParent player, getPosASL player],
        _mt param [1, [player] call comspec_atak_native_fnc_unitCallsign], _mt param [0, call _hms]];
    [{ ["comspec_atak_native_tacMkSrv", _this] call CBA_fnc_serverEvent; }, _args, "Marqueur", 1, 1, "tmk:" + _m] call comspec_atak_native_fnc_netSend;
};
switch (_act) do {
    case "on": { _on };
    case "create": {
        _arg params ["_name", "_pos", ["_channel", 1]];
        if !(_on) exitWith { createMarker [_name, _pos, _channel, player] };
        private _m = createMarkerLocal [_name, _pos];
        if (_m isEqualTo "") exitWith { "" };
        // Signature vide : le premier fn_markerWeb (type, couleur, texte posés) l'envoie.
        _tab set [_m, ["", _channel]];
        _meta set [_m, [call _hms, [player] call comspec_atak_native_fnc_unitCallsign]];
        _m
    };
    case "touch": {
        _arg params [["_m", ""], ["_deleted", false]];
        if (!_on || {!(_m in _tab)}) exitWith {};
        if (_deleted || {(markerShape _m) isEqualTo ""}) exitWith {
            _tab deleteAt _m;
            ["del", _m, []] call _send;
        };
        private _st = [_m] call _stateOf;
        private _sig = str _st;
        if (((_tab get _m) select 0) isEqualTo _sig) exitWith {};
        (_tab get _m) set [0, _sig];
        ["set", _m, _st] call _send;
    };
    case "deleted": {
        if (!_on || {!(_arg in _tab)}) exitWith {};
        _tab deleteAt _arg;
        ["del", _arg, []] call _send;
    };
    case "recv": {
        if (!hasInterface) exitWith {};
        _arg params [["_op", ""], ["_m", ""], ["_st", []], ["_author", ""], ["_at", ""]];
        if (_op isEqualTo "bulk") exitWith { { ["recv", _x] call comspec_atak_native_fnc_markerNet; } forEach _m; };
        // Heure et auteur des marqueurs posés avant mon arrivée (ceux que j'ai vus naître gardent les miens).
        if (_op isEqualTo "meta") exitWith { { _x params ["_k", "_v"]; if !(_k in _meta) then { _meta set [_k, _v]; }; } forEach _m; };
        if (_m isEqualTo "") exitWith {};
        // Téléphone qui ne reçoit pas (pas de téléphone, batterie vide, sans signal) : attend au relais, le dernier état gagne.
        if !([] call comspec_atak_native_fnc_p2pReachable) exitWith {
            private _hold = missionNamespace getVariable "COMSPEC_ATAK_TacMkHold";
            if (isNil "_hold") then { _hold = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_TacMkHold", _hold]; };
            _hold set [_m, _arg];
        };
        if (_op isEqualTo "del") exitWith {
            // Retiré de la table d'abord : l'EH MarkerDeleted ne le renvoie pas.
            _tab deleteAt _m;
            if ((markerShape _m) isNotEqualTo "") then { deleteMarkerLocal _m; };
        };
        if ((count _st) < 10) exitWith {};
        if ((markerShape _m) isNotEqualTo "" && {((_tab getOrDefault [_m, [""]]) select 0) isEqualTo (str _st)}) exitWith {};
        _st params ["_pos", "_shape", "_type", "_text", "_color", "_dir", "_alpha", "_size", "_brush", "_poly"];
        if ((markerShape _m) isEqualTo "") then { createMarkerLocal [_m, _pos]; } else { _m setMarkerPosLocal _pos; };
        _m setMarkerShapeLocal _shape;
        if (_shape isEqualTo "POLYLINE") then { _m setMarkerPolylineLocal _poly; } else { _m setMarkerTypeLocal _type; };
        _m setMarkerColorLocal _color;
        _m setMarkerDirLocal _dir;
        _m setMarkerSizeLocal _size;
        _m setMarkerBrushLocal _brush;
        _m setMarkerAlphaLocal _alpha;
        _m setMarkerTextLocal _text;
        _tab set [_m, [str ([_m] call _stateOf), (_tab getOrDefault [_m, ["", 1]]) select 1]];
        _meta set [_m, [_at, _author]];
        missionNamespace setVariable ["COMSPEC_ATAK_MkRev", (missionNamespace getVariable ["COMSPEC_ATAK_MkRev", 0]) + 1];
    };
    case "flush": {
        private _hold = missionNamespace getVariable ["COMSPEC_ATAK_TacMkHold", createHashMap];
        if ((count _hold) isEqualTo 0 || {!([] call comspec_atak_native_fnc_p2pReachable)}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TacMkHold", createHashMap];
        { ["recv", _y] call comspec_atak_native_fnc_markerNet; } forEach _hold;
        ["INFO", format ["Réseau revenu : %1 marqueur(s) reçu(s)", count _hold], 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "srvMeta": {
        if (!isServer) exitWith {};
        _arg params [["_m", ""], ["_owner", objNull]];
        private _mm = missionNamespace getVariable "COMSPEC_ATAK_MkMetaSrv";
        if (isNil "_mm") then { _mm = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MkMetaSrv", _mm]; };
        // Marqueur effacé vu par le serveur : sa note pourra être retirée (op "note").
        if (isNull _owner) exitWith {
            _mm deleteAt _m;
            private _gone = missionNamespace getVariable "COMSPEC_ATAK_MkGoneSrv";
            if (isNil "_gone") then { _gone = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MkGoneSrv", _gone]; };
            // Seuls comptent les marqueurs qui ont une note (pas de table sans fin sur une mission qui efface beaucoup).
            if ((count _gone) > 1000) then {
                private _nt = missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap];
                { if !(_x in _nt) then { _gone deleteAt _x; }; } forEach (keys _gone);
            };
            _gone set [_m, true];
        };
        if ((count _mm) > 600) then { { if ((markerShape _x) isEqualTo "") then { _mm deleteAt _x; }; } forEach (keys _mm); };
        _mm set [_m, [call _hms, [_owner] call comspec_atak_native_fnc_unitCallsign]];
    };
    case "srv": {
        if (!isServer) exitWith {};
        _arg params [["_op", ""], ["_m", ""], ["_st", []], ["_scope", []], ["_author", ""], ["_at", ""]];
        private _srv = missionNamespace getVariable "COMSPEC_ATAK_TacMkSrv";
        if (isNil "_srv") then { _srv = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_TacMkSrv", _srv]; };
        // Marqueurs que le serveur a vus effacés (EH MarkerDeleted, op "del") depuis la dernière note.
        private _gone = missionNamespace getVariable "COMSPEC_ATAK_MkGoneSrv";
        if (isNil "_gone") then { _gone = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MkGoneSrv", _gone]; };
        // Joueur dans une portée [camp, canal, groupe, véhicule, position ASL] de l'auteur au moment de la pose.
        private _inScope = {
            params ["_sc", "_u"];
            _sc params [["_side", ""], ["_ch", 1], ["_grp", grpNull], ["_veh", objNull], ["_pos", [0, 0, 0]]];
            !(_u isKindOf "HeadlessClient_F") && {(str side group _u) isEqualTo _side} && {
                switch (_ch) do {
                    case 2: { (leader group _u) isEqualTo _u };
                    case 3: { (group _u) isEqualTo _grp };
                    case 4: { [(objectParent _u) isEqualTo _veh, (group _u) isEqualTo _grp] select (isNull _veh) };
                    case 5: { ((getPosASL _u) distance _pos) < 50 };
                    default { true };
                }
            }
        };
        // Joueurs de cette portée (envoi d'un set / del).
        private _who = { params ["_sc"]; allPlayers select { [_sc, _x] call _inScope } };
        switch (_op) do {
            case "sync": {
                if (isNull _m) exitWith {};
                private _mm = (missionNamespace getVariable ["COMSPEC_ATAK_MkMetaSrv", createHashMap]) toArray false;
                if (_mm isNotEqualTo []) then { ["comspec_atak_native_tacMk", ["meta", _mm], _m] call CBA_fnc_targetEvent; };
                private _list = [];
                // Portée testée pour le seul joueur arrivé (pas la liste de tous les joueurs par marqueur).
                { if ([_y select 1, _m] call _inScope) then { _list pushBack ["set", _x, _y select 0, _y select 2, _y select 3]; }; } forEach _srv;
                if (_list isNotEqualTo []) then { ["comspec_atak_native_tacMk", ["bulk", _list], _m] call CBA_fnc_targetEvent; };
            };
            case "del": {
                _gone set [_m, true];
                private _e = _srv deleteAt _m;
                if (isNil "_e") exitWith {};
                // À tout le camp : un joueur qui a changé de groupe garde sinon un marqueur effacé.
                private _side = (_e select 1) param [0, ""];
                ["comspec_atak_native_tacMk", ["del", _m], allPlayers select { (str side group _x) isEqualTo _side }] call CBA_fnc_targetEvent;
            };
            case "note": {
                // Description d'un marqueur (fn_markerEditSave, fn_markerDelete) : une clé à la fois, la table publique
                // (COMSPEC_ATAK_MarkerNotes, JIP) n'est éditée et diffusée que par le serveur (pas de copies concurrentes).
                // _st = [texte, auteur], [] = note retirée. Seules les notes des marqueurs que le serveur a vus effacés sont
                // retirées : un marqueur du réseau ATAK (local aux clients) posé sans signal n'est pas encore connu du serveur
                // (fn_netSend attend le réseau) alors que sa note arrive tout de suite, elle était perdue.
                if (_m isEqualTo "" || {!(_st isEqualTo [] || {_st isEqualTypeArray ["", ""]})}) exitWith {};
                private _old = missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap];
                private _notes = +_old;
                { if (_x isNotEqualTo _m && {_x in _gone} && {(markerShape _x) isEqualTo ""} && {!(_x in _srv)}) then { _notes deleteAt _x; }; } forEach (keys _notes);
                missionNamespace setVariable ["COMSPEC_ATAK_MkGoneSrv", createHashMap];
                if (_st isEqualTo []) then { _notes deleteAt _m; } else { _notes set [_m, _st]; };
                // Toujours diffusée : sur un serveur joueur, la copie locale du poste hôte est déjà à jour (événement
                // serveur exécuté sur place), une comparaison avec elle n'enverrait jamais la note aux autres.
                missionNamespace setVariable ["COMSPEC_ATAK_MarkerNotes", _notes, true];
            };
            case "set": {
                // Marqueur modifié par un autre joueur : la portée, l'auteur et l'heure restent ceux de la pose.
                private _e = _srv getOrDefault [_m, []];
                if (_e isNotEqualTo []) then { _scope = _e select 1; _author = _e select 2; _at = _e select 3; };
                if ((count _srv) >= 500 && {_e isEqualTo []}) exitWith {};
                _srv set [_m, [_st, _scope, _author, _at]];
                _gone deleteAt _m;
                ["comspec_atak_native_tacMk", ["set", _m, _st, _author, _at], [_scope] call _who] call CBA_fnc_targetEvent;
            };
        };
    };
};
