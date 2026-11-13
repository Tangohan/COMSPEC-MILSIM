#include "script_component.hpp"
#include "ui_macros.hpp"

/*
    COMSPEC SSE — IA RP : parler aux IA en texte libre, bulles 3D, journal des échanges.
    Terminal « Parler à… » (ACE, molette sans ACE, touche CBA) sur une IA à 5 m ; commandes RP /me /do /try /todo ;
    interprète d'intentions local (mots-clés FR et EN, fn_iarpIntent) et générateur de répliques à grammaire
    (fn_iarpGen) : aucune IA générative, aucune requête réseau, rien vers Athena.
    La réponse vient de la couverture de CETTE IA (légende de l'identité SSE, faits de l'atelier IA) et de son état
    (automate des IA réalistes) quand l'addon identity est chargé ; sans lui, des faits de l'atelier seulement.
    L'ATAK n'est pas requis par cet addon : bulles, terminal et textes (stringtable.xml) sont propres au SSE.
*/
class CfgPatches {
    class comspec_sse_ia_rp {
        name = "COMSPEC SSE - IA RP";
        units[] = {"COMSPEC_SSE_Module_IarpWorkshop"};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_main", "comspec_sse_core", "comspec_sse_ui", "cba_main", "cba_xeh", "cba_settings", "3DEN", "A3_Modules_F"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class ia_rp {
            file = "z\comspec_sse\addons\ia_rp\functions";
            class iarpSettings {};
            class iarpData {};
            class iarpNorm {};
            class iarpIntent {};
            class iarpOffTopic {};
            class iarpGen {};
            class iarpFill {};
            class iarpProfile {};
            class iarpServer {};
            class iarpReact {};
            class iarpFollow {};
            class iarpAggro {};
            class iarpMelee {};
            class iarpMem {};
            class iarpClient {};
            class iarpBubble {};
            class iarpUi {};
            class iarpSelfTest {};
            class iarpWorkshop {};
            class iarpEden {};
            class iarpModule {};
            class iarpZen {};
            class iarpName {};
            class iarpAnswers {};
            class iarpAnswersUi {};
            class iarpZenHub {};
        };
    };
};

// Demandes du joueur au serveur (demandeur retrouvé par remoteExecutedOwner) ; réponses du serveur au joueur.
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_iarpServer { allowedTargets = 2; };
        class comspec_sse_fnc_iarpClient { allowedTargets = 1; };
    };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_ia_rp {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\ia_rp\XEH_preInit.sqf'";
    };
};
class Extended_PostInit_EventHandlers {
    class comspec_sse_ia_rp {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\ia_rp\XEH_postInit.sqf'";
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\ia_rp\XEH_postInitClient.sqf'";
    };
};

// Manuel SSE (Échap › Manuel de terrain › COMSPEC SSE) : chapitre « Parler aux IA ». La liste complète des phrases, par
// thème et cliquable, est l'onglet GUIDE de la fenêtre « Parler à » (tirée du lexique : elle ne peut pas diverger).
class CfgHints {
    class COMSPEC_SSE {
        class IaRpTalk {
            displayName = "$STR_COMSPEC_IARP_Man_T";
            displayNameShort = "$STR_COMSPEC_IARP_Man_T";
            description = "$STR_COMSPEC_IARP_Man_D";
            tip = "$STR_COMSPEC_IARP_Man_Tip";
            arguments[] = {};
            image = "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa";
            noImage = 0;
        };
        // Pour le maître du jeu : réponses écrites par lui, ce qu'une IA peut évoquer, thèmes interdits.
        class IaRpAnswers {
            displayName = "$STR_COMSPEC_IARP_ManA_T";
            displayNameShort = "$STR_COMSPEC_IARP_ManA_T";
            description = "$STR_COMSPEC_IARP_ManA_D";
            tip = "$STR_COMSPEC_IARP_ManA_Tip";
            arguments[] = {};
            image = "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa";
            noImage = 0;
        };
        // Pour le maître du jeu : émotions des IA (peur des armes comprise), réglage global, par IA et par groupe.
        class IaRpEmotions {
            displayName = "$STR_COMSPEC_IARP_ManE_T";
            displayNameShort = "$STR_COMSPEC_IARP_ManE_T";
            description = "$STR_COMSPEC_IARP_ManE_D";
            tip = "$STR_COMSPEC_IARP_ManE_Tip";
            arguments[] = {};
            image = "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa";
            noImage = 0;
        };
    };
};

