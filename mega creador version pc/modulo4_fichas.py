"""
MÓDULO 4: FILTROS, VISUALIZACIÓN Y FICHAS
- Panel de filtros completo
- Visualización de productos
- Generación de fichas PNG
- Modificación de fichas
- Vista previa en tiempo real
"""

import os
import json
from PIL import Image, ImageDraw, ImageFont, ImageTk
import tkinter as tk
from tkinter import ttk

# ========================================
# CONFIGURACIÓN GENERAL Y PERSISTENCIA
# ========================================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_FILE = os.path.join(BASE_DIR, "config_app.json")

# Valores por defecto
API_KEYS = []
MODELO_IA = "gemini-1.5-flash"
GROQ_KEYS = []
GROQ_MODELO = "llama-3.1-70b-versatile"
IA_PREFERIDA = "Gemini"
ESTILO_IA_ACTUAL = "Neutral"
ULTIMO_NUMERO_SKU = 0

ESTILOS_IA = {
    "General": {
        "Neutral": "Descripción técnica y objetiva.",
        "Creativo": "Descripción artística y emocional con adjetivos sugerentes.",
        "Comercial": "Enfocado en beneficios de venta y exclusividad artesanía."
    },
    "Artesanía": {
        "Rústico": "Resalta la madera, el trabajo manual y la calidez.",
        "Místico": "Resalta el alma, la energía y el propósito espiritual del objeto."
    }
}

# ========================================
# CONFIGURACIÓN DE MARCAS
# ========================================

MARCAS = {
    "NOXERTEZ": {
        "nombre": "NOXERTEZ",
        "slogan": "EL ARTE CON MADERA PERFECTAMENTE IMPERFECTO",
        "color_primario": "#581c87",
        "color_secundario": "#c5a059"
    },
    "CANDLE HOLDER OF THE SOUL": {
        "nombre": "CANDLE HOLDER OF THE SOUL",
        "slogan": "CANDLE HOLDER & WARMTH FOR YOUR HOME",
        "color_primario": "#78350f",
        "color_secundario": "#d6d3d1"
    },
    "THE SECRET ZEN GARDEN": {
        "nombre": "THE SECRET ZEN GARDEN",
        "slogan": "NATURE'S BALANCE IN ANCIENT WOOD STONES",
        "color_primario": "#064e3b",
        "color_secundario": "#a3e635"
    }
}

CATEGORIAS = {
    "PORTAVELAS": ["ABSTRACTO", "TRONCO", "ZIGZAG", "ALFAJIA", "OLIVO", "PIEDRA"],
    "INCIENSO": ["REFLUJO", "CURVO", "RECTANGULAR"],
    "CUADRO": ["3D", "CORAZON", "INTERIOR", "ESTRELLA", "NAUTICO"],
    "CORAZON": ["INTERIOR", "COLOR", "PINO", "LED"]
}

FESTIVIDADES = ["Sin festividad", "San Valentín", "Navidad", "Día de la Madre", 
                "Día del Padre", "Halloween", "Pascua"]

COLORES = ["Natural", "Nogal", "Roble", "Pino", "Wengué", "Blanco", "Negro"]

RUTA_FICHAS = "fichas"
os.makedirs(RUTA_FICHAS, exist_ok=True)

def cargar_configuracion():
    global API_KEYS, MODELO_IA, GROQ_KEYS, GROQ_MODELO, IA_PREFERIDA, ESTILO_IA_ACTUAL, ULTIMO_NUMERO_SKU, CATEGORIAS, MARCAS, FESTIVIDADES, COLORES
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
                data = json.load(f)
                API_KEYS = data.get("API_KEYS", API_KEYS)
                MODELO_IA = data.get("MODELO_IA", MODELO_IA)
                GROQ_KEYS = data.get("GROQ_KEYS", GROQ_KEYS)
                GROQ_MODELO = data.get("GROQ_MODELO", GROQ_MODELO)
                IA_PREFERIDA = data.get("IA_PREFERIDA", IA_PREFERIDA)
                ESTILO_IA_ACTUAL = data.get("ESTILO_IA_ACTUAL", ESTILO_IA_ACTUAL)
                ULTIMO_NUMERO_SKU = data.get("ULTIMO_NUMERO_SKU", ULTIMO_NUMERO_SKU)
                if "CATEGORIAS" in data: CATEGORIAS = data["CATEGORIAS"]
                if "MARCAS" in data: MARCAS = data["MARCAS"]
                if "FESTIVIDADES" in data: FESTIVIDADES = data["FESTIVIDADES"]
                if "COLORES" in data: COLORES = data["COLORES"]
        except Exception as e:
            print(f"Error cargando config: {e}")

