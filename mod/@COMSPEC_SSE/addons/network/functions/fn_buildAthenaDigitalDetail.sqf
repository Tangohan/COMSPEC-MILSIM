/*
    Contenu d'un support numérique RELEVÉ par le joueur, pour le laboratoire numérique d'Athena (kind digital_detail).

    Avant, l'envoi du support (fn_buildAthenaDigitalPayload) ne portait que des TOTAUX (« 12 contacts, 40 messages »)
    et quelques lignes de texte : le site n'avait aucun contact, message, appel, image ni position à afficher, d'où des
    écrans téléphone, ordinateur, communications et chronologie vides. Ici, ce que le joueur a réellement extrait.

    Rien ne part sans extraction : seulement ce que le mode d'exploitation en cours a ouvert (contacts, messages,
    appels, photos, positions, navigateur, courriels, identifiants, fichiers, utilisateurs, USB), ou tout si l'appareil
    est exploité à fond (mode « full » / « computer_full », état EXPLOITED). Un simple « identifier » n'envoie rien.

    [_entity, _fog] call comspec_sse_fnc_buildAthenaDigitalDetail
      -> tableau de morceaux [clé de données (phone_data | computer_data), rubrique, éléments], chacun assez petit
         pour un envoi (la file du SSE découpe en parties de 4000 caractères) ; [] si rien n'a été relevé.
*/
params [
    ["_entity", objNull, [objNull]],
    ["_fog", createHashMap, [createHashMap]]
];
if (isNull _entity) exitWith { [] };

private _mode = toLower (_fog getOrDefault ["mode", ""]);
private _state = toUpper ([_entity] call comspec_sse_fnc_getState);
private _all = (_mode in ["full", "computer_full"]) || {_state isEqualTo "EXPLOITED"};
// Rubriques du support ouvertes par un mode d'exploitation partiel.
private _modeKeys = createHashMapFromArray [
    ["contacts", ["contacts"]], ["messages", ["sms"]], ["calls", ["calls"]], ["photos", ["photos"]],
    ["locations", ["locations"]], ["browser", ["browser"]], ["mail", ["mail"]],
    ["credentials", ["credentials", "encryptedFiles"]], ["files", ["files"]], ["users", ["users"]],
    ["usb", ["usbHistory"]], ["system", ["network"]]
];
private _allowed = if (_all) then { ["*"] } else { _modeKeys getOrDefault [_mode, []] };
if (_allowed isEqualTo []) exitWith { [] };

[_entity] call comspec_sse_fnc_ensureGenerated;
private _devices = [_entity, "digitalDevices"] call comspec_sse_fnc_getSection;
if (isNil "_devices" || {!(_devices isEqualType [])} || {count _devices == 0}) exitWith { [] };
_devices = _devices select { _x isEqualType createHashMap };

private _pcTypes = ["LAPTOP", "COMPUTER", "PC"];
private _phones = _devices select { !((toUpper (_x getOrDefault ["deviceType", ""])) in _pcTypes) };
private _pcs = _devices select { (toUpper (_x getOrDefault ["deviceType", ""])) in _pcTypes };

private _sources = [];
if (count _phones > 0) then {
    _sources pushBack ["phone_data", _phones select 0, ["contacts", "sms", "calls", "photos", "locations", "credentials", "applications", "deletedData", "browser", "mail", "encryptedFiles", "network"]];
};
if (count _pcs > 0) then {
    _sources pushBack ["computer_data", _pcs select 0, ["files", "browser", "mail", "credentials", "usbHistory", "users", "encryptedFiles", "network"]];
};

private _limit = 3000;
private _chunks = [];
{
    _x params ["_dataKey", "_dev", "_keys"];
    {
        private _k = _x;
        if (("*" in _allowed) || {_k in _allowed}) then {
            private _v = _dev getOrDefault [_k, []];
            if (_v isEqualType createHashMap) then {
                // Réseau du support : une carte, pas une liste.
                if (count _v > 0) then { _chunks pushBack [_dataKey, _k, _v]; };
            } else {
                if (_v isEqualType [] && {count _v > 0}) then {
                    private _cur = [];
                    {
                        private _next = _cur + [_x];
                        if ((count (str _next)) > _limit && {count _cur > 0}) then {
                            _chunks pushBack [_dataKey, _k, _cur];
                            _cur = [_x];
                        } else {
                            _cur = _next;
                        };
                    } forEach (_v select [0, (count _v) min 60]);
                    if (count _cur > 0) then { _chunks pushBack [_dataKey, _k, _cur]; };
                };
            };
        };
    } forEach _keys;
} forEach _sources;

_chunks
