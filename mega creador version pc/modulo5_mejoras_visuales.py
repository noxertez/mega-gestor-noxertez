"""
MÓDULO 5: MEJORAS VISUALES Y FEEDBACK
- Preview de imagen seleccionada INMEDIATO
- Indicadores visuales de procesos
- Layout optimizado para pantallas pequeñas
- Feedback constante al usuario
"""

import tkinter as tk
from tkinter import ttk
from PIL import Image, ImageTk
import os

# ========================================
# PREVIEW DE IMAGEN SELECCIONADA
# ========================================

class PreviewImagenSeleccionada:
    """Muestra la imagen seleccionada INMEDIATAMENTE"""
    
    def __init__(self, parent):
        self.frame = ttk.LabelFrame(parent, text="📸 Imagen Seleccionada")
        
        # Canvas para mostrar imagen
        self.canvas = tk.Canvas(self.frame,
                               width=300,
                               height=300,
                               bg='#e5e7eb',
                               highlightthickness=1,
                               highlightbackground='#9ca3af')
        self.canvas.pack(padx=10, pady=10)
        
        # Label de estado
        self.label_estado = tk.Label(self.frame,
                                     text="Sin imagen seleccionada",
                                     font=('Segoe UI', 11),
                                     fg='#6b7280')
        self.label_estado.pack(pady=5)
        
        self.imagen_tk = None
        self.imagen_path = None
    
    def mostrar_imagen(self, ruta):
        """Muestra la imagen seleccionada"""
        try:
            # Cargar y redimensionar
            img = Image.open(ruta)
            img.thumbnail((280, 280), Image.Resampling.LANCZOS)
            
            # Convertir para Tkinter (Asegurando RGB)
            self.imagen_tk = ImageTk.PhotoImage(img.convert("RGB"))
            
            # Mostrar en canvas
            self.canvas.delete("all")
            self.canvas.create_image(150, 150, image=self.imagen_tk)
            self.canvas.image = self.imagen_tk # Referencia crítica para Tkinter
            
            # Actualizar estado
            nombre = os.path.basename(ruta)
            self.label_estado.config(
                text=f"✅ {nombre}",
                fg='#059669'
            )
            
            self.imagen_path = ruta
            
        except Exception as e:
            self.mostrar_error(f"Error: {e}")
    
    def mostrar_placeholder(self):
        """Muestra placeholder cuando no hay imagen"""
        self.canvas.delete("all")
        self.canvas.create_rectangle(50, 50, 250, 250,
                                     outline='#9ca3af',
                                     width=2,
                                     dash=(5, 5))
        self.canvas.create_text(150, 150,
                               text="📁\n\nSelecciona una imagen",
                               font=('Segoe UI', 12),
                               fill='#6b7280',
                               justify=tk.CENTER)
        
        self.label_estado.config(
            text="Sin imagen seleccionada",
            fg='#6b7280'
        )
        self.imagen_tk = None
        self.imagen_path = None
    
    def mostrar_error(self, mensaje):
        """Muestra error"""
        self.canvas.delete("all")
        self.canvas.create_text(150, 150,
                               text=f"❌\n\n{mensaje}",
                               font=('Segoe UI', 11),
                               fill='#dc2626',
                               justify=tk.CENTER,
                               width=260)
        
        self.label_estado.config(
            text="Error al cargar imagen",
            fg='#dc2626'
        )

# ========================================
# INDICADOR DE PROCESO IA
# ========================================