def guardar_configuracion():
    try:
        data = {
            "API_KEYS": API_KEYS,
            "MODELO_IA": MODELO_IA,
            "GROQ_KEYS": GROQ_KEYS,
            "GROQ_MODELO": GROQ_MODELO,
            "IA_PREFERIDA": IA_PREFERIDA,
            "ESTILO_IA_ACTUAL": ESTILO_IA_ACTUAL,
            "ULTIMO_NUMERO_SKU": ULTIMO_NUMERO_SKU,
            "CATEGORIAS": CATEGORIAS,
            "MARCAS": MARCAS,
            "FESTIVIDADES": FESTIVIDADES,
            "COLORES": COLORES
        }
        with open(CONFIG_FILE, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=4)
        return True
    except Exception as e:
        print(f"Error guardando config: {e}")
        return False

# Cargar al importar
cargar_configuracion()

# ========================================
# PANEL DE FILTROS
# ========================================

class PanelFiltros:
    """Panel de filtros completo con buscador"""
    
    def __init__(self, parent, on_filtrar_callback):
        self.on_filtrar = on_filtrar_callback
        
        # Frame principal
        self.frame = ttk.LabelFrame(parent, text="🔍 Filtros y Búsqueda")
        self.frame.pack(side=tk.LEFT, fill=tk.BOTH, padx=5, pady=5)
        
        contenido = tk.Frame(self.frame, bg='#f8f9fa')
        contenido.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        # Categoría
        ttk.Label(contenido, text="Categoría:", 
                 font=('Segoe UI', 12, 'bold')).pack(anchor=tk.W, pady=(5, 0))
        self.combo_categoria = ttk.Combobox(contenido, 
                                           font=('Segoe UI', 11),
                                           state="readonly")
        self.combo_categoria['values'] = [''] + list(CATEGORIAS.keys())
        self.combo_categoria.pack(fill=tk.X, pady=5)
        self.combo_categoria.bind('<<ComboboxSelected>>', self.actualizar_subcategorias)
        
        # Subcategoría
        ttk.Label(contenido, text="Subcategoría:", 
                 font=('Segoe UI', 12, 'bold')).pack(anchor=tk.W, pady=(10, 0))
        self.combo_subcategoria = ttk.Combobox(contenido, 
                                              font=('Segoe UI', 11),
                                              state="readonly")
        self.combo_subcategoria.pack(fill=tk.X, pady=5)
        
        # Marca
        ttk.Label(contenido, text="Marca:", 
                 font=('Segoe UI', 12, 'bold')).pack(anchor=tk.W, pady=(10, 0))
        self.combo_marca = ttk.Combobox(contenido, 
                                       font=('Segoe UI', 11),
                                       state="readonly")
        self.combo_marca['values'] = [''] + list(MARCAS.keys())
        self.combo_marca.pack(fill=tk.X, pady=5)
        
        # Color
        ttk.Label(contenido, text="Color:", 
                 font=('Segoe UI', 12, 'bold')).pack(anchor=tk.W, pady=(10, 0))
        self.entry_color = ttk.Entry(contenido, font=('Segoe UI', 11))
        self.entry_color.pack(fill=tk.X, pady=5)
        
        # Festividad
        ttk.Label(contenido, text="Festividad:", 
                 font=('Segoe UI', 12, 'bold')).pack(anchor=tk.W, pady=(10, 0))
        self.combo_festividad = ttk.Combobox(contenido, 
                                            font=('Segoe UI', 11),
                                            state="readonly")
        self.combo_festividad['values'] = [''] + FESTIVIDADES
        self.combo_festividad.pack(fill=tk.X, pady=5)
        
        # Estado
        ttk.Label(contenido, text="Estado:", 
                 font=('Segoe UI', 12, 'bold')).pack(anchor=tk.W, pady=(10, 0))
        self.combo_estado = ttk.Combobox(contenido, 
                                        font=('Segoe UI', 11),
                                        state="readonly")
        self.combo_estado['values'] = ['', 'STOCK', 'AGOTADO', 'RESERVADO']
        self.combo_estado.pack(fill=tk.X, pady=5)
        
        # Botones
        botones = tk.Frame(contenido, bg='#f8f9fa')
        botones.pack(fill=tk.X, pady=20)
        
        ttk.Button(botones, text="🔍 Aplicar Filtro",
                  command=self.aplicar_filtro).pack(fill=tk.X, pady=3)
        ttk.Button(botones, text="🔄 Limpiar",
                  command=self.limpiar).pack(fill=tk.X, pady=3)
        
        # Búsqueda por SKU
        ttk.Label(contenido, text="Buscar por SKU:", 
                 font=('Segoe UI', 12, 'bold')).pack(anchor=tk.W, pady=(20, 0))
        self.entry_sku = ttk.Entry(contenido, font=('Segoe UI', 11))
        self.entry_sku.pack(fill=tk.X, pady=5)
        ttk.Button(contenido, text="🔎 Buscar SKU",
                  command=self.buscar_sku).pack(fill=tk.X, pady=5)
        
        # Contador de resultados
        self.label_resultados = ttk.Label(contenido,
                                         text="Resultados: 0",
                                         font=('Segoe UI', 14, 'bold'),
                                         foreground='#581c87')
        self.label_resultados.pack(pady=20)
    
    def actualizar_subcategorias(self, event=None):
        """Actualiza subcategorías según categoría"""
        categoria = self.combo_categoria.get()
        if categoria and categoria in CATEGORIAS:
            self.combo_subcategoria['values'] = [''] + CATEGORIAS[categoria]
        else:
            self.combo_subcategoria['values'] = []
        self.combo_subcategoria.set('')
    
    def aplicar_filtro(self):
        """Aplica los filtros"""
        filtros = {
            'categoria': self.combo_categoria.get() or None,
            'subcategoria': self.combo_subcategoria.get() or None,
            'marca': self.combo_marca.get() or None,
            'color': self.entry_color.get() or None,
            'festividad': self.combo_festividad.get() or None,
            'estado': self.combo_estado.get() or None
        }
        self.on_filtrar(filtros, 'filtro')
    
    def buscar_sku(self):
        """Busca por SKU"""
        sku = self.entry_sku.get()
        if sku:
            self.on_filtrar({'sku': sku}, 'sku')
    
    def limpiar(self):
        """Limpia todos los filtros"""
        self.combo_categoria.set('')
        self.combo_subcategoria.set('')
        self.combo_marca.set('')
        self.entry_color.delete(0, tk.END)
        self.combo_festividad.set('')
        self.combo_estado.set('')
        self.entry_sku.delete(0, tk.END)
        self.on_filtrar({}, 'limpiar')
    
    def actualizar_contador(self, cantidad):
        """Actualiza el contador de resultados"""
        self.label_resultados.config(text=f"Resultados: {cantidad}")

