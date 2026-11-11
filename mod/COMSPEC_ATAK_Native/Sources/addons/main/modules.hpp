// Modules Zeus / Eden de l'extraction de données (inclus dans CfgVehicles de config.cpp).
// Attributs Eden à plat (sans AttributesBase) : ne pas rouvrir Module_F:Logic (casse Eden / Zeus / ACE).
// Zeus n'affiche pas les attributs : chaque module ouvre un formulaire vanilla à la pose (fn_zeusForm).
class Module_F;
class COMSPEC_ATAK_Module_DataBase: Module_F {
    author = "COMSPEC";
    scope = 1;
    scopeCurator = 1;
    category = "COMSPEC_Cat_Atak";
    functionPriority = 1;
    isGlobal = 1;
    isTriggerActivated = 0;
    isDisposable = 1;
    is3DEN = 0;
    curatorCanAttach = 1;
    icon = "\z\comspec_atak_native\addons\main\data\mod_data.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_data.paa";
};
class COMSPEC_ATAK_Module_DataNode: COMSPEC_ATAK_Module_DataBase {
    portrait = "\z\comspec_atak_native\addons\main\data\mod_data.paa";
    icon = "\z\comspec_atak_native\addons\main\data\mod_data.paa";
    category = "COMSPEC_Cat_Atak";
    scope = 2;
    scopeCurator = 2;
    displayName = "$STR_COMSPEC_ATAK_CFG_271C15D369";
    function = "comspec_atak_native_fnc_moduleDataNode";
    class Attributes {
            class Name {
                displayName = "$STR_COMSPEC_ATAK_CFG_7D8E20466B";
                tooltip = "$STR_COMSPEC_ATAK_CFG_0BDED24B7D";
                property = "COMSPEC_ATAK_Module_DataNode_Name";
                control = "Edit";
                expression = "_this setVariable ['Name',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Support {
                displayName = "$STR_COMSPEC_ATAK_CFG_4E549A4E3F";
                tooltip = "$STR_COMSPEC_ATAK_CFG_4230BFC2CE";
                property = "COMSPEC_ATAK_Module_DataNode_Support";
                control = "Combo";
                expression = "_this setVariable ['Support',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_ATAK_CFG_667838C91A"; value = ""; default = 1; };
                    class V1 { name = "$STR_COMSPEC_ATAK_CFG_A9AF08EC30"; value = "portable"; };
                    class V2 { name = "$STR_COMSPEC_ATAK_CFG_8F41F620E8"; value = "ordinateur"; };
                    class V3 { name = "$STR_COMSPEC_ATAK_CFG_36E5E1C6A8"; value = "serveur"; };
                    class V4 { name = "$STR_COMSPEC_ATAK_CFG_D3B023EABC"; value = "telephone"; };
                    class V5 { name = "$STR_COMSPEC_ATAK_CFG_7F73B0F8AB"; value = "tablette"; };
                    class V6 { name = "$STR_COMSPEC_ATAK_CFG_B972CCAE08"; value = "usb"; };
                    class V7 { name = "$STR_COMSPEC_ATAK_CFG_1F2BFEDA2D"; value = "radio"; };
                };
            };
            class Theme {
                displayName = "$STR_COMSPEC_ATAK_CFG_2547BF4CA0";
                tooltip = "$STR_COMSPEC_ATAK_CFG_66CB96FE97";
                property = "COMSPEC_ATAK_Module_DataNode_Theme";
                control = "Combo";
                expression = "_this setVariable ['Theme',_value,true];";
                defaultValue = "'windows'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_ATAK_CFG_6CDE1BE9BE"; value = "windows"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_ATAK_CFG_0BF36E37B6"; value = "linux"; };
                    class V2 { name = "$STR_COMSPEC_ATAK_CFG_1E0730DF18"; value = "militaire"; };
                    class V3 { name = "$STR_COMSPEC_ATAK_CFG_0FAE4CBFA9"; value = "scada"; };
                    class V4 { name = "$STR_COMSPEC_ATAK_CFG_A92E2B0C4B"; value = "telephone"; };
                    class V5 { name = "$STR_COMSPEC_ATAK_CFG_2D9AD0EB1E"; value = "coffre"; };
                };
            };
            class Banner {
                displayName = "$STR_COMSPEC_ATAK_CFG_F09016E234";
                tooltip = "$STR_COMSPEC_ATAK_CFG_4BA6A4CA77";
                property = "COMSPEC_ATAK_Module_DataNode_Banner";
                control = "Edit";
                expression = "_this setVariable ['Banner',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Password {
                displayName = "$STR_COMSPEC_ATAK_CFG_94E2F3EE14";
                tooltip = "$STR_COMSPEC_ATAK_CFG_87AC07610A";
                property = "COMSPEC_ATAK_Module_DataNode_Password";
                control = "Edit";
                expression = "_this setVariable ['Password',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Hint {
                displayName = "$STR_COMSPEC_ATAK_CFG_C1C0201437";
                tooltip = "$STR_COMSPEC_ATAK_CFG_ED07A353E4";
                property = "COMSPEC_ATAK_Module_DataNode_Hint";
                control = "Edit";
                expression = "_this setVariable ['Hint',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Attempts {
                displayName = "$STR_COMSPEC_ATAK_CFG_7139BCD3CB";
                tooltip = "$STR_COMSPEC_ATAK_CFG_5386009BE8";
                property = "COMSPEC_ATAK_Module_DataNode_Attempts";
                control = "Edit";
                expression = "_this setVariable ['Attempts',_value,true];";
                defaultValue = "3";
                typeName = "NUMBER";
            };
            class OnFail {
                displayName = "$STR_COMSPEC_ATAK_CFG_CED1C585A6";
                tooltip = "$STR_COMSPEC_ATAK_CFG_5D977D9026";
                property = "COMSPEC_ATAK_Module_DataNode_OnFail";
                control = "Combo";
                expression = "_this setVariable ['OnFail',_value,true];";
                defaultValue = "'lock'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_ATAK_CFG_7EC29D9943"; value = "lock"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_ATAK_CFG_0384948B18"; value = "wipe"; };
                    class V2 { name = "$STR_COMSPEC_ATAK_CFG_2FA41491DA"; value = "alarm"; };
                    class V3 { name = "$STR_COMSPEC_ATAK_CFG_107309E51C"; value = "none"; };
                };
            };
            class Crack {
                displayName = "$STR_COMSPEC_ATAK_CFG_13E08E6708";
                tooltip = "$STR_COMSPEC_ATAK_CFG_7964EED93B";
                property = "COMSPEC_ATAK_Module_DataNode_Crack";
                control = "Checkbox";
                expression = "_this setVariable ['Crack',_value,true];";
                defaultValue = "true";
                typeName = "BOOL";
            };
            class CrackTime {
                displayName = "$STR_COMSPEC_ATAK_CFG_AF7756A553";
                tooltip = "$STR_COMSPEC_ATAK_CFG_CA316DECF0";
                property = "COMSPEC_ATAK_Module_DataNode_CrackTime";
                control = "Edit";
                expression = "_this setVariable ['CrackTime',_value,true];";
                defaultValue = "90";
                typeName = "NUMBER";
            };
            class Duration {
                displayName = "$STR_COMSPEC_ATAK_CFG_C54AECDE5A";
                tooltip = "$STR_COMSPEC_ATAK_CFG_675368CCFD";
                property = "COMSPEC_ATAK_Module_DataNode_Duration";
                control = "Edit";
                expression = "_this setVariable ['Duration',_value,true];";
                defaultValue = "45";
                typeName = "NUMBER";
            };
            class Port {
                displayName = "$STR_COMSPEC_ATAK_CFG_FE035157CD";
                tooltip = "$STR_COMSPEC_ATAK_CFG_E9D174F7CC";
                property = "COMSPEC_ATAK_Module_DataNode_Port";
                control = "Combo";
                expression = "_this setVariable ['Port',_value,true];";
                defaultValue = "'usb'";
                typeName = "STRING";
                class values {
                    class V0 { name = "USB-C"; value = "usb"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_ATAK_CFG_8161F144F1"; value = "rj45"; };
                    class V2 { name = "$STR_COMSPEC_ATAK_CFG_2B264D3E23"; value = "serie"; };
                };
            };
            class Alarm {
                displayName = "$STR_COMSPEC_ATAK_CFG_68C602A962";
                tooltip = "$STR_COMSPEC_ATAK_CFG_F1388D7224";
                property = "COMSPEC_ATAK_Module_DataNode_Alarm";
                control = "Combo";
                expression = "_this setVariable ['Alarm',_value,true];";
                defaultValue = "'none'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_ATAK_CFG_E8F8827310"; value = "none"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_ATAK_CFG_B80BA8ECDA"; value = "connect"; };
                    class V2 { name = "$STR_COMSPEC_ATAK_CFG_58F66D2E68"; value = "fail"; };
                    class V3 { name = "$STR_COMSPEC_ATAK_CFG_7DB7C44D86"; value = "download"; };
                };
            };
            class OneShot {
                displayName = "$STR_COMSPEC_ATAK_CFG_E20F3D8F6F";
                tooltip = "$STR_COMSPEC_ATAK_CFG_145EE908D8";
                property = "COMSPEC_ATAK_Module_DataNode_OneShot";
                control = "Checkbox";
                expression = "_this setVariable ['OneShot',_value,true];";
                defaultValue = "false";
                typeName = "BOOL";
            };
            class Files {
                displayName = "$STR_COMSPEC_ATAK_CFG_23A9D9FCD3";
                tooltip = "$STR_COMSPEC_ATAK_CFG_8A3D429939";
                property = "COMSPEC_ATAK_Module_DataNode_Files";
                control = "EditMulti5";
                expression = "_this setVariable ['Files',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Accent {
                displayName = "$STR_COMSPEC_ATAK_CFG_E28A71B1EF";
                tooltip = "$STR_COMSPEC_ATAK_CFG_838F8C2B63";
                property = "COMSPEC_ATAK_Module_DataNode_Accent";
                control = "Edit";
                expression = "_this setVariable ['Accent',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Wallpaper {
                displayName = "$STR_COMSPEC_ATAK_CFG_7FFED89DE0";
                tooltip = "$STR_COMSPEC_ATAK_CFG_AB5C9325A0";
                property = "COMSPEC_ATAK_Module_DataNode_Wallpaper";
                control = "Edit";
                expression = "_this setVariable ['Wallpaper',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Code {
                displayName = "$STR_COMSPEC_ATAK_CFG_109E16CA46";
                tooltip = "$STR_COMSPEC_ATAK_CFG_297613D47A";
                property = "COMSPEC_ATAK_Module_DataNode_Code";
                control = "EditCodeMulti5";
                expression = "_this setVariable ['Code',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_7A53A95812";
    };
};
class COMSPEC_ATAK_Module_DataFile: COMSPEC_ATAK_Module_DataBase {
    portrait = "\z\comspec_atak_native\addons\main\data\mod_data.paa";
    icon = "\z\comspec_atak_native\addons\main\data\mod_data.paa";
    category = "COMSPEC_Cat_Atak";
    scope = 2;
    scopeCurator = 2;
    displayName = "$STR_COMSPEC_ATAK_CFG_58CA32DD5B";
    function = "comspec_atak_native_fnc_moduleDataFile";
    class Attributes {
            class FileName {
                displayName = "$STR_COMSPEC_ATAK_CFG_59A57B9C41";
                tooltip = "$STR_COMSPEC_ATAK_CFG_3A46E4886A";
                property = "COMSPEC_ATAK_Module_DataFile_FileName";
                control = "Edit";
                expression = "_this setVariable ['FileName',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class FileType {
                displayName = "$STR_COMSPEC_ATAK_CFG_3DEB745651";
                tooltip = "$STR_COMSPEC_ATAK_CFG_FA99319986";
                property = "COMSPEC_ATAK_Module_DataFile_FileType";
                control = "Combo";
                expression = "_this setVariable ['FileType',_value,true];";
                defaultValue = "'texte'";
                typeName = "STRING";
                class values {
                    class V0 { name = "$STR_COMSPEC_ATAK_CFG_FF9AFEF1EA"; value = "texte"; default = 1; };
                    class V1 { name = "$STR_COMSPEC_ATAK_CFG_E214B8A299"; value = "document"; };
                    class V2 { name = "$STR_COMSPEC_ATAK_CFG_7E7DC904BA"; value = "identifiants"; };
                    class V3 { name = "$STR_COMSPEC_ATAK_CFG_7C309D77B2"; value = "coord"; };
                    class V4 { name = "$STR_COMSPEC_ATAK_CFG_21A3E6F5BD"; value = "image"; };
                    class V5 { name = "$STR_COMSPEC_ATAK_CFG_96A9436FE5"; value = "audio"; };
                };
            };
            class FileContent {
                displayName = "$STR_COMSPEC_ATAK_CFG_F3CB82AF6C";
                tooltip = "$STR_COMSPEC_ATAK_CFG_28BDC3469A";
                property = "COMSPEC_ATAK_Module_DataFile_FileContent";
                control = "EditMulti5";
                expression = "_this setVariable ['FileContent',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_E99994D01D";
    };
};
class COMSPEC_ATAK_Module_DataReset: COMSPEC_ATAK_Module_DataBase {
    portrait = "\z\comspec_atak_native\addons\main\data\mod_trash.paa";
    icon = "\z\comspec_atak_native\addons\main\data\mod_trash.paa";
    category = "COMSPEC_Cat_Atak";
    scope = 1;
    scopeCurator = 2;
    displayName = "$STR_COMSPEC_ATAK_CFG_42055C212F";
    function = "comspec_atak_native_fnc_moduleDataReset";
};
// Tinder : une IA devient un profil HVT ou SSE qu'on croise dans l'app, et une cible de la chasse aux HVT
// (fn_moduleDating, fn_datingSpecials, fn_hvtIntel). Eden : synchroniser le module aux IA ; Zeus : le poser sur une IA.
class COMSPEC_ATAK_Module_DatingProfile: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_290D25EB85";
    icon = "\z\comspec_atak_native\addons\main\data\mod_person.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_person.paa";
    function = "comspec_atak_native_fnc_moduleDating";
    class Attributes {
        class Kind {
            displayName = "$STR_COMSPEC_ATAK_CFG_669FE25A32";
            tooltip = "$STR_COMSPEC_ATAK_CFG_2EC13DD1AC";
            property = "COMSPEC_ATAK_Module_DatingProfile_Kind";
            control = "Combo";
            expression = "_this setVariable ['Kind',_value,true];";
            defaultValue = "'HVT'";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_2AABFEE2EC"; value = "HVT"; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_E656CCF617"; value = "SSE"; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_15FAB891FC"; value = "OFF"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_55D68FFFA3"; value = ""; };
            };
        };
        class Name {
            displayName = "$STR_COMSPEC_ATAK_CFG_2072040C4C";
            tooltip = "$STR_COMSPEC_ATAK_CFG_2E2016AB7C";
            property = "COMSPEC_ATAK_Module_DatingProfile_Name";
            control = "Edit";
            expression = "_this setVariable ['Name',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Bio {
            displayName = "$STR_COMSPEC_ATAK_CFG_B31FC969B4";
            tooltip = "$STR_COMSPEC_ATAK_CFG_4F50F1E3F4";
            property = "COMSPEC_ATAK_Module_DatingProfile_Bio";
            control = "Edit";
            expression = "_this setVariable ['Bio',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Chance {
            displayName = "$STR_COMSPEC_ATAK_CFG_6F48D035BE";
            tooltip = "$STR_COMSPEC_ATAK_CFG_134CE848F1";
            property = "COMSPEC_ATAK_Module_DatingProfile_Chance";
            control = "Edit";
            expression = "_this setVariable ['Chance',_value,true];";
            defaultValue = "50";
            typeName = "NUMBER";
        };
        class Tips {
            displayName = "$STR_COMSPEC_ATAK_CFG_6BD4654DE8";
            tooltip = "$STR_COMSPEC_ATAK_CFG_9F3363A1FB";
            property = "COMSPEC_ATAK_Module_DatingProfile_Tips";
            control = "Combo";
            expression = "_this setVariable ['Tips',_value,true];";
            defaultValue = "1";
            typeName = "NUMBER";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_E370327F96"; value = 1; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_A721993901"; value = 0; };
            };
        };
        class FalseShare {
            displayName = "$STR_COMSPEC_ATAK_CFG_F41A9E1B71";
            tooltip = "$STR_COMSPEC_ATAK_CFG_5BD7E56C9D";
            property = "COMSPEC_ATAK_Module_DatingProfile_FalseShare";
            control = "Combo";
            expression = "_this setVariable ['FalseShare',_value,true];";
            defaultValue = "-1";
            typeName = "NUMBER";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_5F5D451210"; value = -1; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_AD986766E8"; value = 0; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_A3BF3DAE24"; value = 0.15; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_D7B313DCBE"; value = 0.35; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_19381BB8F9"; value = 0.6; };
            };
        };
        class Lie {
            displayName = "$STR_COMSPEC_ATAK_CFG_4F95A8E473";
            tooltip = "$STR_COMSPEC_ATAK_CFG_3C1A8EF4C4";
            property = "COMSPEC_ATAK_Module_DatingProfile_Lie";
            control = "Combo";
            expression = "_this setVariable ['Lie',_value,true];";
            defaultValue = "-1";
            typeName = "NUMBER";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_5F5D451210"; value = -1; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_F405E1BE2C"; value = 0; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_FB60B67577"; value = 0.25; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_EE418581AC"; value = 0.5; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_8E77DB9711"; value = 1; };
            };
        };
    };
};
// Réseau : relais ATAK posé par la mission (fn_moduleRelay). Le cercle du module dans Eden est la portée.
class COMSPEC_ATAK_Module_Relay: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_F5DA918AFE";
    icon = "\z\comspec_atak_native\addons\main\data\mod_relay.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_relay.paa";
    function = "comspec_atak_native_fnc_moduleRelay";
    canSetArea = 1;
    canSetAreaShape = 0;
    canSetAreaHeight = 0;
    class AttributeValues {
        size3[] = {3000, 3000, -1};
        isRectangle = 0;
    };
    class Attributes {
        class Name {
            displayName = "$STR_COMSPEC_ATAK_CFG_7FF20370C8";
            tooltip = "$STR_COMSPEC_ATAK_CFG_727D92D89D";
            property = "COMSPEC_ATAK_Module_Relay_Name";
            control = "Edit";
            expression = "_this setVariable ['Name',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Kind {
            displayName = "$STR_COMSPEC_ATAK_CFG_3DEB745651";
            tooltip = "$STR_COMSPEC_ATAK_CFG_7DC59FCF07";
            property = "COMSPEC_ATAK_Module_Relay_Kind";
            control = "Combo";
            expression = "_this setVariable ['Kind',_value,true]; if (is3DEN) then { ['watch'] call comspec_atak_native_fnc_relayEden; };";
            defaultValue = "'mat'";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_DFE8C58442"; value = "portable"; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_5BC6A1AEA4"; value = "mat"; default = 1; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_00D3BD7A04"; value = "tour"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_541695C92F"; value = "satellite"; };
            };
        };
        class Side {
            displayName = "$STR_COMSPEC_ATAK_CFG_7AB7D995F3";
            tooltip = "$STR_COMSPEC_ATAK_CFG_D70CADC0E8";
            property = "COMSPEC_ATAK_Module_Relay_Side";
            control = "Combo";
            expression = "_this setVariable ['Side',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_A79E887C37"; value = ""; default = 1; };
                class V1 { name = "BLUFOR"; value = "WEST"; };
                class V2 { name = "OPFOR"; value = "EAST"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_621714C49D"; value = "GUER"; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_7AA3BFB0ED"; value = "CIV"; };
            };
        };
        class Throughput {
            displayName = "$STR_COMSPEC_ATAK_CFG_89210072F1";
            tooltip = "$STR_COMSPEC_ATAK_CFG_70D61E6EE6";
            property = "COMSPEC_ATAK_Module_Relay_Throughput";
            control = "Edit";
            expression = "_this setVariable ['Throughput',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
        };
        class Height {
            displayName = "$STR_COMSPEC_ATAK_CFG_C126739753";
            tooltip = "$STR_COMSPEC_ATAK_CFG_C3601BD34B";
            property = "COMSPEC_ATAK_Module_Relay_Height";
            control = "Edit";
            expression = "_this setVariable ['Height',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
        };
        class Slots {
            displayName = "$STR_COMSPEC_ATAK_CFG_0CD3597453";
            tooltip = "$STR_COMSPEC_ATAK_CFG_88209FC249";
            property = "COMSPEC_ATAK_Module_Relay_Slots";
            control = "Edit";
            expression = "_this setVariable ['Slots',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
        };
        class Autonomy {
            displayName = "$STR_COMSPEC_ATAK_CFG_FA6170F4DD";
            tooltip = "$STR_COMSPEC_ATAK_CFG_44DB25404A";
            property = "COMSPEC_ATAK_Module_Relay_Autonomy";
            control = "Combo";
            expression = "_this setVariable ['Autonomy',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
            class values {
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_498C64B386"; value = 0; default = 1; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_B67393853B"; value = 60; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_71B0559CE2"; value = 120; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_6133B4D4F9"; value = 240; };
                class V5 { name = "$STR_COMSPEC_ATAK_CFG_47E44D31D4"; value = 480; };
                class V6 { name = "$STR_COMSPEC_ATAK_CFG_872C4C0E01"; value = 720; };
                class V7 { name = "$STR_COMSPEC_ATAK_CFG_FF5C921332"; value = 1440; };
            };
        };
        class Uplink {
            displayName = "$STR_COMSPEC_ATAK_CFG_30DE5E847C";
            tooltip = "$STR_COMSPEC_ATAK_CFG_C3C6B0F083";
            property = "COMSPEC_ATAK_Module_Relay_Uplink";
            control = "Combo";
            expression = "_this setVariable ['Uplink',_value,true];";
            defaultValue = "'root'";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_0167FC858D"; value = "root"; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_92D4E717EB"; value = "repeater"; };
            };
        };
        class Freq {
            displayName = "$STR_COMSPEC_ATAK_CFG_FREQ_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_FREQ_TIP";
            property = "COMSPEC_ATAK_Module_Relay_Freq";
            control = "Combo";
            expression = "_this setVariable ['Freq',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_FREQ_AUTO"; value = 0; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_22424B2581"; value = 400; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_CD3A37DD0E"; value = 1400; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_4D2F56FC32"; value = 2400; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_2D08DDA555"; value = 4900; };
                class C1 { name = "$STR_COMSPEC_ATAK_CFG_3EF48FC3A1"; value = 4940; };
                class C2 { name = "$STR_COMSPEC_ATAK_CFG_D58E1749BA"; value = 4945; };
                class C3 { name = "$STR_COMSPEC_ATAK_CFG_7ED3477395"; value = 4950; };
                class C4 { name = "$STR_COMSPEC_ATAK_CFG_BE95616EC5"; value = 4955; };
                class C5 { name = "$STR_COMSPEC_ATAK_CFG_BC5203222A"; value = 4960; };
                class C6 { name = "$STR_COMSPEC_ATAK_CFG_AA0E17E9A6"; value = 4965; };
                class C7 { name = "$STR_COMSPEC_ATAK_CFG_B765617C85"; value = 4970; };
                class C8 { name = "$STR_COMSPEC_ATAK_CFG_02B48D9628"; value = 4975; };
            };
        };
        class StartOn {
            displayName = "$STR_COMSPEC_ATAK_CFG_BCCEF47A72";
            tooltip = "$STR_COMSPEC_ATAK_CFG_1F9EA27C24";
            property = "COMSPEC_ATAK_Module_Relay_StartOn";
            control = "Checkbox";
            expression = "_this setVariable ['StartOn',_value,true];";
            defaultValue = "true";
            typeName = "BOOL";
        };
        class Indestructible {
            displayName = "$STR_COMSPEC_ATAK_CFG_29CF136691";
            tooltip = "$STR_COMSPEC_ATAK_CFG_B94A8EF536";
            property = "COMSPEC_ATAK_Module_Relay_Indestructible";
            control = "Checkbox";
            expression = "_this setVariable ['Indestructible',_value,true];";
            defaultValue = "false";
            typeName = "BOOL";
        };
        class Required {
            displayName = "$STR_COMSPEC_ATAK_CFG_5D68EBED29";
            tooltip = "$STR_COMSPEC_ATAK_CFG_1CF29F9BF4";
            property = "COMSPEC_ATAK_Module_Relay_Required";
            control = "Checkbox";
            expression = "_this setVariable ['Required',_value,true];";
            defaultValue = "false";
            typeName = "BOOL";
        };
        class RelayOnly {
            displayName = "$STR_COMSPEC_ATAK_CFG_RELAYONLY_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_RELAYONLY_TIP";
            property = "COMSPEC_ATAK_Module_Relay_RelayOnly";
            control = "Checkbox";
            expression = "_this setVariable ['RelayOnly',_value,true];";
            defaultValue = "false";
            typeName = "BOOL";
        };
        class Cond {
            displayName = "$STR_COMSPEC_ATAK_CFG_RELAYCOND_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_RELAYCOND_TIP";
            property = "COMSPEC_ATAK_Module_Relay_Cond";
            control = "Combo";
            expression = "_this setVariable ['Cond',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_RELAYCOND_ALWAYS"; value = ""; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_RELAYCOND_MAST"; value = "mast"; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_RELAYCOND_STOPPED"; value = "stopped"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_RELAYCOND_ENGINE"; value = "engineoff"; };
            };
        };
        class CondCode {
            displayName = "$STR_COMSPEC_ATAK_CFG_RELAYCODE_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_RELAYCODE_TIP";
            property = "COMSPEC_ATAK_Module_Relay_CondCode";
            control = "EditCodeMulti3";
            expression = "_this setVariable ['CondCode',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_22558DD904";
    };
};
// Écran COMSPEC (fn_moduleScreen, fn_screenCast) : objets synchronisés où l'app Briefing diffuse ses diapositives.
class COMSPEC_ATAK_Module_Mesh: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 1;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_82FD73321A";
    icon = "\z\comspec_atak_native\addons\main\data\mod_net.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_net.paa";
    function = "comspec_atak_native_fnc_moduleMesh";
    class Attributes {
        class Keys {
            displayName = "Clés de groupe";
            tooltip = "nom:camp;nom:camp. Exemple : K-ALPHA:WEST;K-BRAVO:WEST. Mécanique de jeu, avec le réglage des clés.";
            property = "COMSPEC_ATAK_Module_Mesh_Keys";
            control = "Edit";
            expression = "_this setVariable ['Keys',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Groups {
            displayName = "Groupes";
            tooltip = "numéro:nom:clé;... Exemple : 1:CMD:K-ALPHA;2:ALPHA:";
            property = "COMSPEC_ATAK_Module_Mesh_Groups";
            control = "Edit";
            expression = "_this setVariable ['Groups',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Freq {
            displayName = "Fréquence vers groupe";
            tooltip = "MHz:groupe;... Exemple : 43.5:2;44:3. Vide : groupe déduit de la fréquence.";
            property = "COMSPEC_ATAK_Module_Mesh_Freq";
            control = "Edit";
            expression = "_this setVariable ['Freq',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Backup {
            displayName = "Canal de secours";
            tooltip = "1 à 16. 0 : réglage du serveur.";
            property = "COMSPEC_ATAK_Module_Mesh_Backup";
            control = "EditShort";
            expression = "_this setVariable ['Backup',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
        };
        class Profile {
            displayName = "Profil de réalisme";
            tooltip = "-1 : réglage du serveur. 3 Milsim, 2 Standard, 1 Arcade.";
            property = "COMSPEC_ATAK_Module_Mesh_Profile";
            control = "EditShort";
            expression = "_this setVariable ['Profile',_value,true];";
            defaultValue = "-1";
            typeName = "NUMBER";
        };
    };
};
class COMSPEC_ATAK_Module_Screen: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_52480F19F2";
    icon = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    function = "comspec_atak_native_fnc_moduleScreen";
    class Attributes {
        class Name {
            displayName = "$STR_COMSPEC_ATAK_CFG_SCREEN_N_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_SCREEN_N_TIP";
            property = "COMSPEC_ATAK_Module_Screen_Name";
            control = "Edit";
            expression = "_this setVariable ['Name',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Range {
            displayName = "$STR_COMSPEC_ATAK_CFG_SCREEN_R_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_SCREEN_R_TIP";
            property = "COMSPEC_ATAK_Module_Screen_Range";
            control = "Edit";
            expression = "_this setVariable ['Range',_value,true];";
            defaultValue = "25";
            typeName = "NUMBER";
        };
        class Side {
            displayName = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_TIP";
            property = "COMSPEC_ATAK_Module_Screen_Side";
            control = "Combo";
            expression = "_this setVariable ['Side',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_ALL"; value = ""; default = 1; };
                class V1 { name = "BLUFOR"; value = "WEST"; };
                class V2 { name = "OPFOR"; value = "EAST"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_GUER"; value = "GUER"; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_CIV"; value = "CIV"; };
            };
        };
        class Index {
            displayName = "$STR_COMSPEC_ATAK_CFG_SCREEN_I_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_SCREEN_I_TIP";
            property = "COMSPEC_ATAK_Module_Screen_Index";
            control = "Edit";
            expression = "_this setVariable ['Index',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
        };
        class Ace {
            displayName = "$STR_COMSPEC_ATAK_CFG_SCREEN_A_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_SCREEN_A_TIP";
            property = "COMSPEC_ATAK_Module_Screen_Ace";
            control = "Checkbox";
            expression = "_this setVariable ['Ace',_value,true];";
            defaultValue = "true";
            typeName = "BOOL";
        };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_SCREEN_DESC";
    };
};
// Écran TOC (fn_moduleTocScreen, fn_tocScreen) : grand écran du jeu qui affiche le réseau ATAK d'un camp, persistant.
class COMSPEC_ATAK_Module_TocScreen: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_C90663322F";
    icon = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    function = "comspec_atak_native_fnc_moduleTocScreen";
    class Attributes {
        class Name {
            displayName = "$STR_COMSPEC_ATAK_CFG_SCREEN_N_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_N_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Name";
            control = "Edit";
            expression = "_this setVariable ['Name',_value,true];";
            defaultValue = "'TOC'";
            typeName = "STRING";
        };
        class Side {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_S_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_S_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Side";
            control = "Combo";
            expression = "_this setVariable ['Side',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_S_ALL"; value = ""; default = 1; };
                class V1 { name = "BLUFOR"; value = "WEST"; };
                class V2 { name = "OPFOR"; value = "EAST"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_GUER"; value = "GUER"; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_CIV"; value = "CIV"; };
            };
        };
        class View {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_View";
            control = "Combo";
            expression = "_this setVariable ['View',_value,true];";
            defaultValue = "'SPLIT'";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_SPLIT"; value = "SPLIT"; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_MAP"; value = "MAP"; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_FEED"; value = "FEED"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_PIP"; value = "TOC"; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_SAT"; value = "SAT"; };
                class V5 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_CLOCK"; value = "CLOCK"; };
                class V6 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_SQUAD"; value = "SQUAD"; };
                class V7 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_LOG"; value = "LOG"; };
                class V8 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_EXCHECK"; value = "EXCHECK"; };
                class V9 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_KILLS"; value = "KILLS"; };
                class V10 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_REQ"; value = "REQ"; };
                class V11 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_STRIP"; value = "STRIP"; };
                class V12 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_PLATE"; value = "PLATE"; };
                class V13 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_MOSAIC"; value = "MOSAIC"; };
                class V14 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_TEXT"; value = "TEXT"; };
                class V15 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_V_BROADCAST"; value = "BROADCAST"; };
            };
        };
        // Affichage « Texte à LED » (fn_tocText) : messages, couleur, vitesse et défilement.
        class Text {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TX_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TX_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Text";
            control = "Edit";
            expression = "_this setVariable ['Text',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class TextColor {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_TextColor";
            control = "Combo";
            expression = "_this setVariable ['TextColor',_value,true];";
            defaultValue = "'G'";
            typeName = "STRING";
            class values {
                class G { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_G"; value = "G"; default = 1; };
                class A { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_A"; value = "A"; };
                class R { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_R"; value = "R"; };
                class W { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_W"; value = "W"; };
            };
        };
        class TextSpeed {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_TextSpeed";
            control = "Combo";
            expression = "_this setVariable ['TextSpeed',_value,true];";
            defaultValue = "1";
            typeName = "NUMBER";
            class values {
                class S0 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_0"; value = 0; };
                class S1 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_1"; value = 1; default = 1; };
                class S2 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_2"; value = 2; };
            };
        };
        class TextScroll {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXM_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXM_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_TextScroll";
            control = "Combo";
            expression = "_this setVariable ['TextScroll',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
            class values {
                class M0 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXM_0"; value = 0; default = 1; };
                class M1 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXM_1"; value = 1; };
            };
        };
        // Salle TOC : satellite et escouade montrés, liste d'exécution préparée.
        class Sat {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_SAT_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_SAT_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Sat";
            control = "Combo";
            expression = "_this setVariable ['Sat',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
            class values {
                class S0 { name = "SAT 1"; value = 0; default = 1; };
                class S1 { name = "SAT 2"; value = 1; };
            };
        };
        class Squad {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_SQ_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_SQ_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Squad";
            control = "Edit";
            expression = "_this setVariable ['Squad',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Panels {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_PN_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_PN_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Panels";
            control = "Edit";
            expression = "_this setVariable ['Panels',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Excheck {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_EX_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_EX_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Excheck";
            control = "Edit";
            expression = "_this setVariable ['Excheck',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Toc {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_T_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_T_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Toc";
            control = "Checkbox";
            expression = "_this setVariable ['Toc',_value,true];";
            defaultValue = "true";
            typeName = "BOOL";
        };
        class Class {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Class";
            control = "Combo";
            expression = "_this setVariable ['Class',_value,true];";
            defaultValue = "'COMSPEC_ATAK_Screen_Tripod'";
            typeName = "STRING";
            class values {
                class C0 { name = "$STR_COMSPEC_ATAK_CFG_D875FDECAE"; value = "COMSPEC_ATAK_Screen_Desk"; };
                class C1 { name = "$STR_COMSPEC_ATAK_CFG_TOCOLD_DESK2"; value = "COMSPEC_ATAK_Screen_Desk2"; };
                class C2 { name = "$STR_COMSPEC_ATAK_CFG_TOCOLD_DESK3"; value = "COMSPEC_ATAK_Screen_Desk3"; };
                class C3 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_1"; value = "COMSPEC_ATAK_TocPC_1"; };
                class C4 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_2"; value = "COMSPEC_ATAK_TocPC_2"; };
                class C5 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_3"; value = "COMSPEC_ATAK_TocPC_3"; };
                class C6 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_C"; value = "COMSPEC_ATAK_TocPC_Curved"; };
                class C7 { name = "$STR_COMSPEC_ATAK_CFG_8C3886D686"; value = "COMSPEC_ATAK_Screen_Tripod"; default = 1; };
                class C8 { name = "$STR_COMSPEC_ATAK_CFG_477D05DF6A"; value = "COMSPEC_ATAK_Screen_Dual"; };
                class C9 { name = "$STR_COMSPEC_ATAK_CFG_BFF6157B7C"; value = "COMSPEC_ATAK_Screen_Wall"; };
                class C10 { name = "$STR_COMSPEC_ATAK_CFG_TOCOLD_TV75"; value = "COMSPEC_ATAK_Screen_Wall75"; };
                class C11 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_98"; value = "COMSPEC_ATAK_Screen_98"; };
                class C12 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_98S"; value = "COMSPEC_ATAK_Screen_98_Stand"; };
                class C13 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_65C"; value = "COMSPEC_ATAK_Screen_65_Ceiling"; };
                class C14 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_65C2"; value = "COMSPEC_ATAK_Screen_65_Ceiling2"; };
                class C15 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_65C3"; value = "COMSPEC_ATAK_Screen_65_Ceiling3"; };
                class C16 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_65C3A"; value = "COMSPEC_ATAK_Screen_65_Ceiling3_Arc"; };
                class C17 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_98C"; value = "COMSPEC_ATAK_Screen_98_Ceiling"; };
                class C18 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W22"; value = "COMSPEC_ATAK_Screen_Wall2x2"; };
                class C19 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W22Q"; value = "COMSPEC_ATAK_Screen_Wall2x2_Quad"; };
                class C20 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W32"; value = "COMSPEC_ATAK_Screen_Wall3x2"; };
                class C21 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W32M"; value = "COMSPEC_ATAK_Screen_Wall3x2_Mixed"; };
                class C22 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W43"; value = "COMSPEC_ATAK_Screen_Wall4x3"; };
                class C23 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W43P"; value = "COMSPEC_ATAK_Screen_Wall4x3_Panels"; };
                class C24 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W53"; value = "COMSPEC_ATAK_Screen_Wall5x3"; };
                class C25 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W53P"; value = "COMSPEC_ATAK_Screen_Wall5x3_Panels"; };
                class C26 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W64"; value = "COMSPEC_ATAK_Screen_Wall6x4"; };
                class C27 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_W64Z"; value = "COMSPEC_ATAK_Screen_Wall6x4_Zones"; };
                class C28 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_136"; value = "COMSPEC_ATAK_Screen_136"; };
                class C29 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_136S"; value = "COMSPEC_ATAK_Screen_136_Stand"; };
                class C30 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_L220"; value = "COMSPEC_ATAK_Screen_Led220"; };
                class C31 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_L220T"; value = "COMSPEC_ATAK_Screen_Led220_Truss"; };
                class C32 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_CRV"; value = "COMSPEC_ATAK_Screen_Curved"; };
                class C33 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_CRVD"; value = "COMSPEC_ATAK_Screen_Curved_Duo"; };
                class C34 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_CRVW"; value = "COMSPEC_ATAK_Screen_Curved_Wall"; };
                class C35 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_CRVWD"; value = "COMSPEC_ATAK_Screen_Curved_Wall_Duo"; };
                class C36 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_CLK"; value = "COMSPEC_ATAK_Screen_ClockStrip"; };
                class C37 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_LT8"; value = "COMSPEC_ATAK_Screen_LedText8"; };
                class C38 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_LT16"; value = "COMSPEC_ATAK_Screen_LedText16"; };
                class C39 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_LT8C"; value = "COMSPEC_ATAK_Screen_LedText8_Ceiling"; };
                class C40 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_LT16C"; value = "COMSPEC_ATAK_Screen_LedText16_Ceiling"; };
                class C41 { name = "$STR_COMSPEC_ATAK_CFG_TOC3D_PLT"; value = "COMSPEC_ATAK_Screen_NamePlate"; };
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_LARGE"; value = "Land_TripodScreen_01_large_F"; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_LARGEB"; value = "Land_TripodScreen_01_large_black_F"; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_DUAL"; value = "Land_TripodScreen_01_dual_v1_F"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_TV"; value = "Land_FlatTV_01_F"; };
                // Ancien V4 : « Land_ProjectorScreen_01_F », classe absente du jeu (toujours remplacée à la pose).
                // Écran de salle de briefing (blanc « OPSIS », DLC Jets) et ordinateur multi-écran renforcé (Contact).
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_BRIEF"; value = "Land_BriefingRoomScreen_01_F"; };
                class V5 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_PC"; value = "Land_PCSet_01_screen_F"; };
                class V6 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_C_MULTI"; value = "Land_MultiScreenComputer_01_black_F"; };
            };
        };
        class Index {
            displayName = "$STR_COMSPEC_ATAK_CFG_SCREEN_I_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_SCREEN_I_TIP";
            property = "COMSPEC_ATAK_Module_TocScreen_Index";
            control = "Edit";
            expression = "_this setVariable ['Index',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
        };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_TOCSCR_DESC";
    };
};
// Salle TOC complète (fn_moduleTocHall, fn_tocHall) : salle d'opérations posée d'un coup, déjà liée à ses PC.
class COMSPEC_ATAK_Module_TocHall: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_TOCHALL_NAME";
    icon = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    function = "comspec_atak_native_fnc_moduleTocHall";
    class Attributes {
        class Name {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCHALL_N_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCHALL_N_TIP";
            property = "COMSPEC_ATAK_Module_TocHall_Name";
            control = "Edit";
            expression = "_this setVariable ['Name',_value,true];";
            defaultValue = "'SALLE TOC'";
            typeName = "STRING";
        };
        class Side {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCHALL_S_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCHALL_S_TIP";
            property = "COMSPEC_ATAK_Module_TocHall_Side";
            control = "Combo";
            expression = "_this setVariable ['Side',_value,true];";
            defaultValue = "'WEST'";
            typeName = "STRING";
            class values {
                class V1 { name = "BLUFOR"; value = "WEST"; default = 1; };
                class V2 { name = "OPFOR"; value = "EAST"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_GUER"; value = "GUER"; };
            };
        };
        class Size {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCHALL_Z_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCHALL_Z_TIP";
            property = "COMSPEC_ATAK_Module_TocHall_Size";
            control = "Combo";
            expression = "_this setVariable ['Size',_value,true];";
            defaultValue = "'M'";
            typeName = "STRING";
            class values {
                class S { name = "$STR_COMSPEC_ATAK_CFG_TOCHALL_Z_S"; value = "S"; };
                class M { name = "$STR_COMSPEC_ATAK_CFG_TOCHALL_Z_M"; value = "M"; default = 1; };
                class L { name = "$STR_COMSPEC_ATAK_CFG_TOCHALL_Z_L"; value = "L"; };
            };
        };
        // Postes des opérateurs : PC portable de TOC ou poste PC fixe (toc_pc.hpp, fn_tocPc).
        class Post {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCHALL_P_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCHALL_P_TIP";
            property = "COMSPEC_ATAK_Module_TocHall_Post";
            control = "Combo";
            expression = "_this setVariable ['Post',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
            class values {
                class P0 { name = "$STR_COMSPEC_ATAK_CFG_TOCLAPTOP"; value = ""; default = 1; };
                class P1 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_1"; value = "COMSPEC_ATAK_TocPC_1"; };
                class P2 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_2"; value = "COMSPEC_ATAK_TocPC_2"; };
                class P3 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_3"; value = "COMSPEC_ATAK_TocPC_3"; };
                class P4 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_C"; value = "COMSPEC_ATAK_TocPC_Curved"; };
            };
        };
        // Module supprimé en cours de partie (Zeus, script) : sa salle part avec lui (fn_tocHallBuild « remove »).
        class Bind {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCHALL_B_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCHALL_B_TIP";
            property = "COMSPEC_ATAK_Module_TocHall_Bind";
            control = "Checkbox";
            expression = "_this setVariable ['Bind',_value,true];";
            defaultValue = "true";
            typeName = "BOOL";
        };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_TOCHALL_DESC";
    };
};
// Tableau des écrans TOC (fn_moduleTocManager, fn_tocManager) : au Zeus, ouvre le tableau ; ne pose rien, sans attribut.
class COMSPEC_ATAK_Module_TocManager: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_TOCMGR_NAME";
    icon = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    function = "comspec_atak_native_fnc_moduleTocManager";
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_TOCMGR_DESC";
    };
};
// Poste opérateur (fn_moduleTocPc, fn_tocPc) : bureau, chaise, téléphone de table et poste PC fixe, prêt à servir.
class COMSPEC_ATAK_Module_TocPc: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_TOCPCMOD_NAME";
    icon = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    function = "comspec_atak_native_fnc_moduleTocPc";
    class Attributes {
        class Class {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCPCMOD_C_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCPCMOD_C_TIP";
            property = "COMSPEC_ATAK_Module_TocPc_Class";
            control = "Combo";
            expression = "_this setVariable ['Class',_value,true];";
            defaultValue = "'COMSPEC_ATAK_TocPC_1'";
            typeName = "STRING";
            class values {
                class P1 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_1"; value = "COMSPEC_ATAK_TocPC_1"; default = 1; };
                class P2 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_2"; value = "COMSPEC_ATAK_TocPC_2"; };
                class P3 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_3"; value = "COMSPEC_ATAK_TocPC_3"; };
                class P4 { name = "$STR_COMSPEC_ATAK_CFG_TOCPC_C"; value = "COMSPEC_ATAK_TocPC_Curved"; };
            };
        };
        class Name {
            displayName = "$STR_COMSPEC_ATAK_CFG_SCREEN_N_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCPCMOD_N_TIP";
            property = "COMSPEC_ATAK_Module_TocPc_Name";
            control = "Edit";
            expression = "_this setVariable ['Name',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Side {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSIDE";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSIDE_TIP";
            property = "COMSPEC_ATAK_Module_TocPc_Side";
            control = "Combo";
            expression = "_this setVariable ['Side',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_S_ALL"; value = ""; default = 1; };
                class V1 { name = "BLUFOR"; value = "WEST"; };
                class V2 { name = "OPFOR"; value = "EAST"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_GUER"; value = "GUER"; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_CIV"; value = "CIV"; };
            };
        };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_TOCPCMOD_DESC";
    };
};
// Afficher un texte (fn_moduleTocText, fn_tocTextTool) : message des écrans en affichage TEXTE, pour un écran ou une salle.
class COMSPEC_ATAK_Module_TocText: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_TOCTXT_NAME";
    icon = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_screen.paa";
    function = "comspec_atak_native_fnc_moduleTocText";
    class Attributes {
        class Text {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCTXT_T_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCTXT_T_TIP";
            property = "COMSPEC_ATAK_Module_TocText_Text";
            control = "Edit";
            expression = "_this setVariable ['Text',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class Color {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_TIP";
            property = "COMSPEC_ATAK_Module_TocText_Color";
            control = "Combo";
            expression = "_this setVariable ['Color',_value,true];";
            defaultValue = "'G'";
            typeName = "STRING";
            class values {
                class G { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_G"; value = "G"; default = 1; };
                class A { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_A"; value = "A"; };
                class R { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_R"; value = "R"; };
                class W { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXC_W"; value = "W"; };
            };
        };
        class Speed {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_TIP";
            property = "COMSPEC_ATAK_Module_TocText_Speed";
            control = "Combo";
            expression = "_this setVariable ['Speed',_value,true];";
            defaultValue = "1";
            typeName = "NUMBER";
            class values {
                class S0 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_0"; value = 0; };
                class S1 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_1"; value = 1; default = 1; };
                class S2 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXS_2"; value = 2; };
            };
        };
        class Scroll {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXM_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXM_TIP";
            property = "COMSPEC_ATAK_Module_TocText_Scroll";
            control = "Combo";
            expression = "_this setVariable ['Scroll',_value,true];";
            defaultValue = "0";
            typeName = "NUMBER";
            class values {
                class M0 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXM_0"; value = 0; default = 1; };
                class M1 { name = "$STR_COMSPEC_ATAK_CFG_TOCSCR_TXM_1"; value = 1; };
            };
        };
        class TxtScope {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCTXT_S_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCTXT_S_TIP";
            property = "COMSPEC_ATAK_Module_TocText_Scope";
            control = "Combo";
            expression = "_this setVariable ['TxtScope',_value,true];";
            defaultValue = "'sync'";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_TOCTXT_S_SYNC"; value = "sync"; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_TOCTXT_S_NEAREST"; value = "nearest"; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_TOCTXT_S_HALL"; value = "hall"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_TOCTXT_S_NEAR"; value = "near"; };
            };
        };
        class Radius {
            displayName = "$STR_COMSPEC_ATAK_CFG_TOCTXT_R_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_TOCTXT_R_TIP";
            property = "COMSPEC_ATAK_Module_TocText_Radius";
            control = "Edit";
            expression = "_this setVariable ['Radius',_value,true];";
            defaultValue = "50";
            typeName = "NUMBER";
        };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_TOCTXT_DESC";
    };
};
// IA équipées d'un ATAK (fn_moduleAiAtak, fn_aiAtak).
class COMSPEC_ATAK_Module_AiAtak: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_D7DEF53FF6";
    icon = "\z\comspec_atak_native\addons\main\data\mod_phone.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_phone.paa";
    function = "comspec_atak_native_fnc_moduleAiAtak";
    class Attributes {
        class Who {
            displayName = "$STR_COMSPEC_ATAK_CFG_AIATAK_W_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_AIATAK_W_TIP";
            property = "COMSPEC_ATAK_Module_AiAtak_Who";
            control = "Combo";
            expression = "_this setVariable ['Who',_value,true];";
            defaultValue = "'all'";
            typeName = "STRING";
            class values {
                class V0 { name = "$STR_COMSPEC_ATAK_CFG_AIATAK_W_ALL"; value = "all"; default = 1; };
                class V1 { name = "$STR_COMSPEC_ATAK_CFG_AIATAK_W_LEAD"; value = "leaders"; };
                class V2 { name = "$STR_COMSPEC_ATAK_CFG_AIATAK_W_NONE"; value = "none"; };
            };
        };
        class Side {
            displayName = "$STR_COMSPEC_ATAK_CFG_AIATAK_S_NAME";
            tooltip = "$STR_COMSPEC_ATAK_CFG_AIATAK_S_TIP";
            property = "COMSPEC_ATAK_Module_AiAtak_Side";
            control = "Combo";
            expression = "_this setVariable ['Side',_value,true];";
            defaultValue = "'WEST'";
            typeName = "STRING";
            class values {
                class V1 { name = "BLUFOR"; value = "WEST"; default = 1; };
                class V2 { name = "OPFOR"; value = "EAST"; };
                class V3 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_GUER"; value = "GUER"; };
                class V4 { name = "$STR_COMSPEC_ATAK_CFG_SCREEN_S_CIV"; value = "CIV"; };
            };
        };
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_AIATAK_DESC";
    };
};
// Contrôle Zeus des ATAK (fn_moduleZeusControl, fn_moduleZeusJammer) : Zeus seulement, formulaire à la pose.
class COMSPEC_ATAK_Module_ZeusControl: COMSPEC_ATAK_Module_DataBase {
    scope = 1;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_0CDE42EDFD";
    icon = "\z\comspec_atak_native\addons\main\data\mod_phone.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_phone.paa";
    function = "comspec_atak_native_fnc_moduleZeusControl";
};
class COMSPEC_ATAK_Module_ZeusJammer: COMSPEC_ATAK_Module_DataBase {
    scope = 1;
    scopeCurator = 2;
    category = "COMSPEC_Cat_Atak";
    displayName = "$STR_COMSPEC_ATAK_CFG_66CE91022B";
    icon = "\z\comspec_atak_native\addons\main\data\mod_jam.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\mod_jam.paa";
    function = "comspec_atak_native_fnc_moduleZeusJammer";
};
