// App « Manifeste » — déclaration de vol depuis le téléphone.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_MF_PHONE_W
    #define COMSPEC_MF_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_MF_PHONE_H
    #define COMSPEC_MF_PHONE_H (COMSPEC_MF_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_MF_SIZE_H
    #define COMSPEC_MF_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_MF_PHONE_H)
#endif
#ifndef COMSPEC_MF_POS_H
    #define COMSPEC_MF_POS_H (((60)) / 2048 * COMSPEC_MF_PHONE_H)
#endif
#ifndef COMSPEC_MF_POS_W
    #define COMSPEC_MF_POS_W (((COMSPEC_MF_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_MF_W
    #define COMSPEC_MF_W(n) ((n) * COMSPEC_MF_POS_W)
#endif
#ifndef COMSPEC_MF_H
    #define COMSPEC_MF_H(n) ((n) * COMSPEC_MF_POS_H)
#endif

class COMSPEC_ATAK_Manifest: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9930;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_MF_W(3));
            h = QUOTE(COMSPEC_MF_H(0.54));
            size = QUOTE(COMSPEC_MF_H(0.36));
            text = "  Manifeste de vol";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_MF_H(0.54));
            w = QUOTE(COMSPEC_MF_W(3));
            h = QUOTE(COMSPEC_MF_H(0.05));
            colorBackground[] = ATAK_ACCENT;
        };

        class BtnSend: COMSPEC_ATAK_BtnGo
        {
            idc = 1520;
            x = QUOTE(COMSPEC_MF_W(0.08));
            y = QUOTE(COMSPEC_MF_H(0.66));
            w = QUOTE(COMSPEC_MF_W(2.84));
            h = QUOTE(COMSPEC_MF_H(0.50));
            size = QUOTE(COMSPEC_MF_H(0.28));
            text = "Transmettre";
            onButtonClick = "[] call comspec_overwatch_connect_fnc_submitFlightManifest";
        };

        class BodyScroll: RscControlsGroup
        {
            idc = 9939;
            x = 0;
            y = QUOTE(COMSPEC_MF_H(1.22));
            w = QUOTE(COMSPEC_MF_W(3));
            h = QUOTE(COMSPEC_MF_H(5.55));
            class VScrollbar
            {
                width = 0.014;
                autoScrollEnabled = 0;
                color[] = {0.45, 0.72, 0.62, 0.85};
            };
            class HScrollbar
            {
                height = 0;
            };
            class controls
            {
                class Hint: RscStructuredText
                {
                    idc = 1540;
                    x = QUOTE(COMSPEC_MF_W(0.08));
                    y = QUOTE(COMSPEC_MF_H(0.06));
                    w = QUOTE(COMSPEC_MF_W(2.84));
                    h = QUOTE(COMSPEC_MF_H(0.70));
                    text = "Identité, mission et codes — à confirmer avant transmission.";
                    colorBackground[] = ATAK_BG_DETAIL;
                    class Attributes
                    {
                        font = "RobotoCondensed";
                        color = "#E8F2FA";
                        align = "left";
                        size = "0.88";
                    };
                };

                class LblCallsign: RscStructuredText
                {
                    idc = -1;
                    x = QUOTE(COMSPEC_MF_W(0.08));
                    y = QUOTE(COMSPEC_MF_H(0.84));
                    w = QUOTE(COMSPEC_MF_W(2.84));
                    h = QUOTE(COMSPEC_MF_H(0.36));
                    text = "Indicatif";
                    colorBackground[] = {0, 0, 0, 0};
                    class Attributes
                    {
                        font = "RobotoCondensedBold";
                        color = "#9ADCF5";
                        align = "left";
                        valign = "middle";
                        size = "0.88";
                    };
                };
                class EditCallsign: RscEdit
                {
                    idc = 1501;
                    x = QUOTE(COMSPEC_MF_W(0.08));
                    y = QUOTE(COMSPEC_MF_H(1.20));
                    w = QUOTE(COMSPEC_MF_W(2.84));
                    h = QUOTE(COMSPEC_MF_H(0.46));
                    colorBackground[] = ATAK_BG_EDIT;
                    colorText[] = {0.95, 0.98, 0.9, 1};
                    sizeEx = QUOTE(COMSPEC_MF_H(0.26));
                    autocomplete = "";
                };

                class LblType: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(1.74));
                    text = "Type";
                };
                class ComboType: RscCombo
                {
                    idc = 1503;
                    x = QUOTE(COMSPEC_MF_W(0.08));
                    y = QUOTE(COMSPEC_MF_H(2.10));
                    w = QUOTE(COMSPEC_MF_W(2.84));
                    h = QUOTE(COMSPEC_MF_H(0.46));
                    wholeHeight = QUOTE(COMSPEC_MF_H(2.20));
                    colorBackground[] = ATAK_BG_EDIT;
                    colorSelectBackground[] = {0.06, 0.22, 0.12, 1};
                    colorText[] = {0.95, 0.98, 0.9, 1};
                    sizeEx = QUOTE(COMSPEC_MF_H(0.26));
                };

                class LblModel: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(2.64));
                    text = "Appareil";
                };
                class EditModel: EditCallsign
                {
                    idc = 1502;
                    y = QUOTE(COMSPEC_MF_H(3.00));
                };

                class LblFreq: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(3.54));
                    text = "Fréquence";
                };
                class EditFreq: EditCallsign
                {
                    idc = 1504;
                    y = QUOTE(COMSPEC_MF_H(3.90));
                };

                class LblGrid: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(4.44));
                    text = "Position (grille)";
                };
                class EditGrid: EditCallsign
                {
                    idc = 1505;
                    y = QUOTE(COMSPEC_MF_H(4.80));
                };

                class LblFuel: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(5.34));
                    text = "Carburant";
                };
                class EditFuel: EditCallsign
                {
                    idc = 1506;
                    y = QUOTE(COMSPEC_MF_H(5.70));
                };

                class LblRole: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(6.24));
                    text = "Rôle";
                };
                class ComboRole: ComboType
                {
                    idc = 1507;
                    y = QUOTE(COMSPEC_MF_H(6.60));
                    wholeHeight = QUOTE(COMSPEC_MF_H(3.00));
                };

                class LblDest: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(7.14));
                    text = "Destination / zone";
                };
                class EditDest: EditCallsign
                {
                    idc = 1508;
                    y = QUOTE(COMSPEC_MF_H(7.50));
                };

                class LblNotes: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(8.04));
                    text = "Notes pour le poste";
                };
                class EditNotes: RscEdit
                {
                    idc = 1509;
                    style = 16;
                    x = QUOTE(COMSPEC_MF_W(0.08));
                    y = QUOTE(COMSPEC_MF_H(8.40));
                    w = QUOTE(COMSPEC_MF_W(2.84));
                    h = QUOTE(COMSPEC_MF_H(0.80));
                    colorBackground[] = ATAK_BG_EDIT;
                    colorText[] = {0.95, 0.98, 0.9, 1};
                    sizeEx = QUOTE(COMSPEC_MF_H(0.26));
                    autocomplete = "";
                };

                class LblLaser: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(9.30));
                    text = "Code laser";
                };
                class EditLaser: EditCallsign
                {
                    idc = 1510;
                    y = QUOTE(COMSPEC_MF_H(9.66));
                    text = "1688";
                };

                class LblAuth: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(10.20));
                    text = "Authentification";
                };
                class EditAuth: EditCallsign
                {
                    idc = 1511;
                    y = QUOTE(COMSPEC_MF_H(10.56));
                    text = "SIGMA-5";
                };

                class LblCount: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(11.10));
                    text = "Appareils";
                };
                class EditCount: EditCallsign
                {
                    idc = 1512;
                    y = QUOTE(COMSPEC_MF_H(11.46));
                    w = QUOTE(COMSPEC_MF_W(1.32));
                    text = "1";
                };

                class LblPax: LblCallsign
                {
                    x = QUOTE(COMSPEC_MF_W(1.52));
                    y = QUOTE(COMSPEC_MF_H(11.10));
                    w = QUOTE(COMSPEC_MF_W(1.40));
                    text = "Personnes à bord";
                };
                class EditPax: EditCallsign
                {
                    idc = 1513;
                    x = QUOTE(COMSPEC_MF_W(1.52));
                    y = QUOTE(COMSPEC_MF_H(11.46));
                    w = QUOTE(COMSPEC_MF_W(1.40));
                    text = "1";
                };

                class LblAlt: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(12.00));
                    text = "Position / altitude";
                };
                class EditAlt: EditCallsign
                {
                    idc = 1514;
                    y = QUOTE(COMSPEC_MF_H(12.36));
                    w = QUOTE(COMSPEC_MF_W(1.32));
                };
                class EditHdg: EditCallsign
                {
                    idc = 1515;
                    x = QUOTE(COMSPEC_MF_W(1.52));
                    y = QUOTE(COMSPEC_MF_H(12.36));
                    w = QUOTE(COMSPEC_MF_W(1.40));
                };

                class LblCrew: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(12.90));
                    text = "Personnes à bord (détectées)";
                };
                class EditCrew: RscEdit
                {
                    idc = 1516;
                    style = 16;
                    x = QUOTE(COMSPEC_MF_W(0.08));
                    y = QUOTE(COMSPEC_MF_H(13.26));
                    w = QUOTE(COMSPEC_MF_W(2.84));
                    h = QUOTE(COMSPEC_MF_H(1.10));
                    colorBackground[] = ATAK_BG_EDIT;
                    colorText[] = {0.95, 0.98, 0.9, 1};
                    sizeEx = QUOTE(COMSPEC_MF_H(0.24));
                    autocomplete = "";
                };

                class LblOrd: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(14.46));
                    text = "Emport / munitions";
                };
                class EditOrd: EditNotes
                {
                    idc = 1517;
                    y = QUOTE(COMSPEC_MF_H(14.82));
                    h = QUOTE(COMSPEC_MF_H(0.90));
                };

                class LblSen: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(15.82));
                    text = "Capteurs / pods";
                };
                class EditSen: EditCallsign
                {
                    idc = 1518;
                    y = QUOTE(COMSPEC_MF_H(16.18));
                };

                class LblPlay: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(16.72));
                    text = "Autonomie sur zone";
                };
                class EditPlay: EditCallsign
                {
                    idc = 1519;
                    y = QUOTE(COMSPEC_MF_H(17.08));
                    w = QUOTE(COMSPEC_MF_W(1.32));
                };
                class LblAto: LblCallsign
                {
                    x = QUOTE(COMSPEC_MF_W(1.52));
                    y = QUOTE(COMSPEC_MF_H(16.72));
                    w = QUOTE(COMSPEC_MF_W(1.40));
                    text = "Numéro de mission";
                };
                class EditAto: EditCallsign
                {
                    idc = 1523;
                    x = QUOTE(COMSPEC_MF_W(1.52));
                    y = QUOTE(COMSPEC_MF_H(17.08));
                    w = QUOTE(COMSPEC_MF_W(1.40));
                };

                class LblAbort: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(17.62));
                    text = "Code d'annulation";
                };
                class EditAbort: EditCallsign
                {
                    idc = 1522;
                    y = QUOTE(COMSPEC_MF_H(17.98));
                };

                class LblNine: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(18.52));
                    text = "Canevas d'attaque (9 lignes)";
                };
                class LblN1: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(18.92));
                    text = "1 — Point de départ (IP / BP)";
                };
                class EditN1: EditCallsign
                {
                    idc = 1551;
                    y = QUOTE(COMSPEC_MF_H(19.28));
                };
                class LblN2: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(19.82));
                    text = "2 — Cap et déviation";
                };
                class EditN2: EditCallsign
                {
                    idc = 1552;
                    y = QUOTE(COMSPEC_MF_H(20.18));
                };
                class LblN3: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(20.72));
                    text = "3 — Distance";
                };
                class EditN3: EditCallsign
                {
                    idc = 1553;
                    y = QUOTE(COMSPEC_MF_H(21.08));
                };
                class LblN4: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(21.62));
                    text = "4 — Altitude de la cible";
                };
                class EditN4: EditCallsign
                {
                    idc = 1554;
                    y = QUOTE(COMSPEC_MF_H(21.98));
                };
                class LblN5: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(22.52));
                    text = "5 — Description de la cible";
                };
                class EditN5: EditCallsign
                {
                    idc = 1555;
                    y = QUOTE(COMSPEC_MF_H(22.88));
                };
                class LblN6: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(23.42));
                    text = "6 — Position de la cible";
                };
                class EditN6: EditCallsign
                {
                    idc = 1556;
                    y = QUOTE(COMSPEC_MF_H(23.78));
                };
                class LblN7: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(24.32));
                    text = "7 — Marquage";
                };
                class EditN7: EditCallsign
                {
                    idc = 1557;
                    y = QUOTE(COMSPEC_MF_H(24.68));
                };
                class LblN8: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(25.22));
                    text = "8 — Alliés proches";
                };
                class EditN8: EditCallsign
                {
                    idc = 1558;
                    y = QUOTE(COMSPEC_MF_H(25.58));
                };
                class LblN9: LblCallsign
                {
                    y = QUOTE(COMSPEC_MF_H(26.12));
                    text = "9 — Sortie";
                };
                class EditN9: EditCallsign
                {
                    idc = 1559;
                    y = QUOTE(COMSPEC_MF_H(26.48));
                };

                class BtnRefresh: COMSPEC_ATAK_Btn
                {
                    idc = 1542;
                    x = QUOTE(COMSPEC_MF_W(0.08));
                    y = QUOTE(COMSPEC_MF_H(27.10));
                    w = QUOTE(COMSPEC_MF_W(2.84));
                    h = QUOTE(COMSPEC_MF_H(0.48));
                    size = QUOTE(COMSPEC_MF_H(0.24));
                    text = "Actualiser l'appareil";
                    onButtonClick = "[] call comspec_overwatch_connect_fnc_fillFlightManifest";
                };

                class Spacer: RscText
                {
                    idc = -1;
                    x = 0;
                    y = QUOTE(COMSPEC_MF_H(27.70));
                    w = QUOTE(COMSPEC_MF_W(0.20));
                    h = QUOTE(COMSPEC_MF_H(0.40));
                    colorBackground[] = {0, 0, 0, 0};
                };
            };
        };

        class BtnRoger: COMSPEC_ATAK_Btn
        {
            idc = 1530;
            x = QUOTE(COMSPEC_MF_W(0.08));
            y = QUOTE(COMSPEC_MF_H(6.86));
            w = QUOTE(COMSPEC_MF_W(0.90));
            h = QUOTE(COMSPEC_MF_H(0.46));
            size = QUOTE(COMSPEC_MF_H(0.22));
            text = "Reçu";
            onButtonClick = "['ROGER'] call comspec_overwatch_connect_fnc_pilotResponse";
        };
        class BtnInbound: BtnRoger
        {
            idc = 1531;
            x = QUOTE(COMSPEC_MF_W(1.05));
            text = "Approche";
            onButtonClick = "['INBOUND'] call comspec_overwatch_connect_fnc_pilotResponse";
        };
        class BtnOnsta: BtnRoger
        {
            idc = 1534;
            x = QUOTE(COMSPEC_MF_W(2.02));
            text = "À poste";
            onButtonClick = "['ONSTA'] call comspec_overwatch_connect_fnc_pilotResponse";
        };
        class BtnEngaged: COMSPEC_ATAK_BtnDanger
        {
            idc = 1532;
            x = QUOTE(COMSPEC_MF_W(0.08));
            y = QUOTE(COMSPEC_MF_H(7.38));
            w = QUOTE(COMSPEC_MF_W(1.38));
            h = QUOTE(COMSPEC_MF_H(0.46));
            size = QUOTE(COMSPEC_MF_H(0.22));
            text = "Engagé";
            onButtonClick = "['ENGAGED'] call comspec_overwatch_connect_fnc_pilotResponse";
        };
        class BtnRtb: COMSPEC_ATAK_Btn
        {
            idc = 1533;
            x = QUOTE(COMSPEC_MF_W(1.54));
            y = QUOTE(COMSPEC_MF_H(7.38));
            w = QUOTE(COMSPEC_MF_W(1.38));
            h = QUOTE(COMSPEC_MF_H(0.46));
            size = QUOTE(COMSPEC_MF_H(0.22));
            text = "Retour";
            colorBackground[] = {0.10, 0.16, 0.24, 0.98};
            colorBackground2[] = {0.10, 0.16, 0.24, 0.98};
            colorBackgroundFocused[] = {0.16, 0.24, 0.36, 1};
            onButtonClick = "['RTB'] call comspec_overwatch_connect_fnc_pilotResponse";
        };
    };
};
