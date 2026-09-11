@echo off
setlocal
set "ROOT=%~dp0"
set "ADDON=%ROOT%Sources\addons\main"
set "OUT=%ROOT%@COMSPEC_ATAK_Native"
set "BUILDER=%ARMA3TOOLS%\AddonBuilder\AddonBuilder.exe"
if not exist "%BUILDER%" set "BUILDER=C:\Program Files (x86)\Steam\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
if not exist "%BUILDER%" (echo [ERROR] AddonBuilder introuvable & exit /b 1)
if not exist "%OUT%\addons" mkdir "%OUT%\addons"
dotnet publish "%ROOT%COMSPECATAKNativeExtension\COMSPECATAKNativeExtension.csproj" -c Release -r win-x64 --self-contained true
if errorlevel 1 exit /b 1
copy /Y "%ROOT%COMSPECATAKNativeExtension\bin\publish\COMSPECATAKNativeExtension_x64.dll" "%OUT%\COMSPECATAKNativeExtension_x64.dll" >nul
"%BUILDER%" "%ADDON%" "%OUT%\addons" -packonly -prefix=z\comspec_atak_native\addons\main
if errorlevel 1 exit /b 1
echo [OK] Mod autonome: %OUT%
endlocal
