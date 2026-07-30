// Terminal biométrique SEEK — enrôlement personne (idd 9991)
// Châssis durci inspiré des terminaux d’enrôlement de terrain : bandeau d’état,
// colonne identité, colonne relevé (platine du lecteur, analyse, LCD), pied procédure.
//
// IDC :
//   9500 bandeau contexte cible        9513 ligne d’état transmission
//   9501 nom      9502 prénom          9514 bouton empreintes
//   9503 alias    9504 âge             9515 bouton photo visage
//   9505 statut   9506 circonstances   9516 enregistrer   9517 annuler
//   9507 nationalité  9508 langue      9518 code dossier SSE
//   9509 signes distinctifs            9519 bouton signature ATAK
//   9510 affiliation                   9520 bloc signature
//   9511 armement détecté              9521 constat de terrain (ACE Medical)
//   9512 déclarations                  9522 analyse biométrique
//                                      9523 bouton iris   9524 bouton ADN
//                                      9525 bandeau LCD  9526 barre d’état
//                                      9527 bouton requête d’identité

#define SEEK_X      (0.235 * safezoneW + safezoneX)
#define SEEK_W      (0.530 * safezoneW)
#define SEEK_Y      (0.040 * safezoneH + safezoneY)
#define SEEK_H      (0.920 * safezoneH)

#define COL_L_X     (0.256 * safezoneW + safezoneX)
#define COL_L_W     (0.239 * safezoneW)
#define COL_R_X     (0.506 * safezoneW + safezoneX)
#define COL_R_W     (0.239 * safezoneW)
#define COL_FULL_W  (0.489 * safezoneW)
#define COL_LB_X    (0.378 * safezoneW + safezoneX)
#define COL_HALF_W  (0.117 * safezoneW)

#define SEEK_LBL    (0.014 * safezoneH)
#define SEEK_FIELD  (0.029 * safezoneH)

// Habillage de l’appareil.
// Le châssis est dessiné en contrôles : le terminal fonctionne sans aucun asset.
// Pour superposer l’illustration du SEEK, convertir l’image en .paa, la déposer dans
// connect/img/device/ puis renseigner le chemin ci-dessous — les contrôles se
// superposent alors à l’image, qui remplace le châssis dessiné.
//   #define SEEK_CHASSIS_TEXTURE "\z\comspec_overwatch\addons\connect\img\device\seek_chassis.paa"
#define SEEK_CHASSIS_TEXTURE ""

class COMSPEC_SsePerson_Dialog {
    idd = 9991;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_ssePersonDialogOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', displayNull]; uiNamespace setVariable ['COMSPEC_SsePerson_Target', objNull]; uiNamespace setVariable ['COMSPEC_SsePerson_Samples', []]; uiNamespace setVariable ['COMSPEC_SsePerson_Signature', []];";

    class Controls {
        // ---------------- Châssis ----------------
        class Chassis: RscText {
            idc = -1;
            x = SEEK_X; y = SEEK_Y; w = SEEK_W; h = SEEK_H;
            colorBackground[] = {0.043, 0.047, 0.051, 0.98};
        };
        class BumperTL: RscText {
            idc = -1;
            x = SEEK_X; y = SEEK_Y; w = (0.030 * safezoneW); h = (0.022 * safezoneH);
            colorBackground[] = {0.086, 0.094, 0.098, 1};
        };
        class BumperTR: BumperTL { x = (0.735 * safezoneW + safezoneX); };
        class BumperBL: BumperTL { y = (0.938 * safezoneH + safezoneY); };
        class BumperBR: BumperTL { x = (0.735 * safezoneW + safezoneX); y = (0.938 * safezoneH + safezoneY); };

        // Poignées latérales — rappel du gabarit de l’appareil.
        class GripLeft: RscText {
            idc = -1;
            x = (0.239 * safezoneW + safezoneX); y = (0.300 * safezoneH + safezoneY);
            w = (0.014 * safezoneW); h = (0.400 * safezoneH);
            colorBackground[] = {0.075, 0.082, 0.086, 1};
        };
        class GripRight: GripLeft { x = (0.748 * safezoneW + safezoneX); };

