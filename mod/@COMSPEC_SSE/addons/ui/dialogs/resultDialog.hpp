class RscText;
class RscButton;
class RscStructuredText;

class COMSPEC_SSE_ResultDialog {
    idd = 93010;
    movingEnable = 0;
    enableSimulation = 1;

    class controlsBackground {
        class BG: RscText {
            idc = -1;
            x = 0.28;
            y = 0.18;
            w = 0.44;
            h = 0.62;
            colorBackground[] = {0.04, 0.07, 0.04, 0.96};
        };
        class Title: RscText {
            idc = 93011;
            text = "COMSPEC SSE";
            x = 0.28;
            y = 0.18;
            w = 0.44;
            h = 0.04;
            colorBackground[] = {0.08, 0.2, 0.08, 1};
            colorText[] = {0.55, 1, 0.55, 1};
        };
    };

    class controls {
        class Body: RscStructuredText {
            idc = 93012;
            x = 0.3;
            y = 0.24;
            w = 0.4;
            h = 0.42;
            colorBackground[] = {0, 0, 0, 0};
        };
        class BtnConsult: RscButton {
            idc = 93013;
            text = "CONSULTER";
            x = 0.3;
            y = 0.68;
            w = 0.12;
            h = 0.04;
            action = "[] call comspec_sse_fnc_resultConsult";
            colorBackground[] = {0.1, 0.3, 0.1, 1};
        };
        class BtnTx: RscButton {
            idc = 93014;
            text = "TRANSMETTRE";
            x = 0.44;
            y = 0.68;
            w = 0.14;
            h = 0.04;
            action = "[] call comspec_sse_fnc_resultTransmit";
            colorBackground[] = {0.15, 0.25, 0.15, 1};
        };
        class BtnClose: RscButton {
            idc = 93015;
            text = "FERMER";
            x = 0.6;
            y = 0.68;
            w = 0.1;
            h = 0.04;
            action = "closeDialog 0";
            colorBackground[] = {0.2, 0.2, 0.2, 1};
        };
    };
};
