#include "common.hpp"

/*
    Écrans SSE « hors SEEK » (terminal, numérique, site, graphe, preuves,
    mission, Zeus).

    Refonte V0.8 : cadre commun sombre, bandeau titre + filet d'accent,
    sections titrées, barre d'actions en pied, grille centrée safeZone.
    Les idd / idc et les classes de contrôles (liste, texte structuré…)
    sont inchangés : les fonctions uiFill* / uiDisplayCtx restent valides.
*/

// Position dans le cadre (cases de grille)
#define SSE_POS(X,Y,W,H) x = SSE_Q(SSE_FX(X)); y = SSE_Q(SSE_FY(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

// Cadre commun : voile, fond, bandeau titre, marque, filet, sous-titre, pied
#define SSE_FRAME(HDRCLASS,LINECLASS,TITLE_IDC,TITLE_TXT,SUB_IDC,SUB_TXT) \
    class Dim: COMSPEC_SSE_RscDim { SSE_FULLSCREEN; }; \
    class BG: COMSPEC_SSE_RscBackground { SSE_POS(0,0,SSE_FRAME_W,SSE_FRAME_H); }; \
    class Title: HDRCLASS { idc = TITLE_IDC; text = TITLE_TXT; SSE_POS(0,0,SSE_FRAME_W,1.6); }; \
    class Brand: COMSPEC_SSE_RscLabel { text = "COMSPEC SSE"; style = 1; SSE_POS(25.5,0.3,10,1); }; \
    class TitleLine: LINECLASS { SSE_POS(0,1.6,SSE_FRAME_W,0.12); }; \
    class Sub: COMSPEC_SSE_RscSubHeader { idc = SUB_IDC; text = SUB_TXT; SSE_POS(0,1.72,SSE_FRAME_W,1.1); }; \
    class Footer: COMSPEC_SSE_RscPanel { SSE_POS(0,20,SSE_FRAME_W,3); }; \
    class FooterLine: COMSPEC_SSE_RscText { colorBackground[] = SSE_C_ACCENT_SOFT; SSE_POS(0,20,SSE_FRAME_W,0.08); }

// Titre de section (petites capitales vertes)
#define SSE_SECTION(NAME,TXT,X,Y,W) class NAME: COMSPEC_SSE_RscSection { text = TXT; SSE_POS(X,Y,W,0.9); }

// ============================================================
// TERMINAL SSE TERRAIN — hub principal (idd 93200)
// ============================================================
class COMSPEC_SSE_TerminalDialog {
    idd = 93200;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['terminal'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        SSE_FRAME(COMSPEC_SSE_RscHeader,COMSPEC_SSE_RscAccentLine,93201,"TERMINAL SSE — TERRAIN",93202,"  Record lié · collecte · transmission");
        SSE_SECTION(SecSummary,"SYNTHÈSE DU RECORD",0.5,4.6,17);
        SSE_SECTION(SecDetail,"TRANSMISSION ET DÉTAIL",0.5,13.2,17);
    };

    class controls {
        class NavBar: COMSPEC_SSE_RscStructuredText {
            idc = 93210;
            colorBackground[] = SSE_C_PANEL_ALT;
            SSE_POS(0.5,3.1,35,1.2);
        };
        class Summary: COMSPEC_SSE_RscStructuredText {
            idc = 93211;
            SSE_POS(0.5,5.5,17,7.4);
        };
        class ListTitle: COMSPEC_SSE_RscSection {
            idc = -1;
            text = "ÉLÉMENTS ET DOSSIERS";
            SSE_POS(18,4.6,17.5,0.9);
        };
        class List: COMSPEC_SSE_RscListBox {
            idc = 93212;
            SSE_POS(18,5.5,17.5,14.2);
        };
        class Detail: COMSPEC_SSE_RscStructuredText {
            idc = 93213;
            SSE_POS(0.5,14.1,17,5.6);
        };

