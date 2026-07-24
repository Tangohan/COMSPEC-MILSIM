/*
    Overlay roleplay pour effets visuels ingame.
    Affiche parasites, glitchs, indicateurs de qualité réseau.
*/

class COMSPEC_RoleplayOverlay
{
    idd = 16800;
    movingEnable = 0;
    onLoad = "uiNamespace setVariable ['COMSPEC_RoleplayOverlay', _this select 0];";
    onUnload = "uiNamespace setVariable ['COMSPEC_RoleplayOverlay', nil];";
    
    class ControlsBackground
    {
        // Effet de parasites (scan lines)
        class ScanLines: RscText
        {
            idc = 16801;
            x = 0;
            y = 0;
            w = 1;
            h = 1;
            colorBackground[] = {0, 0, 0, 0};
            // Texture procédurale pour l'instant (remplacer par PAA si besoin)
            text = "#(argb,8,8,3)color(0.1,0.1,0.1,0.1)";
        };
        
        // Overlay de glitch (flash rouge)
        class GlitchOverlay: RscText
        {
            idc = 16802;
            x = 0;
            y = 0;
            w = 1;
            h = 1;
            colorBackground[] = {0.8, 0, 0, 0};
        };
        
        // Overlay de déconnexion (voile noir)
        class DisconnectOverlay: RscText
        {
            idc = 16803;
            x = 0;
            y = 0;
            w = 1;
            h = 1;
            colorBackground[] = {0, 0, 0, 0};
        };
    };
    
    class Controls
    {
        // Indicateur de qualité réseau (coin supérieur droit)
        class NetworkQuality: RscStructuredText
        {
            idc = 16810;
            x = safeZoneX + safeZoneW - 0.25;
            y = safeZoneY + 0.02;
            w = 0.23;
            h = 0.15;
            colorBackground[] = {0, 0, 0, 0.6};
            size = 0.028;
            text = "";
        };
        
        // Message de déconnexion (centre écran)
        class DisconnectMessage: RscStructuredText
        {
            idc = 16811;
            x = safeZoneX + safeZoneW * 0.5 - 0.2;
            y = safeZoneY + safeZoneH * 0.5 - 0.1;
            w = 0.4;
            h = 0.2;
            colorBackground[] = {0.1, 0.1, 0.1, 0.9};
            size = 0.04;
            text = "";
        };
        
        // Indicateur de packet loss (bas de l'écran)
        class PacketLossIndicator: RscStructuredText
        {
            idc = 16812;
            x = safeZoneX + safeZoneW * 0.5 - 0.15;
            y = safeZoneY + safeZoneH - 0.15;
            w = 0.3;
            h = 0.08;
            colorBackground[] = {0, 0, 0, 0.7};
            size = 0.032;
            text = "";
        };
        
        // Barre de progression déconnexion
        class DisconnectProgress: RscProgress
        {
            idc = 16813;
            x = safeZoneX + safeZoneW * 0.5 - 0.18;
            y = safeZoneY + safeZoneH * 0.5 + 0.08;
            w = 0.36;
            h = 0.02;
            colorBar[] = {0.8, 0.2, 0.2, 0.8};
            colorFrame[] = {0.3, 0.3, 0.3, 0.8};
            texture = "#(argb,8,8,3)color(1,1,1,1)";
        };
    };
};
