import tkinter as tk
from tkinter import ttk, messagebox
import json
import os
from datetime import datetime
from PIL import Image, ImageTk

# Importar constantes y utilidades de otros módulos

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

ESTADOS_KANBAN = [
    ('Por empezar', "#cfe2ff", "#084298"),
    ('En proceso', "#fff3cd", "#856404"),
    ('Secado/Horno', "#f8d7da", "#721c24"),
    ('Acabado/Barniz', "#d1e7dd", "#0f5132"),
    ('Listo para entrega', "#d2f4ea", "#0a58ca")
]

class GestorPedidos:
    def __init__(self, db_path=DB_PATH):
        self.db_path = db_path

    def obtener_pedidos(self):
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute('''SELECT p.*, c.nombre as cliente_nombre, pr.FOTO_PORTADA, pr.NOMBRE as articulo_nombre
                        FROM pedidos p 
                        JOIN clientes c ON p.id_cliente = c.id
                        LEFT JOIN productos pr ON p.sku_articulo = pr.SKU_REF
                        WHERE p.estado != 'Entregado'
                        ORDER BY p.prioridad DESC, p.fecha_pedido ASC''')
        rows = [r for r in cursor.fetchall()]
        conn.close()
        return rows

    def actualizar_estado(self, id_pedido, nuevo_estado):
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        now = datetime.now().strftime("%Y-%m-%d %H:%M")
        
        updates = {"estado": nuevo_estado}
        if nuevo_estado == 'En proceso':
            updates["fecha_inicio"] = now
        elif nuevo_estado == 'Listo para entrega':
            updates["fecha_fin"] = now
            
        set_clause = ", ".join([f"{k} = ?" for k in updates.keys()])
        params = list(updates.values()) + [id_pedido]
        cursor.execute(f"UPDATE pedidos SET {set_clause} WHERE id = %s", params)
        conn.commit()
        conn.close()

