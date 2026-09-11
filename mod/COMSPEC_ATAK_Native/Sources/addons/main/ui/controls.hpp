#include "colors.hpp"
class COMSPEC_RscText: RscText { font="RobotoCondensed"; colorText[]=ATAK_TEXT; colorBackground[]={0,0,0,0}; sizeEx="0.032 * safeZoneH"; };
class COMSPEC_RscStructuredText: RscStructuredText { colorText[]=ATAK_TEXT; colorBackground[]={0,0,0,0}; size="0.030 * safeZoneH"; };
class COMSPEC_RscButton: RscButton { font="RobotoCondensedBold"; colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG2; colorBackgroundActive[]=ATAK_GREEN_DIM; colorFocused[]=ATAK_GREEN_DIM; colorBorder[]=ATAK_BORDER; sizeEx="0.027 * safeZoneH"; };
class COMSPEC_RscButtonIcon: COMSPEC_RscButton { style=2; };
class COMSPEC_RscEdit: RscEdit { colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG2; colorSelection[]=ATAK_GREEN_DIM; };
class COMSPEC_RscCombo: RscCombo { colorText[]=ATAK_TEXT; colorBackground[]=ATAK_BG2; };
class COMSPEC_RscListBox: RscListBox { colorText[]=ATAK_TEXT; colorSelect[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; colorBackground[]=ATAK_BG1; rowHeight="0.035 * safeZoneH"; };
class COMSPEC_RscTree: RscTree { colorText[]=ATAK_TEXT; colorSelect[]=ATAK_TEXT; colorSelectBackground[]=ATAK_GREEN_DIM; colorBackground[]=ATAK_BG1; };
class COMSPEC_RscCheckbox: RscCheckBox {};
class COMSPEC_RscProgress: RscProgress { colorBar[]=ATAK_GREEN; colorFrame[]=ATAK_BORDER; };
class COMSPEC_RscControlsGroup: RscControlsGroup { class VScrollbar { color[]=ATAK_GREEN; width=0.021; autoScrollEnabled=0; }; class HScrollbar { color[]=ATAK_GREEN; height=0.028; }; };
class COMSPEC_RscPicture: RscPicture { colorText[]={1,1,1,1}; };
class COMSPEC_RscMap: RscMapControl { colorBackground[]={0.045,0.055,0.048,1}; colorOutside[]={0.025,0.030,0.027,1}; showCountourInterval=1; };
class COMSPEC_RscPanel: COMSPEC_RscText { colorBackground[]=ATAK_BG1; };
class COMSPEC_RscCard: COMSPEC_RscStructuredText { colorBackground[]=ATAK_BG2; };
class COMSPEC_RscDivider: COMSPEC_RscText { colorBackground[]=ATAK_BORDER; };
