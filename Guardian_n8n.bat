@echo off
title NOXERTEZ - Guardian de n8n (Auto-Restart)
set N8N_SECURE_COOKIE=false
set NODE_OPTIONS=--max-old-space-size=4096
set LOG_FILE="c:\mis app de noxertez 2\SahtoutCMS-main\n8n_guardian.log"

echo ========================================================
echo        GUARDIAN DE n8n - NOXERTEZ ACTIVADO
echo ========================================================
echo Este script reiniciara n8n automaticamente si se cierra.
echo Fecha de inicio: %date% %time%
echo Logs guardados en: %LOG_FILE%
echo ========================================================

:loop
echo [%time%] Iniciando n8n... >> %LOG_FILE%
echo Iniciando n8n (Accesible en http://localhost:5678)...
n8n start >> %LOG_FILE% 2>&1
echo [%time%] ADVERTENCIA: n8n se ha cerrado. Reiniciando en 5 segundos... >> %LOG_FILE%
echo [!] n8n se ha cerrado de forma inesperada.
echo [!] Reiniciando en 5 segundos... (Presiona Ctrl+C para cancelar)
timeout /t 5
goto loop
