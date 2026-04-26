"""
MÓDULO 2: INTERFAZ - TEMA OSCURO "WoW" v4.0
Diseño que iguala la web SahtoutCMS:
  - Fondo oscuro profundo (#0f172a)
  - Acentos morado/violeta (#6366f1, #8b5cf6)
  - Tipografía moderna (Segoe UI)
  - Botones con efecto hover luminoso
  - Tablas con filas alternadas y bordes suaves
"""

import tkinter as tk
from tkinter import ttk

# ═══════════════════════════════════════════════════════════════
# PALETA DE COLORES — idéntica a la web SahtoutCMS
# ═══════════════════════════════════════════════════════════════

COLOR_FONDO        = "#0f172a"   # Fondo principal (azul noche)
COLOR_PANEL        = "#1e293b"   # Paneles y tarjetas
COLOR_PANEL_ALT    = "#1e1b4b"   # Paneles con acento índigo
COLOR_BORDE        = "#334155"   # Bordes suaves
COLOR_MORADO       = "#6366f1"   # Violeta primario (botones, acentos)
COLOR_MORADO_HOVER = "#4f46e5"   # Hover botón primario
COLOR_VERDE        = "#10b981"   # Acento éxito / verde
COLOR_ROJO         = "#ef4444"   # Alerta / eliminar
COLOR_MARRON       = "#f59e0b"   # Advertencia / IA
COLOR_CYAN         = "#06b6d4"   # Acento secundario
COLOR_TEXTO        = "#f1f5f9"   # Texto principal (blanco suave)
COLOR_TEXTO_MUT    = "#94a3b8"   # Texto secundario (gris azulado)
COLOR_FILA_PAR     = "#1e293b"   # Fila tabla par
COLOR_FILA_IMPAR   = "#172033"   # Fila tabla impar
COLOR_SELECCION    = "#312e81"   # Fila seleccionada
COLOR_STOCK_BAJO   = "#7f1d1d"   # Fila stock bajo

FUENTE_TITULO  = ("Segoe UI", 15, "bold")
FUENTE_SUBTIT  = ("Segoe UI", 11, "bold")
FUENTE_NORMAL  = ("Segoe UI", 10)
FUENTE_PEQUENA = ("Segoe UI", 9)


# ═══════════════════════════════════════════════════════════════
# ESTILOS TTK
# ═══════════════════════════════════════════════════════════════

