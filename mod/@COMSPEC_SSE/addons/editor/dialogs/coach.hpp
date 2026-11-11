// Visite guidée (fn_editorCoach) : une bulle avec une pointe, posée À CÔTÉ de ce qu'elle explique, et un cadre vert
// autour de la cible. Pas de voile sombre, rien ne cache la cible. Inclus en dernier dans controls (dessiné au-dessus).
// Positions calculées à l'affichage ; hors écran tant que la visite n'est pas ouverte.

#define CO_BG {0.098,0.149,0.122,1}

class CoOutT: COMSPEC_SSE_AzAccent { idc = 95900; ED_POS(-30,-30,1,0.1); };
class CoOutB: CoOutT { idc = 95901; };
class CoOutL: CoOutT { idc = 95902; };
class CoOutR: CoOutT { idc = 95903; };
// Pointe : un carré tourné de 45°, à moitié sous la bulle.
class CoArrow: COMSPEC_SSE_EdPicture { idc = 95904; style = 48; text = "#(rgb,8,8,3)color(0.098,0.149,0.122,1)"; ED_POS(-30,-30,0.9,0.9); };
class CoBg: COMSPEC_SSE_EdText { idc = 95905; colorBackground[] = CO_BG; ED_POS(-30,-30,13.9,6); };
class CoAccent: COMSPEC_SSE_AzAccent { idc = 95906; ED_POS(-30,-30,13.9,0.12); };
class CoText: RscStructuredText { idc = 95907; colorBackground[] = {0, 0, 0, 0}; size = SSE_Q(SSE_GH * AZ_TXT_BODY); ED_POS(-30,-30,13.3,4); };
class CoNext: COMSPEC_SSE_AzPrimary { idc = 95908; text = "$STR_COMSPEC_SSE_AZ_CoNext"; ED_POS(-30,-30,3.7,1.1); action = "['next'] call comspec_sse_fnc_editorCoach"; };
class CoSkip: COMSPEC_SSE_AzButton { idc = 95909; text = "$STR_COMSPEC_SSE_AZ_CoSkip"; tooltip = "$STR_COMSPEC_SSE_AZ_CoSkip_D"; ED_POS(-30,-30,2.9,1.1); action = "['skip'] call comspec_sse_fnc_editorCoach"; };
class CoNever: COMSPEC_SSE_AzButton { idc = 95910; text = "$STR_COMSPEC_SSE_AZ_CoNever"; tooltip = "$STR_COMSPEC_SSE_AZ_CoNever_D"; ED_POS(-30,-30,6.4,1.1); action = "['never'] call comspec_sse_fnc_editorCoach"; };
