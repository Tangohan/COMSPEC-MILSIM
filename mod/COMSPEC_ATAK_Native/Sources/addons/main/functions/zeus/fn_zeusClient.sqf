/*
    Contrôle Zeus, chez le joueur visé. Événement CBA comspec_atak_native_zeusFx (envoyé par fn_zeusServer) : [action, arguments]
      "refresh" : l'état durable (fn_zeusFx) a changé : annonce au joueur, musique coupée, téléphone rangé ou redessiné
      "once" [type, arguments] : effet ponctuel
        "msg" [texte]          message du commandement en plein écran + notification
        "warn" [texte]         avertissement rouge en plein écran, alarme du téléphone
        "glitch" [s]           écran qui bugue (parasites, couleurs, coupures)
        "noise" [s]            alarme stridente du téléphone, entendue autour du joueur
        "buzz" [nombre]        vibrations à répétition
        "crack" [0-100]        écran fêlé (dégâts ajoutés)
        "emp" [s]              impulsion électromagnétique : extinction, pixels, GPS, antenne, batterie touchés
        "destroy"              appareil détruit         "repair" : téléphone réparé (dégâts effacés)
        "battery" [0-100]      niveau de batterie       "reboot" : redémarrage forcé
      "tick" : chaque seconde (XEH_postInitClient) : alarme en cours, annonce de surchauffe
    Les dégâts passent par fn_deviceDamage : il faut le réglage serveur « Dégâts du téléphone » (comspec_atak_native_damage_sim).
*/
params [["_act", "tick"], ["_args", []]];
if (!hasInterface) exitWith {};
private _redraw = {
    uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
    uiNamespace setVariable ["COMSPEC_ATAK_EwFx", []];
    uiNamespace setVariable ["COMSPEC_ATAK_ZeusJamC", []];
    if (!isNull ([] call comspec_atak_native_fnc_display)) then { [] call comspec_atak_native_fnc_deviceOverlay; };
};
// Texte saisi par le Zeus, affiché en texte structuré : balises neutralisées.
private _esc = { [[_this, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
switch (_act) do {
    case "refresh": {
        // Ce qui a changé depuis la dernière fois : une annonce par effet ajouté ou retiré.
        private _keys = ["off", "lock", "mute", "jam", "heat", "drain"];
        private _now = _keys apply { [_x, "-"] call comspec_atak_native_fnc_zeusFx };
        private _was = uiNamespace getVariable ["COMSPEC_ATAK_ZeusSeen", _keys apply { "-" }];
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusSeen", _now];
        {
            private _k = _x;
            private _v = _now select _forEachIndex;
            private _o = _was param [_forEachIndex, "-"];
            private _on = _v isNotEqualTo "-";
            if (_v isEqualTo _o) then { continue; };
            switch (_k) do {
                case "off": { if (_on) then { hintSilent ((parseText "<t size='1.1' color='#e5483a'>Terminal désactivé à distance</t><br/>Le commandement a coupé votre ATAK.") call comspec_atak_native_fnc_tr); } else { ["SUCCESS", "Terminal réactivé par le commandement", 5, 60] call comspec_atak_native_fnc_notify; }; };
                case "lock": { if (!_on) then { ["SUCCESS", "Terminal déverrouillé", 4, 50] call comspec_atak_native_fnc_notify; }; };
                case "mute": { ["WARNING", ["Haut-parleurs rétablis", "Haut-parleurs coupés à distance"] select _on, 4, 40] call comspec_atak_native_fnc_notify; };
                case "jam": { ["WARNING", [format ["Brouillage : %1 %%", _v], "Fin du brouillage"] select !_on, 4, 40] call comspec_atak_native_fnc_notify; };
                case "heat": { ["WARNING", [["Téléphone chaud", "Téléphone brûlant : il ralentit et se vide vite", "Surchauffe critique : arrêt de sécurité"] select ((_v max 1 min 3) - 1), "Téléphone refroidi"] select !_on, 6, 50] call comspec_atak_native_fnc_notify; };
                case "drain": { if (_on) then { ["WARNING", "La batterie se vide anormalement vite", 5, 40] call comspec_atak_native_fnc_notify; }; };
            };
        } forEach _keys;
        // Haut-parleurs coupés : la musique s'arrête tout de suite (fn_musicTick l'empêche de repartir).
        if (["mute", false] call comspec_atak_native_fnc_zeusFx) then { [] call comspec_atak_native_fnc_musicTick; };
        call _redraw;
    };
    case "once": {
        _args params [["_kind", ""], ["_a", []]];
        if !(_a isEqualType []) then { _a = [_a]; };
        switch (_kind) do {
            case "msg": {
                private _t = (_a param [0, ""]) call _esc;
                [("MESSAGE DU COMMANDEMENT") call comspec_atak_native_fnc_tr, _t, ("Commandement") call comspec_atak_native_fnc_tr, [0.08, 0.22, 0.42, 0.92], 10] call comspec_atak_native_fnc_fullAlert;
                ["MESSAGE", format ["Commandement : %1", _t], 10, 90] call comspec_atak_native_fnc_notify;
                [] call comspec_atak_native_fnc_vibrate;
            };
            case "warn": {
                private _t = (_a param [0, ""]) call _esc;
                if (_t isEqualTo "") then { _t = ("Comportement signalé au commandement.") call comspec_atak_native_fnc_tr; };
                [("AVERTISSEMENT") call comspec_atak_native_fnc_tr, _t, ("Commandement") call comspec_atak_native_fnc_tr, [0.6, 0.06, 0.04, 0.94], 12] call comspec_atak_native_fnc_fullAlert;
                ["WARNING", format ["Avertissement : %1", _t], 10, 95] call comspec_atak_native_fnc_notify;
                playSound3D ["A3\Sounds_F\sfx\alarm_independent.wss", player, false, getPosASL player, 1.2, 1.3, 40];
            };
            case "glitch": { uiNamespace setVariable ["COMSPEC_ATAK_ZeusGlitch", diag_tickTime + ((_a param [0, 20]) max 3 min 300)]; call _redraw; };
            case "noise": { uiNamespace setVariable ["COMSPEC_ATAK_ZeusNoise", diag_tickTime + ((_a param [0, 15]) max 3 min 120)]; ["tick"] call comspec_atak_native_fnc_zeusClient; };
            case "buzz": {
                private _n = (_a param [0, 6]) max 1 min 30;
                for "_i" from 0 to (_n - 1) do { [{ [] call comspec_atak_native_fnc_vibrate; }, [], _i * 1.3] call CBA_fnc_waitAndExecute; };
            };
            case "crack": { [((_a param [0, 40]) max 5 min 99) / 100, "Choc", 0, createHashMapFromArray [["scratch", 0.3]]] call comspec_atak_native_fnc_deviceDamage; };
            case "emp": {
                private _s = (_a param [0, 60]) max 5 min 900;
                [0.1, "Impulsion électromagnétique", _s, createHashMapFromArray [["pixels", 0.4], ["gps", 0.5], ["antenna", 0.4], ["battery", 0.3]]] call comspec_atak_native_fnc_deviceDamage;
                missionNamespace setVariable ["COMSPEC_ATAK_Battery", ((missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) - 30) max 1];
                uiNamespace setVariable ["COMSPEC_ATAK_ZeusGlitch", diag_tickTime + 3];
                ["broken"] call comspec_atak_native_fnc_powerFx;
                call _redraw;
            };
            case "destroy": { [1, "Défaillance totale", 0, createHashMapFromArray [["destroy", true]]] call comspec_atak_native_fnc_deviceDamage; };
            case "repair": {
                uiNamespace setVariable ["COMSPEC_ATAK_ZeusGlitch", 0];
                uiNamespace setVariable ["COMSPEC_ATAK_ZeusNoise", 0];
                ["repair"] call comspec_atak_native_fnc_deviceRepair;
                call _redraw;
            };
            case "reboot": { ["reboot"] call comspec_atak_native_fnc_deviceRepair; };
            case "battery": {
                private _lv = (_a param [0, 100]) max 0 min 100;
                missionNamespace setVariable ["COMSPEC_ATAK_Battery", _lv];
                if (_lv <= 0) then { ["empty"] call comspec_atak_native_fnc_powerFx; };
                [] call comspec_atak_native_fnc_battery;
                call _redraw;
            };
        };
    };
    case "tick": {
        // Alarme de punition : bip strident toutes les 2 s, entendu autour du joueur (jusqu'à 60 m).
        if ((uiNamespace getVariable ["COMSPEC_ATAK_ZeusNoise", 0]) > diag_tickTime && {alive player}) then {
            if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_ZeusNoiseAt", -10]) >= 1.9) then {
                uiNamespace setVariable ["COMSPEC_ATAK_ZeusNoiseAt", diag_tickTime];
                playSound3D ["A3\Sounds_F\sfx\alarm_independent.wss", player, false, getPosASL player, 1.5, 1.5, 60];
                [] call comspec_atak_native_fnc_vibrate;
            };
        };
        // Surchauffe critique : arrêt de sécurité (fn_canUse) ; brûlant : l'écran saute de temps en temps (fn_zeusOverlay).
        private _sig = str ([["off", false], ["lock", ""], ["heat", 0], ["mute", false], ["jam", 0], ["drain", 0]] apply { _x call comspec_atak_native_fnc_zeusFx });
        if (_sig isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_ZeusSig", ""])) then {
            // Arrivée en cours de partie ou réapparition : état relu sans attendre l'événement du serveur.
            uiNamespace setVariable ["COMSPEC_ATAK_ZeusSig", _sig];
            ["refresh"] call comspec_atak_native_fnc_zeusClient;
        };
    };
};
true
