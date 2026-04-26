import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import os
from PIL import Image, ImageTk
from datetime import datetime
from modulo2_interfaz import COLOR_FONDO, COLOR_MORADO, COLOR_VERDE, COLOR_MARRON
from modulo4_fichas import PanelFiltros, CATEGORIAS
from modulo3_gestion import GestorFuturosProyectos

# Colores y Estilos
# COLOR_FONDO = "#f8f9fa" # Now imported from modulo2_interfaz
# COLOR_MORADO = "#581c87" # Now imported from modulo2_interfaz
# COLOR_VERDE = "#16a34a" # Now imported from modulo2_interfaz

# ========================================
# UI: PESTAÑA REGISTRO (SQL)
# ========================================

class TabRegistro(tk.Frame):
    def __init__(self, parent, on_save_callback):
        super().__init__(parent, bg=COLOR_FONDO)
        self.on_save_callback = on_save_callback
        self.gestor = GestorFuturosProyectos()
        
        self.sku_var = tk.StringVar()
        self.categoria_var = tk.StringVar()
        self.subcategoria_var = tk.StringVar()
        self.marca_var = tk.StringVar()
        self.nombre_var = tk.StringVar()
        self.estado_var = tk.StringVar(value="Pendiente")
        self.foto_path_var = tk.StringVar()
        
        self.setup_ui()

    def setup_ui(self):
        # Contenedor con Scroll
        self.canvas_scroll = tk.Canvas(self, bg=COLOR_FONDO, highlightthickness=0)
        self.scrollbar = ttk.Scrollbar(self, orient="vertical", command=self.canvas_scroll.yview)
        self.scrollable_frame = tk.Frame(self.canvas_scroll, bg=COLOR_FONDO, padx=40, pady=20)
        
        self.canvas_scroll.create_window((0, 0), window=self.scrollable_frame, anchor="nw")
        self.canvas_scroll.configure(yscrollcommand=self.scrollbar.set)
        
        self.scrollbar.pack(side="right", fill="y")
        self.canvas_scroll.pack(side="left", fill="both", expand=True)

        # Vincular el resize del canvas para que el frame interno crezca
        def _configure_canvas(event):
            # Hacer que el frame interno tenga al menos el ancho del canvas
            self.canvas_scroll.itemconfig(self.canvas_scroll.find_withtag("all")[0], width=event.width)
        self.canvas_scroll.bind("<Configure>", _configure_canvas)

        self.scrollable_frame.bind("<Configure>", lambda e: self.canvas_scroll.configure(scrollregion=self.canvas_scroll.bbox("all")))

        # Mouse wheel (solo cuando el cursor está sobre este canvas)
        def _on_mousewheel(event):
            self.canvas_scroll.yview_scroll(int(-1*(event.delta/120)), "units")
        
        self.canvas_scroll.bind("<Enter>", lambda _: self.canvas_scroll.bind_all("<MouseWheel>", _on_mousewheel))
        self.canvas_scroll.bind("<Leave>", lambda _: self.canvas_scroll.unbind_all("<MouseWheel>"))

        tk.Label(self.scrollable_frame, text="🚀 Registrar Nuevo Artículo por Crear", 
                 font=("Segoe UI", 20, "bold"), bg=COLOR_FONDO, fg=COLOR_MORADO).pack(pady=(0, 20))

        form = tk.Frame(self.scrollable_frame, bg=COLOR_FONDO)
        form.pack(fill="x")

        # Filas del formulario
        self.add_row(form, "Nombre/Proyecto:", tk.Entry(form, textvariable=self.nombre_var, font=("Segoe UI", 12)), 0)
        self.add_row(form, "Tipo/Categoría:", tk.Entry(form, textvariable=self.categoria_var, font=("Segoe UI", 12)), 1)
        self.add_row(form, "Subcategoría:", tk.Entry(form, textvariable=self.subcategoria_var, font=("Segoe UI", 12)), 2)
        self.add_row(form, "Marca Sugerida:", tk.Entry(form, textvariable=self.marca_var, font=("Segoe UI", 12)), 3)
        
        estados = ["Pendiente", "Urgente", "En Proceso", "Material Faltante", "Terminado"]
        cb_estado = ttk.Combobox(form, textvariable=self.estado_var, values=estados, state="readonly", font=("Segoe UI", 12))
        self.add_row(form, "Prioridad/Estado:", cb_estado, 4)

        # Foto Referencia
        tk.Label(form, text="Foto de Referencia (Web/Móvil):", bg=COLOR_FONDO, font=("Segoe UI", 12, "bold")).grid(row=5, column=0, columnspan=2, sticky="w", pady=(15, 0))
        
        f_frame = tk.Frame(form, bg=COLOR_FONDO)
        f_frame.grid(row=6, column=0, columnspan=2, sticky="ew", pady=5)
        
        tk.Entry(f_frame, textvariable=self.foto_path_var, state="readonly", font=("Segoe UI", 10)).pack(side="left", fill="x", expand=True, padx=(0, 10))
        tk.Button(f_frame, text="📁 Buscar Foto", command=self.seleccionar_foto, bg=COLOR_MORADO, fg="white", font=("Segoe UI", 10)).pack(side="right", padx=2)
        tk.Button(f_frame, text="🤖 IA", command=self.analizar_con_ia, bg="#8b5cf6", fg="white", font=("Segoe UI", 10, "bold")).pack(side="right")

        self.canvas_img = tk.Canvas(form, width=400, height=250, bg="white", highlightthickness=1, bd=0)
        self.canvas_img.grid(row=7, column=0, columnspan=2, pady=20)
        self.img_ref = None

        tk.Button(form, text="💾 GUARDAR PROYECTO", command=self.guardar, 
                  bg=COLOR_VERDE, fg="white", font=("Segoe UI", 12, "bold")).grid(row=8, column=0, columnspan=2, pady=20, sticky="ew", padx=50)

        # Botón para subir carpeta completa
        tk.Label(form, text="O preferiblemente:", bg=COLOR_FONDO, font=("Segoe UI", 10, "italic"), fg="#64748b").grid(row=9, column=0, columnspan=2, pady=(10,0))
        tk.Button(form, text="📁 Subir Carpeta de Imágenes", command=self.subir_carpeta, bg="#f3f4f6", fg="#1e293b", font=("Segoe UI", 10, "bold")).grid(row=10, column=0, columnspan=2, pady=10, sticky="ew", padx=50)

    def analizar_con_ia(self):
        p = self.foto_path_var.get()
        if not p or not os.path.exists(p):
            messagebox.showwarning("IA", "Selecciona una foto primero")
            return
            
        messagebox.showinfo("IA", "Analizando imagen... esto rellenará los campos automáticamente.")
        from modulo1_nucleo import analizar_imagen_ia
        res = analizar_imagen_ia(p, prompt_type="producto")
        
        if res:
            if "nombre" in res: self.nombre_var.set(res["nombre"])
            if "categoria" in res: self.categoria_var.set(res["categoria"])
            if "subcategoria" in res: self.subcategoria_var.set(res["subcategoria"])
            if "marca" in res: self.marca_var.set(res["marca"])
            messagebox.showinfo("IA", "Datos rellenados correctamente.")
        else:
            messagebox.showerror("IA", "No se pudo analizar la imagen. Verifica tus llaves API.")

    def add_row(self, parent, text, widget, row):
        tk.Label(parent, text=text, bg=COLOR_FONDO, font=("Segoe UI", 11, "bold")).grid(row=row, column=0, sticky="w", pady=8, padx=(0, 20))
        widget.grid(row=row, column=1, sticky="ew", pady=8)
        parent.grid_columnconfigure(1, weight=1)

    def seleccionar_foto(self):
        p = filedialog.askopenfilename()
        if p:
            self.foto_path_var.set(p)
            img = Image.open(p)
            img.thumbnail((400, 250))
            self.img_ref = ImageTk.PhotoImage(img)
            self.canvas_img.delete("all")
            self.canvas_img.create_image(200, 125, image=self.img_ref)

    def subir_carpeta(self):
        ruta = filedialog.askdirectory(title="Selecciona la carpeta con las fotos de los proyectos")
        if ruta:
            cant = self.gestor.subir_carpeta_proyectos(ruta)
            if cant > 0:
                messagebox.showinfo("Éxito", f"Se han añadido {cant} nuevos proyectos desde la carpeta.")
                self.limpiar()
                if self.on_save_callback: self.on_save_callback()
            else:
                messagebox.showwarning("Aviso", "No se encontraron imágenes en la carpeta seleccionada.")

    def guardar(self):
        if not self.nombre_var.get() or not self.foto_path_var.get():
            messagebox.showwarning("Aviso", "Nombre y Foto son obligatorios")
            return
            
        # Asegurar que la imagen está en la carpeta de proyectos
        from modulo3_gestion import RUTA_PROYECTOS
        import shutil
        orig = self.foto_path_var.get()
        if os.path.exists(orig) and RUTA_PROYECTOS.lower() not in orig.lower():
            try:
                # Nombre único para evitar conflictos
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                filename = f"{timestamp}_{os.path.basename(orig)}"
                dest = os.path.join(RUTA_PROYECTOS, filename)
                shutil.copy2(orig, dest)
                final_foto = dest
            except Exception as e:
                print(f"Error copiando foto: {e}")
                final_foto = orig
        else:
            final_foto = orig

        datos = {
            "NOMBRE": self.nombre_var.get(),
            "CATEGORIA": self.categoria_var.get(),
            "SUBCATEGORIA": self.subcategoria_var.get(),
            "MARCA": self.marca_var.get(),
            "ESTADO": self.estado_var.get(),
            "FOTO_REFERENCIA": final_foto,
            "UNIDADES_REALIZADAS": "0"
        }
        
        if self.gestor.guardar_proyecto(datos):
            messagebox.showinfo("Éxito", "Proyecto guardado en la base de datos")
            self.limpiar()
            if self.on_save_callback: self.on_save_callback()
        else:
            messagebox.showerror("Error", "No se pudo guardar el proyecto")

    def limpiar(self):
        self.nombre_var.set("")
        self.categoria_var.set("")
        self.subcategoria_var.set("")
        self.marca_var.set("")
        self.estado_var.set("Pendiente")
        self.foto_path_var.set("")
        self.canvas_img.delete("all")

