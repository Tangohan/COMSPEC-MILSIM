/*
    Actions ACE portées par les kits (règle : le minimum d'ACE, le reste passe par le téléphone ATAK).
    Une action n'apparaît que si le joueur porte le kit correspondant et si la cible s'y prête :
    ce sont des gestes physiques au contact, sans équivalent crédible à l'écran.
      Personne : relevé d'empreintes (kit empreintes), prélèvement ADN (kit ADN ou écouvillons),
                 test de résidus de tir (écouvillons), enrôlement au SEEK II (terminal porté)
      Objet    : empreintes latentes (kit empreintes), sac de Faraday (support numérique), cône de preuve
    [] call comspec_sse_fnc_kitActions -> [actionsPersonne, actionsObjet]
*/
private _none = { [] };
private _p = [false, false, false, false, true];
private _has = { params ["_cls"]; _cls in ((items _player) + (assignedItems _player)) };

private _person = [
    ["COMSPEC_SSE_KitFp", "Relever les empreintes (kit)", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
        { params ["_t", "_player"];
          [8, "Relevé décadactylaire...", { params ["_t", "_player"];
              if (([_player, "COMSPEC_SSE_FingerprintKit", 10] call comspec_sse_fnc_kitUse) isEqualTo 0) exitWith {};
              [_t, _player, "empreintes"] call comspec_sse_fnc_bioApplyCapture;
          }, [_t, _player]] call comspec_sse_fnc_progressAction; },
        { params ["_t", "_player"]; ["COMSPEC_SSE_FingerprintKit"] call _has && {!(["COMSPEC_SSE_SEEKII"] call _has)} }],
    ["COMSPEC_SSE_KitDna", "Prélèvement ADN (écouvillon buccal)", "\a3\ui_f\data\igui\cfg\simpleTasks\types\heal_ca.paa",
        { params ["_t", "_player"];
          [6, "Prélèvement ADN...", { params ["_t", "_player"];
              private _cls = ["COMSPEC_SSE_SwabKit", "COMSPEC_SSE_DNKit"] select ("COMSPEC_SSE_DNKit" in (items _player));
              if (([_player, _cls, 12] call comspec_sse_fnc_kitUse) isEqualTo 0) exitWith {};
              [_t, _player, "adn"] call comspec_sse_fnc_bioApplyCapture;
          }, [_t, _player]] call comspec_sse_fnc_progressAction; },
        { params ["_t", "_player"]; ["COMSPEC_SSE_SwabKit"] call _has || {["COMSPEC_SSE_DNKit"] call _has} }],
    ["COMSPEC_SSE_KitGsr", "Test de résidus de tir", "\a3\ui_f\data\igui\cfg\simpleTasks\types\target_ca.paa",
        { [_this select 0, _this select 1] call comspec_sse_fnc_gsrTest },
        { params ["_t", "_player"]; ["COMSPEC_SSE_SwabKit"] call _has }],
    ["COMSPEC_SSE_KitSeek", "Enrôlement biométrique (SEEK II)", "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa",
        { [_this select 0] call comspec_sse_fnc_openSeek },
        { params ["_t", "_player"]; ["COMSPEC_SSE_SEEKII"] call _has }]
];

private _object = [
    ["COMSPEC_SSE_KitLatent", "Relever des empreintes latentes", "\a3\ui_f\data\igui\cfg\simpleTasks\types\search_ca.paa",
        { params ["_t", "_player"];
          if (([_player, "COMSPEC_SSE_FingerprintKit", 10] call comspec_sse_fnc_kitUse) isEqualTo 0) exitWith {};
          [_t, _player] call comspec_sse_fnc_bioCaptureLatentAction; },
        { params ["_t", "_player"]; ["COMSPEC_SSE_FingerprintKit"] call _has && {missionNamespace getVariable ["comspec_sse_bio_latents", true]} }],
    ["COMSPEC_SSE_KitFaraday", "Mettre en sac de Faraday", "\a3\ui_f\data\igui\cfg\simpleTasks\types\box_ca.paa",
        { [_this select 0, _this select 1] call comspec_sse_fnc_faradayBag },
        { params ["_t", "_player"]; ["COMSPEC_SSE_FaradayBag"] call _has && {!isNil { _t getVariable "COMSPEC_ATAK_Data" }} && {!(_t getVariable ["comspec_sse_faraday", false])} }],
    ["COMSPEC_SSE_KitCone", "Poser un cône de preuve", "\a3\ui_f\data\igui\cfg\simpleTasks\types\map_ca.paa",
        { [_this select 0, _this select 1] call comspec_sse_fnc_placeMarker },
        { params ["_t", "_player"]; ["COMSPEC_SSE_EvidenceMarkers"] call _has && {(_t getVariable ["comspec_sse_markerNo", 0]) isEqualTo 0} }]
];

private _mk = {
    params ["_list", "_dist"];
    _list apply {
        _x params ["_id", "_label", "_icon", "_code", "_cond"];
        // _has lit _player : la condition ACE reçoit [_target, _player].
        private _fullCond = compile format ["params ['_t', '_player']; private _has = %1; ([_t] call comspec_sse_fnc_canInspect) && {[_t, _player] call %2}", _has, _cond];
        [_id, _label, _icon, _code, _fullCond, _none, [], {[0,0,0]}, _dist, _p] call comspec_sse_fnc_aceAction
    }
};

[[_person, 4] call _mk, [_object, 3] call _mk]
