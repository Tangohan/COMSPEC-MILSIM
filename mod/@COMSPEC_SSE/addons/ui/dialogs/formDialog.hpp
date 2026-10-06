/*
    Formulaire générique SSE (idd 93600) — sans dépendance ZEN / Achilles.

    Les lignes (libellé + champ) sont créées en SQF par comspec_sse_fnc_uiForm
    dans le groupe 93610 ; ce fichier ne décrit que le cadre.
    Utilisé par les modules Zeus pour se configurer à la pose.
*/

#define SSE_FPOS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_FormDialog {
    idd = 93600;
    movingEnable = 0;
    enableSimulation = 1;
    onUnload = "uiNamespace setVariable ['COMSPEC_SSE_FormDisplay', displayNull];";

    class controlsBackground {
        class Dim: COMSPEC_SSE_RscDim {
            SSE_FULLSCREEN;
        };
        class BG: COMSPEC_SSE_RscBackground { SSE_FPOS(9,2.5,22,20); };
        class Title: COMSPEC_SSE_RscHeaderZeus {
            idc = 93601;
            text = "COMSPEC SSE";
            SSE_FPOS(9,2.5,22,1.6);
        };
        class TitleLine: COMSPEC_SSE_RscAccentLineZeus { SSE_FPOS(9,4.1,22,0.12); };
        class Footer: COMSPEC_SSE_RscPanel { SSE_FPOS(9,20.3,22,2.2); };
    };

    class controls {
        class Description: COMSPEC_SSE_RscStructuredText {
            idc = 93602;
            colorBackground[] = SSE_C_PANEL_ALT;
            size = SSE_TXT_S;
            SSE_FPOS(9.5,4.6,21,2.4);
        };
        class Rows: COMSPEC_SSE_RscControlsGroup {
            idc = 93610;
            SSE_FPOS(9.5,7.3,21,12.7);
        };
        class BtnOk: COMSPEC_SSE_RscButtonZeus {
            idc = 93620;
            text = "VALIDER";
            action = "[] call comspec_sse_fnc_uiFormConfirm";
            SSE_FPOS(19.4,20.7,5.6,1.4);
        };
        class BtnCancel: COMSPEC_SSE_RscButtonClose {
            idc = 93621;
            text = "ANNULER";
            action = "closeDialog 2";
            SSE_FPOS(25.3,20.7,5.2,1.4);
        };
    };
};
