/*
    Terminal « Parler à… » (fn_iarpUi) : historique de l'échange, saisie multi-ligne, commandes RP colorées, journal.
    Grille 40 x 25 de la charte SSE (ui_macros.hpp), contrôles COMSPEC_SSE_Rsc* de l'addon ui. Aucun script par image.
*/
#define IARP_POS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))
#define IARP_REL(X,Y,W,H) x = SSE_Q(SSE_W(X)); y = SSE_Q(SSE_H(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_IarpTerminal {
    idd = 94600;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['onLoad', [_this select 0]] call comspec_sse_fnc_iarpUi";
    onUnload = "['onUnload'] call comspec_sse_fnc_iarpUi";

    class controlsBackground {
        class Back: COMSPEC_SSE_RscBackground { IARP_POS(9,3,22,19); };
        class Head: COMSPEC_SSE_RscHeader { IARP_POS(9,3,22,1.4); };
        class Line: COMSPEC_SSE_RscAccentLine { IARP_POS(9,4.4,22,0.06); };
    };
    class controls {
        class Title: COMSPEC_SSE_RscText { idc = 94601; font = SSE_FONT_TITLE; colorText[] = SSE_C_ACCENT; IARP_POS(9.3,3.15,12.8,1.1); };
        class TabTalk: COMSPEC_SSE_RscButtonNav { idc = 94603; text = "$STR_COMSPEC_IARP_TabTalk"; tooltip = "$STR_COMSPEC_IARP_TabTalk_D"; IARP_POS(22.2,3.2,4.1,1); action = "['tab', ['talk']] call comspec_sse_fnc_iarpUi"; };
        class TabLog: COMSPEC_SSE_RscButtonNav { idc = 94604; text = "$STR_COMSPEC_IARP_TabLog"; tooltip = "$STR_COMSPEC_IARP_TabLog_D"; IARP_POS(26.4,3.2,3.3,1); action = "['tab', ['log']] call comspec_sse_fnc_iarpUi"; };
        class Close: COMSPEC_SSE_RscButtonClose { idc = 94605; text = "X"; tooltip = "$STR_COMSPEC_IARP_Close_D"; IARP_POS(29.8,3.2,0.95,1); action = "closeDialog 2"; };
        class Status: COMSPEC_SSE_RscLabel { idc = 94602; IARP_POS(9.3,4.6,21.4,0.9); };

        class History: COMSPEC_SSE_RscControlsGroup {
            idc = 94610;
            IARP_POS(9.3,5.6,21.4,10.4);
            class controls {
                class Text: COMSPEC_SSE_RscStructuredText { idc = 94611; IARP_REL(0,0,21.2,10.4); colorBackground[] = SSE_C_NONE; };
            };
        };
        class PfxMe: COMSPEC_SSE_RscButtonNav { idc = 94621; text = "/me"; tooltip = "$STR_COMSPEC_IARP_PfxMe_D"; IARP_POS(9.3,16.2,2.6,1.05); action = "['prefix', ['me']] call comspec_sse_fnc_iarpUi"; };
        class PfxDo: COMSPEC_SSE_RscButtonNav { idc = 94622; text = "/do"; tooltip = "$STR_COMSPEC_IARP_PfxDo_D"; IARP_POS(12,16.2,2.6,1.05); action = "['prefix', ['do']] call comspec_sse_fnc_iarpUi"; };
        class PfxTry: COMSPEC_SSE_RscButtonNav { idc = 94623; text = "/try"; tooltip = "$STR_COMSPEC_IARP_PfxTry_D"; IARP_POS(14.7,16.2,2.6,1.05); action = "['prefix', ['try']] call comspec_sse_fnc_iarpUi"; };
        class PfxTodo: COMSPEC_SSE_RscButtonNav { idc = 94624; text = "/todo"; tooltip = "$STR_COMSPEC_IARP_PfxTodo_D"; IARP_POS(17.4,16.2,3,1.05); action = "['prefix', ['todo']] call comspec_sse_fnc_iarpUi"; };
        class Count: COMSPEC_SSE_RscLabel { idc = 94626; style = 1; IARP_POS(25.5,16.2,5.2,1.05); };
        class Input: COMSPEC_SSE_RscEdit {
            idc = 94620;
            style = 16;
            maxChars = 1000;
            autocomplete = "";
            lineSpacing = 1;
            tooltip = "$STR_COMSPEC_IARP_Input_D";
            IARP_POS(9.3,17.4,17.8,3.2);
            onKeyDown = "['key', _this] call comspec_sse_fnc_iarpUi";
            onKeyUp = "['keyUp'] call comspec_sse_fnc_iarpUi";
        };
        class Send: COMSPEC_SSE_RscButton { idc = 94625; text = "$STR_COMSPEC_IARP_Send"; tooltip = "$STR_COMSPEC_IARP_Send_D"; IARP_POS(27.3,17.4,3.4,3.2); action = "['send'] call comspec_sse_fnc_iarpUi"; };
        class Hint: COMSPEC_SSE_RscLabel { idc = 94627; text = "$STR_COMSPEC_IARP_Hint"; IARP_POS(9.3,20.8,21.4,0.9); sizeEx = SSE_Q(SSE_GH * 0.68); };

        class Log: COMSPEC_SSE_RscControlsGroup {
            idc = 94630;
            IARP_POS(9.3,5.6,21.4,13.8);
            class controls {
                class Text: COMSPEC_SSE_RscStructuredText { idc = 94631; IARP_REL(0,0,21.2,13.8); colorBackground[] = SSE_C_NONE; size = SSE_Q(SSE_GH * 0.74); };
            };
        };
        class Copy: COMSPEC_SSE_RscButton { idc = 94632; text = "$STR_COMSPEC_IARP_Copy"; tooltip = "$STR_COMSPEC_IARP_Copy_D"; IARP_POS(9.3,19.7,6.4,1.1); action = "['copy'] call comspec_sse_fnc_iarpUi"; };
        class Refresh: COMSPEC_SSE_RscButtonNav { idc = 94633; text = "$STR_COMSPEC_IARP_Refresh"; tooltip = "$STR_COMSPEC_IARP_Refresh_D"; IARP_POS(15.9,19.7,5,1.1); action = "['tab', ['log']] call comspec_sse_fnc_iarpUi"; };
    };
};
