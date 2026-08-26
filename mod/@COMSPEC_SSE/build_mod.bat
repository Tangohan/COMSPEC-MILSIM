@echo off
REM Point d'entree Overwatch-like : compile les PBO SSE via AddonBuilder.
call "%~dp0build_pbo.bat" %*
exit /b %ERRORLEVEL%