# ========================================
# UI: BUSCADOR DE FUTUROS (ESTILO EDICIÓN)
# ========================================

class TabBuscadorFuturos(tk.Frame):
    def __init__(self, parent):
        super().__init__(parent, bg=COLOR_FONDO)
        self.gestor = GestorFuturosProyectos()
        self.img_refs = []
        self.setup_ui()

    def setup_ui(self):
        # Panel Lateral de Filtros (Estilo Edición)
        self.panel_filtros = tk.Frame(self, bg="#f1f5f9", width=250, padx=15, pady=15, bd=1, relief=tk.SOLID)
        self.panel_filtros.pack(side="left", fill="y")
        self.panel_filtros.pack_propagate(False)

        tk.Label(self.panel_filtros, text="🔍 FILTROS", font=("Segoe UI", 14, "bold"), bg="#f1f5f9", fg=COLOR_MORADO).pack(pady=(0, 20))

        # Campos de Filtro
        tk.Label(self.panel_filtros, text="Nombre:", bg="#f1f5f9", font=("Segoe UI", 10, "bold")).pack(anchor="w")
        self.ent_nombre = ttk.Entry(self.panel_filtros)
        self.ent_nombre.pack(fill="x", pady=(0, 15))

        tk.Label(self.panel_filtros, text="Categoría:", bg="#f1f5f9", font=("Segoe UI", 10, "bold")).pack(anchor="w")
        self.cb_cat = ttk.Combobox(self.panel_filtros)
        self.cb_cat.pack(fill="x", pady=(0, 15))

        tk.Label(self.panel_filtros, text="Estado:", bg="#f1f5f9", font=("Segoe UI", 10, "bold")).pack(anchor="w")
        self.cb_estado = ttk.Combobox(self.panel_filtros, values=["", "Pendiente", "Urgente", "En Proceso", "Terminado"])
        self.cb_estado.pack(fill="x", pady=(0, 15))

        tk.Button(self.panel_filtros, text="APLICAR FILTROS", command=self.buscar, bg=COLOR_MORADO, fg="white", font=("Segoe UI", 10, "bold"), pady=8).pack(fill="x", pady=(20, 10))
        tk.Button(self.panel_filtros, text="🔄 ACTUALIZAR", command=self.buscar, bg="#3b82f6", fg="white", font=("Segoe UI", 10, "bold")).pack(fill="x", pady=5)
        tk.Button(self.panel_filtros, text="📂 ABRIR CARPETA", command=self.abrir_carpeta, bg="#64748b", fg="white", font=("Segoe UI", 10)).pack(fill="x", pady=5)
        tk.Button(self.panel_filtros, text="LIMPIAR", command=self.limpiar, bg="#475569", fg="white", font=("Segoe UI", 10)).pack(fill="x")

        # Área de Resultados
        right_panel = tk.Frame(self, bg=COLOR_FONDO)
        right_panel.pack(side="right", fill="both", expand=True)

        self.canvas = tk.Canvas(right_panel, bg="#f8fafc", bd=0, highlightthickness=0)
        self.scroll = ttk.Scrollbar(right_panel, orient="vertical", command=self.canvas.yview)
        self.results_frame = tk.Frame(self.canvas, bg="#f8fafc")

        self.results_frame.bind("<Configure>", lambda e: self.canvas.configure(scrollregion=self.canvas.bbox("all")))
        self.canvas.create_window((0,0), window=self.results_frame, anchor="nw")
        self.canvas.configure(yscrollcommand=self.scroll.set)

        self.canvas.pack(side="left", fill="both", expand=True)
        self.scroll.pack(side="right", fill="y")
        
        self.actualizar_combos()
        self.buscar()

    def abrir_carpeta(self):
        from modulo3_gestion import RUTA_PROYECTOS
        if not os.path.exists(RUTA_PROYECTOS): os.makedirs(RUTA_PROYECTOS, exist_ok=True)
        os.startfile(RUTA_PROYECTOS)


    def actualizar_combos(self):
        cats = [""] + self.gestor.obtener_categorias()
        self.cb_cat['values'] = cats

    def limpiar(self):
        self.ent_nombre.delete(0, tk.END)
        self.cb_cat.set("")
        self.cb_estado.set("")
        self.buscar()

    def buscar(self):
        filtros = {
            "NOMBRE": self.ent_nombre.get().strip(),
            "CATEGORIA": self.cb_cat.get(),
            "ESTADO": self.cb_estado.get()
        }
        res = self.gestor.buscar_proyectos(filtros)
        self.mostrar_resultados(res)

    def mostrar_resultados(self, resultados):
        for w in self.results_frame.winfo_children(): w.destroy()
        self.img_refs = []
        
        if not resultados:
            tk.Label(self.results_frame, text="No se encontraron artículos en la lista.", bg="#f8fafc", font=("Segoe UI", 12)).pack(pady=100, padx=200)
            return

        cols = 3
        for i, item in enumerate(resultados):
            f = tk.Frame(self.results_frame, bg="white", padx=15, pady=15, highlightthickness=1, highlightbackground="#e2e8f0")
            f.grid(row=i//cols, column=i%cols, padx=15, pady=15, sticky="nsew")
            
            # Etiqueta de Estado
            color_estado = "#f59e0b" if item['ESTADO'] == "Pendiente" else "#10b981"
            if item['ESTADO'] == "Urgente": color_estado = "#ef4444"
            
            lbl_estado = tk.Label(f, text=item['ESTADO'].upper(), font=("Segoe UI", 8, "bold"), bg=color_estado, fg="white", padx=8)
            lbl_estado.pack(anchor="e")

            tk.Label(f, text=item['NOMBRE'], font=("Segoe UI", 12, "bold"), bg="white", wraplength=220).pack(pady=5)
            
            try:
                foto = item['FOTO_REFERENCIA']
                if foto and os.path.exists(foto):
                    img = Image.open(foto)
                    img.thumbnail((220, 180))
                    ph = ImageTk.PhotoImage(img)
                    self.img_refs.append(ph)
                    tk.Label(f, image=ph, bg="white").pack(pady=10)
                else:
                    tk.Label(f, text="[Sin Imagen]", bg="white", fg="#94a3b8").pack(pady=40)
            except:
                tk.Label(f, text="[Error Foto]", bg="white", fg="red").pack(pady=40)
            
            info = f"{item.get('CATEGORIA', 'Sin Cat')} | {item.get('MARCA', 'Sin Marca')}"
            tk.Label(f, text=info, font=("Segoe UI", 9), bg="white", fg="#64748b").pack()
            
            # Botones de acción
            btn_frame = tk.Frame(f, bg="white")
            btn_frame.pack(fill="x", pady=(10, 0))

            # Botón editar
            btn_edit = tk.Button(btn_frame, text="✏️", command=lambda it=item: self.editar(it), bg="#e0f2fe", fg="#0369a1", relief=tk.FLAT, font=("Segoe UI", 10), width=4)
            btn_edit.pack(side="left", padx=2)

            # Botón enviar a producción
            btn_prod = tk.Button(btn_frame, text="🏭", command=lambda it=item: self.enviar_produccion(it),
                                 bg="#fef3c7", fg="#92400e", relief=tk.FLAT, font=("Segoe UI", 10), width=4)
            btn_prod.pack(side="left", padx=2)

            # Botón eliminar
            btn_del = tk.Button(btn_frame, text="🗑️", command=lambda id=item['id']: self.eliminar(id), bg="#fee2e2", fg="#ef4444", relief=tk.FLAT, font=("Segoe UI", 10), width=4)
            btn_del.pack(side="right", padx=2)


    def enviar_produccion(self, item):
        """Crea un pedido de prueba interna en la cola de producción."""
        nombre = item.get('NOMBRE', 'Proyecto sin nombre')
        if not messagebox.askyesno("Enviar a Producción",
                                   f"¿Enviar '{nombre}' a la cola de producción?\n\n"
                                   f"🧪 Se creará como PRUEBA INTERNA\n"
                                   f"   • No se cobra\n   • No se envía a ningún cliente"):
            return
        try:
            from modulo3_gestion import get_db_connection
            from datetime import datetime as _dt
            conn = get_db_connection()
            if not conn: return
            cursor = conn.cursor()

            # Asegurar columnas adicionales (Sintaxis MySQL)
            cursor.execute("DESCRIBE pedidos")
            cols = [c[0] for c in cursor.fetchall()]
            
            columnas_extra = [
                ('sku_articulo', 'TEXT'), 
                ('notas', 'TEXT'),
                ('colab_id', 'INTEGER'), 
                ('encargo_id', 'INTEGER'),
                ('futuro_id', 'INTEGER')
            ]
            
            for col, tipo in columnas_extra:
                if col not in cols:
                    try:
                        cursor.execute(f'ALTER TABLE pedidos ADD COLUMN {col} {tipo}')
                    except: pass

            prio_map = {'Urgente': 'Rojo', 'En Proceso': 'Amarillo'}
            prioridad = prio_map.get(item.get('ESTADO', ''), 'Verde')
            detalles = f"🧪 PRUEBA INTERNA — No enviar / No cobrar\n{nombre}"
            notas = f"Futuro Proyecto #{item['id']} | Cat: {item.get('CATEGORIA', '')}"

            cursor.execute('''
                INSERT INTO pedidos (id_cliente, fecha_pedido, prioridad, estado,
                                     sku_articulo, detalles_criticos, notas, futuro_id)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            ''', (None, _dt.now().strftime("%Y-%m-%d %H:%M"),
                  prioridad, 'Por empezar',
                  item.get('SKU', ''), detalles, notas, item['id']))
            
            pedido_id = cursor.lastrowid
            cursor.execute("UPDATE futuros_proyectos SET ESTADO='En Proceso' WHERE id=%s", (item['id'],))
            conn.commit()
            conn.close()
            messagebox.showinfo("Enviado ✅",
                                f"Pedido #{pedido_id} creado en la cola de producción.\n🧪 Prueba interna - No cobrar")
            self.buscar()   # Refrescar la lista
            messagebox.showinfo("Enviado ✅",
                                f"Pedido #{pedido_id} creado en la cola de producción.\n🧪 Prueba interna - No cobrar")
            self.buscar()   # Refrescar la lista
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo crear el pedido:\n{e}")

    def editar(self, item):
        """Abre una ventana para editar los datos del proyecto con scroll y todos los campos"""
        edit_win = tk.Toplevel(self)
        edit_win.title(f"Editar Proyecto: {item['NOMBRE']}")
        edit_win.geometry("500x700")
        edit_win.configure(bg=COLOR_FONDO)
        edit_win.transient(self.winfo_toplevel())
        edit_win.grab_set()

        # Centrar ventana
        edit_win.update_idletasks()
        width = edit_win.winfo_width()
        height = edit_win.winfo_height()
        x = (edit_win.winfo_screenwidth() // 2) - (width // 2)
        y = (edit_win.winfo_screenheight() // 2) - (height // 2)
        edit_win.geometry(f'{width}x{height}+{x}+{y}')

        # Contenedor con Scrollbar
        canvas = tk.Canvas(edit_win, bg=COLOR_FONDO, highlightthickness=0)
        scroll = ttk.Scrollbar(edit_win, orient="vertical", command=canvas.yview)
        scrollable_frame = tk.Frame(canvas, bg=COLOR_FONDO, padx=20, pady=20)

        scrollable_frame.bind(
            "<Configure>",
            lambda e: canvas.configure(scrollregion=canvas.bbox("all"))
        )

        canvas.create_window((0, 0), window=scrollable_frame, anchor="nw", width=width-20)
        canvas.configure(yscrollcommand=scroll.set)

        canvas.pack(side="left", fill="both", expand=True)
        scroll.pack(side="right", fill="y")

        tk.Label(scrollable_frame, text="EDITAR PROYECTO COMPLETO", font=("Segoe UI", 14, "bold"), bg=COLOR_FONDO, fg=COLOR_MORADO).pack(pady=(0, 20))

        # Helper para añadir campos
        def add_field(parent, label_text, value, is_combo=False, combo_values=None, is_text=False):
            tk.Label(parent, text=label_text, bg=COLOR_FONDO, font=("Segoe UI", 9, "bold")).pack(anchor="w", pady=(10, 0))
            if is_combo:
                widget = ttk.Combobox(parent, values=combo_values or [])
                widget.set(str(value or ''))
                widget.pack(fill="x", pady=(2, 0))
                return widget
            elif is_text:
                widget = tk.Text(parent, height=4, font=("Segoe UI", 10))
                widget.insert("1.0", str(value or ''))
                widget.pack(fill="x", pady=(2, 0))
                return widget
            else:
                widget = ttk.Entry(parent)
                widget.insert(0, str(value or ''))
                widget.pack(fill="x", pady=(2, 0))
                return widget

        # Campos
        ent_nombre = add_field(scrollable_frame, "Nombre:", item.get('NOMBRE'))
        ent_sku = add_field(scrollable_frame, "SKU (Referencia):", item.get('SKU'))
        cb_cat = add_field(scrollable_frame, "Categoría:", item.get('CATEGORIA'), is_combo=True, combo_values=self.gestor.obtener_categorias())
        ent_sub = add_field(scrollable_frame, "Subcategoría:", item.get('SUBCATEGORIA'))
        ent_marca = add_field(scrollable_frame, "Marca:", item.get('MARCA'))
        cb_est = add_field(scrollable_frame, "Estado:", item.get('ESTADO'), is_combo=True, combo_values=["Pendiente", "Urgente", "En Proceso", "Terminado"])
        ent_precio = add_field(scrollable_frame, "Precio:", item.get('PRECIO'))
        ent_color = add_field(scrollable_frame, "Color:", item.get('COLOR'))
        ent_fest = add_field(scrollable_frame, "Festividad:", item.get('FESTIVIDAD'))
        ent_real = add_field(scrollable_frame, "Unidades Realizadas:", item.get('UNIDADES_REALIZADAS') or '0')
        txt_desc = add_field(scrollable_frame, "Descripción:", item.get('DESCRIPCION'), is_text=True)

        def guardar_cambios():
            datos = {
                "NOMBRE": ent_nombre.get().strip(),
                "SKU": ent_sku.get().strip(),
                "CATEGORIA": cb_cat.get().strip(),
                "SUBCATEGORIA": ent_sub.get().strip(),
                "MARCA": ent_marca.get().strip(),
                "ESTADO": cb_est.get(),
                "PRECIO": ent_precio.get().strip(),
                "COLOR": ent_color.get().strip(),
                "FESTIVIDAD": ent_fest.get().strip(),
                "UNIDADES_REALIZADAS": ent_real.get().strip(),
                "DESCRIPCION": txt_desc.get("1.0", tk.END).strip()
            }
            if self.gestor.modificar_proyecto(item['id'], datos):
                messagebox.showinfo("Éxito", "Proyecto actualizado correctamente")
                edit_win.destroy()
                self.buscar()
            else:
                messagebox.showerror("Error", "No se pudieron guardar los cambios")

        tk.Button(scrollable_frame, text="GUARDAR CAMBIOS", command=guardar_cambios, bg=COLOR_VERDE, fg="white", font=("Segoe UI", 10, "bold"), pady=12).pack(fill="x", pady=(30, 10))
        tk.Button(scrollable_frame, text="CANCELAR", command=edit_win.destroy, bg="#94a3b8", fg="white", font=("Segoe UI", 10)).pack(fill="x", pady=(0, 20))

    def eliminar(self, id_proyecto):
        if messagebox.askyesno("Confirmar", "¿Eliminar este artículo de la lista de creación?"):
            if self.gestor.eliminar_proyecto(id_proyecto):
                self.buscar()

# ========================================
# UI: BUSCADOR DE CREADOS (CATÁLOGO REAL - SQL)
# ========================================

class TabBuscadorCreados(tk.Frame):
    def __init__(self, parent, gestor_principal):
        super().__init__(parent, bg=COLOR_FONDO)
        self.gestor = gestor_principal # Instancia de GestorProductos (SQL)
        self.img_refs = []
        self.productos_filtrados = []
        self.setup_ui()

    def setup_ui(self):
        # Main layout con panel de filtros a la izquierda
        self.panel_filtros = PanelFiltros(self, self.ejecutar_busqueda_avanzada)
        
        # Área derecha (Resultados)
        derecha = tk.Frame(self, bg=COLOR_FONDO)
        derecha.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

        top = tk.Frame(derecha, bg="white", pady=15, padx=20)
        top.pack(fill="x")

        tk.Label(top, text="🔍 Búsqueda Rápida:", bg="white", font=("Segoe UI", 12, "bold")).pack(side="left")
        self.ent = tk.Entry(top, font=("Segoe UI", 12), width=30)
        self.ent.pack(side="left", padx=10)
        self.ent.bind("<Return>", lambda e: self.ejecutar_busqueda_basica())
        
        tk.Button(top, text="BUSCAR", command=self.ejecutar_busqueda_basica, bg=COLOR_MORADO, fg="white", font=("Segoe UI", 10, "bold")).pack(side="left")

        # Área scrollable
        self.canvas = tk.Canvas(derecha, bg="#f8fafc")
        self.scroll = ttk.Scrollbar(derecha, orient="vertical", command=self.canvas.yview)
        self.frame = tk.Frame(self.canvas, bg="#f8fafc")

        self.frame.bind("<Configure>", lambda e: self.canvas.configure(scrollregion=self.canvas.bbox("all")))
        self.canvas.create_window((0,0), window=self.frame, anchor="nw")
        self.canvas.configure(yscrollcommand=self.scroll.set)

        self.canvas.pack(side="left", fill="both", expand=True)
        self.scroll.pack(side="right", fill="y")

    def ejecutar_busqueda_avanzada(self, filtros, tipo=None):
        """Callback del PanelFiltros"""
        self.productos_filtrados = self.gestor.buscar_productos(filtros)
        self.renderizar_resultados()

    def ejecutar_busqueda_basica(self):
        txt = self.ent.get().strip().upper()
        if not txt:
            # Si está vacío, mostramos todo
            self.productos_filtrados = self.gestor.productos
        else:
            self.productos_filtrados = []
            for p in self.gestor.productos:
                if (txt in str(p.get('SKU_REF', '')).upper() or 
                    txt in str(p.get('NOMBRE', '')).upper() or 
                    txt in str(p.get('CATEGORIA', '')).upper()):
                    self.productos_filtrados.append(p)
        
        self.renderizar_resultados()

    def renderizar_resultados(self):
        for w in self.frame.winfo_children(): w.destroy()
        self.img_refs = []

        if not self.productos_filtrados:
            tk.Label(self.frame, text="No se encontraron artículos.", bg="#f8fafc", font=("Segoe UI", 12)).pack(pady=40)
            return

        cols = 4 # Más ancho si hay filtros
        # Ajustar columnas según el ancho del canvas
        self.update_idletasks()
        ancho_canvas = self.canvas.winfo_width()
        if ancho_canvas > 1000: cols = 5
        elif ancho_canvas < 600: cols = 2

        for i, res in enumerate(self.productos_filtrados):
            f = tk.Frame(self.frame, bg="white", padx=10, pady=10, highlightthickness=1, highlightbackground="#cbd5e1")
            f.grid(row=i//cols, column=i%cols, padx=10, pady=10)

            tk.Label(f, text=res.get('SKU_REF',''), font=("Segoe UI", 10, "bold"), bg="white", wraplength=180).pack()
            tk.Label(f, text=res.get('NOMBRE',''), font=("Segoe UI", 9), bg="white", fg="#475569", wraplength=180).pack()

            try:
                foto = res.get('FOTO_PORTADA', '')
                if foto and os.path.exists(foto):
                    img = Image.open(foto)
                    img.thumbnail((180, 160))
                    ph = ImageTk.PhotoImage(img)
                    self.img_refs.append(ph)
                    tk.Label(f, image=ph, bg="white").pack(pady=5)
                else:
                    tk.Label(f, text="[Sin Imagen]", bg="white", fg="#94a3b8").pack(pady=30)
            except:
                tk.Label(f, text="[Error Foto]", bg="white", fg="red").pack(pady=30)
            
            tk.Label(f, text=res.get('CATEGORIA',''), font=("Segoe UI", 8, "italic"), bg="white", fg="#64748b").pack()

# ========================================
# UI: VISTA TABLA DE FUTUROS (NUEVO 1.3)
# ========================================

class TabTablaFuturos(tk.Frame):
    def __init__(self, parent):
        super().__init__(parent, bg=COLOR_FONDO)
        self.gestor = GestorFuturosProyectos()
        self.setup_ui()

    def setup_ui(self):
        # Botones superiores
        top = tk.Frame(self, bg=COLOR_FONDO, pady=10)
        top.pack(fill="x")
        
        tk.Button(top, text="🔄 Actualizar Tabla", command=self.cargar_datos, bg="#3b82f6", fg="white", padx=15).pack(side="left", padx=20)
        
        # Tabla
        frame_tabla = tk.Frame(self, bg="white")
        frame_tabla.pack(fill="both", expand=True, padx=20, pady=10)
        
        cols = ("ID", "Fecha", "Nombre", "Categoría", "Marca", "Estado", "Realizadas")
        self.tree = ttk.Treeview(frame_tabla, columns=cols, show="headings")
        for c in cols:
            self.tree.heading(c, text=c)
            self.tree.column(c, width=100)
        
        self.tree.column("ID", width=50)
        self.tree.column("Realizadas", width=100)
        self.tree.column("Nombre", width=250)
        self.tree.pack(side="left", fill="both", expand=True)
        
        sc = ttk.Scrollbar(frame_tabla, orient="vertical", command=self.tree.yview)
        sc.pack(side="right", fill="y")
        self.tree.configure(yscrollcommand=sc.set)
        
        self.cargar_datos()

    def cargar_datos(self):
        for i in self.tree.get_children(): self.tree.delete(i)
        proyectos = self.gestor.buscar_proyectos()
        for p in proyectos:
            self.tree.insert("", tk.END, values=(
                p.get('id'), p.get('FECHA'), p.get('NOMBRE'),
                p.get('CATEGORIA'), p.get('MARCA'), p.get('ESTADO'),
                p.get('UNIDADES_REALIZADAS', '0')
            ))

# ========================================
# CLASE PRINCIPAL DEL MÓDULO (RECICLADA)
# ========================================

class TabFuturosProyectos(tk.Frame):
    """Pestaña unificada para Proyectos Futuros (Usando SQL)"""
    def __init__(self, parent, gestor_principal):
        super().__init__(parent, bg=COLOR_FONDO)
        self.gestor = gestor_principal
        
        # Sub-notebook
        self.nb = ttk.Notebook(self)
        self.nb.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Pestañas hijas
        self.tab_reg = TabRegistro(self.nb, on_save_callback=self.refrescar)
        self.tab_fut = TabBuscadorFuturos(self.nb)
        self.tab_tabla = TabTablaFuturos(self.nb)
        
        self.nb.add(self.tab_reg, text=" 1.1 🆕 NUEVO PARA CREAR ")
        self.nb.add(self.tab_fut, text=" 1.2 🚀 LISTA DE ARTÍCULOS POR CREAR ")
        self.nb.add(self.tab_tabla, text=" 1.3 📊 TABLA DE PROYECTOS ")
        
    def refrescar(self):
        self.tab_fut.actualizar_combos()
        self.tab_fut.buscar()
        self.tab_tabla.cargar_datos()