class IndicadorProcesoIA:
    """Indicador visual del proceso de IA"""
    
    def __init__(self, parent):
        self.frame = tk.Frame(parent, bg='#f8f9fa')
        
        # Barra de progreso
        self.progreso = ttk.Progressbar(self.frame,
                                        mode='indeterminate',
                                        length=250)
        
        # Label de estado
        self.label = tk.Label(self.frame,
                             text="",
                             font=('Segoe UI', 12, 'bold'),
                             bg='#f8f9fa')
        
        self.activo = False
    
    def iniciar(self, mensaje="🤖 Analizando con IA..."):
        """Inicia el indicador"""
        if not self.activo:
            self.frame.pack(fill=tk.X, pady=10)
            self.label.config(text=mensaje, fg='#f59e0b')
            self.label.pack()
            self.progreso.pack(pady=5)
            self.progreso.start(10)
            self.activo = True
    
    def detener(self, exito=True, mensaje=None):
        """Detiene el indicador"""
        if self.activo:
            self.progreso.stop()
            
            if exito:
                texto = mensaje or "✅ ¡Análisis completado!"
                color = '#059669'
            else:
                texto = mensaje or "❌ Error en el análisis"
                color = '#dc2626'
            
            self.label.config(text=texto, fg=color)
            
            # Ocultar después de 2 segundos
            self.frame.after(2000, self._ocultar)
    
    def _ocultar(self):
        """Oculta el indicador"""
        self.progreso.pack_forget()
        self.label.pack_forget()
        self.frame.pack_forget()
        self.activo = False

# ========================================
# BOTÓN CON FEEDBACK VISUAL
# ========================================

class BotonConFeedback:
    """Botón que muestra feedback visual al hacer clic"""
    
    def _ejecutar_wrapper(self):
        """Wrapper para llamar al método interno"""
        self._ejecutar_accion()

    def __init__(self, parent, texto, comando, color='#581c87', icono=""):
        self.comando_original = comando
        self.color_original = color
        self.texto_original = f"{icono} {texto}" if icono else texto
        
        self.btn = tk.Button(parent,
                            text=self.texto_original,
                            command=self._ejecutar_wrapper,
                            font=('Segoe UI', 13, 'bold'),
                            bg=color,
                            fg='white',
                            padx=15,
                            pady=10,
                            cursor='hand2',
                            relief=tk.FLAT,
                            activebackground=self._lighten(color),
                            activeforeground='white')
    
    def _ejecutar_accion(self):
        """Ejecuta el comando con feedback visual"""
        try:
            if not self.btn.winfo_exists(): return
            # Cambiar a estado "procesando"
            self.btn.config(bg='#9ca3af', text="⏳ Procesando...")
            self.btn.update()
            
            # Ejecutar comando
            self.comando_original()
            
            # Feedback de éxito
            if self.btn.winfo_exists():
                self.btn.config(bg='#059669', text="✅ ¡Listo!")
        except Exception as e:
            # Feedback de error
            if self.btn.winfo_exists():
                self.btn.config(bg='#dc2626', text=f"❌ Error")
            print(f"Error en BotonConFeedback: {e}")
        
        # Volver al estado original después de 1 segundo si el widget aún existe
        try:
            if self.btn.winfo_exists():
                self.btn.after(1000, self._restaurar)
        except:
            pass

    def _restaurar(self):
        """Restaura el botón a su estado original si aún existe"""
        try:
            if self.btn.winfo_exists():
                self.btn.config(bg=self.color_original, text=self.texto_original)
        except:
            pass
    
    def _lighten(self, color):
        """Aclara un color"""
        if color == '#581c87':
            return '#7c3aed'
        elif color == '#78350f':
            return '#92400e'
        elif color == '#064e3b':
            return '#047857'
        elif color == '#f59e0b':
            return '#fbbf24'
        return color
    
    def pack(self, **kwargs):
        self.btn.pack(**kwargs)
    
    def grid(self, **kwargs):
        self.btn.grid(**kwargs)

# ========================================
# LAYOUT COMPACTO PARA PANTALLAS PEQUEÑAS
# ========================================

