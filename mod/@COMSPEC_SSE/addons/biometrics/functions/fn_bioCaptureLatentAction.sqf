/*
    Action « Relever des empreintes latentes » sur un objet : poudre, ruban, puis
    recherche de l'auteur dans la base. La latente reste dans la base de la mission :
    si son auteur est contrôlé plus tard, le SEEK la rattache à lui.

    [_object, _player] call comspec_sse_fnc_bioCaptureLatentAction
*/
params [["_obj", objNull, [objNull]], ["_player", player, [objNull]]];
if (isNull _obj) exitWith { false };
if !([_player, "fingerprint"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint (("Kit d'empreintes, SEEK II ou kit de relevé compatible requis.") call comspec_sse_fnc_tr);
    false
};

[player, _obj, "latente", "start"] call comspec_sse_fnc_bioAnimate;
[
    (missionNamespace getVariable ["comspec_sse_timeFingerprint", 8]) max 10,
    "Relevé de latentes (poudre, ruban)...",
    {
        params ["_obj", "_player"];
        private _cap = [_obj, _player] call comspec_sse_fnc_bioCaptureLatent;
        if !(_cap getOrDefault ["ok", false]) exitWith { hint (((_cap getOrDefault ["blocked", "Aucune trace exploitable."])) call comspec_sse_fnc_tr); };

        private _fnames = ["pouce D", "index D", "majeur D", "annulaire D", "auriculaire D", "pouce G", "index G", "majeur G", "annulaire G", "auriculaire G"];
        private _nf = _cap get "nfiqs";
        private _desc = ((_cap get "fingers") apply { format ["%1 (NFIQ %2)", _fnames select _x, _nf select _x] }) joinString ", ";
        hint ((format ["Latentes relevées sur %1 : %2. Recherche de l'auteur…", _cap getOrDefault ["label", "l'objet"], _desc]) call comspec_sse_fnc_tr);

        private _link = toLower (call {
            // État de liaison Athena : COMSPEC_LinkState est une chaîne ("linked", "degraded"…). Sans état
            // COMSPEC Link, on suit la liaison propre de l'ATAK natif (barre d'état verte = en ligne).
            private _s = missionNamespace getVariable ["COMSPEC_LinkState", ""];
            if (!(_s isEqualType "")) then { _s = ""; };
            _s = toLower _s;
            if (_s isEqualTo "connecting") then { _s = "degraded"; };
            if (_s isEqualTo "") then {
                private _n = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                _s = if (_n isEqualType createHashMap) then { toLower (_n getOrDefault ["networkState", "offline"]) } else { "offline" };
                if (_s isEqualTo "connected") then { _s = "linked"; };
            };
            _s
        });
        private _payload = createHashMapFromArray [
            ["ownerKey", _cap get "ownerKey"],
            ["ownerUnit", _cap get "ownerUnit"],
            ["fingers", _cap get "fingers"],
            ["nfiqs", _nf],
            ["label", _cap getOrDefault ["label", ""]],
            ["grid", mapGridPosition _obj],
            ["attempt", _cap getOrDefault ["attempt", 1]],
            ["scope", ["local", "abis"] select (_link isEqualTo "linked")]
        ];
        ["latent", _payload, {
            params ["_res"];
            private _lines = [format ["Latente %1 versée à la base de la mission.", _res getOrDefault ["latentId", "LAT"]]];
            _lines append ([_res] call comspec_sse_fnc_bioDescribe);
            hint (((_lines joinString endl)) call comspec_sse_fnc_tr);
            if (!isNil "comspec_sse_fnc_addJournalEntry") then {
                ["LATENT", "biometrics", "latent", _lines select 1, _res getOrDefault ["score", 0], "LOCAL"] call comspec_sse_fnc_addJournalEntry;
            };
        }] call comspec_sse_fnc_bioRequest;
    },
    [_obj, _player]
] call comspec_sse_fnc_progressAction;
true
