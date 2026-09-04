params [
    ["_ctrl", controlNull],
    ["_isConfirmDialog", false],
    ["_message", ""]
];

private _msg = _message;

if !(_msg isEqualType "") then
{
    _msg = str _msg;
};

missionNamespace setVariable [
    "COMSPEC_ATAK_PageReady",
    true,
    false
];

private _tag = "COMSPEC_ATAK|";
private _idx = _msg find _tag;

if (_idx < 0) then
{
    _tag = "COMSPEC|";
    _idx = _msg find _tag;
};

if (_idx < 0) exitWith {true};

private _start = _idx + count _tag;
private _cmd = trim (
    _msg select [
        _start,
        (count _msg) - _start
    ]
);

private _logCmd = _cmd;

if ([_cmd,"chat:send|"] call {
    params ["_text","_prefix"];
    (count _text) >= (count _prefix)
    && {(_text select [0,count _prefix]) isEqualTo _prefix}
}) then
{
    _logCmd = "chat:send|<message masque>";
};

[
    "DEBUG",
    "WEB",
    "Commande UI recue.",
    _logCmd
] call COMSPEC_fnc_log;


private _startsWith = {
    params ["_text","_prefix"];
    (count _text) >= (count _prefix)
    && {(_text select [0,count _prefix]) isEqualTo _prefix}
};

