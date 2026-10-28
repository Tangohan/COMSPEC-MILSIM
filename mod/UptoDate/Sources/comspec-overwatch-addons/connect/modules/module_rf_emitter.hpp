    class COMSPEC_Module_RfEmitter: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        displayName = "$STR_COMSPEC_OW_CFG_C24C84F979";
        category = "COMSPEC_Roleplay";
        function = "comspec_overwatch_connect_fnc_moduleRfEmitter";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 0;
        is3DEN = 1;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\IGUI\Cfg\simpleTasks\types\listen_ca.paa";
        portrait = "\A3\ui_f\data\IGUI\Cfg\simpleTasks\types\listen_ca.paa";

        class Attributes
        {
            class EmitterLabel
            {
                displayName = "$STR_COMSPEC_OW_CFG_973FF69E5D";
                tooltip = "$STR_COMSPEC_OW_CFG_B82F20C673";
                property = "COMSPEC_RfEmitter_Label";
                control = "Edit";
                expression = "_this setVariable ['EmitterLabel',_value,true];";
                defaultValue = """IPCam-Lobby""";
                typeName = "STRING";
            };
            class EmitterBand
            {
                displayName = "$STR_COMSPEC_OW_CFG_BC8407E188";
                tooltip = "$STR_COMSPEC_OW_CFG_1CD1CF102B";
                property = "COMSPEC_RfEmitter_Band";
                control = "Edit";
                expression = "_this setVariable ['EmitterBand',_value,true];";
                defaultValue = """wifi""";
                typeName = "STRING";
            };
            class EmitterSignature
            {
                displayName = "$STR_COMSPEC_OW_CFG_2F32BE1DC7";
                tooltip = "$STR_COMSPEC_OW_CFG_7859D4AA38";
                property = "COMSPEC_RfEmitter_Signature";
                control = "Edit";
                expression = "_this setVariable ['EmitterSignature',_value,true];";
                defaultValue = """wifi_ipcam""";
                typeName = "STRING";
            };
            class RangeM
            {
                displayName = "$STR_COMSPEC_OW_CFG_18D6665128";
                tooltip = "$STR_COMSPEC_OW_CFG_422DDCE9D2";
                property = "COMSPEC_RfEmitter_Range";
                control = "Edit";
                expression = "_this setVariable ['RangeM',_value,true];";
                defaultValue = "80";
                validate = "number";
                typeName = "NUMBER";
            };
            class PowerDbm
            {
                displayName = "$STR_COMSPEC_OW_CFG_E5BA13EC2E";
                tooltip = "$STR_COMSPEC_OW_CFG_65886348E8";
                property = "COMSPEC_RfEmitter_Power";
                control = "Edit";
                expression = "_this setVariable ['PowerDbm',_value,true];";
                defaultValue = "10";
                validate = "number";
                typeName = "NUMBER";
            };
            class EmitterMac
            {
                displayName = "$STR_COMSPEC_OW_CFG_CB02AF8FD8";
                tooltip = "$STR_COMSPEC_OW_CFG_30F065336E";
                property = "COMSPEC_RfEmitter_Mac";
                control = "Edit";
                expression = "_this setVariable ['EmitterMac',_value,true];";
                defaultValue = """""";
                typeName = "STRING";
            };
        };

        class Arguments
        {
            class EmitterLabel
            {
                displayName = "$STR_COMSPEC_OW_CFG_973FF69E5D";
                description = "$STR_COMSPEC_OW_CFG_73D04CF29E";
                typeName = "STRING";
                defaultValue = "IPCam-Lobby";
            };
            class EmitterBand
            {
                displayName = "$STR_COMSPEC_OW_CFG_BC8407E188";
                description = "$STR_COMSPEC_OW_CFG_B994CF0483";
                typeName = "STRING";
                defaultValue = "wifi";
            };
            class EmitterSignature
            {
                displayName = "$STR_COMSPEC_OW_CFG_2F32BE1DC7";
                description = "$STR_COMSPEC_OW_CFG_14746B633E";
                typeName = "STRING";
                defaultValue = "wifi_ipcam";
            };
            class RangeM
            {
                displayName = "$STR_COMSPEC_OW_CFG_18D6665128";
                description = "$STR_COMSPEC_OW_CFG_BAA720E660";
                typeName = "NUMBER";
                defaultValue = "80";
            };
            class PowerDbm
            {
                displayName = "$STR_COMSPEC_OW_CFG_E5BA13EC2E";
                description = "$STR_COMSPEC_OW_CFG_9E50BCCB02";
                typeName = "NUMBER";
                defaultValue = "10";
            };
            class EmitterMac
            {
                displayName = "$STR_COMSPEC_OW_CFG_B357F1231D";
                description = "$STR_COMSPEC_OW_CFG_F651847795";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_2DDF4339A8";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {};
        };
    };
