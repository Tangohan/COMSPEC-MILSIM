// Visionneuse de résultat SSE — présentation « dossier / feuille ».
// RscText / RscButton / RscStructuredText : config.cpp du addon ui.

class COMSPEC_SSE_ResultDialog {
    idd = 93010;
    movingEnable = 1;
    enableSimulation = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_SSE_ResultDisplay', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_SSE_ResultDisplay', displayNull];";

    class controlsBackground {
        // Voile sombre (hors feuille)
        class Dim: RscText {
            idc = -1;
            x = safezoneXAbs;
            y = safezoneY;
            w = safezoneWAbs;
            h = safezoneH;
            colorBackground[] = {0.02, 0.03, 0.04, 0.55};
        };
        // Ombre de la feuille
        class PaperShadow: RscText {
            idc = -1;
            x = 0.275;
            y = 0.125;
            w = 0.46;
            h = 0.74;
            colorBackground[] = {0, 0, 0, 0.35};
        };
        // Feuille
        class Paper: RscText {
            idc = -1;
            x = 0.265;
            y = 0.11;
            w = 0.46;
            h = 0.74;
            colorBackground[] = {0.94, 0.91, 0.84, 0.98};
        };
        // Bandeau classification
        class ClassBand: RscText {
            idc = -1;
            x = 0.265;
            y = 0.11;
            w = 0.46;
            h = 0.028;
            colorBackground[] = {0.45, 0.08, 0.08, 0.92};
        };
        class ClassLabel: RscText {
            idc = 93016;
            text = "DIFFUSION RESTREINTE — EXPLOITATION TERRAIN";
            x = 0.275;
            y = 0.11;
            w = 0.44;
            h = 0.028;
            colorBackground[] = {0, 0, 0, 0};
            colorText[] = {0.98, 0.92, 0.88, 1};
            sizeEx = 0.028;
            style = 2;
        };
        // En-tête dossier
        class HeaderBar: RscText {
            idc = -1;
            x = 0.265;
            y = 0.138;
            w = 0.46;
            h = 0.055;
            colorBackground[] = {0.88, 0.84, 0.74, 1};
        };
        class Title: RscText {
            idc = 93011;
            text = "DOSSIER SSE";
            x = 0.28;
            y = 0.142;
            w = 0.43;
            h = 0.032;
            colorBackground[] = {0, 0, 0, 0};
            colorText[] = {0.12, 0.1, 0.08, 1};
            sizeEx = 0.038;
            style = 0;
        };
        class SubTitle: RscText {
            idc = 93017;
            text = "Consultation documentaire";
            x = 0.28;
            y = 0.17;
            w = 0.43;
            h = 0.022;
            colorBackground[] = {0, 0, 0, 0};
            colorText[] = {0.35, 0.3, 0.22, 1};
            sizeEx = 0.026;
        };
        // Filet horizontal
        class Rule: RscText {
            idc = -1;
            x = 0.28;
            y = 0.198;
            w = 0.43;
            h = 0.002;
            colorBackground[] = {0.35, 0.28, 0.18, 0.55};
        };
        // Pied de page
        class Footer: RscText {
            idc = -1;
            x = 0.265;
            y = 0.78;
            w = 0.46;
            h = 0.07;
            colorBackground[] = {0.86, 0.82, 0.72, 1};
        };
    };

    class controls {
        class Body: RscStructuredText {
            idc = 93012;
            x = 0.28;
            y = 0.21;
            w = 0.43;
            h = 0.55;
            colorBackground[] = {0, 0, 0, 0};
            colorText[] = {0.12, 0.1, 0.08, 1};
            size = 0.032;
        };
        class Meta: RscStructuredText {
            idc = 93018;
            x = 0.28;
            y = 0.785;
            w = 0.43;
            h = 0.055;
            colorBackground[] = {0, 0, 0, 0};
        };
        class BtnConsult: RscButton {
            idc = 93013;
            text = "FEUILLE";
            x = 0.28;
            y = 0.855;
            w = 0.12;
            h = 0.038;
            action = "[] call comspec_sse_fnc_resultConsult;";
            colorBackground[] = {0.28, 0.22, 0.14, 0.95};
            colorBackgroundActive[] = {0.4, 0.32, 0.18, 1};
            colorText[] = {0.96, 0.93, 0.86, 1};
            sizeEx = 0.028;
        };
        class BtnTx: RscButton {
            idc = 93014;
            text = "TRANSMETTRE";
            x = 0.41;
            y = 0.855;
            w = 0.15;
            h = 0.038;
            action = "[] call comspec_sse_fnc_resultTransmit;";
            colorBackground[] = {0.12, 0.32, 0.22, 0.95};
            colorBackgroundActive[] = {0.16, 0.42, 0.28, 1};
            colorText[] = {0.9, 0.98, 0.92, 1};
            sizeEx = 0.028;
        };
        class BtnClose: RscButton {
            idc = 93015;
            text = "FERMER";
            x = 0.575;
            y = 0.855;
            w = 0.12;
            h = 0.038;
            action = "closeDialog 0;";
            colorBackground[] = {0.22, 0.2, 0.18, 0.95};
            colorBackgroundActive[] = {0.32, 0.28, 0.24, 1};
            colorText[] = {0.95, 0.93, 0.88, 1};
            sizeEx = 0.028;
        };
    };
};
