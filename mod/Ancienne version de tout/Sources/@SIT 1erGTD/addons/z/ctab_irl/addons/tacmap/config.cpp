class CfgPatches
{
	class ctab_irl_tacmap
	{
		name="tacmap";
		units[]={};
		weapons[]={};
		requiredVersion=1.88;
		requiredAddons[]=
		{
			"ctab_irl_main"
		};
		author="AUTHOR";
		version="0.3.0.0";
		versionStr="0.3.0.0";
		versionAr[]={0,3,0,0};
	};
};
class Extended_PreStart_EventHandlers
{
	class ctab_irl_tacmap
	{
		init="call compile preprocessFileLineNumbers '\z\ctab_irl\addons\tacmap\XEH_preStart.sqf'";
	};
};
class Extended_PreInit_EventHandlers
{
	class ctab_irl_tacmap
	{
		init="call compile preprocessFileLineNumbers '\z\ctab_irl\addons\tacmap\XEH_preInit.sqf'";
	};
};
class Extended_PostInit_EventHandlers
{
	class ctab_irl_tacmap
	{
		init="call compile preprocessFileLineNumbers '\z\ctab_irl\addons\tacmap\XEH_postInit.sqf'";
	};
};
