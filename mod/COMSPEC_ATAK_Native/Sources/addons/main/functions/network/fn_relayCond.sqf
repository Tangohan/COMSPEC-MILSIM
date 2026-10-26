/*
    Condition d'activation d'un relais ou d'une passerelle de véhicule (variable COMSPEC_AtakRelayCond de l'objet) : le relais
    ne porte le réseau que tant qu'elle est remplie ; sinon fn_relayNet le liste hors service avec la raison (page Réseau,
    fiche du relais sur la carte). Relue avec la liste des relais (toutes les 2 s).
      ""          toujours actif (par défaut) ;
      "stopped"   véhicule à l'arrêt (moins de 2 km/h) ;
      "engineoff" moteur coupé ;
      "mast"      mât ou antenne déployé(e) : sources d'animation du véhicule dont le nom contient mast, antenna, antena,
                  telescop, aerial, pole ou rod (la plus déployée doit dépasser 90 %). Exemple : GAZ-66 radio de RHS
                  (mât télescopique), véhicules de commandement. Aucune source de ce genre sur le véhicule : jamais actif,
                  « aucun mât reconnu » (écrire alors la condition en code) ;
      autre texte : condition en code SQF, _this = l'objet, qui rend vrai quand le relais doit marcher.
                  Exemple : _this animationSourcePhase "mast_src" > 0.9 && {(abs speed _this) < 1}
                  Sécurité : le code n'est JAMAIS compilé depuis la variable publique (n'importe quel client peut l'écrire).
                  Seul le serveur l'évalue, depuis sa copie locale COMSPEC_AtakRelayCondSrv (non publique, posée par le
                  module en Eden ou par un script serveur), et publie le résultat [actif, raison] dans
                  COMSPEC_AtakRelayCondOk quand il change ("srv" : boucle de XEH_postInitServer, toutes les 2 s).
                  Les clients ne lisent que ce résultat.
    Raison affichée : COMSPEC_AtakRelayCondText de l'objet, sinon celle du préréglage (« mât replié », « en mouvement »...).
    Depuis une mission ou un script (variable publique, réglée sur le serveur ou par le module « ATAK · Relais ») :
      _veh setVariable ["COMSPEC_AtakRelayCond", "mast", true];
    Condition en code (sur le serveur seulement) :
      _veh setVariable ["COMSPEC_AtakRelayCondSrv", "_this animationSourcePhase ""mast_src"" > 0.9"];
      _veh setVariable ["COMSPEC_AtakRelayCond", "code", true];
    Params : [objet] -> [actif, raison ("" quand actif)] ; ["srv"] (serveur) : évalue les conditions en code des relais
    et passerelles et publie leur résultat.
*/
params [["_o", objNull]];
if (_o isEqualTo "srv") exitWith {
    if (!isServer) exitWith {};
    {
        private _c = _x getVariable ["COMSPEC_AtakRelayCond", ""];
        if (_c isEqualType "" && {!((toLower trim _c) in ["", "stopped", "engineoff", "mast"])}) then { [_x] call comspec_atak_native_fnc_relayCond; };
    } forEach (((missionNamespace getVariable ["COMSPEC_AtakRelays", []]) + (missionNamespace getVariable ["COMSPEC_ATAK_Gateways", []])) select { !isNull _x });
};
if (!(_o isEqualType objNull) || {isNull _o}) exitWith { [false, ""] };
private _c = _o getVariable ["COMSPEC_AtakRelayCond", ""];
if (!(_c isEqualType "") || {(trim _c) isEqualTo ""}) exitWith { [true, ""] };
private _why = _o getVariable ["COMSPEC_AtakRelayCondText", ""];
if !(_why isEqualType "") then { _why = ""; };
private _def = "";
private _ok = switch (toLower trim _c) do {
    case "stopped": { _def = "en mouvement"; (abs speed _o) < 2 };
    case "engineoff": { _def = "moteur allumé"; !(isEngineOn _o) };
    case "mast": {
        // Sources du mât par classe, cherchées une fois (config du véhicule).
        private _cache = missionNamespace getVariable "COMSPEC_ATAK_MastSrc";
        if (isNil "_cache") then { _cache = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MastSrc", _cache]; };
        private _src = _cache getOrDefault [typeOf _o, -1];
        if (_src isEqualTo -1) then {
            _src = ((configProperties [configOf _o >> "AnimationSources", "isClass _x", true]) apply { configName _x })
                select { (toLower _x) regexMatch ".*(mast|antenna|antena|telescop|aerial|pole|rod).*" };
            _cache set [typeOf _o, _src];
        };
        if (_src isEqualTo []) exitWith { _def = "aucun mât reconnu"; false };
        _def = "mât replié";
        (selectMax (_src apply { _o animationSourcePhase _x })) >= 0.9
    };
    default {
        _def = "condition non remplie";
        if (isServer) then {
            // Code de la mission, pris dans la copie locale du serveur (jamais dans la variable publique), compilé une
            // fois ; absent, une erreur ou un résultat non booléen vaut « inactif ». Résultat publié quand il change.
            private _src = _o getVariable ["COMSPEC_AtakRelayCondSrv", ""];
            private _r = false;
            if (_src isEqualType "" && {(trim _src) isNotEqualTo ""}) then {
                private _codes = missionNamespace getVariable "COMSPEC_ATAK_RelayCondCode";
                if (isNil "_codes") then { _codes = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_RelayCondCode", _codes]; };
                private _code = _codes getOrDefault [_src, {}];
                if (_code isEqualTo {}) then { _code = compile _src; _codes set [_src, _code]; };
                _r = _o call _code;
                _r = !isNil "_r" && {_r isEqualType true} && {_r};
            };
            if ((_o getVariable ["COMSPEC_AtakRelayCondOk", []]) isNotEqualTo [_r, _def]) then { _o setVariable ["COMSPEC_AtakRelayCondOk", [_r, _def], true]; };
            _r
        } else {
            // Client : résultat publié par le serveur, jamais de code compilé ici.
            (_o getVariable ["COMSPEC_AtakRelayCondOk", []]) params [["_okSrv", false], ["_defSrv", ""]];
            if (_defSrv isEqualType "" && {_defSrv isNotEqualTo ""}) then { _def = _defSrv; };
            _okSrv isEqualTo true
        };
    };
};
[_ok, ["", [_def, _why] select (_why isNotEqualTo "")] select !_ok]
