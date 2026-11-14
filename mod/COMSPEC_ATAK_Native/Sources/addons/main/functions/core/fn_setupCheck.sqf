/*
    Vérification de la configuration : une ligne par contrôle, réussi ou non, et le correctif en une phrase. Pas de fenêtre
    d'alerte du jeu : le résultat s'affiche dans l'app Statut (section « Vérification de la configuration ») et, pour la
    mission, dans l'app Admin (onglet EN JEU).
      ["run"]      contrôles du joueur local -> [[clé, niveau, libellé, détail, correctif]...]
                   niveau : 0 réussi, 1 attention, 2 problème. Clés : install, phone, radio, athena, net, battery.
      ["mission"]  contrôles de la mission (administrateur) -> même forme. Clés : relays, relayRule, local.
      ["worst", liste] -> niveau le plus haut de la liste (0 si vide).
    Les textes sortent déjà traduits (fn_tr) ; les modèles à valeur (%1) sont traduits avant d'être remplis.
*/
params [["_mode", "run"], ["_arg", []]];
private _T = { _this call comspec_atak_native_fnc_tr };
private _F = { params ["_tpl", "_v"]; if !(_v isEqualType []) then { _v = [_v]; }; format ([(_tpl) call comspec_atak_native_fnc_tr] + _v) };
private _row = { params ["_key", "_lvl", "_label", "_detail", ["_fix", ""]]; [_key, _lvl, _label call _T, _detail, _fix call _T] };

