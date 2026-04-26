"""
MÓDULO 22 — FLUJO VISUAL DE PEDIDOS
Diagrama de producción estilo pipeline para el cliente de escritorio.
Tkinter con Canvas propio.
"""

import tkinter as tk
from tkinter import ttk, messagebox
from datetime import datetime, timedelta

try:
    from modulo3_gestion import get_db_connection
    from modulo2_interfaz import COLOR_FONDO, COLOR_MORADO, COLOR_VERDE
    from modulo5_mejoras_visuales import BotonConFeedback, Notificacion
except ImportError:
    def get_db_connection(): return None
    COLOR_FONDO  = "#f3f4f6"
    COLOR_MORADO = "#8b5cf6"
    COLOR_VERDE  = "#10b981"
    class BotonConFeedback:
        def __init__(self, parent, texto, comando, color, icono):
            self.btn = tk.Button(parent, text=f"{icono} {texto}", command=comando,
                                 bg=color, fg="white", font=("Segoe UI", 10, "bold"), padx=10)
        def pack(self, **kwargs): self.btn.pack(**kwargs)
    class Notificacion:
        @staticmethod
        def mostrar(root, msg, tipo): messagebox.showinfo(tipo, msg)

# ── COLORES DEL CANVAS ─────────────────────────────────────────────────────
BG_CANVAS   = "#0d1628"
NODO_W      = 160          # ancho de cada nodo
NODO_H      = 90           # alto de cada nodo
NODO_GAP_X  = 200          # distancia entre nodos (centro a centro)
NODO_GAP_Y  = 140          # salto de fila
COLS_MAX    = 5            # máximo nodos por fila antes de saltar
OFFSET_X    = 40           # margen izquierdo
OFFSET_Y    = 50           # margen superior

COLORES_ESTADO = {
    "pendiente"  : ("#1e293b", "#64748b", "#475569"),   # bg, border, text
    "en_curso"   : ("#1e3a5f", "#3b82f6", "#bfdbfe"),
    "completado" : ("#064e3b", "#10b981", "#a7f3d0"),
    "bloqueado"  : ("#450a0a", "#ef4444", "#fca5a5"),
}

ESTADOS_KANBAN_PEDIDO = [
    "por_empezar", "en_proceso", "montado",
    "tintado", "barnizado", "listo_para_entregar",
    "entregado", "cancelado",
]

TIPOS_INCIDENCIA = ["rotura", "reclamacion", "retraso", "material", "otro"]


