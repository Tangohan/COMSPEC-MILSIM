/*
    Case « RELAIS ATAK » près du cadre de transmission d'ACRE2 (TX: AN/PRC-152), pendant une émission dont la voix passe réellement par le maillage.
    Rien à voir avec l'interface d'ACRE2 : notre propre calque (cutRsc), posé à côté de son cadre dont il lit la position (IGUI_grid_ACRE_notification_X / Y).
    Coût nul hors émission : aucune boucle, seulement des événements. Aucune dépendance à ACRE2 ni à TFAR : sans eux, les événements ne partent jamais.
    Ce qui prouve le relais :
      - ACRE2 : le signal est calculé chez le récepteur (fn_mpuVoice "signal"). Quand le maillage y bat le signal direct, le récepteur renvoie à l'émetteur
        l'événement comspec_atak_native_meshVoiceAck [radio, sauts]. Sans cet accusé (liaison directe suffisante, personne de ponté à l'écoute) : pas de case.
        Plus d'accusé pendant 5 s (récepteur parti, maillage perdu) : la case disparaît, même alternat encore appuyé.
      - TFAR : fn_mpuVoice "tfBefore" appelle "show" quand il allonge vraiment la portée d'émission pour joindre un correspondant ponté. À confirmer en jeu.
    Le lien Athena ne transporte pas la voix (ni fn_mpuVoice ni fn_meshGraph ne le lisent) : la case ne le mentionne pas.
    Réglages joueur : comspec_atak_native_relay_tx_badge (afficher), comspec_atak_native_relay_tx_badge_pos (position).
      "init" ; "start" [radio] ; "stop" ; "ack" [radio, sauts] ; "show" [sauts] ; "check" ; "draw" [sauts]
*/
params [["_act", ""], ["_arg", []]];
disableSerialization;
private _layer = "COMSPEC_ATAK_RelayTx";
switch (_act) do {
    case "init": {
        if (!hasInterface) exitWith {};
        ["acre_startedSpeaking", {
            params ["_u", ["_r", false], ["_id", ""]];
            if (_u isNotEqualTo player || {!(_r isEqualTo true)} || {!(_id isEqualType "")}) exitWith {};
            ["start", [_id]] call comspec_atak_native_fnc_relayTxBadge;
        }] call CBA_fnc_addEventHandler;
        ["acre_stoppedSpeaking", {
            params ["_u"];
            if (_u isEqualTo player) then { ["stop"] call comspec_atak_native_fnc_relayTxBadge; };
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_meshVoiceAck", { ["ack", _this] call comspec_atak_native_fnc_relayTxBadge; }] call CBA_fnc_addEventHandler;
    };
    case "start": {
        uiNamespace setVariable ["COMSPEC_ATAK_RelayTx", [_arg param [0, ""], -1, -100]];
    };
    case "stop": {
        uiNamespace setVariable ["COMSPEC_ATAK_RelayTx", []];
        if (uiNamespace getVariable ["COMSPEC_ATAK_RelayTxUp", false]) then {
            uiNamespace setVariable ["COMSPEC_ATAK_RelayTxUp", false];
            (_layer call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
        };
    };
    case "ack": {
        _arg params [["_id", ""], ["_hops", -1]];
        (uiNamespace getVariable ["COMSPEC_ATAK_RelayTx", []]) params [["_cur", ""], ["_best", -1], ["_at", -100]];
        // Accusé d'une émission finie, ou d'une autre radio : ignoré. Plusieurs récepteurs : le plus court chemin reçu ces 3 dernières secondes.
        if (_cur isEqualTo "" || {_cur isNotEqualTo _id} || {!(_hops isEqualType 0)} || {_hops < 0}) exitWith {};
        private _h = [_hops, _best min _hops] select (_best >= 0 && {diag_tickTime - _at < 3});
        uiNamespace setVariable ["COMSPEC_ATAK_RelayTx", [_cur, _h, diag_tickTime]];
        ["draw", [_h]] call comspec_atak_native_fnc_relayTxBadge;
        [{ ["check"] call comspec_atak_native_fnc_relayTxBadge; }, [], 5] call comspec_atak_native_fnc_waitAndExec;
    };
    case "check": {
        (uiNamespace getVariable ["COMSPEC_ATAK_RelayTx", []]) params [["_cur", ""], "", ["_at", -100]];
        if (_cur isEqualTo "" || {diag_tickTime - _at < 4.5}) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_RelayTxUp", false];
        (_layer call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
    };
    case "show": {
        ["draw", [_arg param [0, 0]]] call comspec_atak_native_fnc_relayTxBadge;
    };
    case "draw": {
        if !((missionNamespace getVariable ["comspec_atak_native_relay_tx_badge", true]) isEqualTo true) exitWith {};
        private _h = _arg param [0, 0];
        if !(uiNamespace getVariable ["COMSPEC_ATAK_RelayTxUp", false]) then {
            (_layer call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleRelayTx", "PLAIN", 0, false];
            uiNamespace setVariable ["COMSPEC_ATAK_RelayTxUp", true];
        };
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_RelayTxDisplay", displayNull];
        if (isNull _d) exitWith {};
        private _c = _d displayCtrl 7751;
        private _t = _d displayCtrl 7752;
        // Cadre d'ACRE2 : 0,352 x 0,128, coin bas droit de l'écran, déplaçable dans la disposition de l'interface d'Arma. Ses cadres s'empilent vers le haut :
        // à gauche, la case ne peut pas les couvrir.
        private _ax = profileNamespace getVariable ["IGUI_grid_ACRE_notification_X", safeZoneX + safeZoneW - 0.352];
        private _ay = profileNamespace getVariable ["IGUI_grid_ACRE_notification_Y", safeZoneY + safeZoneH - 0.128];
        private _fh = 0.034 max (pixelH * 14);
        private _w = _fh * 8.5;
        private _hh = _fh * 1.5;
        private _gap = 0.006;
        private _pos = switch (missionNamespace getVariable ["comspec_atak_native_relay_tx_badge_pos", 0]) do {
            case 1: { [_ax, _ay - _hh - _gap] };
            case 2: { [safeZoneX + safeZoneW - _w - 0.01, safeZoneY + 0.01] };
            case 3: { [safeZoneX + 0.01, safeZoneY + safeZoneH - _hh - 0.01] };
            default { [_ax - _w - _gap, _ay + 0.128 - _hh] };
        };
        { _x ctrlSetPosition [_pos select 0, _pos select 1, _w, _hh]; _x ctrlCommit 0; } forEach [_c, _t];
        _t ctrlSetFontHeight _fh;
        _t ctrlSetText ((["RELAIS ATAK", format ["RELAIS ATAK · %1 saut(s)", _h]] select (_h > 0)) call comspec_atak_native_fnc_tr);
    };
};
