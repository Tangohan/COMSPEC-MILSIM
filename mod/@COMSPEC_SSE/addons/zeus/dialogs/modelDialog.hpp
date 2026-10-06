// Dialogue Zeus « Appliquer un modèle SSE » (idd 93030).
// Classes COMSPEC_SSE_Rsc* : addon ui, déclarées dans config.cpp.

class COMSPEC_SSE_ModelDialog {
    idd = 93030;
    movingEnable = 0;
    enableSimulation = 1;

    class controlsBackground {
        class Dim: COMSPEC_SSE_RscDim { SSE_FULLSCREEN; };
        class BG: COMSPEC_SSE_RscBackground { SSE_ZPOS(10,3.5,20,18); };
        class Title: COMSPEC_SSE_RscHeaderZeus {
            text = "APPLIQUER UN MODÈLE SSE";
            SSE_ZPOS(10,3.5,20,1.6);
        };
        class TitleLine: COMSPEC_SSE_RscAccentLineZeus { SSE_ZPOS(10,5.1,20,0.12); };
        class SecList: COMSPEC_SSE_RscSection {
            text = "MODÈLES DISPONIBLES — intégrés · mission · locaux";
            SSE_ZPOS(10.5,6.9,19,0.9);
        };
        class Footer: COMSPEC_SSE_RscPanel { SSE_ZPOS(10,19.1,20,2.4); };
    };

    class controls {
        class Info: COMSPEC_SSE_RscLabel {
            idc = 93034;
            text = "";
            SSE_ZPOS(10.5,5.6,19,1.2);
        };
        class List: COMSPEC_SSE_RscListBox {
            idc = 93031;
            SSE_ZPOS(10.5,7.8,19,11);
        };
        class BtnApply: COMSPEC_SSE_RscButtonZeus {
            idc = 93032;
            text = "APPLIQUER";
            action = "[] call comspec_sse_fnc_applyModelDialog";
            SSE_ZPOS(10.5,19.5,9.2,1.5);
        };
        class BtnClose: COMSPEC_SSE_RscButtonClose {
            idc = 93033;
            text = "FERMER";
            action = "closeDialog 0";
            SSE_ZPOS(20.3,19.5,9.2,1.5);
        };
    };
};
