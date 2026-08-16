// RscText / RscButton / RscStructuredText sont déclarés dans config.cpp.

class COMSPEC_SSE_SeekDialog {
    idd = 93100;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "[] call comspec_sse_fnc_seekOnLoad";

    class controlsBackground {
        class BG: RscText {
            idc = -1;
            x = 0.18; y = 0.08; w = 0.64; h = 0.84;
            colorBackground[] = {0.03, 0.06, 0.03, 0.97};
        };
        class Title: RscText {
            idc = 93101;
            text = "SEEK II — BIOMETRIC TERMINAL";
            x = 0.18; y = 0.08; w = 0.64; h = 0.045;
            colorBackground[] = {0.08, 0.22, 0.08, 1};
            colorText[] = {0.55, 1, 0.55, 1};
        };
        class Sub: RscText {
            idc = 93102;
            text = "Photo · empreintes · iris · identité · score · qualité";
            x = 0.18; y = 0.125; w = 0.64; h = 0.03;
            colorBackground[] = {0.05, 0.12, 0.05, 1};
            colorText[] = {0.7, 0.9, 0.7, 1};
        };
    };

    class controls {
        class Body: RscStructuredText {
            idc = 93110;
            x = 0.2; y = 0.17; w = 0.6; h = 0.48;
            colorBackground[] = {0, 0, 0, 0.25};
        };

        class BtnFP: RscButton {
            idc = 93120;
            text = "EMPREINTES";
            x = 0.2; y = 0.67; w = 0.14; h = 0.04;
            action = "['fingerprint'] call comspec_sse_fnc_seekCapture";
            colorBackground[] = {0.1, 0.28, 0.1, 1};
        };
        class BtnIR: RscButton {
            idc = 93121;
            text = "IRIS";
            x = 0.35; y = 0.67; w = 0.12; h = 0.04;
            action = "['iris'] call comspec_sse_fnc_seekCapture";
            colorBackground[] = {0.1, 0.28, 0.1, 1};
        };
        class BtnFace: RscButton {
            idc = 93122;
            text = "VISAGE";
            x = 0.48; y = 0.67; w = 0.12; h = 0.04;
            action = "['face'] call comspec_sse_fnc_seekCapture";
            colorBackground[] = {0.1, 0.28, 0.1, 1};
        };
        class BtnDNA: RscButton {
            idc = 93123;
            text = "ADN";
            x = 0.61; y = 0.67; w = 0.17; h = 0.04;
            action = "['dna'] call comspec_sse_fnc_seekCapture";
            colorBackground[] = {0.1, 0.28, 0.1, 1};
        };

        class BtnID: RscButton {
            idc = 93124;
            text = "IDENTIFIER";
            x = 0.2; y = 0.73; w = 0.16; h = 0.045;
            action = "[] call comspec_sse_fnc_seekIdentify";
            colorBackground[] = {0.12, 0.35, 0.12, 1};
        };
        class BtnAll: RscButton {
            idc = 93125;
            text = "CAPTURE ALL";
            x = 0.37; y = 0.73; w = 0.16; h = 0.045;
            action = "['all'] call comspec_sse_fnc_seekCapture";
            colorBackground[] = {0.15, 0.3, 0.15, 1};
        };
        class BtnTx: RscButton {
            idc = 93126;
            text = "TRANSMETTRE";
            x = 0.54; y = 0.73; w = 0.24; h = 0.045;
            action = "[] call comspec_sse_fnc_seekTransmit";
            colorBackground[] = {0.15, 0.25, 0.15, 1};
        };

        class BtnTerminal: RscButton {
            idc = 93128;
            text = "TERMINAL SSE";
            x = 0.2; y = 0.8; w = 0.18; h = 0.04;
            action = "closeDialog 0; [{ ['terminal'] call comspec_sse_fnc_uiOpenScreen; }, [], 0.05] call CBA_fnc_waitAndExecute";
            colorBackground[] = {0.12, 0.22, 0.14, 1};
        };
        class BtnClose: RscButton {
            idc = 93127;
            text = "FERMER";
            x = 0.6; y = 0.8; w = 0.18; h = 0.04;
            action = "[] call comspec_sse_fnc_seekClose";
            colorBackground[] = {0.2, 0.2, 0.2, 1};
        };
    };
};
