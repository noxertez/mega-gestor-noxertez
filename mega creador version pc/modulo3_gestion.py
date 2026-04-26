import pandas as pd
import mysql.connector
from mysql.connector import Error
import os
import shutil
from datetime import datetime

# ========================================
# CONFIGURACIÓN
# ========================================

RUTA_IMAGENES = r"C:\Users\usuario\Desktop\noxertez\aaa creaciones"
RUTA_FICHAS = "fichas"
RUTA_MATERIALES = os.path.join(RUTA_IMAGENES, "materiales")
RUTA_PROYECTOS = os.path.join(RUTA_IMAGENES, "proyectos")
DB_PATH = "catalogo.db" # Solo para compatibilidad con otros módulos
ORGANIZACION = "referencia"  # o "marca" o "categoria"

# Configuración MySQL (Basada en config.php de XAMPP)
MYSQL_CONFIG = {
    'host': 'localhost',
    'database': 'noxertez',
    'user': 'noxertez_user',
    'password': 'Noxertez2024!',
    'charset': 'utf8mb4',
    'use_pure': True
}

# Crear carpetas si no existen
for carpeta in [RUTA_IMAGENES, RUTA_FICHAS, RUTA_MATERIALES, RUTA_PROYECTOS]:
    os.makedirs(carpeta, exist_ok=True)

# ========================================
# GESTIÓN DE CONEXIONES CENTRALIZADA
# ========================================

def get_db_connection(db_path=None):
    """Crea una conexión configurada a MySQL."""
    try:
        conn = mysql.connector.connect(**MYSQL_CONFIG)
        if conn.is_connected():
            return conn
    except Error as e:
        print(f"Error al conectar a MySQL: {e}")
        return None

# ========================================
# GESTOR DE MATERIALES (SQL)
# ========================================

