#define PREFIX Iceman
#define COMPONENT ATAK_TOC_Screens
#define ADDON Iceman_ATAK_TOC_Screens

#define QUOTE(var1) #var1
#define DOUBLES(var1,var2) var1##_##var2
#define PATHTOF_SYS(var1) \ATAK_TOC_Screens\var1
#define PATHTOF(var1) PATHTOF_SYS(var1)
#define QPATHTOF(var1) QUOTE(PATHTOF(var1))
#define FUNC(var1) Iceman_fnc_##var1
#define QFUNC(var1) QUOTE(FUNC(var1))
