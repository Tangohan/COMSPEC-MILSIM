@echo off
title COMSPEC Overwatch - Build
set "MOD_NAME=@COMSPECOverwatch"
set "ARMA_PATH=D:\SteamLibrary\steamapps\common\Arma 3"
set "BUILDER_PATH=D:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
set "PROJECT_DIR=%~dp0"
set "OUTPUT_DIR=%PROJECT_DIR%%MOD_NAME%"
set "BUILD_LOG=%PROJECT_DIR%build_log.txt"
set "TMP_OUT=%PROJECT_DIR%build_tmp_out.txt"

:: --- CONFIGURATION .NET ---
set "CS_PROJ_PATH=%PROJECT_DIR%COMSPECExtension\COMSPECExtension.csproj"
set "DOTNET_BUILD_DIR=%PROJECT_DIR%COMSPECExtension\bin\Release\net8.0\win-x64\publish"

:: Demarrer le log (ecrase a chaque run)
echo ========== COMSPEC Overwatch Build ========== > "%BUILD_LOG%"
echo Date: %date% %time% >> "%BUILD_LOG%"
echo Repertoire: %PROJECT_DIR% >> "%BUILD_LOG%"
echo. >> "%BUILD_LOG%"

echo [ATHENA] Initialisation de la sequence de build...
echo [ATHENA] Initialisation de la sequence de build... >> "%BUILD_LOG%"
echo Log enregistre dans: %BUILD_LOG%
echo.

:: 1. Compilation de l'extension C# (NativeAOT)
echo [DOTNET] Compilation de COMSPECExtension (Release x64)...
echo [DOTNET] Compilation de COMSPECExtension (Release x64)... >> "%BUILD_LOG%"
dotnet publish "%CS_PROJ_PATH%" -c Release -r win-x64 /p:NativeLib=Shared /p:SelfContained=true --nologo > "%TMP_OUT%" 2>&1
type "%TMP_OUT%" >> "%BUILD_LOG%"
type "%TMP_OUT%"
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] La compilation C# a echoue. Verifie ton code .NET. >> "%BUILD_LOG%"
    echo [ERROR] La compilation C# a echoue. Verifie ton code .NET.
    goto :build_fail
)

:: 2. Sources addon
if not exist "%OUTPUT_DIR%\addons" mkdir "%OUTPUT_DIR%\addons"

:: 3. Compilation des PBO
echo [BUILD] Compilation de comspec_overwatch_main... >> "%BUILD_LOG%"
echo [BUILD] Compilation de comspec_overwatch_main..."
if not exist "%PROJECT_DIR%%MOD_NAME%\addons\main" (
    echo [ERREUR] Sources manquantes: %PROJECT_DIR%%MOD_NAME%\addons\main >> "%BUILD_LOG%"
    echo [ERREUR] Sources manquantes: %PROJECT_DIR%%MOD_NAME%\addons\main
    goto :build_fail
)
"%BUILDER_PATH%" "%PROJECT_DIR%%MOD_NAME%\addons\main" "%OUTPUT_DIR%\addons" -packonly -prefix=z\comspec_overwatch\addons\main >> "%BUILD_LOG%" 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERREUR] AddonBuilder main a echoue. >> "%BUILD_LOG%"
    echo [ERREUR] AddonBuilder main a echoue.
    goto :build_fail
)

echo [BUILD] Compilation de comspec_overwatch_connect... >> "%BUILD_LOG%"
echo [BUILD] Compilation de comspec_overwatch_connect..."
if not exist "%PROJECT_DIR%%MOD_NAME%\addons\connect" (
    echo [ERREUR] Sources manquantes: %PROJECT_DIR%%MOD_NAME%\addons\connect >> "%BUILD_LOG%"
    echo [ERREUR] Sources manquantes: %PROJECT_DIR%%MOD_NAME%\addons\connect
    goto :build_fail
)
"%BUILDER_PATH%" "%PROJECT_DIR%%MOD_NAME%\addons\connect" "%OUTPUT_DIR%\addons" -packonly -prefix=z\comspec_overwatch\addons\connect >> "%BUILD_LOG%" 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERREUR] AddonBuilder connect a echoue. >> "%BUILD_LOG%"
    echo [ERREUR] AddonBuilder connect a echoue.
    goto :build_fail
)

:: 4. DLL a la racine du mod
echo [DEPLOY] Transfert de la DLL COMSPECExtension_x64... >> "%BUILD_LOG%"
echo [DEPLOY] Transfert de la DLL COMSPECExtension_x64..."
if exist "%OUTPUT_DIR%\net8.0\win-x64\COMSPECExtension_x64.dll" (
    copy /Y "%OUTPUT_DIR%\net8.0\win-x64\COMSPECExtension_x64.dll" "%OUTPUT_DIR%\COMSPECExtension_x64.dll" >> "%BUILD_LOG%" 2>&1
) else if exist "%DOTNET_BUILD_DIR%\COMSPECExtension_x64.dll" (
    copy /Y "%DOTNET_BUILD_DIR%\COMSPECExtension_x64.dll" "%OUTPUT_DIR%\COMSPECExtension_x64.dll" >> "%BUILD_LOG%" 2>&1
) else (
    echo [WARN] DLL non trouvee. Verifiez la compilation .NET. >> "%BUILD_LOG%"
    echo [WARN] DLL non trouvee. Verifiez la compilation .NET.
)
copy /Y "%PROJECT_DIR%mod.cpp" "%OUTPUT_DIR%\" >> "%BUILD_LOG%" 2>&1

:: 5. Deploiement vers Arma 3
echo [DEPLOY] Synchronisation avec le dossier Arma 3... >> "%BUILD_LOG%"
echo [DEPLOY] Synchronisation avec le dossier Arma 3...
if exist "%ARMA_PATH%" (
    xcopy "%OUTPUT_DIR%" "%ARMA_PATH%\%MOD_NAME%" /E /I /Y >> "%BUILD_LOG%" 2>&1
    if %ERRORLEVEL% NEQ 0 (echo [WARN] xcopy vers Arma 3 a peut-etre echoue. >> "%BUILD_LOG%")
) else (
    echo [WARN] Dossier Arma 3 non trouve: %ARMA_PATH% >> "%BUILD_LOG%"
    echo [WARN] Dossier Arma 3 non trouve: %ARMA_PATH%
    echo         Mod pret dans: %OUTPUT_DIR% >> "%BUILD_LOG%"
)

echo. >> "%BUILD_LOG%"
echo ========== BUILD REUSSI ========== >> "%BUILD_LOG%"
echo Sortie: %OUTPUT_DIR% >> "%BUILD_LOG%"

echo.
echo ===========================================================
echo   BUILD TERMINE - RESULTAT
echo ===========================================================
echo   Sortie mod : %OUTPUT_DIR%
echo   PBO        : %OUTPUT_DIR%\addons\*.pbo
echo   DLL        : %OUTPUT_DIR%\COMSPECExtension_x64.dll
echo.
echo   Log complet : %BUILD_LOG%
echo ===========================================================
pause
del "%TMP_OUT%" 2>nul
exit /b 0

:build_fail
echo. >> "%BUILD_LOG%"
echo ========== BUILD EN ECHEC ========== >> "%BUILD_LOG%"
echo.
echo ===========================================================
echo   BUILD EN ECHEC - Verifiez les messages ci-dessus
echo   Log complet : %BUILD_LOG%
echo ===========================================================
pause
del "%TMP_OUT%" 2>nul
exit /b 1