class GestorMateriales:
    def __init__(self, db_path=DB_PATH):
        self.db_path = db_path
        self._inicializar_tablas() # Asegurar aunque ya esté en modulo nucleo

    def _inicializar_tablas(self):
        # Repetimos por seguridad si se llama de forma aislada
        conn = get_db_connection(self.db_path)
        try:
            cursor = conn.cursor()
            cursor.execute('''CREATE TABLE IF NOT EXISTS materiales (
                                REF_MAT TEXT PRIMARY KEY,
                                NOMBRE TEXT,
                                UNIDAD TEXT,
                                STOCK_ACTUAL REAL,
                                PUNTO_PEDIDO REAL,
                                FOTO TEXT
                            )''')
            cursor.execute('''CREATE TABLE IF NOT EXISTS despiece_articulos (
                                SKU_BASE TEXT,
                                REF_MAT TEXT,
                                CANTIDAD REAL,
                                PRIMARY KEY (SKU_BASE, REF_MAT)
                            )''')
            conn.commit()
        finally:
            conn.close()

    def obtener_materiales(self):
        conn = get_db_connection()
        if not conn: return []
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM materiales")
            rows = cursor.fetchall()
            return rows
        finally:
            conn.close()

    def guardar_material(self, ref, nombre, unidad, stock, punto_pedido, foto=None, categoria=None, subcategoria=None, marca=None, color=None, dimensiones=None, festividad=None):
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            
            # Asegurar columnas nuevas si no existen (Sintaxis MySQL)
            for col in ["FOTO", "CATEGORIA", "SUBCATEGORIA", "MARCA", "COLOR", "DIMENSIONES", "FESTIVIDAD"]:
                try:
                    cursor.execute(f"ALTER TABLE materiales ADD COLUMN {col} TEXT")
                except: pass

            cursor.execute('''
                REPLACE INTO materiales (REF_MAT, NOMBRE, UNIDAD, STOCK_ACTUAL, PUNTO_PEDIDO, FOTO, CATEGORIA, SUBCATEGORIA, MARCA, COLOR, DIMENSIONES, FESTIVIDAD)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ''', (ref, nombre, unidad, stock, punto_pedido, foto, categoria, subcategoria, marca, color, dimensiones, festividad))
            conn.commit()
            return True
        except Exception as e:
            print(f"Error guardando material: {e}")
            return False
        finally:
            conn.close()

    def eliminar_material(self, ref):
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT FOTO FROM materiales WHERE REF_MAT = %s", (ref,))
            row = cursor.fetchone()
            if row and row[0]:
                foto_path = row[0]
                if not os.path.isabs(foto_path):
                    foto_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), foto_path)
                if os.path.exists(foto_path):
                    try:
                        os.remove(foto_path)
                    except: pass

            cursor.execute("DELETE FROM materiales WHERE REF_MAT = %s", (ref,))
            conn.commit()
            return True
        finally:
            conn.close()

    def vincular_material_a_sku(self, sku_base, ref_mat, cantidad):
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            cursor.execute("DELETE FROM despiece_articulos WHERE SKU_BASE = %s AND REF_MAT = %s", (sku_base, ref_mat))
            cursor.execute('''
                INSERT INTO despiece_articulos (SKU_BASE, REF_MAT, CANTIDAD)
                VALUES (%s, %s, %s)
            ''', (sku_base, ref_mat, cantidad))
            conn.commit()
            return True
        finally:
            conn.close()

    def desvincular_material_de_sku(self, sku_base, ref_mat):
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            cursor.execute("DELETE FROM despiece_articulos WHERE SKU_BASE = %s AND REF_MAT = %s", (sku_base, ref_mat))
            conn.commit()
            return True
        finally:
            conn.close()

    def obtener_despiece(self, sku_base):
        conn = get_db_connection()
        if not conn: return []
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute('''
                SELECT d.*, m.NOMBRE, m.UNIDAD 
                FROM despiece_articulos d
                JOIN materiales m ON d.REF_MAT = m.REF_MAT
                WHERE d.SKU_BASE = %s
            ''', (sku_base,))
            return cursor.fetchall()
        finally:
            conn.close()

    def obtener_siguiente_numero_material(self, categoria, subcategoria):
        """Obtiene el siguiente número correlativo para un SKU de material"""
        mats = self.obtener_materiales()
        cat = categoria.replace(' ', '')[:3].upper()
        sub = subcategoria.replace(' ', '')[:3].upper()
        patron = f"{cat}{sub}"
        
        max_num = 0
        for m in mats:
            sku = m.get('REF_MAT', '')
            if sku.startswith(patron):
                try:
                    num_str = sku[len(patron):]
                    # Limpiar por si tiene guiones o algo
                    num_str = "".join(filter(str.isdigit, num_str))
                    if num_str:
                        num = int(num_str)
                        if num > max_num:
                            max_num = num
                except: pass
        return max_num + 1

# ========================================
# GESTIÓN DE IMÁGENES
# ========================================

# ========================================
# GESTIÓN DE IMÁGENES
# ========================================

def guardar_imagen_producto(imagen_origen, sku, marca=None, categoria=None, numero=1):
    """
    Guarda imagen organizada según configuración
    """
    sku_limpio = sku.replace('/', '_').replace('\\', '_')
    
    if ORGANIZACION == "referencia":
        carpeta = os.path.join(RUTA_IMAGENES, sku_limpio)
    elif ORGANIZACION == "marca" and marca:
        carpeta = os.path.join(RUTA_IMAGENES, marca.replace(' ', '_'))
    elif ORGANIZACION == "categoria" and categoria:
        carpeta = os.path.join(RUTA_IMAGENES, categoria)
    else:
        carpeta = RUTA_IMAGENES
    
    os.makedirs(carpeta, exist_ok=True)
    
    extension = os.path.splitext(imagen_origen)[1]
    nombre_archivo = f"{sku_limpio}_{numero}{extension}"
    ruta_destino = os.path.join(carpeta, nombre_archivo)
    
    shutil.copy2(imagen_origen, ruta_destino)
    print(f"Imagen guardada: {ruta_destino}")
    
    return ruta_destino

def obtener_imagenes_producto(sku):
    """Obtiene todas las imágenes de un producto"""
    sku_limpio = sku.replace('/', '_').replace('\\', '_')
    carpeta = os.path.join(RUTA_IMAGENES, sku_limpio)
    
    if not os.path.exists(carpeta):
        return []
    
    imagenes = []
    for archivo in os.listdir(carpeta):
        if archivo.lower().endswith(('.png', '.jpg', '.jpeg', '.gif', '.bmp')):
            imagenes.append(os.path.join(carpeta, archivo))
    
    return sorted(imagenes)

