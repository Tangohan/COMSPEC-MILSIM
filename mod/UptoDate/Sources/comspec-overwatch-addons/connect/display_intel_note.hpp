// Rédacteur de fiche de renseignement simplifiée (idd 9982).
//
// Contrairement au terminal SEEK, qui imite un appareil posé au milieu de
// l'écran, ce rédacteur occupe TOUTE la surface de l'ATAK : c'est une
// application plein cadre, ouverte comme enfant de cTab_Android_dlg.
//
// Disposition, de haut en bas :
//   bandeau      date (gauche) · lieu (droite)
//   étiquettes   thèmes colorés + sigle du type de fiche
//   scène        deux volets exclusifs — rédaction / pièces jointes
//   barre basse  quitter · compact ou plein cadre · valider
//
// Les deux volets partagent le même dialog : seuls les contrôles du volet
// courant sont affichés (même principe que les pages du SEEK). Tout contrôle
// susceptible d'être masqué porte donc un IDC.
//
// IDC :
//   9610 fond bandeau        9611 date            9612 lieu
//   9613 fond étiquettes     9614 étiquettes
//   9615 fond du cadre       9616 cadre de rédaction   9617 compteur
//   9618 message d'état
//   9620 poignée « Fiche »   9621 poignée « Pièces jointes »
//   9622 fond barre basse    9623 quitter   9624 cadrage   9625 valider
//   9626 bouton trombone
//   9630 titre pièces jointes    9631 aide pièces jointes
//   9632-9635 légendes des pièces     9636-9639 boutons « retirer »
//   9640 joindre une photo            9641 capture d'écran
//   9642 pièce depuis un fichier local   9643 revenir à la rédaction
//   9650 fond du volet contexte   9651 titre contexte
//   9652 date saisie   9653 lieu saisie   9654 repère   9655 code dossier
//   9656 type de fiche  9657 urgence  9658 recueil
//   9660-9676 bascules de thème
//   9680 fermer le volet contexte
//   9690-9698 libellés du volet contexte
//   9710-9713 aperçus image           9714 liste de choix photo
//   9715-9718 fonds des emplacements

#define NT_X        (safezoneX)
#define NT_Y        (safezoneY)
#define NT_W        (safezoneW)
#define NT_H        (safezoneH)

#define NT_TOP_H    (0.038 * NT_H)
#define NT_TAG_H    (0.036 * NT_H)
#define NT_BOT_H    (0.062 * NT_H)
#define NT_STAGE_Y  (NT_Y + NT_TOP_H + NT_TAG_H)
#define NT_STAGE_H  (NT_H - NT_TOP_H - NT_TAG_H - NT_BOT_H)

#define NT_PAD_X    (0.045 * NT_W)
#define NT_IN_X     (NT_X + NT_PAD_X)
#define NT_IN_W     (NT_W - 2 * NT_PAD_X)

// Cadre de rédaction : laisse la place au compteur et au trombone en bas.
#define NT_ED_Y     (NT_STAGE_Y + 0.012 * NT_H)
#define NT_ED_H     (NT_STAGE_H - 0.098 * NT_H)
#define NT_CNT_Y    (NT_ED_Y + NT_ED_H + 0.004 * NT_H)

// Volet contexte : colonne à droite. Il commence sous les étiquettes — sinon il
// passerait derrière les pastilles de thème, qui restent visibles en permanence.
#define NT_SHEET_W  (0.34 * NT_W)
#define NT_SHEET_X  (NT_X + NT_W - NT_SHEET_W)
#define NT_SHEET_Y  (NT_STAGE_Y)
#define NT_SHEET_H  (NT_H - NT_TOP_H - NT_TAG_H - NT_BOT_H)
#define NT_SH_IN_X  (NT_SHEET_X + 0.010 * NT_W)
#define NT_SH_IN_W  (NT_SHEET_W - 0.020 * NT_W)
#define NT_SH_ROW   (0.048 * NT_H)
#define NT_SH_Y(n)  (NT_SHEET_Y + 0.052 * NT_H + (n) * NT_SH_ROW)
#define NT_THEME_H  (0.022 * NT_H)
#define NT_THEME_STEP (0.024 * NT_H)
#define NT_THEME_Y0 (NT_SH_Y(5) + 0.026 * NT_H)
#define NT_THEME_YN(n) (NT_THEME_Y0 + (n) * NT_THEME_STEP)

