/*
    Génère un profil ordinateur / laptop lié au cluster.
    [_seed, _profile, _complexity, _cluster, _pools] call comspec_sse_fnc_generateComputer
*/
params [
    ["_seed", 0, [0]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "DETAILED", [""]],
    ["_cluster", createHashMap, [createHashMap]],
    ["_pools", createHashMap, [createHashMap]]
];

if (count _pools == 0) then {
    _pools = [_cluster getOrDefault ["region", "IRAQ"]] call comspec_sse_fnc_getNarrativePools;
};

private _theme = _cluster getOrDefault ["theme", "fuel_delivery"];
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;
private _owner = _cluster getOrDefault ["primaryName", "USER"];
private _host = format ["PC-%1", ([_seed, "host"] call comspec_sse_fnc_hash) mod 99999];

private _users = [
    createHashMapFromArray [["name", "admin"], ["role", "local admin"]],
    createHashMapFromArray [["name", toLower ([_owner, " ", ""] call BIS_fnc_replaceString)], ["role", "owner"]]
];

private _files = _pack getOrDefault ["computerFiles", ["notes.txt"]];
private _fileObjs = [];
{
    _fileObjs pushBack (createHashMapFromArray [
        ["name", _x],
        ["path", format ["C:\\Users\\%1\\Documents\\%2", _owner, _x]],
        ["relevant", true]
    ]);
} forEach _files;

// Fichiers bruit
_fileObjs pushBack (createHashMapFromArray [["name", "recettes.doc"], ["path", "Documents\\recettes.doc"], ["relevant", false]]);
_fileObjs pushBack (createHashMapFromArray [["name", "photos_vacances.zip"], ["path", "Downloads\\photos_vacances.zip"], ["relevant", false]]);

private _browser = [
    createHashMapFromArray [["url", "maps.example.local"], ["title", "Cartes"], ["relevant", true]],
    createHashMapFromArray [["url", "mail.example.local"], ["title", "Webmail"], ["relevant", true]],
    createHashMapFromArray [["url", "news.local"], ["title", "Actualités"], ["relevant", false]]
];

private _mail = [
    createHashMapFromArray [
        ["from", "unknown@proton.local"],
        ["subject", _pack getOrDefault ["label", "Opération"]],
        ["snippet", _pack getOrDefault ["summary", ""]],
        ["relevant", true]
    ]
];

private _usb = ["USB-KINGSTON-16G", "SD-CARD-CAMERA"];
private _creds = [
    createHashMapFromArray [["service", "email"], ["user", toLower _owner], ["hint", "password reused"]],
    createHashMapFromArray [["service", "wifi-safehouse"], ["user", "admin"], ["hint", _pack getOrDefault ["codeword", "ALPHA"]]]
];

createHashMapFromArray [
    ["uid", format ["SSE-DIG-%1", [_seed, "pc"] call comspec_sse_fnc_hash]],
    ["deviceType", "LAPTOP"],
    ["hostname", _host],
    ["owner", _owner],
    ["users", _users],
    ["files", _fileObjs],
    ["browser", _browser],
    ["mail", _mail],
    ["usbHistory", _usb],
    ["network", createHashMapFromArray [
        ["ssid", format ["HOME-%1", [_seed, "ssid"] call comspec_sse_fnc_hash]],
        ["lastIP", format ["192.168.1.%1", 20 + (([_seed, "ip"] call comspec_sse_fnc_hash) mod 200)]]
    ]],
    ["credentials", _creds],
    ["encryptedFiles", [format ["vault_%1.7z", _pack getOrDefault ["codeword", "X"]]]],
    ["cluster", _cluster]
]
