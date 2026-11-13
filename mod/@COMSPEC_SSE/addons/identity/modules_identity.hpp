/*
    Module Eden « Checkpoint IA » (Lot 3) : les gardes (unités synchronisées, sinon IA du camp dans la zone) arrêtent
    les joueurs qui passent, demandent leurs papiers, les lisent avec l'équipement du poste et décident (laisser passer,
    fouiller, retenir, alerter, ouvrir le feu). Exécuté sur le serveur (fn_idCheckpointModule). En Zeus : module ZEN
    « COMSPEC · Identité › Poser un checkpoint IA ».
*/
class COMSPEC_SSE_Module_Base;
class COMSPEC_SSE_Module_IdCheckpoint: COMSPEC_SSE_Module_Base {
    scope = 2;
    scopeCurator = 1;
    displayName = "$STR_COMSPEC_SSE_CFG_73AFB9F42D";
    category = "COMSPEC_Cat_Identite";
    function = "comspec_sse_fnc_idCheckpointModule";
    isGlobal = 0;
    icon = "\z\comspec_sse\addons\zeus\data\mod_check.paa";
    portrait = "\z\comspec_sse\addons\zeus\data\mod_check.paa";

    class Attributes {
        class Radius {
            displayName = "$STR_COMSPEC_SSE_CFG_9E1A18D1AC";
            tooltip = "$STR_COMSPEC_SSE_CFG_71C9B6FF5B";
            property = "COMSPEC_SSE_Module_IdCheckpoint_Radius";
            control = "Edit";
            expression = "_this setVariable ['Radius',_value,true];";
            defaultValue = "-1";
            validate = "number";
            typeName = "NUMBER";
        };
        class Side {
            displayName = "$STR_COMSPEC_SSE_CFG_B13B943E10";
            tooltip = "$STR_COMSPEC_SSE_CFG_896A2FC8F5";
            property = "COMSPEC_SSE_Module_IdCheckpoint_Side";
            control = "Combo";
            expression = "_this setVariable ['Side',_value,true];";
            defaultValue = "'auto'";
            typeName = "STRING";
            class values {
                class Auto { name = "$STR_COMSPEC_SSE_CFG_F8A3C37BEB"; value = "auto"; default = 1; };
                class West { name = "$STR_COMSPEC_SSE_CFG_8B44D9DB67"; value = "west"; };
                class East { name = "$STR_COMSPEC_SSE_CFG_E80F2DBA19"; value = "east"; };
                class Guer { name = "$STR_COMSPEC_SSE_CFG_DE8D4F953A"; value = "guer"; };
                class Civ { name = "$STR_COMSPEC_SSE_CFG_68BA79C70C"; value = "civ"; };
            };
        };
        class Equipment {
            displayName = "$STR_COMSPEC_SSE_CFG_80CFE2A6F4";
            tooltip = "$STR_COMSPEC_SSE_CFG_2AC1771BAB";
            property = "COMSPEC_SSE_Module_IdCheckpoint_Equipment";
            control = "Combo";
            expression = "_this setVariable ['Equipment',_value,true];";
            defaultValue = "1";
            typeName = "NUMBER";
            class values {
                class V0 { name = "$STR_COMSPEC_SSE_CFG_3D97A58A86"; value = 0; };
                class V1 { name = "$STR_COMSPEC_SSE_CFG_F25CFF0659"; value = 1; default = 1; };
                class V2 { name = "$STR_COMSPEC_SSE_CFG_103DEB4F89"; value = 2; };
                class V3 { name = "$STR_COMSPEC_SSE_CFG_40455C3377"; value = 3; };
            };
        };
        class Severity {
            displayName = "$STR_COMSPEC_SSE_CFG_62FD4AE52C";
            tooltip = "$STR_COMSPEC_SSE_CFG_79DEB5E3E4";
            property = "COMSPEC_SSE_Module_IdCheckpoint_Severity";
            control = "Combo";
            expression = "_this setVariable ['Severity',_value,true];";
            defaultValue = "1";
            typeName = "NUMBER";
            class values {
                class V0 { name = "$STR_COMSPEC_SSE_CFG_8A03A21CE4"; value = 0; };
                class V1 { name = "$STR_COMSPEC_SSE_CFG_C82C13B2DD"; value = 1; default = 1; };
                class V2 { name = "$STR_COMSPEC_SSE_CFG_05D49BB51C"; value = 2; };
            };
        };
        class CpName {
            displayName = "$STR_COMSPEC_SSE_CFG_02B0B86BAB";
            tooltip = "$STR_COMSPEC_SSE_CFG_09E57A77DF";
            property = "COMSPEC_SSE_Module_IdCheckpoint_Name";
            control = "Edit";
            expression = "_this setVariable ['CpName',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
    };