// Boutons ronds du volet pièces jointes (centrés en bas, comme la maquette).
#define NT_FAB_W    (0.030 * NT_W)
#define NT_FAB_H    (0.052 * NT_H)
#define NT_FAB_Y    (NT_STAGE_Y + NT_STAGE_H - 0.070 * NT_H)
#define NT_FAB_X(i) (NT_X + 0.5 * NT_W - 2.1 * NT_FAB_W + (i) * (1.4 * NT_FAB_W))

// Emplacements de pièces jointes : une rangée de quatre (aperçu + légende).
#define NT_SLOT_W   ((NT_IN_W - 3 * (0.012 * NT_W)) / 4)
#define NT_SLOT_PIC_H (0.145 * NT_H)
#define NT_SLOT_CAP_H (0.045 * NT_H)
#define NT_SLOT_H   (NT_SLOT_PIC_H + NT_SLOT_CAP_H)
#define NT_SLOT_X(i) (NT_IN_X + (i) * (NT_SLOT_W + 0.012 * NT_W))
#define NT_SLOT_Y   (NT_STAGE_Y + 0.060 * NT_H)

class COMSPEC_IntelNote_Dialog {
    idd = 9982;
    movingEnable = 0;
    onLoad = "uiNamespace setVariable ['COMSPEC_IntelNote_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_intelNoteOnLoad;";
    onUnload = "[] call comspec_overwatch_connect_fnc_intelNoteOnUnload;";

    class Controls {
        // Voile autour du cadre quand la fiche n’occupe pas tout l’écran.
        class Dimmer: RscText {
            idc = 9600;
            x = safezoneX; y = safezoneY; w = safezoneW; h = safezoneH;
            colorBackground[] = {0, 0, 0, 0};
        };
        // ================= FOND PLEIN CADRE =================
        class Backdrop: RscText {
            idc = 9601;
            x = NT_X; y = NT_Y; w = NT_W; h = NT_H;
            colorBackground[] = {0.122, 0.125, 0.137, 0.98};
        };

        // ================= BANDEAU DATE / LIEU =================
        class TopBar: RscText {
            idc = 9610;
            x = NT_X; y = NT_Y; w = NT_W; h = NT_TOP_H;
            colorBackground[] = {0.043, 0.043, 0.051, 1};
        };
        class DateLabel: RscStructuredText {
            idc = 9611;
            text = "";
            x = (NT_X + 0.008 * NT_W); y = NT_Y; w = (0.30 * NT_W); h = NT_TOP_H;
        };
        class PlaceLabel: RscStructuredText {
            idc = 9612;
            text = "";
            x = (NT_X + 0.62 * NT_W); y = NT_Y; w = (0.37 * NT_W); h = NT_TOP_H;
        };

        // ================= ÉTIQUETTES =================
        class TagBar: RscText {
            idc = 9613;
            x = NT_X; y = (NT_Y + NT_TOP_H); w = NT_W; h = NT_TAG_H;
            colorBackground[] = {0.043, 0.043, 0.051, 1};
        };
        // Les étiquettes colorées sont des contrôles créés à la volée : le texte
        // structuré d'Arma ne sait pas peindre un fond derrière un fragment.
        // Ce contrôle ne sert que de repli lorsque la création échoue.
        class TagText: RscStructuredText {
            idc = 9614;
            text = "";
            x = (NT_X + 0.008 * NT_W); y = (NT_Y + NT_TOP_H); w = (0.80 * NT_W); h = NT_TAG_H;
        };

