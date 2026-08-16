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

#define ATHENA_BG_TITLE {0.02, 0.05, 0.07, 0.92}
#define ATHENA_BG_STRIP {0.03, 0.07, 0.09, 0.88}
#define ATHENA_BG_LIST {0.02, 0.05, 0.06, 0.86}
#define ATHENA_BG_DETAIL {0.025, 0.055, 0.07, 0.82}
#define ATHENA_TAB_IDLE {0.06, 0.1, 0.12, 1}
#define ATHENA_TAB_ACTIVE {0.08, 0.32, 0.28, 1}
#define ATHENA_BTN {0.06, 0.14, 0.2, 0.95}
#define ATHENA_BTN_FOCUS {0.1, 0.28, 0.35, 1}
#define ATHENA_BTN_ACCENT {0.08, 0.28, 0.26, 0.95}
#define ATHENA_BTN_ACCENT_F {0.12, 0.4, 0.36, 1}
#define ATHENA_BTN_OK {0.1, 0.26, 0.2, 0.95}
#define ATHENA_BTN_OK_F {0.14, 0.38, 0.28, 1}
#define ATHENA_BTN_WARN {0.28, 0.14, 0.08, 0.95}
#define ATHENA_BTN_WARN_F {0.4, 0.22, 0.1, 1}
#define ATHENA_BTN_DANGER {0.28, 0.1, 0.08, 0.95}
#define ATHENA_BTN_DANGER_F {0.42, 0.14, 0.1, 1}
#define ATHENA_ACCENT {0.2, 0.85, 0.65, 0.9}

class COMSPEC_ATAK_Athena: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9700;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_ATHENA_W(3));
            h = QUOTE(COMSPEC_ATHENA_H(0.62));
            size = QUOTE(COMSPEC_ATHENA_H(0.44));
            text = "Athena";
            colorBackground[] = ATHENA_BG_TITLE;
            colorBackground2[] = ATHENA_BG_TITLE;
            colorBackgroundFocused[] = {0.04, 0.1, 0.12, 0.95};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes { align = "center"; valign = "middle"; };
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
            colorBackground[] = {0.02, 0.05, 0.07, 0.94};
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
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "0.68";
            };
        };

        // --- Rangée 1 d’onglets ---
        class TabAll: BCE_RscButtonMenu
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
            class Attributes { align = "center"; valign = "middle"; };
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
            text = "<t size='0.58' color='#5a9e88'>NOTIFICATIONS</t>";
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
            colorBackground[] = {0.025, 0.06, 0.08, 0.9};
            colorSelect[] = {0.02, 0.04, 0.05, 1};
            colorSelect2[] = {0.02, 0.04, 0.05, 1};
            colorSelectBackground[] = {0.35, 0.55, 0.48, 0.88};
            colorSelectBackground2[] = {0.35, 0.55, 0.48, 0.88};
            sizeEx = QUOTE(COMSPEC_ATHENA_H(0.34));
            rowHeight = QUOTE(COMSPEC_ATHENA_H(0.48));
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_selectNotif";
        };

        class SecJournal: SecNotif
        {
            y = QUOTE(COMSPEC_ATHENA_H(3.10));
            text = "<t size='0.58' color='#5a9e88'>JOURNAL</t>";
        };

        class Inbox: RscListBox
        {
            idc = 9710;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(3.34));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(1.00));
            colorBackground[] = ATHENA_BG_LIST;
            colorSelect[] = {0.02, 0.04, 0.05, 1};
            colorSelect2[] = {0.02, 0.04, 0.05, 1};
            colorSelectBackground[] = {0.45, 0.72, 0.62, 0.92};
            colorSelectBackground2[] = {0.45, 0.72, 0.62, 0.92};
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
            text = "<t size='0.58' color='#5a9e88'>DÉTAIL</t>";
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
            text = "<t size='0.58' color='#5a9e88'>ALERTES RAPIDES</t>";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes { font = "RobotoCondensed"; align = "left"; valign = "middle"; shadow = 0; };
        };

        class BtnTic: BCE_RscButtonMenu
        {
            idc = 9720;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(7.90));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            size = QUOTE(COMSPEC_ATHENA_H(0.24));
            text = "Contact";
            colorBackground[] = ATHENA_BTN_DANGER;
            colorBackground2[] = ATHENA_BTN_DANGER;
            colorBackgroundFocused[] = ATHENA_BTN_DANGER_F;
            onButtonClick = "['TIC'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { align = "center"; valign = "middle"; };
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
        };

        class SecRapports: SecAlertes
        {
            y = QUOTE(COMSPEC_ATHENA_H(8.40));
            text = "<t size='0.58' color='#5a9e88'>COMPTES-RENDUS</t>";
        };

        class BtnFrago: BCE_RscButtonMenu
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
            class Attributes { align = "center"; valign = "middle"; };
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
            text = "<t size='0.58' color='#5a9e88'>APPUI & BRIEFING</t>";
        };

        class BtnCas: BCE_RscButtonMenu
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
            class Attributes { align = "center"; valign = "middle"; };
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

        class BtnPhoto: BCE_RscButtonMenu
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
            class Attributes { align = "center"; valign = "middle"; };
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
        };

        class SecTriage: SecAlertes
        {
            y = QUOTE(COMSPEC_ATHENA_H(10.34));
            text = "<t size='0.58' color='#5a9e88'>TRIAGE MÉDICAL</t>";
        };

        class BtnTriageEnCours: BCE_RscButtonMenu
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
            class Attributes { align = "center"; valign = "middle"; };
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
        };

        class SecOutils: SecAlertes
        {
            y = QUOTE(COMSPEC_ATHENA_H(11.06));
            text = "<t size='0.58' color='#5a9e88'>LIAISON & COMPTE</t>";
        };

        class BtnLink: BCE_RscButtonMenu
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
            class Attributes { align = "center"; valign = "middle"; };
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
        class BtnTablet: BCE_RscButtonMenu
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
