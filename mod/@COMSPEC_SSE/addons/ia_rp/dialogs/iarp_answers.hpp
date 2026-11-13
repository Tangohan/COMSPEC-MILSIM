/*
    Réponses de l'IA (fn_iarpAnswersUi) : ce que le maître du jeu fait dire à une IA ou à un lot d'IA, question par question,
    et ce qu'elle peut évoquer. Même fenêtre en Eden (atelier IA de l'attribut d'une unité) et en Zeus (atelier IA, menu ZEN
    « SSE · Roleplay de l'IA », fenêtre « IA de la zone »). Positions sur la grille 40 x 25 de la charte SSE.
    Mode simple par défaut ; « Options avancées » ouvre les thèmes interdits, les situations, l'ordre, le renseignement, le
    repère et les questions perso. Les positions ci-dessous sont celles du mode simple : fn_iarpAnswersUi (« layout ») replace
    les contrôles selon le mode, la question (perso ou non) et la réponse (automatique ou la mienne).
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
        class Split: COMSPEC_SSE_RscPanel { idc = 94803; AN_POS(1.3,3.45,37.4,0.06); };
        class SplitV: COMSPEC_SSE_RscPanel { idc = 94804; AN_POS(15.5,3.75,0.06,20.55); };
        class PrevBack: COMSPEC_SSE_RscPanel { idc = 94805; AN_POS(15.8,16.4,22.9,3.6); };
    };
    class controls {
        class Title: COMSPEC_SSE_RscText { idc = 94801; font = SSE_FONT_TITLE; colorText[] = SSE_C_ACCENT; AN_POS(1.3,0.65,37.4,1.1); };
        // Ce que cette IA peut évoquer (toujours visible) ; thèmes interdits (options avancées).
        class LEvoke: COMSPEC_SSE_RscLabel { idc = 94806; text = "$STR_COMSPEC_IARP_A_Evoke"; AN_POS(1.3,2.3,10.4,0.9); };
        class Evoke: COMSPEC_SSE_RscCombo { idc = 94810; tooltip = "$STR_COMSPEC_IARP_A_Evoke_D"; onLBSelChanged = "['frame', ['evoke']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(11.9,2.25,9,1); };
        class EvokeHelp: COMSPEC_SSE_RscText { idc = 94818; sizeEx = SSE_TXT_S; colorText[] = SSE_C_TEXT_MUTED; AN_POS(21.2,2.3,17.5,0.9); };
        class LBanned: COMSPEC_SSE_RscLabel { idc = 94807; text = "$STR_COMSPEC_IARP_A_Banned"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(1.3,3.5,6,0.9); };
        class Ban1: COMSPEC_SSE_RscCheckBox { idc = 94811; onCheckedChanged = "['frame'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(8,3.5,0.9,0.9); };
        class BanL1: COMSPEC_SSE_RscLabel { idc = 94861; text = "$STR_COMSPEC_IARP_A_Ban1"; onMouseEnter = "['help', ['Ban']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(9,3.5,3,0.9); };
        class Ban2: Ban1 { idc = 94812; };
        class BanL2: BanL1 { idc = 94862; text = "$STR_COMSPEC_IARP_A_Ban2"; };
        class Ban3: Ban1 { idc = 94813; };
        class BanL3: BanL1 { idc = 94863; text = "$STR_COMSPEC_IARP_A_Ban3"; };
        class Ban4: Ban1 { idc = 94814; };
        class BanL4: BanL1 { idc = 94864; text = "$STR_COMSPEC_IARP_A_Ban4"; };
        class Ban5: Ban1 { idc = 94815; };
        class BanL5: BanL1 { idc = 94865; text = "$STR_COMSPEC_IARP_A_Ban5"; };
        class Ban6: Ban1 { idc = 94816; };
        class BanL6: BanL1 { idc = 94866; text = "$STR_COMSPEC_IARP_A_Ban6"; };
        // Questions : thèmes du guide (même source que l'onglet GUIDE des joueurs), recherche.
        class LSearch: COMSPEC_SSE_RscLabel { idc = 94817; text = "$STR_COMSPEC_IARP_A_Search"; AN_POS(1.3,3.8,3.4,0.9); };
        class Search: COMSPEC_SSE_RscEdit { idc = 94820; tooltip = "$STR_COMSPEC_IARP_A_Search_D"; onKeyUp = "['list'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(4.8,3.75,10.5,1); autocomplete = ""; };
        class List: COMSPEC_SSE_RscListBox { idc = 94821; sizeEx = SSE_Q(SSE_GH * 0.84); onLBSelChanged = "['pick'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(1.3,4.9,14,19.4); };
        class AddCustom: COMSPEC_SSE_RscButtonNav { idc = 94822; text = "$STR_COMSPEC_IARP_A_Add"; action = "['add'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Custom']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(1.3,23.2,14,1.1); };
        // Réponse à la question choisie.
        class QTitle: COMSPEC_SSE_RscStructuredText { idc = 94830; colorBackground[] = SSE_C_NONE; AN_POS(15.8,3.75,22.9,1.9); };
        class Mode: RscToolbox {
            idc = 94831;
            type = 6;
            style = 2;
            rows = 1;
            columns = 2;
            strings[] = {"$STR_COMSPEC_IARP_A_Mode_auto", "$STR_COMSPEC_IARP_A_Mode_perso"};
            values[] = {0, 1};
            font = SSE_FONT_BOLD;
            sizeEx = SSE_TXT_M;
            shadow = 0;
            colorText[] = SSE_C_TEXT_MUTED;
            color[] = SSE_C_TEXT_MUTED;
            colorTextSelect[] = SSE_C_TEXT;
            colorSelect[] = SSE_C_TEXT;
            colorTextDisable[] = SSE_C_TEXT_MUTED;
            colorDisable[] = SSE_C_TEXT_MUTED;
            colorBackground[] = SSE_C_BTN2;
            colorSelectedBg[] = SSE_C_BTN;
            onToolBoxSelChanged = "['mode'] call comspec_sse_fnc_iarpAnswersUi";
            AN_POS(15.8,5.8,16,1.1);
        };
        // Question perso : son nom (pour le maître du jeu) et ses mots déclencheurs.
        class LLabel: COMSPEC_SSE_RscLabel { idc = 94843; text = "$STR_COMSPEC_IARP_A_Label"; AN_POS(15.8,5.8,6.6,1); };
        class Label: COMSPEC_SSE_RscEdit { idc = 94833; tooltip = "$STR_COMSPEC_IARP_A_Label_D"; onKeyUp = "['store'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(22.5,5.8,10.9,1); autocomplete = ""; maxChars = 80; };
        class Remove: COMSPEC_SSE_RscButtonNav { idc = 94842; text = "$STR_COMSPEC_IARP_A_RemoveCustom"; tooltip = "$STR_COMSPEC_IARP_A_RemoveCustom_D"; action = "['remove'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(33.6,5.8,5.1,1); };
        class LTrig: COMSPEC_SSE_RscLabel { idc = 94844; text = "$STR_COMSPEC_IARP_A_Trig"; AN_POS(15.8,6.95,6.6,1); };
        class Trig: COMSPEC_SSE_RscEdit { idc = 94834; tooltip = "$STR_COMSPEC_IARP_A_Trig_D"; onKeyUp = "['store'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(22.5,6.95,16.2,1); autocomplete = ""; maxChars = 700; };
        class AutoNote: COMSPEC_SSE_RscStructuredText { idc = 94845; colorBackground[] = SSE_C_NONE; size = SSE_TXT_S; AN_POS(15.8,7.1,22.9,2); };
        class LTexts: COMSPEC_SSE_RscLabel { idc = 94846; text = "$STR_COMSPEC_IARP_A_Texts"; AN_POS(15.8,7.15,15.2,0.9); };
        class VarBtn: COMSPEC_SSE_RscButtonNav { idc = 94847; text = "$STR_COMSPEC_IARP_A_Var"; tooltip = "$STR_COMSPEC_IARP_A_Var_D"; action = "['vars'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(31.2,7.1,7.5,1); };
        // Pas d'info-bulle sur le champ : elle cacherait le texte en cours d'écriture.
        class Texts: COMSPEC_SSE_RscEdit { idc = 94836; style = 16; onKeyUp = "['store'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(15.8,8.25,22.9,7); autocomplete = ""; maxChars = 2600; };
        class Example: COMSPEC_SSE_RscText { idc = 94848; text = "$STR_COMSPEC_IARP_A_TextsEx"; sizeEx = SSE_TXT_S; colorText[] = SSE_C_TEXT_MUTED; AN_POS(15.8,15.35,22.9,0.9); };
        class Preview: COMSPEC_SSE_RscStructuredText { idc = 94839; colorBackground[] = SSE_C_NONE; AN_POS(15.9,16.45,22.7,3.5); size = SSE_Q(SSE_GH * 0.8); };
        class Adv: COMSPEC_SSE_RscButtonNav { idc = 94870; text = "$STR_COMSPEC_IARP_A_Adv"; action = "['advanced'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(15.8,21.8,22.9,1.1); };
        // Options avancées de la question choisie.
        class LSit: COMSPEC_SSE_RscLabel { idc = 94871; text = "$STR_COMSPEC_IARP_A_Sit"; onMouseEnter = "['help', ['Sit']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(15.8,17,5.4,1); };
        class Sit: COMSPEC_SSE_RscCombo { idc = 94835; onLBSelChanged = "['sit'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Sit']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(21.3,17,7.6,1); };
        class LOrder: COMSPEC_SSE_RscLabel { idc = 94872; text = "$STR_COMSPEC_IARP_A_Order"; onMouseEnter = "['help', ['Order']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(29.2,17,3.6,1); };
        class Order: COMSPEC_SSE_RscCombo { idc = 94832; onLBSelChanged = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Order']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(32.9,17,5.8,1); };
        class LTier: COMSPEC_SSE_RscLabel { idc = 94873; text = "$STR_COMSPEC_IARP_A_Tier"; onMouseEnter = "['help', ['Tier']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(15.8,18.15,5.4,1); };
        class Tier: COMSPEC_SSE_RscCombo { idc = 94837; onLBSelChanged = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Tier']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(21.3,18.15,7.6,1); };
        class LPlace: COMSPEC_SSE_RscLabel { idc = 94874; text = "$STR_COMSPEC_IARP_A_Place"; onMouseEnter = "['help', ['Place']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(29.2,18.15,3.6,1); };
        class Place: COMSPEC_SSE_RscEdit { idc = 94838; onKeyUp = "['store'] call comspec_sse_fnc_iarpAnswersUi"; onMouseEnter = "['help', ['Place']] call comspec_sse_fnc_iarpAnswersUi"; onMouseExit = "['help', ['Adv']] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(32.9,18.15,5.8,1); autocomplete = ""; maxChars = 120; };
        class Help: COMSPEC_SSE_RscStructuredText { idc = 94840; colorBackground[] = SSE_C_NONE; AN_POS(15.8,19.4,22.9,1.9); size = SSE_TXT_S; };
        // Barre du bas.
        class Status: COMSPEC_SSE_RscLabel { idc = 94802; AN_POS(15.8,23.3,10.4,0.9); };
        class Group: COMSPEC_SSE_RscCheckBox { idc = 94851; tooltip = "$STR_COMSPEC_IARP_A_Group_D"; AN_POS(26.4,23.3,0.9,0.9); };
        class GroupL: COMSPEC_SSE_RscLabel { idc = 94852; text = "$STR_COMSPEC_IARP_A_Group"; tooltip = "$STR_COMSPEC_IARP_A_Group_D"; AN_POS(27.35,23.3,3.9,0.9); };
        class Apply: COMSPEC_SSE_RscButton { idc = 94850; text = "$STR_COMSPEC_IARP_A_Apply"; tooltip = "$STR_COMSPEC_IARP_A_Apply_D"; action = "['apply'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(31.4,23.2,4.2,1.1); };
        class Close: COMSPEC_SSE_RscButtonClose { idc = 94853; text = "$STR_COMSPEC_IARP_A_Close"; tooltip = "$STR_COMSPEC_IARP_A_Close_D"; action = "(ctrlParent (_this select 0)) closeDisplay 2"; AN_POS(35.8,23.2,2.9,1.1); };
        // Liste des variables (bouton « Insérer une variable »), au-dessus du reste.
        class VarBack: COMSPEC_SSE_RscPanel { idc = 94880; colorBackground[] = SSE_C_PANEL_ALT; AN_POS(24.2,8.15,14.5,8.6); };
        class VarList: COMSPEC_SSE_RscListBox { idc = 94881; sizeEx = SSE_TXT_S; rowHeight = SSE_Q(SSE_H(0.85)); onLBSelChanged = "['varPick'] call comspec_sse_fnc_iarpAnswersUi"; AN_POS(24.3,8.25,14.3,8.4); };
        // Mesure des lignes de la liste (même police) : jamais affiché.
        class Probe: COMSPEC_SSE_RscText { idc = 94899; sizeEx = SSE_Q(SSE_GH * 0.84); show = 0; AN_POS(0,0,1,1); };
    };
};
