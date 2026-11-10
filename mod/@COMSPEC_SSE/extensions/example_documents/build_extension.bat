@echo off
title COMSPEC SSE - Extension Documents d'exemple
setlocal EnableExtensions EnableDelayedExpansion

REM Construit comspec_sse_ext_example_documents.pbo avec AddonBuilder (Arma 3 Tools), comme build_pbo.bat du SSE.
REM Sortie : extensions\@COMSPEC_SSE_ExampleDocuments\addons\ (mod charge avec -mod=@COMSPEC_SSE;@COMSPEC_SSE_ExampleDocuments).
REM README.md, tools\ et ce script ne vont pas dans le PBO : les sources sont copiees dans un dossier de travail d'abord.

set "BUILDER_PATH=F:\SteamLibrary\steamapps\common\Arma 3 Tools\AddonBuilder\AddonBuilder.exe"
set "SRC_DIR=%~dp0"
if "%SRC_DIR:~-1%"=="\" set "SRC_DIR=%SRC_DIR:~0,-1%"
set "ADDON_NAME=example_documents"
set "PBO_NAME=comspec_sse_ext_%ADDON_NAME%"
set "PREFIX=z\comspec_sse_ext\addons\%ADDON_NAME%"
set "MOD_DIR=%SRC_DIR%\..\@COMSPEC_SSE_ExampleDocuments"
set "STAGE=%TEMP%\comspec_sse_ext_stage\%ADDON_NAME%"
set "ABTMP=%TEMP%\comspec_sse_ext_ab\%ADDON_NAME%"
set "OUT_TMP=%TEMP%\comspec_sse_ext_out"

if not exist "%BUILDER_PATH%" (
    echo [ERREUR] AddonBuilder introuvable: %BUILDER_PATH%
    echo          Installez Arma 3 Tools via Steam, ou adaptez BUILDER_PATH en tete de ce script.
    goto :fail
)
if not exist "%SRC_DIR%\config.cpp" (
    echo [ERREUR] config.cpp introuvable dans %SRC_DIR%
    goto :fail
)
if not exist "%SRC_DIR%\data\tpl_press_card_recto_co.paa" (
    echo [AVERTISSEMENT] data\tpl_press_card_recto_co.paa absent : lancez d'abord python tools\gen_textures.py
)

REM Dossier de travail : sources sans README, tools, mod.cpp ni scripts.
if exist "%STAGE%" rd /s /q "%STAGE%"
mkdir "%STAGE%"
robocopy "%SRC_DIR%" "%STAGE%" /E /NFL /NDL /NJH /NJS /XD tools /XF README.md mod.cpp *.bat *.md *.py >nul
if errorlevel 8 (
    echo [ERREUR] Copie des sources impossible
    goto :fail
)

REM AddonBuilder copie l'addon dans un dossier temporaire sans jamais le vider : dossier propre a chaque build.
if exist "%ABTMP%" rd /s /q "%ABTMP%"
if exist "%OUT_TMP%" rd /s /q "%OUT_TMP%"
mkdir "%OUT_TMP%"

echo [BUILD] %PBO_NAME% ...
"%BUILDER_PATH%" "%STAGE%" "%OUT_TMP%" -packonly -clear -temp="%ABTMP%" -prefix=%PREFIX%
if errorlevel 1 (
    echo [ERREUR] AddonBuilder a echoue
    goto :fail
)
if not exist "%OUT_TMP%\%ADDON_NAME%.pbo" (
    echo [ERREUR] PBO non produit
    goto :fail
)

if not exist "%MOD_DIR%\addons" mkdir "%MOD_DIR%\addons"
copy /Y "%OUT_TMP%\%ADDON_NAME%.pbo" "%MOD_DIR%\addons\%PBO_NAME%.pbo" >nul
copy /Y "%SRC_DIR%\mod.cpp" "%MOD_DIR%\mod.cpp" >nul
rd /s /q "%STAGE%" 2>nul
rd /s /q "%OUT_TMP%" 2>nul

echo [OK] %MOD_DIR%\addons\%PBO_NAME%.pbo
echo.
echo   Charger : -mod=@COMSPEC_SSE;@COMSPEC_SSE_ExampleDocuments  (apres CBA_A3, ACE3, COMSPEC ATAK Native)
echo   Activer : en jeu, menu Echap ou module Zeus "COMSPEC - Extensions" : SSE ROLEPLAY - Extensions
if /I "%COMSPEC_BUILD_NOPAUSE%"=="1" exit /b 0
pause
exit /b 0

:fail
if /I "%COMSPEC_BUILD_NOPAUSE%"=="1" exit /b 1
pause
exit /b 1
