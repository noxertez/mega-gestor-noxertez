import pandas as pd
import os
import shutil
from datetime import datetime

# ========================================
# CONFIGURACIÓN
# ========================================


import mysql.connector
import os

# ========================================
# CONFIGURACIÓN MYSQL
# ========================================

DB_CONFIG = {
    'host': 'localhost',
    'database': 'noxertez',
    'user': 'noxertez_user',
    'password': 'Noxertez2024!',  # <<< CAMBIA ESTO
    'charset': 'utf8mb4'
}

RUTA_IMAGENES = r"C:\Users\usuario\Desktop\noxertez\aaa creaciones"
RUTA_FICHAS = "fichas"
RUTA_MATERIALES = os.path.join(RUTA_IMAGENES, "materiales")
RUTA_PROYECTOS = os.path.join(RUTA_IMAGENES, "proyectos")
RUTA_WEB_PROYECTOS = r"C:\xampp\htdocs\noxertez\uploads\articulos\proyectos"
# Ruta alternativa: servidor SahtoutCMS (si no es junction, se copia también aquí)
RUTA_WEB_PROYECTOS_CMS = r"C:\mis app de noxertez 2\SahtoutCMS-main\Sahtout\uploads\articulos\proyectos"
DB_PATH = "catalogo.db"
ORGANIZACION = "referencia"  # o "marca" o "categoria"

def get_db_connection(*args, **kwargs):
    """Sustituye sqlite3.connect() — devuelve conexión MySQL."""
    conn = mysql.connector.connect(**DB_CONFIG)
    return conn

# Crear carpetas si no existen
for carpeta in [RUTA_IMAGENES, RUTA_FICHAS, RUTA_MATERIALES, RUTA_PROYECTOS]:
    os.makedirs(carpeta, exist_ok=True)

# ========================================
# GESTIÓN DE CONEXIONES CENTRALIZADA
# ========================================

def get_db_connection_wrapper(db_path=DB_PATH):
    """Crea una conexión configurada."""
    return get_db_connection()

# ========================================
# GESTOR DE MATERIALES (SQL)
# ========================================

class GestorMateriales:
    def __init__(self, db_path=DB_PATH):
        self.db_path = db_path
        self._inicializar_tablas()

    def _inicializar_tablas(self):
        conn = get_db_connection()
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute('''CREATE TABLE IF NOT EXISTS materiales (
                                REF_MAT VARCHAR(255) PRIMARY KEY,
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
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM materiales")
            rows = cursor.fetchall()
            return rows
        finally:
            conn.close()

    def guardar_material(self, ref, nombre, unidad, stock, punto_pedido, foto=None, categoria=None, subcategoria=None, marca=None, color=None, dimensiones=None, festividad=None):
        conn = get_db_connection()
        try:
            cursor = conn.cursor(dictionary=True)
            
            for col in ["FOTO", "CATEGORIA", "SUBCATEGORIA", "MARCA", "COLOR", "DIMENSIONES", "FESTIVIDAD"]:
                try:
                    cursor.execute(f"ALTER TABLE materiales ADD COLUMN {col} TEXT")
                except: pass

            cursor.execute('''
                INSERT INTO materiales (REF_MAT, NOMBRE, UNIDAD, STOCK_ACTUAL, PUNTO_PEDIDO, FOTO, CATEGORIA, SUBCATEGORIA, MARCA, COLOR, DIMENSIONES, FESTIVIDAD)
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
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT FOTO FROM materiales WHERE REF_MAT = %s", (ref,))
            row = cursor.fetchone()
            if row and row['FOTO']:
                foto_path = row['FOTO']
                if not os.path.isabs(foto_path):
                    foto_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), foto_path)
                if os.path.exists(foto_path):
                    try:
                        os.remove(foto_path)
                    except Exception as e:
                        print(f"No se pudo eliminar el archivo físico: {e}")

            cursor.execute("DELETE FROM materiales WHERE REF_MAT = %s", (ref,))
            conn.commit()
            return True
        except Exception as e:
            print(f"ERROR eliminando material: {e}")
            return False
        finally:
            conn.close()

    def vincular_material_a_sku(self, sku_base, ref_mat, cantidad):
        conn = get_db_connection()
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("DELETE FROM despiece_articulos WHERE SKU_BASE = %s AND REF_MAT = %s", (sku_base, ref_mat))
            cursor.execute('''
                INSERT INTO despiece_articulos (SKU_BASE, REF_MAT, CANTIDAD)
                VALUES (%s, %s, %s)
            ''', (sku_base, ref_mat, cantidad))
            conn.commit()
            return True
        except Exception as e:
            print(f"ERROR vinculando material: {e}")
            return False
        finally:
            conn.close()

    def desvincular_material_de_sku(self, sku_base, ref_mat):
        conn = get_db_connection()
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("DELETE FROM despiece_articulos WHERE SKU_BASE = %s AND REF_MAT = %s", (sku_base, ref_mat))
            conn.commit()
            return True
        except Exception as e:
            print(f"ERROR desvinculando material: {e}")
            return False
        finally:
            conn.close()

    def obtener_despiece(self, sku_base):
        conn = get_db_connection()
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute('''
                SELECT d.*, m.NOMBRE, m.UNIDAD 
                FROM despiece_articulos d
                JOIN materiales m ON d.REF_MAT = m.REF_MAT
                WHERE d.SKU_BASE = %s
            ''', (sku_base,))
            rows = cursor.fetchall()
            return rows
        finally:
            conn.close()

    def obtener_siguiente_numero_material(self, *args, **kwargs):
        mats = self.obtener_materiales()
        max_num = 0
        import re
        for m in mats:
            sku = m.get('REF_MAT', '')
            match = re.search(r'(\d{4})$', sku)
            if match:
                num = int(match.group(1))
                if num > max_num:
                    max_num = num
        return max_num + 1

