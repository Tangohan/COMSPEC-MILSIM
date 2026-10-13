@echo off
setlocal
:: COMSPEC Base SOF : mod indépendant (aucune dépendance à l'ATAK natif). Emballage seul, comme les pylônes relais.
set "ROOT=%~dp0"
set "OUT=%ROOT%@COMSPEC_SOF_Base"
set "BUILDER=%ARMA3TOOLS%\AddonBuilder\AddonBuilder.exe"
if not exist "%BUILDER%" set "BUILDER=C:\Program Files (x86)\Steam\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
if not exist "%BUILDER%" (echo [ERROR] AddonBuilder introuvable & exit /b 1)
if not exist "%OUT%\addons" mkdir "%OUT%\addons"
"%BUILDER%" "%ROOT%Sources\addons\main" "%OUT%\addons" -packonly -prefix=z\comspec_sof\addons\main
if errorlevel 1 exit /b 1
echo [OK] Mod : %OUT%
endlocal
