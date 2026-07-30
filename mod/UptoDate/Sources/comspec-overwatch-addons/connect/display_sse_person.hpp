// Terminal biométrique SEEK — enrôlement personne (idd 9991)
//
// L'illustration de l'appareil est la vraie surface : les contrôles sont posés
// DANS l'écran du SEEK, pas sur toute la hauteur du châssis. L'écran ne tenant
// qu'une dizaine de lignes, le terminal fonctionne en PAGES, avec un accueil à
// tuiles — comme l'appareil réel.
//
// Géométrie mesurée sur seek_chassis.paa (2048 × 2048) :
//   appareil        x 0.117 – 0.883   y 0.031 – 0.969
//   écran           x 0.297 – 0.703   y 0.065 – 0.330
//   platine verte   x 0.398 – 0.594   y 0.703 – 0.867
// Si la texture change, ce sont les seules valeurs à reprendre.
//
// IDC :
//   9500 bandeau d'état bas            9513 état transmission
//   9501 nom      9502 prénom          9514 empreintes  9523 iris  9524 ADN
//   9503 alias    9504 âge             9515 photo visage
//   9505 statut   9506 circonstances   9516 transmettre 9517 annuler
//   9507 nationalité  9508 langue      9518 code dossier
//   9509 signes distinctifs            9519 signature ATAK   9520 bloc signature
//   9510 affiliation                   9521 constat de terrain
//   9511 armement détecté              9522 analyse biométrique
//   9512 déclarations                  9525 bandeau LCD      9526 barre d'état
//   9527 requête d'identité
//   9530-9535 tuiles d'accueil   9540 titre de page   9541 ◄   9542 ►   9543 accueil
//   9551-9556 libellés page sujet   9560-9565 libellés autres pages
//   9566 platine   9567 cadre LCD
//   Tout libellé doit porter un IDC : sans cela il ne peut pas être masqué et se
//   superpose d'une page à l'autre.

#define SEEK_X      (0.2125 * safezoneW + safezoneX)
#define SEEK_W      (0.5750 * safezoneW)
#define SEEK_Y      (0.0400 * safezoneH + safezoneY)
#define SEEK_H      (0.9200 * safezoneH)

// Illustration de l'appareil. Vide = châssis dessiné seul : le terminal reste
// utilisable sans aucun asset.
#define SEEK_CHASSIS_TEXTURE "\z\comspec_overwatch\addons\connect\img\device\seek_chassis.paa"

// --- Écran, en fractions de l'illustration ---
#define SCR_X       (SEEK_X + 0.297 * SEEK_W)
#define SCR_Y       (SEEK_Y + 0.065 * SEEK_H)
#define SCR_W       (0.406 * SEEK_W)
#define SCR_H       (0.265 * SEEK_H)

// Grille interne : barre d'état en haut, navigation en bas, lignes utiles entre.
#define SP          (0.006 * SEEK_W)
#define IN_X        (SCR_X + SP)
#define IN_W        (SCR_W - 2 * SP)
#define HALF_W      ((IN_W - SP) / 2)
#define IN_X2       (IN_X + HALF_W + SP)

#define BAR_H       (0.0165 * safezoneH)
#define ROW_H       (0.0200 * safezoneH)
#define LBL_H       (0.0125 * safezoneH)
#define ROW(n)      (SCR_Y + BAR_H + (0.004 * safezoneH) + (n) * ROW_H)
#define NAV_Y       (SCR_Y + SCR_H - BAR_H - (0.003 * safezoneH))

// Accueil : deux rangées de trois tuiles.
#define TILE_W      ((IN_W - 2 * SP) / 3)
#define TILE_H      (0.0480 * safezoneH)
#define TILE_X(c)   (IN_X + (c) * (TILE_W + SP))
#define TILE_Y(r)   (SCR_Y + BAR_H + (0.010 * safezoneH) + (r) * (TILE_H + (0.006 * safezoneH)))

#define QW          ((IN_W - 3 * SP) / 4)

class COMSPEC_SsePerson_Dialog {
    idd = 9991;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_ssePersonDialogOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_SsePerson_Display', displayNull]; uiNamespace setVariable ['COMSPEC_SsePerson_Target', objNull]; uiNamespace setVariable ['COMSPEC_SsePerson_Samples', []]; uiNamespace setVariable ['COMSPEC_SsePerson_Signature', []]; uiNamespace setVariable ['COMSPEC_SsePerson_Query', []];";

    class Controls {
        // ================= APPAREIL =================
        // Châssis dessiné : filet si la texture est absente.
        class Chassis: RscText {
            idc = -1;
            x = SEEK_X; y = SEEK_Y; w = SEEK_W; h = SEEK_H;
            colorBackground[] = {0.043, 0.047, 0.051, 0.55};
        };
        class ChassisTexture: RscPicture {
            idc = -1;
            text = SEEK_CHASSIS_TEXTURE;
            x = SEEK_X; y = SEEK_Y; w = SEEK_W; h = SEEK_H;
        };

