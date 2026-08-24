// Panneau Athena dans ATAK (cTab) — grille alignée sur les apps Iceman (POS_H / POS_W).
// Espacement généreux + menus métier (Liaison, Messages, Urgences).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_ATHENA_PHONE_W
    #define COMSPEC_ATHENA_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_ATHENA_PHONE_H
    #define COMSPEC_ATHENA_PHONE_H (COMSPEC_ATHENA_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_ATHENA_SIZE_H
    #define COMSPEC_ATHENA_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_ATHENA_PHONE_H)
#endif
#ifndef COMSPEC_ATHENA_POS_H
    #define COMSPEC_ATHENA_POS_H (((60)) / 2048 * COMSPEC_ATHENA_PHONE_H)
#endif
#ifndef COMSPEC_ATHENA_POS_W
    #define COMSPEC_ATHENA_POS_W (((COMSPEC_ATHENA_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_ATHENA_W
    #define COMSPEC_ATHENA_W(n) ((n) * COMSPEC_ATHENA_POS_W)
#endif
#ifndef COMSPEC_ATHENA_H
    #define COMSPEC_ATHENA_H(n) ((n) * COMSPEC_ATHENA_POS_H)
#endif

#define ATHENA_BG_TITLE ATAK_BG_TITLE
#define ATHENA_BG_STRIP ATAK_BG_STRIP
#define ATHENA_BG_LIST ATAK_BG_LIST
#define ATHENA_BG_DETAIL ATAK_BG_DETAIL
#define ATHENA_TAB_IDLE ATAK_TAB_IDLE
#define ATHENA_TAB_ACTIVE ATAK_TAB_ACTIVE
#define ATHENA_BTN ATAK_BTN
#define ATHENA_BTN_FOCUS ATAK_BTN_F
#define ATHENA_BTN_ACCENT ATAK_BG_TILE
#define ATHENA_BTN_ACCENT_F ATAK_BG_TILE_F
#define ATHENA_BTN_OK ATAK_GO
#define ATHENA_BTN_OK_F ATAK_GO_F
#define ATHENA_BTN_WARN ATAK_WARN
#define ATHENA_BTN_WARN_F ATAK_WARN_F
#define ATHENA_BTN_DANGER ATAK_DANGER
#define ATHENA_BTN_DANGER_F ATAK_DANGER_F
#define ATHENA_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Athena: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9700;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_ATHENA_W(3));
            h = QUOTE(COMSPEC_ATHENA_H(0.62));
            size = QUOTE(COMSPEC_ATHENA_H(0.40));
            text = "  Athena";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_ATHENA_H(0.62));
            w = QUOTE(COMSPEC_ATHENA_W(3));
            h = QUOTE(COMSPEC_ATHENA_H(0.06));
            colorBackground[] = ATHENA_ACCENT;
        };

        // Fond opaque du panneau (évite le bleu Desktop qui transparaît entre les contrôles)
        class PanelFill: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_ATHENA_H(0.68));
            w = QUOTE(COMSPEC_ATHENA_W(3));
            h = QUOTE(COMSPEC_ATHENA_H(11.20));
            colorBackground[] = ATAK_BG_PANEL;
        };

        class Status: RscStructuredText
        {
            idc = 9701;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(0.74));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.46));
            text = "";
            colorBackground[] = ATHENA_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#C8CDD2";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "0.68";
            };
        };

        // --- Rangée 1 d’onglets ---
        class TabAll: COMSPEC_ATAK_Btn
        {
            idc = 9740;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(1.28));
            w = QUOTE(COMSPEC_ATHENA_W(0.70));
            h = QUOTE(COMSPEC_ATHENA_H(0.44));
            size = QUOTE(COMSPEC_ATHENA_H(0.26));
            text = "Tout";
            colorBackground[] = ATHENA_TAB_IDLE;
            colorBackground2[] = ATHENA_TAB_IDLE;
            colorBackgroundFocused[] = ATHENA_TAB_ACTIVE;
            onButtonClick = "['all'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };
        class TabPhoto: TabAll
        {
            idc = 9742;
            x = QUOTE(COMSPEC_ATHENA_W(0.80));
            text = "Photos";
            onButtonClick = "['photo'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };
        class TabOrder: TabAll
        {
            idc = 9743;
            x = QUOTE(COMSPEC_ATHENA_W(1.54));
            text = "Ordres";
            onButtonClick = "['order'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };
        class TabMessages: TabAll
        {
            idc = 9741;
            x = QUOTE(COMSPEC_ATHENA_W(2.28));
            text = "Messages";
            onButtonClick = "['messages'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };

        // --- Rangée 2 d’onglets ---
        class TabUrgences: TabAll
        {
            idc = 9745;
            y = QUOTE(COMSPEC_ATHENA_H(1.76));
            text = "Urgences";
            onButtonClick = "['urgences'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };
        class TabBda: TabAll
        {
            idc = 9744;
            x = QUOTE(COMSPEC_ATHENA_W(0.80));
            y = QUOTE(COMSPEC_ATHENA_H(1.76));
            text = "BDA";
            onButtonClick = "['bda'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };
        class TabLiaison: TabAll
        {
            idc = 9746;
            x = QUOTE(COMSPEC_ATHENA_W(1.54));
            y = QUOTE(COMSPEC_ATHENA_H(1.76));
            text = "Liaison";
            onButtonClick = "['liaison'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };
        class TabModules: TabAll
        {
            idc = 9747;
            x = QUOTE(COMSPEC_ATHENA_W(2.28));
            y = QUOTE(COMSPEC_ATHENA_H(1.76));
            text = "Modules";
            onButtonClick = "['modules'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };

        class SecNotif: RscStructuredText
        {
            idc = -1;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(2.28));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.24));
            text = "<t size='0.58' color='#5EC8F0'>NOTIFICATIONS</t>";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes { font = "RobotoCondensed"; align = "left"; valign = "middle"; shadow = 0; };
        };

        class NotifList: RscListBox
        {
            idc = 9715;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(2.52));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.52));
            colorBackground[] = ATAK_BG_LIST;
            colorText[] = ATAK_LIST_TEXT;
            colorSelect[] = ATAK_LIST_SEL;
            colorSelect2[] = ATAK_LIST_SEL;
            colorSelectBackground[] = ATAK_LIST_SEL_BG;
            colorSelectBackground2[] = ATAK_LIST_SEL_BG;
            sizeEx = QUOTE(COMSPEC_ATHENA_H(0.34));
            rowHeight = QUOTE(COMSPEC_ATHENA_H(0.48));
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_selectNotif";
        };

        class SecJournal: SecNotif
        {
            y = QUOTE(COMSPEC_ATHENA_H(3.10));
            text = "<t size='0.58' color='#5EC8F0'>JOURNAL</t>";
        };

        class Inbox: RscListBox
        {
            idc = 9710;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(3.34));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(1.00));
            colorBackground[] = ATHENA_BG_LIST;
            colorText[] = ATAK_LIST_TEXT;
            colorSelect[] = ATAK_LIST_SEL;
            colorSelect2[] = ATAK_LIST_SEL;
            colorSelectBackground[] = ATAK_LIST_SEL_BG;
            colorSelectBackground2[] = ATAK_LIST_SEL_BG;
            sizeEx = QUOTE(COMSPEC_ATHENA_H(0.36));
            rowHeight = QUOTE(COMSPEC_ATHENA_H(0.50));
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_selectInbox";
        };

        class DetailLabel: RscStructuredText
        {
            idc = -1;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(4.40));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.24));
            text = "<t size='0.58' color='#5EC8F0'>DÉTAIL</t>";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes
            {
                font = "RobotoCondensed";
                align = "left";
                valign = "middle";
                shadow = 0;
            };
        };

        // Zone lecture principale — hauteur généreuse (évite le texte coupé à 1 ligne).
        class Detail: RscStructuredText
        {
            idc = 9711;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(4.64));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(2.55));
            text = "";
            colorBackground[] = ATHENA_BG_DETAIL;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E8F0F4";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "0.72";
            };
        };

        class Feedback: RscStructuredText
        {
            idc = 9712;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(7.26));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.34));
            text = "";
            show = 0;
            colorBackground[] = {0.04, 0.12, 0.14, 0.96};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E8F4F0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "0.70";
            };
        };

        class SecAlertes: RscStructuredText
        {
            idc = -1;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(7.66));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.22));
            text = "<t size='0.58' color='#5EC8F0'>ALERTES RAPIDES</t>";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes { font = "RobotoCondensed"; align = "left"; valign = "middle"; shadow = 0; };
        };

        class BtnTic: COMSPEC_ATAK_BtnDanger
        {
            idc = 9720;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(7.90));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            size = QUOTE(COMSPEC_ATHENA_H(0.24));
            text = "Contact";
            onButtonClick = "['TIC'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };
        class BtnClear: BtnTic
        {
            idc = 9724;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Fin contact";
            colorBackground[] = ATHENA_BTN_OK;
            colorBackground2[] = ATHENA_BTN_OK;
            colorBackgroundFocused[] = ATHENA_BTN_OK_F;
            onButtonClick = "['TIC_CLEAR'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { font = "RobotoCondensed"; color = "#7CFF9A"; align = "center"; valign = "middle"; shadow = "false"; };
        };
        class BtnEagle: BtnTic
        {
            idc = 9723;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Op. à terre";
            colorBackground[] = ATHENA_BTN_WARN;
            colorBackground2[] = ATHENA_BTN_WARN;
            colorBackgroundFocused[] = ATHENA_BTN_WARN_F;
            onButtonClick = "['EAGLE_DOWN'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { font = "RobotoCondensed"; color = "#FFD080"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class SecRapports: SecAlertes
        {
            y = QUOTE(COMSPEC_ATHENA_H(8.40));
            text = "<t size='0.58' color='#5EC8F0'>COMPTES-RENDUS</t>";
        };

        class BtnFrago: COMSPEC_ATAK_Btn
        {
            idc = 9721;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(8.64));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            size = QUOTE(COMSPEC_ATHENA_H(0.24));
            text = "FRAGO";
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "['FRAGO'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };
        class BtnBda: BtnFrago
        {
            idc = 9725;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "BDA";
            onButtonClick = "['BDA'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };
        class BtnSalute: BtnFrago
        {
            idc = 9722;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "SALUTE";
            onButtonClick = "['SALUTE'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };

        class SecAir: SecAlertes
        {
            y = QUOTE(COMSPEC_ATHENA_H(9.14));
            text = "<t size='0.58' color='#5EC8F0'>APPUI & BRIEFING</t>";
        };

        class BtnCas: COMSPEC_ATAK_BtnWarn
        {
            idc = 9750;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(9.38));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.40));
            size = QUOTE(COMSPEC_ATHENA_H(0.22));
            text = "Appui aérien";
            colorBackground[] = ATHENA_BTN_WARN;
            colorBackground2[] = ATHENA_BTN_WARN;
            colorBackgroundFocused[] = ATHENA_BTN_WARN_F;
            onButtonClick = "['CAS'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };
        class BtnManifest: BtnCas
        {
            idc = 9751;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Manifeste";
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "['MANIFEST'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
        };
        class BtnBriefing: BtnCas
        {
            idc = 9752;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Briefing";
            colorBackground[] = ATHENA_BTN_ACCENT;
            colorBackground2[] = ATHENA_BTN_ACCENT;
            colorBackgroundFocused[] = ATHENA_BTN_ACCENT_F;
            onButtonClick = "['BRIEFING'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };

        class BtnPhoto: COMSPEC_ATAK_Btn
        {
            idc = 9732;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(9.86));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.40));
            size = QUOTE(COMSPEC_ATHENA_H(0.22));
            text = "Renvoyer photo";
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_sendPhoto";
        };
        class BtnWebMarker: BtnPhoto
        {
            idc = 9739;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Repère web";
            colorBackground[] = ATHENA_BTN_ACCENT;
            colorBackground2[] = ATHENA_BTN_ACCENT;
            colorBackgroundFocused[] = ATHENA_BTN_ACCENT_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_createWebMarker";
        };
        class BtnSeekTx: BtnPhoto
        {
            idc = 9753;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "TX SEEK";
            colorBackground[] = ATHENA_BTN_OK;
            colorBackground2[] = ATHENA_BTN_OK;
            colorBackgroundFocused[] = ATHENA_BTN_OK_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_sendSeekData";
            class Attributes { font = "RobotoCondensed"; color = "#7CFF9A"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class SecTriage: SecAlertes
        {
            y = QUOTE(COMSPEC_ATHENA_H(10.34));
            text = "<t size='0.58' color='#5EC8F0'>TRIAGE MÉDICAL</t>";
        };

        class BtnTriageEnCours: COMSPEC_ATAK_BtnWarn
        {
            idc = 9736;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(10.58));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.40));
            size = QUOTE(COMSPEC_ATHENA_H(0.20));
            text = "Prise en charge";
            colorBackground[] = ATHENA_BTN_WARN;
            colorBackground2[] = ATHENA_BTN_WARN;
            colorBackgroundFocused[] = ATHENA_BTN_WARN_F;
            onButtonClick = "['en_cours'] call comspec_overwatch_connect_fnc_medicalTriage";
        };
        class BtnTriageTraite: BtnTriageEnCours
        {
            idc = 9737;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Traité";
            colorBackground[] = ATHENA_BTN_OK;
            colorBackground2[] = ATHENA_BTN_OK;
            colorBackgroundFocused[] = ATHENA_BTN_OK_F;
            onButtonClick = "['traite'] call comspec_overwatch_connect_fnc_medicalTriage";
            class Attributes { font = "RobotoCondensed"; color = "#7CFF9A"; align = "center"; valign = "middle"; shadow = "false"; };
        };
        class BtnTriageAnnule: BtnTriageEnCours
        {
            idc = 9738;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Annuler";
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "['annule'] call comspec_overwatch_connect_fnc_medicalTriage";
            class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class SecOutils: SecAlertes
        {
            y = QUOTE(COMSPEC_ATHENA_H(11.06));
            text = "<t size='0.58' color='#5EC8F0'>LIAISON & COMPTE</t>";
        };

        class BtnLink: COMSPEC_ATAK_Btn
        {
            idc = 9734;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(11.30));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.40));
            size = QUOTE(COMSPEC_ATHENA_H(0.20));
            text = "Compte Athena";
            colorBackground[] = ATHENA_BTN_ACCENT;
            colorBackground2[] = ATHENA_BTN_ACCENT;
            colorBackgroundFocused[] = ATHENA_BTN_ACCENT_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_showLinkDialog";
        };
        class BtnPhoneQr: BtnLink
        {
            idc = 9735;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Adresse mobile";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_showPhoneConnect";
        };
        class BtnRefresh: BtnLink
        {
            idc = 9731;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Actualiser";
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_refresh";
        };

        // Conservé pour compat idc (masqué) — plus d’ouverture tablette depuis le panneau
        class BtnTablet: COMSPEC_ATAK_Btn
        {
            idc = 9730;
            x = 0;
            y = 0;
            w = 0;
            h = 0;
            show = 0;
            text = "";
            onButtonClick = "";
        };
        class BtnModulesLog: BtnTablet
        {
            idc = 9733;
        };
    };
};
