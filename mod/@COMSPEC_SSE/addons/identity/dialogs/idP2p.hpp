/*
    Papiers entre joueurs (fn_idP2pUi) : une petite fenêtre, quatre usages.
      request   « X vous demande vos papiers » : papiers à cocher, ACCEPTER / OPTIONS / REFUSER, délai ; Échap = refuser
      present   présenter de sa propre initiative : papiers à cocher, destinataire, montrer ou remettre en main
      wait      chez le contrôleur : demande en cours, puis « refuse de présenter ses papiers » ou « pas de réponse »
      received  papiers qu'on me tend : liste, OUVRIR (visionneuse), durée de la présentation
    Grille 40 x 25 de la charte SSE (ui_macros.hpp). Le bas de la fenêtre (état, boutons, fond) est remonté par
    fn_idP2pUi quand la liste n'a pas lieu d'être (wait). Textes : stringtable de l'addon (STR_COMSPEC_SSE_P2P_*).
*/
#define P2P_POS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_IdP2p {
    idd = 95200;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['load', _this select 0] call comspec_sse_fnc_idP2pUi";
    onUnload = "['unload', _this] call comspec_sse_fnc_idP2pUi";

    class controlsBackground {
        class Dim: COMSPEC_SSE_RscDim { SSE_FULLSCREEN; };
        class Back: COMSPEC_SSE_RscBackground { idc = 95240; P2P_POS(10,4.5,20,16); };
        class HeadBar: COMSPEC_SSE_RscText { P2P_POS(10,4.5,20,1.8); colorBackground[] = SSE_C_HEADER; };
        class HeadLine: COMSPEC_SSE_RscAccentLine { P2P_POS(10,6.3,20,0.06); };
    };

    class controls {
        class Title: COMSPEC_SSE_RscText { idc = 95201; font = SSE_FONT_TITLE; colorText[] = SSE_C_ACCENT; P2P_POS(10.6,4.9,14.4,1); };
        class Timer: COMSPEC_SSE_RscText { idc = 95202; style = 1; colorText[] = SSE_C_WARN; P2P_POS(25.2,4.9,4.2,1); };
        class Body: COMSPEC_SSE_RscStructuredText { idc = 95203; size = SSE_TXT_S; colorBackground[] = SSE_C_NONE; P2P_POS(10.6,6.8,18.8,2.5); };
        class Papers: COMSPEC_SSE_RscListBox { idc = 95213; sizeEx = SSE_TXT_S; onLBSelChanged = "['toggle', _this select 1] call comspec_sse_fnc_idP2pUi"; onLBDblClick = "['dbl', _this select 1] call comspec_sse_fnc_idP2pUi"; P2P_POS(10.6,9.4,18.8,5.5); };
        class ToLbl: COMSPEC_SSE_RscLabel { idc = 95210; text = "$STR_COMSPEC_SSE_P2P_To"; P2P_POS(10.6,15.15,4.6,1); };
        class To: COMSPEC_SSE_RscCombo { idc = 95211; sizeEx = SSE_TXT_S; P2P_POS(15.4,15.1,14,1.1); };
        class Mode: COMSPEC_SSE_RscCombo { idc = 95220; sizeEx = SSE_TXT_S; tooltip = "$STR_COMSPEC_SSE_P2P_Mode_D"; P2P_POS(10.6,16.4,9.2,1.1); };
        class Reason: COMSPEC_SSE_RscCombo { idc = 95221; sizeEx = SSE_TXT_S; tooltip = "$STR_COMSPEC_SSE_P2P_Reason_D"; P2P_POS(20.2,16.4,9.2,1.1); };
        // Ligne d'état de la fenêtre (envoi, refus du serveur, résultat) : jamais une alerte Arma.
        class Status: COMSPEC_SSE_RscStructuredText { idc = 95204; size = SSE_TXT_S; colorBackground[] = SSE_C_NONE; P2P_POS(10.6,17.65,18.8,0.95); };
        class Btn0: COMSPEC_SSE_RscButton { idc = 95230; sizeEx = SSE_TXT_S; action = "['btn', 0] call comspec_sse_fnc_idP2pUi"; P2P_POS(10.6,18.8,6.6,1.3); };
        class Btn2: COMSPEC_SSE_RscButtonNav { idc = 95232; sizeEx = SSE_TXT_S; text = "$STR_COMSPEC_SSE_P2P_BtnOptions"; tooltip = "$STR_COMSPEC_SSE_P2P_BtnOptions_D"; action = "['btn', 2] call comspec_sse_fnc_idP2pUi"; P2P_POS(17.4,18.8,5.2,1.3); };
        class Btn1: COMSPEC_SSE_RscButtonClose { idc = 95231; sizeEx = SSE_TXT_S; action = "['btn', 1] call comspec_sse_fnc_idP2pUi"; P2P_POS(22.8,18.8,6.6,1.3); };
    };
};
