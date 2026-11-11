/*
    Fenêtre « SSE · <nom de l'IA> » (fn_iarpZenHub) : ouverte par le bouton « SSE » de la fenêtre d'édition d'une unité
    de ZEN, par-dessus elle. Les positions et les entrées (gros boutons et une ligne d'explication) sont posées par
    fn_iarpZenHub (« onLoad ») sur la grille 40 x 25 de la charte SSE, selon les fenêtres disponibles pour cette IA.
*/
class COMSPEC_SSE_IarpHub {
    idd = 94900;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['onLoad', [_this select 0]] call comspec_sse_fnc_iarpZenHub";
    onUnload = "['onUnload'] call comspec_sse_fnc_iarpZenHub";

    class controlsBackground {
        class Back: COMSPEC_SSE_RscBackground { idc = 94902; x = SSE_Q(SSE_X(12)); y = SSE_Q(SSE_Y(4)); w = SSE_Q(SSE_W(16)); h = SSE_Q(SSE_H(17)); };
        class Head: COMSPEC_SSE_RscHeaderZeus { idc = 94903; x = SSE_Q(SSE_X(12)); y = SSE_Q(SSE_Y(4)); w = SSE_Q(SSE_W(16)); h = SSE_Q(SSE_H(1.4)); };
        class Line: COMSPEC_SSE_RscAccentLineZeus { idc = 94904; x = SSE_Q(SSE_X(12)); y = SSE_Q(SSE_Y(5.4)); w = SSE_Q(SSE_W(16)); h = SSE_Q(SSE_H(0.06)); };
    };
    class controls {
        class Title: COMSPEC_SSE_RscText { idc = 94901; font = SSE_FONT_TITLE; sizeEx = SSE_TXT_L; colorText[] = SSE_C_ZEUS; x = SSE_Q(SSE_X(12.3)); y = SSE_Q(SSE_Y(4.15)); w = SSE_Q(SSE_W(15.4)); h = SSE_Q(SSE_H(1.1)); };
        class Close: COMSPEC_SSE_RscButtonClose {
            idc = 94905;
            text = "$STR_COMSPEC_IARP_H_Close";
            tooltip = "$STR_COMSPEC_IARP_H_Close_D";
            action = "(ctrlParent (_this select 0)) closeDisplay 2";
            x = SSE_Q(SSE_X(23.7)); y = SSE_Q(SSE_Y(19.55)); w = SSE_Q(SSE_W(4)); h = SSE_Q(SSE_H(1.15));
        };
    };
};
