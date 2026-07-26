@echo off
title COMSPEC Overwatch - Build
set "MOD_NAME=@COMSPECOverwatch"
set "ARMA_PATH=F:\SteamLibrary\steamapps\common\Arma 3"
set "BUILDER_PATH=F:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
set "PROJECT_DIR=%~dp0"
set "OUTPUT_DIR=%PROJECT_DIR%%MOD_NAME%"
set "SOURCES_DIR=%PROJECT_DIR%Sources\comspec-overwatch-addons"
set "BUILD_LOG=%PROJECT_DIR%build_log.txt"
set "TMP_OUT=%PROJECT_DIR%build_tmp_out.txt"

:: --- CONFIGURATION .NET ---
set "CS_PROJ_PATH=%PROJECT_DIR%COMSPECExtension\COMSPECExtension.csproj"
set "DOTNET_PUBLISH_DIR=%PROJECT_DIR%COMSPECExtension\bin\publish"
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
echo [BUILD] Compilation de comspec_overwatch_main...
if not exist "%SOURCES_DIR%\main" (
    echo [ERREUR] Sources manquantes: %SOURCES_DIR%\main >> "%BUILD_LOG%"
    echo [ERREUR] Sources manquantes: %SOURCES_DIR%\main
    goto :build_fail
)
"%BUILDER_PATH%" "%SOURCES_DIR%\main" "%OUTPUT_DIR%\addons" -packonly -prefix=z\comspec_overwatch\addons\main >> "%BUILD_LOG%" 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERREUR] AddonBuilder main a echoue. >> "%BUILD_LOG%"
    echo [ERREUR] AddonBuilder main a echoue.
    goto :build_fail
)

echo [BUILD] Compilation de comspec_overwatch_connect... >> "%BUILD_LOG%"
echo [BUILD] Compilation de comspec_overwatch_connect...
if not exist "%SOURCES_DIR%\connect" (
    echo [ERREUR] Sources manquantes: %SOURCES_DIR%\connect >> "%BUILD_LOG%"
    echo [ERREUR] Sources manquantes: %SOURCES_DIR%\connect
    goto :build_fail
)
"%BUILDER_PATH%" "%SOURCES_DIR%\connect" "%OUTPUT_DIR%\addons" -packonly -prefix=z\comspec_overwatch\addons\connect >> "%BUILD_LOG%" 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERREUR] AddonBuilder connect a echoue. >> "%BUILD_LOG%"
    echo [ERREUR] AddonBuilder connect a echoue.
    goto :build_fail
)

:: Bridge optionnel ATAK Enhanced (cTab/BCE) - ignore si AddonBuilder echoue
if exist "%SOURCES_DIR%\atak_athena\config.cpp" (
    echo [BUILD] Compilation de comspec_overwatch_atak_athena... >> "%BUILD_LOG%"
    echo [BUILD] Compilation de comspec_overwatch_atak_athena...
    "%BUILDER_PATH%" "%SOURCES_DIR%\atak_athena" "%OUTPUT_DIR%\addons" -packonly -prefix=z\comspec_overwatch\addons\atak_athena >> "%BUILD_LOG%" 2>&1
    if %ERRORLEVEL% NEQ 0 (
        echo [WARN] AddonBuilder atak_athena a echoue - PBO optionnel ignore. >> "%BUILD_LOG%"
        echo [WARN] AddonBuilder atak_athena a echoue - PBO optionnel ignore.
    )
)

:: Compat Mavic (charge seulement si Mavic_Core present en jeu)
if exist "%SOURCES_DIR%\mavik_compat\config.cpp" (
    echo [BUILD] Compilation de comspec_overwatch_mavik_compat... >> "%BUILD_LOG%"
    echo [BUILD] Compilation de comspec_overwatch_mavik_compat...
    "%BUILDER_PATH%" "%SOURCES_DIR%\mavik_compat" "%OUTPUT_DIR%\addons" -packonly -prefix=z\comspec_overwatch\addons\mavik_compat >> "%BUILD_LOG%" 2>&1
    if %ERRORLEVEL% NEQ 0 (
        echo [WARN] AddonBuilder mavik_compat a echoue - PBO optionnel ignore. >> "%BUILD_LOG%"
        echo [WARN] AddonBuilder mavik_compat a echoue - PBO optionnel ignore.
    )
)

