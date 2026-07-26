#include "display_device_macros.hpp"



// Connexion téléphone Athena — cadre téléphone Overwatch.

// Affiche URL + code au centre (pas de QR : rarement disponible en jeu).

class COMSPEC_PhoneConnect_Dialog {

    idd = 9971;

    movingEnable = 1;

    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_phoneConnectDialogOnLoad;";

    onUnload = "uiNamespace setVariable ['COMSPEC_PhoneConnect_Display', displayNull];";



    class ControlsBackground {

        class PhoneBezel: RscPicture {

            idc = 9010;

            text = COMSPEC_IMG_PHONE_BG;

            x = COMSPEC_PHONE_X;

            y = COMSPEC_PHONE_Y;

            w = COMSPEC_PHONE_W;

            h = COMSPEC_PHONE_H;

        };

    };



    class Controls {

        class StatusBar: RscText {

            idc = -1;

            x = COMSPEC_PHONE_PX(452);

            y = COMSPEC_PHONE_PY(713);

            w = COMSPEC_PHONE_PW(1098);

            h = COMSPEC_PHONE_PH(52);

            colorBackground[] = {0.02, 0.05, 0.08, 0.92};

        };



        class IconBattery: RscPicture {

            idc = -1;

            text = COMSPEC_IMG_ICON_BATTERY;

            x = COMSPEC_PHONE_PX(470);

            y = COMSPEC_PHONE_PY(718);

            w = COMSPEC_PHONE_PW(38);

            h = COMSPEC_PHONE_PH(38);

            colorText[] = {1, 1, 1, 1};

        };



        class IconSignal: RscPicture {

            idc = -1;

            text = COMSPEC_IMG_ICON_SIGNAL;

            x = COMSPEC_PHONE_PX(1488);

            y = COMSPEC_PHONE_PY(718);

            w = COMSPEC_PHONE_PW(38);

            h = COMSPEC_PHONE_PH(38);

            colorText[] = {1, 1, 1, 1};

        };



        class StatusLabel: RscStructuredText {

            idc = -1;

            text = "<t align='center' size='0.5' color='#9ab0c0'>ATHENA · MOBILE</t>";

            x = COMSPEC_PHONE_PX(520);

            y = COMSPEC_PHONE_PY(718);

            w = COMSPEC_PHONE_PW(950);

            h = COMSPEC_PHONE_PH(40);

        };



        class ScreenFill: RscText {

            idc = -1;

            x = COMSPEC_PHONE_PX(452);

            y = COMSPEC_PHONE_PY(765);

            w = COMSPEC_PHONE_PW(1098);

            h = COMSPEC_PHONE_PH(574);

            colorBackground[] = {0.02, 0.05, 0.1, 0.94};

        };



        class Title: RscStructuredText {

            idc = -1;

            text = "<t font='RobotoCondensedBold' size='0.78' align='center' color='#e8f4f0'>Liaison téléphone</t>";

            x = COMSPEC_PHONE_PX(470);

            y = COMSPEC_PHONE_PY(772);

            w = COMSPEC_PHONE_PW(1060);

            h = COMSPEC_PHONE_PH(40);

        };



        class Hint: RscStructuredText {

            idc = -1;

            text = "<t align='center' size='0.52' color='#b0c4d4'>Sur votre téléphone, ouvrez l’adresse ci-dessous puis saisissez le code.</t>";

            x = COMSPEC_PHONE_PX(470);

            y = COMSPEC_PHONE_PY(812);

            w = COMSPEC_PHONE_PW(1060);

            h = COMSPEC_PHONE_PH(44);

        };



        // Cadre central : URL + code (remplace la zone QR)

        class CenterPanel: RscText {

            idc = 9020;

            x = COMSPEC_PHONE_PX(500);

            y = COMSPEC_PHONE_PY(868);

            w = COMSPEC_PHONE_PW(1000);

            h = COMSPEC_PHONE_PH(300);

            colorBackground[] = {0.06, 0.09, 0.12, 1};

        };



        class CenterContent: RscStructuredText {

            idc = 9026;

            text = "<t align='center' size='0.55' color='#8aa0b4'>Chargement…</t>";

            x = COMSPEC_PHONE_PX(520);

            y = COMSPEC_PHONE_PY(880);

            w = COMSPEC_PHONE_PW(960);

            h = COMSPEC_PHONE_PH(276);

        };



        // Conservés pour compat / scripts (masqués — contenu dans CenterContent)

        class QrPicture: RscPictureKeepAspect {

            idc = 9021;

            text = "";

            show = 0;

            x = COMSPEC_PHONE_PX(0);

            y = COMSPEC_PHONE_PY(0);

            w = COMSPEC_PHONE_PW(1);

            h = COMSPEC_PHONE_PH(1);

        };



        class UrlText: RscStructuredText {

            idc = 9023;

            text = "";

            show = 0;

            x = COMSPEC_PHONE_PX(0);

            y = COMSPEC_PHONE_PY(0);

            w = COMSPEC_PHONE_PW(1);

            h = COMSPEC_PHONE_PH(1);

        };



        class CodeText: RscStructuredText {

            idc = 9022;

            text = "";

            show = 0;

            x = COMSPEC_PHONE_PX(0);

            y = COMSPEC_PHONE_PY(0);

            w = COMSPEC_PHONE_PW(1);

            h = COMSPEC_PHONE_PH(1);

        };



        class RefreshButton: RscButton {

            idc = 9024;

            text = "Nouveau code";

            x = COMSPEC_PHONE_PX(490);

            y = COMSPEC_PHONE_PY(1190);

            w = COMSPEC_PHONE_PW(500);

            h = COMSPEC_PHONE_PH(58);

            sizeEx = 0.026;

            colorBackground[] = {0.06, 0.18, 0.22, 0.95};

            action = "private _d = uiNamespace getVariable ['COMSPEC_PhoneConnect_Display', displayNull]; if (isNull _d) then { _d = findDisplay 9971; }; [_d] call comspec_overwatch_connect_fnc_phoneConnectDialogOnLoad;";

        };



        class CloseButton: RscButton {

            idc = 9025;

            text = "Fermer";

            x = COMSPEC_PHONE_PX(1010);

            y = COMSPEC_PHONE_PY(1190);

            w = COMSPEC_PHONE_PW(500);

            h = COMSPEC_PHONE_PH(58);

            sizeEx = 0.026;

            colorBackground[] = {0.14, 0.08, 0.08, 0.95};

            action = "closeDialog 0;";

        };

    };

};


