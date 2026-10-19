    class COMSPEC_Module_AtakRelay: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        displayName = "$STR_COMSPEC_OW_CFG_F8B88550A1";
        category = "COMSPEC_Roleplay";
        function = "comspec_overwatch_connect_fnc_moduleAtakRelay";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 0;
        is3DEN = 1;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\map\markers\military\flag_CA.paa";
        portrait = "\A3\ui_f\data\map\markers\military\flag_CA.paa";

        class Attributes
        {
            class RelayName
            {
                displayName = "$STR_COMSPEC_OW_CFG_ACB975B116";
                tooltip = "$STR_COMSPEC_OW_CFG_AD3C593256";
                property = "COMSPEC_AtakRelay_Name";
                control = "Edit";
                expression = "_this setVariable ['RelayName',_value,true];";
                defaultValue = """Relais Nord""";
                typeName = "STRING";
            };
            class RangeM
            {
                displayName = "$STR_COMSPEC_OW_CFG_FBB1E75950";
                tooltip = "$STR_COMSPEC_OW_CFG_8F8B7A32A7";
                property = "COMSPEC_AtakRelay_Range";
                control = "Edit";
                expression = "_this setVariable ['RangeM',_value,true];";
                defaultValue = "2000";
                validate = "number";
                typeName = "NUMBER";
            };
            class RelayIdentity
            {
                displayName = "$STR_COMSPEC_OW_CFG_3138BE1C28";
                tooltip = "$STR_COMSPEC_OW_CFG_0A36A96663";
                property = "COMSPEC_AtakRelay_Identity";
                control = "Edit";
                expression = "_this setVariable ['RelayIdentity',_value,true];";
                defaultValue = """RLY-01""";
                typeName = "STRING";
            };
            class RelayIp
            {
                displayName = "$STR_COMSPEC_OW_CFG_F3980FD50D";
                tooltip = "$STR_COMSPEC_OW_CFG_ACC856CB86";
                property = "COMSPEC_AtakRelay_Ip";
                control = "Edit";
                expression = "_this setVariable ['RelayIp',_value,true];";
                defaultValue = """""";
                typeName = "STRING";
            };
            class RelayGateway
            {
                displayName = "$STR_COMSPEC_OW_CFG_34B8F6BE8D";
                tooltip = "$STR_COMSPEC_OW_CFG_82E83A2817";
                property = "COMSPEC_AtakRelay_Gateway";
                control = "Edit";
                expression = "_this setVariable ['RelayGateway',_value,true];";
                defaultValue = """""";
                typeName = "STRING";
            };
            class RelayCertificate
            {
                displayName = "$STR_COMSPEC_OW_CFG_7C61435C4F";
                tooltip = "$STR_COMSPEC_OW_CFG_20F24368E8";
                property = "COMSPEC_AtakRelay_Certificate";
                control = "Edit";
                expression = "_this setVariable ['RelayCertificate',_value,true];";
                defaultValue = """Certificat de relais — valable mission""";
                typeName = "STRING";
            };
            class RelaySlots
            {
                displayName = "$STR_COMSPEC_OW_CFG_446BA25EED";
                tooltip = "$STR_COMSPEC_OW_CFG_05A841DCCE";
                property = "COMSPEC_AtakRelay_Slots";
                control = "Edit";
                expression = "_this setVariable ['RelaySlots',_value,true];";
                defaultValue = "8";
                validate = "number";
                typeName = "NUMBER";
            };
            class RelayPowerW
            {
                displayName = "$STR_COMSPEC_OW_CFG_6138AD0D2D";
                tooltip = "$STR_COMSPEC_OW_CFG_111BDDFEE4";
                property = "COMSPEC_AtakRelay_PowerW";
                control = "Edit";
                expression = "_this setVariable ['RelayPowerW',_value,true];";
                defaultValue = "25";
                validate = "number";
                typeName = "NUMBER";
            };
            class RelayThroughput
            {
                displayName = "$STR_COMSPEC_OW_CFG_6F10F1F3F0";
                tooltip = "$STR_COMSPEC_OW_CFG_B8F5D009D7";
                property = "COMSPEC_AtakRelay_Throughput";
                control = "Edit";
                expression = "_this setVariable ['RelayThroughput',_value,true];";
                defaultValue = "12";
                validate = "number";
                typeName = "NUMBER";
            };
            class RelayReliability
            {
                displayName = "$STR_COMSPEC_OW_CFG_6F2AA889FD";
                tooltip = "$STR_COMSPEC_OW_CFG_1531E3D94F";
                property = "COMSPEC_AtakRelay_Reliability";
                control = "Edit";
                expression = "_this setVariable ['RelayReliability',_value,true];";
                defaultValue = "92";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class RelayName
            {
                displayName = "$STR_COMSPEC_OW_CFG_ACB975B116";
                description = "$STR_COMSPEC_OW_CFG_D10F1C329B";
                typeName = "STRING";
                defaultValue = "Relais Nord";
            };
            class RangeM
            {
                displayName = "$STR_COMSPEC_OW_CFG_FBB1E75950";
                description = "$STR_COMSPEC_OW_CFG_6BF9BDB0FE";
                typeName = "NUMBER";
                defaultValue = "2000";
            };
            class RelayIdentity
            {
                displayName = "$STR_COMSPEC_OW_CFG_3138BE1C28";
                description = "$STR_COMSPEC_OW_CFG_CA532F1DA1";
                typeName = "STRING";
                defaultValue = "RLY-01";
            };
            class RelayIp
            {
                displayName = "$STR_COMSPEC_OW_CFG_F3980FD50D";
                description = "$STR_COMSPEC_OW_CFG_BE3FB7D912";
                typeName = "STRING";
                defaultValue = "";
            };
            class RelayGateway
            {
                displayName = "$STR_COMSPEC_OW_CFG_34B8F6BE8D";
                description = "$STR_COMSPEC_OW_CFG_615FAB1610";
                typeName = "STRING";
                defaultValue = "";
            };
            class RelayCertificate
            {
                displayName = "$STR_COMSPEC_OW_CFG_7C61435C4F";
                description = "$STR_COMSPEC_OW_CFG_2E4E7FDB3A";
                typeName = "STRING";
                defaultValue = "Certificat de relais — valable mission";
            };
            class RelaySlots
            {
                displayName = "$STR_COMSPEC_OW_CFG_0CD3597453";
                description = "$STR_COMSPEC_OW_CFG_404D3A6840";
                typeName = "NUMBER";
                defaultValue = "8";
            };
            class RelayPowerW
            {
                displayName = "$STR_COMSPEC_OW_CFG_6138AD0D2D";
                description = "$STR_COMSPEC_OW_CFG_67FB65AD0A";
                typeName = "NUMBER";
                defaultValue = "25";
            };
            class RelayThroughput
            {
                displayName = "$STR_COMSPEC_OW_CFG_89210072F1";
                description = "$STR_COMSPEC_OW_CFG_098CAA08C6";
                typeName = "NUMBER";
                defaultValue = "12";
            };
            class RelayReliability
            {
                displayName = "$STR_COMSPEC_OW_CFG_987506BC9B";
                description = "$STR_COMSPEC_OW_CFG_31546BB561";
                typeName = "NUMBER";
                defaultValue = "92";
            };
        };

        class ModuleDescription
        {
            description = "$STR_COMSPEC_OW_CFG_413A2AB1F9";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {};
        };
    };
