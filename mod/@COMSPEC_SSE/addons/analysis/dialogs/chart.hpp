/*
    Tableau des liens (idd 93500) : graphe et organigramme des personnes,
    organisations, objets et données, dessinés sur une carte recouverte.
    Inclus par config.cpp (classes COMSPEC_SSE_Rsc* déclarées là).
*/
#define ANA_X(N) SSE_X(0.5 + (N))
#define ANA_Y(N) SSE_Y(0.5 + (N))
#define ANA_POS(X,Y,W,H) x = SSE_Q(ANA_X(X)); y = SSE_Q(ANA_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_ChartDialog {
    idd = 93500;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "(_this select 0) call comspec_sse_fnc_trDisplay; _this call comspec_sse_fnc_chartOnLoad";
    onUnload = "uiNamespace setVariable ['comspec_sse_chartDisplay', displayNull]";

    class controlsBackground {
        class Dim: COMSPEC_SSE_RscDim { SSE_FULLSCREEN; };
        class BG: COMSPEC_SSE_RscBackground { ANA_POS(0,0,39,24); };
        class Title: COMSPEC_SSE_RscHeader { idc = 93501; text = "TABLEAU DES LIENS"; ANA_POS(0,0,39,1.6); };
        class Brand: COMSPEC_SSE_RscLabel { text = "COMSPEC SSE · ANALYSE"; style = 1; ANA_POS(26,0.3,12.5,1); };
        class TitleLine: COMSPEC_SSE_RscAccentLine { idc = 93502; ANA_POS(0,1.6,39,0.12); };
        class Sub: COMSPEC_SSE_RscSubHeader { idc = 93503; text = ""; ANA_POS(0,1.72,39,1); };
        class SideBG: COMSPEC_SSE_RscPanel { ANA_POS(28.2,2.9,10.6,20.9); };
        class SideTitle: COMSPEC_SSE_RscSection { text = "DÉTAIL"; ANA_POS(28.5,3,10,0.9); };
    };

    class controls {
        class Canvas: RscMapControl {
            idc = 93510;
            ANA_POS(0.2,4.4,27.8,19.4);
            maxSatelliteAlpha = 0;
            alphaFadeStartScale = 10;
            alphaFadeEndScale = 10;
            colorBackground[] = {0.035, 0.05, 0.05, 1};
            colorOutside[] = {0.035, 0.05, 0.05, 1};
            scaleMin = 0.002;
            scaleMax = 1;
            scaleDefault = 0.1;
            showCountourInterval = 0;
        };

        class BtnGraph: COMSPEC_SSE_RscButtonNav { idc = 93520; text = "GRAPHE"; tooltip = "Personnes, organisations, objets et données reliés"; action = "['mode', 'graph'] call comspec_sse_fnc_chartSet"; ANA_POS(0.2,2.95,4,1.3); };
        class BtnOrbat: COMSPEC_SSE_RscButtonNav { idc = 93521; text = "ORBAT"; tooltip = "Organigramme : organisations, cellules, membres par rôle"; action = "['mode', 'orbat'] call comspec_sse_fnc_chartSet"; ANA_POS(4.3,2.95,4,1.3); };
        class BtnAll: COMSPEC_SSE_RscButtonNav { idc = 93522; text = "TOUT"; action = "['filter', 'all'] call comspec_sse_fnc_chartSet"; ANA_POS(9,2.95,3.4,1.3); };
        class BtnPeople: COMSPEC_SSE_RscButtonNav { idc = 93523; text = "PERSONNES"; tooltip = "Personnes et organisations"; action = "['filter', 'people'] call comspec_sse_fnc_chartSet"; ANA_POS(12.5,2.95,4.4,1.3); };
        class BtnOrgs: COMSPEC_SSE_RscButtonNav { idc = 93524; text = "ORGANISATIONS"; action = "['filter', 'orgs'] call comspec_sse_fnc_chartSet"; ANA_POS(17,2.95,5.2,1.3); };
        class BtnThings: COMSPEC_SSE_RscButtonNav { idc = 93525; text = "OBJETS ET DONNÉES"; tooltip = "Pièces, appareils, documents, lieux et qui y touche"; action = "['filter', 'things'] call comspec_sse_fnc_chartSet"; ANA_POS(22.3,2.95,5.7,1.3); };

        class DetailGroup: RscControlsGroup {
            idc = 93532;
            ANA_POS(28.4,4,10.2,12.4);
            class controls {
                class Detail: COMSPEC_SSE_RscStructuredText { idc = 93530; x = 0; y = 0; w = SSE_Q(SSE_W(9.7)); h = SSE_Q(SSE_H(12.4)); };
            };
        };
        class Legend: COMSPEC_SSE_RscStructuredText { idc = 93531; ANA_POS(28.4,16.6,10.2,4); };

        class BtnConfirmed: COMSPEC_SSE_RscButtonNav { idc = 93526; text = "CONFIRMÉ SEUL"; tooltip = "Masque ce qui n'est que soupçonné"; action = "['confirmed'] call comspec_sse_fnc_chartSet"; ANA_POS(28.4,20.8,5,1.3); };
        class BtnFit: COMSPEC_SSE_RscButtonNav { idc = 93527; text = "RECADRER"; action = "['fit'] call comspec_sse_fnc_chartSet"; ANA_POS(33.6,20.8,5,1.3); };
        class BtnReveal: COMSPEC_SSE_RscButtonZeus { idc = 93528; text = "RÉVÉLER"; tooltip = "Zeus : rend le nœud choisi connu des joueurs (confirmé)"; action = "['reveal'] call comspec_sse_fnc_chartSet"; ANA_POS(28.4,22.3,5,1.3); };
        class BtnClose: COMSPEC_SSE_RscButtonClose { idc = 93529; text = "FERMER"; action = "closeDialog 0"; ANA_POS(33.6,22.3,5,1.3); };
    };
};
