// Visionneuse de résultat SSE — présentation « dossier / feuille ».
// Classes COMSPEC_SSE_Rsc* : dialogs\base.hpp. Chrome (bandeau, titres, pied,
// papier) : comspec_sse_fnc_getDocumentChrome / comspec_sse_fnc_applyPaperStyle.
// V0.8 : feuille centrée sur la grille safeZone (lisible quelle que soit la
// taille d'interface), polices Roboto / Purista. idc inchangés.

#define SSE_RPOS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_ResultDialog {
    idd = 93010;
    movingEnable = 1;
    enableSimulation = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_SSE_ResultDisplay', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_SSE_ResultDisplay', displayNull];";

    class controlsBackground {
        class Dim: COMSPEC_SSE_RscDim {
            SSE_FULLSCREEN;
        };
        class PaperShadow: COMSPEC_SSE_RscText {
            colorBackground[] = {0, 0, 0, 0.4};
            SSE_RPOS(10.8,1.85,19,20);
        };
        class Paper: COMSPEC_SSE_RscText {
            idc = 93019;
            colorBackground[] = {0.94, 0.91, 0.84, 0.98};
            SSE_RPOS(10.5,1.5,19,20);
        };
        // Taches / plis (masqués par défaut — activés selon paper_style)
        class StainA: COMSPEC_SSE_RscText {
            idc = 93024;
            colorBackground[] = {0.35, 0.22, 0.1, 0.18};
            show = 0;
            SSE_RPOS(11.5,6.5,3.7,2.8);
        };
        class StainB: COMSPEC_SSE_RscText {
            idc = 93025;
            colorBackground[] = {0.28, 0.18, 0.08, 0.16};
            show = 0;
            SSE_RPOS(23.5,15,4,3.2);
        };
        class FoldLine: COMSPEC_SSE_RscText {
            idc = 93026;
            colorBackground[] = {0.2, 0.16, 0.1, 0.22};
            show = 0;
            SSE_RPOS(12.5,11.5,15,0.12);
        };
        class ClassBand: COMSPEC_SSE_RscText {
            idc = 93021;
            colorBackground[] = {0.45, 0.08, 0.08, 0.92};
            SSE_RPOS(10.5,1.5,19,1.1);
        };
        class ClassLabel: COMSPEC_SSE_RscText {
            idc = 93016;
            text = "DIFFUSION RESTREINTE — EXPLOITATION TERRAIN";
            font = SSE_FONT_BOLD;
            sizeEx = SSE_TXT_S;
            colorText[] = {0.98, 0.92, 0.88, 1};
            style = 2;
            SSE_RPOS(10.9,1.55,18.2,1);
        };
        class HeaderBar: COMSPEC_SSE_RscText {
            idc = 93022;
            colorBackground[] = {0.88, 0.84, 0.74, 1};
            SSE_RPOS(10.5,2.6,19,2.3);
        };
        class Title: COMSPEC_SSE_RscText {
            idc = 93011;
            text = "DOSSIER SSE";
            font = SSE_FONT_TITLE;
            sizeEx = SSE_TXT_XL;
            colorText[] = {0.12, 0.1, 0.08, 1};
            SSE_RPOS(11.1,2.65,17.8,1.3);
        };
        class SubTitle: COMSPEC_SSE_RscText {
            idc = 93017;
            text = "Consultation documentaire";
            sizeEx = SSE_TXT_S;
            colorText[] = {0.35, 0.3, 0.22, 1};
            SSE_RPOS(11.1,3.9,17.8,0.9);
        };
        class Rule: COMSPEC_SSE_RscText {
            colorBackground[] = {0.35, 0.28, 0.18, 0.55};
            SSE_RPOS(11.1,4.95,17.8,0.08);
        };
        class Footer: COMSPEC_SSE_RscText {
            idc = 93023;
            colorBackground[] = {0.86, 0.82, 0.72, 1};
            SSE_RPOS(10.5,19,19,2.5);
        };
    };

    class controls {
        class Body: COMSPEC_SSE_RscStructuredText {
            idc = 93012;
            colorBackground[] = {0, 0, 0, 0};
            colorText[] = {0.12, 0.1, 0.08, 1};
            size = SSE_TXT_M;
            class Attributes {
                font = SSE_FONT;
                color = "#1F1A14";
                colorLink = "#5A3A10";
                align = "left";
                shadow = 0;
            };
            SSE_RPOS(11.1,5.25,17.8,13.5);
        };
        class Meta: COMSPEC_SSE_RscStructuredText {
            idc = 93018;
            colorBackground[] = {0, 0, 0, 0};
            size = SSE_TXT_S;
            class Attributes {
                font = SSE_FONT;
                color = "#3A3226";
                colorLink = "#5A3A10";
                align = "left";
                shadow = 0;
            };
            SSE_RPOS(11.1,19.15,17.8,2.2);
        };
        class BtnConsult: COMSPEC_SSE_RscButton {
            idc = 93013;
            text = "FEUILLE";
            action = "[] call comspec_sse_fnc_resultConsult;";
            colorBackground[] = {0.28, 0.22, 0.14, 0.95};
            colorBackgroundActive[] = {0.4, 0.32, 0.18, 1};
            colorFocused[] = {0.4, 0.32, 0.18, 1};
            colorText[] = {0.96, 0.93, 0.86, 1};
            SSE_RPOS(10.5,21.9,5.6,1.4);
        };
        class BtnTx: COMSPEC_SSE_RscButton {
            idc = 93014;
            text = "TRANSMETTRE";
            action = "[] call comspec_sse_fnc_resultTransmit;";
            SSE_RPOS(16.4,21.9,7.2,1.4);
        };
        class BtnClose: COMSPEC_SSE_RscButtonClose {
            idc = 93015;
            text = "FERMER";
            action = "closeDialog 0;";
            SSE_RPOS(23.9,21.9,5.6,1.4);
        };
    };
};
