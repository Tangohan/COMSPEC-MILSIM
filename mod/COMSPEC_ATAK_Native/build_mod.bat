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
:: Overwatch connect est embarque : connexion Athena, synchro, FRS, Reco... sans charger @COMSPECOverwatch.
set "OW=%ROOT%..\UptoDate"
if "%ProgramFiles(x86)%"=="" set "ProgramFiles(x86)=C:\Program Files (x86)"
dotnet publish "%OW%\COMSPECExtension\COMSPECExtension.csproj" -c Release -r win-x64 /p:NativeLib=Shared /p:SelfContained=true /p:IlcUseEnvironmentalTools=true --nologo
if errorlevel 1 exit /b 1
set "OWDLL=%OW%\COMSPECExtension\bin\publish\COMSPECExtension_x64.dll"
if not exist "%OWDLL%" set "OWDLL=%OW%\COMSPECExtension\bin\Release\net8.0\win-x64\publish\COMSPECExtension_x64.dll"
copy /Y "%OWDLL%" "%OUT%\COMSPECExtension_x64.dll" >nul
set "OWTMP=%TEMP%\comspec_ow_pack"
if exist "%OWTMP%" rmdir /s /q "%OWTMP%"
mkdir "%OWTMP%"
"%BUILDER%" "%OW%\Sources\comspec-overwatch-addons\main" "%OWTMP%" -packonly -prefix=z\comspec_overwatch\addons\main
if errorlevel 1 exit /b 1
"%BUILDER%" "%OW%\Sources\comspec-overwatch-addons\connect" "%OWTMP%" -packonly -prefix=z\comspec_overwatch\addons\connect
if errorlevel 1 exit /b 1
copy /Y "%OWTMP%\main.pbo" "%OUT%\addons\comspec_overwatch_main.pbo" >nul
copy /Y "%OWTMP%\connect.pbo" "%OUT%\addons\comspec_overwatch_connect.pbo" >nul
echo [OK] Mod autonome: %OUT%
endlocal
