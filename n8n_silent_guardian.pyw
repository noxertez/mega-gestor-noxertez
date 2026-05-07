import subprocess
import time
import psutil
import os
import sys

# Configuración
N8N_COMMAND = "n8n start"
NODE_OPTIONS = "--max-old-space-size=4096"
LOG_FILE = r"c:\mis app de noxertez 2\SahtoutCMS-main\n8n_silent_guardian.log"
DEBUG_LOG = r"c:\mis app de noxertez 2\SahtoutCMS-main\n8n_error_debug.log"
CHECK_INTERVAL = 60 # Comprobamos cada minuto

def is_n8n_running():
    current_pid = os.getpid()
    for proc in psutil.process_iter(['name', 'cmdline', 'pid']):
        try:
            # Ignoramos este propio proceso
            if proc.info['pid'] == current_pid:
                continue
                
            cmdline = proc.info.get('cmdline')
            if cmdline:
                # Buscamos 'n8n' pero ignoramos si es el script del guardian
                cmd_str = " ".join(cmdline).lower()
                if 'n8n' in cmd_str and 'n8n_silent_guardian' not in cmd_str:
                    return True
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue
    return False

def start_n8n():
    with open(LOG_FILE, "a") as log:
        log.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] REINICIO: n8n no detectado. Arrancando...\n")
    
    env = os.environ.copy()
    env["NODE_OPTIONS"] = NODE_OPTIONS
    env["N8N_SECURE_COOKIE"] = "false"
    
    with open(DEBUG_LOG, "a") as debug_f:
        subprocess.Popen(
            N8N_COMMAND, 
            shell=True, 
            env=env,
            stdout=debug_f,
            stderr=subprocess.STDOUT,
            creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == "win32" else 0
        )

if __name__ == "__main__":
    # La primera vez lo arrancamos sí o sí para asegurar
    if not is_n8n_running():
        start_n8n()
        
    while True:
        time.sleep(CHECK_INTERVAL)
        if not is_n8n_running():
            start_n8n()
