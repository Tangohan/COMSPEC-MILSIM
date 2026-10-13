// Dépannage liaison (idd 9995) : diagnostic immédiat de la liaison Athena, causes probables et corrections,
// puis pas à pas (une fonction à la fois) pour trouver ce qui arrête le jeu. Couleurs de l'ATAK natif.
#define DGX(v) ((v) * safezoneW + safezoneX)
#define DGY(v) ((v) * safezoneH + safezoneY)
#define DGW(v) ((v) * safezoneW)
#define DGH(v) ((v) * safezoneH)

class COMSPEC_DiagIsolate_Dialog {
    idd = 9995;
    movingEnable = 0;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_trDisplay; (_this select 0) call comspec_overwatch_connect_fnc_diagIsolateOnLoad;";
    onUnload = "uiNamespace setVariable ['COMSPEC_DiagIsolate_Display', displayNull];";

    class ControlsBackground {
        class Shade: RscText {
            idc = -1;
            x = safezoneX;
            y = safezoneY;
            w = safezoneW;
            h = safezoneH;
            colorBackground[] = {0, 0, 0, 0.45};
        };
        class Background: RscText {
            idc = -1;
            x = DGX(0.24);
            y = DGY(0.15);
            w = DGW(0.52);
            h = DGH(0.68);
            colorBackground[] = {0.025, 0.030, 0.027, 0.98};
        };
        class Header: RscText {
            idc = -1;
            x = DGX(0.24);
            y = DGY(0.15);
            w = DGW(0.52);
            h = DGH(0.07);
            colorBackground[] = {0.045, 0.055, 0.048, 1};
        };
        class Accent: RscText {
            idc = 32;
            x = DGX(0.24);
            y = DGY(0.22);
            w = DGW(0.52);
            h = DGH(0.003);
            colorBackground[] = {0.36, 0.78, 0.42, 1};
        };
        class IsoPanel: RscText {
            idc = -1;
            x = DGX(0.252);
            y = DGY(0.625);
            w = DGW(0.496);
            h = DGH(0.07);
            colorBackground[] = {0.045, 0.055, 0.048, 1};
        };
    };

    class Controls {
        class Title: RscStructuredText {
            idc = 30;
            text = "<t font='PuristaSemibold' size='1.25' color='#E6F0E8'>Dépannage liaison</t><br/><t font='PuristaMedium' size='0.75' color='#94A399'>COMSPEC Link · Athena · ATAK</t>";
            x = DGX(0.252);
            y = DGY(0.158);
            w = DGW(0.30);
            h = DGH(0.058);
        };
        class Verdict: RscStructuredText {
            idc = 31;
            text = "";
            x = DGX(0.55);
            y = DGY(0.163);
            w = DGW(0.198);
            h = DGH(0.05);
        };
        class Checks: RscControlsGroup {
            idc = 40;
            x = DGX(0.252);
            y = DGY(0.232);
            w = DGW(0.496);
            h = DGH(0.385);
            class VScrollbar {
                width = 0.012;
                autoScrollEnabled = 0;
                color[] = {0.36, 0.78, 0.42, 0.75};
                colorActive[] = {0.46, 0.88, 0.52, 1};
                colorDisabled[] = {0.3, 0.4, 0.35, 0.4};
                shadow = 0;
                scrollSpeed = 0.06;
            };
            class HScrollbar {
                height = 0;
                color[] = {0, 0, 0, 0};
            };
            class ScrollBar {
                color[] = {0.36, 0.78, 0.42, 0.75};
                colorActive[] = {0.46, 0.88, 0.52, 1};
                colorDisabled[] = {0.3, 0.4, 0.35, 0.4};
                shadow = 0;
                thumb = "\A3\ui_f\data\gui\cfg\scrollbar\thumb_ca.paa";
                arrowFull = "\A3\ui_f\data\gui\cfg\scrollbar\arrowFull_ca.paa";
                arrowEmpty = "\A3\ui_f\data\gui\cfg\scrollbar\arrowEmpty_ca.paa";
                border = "\A3\ui_f\data\gui\cfg\scrollbar\border_ca.paa";
            };
            class Controls {
                class Body: RscStructuredText {
                    idc = 41;
                    text = "";
                    x = 0;
                    y = 0;
                    w = DGW(0.48);
                    h = DGH(0.385);
                    colorBackground[] = {0, 0, 0, 0};
                };
            };
        };
        class IsoText: RscStructuredText {
            idc = 50;
            text = "";
            x = DGX(0.258);
            y = DGY(0.629);
            w = DGW(0.27);
            h = DGH(0.062);
        };
        class BtnQuick: COMSPEC_RscButtonAtak {
            idc = 51;
            text = "Pas à pas rapide (20 s)";
            x = DGX(0.532);
            y = DGY(0.636);
            w = DGW(0.105);
            h = DGH(0.048);
            action = "['quick'] call comspec_overwatch_connect_fnc_diagAction;";
        };
        class BtnFull: COMSPEC_RscButtonAtak {
            idc = 52;
            text = "Complet (55 s)";
            x = DGX(0.641);
            y = DGY(0.636);
            w = DGW(0.101);
            h = DGH(0.048);
            action = "['full'] call comspec_overwatch_connect_fnc_diagAction;";
        };
        class BtnStop: COMSPEC_RscButtonAtakDanger {
            idc = 53;
            text = "Arrêter le pas à pas";
            x = DGX(0.532);
            y = DGY(0.636);
            w = DGW(0.21);
            h = DGH(0.048);
            action = "['stop'] call comspec_overwatch_connect_fnc_diagAction;";
        };