        // ================= VOLET RÉDACTION =================
        class EditorFrame: RscText {
            idc = 9615;
            x = NT_IN_X; y = NT_ED_Y; w = NT_IN_W; h = NT_ED_H;
            colorBackground[] = {0.192, 0.196, 0.208, 1};
        };
        class Editor: RscEdit {
            idc = 9616;
            style = 16; // multi-ligne
            x = (NT_IN_X + 0.006 * NT_W); y = (NT_ED_Y + 0.006 * NT_H);
            w = (NT_IN_W - 0.012 * NT_W); h = (NT_ED_H - 0.012 * NT_H);
            colorBackground[] = {0, 0, 0, 0};
            colorText[] = {0.957, 0.961, 0.965, 1};
            colorSelection[] = {0.24, 0.35, 0.75, 0.5};
            sizeEx = 0.034;
            autocomplete = "";
            canModify = 1;
            tooltip = "Écrivez ici ce que vous avez constaté. 1000 caractères au maximum.";
        };
        // Pastille du compteur : le fond est un vrai fond de contrôle, sa couleur
        // change avec le remplissage (gris en cours de saisie, rouge à la limite).
        class Counter: RscStructuredText {
            idc = 9617;
            text = "";
            x = (NT_IN_X + NT_IN_W - 0.06 * NT_W); y = NT_CNT_Y; w = (0.06 * NT_W); h = (0.022 * NT_H);
            colorBackground[] = {0.863, 0.149, 0.149, 1};
        };
        class StatusText: RscStructuredText {
            idc = 9618;
            text = "";
            x = NT_IN_X; y = NT_CNT_Y; w = (NT_IN_W - 0.07 * NT_W); h = (0.022 * NT_H);
        };
        class BtnClip: COMSPEC_RscButton {
            idc = 9626;
            text = "PIÈCES JOINTES";
            x = (NT_X + 0.5 * NT_W - 0.075 * NT_W); y = (NT_STAGE_Y + NT_STAGE_H - 0.050 * NT_H);
            w = (0.15 * NT_W); h = (0.038 * NT_H);
            sizeEx = 0.032;
            colorBackground[] = {0.949, 0.957, 0.965, 0.95};
            colorBackgroundActive[] = {1, 1, 1, 1};
            colorFocused[] = {1, 1, 1, 1};
            colorText[] = {0.078, 0.082, 0.169, 1};
            tooltip = "Photographies, captures et documents joints à la fiche.";
            action = "['pieces'] call comspec_overwatch_connect_fnc_intelNotePane;";
        };

        // ================= POIGNÉES LATÉRALES =================
        class EdgeLeft: COMSPEC_RscButton {
            idc = 9620;
            text = "FICHE";
            x = NT_X; y = (NT_STAGE_Y + 0.5 * NT_STAGE_H - 0.045 * NT_H);
            w = (0.024 * NT_W); h = (0.090 * NT_H);
            sizeEx = 0.028;
            colorBackground[] = {0.231, 0.235, 0.251, 0.95};
            tooltip = "Revenir au cadre de rédaction.";
            action = "['redaction'] call comspec_overwatch_connect_fnc_intelNotePane;";
        };
        class EdgeRight: EdgeLeft {
            idc = 9621;
            text = "PJ";
            x = (NT_X + NT_W - 0.024 * NT_W);
            tooltip = "Ouvrir les pièces jointes.";
            action = "['pieces'] call comspec_overwatch_connect_fnc_intelNotePane;";
        };