def eliminar_imagenes_producto(sku):
    """Elimina todas las imágenes de un producto"""
    sku_limpio = sku.replace('/', '_').replace('\\', '_')
    carpeta = os.path.join(RUTA_IMAGENES, sku_limpio)
    
    if os.path.exists(carpeta):
        shutil.rmtree(carpeta)
        print(f"Imagenes eliminadas: {carpeta}")
        return True
    return False

# ========================================
# GENERACIÓN DE SKU
# ========================================

def generar_sku(categoria, subcategoria, numero, color=None):
    """Formato: [3_CAT][3_SUB][0000][-COLOR]"""
    cat = categoria.replace(' ', '')[:3].upper()
    sub = subcategoria.replace(' ', '')[:3].upper()
    num = str(numero).zfill(4)
    
    sku = f"{cat}{sub}{num}"
    if color:
        sku += f"-{color.upper().replace(' ', '_')}"
    return sku

def obtener_siguiente_numero(productos_existentes, categoria, subcategoria):
    """Obtiene el siguiente número disponible para un SKU"""
    cat = categoria.replace(' ', '')[:3].upper()
    sub = subcategoria.replace(' ', '')[:3].upper()
    patron = f"{cat}{sub}"
    
    max_num = 0
    for prod in productos_existentes:
        sku = prod.get('SKU_REF', '')
        if sku.startswith(patron):
            try:
                num_str = sku[len(patron):len(patron)+4]
                num = int(num_str)
                if num > max_num:
                    max_num = num
            except:
                pass
    return max_num + 1

# ========================================
# CRUD DE PRODUCTOS (SQLITE)
# ========================================

