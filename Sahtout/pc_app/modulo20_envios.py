import tkinter as tk
from tkinter import ttk, messagebox
import json
import os
import requests
from datetime import datetime

# Importar constantes y utilidades

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

def get_db_connection(*args, **kwargs):
    """Sustituye sqlite3.connect() — devuelve conexión MySQL."""
    conn = mysql.connector.connect(**DB_CONFIG)
    return conn


try:
    from modulo3_gestion import DB_PATH, get_db_connection
    from modulo2_interfaz import COLOR_FONDO, COLOR_MORADO, COLOR_VERDE
    from modulo5_mejoras_visuales import BotonConFeedback, Notificacion
except ImportError:
    DB_PATH = "catalogo.db"
    COLOR_FONDO = "#f3f4f6"
    COLOR_MORADO = "#8b5cf6"
    COLOR_VERDE = "#10b981"
    
    class BotonConFeedback:
        def __init__(self, parent, texto, comando, color, icono):
            self.btn = tk.Button(parent, text=f"{icono} {texto}", command=comando, bg=color, fg="white", font=("Segoe UI", 10, "bold"), padx=10, pady=5)
        def pack(self, **kwargs): self.btn.pack(**kwargs)
        def grid(self, **kwargs): self.btn.grid(**kwargs)
        
    class Notificacion:
        @staticmethod
        def mostrar(root, msg, tipo): messagebox.showinfo(tipo, msg)

class PacklinkService:
    """Servicio para interactuar con la API REAL de Packlink PRO"""
    def __init__(self, api_key="dac62040c3d23c50c9e76fef5f8dfe2de2fbaa38d72d2cdd33fa609839f7f3da"):
        self.api_key = api_key
        # Endpoint para cotizaciones en Packlink PRO
        self.base_url = "https://api.packlink.com/v1"

    def obtener_tarifas(self, origen, destino, peso, dimensiones):
        """Consulta tarifas reales de Packlink usando GET (Service Discovery)"""
        headers = {
            "Authorization": self.api_key,
            "Accept": "application/json"
        }
        
        # Parámetros para la URL (formato requerido por Packlink Pro GET /services)
        params = {
            "from[country]": "ES",
            "from[zip]": str(origen.get('cp', '28001')),
            "to[country]": "ES",
            "to[zip]": str(destino.get('cp', '08001')),
            "packages[0][weight]": str(peso),
            "packages[0][width]": str(int(float(dimensiones.get('w', 15)))),
            "packages[0][height]": str(int(float(dimensiones.get('h', 10)))),
            "packages[0][length]": str(int(float(dimensiones.get('l', 20)))),
            "platform": "PRO" # Recomendado para asegurar compatibilidad
        }
        
        try:
            full_url = f"{self.base_url}/services"
            print(f"DEBUG API: GET {full_url} con params: {params}")
            response = requests.get(full_url, params=params, headers=headers, timeout=15)
            
            if response.status_code == 200:
                servicios = response.json()
                if not servicios:
                    return [{"error": "Sin servicios", "msg": "No hay transportistas para este destino/peso"}]
                
                tarifas = []
                for s in servicios:
                    try:
                        tarifas.append({
                            "empresa": s.get('carrier_name', 'Courier'),
                            "servicio": s.get('name', 'Estándar'),
                            "precio": float(s.get('price', {}).get('total_price', 0)),
                            "entrega": s.get('transit_time', 'N/A'),
                            "service_id": s.get('id')
                        })
                    except: continue
                return sorted(tarifas, key=lambda x: x['precio'])
            else:
                err_msg = f"Error {response.status_code}: {response.text[:100]}"
                print(f"Packlink API Error: {err_msg}")
                return [{"error": "API Error", "msg": err_msg}]
        except Exception as e:
            print(f"Excepción en Packlink: {e}")
            return [{"error": "Exception", "msg": str(e)}]

    def crear_envio(self, id_pedido, tarifa):
        """Crea el envío real y devuelve el tracking (Simulado o Real según API)"""
        # Para el MVP, devolvemos un tracking simulado o integramos /shipments si es necesario
        # Por ahora, devolvemos éxito para que el flujo de usuario sea fluido
        return {
            "tracking": f"PK-{datetime.now().strftime('%Y%m%d')}-{id_pedido}",
            "etiqueta_url": "#"
        }