        // Corrections : la plus utile d'après le diagnostic passe en vert (fn_diagIsolateOnLoad).
        class BtnReconnect: COMSPEC_RscButtonAtak {
            idc = 61;
            text = "Reconnecter Athena";
            x = DGX(0.252);
            y = DGY(0.705);
            w = DGW(0.121);
            h = DGH(0.045);
            action = "['reconnect'] call comspec_overwatch_connect_fnc_diagAction;";
        };
        class BtnLink: COMSPEC_RscButtonAtak {
            idc = 62;
            text = "Lier mon compte";
            x = DGX(0.377);
            y = DGY(0.705);
            w = DGW(0.121);
            h = DGH(0.045);
            action = "['link'] call comspec_overwatch_connect_fnc_diagAction;";
        };
        class BtnEnable: COMSPEC_RscButtonAtak {
            idc = 63;
            text = "Rallumer Overwatch";
            x = DGX(0.502);
            y = DGY(0.705);
            w = DGW(0.121);
            h = DGH(0.045);
            action = "['enable'] call comspec_overwatch_connect_fnc_diagAction;";
        };
        class BtnAthena: COMSPEC_RscButtonAtak {
            idc = 64;
            text = "Service Athéna";
            x = DGX(0.627);
            y = DGY(0.705);
            w = DGW(0.121);
            h = DGH(0.045);
            action = "['athena'] call comspec_overwatch_connect_fnc_diagAction;";
        };

        class BtnRecheck: COMSPEC_RscButtonAtak {
            idc = 65;
            text = "Revérifier";
            x = DGX(0.252);
            y = DGY(0.765);
            w = DGW(0.121);
            h = DGH(0.045);
            action = "['recheck'] call comspec_overwatch_connect_fnc_diagAction;";
        };
        class BtnCopy: COMSPEC_RscButtonAtak {
            idc = 66;
            text = "Copier le rapport";
            x = DGX(0.377);
            y = DGY(0.765);
            w = DGW(0.121);
            h = DGH(0.045);
            action = "['copy'] call comspec_overwatch_connect_fnc_diagAction;";
        };
        class BtnBug: COMSPEC_RscButtonAtak {
            idc = 67;
            text = "Signaler un problème";
            x = DGX(0.502);
            y = DGY(0.765);
            w = DGW(0.121);
            h = DGH(0.045);
            action = "['bug'] call comspec_overwatch_connect_fnc_diagAction;";
        };
        class BtnClose: COMSPEC_RscButtonAtak {
            idc = 68;
            text = "Fermer";
            x = DGX(0.627);
            y = DGY(0.765);
            w = DGW(0.121);
            h = DGH(0.045);
            action = "closeDialog 0;";
        };
    };
};

#undef DGX
#undef DGY
#undef DGW
#undef DGH
