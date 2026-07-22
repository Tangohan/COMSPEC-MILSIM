class CfgPatches
{
	class ctab_irl_connect
	{
		name="connect";
		units[]={};
		weapons[]={};
		requiredVersion=1.88;
		requiredAddons[]=
		{
			"ctab_irl_main",
			"ctab_irl_tacmap",
			"ctab_core"
		};
		author="AUTHOR";
		version="0.3.0.0";
		versionStr="0.3.0.0";
		versionAr[]={0,3,0,0};
	};
};
class Extended_PreStart_EventHandlers
{
	class ctab_irl_connect
	{
		init="call compile preprocessFileLineNumbers '\z\ctab_irl\addons\connect\XEH_preStart.sqf'";
	};
};
class Extended_PreInit_EventHandlers
{
	class ctab_irl_connect
	{
		init="call compile preprocessFileLineNumbers '\z\ctab_irl\addons\connect\XEH_preInit.sqf'";
	};
};
class Extended_PostInit_EventHandlers
{
	class ctab_irl_connect
	{
		init="call compile preprocessFileLineNumbers '\z\ctab_irl\addons\connect\XEH_postInit.sqf'";
	};
};
class CfgFontFamilies
{
	class QRFONT
	{
		fonts[]=
		{
			"z\ctab_irl\addons\connect\data\qrfont6",
			"z\ctab_irl\addons\connect\data\qrfont7",
			"z\ctab_irl\addons\connect\data\qrfont8",
			"z\ctab_irl\addons\connect\data\qrfont9",
			"z\ctab_irl\addons\connect\data\qrfont10",
			"z\ctab_irl\addons\connect\data\qrfont11",
			"z\ctab_irl\addons\connect\data\qrfont12",
			"z\ctab_irl\addons\connect\data\qrfont13",
			"z\ctab_irl\addons\connect\data\qrfont14",
			"z\ctab_irl\addons\connect\data\qrfont15",
			"z\ctab_irl\addons\connect\data\qrfont16",
			"z\ctab_irl\addons\connect\data\qrfont17",
			"z\ctab_irl\addons\connect\data\qrfont18",
			"z\ctab_irl\addons\connect\data\qrfont19",
			"z\ctab_irl\addons\connect\data\qrfont20",
			"z\ctab_irl\addons\connect\data\qrfont21",
			"z\ctab_irl\addons\connect\data\qrfont22",
			"z\ctab_irl\addons\connect\data\qrfont23",
			"z\ctab_irl\addons\connect\data\qrfont24",
			"z\ctab_irl\addons\connect\data\qrfont25",
			"z\ctab_irl\addons\connect\data\qrfont26",
			"z\ctab_irl\addons\connect\data\qrfont27",
			"z\ctab_irl\addons\connect\data\qrfont28",
			"z\ctab_irl\addons\connect\data\qrfont29"
		};
	};
};
