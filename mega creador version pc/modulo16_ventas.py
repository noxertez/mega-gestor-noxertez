import tkinter as tk
from tkinter import ttk, messagebox
import pandas as pd
import os
from datetime import datetime

# Importar constantes y utilidades de otros módulos
try:
    from modulo3_gestion import DB_PATH, get_db_connection
    from modulo2_interfaz import COLOR_FONDO, COLOR_MORADO, COLOR_VERDE
    from modulo5_mejoras_visuales import PreviewImagenSeleccionada, BotonConFeedback, Notificacion
except ImportError:
    DB_PATH = "catalogo.db"
    COLOR_FONDO = "#f3f4f6"
    COLOR_MORADO = "#8b5cf6"
    COLOR_VERDE = "#10b981"
    
    class BotonConFeedback:
        def __init__(self, parent, texto, comando, color, icono):
            self.btn = tk.Button(parent, text=f"{icono} {texto}", command=comando, bg=color, fg="white")
        def pack(self, **kwargs): self.btn.pack(**kwargs)
        def grid(self, **kwargs): self.btn.grid(**kwargs)
        
    class PreviewImagenSeleccionada:
        def __init__(self, parent): self.frame = tk.Frame(parent)
        def mostrar_placeholder(self): pass
        def mostrar_imagen(self, ruta): pass
        
    class Notificacion:
        @staticmethod
        def mostrar(root, msg, tipo): messagebox.showinfo(tipo, msg)

    def get_db_connection(db_path=None):
        from modulo3_gestion import get_db_connection as get_mysql_conn
        return get_mysql_conn()

# ========================================
# GESTOR DE VENTAS (SQLITE)
# ========================================

