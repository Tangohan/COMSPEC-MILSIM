// Modules Zeus / Eden de l'extraction de données (inclus dans CfgVehicles de config.cpp).
// Attributs Eden à plat (sans AttributesBase) : ne pas rouvrir Module_F:Logic (casse Eden / Zeus / ACE).
// Zeus n'affiche pas les attributs : chaque module ouvre un formulaire vanilla à la pose (fn_zeusForm).
class Module_F;
class COMSPEC_ATAK_Module_DataBase: Module_F {
    author = "COMSPEC";
    scope = 1;
    scopeCurator = 1;
    category = "COMSPEC_ATAK_Data";
    functionPriority = 1;
    isGlobal = 1;
    isTriggerActivated = 0;
    isDisposable = 1;
    is3DEN = 0;
    curatorCanAttach = 1;
    icon = "\z\comspec_atak_native\addons\main\data\app_datalink.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\app_datalink.paa";
};
class COMSPEC_ATAK_Module_DataNode: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    displayName = "$STR_COMSPEC_ATAK_CFG_38684AA97D";
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
                tooltip = "#rrggbb, vide = couleur du thème.";
                property = "COMSPEC_ATAK_Module_DataNode_Accent";
                control = "Edit";
                expression = "_this setVariable ['Accent',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Wallpaper {
                displayName = "$STR_COMSPEC_ATAK_CFG_7FFED89DE0";
                tooltip = "Image de la mission (ex. images\logo.paa) en tête de l'écran de connexion.";
                property = "COMSPEC_ATAK_Module_DataNode_Wallpaper";
                control = "Edit";
                expression = "_this setVariable ['Wallpaper',_value,true];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class Code {
                displayName = "$STR_COMSPEC_ATAK_CFG_109E16CA46";
                tooltip = "Exécuté sur le serveur à la fin d'un téléchargement. params [""_object"", ""_player"", ""_files""]. Aussi : événement CBA comspec_atak_native_dataDownloaded.";
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
    scope = 2;
    scopeCurator = 2;
    displayName = "$STR_COMSPEC_ATAK_CFG_60D4B6A0C2";
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
    scope = 1;
    scopeCurator = 2;
    displayName = "$STR_COMSPEC_ATAK_CFG_CF51DCF204";
    function = "comspec_atak_native_fnc_moduleDataReset";
};
// Tinder : une IA devient un profil HVT ou SSE qu'on croise dans l'app (fn_moduleDating, fn_datingSpecials).
class COMSPEC_ATAK_Module_DatingProfile: COMSPEC_ATAK_Module_DataBase {
    scope = 1;
    scopeCurator = 2;
    category = "COMSPEC_ATAK_Civil";
    displayName = "$STR_COMSPEC_ATAK_CFG_FA0AF6576A";
    icon = "\z\comspec_atak_native\addons\main\data\app_dating.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\app_dating.paa";
    function = "comspec_atak_native_fnc_moduleDating";
};
// Réseau : relais ATAK posé par la mission (fn_moduleRelay). Le cercle du module dans Eden est la portée.
class COMSPEC_ATAK_Module_Relay: COMSPEC_ATAK_Module_DataBase {
    scope = 2;
    scopeCurator = 2;
    category = "COMSPEC_ATAK_Network";
    displayName = "$STR_COMSPEC_ATAK_CFG_FF947AEE42";
    icon = "\z\comspec_atak_native\addons\main\data\app_waverelay.paa";
    portrait = "\z\comspec_atak_native\addons\main\data\app_waverelay.paa";
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
                class V1 { name = "UHF 400 MHz"; value = 400; };
                class V2 { name = "L 1,4 GHz"; value = 1400; };
                class V3 { name = "S 2,4 GHz"; value = 2400; };
                class V4 { name = "C 4,9 GHz"; value = 4900; };
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
    };
    class ModuleDescription {
        description = "$STR_COMSPEC_ATAK_CFG_22558DD904";
    };
};
