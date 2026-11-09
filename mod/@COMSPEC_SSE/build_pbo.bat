@echo off
title COMSPEC SSE - Build
setlocal EnableExtensions EnableDelayedExpansion

set "MOD_NAME=@COMSPEC_SSE"
set "BUILDER_PATH=F:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
REM Binarize des Arma 3 Tools (meme installation qu'AddonBuilder) : modeles 3D MLOD -> ODOL, voir :binarize_models.
for %%I in ("%BUILDER_PATH%") do set "A3TOOLS_DIR=%%~dpI.."
set "BINARIZE_PATH=%A3TOOLS_DIR%\Binarize\binarize_x64.exe"
set "PROJECT_DIR=%~dp0"
set "ADDONS_DIR=%PROJECT_DIR%addons"
set "BUILD_LOG=%PROJECT_DIR%build_log.txt"
set "TMP_DIR=%PROJECT_DIR%_build_tmp"

echo ========== COMSPEC SSE Build ========== > "%BUILD_LOG%"
echo Date: %date% %time% >> "%BUILD_LOG%"
echo Repertoire: %PROJECT_DIR% >> "%BUILD_LOG%"
echo. >> "%BUILD_LOG%"

if not exist "%BUILDER_PATH%" (
    echo [ERREUR] AddonBuilder introuvable: %BUILDER_PATH%
    echo [ERREUR] AddonBuilder introuvable: %BUILDER_PATH% >> "%BUILD_LOG%"
    echo         Installez Arma 3 Tools via Steam, ou adaptez BUILDER_PATH en tete de ce script.
    echo         Les PBO deja presents dans addons\ peuvent encore etre packages via workshop-pack.ps1.
    goto :build_fail
)

if exist "%TMP_DIR%" rd /s /q "%TMP_DIR%"
mkdir "%TMP_DIR%"
REM AddonBuilder copie chaque addon dans %TEMP%\<nom du dossier> sans jamais le vider : le "main" du SSE reprenait les
REM fichiers du "main" de l'ATAK natif (comspec_sse_main.pbo de 119 Mo au lieu de 360 Ko, publie le 2026-10-26).
REM Chaque addon a donc son propre dossier temporaire, vide a chaque build (-clear -temp), comme build_mod.bat.
set "ABTMP=%TEMP%\comspec_sse_ab"
if exist "%ABTMP%" rd /s /q "%ABTMP%"

REM Copies locales des en-tetes partages (pas d'#include entre PBO dans les config.cpp)
where python >nul 2>&1 && python "%PROJECT_DIR%tools\sync_headers.py"

set "COMPONENTS=main core generator evidence intel interaction zeus eden ui network digital biometrics identity editor analysis campaign guide eyewear compat_bii compat_ace compat_atak"
REM debug optionnel : build_pbo.bat debug
if /I "%~1"=="debug" set "COMPONENTS=main debug core generator evidence intel interaction zeus eden ui network digital biometrics identity editor analysis campaign guide eyewear compat_bii compat_ace compat_atak"
set "FAILED=0"

for %%C in (%COMPONENTS%) do (
    if not exist "%ADDONS_DIR%\%%C\config.cpp" (
        echo [ERREUR] Source manquante: %%C
        echo [ERREUR] Source manquante: %%C >> "%BUILD_LOG%"
        set "FAILED=1"
    ) else (
        echo [BUILD] comspec_sse_%%C ...
        echo [BUILD] comspec_sse_%%C ... >> "%BUILD_LOG%"
        REM Addon avec des modeles 3D, fichier data\models\model.cfg : P3D binarises dans une copie, puis empaquetage de la copie.
        set "SRC_C=%ADDONS_DIR%\%%C"
        if exist "%ADDONS_DIR%\%%C\data\models\model.cfg" (
            call :binarize_models %%C
            if exist "%TMP_DIR%\proj\z\comspec_sse\addons\%%C\config.cpp" set "SRC_C=%TMP_DIR%\proj\z\comspec_sse\addons\%%C"
        )
        "%BUILDER_PATH%" "!SRC_C!" "%TMP_DIR%" -packonly -clear -temp="%ABTMP%\%%C" -prefix=z\comspec_sse\addons\%%C >> "%BUILD_LOG%" 2>&1
        if errorlevel 1 (
            echo [ERREUR] AddonBuilder a echoue pour %%C
            echo [ERREUR] AddonBuilder a echoue pour %%C >> "%BUILD_LOG%"
            set "FAILED=1"
        ) else (
            if exist "%TMP_DIR%\%%C.pbo" (
                copy /Y "%TMP_DIR%\%%C.pbo" "%ADDONS_DIR%\comspec_sse_%%C.pbo" >> "%BUILD_LOG%" 2>&1
                echo [OK] addons\comspec_sse_%%C.pbo
                echo [OK] addons\comspec_sse_%%C.pbo >> "%BUILD_LOG%"
            ) else (
                echo [ERREUR] PBO non produit pour %%C
                echo [ERREUR] PBO non produit pour %%C >> "%BUILD_LOG%"
                set "FAILED=1"
            )
        )
    )
)

if exist "%TMP_DIR%" rd /s /q "%TMP_DIR%"

echo. >> "%BUILD_LOG%"
if "!FAILED!"=="1" (
    echo ========== BUILD EN ECHEC ========== >> "%BUILD_LOG%"
    echo.
    echo ===========================================================
    echo   BUILD EN ECHEC - Verifiez les messages ci-dessus
    echo   Log complet : %BUILD_LOG%
    echo ===========================================================
    goto :build_fail
)

echo ========== BUILD REUSSI ========== >> "%BUILD_LOG%"
echo Sortie: %PROJECT_DIR% >> "%BUILD_LOG%"

echo.
echo ===========================================================
echo   BUILD TERMINE - RESULTAT
echo ===========================================================
echo   Sortie mod : %PROJECT_DIR%
echo   PBO        : %ADDONS_DIR%\comspec_sse_*.pbo
echo.
echo   Workshop   : executer workshop-pack.ps1 avant upload Steam
echo                (ne pas publier les sources SQF / dossiers d'atelier)
echo   Log complet : %BUILD_LOG%
echo ===========================================================
if /I "%COMSPEC_BUILD_NOPAUSE%"=="1" goto :build_done
pause
:build_done
exit /b 0

:build_fail
if /I "%COMSPEC_BUILD_NOPAUSE%"=="1" goto :fail_done
pause
:fail_done
exit /b 1

REM Modeles MLOD -> ODOL avec binarize, dans une copie de l'addon placee sous son prefixe
REM (%TMP_DIR%\proj\z\comspec_sse\addons\<addon>) : binarize resout les chemins z\... des textures et des rvmat depuis le
REM dossier courant (pas de lecteur P:) et lit data\models\model.cfg (squelette). Echec : l'addon est empaquete avec ses
REM MLOD, lisibles en jeu mais plus lourds (environ 1 Mo par modele au lieu de 160 Ko).
:binarize_models
set "BZ_ADDON=%~1"
set "BZ_PROJ=%TMP_DIR%\proj"
set "BZ_STAGE=%BZ_PROJ%\z\comspec_sse\addons\%BZ_ADDON%"
set "BZ_OUT=%TMP_DIR%\odol_%BZ_ADDON%"
if not exist "%BINARIZE_PATH%" (
    echo [AVERT] binarize introuvable : modeles MLOD empaquetes tels quels pour %BZ_ADDON%
    echo [AVERT] binarize introuvable : %BINARIZE_PATH% >> "%BUILD_LOG%"
    exit /b 1
)
if exist "%BZ_PROJ%" rd /s /q "%BZ_PROJ%"
if exist "%BZ_OUT%" rd /s /q "%BZ_OUT%"
mkdir "%BZ_STAGE%"
mkdir "%BZ_OUT%"
xcopy "%ADDONS_DIR%\%BZ_ADDON%" "%BZ_STAGE%\" /E /I /Q /Y >> "%BUILD_LOG%" 2>&1
pushd "%BZ_PROJ%"
"%BINARIZE_PATH%" -always -silent -maxProcesses=4 "%BZ_STAGE%\data\models" "%BZ_OUT%" *.p3d >> "%BUILD_LOG%" 2>&1
popd
set "BZ_MISS=0"
for %%F in ("%BZ_STAGE%\data\models\*.p3d") do if not exist "%BZ_OUT%\%%~nxF" set "BZ_MISS=1"
if "!BZ_MISS!"=="1" (
    echo [AVERT] binarize : modeles non convertis pour %BZ_ADDON%, empaquetes en MLOD - voir le log
    echo [AVERT] binarize : modeles non convertis pour %BZ_ADDON% >> "%BUILD_LOG%"
    rd /s /q "%BZ_PROJ%"
    exit /b 1
)
copy /Y "%BZ_OUT%\*.p3d" "%BZ_STAGE%\data\models\" >> "%BUILD_LOG%" 2>&1
del /q "%BZ_STAGE%\data\models\model.cfg" >nul 2>&1
echo [OK] modeles binarises (ODOL) : %BZ_ADDON%
echo [OK] modeles binarises (ODOL) : %BZ_ADDON% >> "%BUILD_LOG%"
exit /b 0
