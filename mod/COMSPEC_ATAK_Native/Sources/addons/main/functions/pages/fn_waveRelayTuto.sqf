/*
    Tutoriel de l'app Wave Relay : ouvert par le bouton « ? », proposé une fois à la première ouverture (profil du joueur, COMSPEC_ATAK_WrTutoSeen ;
    réglage joueur comspec_atak_native_wr_tuto_offer pour ne jamais le proposer).
      ["rows", écran] -> lignes de formulaire de cet écran, boutons compris
      ["offer"]       -> lignes de la proposition, ou [] si elle n'a pas lieu d'être
      ["set", écran]  (-1 : fermer)
    Chaque phrase a été vérifiée dans le code (fn_linkQuality, fn_waveRelayAction, fn_mpuLink, fn_mpuVoice, fn_antenna, fn_antennaKit) et les valeurs sont lues
    dans les réglages à l'affichage. Pas de chiffre sur les bâtiments : ils sont fixés dans fn_linkQuality et MON NŒUD montre la valeur du moment.
*/
params [["_act", "rows"], ["_arg", 0]];
private _render = { [{ ["WAVERELAY"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
switch (_act) do {
    case "set": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["wrTuto", _arg];
        _s set ["wrTutoOffer", false];
        call _render;
    };
    case "offer": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if (!(profileNamespace getVariable ["COMSPEC_ATAK_WrTutoSeen", false]) && {(missionNamespace getVariable ["comspec_atak_native_wr_tuto_offer", true]) isEqualTo true}) then {
            profileNamespace setVariable ["COMSPEC_ATAK_WrTutoSeen", true];
            [] call comspec_atak_native_fnc_saveProfile;
            _s set ["wrTutoOffer", true];
        };
        if !(_s getOrDefault ["wrTutoOffer", false]) exitWith { [] };
        [
            ["text", "<t font='COMSPEC_BarlowBold'>Première fois sur cette app ?</t><br/><t size='0.9'>Le tutoriel explique le réseau et chaque onglet en cinq écrans courts.</t>", COMSPEC_ATAK_Th get "surface"],
            ["buttons", [
                ["VOIR LE TUTORIEL", { ["set", 0] call comspec_atak_native_fnc_waveRelayTuto; }, true],
                ["NON MERCI", { ["set", -1] call comspec_atak_native_fnc_waveRelayTuto; }]
            ]]
        ]
    };
    case "rows": {
        private _dist = { params ["_d"]; [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 1]] select (_d >= 1000) };
        private _real = ["active"] call comspec_atak_native_fnc_mpuLink;
        private _voice = (missionNamespace getVariable ["comspec_atak_native_mpu_voice", false]) isEqualTo true && {_real};
        private _phone = [missionNamespace getVariable ["COMSPEC_ATAK_MeshRange", 3000]] call _dist;
        private _mpuR = [missionNamespace getVariable ["comspec_atak_native_mpu5_range", 6000]] call _dist;
        private _mpuG = missionNamespace getVariable ["comspec_atak_native_mpu5_gain", 8];
        private _hops = ["maxHops"] call comspec_atak_native_fnc_meshGraph;
        private _radioOn = !_real && {(missionNamespace getVariable ["comspec_atak_native_radio_data", true]) isEqualTo true};
        private _lrR = [missionNamespace getVariable ["comspec_atak_native_radio_lr_range", 10000]] call _dist;
        private _swR = [missionNamespace getVariable ["comspec_atak_native_radio_sw_range", 2000]] call _dist;
        private _mast = _real && {(missionNamespace getVariable ["comspec_atak_native_mesh_mast", true]) isEqualTo true};
        private _req = ["labels", [(["groups"] call comspec_atak_native_fnc_netRequire) apply { _x select 0 }]] call comspec_atak_native_fnc_netRequire;
        private _screens = [
            ["À quoi sert le réseau", [
                "Il transporte ce que votre téléphone envoie et reçoit : positions, messages, photos.",
                "Sans réseau, le téléphone reste utilisable : vos envois attendent en file et partent quand le réseau revient.",
                ["La voix reste sur votre radio : ce réseau ne la transporte pas sur ce serveur.", "Sur ce serveur, la voix d'une radio reliée à une MPU5 peut aussi passer par ce réseau, entre radios reliées."] select _voice,
                ["", format ["Ce serveur exige un objet pour rejoindre le réseau : %1.", _req]] select (_req isNotEqualTo "")
            ]],
            ["Passerelle, relais et voisin", [
                "Relais : antenne de la mission, passerelle de véhicule ou poste de commandement, qui apporte le débit.",
                ["Passerelle : le point vers lequel tout converge. Le relais le plus proche, sinon une passerelle de véhicule, sinon votre chef de groupe.", "Passerelle : le point vers lequel tout converge. Un relais en service, sinon la MPU5 du chef de groupe, sinon votre propre radio."] select _real,
                "Sans aucun relais, un joueur seul devient sa propre passerelle : il ne fait alors rien passer de plus, et le débit affiché est celui de son téléphone.",
                format ["Voisin : un nœud joint sans intermédiaire. Portée d'un téléphone : %1, d'une MPU5 : %2.", _phone, _mpuR],
                ["Saut : un nœud traversé pour atteindre la passerelle.", format ["Saut : un nœud traversé pour atteindre la passerelle. %1 sauts au plus sur ce serveur.", _hops]] select _real
            ]],
            ["Améliorer le signal", [
                "Hauteur : le relief coupe la liaison. Montez, ou contournez ce qui vous sépare du relais.",
                "Bâtiment : toit et murs réduisent le débit, jusqu'à plus aucun signal au cœur du bâtiment ou en sous-sol. Sortez, ou approchez d'une ouverture.",
                switch (true) do { case (_real): { "Radio : seule une MPU5 relie le téléphone sur ce serveur, par câble EUD ou Wi-Fi." }; case (_radioOn): { format ["Radio branchée, depuis MON NŒUD : elle relaie vos données et celles de l'équipe. Portée longue portée : %1, portative : %2.", _lrR, _swR] }; default { "Radio : le branchement est désactivé sur ce serveur." }; },
                format ["MPU5 : +%1 dB sur la liaison.", _mpuG],
                ["Antenne : une antenne d'extension montée sur le téléphone augmente le débit et atténue les pertes.", ""] select _real,
                ["", "Antenne transportable : une fois posée, c'est un relais."] select ((missionNamespace getVariable ["comspec_atak_native_antkit_max", 12]) > 0),
                ["Mât : indisponible sur ce serveur.", "Mât : une MPU5 posée et reliée en Wi-Fi peut déployer un mât, visible de loin."] select _mast
            ]],
            ["Quand c'est coupé", [
                "Regardez MON NŒUD : la carte d'état donne la cause principale et l'action à faire.",
                ["", format ["Vérifiez l'objet requis : %1. Le réseau repart dès qu'il est équipé.", _req]] select (_req isNotEqualTo ""),
                "Mode avion : aucun réseau tant qu'il est actif.",
                "Sortez du bâtiment, montez sur un point haut, éloignez-vous d'une zone brouillée.",
                ["", "MPU5 : vérifiez qu'elle est allumée, que sa batterie n'est pas vide et qu'elle est reliée par câble ou Wi-Fi."] select _real,
                "Vos envois attendent en file. File pleine : les plus anciens non urgents sont abandonnés, les urgents jamais."
            ]],
            ["Les onglets", [
                "MON NŒUD : votre état, ce qui change votre débit, votre radio et vos voisins directs.",
                "RÉSEAU : tous les nœuds, leurs sauts, et LOCALISER pour retrouver un équipier par triangulation.",
                "COUVERTURE : calcule le débit reçu autour de vous, et liste les relais.",
                ["TALKGROUPS : vos groupes de conversation.", "VOIX : qui émet sur vos fréquences, vos radios ACRE2 et leur canal, les équipiers connectés."] select (!isNil "acre_api_fnc_getCurrentRadioList"),
                ["", "Case RELAIS ATAK : affichée près du cadre d'émission quand votre voix passe réellement par le réseau. Elle se masque ou se déplace dans les réglages d'addons."] select _voice
            ]]
        ];
        private _n = count _screens;
        private _i = ((_arg max 0) min (_n - 1));
        (_screens select _i) params ["_title", "_lines"];
        private _rows = [["section", _title, format ["Écran %1 sur %2", _i + 1, _n]]];
        { if (_x isNotEqualTo "") then { _rows pushBack ["text", _x]; }; } forEach _lines;
        private _btn = [];
        if (_i > 0) then { _btn pushBack ["PRÉCÉDENT", compile format ["['set', %1] call comspec_atak_native_fnc_waveRelayTuto;", _i - 1]]; };
        if (_i < _n - 1) then { _btn pushBack ["SUIVANT", compile format ["['set', %1] call comspec_atak_native_fnc_waveRelayTuto;", _i + 1], true]; };
        _btn pushBack ["FERMER", { ["set", -1] call comspec_atak_native_fnc_waveRelayTuto; }, _i isEqualTo (_n - 1)];
        _rows pushBack ["buttons", _btn];
        _rows
    };
};