class TabPedidos(tk.Frame):
    def __init__(self, parent, app_main=None):
        super().__init__(parent, bg=COLOR_FONDO)
        self.app_main = app_main
        self.gestor = GestorPedidos()
        self.img_cache = {} # Para evitar que el GC borre las imágenes
        self._crear_interfaz()
        self.cargar_datos()

    def _crear_interfaz(self):
        # Toolbar superior
        toolbar = tk.Frame(self, bg=COLOR_FONDO, pady=10, padx=20)
        toolbar.pack(fill=tk.X)
        
        tk.Label(toolbar, text="📋 TABLERO KANBAN DE PRODUCCIÓN", font=("Segoe UI", 16, "bold"), bg=COLOR_FONDO).pack(side=tk.LEFT)
        
        # Contador de piezas
        self.lbl_contador = tk.Label(toolbar, text="Piezas hoy: 0", font=("Segoe UI", 12, "bold"), bg="#1e293b", fg="white", padx=15, pady=5)
        self.lbl_contador.pack(side=tk.LEFT, padx=30)

        BotonConFeedback(toolbar, "Nuevo Pedido", self.abrir_crear_pedido, COLOR_MORADO, "➕").pack(side=tk.RIGHT, padx=5)
        BotonConFeedback(toolbar, "Actualizar", self.cargar_datos, COLOR_VERDE, "🔄").pack(side=tk.RIGHT, padx=5)

        # Contenedor Kanban (Scrollable horizontal)
        self.canvas = tk.Canvas(self, bg=COLOR_FONDO, highlightthickness=0)
        self.scroll_x = ttk.Scrollbar(self, orient=tk.HORIZONTAL, command=self.canvas.xview)
        
        # El contenedor interno es donde vive el kanban_container
        self.kanban_container = tk.Frame(self.canvas, bg=COLOR_FONDO)
        self.canvas_frame = self.canvas.create_window((0, 0), window=self.kanban_container, anchor="nw")
        
        # Vincular evento de redimensionado del Canvas para que el Frame interno cubra el alto
        self.canvas.bind("<Configure>", self._on_canvas_configure)
        
        self.canvas.configure(xscrollcommand=self.scroll_x.set)
        self.canvas.pack(fill=tk.BOTH, expand=True)
        self.scroll_x.pack(fill=tk.X)
        
        self.columnas = {}
        for nombre, bg, fg in ESTADOS_KANBAN:
            # Columna con ancho fijo pero alto flexible
            col_outer = tk.Frame(self.kanban_container, bg="#e2e8f0", width=300)
            col_outer.pack(side=tk.LEFT, fill=tk.Y, padx=10, pady=10)
            col_outer.pack_propagate(False) # Mantener el ancho de 300
            
            header = tk.Frame(col_outer, bg=bg, pady=10)
            header.pack(fill=tk.X)
            tk.Label(header, text=nombre.upper(), font=("Segoe UI", 11, "bold"), bg=bg, fg=fg).pack()
            
            # Area de tarjetas (ésta sí debe expandirse para empujar el alto del col_outer)
            list_frame = tk.Frame(col_outer, bg="#e2e8f0")
            list_frame.pack(fill=tk.BOTH, expand=True)
            self.columnas[nombre] = list_frame

    def _on_canvas_configure(self, event):
        # Asegurar que el frame interno tenga al menos el alto del canvas
        self.canvas.itemconfig(self.canvas_frame, height=event.height)

    def cargar_datos(self):
        pedidos = self.gestor.obtener_pedidos()
        
        # Limpiar columnas
        for col in self.columnas.values():
            for child in col.winfo_children(): child.destroy()
            
        contador_hoy = 0
        for p in pedidos:
            self._crear_tarjeta(p)
            if p['estado'] != 'Listo para entrega':
                contador_hoy += 1
        
        self.lbl_contador.config(text=f"Piezas hoy: {contador_hoy}")
        self.update_idletasks()
        self.canvas.config(scrollregion=self.canvas.bbox("all"))

    def _crear_tarjeta(self, pedido):
        estado = pedido.get('estado', 'Por empezar')
        if estado not in self.columnas: 
            estado = 'Por empezar' # Fallback
        
        parent = self.columnas[estado]
        
        # Color semáforo
        border_color = "#ddd"
        if pedido['prioridad'] == 'Rojo': border_color = "#ef4444"
        elif pedido['prioridad'] == 'Amarillo': border_color = "#f59e0b"
        elif pedido['prioridad'] == 'Verde': border_color = "#10b981"
        
        card = tk.Frame(parent, bg="white", highlightthickness=2, highlightbackground=border_color, pady=10, padx=10)
        card.pack(fill=tk.X, padx=5, pady=5)
        
        # Header: ID y Cliente
        tk.Label(card, text=f"Pedido #{pedido['id']:04d}", font=("Segoe UI", 10, "bold"), bg="white", fg="#64748b").pack(anchor="w")
        tk.Label(card, text=pedido['cliente_nombre'], font=("Segoe UI", 12, "bold"), bg="white", wraplength=250).pack(anchor="w")

        # Imagen del Artículo y Nombre
        art_frame = tk.Frame(card, bg="white")
        art_frame.pack(fill=tk.X, pady=5)
        
        foto = pedido.get('FOTO_PORTADA')
        if foto and os.path.exists(foto):
            try:
                img = Image.open(foto)
                img.thumbnail((60, 60), Image.Resampling.LANCZOS)
                photo = ImageTk.PhotoImage(img)
                self.img_cache[pedido['id']] = photo # Ref
                lbl_img = tk.Label(art_frame, image=photo, bg="white")
                lbl_img.pack(side=tk.LEFT, padx=(0, 10))
            except:
                pass
        
        info_art = tk.Frame(art_frame, bg="white")
        info_art.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        
        tk.Label(info_art, text=pedido.get('articulo_nombre', 'N/A'), font=("Segoe UI", 9, "bold"), bg="white", fg="#475569", wraplength=180, justify="left").pack(anchor="w")
        tk.Label(info_art, text=f"SKU: {pedido.get('sku_articulo', '--')}", font=("Consolas", 8), bg="white", fg="#94a3b8").pack(anchor="w")
        
        # Detalles Críticos
        crit_text = pedido.get('detalles_criticos')
        if crit_text and str(crit_text).strip():
            crit_frame = tk.Frame(card, bg="#fef2f2", padx=5, pady=5)
            crit_frame.pack(fill=tk.X, pady=5)
            tk.Label(crit_frame, text=f"⚠️ {crit_text}", font=("Segoe UI", 9, "bold"), bg="#fef2f2", fg="#b91c1c", wraplength=230).pack()

        # Notas / Trabajo
        notas_text = pedido.get('notas')
        if notas_text and str(notas_text).strip():
            note_frame = tk.Frame(card, bg="#f0f9ff", padx=5, pady=5)
            note_frame.pack(fill=tk.X, pady=(0, 5))
            tk.Label(note_frame, text=f"📝 {notas_text}", font=("Segoe UI", 9), bg="#f0f9ff", fg="#0369a1", wraplength=230).pack()

        # Footer: Fecha y Botones de Movimiento
        footer = tk.Frame(card, bg="white", pady=5)
        footer.pack(fill=tk.X)
        
        fecha_str = pedido['fecha_pedido'].split()[0] if pedido['fecha_pedido'] else ""
        tk.Label(footer, text=f"📅 {fecha_str}", font=("Segoe UI", 8), bg="white", fg="#94a3b8").pack(side=tk.LEFT)
        
        # Botones de estado
        btn_next = tk.Button(footer, text="➡️", command=lambda p=pedido: self.mover_siguiente(p), bg="#f1f5f9", relief=tk.FLAT)
        btn_next.pack(side=tk.RIGHT)
        
        if estado != 'Por empezar':
            btn_prev = tk.Button(footer, text="⬅️", command=lambda p=pedido: self.mover_anterior(p), bg="#f1f5f9", relief=tk.FLAT)
            btn_prev.pack(side=tk.RIGHT, padx=5)

        # Clic para ver detalles/checklist
        card.bind("<Button-1>", lambda e, p=pedido: self.ver_detalle_pedido(p))

    def mover_siguiente(self, pedido):
        orden = [e[0] for e in ESTADOS_KANBAN] + ['Entregado']
        idx = orden.index(pedido['estado'])
        if idx < len(orden) - 1:
            self.gestor.actualizar_estado(pedido['id'], orden[idx+1])
            self.cargar_datos()

    def mover_anterior(self, pedido):
        orden = [e[0] for e in ESTADOS_KANBAN]
        idx = orden.index(pedido['estado'])
        if idx > 0:
            self.gestor.actualizar_estado(pedido['id'], orden[idx-1])
            self.cargar_datos()

    def ver_detalle_pedido(self, pedido):
        # Ventana de detalles, checklist unboxing, etc.
        win = tk.Toplevel(self)
        win.title(f"Detalle Pedido #{pedido['id']:04d}")
        win.geometry("600x700")
        win.configure(bg=COLOR_FONDO)
        
        tk.Label(win, text=f"PEDIDO PARA: {pedido['cliente_nombre']}", font=("Segoe UI", 14, "bold"), bg=COLOR_FONDO).pack(pady=20)
        
        # Checklist Unboxing
        check_frame = ttk.LabelFrame(win, text=" Checklist de Unboxing ")
        check_frame.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)
        
        items = ["Tarjeta agradecimiento", "Pegatina", "Regalo extra", "Instrucciones cuidado"]
        saved_check = json.loads(pedido['unboxing_checklist']) if pedido['unboxing_checklist'] else []
        
        vars_check = []
        for it in items:
            v = tk.BooleanVar(value=it in saved_check)
            cb = ttk.Checkbutton(check_frame, text=it, variable=v)
            cb.pack(anchor="w", padx=20, pady=5)
            vars_check.append((it, v))
            
        def guardar_checks():
            final_list = [it for it, v in vars_check if v.get()]
            conn = get_db_connection()
            conn.execute("UPDATE pedidos SET unboxing_checklist = ? WHERE id = ?", (json.dumps(final_list), pedido['id']))
            conn.commit()
            conn.close()
            messagebox.showinfo("Guardado", "Checklist actualizado")
            
        BotonConFeedback(win, "Guardar Checklist", guardar_checks, COLOR_VERDE, "✅").pack(pady=20)

    def abrir_crear_pedido(self):
        # Ventana simple para crear pedido
        win = tk.Toplevel(self)
        win.title("Nuevo Pedido")
        win.geometry("500x600")
        win.configure(bg="white")
        
        tk.Label(win, text="CLIENTE:", bg="white", font=("Segoe UI", 10, "bold")).pack(pady=(20,0))
        
        # Selector de cliente (simplificado)
        conn = get_db_connection()
        clientes = conn.execute("SELECT id, nombre FROM clientes").fetchall()
        conn.close()
        
        cli_dict = {f"{c[1]} (ID:{c[0]})": c[0] for c in clientes}
        v_cli = tk.StringVar()
        cb_cli = ttk.Combobox(win, textvariable=v_cli, values=list(cli_dict.keys()), width=40)
        cb_cli.pack(pady=5)
        
        tk.Label(win, text="PRIORIDAD:", bg="white", font=("Segoe UI", 10, "bold")).pack(pady=(15,0))
        v_prio = tk.StringVar(value="Verde")
        ttk.Combobox(win, textvariable=v_prio, values=["Rojo", "Amarillo", "Verde"], width=15).pack(pady=5)
        
        tk.Label(win, text="DETALLES CRÍTICOS / PERSONALIZACIÓN:", bg="white", font=("Segoe UI", 10, "bold")).pack(pady=(15,0))
        txt_crit = tk.Text(win, height=4, width=50, font=("Segoe UI", 10))
        txt_crit.pack(pady=5, padx=20)
        
        def save():
            if not v_cli.get(): return
            id_cli = cli_dict[v_cli.get()]
            crit = txt_crit.get("1.0", tk.END).strip()
            prio = v_prio.get()
            now = datetime.now().strftime("%Y-%m-%d %H:%M")
            
            conn = get_db_connection()
            conn.execute("INSERT INTO pedidos (id_cliente, fecha_pedido, prioridad, detalles_criticos, estado) VALUES (?,?,?,?,?)",
                        (id_cli, now, prio, crit, 'Por empezar'))
            conn.commit()
            conn.close()
            win.destroy()
            self.cargar_datos()
            
        BotonConFeedback(win, "Crear Pedido", save, COLOR_MORADO, "✨").pack(pady=30)
