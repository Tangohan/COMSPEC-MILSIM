class RscText;
class RscButton;
class RscCombo;
class RscCheckbox;
class RscSlider;

class COMSPEC_SSE_GenerateDialog {
    idd = 93001;
    movingEnable = 0;
    enableSimulation = 1;

    class controlsBackground {
        class BG: RscText {
            idc = -1;
            x = 0.3;
            y = 0.2;
            w = 0.4;
            h = 0.58;
            colorBackground[] = {0.05, 0.08, 0.05, 0.95};
        };
        class Title: RscText {
            idc = -1;
            text = "COMSPEC SSE — GÉNÉRER PROFIL";
            x = 0.3;
            y = 0.2;
            w = 0.4;
            h = 0.04;
            colorBackground[] = {0.1, 0.25, 0.1, 1};
            colorText[] = {0.6, 1, 0.6, 1};
        };
    };

    class controls {
        class LblProfile: RscText {
            text = "Profil";
            x = 0.32; y = 0.26; w = 0.15; h = 0.03;
            colorText[] = {0.7, 1, 0.7, 1};
        };
        class Profile: RscCombo {
            idc = 93010;
            x = 0.48; y = 0.26; w = 0.2; h = 0.03;
        };
        class LblRich: RscText {
            text = "Richesse";
            x = 0.32; y = 0.31; w = 0.15; h = 0.03;
            colorText[] = {0.7, 1, 0.7, 1};
        };
        class Rich: RscCombo {
            idc = 93011;
            x = 0.48; y = 0.31; w = 0.2; h = 0.03;
        };
        class CbId: RscCheckbox {
            idc = 93012;
            x = 0.32; y = 0.37; w = 0.04; h = 0.04;
        };
        class LblId: RscText {
            text = "Identité";
            x = 0.37; y = 0.37; w = 0.2; h = 0.04;
            colorText[] = {0.7, 1, 0.7, 1};
        };
        class CbPhone: RscCheckbox {
            idc = 93013;
            x = 0.32; y = 0.42; w = 0.04; h = 0.04;
        };
        class LblPhone: RscText {
            text = "Téléphone";
            x = 0.37; y = 0.42; w = 0.2; h = 0.04;
            colorText[] = {0.7, 1, 0.7, 1};
        };
        class CbDoc: RscCheckbox {
            idc = 93014;
            x = 0.32; y = 0.47; w = 0.04; h = 0.04;
        };
        class LblDoc: RscText {
            text = "Documents";
            x = 0.37; y = 0.47; w = 0.2; h = 0.04;
            colorText[] = {0.7, 1, 0.7, 1};
        };
        class CbBio: RscCheckbox {
            idc = 93015;
            x = 0.32; y = 0.52; w = 0.04; h = 0.04;
        };
        class LblBio: RscText {
            text = "Biométrie";
            x = 0.37; y = 0.52; w = 0.2; h = 0.04;
            colorText[] = {0.7, 1, 0.7, 1};
        };
        class CbNet: RscCheckbox {
            idc = 93016;
            x = 0.32; y = 0.57; w = 0.04; h = 0.04;
        };
        class LblNet: RscText {
            text = "Liens réseau";
            x = 0.37; y = 0.57; w = 0.2; h = 0.04;
            colorText[] = {0.7, 1, 0.7, 1};
        };
        class LblNoise: RscText {
            text = "Données inutiles %";
            x = 0.32; y = 0.63; w = 0.2; h = 0.03;
            colorText[] = {0.7, 1, 0.7, 1};
        };
        class Noise: RscSlider {
            idc = 93017;
            x = 0.32; y = 0.66; w = 0.36; h = 0.03;
        };
        class BtnGen: RscButton {
            idc = 93020;
            text = "GÉNÉRER";
            x = 0.32; y = 0.71; w = 0.17; h = 0.045;
            action = "[] call comspec_sse_fnc_applyGenerateDialog";
            colorBackground[] = {0.1, 0.35, 0.1, 1};
        };
        class BtnClose: RscButton {
            idc = 93021;
            text = "FERMER";
            x = 0.51; y = 0.71; w = 0.17; h = 0.045;
            action = "closeDialog 0";
            colorBackground[] = {0.2, 0.2, 0.2, 1};
        };
    };
};
