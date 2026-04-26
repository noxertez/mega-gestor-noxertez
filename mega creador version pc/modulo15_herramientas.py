import tkinter as tk
from tkinter import ttk, messagebox
import os
import json
from modulo2_interfaz import COLOR_FONDO, COLOR_MORADO, COLOR_VERDE
import modulo_backup
import threading
from tkinter import filedialog

# Importar constantes y funciones de config de modulo4_fichas
# (Las añadiremos en el siguiente paso a modulo4_fichas)
try:
    from modulo4_fichas import (
        CATEGORIAS, MARCAS, COLORES, FESTIVIDADES, 
        API_KEYS, MODELO_IA, GROQ_KEYS, GROQ_MODELO, IA_PREFERIDA,
        ESTILO_IA_ACTUAL, ESTILOS_IA, guardar_configuracion
    )
except ImportError:
    # Fallback si aún no están implementadas
    CATEGORIAS = {}
    MARCAS = {}
    COLORES = []
    FESTIVIDADES = []
    API_KEYS = []
    MODELO_IA = "gemini-1.5-flash"
    GROQ_KEYS = []
    GROQ_MODELO = "llama-3.1-70b-versatile"
    IA_PREFERIDA = "Gemini"
    ESTILO_IA_ACTUAL = "Neutral"
    ESTILOS_IA = {}
    def guardar_configuracion(): return False