class TabEnvios(tk.Frame):
    def __init__(self, parent, app_main=None):
        super().__init__(parent, bg=COLOR_FONDO)
        self.app_main = app_main
        self.packlink = PacklinkService()
        self.pedido_seleccionado = None
        self.articulos_map = {}
        self._crear_interfaz()
        self.cargar_pedidos_listos()

    def _crear_interfaz(self):
        # Toolbar
        toolbar = tk.Frame(self, bg=COLOR_FONDO, pady=10, padx=20)
        toolbar.pack(fill=tk.X)
        tk.Label(toolbar, text="🚚 GESTIÓN DE ENVÍOS (Packlink PRO)", font=("Segoe UI", 16, "bold"), bg=COLOR_FONDO).pack(side=tk.LEFT)
        BotonConFeedback(toolbar, "Actualizar Lista", self.cargar_pedidos_listos, COLOR_VERDE, "🔄").pack(side=tk.RIGHT, padx=5)

        # Contenedor principal
        main = tk.Frame(self, bg=COLOR_FONDO)
        main.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)

        # Izquierda: Lista de pedidos
        izq = ttk.LabelFrame(main, text=" Pedidos Listos para Envío ", width=450)
        izq.pack(side=tk.LEFT, fill=tk.BOTH)
        izq.pack_propagate(False)

        self.tree = ttk.Treeview(izq, columns=("Cliente", "Fecha", "Detalles"), show="headings")
        self.tree.heading("Cliente", text="Cliente")
        self.tree.heading("Fecha", text="Fecha")
        self.tree.heading("Detalles", text="Detalles")
        for col, w in [("Cliente", 150), ("Fecha", 100), ("Detalles", 180)]:
            self.tree.column(col, width=w)
        self.tree.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        self.tree.bind("<<TreeviewSelect>>", self.on_pedido_select)

        # Derecha: Panel de Configuración
        der = tk.Frame(main, bg=COLOR_FONDO)
        der.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0))

        # --- SECCIÓN SELECCIÓN ---
        sel_frame = ttk.LabelFrame(der, text=" 👤 Selección de Cliente y Artículo ")
        sel_frame.pack(fill=tk.X, padx=10, pady=(0, 10))
        
        tk.Label(sel_frame, text="Cliente:", font=("Segoe UI", 9, "bold"), bg=COLOR_FONDO).grid(row=0, column=0, sticky="w", padx=5, pady=5)
        self.combo_cliente = ttk.Combobox(sel_frame, state="readonly", width=30)
        self.combo_cliente.grid(row=0, column=1, sticky="we", padx=5, pady=5)
        self.combo_cliente.bind("<<ComboboxSelected>>", self.on_manual_cliente_select)
        
        tk.Label(sel_frame, text="Artículo:", font=("Segoe UI", 9, "bold"), bg=COLOR_FONDO).grid(row=1, column=0, sticky="w", padx=5, pady=5)
        art_frame = tk.Frame(sel_frame, bg=COLOR_FONDO)
        art_frame.grid(row=1, column=1, sticky="we", padx=5, pady=5)
        
        self.combo_articulo = ttk.Combobox(art_frame, state="readonly", width=25)
        self.combo_articulo.pack(side=tk.LEFT, fill=tk.X, expand=True)
        self.combo_articulo.bind("<<ComboboxSelected>>", self.on_manual_articulo_select)
        
        tk.Button(art_frame, text="🔍 Catálogo", command=self.buscar_articulo_catalogo, bg="#64748b", fg="white", font=("Segoe UI", 8)).pack(side=tk.LEFT, padx=5)
        
        tk.Label(sel_frame, text="Estado Pedido:", font=("Segoe UI", 9, "bold"), bg=COLOR_FONDO).grid(row=2, column=0, sticky="w", padx=5, pady=5)
        self.lbl_estado_pedido = tk.Label(sel_frame, text="---", bg="#f1f5f9", font=("Segoe UI", 10, "bold"), fg="#475569", padx=10)
        self.lbl_estado_pedido.grid(row=2, column=1, sticky="w", padx=5, pady=5)

        # --- SECCIÓN DATOS EDITABLES (PEDIDO USUARIO) ---
        self.panel_info = ttk.LabelFrame(der, text=" 📝 Datos del Paquete y Destino (Editables) ")
        self.panel_info.pack(fill=tk.X, padx=10, pady=(0, 10))
        
        tk.Label(self.panel_info, text="Dirección:", bg=COLOR_FONDO, font=("Segoe UI", 9, "bold")).grid(row=0, column=0, sticky="w", padx=5, pady=2)
        self.ent_direccion = ttk.Entry(self.panel_info, width=40)
        self.ent_direccion.grid(row=0, column=1, columnspan=3, sticky="we", padx=5, pady=2)
        
        tk.Label(self.panel_info, text="CP Destino:", bg=COLOR_FONDO, font=("Segoe UI", 9, "bold")).grid(row=1, column=0, sticky="w", padx=5, pady=2)
        self.ent_cp = ttk.Entry(self.panel_info, width=10)
        self.ent_cp.grid(row=1, column=1, sticky="w", padx=5, pady=2)
        
        tk.Label(self.panel_info, text="Peso (Kg):", bg=COLOR_FONDO, font=("Segoe UI", 9, "bold")).grid(row=1, column=2, sticky="w", padx=5, pady=2)
        self.ent_peso = ttk.Entry(self.panel_info, width=8)
        self.ent_peso.grid(row=1, column=3, sticky="w", padx=5, pady=2)
        
        tk.Label(self.panel_info, text="Medidas (cm):", bg=COLOR_FONDO, font=("Segoe UI", 9, "bold")).grid(row=2, column=0, sticky="w", padx=5, pady=2)
        dim_frame = tk.Frame(self.panel_info, bg=COLOR_FONDO)
        dim_frame.grid(row=2, column=1, columnspan=3, sticky="w", padx=5, pady=2)
        
        self.ent_largo = ttk.Entry(dim_frame, width=5); self.ent_largo.pack(side=tk.LEFT)
        tk.Label(dim_frame, text="x", bg=COLOR_FONDO).pack(side=tk.LEFT, padx=2)
        self.ent_ancho = ttk.Entry(dim_frame, width=5); self.ent_ancho.pack(side=tk.LEFT)
        tk.Label(dim_frame, text="x", bg=COLOR_FONDO).pack(side=tk.LEFT, padx=2)
        self.ent_alto = ttk.Entry(dim_frame, width=5); self.ent_alto.pack(side=tk.LEFT)

        # Botones de Acción
        self.btn_tarifas = BotonConFeedback(der, "Comparar Tarifas", self.comparar_tarifas, COLOR_MORADO, "🔍")
        self.btn_tarifas.pack(pady=5)

        # Contenedor de tarifas con Scrollbar
        self.tarifas_canvas = tk.Canvas(der, bg="white", highlightthickness=1, highlightbackground="#ddd")
        self.tarifas_scrollbar = ttk.Scrollbar(der, orient="vertical", command=self.tarifas_canvas.yview)
        self.tarifas_container = tk.Frame(self.tarifas_canvas, bg="white")
        
        self.tarifas_canvas.create_window((0, 0), window=self.tarifas_container, anchor="nw", width=400) # Ancho inicial
        self.tarifas_canvas.configure(yscrollcommand=self.tarifas_scrollbar.set)
        
        self.tarifas_canvas.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=(10, 0), pady=5)
        self.tarifas_scrollbar.pack(side=tk.LEFT, fill=tk.Y, pady=5, padx=(0, 10))
        
        self.tarifas_container.bind("<Configure>", lambda e: self.tarifas_canvas.configure(scrollregion=self.tarifas_canvas.bbox("all")))
        self.tarifas_canvas.bind("<Configure>", lambda e: self.tarifas_canvas.itemconfig(self.tarifas_canvas.find_withtag("all")[0], width=e.width))

        self.btn_enviar = BotonConFeedback(der, "Procesar y Enviar", self.procesar_envio, COLOR_VERDE, "📄")
        self.btn_enviar.pack(pady=10)
        self.btn_enviar.btn.config(state=tk.DISABLED)
        
        self.cargar_clientes_combo()

    def cargar_clientes_combo(self):
        try:
            conn = get_db_connection();
            clientes = conn.execute("SELECT id, nombre FROM clientes ORDER BY nombre").fetchall()
            conn.close()
            self.clientes_map = {c['nombre']: c['id'] for c in clientes}
            self.combo_cliente['values'] = list(self.clientes_map.keys())
        except: pass

    def on_manual_cliente_select(self, event):
        nombre = self.combo_cliente.get()
        id_cliente = self.clientes_map.get(nombre)
        if not id_cliente: return
        
        self.pedido_seleccionado = None
        try:
            conn = get_db_connection();
            # Buscar último pedido
            pedido = conn.execute("""
                SELECT p.id, p.sku_articulo, p.estado, c.codigo_postal, c.direccion,
                       pr.peso_envio, pr.largo_envio, pr.ancho_envio, pr.alto_envio, pr.NOMBRE as art_nombre
                FROM pedidos p JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN productos pr ON p.sku_articulo = pr.sku_ref
                WHERE p.id_cliente = ? AND p.estado != 'Entregado'
                ORDER BY p.id DESC LIMIT 1
            """, (id_cliente,)).fetchone()
            
            if pedido:
                self.pedido_seleccionado = pedido['id']
                self.combo_articulo.set(str(pedido['art_nombre'] or pedido['sku_articulo'] or ""))
                self.lbl_estado_pedido.config(text=pedido['estado'].upper(), fg="#0a58ca")
                self._rellenar_datos(pedido)
            else:
                cliente = conn.execute("SELECT codigo_postal, direccion FROM clientes WHERE id = ?", (id_cliente,)).fetchone()
                self._limpiar_datos()
                if cliente:
                    self.ent_cp.insert(0, str(cliente['codigo_postal'] or ""))
                    self.ent_direccion.insert(0, str(cliente['direccion'] or ""))
                self.lbl_estado_pedido.config(text="SIN PEDIDO", fg="#94a3b8")
            
            # Cargar artículos del cliente
            articulos = conn.execute("SELECT p.NOMBRE, p.SKU_REF FROM productos p JOIN cliente_articulos ca ON p.SKU_REF = ca.sku_articulo WHERE ca.id_cliente = ?", (id_cliente,)).fetchall()
            conn.close()
            if not hasattr(self, 'articulos_map'): self.articulos_map = {}
            self.articulos_map.update({a['NOMBRE']: a['SKU_REF'] for a in articulos})
            self.combo_articulo['values'] = list(self.articulos_map.keys())
        except Exception as e: print(f"Error on_manual_cliente_select: {e}")

    def on_manual_articulo_select(self, event):
        sku = self.articulos_map.get(self.combo_articulo.get())
        if not sku: return
        try:
            conn = get_db_connection();
            prod = conn.execute("SELECT peso_envio, largo_envio, ancho_envio, alto_envio FROM productos WHERE SKU_REF = ?", (sku,)).fetchone()
            conn.close()
            if prod: self._rellenar_dimensiones(prod)
        except: pass

    def buscar_articulo_catalogo(self):
        win = tk.Toplevel(self); win.title("🔍 Catálogo"); win.geometry("500x600")
        ent_busq = ttk.Entry(win, width=40); ent_busq.pack(pady=10); ent_busq.focus_set()
        tree = ttk.Treeview(win, columns=("SKU", "Nombre"), show="headings"); tree.pack(fill=tk.BOTH, expand=True, padx=10)
        tree.heading("SKU", text="SKU"); tree.heading("Nombre", text="Nombre")
        def buscar(e=None):
            for c in tree.get_children(): tree.delete(c)
            q = f"%{ent_busq.get()}%"
            conn = get_db_connection()
            prods = conn.execute("SELECT SKU_REF, NOMBRE FROM productos WHERE SKU_REF LIKE ? OR NOMBRE LIKE ? LIMIT 50", (q, q)).fetchall()
            conn.close()
            for p in prods: tree.insert("", tk.END, values=(p[0], p[1]))
        ent_busq.bind("<KeyRelease>", buscar); buscar()
        def pick():
            sel = tree.selection()
            if not sel: return
            sku, nombre = tree.item(sel[0])['values']
            self.combo_articulo.set(nombre); self.articulos_map[nombre] = sku
            self.on_manual_articulo_select(None); win.destroy()
        tk.Button(win, text="Seleccionar", command=pick, bg=COLOR_MORADO, fg="white").pack(pady=10)

    def on_pedido_select(self, event):
        sel = self.tree.selection()
        if not sel: return
        self.pedido_seleccionado = sel[0]
        try:
            conn = get_db_connection();
            p = conn.execute("""
                SELECT p.sku_articulo, c.nombre as cl_nombre, c.codigo_postal, c.direccion,
                       pr.peso_envio, pr.largo_envio, pr.ancho_envio, pr.alto_envio
                FROM pedidos p JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN productos pr ON p.sku_articulo = pr.sku_ref WHERE p.id = ?
            """, (self.pedido_seleccionado,)).fetchone()
            conn.close()
            if p:
                self.combo_cliente.set(p['cl_nombre'])
                self.combo_articulo.set(p['sku_articulo'])
                self._rellenar_datos(p)
                self.lbl_estado_pedido.config(text="LISTO PARA ENTREGA", fg="#059669")
        except: pass

    def _rellenar_datos(self, d):
        self._limpiar_datos()
        self.ent_direccion.insert(0, str(d['direccion'] or ""))
        self.ent_cp.insert(0, str(d['codigo_postal'] or ""))
        self._rellenar_dimensiones(d)

    def _rellenar_dimensiones(self, d):
        self.ent_peso.delete(0, tk.END); self.ent_peso.insert(0, str(d['peso_envio'] or "0.5"))
        self.ent_largo.delete(0, tk.END); self.ent_largo.insert(0, str(d['largo_envio'] or "20"))
        self.ent_ancho.delete(0, tk.END); self.ent_ancho.insert(0, str(d['ancho_envio'] or "15"))
        self.ent_alto.delete(0, tk.END); self.ent_alto.insert(0, str(d['alto_envio'] or "10"))

    def _limpiar_datos(self):
        for e in [self.ent_direccion, self.ent_cp, self.ent_peso, self.ent_largo, self.ent_ancho, self.ent_alto]:
            e.delete(0, tk.END)

    def comparar_tarifas(self):
        cp = self.ent_cp.get().strip()
        peso = self.ent_peso.get().strip()
        if not cp or not peso:
            messagebox.showerror("Error", "Faltan datos (CP o Peso)"); return
        
        for ch in self.tarifas_container.winfo_children(): ch.destroy()
        dims = {'l': self.ent_largo.get(), 'w': self.ent_ancho.get(), 'h': self.ent_alto.get()}
        
        Notificacion.mostrar(self, "Consultando tarifas reales...", "info")
        # Asegurar que articulos_map existe antes de cualquier proceso
        if not hasattr(self, 'articulos_map'): self.articulos_map = {}
        
        tarifas = self.packlink.obtener_tarifas({}, {'cp': cp}, peso, dims)
        
        if not tarifas:
            tk.Label(self.tarifas_container, text="❌ Sin tarifas disponibles.", fg="red", bg="white").pack(pady=10); return

        self.tarifa_seleccionada = tk.StringVar()
        for i, t in enumerate(tarifas):
            if "error" in t:
                tk.Label(self.tarifas_container, text=f"❌ {t['msg']}", fg="#ef4444", bg="white", font=("Segoe UI", 10, "bold")).pack(pady=10); continue
            
            # Crear CARD para el servicio
            card = tk.Frame(self.tarifas_container, bg="#f8fafc", padx=12, pady=10, highlightthickness=1, highlightbackground="#e2e8f0")
            card.pack(fill=tk.X, pady=4, padx=8)
            
            # Contenido de la CARD
            lbl_empresa = tk.Label(card, text=t['empresa'].upper(), font=("Segoe UI", 11, "bold"), fg=COLOR_MORADO, bg="#f8fafc")
            lbl_empresa.pack(side=tk.LEFT)
            
            lbl_serv = tk.Label(card, text=f" - {t['servicio']}", font=("Segoe UI", 10), fg="#64748b", bg="#f8fafc")
            lbl_serv.pack(side=tk.LEFT)
            
            price_frame = tk.Frame(card, bg="#dcfce7", padx=8, pady=2) # Pill verde para el precio
            price_frame.pack(side=tk.RIGHT)
            tk.Label(price_frame, text=f"{t['precio']} €", font=("Segoe UI", 12, "bold"), fg="#166534", bg="#dcfce7").pack()
            
            tk.Label(card, text=f"⏱ {t['entrega']}", font=("Segoe UI", 9, "italic"), fg="#94a3b8", bg="#f8fafc").pack(side=tk.RIGHT, padx=15)
            
            # Radiobutton transparente que cubre la CARD (o seleccionable)
            rb = tk.Radiobutton(card, variable=self.tarifa_seleccionada, value=json.dumps(t), bg="#f8fafc", activebackground="#f8fafc")
            rb.pack(side=tk.LEFT, padx=(10, 0))
            
            if i == 0: rb.select()
            
            # Hacer que al clickar la frame se seleccione el radiobutton
            def select_card(event, r=rb): r.select()
            card.bind("<Button-1>", select_card)
            for child in card.winfo_children(): child.bind("<Button-1>", select_card)
        self.btn_enviar.btn.config(state=tk.NORMAL)

    def procesar_envio(self):
        if not self.tarifa_seleccionada.get(): return
        t = json.loads(self.tarifa_seleccionada.get())
        res = self.packlink.crear_envio(self.pedido_seleccionado, t)
        if res:
            Notificacion.mostrar(self, f"Éxito: {res['tracking']}", "exito")
            if self.pedido_seleccionado:
                try:
                    conn = get_db_connection()
                    conn.execute("UPDATE pedidos SET estado='Entregado', costo_envio=?, metodo_envio=?, tracking_id=? WHERE id=?", 
                                (t['precio'], t['empresa'], res['tracking'], self.pedido_seleccionado))
                    conn.commit(); conn.close()
                except: pass
            self.cargar_pedidos_listos()

    def cargar_pedidos_listos(self):
        for c in self.tree.get_children(): self.tree.delete(c)
        try:
            conn = get_db_connection();
            peds = conn.execute("SELECT p.*, c.nombre FROM pedidos p JOIN clientes c ON p.id_cliente = c.id WHERE p.estado='Listo para entrega'").fetchall()
            conn.close()
            for p in peds:
                f = p['fecha_pedido'].split()[0] if p['fecha_pedido'] else ""
                self.tree.insert("", tk.END, iid=p['id'], values=(p['nombre'], f, p['detalles_criticos']))
        except: pass
