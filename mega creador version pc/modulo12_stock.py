import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import os
import pandas as pd
from PIL import Image, ImageTk
from modulo3_gestion import GestorMateriales
from modulo1_nucleo import analizar_imagen_ia

class TabStock:
    def __init__(self, parent, gestor, app_root):
        self.parent = parent
        self.gestor = gestor
        self.app_root = app_root
        self.gestor_mat = GestorMateriales()
        self.img_vinc_refs = []
        
        self.frame = tk.Frame(parent, bg="#f3f4f6")
        
        self.crear_interfaz()
        
    def crear_interfaz(self):
        # Notebook interno para sub-pestañas
        self.sub_nb = ttk.Notebook(self.frame)
        self.sub_nb.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        # Sub-pestaña 5.1: Inventario de Materiales (Tabla 3)
        self.tab_inv = tk.Frame(self.sub_nb, bg="#f3f4f6")
        self.sub_nb.add(self.tab_inv, text=" 5.1 🪵 Inventario Materiales")
        self.crear_tab_inventario()
        
        # Sub-pestaña 5.2: Galería de Materiales (NUEVO)
        self.tab_galeria_mat = TabBuscadorMateriales(self.sub_nb, self.gestor_mat)
        self.sub_nb.add(self.tab_galeria_mat, text=" 5.2 🖼️ Galería Materiales")

        # Sub-pestaña 5.3: Recetas y Despiece (Tabla 2)
        self.tab_recetas = tk.Frame(self.sub_nb, bg="#f3f4f6")
        self.sub_nb.add(self.tab_recetas, text=" 5.3 🪚 Despiece de Artículos")
        self.crear_tab_recetas()

    def crear_tab_inventario(self):
        # Layout: Formulario arriba, Tabla abajo
        top = tk.Frame(self.tab_inv, bg="#f3f4f6", pady=10)
        top.pack(fill=tk.X)
        
        form = ttk.LabelFrame(top, text="➕ Añadir/Actualizar Material")
        form.pack(padx=20, pady=10, fill=tk.X)
        
        tk.Label(form, text="ID Material (SKU):").grid(row=0, column=0, padx=5, pady=5)
        self.ent_mat_id = ttk.Entry(form, width=20)
        self.ent_mat_id.grid(row=0, column=1, padx=5)
        
        tk.Label(form, text="Nombre:").grid(row=0, column=2, padx=5)
        self.ent_mat_nom = ttk.Entry(form, width=30)
        self.ent_mat_nom.grid(row=0, column=3, padx=5)
        
        tk.Label(form, text="Unidad:").grid(row=1, column=0, padx=5, pady=5)
        self.ent_mat_uni = ttk.Combobox(form, values=["cm", "unidades", "m2"], width=10)
        self.ent_mat_uni.set("cm")
        self.ent_mat_uni.grid(row=1, column=1, padx=5)
        
        tk.Label(form, text="Cantidad (+):").grid(row=1, column=2, padx=5)
        self.ent_mat_cant = ttk.Entry(form, width=10)
        self.ent_mat_cant.grid(row=1, column=3, padx=5, sticky="w")
        
        # Foto Material
        self.foto_mat_var = tk.StringVar()
        tk.Label(form, text="Foto Material:").grid(row=2, column=0, padx=5)
        self.ent_mat_foto = ttk.Entry(form, textvariable=self.foto_mat_var, width=30, state="readonly")
        self.ent_mat_foto.grid(row=2, column=1, columnspan=2, padx=5, sticky="ew")
        tk.Button(form, text="📁", command=self.seleccionar_foto_material).grid(row=2, column=3, padx=5, sticky="w")

        tk.Label(form, text="Categoría:").grid(row=3, column=0, padx=5, pady=5)
        self.ent_mat_cat = ttk.Entry(form, width=20)
        self.ent_mat_cat.grid(row=3, column=1, padx=5)
        self.ent_mat_cat.bind("<FocusOut>", self.on_cat_sub_change)

        tk.Label(form, text="Subcategoría:").grid(row=3, column=2, padx=5)
        self.ent_mat_sub = ttk.Entry(form, width=20)
        self.ent_mat_sub.grid(row=3, column=3, padx=5)
        self.ent_mat_sub.bind("<FocusOut>", self.on_cat_sub_change)

        # Nuevos campos Marca/Color/Dim/Fest/PP
        tk.Label(form, text="Marca:").grid(row=4, column=0, padx=5, pady=5)
        self.ent_mat_marca = ttk.Entry(form, width=20)
        self.ent_mat_marca.grid(row=4, column=1, padx=5)

        tk.Label(form, text="Color:").grid(row=4, column=2, padx=5)
        self.ent_mat_color = ttk.Entry(form, width=20)
        self.ent_mat_color.grid(row=4, column=3, padx=5)

        tk.Label(form, text="Medidas:").grid(row=5, column=0, padx=5, pady=5)
        self.ent_mat_dim = ttk.Entry(form, width=20)
        self.ent_mat_dim.grid(row=5, column=1, padx=5)

        tk.Label(form, text="Festividad:").grid(row=5, column=2, padx=5)
        self.ent_mat_fest = ttk.Entry(form, width=20)
        self.ent_mat_fest.grid(row=5, column=3, padx=5)

        tk.Label(form, text="Punto Pedido:").grid(row=6, column=0, padx=5, pady=5)
        self.ent_mat_pp = ttk.Entry(form, width=10)
        self.ent_mat_pp.insert(0, "100")
        self.ent_mat_pp.grid(row=6, column=1, padx=5, sticky="w")

        tk.Button(form, text="💾 GUARDAR MATERIAL", bg="#10b981", fg="white", 
                  command=self.guardar_material, font=("Segoe UI", 10, "bold")).grid(row=6, column=4, padx=20, pady=10)
        
        # Preview Foto Material (Derecha del form)
        self.lbl_preview_mat = tk.Label(top, text="Sin foto", bg="#e5e7eb", width=15, height=7)
        self.lbl_preview_mat.pack(side=tk.RIGHT, padx=20)
        
        tk.Button(top, text="🔄 ACTUALIZAR", command=self.cargar_datos_inv, bg="#3b82f6", fg="white", font=("Segoe UI", 9, "bold")).pack(side=tk.RIGHT, padx=5)
        tk.Button(top, text="🗑️ Eliminar Seleccionado", command=self.eliminar_material, bg="#ef4444", fg="white").pack(side=tk.RIGHT, padx=5)
        
        # Tabla
        bot = tk.Frame(self.tab_inv, bg="#f3f4f6", padx=20, pady=10)
        bot.pack(fill=tk.BOTH, expand=True)
        
        columns = ("REF_MAT", "Nombre", "Categoría", "Subcategoría", "Unidad", "Stock_Actual", "Punto_Pedido", "Realizadas")
        self.tree_inv = ttk.Treeview(bot, columns=columns, show="headings")
        for c in columns:
            self.tree_inv.heading(c, text=c)
            self.tree_inv.column(c, width=100)
        self.tree_inv.column("Realizadas", width=80)
        self.tree_inv.pack(fill=tk.BOTH, expand=True)
        self.tree_inv.bind("<<TreeviewSelect>>", self.on_material_selected)
        
        self.cargar_datos_inv()

    def crear_tab_recetas(self):
        # Izquierda: Lista de productos (Tabla 2)
        # Derecha: Vista de despiece (Texto, Imagen, Medidas)
        paned = tk.PanedWindow(self.tab_recetas, orient=tk.HORIZONTAL, bg="#f3f4f6", sashwidth=4)
        paned.pack(fill=tk.BOTH, expand=True)
        
        left = tk.Frame(paned, bg="#f3f4f6", padx=10, pady=10)
        paned.add(left, width=400)
        
        btns_top = tk.Frame(left, bg="#f3f4f6")
        btns_top.pack(fill=tk.X, pady=5)
        tk.Button(btns_top, text="🔄 Sincronizar", bg="#4f46e5", fg="white", 
                  command=self.cargar_datos_recetas).pack(side=tk.LEFT, padx=2)
        
        tk.Button(btns_top, text="☑️ Todo", bg="#10b981", fg="white", font=("Segoe UI", 8),
                  command=lambda: self.marcar_todos(True)).pack(side=tk.LEFT, padx=2)
        tk.Button(btns_top, text="☐ Nada", bg="#64748b", fg="white", font=("Segoe UI", 8),
                  command=lambda: self.marcar_todos(False)).pack(side=tk.LEFT, padx=2)
        
        self.tree_recetas = ttk.Treeview(left, columns=("Sel", "SKU", "Nombre", "Realizadas", "Terminados"), show="headings")
        self.tree_recetas.heading("Sel", text="[ ]")
        self.tree_recetas.heading("SKU", text="SKU")
        self.tree_recetas.heading("Nombre", text="Nombre")
        self.tree_recetas.heading("Realizadas", text="Tot.Realiz.")
        self.tree_recetas.heading("Terminados", text="📦 Terminados")
        self.tree_recetas.column("Sel", width=30, anchor="center")
        self.tree_recetas.column("Realizadas", width=80, anchor="center")
        self.tree_recetas.column("Terminados", width=90, anchor="center")
        self.tree_recetas.pack(fill=tk.BOTH, expand=True)

        self.tree_recetas.bind("<<TreeviewSelect>>", self.on_receta_selected)
        self.tree_recetas.bind("<Button-1>", self.on_tree_click)
        
        right = tk.Frame(paned, bg="white", padx=20, pady=20)
        paned.add(right)
        
        self.lbl_receta_sku = tk.Label(right, text="Selecciona un producto", font=("Segoe UI", 16, "bold"), bg="white")
        self.lbl_receta_sku.pack(anchor=tk.W)
        
        self.lbl_receta_medidas = tk.Label(right, text="Medidas: --", font=("Segoe UI", 11), bg="white", fg="#4b5563")
        self.lbl_receta_medidas.pack(anchor=tk.W, pady=2)
        
        # Indicador de Capacidad (Ahora en el header)
        self.lbl_status_stock = tk.Label(right, text="", font=("Segoe UI", 12, "bold"), bg="white")
        self.lbl_status_stock.pack(anchor=tk.W, pady=(0,5))
        
        # --- DASHBOARD DE 3 TIPOS DE STOCK ---
        dash = tk.Frame(right, bg="#f8fafc", relief=tk.GROOVE, bd=1)
        dash.pack(fill=tk.X, padx=0, pady=(0, 8))
        
        def _dash_col(parent, title, color):
            f = tk.Frame(parent, bg=color, padx=12, pady=8)
            f.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=2, pady=4)
            tk.Label(f, text=title, font=("Segoe UI", 8, "bold"), bg=color, fg="white").pack()
            lbl = tk.Label(f, text="--", font=("Segoe UI", 20, "bold"), bg=color, fg="white")
            lbl.pack()
            return lbl
        
        self.lbl_fab = _dash_col(dash, "🏭 FABRICABLES\n(con materiales)", "#4f46e5")
        self.lbl_fisico = _dash_col(dash, "📦 TERMINADOS\n(listos enviar)", "#059669")
        self.lbl_realizadas_dash = _dash_col(dash, "🔢 TOTAL REALIZADAS\n(histórico total)", "#b45309")
        
        # Botón editar stock físico
        self.btn_editar_fisico = tk.Button(right, text="📦 EDITAR STOCK FÍSICO (Terminados)", bg="#059669", fg="white",
                                           font=("Segoe UI", 11, "bold"), command=self.editar_stock_fisico,
                                           state=tk.DISABLED, pady=5)
        self.btn_editar_fisico.pack(fill=tk.X, pady=(5, 12))

        
        # Botones de Acción
        actions_frame = tk.Frame(right, bg="white")
        actions_frame.pack(fill=tk.X, pady=10)
        tk.Button(actions_frame, text="🤖 Generar Despiece IA", bg="#8b5cf6", fg="white", 
                  command=self.generar_despiece_ia, font=("Segoe UI", 10, "bold")).pack(side=tk.LEFT, padx=(0,10))
        self.btn_check_cap = tk.Button(actions_frame, text="📊 Calcular Capacidad", bg="#059669", fg="white", 
                                      command=self.verificar_capacidad, state=tk.DISABLED)
        self.btn_check_cap.pack(side=tk.LEFT)
        
        tk.Button(actions_frame, text="✅ Registrar Realizadas", bg="#0ea5e9", fg="white",
                  command=self.sumar_realizadas, font=("Segoe UI", 10, "bold")).pack(side=tk.LEFT, padx=10)
        
        # Split horizontal para imagen y Galería de Materiales
        self.mid_paned = tk.PanedWindow(right, orient=tk.HORIZONTAL, bg="white", sashwidth=2)
        self.mid_paned.pack(fill=tk.BOTH, expand=True)
        
        # Lado Izquierdo: Imagen del Artículo
        self.lbl_img_receta = tk.Label(self.mid_paned, text="Sin imagen", bg="#f3f4f6", relief=tk.SUNKEN)
        self.mid_paned.add(self.lbl_img_receta, width=400)
        
        # Lado Derecho: Galería de Materiales (Scrollable)
        right_gallery_frame = tk.Frame(self.mid_paned, bg="white")
        self.mid_paned.add(right_gallery_frame)
        
        tk.Label(right_gallery_frame, text="🖼️ MATERIALES COMPONENTES", font=("Segoe UI", 10, "bold"), bg="white").pack(pady=5)
        
        self.canvas_vinc = tk.Canvas(right_gallery_frame, bg="white", highlightthickness=0)
        self.scroll_vinc = ttk.Scrollbar(right_gallery_frame, orient="vertical", command=self.canvas_vinc.yview)
        self.frame_galeria_vinc = tk.Frame(self.canvas_vinc, bg="white")
        
        self.canvas_vinc.configure(yscrollcommand=self.scroll_vinc.set)
        self.canvas_vinc.pack(side="left", fill="both", expand=True)
        self.scroll_vinc.pack(side="right", fill="y")
        
        self.canvas_vinc.create_window((0,0), window=self.frame_galeria_vinc, anchor="nw")
        
        def on_vinc_configure(e):
            self.canvas_vinc.configure(scrollregion=self.canvas_vinc.bbox("all"))
            # Forzar el ancho del frame al ancho del canvas
            self.canvas_vinc.itemconfig(1, width=e.width)

        self.frame_galeria_vinc.bind("<Configure>", lambda e: self.canvas_vinc.configure(scrollregion=self.canvas_vinc.bbox("all")))
        self.canvas_vinc.bind("<Configure>", on_vinc_configure)
        
        # Status y Capacidad (YA NO ESTÁ AQUÍ)

        # Sección para añadir materiales específicos a la receta
        self.frame_mat_receta = ttk.LabelFrame(right, text="🔗 Vincular Material a Receta")
        self.frame_mat_receta.pack(fill=tk.X, pady=10)
        
        tk.Label(self.frame_mat_receta, text="Material:").pack(side=tk.LEFT, padx=5)
        self.cb_mat_link = ttk.Combobox(self.frame_mat_receta, width=20)
        self.cb_mat_link.pack(side=tk.LEFT, padx=5)
        
        tk.Label(self.frame_mat_receta, text="Cant (cm/un):").pack(side=tk.LEFT, padx=5)
        self.ent_mat_link_cant = ttk.Entry(self.frame_mat_receta, width=8)
        self.ent_mat_link_cant.pack(side=tk.LEFT, padx=5)
        
        self.lbl_stock_disp = tk.Label(self.frame_mat_receta, text="Stock: --", font=("Segoe UI", 9, "bold"), fg="#059669")
        self.lbl_stock_disp.pack(side=tk.LEFT, padx=10)
        
        self.cb_mat_link.bind("<<ComboboxSelected>>", self.on_mat_to_link_selected)
        
        tk.Button(self.frame_mat_receta, text="➕ Vincular Seleccionados", bg="#4f46e5", fg="white", 
                  command=self.vincular_material).pack(side=tk.LEFT, padx=10, pady=5)
        
        # Tabla de materiales vinculados (mini)
        self.tree_mat_vinc = ttk.Treeview(right, columns=("Material", "Cantidad"), show="headings", height=5)
        self.tree_mat_vinc.heading("Material", text="Material")
        self.tree_mat_vinc.heading("Cantidad", text="Cant.")
        self.tree_mat_vinc.column("Material", width=250)
        self.tree_mat_vinc.pack(fill=tk.X, pady=5)

        # --- SECCIÓN MEDIDAS DE ENVÍO (EDICIÓN EN LOTE) ---
        ship_frame = ttk.LabelFrame(right, text=" 📦 MEDIDAS DE ENVÍO (Edición en Lote) ")
        ship_frame.pack(fill=tk.X, pady=10)
        
        f_inputs = tk.Frame(ship_frame, bg="white")
        f_inputs.pack(fill=tk.X, padx=10, pady=5)
        
        tk.Label(f_inputs, text="Peso (Kg):", bg="white", font=("Segoe UI", 9, "bold")).grid(row=0, column=0, sticky="w")
        self.ent_peso_ship = ttk.Entry(f_inputs, width=8)
        self.ent_peso_ship.grid(row=0, column=1, padx=5, pady=2)
        
        tk.Label(f_inputs, text="Largo (cm):", bg="white", font=("Segoe UI", 9, "bold")).grid(row=0, column=2, sticky="w", padx=(15,0))
        self.ent_largo_ship = ttk.Entry(f_inputs, width=8)
        self.ent_largo_ship.grid(row=0, column=3, padx=5)
        
        tk.Label(f_inputs, text="Ancho (cm):", bg="white", font=("Segoe UI", 9, "bold")).grid(row=1, column=0, sticky="w")
        self.ent_ancho_ship = ttk.Entry(f_inputs, width=8)
        self.ent_ancho_ship.grid(row=1, column=1, padx=5, pady=2)
        
        tk.Label(f_inputs, text="Alto (cm):", bg="white", font=("Segoe UI", 9, "bold")).grid(row=1, column=2, sticky="w", padx=(15,0))
        self.ent_alto_ship = ttk.Entry(f_inputs, width=8)
        self.ent_alto_ship.grid(row=1, column=3, padx=5)

        from modulo5_mejoras_visuales import BotonConFeedback
        self.btn_save_ship = BotonConFeedback(ship_frame, "Aplicar a Marcados [x]", self.guardar_medidas_lote, "#4f46e5", "💾")
        self.btn_save_ship.pack(pady=10)
        
        self.cargar_datos_recetas()

    def actualizar_combos_materiales(self):
        mats = self.gestor_mat.obtener_materiales()
        self.cb_mat_link['values'] = [m['REF_MAT'] for m in mats]

    # --- Lógica Inventario ---
    def cargar_datos_inv(self):
        for i in self.tree_inv.get_children(): self.tree_inv.delete(i)
        mats = self.gestor_mat.obtener_materiales()
        for m in mats:
            self.tree_inv.insert("", tk.END, values=(
                m['REF_MAT'], m['NOMBRE'], m.get('CATEGORIA',''), 
                m.get('SUBCATEGORIA',''), m['UNIDAD'], m['STOCK_ACTUAL'], m['PUNTO_PEDIDO'],
                m.get('UNIDADES_REALIZADAS', '0')
            ))

    def seleccionar_foto_material(self):
        f = filedialog.askopenfilename(title="Seleccionar foto de material", filetypes=[("Imágenes", "*.png *.jpg *.jpeg *.webp")])
        if f:
            self.foto_mat_var.set(f)
            self.mostrar_preview_material(f)

    def mostrar_preview_material(self, path):
        try:
            if not path or not os.path.exists(path):
                self.lbl_preview_mat.config(image='', text="Sin foto")
                return
            img = Image.open(path)
            img.thumbnail((120, 120))
            photo = ImageTk.PhotoImage(img)
            self.lbl_preview_mat.config(image=photo, text="")
            self.lbl_preview_mat.image = photo
        except:
            self.lbl_preview_mat.config(image='', text="Error")

    def on_material_selected(self, event):
        sel = self.tree_inv.selection()
        if not sel: return
        # Obtener datos completos de la DB para la foto
        ref = self.tree_inv.item(sel[0])['values'][0]
        mats = self.gestor_mat.obtener_materiales()
        m = next((mat for mat in mats if mat['REF_MAT'] == ref), None)
        if not m: return

        self.ent_mat_id.delete(0, tk.END); self.ent_mat_id.insert(0, m['REF_MAT'])
        self.ent_mat_nom.delete(0, tk.END); self.ent_mat_nom.insert(0, m['NOMBRE'])
        self.ent_mat_uni.set(m['UNIDAD'])
        self.ent_mat_cant.delete(0, tk.END)
        self.ent_mat_cat.delete(0, tk.END); self.ent_mat_cat.insert(0, m.get('CATEGORIA','') or '')
        self.ent_mat_sub.delete(0, tk.END); self.ent_mat_sub.insert(0, m.get('SUBCATEGORIA','') or '')
        
        self.ent_mat_marca.delete(0, tk.END); self.ent_mat_marca.insert(0, m.get('MARCA','') or '')
        self.ent_mat_color.delete(0, tk.END); self.ent_mat_color.insert(0, m.get('COLOR','') or '')
        self.ent_mat_dim.delete(0, tk.END); self.ent_mat_dim.insert(0, m.get('DIMENSIONES','') or '')
        self.ent_mat_fest.delete(0, tk.END); self.ent_mat_fest.insert(0, m.get('FESTIVIDAD','') or '')
        self.ent_mat_pp.delete(0, tk.END); self.ent_mat_pp.insert(0, str(m.get('PUNTO_PEDIDO', 100)))

        self.foto_mat_var.set(m.get('FOTO', '') or '')
        self.mostrar_preview_material(m.get('FOTO'))

    def on_cat_sub_change(self, event=None):
        cat = self.ent_mat_cat.get().strip()
        sub = self.ent_mat_sub.get().strip()
        if cat and sub and not self.ent_mat_id.get():
            num = self.gestor_mat.obtener_siguiente_numero_material(cat, sub)
            sku = f"{cat.replace(' ', '')[:3].upper()}{sub.replace(' ', '')[:3].upper()}{str(num).zfill(3)}"
            self.ent_mat_id.delete(0, tk.END)
            self.ent_mat_id.insert(0, sku)

    def guardar_material(self):
        mid = self.ent_mat_id.get().strip().upper()
        nom = self.ent_mat_nom.get().strip()
        uni = self.ent_mat_uni.get()
        cant = self.ent_mat_cant.get().strip() or "0"
        foto = self.foto_mat_var.get()
        cat = self.ent_mat_cat.get().strip()
        sub = self.ent_mat_sub.get().strip()
        
        if not mid:
            messagebox.showwarning("Aviso", "ID es obligatorio")
            return
            
        try:
            # Aseguras que la imagen está en la carpeta de materiales
            from modulo3_gestion import RUTA_MATERIALES
            import shutil
            if foto and os.path.exists(foto) and RUTA_MATERIALES.lower() not in foto.lower():
                try:
                    ext = os.path.splitext(foto)[1]
                    dest = os.path.join(RUTA_MATERIALES, f"{mid}{ext}")
                    shutil.copy2(foto, dest)
                    final_foto = dest
                except Exception as e:
                    print(f"Error copiando material foto: {e}")
                    final_foto = foto
            else:
                final_foto = foto

            # Nuevos Campos
            marca = self.ent_mat_marca.get().strip()
            color = self.ent_mat_color.get().strip()
            dim = self.ent_mat_dim.get().strip()
            fest = self.ent_mat_fest.get().strip()
            pp = self.ent_mat_pp.get().strip() or "100"

            # Si ya existe, sumar, si no, crear
            mats = self.gestor_mat.obtener_materiales()
            m_orig = next((m for m in mats if m['REF_MAT'] == mid), None)
            actual = m_orig['STOCK_ACTUAL'] if m_orig else 0
            
            nuevo_total = actual + float(cant)
            
            if self.gestor_mat.guardar_material(mid, nom, uni, nuevo_total, float(pp), final_foto, cat, sub, marca, color, dim, fest):
                messagebox.showinfo("Éxito", f"Material {mid} guardado con stock total: {nuevo_total}")
                self.cargar_datos_inv()
                self.actualizar_combos_materiales()
                
                # Limpiar campos
                self.ent_mat_id.delete(0, tk.END)
                self.ent_mat_nom.delete(0, tk.END)
                self.ent_mat_cant.delete(0, tk.END)
                self.ent_mat_marca.delete(0, tk.END)
                self.ent_mat_color.delete(0, tk.END)
                self.ent_mat_dim.delete(0, tk.END)
                self.ent_mat_fest.delete(0, tk.END)
                self.foto_mat_var.set("")
                self.lbl_preview_mat.config(image="", text="Sin foto")
            else:
                messagebox.showerror("Error", "No se pudo guardar el material")
        except Exception as e:
            messagebox.showerror("Error", f"Error al guardar: {e}")

    def eliminar_material(self):
        sel = self.tree_inv.selection()
        if not sel: return
        mid = self.tree_inv.item(sel[0])['values'][0]
        if messagebox.askyesno("Confirmar", f"¿Eliminar material {mid}?"):
            if self.gestor_mat.eliminar_material(mid):
                self.cargar_datos_inv()
                self.actualizar_combos_materiales()

    # --- Lógica Recetas ---
    def cargar_datos_recetas(self):
        for i in self.tree_recetas.get_children(): self.tree_recetas.delete(i)
        # Cargar productos BASE del catálogo
        for p in self.gestor.productos:
            if str(p.get('ES_VARIANTE', '')).upper() == 'BASE':
                self.tree_recetas.insert("", tk.END, values=("[ ]", p['SKU_REF'], p['NOMBRE'], 
                                                               p.get('UNIDADES_REALIZADAS', '0'),
                                                               p.get('STOCK_FISICO', '0')))


    def on_receta_selected(self, event):
        sel = self.tree_recetas.selection()
        if not sel: return
        item = self.tree_recetas.item(sel[0])
        sku = item['values'][1]
        
        # Buscar en el catálogo para el nombre e imagen
        prod = next((p for p in self.gestor.productos if p['SKU_REF'] == sku), None)
        if not prod: return

        self.lbl_receta_sku.config(text=f"📌 {sku}: {prod['NOMBRE']}")
        self.lbl_receta_medidas.config(text=f"📐 Medidas ARTÍCULO (Físicas): {prod.get('DIMENSIONES', '--')}")
        
        # Habilitar botones
        self.btn_check_cap.config(state=tk.NORMAL)
        self.btn_editar_fisico.config(state=tk.NORMAL)

        
        img_path = str(prod.get('FOTO_PORTADA', ''))
        if img_path and os.path.exists(img_path):
            self.mostrar_imagen(img_path)
        else:
            self.lbl_img_receta.config(image='', text="🖼️ Sin imagen")
            
        # Cargar materiales vinculados (SQL)
        for i in self.tree_mat_vinc.get_children(): self.tree_mat_vinc.delete(i)
        for w in self.frame_galeria_vinc.winfo_children(): w.destroy()
        self.img_vinc_refs = []
        
        # Actualizar dashboard de stock
        self.verificar_capacidad()
        
        # Precargar medidas de envío
        self.ent_peso_ship.delete(0, tk.END); self.ent_peso_ship.insert(0, str(prod.get('peso_envio', '0.5')))
        self.ent_largo_ship.delete(0, tk.END); self.ent_largo_ship.insert(0, str(prod.get('largo_envio', '20')))
        self.ent_ancho_ship.delete(0, tk.END); self.ent_ancho_ship.insert(0, str(prod.get('ancho_envio', '15')))
        self.ent_alto_ship.delete(0, tk.END); self.ent_alto_ship.insert(0, str(prod.get('alto_envio', '10')))

        desp = self.gestor_mat.obtener_despiece(sku)
        mats_all = self.gestor_mat.obtener_materiales()
        
        for d in desp:
            self.tree_mat_vinc.insert("", tk.END, values=(f"{d['REF_MAT']} ({d['NOMBRE']})", d['CANTIDAD']))
            
            # Galería visual mini (Ahora vertical/rejilla en el panel lateral)
            m_info = next((m for m in mats_all if m['REF_MAT'] == d['REF_MAT']), None)
            if m_info and m_info.get('FOTO'):
                try:
                    fp = m_info['FOTO']
                    if os.path.exists(fp):
                        img = Image.open(fp)
                        img.thumbnail((150, 150)) # Más grande como pidió el usuario
                        ph = ImageTk.PhotoImage(img)
                        self.img_vinc_refs.append(ph)
                        
                        f_item = tk.Frame(self.frame_galeria_vinc, bg="white", pady=15, padx=10)
                        f_item.pack(fill=tk.X)
                        
                        tk.Label(f_item, image=ph, bg="white").pack(side=tk.LEFT)
                        
                        v_info = tk.Frame(f_item, bg="white")
                        v_info.pack(side=tk.LEFT, padx=15, fill=tk.BOTH, expand=True)
                        
                        tk.Label(v_info, text=m_info['NOMBRE'], font=("Segoe UI", 11, "bold"), bg="white", wraplength=250).pack(anchor="w")
                        tk.Label(v_info, text=f"Referencia: {m_info['REF_MAT']}", font=("Segoe UI", 9), bg="white", fg="#64748b").pack(anchor="w")
                        tk.Label(v_info, text=f"Cant. vinculada: {d['CANTIDAD']} {m_info['UNIDAD']}", font=("Segoe UI", 10), bg="white").pack(anchor="w", pady=2)
                        
                        color_stock = "#059669" if m_info['STOCK_ACTUAL'] > 0 else "#ef4444"
                        tk.Label(v_info, text=f"EN ALMACÉN: {m_info['STOCK_ACTUAL']} {m_info['UNIDAD']}", 
                                 font=("Segoe UI", 10, "bold"), bg="white", fg=color_stock).pack(anchor="w")
                        
                        # Botones de Acción para el vínculo (Editar/Eliminar)
                        f_btns = tk.Frame(v_info, bg="white")
                        f_btns.pack(anchor="w", pady=5)
                        
                        tk.Button(f_btns, text="✏️", font=("Segoe UI", 8), bg="#3b82f6", fg="white", 
                                  command=lambda s=sku, m=m_info['REF_MAT'], c=d['CANTIDAD']: self.editar_cantidad_vinculo(s, m, c)).pack(side=tk.LEFT, padx=2)
                        
                        tk.Button(f_btns, text="🗑️", font=("Segoe UI", 8), bg="#ef4444", fg="white", 
                                  command=lambda s=sku, m=m_info['REF_MAT']: self.eliminar_vinculo(s, m)).pack(side=tk.LEFT, padx=2)
                except: pass

        self.btn_check_cap.config(state=tk.NORMAL)
        self.lbl_status_stock.config(text="")
        self.actualizar_combos_materiales()

    def on_mat_to_link_selected(self, event):
        ref = self.cb_mat_link.get()
        if not ref: return
        mats = self.gestor_mat.obtener_materiales()
        m = next((mat for mat in mats if mat['REF_MAT'] == ref), None)
        if m:
            self.lbl_stock_disp.config(text=f"Stock: {m['STOCK_ACTUAL']} {m['UNIDAD']}")
            if m['STOCK_ACTUAL'] <= 0:
                self.lbl_stock_disp.config(fg="#ef4444")
            else:
                self.lbl_stock_disp.config(fg="#059669")
            
            # Rellenar el campo de cantidad con el stock actual (sugerencia)
            self.ent_mat_link_cant.delete(0, tk.END)
            self.ent_mat_link_cant.insert(0, str(m['STOCK_ACTUAL']))

    def vincular_material(self):
        mat_ref = self.cb_mat_link.get()
        cant = self.ent_mat_link_cant.get()
        
        if not mat_ref or not cant: return
        
        # Obtener todos los SKUs marcados con [x]
        skus_to_link = []
        for item in self.tree_recetas.get_children():
            values = self.tree_recetas.item(item)['values']
            if values[0] == "[x]":
                skus_to_link.append(values[1])
        
        # Si no hay marcados, usar el actual seleccionado (backward compatibility)
        if not skus_to_link:
            sel = self.tree_recetas.selection()
            if sel:
                skus_to_link.append(self.tree_recetas.item(sel[0])['values'][1])
        
        if not skus_to_link:
            messagebox.showwarning("Aviso", "No has marcado ningún artículo con [x]")
            return
            
        try:
            exitos = 0
            for sku in skus_to_link:
                if self.gestor_mat.vincular_material_a_sku(sku, mat_ref, float(cant)):
                    exitos += 1
            
            self.on_receta_selected(None) # Refrescar vista actual
            messagebox.showinfo("Vínculo en Lote", f"Material {mat_ref} vinculado a {exitos} artículos")
            self.verificar_capacidad()
        except Exception as e:
            messagebox.showerror("Error", str(e))

    def on_tree_click(self, event):
        # Manejar el click en el checkbox column
        region = self.tree_recetas.identify_region(event.x, event.y)
        if region == "cell":
            column = self.tree_recetas.identify_column(event.x)
            if column == "#1":  # La columna Sel es la primera
                item = self.tree_recetas.identify_row(event.y)
                if item:
                    vals = list(self.tree_recetas.item(item)['values'])
                    vals[0] = "[x]" if vals[0] == "[ ]" else "[ ]"
                    self.tree_recetas.item(item, values=vals)
                    return "break" # Evitar que se cambie la selección principal si solo queremos marcar

    def marcar_todos(self, estado):
        txt = "[x]" if estado else "[ ]"
        for item in self.tree_recetas.get_children():
            vals = list(self.tree_recetas.item(item)['values'])
            vals[0] = txt
            self.tree_recetas.item(item, values=vals)

    def editar_cantidad_vinculo(self, sku, mat_ref, cant_actual):
        from tkinter import simpledialog
        nueva = simpledialog.askfloat("Editar Cantidad", f"Cantidad necesaria para {mat_ref}:", initialvalue=cant_actual)
        if nueva is not None:
            if self.gestor_mat.vincular_material_a_sku(sku, mat_ref, nueva):
                self.on_receta_selected(None)
                self.verificar_capacidad()

    def eliminar_vinculo(self, sku, mat_ref):
        if messagebox.askyesno("Confirmar", f"¿Eliminar el material {mat_ref} de esta receta?"):
            if self.gestor_mat.desvincular_material_de_sku(sku, mat_ref):
                self.on_receta_selected(None)
                self.verificar_capacidad()

    def generar_despiece_ia(self):
        sel = self.tree_recetas.selection()
        if not sel: return
        sku = self.tree_recetas.item(sel[0])['values'][0]
        prod = next((p for p in self.gestor.productos if p['SKU_REF'] == sku), None)
        if not prod: return
        
        img_path = prod.get('FOTO_PORTADA')
        if not img_path or not os.path.exists(img_path):
            messagebox.showwarning("IA", "No hay imagen para analizar")
            return
            
        messagebox.showinfo("IA", "Analizando imagen... espera un momento (pueden aplicarse delays de ráfaga)")
        from modulo1_nucleo import analizar_imagen_ia
        res = analizar_imagen_ia(img_path, prompt_type="despiece")
        
        if res and isinstance(res, list):
            self.txt_despiece.delete(1.0, tk.END)
            self.txt_despiece.insert(tk.END, "📢 SUGERENCIA IA:\n")
            for item in res:
                self.txt_despiece.insert(tk.END, f"- {item.get('material')}: {item.get('cantidad')} {item.get('unidad')}\n")
            messagebox.showinfo("IA", "Análisis completado. Puedes vincular los materiales detectados manualmente.")
        else:
            messagebox.showerror("IA", "No se pudo obtener el despiece. Revisa tus llaves API.")

    def verificar_capacidad(self):
        sel = self.tree_recetas.selection()
        if not sel: return
        sku = self.tree_recetas.item(sel[0])['values'][1]
        
        # Calcular capacidad (SQL)
        desp = self.gestor_mat.obtener_despiece(sku)
        mats_inventario = self.gestor_mat.obtener_materiales()
        
        if not desp:
            self.lbl_status_stock.config(text="⚠️ Sin materiales vinculados", fg="orange")
            return

        limitantes = []
        for d in desp:
            mat = next((m for m in mats_inventario if m['REF_MAT'] == d['REF_MAT']), None)
            if not mat:
                limitantes.append(0)
                continue
            
            posibles = mat['STOCK_ACTUAL'] // d['CANTIDAD'] if d['CANTIDAD'] > 0 else 9999
            limitantes.append(posibles)
        
        cant = int(min(limitantes)) if limitantes else 0
        
        # Obtener valores del producto seleccionado con manejo de None
        item_vals = self.tree_recetas.item(sel[0])['values'] if sel else []
        
        def safe_int(val):
            try:
                if val is None or str(val).strip().lower() in ['none', '', 'nan', 'null']:
                    return 0
                return int(float(str(val).replace(',', '.')))
            except (ValueError, TypeError):
                return 0

        stock_fisico = safe_int(item_vals[4]) if len(item_vals) > 4 else 0
        realizadas = safe_int(item_vals[3]) if len(item_vals) > 3 else 0
        
        # Actualizar dashboard
        self.lbl_fab.config(text=str(cant))
        self.lbl_fisico.config(text=str(stock_fisico))
        self.lbl_realizadas_dash.config(text=str(realizadas))
        
        if cant > 0:
            self.lbl_status_stock.config(text=f"✅ VIABLE: Puedes fabricar {cant} unidades", fg="#059669")
        else:
            self.lbl_status_stock.config(text="❌ INSUFICIENTE: Revisa el material necesario", fg="#dc2626")


    def mostrar_imagen(self, path):
        try:
            img = Image.open(path)
            img.thumbnail((400, 400), Image.Resampling.LANCZOS)
            photo = ImageTk.PhotoImage(img)
            self.lbl_img_receta.config(image=photo, text="")
            self.lbl_img_receta.image = photo 
        except:
            self.lbl_img_receta.config(image='', text="Error imagen")

    def editar_stock_fisico(self):
        """Permite actualizar cuantos articulos estan ya TERMINADOS y listos para enviar."""
        sel = self.tree_recetas.selection()
        if not sel: return
        sku = self.tree_recetas.item(sel[0])['values'][1]
        actual = self.tree_recetas.item(sel[0])['values'][4] if len(self.tree_recetas.item(sel[0])['values']) > 4 else 0
        
        from tkinter import simpledialog
        nuevo = simpledialog.askinteger(
            "📦 Stock Terminado (listos para enviar)",
            f"SKU: {sku}\n"
            f"Terminados actuales: {actual}\n\n"
            "Introduce el TOTAL de unidades terminadas y listas para enviar:",
            initialvalue=int(actual) if str(actual).isdigit() else 0,
            minvalue=0
        )
        
        if nuevo is not None:
            if self.gestor.actualizar_producto(sku, {'STOCK_FISICO': nuevo}):
                messagebox.showinfo("✅ Actualizado", f"{sku}: {nuevo} unidades terminadas registradas")
                self.cargar_datos_recetas()
                self.on_receta_selected(None)
    
    def guardar_medidas_lote(self):
        """Guarda peso y dimensiones para todos los SKUs marcados con [x]"""
        try:
            peso = float(self.ent_peso_ship.get() or 0.5)
            largo = float(self.ent_largo_ship.get() or 20)
            ancho = float(self.ent_ancho_ship.get() or 15)
            alto = float(self.ent_alto_ship.get() or 10)
        except ValueError:
            messagebox.showerror("Error", "Peso y dimensiones deben ser números válidos")
            return

        # Recoger SKUs marcados
        skus_marcados = []
        for item in self.tree_recetas.get_children():
            vals = self.tree_recetas.item(item)['values']
            if vals[0] == "[x]":
                skus_marcados.append(vals[1])
        
        if not skus_marcados:
            # Si no hay marcados, intentar con el seleccionado
            sel = self.tree_recetas.selection()
            if sel:
                skus_marcados.append(self.tree_recetas.item(sel[0])['values'][1])
            else:
                messagebox.showwarning("Atención", "No hay artículos marcados con [x] ni seleccionados")
                return

        if not messagebox.askyesno("Confirmar", f"¿Aplicar medidas a {len(skus_marcados)} artículos?"):
            return

        exitos = 0
        datos_update = {
            'peso_envio': peso,
            'largo_envio': largo,
            'ancho_envio': ancho,
            'alto_envio': alto
        }
        
        for sku in skus_marcados:
            if self.gestor.actualizar_producto(sku, datos_update):
                exitos += 1
        
        messagebox.showinfo("Éxito", f"Se han actualizado {exitos} artículos con éxito.")
        self.cargar_datos_recetas()
        self.on_receta_selected(None)

    def sumar_realizadas(self):

        sel = self.tree_recetas.selection()
        if not sel: return
        sku = self.tree_recetas.item(sel[0])['values'][1]
        
        from tkinter import simpledialog
        actual = self.tree_recetas.item(sel[0])['values'][3]
        nueva_cantidad = simpledialog.askstring("Unidades Realizadas", f"Unidades actuales: {actual}\nIngrese el TOTAL de unidades realizadas:", initialvalue=str(actual))
        
        if nueva_cantidad is not None:
            if self.gestor.actualizar_producto(sku, {'UNIDADES_REALIZADAS': nueva_cantidad}):
                messagebox.showinfo("Éxito", f"Unidades realizadas actualizadas para {sku}")
                self.cargar_datos_recetas()
                self.on_receta_selected(None)