        // ================= VOLET PIÈCES JOINTES =================
        class PiecesTitle: RscStructuredText {
            idc = 9630;
            text = "";
            x = NT_IN_X; y = (NT_STAGE_Y + 0.010 * NT_H); w = (0.50 * NT_W); h = (0.026 * NT_H);
        };
        class PiecesHelp: RscStructuredText {
            idc = 9631;
            text = "<t size='0.64' color='#8b929c'>Quatre pièces au maximum. PHOTO joint une capture déjà prise. CAPTURE photographie la scène à la validation.</t>";
            x = NT_IN_X; y = (NT_STAGE_Y + 0.034 * NT_H); w = (0.70 * NT_W); h = (0.032 * NT_H);
        };
        class SlotBg0: RscText {
            idc = 9715;
            x = NT_SLOT_X(0); y = NT_SLOT_Y; w = NT_SLOT_W; h = NT_SLOT_PIC_H;
            colorBackground[] = {0.165, 0.169, 0.180, 1};
        };
        class SlotBg1: SlotBg0 { idc = 9716; x = NT_SLOT_X(1); };
        class SlotBg2: SlotBg0 { idc = 9717; x = NT_SLOT_X(2); };
        class SlotBg3: SlotBg0 { idc = 9718; x = NT_SLOT_X(3); };
        class SlotPic0: RscPictureKeepAspect {
            idc = 9710;
            text = "";
            x = NT_SLOT_X(0); y = NT_SLOT_Y; w = NT_SLOT_W; h = NT_SLOT_PIC_H;
            colorText[] = {1, 1, 1, 1};
        };
        class SlotPic1: SlotPic0 { idc = 9711; x = NT_SLOT_X(1); };
        class SlotPic2: SlotPic0 { idc = 9712; x = NT_SLOT_X(2); };
        class SlotPic3: SlotPic0 { idc = 9713; x = NT_SLOT_X(3); };
        class Slot0: RscStructuredText {
            idc = 9632;
            text = "";
            x = NT_SLOT_X(0); y = (NT_SLOT_Y + NT_SLOT_PIC_H); w = NT_SLOT_W; h = NT_SLOT_CAP_H;
            colorBackground[] = {0.122, 0.125, 0.137, 1};
        };
        class Slot1: Slot0 { idc = 9633; x = NT_SLOT_X(1); };
        class Slot2: Slot0 { idc = 9634; x = NT_SLOT_X(2); };
        class Slot3: Slot0 { idc = 9635; x = NT_SLOT_X(3); };
        class PhotoPicker: RscListBox {
            idc = 9714;
            x = NT_IN_X; y = NT_SLOT_Y; w = NT_IN_W; h = NT_SLOT_H;
            colorBackground[] = {0.063, 0.067, 0.078, 0.98};
            colorSelectBackground[] = {0.165, 0.141, 0.498, 1};
            colorSelect[] = {1, 1, 1, 1};
            colorText[] = {0.957, 0.961, 0.965, 1};
            sizeEx = 0.032;
            rowHeight = 0.032;
            onLBSelChanged = "_this call comspec_overwatch_connect_fnc_intelNotePickPhoto;";
        };
        class Drop0: COMSPEC_RscButtonDanger {
            idc = 9636;
            text = "Retirer";
            x = NT_SLOT_X(0); y = (NT_SLOT_Y + NT_SLOT_H + 0.004 * NT_H);
            w = NT_SLOT_W; h = (0.028 * NT_H);
            sizeEx = 0.028;
            action = "[0] call comspec_overwatch_connect_fnc_intelNoteDropPiece;";
        };
        class Drop1: Drop0 { idc = 9637; x = NT_SLOT_X(1); action = "[1] call comspec_overwatch_connect_fnc_intelNoteDropPiece;"; };
        class Drop2: Drop0 { idc = 9638; x = NT_SLOT_X(2); action = "[2] call comspec_overwatch_connect_fnc_intelNoteDropPiece;"; };
        class Drop3: Drop0 { idc = 9639; x = NT_SLOT_X(3); action = "[3] call comspec_overwatch_connect_fnc_intelNoteDropPiece;"; };

