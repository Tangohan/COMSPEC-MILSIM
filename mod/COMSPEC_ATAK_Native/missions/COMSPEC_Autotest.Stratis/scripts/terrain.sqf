// Antennes (appel direct des fonctions, sans la barre ACE), GPS de poignet, déclencheur réseau et charge ACE.
if ("antennes" call AT_fnc_groupe) then {
    {
        _x params ["_nom", "_kit", "_mur", "_ace"];
        [_nom, {
            params ["_kit", "_mur", "_ace"];
            if (["comspec_atak_native_fnc_antennaKit", "comspec_atak_native_fnc_relayNet"] call AT_fnc_nil) exitWith {};
            if !(isClass (configFile >> "CfgWeapons" >> _kit)) exitWith { (format ["kit %1 absent de ce build", _kit]) call AT_fnc_skip; };
            player addItem _kit;
            private _arg = [_kit];
            if (_mur) then {
                // Mini-antenne : sur le mur du bâtiment le plus proche, joueur juste à côté.
                _arg = [];
                private _h = (nearestObjects [player, ["House_F"], 300]) select { (((boundingBoxReal _x) select 1) select 2) > 3 };
                if (_h isNotEqualTo []) then {
                    private _b = _h select 0;
                    private _p = _b modelToWorld [(((boundingBoxReal _b) select 1) select 0) + 2, 0, 0];
                    player setPosATL [_p select 0, _p select 1, 0];
                    player setDir (player getDir _b);
                    uiSleep 1;
                    private _hit = lineIntersectsSurfaces [eyePos player, AGLToASL (_b modelToWorld [0, 0, 1.6]), player, objNull, true, 1, "GEOM", "FIRE"];
                    if (_hit isNotEqualTo []) then { _arg = [_kit, [(_hit select 0) select 0, (_hit select 0) select 1, _b]]; };
                };
            };
            if (_arg isEqualTo []) exitWith { player removeItem _kit; player setPosATL AT_Base; "pas de bâtiment à portée pour la mini-antenne" call AT_fnc_skip; };
            // _ace : comme l'action ACE du joueur, barre de progression comprise ; sinon appel direct.
            if (_ace) then {
                ["deploy", [_kit]] call comspec_atak_native_fnc_antennaKit;
                uiSleep 2;
                [format ["%1_barre", _kit]] call AT_fnc_shot;
                uiSleep ((missionNamespace getVariable ["comspec_atak_native_antkit_sat_time", 20]) - 3);
            } else {
                ["deployNow", _arg] call comspec_atak_native_fnc_antennaKit;
            };
            private _trouve = { ((missionNamespace getVariable ["COMSPEC_AtakRelays", []]) select { !isNull _x && {(_x getVariable ["COMSPEC_AtakRelayKit", ""]) isEqualTo _kit} }) param [0, objNull] };
            [[{ !isNull (call _trouve) }, 8] call AT_fnc_wait, "aucun relais créé par le déploiement"] call AT_fnc_check;
            private _o = call _trouve;
            [!(_kit in (items player)), "le kit reste dans l'inventaire après déploiement"] call AT_fnc_check;
            if (!isNull _o) then {
                uiSleep 3; // liste des relais en cache 2 s
                private _e = ["entry", _o] call comspec_atak_native_fnc_relayNet;
                [_e isNotEqualTo [], "absent de la liste du réseau (fn_relayNet)"] call AT_fnc_check;
                (format ["%1 : relais %2, portée %3 m, état « %4 »", typeOf _o, _e param [2, "?"], _e param [4, "?"], _e param [7, "?"]]) call AT_fnc_info;
                player setDir (player getDir _o);
                [format ["%1_pose", _kit]] call AT_fnc_shot;
                if (_ace) then { ["fold", [_o]] call comspec_atak_native_fnc_antennaKit; } else { ["srvFold", [player, _o]] call comspec_atak_native_fnc_antennaKit; };
                [[{ isNull _o && {_kit in (items player)} }, [8, 25] select _ace] call AT_fnc_wait, "repli : objet resté ou kit non rendu"] call AT_fnc_check;
            };
            player removeItem _kit;
            player setPosATL AT_Base;
            call AT_fnc_done;
        }, 90, [_kit, _mur, _ace]] call AT_fnc_run;
    } forEach [
        ["antenne_satellite", "COMSPEC_ATAK_SatAntennaKit", false, true],
        ["antenne_mat", "COMSPEC_ATAK_MastAntennaKit", false, false],
        ["antenne_mini", "COMSPEC_ATAK_BuildingAntennaKit", true, false]
    ];
};

if ("gps" call AT_fnc_groupe) then {
    ["gps_poignet", {
        if (["comspec_atak_native_fnc_wristGps"] call AT_fnc_nil) exitWith {};
        player linkItem "COMSPEC_ATAK_WristGps";
        [["has"] call comspec_atak_native_fnc_wristGps, "GPS de poignet porté mais « has » faux"] call AT_fnc_check;
        [["toggle"] call comspec_atak_native_fnc_wristGps, "ouverture refusée"] call AT_fnc_check;
        uiSleep 1.5;
        ["gps_poignet"] call AT_fnc_shot;
        ["big"] call comspec_atak_native_fnc_wristGps;
        uiSleep 1;
        for "_i" from 0 to 3 do {
            [format ["gps_page%1", _i]] call AT_fnc_shot;
            ["page"] call comspec_atak_native_fnc_wristGps;
            uiSleep 0.5;
        };
        private _cle = format ["COMSPEC_ATAK_WristGps_%1", toLower worldName];
        private _n = count (profileNamespace getVariable [_cle, []]);
        ["mark"] call comspec_atak_native_fnc_wristGps;
        uiSleep 0.5;
        [(count (profileNamespace getVariable [_cle, []])) isEqualTo (_n + 1), "MARK n'enregistre pas de point"] call AT_fnc_check;
        ["close"] call comspec_atak_native_fnc_wristGps;
        player unlinkItem "COMSPEC_ATAK_WristGps";
        call AT_fnc_done;
    }, 45] call AT_fnc_run;
};