def configurar_estilos():
    """Configura todos los estilos ttk para el tema oscuro WoW."""
    style = ttk.Style()

    # Intentar usar clam como base (más personalizable)
    try:
        style.theme_use("clam")
    except Exception:
        pass

    # ── Fondo general ──────────────────────────────────────────
    style.configure(".",
        background=COLOR_FONDO,
        foreground=COLOR_TEXTO,
        font=FUENTE_NORMAL,
        borderwidth=0,
        relief="flat"
    )
    style.configure("TFrame", background=COLOR_FONDO)
    style.configure("TLabel", background=COLOR_FONDO, foreground=COLOR_TEXTO, font=FUENTE_NORMAL)
    style.configure("TLabelframe", background=COLOR_PANEL, foreground=COLOR_TEXTO, font=FUENTE_SUBTIT,
                    bordercolor=COLOR_BORDE, relief="groove")
    style.configure("TLabelframe.Label", background=COLOR_PANEL, foreground=COLOR_MORADO, font=FUENTE_SUBTIT)

    # ── Botón TTK ──────────────────────────────────────────────
    style.configure("TButton",
        background=COLOR_MORADO,
        foreground="white",
        font=FUENTE_SUBTIT,
        padding=(12, 6),
        relief="flat",
        borderwidth=0
    )
    style.map("TButton",
        background=[("active", COLOR_MORADO_HOVER), ("pressed", "#3730a3")],
        foreground=[("active", "white")]
    )

    # Botón verde
    style.configure("Verde.TButton", background=COLOR_VERDE, foreground="white")
    style.map("Verde.TButton", background=[("active", "#059669"), ("pressed", "#047857")])

    # Botón rojo
    style.configure("Rojo.TButton", background=COLOR_ROJO, foreground="white")
    style.map("Rojo.TButton", background=[("active", "#dc2626"), ("pressed", "#b91c1c")])

    # Botón naranja (IA)
    style.configure("IA.TButton", background=COLOR_MARRON, foreground="white")
    style.map("IA.TButton", background=[("active", "#d97706")])

    # ── Entry / Combobox ───────────────────────────────────────
    style.configure("TEntry",
        fieldbackground=COLOR_PANEL,
        foreground=COLOR_TEXTO,
        insertcolor=COLOR_TEXTO,
        bordercolor=COLOR_BORDE,
        lightcolor=COLOR_BORDE,
        darkcolor=COLOR_BORDE,
        padding=(6, 4)
    )
    style.configure("TCombobox",
        fieldbackground=COLOR_PANEL,
        foreground=COLOR_TEXTO,
        background=COLOR_PANEL,
        arrowcolor=COLOR_MORADO,
        padding=(6, 4)
    )
    style.map("TCombobox",
        fieldbackground=[("readonly", COLOR_PANEL)],
        foreground=[("readonly", COLOR_TEXTO)]
    )

    # ── Notebook (pestañas) ────────────────────────────────────
    style.configure("TNotebook",
        background=COLOR_FONDO,
        tabmargins=[4, 4, 0, 0],
        borderwidth=0
    )
    style.configure("TNotebook.Tab",
        background=COLOR_PANEL,
        foreground=COLOR_TEXTO_MUT,
        font=FUENTE_PEQUEÑA_TAB(),
        padding=[14, 6],
        borderwidth=0
    )
    style.map("TNotebook.Tab",
        background=[("selected", COLOR_MORADO), ("active", COLOR_PANEL_ALT)],
        foreground=[("selected", "white"), ("active", COLOR_TEXTO)]
    )

    # ── Treeview (tabla) ───────────────────────────────────────
    style.configure("Wow.Treeview",
        background=COLOR_FILA_PAR,
        foreground=COLOR_TEXTO,
        fieldbackground=COLOR_FILA_PAR,
        font=FUENTE_NORMAL,
        rowheight=28,
        borderwidth=0
    )
    style.configure("Wow.Treeview.Heading",
        background=COLOR_PANEL_ALT,
        foreground=COLOR_MORADO,
        font=FUENTE_SUBTIT,
        relief="flat",
        padding=[8, 6]
    )
    style.map("Wow.Treeview",
        background=[("selected", COLOR_SELECCION)],
        foreground=[("selected", "white")]
    )
    style.map("Wow.Treeview.Heading",
        background=[("active", COLOR_MORADO)],
        foreground=[("active", "white")]
    )

    # ── Scrollbar ─────────────────────────────────────────────
    style.configure("TScrollbar",
        background=COLOR_PANEL,
        troughcolor=COLOR_FONDO,
        arrowcolor=COLOR_TEXTO_MUT,
        borderwidth=0,
        relief="flat"
    )
    style.map("TScrollbar",
        background=[("active", COLOR_MORADO)]
    )

    # ── Separador ─────────────────────────────────────────────
    style.configure("TSeparator", background=COLOR_BORDE)

    # ── Progressbar ───────────────────────────────────────────
    style.configure("TProgressbar",
        troughcolor=COLOR_PANEL,
        background=COLOR_MORADO,
        borderwidth=0
    )

    # Tags para Treeview (stock bajo, etc.)
    # Se aplican en cada instancia de Treeview


def FUENTE_PEQUEÑA_TAB():
    return ("Segoe UI", 9, "bold")


# ═══════════════════════════════════════════════════════════════
# BARRA DE ESTADO SUPERIOR
# ═══════════════════════════════════════════════════════════════