# ══════════════════════════════════════════════════════════════════════════
class GestorFlujo:
    """Acceso a BD para el módulo de flujos."""

    # ── PEDIDOS ACTIVOS ────────────────────────────────────────────────
    def obtener_pedidos_activos(self):
        conn = get_db_connection()
        if not conn: return []
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute("""
                SELECT p.id, p.numero_pedido, p.estado, p.prioridad,
                       p.canal_origen, p.fecha_pedido, p.fecha_entrega_prometida,
                       COALESCE(c.nombre, p.nombre_cliente, 'Sin cliente') AS cliente_nombre,
                       p.id_flujo_plantilla
                FROM pedidos p
                LEFT JOIN clientes c ON p.id_cliente = c.id
                WHERE p.estado NOT IN ('entregado','cancelado')
                ORDER BY p.prioridad DESC, p.fecha_pedido ASC
            """)
            return cur.fetchall()
        finally:
            conn.close()

    # ── FLUJO DE UN PEDIDO ─────────────────────────────────────────────
    def obtener_flujo(self, id_pedido):
        conn = get_db_connection()
        if not conn: return None
        try:
            cur = conn.cursor(dictionary=True)
            # Datos del pedido
            cur.execute("""
                SELECT p.*, COALESCE(c.nombre, p.nombre_cliente,'Sin cliente') AS cliente_nombre
                FROM pedidos p LEFT JOIN clientes c ON p.id_cliente = c.id
                WHERE p.id = %s
            """, (id_pedido,))
            pedido = cur.fetchone()
            if not pedido: return None

            # Nodos
            cur.execute("""
                SELECT pn.id, pn.estado, pn.fecha_inicio, pn.fecha_fin,
                       pn.notas, pn.tiempo_real_minutos,
                       fnp.id AS id_nodo_plantilla, fnp.orden, fnp.nombre,
                       fnp.icono, fnp.color, fnp.tiempo_estimado_min, fnp.tipo,
                       (SELECT COUNT(*) FROM pedido_nodo_incidencias
                        WHERE id_pedido_nodo = pn.id AND resuelto = 0) AS inc_abiertas
                FROM pedido_nodos pn
                JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                WHERE pn.id_pedido = %s
                ORDER BY fnp.orden ASC
            """, (id_pedido,))
            nodos = cur.fetchall()

            return {"pedido": pedido, "nodos": nodos}
        finally:
            conn.close()

    # ── PLANTILLAS ─────────────────────────────────────────────────────
    def obtener_plantillas(self):
        conn = get_db_connection()
        if not conn: return []
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute("SELECT id, nombre FROM flujo_plantillas WHERE activo=1 ORDER BY nombre")
            return cur.fetchall()
        finally:
            conn.close()

    def asignar_plantilla(self, id_pedido, id_plantilla):
        conn = get_db_connection()
        if not conn: return False
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute("SELECT * FROM flujo_nodos_plantilla WHERE id_plantilla=%s ORDER BY orden", (id_plantilla,))
            nodos = cur.fetchall()
            for i, n in enumerate(nodos):
                estado_init = "en_curso" if i == 0 else "pendiente"
                fecha_init  = datetime.now().strftime("%Y-%m-%d %H:%M:%S") if i == 0 else None
                cur.execute("""
                    INSERT IGNORE INTO pedido_nodos
                      (id_pedido, id_nodo_plantilla, estado, fecha_inicio)
                    VALUES (%s, %s, %s, %s)
                """, (id_pedido, n["id"], estado_init, fecha_init))
            cur.execute("UPDATE pedidos SET id_flujo_plantilla=%s WHERE id=%s", (id_plantilla, id_pedido))
            conn.commit()
            return True
        except Exception as e:
            print(f"[Flujo] Error asignando plantilla: {e}")
            conn.rollback()
            return False
        finally:
            conn.close()

    # ── ACTUALIZAR ESTADO NODO ────────────────────────────────────────
    def actualizar_nodo(self, id_nodo, estado, notas=None, tiempo=None):
        conn = get_db_connection()
        if not conn: return
        try:
            cur = conn.cursor(dictionary=True)
            if estado == "completado":
                cur.execute("""
                    UPDATE pedido_nodos SET estado=%s, fecha_fin=NOW(),
                    tiempo_real_minutos=TIMESTAMPDIFF(MINUTE, fecha_inicio, NOW())
                    WHERE id=%s
                """, (estado, id_nodo))
                # Activar siguiente nodo
                cur.execute("""
                    SELECT pn.id_pedido, fnp.orden FROM pedido_nodos pn
                    JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                    WHERE pn.id = %s
                """, (id_nodo,))
                row = cur.fetchone()
                if row:
                    cur.execute("""
                        UPDATE pedido_nodos pn
                        JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla = fnp.id
                        SET pn.estado='en_curso', pn.fecha_inicio=NOW()
                        WHERE pn.id_pedido=%s AND fnp.orden > %s AND fnp.tipo='nodo'
                        ORDER BY fnp.orden ASC LIMIT 1
                    """, (row["id_pedido"], row["orden"]))
            elif estado == "en_curso":
                cur.execute("UPDATE pedido_nodos SET estado=%s, fecha_inicio=COALESCE(fecha_inicio,NOW()) WHERE id=%s",
                            (estado, id_nodo))
            else:
                cur.execute("UPDATE pedido_nodos SET estado=%s WHERE id=%s", (estado, id_nodo))

            if notas is not None:
                cur.execute("UPDATE pedido_nodos SET notas=%s WHERE id=%s", (notas, id_nodo))
            if tiempo is not None:
                cur.execute("UPDATE pedido_nodos SET tiempo_real_minutos=%s WHERE id=%s", (tiempo, id_nodo))
            conn.commit()
        finally:
            conn.close()

    # ── REGISTRAR INCIDENCIA ───────────────────────────────────────────
    def registrar_incidencia(self, id_nodo, tipo, descripcion):
        conn = get_db_connection()
        if not conn: return
        try:
            cur = conn.cursor()
            cur.execute("UPDATE pedido_nodos SET estado='bloqueado' WHERE id=%s", (id_nodo,))
            cur.execute(
                "INSERT INTO pedido_nodo_incidencias (id_pedido_nodo,tipo,descripcion) VALUES (%s,%s,%s)",
                (id_nodo, tipo, descripcion)
            )
            conn.commit()
        finally:
            conn.close()

    # ── ANALYTICS BÁSICO ──────────────────────────────────────────────
    def obtener_cuellos_botella(self):
        conn = get_db_connection()
        if not conn: return []
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute("""
                SELECT fnp.nombre, COUNT(pn.id) AS total,
                       SUM(pn.estado='bloqueado') AS bloqueados
                FROM pedido_nodos pn
                JOIN flujo_nodos_plantilla fnp ON pn.id_nodo_plantilla=fnp.id
                WHERE pn.estado IN ('en_curso','bloqueado')
                GROUP BY fnp.id HAVING total >= 1
                ORDER BY bloqueados DESC, total DESC LIMIT 5
            """)
            return cur.fetchall()
        finally:
            conn.close()


