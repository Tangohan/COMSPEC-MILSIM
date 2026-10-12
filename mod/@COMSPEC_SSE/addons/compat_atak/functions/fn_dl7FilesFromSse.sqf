/*
    Fichiers DL-7 tirés des appareils générés par le SSE sur un objet.

    Chaque appareil (ordinateur, téléphone, clé USB…) donne ses fichiers, courriels,
    messages, contacts, appels, lieux, photos et identifiants au format de l'app
    Extraction : [nom, type, contenu, taille Ko].

    [_obj] call comspec_sse_fnc_dl7FilesFromSse
    -> [files, nomProposé, thèmeProposé]
*/
params [["_obj", objNull, [objNull]]];
if (isNull _obj) exitWith { [[], "", "windows"] };

if (!isNil "comspec_sse_fnc_ensureGenerated") then { [_obj] call comspec_sse_fnc_ensureGenerated; };
private _devices = [_obj, "digitalDevices"] call comspec_sse_fnc_getSection;
if (isNil "_devices" || {!(_devices isEqualType [])}) exitWith { [[], "", "windows"] };

private _str = { params ["_v"]; if (_v isEqualType "") then { _v } else { str _v } };
private _lines = {
    params ["_list", "_fields"];
    (_list select { _x isEqualType createHashMap }) apply {
        private _h = _x;
        (_fields select { (_h getOrDefault [_x select 0, ""]) isNotEqualTo "" } apply { format ["%1 : %2", _x select 1, [_h get (_x select 0)] call _str] }) joinString " · "
    }
};

private _files = [];
private _name = "";
private _theme = "windows";
{
    private _d = _x;
    if (_d isEqualType createHashMap) then {
        private _type = toUpper (_d getOrDefault ["deviceType", ""]);
        private _tag = switch (true) do {
            case (_type in ["LAPTOP", "COMPUTER", "PC"]): { _d getOrDefault ["hostname", "PC"] };
            case (_type in ["SMARTPHONE", "PHONE"]): { _d getOrDefault ["model", "Téléphone"] };
            default { _d getOrDefault ["uid", _type] };
        };
        if (_name isEqualTo "") then {
            _name = format ["%1 (%2)", _tag, _d getOrDefault ["owner", "?"]];
            if (_type in ["SMARTPHONE", "PHONE"]) then { _theme = "telephone"; };
        };
        private _pre = toLower ([_tag] call _str);

        {
            if (_x isEqualType createHashMap) then {
                private _n = _x getOrDefault ["name", "fichier"];
                private _body = format ["%1%2", _x getOrDefault ["path", _n], ["", endl + ([_x getOrDefault ["content", ""]] call _str)] select ((_x getOrDefault ["content", ""]) isNotEqualTo "")];
                _files pushBack [_n, ["texte", "document"] select ((_n find ".doc") > 0 || {(_n find ".pdf") > 0}), _body, 0];
            } else {
                _files pushBack [[_x] call _str, "texte", [_x] call _str, 0];
            };
        } forEach (_d getOrDefault ["files", []]);

        private _blocks = [
            ["courriels", [_d getOrDefault ["mail", []], [["from", "De"], ["to", "À"], ["subject", "Objet"], ["snippet", "Texte"], ["body", "Texte"]]] call _lines],
            ["messages", [_d getOrDefault ["sms", _d getOrDefault ["messages", []]], [["from", "De"], ["to", "À"], ["when", "Le"], ["text", "Texte"]]] call _lines],
            ["contacts", [(_d getOrDefault ["contacts", []]) apply { if (_x isEqualType createHashMap) then { _x } else { createHashMapFromArray [["name", _x]] } }, [["name", "Nom"], ["phone", "Numéro"]]] call _lines],
            ["journal_appels", [_d getOrDefault ["calls", []], [["with", "Avec"], ["dir", "Sens"], ["duration", "Durée"], ["missed", "Manqué"]]] call _lines],
            ["navigateur", [_d getOrDefault ["browser", []], [["title", "Titre"], ["url", "Adresse"]]] call _lines],
            ["supprimes_recuperes", [(_d getOrDefault ["deleted", []]) select { _x isEqualType createHashMap && {_x getOrDefault ["recoverable", true]} }, [["type", "Type"], ["text", "Texte"]]] call _lines]
        ];
        {
            _x params ["_k", "_ls"];
            if (_ls isNotEqualTo []) then { _files pushBack [format ["%1_%2.txt", _pre, _k], "texte", _ls joinString endl, 0]; };
        } forEach _blocks;

        {
            if (_x isEqualType createHashMap) then {
                _files pushBack [format ["identifiants_%1", _x getOrDefault ["service", "compte"]], "identifiants",
                    format ["Service : %1%2Utilisateur : %3%2Indice : %4", _x getOrDefault ["service", ""], endl, _x getOrDefault ["user", ""], _x getOrDefault ["hint", ""]], 1];
            };
        } forEach (_d getOrDefault ["credentials", []]);
        {
            if (_x isEqualType createHashMap) then {
                _files pushBack [format ["lieu_%1", _x getOrDefault ["label", "point"]], "coord", [_x getOrDefault ["grid", ""]] call _str, 1];
            };
        } forEach (_d getOrDefault ["locations", []]);
        {
            if (_x isEqualType createHashMap) then {
                _files pushBack [format ["%1.jpg", _x getOrDefault ["id", "IMG"]], "texte", format ["Photo : %1", _x getOrDefault ["caption", ""]], 0];
            };
        } forEach (_d getOrDefault ["photos", []]);
        {
            _files pushBack [[_x] call _str, "document", "Archive chiffrée (7z) : mot de passe requis", 0];
        } forEach (_d getOrDefault ["encryptedFiles", []]);
    };
} forEach _devices;

[_files, _name, _theme]
