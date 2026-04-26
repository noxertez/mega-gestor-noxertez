@echo off
echo ==========================================
echo  NOXERTEZ v4.0 - Iniciando aplicacion...
echo ==========================================
cd /d "%~dp0"

REM Instalar dependencias si hace falta
echo Verificando dependencias...
pip install mysql-connector-python --quiet

REM Arrancar la aplicacion
python app.py
pause