class LayoutCompacto:
    """Layout optimizado para pantallas pequeñas"""
    
    @staticmethod
    def crear_seccion_plegable(parent, titulo, contenido_func):
        """Crea una sección que se puede plegar/desplegar"""
        
        # Frame principal
        seccion = tk.Frame(parent, bg='#f8f9fa')
        
        # Variable para estado
        expandido = tk.BooleanVar(value=True)
        
        # Header (siempre visible)
        header = tk.Frame(seccion, bg='#e5e7eb', cursor='hand2')
        header.pack(fill=tk.X)
        
        # Flecha y título
        flecha = tk.Label(header,
                         text="▼",
                         font=('Segoe UI', 12),
                         bg='#e5e7eb',
                         cursor='hand2')
        flecha.pack(side=tk.LEFT, padx=5)
        
        titulo_label = tk.Label(header,
                               text=titulo,
                               font=('Segoe UI', 13, 'bold'),
                               bg='#e5e7eb',
                               cursor='hand2')
        titulo_label.pack(side=tk.LEFT, padx=5, pady=8)
        
        # Contenido (plegable)
        contenido = tk.Frame(seccion, bg='#f8f9fa')
        contenido.pack(fill=tk.BOTH, expand=True)
        
        # Función toggle
        def toggle():
            if expandido.get():
                # Plegar
                contenido.pack_forget()
                flecha.config(text="▶")
                expandido.set(False)
            else:
                # Desplegar
                contenido.pack(fill=tk.BOTH, expand=True)
                flecha.config(text="▼")
                expandido.set(True)
        
        # Bind clicks
        header.bind('<Button-1>', lambda e: toggle())
        flecha.bind('<Button-1>', lambda e: toggle())
        titulo_label.bind('<Button-1>', lambda e: toggle())
        
        # Llenar contenido
        contenido_func(contenido)
        
        return seccion

# ========================================
# NOTIFICACIONES TOAST
# ========================================

class Notificacion:
    """Notificaciones estilo toast (aparecen y desaparecen)"""
    
    @staticmethod
    def mostrar(root, mensaje, tipo='info', duracion=3000):
        """
        Muestra una notificación toast
        
        Args:
            root: ventana principal
            mensaje: texto a mostrar
            tipo: 'info', 'exito', 'error', 'aviso'
            duracion: milisegundos que se muestra
        """
        
        # Colores según tipo
        colores = {
            'info': ('#3b82f6', 'white'),
            'exito': ('#059669', 'white'),
            'error': ('#dc2626', 'white'),
            'aviso': ('#f59e0b', 'white')
        }
        
        bg, fg = colores.get(tipo, colores['info'])
        
        # Crear ventana toplevel
        toast = tk.Toplevel(root)
        toast.overrideredirect(True)  # Sin bordes
        
        # Frame con el mensaje
        frame = tk.Frame(toast, bg=bg, padx=20, pady=15)
        frame.pack()
        
        label = tk.Label(frame,
                        text=mensaje,
                        font=('Segoe UI', 12, 'bold'),
                        bg=bg,
                        fg=fg)
        label.pack()
        
        # Posicionar en la esquina superior derecha
        toast.update_idletasks()
        ancho = toast.winfo_width()
        alto = toast.winfo_height()
        x = root.winfo_x() + root.winfo_width() - ancho - 20
        y = root.winfo_y() + 80
        toast.geometry(f"+{x}+{y}")
        
        # Hacer semi-transparente (si el SO lo soporta)
        try:
            toast.attributes('-alpha', 0.95)
        except:
            pass
        
        # Auto-destruir después de la duración
        toast.after(duracion, toast.destroy)
        
        return toast

# ========================================
# SELECTOR DE IMÁGENES MEJORADO
# ========================================