        class FabGallery: COMSPEC_RscButton {
            idc = 9640;
            text = "PHOTO";
            x = NT_FAB_X(0); y = NT_FAB_Y; w = (1.2 * NT_FAB_W); h = NT_FAB_H;
            sizeEx = 0.028;
            colorBackground[] = {0.122, 0.106, 0.420, 0.95};
            colorBackgroundActive[] = {0.165, 0.141, 0.498, 1};
            colorFocused[] = {0.165, 0.141, 0.498, 1};
            tooltip = "Joindre une photographie déjà prise (Photos ATAK ou captures d’écran du jeu).";
            action = "['galerie'] call comspec_overwatch_connect_fnc_intelNoteAddPiece;";
        };
        class FabCapture: FabGallery {
            idc = 9641;
            text = "CAPTURE";
            x = NT_FAB_X(1.5);
            tooltip = "Prendre une capture de la scène pour l’attacher à la fiche.";
            action = "['capture'] call comspec_overwatch_connect_fnc_intelNoteAddPiece;";
        };
        class FabCroquis: FabGallery {
            idc = 9642;
            text = "RELEVÉ";
            x = NT_FAB_X(3);
            tooltip = "Joindre le relevé de position et l’instant courant comme pièce écrite.";
            action = "['releve'] call comspec_overwatch_connect_fnc_intelNoteAddPiece;";
        };
        class FabBack: COMSPEC_RscButton {
            idc = 9643;
            text = "REVENIR";
            x = NT_FAB_X(4.5); y = NT_FAB_Y; w = (1.2 * NT_FAB_W); h = NT_FAB_H;
            sizeEx = 0.028;
            colorBackground[] = {0.678, 0.686, 0.702, 0.95};
            colorText[] = {0.12, 0.14, 0.18, 1};
            tooltip = "Revenir au cadre de rédaction.";
            action = "['redaction'] call comspec_overwatch_connect_fnc_intelNotePane;";
        };

