"""
CATÁLOGO v3.5 - GESTIÓN INTEGRADA
"""

import tkinter as tk
from tkinter import ttk, filedialog, messagebox
import os
import threading

import warnings
try:
    from urllib3.exceptions import DependencyWarning
    warnings.filterwarnings("ignore", category=DependencyWarning)
except:
    pass

# Importar todos los módulos
try:
    from modulo1_nucleo import analizar_imagen_ia, cargar_contador
    from modulo2_interfaz import configurar_estilos, BarraEstado, CheckboxMejorado, COLOR_FONDO, COLOR_MORADO, COLOR_MARRON, COLOR_VERDE
    from modulo3_gestion import GestorProductos
    from modulo4_fichas import PanelFiltros, CATEGORIAS, MARCAS
    from modulo5_mejoras_visuales import PreviewImagenSeleccionada, IndicadorProcesoIA, BotonConFeedback, Notificacion
    from modulo6_correcciones import (
        generar_sku_correcto, obtener_siguiente_numero_sku, 
        guardar_imagenes_producto, obtener_variantes, VistaTablaExcel
    )
    from modulo11_whatsapp import compartir_producto_whatsapp
    from modulo12_stock import TabStock
    from modulo13_futuros_proyectos import TabFuturosProyectos, TabBuscadorCreados
    import modulo14_servidor_web as web
    from modulo15_herramientas import TabHerramientas
    from modulo16_ventas import TabVentas
    from modulo17_influencers import TabInfluencers
    from modulo18_clientes import TabClientes
    from modulo19_pedidos import TabPedidos
    from modulo20_envios import TabEnvios
    from modulo21_gestion_pedidos import TabGestionPedidos
    from modulo22_flujo_pedidos import TabFlujoVisual
    try:
        from start_cloudflare import start_tunnel
    except ImportError:
        def start_tunnel(*args, **kwargs): print("⚠️ start_tunnel no disponible")
    
    print("Módulos base cargados correctamente")
except Exception as e:
    print(f"Error cargando módulos base: {e}")
    # Definir stubs para evitar NameError
    globals_dict = globals()
    
    # Colores por defecto si modulo2_interfaz falló
    if 'COLOR_FONDO' not in globals_dict: COLOR_FONDO = "#f0f0f0"
    if 'COLOR_MORADO' not in globals_dict: COLOR_MORADO = "#800080"
    if 'COLOR_MARRON' not in globals_dict: COLOR_MARRON = "#8B4513"
    if 'COLOR_VERDE' not in globals_dict: COLOR_VERDE = "#008000"
    
    # Stub genérico para clases de UI
    class UIStub:
        def __init__(self, *args, **kwargs):
            self.frame = tk.Frame(args[0]) if args else None
            if hasattr(self, 'frame') and self.frame:
                tk.Label(self.frame, text="Módulo no cargado").pack()
        def pack(self, **kwargs): 
            if hasattr(self, 'frame') and self.frame: self.frame.pack(**kwargs)
        def grid(self, **kwargs):
            if hasattr(self, 'frame') and self.frame: self.frame.grid(**kwargs)

    # Clases críticas
    if 'GestorProductos' not in globals_dict:
        class GestorProductos:
            def __init__(self): pass
            def cargar_productos(self): return []
            def buscar_productos(self, *args): return []

    # Registrar stubs para todas las clases que podrían faltar
    stubs_needed = [
        'TabFuturosProyectos', 'TabBuscadorCreados', 'TabStock', 
        'TabHerramientas', 'TabVentas', 'TabInfluencers', 
        'TabClientes', 'TabPedidos', 'TabEnvios', 'TabFlujoVisual',
        'PanelFiltros', 'PreviewImagenSeleccionada', 'IndicadorProcesoIA',
        'VistaTablaExcel', 'Notificacion', 'BotonConFeedback', 'BarraEstado'
    ]
    
    for stub_name in stubs_needed:
        if stub_name not in globals_dict:
            globals_dict[stub_name] = UIStub
            
    if 'configurar_estilos' not in globals_dict:
        def configurar_estilos(): pass
    
    if 'generar_sku_correcto' not in globals_dict:
        def generar_sku_correcto(*args): return "ERROR-SKU"
        
    if 'web' not in globals_dict:
        class web:
            @staticmethod
            def start_server(*args): pass

# Importación opcional de n8n
n8n_mod = None
try:
    import sys, os as _os
    n8n_path = _os.path.join(_os.path.dirname(__file__), 'n8n')
    if _os.path.exists(n8n_path):
        sys.path.insert(0, n8n_path)
        import modulo_n8n as n8n_mod
        print("✅ Módulo n8n cargado")
    else:
        print("⚠️ Carpeta 'n8n' no encontrada, funciones de automatización desactivadas")
except Exception as e:
    print(f"⚠️ Error cargando modulo_n8n (opcional): {e}")

# Stub para n8n_mod si falló
if n8n_mod is None:
    class n8n_mod_stub:
        @staticmethod
        def inicializar_tablas(): pass
        @staticmethod
        def arrancar_n8n_local(): return None
        @staticmethod
        def obtener_notificaciones_nuevas(): return []
    n8n_mod = n8n_mod_stub

# ========================================
# APLICACIÓN COMPLETA
# ========================================

