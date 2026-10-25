@echo off
setlocal
set "ROOT=%~dp0"
set "ADDON=%ROOT%Sources\addons\main"
set "OUT=%ROOT%@COMSPEC_ATAK_Native"
set "BUILDER=%ARMA3TOOLS%\AddonBuilder\AddonBuilder.exe"
if not exist "%BUILDER%" set "BUILDER=C:\Program Files (x86)\Steam\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
:: Arma 3 Tools installe dans une autre bibliotheque Steam (X:\SteamLibrary).
if not exist "%BUILDER%" for %%d in (C D E F G H) do if exist "%%d:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe" set "BUILDER=%%d:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
if not exist "%BUILDER%" (echo [ERROR] AddonBuilder introuvable : definir ARMA3TOOLS & exit /b 1)
:: AddonBuilder copie chaque addon dans %TEMP%\<nom du dossier> sans jamais le vider : les deux "main" (ATAK natif et
:: Overwatch) partageaient %TEMP%\main et accumulaient les fichiers de tous les anciens builds (Overwatch main = copie
:: de l'ATAK, modeles retires encore dans main.pbo). Chaque addon a donc son propre dossier temporaire, vide a chaque build.
set "ABTMP=%TEMP%\comspec_atak_native_ab"
if exist "%ABTMP%" rmdir /s /q "%ABTMP%"
:: NativeAOT (DLL Link) : link.exe et vswhere.exe doivent etre trouvables, sinon "link" sort en 9009.
set "VSWHERE=%ProgramFiles(x86)%\Microsoft Visual Studio\Installer"
if exist "%VSWHERE%\vswhere.exe" set "PATH=%VSWHERE%;%PATH%"
where link.exe >nul 2>nul
if errorlevel 1 if exist "%VSWHERE%\vswhere.exe" for /f "usebackq delims=" %%i in (`"%VSWHERE%\vswhere.exe" -latest -products * -property installationPath`) do if exist "%%i\VC\Auxiliary\Build\vcvars64.bat" call "%%i\VC\Auxiliary\Build\vcvars64.bat" >nul
if not exist "%OUT%\addons" mkdir "%OUT%\addons"
:: Sortie HEMTT copiee sans la renommer : deux PBO du meme addon, Arma lit souvent l'ancien (plus de journal ni de connexion).
if exist "%OUT%\addons\comspec_atak_native_main.pbo" del /q "%OUT%\addons\comspec_atak_native_main.pbo"
if exist "%OUT%\addons\comspec_atak_native_towers.pbo" del /q "%OUT%\addons\comspec_atak_native_towers.pbo"
if exist "%OUT%\addons\comspec_atak_native_items.pbo" del /q "%OUT%\addons\comspec_atak_native_items.pbo"
dotnet publish "%ROOT%COMSPECATAKNativeExtension\COMSPECATAKNativeExtension.csproj" -c Release -r win-x64 --self-contained true
if errorlevel 1 exit /b 1
copy /Y "%ROOT%COMSPECATAKNativeExtension\bin\publish\COMSPECATAKNativeExtension_x64.dll" "%OUT%\COMSPECATAKNativeExtension_x64.dll" >nul || (echo [ERREUR] COMSPECATAKNativeExtension_x64.dll non copiee : DLL verrouillee, fermer Arma & exit /b 1)
:: Empreinte du build (docs\VERSIONING.md) : date UTC et commit dans build_info.hpp le temps d'empaqueter main.pbo.
:: Le jeu affiche alors "1.94.0 - 2026.10.14 a1b2c3d" (Reglages, Debug, journal). Le fichier est remis a vide ensuite.
set "BCOMMIT=local"
for /f %%i in ('git -C "%ROOT%." rev-parse --short HEAD 2^>nul') do set "BCOMMIT=%%i"
set "BDATE="
for /f %%i in ('powershell -NoProfile -Command "Get-Date ([DateTime]::UtcNow) -Format yyyy.MM.dd"') do set "BDATE=%%i"
if not defined BDATE set "BDATE=local"
set "STAMPFILE=%ADDON%\build_info.hpp"
copy /Y "%STAMPFILE%" "%TEMP%\comspec_build_info.hpp" >nul
> "%STAMPFILE%" echo #define BUILD_STAMP "%BDATE% %BCOMMIT%"
echo [INFO] Build ATAK natif : %BDATE% %BCOMMIT%
"%BUILDER%" "%ADDON%" "%OUT%\addons" -packonly -clear -temp="%ABTMP%\native_main" -prefix=z\comspec_atak_native\addons\main
set "BERR=%errorlevel%"
copy /Y "%TEMP%\comspec_build_info.hpp" "%STAMPFILE%" >nul
if not "%BERR%"=="0" exit /b 1
:: Pylones relais 3D (modeles MLOD volumineux) : PBO a part.
"%BUILDER%" "%ROOT%Sources\addons\towers" "%OUT%\addons" -packonly -clear -temp="%ABTMP%\native_towers" -prefix=z\comspec_atak_native\addons\towers
if errorlevel 1 exit /b 1
:: Objets d'inventaire 3D (modeles MLOD, atlas) : PBO a part.
"%BUILDER%" "%ROOT%Sources\addons\items" "%OUT%\addons" -packonly -clear -temp="%ABTMP%\native_items" -prefix=z\comspec_atak_native\addons\items
if errorlevel 1 exit /b 1
:: Overwatch connect est embarque : connexion Athena, synchro, FRS, Reco... sans charger @COMSPECOverwatch.
set "OW=%ROOT%..\UptoDate"
if "%ProgramFiles(x86)%"=="" set "ProgramFiles(x86)=C:\Program Files (x86)"
dotnet publish "%OW%\COMSPECExtension\COMSPECExtension.csproj" -c Release -r win-x64 /p:NativeLib=Shared /p:SelfContained=true /p:IlcUseEnvironmentalTools=true --nologo
if errorlevel 1 exit /b 1
set "OWDLL=%OW%\COMSPECExtension\bin\publish\COMSPECExtension_x64.dll"
if not exist "%OWDLL%" set "OWDLL=%OW%\COMSPECExtension\bin\Release\net8.0\win-x64\publish\COMSPECExtension_x64.dll"
if not exist "%OWDLL%" (echo [ERREUR] COMSPECExtension_x64.dll introuvable apres dotnet publish & exit /b 1)
copy /Y "%OWDLL%" "%OUT%\COMSPECExtension_x64.dll" >nul || (echo [ERREUR] COMSPECExtension_x64.dll non copiee : DLL verrouillee, fermer Arma & exit /b 1)
set "OWTMP=%TEMP%\comspec_ow_pack"
if exist "%OWTMP%" rmdir /s /q "%OWTMP%"
mkdir "%OWTMP%"
"%BUILDER%" "%OW%\Sources\comspec-overwatch-addons\main" "%OWTMP%" -packonly -clear -temp="%ABTMP%\ow_main" -prefix=z\comspec_overwatch\addons\main
if errorlevel 1 exit /b 1
"%BUILDER%" "%OW%\Sources\comspec-overwatch-addons\connect" "%OWTMP%" -packonly -clear -temp="%ABTMP%\ow_connect" -prefix=z\comspec_overwatch\addons\connect
if errorlevel 1 exit /b 1
copy /Y "%OWTMP%\main.pbo" "%OUT%\addons\comspec_overwatch_main.pbo" >nul
copy /Y "%OWTMP%\connect.pbo" "%OUT%\addons\comspec_overwatch_connect.pbo" >nul
:: Controle final (tools\verify_build.py) : tout PBO hors des cinq attendus est deplace dans %TEMP%\comspec_atak_retires,
:: puis taille, SHA-256 et prefixe de chaque PBO ; echec si doublon, PBO manquant ou fichier absent des sources (build perime).
set "PY="
py -3 -c "import sys" >nul 2>nul && set "PY=py -3"
if not defined PY python -c "import sys; sys.exit(sys.version_info[0] < 3)" >nul 2>nul && set "PY=python"
if not defined PY echo [ATTENTION] Python 3 introuvable : controle des PBO saute.
if defined PY %PY% "%ROOT%tools\verify_build.py" "%OUT%\addons" --retire "%TEMP%\comspec_atak_retires" || (echo [ERREUR] PBO non conformes : ne pas publier. & exit /b 1)
echo [OK] Mod autonome: %OUT%
endlocal
