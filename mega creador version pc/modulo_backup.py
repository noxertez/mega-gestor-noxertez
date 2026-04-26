import os
import shutil
import zipfile
import subprocess
from datetime import datetime

def crear_backup_total(app_root, db_config, ruta_imagenes, ruta_destino=None):
    """
    Crea un respaldo completo:
    1. Dump de la base de datos MySQL.
    2. Compresión de la carpeta de la aplicación.
    3. Compresión de la carpeta de imágenes (aaa creaciones).
    """
    timestamp = datetime.now().strftime("%Y-%m-%d_%H-%M-%S")
    
    # Determinar carpeta de destino
    if ruta_destino and os.path.isdir(ruta_destino):
        backup_folder = ruta_destino
    else:
        backup_folder = os.path.join(app_root, "backups")
        
    os.makedirs(backup_folder, exist_ok=True)
    
    zip_filename = f"noxertez_pc_backup_{timestamp}.zip"
    zip_path = os.path.join(backup_folder, zip_filename)
    db_dump_path = os.path.join(backup_folder, f"temp_db_{timestamp}.sql")
    
    try:
        # 1. Dump de Base de Datos
        mysqldump_exe = "C:\\xampp\\mysql\\bin\\mysqldump.exe"
        cmd = [
            mysqldump_exe,
            f"--user={db_config['user']}",
            f"--password={db_config['password']}",
            f"--host={db_config['host']}",
            db_config['database']
        ]
        
        with open(db_dump_path, 'w') as f:
            result = subprocess.run(cmd, stdout=f, stderr=subprocess.PIPE, text=True)
            
        if result.returncode != 0:
            return False, f"Error en mysqldump: {result.stderr}"

        # 2. Crear ZIP
        with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
            # Añadir archivos de la App PC (esta carpeta)
            for root, dirs, files in os.walk(app_root):
                # Excluir la carpeta de backups para no ser recursivo
                if "backups" in root:
                    continue
                # Excluir venv y caches
                if ".venv" in root or "__pycache__" in root:
                    continue
                    
                for file in files:
                    full_path = os.path.join(root, file)
                    rel_path = os.path.relpath(full_path, app_root)
                    zf.write(full_path, os.path.join("app_pc", rel_path))
            
            # Añadir dump de la DB
            zf.write(db_dump_path, "database_dump.sql")
            
            # Añadir Imágenes (aaa creaciones)
            if os.path.exists(ruta_imagenes):
                for root, dirs, files in os.walk(ruta_imagenes):
                    for file in files:
                        full_path = os.path.join(root, file)
                        rel_path = os.path.relpath(full_path, ruta_imagenes)
                        zf.write(full_path, os.path.join("imagenes_productos", rel_path))
        
        # Limpiar dump temporal
        if os.path.exists(db_dump_path):
            os.remove(db_dump_path)
            
        return True, zip_path
        
    except Exception as e:
        return False, str(e)