class TabHerramientas:
    def __init__(self, parent, app):
        self.parent = parent
        self.app = app
        self.tab_frame = tk.Frame(parent, bg=COLOR_FONDO)
        
        # Scrollable container
        self.canvas = tk.Canvas(self.tab_frame, bg=COLOR_FONDO, highlightthickness=0)
        self.scrollbar = ttk.Scrollbar(self.tab_frame, orient="vertical", command=self.canvas.yview)
        self.scrollable_frame = tk.Frame(self.canvas, bg=COLOR_FONDO)

        self.scrollable_frame.bind(
            "<Configure>",
            lambda e: self.canvas.configure(scrollregion=self.canvas.bbox("all"))
        )

        self.canvas.create_window((0, 0), window=self.scrollable_frame, anchor="nw")
        self.canvas.configure(yscrollcommand=self.scrollbar.set)

        self.canvas.pack(side="left", fill="both", expand=True)
        self.scrollbar.pack(side="right", fill="y")
        
        self.crear_interfaz()

    def crear_interfaz(self):
        tk.Label(self.scrollable_frame, text="🛠️ Configuración y Herramientas", 
                 font=("Segoe UI", 18, "bold"), bg=COLOR_FONDO, fg=COLOR_MORADO).pack(pady=20)

        # --- SECCIÓN IA ---
        ia_frame = ttk.LabelFrame(self.scrollable_frame, text="🤖 Configuración de IA")
        ia_frame.pack(fill="x", padx=20, pady=10)

        # Preferencia
        f_pref = tk.Frame(ia_frame, bg=COLOR_FONDO)
        f_pref.pack(fill="x", padx=10, pady=5)
        tk.Label(f_pref, text="IA Preferida:", bg=COLOR_FONDO, font=("Segoe UI", 10, "bold")).pack(side="left")
        self.var_ia = tk.StringVar(value=IA_PREFERIDA)
        ttk.Radiobutton(f_pref, text="Gemini", variable=self.var_ia, value="Gemini").pack(side="left", padx=10)
        ttk.Radiobutton(f_pref, text="Groq", variable=self.var_ia, value="Groq").pack(side="left")

        # Estilo
        f_style = tk.Frame(ia_frame, bg=COLOR_FONDO)
        f_style.pack(fill="x", padx=10, pady=5)
        tk.Label(f_style, text="Estilo de Descripción:", bg=COLOR_FONDO, font=("Segoe UI", 10, "bold")).pack(side="left")
        estilos_list = []
        for cat in ESTILOS_IA.values(): estilos_list.extend(cat.keys())
        self.combo_estilo = ttk.Combobox(f_style, values=sorted(estilos_list), state="readonly")
        self.combo_estilo.set(ESTILO_IA_ACTUAL)
        self.combo_estilo.pack(side="left", padx=10, fill="x", expand=True)

        # API Keys Gemini
        tk.Label(ia_frame, text="Llaves API Gemini:", bg=COLOR_FONDO, font=("Segoe UI", 10, "bold")).pack(anchor="w", padx=10, pady=(10,0))
        self.list_gemini = tk.Listbox(ia_frame, height=3)
        self.list_gemini.pack(fill="x", padx=10, pady=5)
        for k in API_KEYS: self.list_gemini.insert(tk.END, f"{k.get('nombre')}: {k.get('key')[:10]}...")

        # API Keys Groq
        tk.Label(ia_frame, text="Llaves API Groq:", bg=COLOR_FONDO, font=("Segoe UI", 10, "bold")).pack(anchor="w", padx=10, pady=(10,0))
        self.list_groq = tk.Listbox(ia_frame, height=3)
        self.list_groq.pack(fill="x", padx=10, pady=5)
        for k in GROQ_KEYS: self.list_groq.insert(tk.END, f"{k.get('nombre')}: {k.get('key')[:10]}...")

        # Añadir Nueva Key
        f_add = tk.Frame(ia_frame, bg=COLOR_FONDO)
        f_add.pack(fill="x", padx=10, pady=10)
        tk.Label(f_add, text="Nueva Key:", bg=COLOR_FONDO).pack(side="left")
        self.ent_key_nom = ttk.Entry(f_add, width=10)
        self.ent_key_nom.pack(side="left", padx=5)
        self.ent_key_val = ttk.Entry(f_add)
        self.ent_key_val.pack(side="left", fill="x", expand=True, padx=5)
        
        self.var_tipo = tk.StringVar(value="Gemini")
        ttk.OptionMenu(f_add, self.var_tipo, "Gemini", "Gemini", "Groq").pack(side="left", padx=5)
        tk.Button(f_add, text="➕ Añadir", command=self.añadir_key, bg=COLOR_MORADO, fg="white").pack(side="left")

        # Botón Guardar Principal
        tk.Button(self.scrollable_frame, text="💾 GUARDAR CONFIGURACIÓN", 
                  command=self.guardar, bg=COLOR_MORADO, fg="white", 
                  font=("Segoe UI", 12, "bold"), pady=10).pack(pady=20, fill="x", padx=100)

        # --- SECCIÓN SERVIDOR MÓVIL ---
        srv_frame = ttk.LabelFrame(self.scrollable_frame, text="🌐 Acceso desde Móvil (Servidor)")
        srv_frame.pack(fill="x", padx=20, pady=10)

        def obtener_ip():
            import socket
            try:
                s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
                s.connect(("8.8.8.8", 80))
                ip = s.getsockname()[0]
                s.close()
                return ip
            except: return "127.0.0.1"

        ip_local = obtener_ip()
        info_ip = tk.Frame(srv_frame, bg=COLOR_FONDO)
        info_ip.pack(fill="x", padx=10, pady=5)
        
        tk.Label(info_ip, text="Tu IP Local:", bg=COLOR_FONDO, font=("Segoe UI", 10, "bold")).pack(side="left")
        tk.Label(info_ip, text=ip_local, bg="white", fg=COLOR_MORADO, font=("Consolas", 12, "bold"), padx=10).pack(side="left", padx=10)
        
        tk.Label(srv_frame, text=f"URL: http://{ip_local}:5000", font=("Consolas", 11, "bold"), bg=COLOR_FONDO, fg="#2563eb").pack(anchor="w", padx=10, pady=5)
        tk.Button(srv_frame, text="💡 Reactivar Servidor Móvil", command=self.app.iniciar_servidor_web, bg="#f3f4f6").pack(pady=10)

        # --- SECCIÓN BACKUP (TOTAL) ---
        back_frame = ttk.LabelFrame(self.scrollable_frame, text="💾 Copia de Seguridad Total")
        back_frame.pack(fill="x", padx=20, pady=10)

        tk.Label(back_frame, text="Respaldo de App, Imágenes y Base de Datos.", font=("Segoe UI", 9), bg=COLOR_FONDO).pack(anchor="w", padx=10, pady=5)
        
        # Ruta Personalizada
        f_path = tk.Frame(back_frame, bg=COLOR_FONDO)
        f_path.pack(fill="x", padx=10, pady=5)
        tk.Label(f_path, text="Ruta de Destino:", bg=COLOR_FONDO, font=("Segoe UI", 9, "bold")).pack(side="left")
        
        self.ent_back_path = ttk.Entry(f_path)
        ruta_guardada = self.app.gestor.get_config('ruta_backup', '')
        self.ent_back_path.insert(0, ruta_guardada)
        self.ent_back_path.pack(side="left", fill="x", expand=True, padx=5)
        
        tk.Button(f_path, text="📁", command=self.seleccionar_ruta_backup).pack(side="left")

        self.btn_backup = tk.Button(back_frame, text="🚀 CREAR BACKUP TOTAL (ZIP)", command=self.ejecutar_backup, bg=COLOR_VERDE, fg="white", font=("Segoe UI", 10, "bold"), pady=8)
        self.btn_backup.pack(pady=10, fill="x", padx=50)
        self.lbl_back_status = tk.Label(back_frame, text="", bg=COLOR_FONDO, font=("Segoe UI", 9))
        self.lbl_back_status.pack(pady=5)

        # --- SECCIÓN CONTACTO ---
        contact_frame = ttk.LabelFrame(self.scrollable_frame, text="📞 Información de Contacto")
        contact_frame.pack(fill="x", padx=20, pady=10)
        f_tel = tk.Frame(contact_frame, bg=COLOR_FONDO)
        f_tel.pack(fill="x", padx=10, pady=10)
        tk.Label(f_tel, text="WhatsApp (34...):", bg=COLOR_FONDO, font=("Segoe UI", 10, "bold")).pack(side="left")
        tel_actual = self.app.gestor.get_config('telefono', '')
        self.ent_tel = ttk.Entry(f_tel)
        self.ent_tel.insert(0, tel_actual)
        self.ent_tel.pack(side="left", padx=10, fill="x", expand=True)

    def añadir_key(self):
        nom = self.ent_key_nom.get(); val = self.ent_key_val.get(); tipo = self.var_tipo.get()
        if not nom or not val: return
        new_k = {"nombre": nom, "key": val}
        if tipo == "Gemini":
            API_KEYS.append(new_k); self.list_gemini.insert(tk.END, f"{nom}: {val[:10]}...")
        else:
            GROQ_KEYS.append(new_k); self.list_groq.insert(tk.END, f"{nom}: {val[:10]}...")
        self.ent_key_nom.delete(0, tk.END); self.ent_key_val.delete(0, tk.END)

    def guardar(self):
        import modulo4_fichas
        modulo4_fichas.IA_PREFERIDA = self.var_ia.get()
        modulo4_fichas.ESTILO_IA_ACTUAL = self.combo_estilo.get()
        modulo4_fichas.API_KEYS = API_KEYS; modulo4_fichas.GROQ_KEYS = GROQ_KEYS
        nuevo_tel = self.ent_tel.get().strip()
        self.app.gestor.set_config('telefono', nuevo_tel)
        
        # Guardar Ruta Backup
        ruta_back = self.ent_back_path.get().strip()
        self.app.gestor.set_config('ruta_backup', ruta_back)

        if guardar_configuracion(): messagebox.showinfo("Éxito", "Guardado ok.")
        else: messagebox.showerror("Error", "No se pudo guardar.")

    def seleccionar_ruta_backup(self):
        ruta = filedialog.askdirectory()
        if ruta:
            self.ent_back_path.delete(0, tk.END)
            self.ent_back_path.insert(0, ruta)

    def ejecutar_backup(self):
        self.btn_backup.config(state="disabled", text="⏳ Procesando...")
        self.lbl_back_status.config(text="Generando copia de seguridad...", fg="blue")
        def tarea():
            from modulo3_gestion import MYSQL_CONFIG, RUTA_IMAGENES
            app_root = os.path.dirname(os.path.abspath(__file__))
            ruta_custom = self.ent_back_path.get().strip()
            
            exito, resultado = modulo_backup.crear_backup_total(app_root, MYSQL_CONFIG, RUTA_IMAGENES, ruta_custom)
            
            def finalizado():
                self.btn_backup.config(state="normal", text="🚀 CREAR BACKUP TOTAL (ZIP)")
                if exito:
                    self.lbl_back_status.config(text=f"✅ Backup creado: {os.path.basename(resultado)}", fg="green")
                    messagebox.showinfo("Backup Completado", f"Se ha creado la copia de seguridad correctamente en:\n\n{resultado}")
                else:
                    self.lbl_back_status.config(text=f"❌ Error: {resultado[:50]}...", fg="red")
                    messagebox.showerror("Error de Backup", f"No se pudo crear la copia de seguridad:\n\n{resultado}")
            
            self.parent.after(0, finalizado)

        threading.Thread(target=tarea, daemon=True).start()