# ══════════════════════════════════════════════════════════════════════════
class TabFlujoVisual(tk.Frame):
    """Pestaña principal del módulo de Flujo de Pedidos."""

    def __init__(self, parent, app_main=None):
        super().__init__(parent, bg=COLOR_FONDO)
        self.app_main  = app_main
        self.gestor    = GestorFlujo()
        self.pedido_id = None      # ID pedido seleccionado
        self.flujo     = None      # datos del flujo actual
        self.nodo_rects = {}       # id_nodo → (rect_id, texto_id, ...) en canvas
        self._crear_interfaz()
        self._cargar_pedidos()

    # ── INTERFAZ ───────────────────────────────────────────────────────
    def _crear_interfaz(self):
        # ── Toolbar ─────────────────────────────────────────────────
        tb = tk.Frame(self, bg="#0f172a", pady=8, padx=16)
        tb.pack(fill=tk.X)

        tk.Label(tb, text="🔀 FLUJO VISUAL DE PRODUCCIÓN",
                 font=("Segoe UI", 14, "bold"), bg="#0f172a", fg="#C89B3C").pack(side=tk.LEFT)

        BotonConFeedback(tb, "Actualizar", self._cargar_pedidos, COLOR_VERDE, "🔄").pack(side=tk.RIGHT, padx=4)
        BotonConFeedback(tb, "Analítica", self._ver_analitica, "#6366f1", "📊").pack(side=tk.RIGHT, padx=4)
        BotonConFeedback(tb, "Asignar Plantilla", self._abrir_asignar_plantilla, "#f59e0b", "📋").pack(side=tk.RIGHT, padx=4)

        # ── Selector de pedido ──────────────────────────────────────
        sel_frame = tk.Frame(self, bg=COLOR_FONDO, pady=6, padx=16)
        sel_frame.pack(fill=tk.X)

        tk.Label(sel_frame, text="Pedido:", font=("Segoe UI", 11, "bold"), bg=COLOR_FONDO).pack(side=tk.LEFT, padx=(0, 8))

        self.var_pedido = tk.StringVar()
        self.cb_pedido  = ttk.Combobox(sel_frame, textvariable=self.var_pedido,
                                        state="readonly", width=55)
        self.cb_pedido.pack(side=tk.LEFT)
        self.cb_pedido.bind("<<ComboboxSelected>>", self._on_pedido_seleccionado)

        self.lbl_info = tk.Label(sel_frame, text="", font=("Segoe UI", 10),
                                  bg=COLOR_FONDO, fg="#64748b")
        self.lbl_info.pack(side=tk.LEFT, padx=16)

        # ── Canvas principal ────────────────────────────────────────
        canvas_frame = tk.Frame(self, bg=BG_CANVAS)
        canvas_frame.pack(fill=tk.BOTH, expand=True)

        self.canvas = tk.Canvas(canvas_frame, bg=BG_CANVAS,
                                 highlightthickness=0, cursor="hand2")
        scroll_x = ttk.Scrollbar(canvas_frame, orient=tk.HORIZONTAL, command=self.canvas.xview)
        scroll_y = ttk.Scrollbar(canvas_frame, orient=tk.VERTICAL,   command=self.canvas.yview)

        self.canvas.configure(xscrollcommand=scroll_x.set, yscrollcommand=scroll_y.set)

        scroll_x.pack(side=tk.BOTTOM, fill=tk.X)
        scroll_y.pack(side=tk.RIGHT,  fill=tk.Y)
        self.canvas.pack(fill=tk.BOTH, expand=True)

        # ── Panel lateral ───────────────────────────────────────────
        self.panel = tk.Frame(self, bg="#0f172a", width=300)
        # No se empaqueta hasta que se hace click en un nodo

    # ── CARGA DE PEDIDOS ──────────────────────────────────────────────
    def _cargar_pedidos(self):
        pedidos = self.gestor.obtener_pedidos_activos()
        self._pedidos_dict = {p["id"]: p for p in pedidos}
        vals = [
            f"#{p['id']:04d} — {p['cliente_nombre']} ({p['estado'].replace('_',' ')})"
            for p in pedidos
        ]
        self.cb_pedido["values"] = vals
        self._pedidos_ids = [p["id"] for p in pedidos]

        if self.pedido_id and self.pedido_id in self._pedidos_dict:
            idx = self._pedidos_ids.index(self.pedido_id)
            self.cb_pedido.current(idx)
            self._dibujar_flujo()
        elif vals:
            self.cb_pedido.current(0)
            self.pedido_id = self._pedidos_ids[0]
            self._dibujar_flujo()

    def _on_pedido_seleccionado(self, _=None):
        idx = self.cb_pedido.current()
        if idx < 0: return
        self.pedido_id = self._pedidos_ids[idx]
        self._dibujar_flujo()

    # ── DIBUJO DEL FLUJO ──────────────────────────────────────────────
    def _dibujar_flujo(self):
        self.canvas.delete("all")
        self.nodo_rects.clear()
        self._cerrar_panel()

        if not self.pedido_id: return

        self.flujo = self.gestor.obtener_flujo(self.pedido_id)
        if not self.flujo:
            self.canvas.create_text(300, 150, text="Sin datos de flujo para este pedido",
                                     fill="#475569", font=("Segoe UI", 14))
            return

        pedido = self.flujo["pedido"]
        nodos  = self.flujo["nodos"]

        if not nodos:
            self._dibujar_sin_flujo()
            return

        # Info del pedido en la barra
        dias = ""
        if pedido.get("fecha_pedido"):
            try:
                fp   = pedido["fecha_pedido"]
                if isinstance(fp, str): fp = datetime.strptime(fp.split()[0], "%Y-%m-%d")
                dias = f"  |  Día {(datetime.now()-fp).days} del pedido"
            except: pass

        entrega = ""
        if pedido.get("fecha_entrega_prometida"):
            try:
                fe = pedido["fecha_entrega_prometida"]
                if isinstance(fe, str): fe = datetime.strptime(str(fe), "%Y-%m-%d")
                diff = (fe - datetime.now()).days
                color_e = "#10b981" if diff > 2 else ("#f59e0b" if diff >= 0 else "#ef4444")
                entrega = f"  |  Entrega: {diff}d"
            except: pass

        self.lbl_info.config(text=f"Cliente: {pedido['cliente_nombre']}{dias}{entrega}")

        # Separar nodos principales e incidencias
        principales  = [n for n in nodos if n["tipo"] == "nodo"]
        incidencias  = [n for n in nodos if n["tipo"] == "incidencia"]

        # ── Dibujar nodos principales ─────────────────────────────
        for idx, nodo in enumerate(principales):
            col = idx % COLS_MAX
            row = idx // COLS_MAX
            x   = OFFSET_X + col * NODO_GAP_X + NODO_W // 2
            y   = OFFSET_Y + row * NODO_GAP_Y + NODO_H // 2
            self._dibujar_nodo(nodo, x, y)

            # Línea al siguiente
            if idx < len(principales) - 1:
                n2  = principales[idx + 1]
                c2  = (idx + 1) % COLS_MAX
                r2  = (idx + 1) // COLS_MAX
                x2  = OFFSET_X + c2 * NODO_GAP_X + NODO_W // 2
                y2  = OFFSET_Y + r2 * NODO_GAP_Y + NODO_H // 2
                if row == r2:
                    # misma fila → línea horizontal
                    self.canvas.create_line(
                        x + NODO_W//2, y, x2 - NODO_W//2, y2,
                        fill="#334155", width=2, arrow=tk.LAST, arrowshape=(10,12,4)
                    )
                else:
                    # salto de fila → curva en L
                    mid_x = x + NODO_W//2 + 20
                    self.canvas.create_line(
                        x + NODO_W//2, y, mid_x, y,
                        fill="#334155", width=2
                    )
                    self.canvas.create_line(
                        mid_x, y, mid_x, y2,
                        fill="#334155", width=2
                    )
                    self.canvas.create_line(
                        mid_x, y2, x2 - NODO_W//2, y2,
                        fill="#334155", width=2, arrow=tk.LAST, arrowshape=(10,12,4)
                    )

        # ── Dibujar nodos de incidencia (rama lateral) ────────────
        for idx, nodo in enumerate(incidencias):
            # Posicionar a la derecha del último nodo
            cols  = min(len(principales), COLS_MAX)
            x_inc = OFFSET_X + cols * NODO_GAP_X + NODO_W // 2 + 30
            y_inc = OFFSET_Y + idx * NODO_GAP_Y + NODO_H // 2
            self._dibujar_nodo(nodo, x_inc, y_inc, incidencia=True)

            # Conectar con nodo bloqueado si existe
            bloq = next((n for n in principales if n["estado"] == "bloqueado"), None)
            if bloq and bloq["id"] in self.nodo_rects:
                bx, by = self.nodo_rects[bloq["id"]]["center"]
                self.canvas.create_line(
                    bx + NODO_W//2, by, x_inc - NODO_W//2, y_inc,
                    fill="#ef4444", width=2, dash=(6, 4), arrow=tk.LAST, arrowshape=(10,12,4)
                )

        # Actualizar scrollregion
        self.canvas.update_idletasks()
        self.canvas.configure(scrollregion=self.canvas.bbox("all"))

    def _dibujar_nodo(self, nodo, cx, cy, incidencia=False):
        """Dibuja un nodo en el canvas y guarda refs para click."""
        estado = nodo.get("estado", "pendiente")
        colors = COLORES_ESTADO.get(estado, COLORES_ESTADO["pendiente"])
        bg, border, fg = colors

        x0, y0 = cx - NODO_W//2, cy - NODO_H//2
        x1, y1 = cx + NODO_W//2, cy + NODO_H//2

        dash = (6, 4) if incidencia else None

        # Sombra
        self.canvas.create_rectangle(x0+4, y0+4, x1+4, y1+4, fill="#050b18", outline="", tags="shadow")

        # Fondo
        rect = self.canvas.create_rectangle(
            x0, y0, x1, y1, fill=bg, outline=border, width=2,
            tags=("nodo", f"n_{nodo['id']}")
        )
        if dash:
            self.canvas.itemconfig(rect, dash=dash)

        # Barra de color superior (indica estado)
        self.canvas.create_rectangle(
            x0, y0, x1, y0+6, fill=border, outline="", tags=f"n_{nodo['id']}"
        )

        # Nombre del nodo
        self.canvas.create_text(
            cx, cy - 16, text=nodo["nombre"], fill=fg,
            font=("Segoe UI", 9, "bold"), width=NODO_W - 16,
            tags=f"n_{nodo['id']}"
        )

        # Badge de estado
        estado_txt = {"pendiente":"Pendiente","en_curso":"En Curso",
                       "completado":"✓ Hecho","bloqueado":"⚠ Bloqueado"}.get(estado, estado)
        self.canvas.create_text(
            cx, cy + 6, text=estado_txt, fill=border,
            font=("Segoe UI", 8), tags=f"n_{nodo['id']}"
        )

        # Tiempo
        if nodo.get("tiempo_estimado_min"):
            txt_t = f"{nodo['tiempo_real_minutos'] or 0}m / {nodo['tiempo_estimado_min']}m"
        else:
            txt_t = ""
        if txt_t:
            self.canvas.create_text(
                cx, cy + 22, text=txt_t, fill="#64748b",
                font=("Segoe UI", 7), tags=f"n_{nodo['id']}"
            )

        # Indicador de incidencias abiertas
        if nodo.get("inc_abiertas"):
            self.canvas.create_oval(x1-18, y0+2, x1-2, y0+18, fill="#ef4444", outline="")
            self.canvas.create_text(x1-10, y0+10, text=str(nodo["inc_abiertas"]),
                                     fill="white", font=("Segoe UI", 7, "bold"))

        # Guardar refs y bind click
        self.nodo_rects[nodo["id"]] = {"rect": rect, "center": (cx, cy), "nodo": nodo}
        tag = f"n_{nodo['id']}"
        self.canvas.tag_bind(tag, "<Button-1>", lambda e, n=nodo: self._abrir_panel_nodo(n))
        self.canvas.tag_bind(tag, "<Enter>",
                              lambda e, r=rect: self.canvas.itemconfig(r, outline="#C89B3C"))
        self.canvas.tag_bind(tag, "<Leave>",
                              lambda e, r=rect, b=border: self.canvas.itemconfig(r, outline=b))

    def _dibujar_sin_flujo(self):
        self.canvas.create_text(
            400, 150,
            text="Este pedido no tiene flujo asignado\n\nUsa el botón 'Asignar Plantilla'",
            fill="#475569", font=("Segoe UI", 14), justify=tk.CENTER
        )

    # ── PANEL LATERAL ─────────────────────────────────────────────────
    def _abrir_panel_nodo(self, nodo):
        """Muestra información detallada del nodo en una ventana emergente."""
        win = tk.Toplevel(self)
        win.title(f"Nodo: {nodo['nombre']}")
        win.geometry("420x500")
        win.configure(bg="#0f172a")
        win.transient(self.winfo_toplevel())

        # Título
        tk.Label(win, text=nodo["nombre"], font=("Segoe UI", 14, "bold"),
                  bg="#0f172a", fg="#C89B3C").pack(pady=(16, 0))
        tk.Label(win, text=f"Estado: {nodo['estado'].upper()}", font=("Segoe UI", 11),
                  bg="#0f172a",
                  fg=COLORES_ESTADO.get(nodo["estado"], COLORES_ESTADO["pendiente"])[1]).pack(pady=4)

        ttk.Separator(win).pack(fill=tk.X, padx=16, pady=8)

        body = tk.Frame(win, bg="#0f172a")
        body.pack(fill=tk.BOTH, expand=True, padx=20)

        # Estado
        tk.Label(body, text="Cambiar Estado:", font=("Segoe UI", 10, "bold"),
                  bg="#0f172a", fg="#94a3b8").pack(anchor="w", pady=(4, 0))
        v_estado = tk.StringVar(value=nodo["estado"])
        cb_e = ttk.Combobox(body, textvariable=v_estado,
                             values=["pendiente","en_curso","completado","bloqueado"],
                             state="readonly", width=25)
        cb_e.pack(anchor="w", pady=4)

        # Tiempo real
        tk.Label(body, text="Tiempo real (minutos):", font=("Segoe UI", 10, "bold"),
                  bg="#0f172a", fg="#94a3b8").pack(anchor="w", pady=(8, 0))
        ent_t = ttk.Entry(body, width=10)
        ent_t.insert(0, str(nodo.get("tiempo_real_minutos") or 0))
        ent_t.pack(anchor="w", pady=4)

        # Notas
        tk.Label(body, text="Notas:", font=("Segoe UI", 10, "bold"),
                  bg="#0f172a", fg="#94a3b8").pack(anchor="w", pady=(8, 0))
        txt_n = tk.Text(body, height=5, bg="#1e293b", fg="#e2e8f0",
                         font=("Segoe UI", 10), relief=tk.FLAT, padx=6, pady=6)
        txt_n.insert("1.0", nodo.get("notas") or "")
        txt_n.pack(fill=tk.X, pady=4)

        # Tiempos info
        if nodo.get("tiempo_estimado_min"):
            tk.Label(body,
                      text=f"⏱ Estimado: {nodo['tiempo_estimado_min']} min  |  Real: {nodo.get('tiempo_real_minutos',0)} min",
                      font=("Segoe UI", 9), bg="#0f172a", fg="#64748b").pack(anchor="w")

        # Botones
        btn_frame = tk.Frame(body, bg="#0f172a")
        btn_frame.pack(fill=tk.X, pady=12)

        def guardar():
            estado = v_estado.get()
            notas  = txt_n.get("1.0", tk.END).strip()
            try: tiempo = int(ent_t.get())
            except: tiempo = 0
            self.gestor.actualizar_nodo(nodo["id"], estado, notas, tiempo)
            win.destroy()
            self._dibujar_flujo()
            Notificacion.mostrar(self, "Nodo actualizado", "exito")

        def completar():
            notas  = txt_n.get("1.0", tk.END).strip()
            try: tiempo = int(ent_t.get())
            except: tiempo = 0
            self.gestor.actualizar_nodo(nodo["id"], "completado", notas, tiempo)
            win.destroy()
            self._dibujar_flujo()
            Notificacion.mostrar(self, "✅ Nodo completado — Siguiente activado", "exito")

        def registrar_inc():
            self._dialogo_incidencia(nodo, win)

        tk.Button(btn_frame, text="✅ COMPLETAR NODO",
                   font=("Segoe UI", 10, "bold"), bg="#059669", fg="white",
                   relief=tk.FLAT, padx=10, pady=8, command=completar).pack(fill=tk.X, pady=2)
        tk.Button(btn_frame, text="💾 Guardar Estado/Notas",
                   font=("Segoe UI", 10), bg="#1e3a5f", fg="#60a5fa",
                   relief=tk.FLAT, padx=10, pady=6, command=guardar).pack(fill=tk.X, pady=2)
        tk.Button(btn_frame, text="⚠️ Registrar Incidencia",
                   font=("Segoe UI", 10), bg="#450a0a", fg="#f87171",
                   relief=tk.FLAT, padx=10, pady=6, command=registrar_inc).pack(fill=tk.X, pady=2)

    def _cerrar_panel(self):
        if self.panel.winfo_ismapped():
            self.panel.pack_forget()

    # ── DIÁLOGO INCIDENCIA ────────────────────────────────────────────
    def _dialogo_incidencia(self, nodo, parent_win=None):
        if parent_win: parent_win.destroy()
        win = tk.Toplevel(self)
        win.title("Registrar Incidencia")
        win.geometry("360x280")
        win.configure(bg="#0f172a")
        win.transient(self.winfo_toplevel())
        win.grab_set()

        tk.Label(win, text="⚠️ REGISTRAR INCIDENCIA", font=("Segoe UI", 12, "bold"),
                  bg="#0f172a", fg="#ef4444").pack(pady=12)

        tk.Label(win, text="Tipo:", bg="#0f172a", fg="#94a3b8").pack(anchor="w", padx=20)
        v_tipo = tk.StringVar(value="rotura")
        cb_t = ttk.Combobox(win, textvariable=v_tipo, values=TIPOS_INCIDENCIA, state="readonly", width=22)
        cb_t.pack(anchor="w", padx=20, pady=4)

        tk.Label(win, text="Descripción:", bg="#0f172a", fg="#94a3b8").pack(anchor="w", padx=20, pady=(8,0))
        txt_d = tk.Text(win, height=4, bg="#1e293b", fg="#e2e8f0", font=("Segoe UI", 10),
                         relief=tk.FLAT, padx=6, pady=6)
        txt_d.pack(fill=tk.X, padx=20, pady=4)

        def guardar():
            desc = txt_d.get("1.0", tk.END).strip()
            if not desc:
                messagebox.showwarning("Aviso", "Añade una descripción", parent=win)
                return
            self.gestor.registrar_incidencia(nodo["id"], v_tipo.get(), desc)
            win.destroy()
            self._dibujar_flujo()
            Notificacion.mostrar(self, "Incidencia registrada — Nodo bloqueado", "error")

        tk.Button(win, text="Registrar Incidencia", font=("Segoe UI", 11, "bold"),
                   bg="#dc2626", fg="white", relief=tk.FLAT, padx=12, pady=8,
                   command=guardar).pack(pady=16)

    # ── ASIGNAR PLANTILLA ─────────────────────────────────────────────
    def _abrir_asignar_plantilla(self):
        if not self.pedido_id:
            messagebox.showwarning("Aviso", "Selecciona un pedido primero")
            return

        plantillas = self.gestor.obtener_plantillas()
        if not plantillas:
            messagebox.showinfo("Info", "No hay plantillas de flujo disponibles en la base de datos")
            return

        win = tk.Toplevel(self)
        win.title("Asignar Plantilla de Flujo")
        win.geometry("380x200")
        win.configure(bg="#0f172a")
        win.transient(self.winfo_toplevel())
        win.grab_set()

        tk.Label(win, text="📋 Selecciona la plantilla de producción",
                  font=("Segoe UI", 11, "bold"), bg="#0f172a", fg="#C89B3C").pack(pady=16)

        v_pt = tk.StringVar()
        pt_dict = {p["nombre"]: p["id"] for p in plantillas}
        cb_pt = ttk.Combobox(win, textvariable=v_pt, values=list(pt_dict.keys()),
                              state="readonly", width=35)
        cb_pt.current(0)
        cb_pt.pack(pady=6)

        def asignar():
            id_pt = pt_dict.get(v_pt.get())
            if not id_pt: return
            ok = self.gestor.asignar_plantilla(self.pedido_id, id_pt)
            win.destroy()
            if ok:
                self._dibujar_flujo()
                Notificacion.mostrar(self, "✅ Flujo generado correctamente", "exito")
            else:
                messagebox.showerror("Error", "No se pudo asignar la plantilla")

        tk.Button(win, text="⚡ Generar Flujo", font=("Segoe UI", 11, "bold"),
                   bg="#d97706", fg="white", relief=tk.FLAT, padx=16, pady=8,
                   command=asignar).pack(pady=16)

    # ── ANALÍTICA ─────────────────────────────────────────────────────
    def _ver_analitica(self):
        cuellos = self.gestor.obtener_cuellos_botella()

        win = tk.Toplevel(self)
        win.title("Analítica de Flujos")
        win.geometry("500x350")
        win.configure(bg="#0f172a")

        tk.Label(win, text="📊 CUELLOS DE BOTELLA ACTUALES",
                  font=("Segoe UI", 13, "bold"), bg="#0f172a", fg="#f59e0b").pack(pady=12)

        if not cuellos:
            tk.Label(win, text="Sin datos todavía", font=("Segoe UI", 11),
                      bg="#0f172a", fg="#475569").pack(pady=40)
            return

        container = tk.Frame(win, bg="#0f172a")
        container.pack(fill=tk.BOTH, expand=True, padx=20)

        tk.Label(container, text="Nodo", font=("Segoe UI", 10, "bold"),
                  bg="#0f172a", fg="#94a3b8", width=24, anchor="w").grid(row=0, column=0, sticky="w")
        tk.Label(container, text="Total", font=("Segoe UI", 10, "bold"),
                  bg="#0f172a", fg="#94a3b8", width=8).grid(row=0, column=1)
        tk.Label(container, text="Bloqueados", font=("Segoe UI", 10, "bold"),
                  bg="#0f172a", fg="#94a3b8", width=10).grid(row=0, column=2)

        for i, c in enumerate(cuellos):
            bloq = int(c.get("bloqueados") or 0)
            color = "#ef4444" if bloq > 0 else "#10b981"
            tk.Label(container, text=c["nombre"], font=("Segoe UI", 10),
                      bg="#0f172a", fg="#e2e8f0", width=24, anchor="w").grid(row=i+1, column=0, sticky="w", pady=4)
            tk.Label(container, text=str(c["total"]), font=("Segoe UI", 10),
                      bg="#0f172a", fg="#94a3b8", width=8).grid(row=i+1, column=1)
            tk.Label(container, text=str(bloq), font=("Segoe UI", 10, "bold"),
                      bg="#0f172a", fg=color, width=10).grid(row=i+1, column=2)