        class BtnDigital: COMSPEC_SSE_RscButtonNav {
            idc = 93220; text = "NUMÉRIQUE";
            tooltip = "Exploitation numérique du support lié (contacts, messages, fichiers…)";
            action = "['digital'] call comspec_sse_fnc_uiOpenScreen";
            SSE_POS(0.5,20.3,5.6,1.25);
        };
        class BtnSeek: COMSPEC_SSE_RscButtonNav {
            idc = 93221; text = "SEEK II";
            tooltip = "Terminal biométrique SEEK II";
            action = "['seek'] call comspec_sse_fnc_uiOpenScreen";
            SSE_POS(6.4,20.3,5.6,1.25);
        };
        class BtnSite: COMSPEC_SSE_RscButtonNav {
            idc = 93222; text = "SITE";
            tooltip = "Complétude et triage du site autour du record";
            action = "['site'] call comspec_sse_fnc_uiOpenScreen";
            SSE_POS(12.3,20.3,5.6,1.25);
        };
        class BtnGraph: COMSPEC_SSE_RscButtonNav {
            idc = 93223; text = "GRAPHE";
            tooltip = "Relations entre personnes, objets et lieux";
            action = "['graph'] call comspec_sse_fnc_uiOpenScreen";
            SSE_POS(18.2,20.3,5.6,1.25);
        };
        class BtnEvidence: COMSPEC_SSE_RscButtonNav {
            idc = 93224; text = "PREUVES";
            tooltip = "Pièces saisies et chaîne de conservation";
            action = "['evidence'] call comspec_sse_fnc_uiOpenScreen";
            SSE_POS(24.1,20.3,5.6,1.25);
        };
        class BtnMission: COMSPEC_SSE_RscButtonNav {
            idc = 93225; text = "MISSION";
            tooltip = "Fusion du renseignement de la mission";
            action = "['mission'] call comspec_sse_fnc_uiOpenScreen";
            SSE_POS(30,20.3,5.6,1.25);
        };
        class BtnRefresh: COMSPEC_SSE_RscButtonNav {
            idc = 93226; text = "RAFRAÎCHIR";
            action = "['terminal'] call comspec_sse_fnc_uiRefresh";
            SSE_POS(0.5,21.7,5.6,1.15);
        };
        class BtnTx: COMSPEC_SSE_RscButton {
            idc = 93227; text = "TRANSMETTRE";
            tooltip = "Envoie le record vers Athena (ou file hors-ligne)";
            action = "[] call comspec_sse_fnc_uiTransmitRecord";
            SSE_POS(6.4,21.7,7,1.15);
        };
        class BtnZeus: COMSPEC_SSE_RscButtonZeus {
            idc = 93229; text = "ZEUS";
            tooltip = "Contrôle Zeus (réservé au chef de mission)";
            action = "['zeus'] call comspec_sse_fnc_uiOpenScreen";
            SSE_POS(13.7,21.7,5,1.15);
        };
        class BtnClose: COMSPEC_SSE_RscButtonClose {
            idc = 93228; text = "FERMER";
            action = "closeDialog 0";
            SSE_POS(30,21.7,5.6,1.15);
        };
    };
};

// ============================================================
// EXPLOITATION NUMÉRIQUE — onglets (idd 93250)
// ============================================================
class COMSPEC_SSE_DigitalDialog {
    idd = 93250;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['digital'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        SSE_FRAME(COMSPEC_SSE_RscHeader,COMSPEC_SSE_RscAccentLine,93251,"EXPLOITATION NUMÉRIQUE",-1,"  Support numérique lié au record");
        SSE_SECTION(SecBody,"CONTENU",0.5,4.6,22);
        SSE_SECTION(SecList,"ÉLÉMENTS",23,4.6,12.5);
    };

    class controls {
        class Tabs: COMSPEC_SSE_RscStructuredText {
            idc = 93252;
            colorBackground[] = SSE_C_PANEL_ALT;
            SSE_POS(0.5,3.1,35,1.2);
        };
        class Body: COMSPEC_SSE_RscStructuredText {
            idc = 93253;
            SSE_POS(0.5,5.5,22,14.2);
        };
        class List: COMSPEC_SSE_RscListBox {
            idc = 93254;
            SSE_POS(23,5.5,12.5,14.2);
        };

