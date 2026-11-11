/*
    Papiers de l'IA, fenêtre du Zeus (fn_idZeusPapers) : à gauche le document tel qu'un joueur le voit (rendu de la
    visionneuse, fn_idViewerOnLoad, sur les contrôles 93704 à 93720 de cette fenêtre), à droite l'analyse réservée au Zeus
    (vraie identité, qualité expliquée, défauts, ce que trouvera chaque contrôle, couverture, HVT, BOLO) et les actions.
    Grille 40 x 25 de la charte SSE (ui_macros.hpp, copie de tools/sync_headers.py) : mêmes proportions en 4:3, 16:9,
    16:10 et 21:9, à toutes les tailles d'interface. Échap ferme (fenêtre de dialogue).
*/
#define ZP_POS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))
#define ZP_REL(X,Y,W,H) x = SSE_Q(SSE_W(X)); y = SSE_Q(SSE_H(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_IdZeusPapers {
    idd = 95100;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['load', _this select 0] call comspec_sse_fnc_idZeusPapers";
    onUnload = "['stop'] call comspec_sse_fnc_idFace; uiNamespace setVariable ['comspec_sse_idViewerDisplay', displayNull]; ['unload'] call comspec_sse_fnc_idZeusPapers";

    class controlsBackground {
        class Dim: COMSPEC_SSE_RscDim { SSE_FULLSCREEN; };
        class Back: COMSPEC_SSE_RscBackground { ZP_POS(0,0,40,25); };
        class HeadBar: COMSPEC_SSE_RscText { ZP_POS(0,0,40,1.8); colorBackground[] = SSE_C_HEADER_ZEUS; };
        class HeadLine: COMSPEC_SSE_RscAccentLineZeus { ZP_POS(0,1.8,40,0.06); };
        class CardBack: COMSPEC_SSE_RscPanel { ZP_POS(0.4,2.3,24.2,22.3); };
        class SideBack: COMSPEC_SSE_RscPanel { ZP_POS(25,2.3,14.6,22.3); };
    };

    class controls {
        // ------------------------------------------------------------ en-tête (même charte que l'éditeur SSE)
        class Logo: RscPicture { idc = -1; style = 2096; text = "\z\comspec_sse\addons\editor\data\logo_athena_ca.paa"; colorText[] = SSE_C_TEXT; ZP_POS(0.4,0.2,2.8,1.4); };
        class HeadTitle: COMSPEC_SSE_RscText { idc = 95101; text = "$STR_COMSPEC_SSE_ZP_Title"; font = SSE_FONT_TITLE; colorText[] = SSE_C_ZEUS; ZP_POS(3.4,0.4,12,1); };
        class HeadWho: COMSPEC_SSE_RscText { idc = 95102; style = 1; sizeEx = SSE_TXT_S; ZP_POS(15.6,0.4,16.4,1); };
        class HeadRefresh: COMSPEC_SSE_RscButtonNav { idc = 95103; text = "$STR_COMSPEC_SSE_ZP_Refresh"; tooltip = "$STR_COMSPEC_SSE_ZP_Refresh_D"; sizeEx = SSE_TXT_S; action = "['refresh'] call comspec_sse_fnc_idZeusPapers"; ZP_POS(32.2,0.35,4.4,1.1); };
        class HeadClose: COMSPEC_SSE_RscButtonClose { idc = 95104; text = "$STR_COMSPEC_SSE_ZP_Close"; sizeEx = SSE_TXT_S; action = "closeDialog 2"; ZP_POS(36.8,0.35,2.9,1.1); };

        // ------------------------------------------------------------ document (gauche)
        class DocLbl: COMSPEC_SSE_RscLabel { idc = 95110; text = "$STR_COMSPEC_SSE_ZP_DocLbl"; ZP_POS(0.8,2.55,3.5,1); };
        class DocPick: COMSPEC_SSE_RscCombo { idc = 95111; onLBSelChanged = "['pick', _this select 1] call comspec_sse_fnc_idZeusPapers"; ZP_POS(4.4,2.5,19.8,1.1); };
        // Contrôles de la visionneuse (mêmes idc : fn_idViewerOnLoad les place autour du document).
        class Card: RscPicture { idc = 93720; text = ""; ZP_POS(0.8,5.8,23.4,15.6); };
        class Title: COMSPEC_SSE_RscStructuredText { idc = 93704; size = SSE_TXT_S; colorBackground[] = SSE_C_NONE; ZP_POS(0.8,3.9,23.4,1.7); };
        class Remark: COMSPEC_SSE_RscStructuredText { idc = 93705; size = SSE_TXT_S; colorBackground[] = SSE_C_NONE; show = 0; ZP_POS(0.8,21.3,23.4,0.75); };
        class ViewStatus: COMSPEC_SSE_RscStructuredText { idc = 93706; size = SSE_TXT_S; colorBackground[] = SSE_C_NONE; show = 0; ZP_POS(0.8,22.1,23.4,0.7); };
        class BtnSide: COMSPEC_SSE_RscButtonNav { idc = 93710; text = "$STR_COMSPEC_SSE_ZP_Side"; sizeEx = SSE_TXT_S; action = "['side'] call comspec_sse_fnc_idViewerOnLoad"; ZP_POS(0.8,22.9,23.4,1.1); };
        // Pas de papiers (ou cible introuvable) : explication à la place du document, et de quoi en créer.
        class Empty: COMSPEC_SSE_RscStructuredText { idc = 95112; colorBackground[] = SSE_C_NONE; show = 0; ZP_POS(1.6,9.5,21.8,4.5); };
        class EmptyCreate: COMSPEC_SSE_RscButtonZeus { idc = 95113; text = "$STR_COMSPEC_SSE_ZP_BtnCreate"; tooltip = "$STR_COMSPEC_SSE_ZP_BtnCreate_D"; sizeEx = SSE_TXT_S; show = 0; action = "['act', 'give'] call comspec_sse_fnc_idZeusPapers"; ZP_POS(8.5,14.3,8,1.3); };

        // ------------------------------------------------------------ analyse du Zeus (droite)
        class SideTitle: COMSPEC_SSE_RscText { idc = 95120; text = "$STR_COMSPEC_SSE_ZP_Analysis"; font = SSE_FONT_TITLE; colorText[] = SSE_C_ZEUS; ZP_POS(25.3,2.45,14,1.1); };
        class SideScroll: COMSPEC_SSE_RscControlsGroup {
            idc = 95121;
            ZP_POS(25.3,3.7,14.1,14.3);
            class controls {
                class Body: COMSPEC_SSE_RscStructuredText { idc = 95122; size = SSE_TXT_S; colorBackground[] = SSE_C_NONE; ZP_REL(0,0,13.4,14.3); };
            };
        };
        // Ligne d'état de la fenêtre (retour des actions) : jamais une alerte Arma.
        class SideStatus: COMSPEC_SSE_RscStructuredText { idc = 95123; size = SSE_TXT_S; colorBackground[] = SSE_C_NONE; ZP_POS(25.3,18.05,14.1,1.6); };
        class Act0: COMSPEC_SSE_RscButtonZeus { idc = 95130; sizeEx = SSE_TXT_S; action = "['act', 0] call comspec_sse_fnc_idZeusPapers"; ZP_POS(25.3,19.7,6.95,1.35); };
        class Act1: Act0 { idc = 95131; action = "['act', 1] call comspec_sse_fnc_idZeusPapers"; ZP_POS(32.45,19.7,6.95,1.35); };
        class Act2: Act0 { idc = 95132; action = "['act', 2] call comspec_sse_fnc_idZeusPapers"; ZP_POS(25.3,21.3,6.95,1.35); };
        class Act3: Act0 { idc = 95133; action = "['act', 3] call comspec_sse_fnc_idZeusPapers"; ZP_POS(32.45,21.3,6.95,1.35); };
        class Act4: COMSPEC_SSE_RscButtonNav { idc = 95134; sizeEx = SSE_TXT_S; action = "['act', 4] call comspec_sse_fnc_idZeusPapers"; ZP_POS(25.3,22.9,6.95,1.35); };
        class Act5: Act4 { idc = 95135; action = "['act', 5] call comspec_sse_fnc_idZeusPapers"; ZP_POS(32.45,22.9,6.95,1.35); };
    };
};