# ========================================
# GESTIÓN DE IMÁGENES
# ========================================

def guardar_imagen_producto(imagen_origen, sku, marca=None, categoria=None, numero=1):
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
    return ruta_destino

def obtener_imagenes_producto(sku):
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
    sku_limpio = sku.replace('/', '_').replace('\\', '_')
    carpeta = os.path.join(RUTA_IMAGENES, sku_limpio)
    
    if os.path.exists(carpeta):
        shutil.rmtree(carpeta)
        return True
    return False

# ========================================
# GENERACIÓN DE SKU
# ========================================

def generar_sku(categoria, subcategoria, numero, color=None):
    cat = categoria.replace(' ', '')[:3].upper()
    sub = subcategoria.replace(' ', '')[:3].upper()
    num = str(numero).zfill(4)
    
    sku = f"{cat}{sub}{num}"
    if color:
        sku += f"-{color.upper().replace(' ', '_')}"
    return sku

def obtener_siguiente_numero(productos_existentes, categoria, subcategoria):
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
# CRUD DE PRODUCTOS (MYSQL)
# ========================================

class GestorProductos:
    def __init__(self, ruta_db=DB_PATH):
        self.ruta_db = ruta_db
        self.productos = []
        self.crear_tablas_si_no_existen()
        self.cargar_productos()
    
    def crear_tablas_si_no_existen(self):
        conn = get_db_connection()
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS productos (
                    SKU_REF VARCHAR(255) PRIMARY KEY,
                    SKU_BASE TEXT,
                    ES_VARIANTE TEXT,
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
                )
            ''')
            
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS materiales (
                    REF_MAT VARCHAR(255) PRIMARY KEY,
                    NOMBRE TEXT,
                    UNIDAD TEXT,
                    STOCK_ACTUAL REAL DEFAULT 0,
                    PUNTO_PEDIDO REAL DEFAULT 0,
                    FOTO TEXT,
                    CATEGORIA TEXT,
                    SUBCATEGORIA TEXT
                )
            ''')

            cursor.execute('''
                CREATE TABLE IF NOT EXISTS despiece_articulos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    SKU_BASE TEXT,
                    REF_MAT TEXT,
                    CANTIDAD REAL
                )
            ''')
            
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS clientes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nombre TEXT,
                    telefono TEXT,
                    fecha TEXT
                )
            ''')
            
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS futuros_proyectos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    FECHA TEXT,
                    SKU TEXT,
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
                )
            ''')
            conn.commit()
        finally:
            conn.close()
        self.inicializar_configuracion()

    def inicializar_configuracion(self):
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS configuracion (
                    clave VARCHAR(255) PRIMARY KEY,
                    valor TEXT
                )
            ''')
            conn.commit()
        except Exception as e:
            print(f"Error inicializando configuración: {e}")
        finally:
            try: conn.close()
            except: pass

    def get_clientes(self):
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM clientes ORDER BY nombre ASC")
            rows = cursor.fetchall()
            return rows
        except:
            return []
        finally:
            try: conn.close()
            except: pass

    def guardar_cliente(self, id_cliente, nombre, telefono):
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
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
            try: conn.close()
            except: pass

    def eliminar_cliente(self, id_cliente):
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
            cursor.execute("DELETE FROM clientes WHERE id=%s", (id_cliente,))
            conn.commit()
            return True
        except:
            return False
        finally:
            try: conn.close()
            except: pass

    def get_config(self, clave, default=None):
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT valor FROM configuracion WHERE clave = %s", (clave,))
            row = cursor.fetchone()
            return row['valor'] if row else default
        except:
            return default
        finally:
            try: conn.close()
            except: pass

    def set_config(self, clave, valor):
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
            cursor.execute("REPLACE INTO configuracion (clave, valor) VALUES (%s, %s)", (clave, valor))
            conn.commit()
            return True
        except:
            return False
        finally:
            try: conn.close()
            except: pass

    def cargar_productos(self):
        try:
            conn = get_db_connection()
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM productos")
            rows = cursor.fetchall()
            self.productos = rows
            return True
        except Exception as e:
            print(f"ERROR cargando de MySQL: {e}")
            return False
        finally:
            try: conn.close()
            except: pass

    def crear_producto(self, datos, imagenes=None):
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
        try:
            cols = ', '.join(datos.keys())
            placeholders = ', '.join(['%s'] * len(datos))
            cursor = conn.cursor(dictionary=True)
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
        datos_nuevos['FECHA'] = datetime.now().strftime("%d/%m/%Y, %H:%M:%S")
        conn = get_db_connection()
        try:
            set_clause = ', '.join([f"{k} = %s" for k in datos_nuevos.keys()])
            cursor = conn.cursor(dictionary=True)
            cursor.execute(f"UPDATE productos SET {set_clause} WHERE SKU_REF = %s", tuple(datos_nuevos.values()) + (sku,))
            conn.commit()
            self.cargar_productos()
            return True
        except Exception as e:
            print(f"ERROR actualizando producto: {e}")
            return False
        finally:
            conn.close()

    def eliminar_producto(self, sku):
        conn = get_db_connection()
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT FOTO_PORTADA, GALERIA FROM productos WHERE SKU_REF = %s", (sku,))
            row = cursor.fetchone()
            
            if row:
                fotos = []
                if row['FOTO_PORTADA']: fotos.append(row['FOTO_PORTADA'])
                if row['GALERIA']: fotos.extend([f.strip() for f in row['GALERIA'].split(',') if f.strip()])
                
                for f in fotos:
                    if os.path.exists(f):
                        try: os.remove(f)
                        except: pass

            cursor.execute("DELETE FROM productos WHERE SKU_REF = %s", (sku,))
            conn.commit()
            eliminar_imagenes_producto(sku)
            self.cargar_productos()
            return True
        except Exception as e:
            print(f"ERROR eliminando producto: {e}")
            return False
        finally:
            conn.close()

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
            if fotos_sku:
                fotos_sku.sort()
                self.actualizar_producto(sku, {
                    'FOTO_PORTADA': fotos_sku[0],
                    'GALERIA': ', '.join(fotos_sku[1:]) if len(fotos_sku) > 1 else ''
                })
                count += 1
        return count

# ========================================
# CRUD DE FUTUROS PROYECTOS (MYSQL)
# ========================================

class GestorFuturosProyectos:
    def __init__(self, ruta_db=DB_PATH):
        self.ruta_db = ruta_db

    def sanitizar_nombre_archivo(self, nombre):
        import re
        name, ext = os.path.splitext(nombre)
        name = re.sub(r'[^a-zA-Z0-9_\-]', '_', name)
        name = re.sub(r'_+', '_', name)
        return name + ext.lower()

    def guardar_proyecto(self, datos):
        datos['FECHA'] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        conn = get_db_connection()
        try:
            cols = ', '.join(datos.keys())
            placeholders = ', '.join(['%s'] * len(datos))
            cursor = conn.cursor(dictionary=True)
            cursor.execute(f"INSERT INTO futuros_proyectos ({cols}) VALUES ({placeholders})", tuple(datos.values()))
            conn.commit()
            return True
        except Exception as e:
            print(f"ERROR guardando proyecto: {e}")
            return False
        finally:
            conn.close()

    def subir_carpeta_proyectos(self, ruta_carpeta):
        extensiones = ('.jpg', '.jpeg', '.png', '.webp')
        categoria = os.path.basename(ruta_carpeta).capitalize()
        count = 0
        try:
            for archivo in os.listdir(ruta_carpeta):
                if archivo.lower().endswith(extensiones):
                    ruta_completa = os.path.join(ruta_carpeta, archivo)
                    nombre = os.path.splitext(archivo)[0].replace('_', ' ').capitalize()
                    final_foto = ruta_completa
                    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                    archivo_clean = self.sanitizar_nombre_archivo(archivo)
                    filename = f"{timestamp}_{archivo_clean}"

                    if RUTA_PROYECTOS.lower() not in ruta_completa.lower():
                        try:
                            dest = os.path.join(RUTA_PROYECTOS, filename)
                            shutil.copy2(ruta_completa, dest)
                            final_foto = dest
                        except Exception as e:
                            print(f"Error copiando foto: {e}")
                    else:
                        filename = archivo
                    
                    # Copiar a XAMPP
                    if os.path.exists(RUTA_WEB_PROYECTOS):
                        try:
                            dest_web = os.path.join(RUTA_WEB_PROYECTOS, filename)
                            shutil.copy2(ruta_completa, dest_web)
                        except Exception as e:
                            print(f"Error copiando foto a XAMPP: {e}")
                    # Copiar a SahtoutCMS (si no es junction y es distinta a XAMPP)
                    try:
                        os.makedirs(RUTA_WEB_PROYECTOS_CMS, exist_ok=True)
                        dest_cms = os.path.join(RUTA_WEB_PROYECTOS_CMS, filename)
                        if not os.path.exists(dest_cms):
                            shutil.copy2(ruta_completa, dest_cms)
                    except Exception as e:
                        print(f"Error copiando foto a SahtoutCMS: {e}")

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
        conn = get_db_connection()
        try:
            placeholders = ', '.join([f"{k} = %s" for k in datos.keys()])
            cursor = conn.cursor(dictionary=True)
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
            rows = cursor.fetchall()
            return rows
        except Exception as e:
            print(f"ERROR buscando proyectos: {e}")
            return []
        finally:
            conn.close()

    def eliminar_proyecto(self, id_proyecto):
        conn = get_db_connection()
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT FOTO_REFERENCIA FROM futuros_proyectos WHERE id = %s", (id_proyecto,))
            row = cursor.fetchone()
            if row and row['FOTO_REFERENCIA']:
                if os.path.exists(row['FOTO_REFERENCIA']):
                    try: os.remove(row['FOTO_REFERENCIA'])
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
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT DISTINCT CATEGORIA FROM futuros_proyectos WHERE CATEGORIA IS NOT NULL AND CATEGORIA != ''")
            rows = cursor.fetchall()
            return [r['CATEGORIA'] for r in rows]
        except Exception as e:
            print(f"ERROR obteniendo categorías: {e}")
            return []
        finally:
            conn.close()

# ========================================
# TEST DEL MÓDULO
# ========================================

if __name__ == "__main__":
    print("🧪 TEST DEL MÓDULO 3 - SQLITE\n")
    gestor = GestorProductos()
    print(f"✅ {len(gestor.productos)} productos en DB")
    
    futuros = GestorFuturosProyectos()
    print("✅ Gestor de futuros listo")