        class BtnOV: COMSPEC_SSE_RscButtonNav { idc = 93260; text = "APERÇU"; sizeEx = SSE_TXT_S; action = "['overview'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(0.5,20.3,3.6,1.25); };
        class BtnCT: COMSPEC_SSE_RscButtonNav { idc = 93261; text = "CONTACTS"; sizeEx = SSE_TXT_S; action = "['contacts'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(4.42,20.3,3.6,1.25); };
        class BtnMSG: COMSPEC_SSE_RscButtonNav { idc = 93262; text = "MESSAGES"; sizeEx = SSE_TXT_S; action = "['messages'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(8.34,20.3,3.6,1.25); };
        class BtnCALL: COMSPEC_SSE_RscButtonNav { idc = 93263; text = "APPELS"; sizeEx = SSE_TXT_S; action = "['calls'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(12.26,20.3,3.6,1.25); };
        class BtnFILE: COMSPEC_SSE_RscButtonNav { idc = 93264; text = "FICHIERS"; sizeEx = SSE_TXT_S; action = "['files'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(16.18,20.3,3.6,1.25); };
        class BtnPIC: COMSPEC_SSE_RscButtonNav { idc = 93265; text = "PHOTOS"; sizeEx = SSE_TXT_S; action = "['photos'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(20.1,20.3,3.6,1.25); };
        class BtnLOC: COMSPEC_SSE_RscButtonNav { idc = 93266; text = "LIEUX"; sizeEx = SSE_TXT_S; action = "['locations'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(24.02,20.3,3.6,1.25); };
        class BtnDEL: COMSPEC_SSE_RscButtonNav { idc = 93267; text = "SUPPRIMÉS"; sizeEx = SSE_TXT_S; action = "['deleted'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(27.94,20.3,3.6,1.25); };
        class BtnNET: COMSPEC_SSE_RscButtonNav { idc = 93268; text = "RÉSEAU"; sizeEx = SSE_TXT_S; action = "['network'] call comspec_sse_fnc_uiDigitalTab"; SSE_POS(31.86,20.3,3.64,1.25); };
        class BtnBack: COMSPEC_SSE_RscButtonNav { idc = 93269; text = "TERMINAL"; action = "['terminal'] call comspec_sse_fnc_uiOpenScreen"; SSE_POS(0.5,21.7,5.6,1.15); };
        class BtnClose: COMSPEC_SSE_RscButtonClose { idc = 93270; text = "FERMER"; action = "closeDialog 0"; SSE_POS(30,21.7,5.6,1.15); };
    };
};

// ============================================================
// EXPLOITATION DE SITE (idd 93300)
// ============================================================
class COMSPEC_SSE_SiteDialog {
    idd = 93300;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['site'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        SSE_FRAME(COMSPEC_SSE_RscHeader,COMSPEC_SSE_RscAccentLine,93301,"EXPLOITATION DE SITE",-1,"  Complétude · priorités · éléments non traités");
        SSE_SECTION(SecSummary,"SYNTHÈSE",0.5,3.1,35);
        SSE_SECTION(SecList,"ÉLÉMENTS — TRIAGE",0.5,8.7,17.25);
        SSE_SECTION(SecDetail,"DÉTAIL",18.25,8.7,17.25);
    };
    class controls {
        class Summary: COMSPEC_SSE_RscStructuredText { idc = 93310; SSE_POS(0.5,4,35,4.4); };
        class List: COMSPEC_SSE_RscListBox { idc = 93311; SSE_POS(0.5,9.6,17.25,10.1); };
        class Detail: COMSPEC_SSE_RscStructuredText { idc = 93312; SSE_POS(18.25,9.6,17.25,10.1); };
        class BtnTriage: COMSPEC_SSE_RscButton { idc = 93320; text = "TRIAGE"; tooltip = "Classe les éléments par priorité d'exploitation"; action = "[] call comspec_sse_fnc_uiSiteTriage"; SSE_POS(0.5,20.75,6,1.5); };
        class BtnRefresh: COMSPEC_SSE_RscButtonNav { idc = 93321; text = "RAFRAÎCHIR"; action = "['site'] call comspec_sse_fnc_uiRefresh"; SSE_POS(6.8,20.75,6,1.5); };
        class BtnBack: COMSPEC_SSE_RscButtonNav { idc = 93322; text = "TERMINAL"; action = "['terminal'] call comspec_sse_fnc_uiOpenScreen"; SSE_POS(23.2,20.75,6,1.5); };
        class BtnClose: COMSPEC_SSE_RscButtonClose { idc = 93323; text = "FERMER"; action = "closeDialog 0"; SSE_POS(29.5,20.75,6,1.5); };
    };
};

// ============================================================
// GRAPHE DE RENSEIGNEMENT (idd 93350)
// ============================================================
class COMSPEC_SSE_GraphDialog {
    idd = 93350;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['graph'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        SSE_FRAME(COMSPEC_SSE_RscHeader,COMSPEC_SSE_RscAccentLine,93351,"GRAPHE DE RENSEIGNEMENT",-1,"  Nœuds et relations établies sur le terrain");
        SSE_SECTION(SecNodes,"NŒUDS",0.5,3.1,13);
        SSE_SECTION(SecEdges,"RELATIONS",14,3.1,21.5);
    };
    class controls {
        class Nodes: COMSPEC_SSE_RscListBox { idc = 93360; SSE_POS(0.5,4,13,15.7); };
        class Edges: COMSPEC_SSE_RscStructuredText { idc = 93361; SSE_POS(14,4,21.5,15.7); };
        class BtnPivot: COMSPEC_SSE_RscButton { idc = 93370; text = "PIVOT"; tooltip = "Recherche les liens à partir du nœud sélectionné"; action = "[] call comspec_sse_fnc_uiGraphPivot"; SSE_POS(0.5,20.75,6,1.5); };
        class BtnBack: COMSPEC_SSE_RscButtonNav { idc = 93371; text = "TERMINAL"; action = "['terminal'] call comspec_sse_fnc_uiOpenScreen"; SSE_POS(23.2,20.75,6,1.5); };
        class BtnClose: COMSPEC_SSE_RscButtonClose { idc = 93372; text = "FERMER"; action = "closeDialog 0"; SSE_POS(29.5,20.75,6,1.5); };
    };
};

// ============================================================
// PREUVES / CHAÎNE DE CONSERVATION (idd 93400)
// ============================================================
class COMSPEC_SSE_EvidenceDialog {
    idd = 93400;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['evidence'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        SSE_FRAME(COMSPEC_SSE_RscHeader,COMSPEC_SSE_RscAccentLine,93401,"PREUVES — CHAÎNE DE CONSERVATION",-1,"  Pièces saisies · scellés · traçabilité");
        SSE_SECTION(SecList,"PIÈCES",0.5,3.1,16);
        SSE_SECTION(SecDetail,"DÉTAIL DE LA PIÈCE",17,3.1,18.5);
    };
    class controls {
        class List: COMSPEC_SSE_RscListBox { idc = 93410; SSE_POS(0.5,4,16,15.7); };
        class Detail: COMSPEC_SSE_RscStructuredText { idc = 93411; SSE_POS(17,4,18.5,15.7); };
        class BtnBag: COMSPEC_SSE_RscButton { idc = 93420; text = "METTRE SOUS SCELLÉ"; tooltip = "Place la pièce sélectionnée sous scellé"; action = "[] call comspec_sse_fnc_uiBagSelected"; SSE_POS(0.5,20.75,8,1.5); };
        class BtnBack: COMSPEC_SSE_RscButtonNav { idc = 93421; text = "TERMINAL"; action = "['terminal'] call comspec_sse_fnc_uiOpenScreen"; SSE_POS(23.2,20.75,6,1.5); };
        class BtnClose: COMSPEC_SSE_RscButtonClose { idc = 93422; text = "FERMER"; action = "closeDialog 0"; SSE_POS(29.5,20.75,6,1.5); };
    };
};

// ============================================================
// RENSEIGNEMENT MISSION — FUSION (idd 93450)
// ============================================================
class COMSPEC_SSE_MissionIntelDialog {
    idd = 93450;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['mission'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        SSE_FRAME(COMSPEC_SSE_RscHeader,COMSPEC_SSE_RscAccentLine,93451,"RENSEIGNEMENT MISSION — FUSION",-1,"  Ensemble des renseignements recueillis · filtre par fiabilité");
        SSE_SECTION(SecList,"RENSEIGNEMENTS",0.5,4.6,35);
    };
    class controls {
        class Filter: COMSPEC_SSE_RscStructuredText { idc = 93452; colorBackground[] = SSE_C_PANEL_ALT; SSE_POS(0.5,3.1,35,1.2); };
        class List: COMSPEC_SSE_RscListBox { idc = 93453; SSE_POS(0.5,5.5,35,14.2); };
        class BtnAll: COMSPEC_SSE_RscButtonNav { idc = 93460; text = "TOUS"; action = "['ALL'] call comspec_sse_fnc_uiMissionFilter"; SSE_POS(0.5,20.3,5.6,1.25); };
        class BtnObs: COMSPEC_SSE_RscButtonNav { idc = 93461; text = "OBSERVÉ"; tooltip = "Constaté directement sur le terrain"; action = "['OBSERVED'] call comspec_sse_fnc_uiMissionFilter"; SSE_POS(6.4,20.3,5.6,1.25); };
        class BtnRep: COMSPEC_SSE_RscButtonNav { idc = 93462; text = "RAPPORTÉ"; tooltip = "Rapporté par une source, non vérifié"; action = "['REPORTED'] call comspec_sse_fnc_uiMissionFilter"; SSE_POS(12.3,20.3,5.6,1.25); };
        class BtnAss: COMSPEC_SSE_RscButtonNav { idc = 93463; text = "ÉVALUÉ"; tooltip = "Analysé et jugé plausible"; action = "['ASSESSED'] call comspec_sse_fnc_uiMissionFilter"; SSE_POS(18.2,20.3,5.6,1.25); };
        class BtnConf: COMSPEC_SSE_RscButtonNav { idc = 93464; text = "CONFIRMÉ"; tooltip = "Corroboré par plusieurs sources"; action = "['CONFIRMED'] call comspec_sse_fnc_uiMissionFilter"; SSE_POS(24.1,20.3,5.6,1.25); };
        class BtnBack: COMSPEC_SSE_RscButtonNav { idc = 93465; text = "TERMINAL"; action = "['terminal'] call comspec_sse_fnc_uiOpenScreen"; SSE_POS(0.5,21.7,5.6,1.15); };
        class BtnClose: COMSPEC_SSE_RscButtonClose { idc = 93466; text = "FERMER"; action = "closeDialog 0"; SSE_POS(30,21.7,5.6,1.15); };
    };
};

// ============================================================
// CONTRÔLE ZEUS SSE (idd 93500)
// ============================================================
class COMSPEC_SSE_ZeusControlDialog {
    idd = 93500;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "['zeus'] call comspec_sse_fnc_uiOnLoad";

