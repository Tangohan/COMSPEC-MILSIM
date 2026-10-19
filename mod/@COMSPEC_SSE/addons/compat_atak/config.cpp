#include "script_component.hpp"

/*
    Passerelle SSE ↔ ATAK natif : le câble DL-7 se configure dans le SSE.

    Chargée seulement si l'ATAK natif est présent (skipWhenMissingDependencies) :
      - les modules d'extraction de l'ATAK passent dans « COMSPEC SSE · 4. Numérique » ;
      - catégorie Eden « COMSPEC SSE — Extraction DL-7 » sur n'importe quel objet ;
      - le contenu numérique généré par le SSE (fichiers, courriels, messages,
        identifiants, lieux) devient extractible au câble sans rien saisir ;
      - action Zeus (ZEN) « Rendre extractible au DL-7 » sur la sélection.
*/
class CfgPatches {
    class comspec_sse_compat_atak {
        name = "COMSPEC SSE - Compat ATAK (câble DL-7)";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_generator",
            "comspec_sse_digital",
            "comspec_sse_zeus",
            "comspec_sse_eden",
            "comspec_atak_native_main"
        };
        skipWhenMissingDependencies = 1;
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class compat_atak {
            file = "z\comspec_sse\addons\compat_atak\functions";
            class dl7FilesFromSse {};
            class dl7Apply {};
            class dl7ApplyEden {};
            class dl7ZeusForm {};
            class dl7Auto {};
            class dl7Downloaded {};
            class fieldTarget {};
            class fieldSite {};
            class fieldPage {};
            class fieldAction {};
            class photoWatch {};
            class evidencePhoto {};
        };
    };
};

// App « SSE terrain » du téléphone ATAK : le travail SSE passe par le terminal plutôt que par les menus ACE.
class COMSPEC_ATAK_Apps {
    class SseField { name="SSE terrain"; page="SSEFIELD"; function="comspec_sse_fnc_fieldPage"; section="Renseignement"; order=119; dock=0; new=1; icon="\z\comspec_atak_native\addons\main\data\app_sse.paa"; };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_compat_atak {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\compat_atak\XEH_postInit.sqf'";
    };
};

class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_dl7Apply { allowedTargets = 2; };
    };
};

// Modules d'extraction de l'ATAK rangés avec le numérique du SSE.
class CfgVehicles {
    class Module_F;
    class COMSPEC_ATAK_Module_DataBase: Module_F {};
    class COMSPEC_ATAK_Module_DataNode: COMSPEC_ATAK_Module_DataBase {
        category = "COMSPEC_SSE_Domex";
        displayName = "$STR_COMSPEC_SSE_CFG_B019C6DD4A";
    };
    class COMSPEC_ATAK_Module_DataFile: COMSPEC_ATAK_Module_DataBase {
        category = "COMSPEC_SSE_Domex";
        displayName = "$STR_COMSPEC_SSE_CFG_30F2887289";
    };
    class COMSPEC_ATAK_Module_DataReset: COMSPEC_ATAK_Module_DataBase {
        category = "COMSPEC_SSE_Domex";
        displayName = "$STR_COMSPEC_SSE_CFG_AA363E785D";
    };
};

#define DL7_COND condition = "objectSimulated"