class CatalogoApp:
    
    def __init__(self, root):
        self.root = root
        self.root.title("🎨 Catálogo Artesanal v3.5")
        self.root.geometry("1600x900")
        self.root.configure(bg=COLOR_FONDO)
        
        # Gestor
        self.gestor = GestorProductos()
        self.producto_actual = None
        self.imagenes_seleccionadas = []
        self.cache_variantes = [] # Cache para miniaturas de variantes
        self.bloquear_actualizaciones = False # Bandera para evitar disparos de eventos durante carga
        
        # Configurar estilos
        configurar_estilos()
        
        # Crear interfaz
        self.crear_interfaz()
        
        # Mensaje de bienvenida
        self.root.after(500, lambda: Notificacion.mostrar(
            self.root,
            "✅ v3.5: DB MySQL Conectada | Servidor Web Listo",
            'exito'
        ))
        
        # Iniciar Servidor Web y Túnel Cloudflare en segundo plano
        self.servidor_hilos = []
        self.iniciar_servidor_web()
        self.iniciar_tunel_cloudflare()
        
        # Hilo de monitoreo del servidor
        threading.Thread(target=self.monitorear_servidor, daemon=True).start()
        
        # Inicializar tablas n8n en la base de datos
        try:
            n8n_mod.inicializar_tablas()
            
            # Arrancar n8n local (v1.21.0) en segundo plano
            self.n8n_process = n8n_mod.arrancar_n8n_local()
        except Exception as _e:
            print(f"[n8n] No se pudieron inicializar tablas o motor: {_e}")


    def iniciar_servidor_web(self):
        """Inicia el servidor Flask en un hilo"""
        t = threading.Thread(target=web.iniciar_servidor, daemon=True)
        t.start()
        self.servidor_hilos.append(t)
        if hasattr(self, 'barra'):
            self.barra.agregar_log("🌐 Intentando activar Servidor Web...")

    def iniciar_tunel_cloudflare(self):
        """Inicia el túnel de Cloudflare en un hilo"""
        def launch():
            try:
                if start_tunnel():
                    if hasattr(self, 'barra'):
                        self.barra.agregar_log("✅ Túnel Cloudflare activo.")
                else:
                    if hasattr(self, 'barra'):
                        self.barra.agregar_log("❌ Error iniciando Túnel Cloudflare.")
            except Exception as e:
                print(f"Error en hilo de Cloudflare: {e}")
        
        t = threading.Thread(target=launch, daemon=True)
        t.start()
        self.servidor_hilos.append(t)

    def monitorear_servidor(self):
        """Verifica periódicamente si el servidor responde"""
        import socket
        import time
        while True:
            try:
                # Intento de conexión rápida al puerto 5000
                with socket.create_connection(("127.0.0.1", 5000), timeout=1):
                    self.root.after(0, lambda: self.barra.activar_web(True))
            except:
                self.root.after(0, lambda: self.barra.activar_web(False))
            time.sleep(5)
    
    def crear_interfaz(self):
        """Crea TODA la interfaz"""
        
        # Barra superior
        self.barra = BarraEstado(self.root)
        
        # Menú
        self.crear_menu()
        
        # Notebook (pestañas)
        self.notebook = ttk.Notebook(self.root)
        self.notebook.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # ========================================
        # PESTAÑA 1: FUTUROS PROYECTOS (RECICLADA)
        # ========================================
        self.tab_futuros = TabFuturosProyectos(self.notebook, self.gestor)
        self.notebook.add(self.tab_futuros, text="1. 🚀 Futuros Proyectos")
        
        # ========================================
        # PESTAÑA 2: CATÁLOGO REAL (TABLA + BUSCADOR)
        # ========================================
        # Se crea dinámicamente o se unifica
        self.crear_tab_catalogo_real()
        
        # ========================================
        # PESTAÑA 3: EDITOR (FORMULARIO)
        # ========================================
        self.crear_tab_editor()

        # ========================================
        # PESTAÑA 4: WHATSAPP
        # ========================================
        self.crear_tab_whatsapp()

        # ========================================
        # PESTAÑA 5: STOCK
        # ========================================
        self.crear_tab_stock()

        # ========================================
        # PESTAÑA 6: HERRAMIENTAS
        # ========================================
        self.crear_tab_herramientas()

        # = [[]] ===============================
        # PESTAÑA 7: VENTAS
        # ========================================
        self.crear_tab_ventas()

        # ========================================
        # PESTAÑA 8: INFLUENCERS
        # ========================================
        self.crear_tab_influencers()

        # ========================================
        # PESTAÑAS NUEVAS (9, 10, 11)
        # ========================================
        self.crear_tab_clientes()
        self.crear_tab_pedidos_kanban()
        self.crear_tab_gestion_pedidos()
        self.crear_tab_envios()
        self.crear_tab_flujo()
        self.crear_tab_n8n()

    
    def crear_menu(self):
        """Menú completo"""
        menubar = tk.Menu(self.root)
        self.root.config(menu=menubar)
        
        # Archivo
        menu_archivo = tk.Menu(menubar, tearoff=0, font=('Segoe UI', 11))
        menubar.add_cascade(label="📁 Archivo", menu=menu_archivo)
        menu_archivo.add_separator()
        menu_archivo.add_command(label="Nuevo Producto", command=self.nuevo_producto)
        
        # Ayuda
        menu_ayuda = tk.Menu(menubar, tearoff=0, font=('Segoe UI', 11))
        menubar.add_cascade(label="❓ Ayuda", menu=menu_ayuda)
        menu_ayuda.add_command(label="API Key", command=self.ayuda_api_key)
        menu_ayuda.add_command(label="Acerca de", command=self.acerca_de)
    
    def crear_tab_editor(self):
        """Pestaña principal"""
        self.tab_editor = tk.Frame(self.notebook, bg=COLOR_FONDO)
        self.notebook.add(self.tab_editor, text="3. 📝 Editor")
        
        main = tk.Frame(self.tab_editor, bg=COLOR_FONDO)
        main.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Filtros (izquierda)
        self.panel_filtros = PanelFiltros(main, self.on_filtrar)
        
        # Centro
        centro = tk.Frame(main, bg=COLOR_FONDO)
        centro.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=5)
        
        self.indicador_ia = IndicadorProcesoIA(centro)
        self.crear_formulario(centro)
        
        # Panel de Variantes (Abajo del formulario)
        self.crear_panel_variantes(centro)
        
        # Derecha
        derecha = tk.Frame(main, bg=COLOR_FONDO, width=420)
        derecha.pack(side=tk.RIGHT, fill=tk.BOTH, padx=5)
        derecha.pack_propagate(False)
        
        self.preview_imagen = PreviewImagenSeleccionada(derecha)
        self.preview_imagen.frame.pack(fill=tk.X, pady=5)
        
    
    def crear_tab_catalogo_real(self):
        """Pestaña que une la Tabla Excel y el Buscador Visual de artículos creados"""
        main_tab = tk.Frame(self.notebook, bg=COLOR_FONDO)
        self.notebook.add(main_tab, text="2. 📊 Catálogo Real")
        
        sub_nb = ttk.Notebook(main_tab)
        sub_nb.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Sub-pestaña 2.1: Tabla
        tab_tabla = tk.Frame(sub_nb, bg=COLOR_FONDO)
        sub_nb.add(tab_tabla, text=" 2.1 📝 Vista Tabla ")
        self._crear_contenido_tabla(tab_tabla)
        
        # Sub-pestaña 2.2: Buscador Visual (Buscador para articulos ya creados pedido por el usuario)
        tab_buscador = TabBuscadorCreados(sub_nb, self.gestor)
        sub_nb.add(tab_buscador, text=" 2.2 🔍 Buscador Visual ")

    def _crear_contenido_tabla(self, parent):
        """Lógica de visualización de tabla excel"""
        columnas = ['SKU_REF', 'SKU_BASE', 'ES_VARIANTE', 'MARCA', 'CATEGORIA', 'SUBCATEGORIA', 'NOMBRE', 'PRECIO', 'COLOR', 'ESTADO', 'FOTO_PORTADA', 'GALERIA']
        self.vista_tabla = VistaTablaExcel(parent, columnas, self.on_seleccionar_desde_tabla)
        self.vista_tabla.frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        self.lbl_total_tabla = tk.Label(parent, text="Total: 0 artículos", font=('Segoe UI', 10), bg=COLOR_FONDO)
        self.lbl_total_tabla.pack(pady=5)
        
        btns_frame = tk.Frame(parent, bg=COLOR_FONDO)
        btns_frame.pack(pady=5)
        
        tk.Button(btns_frame, text="🔄 Actualizar Tabla", font=('Segoe UI', 10, 'bold'), 
                   command=self.recargar_datos_tabla, bg='#f3f4f6', padx=10).pack(side=tk.LEFT, padx=5)
        
        tk.Button(btns_frame, text="✏️ ABRIR EN EDITOR", font=('Segoe UI', 10, 'bold'), 
                   command=self.ir_al_editor, bg=COLOR_VERDE, fg='white', padx=15).pack(side=tk.LEFT, padx=5)
        
    def ir_al_editor(self):
        """Lleva a la pestaña del editor con el producto ya cargado"""
        if not self.producto_actual:
            messagebox.showwarning("Aviso", "Primero selecciona un artículo de la tabla")
            return
        self.notebook.select(self.tab_editor)
    

    def crear_tab_whatsapp(self):
        """Pestaña de Compartir por WhatsApp"""
        try:
            # TabWhatsApp está definida al final de este archivo app.py
            self.tab_whatsapp = TabWhatsApp(self.notebook, self.gestor, self)
            self.notebook.add(self.tab_whatsapp.frame, text="4. 💬 WhatsApp")
        except Exception as e:
            print(f"Error cargando TabWhatsApp: {e}")

        self.tab_stock = TabStock(self.notebook, self.gestor, self)
        self.notebook.add(self.tab_stock.frame, text="5. 📦 Faltantes / Stock")

    def crear_tab_influencers(self):
        """Pestaña de Gestión de Influencers (MÓDULO 17)"""
        self.tab_influencers = TabInfluencers(self.notebook, self.gestor, self)
        self.notebook.add(self.tab_influencers, text="8. 👥 Influencers")

    def crear_tab_clientes(self):
        """Pestaña de Gestión de Clientes (MÓDULO 18)"""
        self.tab_clientes = TabClientes(self.notebook, self)
        self.notebook.add(self.tab_clientes, text="9. 👥 Clientes")

    def crear_tab_pedidos_kanban(self):
        """Pestaña de Tablero Kanban de Pedidos (MÓDULO 19)"""
        self.tab_pedidos = TabPedidos(self.notebook, self)
        self.notebook.add(self.tab_pedidos, text="10. 📋 Tablero Kanban")

    def crear_tab_gestion_pedidos(self):
        """Pestaña de Gestión de Pedidos tipo Web (MÓDULO 21)"""
        self.tab_gestion_pedidos = TabGestionPedidos(self.notebook, self)
        self.notebook.add(self.tab_gestion_pedidos, text="11. 📋 Gestión Pedidos")

    def crear_tab_envios(self):
        """Pestaña de Gestión de Envíos y Packlink (MÓDULO 20)"""
        self.tab_envios = TabEnvios(self.notebook, self)
        self.notebook.add(self.tab_envios, text="12. 📦 Envíos")

    def crear_tab_flujo(self):
        """Pestaña de Flujo Visual de Producción (MÓDULO 22)"""
        try:
            self.tab_flujo = TabFlujoVisual(self.notebook, self)
            self.notebook.add(self.tab_flujo, text="13. 🔀 Flujo Visual")
        except Exception as e:
            print(f"[app] Error cargando TabFlujoVisual: {e}")

    def crear_tab_n8n(self):
        """Pestaña de Automatización con n8n"""
        import webbrowser, os as _os, threading as _th, socket

        tab = tk.Frame(self.notebook, bg=COLOR_FONDO)
        self.notebook.add(tab, text="13. 🤖 Automatización")

        # ── Título ──────────────────────────────────────────
        header = tk.Frame(tab, bg='#1e1b4b', pady=12)
        header.pack(fill=tk.X)
        tk.Label(header, text="🤖  Automatización con n8n",
                 font=('Segoe UI', 16, 'bold'), bg='#1e1b4b', fg='white').pack()
        tk.Label(header,
                 text="Conecta tu app con flujos automáticos: pedidos, WhatsApp, stock, asistente de voz…",
                 font=('Segoe UI', 10), bg='#1e1b4b', fg='#a5b4fc').pack(pady=(2, 0))

        # ── Estado de conexión ───────────────────────────────
        est_frame = tk.Frame(tab, bg=COLOR_FONDO)
        est_frame.pack(fill=tk.X, padx=20, pady=12)

        lbl_estado_icon = tk.Label(est_frame, text='⚪', font=('Segoe UI', 18), bg=COLOR_FONDO)
        lbl_estado_icon.pack(side=tk.LEFT, padx=(0, 8))
        lbl_estado_txt  = tk.Label(est_frame, text='Comprobando estado de n8n…',
                                   font=('Segoe UI', 12), bg=COLOR_FONDO, fg='#64748b')
        lbl_estado_txt.pack(side=tk.LEFT)

        btn_refrescar = tk.Button(est_frame, text='🔄 Refrescar',
                                  font=('Segoe UI', 9), bg='#e2e8f0', padx=8,
                                  relief=tk.FLAT)
        btn_refrescar.pack(side=tk.RIGHT)

        def verificar_n8n():
            """Comprueba si n8n responde en el puerto 5678."""
            try:
                with socket.create_connection(('127.0.0.1', 5678), timeout=2):
                    conectado = True
            except Exception:
                conectado = False
            def _upd():
                if conectado:
                    lbl_estado_icon.config(text='🟢')
                    lbl_estado_txt.config(text='n8n ACTIVO  —  http://localhost:5678', fg='#16a34a')
                else:
                    lbl_estado_icon.config(text='🔴')
                    lbl_estado_txt.config(
                        text='n8n NO detectado  —  Instala Node.js y ejecuta: npx n8n',
                        fg='#dc2626')
            tab.after(0, _upd)

        btn_refrescar.config(command=lambda: _th.Thread(target=verificar_n8n, daemon=True).start())
        _th.Thread(target=verificar_n8n, daemon=True).start()

        ttk.Separator(tab, orient='horizontal').pack(fill=tk.X, padx=20, pady=6)

        # ── Acciones rápidas ─────────────────────────────────
        acciones = tk.LabelFrame(tab, text='  Acciones rápidas  ',
                                  font=('Segoe UI', 11, 'bold'), bg=COLOR_FONDO, padx=12, pady=10)
        acciones.pack(fill=tk.X, padx=20, pady=6)

        _n8n_dir = _os.path.join(_os.path.dirname(__file__), 'n8n')
        _db_path = _os.path.join(_os.path.dirname(__file__), 'catalogo.db')

        def abrir_n8n_web():
            webbrowser.open('http://localhost:5678')

        def abrir_asistente():
            webbrowser.open('http://localhost:5000/asistente-voz')

        def abrir_carpeta_flujos():
            import subprocess
            subprocess.Popen(['explorer', _n8n_dir])

        botones_data = [
            ('🌐  Abrir n8n en navegador',   '#6366f1', abrir_n8n_web,
             'Accede al panel de n8n\n(http://localhost:5678)'),
            ('🎙️  Abrir Asistente de Voz',   '#0891b2', abrir_asistente,
             'Abre el asistente con\nmicro en el navegador'),
            ('📂  Ver Flujos JSON',           '#059669', abrir_carpeta_flujos,
             'Abre la carpeta con los\n5 flujos para importar'),
        ]

        for (txt, color, cmd, tooltip) in botones_data:
            col_frame = tk.Frame(acciones, bg=COLOR_FONDO)
            col_frame.pack(side=tk.LEFT, padx=12, pady=4)
            btn = tk.Button(col_frame, text=txt, font=('Segoe UI', 10, 'bold'),
                            bg=color, fg='white', relief=tk.FLAT,
                            padx=14, pady=10, command=cmd, cursor='hand2')
            btn.pack()
            tk.Label(col_frame, text=tooltip, font=('Segoe UI', 8),
                     bg=COLOR_FONDO, fg='#64748b', justify=tk.CENTER).pack(pady=(4, 0))

        ttk.Separator(tab, orient='horizontal').pack(fill=tk.X, padx=20, pady=6)

        # ── Flujos disponibles ───────────────────────────────
        flujos_frame = tk.LabelFrame(tab, text='  Flujos disponibles (importar en n8n)  ',
                                     font=('Segoe UI', 11, 'bold'), bg=COLOR_FONDO, padx=12, pady=8)
        flujos_frame.pack(fill=tk.X, padx=20, pady=6)

        flujos = [
            ('flujo1_pedido_manual.json',  '📋 Flujo 1 — Pedido Manual',
             'Guarda pedidos desde la app en SQLite automáticamente'),
            ('flujo2_parsear_mensaje.json','💬 Flujo 2 — Parsear WhatsApp',
             'Detecta intención y extrae artículos de mensajes de WhatsApp'),
            ('flujo3_postventa.json',      '📬 Flujo 3 — Post-Venta',
             'Genera mensajes de seguimiento automáticos tras pedidos'),
            ('flujo4_stock_resumen.json',  '📦 Flujo 4 — Resumen de Stock',
             'Consulta el stock y genera resúmenes periódicos'),
            ('flujo5_asistente_voz.json',  '🎙️ Flujo 5 — Asistente de Voz',
             'Procesa comandos de voz y responde en lenguaje natural'),
        ]

        for (fname, nombre, desc) in flujos:
            fila = tk.Frame(flujos_frame, bg='#f8fafc', bd=1, relief=tk.RIDGE)
            fila.pack(fill=tk.X, pady=3)
            tk.Label(fila, text=nombre, font=('Segoe UI', 10, 'bold'),
                     bg='#f8fafc', anchor='w', width=38).pack(side=tk.LEFT, padx=10, pady=6)
            tk.Label(fila, text=desc, font=('Segoe UI', 9),
                     bg='#f8fafc', fg='#475569', anchor='w').pack(side=tk.LEFT, padx=4)
            fpath = _os.path.join(_n8n_dir, fname)
            existe = '✅ Listo' if _os.path.exists(fpath) else '❌ No encontrado'
            tk.Label(fila, text=existe, font=('Segoe UI', 9, 'bold'),
                     bg='#f8fafc', fg='#16a34a' if '✅' in existe else '#dc2626').pack(side=tk.RIGHT, padx=12)

        ttk.Separator(tab, orient='horizontal').pack(fill=tk.X, padx=20, pady=6)

        # ── Notificaciones n8n pendientes ────────────────────
        notif_outer = tk.LabelFrame(tab, text='  Notificaciones pendientes de n8n  ',
                                    font=('Segoe UI', 11, 'bold'), bg=COLOR_FONDO, padx=12, pady=8)
        notif_outer.pack(fill=tk.X, padx=20, pady=6)

        notif_lista = tk.Text(notif_outer, height=3, font=('Segoe UI', 10),
                              bg='#0f172a', fg='#94a3b8', state=tk.DISABLED,
                              relief=tk.FLAT, padx=8, pady=6)
        notif_lista.pack(fill=tk.X)

        def cargar_notificaciones():
            try:
                notifs = n8n_mod.obtener_notificaciones_nuevas()
                notif_lista.config(state=tk.NORMAL)
                notif_lista.delete(1.0, tk.END)
                if notifs:
                    for n in notifs:
                        notif_lista.insert(tk.END,
                            f"[{n.get('fecha','')}] {n.get('tipo','').upper()}: {n.get('mensaje','')}\n")
                else:
                    notif_lista.insert(tk.END, 'Sin notificaciones pendientes.')
                notif_lista.config(state=tk.DISABLED)
            except Exception as e:
                notif_lista.config(state=tk.NORMAL)
                notif_lista.delete(1.0, tk.END)
                notif_lista.insert(tk.END, f'Error: {e}')
                notif_lista.config(state=tk.DISABLED)

        # ── Tareas / Recordatorios (Asistente de Voz) ──────────
        tareas_outer = tk.LabelFrame(tab, text='  📝 Recordatorios y Tareas (Voz)  ',
                                     font=('Segoe UI', 11, 'bold'), bg=COLOR_FONDO, padx=12, pady=8)
        tareas_outer.pack(fill=tk.BOTH, expand=True, padx=20, pady=6)

        tareas_lista = tk.Listbox(tareas_outer, font=('Segoe UI', 10),
                                  bg='white', fg='#1e293b', relief=tk.FLAT,
                                  borderwidth=1, selectbackground='#e2e8f0', selectforeground='#1e293b')
        tareas_lista.pack(fill=tk.BOTH, expand=True, side=tk.LEFT)
        
        scroll_t = ttk.Scrollbar(tareas_outer, orient=tk.VERTICAL, command=tareas_lista.yview)
        scroll_t.pack(side=tk.RIGHT, fill=tk.Y)
        tareas_lista.config(yscrollcommand=scroll_t.set)

        def cargar_tareas():
            try:
                from modulo3_gestion import get_db_connection
                conn = get_db_connection()
                cursor = conn.cursor(dictionary=True)
                cursor.execute("SELECT * FROM tareas WHERE completada = 0 ORDER BY fecha_creacion DESC LIMIT 20")
                tareas = cursor.fetchall()
                tareas_lista.delete(0, tk.END)
                if tareas:
                    for t in tareas:
                        tareas_lista.insert(tk.END, f"• {t['descripcion']}")
                else:
                    tareas_lista.insert(tk.END, "Sin recordatorios pendientes.")
                conn.close()
            except Exception as e:
                print(f"Error cargando tareas: {e}")

        btn_box = tk.Frame(tareas_outer, bg=COLOR_FONDO)
        btn_box.pack(fill=tk.X, side=tk.BOTTOM, pady=(4,0))
        
        tk.Button(btn_box, text='🔄 Actualizar Todo',
                  font=('Segoe UI', 9), bg='#e2e8f0', relief=tk.FLAT, padx=8,
                  command=lambda: [cargar_notificaciones(), cargar_tareas()]).pack(side=tk.RIGHT)
        
        def marcar_hecho():
            sel = tareas_lista.curselection()
            if not sel: return
            texto = tareas_lista.get(sel[0]).replace('• ', '')
            try:
                from modulo3_gestion import get_db_connection
                conn = get_db_connection()
                cursor = conn.cursor()
                cursor.execute("UPDATE tareas SET completada = 1 WHERE descripcion = %s", (texto,))
                conn.commit()
                conn.close()
                cargar_tareas()
            except Exception as e:
                print(f"Error marcando tarea como hecha: {e}")

        def eliminar_tarea():
            sel = tareas_lista.curselection()
            if not sel: return
            if not tk.messagebox.askyesno("Eliminar", "¿Eliminar este recordatorio permanentemente?"): return
            texto = tareas_lista.get(sel[0]).replace('• ', '')
            try:
                from modulo3_gestion import get_db_connection
                conn = get_db_connection()
                cursor = conn.cursor()
                cursor.execute("DELETE FROM tareas WHERE descripcion = %s", (texto,))
                conn.commit()
                conn.close()
                cargar_tareas()
            except Exception as e:
                print(f"Error eliminando tarea: {e}")

        tk.Button(btn_box, text='✅ Hecho',
                  font=('Segoe UI', 9, 'bold'), bg='#dcfce7', fg='#166534', relief=tk.FLAT, padx=12,
                  command=marcar_hecho).pack(side=tk.LEFT, padx=2)
        
        tk.Button(btn_box, text='🗑️ Eliminar',
                  font=('Segoe UI', 9), bg='#fee2e2', fg='#991b1b', relief=tk.FLAT, padx=12,
                  command=eliminar_tarea).pack(side=tk.LEFT, padx=2)

        def resetear_credenciales_n8n():
            """Llama al script de reset de contraseña."""
            if not tk.messagebox.askyesno("Confirmar", "¿Deseas resetear la contraseña de n8n a 'erforo2006'?"):
                return
            
            try:
                import subprocess, sys
                script_path = _os.path.join(_os.path.dirname(__file__), "reset_n8n_password.py")
                # Usar el ejecutable de python actual (venv)
                subprocess.Popen([sys.executable, script_path], creationflags=subprocess.CREATE_NO_WINDOW if _os.name == 'nt' else 0)
                tk.messagebox.showinfo("Éxito", "Proceso de reset enviado. n8n se actualizará en unos segundos.")
            except Exception as e:
                tk.messagebox.showerror("Error", f"No se pudo ejecutar el reset: {e}")

        tk.Button(btn_box, text='🔑 Resetear n8n',
                  font=('Segoe UI', 9, 'bold'), bg='#fef9c3', fg='#854d0e', relief=tk.FLAT, padx=12,
                  command=resetear_credenciales_n8n).pack(side=tk.LEFT, padx=2)

        # Cargar al arrancar
        tab.after(1500, lambda: [cargar_notificaciones(), cargar_tareas()])
    
    def crear_formulario(self, parent):
        """Formulario"""
        form_frame = ttk.LabelFrame(parent, text="📝 Producto")
        form_frame.pack(fill=tk.X, padx=5, pady=5)
        
        form = tk.Frame(form_frame, bg=COLOR_FONDO)
        form.pack(fill=tk.X, padx=10, pady=10)
        form.columnconfigure(1, weight=1)
        form.columnconfigure(3, weight=1)
        
        # SKU
        ttk.Label(form, text="SKU:", font=('Segoe UI', 12, 'bold')).grid(row=0, column=0, sticky=tk.W, padx=5, pady=5)
        self.entry_sku = ttk.Entry(form, font=('Segoe UI', 11), width=22)
        self.entry_sku.grid(row=0, column=1, sticky=tk.EW, padx=5)
        
        # Marca
        ttk.Label(form, text="Marca:", font=('Segoe UI', 12, 'bold')).grid(row=0, column=2, sticky=tk.W, padx=5)
        self.combo_marca = ttk.Combobox(form, font=('Segoe UI', 11), width=22)
        self.combo_marca['values'] = list(MARCAS.keys())
        self.combo_marca.grid(row=0, column=3, sticky=tk.EW, padx=5)
        self.combo_marca.bind('<<ComboboxSelected>>', self.actualizar_sku)
        
        # Categoría
        ttk.Label(form, text="Categoría:", font=('Segoe UI', 12, 'bold')).grid(row=1, column=0, sticky=tk.W, padx=5, pady=5)
        self.combo_categoria = ttk.Combobox(form, font=('Segoe UI', 11), width=22)
        self.combo_categoria['values'] = list(CATEGORIAS.keys())
        self.combo_categoria.grid(row=1, column=1, sticky=tk.EW, padx=5)
        self.combo_categoria.bind('<<ComboboxSelected>>', self.actualizar_subcategorias)
        
        # Subcategoría
        ttk.Label(form, text="Subcategoría:", font=('Segoe UI', 12, 'bold')).grid(row=1, column=2, sticky=tk.W, padx=5)
        self.combo_subcategoria = ttk.Combobox(form, font=('Segoe UI', 11), width=22)
        self.combo_subcategoria.grid(row=1, column=3, sticky=tk.EW, padx=5)
        self.combo_subcategoria.bind('<<ComboboxSelected>>', self.actualizar_sku)
        
        # Nombre
        ttk.Label(form, text="Nombre:", font=('Segoe UI', 12, 'bold')).grid(row=2, column=0, sticky=tk.W, padx=5, pady=5)
        self.entry_nombre = ttk.Entry(form, font=('Segoe UI', 11))
        self.entry_nombre.grid(row=2, column=1, columnspan=3, sticky=tk.EW, padx=5)
        
        # Precio
        ttk.Label(form, text="Precio €:", font=('Segoe UI', 12, 'bold')).grid(row=3, column=0, sticky=tk.W, padx=5, pady=5)
        self.entry_precio = ttk.Entry(form, font=('Segoe UI', 11), width=8)
        self.entry_precio.grid(row=3, column=1, sticky=tk.W, padx=5)
        
        # Color
        ttk.Label(form, text="Color:", font=('Segoe UI', 12, 'bold')).grid(row=3, column=2, sticky=tk.W, padx=5)
        self.entry_color = ttk.Entry(form, font=('Segoe UI', 11), width=15)
        self.entry_color.grid(row=3, column=3, sticky=tk.W, padx=5)
        self.entry_color.bind('<KeyRelease>', self.actualizar_sku)
        
        # Dimensiones Artículo
        ttk.Label(form, text="Medidas Art.:", font=('Segoe UI', 12, 'bold')).grid(row=4, column=0, sticky="w", padx=5, pady=5)
        self.entry_dimensiones = ttk.Entry(form, font=('Segoe UI', 11))
        self.entry_dimensiones.grid(row=4, column=1, sticky="ew", padx=5)
        
        # Festividad
        ttk.Label(form, text="Festividad:", font=('Segoe UI', 12, 'bold')).grid(row=4, column=2, sticky="w", padx=5)
        self.combo_festividad = ttk.Combobox(form, font=('Segoe UI', 11), width=22)
        self.combo_festividad['values'] = ["Sin festividad", "San Valentín", "Navidad"]
        self.combo_festividad.grid(row=4, column=3, sticky="ew", padx=5)

        # --- SECCIÓN LOGÍSTICA (NUEVO) ---
        log_frame = tk.Frame(form, bg="#f8fafc", bd=1, relief=tk.RIDGE)
        log_frame.grid(row=5, column=0, columnspan=4, sticky="ew", pady=10, padx=5)
        
        tk.Label(log_frame, text="📦 LOGÍSTICA (PARA ENVÍOS):", font=('Segoe UI', 10, 'bold'), bg="#f8fafc", fg="#475569").pack(side=tk.LEFT, padx=5)
        
        tk.Label(log_frame, text="Peso (Kg):", bg="#f8fafc").pack(side=tk.LEFT, padx=(10,0))
        self.ent_peso_prod = ttk.Entry(log_frame, width=6)
        self.ent_peso_prod.pack(side=tk.LEFT, padx=5)
        self.ent_peso_prod.insert(0, "0.5")

        tk.Label(log_frame, text="Medidas Paquete (LxAnxAl):", bg="#f8fafc").pack(side=tk.LEFT, padx=(10,0))
        self.ent_l_prod = ttk.Entry(log_frame, width=4); self.ent_l_prod.pack(side=tk.LEFT, padx=2)
        tk.Label(log_frame, text="x", bg="#f8fafc").pack(side=tk.LEFT)
        self.ent_w_prod = ttk.Entry(log_frame, width=4); self.ent_w_prod.pack(side=tk.LEFT, padx=2)
        tk.Label(log_frame, text="x", bg="#f8fafc").pack(side=tk.LEFT)
        self.ent_h_prod = ttk.Entry(log_frame, width=4); self.ent_h_prod.pack(side=tk.LEFT, padx=2)
        
        self.ent_l_prod.insert(0, "20"); self.ent_w_prod.insert(0, "15"); self.ent_h_prod.insert(0, "10")
        
        # Variante (Mover a fila 6 junto con descripción o solo)
        self.chk_es_variante = CheckboxMejorado(form, "Es Variante", 6, 3)
        self.chk_es_variante.check.bind('<Button-1>', lambda e: self.root.after(100, self.actualizar_sku))

        # Descripción
        ttk.Label(form, text="Descripción:", font=('Segoe UI', 12, 'bold')).grid(row=6, column=0, sticky=tk.NW, padx=5, pady=5)
        self.text_descripcion = tk.Text(form, font=('Segoe UI', 11), height=2, wrap=tk.WORD)
        self.text_descripcion.grid(row=6, column=1, columnspan=3, sticky=tk.EW, padx=5, pady=5)
        
        # Botones
        botones = tk.Frame(form_frame, bg=COLOR_FONDO)
        botones.pack(fill=tk.X, padx=10, pady=10)
        
        BotonConFeedback(botones, "Imagen", self.seleccionar_imagen, COLOR_MORADO, "📁").pack(side=tk.LEFT, padx=3)
        BotonConFeedback(botones, "Enlazar con imagen", self.enlazar_imagenes_bulk, '#4f46e5', "🔗").pack(side=tk.LEFT, padx=3)
        BotonConFeedback(botones, "IA", self.analizar_con_ia, COLOR_MARRON, "🤖").pack(side=tk.LEFT, padx=3)
        BotonConFeedback(botones, "Guardar", self.guardar_producto, COLOR_VERDE, "💾").pack(side=tk.LEFT, padx=3)
        BotonConFeedback(botones, "Variante", self.preparar_nueva_variante, '#06b6d4', "➕").pack(side=tk.LEFT, padx=3)
        BotonConFeedback(botones, "Eliminar", self.eliminar_producto_actual, '#9f1239', "🗑️").pack(side=tk.LEFT, padx=3)
        BotonConFeedback(botones, "Corte", self.seleccionar_imagen_despiece, '#8b5cf6', "🪚").pack(side=tk.LEFT, padx=3)

    def crear_panel_variantes(self, parent):
        """Panel para gestionar variantes del producto base seleccionado"""
        self.frame_variantes = ttk.LabelFrame(parent, text="📦 Variantes del Producto (Mismo Modelo)")
        self.frame_variantes.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Header con botón de sincronizar
        header = tk.Frame(self.frame_variantes, bg=COLOR_FONDO)
        header.pack(fill=tk.X, padx=5, pady=5)
        
        tk.Label(header, text="Variantes detectadas:", font=('Segoe UI', 10, 'bold'), bg=COLOR_FONDO).pack(side=tk.LEFT)
        self.btn_sync = ttk.Button(header, text="🔄 Sincronizar Datos (Precio/Desc)", command=self.sincronizar_variantes)
        self.btn_sync.pack(side=tk.RIGHT)
        
        # Lista de variantes
        self.container_variantes = tk.Frame(self.frame_variantes, bg='white')
        self.container_variantes.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        self.lbl_no_vars = tk.Label(self.container_variantes, text="Selecciona un producto base para ver sus variantes", fg='#999', bg='white')
        self.lbl_no_vars.pack(pady=20)
    
    # Funciones auxiliares
    def actualizar_vistas_combo(self):
        """Busca todos los valores únicos en el Excel para sugerir en combos"""
        if not self.gestor.productos:
            return
            
        marcas = set(MARCAS.keys())
        cats = set(CATEGORIAS.keys())
        
        for p in self.gestor.productos:
            if p.get('MARCA'): marcas.add(p.get('MARCA'))
            if p.get('CATEGORIA'): cats.add(p.get('CATEGORIA'))
            
        self.combo_marca['values'] = sorted(list(marcas))
        self.combo_categoria['values'] = sorted(list(cats))
        
        # También actualizar el buscador del panel de filtros
        if hasattr(self, 'panel_filtros'):
             self.panel_filtros.combo_categoria['values'] = [''] + sorted(list(cats))
             self.panel_filtros.combo_marca['values'] = [''] + sorted(list(marcas))
             
        # Y el tab de WhatsApp
        if hasattr(self, 'tab_whatsapp'):
            self.tab_whatsapp.combo_cat['values'] = [''] + sorted(list(cats))

    def actualizar_subcategorias(self, event=None):
        categoria = self.combo_categoria.get()
        if categoria in CATEGORIAS:
            # Combinar sugerencias estáticas con las del Excel
            sugerencias = set(CATEGORIAS[categoria])
            for p in self.gestor.productos:
                if p.get('CATEGORIA') == categoria and p.get('SUBCATEGORIA'):
                    sugerencias.add(p.get('SUBCATEGORIA'))
            
            self.combo_subcategoria['values'] = sorted(list(sugerencias))
        else:
            # Si es una categoría nueva, buscar subcategorías usadas para ella
            sugerencias = set()
            for p in self.gestor.productos:
                if p.get('CATEGORIA') == categoria and p.get('SUBCATEGORIA'):
                    sugerencias.add(p.get('SUBCATEGORIA'))
            
            if sugerencias:
                self.combo_subcategoria['values'] = sorted(list(sugerencias))
            else:
                self.combo_subcategoria['values'] = []
            
        self.actualizar_sku()
    
    def actualizar_sku(self, event=None):
        """Actualiza el SKU autogenerado basado en los campos del formulario"""
        if getattr(self, 'bloquear_actualizaciones', False):
            return
            
        marca = self.combo_marca.get()
        categoria = self.combo_categoria.get()
        subcategoria = self.combo_subcategoria.get()
        color = self.entry_color.get()
        es_variante = self.chk_es_variante.get()
        
        if marca and categoria and subcategoria:
            # Si es una variante, intentamos heredar el número del producto actual o buscar base
            numero = None
            if es_variante:
                # Si hay un producto actual y es base, usamos su SKU como base
                if self.producto_actual:
                    sku_temp = self.producto_actual.get('SKU_REF', '')
                    # Extraer el número base (antes del primer guion o al final si no hay)
                    import re
                    match = re.search(r'(\d+)', sku_temp)
                    if match:
                        numero = int(match.group(1))
            
            # Si no conseguimos número aún, o no es variante
            if numero is None:
                numero = obtener_siguiente_numero_sku(self.gestor.productos, marca, categoria, subcategoria)
                
            from modulo6_correcciones import generar_sku_correcto
            sku = generar_sku_correcto(marca, categoria, subcategoria, numero, color if es_variante else None)
            
            # Si NO es variante, el SKU no lleva color por defecto en la base
            if not es_variante:
                # Forzar SKU base (sin color) si el usuario no activó variante
                sku = sku.split('-')[0]
            
            self.entry_sku.delete(0, tk.END)
            self.entry_sku.insert(0, sku)
            
    
    def seleccionar_imagen(self):
        rutas = filedialog.askopenfilenames(title="Seleccionar imágenes", filetypes=[("Imágenes", "*.png *.jpg *.jpeg")])
        if rutas:
            self.imagenes_seleccionadas = list(rutas)
            self.preview_imagen.mostrar_imagen(rutas[0])
            Notificacion.mostrar(self.root, f"📸 {len(rutas)} imagen(es)", 'exito')

    def seleccionar_imagen_despiece(self):
        ruta = filedialog.askopenfilename(title="Seleccionar imagen de despiece", filetypes=[("Imágenes", "*.png *.jpg *.jpeg")])
        if ruta:
            # Guardar en la misma carpeta que las fotos pero con sufijo _DESPIECE
            if self.producto_actual:
                sku = self.producto_actual.get('SKU_REF')
                from modulo3_gestion import guardar_imagen_producto
                ruta_guardada = guardar_imagen_producto(ruta, sku, self.combo_marca.get(), self.combo_categoria.get(), numero="DESPIECE")
                self.producto_actual['Imagen_Despiece'] = ruta_guardada
                self.gestor.actualizar_producto(sku, {'Imagen_Despiece': ruta_guardada})
                self.gestor.guardar_excel()
                Notificacion.mostrar(self.root, "🪚 Imagen de despiece guardada", 'exito')
                # Actualizar tab de stock si existe
                if hasattr(self, 'tab_stock'):
                    self.tab_stock.on_producto_selected()
            else:
                Notificacion.mostrar(self.root, "⚠️ Primero guarda el producto", 'aviso')
    
    def analizar_con_ia(self):
        if not self.imagenes_seleccionadas:
            Notificacion.mostrar(self.root, "⚠️ Selecciona imagen", 'aviso')
            return
        
        self.indicador_ia.iniciar()
        
        def analizar():
            from modulo4_fichas import ESTILO_IA_ACTUAL
            datos = analizar_imagen_ia(self.imagenes_seleccionadas[0], style=ESTILO_IA_ACTUAL)
            self.root.after(0, lambda: self._aplicar_ia(datos))
        
        threading.Thread(target=analizar, daemon=True).start()
    
    def _aplicar_ia(self, datos):
        if datos:
            self.combo_marca.set(datos.get('marca', ''))
            self.combo_categoria.set(datos.get('categoria', ''))
            self.actualizar_subcategorias()
            self.combo_subcategoria.set(datos.get('subcategoria', ''))
            self.entry_nombre.delete(0, tk.END)
            self.entry_nombre.insert(0, datos.get('nombre', ''))
            self.entry_precio.delete(0, tk.END)
            self.entry_precio.insert(0, str(datos.get('precio', '')))
            self.entry_color.delete(0, tk.END)
            self.entry_color.insert(0, datos.get('color', ''))
            self.entry_dimensiones.delete(0, tk.END)
            self.entry_dimensiones.insert(0, datos.get('dimensiones', ''))
            self.text_descripcion.delete(1.0, tk.END)
            self.text_descripcion.insert(1.0, datos.get('descripcion', ''))
            self.actualizar_sku()
            self.indicador_ia.detener(True)
            Notificacion.mostrar(self.root, "✅ Completado", 'exito')
            self.barra.actualizar()
        else:
            self.indicador_ia.detener(False)
            Notificacion.mostrar(self.root, "❌ Error", 'error')

    def actualizar_panel_variantes(self, sku):
        """Busca y muestra las variantes del SKU seleccionado con miniaturas"""
        # Limpiar container y cache
        for widget in self.container_variantes.winfo_children():
            widget.destroy()
        self.cache_variantes = []
            
        sku_base = sku.split('-')[0]
        self.variantes_actuales = obtener_variantes(self.gestor.productos, sku_base)
        
        if len(self.variantes_actuales) <= 1:
            tk.Label(self.container_variantes, text="No se detectaron variantes de color para este modelo", bg='white', fg='#666').pack(pady=10)
            return

        # Grid de variantes
        row, col = 0, 0
        from PIL import Image, ImageTk
        
        for p in self.variantes_actuales:
            f = tk.Frame(self.container_variantes, bg='#f3f4f6', bd=1, relief=tk.SOLID, padx=5, pady=5)
            f.grid(row=row, column=col, padx=5, pady=5, sticky='nsew')
            
            # 1. MINIATURA
            foto_ruta = p.get('FOTO_PORTADA')
            if foto_ruta and os.path.exists(foto_ruta):
                try:
                    img = Image.open(foto_ruta)
                    img.thumbnail((80, 80), Image.Resampling.LANCZOS)
                    img_tk = ImageTk.PhotoImage(img)
                    self.cache_variantes.append(img_tk) # Mantener referencia
                    lbl_img = tk.Label(f, image=img_tk, bg='#f3f4f6')
                    lbl_img.pack()
                except:
                    tk.Label(f, text="🖼️", font=('Segoe UI', 20), bg='#f3f4f6').pack()
            else:
                tk.Label(f, text="🚫", font=('Segoe UI', 20), bg='#f3f4f6').pack()

            # 2. COLOR Y SKU
            color = p.get('COLOR', 'N/A')
            tk.Label(f, text=color, font=('Segoe UI', 9, 'bold'), bg='#f3f4f6').pack()
            tk.Label(f, text=p.get('SKU_REF', ''), font=('Consolas', 8), bg='#f3f4f6', fg='#666').pack()
            
            # 3. BOTÓN PARA CARGAR
            btn = tk.Button(f, text="Cargar", font=('Segoe UI', 8), command=lambda p=p: self.on_seleccionar_producto(p))
            btn.pack(pady=2)
            
            col += 1
            if col > 5:
                col = 0
                row += 1

    def sincronizar_variantes(self):
        """Aplica los datos del producto actual a todas sus variantes"""
        if not self.producto_actual: return
        
        sku_base = self.producto_actual.get('SKU_REF', '').split('-')[0]
        variantes = obtener_variantes(self.gestor.productos, sku_base)
        
        if len(variantes) <= 1:
            messagebox.showinfo("Info", "No hay otras variantes que sincronizar.")
            return
            
        if not messagebox.askyesno("Confirmar Sincronización", 
                                  f"¿Sincronizar Precio, Categoría, Descripción y Nombre con las {len(variantes)} variantes?\n"
                                  "Las imágenes y el color se mantendrán individuales."):
            return
            
        precio = self.entry_precio.get()
        nombre = self.entry_nombre.get()
        cat = self.combo_categoria.get()
        subcat = self.combo_subcategoria.get()
        desc = self.text_descripcion.get(1.0, tk.END).strip()
        dims = self.entry_dimensiones.get()
        marca = self.combo_marca.get()
        
        count = 0
        for v in variantes:
            # Mantener SKU, Color y Fotos originales de la variante
            v['PRECIO'] = precio
            v['NOMBRE'] = nombre
            v['CATEGORIA'] = cat
            v['SUBCATEGORIA'] = subcat
            v['DESCRIPCION'] = desc
            v['DIMENSIONES'] = dims
            v['MARCA'] = marca
            # Sincronizar también logística si existe en la UI
            v['peso_envio'] = self.ent_peso_prod.get()
            v['largo_envio'] = self.ent_l_prod.get()
            v['ancho_envio'] = self.ent_w_prod.get()
            v['alto_envio'] = self.ent_h_prod.get()
            # Actualizar en el gestor
            self.gestor.actualizar_producto(v['SKU_REF'], v)
            count += 1
            
        self.gestor.guardar_excel()
        Notificacion.mostrar(self.root, f"✅ {count} variantes sincronizadas", 'exito')
        if hasattr(self, 'vista_tabla'):
            self.vista_tabla.cargar_productos(self.gestor.productos)
    
    def guardar_producto(self):
        sku = self.entry_sku.get()
        if not sku or not self.entry_nombre.get():
            Notificacion.mostrar(self.root, "⚠️ Faltan datos", 'aviso')
            return
        
        # Si hay nuevas imagenes, usarlas. Si no, preservar las rutas existentes
        if self.imagenes_seleccionadas:
            rutas_imgs = guardar_imagenes_producto(self.imagenes_seleccionadas, sku, self.combo_marca.get(), self.combo_categoria.get())
            foto_portada = rutas_imgs['FOTO_PORTADA']
            galeria = rutas_imgs['GALERIA']
        else:
            foto_portada = self.producto_actual.get('FOTO_PORTADA', '') if self.producto_actual else ''
            galeria = self.producto_actual.get('GALERIA', '') if self.producto_actual else ''
            
        producto = {
            'SKU_REF': sku,
            'MARCA': self.combo_marca.get(),
            'CATEGORIA': self.combo_categoria.get(),
            'SUBCATEGORIA': self.combo_subcategoria.get(),
            'NOMBRE': self.entry_nombre.get(),
            'PRECIO': self.entry_precio.get(),
            'COLOR': self.entry_color.get(),
            'DIMENSIONES': self.entry_dimensiones.get(),
            'FESTIVIDAD': self.combo_festividad.get(),
            'DESCRIPCION': self.text_descripcion.get(1.0, tk.END).strip(),
            'ESTADO': 'STOCK',
            'ES_VARIANTE': 'VARIANTE' if self.chk_es_variante.get() else 'BASE',
            'FOTO_PORTADA': foto_portada,
            'GALERIA': galeria,
            'Imagen_Despiece': self.producto_actual.get('Imagen_Despiece', '') if self.producto_actual else '',
            # Nuevos campos logística
            'peso_envio': self.ent_peso_prod.get(),
            'largo_envio': self.ent_l_prod.get(),
            'ancho_envio': self.ent_w_prod.get(),
            'alto_envio': self.ent_h_prod.get()
        }
        
        if self.producto_actual:
            sku_antiguo = self.producto_actual.get('SKU_REF')
            self.gestor.actualizar_producto(sku_antiguo, producto)
            self.producto_actual = producto # Sincronizar para posibles ediciones inmediatas
            mensaje = f"✏️ Actualizado"
        else:
            self.gestor.crear_producto(producto, [])
            self.producto_actual = producto # Ahora ya existe
            mensaje = f"➕ Creado"
        
        self.gestor.guardar_excel()
        self.panel_filtros.actualizar_contador(len(self.gestor.productos))
        self.actualizar_vistas_combo()
        if hasattr(self, 'vista_tabla'):
            self.vista_tabla.cargar_productos(self.gestor.productos)
            self.lbl_total_tabla.config(text=f"Total: {len(self.gestor.productos)} artículos")
        
        Notificacion.mostrar(self.root, mensaje, 'exito')
    
    
    def enlazar_imagenes_bulk(self):
        """Abre selección de carpeta para enlazar imágenes masivamente"""
        carpeta = filedialog.askdirectory(title="Seleccionar carpeta con imágenes (organizadas por SKU)")
        if not carpeta:
            return
            
        Notificacion.mostrar(self.root, "⏳ Enlazando imágenes...", 'info')
        self.root.update()
        
        count = self.gestor.enlazar_imagenes_por_sku(carpeta)
        
        if count > 0:
            self.gestor.guardar_excel()
            self.vista_tabla.cargar_productos(self.gestor.productos)
            Notificacion.mostrar(self.root, f"✅ {count} productos enlazados", 'exito')
            messagebox.showinfo("Éxito", f"Se han enlazado imágenes para {count} productos.\nEl Excel ha sido actualizado.")
        else:
            Notificacion.mostrar(self.root, "⚠️ No se encontraron coincidencias", 'aviso')
            messagebox.showwarning("Aviso", "No se encontraron carpetas o archivos que coincidan con los SKUs actuales.")

    def nuevo_producto(self):
        self.producto_actual = None
        self.entry_sku.delete(0, tk.END)
        self.combo_marca.set('')
        self.combo_categoria.set('')
        self.combo_subcategoria.set('')
        self.entry_nombre.delete(0, tk.END)
        self.entry_precio.delete(0, tk.END)
        self.entry_color.delete(0, tk.END)
        self.entry_dimensiones.delete(0, tk.END)
        self.combo_festividad.set('Sin festividad')
        self.text_descripcion.delete(1.0, tk.END)
        self.chk_es_variante.clear()
        
        # Limpiar logística
        self.ent_peso_prod.delete(0, tk.END); self.ent_peso_prod.insert(0, "0.5")
        self.ent_l_prod.delete(0, tk.END); self.ent_l_prod.insert(0, "20")
        self.ent_w_prod.delete(0, tk.END); self.ent_w_prod.insert(0, "15")
        self.ent_h_prod.delete(0, tk.END); self.ent_h_prod.insert(0, "10")

        self.imagenes_seleccionadas = []
        self.preview_imagen.mostrar_placeholder()
        Notificacion.mostrar(self.root, "📄 Limpio", 'info')
    
            
    def recargar_datos_tabla(self):
        """Recarga los datos de la base de datos SQL y actualiza la tabla"""
        if self.gestor.cargar_productos():
            if hasattr(self, 'vista_tabla'):
                self.vista_tabla.cargar_productos(self.gestor.productos)
                self.lbl_total_tabla.config(text=f"Total: {len(self.gestor.productos)} artículos")
            self.panel_filtros.actualizar_contador(len(self.gestor.productos))
            self.actualizar_vistas_combo()
            Notificacion.mostrar(self.root, "🔄 Datos sincronizados con SQL", 'exito')
            
    def eliminar_producto_actual(self):
        """Elimina el producto actual de la base de datos"""
        if not self.producto_actual:
            Notificacion.mostrar(self.root, "⚠️ Selecciona producto", 'aviso')
            return
            
        sku = self.producto_actual.get('SKU_REF', '')
        if messagebox.askyesno("Confirmar Eliminación", f"¿Eliminar el producto {sku} permanentemente del Excel?\nEsta acción no se puede deshacer."):
            # Eliminar ficha física
            
            # Eliminar del gestor
            self.gestor.eliminar_producto(sku)
            self.gestor.guardar_excel()
            
            # Limpiar UI
            self.nuevo_producto()
            
            # Actualizar tabla y contador
            if hasattr(self, 'vista_tabla'):
                self.vista_tabla.cargar_productos(self.gestor.productos)
                self.lbl_total_tabla.config(text=f"Total: {len(self.gestor.productos)} artículos")
            self.panel_filtros.actualizar_contador(len(self.gestor.productos))
            
            Notificacion.mostrar(self.root, "🗑️ Producto eliminado", 'exito')
    
    def on_filtrar(self, filtros, tipo):
        res = self.gestor.productos.copy()
        if tipo == 'sku':
            p = self.gestor.buscar_producto(filtros.get('sku'))
            if p:
                res = [p]
                self.on_seleccionar_producto(p)
        elif tipo != 'limpiar':
            for k, v in filtros.items():
                if v:
                    key_map = {
                        'categoria': 'CATEGORIA',
                        'subcategoria': 'SUBCATEGORIA',
                        'marca': 'MARCA',
                        'color': 'COLOR',
                        'festividad': 'FESTIVIDAD',
                        'estado': 'ESTADO'
                    }
                    data_key = key_map.get(k, k.upper())
                    res = [p for p in res if str(p.get(data_key, '')).lower() == str(v).lower()]
        
        self.panel_filtros.actualizar_contador(len(res))
        if hasattr(self, 'vista_tabla'):
            self.vista_tabla.cargar_productos(res)
            self.lbl_total_tabla.config(text=f"Total: {len(res)} artículos")
    def on_seleccionar_producto(self, producto):
        self.producto_actual = producto
        self.imagenes_seleccionadas = [] # Limpiar selección previa de imágenes
        
        img = producto.get('FOTO_PORTADA', '')
        if img and os.path.exists(img):
            self.preview_imagen.mostrar_imagen(img)
        
        # Bloquear actualizaciones de SKU durante la carga inicial de los widgets
        self.bloquear_actualizaciones = True
        
        self.entry_sku.delete(0, tk.END)
        self.entry_sku.insert(0, producto.get('SKU_REF', ''))
        self.combo_marca.set(producto.get('MARCA', ''))
        self.combo_categoria.set(producto.get('CATEGORIA', ''))
        self.actualizar_subcategorias()
        self.combo_subcategoria.set(producto.get('SUBCATEGORIA', ''))
        self.entry_nombre.delete(0, tk.END)
        self.entry_nombre.insert(0, producto.get('NOMBRE', ''))
        self.entry_precio.delete(0, tk.END)
        self.entry_precio.insert(0, str(producto.get('PRECIO', '')))
        
        # Cargar logística
        self.ent_peso_prod.delete(0, tk.END)
        self.ent_peso_prod.insert(0, str(producto.get('peso_envio', '0.5')))
        self.ent_l_prod.delete(0, tk.END)
        self.ent_l_prod.insert(0, str(producto.get('largo_envio', '20')))
        self.ent_w_prod.delete(0, tk.END)
        self.ent_w_prod.insert(0, str(producto.get('ancho_envio', '15')))
        self.ent_h_prod.delete(0, tk.END)
        self.ent_h_prod.insert(0, str(producto.get('alto_envio', '10')))
        self.entry_color.delete(0, tk.END)
        self.entry_color.insert(0, producto.get('COLOR', ''))
        self.entry_dimensiones.delete(0, tk.END)
        self.entry_dimensiones.insert(0, producto.get('DIMENSIONES', ''))
        self.combo_festividad.set(producto.get('FESTIVIDAD', ''))
        self.text_descripcion.delete(1.0, tk.END)
        self.text_descripcion.insert(1.0, producto.get('DESCRIPCION', ''))
        
        # Checkbox Variante
        es_variante = producto.get('ES_VARIANTE', 'BASE') == 'VARIANTE'
        self.chk_es_variante.set(es_variante)
        
        self.bloquear_actualizaciones = False
        
        # Actualizar Panel Variantes
        self.actualizar_panel_variantes(producto.get('SKU_REF', ''))
    
    def on_seleccionar_desde_tabla(self, producto):
        self.on_seleccionar_producto(producto)
        # Opcional: El usuario pidió botón, así que quitamos el auto-salto si prefiere darle al botón
        # o lo dejamos para que haga ambos. El usuario dijo "no habre", quizás el select(2) falla.
        # Probemos select(widget) que es más seguro.
        try:
            self.notebook.select(self.tab_editor)
        except:
            self.notebook.select(2)
    
    def preparar_nueva_variante(self):
        """Toma el producto actual y prepara el form para una variante"""
        if not self.producto_actual:
            Notificacion.mostrar(self.root, "⚠️ Selecciona un producto base", 'aviso')
            return
            
        p = self.producto_actual
        sku_base = p.get('SKU_REF', '').split('-')[0]
        
        self.combo_marca.set(p.get('MARCA', ''))
        self.combo_categoria.set(p.get('CATEGORIA', ''))
        self.actualizar_subcategorias()
        self.combo_subcategoria.set(p.get('SUBCATEGORIA', ''))
        
        self.entry_nombre.delete(0, tk.END)
        self.entry_nombre.insert(0, p.get('NOMBRE', ''))
        self.entry_precio.delete(0, tk.END)
        self.entry_precio.insert(0, str(p.get('PRECIO', '')))
        
        self.entry_color.delete(0, tk.END)
        self.entry_color.insert(0, "NUEVO_COLOR")
        self.chk_es_variante.set(True)
        
        self.imagenes_seleccionadas = []
        self.preview_imagen.mostrar_placeholder()
        
        from modulo6_correcciones import generar_sku_correcto
        # Extraer el numero del SKU base de forma robusta (todos los digitos al final)
        import re
        match = re.search(r'(\d+)$', sku_base)
        numero_base = match.group(1) if match else "0001"
        
        nuevo_sku = generar_sku_correcto(
            p.get('MARCA', ''), 
            p.get('CATEGORIA', ''), 
            p.get('SUBCATEGORIA', ''), 
            numero_base, 
            "NUEVO_COLOR"
        )
        
        self.entry_sku.delete(0, tk.END)
        self.entry_sku.insert(0, nuevo_sku)
        self.producto_actual = None
        Notificacion.mostrar(self.root, "✨ Preparado para nueva variante", 'info')
    
    def ayuda_api_key(self):
        messagebox.showinfo("API Keys", "Puedes gestionar tus llaves de Gemini y Groq en la nueva pestaña '🛠️ Herramientas'.\nRecuerda guardar los cambios para aplicarlos.")
    
    def acerca_de(self):
        messagebox.showinfo("Acerca de", "🎨 Catálogo Noxertez v3.5\n\n✅ Gestión de Stock con Fotos\n✅ IA Multiclave (Gemini/Groq)\n✅ Registro de Proyectos Futuros\n✅ Sincronización Web móvil")

    def crear_tab_stock(self):
        self.tab_stock = TabStock(self.notebook, self.gestor, self)
        self.notebook.add(self.tab_stock.frame, text="5. 📦 Stock e Inventario")

    def crear_tab_herramientas(self):
        self.tab_herr = TabHerramientas(self.notebook, self)
        self.notebook.add(self.tab_herr.tab_frame, text="6. 🛠️ Herramientas")

    def crear_tab_ventas(self):
        self.tab_ventas = TabVentas(self.notebook, self.gestor, self)
        self.notebook.add(self.tab_ventas.frame, text="7. 💰 Ventas")