class BarraEstado:
    """Barra superior con estado del servidor, logs y controles."""

    def __init__(self, parent):
        self.frame = tk.Frame(parent, bg=COLOR_PANEL_ALT, pady=6)
        self.frame.pack(fill=tk.X, side=tk.TOP)

        # Logo / título
        tk.Label(self.frame,
                 text="🎨 NOXERTEZ v4.0",
                 font=("Segoe UI", 13, "bold"),
                 bg=COLOR_PANEL_ALT, fg=COLOR_MORADO).pack(side=tk.LEFT, padx=14)

        # Separador visual
        tk.Frame(self.frame, bg=COLOR_BORDE, width=2).pack(side=tk.LEFT, fill=tk.Y, padx=6)

        # Indicador web
        self.lbl_web_icon = tk.Label(self.frame, text="⚫", font=("Segoe UI", 11),
                                     bg=COLOR_PANEL_ALT)
        self.lbl_web_icon.pack(side=tk.LEFT)
        self.lbl_web = tk.Label(self.frame, text="API desconectada",
                                font=FUENTE_PEQUENA, bg=COLOR_PANEL_ALT, fg=COLOR_TEXTO_MUT)
        self.lbl_web.pack(side=tk.LEFT, padx=4)

        # Registro de logs (texto expansible)
        self.log = tk.Label(self.frame, text="",
                            font=FUENTE_PEQUENA, bg=COLOR_PANEL_ALT, fg=COLOR_TEXTO_MUT,
                            anchor="w")
        self.log.pack(side=tk.LEFT, padx=10, fill=tk.X, expand=True)

        # Hora
        self.lbl_hora = tk.Label(self.frame, text="",
                                 font=FUENTE_PEQUENA, bg=COLOR_PANEL_ALT, fg=COLOR_TEXTO_MUT)
        self.lbl_hora.pack(side=tk.RIGHT, padx=14)
        self._actualizar_hora()

    def _actualizar_hora(self):
        from datetime import datetime
        self.lbl_hora.config(text=datetime.now().strftime("%d/%m/%Y  %H:%M:%S"))
        self.lbl_hora.after(1000, self._actualizar_hora)

    def activar_web(self, activo: bool):
        if activo:
            self.lbl_web_icon.config(text="🟢")
            self.lbl_web.config(text="MySQL / API activa", fg=COLOR_VERDE)
        else:
            self.lbl_web_icon.config(text="🔴")
            self.lbl_web.config(text="API desconectada", fg=COLOR_ROJO)

    def agregar_log(self, mensaje: str):
        self.log.config(text=mensaje)


# ═══════════════════════════════════════════════════════════════
# CHECKBOX MEJORADO
# ═══════════════════════════════════════════════════════════════

class CheckboxMejorado:
    def __init__(self, parent, texto, row, col):
        self.var = tk.BooleanVar()
        self.check = tk.Checkbutton(
            parent, text=texto, variable=self.var,
            bg=COLOR_FONDO, fg=COLOR_TEXTO, font=FUENTE_NORMAL,
            selectcolor=COLOR_MORADO, activebackground=COLOR_FONDO,
            activeforeground=COLOR_TEXTO,
            relief=tk.FLAT, cursor="hand2"
        )
        self.check.grid(row=row, column=col, sticky=tk.W, padx=5, pady=3)

    def get(self):
        return self.var.get()

    def set(self, value):
        self.var.set(value)


# ═══════════════════════════════════════════════════════════════
# BOTÓN CON FEEDBACK VISUAL (ANIMACIÓN CLICK)
# ═══════════════════════════════════════════════════════════════

class BotonConFeedback:
    """Botón tk con efecto visual al hacer clic."""

    COLOR_MAP = {
        COLOR_MORADO: COLOR_MORADO_HOVER,
        COLOR_VERDE:  "#059669",
        COLOR_ROJO:   "#dc2626",
        COLOR_MARRON: "#d97706",
        "#9f1239":    "#be123c",
        "#06b6d4":    "#0891b2",
        "#4f46e5":    "#3730a3",
        "#8b5cf6":    "#7c3aed",
        "#059669":    "#047857",
    }

    def __init__(self, parent, texto, comando, color=COLOR_MORADO, icono=""):
        label = f"{icono} {texto}" if icono else texto
        hover = self.COLOR_MAP.get(color, COLOR_MORADO_HOVER)
        self.btn = tk.Button(
            parent,
            text=label,
            command=self._click,
            bg=color, fg="white",
            font=FUENTE_SUBTIT,
            relief=tk.FLAT,
            padx=12, pady=6,
            cursor="hand2",
            activebackground=hover,
            activeforeground="white",
            borderwidth=0
        )
        self._cmd = comando
        self._color_orig = color
        self._color_hover = hover

        self.btn.bind("<Enter>", lambda e: self.btn.config(bg=hover))
        self.btn.bind("<Leave>", lambda e: self.btn.config(bg=color))

    def _click(self):
        self.btn.config(bg="#1e293b")
        self.btn.after(120, lambda: self.btn.config(bg=self._color_orig))
        if self._cmd:
            self._cmd()

    def pack(self, **kwargs):
        self.btn.pack(**kwargs)

    def grid(self, **kwargs):
        self.btn.grid(**kwargs)


