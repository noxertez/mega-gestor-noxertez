@echo off
title Lanzador de Tunel Cloudflare (Noxertez v3)
echo Iniciando Tunel Cloudflare...
cd /d "c:\mis app de noxertez 2\SahtoutCMS-main"
cloudflared tunnel --config "c:\mis app de noxertez 2\SahtoutCMS-main\config.yml" run noxertez-v3
pause