        // ================= VOLET CONTEXTE =================
        class SheetBackdrop: RscText {
            idc = 9650;
            x = NT_SHEET_X; y = NT_SHEET_Y; w = NT_SHEET_W; h = NT_SHEET_H;
            colorBackground[] = {0.090, 0.094, 0.106, 0.98};
        };
        class SheetTitle: RscStructuredText {
            idc = 9651;
            text = "<t size='0.82' color='#f4f5f6'>Contexte de la fiche</t>";
            x = NT_SH_IN_X; y = (NT_SHEET_Y + 0.010 * NT_H); w = NT_SH_IN_W; h = (0.034 * NT_H);
        };
        class LabelDate: RscStructuredText {
            idc = 9690;
            text = "<t size='0.68' color='#c5ccd4'>DATE ET HEURE DE L’ÉVÉNEMENT</t>";
            x = NT_SH_IN_X; y = NT_SH_Y(0); w = NT_SH_IN_W; h = (0.024 * NT_H);
        };
        class EditDate: RscEdit {
            idc = 9652;
            x = NT_SH_IN_X; y = (NT_SH_Y(0) + 0.024 * NT_H); w = NT_SH_IN_W; h = (0.028 * NT_H);
            colorBackground[] = {0.063, 0.067, 0.078, 1};
            colorText[] = {0.957, 0.961, 0.965, 1};
            sizeEx = 0.034;
            autocomplete = "";
            tooltip = "Format jour/mois/année heure:minute.";
        };
        class LabelPlace: LabelDate {
            idc = 9691;
            text = "<t size='0.68' color='#c5ccd4'>LIEU</t>";
            y = NT_SH_Y(1);
        };
        class EditPlace: EditDate {
            idc = 9653;
            y = (NT_SH_Y(1) + 0.024 * NT_H);
            tooltip = "Commune, secteur, axe ou point de repère.";
        };
        class LabelGrid: LabelDate {
            idc = 9692;
            text = "<t size='0.68' color='#c5ccd4'>REPÈRE (CARROYAGE)</t>";
            y = NT_SH_Y(2);
        };
        class EditGrid: EditDate {
            idc = 9654;
            y = (NT_SH_Y(2) + 0.024 * NT_H);
            tooltip = "Prérempli avec votre carroyage courant.";
        };
        class LabelKind: LabelDate {
            idc = 9693;
            text = "<t size='0.68' color='#c5ccd4'>TYPE DE FICHE</t>";
            y = NT_SH_Y(3);
        };
        class ComboKind: RscCombo {
            idc = 9656;
            x = NT_SH_IN_X; y = (NT_SH_Y(3) + 0.024 * NT_H); w = NT_SH_IN_W; h = (0.028 * NT_H);
            colorBackground[] = {0.063, 0.067, 0.078, 1};
            colorText[] = {0.957, 0.961, 0.965, 1};
            colorSelectBackground[] = {0.165, 0.141, 0.498, 1};
            sizeEx = 0.032;
            onLBSelChanged = "[] call comspec_overwatch_connect_fnc_intelNoteRefresh;";
        };
        class LabelUrgency: LabelDate {
            idc = 9694;
            text = "<t size='0.68' color='#c5ccd4'>URGENCE</t>";
            y = NT_SH_Y(4);
            w = (NT_SH_IN_W / 2 - 0.003 * NT_W);
        };
        class ComboUrgency: ComboKind {
            idc = 9657;
            y = (NT_SH_Y(4) + 0.024 * NT_H);
            w = (NT_SH_IN_W / 2 - 0.003 * NT_W);
        };
        class LabelSource: LabelDate {
            idc = 9698;
            text = "<t size='0.68' color='#c5ccd4'>RECUEIL</t>";
            x = (NT_SH_IN_X + NT_SH_IN_W / 2 + 0.003 * NT_W);
            y = NT_SH_Y(4);
            w = (NT_SH_IN_W / 2 - 0.003 * NT_W);
        };
        class ComboSource: ComboKind {
            idc = 9658;
            x = (NT_SH_IN_X + NT_SH_IN_W / 2 + 0.003 * NT_W);
            y = (NT_SH_Y(4) + 0.024 * NT_H);
            w = (NT_SH_IN_W / 2 - 0.003 * NT_W);
        };
        class LabelThemes: LabelDate {
            idc = 9695;
            text = "<t size='0.68' color='#c5ccd4'>THÈMES — 4 AU MAXIMUM</t>";
            y = NT_SH_Y(5);
        };
        // Bascules de thème : deux colonnes de neuf (17 thèmes + un emplacement libre).
        class Theme0: COMSPEC_RscButton {
            idc = 9660;
            text = "";
            x = NT_SH_IN_X;
            y = NT_THEME_YN(0);
            w = (NT_SH_IN_W / 2 - 0.003 * NT_W); h = NT_THEME_H;
            sizeEx = 0.026;
            colorBackground[] = {0.063, 0.067, 0.078, 1};
            action = "[0] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;";
        };
        class Theme1: Theme0 { idc = 9661; y = NT_THEME_YN(1); action = "[1] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme2: Theme0 { idc = 9662; y = NT_THEME_YN(2); action = "[2] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme3: Theme0 { idc = 9663; y = NT_THEME_YN(3); action = "[3] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme4: Theme0 { idc = 9664; y = NT_THEME_YN(4); action = "[4] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme5: Theme0 { idc = 9665; y = NT_THEME_YN(5); action = "[5] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme6: Theme0 { idc = 9666; y = NT_THEME_YN(6); action = "[6] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme7: Theme0 { idc = 9667; y = NT_THEME_YN(7); action = "[7] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme8: Theme0 { idc = 9668; y = NT_THEME_YN(8); action = "[8] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme9: Theme0 {
            idc = 9669;
            x = (NT_SH_IN_X + NT_SH_IN_W / 2 + 0.003 * NT_W);
            action = "[9] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;";
        };
        class Theme10: Theme9 { idc = 9670; y = NT_THEME_YN(1); action = "[10] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme11: Theme9 { idc = 9671; y = NT_THEME_YN(2); action = "[11] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme12: Theme9 { idc = 9672; y = NT_THEME_YN(3); action = "[12] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme13: Theme9 { idc = 9673; y = NT_THEME_YN(4); action = "[13] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme14: Theme9 { idc = 9674; y = NT_THEME_YN(5); action = "[14] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme15: Theme9 { idc = 9675; y = NT_THEME_YN(6); action = "[15] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };
        class Theme16: Theme9 { idc = 9676; y = NT_THEME_YN(7); action = "[16] call comspec_overwatch_connect_fnc_intelNoteToggleTheme;"; };

