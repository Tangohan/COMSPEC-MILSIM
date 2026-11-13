/*
    Terminal « Parler à… » (fn_iarpUi) : en-tête (avatar, nom de l'IA, état, distance), onglets DISCUSSION / GUIDE /
    JOURNAL, fil en bulles distinctes (moi à droite, l'IA à gauche, actions au centre), menu « Faire une action », saisie.
    Grille 40 x 25 de la charte SSE (ui_macros.hpp) : suit la taille d'interface et le format d'écran ; aucun script par
    image (bulles du fil créées à chaque nouveau message, fn_iarpUi « refresh »).
*/
#define IARP_POS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

// Classes créées par script (fil, guide) : texte de bulle, fond de bulle, exemple cliquable du guide.
class COMSPEC_SSE_IarpBubbleText: COMSPEC_SSE_RscStructuredText {
    colorBackground[] = SSE_C_NONE;
    size = SSE_TXT_M;
};
class COMSPEC_SSE_IarpBubbleBg: COMSPEC_SSE_RscText {
    colorBackground[] = SSE_C_PANEL_ALT;
};
class COMSPEC_SSE_IarpExample: COMSPEC_SSE_RscButtonNav {
    style = 0;
    sizeEx = SSE_TXT_S;
    colorBackground[] = {0.11,0.14,0.13,1};
    colorBackgroundActive[] = {0.17,0.32,0.21,1};
    colorFocused[] = {0.17,0.32,0.21,1};
};

class COMSPEC_SSE_IarpTerminal {
    idd = 94600;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['onLoad', [_this select 0]] call comspec_sse_fnc_iarpUi";
    onUnload = "['onUnload'] call comspec_sse_fnc_iarpUi";

    class controlsBackground {
        class Back: COMSPEC_SSE_RscBackground { IARP_POS(6,1.5,28,22); };
        class Head: COMSPEC_SSE_RscHeader { IARP_POS(6,1.5,28,3.2); };
        class Line: COMSPEC_SSE_RscAccentLine { IARP_POS(6,4.7,28,0.06); };
        class ThreadBack: COMSPEC_SSE_RscText { idc = 94612; colorBackground[] = {0.035,0.045,0.05,0.9}; IARP_POS(6.5,6.4,27,10.6); };
    };
    class controls {
        class AvatarBg: COMSPEC_SSE_RscText { idc = 94640; colorBackground[] = {0.15,0.3,0.19,1}; IARP_POS(6.5,1.95,2.9,2.3); };
        class AvatarTxt: COMSPEC_SSE_RscText { idc = 94641; style = 2; font = SSE_FONT_BOLD; sizeEx = SSE_Q(SSE_GH * 1.25); IARP_POS(6.5,1.95,2.9,2.3); };
        class Title: COMSPEC_SSE_RscText { idc = 94601; font = SSE_FONT_TITLE; sizeEx = SSE_Q(SSE_GH * 1.15); colorText[] = SSE_C_TEXT; IARP_POS(9.8,1.8,22.3,1.4); };
        class Status: COMSPEC_SSE_RscStructuredText { idc = 94602; colorBackground[] = SSE_C_NONE; size = SSE_Q(SSE_GH * 0.8); IARP_POS(9.8,3.2,22.3,1.2); };
        class Close: COMSPEC_SSE_RscButtonClose { idc = 94605; text = "X"; tooltip = "$STR_COMSPEC_IARP_Close_D"; IARP_POS(32.4,1.85,1.27,0.95); action = "closeDialog 2"; };

        class TabTalk: COMSPEC_SSE_RscButtonNav { idc = 94603; text = "$STR_COMSPEC_IARP_TabTalk"; tooltip = "$STR_COMSPEC_IARP_TabTalk_D"; IARP_POS(6.5,5.0,6.2,1.1); action = "['tab', ['talk']] call comspec_sse_fnc_iarpUi"; };
        class TabGuide: COMSPEC_SSE_RscButtonNav { idc = 94606; text = "$STR_COMSPEC_IARP_TabGuide"; tooltip = "$STR_COMSPEC_IARP_TabGuide_D"; IARP_POS(12.9,5.0,4.6,1.1); action = "['tab', ['guide']] call comspec_sse_fnc_iarpUi"; };
        class TabLog: COMSPEC_SSE_RscButtonNav { idc = 94604; text = "$STR_COMSPEC_IARP_TabLog"; tooltip = "$STR_COMSPEC_IARP_TabLog_D"; IARP_POS(17.7,5.0,5.2,1.1); action = "['tab', ['log']] call comspec_sse_fnc_iarpUi"; };