:: 4. DLL a la racine du mod (Native AOT ~5 Mo ??? jamais le stub manag?? ~30 Ko)
::    Ne PAS copier *.pdb / net8.0 (fuite symbols + chemins) ??? pack Workshop : workshop-pack.ps1
echo [DEPLOY] Transfert de la DLL COMSPECExtension_x64... >> "%BUILD_LOG%"
echo [DEPLOY] Transfert de la DLL COMSPECExtension_x64...
set "DLL_SRC="
if exist "%DOTNET_PUBLISH_DIR%\COMSPECExtension_x64.dll" set "DLL_SRC=%DOTNET_PUBLISH_DIR%\COMSPECExtension_x64.dll"
if not defined DLL_SRC if exist "%DOTNET_BUILD_DIR%\COMSPECExtension_x64.dll" set "DLL_SRC=%DOTNET_BUILD_DIR%\COMSPECExtension_x64.dll"
if not defined DLL_SRC if exist "%OUTPUT_DIR%\net8.0\win-x64\native\COMSPECExtension_x64.dll" set "DLL_SRC=%OUTPUT_DIR%\net8.0\win-x64\native\COMSPECExtension_x64.dll"
if defined DLL_SRC (
    copy /Y "%DLL_SRC%" "%OUTPUT_DIR%\COMSPECExtension_x64.dll" >> "%BUILD_LOG%" 2>&1
) else (
    echo [WARN] DLL Native AOT non trouvee. Verifiez la compilation .NET. >> "%BUILD_LOG%"
    echo [WARN] DLL Native AOT non trouvee. Verifiez la compilation .NET.
)
:: Nettoyage artefacts de debug / publish accidentels a la racine du mod
if exist "%OUTPUT_DIR%\COMSPECExtension_x64.pdb" del /F /Q "%OUTPUT_DIR%\COMSPECExtension_x64.pdb" >> "%BUILD_LOG%" 2>&1
if exist "%OUTPUT_DIR%\net8.0" (
    echo [CLEAN] Suppression dossier net8.0 du mod - ne pas shipper. >> "%BUILD_LOG%"
    rmdir /S /Q "%OUTPUT_DIR%\net8.0" >> "%BUILD_LOG%" 2>&1
)
if exist "%PROJECT_DIR%logo.paa" copy /Y "%PROJECT_DIR%logo.paa" "%OUTPUT_DIR%\logo.paa" >> "%BUILD_LOG%" 2>&1
if exist "%PROJECT_DIR%logoSmall.paa" copy /Y "%PROJECT_DIR%logoSmall.paa" "%OUTPUT_DIR%\logoSmall.paa" >> "%BUILD_LOG%" 2>&1
if exist "%PROJECT_DIR%mod.cpp" (
    copy /Y "%PROJECT_DIR%mod.cpp" "%OUTPUT_DIR%\mod.cpp" >> "%BUILD_LOG%" 2>&1
) else (
    if exist "%OUTPUT_DIR%\mod.cpp" (
        echo [INFO] mod.cpp deja present dans le mod. >> "%BUILD_LOG%"
    ) else (
        echo [WARN] mod.cpp introuvable - verifiez le mod.cpp du dossier mod. >> "%BUILD_LOG%"
    )
)