switch (true) do
{
    case (_cmd isEqualTo "ready"):
    {
        [] call COMSPEC_fnc_webPushState;
    };

    case (_cmd isEqualTo "close"):
    {
        [] call COMSPEC_fnc_closeTablet;
    };

    case (_cmd isEqualTo "connect:athena"):
    {
        [] call COMSPEC_fnc_networkConnectAthena;
    };

    case (_cmd isEqualTo "auth:pair:start"):
    {
        [true] call COMSPEC_fnc_networkStartPairing;
    };

    case ([_cmd,"auth:login:email|"] call _startsWith):
    {
        private _email = trim (_cmd select [18, (count _cmd) - 18]);
        missionNamespace setVariable ["COMSPEC_ATAK_LoginEmail", _email, false];
    };

    case ([_cmd,"auth:login:secret|"] call _startsWith):
    {
        private _secret = _cmd select [19, (count _cmd) - 19];
        missionNamespace setVariable ["COMSPEC_ATAK_LoginSecret", _secret, false];
    };

    case (_cmd isEqualTo "auth:login:go"):
    {
        [] call COMSPEC_fnc_networkAuthPassword;
    };

    case (_cmd isEqualTo "auth:pair:open"):
    {
        ["if(window.COMSPEC_ATAK_openPairing){window.COMSPEC_ATAK_openPairing();}"] call COMSPEC_fnc_webExecJS;
    };

    case ([_cmd,"auth:pair:redeem|"] call _startsWith):
    {
        private _code = _cmd select [17,(count _cmd) - 17];
        [_code] call COMSPEC_fnc_networkRedeemPairingCode;
    };

    case ([_cmd,"auth:recovery|"] call _startsWith):
    {
        private _code = _cmd select [14,(count _cmd) - 14];
        [_code] call COMSPEC_fnc_networkRecoveryCode;
    };

    case ([_cmd,"connect:p2p|"] call _startsWith):
    {
        private _channel = trim (
            _cmd select [
                12,
                (count _cmd) - 12
            ]
        );

        [_channel] call COMSPEC_fnc_networkConnectP2P;
    };

    case (_cmd isEqualTo "connect:p2p"):
    {
        [] call COMSPEC_fnc_networkConnectP2P;
    };

    case (_cmd isEqualTo "disconnect"):
    {
        [] call COMSPEC_fnc_networkDisconnect;
        [true] call COMSPEC_fnc_networkShowConnection;
    };

    case ([_cmd,"chat:send|"] call _startsWith):
    {
        private _rest = _cmd select [10,(count _cmd)-10];
        private _parts = _rest splitString "|";
        private _channel = _parts param [0,"TEAM",[""]];
        private _body = if ((count _parts) > 1) then {(_parts select [1]) joinString "|"} else {_rest};
        [_body,_channel,"TEXT",createHashMap] call COMSPEC_fnc_sendChat;
    };

    case ([_cmd,"chat:quick|"] call _startsWith):
    {
        private _rest = _cmd select [11,(count _cmd)-11];
        private _parts = _rest splitString "|";
        private _channel = _parts param [0,"TEAM",[""]];
        private _body = _parts param [1,"ACK",[""]];
        [_body,_channel,"QUICK",createHashMap] call COMSPEC_fnc_sendChat;
    };

    case ([_cmd,"chat:position|"] call _startsWith):
    {
        private _channel = _cmd select [14,(count _cmd)-14];
        [_channel] call COMSPEC_fnc_chatSharePosition;
    };

    case (_cmd isEqualTo "chat:clear"):
    {
        [] call COMSPEC_fnc_chatClear;
    };

    case (_cmd isEqualTo "map:show"):
    {
        if !(missionNamespace getVariable ["COMSPEC_ATAK_MapVisible", false]) then
        {
            [] call COMSPEC_fnc_webMapShow;
        };
    };

    case (_cmd isEqualTo "mini:enter"):
    {
        [] call COMSPEC_fnc_tabletEnterMini;
    };

    case (_cmd isEqualTo "map:hide"):
    {
        [] call COMSPEC_fnc_webMapHide;
    };

    case ([_cmd,"map:tool|"] call _startsWith):
    {
        private _tool = _cmd select [
            9,
            (count _cmd) - 9
        ];

        [_tool] call COMSPEC_fnc_mapSetTool;
        [] call COMSPEC_fnc_webMapShow;
    };

    case ([_cmd,"map:texture|"] call _startsWith):
    {
        private _tex = toUpper (
            _cmd select [
                13,
                (count _cmd) - 13
            ]
        );

        if (_tex in ["SAT","TOPO"]) then
        {
            ["mapTexture", _tex] call COMSPEC_fnc_setState;

            if (
                missionNamespace getVariable [
                    "COMSPEC_ATAK_MapVisible",
                    false
                ]
            ) then
            {
                [] call COMSPEC_fnc_mapApplyTexture;
            };
        };
    };

    case (_cmd isEqualTo "map:bft"):
    {
        [] call COMSPEC_fnc_mapToggleBft;
    };

    case (_cmd isEqualTo "map:center"):
    {
        [] call COMSPEC_fnc_mapCenterOnPlayer;
    };

    case ([_cmd,"map:worldclick|"] call _startsWith):
    {
        private _parts = (_cmd select [15, (count _cmd) - 15]) splitString "|";
        if ((count _parts) >= 2) then
        {
            [
                [parseNumber (_parts # 0), parseNumber (_parts # 1), 0]
            ] call COMSPEC_fnc_mapUseToolAt;
        };
    };

    case ([_cmd,"view:commit|"] call _startsWith):
    {
        private _parts = (_cmd select [12, (count _cmd) - 12]) splitString "|";
        if ((count _parts) >= 4) then
        {
            private _name = _parts # 0;
            private _center = [parseNumber (_parts # 1), parseNumber (_parts # 2), 0];
            private _zoom = parseNumber (_parts # 3);
            private _tex = toUpper (_parts param [4, "SAT"]);
            if !(_tex in ["SAT","TOPO"]) then {_tex = "SAT";};
            private _views = profileNamespace getVariable ["COMSPEC_ATAK_SavedViews", []];
            _views pushBack [_name, _center, _zoom, _tex];
            while {(count _views) > 20} do { _views deleteAt 0; };
            profileNamespace setVariable ["COMSPEC_ATAK_SavedViews", _views];
            saveProfileNamespace;
            [] call COMSPEC_fnc_webPushState;
        };
    };

    case (_cmd isEqualTo "map:clear"):
    {
        [] call COMSPEC_fnc_mapClearMarks;
    };

    case (_cmd isEqualTo "home"):
    {
        [] call COMSPEC_fnc_webMapHide;
        ["home"] call COMSPEC_fnc_openApp;
    };

    case (_cmd isEqualTo "back"):
    {
        [] call COMSPEC_fnc_webMapHide;
        ["back"] call COMSPEC_fnc_openApp;
    };

    case ([_cmd,"open:"] call _startsWith):
    {
        private _app = _cmd select [
            5,
            (count _cmd) - 5
        ];

        [_app] call COMSPEC_fnc_openApp;
    };

    case ([_cmd,"settings:coord|"] call _startsWith):
    {
        private _precision = parseNumber (_cmd select [15,(count _cmd) - 15]);
        if !(_precision in [6,8,10]) then {_precision = 6;};
        profileNamespace setVariable ["COMSPEC_ATAK_CoordPrecision",_precision];
        saveProfileNamespace;
        [] call COMSPEC_fnc_webPushState;
    };

    case ([_cmd,"settings:mini|"] call _startsWith):
    {
        private _rest = _cmd select [14,(count _cmd) - 14];
        private _sep = _rest find "|";
        if (_sep > 0) then
        {
            private _key = toLower (_rest select [0,_sep]);
            private _value = _rest select [_sep + 1,(count _rest) - (_sep + 1)];
            switch (_key) do
            {
                case "selfmarker": {profileNamespace setVariable ["COMSPEC_ATAK_SelfMarkerSize",((parseNumber _value) max 14) min 64];};
                case "selflabel": {profileNamespace setVariable ["COMSPEC_ATAK_SelfLabelSize",((parseNumber _value) max 16) min 60];};
                case "othermarker": {profileNamespace setVariable ["COMSPEC_ATAK_OtherMarkerSize",((parseNumber _value) max 10) min 54];};
                case "otherlabel": {profileNamespace setVariable ["COMSPEC_ATAK_OtherLabelSize",((parseNumber _value) max 14) min 56];};
                case "font":
                {
                    private _font = toUpper _value;
                    if !(_font in ["ROBOTO","PURISTA","ETELKA","TAHOMA"]) then {_font = "ROBOTO";};
                    profileNamespace setVariable ["COMSPEC_ATAK_MapFont",_font];
                };
            };
            saveProfileNamespace;
            [] call COMSPEC_fnc_webPushState;
        };
    };

    case ([_cmd,"settings:save|"] call _startsWith):
    {
        private _rest = _cmd select [
            14,
            (count _cmd) - 14
        ];

        private _sep = _rest find "|";

        private _mode = if (_sep < 0) then
        {
            _rest
        }
        else
        {
            _rest select [0,_sep]
        };

        private _local = if (_sep < 0) then
        {
            ""
        }
        else
        {
            _rest select [
                _sep + 1,
                (count _rest) - (_sep + 1)
            ]
        };

        [_mode,_local] call COMSPEC_fnc_settingsSaveServer;
    };

    case ([_cmd,"settings:community|"] call _startsWith):
    {
        private _rest = _cmd select [19, (count _cmd) - 19];
        private _sep = _rest find "|";
        private _token = if (_sep < 0) then { _rest } else { _rest select [0, _sep] };
        private _tenant = if (_sep < 0) then { "" } else { _rest select [_sep + 1, (count _rest) - (_sep + 1)] };
        [_token, _tenant] call {
            params ["_token", "_tenant"];
            _token = trim _token;
            _tenant = trim _tenant;
            if ((count _token) >= 16) then
            {
                profileNamespace setVariable ["COMSPEC_ATAK_ApiKey", _token];
                missionNamespace setVariable ["COMSPEC_ATAK_api_key", _token];
                if (!isNil "CBA_fnc_setSetting") then
                {
                    ["COMSPEC_ATAK_api_key", _token, 0, "client"] call CBA_fnc_setSetting;
                };
            };
            if (!(_tenant isEqualTo "")) then
            {
                profileNamespace setVariable ["COMSPEC_ATAK_TenantId", _tenant];
                missionNamespace setVariable ["COMSPEC_ATAK_tenant_id", _tenant];
                if (!isNil "CBA_fnc_setSetting") then
                {
                    ["COMSPEC_ATAK_tenant_id", _tenant, 0, "client"] call CBA_fnc_setSetting;
                };
            };
            saveProfileNamespace;
            [] call COMSPEC_fnc_webPushState;
        };
    };


    case ([_cmd,"layer:set|"] call _startsWith):
    {
        private _rest = _cmd select [
            10,
            (count _cmd) - 10
        ];

        private _sep = _rest find "|";

        if (_sep >= 0) then
        {
            private _layer = _rest select [
                0,
                _sep
            ];

            private _value = toLower (
                _rest select [
                    _sep + 1,
                    (count _rest) - (_sep + 1)
                ]
            );

            [
                _layer,
                _value in ["1","true","on","yes"]
            ] call COMSPEC_fnc_mapSetLayer;
        };
    };

    case ([_cmd,"point:"] call _startsWith):
    {
        private _point = toUpper (
            _cmd select [
                6,
                (count _cmd) - 6
            ]
        );

        [_point] call COMSPEC_fnc_mapSetTool;
        [] call COMSPEC_fnc_webMapShow;
    };

    case ([_cmd,"scene:event|"] call _startsWith):
    {
        private _parts = (_cmd select [13, (count _cmd) - 13]) splitString "|";
        [
            _parts param [0, ""],
            _parts param [1, ""],
            _parts param [2, ""]
        ] call COMSPEC_fnc_scenePersist;
    };

    case ([_cmd,"scene:json|"] call _startsWith):
    {
        private _json = _cmd select [11, (count _cmd) - 11];
        missionNamespace setVariable ["COMSPEC_ATAK_ScenePending", true, false];
        if (_json isNotEqualTo "") then
        {
            ["Scene.Ingest", [_json]] call COMSPEC_fnc_extensionCall;
            [
                "INFO",
                "MAP",
                "Objet carte complet reçu.",
                ""
            ] call COMSPEC_fnc_log;
        };
    };

    case ([_cmd,"map:pack:keep|"] call _startsWith):
    {
        private _parts = (_cmd select [14, (count _cmd) - 14]) splitString "|";
        [
            "MapPack.Keep",
            [
                _parts param [0, worldName],
                _parts param [1, "0"],
                _parts param [2, "0"],
                _parts param [3, "0"]
            ]
        ] call COMSPEC_fnc_extensionCall;
    };

    case (_cmd isEqualTo "map:pack:install"):
    {
        ["MapPack.Install", [worldName]] call COMSPEC_fnc_extensionCall;
        ["if(window.COMSPEC_ATAK_mapPackStarted){window.COMSPEC_ATAK_mapPackStarted();}"] call COMSPEC_fnc_webExecJS;
    };

    case (_cmd isEqualTo "sync:roster"):
    {
        private _raw = ["Sync.Roster", []] call COMSPEC_fnc_extensionCall;
        private _tsv = if ((_raw find "OK|") == 0) then { _raw select [3] } else { "" };
        private _js = format [
            "if(window.COMSPEC_ATAK_syncRoster){window.COMSPEC_ATAK_syncRoster('%1');}",
            [_tsv] call COMSPEC_fnc_webJsEscape
        ];
        [_js] call COMSPEC_fnc_webExecJS;
    };

    case ([_cmd,"sync:snapshot|"] call _startsWith):
    {
        private _parts = (_cmd select [15, (count _cmd) - 15]) splitString "|";
        [
            "Sync.Snapshot",
            [
                _parts param [0, "0"],
                _parts param [1, "0"],
                _parts param [2, "0"],
                _parts param [3, "0"],
                _parts param [4, "0"],
                _parts param [5, "0"]
            ]
        ] call COMSPEC_fnc_extensionCall;
    };

    case ([_cmd,"tool:"] call _startsWith):
    {
        private _tool = toUpper (
            _cmd select [
                5,
                (count _cmd) - 5
            ]
        );

        switch (_tool) do
        {
            case "TASK":
            {
                [
                    "TASK",
                    "Ordre cree depuis ATAK",
                    getPosATL player
                ] call COMSPEC_fnc_taskCreate;
            };

            case "MEDEVAC":
            {
                ["MEDEVAC non encore branche au backend.", "INFO"] call COMSPEC_fnc_notify;
            };

            case "CAS":
            {
                ["CAS non encore branche au backend.", "INFO"] call COMSPEC_fnc_notify;
            };

            case "SSE":
            {
                ["SSE", getPosATL player] call COMSPEC_fnc_markerCreate;
            };

            default
            {
                [_tool] call COMSPEC_fnc_mapSetTool;
                [] call COMSPEC_fnc_webMapShow;
            };
        };
    };

    case ([_cmd,"marker:createat|"] call _startsWith):
    {
        private _parts = (_cmd select [16,(count _cmd) - 16]) splitString "|";
        private _pos = missionNamespace getVariable ["COMSPEC_ATAK_ContextMarkerPos",[]];

        if ((count _parts) >= 5 && {(count _pos) >= 2}) then
        {
            private _category = toUpper (_parts # 0);
            private _affiliation = toUpper (_parts # 1);
            private _label = _parts # 2;
            private _description = _parts # 3;
            private _priority = toUpper (_parts # 4);

            private _type = switch (true) do
            {
                case (_category isEqualTo "SSE"): {"SSE"};
                case (_affiliation isEqualTo "FRIENDLY"): {"FRIENDLY"};
                case (_affiliation isEqualTo "HOSTILE"): {"HOSTILE"};
                default {"POI"};
            };

            private _id = [
                _type,_pos,_label,true,_description,_affiliation,_category,_priority
            ] call COMSPEC_fnc_markerCreate;

            missionNamespace setVariable ["COMSPEC_ATAK_ContextMarkerPos",[],false];

            if (_id isNotEqualTo "") then
            {
                ["Marqueur créé à la position sélectionnée.","OK"] call COMSPEC_fnc_notify;
                ["if(window.COMSPEC_ATAK_markerPlaced){window.COMSPEC_ATAK_markerPlaced();}"] call COMSPEC_fnc_webExecJS;
            };
        };
    };

    case ([_cmd,"marker:prepare|"] call _startsWith):
    {
        private _parts = (_cmd select [15,(count _cmd) - 15]) splitString "|";
        private _category = toUpper (_parts param [0,"POINT"]);
        private _affiliation = toUpper (_parts param [1,"UNKNOWN"]);
        private _label = _parts param [2,"POINT"];
        private _description = _parts param [3,""];
        private _priority = toUpper (_parts param [4,"NORMAL"]);

        if !(_category in ["UNIT","CONTACT","POINT","OBJECTIVE","HAZARD","SSE"]) then {_category = "POINT";};
        if !(_affiliation in ["FRIENDLY","HOSTILE","NEUTRAL","UNKNOWN"]) then {_affiliation = "UNKNOWN";};
        if !(_priority in ["LOW","NORMAL","HIGH","CRITICAL"]) then {_priority = "NORMAL";};

        private _type = switch (true) do
        {
            case (_category isEqualTo "SSE"): {"SSE"};
            case (_affiliation isEqualTo "FRIENDLY"): {"FRIENDLY"};
            case (_affiliation isEqualTo "HOSTILE"): {"HOSTILE"};
            default {"POI"};
        };

        missionNamespace setVariable ["COMSPEC_ATAK_PendingMarker",createHashMapFromArray [
            ["category",_category],
            ["affiliation",_affiliation],
            ["label",_label],
            ["description",_description],
            ["priority",_priority]
        ]];

        [_type] call COMSPEC_fnc_mapSetTool;
        [] call COMSPEC_fnc_webMapShow;

        ["INFO","MARKER","Pose de marqueur armée.",format ["category=%1 affiliation=%2 priority=%3",_category,_affiliation,_priority]] call COMSPEC_fnc_log;
        ["if(window.COMSPEC_ATAK_markerArmed){window.COMSPEC_ATAK_markerArmed();}"] call COMSPEC_fnc_webExecJS;
    };

    case ([_cmd,"marker:update|"] call _startsWith):
    {
        private _parts = (_cmd select [14,(count _cmd) - 14]) splitString "|";
        if ((count _parts) >= 6) then
        {
            [
                _parts # 0,
                _parts # 3,
                true,
                _parts # 4,
                _parts # 2,
                _parts # 1,
                _parts # 5
            ] call COMSPEC_fnc_markerUpdate;
        };
    };

    case ([_cmd,"marker:rename|"] call _startsWith):
    {
        private _parts = (_cmd select [14, (count _cmd) - 14]) splitString "|";
        if ((count _parts) >= 2) then
        {
            [_parts # 0, _parts # 1, true] call COMSPEC_fnc_markerUpdate;
        };
    };

    case ([_cmd,"marker:delete|"] call _startsWith):
    {
        private _id = _cmd select [14, (count _cmd) - 14];
        [_id, true] call COMSPEC_fnc_markerDelete;
    };

    case ([_cmd,"task:create|"] call _startsWith):
    {
        private _parts = (_cmd select [12, (count _cmd) - 12]) splitString "|";
        private _title = if ((count _parts) > 0) then {_parts # 0} else {"TASK"};
        private _description = if ((count _parts) > 1) then {_parts # 1} else {""};
        private _assignee = if ((count _parts) > 2) then {_parts # 2} else {"ALL"};
        private _priority = if ((count _parts) > 3) then {_parts # 3} else {"NORMAL"};
        private _due = if ((count _parts) > 4) then {parseNumber (_parts # 4)} else {0};

        [_title, _description, getPosATL player, true, _assignee, _priority, _due] call COMSPEC_fnc_taskCreate;
    };

    case ([_cmd,"task:status|"] call _startsWith):
    {
        private _parts = (_cmd select [12, (count _cmd) - 12]) splitString "|";
        if ((count _parts) >= 2) then
        {
            [_parts # 0, _parts # 1, true] call COMSPEC_fnc_taskUpdate;
        };
    };

    case ([_cmd,"compat:"] call _startsWith):
    {
        private _payload = _cmd select [7, (count _cmd) - 7];
        private _parts = _payload splitString "|";
        private _action = _parts param [0, ""];
        private _args = if ((count _parts) > 1) then {_parts select [1]} else {[]};
        [_action, _args] call COMSPEC_fnc_compatAction;
        [] call COMSPEC_fnc_webPushState;
    };

    case ([_cmd,"view:save|"] call _startsWith):
    {
        private _name = _cmd select [
            10,
            (count _cmd) - 10
        ];

        [_name] call COMSPEC_fnc_viewSave;
    };

    case ([_cmd,"view:load|"] call _startsWith):
    {
        private _index = parseNumber (
            _cmd select [
                10,
                (count _cmd) - 10
            ]
        );

        [_index] call COMSPEC_fnc_viewLoad;
    };


    case (_cmd isEqualTo "tablet:mini"):
    {
        [] call COMSPEC_fnc_tabletEnterMini;
    };

    case (_cmd isEqualTo "tablet:resetpos"):
    {
        [] call COMSPEC_fnc_tabletResetPosition;
    };

    case (_cmd isEqualTo "refresh"):
    {
        [] call COMSPEC_fnc_webPushState;
    };

    case (_cmd isEqualTo "debug:networkstate"):
    {
        [] call COMSPEC_fnc_networkDebugState;
    };

    case (_cmd isEqualTo "debug:selftest"):
    {
        private _ping = [
            "Ping",
            []
        ] call COMSPEC_fnc_extensionCall;

        [
            "ATHENA",
            format ["DLL : %1", _ping],
            if ((_ping find "OK|") isEqualTo 0) then {"OK"} else {"ERR"}
        ] call COMSPEC_fnc_networkUpdateConnectionUI;

        diag_log format [
            "[COMSPEC ATAK][SELFTEST] %1",
            _ping
        ];
    };

    case (_cmd isEqualTo "log:pull"):
    {
        // UI-local journal in 0.4.3; RPT remains the persistent log.
    };

    case (_cmd isEqualTo "log:clear"):
    {
        diag_log "[COMSPEC ATAK][LOG] journal UI effacé";
    };


    case (_cmd isEqualTo "map:center"):
    {
        ["center",[]] call COMSPEC_fnc_webMapInput;
    };

    case (_cmd isEqualTo "map:zoom:in"):
    {
        ["zoomin",[]] call COMSPEC_fnc_webMapInput;
    };

    case (_cmd isEqualTo "map:zoom:out"):
    {
        ["zoomout",[]] call COMSPEC_fnc_webMapInput;
    };

    case (_cmd find "map:click:" isEqualTo 0):
    {
        private _parts = _cmd splitString ":";
        if ((count _parts) >= 4) then
        {
            ["click",[
                parseNumber (_parts # 2),
                parseNumber (_parts # 3)
            ]] call COMSPEC_fnc_webMapInput;
        };
    };

    case (_cmd find "map:pan:" isEqualTo 0):
    {
        private _parts = _cmd splitString ":";
        if ((count _parts) >= 6) then
        {
            ["pan",[
                parseNumber (_parts # 2),
                parseNumber (_parts # 3),
                parseNumber (_parts # 4),
                parseNumber (_parts # 5)
            ]] call COMSPEC_fnc_webMapInput;
        };
    };


    case (_cmd find "map:viewport:" isEqualTo 0):
    {
        private _parts = _cmd splitString ":";
        if ((count _parts) >= 6) then
        {
            [
                parseNumber (_parts # 2),
                parseNumber (_parts # 3),
                parseNumber (_parts # 4),
                parseNumber (_parts # 5)
            ] call COMSPEC_fnc_webMapSetViewport;
        };
    };

    default
    {
        [
            "WARN",
            "WEB",
            "Commande UI inconnue.",
            _cmd
        ] call COMSPEC_fnc_log;
    };
};

true
