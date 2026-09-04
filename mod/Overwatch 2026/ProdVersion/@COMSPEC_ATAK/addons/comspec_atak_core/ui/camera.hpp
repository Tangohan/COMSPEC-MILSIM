class COMSPEC_ATAK_CameraHud
{
    idd = 88510;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_ATAK_CameraHudDisplay', _this select 0];";
    onUnload = "if (missionNamespace getVariable ['COMSPEC_ATAK_CameraOpen', false] && {!(missionNamespace getVariable ['COMSPEC_ATAK_CameraClosing', false])}) then { [] spawn { [] call COMSPEC_fnc_cameraClose; }; };";

    class controlsBackground {};
    class controls
    {
        class Hint: RscStructuredText
        {
            idc = 1203;
            x = safeZoneX + 0.04 * safeZoneW;
            y = safeZoneY + safeZoneH - 0.20;
            w = 0.42 * safeZoneW;
            h = 0.07;
            size = 0.032;
            colorBackground[] = {0.02, 0.03, 0.03, 0.55};
            text = "Déclencher prend le cliché. Le viseur disparaît le temps de la prise. Échap ferme.";
        };

        class Shot: RscButton
        {
            idc = 1201;
            text = "DÉCLENCHER";
            x = 0.5 - 0.14;
            y = safeZoneY + safeZoneH - 0.12;
            w = 0.28;
            h = 0.058;
            colorBackground[] = {0.07, 0.14, 0.12, 0.82};
            colorBackgroundActive[] = {0.12, 0.28, 0.22, 0.95};
            onButtonClick = "[] call COMSPEC_fnc_cameraShot;";
        };

        class Close: RscButton
        {
            idc = 1202;
            text = "FERMER";
            x = safeZoneX + safeZoneW - 0.18;
            y = safeZoneY + 0.035;
            w = 0.14;
            h = 0.042;
            colorBackground[] = {0.08, 0.08, 0.08, 0.7};
            onButtonClick = "[] call COMSPEC_fnc_cameraClose;";
        };
    };
};

class RscTitles
{
    class COMSPEC_ATAK_CameraOverlay
    {
        idd = -1;
        duration = 1e10;
        fadeIn = 0;
        fadeOut = 0;
        movingEnable = 0;
        onLoad = "uiNamespace setVariable ['COMSPEC_ATAK_CameraOverlayDisplay', _this select 0];";
        onUnload = "uiNamespace setVariable ['COMSPEC_ATAK_CameraOverlayDisplay', displayNull];";

        class controls
        {
            class Overlay: RscPicture
            {
                idc = 1210;
                text = "\z\comspec_atak\addons\comspec_atak_core\web\media\camera-overlay.png";
                x = "safeZoneXAbs";
                y = "safeZoneY";
                w = "safeZoneWAbs";
                h = "safeZoneH";
                colorText[] = {1, 1, 1, 1};
            };

            class JpegBadge: RscText
            {
                idc = 1214;
                text = "JPEG";
                x = "safeZoneX + safeZoneW - 0.22";
                y = "safeZoneY + safeZoneH - 0.085";
                w = 0.08;
                h = 0.036;
                sizeEx = 0.032;
                style = 2;
                colorText[] = {0.92, 0.93, 0.90, 1};
                colorBackground[] = {0.02, 0.03, 0.03, 0.55};
            };
        };
    };
};