        class LabelCase: LabelDate {
            idc = 9696;
            text = "<t size='0.68' color='#c5ccd4'>RATTACHER À UN DOSSIER (FACULTATIF)</t>";
            y = (NT_THEME_YN(8) + NT_THEME_H + 0.010 * NT_H);
        };
        class EditCase: EditDate {
            idc = 9655;
            y = (NT_THEME_YN(8) + NT_THEME_H + 0.034 * NT_H);
            tooltip = "Laissez vide si vous ne connaissez pas la référence : le bureau classera la fiche.";
        };
        class SheetHint: RscStructuredText {
            idc = 9697;
            text = "<t size='0.62' color='#a8b0b8'>Une fiche n’identifie personne et ne vaut pas preuve : elle consigne un constat daté et situé.</t>";
            x = NT_SH_IN_X; y = (NT_THEME_YN(8) + NT_THEME_H + 0.068 * NT_H); w = NT_SH_IN_W; h = (0.056 * NT_H);
        };
        class BtnSheetClose: COMSPEC_RscButtonAccent {
            idc = 9680;
            text = "REVENIR À LA RÉDACTION";
            x = NT_SH_IN_X; y = (NT_SHEET_Y + NT_SHEET_H - 0.048 * NT_H);
            w = NT_SH_IN_W; h = (0.038 * NT_H);
            sizeEx = 0.032;
            action = "['redaction'] call comspec_overwatch_connect_fnc_intelNotePane;";
        };

        // ================= BARRE BASSE =================
        class BottomBar: RscText {
            idc = 9622;
            x = NT_X; y = (NT_Y + NT_H - NT_BOT_H); w = NT_W; h = NT_BOT_H;
            colorBackground[] = {0.122, 0.106, 0.420, 1};
        };
        class BtnQuit: COMSPEC_RscButton {
            idc = 9623;
            text = "QUITTER";
            x = (NT_X + 0.010 * NT_W); y = (NT_Y + NT_H - NT_BOT_H + 0.010 * NT_H);
            w = (0.10 * NT_W); h = (0.036 * NT_H);
            sizeEx = 0.032;
            colorBackground[] = {0.078, 0.067, 0.290, 0.95};
            colorBackgroundActive[] = {0.114, 0.098, 0.380, 1};
            colorFocused[] = {0.114, 0.098, 0.380, 1};
            tooltip = "Ferme le rédacteur. Le brouillon est conservé pour la prochaine ouverture.";
            action = "[] call comspec_overwatch_connect_fnc_intelNoteClose;";
        };
        class BtnContext: BtnQuit {
            idc = 9624;
            text = "CONTEXTE : DATE, LIEU, THÈMES";
            x = (NT_X + 0.5 * NT_W - 0.14 * NT_W);
            w = (0.28 * NT_W);
            tooltip = "Date, lieu, repère, type de fiche, thèmes et urgence.";
            action = "['contexte'] call comspec_overwatch_connect_fnc_intelNotePane;";
        };
        class BtnValidate: COMSPEC_RscButtonAccent {
            idc = 9625;
            text = "VALIDER ET TRANSMETTRE";
            x = (NT_X + NT_W - 0.22 * NT_W); y = (NT_Y + NT_H - NT_BOT_H + 0.010 * NT_H);
            w = (0.21 * NT_W); h = (0.036 * NT_H);
            sizeEx = 0.032;
            colorBackground[] = {0.063, 0.725, 0.506, 0.95};
            colorBackgroundActive[] = {0.020, 0.588, 0.412, 1};
            colorFocused[] = {0.020, 0.588, 0.412, 1};
            colorText[] = {0.043, 0.129, 0.098, 1};
            tooltip = "Transmet la fiche au bureau SSE, puis envoie les pièces jointes.";
            action = "[] call comspec_overwatch_connect_fnc_intelNoteSubmit;";
        };
    };
};