class GestorProductos:
    """Gestor completo de productos usando SQLite"""
    
    def __init__(self, ruta_db=DB_PATH):
        self.ruta_db = ruta_db
        self.productos = []
        self.crear_tablas_si_no_existen()
        self.cargar_productos()
    
    def crear_tablas_si_no_existen(self):
        """Crea todas las tablas necesarias en MySQL si no existen."""
        conn = get_db_connection()
        if not conn: return
        try:
            cursor = conn.cursor()
            # 1. Tabla de PRODUCTOS
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS productos (
                    SKU_REF VARCHAR(100) PRIMARY KEY,
                    SKU_BASE VARCHAR(100),
                    ES_VARIANTE VARCHAR(10),
                    NOMBRE TEXT,
                    PRECIO TEXT,
                    COLOR TEXT,
                    CATEGORIA TEXT,
                    SUBCATEGORIA TEXT,
                    MARCA TEXT,
                    DIMENSIONES TEXT,
                    DESCRIPCION TEXT,
                    STOCK TEXT,
                    ESTADO TEXT,
                    FOTO_PORTADA TEXT,
                    GALERIA TEXT,
                    MOCKUP TEXT,
                    FECHA TEXT,
                    FESTIVIDAD TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ''')
            
            # Asegurar columnas nuevas en productos (Sintaxis MySQL)
            columnas_prod = [
                ("peso_envio", "DOUBLE DEFAULT 0.5"),
                ("largo_envio", "DOUBLE DEFAULT 20"),
                ("ancho_envio", "DOUBLE DEFAULT 15"),
                ("alto_envio", "DOUBLE DEFAULT 10"),
                ("STOCK_FISICO", "INT DEFAULT 0")
            ]
            for col, tipo in columnas_prod:
                try: cursor.execute(f"ALTER TABLE productos ADD COLUMN {col} {tipo}")
                except: pass

            # 2. Tabla de CLIENTES
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS clientes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nombre TEXT,
                    telefono VARCHAR(50) UNIQUE,
                    fecha DATETIME
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ''')
            
            # Asegurar columnas nuevas en clientes
            columnas_cli = [
                ("email", "TEXT"),
                ("instagram", "TEXT"),
                ("direccion", "TEXT"),
                ("ciudad", "TEXT"),
                ("codigo_postal", "TEXT"),
                ("pais", "TEXT"),
                ("notas", "TEXT")
            ]
            for col, tipo in columnas_cli:
                try: cursor.execute(f"ALTER TABLE clientes ADD COLUMN {col} {tipo}")
                except: pass

            # 3. Tabla de PEDIDOS
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS pedidos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_cliente INT,
                    fecha_pedido DATETIME,
                    fecha_inicio DATETIME,
                    fecha_fin DATETIME,
                    estado TEXT,
                    prioridad TEXT,
                    sku_articulo TEXT,
                    detalles_criticos TEXT,
                    notas TEXT,
                    unboxing_checklist TEXT,
                    total DOUBLE DEFAULT 0,
                    costo_envio DOUBLE DEFAULT 0,
                    metodo_envio TEXT,
                    tracking_id TEXT,
                    INDEX(id_cliente)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ''')

            # 4. Tabla de CLIENTE_ARTICULOS
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS cliente_articulos (
                    id_cliente INT,
                    sku_articulo VARCHAR(100),
                    PRIMARY KEY (id_cliente, sku_articulo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ''')

            # 5. Tabla de MATERIALES
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS materiales (
                    REF_MAT VARCHAR(100) PRIMARY KEY,
                    NOMBRE TEXT,
                    UNIDAD TEXT,
                    STOCK_ACTUAL DOUBLE DEFAULT 0,
                    PUNTO_PEDIDO DOUBLE DEFAULT 0,
                    FOTO TEXT,
                    CATEGORIA TEXT,
                    SUBCATEGORIA TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ''')

            # 6. Tabla de DESPIECE
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS despiece_articulos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    SKU_BASE VARCHAR(100),
                    REF_MAT VARCHAR(100),
                    CANTIDAD DOUBLE,
                    INDEX(SKU_BASE),
                    INDEX(REF_MAT)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ''')
            
            # 7. Tabla de FUTUROS PROYECTOS
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS futuros_proyectos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    FECHA DATETIME,
                    SKU VARCHAR(100),
                    CATEGORIA TEXT,
                    SUBCATEGORIA TEXT,
                    MARCA TEXT,
                    ESTADO TEXT,
                    FOTO_REFERENCIA TEXT,
                    NOMBRE TEXT,
                    DESCRIPCION TEXT,
                    PRECIO TEXT,
                    COLOR TEXT,
                    FESTIVIDAD TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ''')
            conn.commit()
        finally:
            conn.close()
        # Inicializar tabla configuración si no existe
        self.inicializar_configuracion()

    def inicializar_configuracion(self):
        """Crea la tabla de configuración y popula datos iniciales"""
        try:
            conn = get_db_connection(self.ruta_db)
            cursor = conn.cursor()
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS configuracion (
                    clave TEXT PRIMARY KEY,
                    valor TEXT
                )
            ''')
            
            # Importar teléfono desde archivo si existe y la tabla está vacía para esa clave
            if os.path.exists('telefomo.md'):
                with open('telefomo.md', 'r') as f:
                    lineas = f.readlines()
                for linea in lineas:
                    tel = linea.strip()
                    if tel:
                        # Si no tiene nombre asignado, poner "Cliente Importado"
                        cursor.execute("INSERT OR IGNORE INTO clientes (nombre, telefono, fecha) VALUES (?, ?, ?)", 
                                     ("Cliente Importado", tel, datetime.now().strftime("%Y-%m-%d %H:%M:%S")))
            
            conn.commit()
        except Exception as e:
            print(f"Error inicializando configuración: {e}")
        finally:
            try: conn.close()
            except: pass

    # --- Gestión de Clientes ---
    def get_clientes(self):
        """Obtiene todos los clientes de la DB"""
        conn = get_db_connection()
        if not conn: return []
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM clientes ORDER BY nombre ASC")
            return cursor.fetchall()
        except:
            return []
        finally:
            if conn: conn.close()

    def guardar_cliente(self, id_cliente, nombre, telefono):
        """Guarda o actualiza un cliente"""
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            fecha = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            if id_cliente:
                cursor.execute("UPDATE clientes SET nombre=%s, telefono=%s WHERE id=%s", (nombre, telefono, id_cliente))
            else:
                cursor.execute("INSERT INTO clientes (nombre, telefono, fecha) VALUES (%s, %s, %s)", (nombre, telefono, fecha))
            conn.commit()
            return True
        except Exception as e:
            print(f"Error guardando cliente: {e}")
            return False
        finally:
            if conn: conn.close()

    def eliminar_cliente(self, id_cliente):
        """Elimina un cliente"""
        try:
            conn = get_db_connection(self.ruta_db)
            cursor = conn.cursor()
            cursor.execute("DELETE FROM clientes WHERE id=?", (id_cliente,))
            conn.commit()
            return True
        except:
            return False
        finally:
            try: conn.close()
            except: pass

    def get_config(self, clave, default=None):
        """Obtiene un valor de configuración"""
        conn = get_db_connection()
        if not conn: return default
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT valor FROM configuracion WHERE clave = %s", (clave,))
            row = cursor.fetchone()
            return row[0] if row else default
        except:
            return default
        finally:
            if conn: conn.close()

    def set_config(self, clave, valor):
        """Guarda un valor de configuración"""
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            cursor.execute("REPLACE INTO configuracion (clave, valor) VALUES (%s, %s)", (clave, valor))
            conn.commit()
            return True
        except:
            return False
        finally:
            if conn: conn.close()

    def cargar_productos(self):
        """Carga todos los productos de la base de datos"""
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM productos")
            rows = cursor.fetchall()
            self.productos = rows
            print(f"OK {len(self.productos)} productos cargados de MySQL")
            return True
        except Exception as e:
            print(f"ERROR cargando de MySQL: {e}")
            return False
        finally:
            if conn: conn.close()


    def crear_producto(self, datos, imagenes=None):
        """Crea un nuevo producto en MySQL"""
        if imagenes:
            rutas_guardadas = []
            for i, img in enumerate(imagenes, 1):
                ruta = guardar_imagen_producto(img, datos['SKU_REF'], datos.get('MARCA'), datos.get('CATEGORIA'), i)
                rutas_guardadas.append(ruta)
            datos['FOTO_PORTADA'] = rutas_guardadas[0]
            if len(rutas_guardadas) > 1:
                datos['GALERIA'] = ', '.join(rutas_guardadas[1:])
        
        datos['FECHA'] = datetime.now().strftime("%d/%m/%Y, %H:%M:%S")
        
        conn = get_db_connection()
        if not conn: return None
        try:
            cols = ', '.join(datos.keys())
            placeholders = ', '.join(['%s'] * len(datos))
            cursor = conn.cursor()
            cursor.execute(f"INSERT INTO productos ({cols}) VALUES ({placeholders})", tuple(datos.values()))
            conn.commit()
            self.cargar_productos()
            return datos['SKU_REF']
        except Exception as e:
            print(f"ERROR creando producto: {e}")
            return None
        finally:
            conn.close()

    def buscar_producto(self, sku):
        return next((p for p in self.productos if p.get('SKU_REF') == sku), None)

    def actualizar_producto(self, sku, datos_nuevos):
        """Actualiza un producto en MySQL"""
        datos_nuevos['FECHA'] = datetime.now().strftime("%d/%m/%Y, %H:%M:%S")
        conn = get_db_connection()
        if not conn: return False
        try:
            set_clause = ', '.join([f"{k} = %s" for k in datos_nuevos.keys()])
            cursor = conn.cursor()
            cursor.execute(f"UPDATE productos SET {set_clause} WHERE SKU_REF = %s", tuple(datos_nuevos.values()) + (sku,))
            conn.commit()
            self.cargar_productos()
            return True
        except Exception as e:
            print(f"ERROR actualizando producto: {e}")
            return False
        finally:
            if conn: conn.close()

    def eliminar_producto(self, sku):
        """Elimina un producto en MySQL y sus archivos físicos"""
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            # Obtener rutas de fotos antes de borrar de la DB
            cursor.execute("SELECT FOTO_PORTADA, GALERIA FROM productos WHERE SKU_REF = %s", (sku,))
            row = cursor.fetchone()
            
            if row:
                fotos = []
                if row[0]: fotos.append(row[0])
                if row[1]: fotos.extend([f.strip() for f in row[1].split(',') if f.strip()])
                
                for f in fotos:
                    f_path = f
                    if not os.path.isabs(f_path):
                        f_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), f_path)
                    
                    if os.path.exists(f_path):
                        try:
                            os.remove(f_path)
                        except: pass

            cursor.execute("DELETE FROM productos WHERE SKU_REF = %s", (sku,))
            conn.commit()
            
            # Intentar eliminar carpeta si está organizada por SKU
            eliminar_imagenes_producto(sku)
            
            # Eliminar ficha
            sku_limpio = sku.replace('/', '_')
            ficha = os.path.join(RUTA_FICHAS, f"FICHA_{sku_limpio}.png")
            if os.path.exists(ficha): os.remove(ficha)
            
            self.cargar_productos()
            return True
        except Exception as e:
            print(f"ERROR eliminando producto: {e}")
            return False
        finally:
            if conn: conn.close()

    def obtener_grupo_producto(self, sku_base):
        variantes = [p for p in self.productos if p.get('SKU_BASE') == sku_base]
        base = next((p for p in variantes if p.get('SKU_REF') == sku_base), None)
        return {
            'base': base,
            'variantes': variantes,
            'total_variantes': len(variantes)
        }

    def filtrar(self, categoria=None, marca=None, festividad=None, es_variante=None):
        res = self.productos
        if categoria: res = [p for p in res if p.get('CATEGORIA') == categoria]
        if marca: res = [p for p in res if p.get('MARCA') == marca]
        if festividad: res = [p for p in res if p.get('FESTIVIDAD') == festividad]
        if es_variante: res = [p for p in res if p.get('ES_VARIANTE') == es_variante]
        return res

    def buscar_productos(self, filtros):
        """Busca productos usando un diccionario de filtros"""
        res = self.productos
        if not filtros: return res
        
        for k, v in filtros.items():
            if v:
                key = k.upper()
                val = str(v).upper()
                res = [p for p in res if val in str(p.get(key, '')).upper()]
        return res

    def productos_sin_imagen(self):
        return [p for p in self.productos if not p.get('FOTO_PORTADA')]

    def enlazar_imagenes_por_sku(self, carpeta_raiz):
        """Escanea carpeta y enlaza imágenes"""
        if not os.path.exists(carpeta_raiz): return 0
        
        def normalizar(t):
            import unicodedata
            if not t: return ""
            s = "".join(c for c in unicodedata.normalize('NFD', str(t).lower()) if unicodedata.category(c) != 'Mn')
            return s.replace(' ', '').replace('_', '').replace('-', '')

        count = 0
        from collections import defaultdict
        mapa_folders = defaultdict(list)
        for root, dirs, _ in os.walk(carpeta_raiz):
            for d in dirs: mapa_folders[normalizar(d)].append(os.path.join(root, d))

        for prod in self.productos:
            sku = prod.get('SKU_REF')
            if not sku: continue
            
            sku_norm = normalizar(sku)
            fotos_sku = []
            
            if sku_norm in mapa_folders:
                for folder in mapa_folders[sku_norm]:
                    fotos_sku.extend([os.path.join(folder, f) for f in os.listdir(folder) if f.lower().endswith(('.png', '.jpg', '.jpeg'))])
            
            if not fotos_sku:
                for root, _, files in os.walk(carpeta_raiz):
                    for f in files:
                        if f.lower().endswith(('.png', '.jpg', '.jpeg')) and normalizar(f).startswith(sku_norm):
                            fotos_sku.append(os.path.join(root, f))
            
            if fotos_sku:
                fotos_sku.sort()
                self.actualizar_producto(sku, {
                    'FOTO_PORTADA': fotos_sku[0],
                    'GALERIA': ', '.join(fotos_sku[1:]) if len(fotos_sku) > 1 else ''
                })
                count += 1
        return count