class Cfg3DEN {
    class Object {
        class AttributeCategories {
            class COMSPEC_SSE_Dl7 {
                displayName = "$STR_COMSPEC_SSE_CFG_5FA316DD74";
                collapsed = 1;
                class Attributes {
                    class COMSPEC_SSE_Dl7_Enabled {
                        displayName = "$STR_COMSPEC_SSE_CFG_8A5DE7627B";
                        tooltip = "$STR_COMSPEC_SSE_CFG_29AE468CF2";
                        property = "COMSPEC_SSE_Dl7_Enabled";
                        control = "Checkbox";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Enabled', _value];";
                        defaultValue = "false";
                        typeName = "BOOL";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Content {
                        displayName = "$STR_COMSPEC_SSE_CFG_F3CB82AF6C";
                        tooltip = "$STR_COMSPEC_SSE_CFG_F153B82AB4";
                        property = "COMSPEC_SSE_Dl7_Content";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Content', _value];";
                        defaultValue = "'sse'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class Sse { name = "$STR_COMSPEC_SSE_CFG_2B49D01A7A"; value = "sse"; default = 1; };
                            class Manual { name = "$STR_COMSPEC_SSE_CFG_A95FCA2FCE"; value = "manual"; };
                            class Both { name = "$STR_COMSPEC_SSE_CFG_90DB01B4A5"; value = "both"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_Files {
                        displayName = "$STR_COMSPEC_SSE_CFG_F92DB9F07D";
                        tooltip = "$STR_COMSPEC_SSE_CFG_AC66EEB9F9";
                        property = "COMSPEC_SSE_Dl7_Files";
                        control = "EditMulti5";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Files', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Name {
                        displayName = "$STR_COMSPEC_SSE_CFG_7D8E20466B";
                        tooltip = "$STR_COMSPEC_SSE_CFG_F2D33E352D";
                        property = "COMSPEC_SSE_Dl7_Name";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Name', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Theme {
                        displayName = "$STR_COMSPEC_SSE_CFG_2547BF4CA0";
                        tooltip = "$STR_COMSPEC_SSE_CFG_5BD884C543";
                        property = "COMSPEC_SSE_Dl7_Theme";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Theme', _value];";
                        defaultValue = "'auto'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class Auto { name = "$STR_COMSPEC_SSE_CFG_F2F36B7F9C"; value = "auto"; default = 1; };
                            class Windows { name = "$STR_COMSPEC_SSE_CFG_6CDE1BE9BE"; value = "windows"; };
                            class Linux { name = "$STR_COMSPEC_SSE_CFG_0BF36E37B6"; value = "linux"; };
                            class Militaire { name = "$STR_COMSPEC_SSE_CFG_1E0730DF18"; value = "militaire"; };
                            class Scada { name = "$STR_COMSPEC_SSE_CFG_0FAE4CBFA9"; value = "scada"; };
                            class Telephone { name = "$STR_COMSPEC_SSE_CFG_A92E2B0C4B"; value = "telephone"; };
                            class Coffre { name = "$STR_COMSPEC_SSE_CFG_2D9AD0EB1E"; value = "coffre"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_Banner {
                        displayName = "$STR_COMSPEC_SSE_CFG_F09016E234";
                        tooltip = "$STR_COMSPEC_SSE_CFG_0C8B638B56";
                        property = "COMSPEC_SSE_Dl7_Banner";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Banner', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Password {
                        displayName = "$STR_COMSPEC_SSE_CFG_94E2F3EE14";
                        tooltip = "$STR_COMSPEC_SSE_CFG_B72C6C2193";
                        property = "COMSPEC_SSE_Dl7_Password";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Password', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Hint {
                        displayName = "$STR_COMSPEC_SSE_CFG_C1C0201437";
                        tooltip = "$STR_COMSPEC_SSE_CFG_98E82155A6";
                        property = "COMSPEC_SSE_Dl7_Hint";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Hint', _value];";
                        defaultValue = "''";
                        typeName = "STRING";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Attempts {
                        displayName = "$STR_COMSPEC_SSE_CFG_F3FEFB2906";
                        tooltip = "$STR_COMSPEC_SSE_CFG_00C08F59B3";
                        property = "COMSPEC_SSE_Dl7_Attempts";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Attempts', _value];";
                        defaultValue = "3";
                        validate = "number";
                        typeName = "NUMBER";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_OnFail {
                        displayName = "$STR_COMSPEC_SSE_CFG_CED1C585A6";
                        tooltip = "$STR_COMSPEC_SSE_CFG_600E25C8D2";
                        property = "COMSPEC_SSE_Dl7_OnFail";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_OnFail', _value];";
                        defaultValue = "'lock'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class Lock { name = "$STR_COMSPEC_SSE_CFG_7EC29D9943"; value = "lock"; default = 1; };
                            class Wipe { name = "$STR_COMSPEC_SSE_CFG_0384948B18"; value = "wipe"; };
                            class Alarm { name = "$STR_COMSPEC_SSE_CFG_2FA41491DA"; value = "alarm"; };
                            class None { name = "$STR_COMSPEC_SSE_CFG_107309E51C"; value = "none"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_Crack {
                        displayName = "$STR_COMSPEC_SSE_CFG_13E08E6708";
                        tooltip = "$STR_COMSPEC_SSE_CFG_03CAF58EAF";
                        property = "COMSPEC_SSE_Dl7_Crack";
                        control = "Checkbox";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Crack', _value];";
                        defaultValue = "true";
                        typeName = "BOOL";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_CrackTime {
                        displayName = "$STR_COMSPEC_SSE_CFG_AF7756A553";
                        tooltip = "$STR_COMSPEC_SSE_CFG_18D06EB587";
                        property = "COMSPEC_SSE_Dl7_CrackTime";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_CrackTime', _value];";
                        defaultValue = "90";
                        validate = "number";
                        typeName = "NUMBER";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Duration {
                        displayName = "$STR_COMSPEC_SSE_CFG_C54AECDE5A";
                        tooltip = "$STR_COMSPEC_SSE_CFG_7F7B72796A";
                        property = "COMSPEC_SSE_Dl7_Duration";
                        control = "Edit";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Duration', _value];";
                        defaultValue = "45";
                        validate = "number";
                        typeName = "NUMBER";
                        DL7_COND;
                    };
                    class COMSPEC_SSE_Dl7_Port {
                        displayName = "$STR_COMSPEC_SSE_CFG_FE035157CD";
                        tooltip = "$STR_COMSPEC_SSE_CFG_4F40071791";
                        property = "COMSPEC_SSE_Dl7_Port";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Port', _value];";
                        defaultValue = "'usb'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class Usb { name = "$STR_COMSPEC_SSE_CFG_DBB1867285"; value = "usb"; default = 1; };
                            class Rj45 { name = "$STR_COMSPEC_SSE_CFG_8161F144F1"; value = "rj45"; };
                            class Serie { name = "$STR_COMSPEC_SSE_CFG_2B264D3E23"; value = "serie"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_Alarm {
                        displayName = "$STR_COMSPEC_SSE_CFG_68C602A962";
                        tooltip = "$STR_COMSPEC_SSE_CFG_BA85DC89ED";
                        property = "COMSPEC_SSE_Dl7_Alarm";
                        control = "Combo";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_Alarm', _value];";
                        defaultValue = "'none'";
                        typeName = "STRING";
                        DL7_COND;
                        class values {
                            class None { name = "$STR_COMSPEC_SSE_CFG_E8F8827310"; value = "none"; default = 1; };
                            class Connect { name = "$STR_COMSPEC_SSE_CFG_B80BA8ECDA"; value = "connect"; };
                            class Fail { name = "$STR_COMSPEC_SSE_CFG_58F66D2E68"; value = "fail"; };
                            class Download { name = "$STR_COMSPEC_SSE_CFG_7DB7C44D86"; value = "download"; };
                        };
                    };
                    class COMSPEC_SSE_Dl7_OneShot {
                        displayName = "$STR_COMSPEC_SSE_CFG_4275ED3369";
                        tooltip = "$STR_COMSPEC_SSE_CFG_6DE71EE356";
                        property = "COMSPEC_SSE_Dl7_OneShot";
                        control = "Checkbox";
                        expression = "_this setVariable ['COMSPEC_SSE_Dl7_OneShot', _value];";
                        defaultValue = "false";
                        typeName = "BOOL";
                        DL7_COND;
                    };
                };
            };
        };
    };
};