:: 5. Deploiement vers Arma 3
:: IMPORTANT: le launcher charge souvent !Workshop\@COMSPECOverwatch
:: (= junction vers steamapps\workshop\content\107410\3684656708), PAS le dossier local @COMSPECOverwatch.
:: Ne jamais laisser addons\connect\ ou addons\main\ (sources) a cote des .pbo : conflit de prefixe.
echo [DEPLOY] Synchronisation avec le dossier Arma 3... >> "%BUILD_LOG%"
echo [DEPLOY] Synchronisation avec le dossier Arma 3...
set "WORKSHOP_MOD=%ARMA_PATH%\!Workshop\%MOD_NAME%"
set "LOCAL_MOD=%ARMA_PATH%\%MOD_NAME%"
set "WORKSHOP_CONTENT=F:\SteamLibrary\steamapps\workshop\content\107410\3684656708"

if exist "%ARMA_PATH%" (
    for %%T in ("%WORKSHOP_CONTENT%" "%LOCAL_MOD%" "%WORKSHOP_MOD%") do (
        if exist %%~T (
            echo [DEPLOY] Cible: %%~T >> "%BUILD_LOG%"
            echo [DEPLOY] Cible: %%~T
            if not exist "%%~T\addons" mkdir "%%~T\addons"
            if exist "%%~T\addons\connect" rd /s /q "%%~T\addons\connect"
            if exist "%%~T\addons\main" rd /s /q "%%~T\addons\main"
            if exist "%%~T\addons\atak_athena" rd /s /q "%%~T\addons\atak_athena"
            if exist "%%~T\addons\mavik_compat" rd /s /q "%%~T\addons\mavik_compat"
            if exist "%%~T\addons\connect.pbo.pbo" del /f /q "%%~T\addons\connect.pbo.pbo"
            copy /Y "%OUTPUT_DIR%\addons\connect.pbo" "%%~T\addons\connect.pbo" >> "%BUILD_LOG%" 2>&1
            copy /Y "%OUTPUT_DIR%\addons\main.pbo" "%%~T\addons\main.pbo" >> "%BUILD_LOG%" 2>&1
            if exist "%OUTPUT_DIR%\addons\atak_athena.pbo" copy /Y "%OUTPUT_DIR%\addons\atak_athena.pbo" "%%~T\addons\atak_athena.pbo" >> "%BUILD_LOG%" 2>&1
            if exist "%OUTPUT_DIR%\addons\mavik_compat.pbo" copy /Y "%OUTPUT_DIR%\addons\mavik_compat.pbo" "%%~T\addons\mavik_compat.pbo" >> "%BUILD_LOG%" 2>&1
            if exist "%OUTPUT_DIR%\COMSPECExtension_x64.dll" copy /Y "%OUTPUT_DIR%\COMSPECExtension_x64.dll" "%%~T\COMSPECExtension_x64.dll" >> "%BUILD_LOG%" 2>&1
            if exist "%OUTPUT_DIR%\mod.cpp" copy /Y "%OUTPUT_DIR%\mod.cpp" "%%~T\mod.cpp" >> "%BUILD_LOG%" 2>&1
            if exist "%OUTPUT_DIR%\logo.paa" copy /Y "%OUTPUT_DIR%\logo.paa" "%%~T\logo.paa" >> "%BUILD_LOG%" 2>&1
            if exist "%OUTPUT_DIR%\logoSmall.paa" copy /Y "%OUTPUT_DIR%\logoSmall.paa" "%%~T\logoSmall.paa" >> "%BUILD_LOG%" 2>&1
        ) else (
            echo [WARN] Cible absente: %%~T >> "%BUILD_LOG%"
            echo [WARN] Cible absente: %%~T
        )
    )
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
echo   Workshop   : executer workshop-pack.ps1 avant upload Steam
echo                (ne pas publier les .sqf / net8.0 / .pdb)
echo   Log complet : %BUILD_LOG%
echo ===========================================================
if /I "%COMSPEC_BUILD_NOPAUSE%"=="1" goto :build_done
pause
:build_done
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
if /I "%COMSPEC_BUILD_NOPAUSE%"=="1" goto :fail_done
pause
:fail_done
del "%TMP_OUT%" 2>nul
exit /b 1
