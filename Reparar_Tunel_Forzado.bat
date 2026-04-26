@echo off
:: Reparar servicio cloudflared forzando el cierre del proceso
title Reparador Forzado de Tunel Cloudflare
echo [1/3] Forzando el cierre de cualquier proceso de cloudflared...
taskkill /F /IM cloudflared.exe /T

echo [2/3] Re-configurando el servicio...
sc config cloudflared binPath= "\"C:\Program Files (x86)\cloudflared\cloudflared.exe\" tunnel --config \"c:\mis app de noxertez 2\SahtoutCMS-main\config.yml\" run noxertez-v3"

echo [3/3] Iniciando el servicio...
sc start cloudflared

echo.
echo Proceso finalizado. Verifica si noxertez.com ya funciona.
pause
