"""
MÓDULO 2: INTERFAZ MEJORADA
- Fuentes grandes (14-16pt)
- Semáforos de estado
- Diseño limpio
- Colores de marca
"""

import tkinter as tk
from tkinter import ttk, messagebox
from modulo1_nucleo import cargar_contador, LIMITE_DIARIO

# ========================================
# COLORES
# ========================================

COLOR_FONDO = "#f8f9fa"
COLOR_MORADO = "#581c87"  # NOXERTEZ
COLOR_MARRON = "#78350f"  # CANDLE HOLDER
COLOR_VERDE = "#064e3b"   # ZEN GARDEN
COLOR_DORADO = "#c5a059"

# ========================================
# ESTILOS
# ========================================

def configurar_estilos():
    """Configura estilos TTK"""
    style = ttk.Style()
    style.theme_use('clam')
    
    # Fuentes GRANDES
    style.configure('.', 
                   font=('Segoe UI', 14),
                   background=COLOR_FONDO)
    
    # Botones grandes
    style.configure('TButton',
                   font=('Segoe UI', 13, 'bold'),
                   padding=10)
    
    # Labels grandes
    style.configure('TLabel',
                   font=('Segoe UI', 14),
                   background=COLOR_FONDO)
    
    style.configure('Title.TLabel',
                   font=('Segoe UI', 18, 'bold'),
                   foreground=COLOR_MORADO,
                   background=COLOR_FONDO)
    
    # LabelFrames
    style.configure('TLabelframe',
                   font=('Segoe UI', 14, 'bold'),
                   foreground=COLOR_MORADO,
                   background=COLOR_FONDO)
    
    style.configure('TLabelframe.Label',
                   font=('Segoe UI', 14, 'bold'),
                   foreground=COLOR_MORADO)

# ========================================
# BARRA DE ESTADO CON SEMÁFOROS
# ========================================

class BarraEstado:
    """Barra superior con semáforos de estado"""
    
    def __init__(self, parent):
        self.frame = tk.Frame(parent, bg=COLOR_MORADO, height=60)
        self.frame.pack(fill=tk.X, side=tk.TOP)
        self.frame.pack_propagate(False)
        
        # Título
        tk.Label(self.frame,
                text="🎨 CATÁLOGO ARTESANAL v3.0",
                font=('Segoe UI', 18, 'bold'),
                bg=COLOR_MORADO,
                fg='white').pack(side=tk.LEFT, padx=20)
        
        # Semáforos
        semaforos = tk.Frame(self.frame, bg=COLOR_MORADO)
        semaforos.pack(side=tk.RIGHT, padx=20)
        
        # Semáforo BD
        self.sem_bd = tk.Label(semaforos,
                              text="⚪ Base Datos",
                              font=('Segoe UI', 13, 'bold'),
                              bg=COLOR_MORADO,
                              fg='white',
                              padx=12)
        self.sem_bd.pack(side=tk.LEFT, padx=5)
        
        # Semáforo IA
        self.sem_ia = tk.Label(semaforos,
                              text="⚪ IA: 0/1500",
                              font=('Segoe UI', 13, 'bold'),
                              bg=COLOR_MORADO,
                              fg='white',
                              padx=12)
        self.sem_ia.pack(side=tk.LEFT, padx=5)
        
        # Semáforo Sistema
        self.sem_sistema = tk.Label(semaforos,
                                   text="🟢 Sistema",
                                   font=('Segoe UI', 13, 'bold'),
                                   bg=COLOR_MORADO,
                                   fg='white',
                                   padx=12)
        self.sem_sistema.pack(side=tk.LEFT, padx=5)

        # Semáforo Servidor Web (Nuevo)
        self.sem_web = tk.Label(semaforos,
                               text="⚪ Web",
                               font=('Segoe UI', 13, 'bold'),
                               bg=COLOR_MORADO,
                               fg='white',
                               padx=12)
        self.sem_web.pack(side=tk.LEFT, padx=5)
        
        # Actualizar
        self.actualizar()
    
    def actualizar(self):
        """Actualiza los semáforos"""
        # IA
        try:
            res_cnt = cargar_contador()
            contador = res_cnt.get("llamadas", 0)
            if contador < 500:
                color, emoji = '#86efac', '🟢'
            elif contador < 1200:
                color, emoji = '#fcd34d', '🟡'
            else:
                color, emoji = '#fca5a5', '🔴'
            
            self.sem_ia.config(
                text=f"{emoji} IA: {contador}/{LIMITE_DIARIO}",
                fg=color
            )
        except:
            self.sem_ia.config(text="❌ IA", fg='#fca5a5')
    
    def activar_bd(self, activo=True):
        """Actualiza semáforo de BD"""
        if activo:
            self.sem_bd.config(text="🟢 Base Datos", fg='#86efac')
        else:
            self.sem_bd.config(text="🔴 Sin BD", fg='#fca5a5')

    def activar_web(self, activo=True):
        """Actualiza semáforo del Servidor Web"""
        if activo:
            self.sem_web.config(text="🟢 Web", fg='#86efac')
        else:
            self.sem_web.config(text="🔴 Web Off", fg='#fca5a5')

    def agregar_log(self, mensaje):
        """Implementación simplificada para la barra de estado"""
        print(f"[STATUS] {mensaje}")

# ========================================
# COMPONENTE: CAMPO DE ENTRADA MEJORADO
# ========================================