if ("explo" call AT_fnc_groupe) then {
    ["explo_reseau", {
        if (["comspec_atak_native_fnc_exploNet", "comspec_atak_native_fnc_exploAction", "comspec_atak_native_fnc_exploList"] call AT_fnc_nil) exitWith {};
        if (isNil "ace_explosives_fnc_placeExplosive") exitWith { "ACE Explosifs non chargé" call AT_fnc_skip; };
        private _mag = "DemoCharge_Remote_Mag";
        private _trig = ["triggerOf", _mag] call comspec_atak_native_fnc_exploNet;
        if !([_trig isNotEqualTo "", "aucun déclencheur télécommandé pour DemoCharge_Remote_Mag"] call AT_fnc_check) exitWith { call AT_fnc_done; };
        // Zone vide : premier point d'une grille autour de la base sans construction à 80 m, carte et mission comprises.
        // Le joueur est invulnérable pendant toute la mission.
        private _constr = ["BUILDING", "HOUSE", "CHURCH", "CHAPEL", "FUEL STATION", "HOSPITAL", "RUIN", "TOURISM", "TRANSMITTER", "LIGHTHOUSE", "POWERSOLAR", "POWERWIND", "WATERTOWER", "BUNKER", "FORTRESS", "VIEW-TOWER", "SHIPWRECK"];
        private _champ = [];
        {
            private _d = _x;
            {
                private _p = AT_Base getPos [_d, _x];
                if (_champ isEqualTo [] && {!surfaceIsWater _p} && {(nearestTerrainObjects [_p, _constr, 80, false]) isEqualTo []}
                    && {(nearestObjects [_p, ["House", "Building", "LandVehicle", "Strategic"], 80]) isEqualTo []}) then { _champ = _p; };
            } forEach [0, 45, 90, 135, 180, 225, 270, 315];
        } forEach [300, 500, 800, 1100, 1500];
        if !([_champ isNotEqualTo [], "aucun champ vide trouvé autour de la base"] call AT_fnc_check) exitWith { call AT_fnc_done; };
        player setPosATL [_champ select 0, _champ select 1, 0];
        uiSleep 1;
        // Animaux d'ambiance (serpents, lapins, oiseaux) exclus : ils apparaissent partout, la charge ne vise personne.
        private _vide = ((nearestObjects [player, ["House", "Building", "LandVehicle", "Man"], 60]) - [player]) select { !(_x isKindOf "Animal") };
        [_vide isEqualTo [], format ["zone non vide : %1", _vide apply { typeOf _x }]] call AT_fnc_check;
        player addItem "COMSPEC_ATAK_NetDetonator";
        player addMagazine _mag;
        private _e = [player, (getPosATL player) vectorAdd [8, 0, 0], 0, _mag, _trig, []] call ace_explosives_fnc_placeExplosive;
        if !([!isNull _e, "charge ACE non posée"] call AT_fnc_check) exitWith { player setPosATL AT_Base; call AT_fnc_done; };
        ["linkPlaced", [_e, _mag, _trig]] call comspec_atak_native_fnc_exploNet;
        [[{ (_e getVariable ["COMSPEC_ATAK_NetCharge", []]) isNotEqualTo [] }, 6] call AT_fnc_wait, "charge non liée au réseau par le serveur"] call AT_fnc_check;
        ["cmd", ["arm", [_e]]] call comspec_atak_native_fnc_exploNet;
        [[{ ((_e getVariable ["COMSPEC_ATAK_NetCharge", []]) param [4, false]) isEqualTo true }, 6] call AT_fnc_wait, "charge non armée"] call AT_fnc_check;
        [["EXPLO", 2] call AT_fnc_page, "app Explosifs non ouverte"] call AT_fnc_check;
        ["explo_liste"] call AT_fnc_shot;
        private _c = ([true] call comspec_atak_native_fnc_exploList) select { (_x get "obj") isEqualTo _e };
        if !([_c isNotEqualTo [], "charge absente de la liste de l'app"] call AT_fnc_check) exitWith { deleteVehicle _e; player setPosATL AT_Base; call AT_fnc_done; };
        private _cle = (_c select 0) get "key";
        // Confirmation puis annulation : aucune détonation.
        ["ask", _cle] call comspec_atak_native_fnc_exploAction;
        uiSleep 1;
        [uiNamespace getVariable ["COMSPEC_ATAK_ExploConfirmT", controlNull], "décompte de la confirmation"] call AT_fnc_tient;
        ["explo_confirmation"] call AT_fnc_shot;
        ["askNo"] call comspec_atak_native_fnc_exploAction;
        uiSleep 1.5;
        [alive _e, "détonation après annulation"] call AT_fnc_check;
        ["explo_annulee"] call AT_fnc_shot;
        // Mise à feu réelle : la seule de la mission, dans le champ vide.
        ["ask", _cle] call comspec_atak_native_fnc_exploAction;
        uiSleep 1;
        ["askYes"] call comspec_atak_native_fnc_exploAction;
        [[{ isNull _e || {!alive _e} }, 15] call AT_fnc_wait, "pas de détonation après confirmation"] call AT_fnc_check;
        ["explo_detonation"] call AT_fnc_shot;
        player setPosATL AT_Base;
        call AT_fnc_done;
    }, 90] call AT_fnc_run;
};
