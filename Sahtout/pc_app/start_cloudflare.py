import subprocess
import re
import os
import time
import sys

def start_tunnel():
    print("🚀 Iniciando Túnel de Cloudflare (Dominio Propio: noxertez.com)...")
    
    # Usamos 'tunnel run' para ejecutar el túnel configurado en config.yml
    cmd = ["cloudflared", "tunnel", "--config", r"c:\mis app de noxertez 2\SahtoutCMS-main\config.yml", "run"]
    
    try:
        process = subprocess.Popen(
            cmd, 
            stdout=subprocess.PIPE, 
            stderr=subprocess.STDOUT, 
            text=True,
            bufsize=1,
            creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
        )
        
        url = "https://noxertez.com"
        print(f"✅ Túnel activo en: {url}")

        # Preparar el contenido del archivo
        contenido = f"{url}\n\nEsta es la dirección oficial de tu sitio web.\n\nPara entrar al CMS:\n{url}/noxertez\n"
        
        # Guardar en local (en la raíz del proyecto)
        base_dir = r"c:\mis app de noxertez 2\SahtoutCMS-main"
        with open(os.path.join(base_dir, "direccion web.txt"), "w", encoding="utf-8") as f:
            f.write(contenido)
        
        # Guardar en Desktop
        desktop_folder = r"C:\Users\usuario\Desktop\noxertez"
        try:
            if not os.path.exists(desktop_folder):
                os.makedirs(desktop_folder, exist_ok=True)
            
            ruta_desktop = os.path.join(desktop_folder, "direccion web.txt")
            with open(ruta_desktop, "w", encoding="utf-8") as f:
                f.write(contenido)
            print(f"🏠 Dirección guardada en Escritorio: {ruta_desktop}")
        except Exception as e:
            print(f"⚠️ No se pudo guardar en el Escritorio: {e}")
        
        return True
            
    except Exception as e:
        print(f"❌ Error al iniciar Túnel: {e}")
        return False

if __name__ == "__main__":
    success = start_tunnel()
    if success:
        print("🌐 Cloudflare está funcionando en segundo plano.")
    else:
        print("❌ El túnel no se pudo iniciar.")
        sys.exit(1)