// --- Atelier IA : module Eden (catégorie « COMSPEC SSE » des modules de config, addon main), attribut « Profil IA RP »
// (unités et module). Les modules et le menu ZEN du Zeus : fn_iarpZen.

class CfgVehicles {
    class Logic;
    class Module_F: Logic {};
    class COMSPEC_SSE_Module_IarpWorkshop: Module_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 1;
        displayName = "$STR_COMSPEC_IARP_M_Name";
        category = "COMSPEC_Cat_Sse";
        function = "comspec_sse_fnc_iarpModule";
        functionPriority = 1;
        isGlobal = 0;
        isTriggerActivated = 0;
        isDisposable = 1;
        is3DEN = 0;
        icon = "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa";
        portrait = "\a3\ui_f\data\igui\cfg\simpleTasks\types\talk_ca.paa";
        class Attributes {
            class IarpProfile {
                displayName = "$STR_COMSPEC_IARP_M_Profile";
                tooltip = "$STR_COMSPEC_IARP_M_Profile_D";
                property = "COMSPEC_SSE_Module_IarpWorkshop_Profile";
                control = "COMSPEC_SSE_IarpProfileAttr";
                expression = "_this setVariable ['IarpProfile', _value];";
                defaultValue = "''";
                typeName = "STRING";
            };
            class IarpEach {
                displayName = "$STR_COMSPEC_IARP_M_Each";
                tooltip = "$STR_COMSPEC_IARP_M_Each_D";
                property = "COMSPEC_SSE_Module_IarpWorkshop_Each";
                control = "Checkbox";
                expression = "_this setVariable ['IarpEach', _value];";
                defaultValue = "false";
                typeName = "BOOL";
            };
        };
        class ModuleDescription {
            description = "$STR_COMSPEC_IARP_M_Desc";
            sync[] = {"AnyBrain"};
        };
    };
};

