/*
    Action « Relever des empreintes latentes » sur un objet : poudre, ruban, puis
    recherche de l'auteur dans la base. La latente reste dans la base de la mission :
    si son auteur est contrôlé plus tard, le SEEK la rattache à lui.

    [_object, _player] call comspec_sse_fnc_bioCaptureLatentAction
*/
params [["_obj", objNull, [objNull]], ["_player", player, [objNull]]];
if (isNull _obj) exitWith { false };
if !([_player, "fingerprint"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Kit d'empreintes, SEEK II ou kit de relevé compatible requis.";
    false
};

[
    (missionNamespace getVariable ["comspec_sse_timeFingerprint", 8]) max 10,
    "Relevé de latentes (poudre, ruban)...",
    {
        params ["_obj", "_player"];
        private _cap = [_obj, _player] call comspec_sse_fnc_bioCaptureLatent;
        if !(_cap getOrDefault ["ok", false]) exitWith { hint (_cap getOrDefault ["blocked", "Aucune trace exploitable."]); };

        private _fnames = ["pouce D", "index D", "majeur D", "annulaire D", "auriculaire D", "pouce G", "index G", "majeur G", "annulaire G", "auriculaire G"];
        private _nf = _cap get "nfiqs";
        private _desc = ((_cap get "fingers") apply { format ["%1 (NFIQ %2)", _fnames select _x, _nf select _x] }) joinString ", ";
        hint format ["Latentes relevées sur %1 : %2. Recherche de l'auteur…", _cap getOrDefault ["label", "l'objet"], _desc];

        private _link = toLower (str (missionNamespace getVariable ["COMSPEC_LinkState", "offline"]));
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
            hint (_lines joinString endl);
            if (!isNil "comspec_sse_fnc_addJournalEntry") then {
                ["LATENT", "biometrics", "latent", _lines select 1, _res getOrDefault ["score", 0], "LOCAL"] call comspec_sse_fnc_addJournalEntry;
            };
        }] call comspec_sse_fnc_bioRequest;
    },
    [_obj, _player]
] call comspec_sse_fnc_progressAction;
true