# ========================================
# UI: BUSCADOR VISUAL DE MATERIALES (5.2)
# ========================================

class TabBuscadorMateriales(tk.Frame):
    def __init__(self, parent, gestor_mat):
        super().__init__(parent, bg="#f3f4f6")
        self.gestor_mat = gestor_mat
        self.setup_ui()

    def setup_ui(self):
        # Top panel con botón de actualizar
        top = tk.Frame(self, bg="#f3f4f6", pady=10)
        top.pack(fill="x")
        
        tk.Button(top, text="🔄 ACTUALIZAR GALERÍA", command=self.refrescar, bg="#3b82f6", fg="white", font=("Segoe UI", 10, "bold"), padx=20).pack(side=tk.LEFT, padx=10)
        tk.Button(top, text="📂 ABRIR CARPETA MATERIALES", command=self.abrir_carpeta, bg="#64748b", fg="white", font=("Segoe UI", 10)).pack(side=tk.LEFT, padx=10)

        # Scrollable Canvas para la rejilla de fotos
        self.canvas = tk.Canvas(self, bg="#f3f4f6", highlightthickness=0)
        self.scroll = ttk.Scrollbar(self, orient="vertical", command=self.canvas.yview)
        self.frame_grid = tk.Frame(self.canvas, bg="#f3f4f6")
        
        self.canvas.configure(yscrollcommand=self.scroll.set)
        self.canvas.pack(side="left", fill="both", expand=True)
        self.scroll.pack(side="right", fill="y")
        
        self.canvas_window = self.canvas.create_window((0, 0), window=self.frame_grid, anchor="nw")
        self.frame_grid.bind("<Configure>", lambda e: self.canvas.configure(scrollregion=self.canvas.bbox("all")))
        self.canvas.bind("<Configure>", self.on_canvas_configure)
        
        self.refrescar()

    def abrir_carpeta(self):
        import os
        from modulo3_gestion import RUTA_MATERIALES
        if not os.path.exists(RUTA_MATERIALES): os.makedirs(RUTA_MATERIALES, exist_ok=True)
        os.startfile(RUTA_MATERIALES)

    def on_canvas_configure(self, event):
        self.canvas.itemconfig(self.canvas_window, width=event.width)

    def refrescar(self):
        for widget in self.frame_grid.winfo_children():
            widget.destroy()
            
        mats = self.gestor_mat.obtener_materiales()
        
        cols = 4  # Ajustable
        for i, m in enumerate(mats):
            f = tk.Frame(self.frame_grid, bg="white", bd=1, relief="ridge", padx=10, pady=10)
            f.grid(row=i//cols, column=i%cols, padx=10, pady=10, sticky="nsew")
            
            # Imagen
            photo = None
            path = m.get('FOTO')
            if path and os.path.exists(path):
                try:
                    img = Image.open(path)
                    img.thumbnail((150, 150))
                    photo = ImageTk.PhotoImage(img)
                except: pass
                
            lbl_img = tk.Label(f, bg="white")
            if photo:
                lbl_img.config(image=photo)
                lbl_img.image = photo
            else:
                lbl_img.config(text="🖼️ Sin Foto", fg="#94a3b8")
            lbl_img.pack(pady=5)
            
            tk.Label(f, text=m['NOMBRE'], font=("Segoe UI", 10, "bold"), bg="white").pack()
            tk.Label(f, text=f"REF: {m['REF_MAT']}", font=("Segoe UI", 8), bg="white", fg="#64748b").pack()
            tk.Label(f, text=f"Stock: {m['STOCK_ACTUAL']} {m['UNIDAD']}", font=("Segoe UI", 9), bg="white", fg="#4f46e5").pack()

