/*
 * Auteur: COMSPEC
 * Système de sons à distance (Troll + Réaliste)
 * Appelé par l'API backend via extension
 * 
 * Paramètres:
 * 0: STRING - Type son ("troll" ou "realistic")
 * 1: STRING - Identifiant du son
 * 2: ARRAY (optionnel) - Position [x, y, z] pour son 3D réaliste
 * 3: NUMBER (optionnel) - Volume (0-1, défaut 1)
 * 4: NUMBER (optionnel) - Distance audible (mètres, défaut 100)
 * 
 * Retour: BOOL - Succès
 */

params [
    ["_type", "realistic", [""]],
    ["_soundId", "", [""]],
    ["_position", [], [[]]],
    ["_volume", 1, [0]],
    ["_distance", 100, [0]]
];

if (_soundId isEqualTo "") exitWith {
    diag_log "[COMSPEC ATAK] playRemoteSound: soundId vide";
    false
};

// Vérifier mode troll activé si son troll
if (_type isEqualTo "troll") then {
    private _trollEnabled = missionNamespace getVariable ["comspec_atak_troll_mode_enabled", false];
    if (!_trollEnabled) exitWith {
        diag_log format ["[COMSPEC ATAK] playRemoteSound: Mode troll désactivé, son '%1' ignoré", _soundId];
        false
    };
};

// === SONS TROLL ===
private _trollSounds = createHashMapFromArray [
    ["airhorn", "Alarm"], // Klaxon
    ["inception", "RadioAmbient9"], // BWOOOOM
    ["alert_crazy", "UAV_loop"], // Alerte folle
    ["clown", "FD_Finish_F"], // Cirque
    ["suspense", "UAV_05"], // Suspense
    ["dramatic", "FD_Start_F"], // Dramatique
    ["victory", "FD_CP_Clear_F"], // Victoire
    ["fail", "FD_CP_Not_Clear_F"], // Échec
    ["rickroll", "RadioAmbient1"], // Rick Astley (simulation)
    ["nope", "UAV_03"], // Nope
    ["yeet", "FD_Finish_F"], // YEET
    ["bruh", "UAV_loop"], // Bruh
    ["ohno", "Alarm"], // Oh no
    ["surprise", "FD_Start_F"], // Surprise
    ["cursed", "UAV_05"] // Maudit
];

// === SONS RÉALISTES ===
private _realisticSounds = createHashMapFromArray [
    // Radio / Communications
    ["radio_static", "RadioAmbient5"],
    ["radio_beep", "RadioAmbient3"],
    ["radio_squelch", "RadioAmbient1"],
    ["radio_voice_order", "RadioAmbient9"], // Ordre radio
    ["radio_voice_sitrep", "RadioAmbient7"], // SITREP
    ["radio_voice_medevac", "RadioAmbient2"], // MEDEVAC
    
    // Alertes / Alarmes
    ["alarm_base", "Alarm"],
    ["alarm_vehicle", "UAV_loop"],
    ["siren_medevac", "FD_CP_Clear_F"],
    ["siren_qrf", "FD_Start_F"],
    ["warning_zone", "UAV_05"],
    ["warning_critical", "UAV_03"],
    
    // Explosions / Combat
    ["explosion_distant", "Shell4"],
    ["explosion_near", "Shell3"],
    ["gunfire_distant", "BattlefieldJet1_3D"],
    ["artillery_incoming", "Shell1"],
    ["aircraft_flyby", "BattlefieldJet2_3D"],
    
    // Véhicules
    ["vehicle_engine_start", "UAV_engine"],
    ["vehicle_alarm", "Alarm"],
    ["helicopter_approach", "Heli_Attack_01_dist"],
    ["helicopter_landing", "Heli_Transport_01_int"],
    
    // Environnement / Immersion
    ["thunder", "thunder_close"],
    ["rain_heavy", "rain"],
    ["wind_strong", "wind"],
    ["ambient_forest", "BattlefieldExplosions1_3D"],
    ["ambient_urban", "BattlefieldExplosions5_3D"],
    
    // Événements Mission
    ["mission_start", "FD_Start_F"],
    ["mission_complete", "FD_Finish_F"],
    ["objective_captured", "FD_CP_Clear_F"],
    ["objective_lost", "FD_CP_Not_Clear_F"],
    ["intel_discovered", "UAV_06"],
    
    // Médical
    ["heartbeat_fast", "Heartbeat_EP1"],
    ["flatline", "FD_CP_Not_Clear_F"],
    ["medical_alert", "Alarm"],
    
    // Notifications
    ["notif_incoming", "RadioAmbient3"],
    ["notif_urgent", "UAV_05"],
    ["notif_critical", "Alarm"]
];