class TabWhatsApp:
    """Pestaña para compartir productos por WhatsApp con filtros avanzados"""
    def __init__(self, parent, gestor_productos, app):
        self.parent = parent
        self.gestor = gestor_productos
        self.app = app
        self.frame = tk.Frame(parent, bg=COLOR_FONDO)
        self.productos_filtrados = []
        self.var_contenido = tk.StringVar(value="ficha") # "imagen", "ficha", "pdf"
        self.var_usar_real = tk.BooleanVar(value=False)
        self.clientes = []  # Cache de clientes
        
        self.crear_interfaz()
        self.recargar_clientes()
        
    def crear_interfaz(self):
        # Panel superior: Filtros
        filtros_frame = ttk.LabelFrame(self.frame, text="🔍 Buscar Producto para Enviar")
        filtros_frame.pack(fill=tk.X, padx=10, pady=10)
        
        f_inner = tk.Frame(filtros_frame, bg=COLOR_FONDO)
        f_inner.pack(fill=tk.X, padx=5, pady=5)
        
        # Filtros en una fila
        tk.Label(f_inner, text="Categoría:", font=('Segoe UI', 10, 'bold'), bg=COLOR_FONDO).grid(row=0, column=0, padx=5)
        self.combo_cat = ttk.Combobox(f_inner, values=[''] + list(CATEGORIAS.keys()), width=15)
        self.combo_cat.grid(row=0, column=1, padx=5)
        self.combo_cat.bind('<<ComboboxSelected>>', self.actualizar_subcats)
        
        tk.Label(f_inner, text="Subcat:", font=('Segoe UI', 10, 'bold'), bg=COLOR_FONDO).grid(row=0, column=2, padx=5)
        self.combo_sub = ttk.Combobox(f_inner, width=15)
        self.combo_sub.grid(row=0, column=3, padx=5)
        
        tk.Label(f_inner, text="Color:", font=('Segoe UI', 10, 'bold'), bg=COLOR_FONDO).grid(row=0, column=4, padx=5)
        self.entry_color = ttk.Entry(f_inner, width=12)
        self.entry_color.grid(row=0, column=5, padx=5)
        
        btn_buscar = tk.Button(f_inner, text="🔎 Filtrar", command=self.filtrar, bg=COLOR_MORADO, fg='white', padx=10)
        btn_buscar.grid(row=0, column=6, padx=10)
        
        # Lista de productos (Centro)
        main_middle = tk.Frame(self.frame, bg=COLOR_FONDO)
        main_middle.pack(fill=tk.BOTH, expand=True, padx=10, pady=5)
        
        list_frame = tk.Frame(main_middle, bg='white')
        list_frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        scroll = ttk.Scrollbar(list_frame)
        scroll.pack(side=tk.RIGHT, fill=tk.Y)
        
        self.tree = ttk.Treeview(list_frame, columns=('SKU', 'Nombre', 'Precio', 'Color'), show='headings', yscrollcommand=scroll.set)
        self.tree.heading('SKU', text='SKU')
        self.tree.heading('Nombre', text='Nombre')
        self.tree.heading('Precio', text='Precio')
        self.tree.heading('Color', text='Color')
        
        self.tree.column('SKU', width=150)
        self.tree.column('Nombre', width=300)
        self.tree.column('Precio', width=80)
        self.tree.column('Color', width=100)
        
        self.tree.pack(fill=tk.BOTH, expand=True)
        scroll.config(command=self.tree.yview)
        self.tree.bind('<<TreeviewSelect>>', self.on_seleccionar_lista)
        
        # Preview a la derecha
        self.preview_wa = PreviewImagenSeleccionada(main_middle)
        self.preview_wa.frame.pack(side=tk.RIGHT, fill=tk.Y, padx=(10, 0))
        self.preview_wa.frame.config(text="🖼️ Vista Previa (Ficha/Imagen)")
        self.preview_wa.mostrar_placeholder()
        
        # Panel inferior: WhatsApp
        wa_frame = ttk.LabelFrame(self.frame, text="📲 Enviar por WhatsApp")
        wa_frame.pack(fill=tk.X, padx=10, pady=10)
        
        wa_inner = tk.Frame(wa_frame, bg=COLOR_FONDO)
        wa_inner.pack(fill=tk.X, padx=5, pady=5)
        
        # Opciones de contenido (Fila 1)
        opts_frame = tk.Frame(wa_inner, bg=COLOR_FONDO)
        opts_frame.pack(fill=tk.X, pady=5)
        
        tk.Label(opts_frame, text="¿Qué enviar?:", font=('Segoe UI', 10, 'bold'), bg=COLOR_FONDO).pack(side=tk.LEFT, padx=5)
        tk.Radiobutton(opts_frame, text="🖼️ Foto Portada", variable=self.var_contenido, value="imagen", bg=COLOR_FONDO, font=('Segoe UI', 10)).pack(side=tk.LEFT, padx=5)
        tk.Radiobutton(opts_frame, text="🎨 Ficha PNG", variable=self.var_contenido, value="ficha", bg=COLOR_FONDO, font=('Segoe UI', 10)).pack(side=tk.LEFT, padx=5)
        
        # Checkbox Real (Fila 2)
        real_frame = tk.Frame(wa_inner, bg=COLOR_FONDO)
        real_frame.pack(fill=tk.X, pady=5)
        
        tk.Checkbutton(real_frame, text="🚀 Enviar Archivo Real (pywhatkit - requiere PC libre)", 
                       variable=self.var_usar_real, bg=COLOR_FONDO, font=('Segoe UI', 10, 'bold'), 
                       fg=COLOR_MORADO).pack(side=tk.LEFT, padx=5)
        
        # Teléfono y Botón (Fila 3)
        send_frame = tk.Frame(wa_inner, bg=COLOR_FONDO)
        send_frame.pack(fill=tk.X, pady=5)
        
        tk.Label(send_frame, text="👤 Cliente:", font=('Segoe UI', 10, 'bold'), bg=COLOR_FONDO).pack(side=tk.LEFT, padx=5)
        self.combo_cliente = ttk.Combobox(send_frame, font=('Segoe UI', 10), state="readonly", width=25)
        self.combo_cliente.pack(side=tk.LEFT, padx=5)
        self.combo_cliente.bind('<<ComboboxSelected>>', self.on_cliente_selected)
        
        btn_add_cli = tk.Button(send_frame, text="➕", command=self.gestionar_clientes, bg=COLOR_FONDO, font=('Segoe UI', 8))
        btn_add_cli.pack(side=tk.LEFT, padx=2)
        
        tk.Label(send_frame, text="📱 Tel:", font=('Segoe UI', 10, 'bold'), bg=COLOR_FONDO).pack(side=tk.LEFT, padx=(10, 5))
        self.entry_tel = ttk.Entry(send_frame, font=('Segoe UI', 11), width=15)
        self.entry_tel.pack(side=tk.LEFT, padx=5)
        
        btn_send = tk.Button(send_frame, text="🚀 ENVIAR AHORA", command=self.enviar_wa, 
                             bg='#25d366', fg='white', font=('Segoe UI', 11, 'bold'), padx=20, pady=5)
        btn_send.pack(side=tk.RIGHT, padx=10)
        
        # Ajustes de API (Fila 4 - Expandible)
        api_frame = tk.Frame(wa_inner, bg=COLOR_FONDO)
        api_frame.pack(fill=tk.X, pady=(10, 0))
        
        tk.Label(api_frame, text="🔑 API Key ImgBB:", font=('Segoe UI', 9), bg=COLOR_FONDO).pack(side=tk.LEFT, padx=5)
        
        from modulo11_whatsapp import obtener_api_key_actual
        self.entry_api_key = ttk.Entry(api_frame, font=('Segoe UI', 9), width=30)
        self.entry_api_key.insert(0, obtener_api_key_actual())
        self.entry_api_key.pack(side=tk.LEFT, padx=5)
        
        btn_save_api = tk.Button(api_frame, text="💾 Guardar Key", command=self.guardar_api_key,
                                 bg=COLOR_FONDO, font=('Segoe UI', 8), padx=10)
        btn_save_api.pack(side=tk.LEFT, padx=5)
        
        tk.Label(api_frame, text="(Consigue una gratis en imgbb.com)", font=('Segoe UI', 8, 'italic'), 
                 fg='#666', bg=COLOR_FONDO).pack(side=tk.LEFT, padx=5)

    def actualizar_subcats(self, event=None):
        cat = self.combo_cat.get()
        if cat in CATEGORIAS:
            # Combinar sugerencias estáticas con las del Excel
            sugerencias = set(CATEGORIAS[cat])
            for p in self.gestor.productos:
                if p.get('CATEGORIA') == cat and p.get('SUBCATEGORIA'):
                    sugerencias.add(p.get('SUBCATEGORIA'))
            
            self.combo_sub['values'] = sorted(list(sugerencias))
        else:
            # Si es una categoría nueva, buscar subcategorías usadas para ella
            sugerencias = set()
            for p in self.gestor.productos:
                if p.get('CATEGORIA') == cat and p.get('SUBCATEGORIA'):
                    sugerencias.add(p.get('SUBCATEGORIA'))
            
            if sugerencias:
                self.combo_sub['values'] = sorted(list(sugerencias))
            else:
                self.combo_sub['values'] = []
            
        self.combo_sub.set('')

    def guardar_api_key(self):
        nueva_key = self.entry_api_key.get().strip()
        if not nueva_key:
            messagebox.showwarning("Aviso", "La API Key no puede estar vacía")
            return
            
        from modulo11_whatsapp import actualizar_api_key
        if actualizar_api_key(nueva_key):
            Notificacion.mostrar(self.app.root, "✅ API Key guardada correctamente", 'exito')
        else:
            messagebox.showerror("Error", "No se pudo guardar la API Key")

    def filtrar(self):
        cat = self.combo_cat.get().lower()
        sub = self.combo_sub.get().lower()
        color = self.entry_color.get().lower()
        
        res = []
        for p in self.gestor.productos:
            match_cat = not cat or p.get('CATEGORIA', '').lower() == cat
            match_sub = not sub or p.get('SUBCATEGORIA', '').lower() == sub
            match_color = not color or color in p.get('COLOR', '').lower()
            
            if match_cat and match_sub and match_color:
                res.append(p)
        
        self.productos_filtrados = res
        self.actualizar_tabla()

    def actualizar_tabla(self):
        for item in self.tree.get_children():
            self.tree.delete(item)
            
        for p in self.productos_filtrados:
            self.tree.insert('', tk.END, values=(
                p.get('SKU_REF', ''),
                p.get('NOMBRE', ''),
                f"{p.get('PRECIO', '0')}€",
                p.get('COLOR', '')
            ))
        
        if not self.productos_filtrados:
            self.preview_wa.mostrar_placeholder()

    def on_seleccionar_lista(self, event=None):
        selected = self.tree.selection()
        if not selected:
            return
            
        idx = self.tree.index(selected[0])
        producto = self.productos_filtrados[idx]
        
        # Prioridad: Ficha PNG si existe, si no, foto portada
        sku = producto.get('SKU_REF', '')
        sku_limpio = sku.replace('/', '_')
        posibles_fichas = [
            os.path.join('fichas', f"FICHA_{sku_limpio}_VARIANTES.png"),
            os.path.join('fichas', f"FICHA_{sku_limpio}.png"),
            os.path.join('fichas', f"FICHA_{sku_limpio.split('-')[0]}_VARIANTES.png")
        ]
        
        ruta_imagen = None
        for f in posibles_fichas:
            if os.path.exists(f):
                ruta_imagen = f
                break
        
        if not ruta_imagen:
            ruta_imagen = producto.get('FOTO_PORTADA')
            
        if ruta_imagen and os.path.exists(ruta_imagen):
            self.preview_wa.mostrar_imagen(ruta_imagen)
        else:
            self.preview_wa.mostrar_placeholder()

    def recargar_clientes(self):
        """Carga los clientes de la DB al combo"""
        self.clientes = self.gestor.get_clientes()
        self.combo_cliente['values'] = ["-- Seleccionar Cliente --"] + [f"{c['nombre']} ({c['telefono']})" for c in self.clientes]
        self.combo_cliente.current(0)

    def on_cliente_selected(self, event=None):
        idx = self.combo_cliente.current()
        if idx > 0:
            cliente = self.clientes[idx-1]
            self.entry_tel.delete(0, tk.END)
            self.entry_tel.insert(0, cliente['telefono'])

    def gestionar_clientes(self):
        """Abre ventana para añadir/editar clientes"""
        win = tk.Toplevel(self.app.root)
        win.title("👥 Directorio de Clientes")
        win.geometry("500x600")
        win.configure(bg=COLOR_FONDO)
        
        tk.Label(win, text="👥 Mis Clientes", font=("Segoe UI", 14, "bold"), bg=COLOR_FONDO).pack(pady=10)
        
        # Lista
        frame_list = tk.Frame(win, bg='white')
        frame_list.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)
        
        tree = ttk.Treeview(frame_list, columns=('Nombre', 'Teléfono'), show='headings', height=10)
        tree.heading('Nombre', text='Nombre')
        tree.heading('Teléfono', text='Teléfono')
        tree.column('Nombre', width=200)
        tree.column('Teléfono', width=150)
        tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        sc = ttk.Scrollbar(frame_list, orient="vertical", command=tree.yview)
        sc.pack(side=tk.RIGHT, fill=tk.Y)
        tree.configure(yscrollcommand=sc.set)
        
        def refresh():
            for i in tree.get_children(): tree.delete(i)
            for c in self.gestor.get_clientes():
                tree.insert('', tk.END, values=(c['nombre'], c['telefono']), tags=(c['id'],))
        
        refresh()
        
        # Formulario
        form = tk.Frame(win, bg=COLOR_FONDO)
        form.pack(fill=tk.X, padx=20, pady=10)
        
        tk.Label(form, text="Nombre:", bg=COLOR_FONDO).grid(row=0, column=0, sticky='w')
        ent_nom = ttk.Entry(form)
        ent_nom.grid(row=0, column=1, sticky='ew', padx=5, pady=2)
        
        tk.Label(form, text="Teléfono:", bg=COLOR_FONDO).grid(row=1, column=0, sticky='w')
        ent_tel = ttk.Entry(form)
        ent_tel.grid(row=1, column=1, sticky='ew', padx=5, pady=2)
        
        def save():
            nom = ent_nom.get()
            tel = ent_tel.get()
            if nom and tel:
                if self.gestor.guardar_cliente(None, nom, tel):
                    messagebox.showinfo("Éxito", "Cliente guardado")
                    ent_nom.delete(0, tk.END)
                    ent_tel.delete(0, tk.END)
                    refresh()
                    self.recargar_clientes()
        
        def delete():
            sel = tree.selection()
            if sel:
                # Obtener ID del tag si lo pusimos o buscar por valores
                vals = tree.item(sel[0])['values']
                # Buscamos en la lista de la DB
                for c in self.gestor.get_clientes():
                    if c['nombre'] == vals[0] and c['telefono'] == str(vals[1]):
                        if messagebox.askyesno("Confirmar", "¿Eliminar cliente?"):
                            self.gestor.eliminar_cliente(c['id'])
                            refresh()
                            self.recargar_clientes()
                        break

        btn_f = tk.Frame(win, bg=COLOR_FONDO)
        btn_f.pack(fill=tk.X, padx=20, pady=10)
        
        tk.Button(btn_f, text="💾 Añadir/Guardar", command=save, bg=COLOR_MORADO, fg='white', padx=10).pack(side=tk.LEFT, padx=5)
        tk.Button(btn_f, text="🗑️ Eliminar Seleccionado", command=delete, bg='#9f1239', fg='white', padx=10).pack(side=tk.LEFT, padx=5)

    def enviar_wa(self):
        selected = self.tree.selection()
        if not selected:
            messagebox.showwarning("Aviso", "Selecciona un producto de la lista primero")
            return
            
        idx = self.tree.index(selected[0])
        producto = self.productos_filtrados[idx]
        telefono = self.entry_tel.get().strip()
        contenido = self.var_contenido.get()
        usar_real = self.var_usar_real.get()
        
        # Validación obligatoria para pywhatkit
        if usar_real and not telefono:
            messagebox.showwarning("Aviso", "El modo 'Enviar Archivo Real' requiere obligatoriamente un número de teléfono (ej: 34600112233) para poder entrar en el chat y pegar la imagen.")
            return
        
        sku = producto.get('SKU_REF', '')
        sku_limpio = sku.replace('/', '_')
        
        ruta_archivo = None
        
        # 1. Determinar qué archivo enviar
        if contenido == "imagen":
            ruta_archivo = producto.get('FOTO_PORTADA')
        
        elif contenido == "ficha":
            posibles = [
                os.path.join('fichas', f"FICHA_{sku_limpio}_VARIANTES.png"),
                os.path.join('fichas', f"FICHA_{sku_limpio}.png"),
                os.path.join('fichas', f"FICHA_{sku_limpio.split('-')[0]}_VARIANTES.png")
            ]
            for p in posibles:
                if os.path.exists(p):
                    ruta_archivo = p
                    break
        
        
        # 2. Verificar si el archivo existe
        if contenido != "pdf" and (not ruta_archivo or not os.path.exists(ruta_archivo)):
            if not messagebox.askyesno("Confirmar", f"No se encontró el archivo de {contenido}. ¿Enviar solo texto?"):
                return
            ruta_archivo = None
            
        # 3. Proceder al envío
        try:
            from modulo11_whatsapp import compartir_producto_whatsapp
            
            msg_status = "🚀 Enviando archivo real..." if usar_real else "⏳ Abriendo WhatsApp..."
            Notificacion.mostrar(self.app.root, msg_status, 'info')
            self.app.root.update()
            
            exito = compartir_producto_whatsapp(
                producto, 
                ruta_archivo, 
                numero_telefono=telefono,
                usar_pywhatkit=usar_real
            )
            
            if exito:
                Notificacion.mostrar(self.app.root, "✅ WhatsApp Procesado", 'exito')
            else:
                Notificacion.mostrar(self.app.root, "❌ Fallo en el envío", 'error')
                
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo completar la operación: {e}")
            import traceback
            traceback.print_exc()

# ========================================
# MAIN
# ========================================

if __name__ == "__main__":
    root = tk.Tk()
    app = CatalogoApp(root)
    root.mainloop()
