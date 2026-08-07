class RscText;
class RscButton;
class RscListbox;

class COMSPEC_SSE_ModelDialog {
    idd = 93030;
    movingEnable = 0;
    enableSimulation = 1;

    class controlsBackground {
        class BG: RscText {
            idc = -1;
            x = 0.28; y = 0.2; w = 0.44; h = 0.52;
            colorBackground[] = {0.05, 0.08, 0.05, 0.95};
        };
        class Title: RscText {
            idc = -1;
            text = "COMSPEC SSE — MODÈLES";
            x = 0.28; y = 0.2; w = 0.44; h = 0.04;
            colorBackground[] = {0.1, 0.25, 0.1, 1};
            colorText[] = {0.6, 1, 0.6, 1};
        };
    };

    class controls {
        class List: RscListbox {
            idc = 93031;
            x = 0.3; y = 0.26; w = 0.4; h = 0.34;
        };
        class BtnApply: RscButton {
            idc = 93032;
            text = "APPLIQUER";
            x = 0.3; y = 0.63; w = 0.18; h = 0.045;
            action = "[] call comspec_sse_fnc_applyModelDialog";
            colorBackground[] = {0.1, 0.35, 0.1, 1};
        };
        class BtnClose: RscButton {
            idc = 93033;
            text = "FERMER";
            x = 0.52; y = 0.63; w = 0.18; h = 0.045;
            action = "closeDialog 0";
            colorBackground[] = {0.2, 0.2, 0.2, 1};
        };
    };
};