# ═══════════════════════════════════════════════════════════════
# NOTIFICACIÓN TOAST
# ═══════════════════════════════════════════════════════════════

class Notificacion:
    COLORES = {
        'exito':    ("#dcfce7", "#166534", "✅"),
        'error':    ("#fee2e2", "#991b1b", "❌"),
        'info':     ("#e0e7ff", "#3730a3", "ℹ️"),
        'aviso':    ("#fef9c3", "#854d0e", "⚠️"),
    }

    @staticmethod
    def mostrar(parent, mensaje: str, tipo='info', duracion=3500):
        bg, fg, icono = Notificacion.COLORES.get(tipo, Notificacion.COLORES['info'])
        popup = tk.Toplevel(parent)
        popup.overrideredirect(True)
        popup.attributes("-topmost", True)
        popup.configure(bg=bg)

        tk.Label(popup, text=f"  {icono}  {mensaje}  ",
                 bg=bg, fg=fg, font=("Segoe UI", 10, "bold"),
                 padx=16, pady=10).pack()

        # Posicionar en esquina inferior derecha
        parent.update_idletasks()
        sw = parent.winfo_screenwidth()
        sh = parent.winfo_screenheight()
        popup.geometry(f"+{sw - 500}+{sh - 100}")
        popup.after(duracion, popup.destroy)


# ═══════════════════════════════════════════════════════════════
# TREEVIEW WoW (tabla estilizada)
# ═══════════════════════════════════════════════════════════════

def crear_treeview_wow(parent, columnas, alto=300):
    """Crea un Treeview con el estilo oscuro WoW y scrollbars."""
    frame = tk.Frame(parent, bg=COLOR_FONDO)

    tree = ttk.Treeview(frame, columns=columnas, show="headings",
                        style="Wow.Treeview", height=alto)

    # Scrollbars
    vsb = ttk.Scrollbar(frame, orient="vertical", command=tree.yview)
    hsb = ttk.Scrollbar(frame, orient="horizontal", command=tree.xview)
    tree.configure(yscrollcommand=vsb.set, xscrollcommand=hsb.set)

    vsb.pack(side=tk.RIGHT, fill=tk.Y)
    hsb.pack(side=tk.BOTTOM, fill=tk.X)
    tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

    # Tags de color
    tree.tag_configure("par",       background=COLOR_FILA_PAR)
    tree.tag_configure("impar",     background=COLOR_FILA_IMPAR)
    tree.tag_configure("alerta",    background=COLOR_STOCK_BAJO,   foreground="#fca5a5")
    tree.tag_configure("ok",        foreground="#4ade80")
    tree.tag_configure("pendiente", foreground=COLOR_MARRON)
    tree.tag_configure("enviado",   foreground=COLOR_CYAN)
    tree.tag_configure("entregado", foreground=COLOR_VERDE)

    return frame, tree


# ═══════════════════════════════════════════════════════════════
# HELPER: Fondo oscuro para widgets hijos
# ═══════════════════════════════════════════════════════════════

def aplicar_fondo_oscuro(widget):
    """Recursivamente aplica el fondo oscuro a un widget y sus hijos."""
    try:
        widget.configure(bg=COLOR_FONDO)
    except Exception:
        pass
    for child in widget.winfo_children():
        aplicar_fondo_oscuro(child)