# ========================================
# CRUD DE FUTUROS PROYECTOS (SQLITE)
# ========================================

class GestorFuturosProyectos:
    """Implementa la gestión de artículos por crear en SQLite"""
    
    def __init__(self, ruta_db=DB_PATH):
        self.ruta_db = ruta_db

    def guardar_proyecto(self, datos):
        datos['FECHA'] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        conn = get_db_connection()
        if not conn: return False
        try:
            cols = ', '.join(datos.keys())
            placeholders = ', '.join(['%s'] * len(datos))
            cursor = conn.cursor()
            cursor.execute(f"INSERT INTO futuros_proyectos ({cols}) VALUES ({placeholders})", tuple(datos.values()))
            conn.commit()
            return True
        except Exception as e:
            print(f"ERROR guardando proyecto: {e}")
            return False
        finally:
            conn.close()

    def subir_carpeta_proyectos(self, ruta_carpeta):
        """Escanea una carpeta y añade todas las imágenes como proyectos futuros, usando el nombre de la carpeta como categoría"""
        extensiones = ('.jpg', '.jpeg', '.png', '.webp')
        categoria = os.path.basename(ruta_carpeta).capitalize()
        count = 0
        from modulo3_gestion import RUTA_PROYECTOS
        try:
            for archivo in os.listdir(ruta_carpeta):
                if archivo.lower().endswith(extensiones):
                    ruta_completa = os.path.join(ruta_carpeta, archivo)
                    nombre = os.path.splitext(archivo)[0].replace('_', ' ').capitalize()
                    
                    # Asegurar que la imagen está en la carpeta de proyectos
                    final_foto = ruta_completa
                    if RUTA_PROYECTOS.lower() not in ruta_completa.lower():
                        try:
                            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                            filename = f"{timestamp}_{archivo}"
                            dest = os.path.join(RUTA_PROYECTOS, filename)
                            shutil.copy2(ruta_completa, dest)
                            final_foto = dest
                        except Exception as e:
                            print(f"Error copiando foto en subida masiva: {e}")

                    # Añadir a la base de datos
                    self.guardar_proyecto({
                        "NOMBRE": nombre,
                        "CATEGORIA": categoria,
                        "ESTADO": "PENDIENTE",
                        "FOTO_REFERENCIA": final_foto
                    })
                    count += 1
            return count
        except Exception as e:
            print(f"ERROR subiendo carpeta: {e}")
            return 0

    def modificar_proyecto(self, id_proyecto, datos):
        """Actualiza un proyecto existente en MySQL"""
        conn = get_db_connection()
        if not conn: return False
        try:
            placeholders = ', '.join([f"{k} = %s" for k in datos.keys()])
            cursor = conn.cursor()
            query = f"UPDATE futuros_proyectos SET {placeholders} WHERE id = %s"
            cursor.execute(query, tuple(datos.values()) + (id_proyecto,))
            conn.commit()
            return True
        except Exception as e:
            print(f"ERROR modificando proyecto: {e}")
            return False
        finally:
            conn.close()

    def buscar_proyectos(self, filtros=None):
        conn = get_db_connection()
        if not conn: return []
        try:
            cursor = conn.cursor(dictionary=True)
            
            query = "SELECT * FROM futuros_proyectos"
            params = []
            
            if filtros:
                clauses = []
                for k, v in filtros.items():
                    if v:
                        clauses.append(f"{k} LIKE %s")
                        params.append(f"%{v}%")
                if clauses:
                    query += " WHERE " + " AND ".join(clauses)
            
            cursor.execute(query, params)
            return cursor.fetchall()
        except Exception as e:
            print(f"ERROR buscando proyectos: {e}")
            return []
        finally:
            conn.close()

    def eliminar_proyecto(self, id_proyecto):
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT FOTO_REFERENCIA FROM futuros_proyectos WHERE id = %s", (id_proyecto,))
            row = cursor.fetchone()
            if row and row[0]:
                foto_path = row[0]
                if os.path.exists(foto_path):
                    try:
                        os.remove(foto_path)
                    except: pass

            cursor.execute("DELETE FROM futuros_proyectos WHERE id = %s", (id_proyecto,))
            conn.commit()
            return True
        except Exception as e:
            print(f"ERROR eliminando proyecto: {e}")
            return False
        finally:
            conn.close()

    def obtener_categorias(self):
        conn = get_db_connection()
        if not conn: return []
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT DISTINCT CATEGORIA FROM futuros_proyectos")
            cats = [row[0] for row in cursor.fetchall() if row[0]]
            return sorted(cats)
        except:
            return []
        finally:
            conn.close()

# ========================================
# TEST DEL MÓDULO
# ========================================

if __name__ == "__main__":
    print("🧪 TEST DEL MÓDULO 3 - MYSQL\n")
    gestor = GestorProductos()
    print(f"✅ {len(gestor.productos)} productos en DB")
    
    futuros = GestorFuturosProyectos()
    print("✅ Gestor de futuros listo")
