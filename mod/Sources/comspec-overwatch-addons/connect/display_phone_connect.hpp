#include "display_device_macros.hpp"

// Connexion téléphone Athena — cadre téléphone Overwatch (assets cTab NSWDG repris / préfixés).
// Zone écran utile cTab android : (452,713) taille 1098×626 (barre d’état 60 px incluse).
class COMSPEC_PhoneConnect_Dialog {
    idd = 9971;
    movingEnable = 1;
    onLoad = "";

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
            text = "<t align='center' size='0.5' color='#9ab0c0'>ATHENA · PHONE</t>";
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
            text = "<t font='RobotoCondensedBold' size='0.78' align='center' color='#e8f4f0'>Phone connection</t>";
            x = COMSPEC_PHONE_PX(470);
            y = COMSPEC_PHONE_PY(772);
            w = COMSPEC_PHONE_PW(1060);
            h = COMSPEC_PHONE_PH(40);
        };

        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.52' color='#b0c4d4'>Scan the QR code with your phone, or enter the code on the connection page.</t>";
            x = COMSPEC_PHONE_PX(470);
            y = COMSPEC_PHONE_PY(812);
            w = COMSPEC_PHONE_PW(1060);
            h = COMSPEC_PHONE_PH(48);
        };

        class QrFrame: RscText {
            idc = 9020;
            x = COMSPEC_PHONE_PX(760);
            y = COMSPEC_PHONE_PY(868);
            w = COMSPEC_PHONE_PW(480);
            h = COMSPEC_PHONE_PH(250);
            colorBackground[] = {0.08, 0.1, 0.14, 1};
        };

        class QrPicture: RscPictureKeepAspect {
            idc = 9021;
            text = "";
            x = COMSPEC_PHONE_PX(772);
            y = COMSPEC_PHONE_PY(875);
            w = COMSPEC_PHONE_PW(456);
            h = COMSPEC_PHONE_PH(236);
        };

        class QrFallback: RscStructuredText {
            idc = 9026;
            text = "";
            x = COMSPEC_PHONE_PX(490);
            y = COMSPEC_PHONE_PY(880);
            w = COMSPEC_PHONE_PW(1020);
            h = COMSPEC_PHONE_PH(220);
        };

        class CodeLabel: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.5' color='#8ab89a'>PAIRING CODE</t>";
            x = COMSPEC_PHONE_PX(470);
            y = COMSPEC_PHONE_PY(1125);
            w = COMSPEC_PHONE_PW(1060);
            h = COMSPEC_PHONE_PH(28);
        };

        class CodeText: RscStructuredText {
            idc = 9022;
            text = "<t align='center' size='1.25' font='RobotoCondensedBold' color='#ffffff'>——————</t>";
            x = COMSPEC_PHONE_PX(470);
            y = COMSPEC_PHONE_PY(1150);
            w = COMSPEC_PHONE_PW(1060);
            h = COMSPEC_PHONE_PH(48);
            size = 1.0;
        };

        class UrlText: RscStructuredText {
            idc = 9023;
            text = "";
            x = COMSPEC_PHONE_PX(470);
            y = COMSPEC_PHONE_PY(1200);
            w = COMSPEC_PHONE_PW(1060);
            h = COMSPEC_PHONE_PH(42);
            size = 0.45;
        };

        class RefreshButton: RscButton {
            idc = 9024;
            text = "New code";
            x = COMSPEC_PHONE_PX(490);
            y = COMSPEC_PHONE_PY(1255);
            w = COMSPEC_PHONE_PW(500);
            h = COMSPEC_PHONE_PH(58);
            sizeEx = 0.026;
            colorBackground[] = {0.06, 0.18, 0.22, 0.95};
            action = "[] call comspec_overwatch_connect_fnc_phoneConnectShow;";
        };

        class CloseButton: RscButton {
            idc = 9025;
            text = "Close";
            x = COMSPEC_PHONE_PX(1010);
            y = COMSPEC_PHONE_PY(1255);
            w = COMSPEC_PHONE_PW(500);
            h = COMSPEC_PHONE_PH(58);
            sizeEx = 0.026;
            colorBackground[] = {0.14, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
