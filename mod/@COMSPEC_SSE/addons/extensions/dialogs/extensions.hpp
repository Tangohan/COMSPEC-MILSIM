/*
    Fenêtre « SSE ROLEPLAY · Extensions » (fn_docExtWindow), sur la grille 40 x 25 de la charte SSE (ui_macros.hpp) :
    cases carrées, textes en fraction de la case, donc mêmes proportions à toutes les tailles d'interface et en 4:3,
    16:9, 16:10, 21:9. Trois vues, choisies par fn_docExtWindow (« layout ») :
      - extensions : liste à gauche ; nom, fiche (description et modèles, défilante) et aperçu à droite ;
      - vide       : aucune extension détectée, explication et bouton « Ouvrir le tuto » à la place des deux volets ;
      - tuto       : « Comment ça marche », étapes à gauche, lecture défilante à droite, « Copier l'exemple ».
    En-tête : titre, résumé, bouton « Aide » (tuto, à tout moment). Bas : ligne d'état sur deux lignes au plus (police
    réduite si besoin), puis les boutons, sous la ligne d'état : rien ne se chevauche. Aucune alerte Arma.
*/
#define EXT_POS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))
#define EXT_SIZE(W,H) x = 0; y = 0; w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_DocExtensions {
    idd = 93900;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['load', _this select 0] call comspec_sse_fnc_docExtWindow; if (!isNil 'comspec_sse_fnc_guideButton') then { [_this select 0, 'Extensions'] call comspec_sse_fnc_guideButton; };";
    onUnload = "['unload'] call comspec_sse_fnc_docExtWindow";

    class controlsBackground {
        class Dim: COMSPEC_SSE_RscDim { SSE_FULLSCREEN; };
        class Back: COMSPEC_SSE_RscBackground { EXT_POS(1.5,0.8,37,23.4); };
        class Head: COMSPEC_SSE_RscHeader { EXT_POS(1.5,0.8,37,2.8); };
        class Line: COMSPEC_SSE_RscAccentLine { EXT_POS(1.5,3.6,37,0.06); };
    };

    class controls {
        class Title: COMSPEC_SSE_RscText {
            idc = 93901;
            text = "$STR_COMSPEC_SSE_EXT_Title";
            font = SSE_FONT_TITLE;
            sizeEx = SSE_TXT_L;
            colorText[] = SSE_C_ACCENT;
            EXT_POS(2,0.95,26.5,1.4);
        };
        class Summary: COMSPEC_SSE_RscText {
            idc = 93902;
            sizeEx = SSE_TXT_S;
            colorText[] = SSE_C_TEXT_MUTED;
            EXT_POS(2,2.25,26.5,1.1);
        };
        // « Aide » : ouvre le tuto ; dans le tuto, le même bouton ramène aux extensions.
        class BtnHelp: COMSPEC_SSE_RscButtonNav {
            idc = 93903;
            text = "$STR_COMSPEC_SSE_EXT_Help";
            tooltip = "$STR_COMSPEC_SSE_EXT_Help_D";
            action = "['help'] call comspec_sse_fnc_docExtWindow";
            EXT_POS(29,1.4,7.5,1.5);
        };

        // ---- vue « extensions »
        class List: COMSPEC_SSE_RscListBox {
            idc = 93910;
            EXT_POS(2,4.1,13,15.8);
            onLBSelChanged = "['select'] call comspec_sse_fnc_docExtWindow";
        };
        class RightPanel: COMSPEC_SSE_RscPanel { idc = 93926; EXT_POS(15.5,4.1,22.5,15.8); };
        class Name: COMSPEC_SSE_RscText {
            idc = 93920;
            font = SSE_FONT_TITLE;
            sizeEx = SSE_TXT_L;
            EXT_POS(16,4.3,21.5,1.3);
        };
        // Fiche : auteur, version, description, « Modèles de documents » et la liste des modèles ; défile si elle est longue.
        class Info: COMSPEC_SSE_RscControlsGroup {
            idc = 93927;
            EXT_POS(16,5.7,21.5,7.6);
            class controls {
                class Text: COMSPEC_SSE_RscStructuredText {
                    idc = 93921;
                    size = SSE_TXT_S;
                    colorBackground[] = SSE_C_NONE;
                    EXT_SIZE(20.7,7.6);
                };
            };
        };
        class Preview: RscPicture {
            idc = 93923;
            style = 2096;
            text = "";
            EXT_POS(16,13.5,21.5,6.1);
        };

        // ---- vue « vide » (aucune extension détectée)
        class EmptyText: COMSPEC_SSE_RscStructuredText {
            idc = 93951;
            size = SSE_TXT_S;
            colorBackground[] = SSE_C_PANEL;
            EXT_POS(2,4.1,36,11);
        };
        class EmptyTuto: COMSPEC_SSE_RscButton {
            idc = 93952;
            text = "$STR_COMSPEC_SSE_EXT_OpenTuto";
            action = "['help', 'tuto'] call comspec_sse_fnc_docExtWindow";
            EXT_POS(14,16,12,1.6);
        };

        // ---- vue « tuto » (Comment ça marche)
        class Steps: COMSPEC_SSE_RscListBox {
            idc = 93960;
            EXT_POS(2,4.1,12,15.8);
            onLBSelChanged = "['step'] call comspec_sse_fnc_docExtWindow";
        };
        class ReadBack: COMSPEC_SSE_RscPanel { idc = 93964; EXT_POS(14.5,4.1,23.5,15.8); };
        class Read: COMSPEC_SSE_RscControlsGroup {
            idc = 93961;
            EXT_POS(15,4.4,22.8,13.3);
            class controls {
                class Text: COMSPEC_SSE_RscStructuredText {
                    idc = 93962;
                    size = SSE_TXT_S;
                    colorBackground[] = SSE_C_NONE;
                    EXT_SIZE(22,13.3);
                };
            };
        };
        class BtnCopy: COMSPEC_SSE_RscButton {
            idc = 93963;
            text = "$STR_COMSPEC_SSE_EXT_Copy";
            tooltip = "$STR_COMSPEC_SSE_EXT_Copy_D";
            action = "['copy'] call comspec_sse_fnc_docExtWindow";
            EXT_POS(15,18,10,1.5);
        };

        // ---- bas : ligne d'état (deux lignes au plus), puis les boutons
        class State: COMSPEC_SSE_RscStructuredText {
            idc = 93924;
            size = SSE_TXT_S;
            colorBackground[] = SSE_C_NONE;
            EXT_POS(2,20.15,36,2);
        };
        class BtnToggle: COMSPEC_SSE_RscButton {
            idc = 93930;
            text = "$STR_COMSPEC_SSE_EXT_Enable";
            action = "['toggle'] call comspec_sse_fnc_docExtWindow";
            EXT_POS(22.5,22.4,7.5,1.5);
        };
        class BtnClose: COMSPEC_SSE_RscButtonClose {
            idc = 93931;
            text = "$STR_COMSPEC_SSE_EXT_Close";
            action = "closeDialog 2";
            EXT_POS(30.5,22.4,7.5,1.5);
        };
        // Mesure des libellés de la liste (coupés avec « … » plutôt que sous l'état « Active »).
        class Probe: COMSPEC_SSE_RscText { idc = 93999; show = 0; EXT_POS(0,0,1,1); };
    };
};