switch (_mode) do {
    case "worst": { private _w = 0; { _w = _w max (_x select 1); } forEach _arg; _w };
    case "run": {
        private _out = [];
        private _simOn = missionNamespace getVariable ["comspec_atak_native_net_sim", true];
        // 1. Installation : PBO et DLL de la même version, un seul PBO par addon (fn_integrityCheck).
        private _bad = missionNamespace getVariable ["COMSPEC_ATAK_Integrity", []];
        if (_bad isEqualType [] && {_bad isNotEqualTo []}) then {
            _out pushBack (["install", 2, "Installation", (_bad select 0) param [2, ""], "Mettez le mod à jour et retirez tout ancien PBO ou DLL COMSPEC : un seul main.pbo, une seule DLL, de la même version."] call _row);
        } else {
            _out pushBack (["install", 0, "Installation", "PBO et DLL cohérents" call _T] call _row);
        };
        // 2. Téléphone et objets exigés.
        if ([player] call comspec_atak_native_fnc_hasDevice) then {
            _out pushBack (["phone", 0, "Téléphone", "porté" call _T] call _row);
        } else {
            _out pushBack (["phone", 2, "Téléphone", "aucun téléphone sur vous" call _T, "Prenez un téléphone (objet COMSPEC ATAK Phone) ou demandez à l'administrateur de couper « Item obligatoire »."] call _row);
        };
        private _den = ["denied"] call comspec_atak_native_fnc_netRequire;
        if (_den isNotEqualTo []) then {
            _out pushBack (["radio", 2, "Objet radio exigé", ["Il vous manque : %1", ["labels", [_den]] call comspec_atak_native_fnc_netRequire] call _F, "Sans cet objet vous êtes hors du réseau : prenez-le dans l'inventaire (ou demandez à l'administrateur de le retirer des objets obligatoires)."] call _row);
        } else {
            _out pushBack (["radio", 0, "Objet radio exigé", "rien ne manque" call _T] call _row);
        };
        // 3. Athena : facultatif, jamais un problème (le mode local marche sans portail).
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _ns = _st getOrDefault ["networkState", "OFFLINE"];
        if (missionNamespace getVariable ["comspec_atak_native_local_only", false]) then {
            _out pushBack (["athena", 0, "Athena", "mode local : aucun portail, c'est voulu" call _T] call _row);
        } else {
            switch (_ns) do {
                case "CONNECTED": { _out pushBack (["athena", 0, "Athena", "lié et connecté" call _T] call _row); };
                case "DEGRADED": { _out pushBack (["athena", 1, "Athena", "liaison instable" call _T, "Les envois sont retardés : rapprochez-vous d'un relais ou attendez, la file repart seule."] call _row); };
                default { _out pushBack (["athena", 1, "Athena", "non lié (facultatif)" call _T, "Pour lier le téléphone : app Athena, onglet CODE, puis autorisez le code sur athena-comspec.eu/lier. Sans lien, le mode local fonctionne."] call _row); };
            };
        };
        // 4. Réseau en jeu.
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        private _relays = (["list", [str side group player]] call comspec_atak_native_fnc_relayNet) select { (_x select 3) isEqualTo "relay" };
        private _need = ["required"] call comspec_atak_native_fnc_relayNet;
        switch (true) do {
            case (!_simOn): { _out pushBack (["net", 0, "Réseau en jeu", "simulation coupée : réseau parfait" call _T] call _row); };
            case (_q getOrDefault ["airplane", false]): { _out pushBack (["net", 1, "Réseau en jeu", "mode avion" call _T, "Coupez le mode avion du téléphone (paramètres rapides)."] call _row); };
            case ((_q getOrDefault ["bars", 0]) > 0): { _out pushBack (["net", 0, "Réseau en jeu", ["%1 barre(s) sur 4", _q get "bars"] call _F] call _row); };
            case (_need && {_relays isEqualTo []}): { _out pushBack (["net", 2, "Réseau en jeu", "aucun relais dans cette mission" call _T, "Il n'y a aucun réseau sans relais : un Zeus ou le créateur de mission doit poser un relais (module « Poser un relais »), ou couper « Réseau exclusivement par relais »."] call _row); };
            default { _out pushBack (["net", 2, "Réseau en jeu", "aucun signal ici" call _T, "Approchez-vous d'un relais, d'une passerelle de véhicule ou d'un équipier avec une MPU5, ou sortez des bâtiments et des brouilleurs."] call _row); };
        };
        // 5. Batterie.
        private _bat = missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100];
        if (_bat <= 0) then {
            _out pushBack (["battery", 2, "Batterie", "vide" call _T, "Rechargez le téléphone : véhicule moteur allumé ou socle de recharge."] call _row);
        } else {
            _out pushBack (["battery", [0, 1] select (_bat <= 15), "Batterie", ["%1 %%", round _bat] call _F, ["", "Rechargez bientôt : véhicule moteur allumé ou socle de recharge."] select (_bat <= 15)] call _row);
        };
        _out
    };
    case "mission": {
        private _out = [];
        private _need = ["required"] call comspec_atak_native_fnc_relayNet;
        private _simOn = missionNamespace getVariable ["comspec_atak_native_net_sim", true];
        // Relais posés pour chaque camp (relais de mission seulement, pas les passerelles ni les radios).
        private _per = [west, east, independent, civilian] apply { private _s = _x; [_s, count ((["list", [str _s]] call comspec_atak_native_fnc_relayNet) select { (_x select 3) isEqualTo "relay" })] };
        private _total = 0;
        { _total = _total + (_x select 1); } forEach _per;
        if (!_simOn) then {
            _out pushBack (["relayRule", 0, "Réseau de la mission", "simulation coupée : réseau parfait pour tous" call _T] call _row);
        } else {
            if (_total isEqualTo 0 && {_need}) then {
                _out pushBack (["relays", 2, "Relais de la mission", "cette mission n'a pas de relais" call _T, "Personne n'a de réseau en jeu : posez un relais (module « Poser un relais » en Eden ou Zeus) ou coupez « Réseau exclusivement par relais » dans les réglages du serveur."] call _row);
            } else {
                if (_total isEqualTo 0) then {
                    _out pushBack (["relays", 1, "Relais de la mission", "aucun relais posé" call _T, "Le réseau passe sans relais ; ajoutez-en pour un réseau réaliste (couverture, brouillage, répéteurs)."] call _row);
                } else {
                    private _lines = (_per select { (_x select 1) > 0 }) apply { format ["%1 : %2", str (_x select 0), _x select 1] };
                    _out pushBack (["relays", 0, "Relais de la mission", ["%1 relais posé(s) · %2", [_total, _lines joinString ", "]] call _F] call _row);
                    // Un camp qui joue sans aucun relais, alors que les relais sont obligatoires.
                    private _lonely = [];
                    { private _e = _x; if ((_e select 1) isEqualTo 0 && {(count (allPlayers select { (side group _x) isEqualTo (_e select 0) })) > 0}) then { _lonely pushBack str (_e select 0); }; } forEach _per;
                    if (_need && {_lonely isNotEqualTo []}) then {
                        _out pushBack (["relays", 2, "Relais par camp", ["Aucun relais pour : %1", _lonely joinString ", "] call _F, "Ces joueurs n'ont pas de réseau en jeu : posez un relais de leur camp (ou sans camp)."] call _row);
                    };
                };
            };
        };
        _out pushBack (["relayRule", [0, 1] select (!_need && {_total isEqualTo 0}), "Relais obligatoires", (["oui : sans relais reçu, pas de réseau", "non : le réseau passe aussi sans relais"] select (!_need)) call _T] call _row);
        _out
    };
    default { [] };
};