class GestorVentas:
    def __init__(self, db_path=DB_PATH):
        self.db_path = db_path
    def __init__(self, db_path=DB_PATH):
        self.db_path = db_path
        self._inicializar_tablas()
        self.columnas_plataformas = self.obtener_plataformas()

    def _inicializar_tablas(self):
        conn = get_db_connection()
        if not conn: return
        try:
            cursor = conn.cursor()
            
            # Tabla de configuración de plataformas
            cursor.execute('''CREATE TABLE IF NOT EXISTS plataformas_config (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                nombre_columna VARCHAR(100) UNIQUE,
                                nombre_visible VARCHAR(100),
                                orden INT
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4''')
            
            # Seed plataformas iniciales si está vacío
            cursor.execute("SELECT count(*) FROM plataformas_config")
            if cursor.fetchone()[0] == 0:
                plats_iniciales = [
                    ('milanuncio', 'Milanuncio'), ('mil_e', 'Mil-E'), ('wallapop', 'Wallapop'),
                    ('trendiof', 'Trendiof'), ('trendioff_velas', 'Trendioff Velas'),
                    ('vinted', 'Vinted'), ('marketplace', 'Marketplace'),
                    ('etsy_noxertez', 'Etsy Noxertez'), ('etsy_atelier', 'Etsy Atelier'),
                    ('etsy_portavelas', 'Etsy Portavelas'), ('etsy_piedras', 'Etsy Piedras')
                ]
                for i, (col, vis) in enumerate(plats_iniciales):
                    cursor.execute("INSERT INTO plataformas_config (nombre_columna, nombre_visible, orden) VALUES (%s, %s, %s)", (col, vis, i))
            
            # Recargar plataformas
            cursor.execute("SELECT nombre_columna FROM plataformas_config ORDER BY orden")
            plats = [r[0] for r in cursor.fetchall()]

            # Tabla para plataformas de venta
            cursor.execute("SHOW TABLES LIKE 'plataformas_ventas'")
            table_exists = cursor.fetchone()

            if not table_exists:
                query = '''CREATE TABLE plataformas_ventas (
                            SKU_BASE VARCHAR(100) PRIMARY KEY,
                            UNIDADES_VENTA DOUBLE DEFAULT 0'''
                for plat in plats:
                    query += f", {plat}_ESTADO VARCHAR(50), {plat}_PRECIO DOUBLE"
                query += ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                cursor.execute(query)
            else:
                # Asegurar que todas las columnas configuradas existen
                cursor.execute("DESCRIBE plataformas_ventas")
                cols_db = [c[0] for c in cursor.fetchall()]
                for plat in plats:
                    est_col = f"{plat}_ESTADO"
                    pre_col = f"{plat}_PRECIO"
                    url_col = f"{plat}_URL"
                    if est_col not in cols_db:
                        cursor.execute(f"ALTER TABLE plataformas_ventas ADD COLUMN {est_col} VARCHAR(50)")
                    if pre_col not in cols_db:
                        cursor.execute(f"ALTER TABLE plataformas_ventas ADD COLUMN {pre_col} DOUBLE")
                    if url_col not in cols_db:
                        cursor.execute(f"ALTER TABLE plataformas_ventas ADD COLUMN {url_col} VARCHAR(500)")
            
            conn.commit()
        finally:
            if conn: conn.close()

    def obtener_plataformas(self):
        conn = get_db_connection()
        if not conn: return []
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT nombre_columna, nombre_visible FROM plataformas_config ORDER BY orden")
            return cursor.fetchall()
        except:
            return []
        finally:
            if conn: conn.close()

    def agregar_plataforma(self, nombre_visible):
        import re
        nombre_col = re.sub(r'[^a-zA-Z0-9]', '_', nombre_visible.lower())
        conn = get_db_connection()
        if not conn: return False, "No se pudo conectar a la DB"
        try:
            cursor = conn.cursor()
            # Verificar si ya existe
            cursor.execute("SELECT 1 FROM plataformas_config WHERE nombre_columna = %s", (nombre_col,))
            if cursor.fetchone():
                return False, "La plataforma ya existe"

            # Agregar a config
            cursor.execute("SELECT MAX(orden) FROM plataformas_config")
            row_max = cursor.fetchone()
            max_orden = row_max[0] if row_max and row_max[0] else 0
            cursor.execute("INSERT INTO plataformas_config (nombre_columna, nombre_visible, orden) VALUES (%s, %s, %s)", 
                         (nombre_col, nombre_visible, max_orden + 1))
            
            # Agregar columnas a tabla ventas
            cursor.execute(f"ALTER TABLE plataformas_ventas ADD COLUMN {nombre_col}_ESTADO VARCHAR(50)")
            cursor.execute(f"ALTER TABLE plataformas_ventas ADD COLUMN {nombre_col}_PRECIO DOUBLE")
            
            conn.commit()
            self.columnas_plataformas = self.obtener_plataformas()
            return True, "Plataforma agregada"
        except Exception as e:
            return False, str(e)
        finally:
            if conn: conn.close()

    def renombrar_plataforma(self, id_plat, nuevo_nombre_visible):
        import re
        nuevo_nombre_col = re.sub(r'[^a-zA-Z0-9]', '_', nuevo_nombre_visible.lower())
        conn = get_db_connection()
        if not conn: return False, "No se pudo conectar a la DB"
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT nombre_columna FROM plataformas_config WHERE id = %s", (id_plat,))
            old_res = cursor.fetchone()
            if not old_res: return False, "No se encontró la plataforma"
            old_col = old_res[0]

            if old_col != nuevo_nombre_col:
                # Renombrar columnas en MySQL
                cursor.execute(f"ALTER TABLE plataformas_ventas CHANGE COLUMN {old_col}_ESTADO {nuevo_nombre_col}_ESTADO VARCHAR(50)")
                cursor.execute(f"ALTER TABLE plataformas_ventas CHANGE COLUMN {old_col}_PRECIO {nuevo_nombre_col}_PRECIO DOUBLE")

            cursor.execute("UPDATE plataformas_config SET nombre_columna = %s, nombre_visible = %s WHERE id = %s", 
                         (nuevo_nombre_col, nuevo_nombre_visible, id_plat))
            
            conn.commit()
            self.columnas_plataformas = self.obtener_plataformas()
            return True, "Plataforma renombrada"
        except Exception as e:
            return False, str(e)
        finally:
            if conn: conn.close()

    def obtener_datos_ventas(self, sku_base=None):
        conn = get_db_connection()
        if not conn: return [] if not sku_base else None
        try:
            cursor = conn.cursor(dictionary=True)
            if sku_base:
                cursor.execute("SELECT * FROM plataformas_ventas WHERE SKU_BASE = %s", (sku_base,))
                return cursor.fetchone()
            else:
                cursor.execute("SELECT * FROM plataformas_ventas")
                return cursor.fetchall()
        finally:
            if conn: conn.close()

    def guardar_datos_ventas(self, datos):
        """datos: dict con SKU_BASE y los campos a actualizar"""
        if 'SKU_BASE' not in datos:
            return False
            
        conn = get_db_connection()
        if not conn: return False
        try:
            cursor = conn.cursor()
            sku_base = datos['SKU_BASE']
            
            # Separar datos de la tabla productos (stock general, fisico y realizadas)
            prod_updates = {}
            # Mapeo de nombres si vienen de la web (donde pueden ser minúsculas o diferentes)
            if 'STOCK_FISICO' in datos: prod_updates['STOCK_FISICO'] = datos.pop('STOCK_FISICO')
            if 'stock_fisico' in datos: prod_updates['STOCK_FISICO'] = datos.pop('stock_fisico')
            if 'UNIDADES_REALIZADAS' in datos: prod_updates['UNIDADES_REALIZADAS'] = datos.pop('UNIDADES_REALIZADAS')
            if 'unidades_realizadas' in datos: prod_updates['UNIDADES_REALIZADAS'] = datos.pop('unidades_realizadas')
            if 'STOCK' in datos: prod_updates['STOCK'] = datos.pop('STOCK')
            if 'stock' in datos: prod_updates['STOCK'] = datos.pop('stock')

            if prod_updates:
                set_clause = ", ".join([f"{col} = %s" for col in prod_updates.keys()])
                params = list(prod_updates.values()) + [sku_base]
                cursor.execute(f"UPDATE productos SET {set_clause} WHERE SKU_REF = %s", params)

            # Verificar si existe en plataformas_ventas
            cursor.execute("SELECT 1 FROM plataformas_ventas WHERE SKU_BASE = %s", (sku_base,))
            exists = cursor.fetchone()
            
            # Solo si quedan campos por actualizar en plataformas_ventas
            if len(datos) > 1: # Queda al menos SKU_BASE y otro
                cols = [k for k in datos.keys() if k != 'SKU_BASE']
                vals = [datos[k] for k in cols]
                
                if exists:
                    set_clause_v = ", ".join([f"{col} = %s" for col in cols])
                    params_v = vals + [sku_base]
                    cursor.execute(f"UPDATE plataformas_ventas SET {set_clause_v} WHERE SKU_BASE = %s", params_v)
                else:
                    cols_all = ['SKU_BASE'] + cols
                    vals_all = [sku_base] + vals
                    placeholders = ", ".join(["%s"] * len(cols_all))
                    cursor.execute(f"INSERT INTO plataformas_ventas ({', '.join(cols_all)}) VALUES ({placeholders})", vals_all)
            
            conn.commit()
            return True
        except Exception as e:
            print(f"Error guardando datos de ventas: {e}")
            return False
        finally:
            if conn: conn.close()

    def importar_desde_excel(self, excel_path):
        """Importa datos desde el listado de creaciones noxertez.xlsx"""
        if not os.path.exists(excel_path):
            return 0
            
        try:
            df = pd.read_excel(excel_path)
            # Limpiar nombres de columnas
            original_cols = list(df.columns)
            df.columns = [str(c).strip().lower() for c in df.columns]
            
            # Identificar columna SKU (el usuario usa 'sku' mayoritariamente)
            sku_idx = -1
            for i, c in enumerate(df.columns):
                if c in ['sku', 'sko', 'referencia', 'sku_base', 'referencia base', 'ref', 'sku_ref']:
                    sku_idx = i
                    break
            
            if sku_idx == -1:
                print("No se encontró columna de SKU en el Excel")
                return 0
                
            sku_col_name = df.columns[sku_idx]
            sku_col_original = original_cols[sku_idx]

            # Mapeo de columnas Excel a DB (basado en la estructura observada)
            mapeo = {
                'milanuncio': ('milanuncio_ESTADO', 'precio.1'),
                'mil-e': ('mil_e_ESTADO', 'precio.2'),
                'wallapop': ('wallapop_ESTADO', 'precio.3'),
                'trendiof': ('trendiof_ESTADO', 'precio.4'),
                'trendioff velas': ('trendioff_velas_ESTADO', 'precio.5'),
                'vinted': ('vinted_ESTADO', 'precio.6'),
                'marketplace': ('marketplace_ESTADO', 'precio.7'),
                'etsy noxertez': ('etsy_noxertez_ESTADO', 'precio.8'),
                'etsy atelier': ('etsy_atelier_ESTADO', 'precio.9'),
                'etsy portavelas': ('etsy_portavelas_ESTADO', 'precio.10'),
                'etsy piedras': ('etsy_piedras_ESTADO', 'precio.11')
            }
            
            # Obtener mapas de búsqueda de la tabla productos
            conn = get_db_connection()
            if not conn: return 0
            try:
                cursor = conn.cursor()
                
                # Mapa por Nombre (para fallback)
                cursor.execute("SELECT SKU_BASE, NOMBRE FROM productos WHERE ES_VARIANTE = 'BASE'")
                productos_por_nombre = {str(row[1]).lower().strip(): row[0] for row in cursor.fetchall()}
                
                # Mapa por SKU (SKU_REF -> SKU_BASE)
                cursor.execute("SELECT SKU_REF, SKU_BASE FROM productos")
                productos_por_sku = {str(row[0]).strip().upper(): row[1] for row in cursor.fetchall()}
            finally:
                if conn: conn.close()
            
            count = 0
            for _, row in df.iterrows():
                # El valor en la columna SKU puede ser un SKU real o el Nombre del artículo
                val_raw = str(row[sku_col_name]).strip()
                if not val_raw or val_raw.lower() == 'nan' or val_raw.startswith('---'):
                    continue
                
                sku_base = None
                
                # 1. Intentar coincidencia exacta de SKU
                sku_upper = val_raw.upper()
                sku_base = productos_por_sku.get(sku_upper)
                
                # 2. Intentar coincidencia de SKU sin guiones/espacios
                if not sku_base:
                    sku_clean = sku_upper.replace(' ', '').replace('-', '').replace('_', '')
                    # Necesitaríamos un mapa de SKUs limpios para esto ser eficiente, 
                    # pero probaremos con los más comunes o re-escaneando si es necesario.
                    # Por ahora probamos coincidencia directa del valor limpio.
                    for s_ref, s_base in productos_por_sku.items():
                        if s_ref.replace(' ', '').replace('-', '').replace('_', '') == sku_clean:
                            sku_base = s_base
                            break

                # 3. Intentar coincidencia por Nombre (usando el valor de la columna SKU como nombre)
                if not sku_base:
                    nombre_busqueda = val_raw.lower()
                    sku_base = productos_por_nombre.get(nombre_busqueda)
                
                # 4. Intentar con la columna 'articulo' si existe (por si acaso)
                if not sku_base and 'articulo' in df.columns:
                    art_name = str(row['articulo']).lower().strip()
                    sku_base = productos_por_nombre.get(art_name)

                if not sku_base:
                    # Si no hay coincidencia, saltar fila
                    continue
                
                datos = {'SKU_BASE': sku_base}
                
                # Extraer estados y precios
                for excel_col, (db_status_col, excel_price_col) in mapeo.items():
                    # Estado (la columna coincide con la clave del mapeo)
                    if excel_col in df.columns:
                        status_val = row[excel_col]
                        # Si es booleano en el Excel, convertir a texto útil
                        if isinstance(status_val, bool):
                            datos[db_status_col] = "Subido" if status_val else "Pendiente"
                        else:
                            datos[db_status_col] = str(status_val) if pd.notna(status_val) else "Pendiente"
                    
                    # Precio
                    if excel_price_col in df.columns:
                        precio = row[excel_price_col]
                        try:
                            datos[db_status_col.replace('_ESTADO', '_PRECIO')] = float(precio) if pd.notna(precio) else 0.0
                        except:
                            datos[db_status_col.replace('_ESTADO', '_PRECIO')] = 0.0
                
                if self.guardar_datos_ventas(datos):
                    count += 1
            
            return count
        except Exception as e:
            print(f"Error crítico importando Excel de ventas: {e}")
            import traceback
            traceback.print_exc()
            return 0

