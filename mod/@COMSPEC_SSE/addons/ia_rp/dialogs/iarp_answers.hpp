/*
    Réponses de l'IA (fn_iarpAnswersUi) : ce que le maître du jeu fait dire à une IA ou à un lot d'IA, question par question,
    et ce qu'elle peut évoquer. Même fenêtre en Eden (atelier IA de l'attribut d'une unité) et en Zeus (atelier IA, menu ZEN
    « SSE · Roleplay de l'IA », fenêtre « IA de la zone »). Positions sur la grille 40 x 25 de la charte SSE.
*/
#define AN_POS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_IarpAnswers {
    idd = 94800;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['onLoad', [_this select 0]] call comspec_sse_fnc_iarpAnswersUi";
    onUnload = "['onUnload'] call comspec_sse_fnc_iarpAnswersUi";

    class controlsBackground {
        class Back: COMSPEC_SSE_RscBackground { AN_POS(1,0.5,38,24.2); };
        class Head: COMSPEC_SSE_RscHeader { AN_POS(1,0.5,38,1.4); };
        class Line: COMSPEC_SSE_RscAccentLine { AN_POS(1,1.9,38,0.06); };
        class Split: COMSPEC_SSE_RscPanel { AN_POS(1.3,4.75,37.4,0.06); };
        class SplitV: COMSPEC_SSE_RscPanel { AN_POS(15.5,5.0,0.06,19.3); };
        class PrevBack: COMSPEC_SSE_RscPanel { AN_POS(15.8,16.85,22.9,2.3); };
    };
    class controls {
        class Title: COMSPEC_SSE_RscText { idc = 94801; font = SSE_FONT_TITLE; colorText[] = SSE_C_ACCENT; AN_POS(1.3,0.65,24,1.1); };
        class Status: COMSPEC_SSE_RscLabel { idc = 94802; style = 1; AN_POS(25.5,0.75,13.2,0.9); };
        // Cadre : ce qu'elle peut évoquer, thèmes interdits.
        class LEvoke: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Evoke"; tooltip = "$STR_COMSPEC_IARP_A_Evoke_D"; AN_POS(1.3,2.25,7.5,0.9); };
        class Evoke: COMSPEC_SSE_RscCombo { idc = 94810; tooltip = "$STR_COMSPEC_IARP_A_Evoke_D"; onLBSelChanged = "['frame'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Evoke']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(8.9,2.2,8,1); };
        class EvokeHelp: COMSPEC_SSE_RscStructuredText { idc = 94818; AN_POS(17.1,2.12,21.6,1.25); size = SSE_Q(SSE_GH * 0.72); };
        class LBanned: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Banned"; tooltip = "$STR_COMSPEC_IARP_A_Banned_D"; AN_POS(1.3,3.55,7.5,0.9); };
        class Ban1: COMSPEC_SSE_RscCheckBox { idc = 94811; tooltip = "$STR_COMSPEC_IARP_A_Ban1_D"; onCheckedChanged = "['frame'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(8.9,3.55,0.9,0.9); };
        class BanL1: COMSPEC_SSE_RscLabel { idc = 94861; text = "$STR_COMSPEC_IARP_A_Ban1"; tooltip = "$STR_COMSPEC_IARP_A_Ban1_D"; AN_POS(9.85,3.55,3.95,0.9); };
        class Ban2: COMSPEC_SSE_RscCheckBox { idc = 94812; tooltip = "$STR_COMSPEC_IARP_A_Ban2_D"; onCheckedChanged = "['frame'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(13.85,3.55,0.9,0.9); };
        class BanL2: COMSPEC_SSE_RscLabel { idc = 94862; text = "$STR_COMSPEC_IARP_A_Ban2"; tooltip = "$STR_COMSPEC_IARP_A_Ban2_D"; AN_POS(14.8,3.55,3.95,0.9); };
        class Ban3: COMSPEC_SSE_RscCheckBox { idc = 94813; tooltip = "$STR_COMSPEC_IARP_A_Ban3_D"; onCheckedChanged = "['frame'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(18.8,3.55,0.9,0.9); };
        class BanL3: COMSPEC_SSE_RscLabel { idc = 94863; text = "$STR_COMSPEC_IARP_A_Ban3"; tooltip = "$STR_COMSPEC_IARP_A_Ban3_D"; AN_POS(19.75,3.55,3.95,0.9); };
        class Ban4: COMSPEC_SSE_RscCheckBox { idc = 94814; tooltip = "$STR_COMSPEC_IARP_A_Ban4_D"; onCheckedChanged = "['frame'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(23.75,3.55,0.9,0.9); };
        class BanL4: COMSPEC_SSE_RscLabel { idc = 94864; text = "$STR_COMSPEC_IARP_A_Ban4"; tooltip = "$STR_COMSPEC_IARP_A_Ban4_D"; AN_POS(24.7,3.55,3.95,0.9); };
        class Ban5: COMSPEC_SSE_RscCheckBox { idc = 94815; tooltip = "$STR_COMSPEC_IARP_A_Ban5_D"; onCheckedChanged = "['frame'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(28.7,3.55,0.9,0.9); };
        class BanL5: COMSPEC_SSE_RscLabel { idc = 94865; text = "$STR_COMSPEC_IARP_A_Ban5"; tooltip = "$STR_COMSPEC_IARP_A_Ban5_D"; AN_POS(29.65,3.55,3.95,0.9); };
        class Ban6: COMSPEC_SSE_RscCheckBox { idc = 94816; tooltip = "$STR_COMSPEC_IARP_A_Ban6_D"; onCheckedChanged = "['frame'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(33.65,3.55,0.9,0.9); };
        class BanL6: COMSPEC_SSE_RscLabel { idc = 94866; text = "$STR_COMSPEC_IARP_A_Ban6"; tooltip = "$STR_COMSPEC_IARP_A_Ban6_D"; AN_POS(34.6,3.55,3.95,0.9); };
        // Questions : thèmes du guide (même source que l'onglet GUIDE des joueurs), recherche, état de chacune.
        class LSearch: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Search"; AN_POS(1.3,5.05,3,0.9); };
        class Search: COMSPEC_SSE_RscEdit { idc = 94820; tooltip = "$STR_COMSPEC_IARP_A_Search_D"; onKeyUp = "['list'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(4.4,5.0,10.9,1); autocomplete = ""; };
        class List: COMSPEC_SSE_RscListBox { idc = 94821; onLBSelChanged = "['pick'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(1.3,6.15,14,16.85); };
        class AddCustom: COMSPEC_SSE_RscButtonNav { idc = 94822; text = "$STR_COMSPEC_IARP_A_Add"; tooltip = "$STR_COMSPEC_IARP_A_Add_D"; action = "['add'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Custom']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(1.3,23.2,14,1.1); };
        // Réponse à la question choisie.
        class QTitle: COMSPEC_SSE_RscStructuredText { idc = 94830; AN_POS(15.8,5.0,22.9,1.95); };
        class LMode: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Mode"; AN_POS(15.8,7.05,4,0.9); };
        class Mode: COMSPEC_SSE_RscCombo { idc = 94831; tooltip = "$STR_COMSPEC_IARP_A_Mode_D"; onLBSelChanged = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Mode']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(19.9,7.0,7.1,1); };
        class LOrder: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Order"; AN_POS(27.2,7.05,4,0.9); };
        class Order: COMSPEC_SSE_RscCombo { idc = 94832; tooltip = "$STR_COMSPEC_IARP_A_Order_D"; onLBSelChanged = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Order']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(31.3,7.0,7.4,1); };
        class LLabel: COMSPEC_SSE_RscLabel { idc = 94843; text = "$STR_COMSPEC_IARP_A_Label"; AN_POS(15.8,8.25,4,0.9); };
        class Label: COMSPEC_SSE_RscEdit { idc = 94833; tooltip = "$STR_COMSPEC_IARP_A_Label_D"; onKeyUp = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Custom']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(19.9,8.2,18.8,1); autocomplete = ""; maxChars = 80; };
        class LTrig: COMSPEC_SSE_RscLabel { idc = 94844; text = "$STR_COMSPEC_IARP_A_Trig"; AN_POS(15.8,9.45,4,0.9); };
        class Trig: COMSPEC_SSE_RscEdit { idc = 94834; tooltip = "$STR_COMSPEC_IARP_A_Trig_D"; onKeyUp = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Trig']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(19.9,9.4,18.8,1); autocomplete = ""; maxChars = 700; };
        class AutoNote: COMSPEC_SSE_RscStructuredText { idc = 94845; AN_POS(15.8,8.2,22.9,2.2); size = SSE_Q(SSE_GH * 0.74); };
        class LSit: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Sit"; AN_POS(15.8,10.65,4,0.9); };
        class Sit: COMSPEC_SSE_RscCombo { idc = 94835; tooltip = "$STR_COMSPEC_IARP_A_Sit_D"; onLBSelChanged = "['sit'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Sit']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(19.9,10.6,18.8,1); };
        class LTexts: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Texts"; AN_POS(15.8,11.85,4,0.9); };
        class Texts: COMSPEC_SSE_RscEdit { idc = 94836; style = 16; tooltip = "$STR_COMSPEC_IARP_A_Texts_D"; onKeyUp = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Texts']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(19.9,11.8,18.8,3.6); autocomplete = ""; maxChars = 2600; };
        class LTier: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Tier"; AN_POS(15.8,15.65,4,0.9); };
        class Tier: COMSPEC_SSE_RscCombo { idc = 94837; tooltip = "$STR_COMSPEC_IARP_A_Tier_D"; onLBSelChanged = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Tier']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(19.9,15.6,7.1,1); };
        class LPlace: COMSPEC_SSE_RscLabel { text = "$STR_COMSPEC_IARP_A_Place"; AN_POS(27.2,15.65,4,0.9); };
        class Place: COMSPEC_SSE_RscEdit { idc = 94838; tooltip = "$STR_COMSPEC_IARP_A_Place_D"; onKeyUp = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Place']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(31.3,15.6,7.4,1); autocomplete = ""; maxChars = 120; };
        class Preview: COMSPEC_SSE_RscStructuredText { idc = 94839; AN_POS(15.9,16.9,22.7,2.2); size = SSE_Q(SSE_GH * 0.8); };
        class Help: COMSPEC_SSE_RscStructuredText { idc = 94840; AN_POS(15.8,19.3,22.9,3.75); size = SSE_Q(SSE_GH * 0.74); };
        class Remove: COMSPEC_SSE_RscButtonNav { idc = 94842; text = "$STR_COMSPEC_IARP_A_Remove"; tooltip = "$STR_COMSPEC_IARP_A_Remove_D"; action = "['remove'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(15.8,23.2,6.5,1.1); };
        class Group: COMSPEC_SSE_RscCheckBox { idc = 94851; tooltip = "$STR_COMSPEC_IARP_A_Group_D"; onMouseEnter = "['help', ['Group']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(22.5,23.3,0.9,0.9); };
        class GroupL: COMSPEC_SSE_RscLabel { idc = 94852; text = "$STR_COMSPEC_IARP_A_Group"; tooltip = "$STR_COMSPEC_IARP_A_Group_D"; AN_POS(23.45,23.3,5.5,0.9); };
        class Apply: COMSPEC_SSE_RscButton { idc = 94850; text = "$STR_COMSPEC_IARP_A_Apply"; tooltip = "$STR_COMSPEC_IARP_A_Apply_D"; action = "['apply'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Apply']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(29.1,23.2,5.1,1.1); };
        class Close: COMSPEC_SSE_RscButtonClose { idc = 94853; text = "$STR_COMSPEC_IARP_W_CloseBtn"; tooltip = "$STR_COMSPEC_IARP_A_Close_D"; action = "(ctrlParent (_this select 0)) closeDisplay 2"; AN_POS(34.4,23.2,4.3,1.1); };
    };
};
