#include "\a3\ui_f\hpp\defineCommonGrids.inc"

params ["_mode"] ;

private _EH = uiNamespace getVariable ["PLP_SMT_EH",-1] ;
private _map = findDisplay 12 displayCtrl 51 ;
_map ctrlRemoveEventHandler ["Draw",_EH] ;
ctrlDelete ((findDisplay 12) displayCtrl 73453) ;
ctrlDelete ((findDisplay 12) displayCtrl 73454) ;

//private _getConfig = 

call {
	if (_mode == "open") exitWith {
		if !(visibleMap) exitWith {} ;	// eliminate the task when map is not visible
		// opens radial menu
		"PLP_SMT_RadialMenuBase" cutRsc ["PLP_SMT_RadialMenuBase","plain"] ;
		private _disp = uiNamespace getVariable ["PLP_SMT_RadialMenuBase",displayNull] ;
		private _cGrp = _disp displayCtrl 1 ;

		#define SCALE 0.7

		// sets pos on the mouse's pos
		_cGrp ctrlSetPosition (getMousePosition vectorDiff [(GUI_GRID_W*15/2*SCALE),(GUI_GRID_H*15/2*SCALE)]) ;
		_cGrp ctrlCommit 0 ;

		// todo move to config
		private _items = "true" configClasses (configFile >> "PLP_SMT_Data" >> "RadialMenu") ;

		private _coef = 1-(count _items mod 2) ;

		// add items into the menu
		{
			private _ctrl = _disp ctrlCreate ["PLP_SMT_RscTextCenter",-1,_cGrp] ;
			_ctrl ctrlSetText getText (_x >> "displayName") ;

			private _dir = _forEachIndex / count _items * 360 + 180 ;

			_ctrl ctrlSetPosition [
				(sin _dir * (GUI_GRID_W*5*SCALE)) + (GUI_GRID_W*15/2*SCALE) - GUI_GRID_W*5*SCALE/2,
				(cos _dir * (GUI_GRID_H*5*SCALE)) + (GUI_GRID_H*15/2*SCALE) - GUI_GRID_H*2*SCALE/2
			] ;
			_ctrl ctrlCommit 0 ;

			private _isDisabled = call {
				private _cfg = missionConfigFile >> "PLP_SMT_RadialMenu" >> configName _x ;
				if (isNumber (_cfg)) exitWith {getNumber (_cfg) == 0} ;
				
				private _cfg = configFile >> "PLP_SMT_Data" >> "RadialMenu" >> configName _x >> "enabled" ;
				if (isNumber (_cfg)) exitWith {getNumber (_cfg) == 0} ;
				false
			} ;
			if (_isDisabled) then {
				_ctrl ctrlSetTextColor [0.6,0.6,0.6,1] ;
				_ctrl ctrlSetPosition [
					(sin _dir * (GUI_GRID_W*5*SCALE)) + (GUI_GRID_W*15/2*SCALE) - GUI_GRID_W*5*SCALE/2,
					(cos _dir * (GUI_GRID_H*5*SCALE)) + (GUI_GRID_H*15/2*SCALE) - GUI_GRID_H*2.5*SCALE/2
				] ;
				_ctrl ctrlCommit 0 ;

				private _ctrl = _disp ctrlCreate ["PLP_SMT_RscTextCenter",-1,_cGrp] ;
				_ctrl ctrlSetText "(Disabled)" ;
				_ctrl ctrlSetTextColor [0.6,0.6,0.6,1] ;
				_ctrl ctrlSetPosition [
					(sin _dir * (GUI_GRID_W*5*SCALE)) + (GUI_GRID_W*15/2*SCALE) - GUI_GRID_W*5*SCALE/2,
					(cos _dir * (GUI_GRID_H*5*SCALE)) + (GUI_GRID_H*15/2*SCALE) - GUI_GRID_H*1*SCALE/2
				] ;
				_ctrl ctrlSetFontHeight (GUI_GRID_H*0.8*SCALE) ;
				_ctrl ctrlCommit 0 ;
			} ;

			private _dir = (_forEachIndex + ((count _items+_coef) mod 2)/2) / count _items * 360 ;
			private _ctrl = _disp ctrlCreate ["PLP_SMT_RadialSeparator",-1,_cGrp] ;
			_ctrl ctrlSetAngle [_dir,0.5,0.5,true] ;
		} forEach _items ;

		// mouse movement detection
		private _MEH = addMissionEventHandler ["EachFrame",{
			private _disp = uiNamespace getVariable ["PLP_SMT_RadialMenuBase",displayNull] ;
			if (isNull _disp) exitWith {removeMissionEventHandler ["EachFrame",_thisEventHandler]} ;
			private _cGrp = _disp displayCtrl 1 ;

			private _items = "true" configClasses (configFile >> "PLP_SMT_Data" >> "RadialMenu") ;

			private _coef = 1-(count _items mod 2) ;

			getResolution params ["_resW","_resH"] ;

			// convert safezone into abs
			private _mPos = ctrlMousePosition _cGrp ;
			private _center = [GUI_GRID_W*15/2*SCALE,GUI_GRID_H*15/2*SCALE] ;

			private _mPosSafeZone = [_mPos#0/3,_mPos#1/4] ;
			private _centerSafeZone = [_center#0/3,_center#1/4] ;

			private _dist = _centerSafeZone distance2D [0,_centerSafeZone#1] ;

			_cGrp controlsGroupCtrl 10 ctrlShow (
				_mPosSafeZone distance2D _centerSafeZone < (_dist*0.25) or
				_mPosSafeZone distance2D _centerSafeZone > _dist
			) ;

			private _relDir = _mPosSafeZone getDir _centerSafeZone ;
			private _selected = floor ((_relDir/360*count _items) + ((count _items+_coef) mod 2)/2) mod (count _items) ;

			private _isDisabled = call {
				private _selected = _items#_selected ;
				private _cfg = missionConfigFile >> "PLP_SMT_RadialMenu" >> configName _selected ;
				if (isNumber (_cfg)) exitWith {getNumber (_cfg) == 0} ;
				
				private _cfg = configFile >> "PLP_SMT_Data" >> "RadialMenu" >> configName _selected >> "enabled" ;
				if (isNumber (_cfg)) exitWith {getNumber (_cfg) == 0} ;
				false
			} ;

			if (!_isDisabled) then {
				{
					private _ctrl = _cGrp controlsGroupCtrl _x ;
					_ctrl ctrlShow (
						_mPosSafeZone distance2D _centerSafeZone > (_dist*0.25) and
						_mPosSafeZone distance2D _centerSafeZone < _dist
					) ;
					
					private _dir = (_forEachIndex - _selected -1 + ((count _items+_coef) mod 2)/2) / count _items * 360 ;
					_ctrl ctrlSetAngle [_dir,0.5,0.5,true] ;
				} forEach [11,12] ;
			} ;
		}] ;
	} ;
	if (_mode == "close") exitWith {
		private _disp = uiNamespace getVariable ["PLP_SMT_RadialMenuBase",displayNull] ;
		private _cGrp = _disp displayCtrl 1 ;

		private _items = "true" configClasses (configFile >> "PLP_SMT_Data" >> "RadialMenu") ;
		
		getResolution params ["_resW","_resH"] ;
		private _coef = 1-(count _items mod 2) ;

		private _mPos = ctrlMousePosition _cGrp ;
		private _center = [GUI_GRID_W*15/2*SCALE,GUI_GRID_H*15/2*SCALE] ;

		private _mPosSafeZone = [_mPos#0/3,_mPos#1/4] ;
		private _centerSafeZone = [_center#0/3,_center#1/4] ;

		private _dist = _centerSafeZone distance2D [0,_centerSafeZone#1] ;
		private _relDir = _mPosSafeZone getDir _centerSafeZone ;

		private _selected = floor ((_relDir/360*count _items) + ((count _items+_coef) mod 2)/2) mod (count _items) ;

		private _isDisabled = call {
			private _selected = _items#_selected ;
			private _cfg = missionConfigFile >> "PLP_SMT_RadialMenu" >> configName _selected ;
			if (isNumber (_cfg)) exitWith {getNumber (_cfg) == 0} ;
			
			private _cfg = configFile >> "PLP_SMT_Data" >> "RadialMenu" >> configName _selected >> "enabled" ;
			if (isNumber (_cfg)) exitWith {getNumber (_cfg) == 0} ;
			false
		} ;

		if (_isDisabled) exitWith {_disp closeDisplay 0} ;
		if (
			_mPosSafeZone distance2D _centerSafeZone < (_dist*0.25) or
			_mPosSafeZone distance2D _centerSafeZone > _dist
		) then {
			// do nuthin
		} else {
			call (missionNamespace getVariable getText ((_items#_selected) >> "function")) ;
			//call compile preprocessFileLineNumbers getText ((_items#_selected) >> "function") ;
			(_items#_selected) call PLP_fnc_SMT_Description ;
		} ;

		_disp closeDisplay 0 ;
	} ;
} ;