        // ================= ÉCRAN =================
        class Screen: RscText {
            idc = -1;
            x = SCR_X; y = SCR_Y; w = SCR_W; h = SCR_H;
            colorBackground[] = {0.027, 0.055, 0.063, 0.97};
        };
        class ScreenBar: RscText {
            idc = -1;
            x = SCR_X; y = SCR_Y; w = SCR_W; h = BAR_H;
            colorBackground[] = {0.169, 0.204, 0.251, 1};
        };
        class BtnHome: RscButton {
            idc = 9543;
            text = "Home";
            x = SCR_X; y = SCR_Y; w = (0.16 * SCR_W); h = BAR_H;
            colorBackground[] = {0, 0, 0, 0};
            colorBackgroundActive[] = {0.24, 0.87, 0.55, 0.35};
            colorText[] = {0.91, 0.96, 0.94, 1};
            colorFocused[] = {0, 0, 0, 0};
            sizeEx = 0.020;
            action = "[0] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class ScreenTitle: RscStructuredText {
            idc = 9540;
            text = "<t size='0.42' align='center' color='#ffffff'>SEEK</t>";
            x = (SCR_X + 0.16 * SCR_W); y = (SCR_Y + 0.001 * safezoneH);
            w = (0.46 * SCR_W); h = (BAR_H - 0.002 * safezoneH);
        };
        class StatusRight: RscStructuredText {
            idc = 9526;
            text = "<t size='0.38' align='right' color='#c8d4e0'>--:--</t>";
            x = (SCR_X + 0.62 * SCR_W); y = (SCR_Y + 0.001 * safezoneH);
            w = (0.37 * SCR_W); h = (BAR_H - 0.002 * safezoneH);
        };