class ctrlStatic;
class ctrlStaticMulti;
class ctrlEdit;
class ctrlEditMulti;
class ctrlButton;
class Cfg3DEN {
    class Attributes {
        class Default;
        class Title: Default { class Controls { class Title; }; };
        // Résumé du profil, « Question » et « Réponse de l'IA » (la 1re question perso du profil, fn_iarpAnswers
        // « qaSet »), puis le bouton « Atelier IA… » ; valeur (profil en texte) gardée sur le groupe (fn_iarpEden).
        class COMSPEC_SSE_IarpProfileAttr: Title {
            attributeLoad = "['load', [_this, _value]] call comspec_sse_fnc_iarpEden";
            attributeSave = "['save', _this] call comspec_sse_fnc_iarpEden";
            h = "45 * (pixelH * pixelGrid * 0.5)";
            class Controls: Controls {
                class Title: Title {};
                class Summary: ctrlStatic {
                    idc = 101;
                    x = "48 * (pixelW * pixelGrid * 0.5)";
                    y = "0";
                    w = "82 * (pixelW * pixelGrid * 0.5)";
                    h = "5 * (pixelH * pixelGrid * 0.5)";
                    colorBackground[] = {0, 0, 0, 0.25};
                };
                class LabelQ: ctrlStatic {
                    idc = 110;
                    style = 1;
                    text = "$STR_COMSPEC_IARP_W_Question";
                    tooltip = "$STR_COMSPEC_IARP_W_Question_D";
                    x = "0";
                    y = "6 * (pixelH * pixelGrid * 0.5)";
                    w = "47 * (pixelW * pixelGrid * 0.5)";
                    h = "5 * (pixelH * pixelGrid * 0.5)";
                };
                class QaQ: ctrlEdit {
                    idc = 103;
                    tooltip = "$STR_COMSPEC_IARP_W_Question_D";
                    maxChars = 80;
                    x = "48 * (pixelW * pixelGrid * 0.5)";
                    y = "6 * (pixelH * pixelGrid * 0.5)";
                    w = "82 * (pixelW * pixelGrid * 0.5)";
                    h = "5 * (pixelH * pixelGrid * 0.5)";
                    onKeyUp = "['hint', [ctrlParentControlsGroup (_this select 0)]] call comspec_sse_fnc_iarpEden";
                };
                class HintQ: ctrlStaticMulti {
                    idc = 104;
                    style = 16;
                    text = "$STR_COMSPEC_IARP_W_QuestionHint";
                    tooltip = "$STR_COMSPEC_IARP_W_Question_D";
                    x = "48 * (pixelW * pixelGrid * 0.5)";
                    y = "11 * (pixelH * pixelGrid * 0.5)";
                    w = "82 * (pixelW * pixelGrid * 0.5)";
                    h = "7 * (pixelH * pixelGrid * 0.5)";
                    sizeEx = "3.2 * (pixelH * pixelGrid * 0.5)";
                    colorText[] = {1, 1, 1, 0.6};
                };
                class LabelA: ctrlStatic {
                    idc = 111;
                    style = 1;
                    text = "$STR_COMSPEC_IARP_W_Reply";
                    tooltip = "$STR_COMSPEC_IARP_W_Reply_D";
                    x = "0";
                    y = "19 * (pixelH * pixelGrid * 0.5)";
                    w = "47 * (pixelW * pixelGrid * 0.5)";
                    h = "5 * (pixelH * pixelGrid * 0.5)";
                };
                class QaA: ctrlEditMulti {
                    idc = 105;
                    tooltip = "$STR_COMSPEC_IARP_W_Reply_D";
                    maxChars = 2400;
                    x = "48 * (pixelW * pixelGrid * 0.5)";
                    y = "19 * (pixelH * pixelGrid * 0.5)";
                    w = "82 * (pixelW * pixelGrid * 0.5)";
                    h = "12 * (pixelH * pixelGrid * 0.5)";
                    onKeyUp = "['hint', [ctrlParentControlsGroup (_this select 0)]] call comspec_sse_fnc_iarpEden";
                };
                class HintA: ctrlStaticMulti {
                    idc = 106;
                    style = 16;
                    text = "$STR_COMSPEC_IARP_W_ReplyHint";
                    tooltip = "$STR_COMSPEC_IARP_W_Reply_D";
                    x = "48 * (pixelW * pixelGrid * 0.5)";
                    y = "31 * (pixelH * pixelGrid * 0.5)";
                    w = "82 * (pixelW * pixelGrid * 0.5)";
                    h = "7 * (pixelH * pixelGrid * 0.5)";
                    sizeEx = "3.2 * (pixelH * pixelGrid * 0.5)";
                    colorText[] = {1, 1, 1, 0.6};
                };
                class Open: ctrlButton {
                    idc = 102;
                    text = "$STR_COMSPEC_IARP_W_Open";
                    tooltip = "$STR_COMSPEC_IARP_W_Attr_D";
                    x = "48 * (pixelW * pixelGrid * 0.5)";
                    y = "39 * (pixelH * pixelGrid * 0.5)";
                    w = "82 * (pixelW * pixelGrid * 0.5)";
                    h = "5 * (pixelH * pixelGrid * 0.5)";
                    onButtonClick = "['open', [ctrlParentControlsGroup (_this select 0)]] call comspec_sse_fnc_iarpEden";
                };
            };
        };
    };
    class Object {
        class AttributeCategories {
            class COMSPEC_SSE_IaRp {
                displayName = "$STR_COMSPEC_IARP_A_Cat";
                collapsed = 1;
                class Attributes {
                    // Profil appliqué sur le serveur seulement : secrets et renseignement ne sont jamais diffusés.
                    class comspec_sse_iarpProfile {
                        displayName = "$STR_COMSPEC_IARP_W_Attr";
                        tooltip = "$STR_COMSPEC_IARP_W_Attr_D";
                        property = "comspec_sse_iarpProfile";
                        control = "COMSPEC_SSE_IarpProfileAttr";
                        expression = "if (isServer && {_value isNotEqualTo ''}) then { ['applyRaw', [_this, _value]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "''";
                        typeName = "STRING";
                        condition = "objectBrain";
                    };
                    // Émotions de cette IA (fn_iarpProfile « edenEmo », serveur) : préréglage, une case par émotion, tout son
                    // groupe ; composées à l'image suivante dans son profil (champs « emotionsOn », « emotions »).
                    class comspec_sse_iarpEmoMode {
                        displayName = "$STR_COMSPEC_IARP_Z_EmoMode";
                        tooltip = "$STR_COMSPEC_IARP_E_EmoMode_D";
                        property = "comspec_sse_iarpEmoMode";
                        control = "Combo";
                        expression = "if (isServer && {_value isNotEqualTo 'auto'}) then { ['edenEmo', [_this, 'mode', _value]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "'auto'";
                        typeName = "STRING";
                        condition = "objectBrain";
                        class values {
                            class Auto { name = "$STR_COMSPEC_IARP_Z_EmoMode_auto"; value = "auto"; default = 1; };
                            class Custom { name = "$STR_COMSPEC_IARP_Z_EmoMode_custom"; value = "custom"; };
                            class Normal { name = "$STR_COMSPEC_IARP_Z_EmoMode_normal"; value = "normal"; };
                            class Impassible { name = "$STR_COMSPEC_IARP_Z_EmoMode_impassible"; value = "impassible"; };
                            class Emotif { name = "$STR_COMSPEC_IARP_Z_EmoMode_emotif"; value = "emotif"; };
                            class Neutre { name = "$STR_COMSPEC_IARP_Z_EmoMode_neutre"; value = "neutre"; };
                        };
                    };
                    class comspec_sse_iarpEmo_peur {
                        displayName = "$STR_COMSPEC_IARP_S_Emo_peur_T";
                        tooltip = "$STR_COMSPEC_IARP_S_Emo_peur_D";
                        property = "comspec_sse_iarpEmo_peur";
                        control = "Checkbox";
                        expression = "if (isServer && {!_value}) then { ['edenEmo', [_this, 'peur', false]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "true";
                        typeName = "BOOL";
                        condition = "objectBrain";
                    };
                    class comspec_sse_iarpEmo_armes {
                        displayName = "$STR_COMSPEC_IARP_S_Emo_armes_T";
                        tooltip = "$STR_COMSPEC_IARP_S_Emo_armes_D";
                        property = "comspec_sse_iarpEmo_armes";
                        control = "Checkbox";
                        expression = "if (isServer && {!_value}) then { ['edenEmo', [_this, 'armes', false]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "true";
                        typeName = "BOOL";
                        condition = "objectBrain";
                    };
                    class comspec_sse_iarpEmo_nervosite {
                        displayName = "$STR_COMSPEC_IARP_S_Emo_nervosite_T";
                        tooltip = "$STR_COMSPEC_IARP_S_Emo_nervosite_D";
                        property = "comspec_sse_iarpEmo_nervosite";
                        control = "Checkbox";
                        expression = "if (isServer && {!_value}) then { ['edenEmo', [_this, 'nervosite', false]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "true";
                        typeName = "BOOL";
                        condition = "objectBrain";
                    };
                    class comspec_sse_iarpEmo_colere {
                        displayName = "$STR_COMSPEC_IARP_S_Emo_colere_T";
                        tooltip = "$STR_COMSPEC_IARP_S_Emo_colere_D";
                        property = "comspec_sse_iarpEmo_colere";
                        control = "Checkbox";
                        expression = "if (isServer && {!_value}) then { ['edenEmo', [_this, 'colere', false]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "true";
                        typeName = "BOOL";
                        condition = "objectBrain";
                    };
                    class comspec_sse_iarpEmo_mefiance {
                        displayName = "$STR_COMSPEC_IARP_S_Emo_mefiance_T";
                        tooltip = "$STR_COMSPEC_IARP_S_Emo_mefiance_D";
                        property = "comspec_sse_iarpEmo_mefiance";
                        control = "Checkbox";
                        expression = "if (isServer && {!_value}) then { ['edenEmo', [_this, 'mefiance', false]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "true";
                        typeName = "BOOL";
                        condition = "objectBrain";
                    };
                    class comspec_sse_iarpEmo_soulagement {
                        displayName = "$STR_COMSPEC_IARP_S_Emo_soulagement_T";
                        tooltip = "$STR_COMSPEC_IARP_S_Emo_soulagement_D";
                        property = "comspec_sse_iarpEmo_soulagement";
                        control = "Checkbox";
                        expression = "if (isServer && {!_value}) then { ['edenEmo', [_this, 'soulagement', false]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "true";
                        typeName = "BOOL";
                        condition = "objectBrain";
                    };
                    class comspec_sse_iarpEmoGroup {
                        displayName = "$STR_COMSPEC_IARP_Z_EmoGroup";
                        tooltip = "$STR_COMSPEC_IARP_E_EmoGroup_D";
                        property = "comspec_sse_iarpEmoGroup";
                        control = "Checkbox";
                        expression = "if (isServer && {_value}) then { ['edenEmo', [_this, 'group', true]] call comspec_sse_fnc_iarpProfile; };";
                        defaultValue = "false";
                        typeName = "BOOL";
                        condition = "objectBrain";
                    };
                    // Agressivité de cette IA envers les joueurs (fn_iarpAggro) : niveau, trait de caractère, intensité des
                    // dégâts, mortel. « Réglage de la mission » : le réglage CBA décide. Variables d'unité diffusées.
                    class comspec_sse_iarpAggroMode {
                        displayName = "$STR_COMSPEC_IARP_Z_AggroMode";
                        tooltip = "$STR_COMSPEC_IARP_E_AggroMode_D";
                        property = "comspec_sse_iarpAggroMode";
                        control = "Combo";
                        expression = "if (isServer && {_value isNotEqualTo 'auto'}) then { _this setVariable ['comspec_sse_aggroMode', _value, true]; };";
                        defaultValue = "'auto'";
                        typeName = "STRING";
                        condition = "objectBrain";
                        class values {
                            class Auto { name = "$STR_COMSPEC_IARP_Z_Aggro_auto"; value = "auto"; default = 1; };
                            class Off { name = "$STR_COMSPEC_IARP_S_Aggro_off"; value = "off"; };
                            class Verbal { name = "$STR_COMSPEC_IARP_S_Aggro_verbal"; value = "verbal"; };
                            class Physical { name = "$STR_COMSPEC_IARP_S_Aggro_physical"; value = "physical"; };
                        };
                    };
                    class comspec_sse_iarpAggroTrait {
                        displayName = "$STR_COMSPEC_IARP_Z_AggroTrait";
                        tooltip = "$STR_COMSPEC_IARP_E_AggroTrait_D";
                        property = "comspec_sse_iarpAggroTrait";
                        control = "Combo";
                        expression = "if (isServer && {_value isNotEqualTo 'auto'}) then { _this setVariable ['comspec_sse_aggroTrait', _value, true]; };";
                        defaultValue = "'auto'";
                        typeName = "STRING";
                        condition = "objectBrain";
                        class values {
                            class Auto { name = "$STR_COMSPEC_IARP_Z_AggroTrait_auto"; value = "auto"; default = 1; };
                            class None { name = "$STR_COMSPEC_IARP_Z_AggroTrait_none"; value = "none"; };
                            class Hostile { name = "$STR_COMSPEC_IARP_Z_AggroTrait_hostile"; value = "hostile"; };
                            class Irritable { name = "$STR_COMSPEC_IARP_Z_AggroTrait_irritable"; value = "irritable"; };
                            class Drunk { name = "$STR_COMSPEC_IARP_Z_AggroTrait_drunk"; value = "drunk"; };
                            class Fanatic { name = "$STR_COMSPEC_IARP_Z_AggroTrait_fanatic"; value = "fanatic"; };
                            class Resentful { name = "$STR_COMSPEC_IARP_Z_AggroTrait_resentful"; value = "resentful"; };
                            class Proud { name = "$STR_COMSPEC_IARP_Z_AggroTrait_proud"; value = "proud"; };
                            class Bluff { name = "$STR_COMSPEC_IARP_Z_AggroTrait_bluff"; value = "bluff"; };
                        };
                    };
                    class comspec_sse_iarpAggroDmg {
                        displayName = "$STR_COMSPEC_IARP_Z_AggroDmg";
                        tooltip = "$STR_COMSPEC_IARP_E_AggroDmg_D";
                        property = "comspec_sse_iarpAggroDmg";
                        control = "Combo";
                        expression = "if (isServer && {_value >= 0}) then { _this setVariable ['comspec_sse_aggroDmg', _value, true]; };";
                        defaultValue = "-1";
                        typeName = "NUMBER";
                        condition = "objectBrain";
                        class values {
                            class Auto { name = "$STR_COMSPEC_IARP_Z_Aggro_auto"; value = -1; default = 1; };
                            class D0 { name = "$STR_COMSPEC_IARP_Z_AggroDmg_0"; value = 0; };
                            class D25 { name = "$STR_COMSPEC_IARP_Z_AggroDmg_25"; value = 25; };
                            class D50 { name = "$STR_COMSPEC_IARP_Z_AggroDmg_50"; value = 50; };
                            class D75 { name = "$STR_COMSPEC_IARP_Z_AggroDmg_75"; value = 75; };
                            class D100 { name = "$STR_COMSPEC_IARP_Z_AggroDmg_100"; value = 100; };
                        };
                    };
                    class comspec_sse_iarpAggroLethal {
                        displayName = "$STR_COMSPEC_IARP_Z_AggroLethal";
                        tooltip = "$STR_COMSPEC_IARP_E_AggroLethal_D";
                        property = "comspec_sse_iarpAggroLethal";
                        control = "Combo";
                        expression = "if (isServer && {_value >= 0}) then { _this setVariable ['comspec_sse_aggroLethal', _value, true]; };";
                        defaultValue = "-1";
                        typeName = "NUMBER";
                        condition = "objectBrain";
                        class values {
                            class Auto { name = "$STR_COMSPEC_IARP_Z_Aggro_auto"; value = -1; default = 1; };
                            class No { name = "$STR_COMSPEC_IARP_Z_Aggro_no"; value = 0; };
                            class Yes { name = "$STR_COMSPEC_IARP_Z_Aggro_yes"; value = 1; };
                        };
                    };
                };
            };
        };
    };
};

class COMSPEC_SSE_RscBackground;
class COMSPEC_SSE_RscPanel;
class COMSPEC_SSE_RscCombo;
class COMSPEC_SSE_RscCheckBox;
class COMSPEC_SSE_RscSlider;
class COMSPEC_SSE_RscButtonZeus;
class COMSPEC_SSE_RscHeader;
class COMSPEC_SSE_RscHeaderZeus;
class COMSPEC_SSE_RscAccentLine;
class COMSPEC_SSE_RscAccentLineZeus;
class COMSPEC_SSE_RscText;
class COMSPEC_SSE_RscLabel;
class COMSPEC_SSE_RscButton;
class COMSPEC_SSE_RscButtonNav;
class COMSPEC_SSE_RscButtonClose;
class COMSPEC_SSE_RscEdit;
class COMSPEC_SSE_RscStructuredText;
class COMSPEC_SSE_RscControlsGroup;
class COMSPEC_SSE_RscListBox;
class RscToolbox;

#include "dialogs\iarp_terminal.hpp"
#include "dialogs\iarp_workshop.hpp"
#include "dialogs\iarp_answers.hpp"
#include "dialogs\iarp_hub.hpp"