# ========================================
# VISOR DE PRODUCTOS
# ========================================

class VisorProductos:
    """Visor de productos con lista y preview de ficha"""
    
    def __init__(self, parent, on_seleccionar_callback):
        self.on_seleccionar = on_seleccionar_callback
        self.productos = []
        
        # Frame principal
        self.frame = ttk.LabelFrame(parent, text="📋 Productos")
        self.frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Lista de productos
        lista_frame = tk.Frame(self.frame)
        lista_frame.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Scrollbar
        scrollbar = ttk.Scrollbar(lista_frame)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        
        # Listbox
        self.listbox = tk.Listbox(lista_frame,
                                  font=('Consolas', 11),
                                  yscrollcommand=scrollbar.set,
                                  selectmode=tk.SINGLE)
        self.listbox.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar.config(command=self.listbox.yview)
        
        # Bind selección
        self.listbox.bind('<<ListboxSelect>>', self.on_select)
        
        # Doble clic para ver completo
        self.listbox.bind('<Double-Button-1>', self.ver_completo)
    
    def cargar_productos(self, productos):
        """Carga lista de productos"""
        self.productos = productos
        self.listbox.delete(0, tk.END)
        
        for prod in productos:
            sku = prod.get('SKU_REF', '')
            nombre = prod.get('NOMBRE', '')
            precio = prod.get('PRECIO', '')
            
            # Formato: SKU | Nombre | Precio
            linea = f"{sku:<20} | {nombre:<40} | {precio}€"
            self.listbox.insert(tk.END, linea)
    
    def on_select(self, event=None):
        """Cuando se selecciona un producto"""
        sel = self.listbox.curselection()
        if sel:
            idx = sel[0]
            producto = self.productos[idx]
            self.on_seleccionar(producto)
    
    def ver_completo(self, event=None):
        """Ver producto completo (doble clic)"""
        self.on_select()

