@echo off
title COMSPEC SSE - Build
setlocal EnableExtensions EnableDelayedExpansion

set "MOD_NAME=@COMSPEC_SSE"
set "BUILDER_PATH=F:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
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

set "COMPONENTS=main core generator evidence intel interaction zeus eden ui network digital biometrics compat_bii compat_ace"
REM debug optionnel : build_pbo.bat debug
if /I "%~1"=="debug" set "COMPONENTS=main debug core generator evidence intel interaction zeus eden ui network digital biometrics compat_bii compat_ace"
set "FAILED=0"

for %%C in (%COMPONENTS%) do (
    if not exist "%ADDONS_DIR%\%%C\config.cpp" (
        echo [ERREUR] Source manquante: %%C
        echo [ERREUR] Source manquante: %%C >> "%BUILD_LOG%"
        set "FAILED=1"
    ) else (
        echo [BUILD] comspec_sse_%%C ...
        echo [BUILD] comspec_sse_%%C ... >> "%BUILD_LOG%"
        "%BUILDER_PATH%" "%ADDONS_DIR%\%%C" "%TMP_DIR%" -packonly -prefix=z\comspec_sse\addons\%%C >> "%BUILD_LOG%" 2>&1
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