    class ModuleDescription {
        description = "$STR_COMSPEC_SSE_CFG_FE5A378E70";
        sync[] = {"AnyBrain"};
    };
};

/*
    Module « Synchroniser les IA avec Athena » (lore de session, ICD-SSE-LORE-001), Eden et Zeus : fn_loreModule.
    Eden : exécuté sur le serveur au lancement (envoi, récupération, rafraîchissement selon les réglages ci-dessous).
    Zeus : fenêtre du module (action, portée, code d'opération, intervalle, état de la dernière synchro).
*/
class COMSPEC_SSE_Module_LoreSync: COMSPEC_SSE_Module_Base {
    scope = 2;
    scopeCurator = 2;
    displayName = "$STR_COMSPEC_SSE_LORE_Module";
    category = "COMSPEC_Cat_Identite";
    function = "comspec_sse_fnc_loreModule";
    icon = "\z\comspec_sse\addons\zeus\data\mod_person.paa";
    portrait = "\z\comspec_sse\addons\zeus\data\mod_person.paa";

    class Attributes {
        class Scope {
            displayName = "$STR_COMSPEC_SSE_LORE_Scope";
            tooltip = "$STR_COMSPEC_SSE_LORE_ScopeTip";
            property = "COMSPEC_SSE_Module_LoreSync_Scope";
            control = "Combo";
            expression = "_this setVariable ['Scope',_value,true];";
            defaultValue = "'all'";
            typeName = "STRING";
            class values {
                class All { name = "$STR_COMSPEC_SSE_LORE_ScopeAll"; value = "all"; default = 1; };
                class Synced { name = "$STR_COMSPEC_SSE_LORE_ScopeSynced"; value = "synced"; };
                class Sse { name = "$STR_COMSPEC_SSE_LORE_ScopeSse"; value = "sse"; };
            };
        };
        class OpKey {
            displayName = "$STR_COMSPEC_SSE_LORE_Op";
            tooltip = "$STR_COMSPEC_SSE_LORE_OpTip";
            property = "COMSPEC_SSE_Module_LoreSync_Op";
            control = "Edit";
            expression = "_this setVariable ['OpKey',_value,true];";
            defaultValue = "''";
            typeName = "STRING";
        };
        class PushStart {
            displayName = "$STR_COMSPEC_SSE_LORE_PushStart";
            tooltip = "$STR_COMSPEC_SSE_LORE_PushStartTip";
            property = "COMSPEC_SSE_Module_LoreSync_PushStart";
            control = "Checkbox";
            expression = "_this setVariable ['PushStart',_value,true];";
            defaultValue = "true";
            typeName = "BOOL";
        };
        class PullStart {
            displayName = "$STR_COMSPEC_SSE_LORE_PullStart";
            tooltip = "$STR_COMSPEC_SSE_LORE_PullStartTip";
            property = "COMSPEC_SSE_Module_LoreSync_PullStart";
            control = "Checkbox";
            expression = "_this setVariable ['PullStart',_value,true];";
            defaultValue = "true";
            typeName = "BOOL";
        };
        class Refresh {
            displayName = "$STR_COMSPEC_SSE_LORE_Refresh";
            tooltip = "$STR_COMSPEC_SSE_LORE_RefreshTip";
            property = "COMSPEC_SSE_Module_LoreSync_Refresh";
            control = "Edit";
            expression = "_this setVariable ['Refresh',_value,true];";
            defaultValue = "0";
            validate = "number";
            typeName = "NUMBER";
        };
    };

    class ModuleDescription {
        description = "$STR_COMSPEC_SSE_LORE_ModuleDesc";
        sync[] = {"AnyBrain"};
    };
};