// Sélectionner son selon type
private _soundClass = "";

if (_type isEqualTo "troll") then {
    _soundClass = _trollSounds getOrDefault [_soundId, ""];
    if (_soundClass isEqualTo "") then {
        diag_log format ["[COMSPEC ATAK] playRemoteSound: Son troll '%1' inconnu", _soundId];
        false
    };
} else {
    _soundClass = _realisticSounds getOrDefault [_soundId, ""];
    if (_soundClass isEqualTo "") then {
        diag_log format ["[COMSPEC ATAK] playRemoteSound: Son réaliste '%1' inconnu", _soundId];
        false
    };
};

// Jouer son
if (count _position > 0) then {
    // Son 3D à position spécifique (réaliste uniquement)
    if (_type isEqualTo "realistic") then {
        private _soundSource = "Land_HelipadEmpty_F" createVehicleLocal _position;
        _soundSource say3D [_soundClass, _distance, 1, false];
        
        // Nettoyer source après 10s
        [{
            params ["_src"];
            deleteVehicle _src;
        }, [_soundSource], 10] call CBA_fnc_waitAndExecute;
        
        diag_log format ["[COMSPEC ATAK] playRemoteSound: Son 3D '%1' joué à %2 (distance %3m)", _soundId, _position, _distance];
        
        // Feedback visuel si proche
        if (player distance _position < 200) then {
            private _dir = player getDir _position;
            private _dist = round (player distance _position);
            hint parseText format ["<t color='#ffa500'>🔊 Son distant</t><br/><t size='0.8'>Direction: %1°<br/>Distance: %2m</t>", round _dir, _dist];
        };
    } else {
        // Troll : toujours 2D
        playSound [_soundClass, _volume];
        diag_log format ["[COMSPEC ATAK] playRemoteSound: Son troll '%1' joué (2D)", _soundId];
    };
} else {
    // Son 2D global (dans la tête du joueur)
    playSound [_soundClass, _volume];
    diag_log format ["[COMSPEC ATAK] playRemoteSound: Son 2D '%1' joué", _soundId];
};

// Notification selon type
if (_type isEqualTo "troll") then {
    systemChat format ["🎭 [TROLL] %1", _soundId];
    
    // Easter egg messages
    private _message = switch (_soundId) do {
        case "airhorn": { "BWAAAAA! 📯" };
        case "inception": { "BWOOOOOOM 💥" };
        case "rickroll": { "Never gonna give you up 🎵" };
        case "yeet": { "YEEEET! 🚀" };
        case "bruh": { "Bruh... 😐" };
        case "ohno": { "Oh no no no no 🙈" };
        case "surprise": { "Surprise! 🎉" };
        default { "Son troll activé!" };
    };
    
    hint parseText format ["<t color='#ff00ff' size='1.5'>🎭 MODE TROLL</t><br/><t size='1'>%1</t>", _message];
} else {
    // Réaliste : notification discrète
    private _icon = switch (true) do {
        case (_soundId find "radio" >= 0): { "📻" };
        case (_soundId find "alarm" >= 0): { "🚨" };
        case (_soundId find "explosion" >= 0): { "💥" };
        case (_soundId find "vehicle" >= 0): { "🚗" };
        case (_soundId find "helicopter" >= 0): { "🚁" };
        case (_soundId find "medical" >= 0): { "⚕️" };
        case (_soundId find "mission" >= 0): { "🎯" };
        default { "🔊" };
    };
    
    systemChat format ["%1 [Audio] %2", _icon, _soundId];
};

true