        // Fil : bulles créées par script dans ce groupe (défilement jusqu'au dernier message).
        class History: COMSPEC_SSE_RscControlsGroup { idc = 94610; IARP_POS(6.5,6.4,27,10.6); };

        // Guide : thèmes à gauche, explication et exemples cliquables à droite (tirés du lexique, fn_iarpData « guide »).
        class GuideThemes: COMSPEC_SSE_RscListBox {
            idc = 94660;
            sizeEx = SSE_TXT_S;
            rowHeight = SSE_Q(SSE_H(1.15));
            IARP_POS(6.5,6.4,8.4,10.6);
            onLBSelChanged = "['theme', [_this select 1]] call comspec_sse_fnc_iarpUi";
        };
        class GuideBody: COMSPEC_SSE_RscControlsGroup { idc = 94661; IARP_POS(15.2,6.4,18.3,10.6); };

        class ActionBtn: COMSPEC_SSE_RscButtonNav { idc = 94650; text = "$STR_COMSPEC_IARP_ActionBtn"; tooltip = "$STR_COMSPEC_IARP_ActionBtn_D"; IARP_POS(6.5,17.3,9.0,1.0); sizeEx = SSE_TXT_S; action = "['actions'] call comspec_sse_fnc_iarpUi"; };
        class Count: COMSPEC_SSE_RscLabel { idc = 94626; style = 1; IARP_POS(27.5,17.3,6,1.0); };
        class Input: COMSPEC_SSE_RscEdit {
            idc = 94620;
            style = 16;
            maxChars = 1000;
            autocomplete = "";
            lineSpacing = 1;
            tooltip = "$STR_COMSPEC_IARP_Input_D";
            IARP_POS(6.5,18.5,21.4,3.0);
            onKeyDown = "['key', _this] call comspec_sse_fnc_iarpUi";
            onKeyUp = "['keyUp'] call comspec_sse_fnc_iarpUi";
        };
        class Send: COMSPEC_SSE_RscButton { idc = 94625; text = "$STR_COMSPEC_IARP_Send"; tooltip = "$STR_COMSPEC_IARP_Send_D"; IARP_POS(28.1,18.5,5.4,3.0); action = "['send'] call comspec_sse_fnc_iarpUi"; };
        class Hint: COMSPEC_SSE_RscLabel { idc = 94627; text = "$STR_COMSPEC_IARP_Hint"; IARP_POS(6.5,21.75,27,0.9); sizeEx = SSE_Q(SSE_GH * 0.66); };

        class Log: COMSPEC_SSE_RscControlsGroup {
            idc = 94630;
            IARP_POS(6.5,6.4,27,14.2);
            class controls {
                class Text: COMSPEC_SSE_RscStructuredText { idc = 94631; x = 0; y = 0; w = SSE_Q(SSE_W(26.6)); h = SSE_Q(SSE_H(14.2)); colorBackground[] = SSE_C_NONE; size = SSE_Q(SSE_GH * 0.78); };
            };
        };
        class Copy: COMSPEC_SSE_RscButton { idc = 94632; text = "$STR_COMSPEC_IARP_Copy"; tooltip = "$STR_COMSPEC_IARP_Copy_D"; IARP_POS(6.5,21.0,6.4,1.1); action = "['copy'] call comspec_sse_fnc_iarpUi"; };
        class Refresh: COMSPEC_SSE_RscButtonNav { idc = 94633; text = "$STR_COMSPEC_IARP_Refresh"; tooltip = "$STR_COMSPEC_IARP_Refresh_D"; IARP_POS(13.1,21.0,5,1.1); action = "['tab', ['log']] call comspec_sse_fnc_iarpUi"; };
        class Filter: COMSPEC_SSE_RscButtonNav { idc = 94634; text = ""; tooltip = "$STR_COMSPEC_IARP_Filter_D"; IARP_POS(18.3,21.0,7.2,1.1); action = "['filter'] call comspec_sse_fnc_iarpUi"; };

        // Menu « Faire une action » : par-dessus le fil, juste au-dessus du bouton ; caché tant qu'on ne l'ouvre pas.
        class ActionList: COMSPEC_SSE_RscListBox {
            idc = 94651;
            sizeEx = SSE_TXT_S;
            rowHeight = SSE_Q(SSE_H(0.95));
            colorBackground[] = {0.07,0.09,0.09,0.99};
            IARP_POS(6.5,6.45,10.5,10.75);
            onLBSelChanged = "['actionPick', [_this select 1]] call comspec_sse_fnc_iarpUi";
        };
        // Mesure de la largeur d'un texte (bulles ajustées à leur contenu) : jamais visible.
        class Probe: COMSPEC_SSE_RscText { idc = 94699; IARP_POS(0,0,30,1); colorText[] = SSE_C_NONE; };
    };
};