# ========================================
# PESTAÑA DE INTERFAZ (TKINTER)
# ========================================

# ========================================
# UI: BUSCADOR DE VENTAS (ESTILO 1.2)
# ========================================

class TabBuscadorVentas(tk.Frame):
    def __init__(self, parent, gestor_productos, on_select_callback):
        super().__init__(parent, bg=COLOR_FONDO)
        self.gestor = gestor_productos
        self.on_select_callback = on_select_callback
        self.img_refs = []
        self.productos_filtrados = []
        
        # Importar PanelFiltros aquí si es necesario o recibirlo
        from modulo4_fichas import PanelFiltros
        
        # Panel lateral de filtros
        self.panel_filtros = PanelFiltros(self, self.ejecutar_busqueda)
        
        # Área derecha (Resultados)
        derecha = tk.Frame(self, bg=COLOR_FONDO)
        derecha.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        # Barra superior de búsqueda rápida
        top = tk.Frame(derecha, bg="white", pady=10, padx=20)
        top.pack(fill="x")
        
        tk.Label(top, text="🔍 Buscar Artículo:", bg="white", font=('Segoe UI', 11, 'bold')).pack(side="left")
        self.ent_busqueda = tk.Entry(top, font=("Segoe UI", 11), width=30)
        self.ent_busqueda.pack(side="left", padx=10)
        self.ent_busqueda.bind("<Return>", lambda e: self.ejecutar_busqueda_rapida())
        
        tk.Button(top, text="BUSCAR", command=self.ejecutar_busqueda_rapida, bg=COLOR_MORADO, fg="white", font=("Segoe UI", 10, "bold"), padx=15).pack(side="left")
        
        # Área scrollable para la cuadrícula
        self.canvas = tk.Canvas(derecha, bg="#f8fafc", highlightthickness=0)
        self.scroll = ttk.Scrollbar(derecha, orient="vertical", command=self.canvas.yview)
        self.grid_frame = tk.Frame(self.canvas, bg="#f8fafc")
        
        self.grid_frame.bind("<Configure>", lambda e: self.canvas.configure(scrollregion=self.canvas.bbox("all")))
        self.canvas.create_window((0,0), window=self.grid_frame, anchor="nw")
        self.canvas.configure(yscrollcommand=self.scroll.set)
        
        self.canvas.pack(side="left", fill="both", expand=True)
        self.scroll.pack(side="right", fill="y")
        
        # Cargar datos iniciales
        self.ejecutar_busqueda_rapida()

    def ejecutar_busqueda_rapida(self):
        txt = self.ent_busqueda.get().strip().upper()
        # Solo mostrar artículos BASE en ventas
        productos_base = [p for p in self.gestor.productos if p.get('ES_VARIANTE', 'BASE') == 'BASE']
        
        if not txt:
            self.productos_filtrados = productos_base
        else:
            self.productos_filtrados = [p for p in productos_base if 
                                       txt in str(p.get('SKU_REF', '')).upper() or 
                                       txt in str(p.get('NOMBRE', '')).upper()]
        self.renderizar()

    def ejecutar_busqueda(self, filtros, tipo=None):
        # Filtrar solo base
        filtros['es_variante'] = 'BASE'
        self.productos_filtrados = self.gestor.buscar_productos(filtros)
        self.renderizar()

    def renderizar(self):
        for w in self.grid_frame.winfo_children(): w.destroy()
        self.img_refs = []
        
        if not self.productos_filtrados:
            tk.Label(self.grid_frame, text="No se encontraron artículos.", bg="#f8fafc", font=("Segoe UI", 12)).pack(pady=50, padx=50)
            return

        from PIL import Image, ImageTk
        cols = 4
        # Dinámico según ancho
        self.update_idletasks()
        ancho = self.canvas.winfo_width()
        if ancho > 1200: cols = 5
        elif ancho < 800: cols = 3

        for i, p in enumerate(self.productos_filtrados):
            f = tk.Frame(self.grid_frame, bg="white", padx=10, pady=10, highlightthickness=1, highlightbackground="#cbd5e1", cursor="hand2")
            f.grid(row=i//cols, column=i%cols, padx=10, pady=10)
            
            # Hacer que todo el frame sea clicable
            f.bind("<Button-1>", lambda e, prod=p: self.on_select_callback(prod))
            
            sku = p.get('SKU_REF', '')
            tk.Label(f, text=sku, font=("Segoe UI", 10, "bold"), bg="white", wraplength=180).pack()
            
            try:
                foto = p.get('FOTO_PORTADA', '')
                if foto and os.path.exists(foto):
                    img = Image.open(foto)
                    img.thumbnail((180, 160))
                    ph = ImageTk.PhotoImage(img)
                    self.img_refs.append(ph)
                    lbl_img = tk.Label(f, image=ph, bg="white")
                    lbl_img.pack(pady=5)
                    lbl_img.bind("<Button-1>", lambda e, prod=p: self.on_select_callback(prod))
                else:
                    tk.Label(f, text="[Sin Imagen]", bg="white", fg="#94a3b8").pack(pady=30)
            except:
                tk.Label(f, text="[Error Foto]", bg="white", fg="red").pack(pady=30)
            
            lbl_nom = tk.Label(f, text=p.get('NOMBRE',''), font=("Segoe UI", 9), bg="white", fg="#475569", wraplength=180, height=2)
            lbl_nom.pack()
            lbl_nom.bind("<Button-1>", lambda e, prod=p: self.on_select_callback(prod))

# ========================================
# PESTAÑA DE INTERFAZ (TKINTER)
# ========================================

class TabVentas:
    def __init__(self, notebook, gestor_productos, app_main):
        self.notebook = notebook
        self.gestor_productos = gestor_productos
        self.app_main = app_main
        self.gestor_ventas = GestorVentas()
        
        self.frame = tk.Frame(self.notebook, bg=COLOR_FONDO)
        self._crear_interfaz()
        
    def _crear_interfaz(self):
        # Panel superior de herramientas
        toolbar = tk.Frame(self.frame, bg=COLOR_FONDO)
        toolbar.pack(fill=tk.X, padx=10, pady=5)
        
        tk.Label(toolbar, text="💰 Gestión de Ventas Multiplataforma", font=('Segoe UI', 14, 'bold'), bg=COLOR_FONDO).pack(side=tk.LEFT, padx=5)
        
        BotonConFeedback(toolbar, "Importar Excel", self.importar_excel, COLOR_MORADO, "📥").pack(side=tk.RIGHT, padx=5)
        BotonConFeedback(toolbar, "Actualizar", self.cargar_datos, COLOR_VERDE, "🔄").pack(side=tk.RIGHT, padx=5)
        
        # Notebook interno para sub-pestañas
        self.sub_notebook = ttk.Notebook(self.frame)
        self.sub_notebook.pack(fill=tk.BOTH, expand=True, padx=10, pady=5)
        
        # Sub-pestaña 1: Buscador Visual (NUEVO - Estilo 1.2)
        self.tab_buscador = TabBuscadorVentas(self.sub_notebook, self.gestor_productos, self.on_art_selected_visual)
        self.sub_notebook.add(self.tab_buscador, text=" 🔍 Buscador Visual ")

        # Sub-pestaña 2: Vista General (Tabla)
        self.tab_tabla = tk.Frame(self.sub_notebook, bg=COLOR_FONDO)
        self.sub_notebook.add(self.tab_tabla, text=" 📊 Vista Tabla ")
        self._crear_tabla_general(self.tab_tabla)
        
        # Sub-pestaña 3: Gestión Detallada
        self.tab_detalle = tk.Frame(self.sub_notebook, bg=COLOR_FONDO)
        self.sub_notebook.add(self.tab_detalle, text=" 📝 Detalle por Artículo ")
        self._crear_detalle(self.tab_detalle)
        
        # Cargar datos iniciales
        self.cargar_datos()

    def _crear_tabla_general(self, parent):
        # Filtro rápido
        filtro_frame = tk.Frame(parent, bg=COLOR_FONDO)
        filtro_frame.pack(fill=tk.X, padx=5, pady=5)
        
        tk.Label(filtro_frame, text="🔍 Buscar:", bg=COLOR_FONDO, font=('Segoe UI', 10)).pack(side=tk.LEFT, padx=5)
        self.entry_busqueda = ttk.Entry(filtro_frame, font=('Segoe UI', 10))
        self.entry_busqueda.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=5)
        self.entry_busqueda.bind('<KeyRelease>', lambda e: self.filtrar_tabla())
        
        # Tabla Treeview
        columns = ['SKU', 'Nombre', 'Stock Online', 'Stock Físico'] + [p['nombre_visible'] for p in self.gestor_ventas.columnas_plataformas]
        self.tree = ttk.Treeview(parent, columns=columns, show='headings')
        
        # Configurar cabeceras y anchos
        anchos = {'SKU': 100, 'Nombre': 200, 'Stock Online': 80, 'Stock Físico': 90}
        for col in columns:
            self.tree.heading(col, text=col.replace('_', ' ').title())
            self.tree.column(col, width=anchos.get(col, 80), anchor='center')

        
        self.tree.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Scrollbar
        scrolly = ttk.Scrollbar(self.tree, orient=tk.VERTICAL, command=self.tree.yview)
        self.tree.configure(yscrollcommand=scrolly.set)
        scrolly.pack(side=tk.RIGHT, fill=tk.Y)
        
        # Tags para filas alternas
        self.tree.tag_configure('par', background='#e0f2fe')
        self.tree.tag_configure('impar', background='#fdf2f8')
        
        self.tree.bind('<<TreeviewSelect>>', self.on_item_selected)

    def on_art_selected_visual(self, producto):
        """Callback cuando se selecciona un artículo en el buscador visual"""
        sku = producto.get('SKU_REF')
        # Buscar el item en el treeview para sincronizar (opcional pero ayuda)
        for item in self.tree.get_children():
            if self.tree.item(item)['values'][0] == sku:
                self.tree.selection_set(item)
                self.tree.see(item)
                break
        
        # Cargar detalles y cambiar de pestaña
        self.cargar_detalle_articulo(producto)
        self.sub_notebook.select(2) # Cambiar a pestaña Detalle

    def cargar_detalle_articulo(self, prod_full):
        if not prod_full: return
        
        sku = prod_full.get('SKU_REF')
        nombre = prod_full.get('NOMBRE')
        
        self.sku_detalle = sku
        self.lbl_art_nombre.config(text=f"{nombre}")
        self.lbl_sku.config(text=f"SKU: {sku}")
        
        # Mostrar Info (CASTING SEGURO)
        try:
            p_raw = str(prod_full.get('PRECIO', 0))
            precio_val = float(p_raw.replace('€', '').replace(',', '.').strip()) if p_raw else 0.0
            self.lbl_precio_base.config(text=f"Precio Catálogo: {precio_val:.2f}€")
        except:
            self.lbl_precio_base.config(text=f"Precio Catálogo: {prod_full.get('PRECIO', '0')}€")
            
        try:
            stock_online = int(prod_full.get('STOCK', 0))
            self.lbl_stock_online.config(text=f"Stock Online (Total): {stock_online}")
        except:
            self.lbl_stock_online.config(text=f"Stock Online (Total): {prod_full.get('STOCK', '0')}")

        try:
            stock_fisico = int(prod_full.get('STOCK_FISICO', 0))
            self.lbl_stock_fisico.config(text=f"Stock Físico (Terminados): {stock_fisico}")
            self.spin_fisico.set(stock_fisico)
        except:
            self.lbl_stock_fisico.config(text=f"Stock Físico (Terminados): {prod_full.get('STOCK_FISICO', '0')}")
            self.spin_fisico.set(0)

        # Fabricable (Cálculo rápido si hay receta)
        sku = prod_full.get('SKU_REF')
        from modulo12_stock import GestorMateriales
        gm = GestorMateriales()
        desp = gm.obtener_despiece(sku)
        if desp:
            mats = gm.obtener_materiales()
            limitantes = []
            for d in desp:
                m = next((mat for mat in mats if mat['REF_MAT'] == d['REF_MAT']), None)
                if m: limitantes.append(m['STOCK_ACTUAL'] // d['CANTIDAD'] if d['CANTIDAD'] > 0 else 9999)
            fab = int(min(limitantes)) if limitantes else 0
            self.lbl_fabricable.config(text=f"Fabricable (Potencial): {fab}", fg="#8b5cf6")
        else:
            self.lbl_fabricable.config(text="Fabricable (Potencial): Sin receta", fg="#64748b")
        
        # Mostrar imagen
        ruta_foto = prod_full.get('FOTO_PORTADA')
        if ruta_foto and os.path.exists(ruta_foto):
            self.preview_img.mostrar_imagen(ruta_foto)
        else:
            self.preview_img.mostrar_placeholder()
            
        v = self.ventas_map.get(sku, {})
        self.spin_unidades.set(v.get('UNIDADES_VENTA', 0))
        
        for plat, (var_est, var_pre, var_url) in self.plats_widgets.items():
            var_est.set(v.get(f"{plat}_ESTADO", "Pendiente"))
            var_pre.set(str(v.get(f"{plat}_PRECIO", 0.0)))
            var_url.set(str(v.get(f"{plat}_URL", "")))

    def _crear_detalle(self, parent):
        # Splitter o frames
        self.detalle_main = tk.Frame(parent, bg=COLOR_FONDO)
        self.detalle_main.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)
        
        # Izquierda: Info básica mejorada
        izq = ttk.LabelFrame(self.detalle_main, text=" Información del Artículo ", width=400)
        izq.pack(side=tk.LEFT, fill=tk.BOTH, expand=False, padx=10)
        izq.pack_propagate(False)
        
        # Imagen del artículo
        self.preview_img = PreviewImagenSeleccionada(izq)
        self.preview_img.frame.pack(pady=10, fill=tk.X)
        self.preview_img.mostrar_placeholder()
        
        self.lbl_art_nombre = tk.Label(izq, text="Seleccione un artículo", font=('Segoe UI', 14, 'bold'), wraplength=350, fg=COLOR_MORADO)
        self.lbl_art_nombre.pack(pady=10)

        # Precio y Stock
        frame_info = tk.Frame(izq, bg=None)
        frame_info.pack(pady=5, fill=tk.X, padx=20)
        
        self.lbl_sku = tk.Label(frame_info, text="SKU: -", font=('Segoe UI', 10))
        self.lbl_sku.pack(anchor='w')
        
        self.lbl_precio_base = tk.Label(frame_info, text="Precio Catálogo: 0.00€", font=('Segoe UI', 11, 'bold'), fg="#2563eb")
        self.lbl_precio_base.pack(anchor='w', pady=2)
        
        self.lbl_stock_online = tk.Label(frame_info, text="Stock Online: 0", font=('Segoe UI', 10, 'bold'), fg="#2563eb")
        self.lbl_stock_online.pack(anchor='w')

        self.lbl_stock_fisico = tk.Label(frame_info, text="Stock Físico: 0", font=('Segoe UI', 10, 'bold'), fg=COLOR_VERDE)
        self.lbl_stock_fisico.pack(anchor='w')

        self.lbl_fabricable = tk.Label(frame_info, text="Fabricable: -", font=('Segoe UI', 10, 'italic'), fg="#64748b")
        self.lbl_fabricable.pack(anchor='w')

        tk.Label(izq, text="📈 UNIDADES EN PLATAFORMAS (Stock Online):", font=('Segoe UI', 10, 'bold'), fg="#2563eb").pack(pady=(15, 0))
        self.spin_unidades = ttk.Spinbox(izq, from_=0, to=9999, width=15, font=('Segoe UI', 12, 'bold'))
        self.spin_unidades.pack(pady=2)

        tk.Label(izq, text="📦 UNIDADES TERMINADAS (Stock Físico):", font=('Segoe UI', 10, 'bold'), fg=COLOR_VERDE).pack(pady=(10, 0))
        self.spin_fisico = ttk.Spinbox(izq, from_=0, to=9999, width=15, font=('Segoe UI', 12, 'bold'))
        self.spin_fisico.pack(pady=2)
        
        BotonConFeedback(izq, "Guardar Unidades", self.guardar_unidades, COLOR_VERDE, "💾").pack(pady=20, fill=tk.X, padx=50)
        
        # Derecha: Grid de plataformas
        der = ttk.LabelFrame(self.detalle_main, text=" Estado en Plataformas ")
        der.pack(side=tk.RIGHT, fill=tk.BOTH, expand=True, padx=10)
        
        self.canvas_plats = tk.Canvas(der, bg=COLOR_FONDO, highlightthickness=0)
        self.scroll_plats = ttk.Scrollbar(der, orient=tk.VERTICAL, command=self.canvas_plats.yview)
        self.frame_plats = tk.Frame(self.canvas_plats, bg=COLOR_FONDO)
        
        self.canvas_plats.create_window((0,0), window=self.frame_plats, anchor='nw')
        self.canvas_plats.configure(yscrollcommand=self.scroll_plats.set)
        
        self.canvas_plats.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        self.scroll_plats.pack(side=tk.RIGHT, fill=tk.Y)
        
        self.plats_widgets = {} # Para guardar variables y entries
        
        for i, plat_info in enumerate(self.gestor_ventas.columnas_plataformas):
            plat = plat_info['nombre_columna']
            nombre = plat_info['nombre_visible']
            
            # Colores pastel alternos para los "cards"
            bg_card = "#fdf2f8" if i % 2 == 0 else "#f0f9ff"
            f = tk.Frame(self.frame_plats, bg=bg_card, pady=10, bd=1, relief=tk.RIDGE)
            f.pack(fill=tk.X, padx=10, pady=5)
            
            tk.Label(f, text=nombre, width=18, anchor='w', bg=bg_card, font=('Segoe UI', 11, 'bold')).pack(side=tk.LEFT, padx=10)
            
            var_estado = tk.StringVar(value="Pendiente")
            cb = ttk.Combobox(f, textvariable=var_estado, values=["Pendiente", "Subido", "Vendido", "Oculto"], width=12, font=('Segoe UI', 10))
            cb.pack(side=tk.LEFT, padx=5)
            
            tk.Label(f, text="Precio €:", bg=bg_card, font=('Segoe UI', 10)).pack(side=tk.LEFT, padx=5)
            var_precio = tk.StringVar(value="0.0")
            ent_precio = ttk.Entry(f, textvariable=var_precio, width=10, font=('Segoe UI', 11, 'bold'))
            ent_precio.pack(side=tk.LEFT, padx=5)
            
            tk.Label(f, text="URL:", bg=bg_card, font=('Segoe UI', 10)).pack(side=tk.LEFT, padx=5)
            var_url = tk.StringVar(value="")
            ent_url = ttk.Entry(f, textvariable=var_url, font=('Segoe UI', 9))
            ent_url.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=5)
            
            self.plats_widgets[plat] = (var_estado, var_precio, var_url)
            
        btn_save_all = BotonConFeedback(der, "Guardar Todos los Estados", self.guardar_todo, COLOR_MORADO, "💾")
        btn_save_all.pack(pady=15, fill=tk.X, padx=100)

    def cargar_datos(self):
        self.productos_base = self.gestor_productos.filtrar(es_variante='BASE')
        ventas_data = self.gestor_ventas.obtener_datos_ventas()
        self.ventas_map = {v['SKU_BASE']: v for v in ventas_data}
        
        self.actualizar_tabla()

    def actualizar_tabla(self):
        for item in self.tree.get_children():
            self.tree.delete(item)
            
        for i, p in enumerate(self.productos_base):
            sku = p['SKU_REF']
            v_info = self.ventas_map.get(sku, {})
            
            valores = [
                sku, 
                p['NOMBRE'], 
                p.get('STOCK', 0),
                p.get('STOCK_FISICO', 0)
            ]
            
            for plat in self.gestor_ventas.columnas_plataformas:
                p_id = plat['nombre_columna']
                estado = v_info.get(f"{p_id}_ESTADO", "-")
                precio = v_info.get(f"{p_id}_PRECIO", "-")
                valores.append(f"{estado} ({precio}€)" if estado != "-" else "-")
            
            tag = 'par' if i % 2 == 0 else 'impar' # Changed from 'odd'/'even' to 'par'/'impar' to match existing tags
            self.tree.insert('', tk.END, values=valores, tags=(tag,))


    def on_item_selected(self, event):
        selection = self.tree.selection()
        if not selection:
            return
            
        item_data = self.tree.item(selection[0])
        sku = item_data['values'][0]
        
        prod_full = next((p for p in self.productos_base if p['SKU_REF'] == sku), None)
        self.cargar_detalle_articulo(prod_full)

    def guardar_unidades(self):
        if not hasattr(self, 'sku_detalle'):
            return
            
        unidades = self.spin_unidades.get()
        fisico = self.spin_fisico.get()
        if self.gestor_ventas.guardar_datos_ventas({
            'SKU_BASE': self.sku_detalle, 
            'UNIDADES_VENTA': unidades,
            'STOCK': unidades,        # Mapeamos ONLINE general a STOCK
            'STOCK_FISICO': fisico
        }):
            messagebox.showinfo("Éxito", "Unidades actualizadas")
            self.cargar_datos()
        else:
            messagebox.showerror("Error", "No se pudo guardar")

    def guardar_todo(self):
        if not hasattr(self, 'sku_detalle'):
            return
            
        datos = {'SKU_BASE': self.sku_detalle}
        datos['UNIDADES_VENTA'] = float(self.spin_unidades.get())
        
        for plat, (var_est, var_pre, var_url) in self.plats_widgets.items():
            datos[f"{plat}_ESTADO"] = var_est.get()
            datos[f"{plat}_URL"] = var_url.get()
            try:
                datos[f"{plat}_PRECIO"] = float(var_pre.get())
            except:
                datos[f"{plat}_PRECIO"] = 0.0
                
        if self.gestor_ventas.guardar_datos_ventas(datos):
            messagebox.showinfo("Éxito", "Datos de plataformas actualizados")
            self.cargar_datos()
        else:
            messagebox.showerror("Error", "No se pudo guardar")

    def filtrar_tabla(self):
        busqueda = self.entry_busqueda.get().lower().strip()
        filtered = [p for p in self.productos_base if busqueda in p.get('NOMBRE', '').lower() or busqueda in p.get('SKU_REF', '').lower()]
        
        # Limpiar tabla
        for item in self.tree.get_children():
            self.tree.delete(item)
            
        # Insertar filtrados
        for i, p in enumerate(filtered):
            sku = p.get('SKU_REF')
            v = self.ventas_map.get(sku, {})
            valores = [sku, p.get('NOMBRE'), v.get('UNIDADES_VENTA', 0)]
            for plat_info in self.gestor_ventas.columnas_plataformas:
                plat = plat_info['nombre_columna']
                valores.append(v.get(f"{plat}_ESTADO", "Pendiente"))
            
            tag = 'par' if i % 2 == 0 else 'impar'
            self.tree.insert('', tk.END, values=valores, tags=(tag,))

    def importar_excel(self):
        excel_path = "listado de creaciones noxertez.xlsx"
        if not os.path.exists(excel_path):
            messagebox.showerror("Error", f"No se encontró el archivo {excel_path}")
            return
            
        count = self.gestor_ventas.importar_desde_excel(excel_path)
        if count > 0:
            messagebox.showinfo("Importación", f"Se importaron/actualizaron datos de {count} artículos base.")
            self.cargar_datos()
        else:
            messagebox.showwarning("Aviso", "No se importaron datos. Asegúrese de que los nombres de los artículos coincidan.")

