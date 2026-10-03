#include "colors.hpp"
class RscText;
class RscStructuredText;
class RscButton;
class RscEdit;
class RscCombo;
class RscListBox;
class RscCheckBox;
class RscProgress;
class RscPicture;
class RscMapControl;
class RscControlsGroup {
    class VScrollbar;
    class HScrollbar;
};

class COMSPEC_RscText: RscText { font="RobotoCondensed"; colorText[]=ATAK_TEXT; colorBackground[]={0,0,0,0}; sizeEx="0.026 * safeZoneH"; };
class COMSPEC_RscTextRight: COMSPEC_RscText { style=1; };
class COMSPEC_RscTextCenter: COMSPEC_RscText { style=2; };
class COMSPEC_RscStructuredText: RscStructuredText { colorText[]=ATAK_TEXT; colorBackground[]={0,0,0,0}; size="0.024 * safeZoneH"; };
class COMSPEC_RscButton: RscButton {
    font="RobotoCondensedBold"; colorText[]=ATAK_TEXT; colorDisabled[]=ATAK_TEXT_DIM;
    colorBackground[]=ATAK_BG2; colorBackgroundActive[]=ATAK_GREEN_DIM; colorBackgroundDisabled[]=ATAK_BG1;
    colorFocused[]=ATAK_BG2; colorBorder[]=ATAK_BORDER; colorShadow[]={0,0,0,0}; offsetPressedX=0; offsetPressedY=0;
    sizeEx="0.024 * safeZoneH";
};
class COMSPEC_RscButtonPrimary: COMSPEC_RscButton { colorText[]=ATAK_BG0; colorBackground[]=ATAK_GREEN; colorFocused[]=ATAK_GREEN; colorBackgroundActive[]={0.46,0.88,0.52,1}; };
// Bouton invisible posé au-dessus d'une tuile (fond + icône + libellé) : seul le survol l'éclaire.
class COMSPEC_RscButtonOverlay: COMSPEC_RscButton { colorBackground[]={0,0,0,0}; colorFocused[]={0,0,0,0}; colorBackgroundActive[]={0.36,0.78,0.42,0.18}; colorBorder[]={0,0,0,0}; text=""; };
class COMSPEC_RscEdit: RscEdit { font="RobotoCondensed"; colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG0; colorSelection[]=ATAK_GREEN_DIM; sizeEx="0.024 * safeZoneH"; };
class COMSPEC_RscCombo: RscCombo { font="RobotoCondensed"; colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG0; colorSelect[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; sizeEx="0.024 * safeZoneH"; };
class COMSPEC_RscListBox: RscListBox { font="RobotoCondensed"; colorText[]=ATAK_TEXT; colorSelect[]=ATAK_TEXT; colorSelect2[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; colorSelectBackground2[]=ATAK_GREEN_DIM; colorBackground[]={0,0,0,0}; sizeEx="0.024 * safeZoneH"; rowHeight="0.040 * safeZoneH"; };
class COMSPEC_RscCheckbox: RscCheckBox {};
class COMSPEC_RscProgress: RscProgress { colorBar[]=ATAK_GREEN; colorFrame[]=ATAK_BORDER; };
class COMSPEC_RscControlsGroup: RscControlsGroup {
    class VScrollbar: VScrollbar { color[]=ATAK_GREEN; width=0.012; autoScrollEnabled=0; };
    class HScrollbar: HScrollbar { color[]=ATAK_GREEN; height=0; };
};
class COMSPEC_RscPicture: RscPicture { colorText[]=ATAK_GREEN; colorBackground[]={0,0,0,0}; style=2096; };
class COMSPEC_RscMap: RscMapControl { colorBackground[]={0.045,0.055,0.048,1}; colorOutside[]={0.025,0.030,0.027,1}; showCountourInterval=1; };
class COMSPEC_RscPanel: COMSPEC_RscText { colorBackground[]=ATAK_BG1; };
class COMSPEC_RscTile: COMSPEC_RscText { colorBackground[]=ATAK_BG2; };
class COMSPEC_RscBadge: COMSPEC_RscTextCenter { font="RobotoCondensedBold"; colorBackground[]=ATAK_DANGER; colorText[]={1,1,1,1}; sizeEx="0.018 * safeZoneH"; };
class COMSPEC_RscLabel: COMSPEC_RscText { font="EtelkaMonospacePro"; colorText[]=ATAK_TEXT_DIM; sizeEx="0.017 * safeZoneH"; };
class COMSPEC_RscCard: COMSPEC_RscStructuredText { colorBackground[]=ATAK_BG2; };
class COMSPEC_RscDivider: COMSPEC_RscText { colorBackground[]=ATAK_BORDER; };