    class controlsBackground {
        SSE_FRAME(COMSPEC_SSE_RscHeaderZeus,COMSPEC_SSE_RscAccentLineZeus,93501,"CONTRÔLE ZEUS SSE — VÉRITÉ / CONNU JOUEURS",-1,"  Réservé au chef de mission · ne pas afficher aux joueurs");
        SSE_SECTION(SecKnown,"CE QUE SAVENT LES JOUEURS",0.5,3.1,17.25);
        SSE_SECTION(SecTruth,"VÉRITÉ COMPLÈTE",18.25,3.1,17.25);
        SSE_SECTION(SecList,"ENTITÉS SSE À PROXIMITÉ",0.5,13.1,35);
    };
    class controls {
        class Known: COMSPEC_SSE_RscStructuredText { idc = 93510; SSE_POS(0.5,4,17.25,8.8); };
        class Truth: COMSPEC_SSE_RscStructuredText { idc = 93511; colorBackground[] = {0.14,0.07,0.06,0.94}; SSE_POS(18.25,4,17.25,8.8); };
        class List: COMSPEC_SSE_RscListBox { idc = 93512; SSE_POS(0.5,14,35,5.7); };
        class BtnGen: COMSPEC_SSE_RscButtonZeus { idc = 93520; text = "BRIEF / GÉNÉRER"; tooltip = "Applique le dataset FALCON autour de vous (ou un brief par défaut)"; action = "[] call comspec_sse_fnc_uiZeusGenerate"; SSE_POS(0.5,20.75,7,1.5); };
        class BtnLink: COMSPEC_SSE_RscButtonZeus { idc = 93521; text = "LIER (PIVOT)"; tooltip = "Crée / affiche les liens pivot du record courant"; action = "[] call comspec_sse_fnc_uiZeusLink"; SSE_POS(7.8,20.75,7,1.5); };
        class BtnExport: COMSPEC_SSE_RscButtonZeus { idc = 93522; text = "EXPORTER LE GRAPHE"; action = "[] call comspec_sse_fnc_uiZeusExport"; SSE_POS(15.1,20.75,7,1.5); };
        class BtnAAR: COMSPEC_SSE_RscButtonZeus { idc = 93523; text = "AAR"; tooltip = "Compte rendu de fin de mission"; action = "[] call comspec_sse_fnc_uiZeusAAR"; SSE_POS(22.4,20.75,5,1.5); };
        class BtnClose: COMSPEC_SSE_RscButtonClose { idc = 93524; text = "FERMER"; action = "closeDialog 0"; SSE_POS(29.5,20.75,6,1.5); };
    };
};
