@echo off
rem COMSPEC : double-cliquez pour preparer un .zip de vos logs sur le Bureau.
rem COMSPEC: double-click to build a .zip of your logs on the Desktop.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Envoyer_mes_logs.ps1"