class SelectorImagenesMejorado:
    """Selector con preview instantáneo"""
    
    def __init__(self, parent, on_seleccionar_callback):
        self.on_seleccionar = on_seleccionar_callback
        
        self.frame = ttk.LabelFrame(parent, text="📁 Imágenes del Producto")
        
        # Botón grande para seleccionar
        self.btn_seleccionar = tk.Button(self.frame,
                                         text="📁 Seleccionar Imagen(es)",
                                         font=('Segoe UI', 14, 'bold'),
                                         bg='#581c87',
                                         fg='white',
                                         padx=20,
                                         pady=15,
                                         cursor='hand2',
                                         command=self.seleccionar)
        self.btn_seleccionar.pack(padx=10, pady=10)
        
        # Preview de imagen(es) seleccionada(s)
        self.preview_frame = tk.Frame(self.frame, bg='#f8f9fa')
        self.preview_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        self.imagenes_seleccionadas = []
        self.previews = []
    
    def seleccionar(self):
        """Abre diálogo de selección"""
        from tkinter import filedialog
        
        rutas = filedialog.askopenfilenames(
            title="Seleccionar imágenes del producto",
            filetypes=[("Imágenes", "*.png *.jpg *.jpeg *.gif *.bmp")]
        )
        
        if rutas:
            self.imagenes_seleccionadas = list(rutas)
            self.mostrar_previews()
            self.on_seleccionar(self.imagenes_seleccionadas)
    
    def mostrar_previews(self):
        """Muestra previews de todas las imágenes"""
        # Limpiar previews anteriores
        for widget in self.preview_frame.winfo_children():
            widget.destroy()
        
        self.previews = []
        
        # Mostrar cada imagen
        for i, ruta in enumerate(self.imagenes_seleccionadas):
            try:
                # Frame para esta imagen
                img_frame = tk.Frame(self.preview_frame, 
                                    bg='white',
                                    relief=tk.RAISED,
                                    borderwidth=2)
                img_frame.pack(side=tk.LEFT, padx=5, pady=5)
                
                # Cargar y redimensionar
                img = Image.open(ruta)
                img.thumbnail((120, 120), Image.Resampling.LANCZOS)
                
                img_tk = ImageTk.PhotoImage(img)
                self.previews.append(img_tk)  # Guardar referencia
                
                # Canvas con la imagen
                canvas = tk.Canvas(img_frame,
                                  width=120,
                                  height=120,
                                  bg='white',
                                  highlightthickness=0)
                canvas.pack()
                canvas.create_image(60, 60, image=img_tk)
                
                # Label con número
                numero = tk.Label(img_frame,
                                 text=f"#{i+1}",
                                 font=('Segoe UI', 10, 'bold'),
                                 bg='#581c87',
                                 fg='white',
                                 padx=8,
                                 pady=2)
                numero.pack()
                
                # Si es la primera, marcar como principal
                if i == 0:
                    principal = tk.Label(img_frame,
                                       text="⭐ Principal",
                                       font=('Segoe UI', 9),
                                       bg='#fbbf24',
                                       fg='white',
                                       padx=5)
                    principal.pack()
                
            except Exception as e:
                print(f"Error mostrando preview: {e}")

# ========================================
# TEST DEL MÓDULO
# ========================================

if __name__ == "__main__":
    root = tk.Tk()
    root.title("Test Módulo 5 - Mejoras Visuales")
    root.geometry("800x700")
    root.configure(bg='#f8f9fa')
    
    # Preview de imagen
    preview = PreviewImagenSeleccionada(root)
    preview.frame.pack(side=tk.LEFT, padx=10, pady=10)
    preview.mostrar_placeholder()
    
    # Selector mejorado
    def on_select(imgs):
        if imgs:
            preview.mostrar_imagen(imgs[0])
            Notificacion.mostrar(root, 
                                f"✅ {len(imgs)} imagen(es) seleccionada(s)",
                                'exito')
    
    selector = SelectorImagenesMejorado(root, on_select)
    selector.frame.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=10, pady=10)
    
    # Indicador de IA
    indicador = IndicadorProcesoIA(root)
    indicador.frame.pack(fill=tk.X, padx=10)
    
    # Botones de prueba
    botones = tk.Frame(root, bg='#f8f9fa')
    botones.pack(pady=20)
    
    def test_ia():
        indicador.iniciar()
        root.after(3000, lambda: indicador.detener(True))
    
    def test_notif_exito():
        Notificacion.mostrar(root, "✅ Producto guardado correctamente", 'exito')
    
    def test_notif_error():
        Notificacion.mostrar(root, "❌ Error al guardar", 'error')
    
    BotonConFeedback(botones, "Test IA", test_ia, '#f59e0b', '🤖').pack(side=tk.LEFT, padx=5)
    BotonConFeedback(botones, "Notif Éxito", test_notif_exito, '#059669', '✅').pack(side=tk.LEFT, padx=5)
    BotonConFeedback(botones, "Notif Error", test_notif_error, '#dc2626', '❌').pack(side=tk.LEFT, padx=5)
    
    print("✅ Test del Módulo 5 ejecutándose...")
    print("   - Preview de imagen: ✅")
    print("   - Selector mejorado: ✅")
    print("   - Indicador de IA: ✅")
    print("   - Notificaciones toast: ✅")
    print("   - Botones con feedback: ✅")
    
    root.mainloop()