        // Illustration de l’appareil : dessinée par-dessus le châssis, sous l’écran.
        // Reste invisible tant que SEEK_CHASSIS_TEXTURE est vide.
        class ChassisTexture: RscPicture {
            idc = -1;
            text = SEEK_CHASSIS_TEXTURE;
            x = SEEK_X; y = SEEK_Y; w = SEEK_W; h = SEEK_H;
        };

        // ---------------- Écran ----------------
        class Screen: RscText {
            idc = -1;
            x = (0.248 * safezoneW + safezoneX); y = (0.055 * safezoneH + safezoneY);
            w = (0.505 * safezoneW); h = (0.762 * safezoneH);
            colorBackground[] = {0.024, 0.078, 0.086, 0.99};
        };
        class ScreenAccent: RscText {
            idc = -1;
            x = (0.248 * safezoneW + safezoneX); y = (0.055 * safezoneH + safezoneY);
            w = (0.505 * safezoneW); h = (0.0035 * safezoneH);
            colorBackground[] = {0.24, 0.87, 0.55, 0.95};
        };

        // ---------------- Barre d’état de l’appareil ----------------
        class StatusBar: RscText {
            idc = -1;
            x = (0.248 * safezoneW + safezoneX); y = (0.0585 * safezoneH + safezoneY);
            w = (0.505 * safezoneW); h = (0.024 * safezoneH);
            colorBackground[] = {0.169, 0.204, 0.251, 1};
        };
        class StatusHome: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#e8f4f0'>Home</t>";
            x = (0.256 * safezoneW + safezoneX); y = (0.0615 * safezoneH + safezoneY);
            w = (0.070 * safezoneW); h = (0.019 * safezoneH);
        };
        class StatusBrand: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.58' align='center' color='#ffffff'>SEEK</t>";
            x = (0.330 * safezoneW + safezoneX); y = (0.0605 * safezoneH + safezoneY);
            w = (0.340 * safezoneW); h = (0.020 * safezoneH);
        };
        // Renseignée au chargement : liaison, horodatage, état d’enregistrement.
        class StatusRight: RscStructuredText {
            idc = 9526;
            text = "<t size='0.5' align='right' color='#c8d4e0'>--:--</t>";
            x = (0.600 * safezoneW + safezoneX); y = (0.0615 * safezoneH + safezoneY);
            w = (0.145 * safezoneW); h = (0.019 * safezoneH);
        };

        class DeviceTitle: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensed' size='0.56' color='#7ee0a0'>ENRÔLEMENT BIOMÉTRIQUE DE TERRAIN</t>";
            x = COL_L_X; y = (0.086 * safezoneH + safezoneY); w = (0.300 * safezoneW); h = (0.022 * safezoneH);
        };
        class Hint: RscStructuredText {
            idc = 9500;
            text = "<t size='0.5' color='#7f95a8'>Relevez l’identité, le contexte et la biométrie de la personne contrôlée.</t>";
            x = COL_L_X; y = (0.104 * safezoneH + safezoneY); w = COL_FULL_W; h = (0.022 * safezoneH);
        };
        class HeaderRule: RscText {
            idc = -1;
            x = COL_L_X; y = (0.116 * safezoneH + safezoneY); w = COL_FULL_W; h = (0.0015 * safezoneH);
            colorBackground[] = {0.16, 0.34, 0.36, 0.8};
        };

        // ============ COLONNE GAUCHE — IDENTITÉ ============
        class SectionIdent: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.54' color='#4fbf8a'>1 · IDENTITÉ</t>";
            x = COL_L_X; y = (0.126 * safezoneH + safezoneY); w = COL_L_W; h = SEEK_LBL;
        };
        class LabelLast: RscStructuredText {
            idc = -1;
            text = "<t size='0.48' color='#5f7383'>NOM</t>";
            x = COL_L_X; y = (0.146 * safezoneH + safezoneY); w = COL_HALF_W; h = SEEK_LBL;
        };
        class EditLast: RscEdit {
            idc = 9501;
            x = COL_L_X; y = (0.160 * safezoneH + safezoneY); w = COL_HALF_W; h = SEEK_FIELD;
            colorBackground[] = {0.043, 0.110, 0.118, 1}; colorText[] = {0.91, 0.97, 0.94, 1};
            sizeEx = 0.030; autocomplete = "";
        };
        class LabelFirst: LabelLast { text = "<t size='0.48' color='#5f7383'>PRÉNOM</t>"; x = COL_LB_X; };
        class EditFirst: EditLast { idc = 9502; x = COL_LB_X; };

        class LabelAlias: LabelLast { text = "<t size='0.48' color='#5f7383'>ALIAS</t>"; y = (0.194 * safezoneH + safezoneY); };
        class EditAlias: EditLast { idc = 9503; y = (0.208 * safezoneH + safezoneY); };
        class LabelAge: LabelLast { text = "<t size='0.48' color='#5f7383'>ÂGE ESTIMÉ</t>"; x = COL_LB_X; y = (0.194 * safezoneH + safezoneY); };
        class EditAge: EditLast { idc = 9504; x = COL_LB_X; y = (0.208 * safezoneH + safezoneY); };

        class LabelNat: LabelLast { text = "<t size='0.48' color='#5f7383'>NATIONALITÉ</t>"; y = (0.242 * safezoneH + safezoneY); };
        class EditNat: EditLast { idc = 9507; y = (0.256 * safezoneH + safezoneY); };
        class LabelLang: LabelLast { text = "<t size='0.48' color='#5f7383'>LANGUE</t>"; x = COL_LB_X; y = (0.242 * safezoneH + safezoneY); };
        class EditLang: EditLast { idc = 9508; x = COL_LB_X; y = (0.256 * safezoneH + safezoneY); };

        class SectionContext: SectionIdent {
            text = "<t font='RobotoCondensedBold' size='0.54' color='#4fbf8a'>2 · CONTEXTE</t>";
            y = (0.294 * safezoneH + safezoneY);
        };
        class LabelStatus: LabelLast { text = "<t size='0.48' color='#5f7383'>STATUT</t>"; y = (0.314 * safezoneH + safezoneY); };
        class ComboStatus: RscCombo {
            idc = 9505;
            x = COL_L_X; y = (0.328 * safezoneH + safezoneY); w = COL_HALF_W; h = SEEK_FIELD;
            colorBackground[] = {0.043, 0.110, 0.118, 1}; colorText[] = {0.91, 0.97, 0.94, 1}; sizeEx = 0.030;
        };
        class LabelCirc: LabelLast { text = "<t size='0.48' color='#5f7383'>CIRCONSTANCES</t>"; x = COL_LB_X; y = (0.314 * safezoneH + safezoneY); };
        class ComboCirc: ComboStatus { idc = 9506; x = COL_LB_X; };

        class LabelAffil: LabelLast { text = "<t size='0.48' color='#5f7383'>AFFILIATION ESTIMÉE</t>"; y = (0.362 * safezoneH + safezoneY); };
        class EditAffil: EditLast { idc = 9510; y = (0.376 * safezoneH + safezoneY); w = COL_L_W; };

        class LabelMarks: LabelLast { text = "<t size='0.48' color='#5f7383'>SIGNES DISTINCTIFS</t>"; y = (0.410 * safezoneH + safezoneY); };
        class EditMarks: EditLast { idc = 9509; y = (0.424 * safezoneH + safezoneY); w = COL_L_W; };

        class LabelStmt: LabelLast { text = "<t size='0.48' color='#5f7383'>DÉCLARATIONS</t>"; y = (0.458 * safezoneH + safezoneY); };
        class EditStmt: EditLast { idc = 9512; y = (0.472 * safezoneH + safezoneY); w = COL_L_W; h = (0.052 * safezoneH); };

        class LabelWeapons: LabelLast { text = "<t size='0.48' color='#5f7383'>ARMEMENT / ÉQUIPEMENT (détecté)</t>"; y = (0.532 * safezoneH + safezoneY); };
        class TextWeapons: RscStructuredText {
            idc = 9511;
            text = "<t size='0.48' color='#5f7383'>Aucun inventaire détecté.</t>";
            x = COL_L_X; y = (0.546 * safezoneH + safezoneY); w = COL_L_W; h = (0.058 * safezoneH);
        };

        // ============ COLONNE DROITE — RELEVÉ ============
        class SectionMedical: SectionIdent {
            text = "<t font='RobotoCondensedBold' size='0.54' color='#4fbf8a'>3 · CONSTAT DE TERRAIN</t>";
            x = COL_R_X; y = (0.126 * safezoneH + safezoneY); w = COL_R_W;
        };
        class MedicalPanel: RscText {
            idc = -1;
            x = COL_R_X; y = (0.144 * safezoneH + safezoneY); w = COL_R_W; h = (0.086 * safezoneH);
            colorBackground[] = {0.035, 0.094, 0.102, 1};
        };
        class TextMedical: RscStructuredText {
            idc = 9521;
            text = "<t size='0.48' color='#5f7383'>Aucun constat disponible.</t>";
            x = (0.512 * safezoneW + safezoneX); y = (0.149 * safezoneH + safezoneY);
            w = (0.227 * safezoneW); h = (0.078 * safezoneH);
        };
        class MedicalNote: RscStructuredText {
            idc = -1;
            text = "<t size='0.42' color='#5f7383'>Constat d’observation — ne remplace pas un bilan médical.</t>";
            x = COL_R_X; y = (0.232 * safezoneH + safezoneY); w = COL_R_W; h = (0.016 * safezoneH);
        };

        // ---------------- Lecteur biométrique ----------------
        class SectionBio: SectionIdent {
            text = "<t font='RobotoCondensedBold' size='0.54' color='#4fbf8a'>4 · BIOMÉTRIE (SIMULATION)</t>";
            x = COL_R_X; y = (0.254 * safezoneH + safezoneY); w = COL_R_W;
        };
        class PlatenFrame: RscText {
            idc = -1;
            x = (0.558 * safezoneW + safezoneX); y = (0.272 * safezoneH + safezoneY);
            w = (0.136 * safezoneW); h = (0.070 * safezoneH);
            colorBackground[] = {0.086, 0.094, 0.098, 1};
        };
        class Platen: RscText {
            idc = -1;
            x = (0.562 * safezoneW + safezoneX); y = (0.277 * safezoneH + safezoneY);
            w = (0.128 * safezoneW); h = (0.060 * safezoneH);
            colorBackground[] = {0.176, 0.784, 0.290, 0.85};
        };
        class PlatenScanline: RscText {
            idc = -1;
            x = (0.562 * safezoneW + safezoneX); y = (0.303 * safezoneH + safezoneY);
            w = (0.128 * safezoneW); h = (0.005 * safezoneH);
            colorBackground[] = {0.78, 1.0, 0.82, 0.75};
        };
        class PlatenLabel: RscStructuredText {
            idc = -1;
            text = "<t size='0.44' align='center' color='#5f7383'>PLATINE DE LECTURE</t>";
            x = COL_R_X; y = (0.344 * safezoneH + safezoneY); w = COL_R_W; h = (0.016 * safezoneH);
        };

        class BtnBio: COMSPEC_RscButton {
            idc = 9514;
            text = "Empreintes";
            x = COL_R_X; y = (0.363 * safezoneH + safezoneY); w = (0.056 * safezoneW); h = (0.029 * safezoneH);
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class BtnIris: BtnBio {
            idc = 9523;
            text = "Iris";
            x = (0.567 * safezoneW + safezoneX);
            action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class BtnDna: BtnBio {
            idc = 9524;
            text = "ADN";
            x = (0.628 * safezoneW + safezoneX);
            action = "['adn'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        // Interrogation de la base d'identités : exige au moins une acquisition.
        class BtnQuery: COMSPEC_RscButtonAccent {
            idc = 9527;
            text = "REQUÊTE";
            x = (0.689 * safezoneW + safezoneX); y = (0.363 * safezoneH + safezoneY);
            w = (0.056 * safezoneW); h = (0.029 * safezoneH);
            action = "[] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };

        class BioPanel: RscText {
            idc = -1;
            x = COL_R_X; y = (0.398 * safezoneH + safezoneY); w = COL_R_W; h = (0.106 * safezoneH);
            colorBackground[] = {0.035, 0.094, 0.102, 1};
        };
        class TextBiometrics: RscStructuredText {
            idc = 9522;
            text = "<t size='0.48' color='#5f7383'>Aucun prélèvement.</t>";
            x = (0.512 * safezoneW + safezoneX); y = (0.403 * safezoneH + safezoneY);
            w = (0.227 * safezoneW); h = (0.098 * safezoneH);
        };

        // ---------------- Photo ----------------
        class SectionPhoto: SectionIdent {
            text = "<t font='RobotoCondensedBold' size='0.54' color='#4fbf8a'>5 · PHOTOGRAPHIE</t>";
            x = COL_R_X; y = (0.512 * safezoneH + safezoneY); w = COL_R_W;
        };
        class BtnPhoto: COMSPEC_RscButton {
            idc = 9515;
            text = "Photo du visage";
            x = COL_R_X; y = (0.530 * safezoneH + safezoneY); w = COL_R_W; h = (0.029 * safezoneH);
            action = "uiNamespace setVariable ['COMSPEC_SsePerson_PhotoPending', true]; ['Photo du visage : une capture récente sera jointe à la fiche.', 'tactical', 'info'] call comspec_overwatch_connect_fnc_announce; [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;";
        };
        class PhotoNote: RscStructuredText {
            idc = -1;
            text = "<t size='0.42' color='#5f7383'>Cadrez le visage, prenez une capture, puis armez la photo.</t>";
            x = COL_R_X; y = (0.562 * safezoneH + safezoneY); w = COL_R_W; h = (0.026 * safezoneH);
        };

        class LcdFrame: RscText {
            idc = -1;
            x = COL_R_X; y = (0.586 * safezoneH + safezoneY); w = COL_R_W; h = (0.024 * safezoneH);
            colorBackground[] = {0.055, 0.129, 0.118, 1};
        };
        class TextLcd: RscStructuredText {
            idc = 9525;
            text = "<t size='0.48' color='#9ed8b4' align='center'>PRÊT</t>";
            x = COL_R_X; y = (0.590 * safezoneH + safezoneY); w = COL_R_W; h = (0.018 * safezoneH);
        };

        // ============ PIED — CLASSEMENT / PROCÈS-VERBAL ============
        class FooterRule: RscText {
            idc = -1;
            x = COL_L_X; y = (0.620 * safezoneH + safezoneY); w = COL_FULL_W; h = (0.0015 * safezoneH);
            colorBackground[] = {0.16, 0.34, 0.36, 0.8};
        };
        class SectionFiling: SectionIdent {
            text = "<t font='RobotoCondensedBold' size='0.54' color='#4fbf8a'>6 · CLASSEMENT ET PROCÈS-VERBAL</t>";
            y = (0.630 * safezoneH + safezoneY); w = COL_FULL_W;
        };
        class LabelCase: LabelLast { text = "<t size='0.48' color='#5f7383'>CODE DOSSIER SSE</t>"; y = (0.650 * safezoneH + safezoneY); };
        class EditCase: EditLast {
            idc = 9518;
            y = (0.664 * safezoneH + safezoneY); w = COL_L_W;
            colorText[] = {0.62, 0.90, 0.72, 1};
        };
        class CaseNote: RscStructuredText {
            idc = -1;
            text = "<t size='0.42' color='#5f7383'>Référence fournie par le poste de commandement. Vide = fiche non classée.</t>";
            x = COL_L_X; y = (0.696 * safezoneH + safezoneY); w = COL_L_W; h = (0.028 * safezoneH);
        };

        class LabelSign: LabelLast { text = "<t size='0.48' color='#5f7383'>SIGNATURE ATAK</t>"; x = COL_R_X; y = (0.650 * safezoneH + safezoneY); };
        class BtnSign: COMSPEC_RscButton {
            idc = 9519;
            text = "Signer par l’ATAK";
            x = COL_R_X; y = (0.664 * safezoneH + safezoneY); w = COL_R_W; h = (0.029 * safezoneH);
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
        class TextSignature: RscStructuredText {
            idc = 9520;
            text = "<t size='0.48' color='#e0b07e'>NON SIGNÉ</t>";
            x = COL_R_X; y = (0.696 * safezoneH + safezoneY); w = COL_R_W; h = (0.032 * safezoneH);
        };

        class StatusText: RscStructuredText {
            idc = 9513;
            text = "";
            x = COL_L_X; y = (0.736 * safezoneH + safezoneY); w = COL_FULL_W; h = (0.030 * safezoneH);
        };

        class BtnSave: COMSPEC_RscButtonAccent {
            idc = 9516;
            text = "Enregistrer et transmettre";
            x = COL_L_X; y = (0.840 * safezoneH + safezoneY); w = (0.330 * safezoneW); h = (0.038 * safezoneH);
            action = "[] call comspec_overwatch_connect_fnc_ssePersonDialogSubmit;";
        };
        class BtnClose: COMSPEC_RscButtonDanger {
            idc = 9517;
            text = "Annuler";
            x = (0.594 * safezoneW + safezoneX); y = (0.840 * safezoneH + safezoneY); w = (0.151 * safezoneW); h = (0.038 * safezoneH);
            action = "private _d = uiNamespace getVariable ['COMSPEC_SsePerson_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; } else { closeDialog 0; };";
        };

        // ---------------- Touches physiques de l’appareil ----------------
        // Raccourcis matériels : A1 empreintes, A2 iris, − / + photo et signature,
        // grille pour vider les relevés, loupe pour transmettre.
        class KeyA1: COMSPEC_RscButton {
            idc = -1;
            text = "A1";
            x = (0.256 * safezoneW + safezoneX); y = (0.890 * safezoneH + safezoneY);
            w = (0.070 * safezoneW); h = (0.030 * safezoneH);
            tooltip = "Relevé d’empreintes";
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyA2: KeyA1 {
            text = "A2";
            x = (0.334 * safezoneW + safezoneX);
            tooltip = "Relevé iris";
            action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyMinus: KeyA1 {
            text = "−";
            x = (0.412 * safezoneW + safezoneX);
            w = (0.045 * safezoneW);
            tooltip = "Prélèvement ADN";
            action = "['adn'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyPlus: KeyA1 {
            text = "+";
            x = (0.461 * safezoneW + safezoneX);
            w = (0.045 * safezoneW);
            tooltip = "Armer la photo du visage";
            action = "uiNamespace setVariable ['COMSPEC_SsePerson_PhotoPending', true]; ['Photo du visage : une capture récente sera jointe à la fiche.', 'tactical', 'info'] call comspec_overwatch_connect_fnc_announce; [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;";
        };
        class KeyGrid: KeyA1 {
            text = "::";
            x = (0.586 * safezoneW + safezoneX);
            tooltip = "Effacer les relevés en attente";
            action = "uiNamespace setVariable ['COMSPEC_SsePerson_Samples', []]; uiNamespace setVariable ['COMSPEC_SsePerson_PhotoPending', false]; ['Relevés effacés.', 'tactical', 'info'] call comspec_overwatch_connect_fnc_announce; [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;";
        };
        class KeySign: KeyA1 {
            text = "SIGN";
            x = (0.664 * safezoneW + safezoneX);
            w = (0.081 * safezoneW);
            tooltip = "Signer par l’ATAK";
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
    };
};
