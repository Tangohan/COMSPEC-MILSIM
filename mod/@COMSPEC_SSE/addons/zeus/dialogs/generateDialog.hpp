// Dialogue Zeus « Générer un profil SSE » (idd 93001).
// Classes COMSPEC_SSE_Rsc* : addon ui (dialogs\base.hpp), déclarées dans config.cpp.
// Rempli par comspec_sse_fnc_openGenerateDialog, validé par comspec_sse_fnc_applyGenerateDialog.

#define SSE_ZPOS(X,Y,W,H) x = SSE_Q(SSE_X(X)); y = SSE_Q(SSE_Y(Y)); w = SSE_Q(SSE_W(W)); h = SSE_Q(SSE_H(H))

class COMSPEC_SSE_GenerateDialog {
    idd = 93001;
    movingEnable = 0;
    enableSimulation = 1;

    class controlsBackground {
        class Dim: COMSPEC_SSE_RscDim { SSE_FULLSCREEN; };
        class BG: COMSPEC_SSE_RscBackground { SSE_ZPOS(11,3.5,18,19.5); };
        class Title: COMSPEC_SSE_RscHeaderZeus {
            text = "GÉNÉRER UN PROFIL SSE";
            SSE_ZPOS(11,3.5,18,1.6);
        };
        class TitleLine: COMSPEC_SSE_RscAccentLineZeus { SSE_ZPOS(11,5.1,18,0.12); };
        class LblProfile: COMSPEC_SSE_RscText {
            text = "Profil";
            tooltip = "Rôle narratif du sujet : oriente contacts, messages, documents.";
            SSE_ZPOS(11.5,7.3,6,1.15);
        };
        class LblRich: COMSPEC_SSE_RscText {
            text = "Richesse";
            tooltip = "Quantité d'éléments exploitables générés.";
            SSE_ZPOS(11.5,8.8,6,1.15);
        };
        class SecContent: COMSPEC_SSE_RscSection {
            text = "CONTENU GÉNÉRÉ";
            SSE_ZPOS(11.5,10.4,17,0.9);
        };
        class LblId: COMSPEC_SSE_RscText {
            text = "Identité réelle (Arma / Eden)";
            tooltip = "Décoché : le terminal invente un nom SSE au lieu de reprendre l'identité de l'unité.";
            SSE_ZPOS(13.3,11.4,15,1.15);
        };
        class LblPhone: COMSPEC_SSE_RscText {
            text = "Téléphone";
            tooltip = "Décoché : aucun téléphone généré sur le sujet.";
            SSE_ZPOS(13.3,12.7,15,1.15);
        };
        class LblDoc: COMSPEC_SSE_RscText {
            text = "Documents";
            tooltip = "Décoché : aucun document papier généré.";
            SSE_ZPOS(13.3,14,15,1.15);
        };
        class LblBio: COMSPEC_SSE_RscText {
            text = "Biométrie";
            tooltip = "Décoché : pas d'empreintes / iris / ADN pour SEEK.";
            SSE_ZPOS(13.3,15.3,15,1.15);
        };
        class LblNet: COMSPEC_SSE_RscText {
            text = "Lier les cibles entre elles";
            tooltip = "Crée des liens « associé » entre les cibles sélectionnées (graphe).";
            SSE_ZPOS(13.3,16.6,15,1.15);
        };
        class LblNoise: COMSPEC_SSE_RscText {
            text = "Données inutiles (bruit)";
            tooltip = "Probabilité d'ajouter des messages sans intérêt, pour masquer les vrais indices.";
            SSE_ZPOS(11.5,18,12,1.1);
        };
        class Footer: COMSPEC_SSE_RscPanel { SSE_ZPOS(11,20.6,18,2.4); };
    };

    class controls {
        class Targets: COMSPEC_SSE_RscLabel {
            idc = 93018;
            text = "";
            SSE_ZPOS(11.5,5.6,17,1.2);
        };
        class Profile: COMSPEC_SSE_RscCombo {
            idc = 93010;
            SSE_ZPOS(18,7.3,10.5,1.15);
        };
        class Rich: COMSPEC_SSE_RscCombo {
            idc = 93011;
            SSE_ZPOS(18,8.8,10.5,1.15);
        };
        class CbId: COMSPEC_SSE_RscCheckBox { idc = 93012; SSE_ZPOS(11.5,11.4,1.5,1.15); };
        class CbPhone: COMSPEC_SSE_RscCheckBox { idc = 93013; SSE_ZPOS(11.5,12.7,1.5,1.15); };
        class CbDoc: COMSPEC_SSE_RscCheckBox { idc = 93014; SSE_ZPOS(11.5,14,1.5,1.15); };
        class CbBio: COMSPEC_SSE_RscCheckBox { idc = 93015; SSE_ZPOS(11.5,15.3,1.5,1.15); };
        class CbNet: COMSPEC_SSE_RscCheckBox { idc = 93016; SSE_ZPOS(11.5,16.6,1.5,1.15); };
        class NoiseValue: COMSPEC_SSE_RscText {
            idc = 93019;
            text = "25 %";
            style = 1;
            SSE_ZPOS(24.5,18,4,1.1);
        };
        class Noise: COMSPEC_SSE_RscSlider {
            idc = 93017;
            onSliderPosChanged = "params ['_c','_v']; ((ctrlParent _c) displayCtrl 93019) ctrlSetText format ['%1 %2', round _v, '%'];";
            SSE_ZPOS(11.5,19.1,17,1);
        };
        class BtnGen: COMSPEC_SSE_RscButtonZeus {
            idc = 93020;
            text = "GÉNÉRER";
            action = "[] call comspec_sse_fnc_applyGenerateDialog";
            SSE_ZPOS(11.5,21,8.2,1.5);
        };
        class BtnClose: COMSPEC_SSE_RscButtonClose {
            idc = 93021;
            text = "FERMER";
            action = "closeDialog 0";
            SSE_ZPOS(20.3,21,8.2,1.5);
        };
    };
};