        // ================= PAGE 0 — ACCUEIL =================
        class Tile0: RscButton {
            idc = 9530;
            text = "SUJET";
            x = TILE_X(0); y = TILE_Y(0); w = TILE_W; h = TILE_H;
            colorBackground[] = {0.055, 0.118, 0.125, 0.95};
            colorBackgroundActive[] = {0.07, 0.82, 0.56, 0.35};
            colorText[] = {0.91, 0.97, 0.94, 1};
            sizeEx = 0.022;
            tooltip = "Identité de la personne";
            action = "[1] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class Tile1: Tile0 { idc = 9531; text = "CONTEXTE"; x = TILE_X(1); tooltip = "Statut, circonstances, affiliation"; action = "[2] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile2: Tile0 { idc = 9532; text = "BIOMETRIE"; x = TILE_X(2); tooltip = "Empreintes, iris, ADN, requête d’identité"; action = "[3] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile3: Tile0 { idc = 9533; text = "CONSTAT"; y = TILE_Y(1); tooltip = "Constat de terrain et déclarations"; action = "[4] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile4: Tile0 { idc = 9534; text = "PHOTO"; x = TILE_X(1); y = TILE_Y(1); tooltip = "Photographie du visage"; action = "[5] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };
        class Tile5: Tile0 { idc = 9535; text = "DOSSIER"; x = TILE_X(2); y = TILE_Y(1); tooltip = "Classement, signature, transmission"; action = "[6] call comspec_overwatch_connect_fnc_sseTerminalPage;"; };

        class Hint: RscStructuredText {
            idc = 9500;
            text = "<t size='0.38' align='center' color='#7f95a8'>Prêt.</t>";
            x = IN_X; y = (NAV_Y - 0.019 * safezoneH); w = IN_W; h = (0.017 * safezoneH);
        };

        // ================= PAGE 1 — SUJET =================
        class LabelLast: RscStructuredText {
            idc = 9551;
            text = "<t size='0.38' color='#5f7383'>NOM</t>";
            x = IN_X; y = ROW(0); w = HALF_W; h = LBL_H;
        };
        class EditLast: RscEdit {
            idc = 9501;
            x = IN_X; y = (ROW(0) + LBL_H); w = HALF_W; h = (ROW_H - LBL_H);
            colorBackground[] = {0.043, 0.110, 0.118, 1}; colorText[] = {0.91, 0.97, 0.94, 1};
            sizeEx = 0.022; autocomplete = "";
        };
        class LabelFirst: LabelLast { idc = 9552; text = "<t size='0.38' color='#5f7383'>PRENOM</t>"; x = IN_X2; };
        class EditFirst: EditLast { idc = 9502; x = IN_X2; };
        class LabelAlias: LabelLast { idc = 9553; text = "<t size='0.38' color='#5f7383'>ALIAS</t>"; y = ROW(1); };
        class EditAlias: EditLast { idc = 9503; y = (ROW(1) + LBL_H); };
        class LabelAge: LabelLast { idc = 9554; text = "<t size='0.38' color='#5f7383'>AGE</t>"; x = IN_X2; y = ROW(1); };
        class EditAge: EditLast { idc = 9504; x = IN_X2; y = (ROW(1) + LBL_H); };
        class LabelNat: LabelLast { idc = 9555; text = "<t size='0.38' color='#5f7383'>NATIONALITE</t>"; y = ROW(2); };
        class EditNat: EditLast { idc = 9507; y = (ROW(2) + LBL_H); };
        class LabelLang: LabelLast { idc = 9556; text = "<t size='0.38' color='#5f7383'>LANGUE</t>"; x = IN_X2; y = ROW(2); };
        class EditLang: EditLast { idc = 9508; x = IN_X2; y = (ROW(2) + LBL_H); };
        class TextWeapons: RscStructuredText {
            idc = 9511;
            text = "<t size='0.38' color='#5f7383'>Aucun inventaire détecté.</t>";
            x = IN_X; y = ROW(3); w = IN_W; h = (2.4 * ROW_H);
        };

        // ================= PAGE 2 — CONTEXTE =================
        class LabelStatus: LabelLast { idc = 9560; text = "<t size='0.38' color='#5f7383'>STATUT</t>"; };
        class ComboStatus: RscCombo {
            idc = 9505;
            x = IN_X; y = (ROW(0) + LBL_H); w = HALF_W; h = (ROW_H - LBL_H);
            colorBackground[] = {0.043, 0.110, 0.118, 1}; colorText[] = {0.91, 0.97, 0.94, 1}; sizeEx = 0.022;
        };
        class LabelCirc: LabelLast { idc = 9561; text = "<t size='0.38' color='#5f7383'>CIRCONSTANCES</t>"; x = IN_X2; };
        class ComboCirc: ComboStatus { idc = 9506; x = IN_X2; };
        class LabelAffil: LabelLast { idc = 9562; text = "<t size='0.38' color='#5f7383'>AFFILIATION ESTIMEE</t>"; y = ROW(1); };
        class EditAffil: EditLast { idc = 9510; y = (ROW(1) + LBL_H); w = IN_W; };
        class LabelMarks: LabelLast { idc = 9563; text = "<t size='0.38' color='#5f7383'>SIGNES DISTINCTIFS</t>"; y = ROW(2); };
        class EditMarks: EditLast { idc = 9509; y = (ROW(2) + LBL_H); w = IN_W; };

        // ================= PAGE 3 — BIOMETRIE =================
        class Platen: RscText {
            idc = 9566;
            x = IN_X; y = ROW(0); w = IN_W; h = (0.013 * safezoneH);
            colorBackground[] = {0.176, 0.784, 0.290, 0.8};
        };
        class BtnBio: COMSPEC_RscButton {
            idc = 9514;
            text = "EMPR.";
            x = IN_X; y = (ROW(1) - 0.003 * safezoneH); w = QW; h = (0.020 * safezoneH);
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class BtnIris: BtnBio { idc = 9523; text = "IRIS"; x = (IN_X + QW + SP); action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;"; };
        class BtnDna: BtnBio { idc = 9524; text = "ADN"; x = (IN_X + 2 * (QW + SP)); action = "['adn'] call comspec_overwatch_connect_fnc_sseBiometricSample;"; };
        class BtnQuery: COMSPEC_RscButtonAccent {
            idc = 9527;
            text = "QUERY";
            x = (IN_X + 3 * (QW + SP)); y = (ROW(1) - 0.003 * safezoneH);
            w = QW; h = (0.020 * safezoneH);
            action = "[] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };
        class TextBiometrics: RscStructuredText {
            idc = 9522;
            text = "<t size='0.38' color='#5f7383'>Aucun prélèvement.</t>";
            x = IN_X; y = ROW(2); w = IN_W; h = (3.2 * ROW_H);
        };

        // ================= PAGE 4 — CONSTAT =================
        class TextMedical: RscStructuredText {
            idc = 9521;
            text = "<t size='0.38' color='#5f7383'>Aucun constat.</t>";
            x = IN_X; y = ROW(0); w = IN_W; h = (2.4 * ROW_H);
        };
        class LabelStmt: LabelLast { idc = 9564; text = "<t size='0.38' color='#5f7383'>DECLARATIONS</t>"; y = ROW(3); };
        class EditStmt: EditLast { idc = 9512; y = (ROW(3) + LBL_H); w = IN_W; h = (1.4 * ROW_H); };

        // ================= PAGE 5 — PHOTO =================
        class BtnPhoto: COMSPEC_RscButton {
            idc = 9515;
            text = "PHOTO DU VISAGE";
            x = IN_X; y = ROW(1); w = IN_W; h = (0.022 * safezoneH);
            action = "uiNamespace setVariable ['COMSPEC_SsePerson_PhotoPending', true]; ['Photo du visage : une capture récente sera jointe à la fiche.', 'tactical', 'info'] call comspec_overwatch_connect_fnc_announce; [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;";
        };
        class LcdFrame: RscText {
            idc = 9567;
            x = IN_X; y = ROW(3); w = IN_W; h = (0.018 * safezoneH);
            colorBackground[] = {0.055, 0.129, 0.118, 1};
        };
        class TextLcd: RscStructuredText {
            idc = 9525;
            text = "<t size='0.38' color='#9ed8b4' align='center'>PRET</t>";
            x = IN_X; y = (ROW(3) + 0.002 * safezoneH); w = IN_W; h = (0.015 * safezoneH);
        };

        // ================= PAGE 6 — DOSSIER =================
        class LabelCase: LabelLast { idc = 9565; text = "<t size='0.38' color='#5f7383'>CODE DOSSIER SSE</t>"; };
        class EditCase: EditLast {
            idc = 9518;
            y = (ROW(0) + LBL_H); w = IN_W;
            colorText[] = {0.62, 0.90, 0.72, 1};
        };
        class BtnSign: COMSPEC_RscButton {
            idc = 9519;
            text = "SIGNER PAR L’ATAK";
            x = IN_X; y = ROW(1); w = IN_W; h = (0.020 * safezoneH);
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
        class TextSignature: RscStructuredText {
            idc = 9520;
            text = "<t size='0.38' color='#e0b07e'>NON SIGNE</t>";
            x = IN_X; y = ROW(2); w = IN_W; h = (1.4 * ROW_H);
        };
        class StatusText: RscStructuredText {
            idc = 9513;
            text = "";
            x = IN_X; y = ROW(4); w = IN_W; h = (1.2 * ROW_H);
        };
        class BtnSave: COMSPEC_RscButtonAccent {
            idc = 9516;
            text = "TRANSMETTRE";
            x = IN_X; y = (NAV_Y - 0.023 * safezoneH); w = HALF_W; h = (0.021 * safezoneH);
            action = "[] call comspec_overwatch_connect_fnc_ssePersonDialogSubmit;";
        };
        class BtnClose: COMSPEC_RscButtonDanger {
            idc = 9517;
            text = "ANNULER";
            x = IN_X2; y = (NAV_Y - 0.023 * safezoneH); w = HALF_W; h = (0.021 * safezoneH);
            action = "private _d = uiNamespace getVariable ['COMSPEC_SsePerson_Display', displayNull]; if (!isNull _d) then { _d closeDisplay 2; } else { closeDialog 0; };";
        };

        // ================= NAVIGATION =================
        class BtnPrev: RscButton {
            idc = 9541;
            text = "<";
            x = IN_X; y = NAV_Y; w = (0.14 * SCR_W); h = BAR_H;
            colorBackground[] = {0.055, 0.118, 0.125, 0.9};
            colorBackgroundActive[] = {0.07, 0.82, 0.56, 0.35};
            colorText[] = {0.91, 0.97, 0.94, 1};
            colorFocused[] = {0.055, 0.118, 0.125, 0.9};
            sizeEx = 0.022;
            action = "[-1, true] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };
        class BtnNext: BtnPrev {
            idc = 9542;
            text = ">";
            x = (SCR_X + SCR_W - SP - (0.14 * SCR_W));
            action = "[1, true] call comspec_overwatch_connect_fnc_sseTerminalPage;";
        };

        // ================= TOUCHES PHYSIQUES (hors écran) =================
        class KeyA1: COMSPEC_RscButton {
            idc = -1;
            text = "A1";
            x = (SEEK_X + 0.300 * SEEK_W); y = (SEEK_Y + 0.360 * SEEK_H);
            w = (0.085 * SEEK_W); h = (0.030 * SEEK_H);
            tooltip = "Relevé d’empreintes";
            action = "['empreintes'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyA2: KeyA1 {
            text = "A2";
            x = (SEEK_X + 0.395 * SEEK_W);
            tooltip = "Relevé iris";
            action = "['iris'] call comspec_overwatch_connect_fnc_sseBiometricSample;";
        };
        class KeyQuery: KeyA1 {
            text = "Q";
            x = (SEEK_X + 0.490 * SEEK_W);
            w = (0.055 * SEEK_W);
            tooltip = "Requête d’identité";
            action = "[] call comspec_overwatch_connect_fnc_sseIdentityQuery;";
        };
        class KeySign: KeyA1 {
            text = "SIGN";
            x = (SEEK_X + 0.555 * SEEK_W);
            w = (0.085 * SEEK_W);
            tooltip = "Signer par l’ATAK";
            action = "[] call comspec_overwatch_connect_fnc_sseSignAtak;";
        };
    };
};