class CampoMejorado:
    """Campo de entrada con label grande"""
    
    def __init__(self, parent, label, row, ancho=40):
        # Label grande
        ttk.Label(parent, 
                 text=label,
                 font=('Segoe UI', 14, 'bold')).grid(
                     row=row, column=0, 
                     sticky=tk.W, pady=8, padx=10)
        
        # Entry grande
        self.entry = ttk.Entry(parent, 
                              width=ancho,
                              font=('Segoe UI', 14))
        self.entry.grid(row=row, column=1, 
                       sticky=tk.EW, pady=8, padx=10)
    
    def get(self):
        return self.entry.get()
    
    def set(self, valor):
        self.entry.delete(0, tk.END)
        self.entry.insert(0, valor)
    
    def clear(self):
        self.entry.delete(0, tk.END)

class CheckboxMejorado:
    """Checkbox con label grande"""
    
    def __init__(self, parent, label, row, column=0):
        self.var = tk.BooleanVar()
        
        self.check = ttk.Checkbutton(parent, 
                                    text=label,
                                    variable=self.var,
                                    style='TCheckbutton')
        # Configurar estilo específico si es necesario, por ahora usa default heredado
        # Hack para fuente grande en checkbox si estilo no aplica bien directo
        
        self.check.grid(row=row, column=column, 
                       sticky=tk.W, pady=8, padx=10)
    
    def get(self):
        return self.var.get()
    
    def set(self, valor):
        self.var.set(bool(valor))
    
    def clear(self):
        self.var.set(False)

# ========================================
# COMPONENTE: BOTÓN GRANDE
# ========================================

class BotonGrande:
    """Botón grande y visible"""
    
    def __init__(self, parent, texto, comando, color=COLOR_MORADO):
        self.btn = tk.Button(parent,
                            text=texto,
                            command=comando,
                            font=('Segoe UI', 14, 'bold'),
                            bg=color,
                            fg='white',
                            padx=20,
                            pady=12,
                            cursor='hand2',
                            relief=tk.FLAT)
        
        # Efectos hover
        self.btn.bind('<Enter>', lambda e: self.btn.config(bg=self._lighten(color)))
        self.btn.bind('<Leave>', lambda e: self.btn.config(bg=color))
    
    def _lighten(self, color):
        """Aclara un color hex"""
        # Simplificado: retorna color más claro
        if color == COLOR_MORADO:
            return '#7c3aed'
        elif color == COLOR_MARRON:
            return '#92400e'
        elif color == COLOR_VERDE:
            return '#047857'
        return color
    
    def pack(self, **kwargs):
        self.btn.pack(**kwargs)
    
    def grid(self, **kwargs):
        self.btn.grid(**kwargs)

# ========================================
# COMPONENTE: LOG MEJORADO
# ========================================

class LogMejorado:
    """Área de log con fuentes grandes"""
    
    def __init__(self, parent):
        frame = ttk.LabelFrame(parent, text="📋 Registro de Actividad")
        frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        # Texto grande
        self.text = tk.Text(frame,
                           font=('Consolas', 12),
                           bg='#1e293b',
                           fg='#e2e8f0',
                           wrap=tk.WORD,
                           height=10)
        self.text.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Scrollbar
        scroll = tk.Scrollbar(self.text)
        scroll.pack(side=tk.RIGHT, fill=tk.Y)
        self.text.config(yscrollcommand=scroll.set)
        scroll.config(command=self.text.yview)
    
    def agregar(self, mensaje):
        """Agrega mensaje al log"""
        from datetime import datetime
        timestamp = datetime.now().strftime("%H:%M:%S")
        self.text.insert(tk.END, f"[{timestamp}] {mensaje}\n")
        self.text.see(tk.END)

# ========================================
# TEST DEL MÓDULO
# ========================================

if __name__ == "__main__":
    # Test de componentes
    root = tk.Tk()
    root.title("Test Módulo 2 - Interfaz Mejorada")
    root.geometry("900x700")
    root.configure(bg=COLOR_FONDO)
    
    configurar_estilos()
    
    # Barra de estado
    barra = BarraEstado(root)
    
    # Frame principal
    main = tk.Frame(root, bg=COLOR_FONDO)
    main.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
    
    # Título
    ttk.Label(main, 
             text="Test de Componentes Mejorados",
             style='Title.TLabel').pack(pady=20)
    
    # Frame de campos
    campos_frame = tk.Frame(main, bg=COLOR_FONDO)
    campos_frame.pack(fill=tk.X, padx=20, pady=10)
    campos_frame.columnconfigure(1, weight=1)
    
    # Campos de prueba
    campo1 = CampoMejorado(campos_frame, "Nombre:", 0)
    campo2 = CampoMejorado(campos_frame, "Precio:", 1)
    campo3 = CampoMejorado(campos_frame, "Color:", 2)
    
    # Botones de prueba
    botones = tk.Frame(main, bg=COLOR_FONDO)
    botones.pack(pady=20)
    
    def test_accion():
        messagebox.showinfo("Test", "¡Botón funcionando!")
    
    BotonGrande(botones, "🤖 Analizar con IA", test_accion, COLOR_MORADO).pack(side=tk.LEFT, padx=5)
    BotonGrande(botones, "💾 Guardar", test_accion, COLOR_VERDE).pack(side=tk.LEFT, padx=5)
    BotonGrande(botones, "🎨 Generar Ficha", test_accion, COLOR_MARRON).pack(side=tk.LEFT, padx=5)
    
    # Log
    log = LogMejorado(main)
    log.agregar("✅ Módulo 2 cargado correctamente")
    log.agregar("🎨 Interfaz mejorada con fuentes grandes")
    log.agregar("🚦 Semáforos de estado activos")
    
    # Actualizar BD
    barra.activar_bd(True)
    
    print("✅ Test del Módulo 2 ejecutándose...")
    print("   - Fuentes grandes: ✅")
    print("   - Semáforos: ✅")
    print("   - Componentes: ✅")
    
    root.mainloop()
