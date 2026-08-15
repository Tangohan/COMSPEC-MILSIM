private _map = findDisplay 12 displayCtrl 51 ;

addMissionEventHandler ["Map", {
	params ["_mapOpened"] ;
	if (!_mapOpened) then {
		"close" call PLP_fnc_SMT_radialMenu ;
	} ;
}];

/*private _EH = _map ctrlAddEventHandler ["Draw",{
	params ["_map"] ;
	#define MAPPOS(xx,yy)	(_map ctrlMapScreenToWorld [xx,yy])

	MAPPOS(pixelW,pixelH) vectorDiff MAPPOS(0,0) params ["_pixelW","_pixelH"] ;

	private _gridW = _pixelW * 150 ;
	private _gridH = _pixelH * 150 ;

	private _mousePos = localNamespace getVariable ["PLP_SMT_mousePos",[-99,-99]] ;

	if ((_mousePos distance2D [-99,-99]) != 0) then {
		_map drawIcon [
			getMissionPath "data\radialBase_ca.paa",
			[1,1,1,1],MAPPOS(_mousePos select 0,_mousePos select 1),
			300,300,0
		] ;
		_map drawIcon [
			"#(argb,1,1,1)color(0,0,0,0)",
			[1,1,1,1],MAPPOS(_mousePos select 0,_mousePos select 1),
			0,0,0,"CANCEL",2,_gridH
		] ;
	} ;
}] ;*/