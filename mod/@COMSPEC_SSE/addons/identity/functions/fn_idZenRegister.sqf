/*
    Modules Zeus (ZEN) « COMSPEC SSE · Identité » : Donner des papiers, Signaler volé / révoquer, Voir les vrais papiers.
    Tout s'exécute sur le serveur (fn_idServer give / report / truth), qui vérifie que le demandeur est bien Zeus.
    « Voir les vrais papiers » : réservé au Zeus (vraie identité, nature, qualité, défauts), jamais aux joueurs.
*/
if (!hasInterface) exitWith {};
[{ !isNil "zen_custom_modules_fnc_register" }, {
    private _cat = "COMSPEC SSE · Identité";
    private _unitOf = {
        params ["_obj"];
        if (isNull _obj || {!(_obj isKindOf "CAManBase")}) exitWith { ["Placez le module sur une personne.", "warn"] call comspec_sse_fnc_zeusNotify; objNull };
        _obj
    };
    [_cat, "Donner des papiers", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        [
            format ["Papiers — %1", name _u],
            [
                ["LIST", ["Document", "Pièce délivrée à la personne (objet dans son inventaire)."], [["id_card", "passport", "driving_license"], ["Carte d'identité", "Passeport", "Permis de conduire"], 0]],
                ["LIST", ["Pays", "Pays fictif (SIMULATION)."], [["auto", "ALT", "TNA", "LVN", "MLD"], ["Pays de la mission", "République d'Altis", "Fédération de Tanoa", "République de Livonie", "République de Malden"], 0]],
                ["LIST", ["Nature", "Civil : vrais papiers. Couverture : identité préparée qui passe tous les contrôles sauf la biométrie. Faux : défauts selon la qualité."], [["civil", "cover", "forged"], ["Vrais papiers", "Couverture préparée", "Faux papiers"], 0]],
                ["LIST", ["Procédé du faux", "Seulement pour de faux papiers."], [["", "counterfeit", "altered", "photo_swap", "stolen_blank"], ["Au hasard (selon la qualité)", "Contrefaçon complète", "Vrai document modifié", "Photo substituée", "Vierge volé"], 0]],
                ["SLIDER", ["Qualité du faux", "0 : grossier, vu au premier coup d'œil. 100 : excellent, seule la base ou la biométrie le trahit."], [0, 100, 50, 0]],
                ["EDIT", ["Nom imprimé", "Vide : nom de la personne, ou généré."], ""],
                ["EDIT", ["Prénom imprimé", "Vide : prénom de la personne, ou généré."], ""],
                ["EDIT", ["Numéro Athena", "Facultatif : numéro d'un document délivré sur Athena (paquet chargé) ; ses données remplacent les choix ci-dessus."], ""]
            ],
            {
                params ["_v", "_args"];
                _v params ["_type", "_country", "_kind", "_fk", "_q", "_last", "_first", "_ath"];
                _args params ["_u"];
                private _spec = createHashMapFromArray [["type", _type], ["country", _country], ["kind", _kind], ["forgeryKind", _fk], ["quality", round _q],
                    ["last", trim _last], ["first", trim _first], ["athena", trim _ath]];
                ["send", ["give", [player, _u, _spec]]] call comspec_sse_fnc_idClient;
            }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\identity\data\icon_card_ca.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Signaler volé / révoquer", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        [
            format ["Signalement — %1", name _u],
            [["LIST", ["Signalement", "S'applique à tous les documents de la personne : visible au contrôle N3 (base)."], [["vole", "perdu", "revoque", ""], ["Volé", "Perdu", "Révoqué", "Lever le signalement"], 0]]],
            { params ["_v", "_args"]; ["send", ["report", [player, _args select 0, _v select 0]]] call comspec_sse_fnc_idClient; }, {}, [_u]
        ] call comspec_sse_fnc_zenDialog;
    }, "\A3\ui_f\data\igui\cfg\simpletasks\types\danger_ca.paa"] call comspec_sse_fnc_zenModule;

    [_cat, "Voir les vrais papiers", {
        params ["_pos", "_obj"];
        private _u = [_obj] call (missionNamespace getVariable "comspec_sse_idZenUnitOf");
        if (isNull _u) exitWith {};
        ["send", ["truth", [player, _u]]] call comspec_sse_fnc_idClient;
    }, "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa"] call comspec_sse_fnc_zenModule;
    missionNamespace setVariable ["comspec_sse_idZenUnitOf", _unitOf];
}, [], 45] call CBA_fnc_waitUntilAndExecute;
