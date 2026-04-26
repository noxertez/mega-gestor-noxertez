import tkinter as tk
from tkinter import ttk, messagebox
import os
from datetime import datetime

# Importar constantes y utilidades de otros módulos
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

class GestorClientes:
    def __init__(self, db_path=None):
        pass

    def obtener_clientes(self, filtro=""):
        conn = get_db_connection()
        if not conn: return []
        try:
            cursor = conn.cursor(dictionary=True)
            if filtro:
                cursor.execute("SELECT * FROM clientes WHERE nombre LIKE %s OR telefono LIKE %s OR email LIKE %s OR instagram LIKE %s", 
                             (f'%{filtro}%', f'%{filtro}%', f'%{filtro}%', f'%{filtro}%'))
            else:
                cursor.execute("SELECT * FROM clientes")
            return cursor.fetchall()
        finally:
            if conn: conn.close()

    def guardar_cliente(self, datos):
        conn = get_db_connection()
        if not conn: return False, "No se pudo conectar a la base de datos"
        try:
            cursor = conn.cursor()
            if 'id' in datos and datos['id']:
                # Update
                id_cliente = datos.pop('id')
                set_clause = ", ".join([f"{k} = %s" for k in datos.keys()])
                params = list(datos.values()) + [id_cliente]
                cursor.execute(f"UPDATE clientes SET {set_clause} WHERE id = %s", params)
            else:
                # Insert
                keys = list(datos.keys())
                placeholders = ", ".join(["%s"] * len(keys))
                cursor.execute(f"INSERT INTO clientes ({', '.join(keys)}) VALUES ({placeholders})", list(datos.values()))
            conn.commit()
            return True, "Cliente guardado correctamente"
        except Exception as e:
            return False, str(e)
        finally:
            if conn: conn.close()

    def eliminar_cliente(self, id_cliente):
        conn = get_db_connection()
        if not conn: return False, "No se pudo conectar a la base de datos"
        try:
            cursor = conn.cursor()
            cursor.execute("DELETE FROM clientes WHERE id = %s", (id_cliente,))
            conn.commit()
            return True, "Cliente eliminado"
        except Exception as e:
            return False, str(e)
        finally:
            if conn: conn.close()

    def obtener_articulos_asociados(self, id_cliente):
        conn = get_db_connection()
        if not conn: return []
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT p.SKU_REF, p.NOMBRE, p.PRECIO 
                FROM productos p
                JOIN cliente_articulos ca ON p.SKU_REF = ca.sku_articulo
                WHERE ca.id_cliente = %s
            """, (id_cliente,))
            return cursor.fetchall()
        finally:
            if conn: conn.close()

    def asignar_articulo(self, id_cliente, sku):
        conn = get_db_connection()
        if not conn: return False, "No se pudo conectar a la base de datos"
        try:
            cursor = conn.cursor()
            cursor.execute("INSERT IGNORE INTO cliente_articulos (id_cliente, sku_articulo) VALUES (%s, %s)", (id_cliente, sku))
            conn.commit()
            return True, "Artículo asignado"
        except Exception as e:
            return False, str(e)
        finally:
            if conn: conn.close()

    def desvincular_articulo(self, id_cliente, sku):
        conn = get_db_connection()
        if not conn: return False, "No se pudo conectar a la base de datos"
        try:
            cursor = conn.cursor()
            cursor.execute("DELETE FROM cliente_articulos WHERE id_cliente = %s AND sku_articulo = %s", (id_cliente, sku))
            conn.commit()
            return True, "Vínculo eliminado"
        except Exception as e:
            return False, str(e)
        finally:
            if conn: conn.close()

    def crear_pedido_manual(self, id_cliente, sku, tipo_trabajo):
        conn = get_db_connection()
        if not conn: return False, "No se pudo conectar a la base de datos"
        try:
            cursor = conn.cursor()
            fecha = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            estado = "Por empezar"
            
            # Si es Stock, restamos de la tabla productos
            if tipo_trabajo == "Stock (Listo)":
                cursor.execute("UPDATE productos SET STOCK_FISICO = GREATEST(0, COALESCE(STOCK_FISICO, 0) - 1) WHERE SKU_REF = %s", (sku,))
                estado = "Listo para entrega"
            
            notas = f"Trabajo: {tipo_trabajo}"
            
            cursor.execute("""
                INSERT INTO pedidos (id_cliente, fecha_pedido, fecha_inicio, estado, sku_articulo, notas)
                VALUES (%s, %s, %s, %s, %s, %s)
            """, (id_cliente, fecha, fecha, estado, sku, notas))
            
            conn.commit()
            return True, f"Pedido creado como: {tipo_trabajo}"
        except Exception as e:
            return False, str(e)
        finally:
            if conn: conn.close()

class TabClientes(tk.Frame):
    def __init__(self, parent, app_main=None):
        super().__init__(parent, bg=COLOR_FONDO)
        self.app_main = app_main
        self.gestor = GestorClientes()
        self.cliente_actual_id = None
        self._crear_interfaz()
        self.cargar_datos()

    def _crear_interfaz(self):
        # Toolbar superior
        toolbar = tk.Frame(self, bg=COLOR_FONDO, pady=10, padx=20)
        toolbar.pack(fill=tk.X)
        
        tk.Label(toolbar, text="👥 GESTIÓN DE CLIENTES", font=("Segoe UI", 16, "bold"), bg=COLOR_FONDO).pack(side=tk.LEFT)
        BotonConFeedback(toolbar, "Nuevo Cliente", self.nuevo_cliente, COLOR_MORADO, "➕").pack(side=tk.RIGHT, padx=5)
        BotonConFeedback(toolbar, "Actualizar", self.cargar_datos, COLOR_VERDE, "🔄").pack(side=tk.RIGHT, padx=5)

        # Contenedor principal (Splitter izquierdo: lista, derecho: detalle)
        main_container = tk.Frame(self, bg=COLOR_FONDO)
        main_container.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)

        # PARTE IZQUIERDA: BUSCADOR Y LISTA
        izq = tk.Frame(main_container, bg="white", width=400, highlightthickness=1, highlightbackground="#ddd")
        izq.pack(side=tk.LEFT, fill=tk.BOTH, padx=(0, 10))
        izq.pack_propagate(False)

        search_frame = tk.Frame(izq, bg="white", pady=10, padx=10)
        search_frame.pack(fill=tk.X)
        self.ent_busqueda = ttk.Entry(search_frame, font=("Segoe UI", 11))
        self.ent_busqueda.pack(side=tk.LEFT, fill=tk.X, expand=True)
        self.ent_busqueda.bind("<KeyRelease>", lambda e: self.cargar_datos())
        tk.Label(search_frame, text="🔍", bg="white", font=("Segoe UI", 12)).pack(side=tk.RIGHT, padx=5)

        # Tabla de clientes
        self.tree = ttk.Treeview(izq, columns=("Nombre", "Teléfono"), show="headings")
        self.tree.heading("Nombre", text="Nombre")
        self.tree.heading("Teléfono", text="Teléfono")
        self.tree.column("Nombre", width=250)
        self.tree.column("Teléfono", width=120)
        self.tree.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        self.tree.bind("<<TreeviewSelect>>", self.on_cliente_select)

        # PARTE DERECHA: NOTEBOOK DE DETALLE
        self.right_nb = ttk.Notebook(main_container)
        self.right_nb.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

        # TAB 1: DATOS PERSONALES
        self.tab_datos = tk.Frame(self.right_nb, bg=COLOR_FONDO)
        self.right_nb.add(self.tab_datos, text=" 👤 Datos ")
        self._crear_tab_datos()

        # TAB 2: NUEVA ASIGNACIÓN (PEDIDOS)
        self.tab_asignar = tk.Frame(self.right_nb, bg=COLOR_FONDO)
        self.right_nb.add(self.tab_asignar, text=" 📋 Nueva Asignación ")
        self._crear_tab_asignacion()

        # TAB 3: HISTORIAL Y ESTADOS
        self.tab_historial = tk.Frame(self.right_nb, bg=COLOR_FONDO)
        self.right_nb.add(self.tab_historial, text=" 📜 Historial / Estado ")
        self._crear_tab_historial()

    def _crear_tab_datos(self):
        der = self.tab_datos
        self.form_frame = tk.Frame(der, bg=COLOR_FONDO, pady=20, padx=20)
        self.form_frame.pack(fill=tk.BOTH, expand=True)

        # Campos del formulario
        self.vars = {}
        campos = [
            ("nombre", "Nombre Completo:", "👤"),
            ("telefono", "Teléfono:", "📞"),
            ("email", "Email:", "📧"),
            ("instagram", "Instagram:", "📸"),
            ("direccion", "Dirección:", "🏠"),
            ("ciudad", "Ciudad:", "🌆"),
            ("codigo_postal", "C.P:", "📮"),
            ("pais", "País:", "🌍")
        ]

        for i, (key, label, icono) in enumerate(campos):
            row = i // 2
            col = (i % 2) * 2
            tk.Label(self.form_frame, text=f"{icono} {label}", bg=COLOR_FONDO, font=("Segoe UI", 10, "bold")).grid(row=row*2, column=col, sticky="w", pady=(10, 0), padx=5)
            self.vars[key] = tk.StringVar()
            entry = ttk.Entry(self.form_frame, textvariable=self.vars[key], font=("Segoe UI", 11), width=35)
            entry.grid(row=row*2+1, column=col, sticky="we", padx=5, pady=(0, 5))

        # Notas (Text widget)
        tk.Label(self.form_frame, text="📝 Notas:", bg=COLOR_FONDO, font=("Segoe UI", 10, "bold")).grid(row=8, column=0, sticky="w", pady=(10, 0), padx=5)
        self.txt_notas = tk.Text(self.form_frame, font=("Segoe UI", 11), height=5, width=70)
        self.txt_notas.grid(row=9, column=0, columnspan=3, sticky="we", padx=5, pady=5)

        # Botones de acción
        btn_frame = tk.Frame(der, bg=COLOR_FONDO, pady=10)
        btn_frame.pack(fill=tk.X)
        
        self.btn_guardar = BotonConFeedback(btn_frame, "Guardar Cliente", self.guardar_cliente, COLOR_VERDE, "💾")
        self.btn_guardar.pack(side=tk.LEFT, padx=20)
        
        self.btn_eliminar = BotonConFeedback(btn_frame, "Eliminar", self.eliminar_cliente, "#ef4444", "🗑️")
        self.btn_eliminar.pack(side=tk.RIGHT, padx=20)

    def _crear_tab_asignacion(self):
        der = self.tab_asignar
        
        # --- BUSCADOR AVANZADO ---
        filter_frame = ttk.LabelFrame(der, text=" 🔍 Buscador de Artículos ")
        filter_frame.pack(fill=tk.X, padx=10, pady=10)
        
        # Grid para filtros
        f_grid = tk.Frame(filter_frame, bg=COLOR_FONDO)
        f_grid.pack(fill=tk.X, padx=10, pady=5)
        
        tk.Label(f_grid, text="SKU:", bg=COLOR_FONDO).grid(row=0, column=0, sticky="w")
        self.ent_sku_f = ttk.Entry(f_grid, width=15)
        self.ent_sku_f.grid(row=0, column=1, padx=5, pady=2)
        
        tk.Label(f_grid, text="Nombre:", bg=COLOR_FONDO).grid(row=0, column=2, sticky="w")
        self.ent_nom_f = ttk.Entry(f_grid, width=20)
        self.ent_nom_f.grid(row=0, column=3, padx=5)
        
        tk.Label(f_grid, text="Categoría:", bg=COLOR_FONDO).grid(row=1, column=0, sticky="w")
        self.ent_cat_f = ttk.Entry(f_grid, width=15)
        self.ent_cat_f.grid(row=1, column=1, padx=5, pady=2)
        
        tk.Label(f_grid, text="Subcat:", bg=COLOR_FONDO).grid(row=1, column=2, sticky="w")
        self.ent_sub_f = ttk.Entry(f_grid, width=20)
        self.ent_sub_f.grid(row=1, column=3, padx=5)
        
        tk.Button(f_grid, text="🔍 BUSCAR", command=self.buscar_articulos_avanzado, bg="#3b82f6", fg="white").grid(row=0, column=4, rowspan=2, padx=10, sticky="ns")
        
        # Tabla de resultados
        self.tree_res = ttk.Treeview(der, columns=("SKU", "Nombre", "Precio"), show="headings", height=10)
        self.tree_res.heading("SKU", text="SKU")
        self.tree_res.heading("Nombre", text="Nombre")
        self.tree_res.heading("Precio", text="Precio")
        self.tree_res.column("SKU", width=120)
        self.tree_res.column("Nombre", width=300)
        self.tree_res.column("Precio", width=80)
        self.tree_res.pack(fill=tk.BOTH, expand=True, padx=10)
        
        # --- PANEL DE ACCIÓN ---
        action_frame = ttk.LabelFrame(der, text=" 🔨 Crear Nuevo Pedido para el Cliente SELECCIONADO ")
        action_frame.pack(fill=tk.X, padx=10, pady=10)
        
        tk.Label(action_frame, text="Tipo de Trabajo:", font=("Segoe UI", 10, "bold")).pack(side=tk.LEFT, padx=10, pady=10)
        self.cb_tipo_trabajo = ttk.Combobox(action_frame, values=["Stock (Listo)", "Solo Barnizar", "Para Montaje", "Fabricar Total"], width=20, state="readonly")
        self.cb_tipo_trabajo.set("Fabricar Total")
        self.cb_tipo_trabajo.pack(side=tk.LEFT, padx=5)
        
        BotonConFeedback(action_frame, "EMPEZAR PEDIDO", self.empezar_pedido_manual, COLOR_VERDE, "🚀").pack(side=tk.RIGHT, padx=20)

    def _crear_tab_historial(self):
        der = self.tab_historial
        
        # Historial de Pedidos (Kanban de este cliente)
        hist_frame = tk.Frame(der, bg=COLOR_FONDO)
        hist_frame.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)

        self.hist_nb = ttk.Notebook(hist_frame)
        self.hist_nb.pack(fill=tk.BOTH, expand=True)

        # Tab Encargados
        self.tab_encargados = tk.Frame(self.hist_nb, bg="white")
        self.hist_nb.add(self.tab_encargados, text=" ⏳ En Proceso / Pendientes ")
        self.tree_enc = ttk.Treeview(self.tab_encargados, columns=("ID", "Fecha", "Estado", "Articulo", "Precio"), show="headings")
        self.tree_enc.heading("ID", text="ID"); self.tree_enc.column("ID", width=50)
        self.tree_enc.heading("Fecha", text="Fecha"); self.tree_enc.column("Fecha", width=110)
        self.tree_enc.heading("Estado", text="Estado"); self.tree_enc.column("Estado", width=100)
        self.tree_enc.heading("Articulo", text="Artículo"); self.tree_enc.column("Articulo", width=250)
        self.tree_enc.heading("Precio", text="Precio"); self.tree_enc.column("Precio", width=80)
        self.tree_enc.pack(fill=tk.BOTH, expand=True)
        self.tree_enc.bind("<Button-3>", lambda e: self.mostrar_menu_pedido(e, self.tree_enc))

        # Tab Comprados
        self.tab_comprados = tk.Frame(self.hist_nb, bg="white")
        self.hist_nb.add(self.tab_comprados, text=" ✅ Histórico Realizados ")
        self.tree_com = ttk.Treeview(self.tab_comprados, columns=("ID", "Fecha", "Articulo", "Envio", "Tracking"), show="headings")
        self.tree_com.heading("ID", text="ID"); self.tree_com.column("ID", width=50)
        self.tree_com.heading("Fecha", text="Fecha"); self.tree_com.column("Fecha", width=110)
        self.tree_com.heading("Articulo", text="Artículo"); self.tree_com.column("Articulo", width=250)
        self.tree_com.heading("Envio", text="Coste Envío"); self.tree_com.column("Envio", width=90)
        self.tree_com.heading("Tracking", text="Tracking / Envío"); self.tree_com.column("Tracking", width=200)
        self.tree_com.pack(fill=tk.BOTH, expand=True)
        self.tree_com.bind("<Button-3>", lambda e: self.mostrar_menu_pedido(e, self.tree_com))

    def mostrar_menu_pedido(self, event, tree):
        item = tree.identify_row(event.y)
        if not item: return
        tree.selection_set(item)
        id_pedido = tree.item(item)['values'][0]
        
        menu = tk.Menu(self, tearoff=0)
        menu.add_command(label="✏️ Editar / Modificar Pedido", command=lambda: self.abrir_editor_pedido(id_pedido))
        menu.add_separator()
        menu.add_command(label="🗑️ Eliminar Pedido", command=lambda: self.eliminar_pedido(id_pedido), foreground="red")
        menu.post(event.x_root, event.y_root)

    def abrir_editor_pedido(self, id_pedido):
        win = tk.Toplevel(self)
        win.title(f"✏️ Editar Pedido #{id_pedido}")
        win.geometry("500x550")
        win.configure(bg="white")
        
        # Cargar datos actuales
        conn = get_db_connection()
        if not conn: return
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM pedidos WHERE id = %s", (id_pedido,))
            p = cursor.fetchone()
        finally:
            if conn: conn.close()
        
        if not p: return

        # Formulario
        f = tk.Frame(win, bg="white", padx=20, pady=20)
        f.pack(fill=tk.BOTH, expand=True)

        tk.Label(f, text=f"DETALLES DEL PEDIDO #{id_pedido}", font=("Segoe UI", 12, "bold"), bg="white").pack(pady=(0, 20))

        tk.Label(f, text="SKU Artículo:", bg="white").pack(anchor="w")
        ent_sku = ttk.Entry(f, width=40)
        ent_sku.insert(0, p['sku_articulo'] or "")
        ent_sku.pack(pady=(0, 10))

        tk.Label(f, text="Precio Total (€):", bg="white").pack(anchor="w")
        ent_total = ttk.Entry(f, width=20)
        ent_total.insert(0, str(p['total'] or "0.0"))
        ent_total.pack(pady=(0, 10))

        tk.Label(f, text="Estado:", bg="white").pack(anchor="w")
        cb_estado = ttk.Combobox(f, values=["Por empezar", "En proceso", "Secado/Horno", "Acabado/Barniz", "Listo para entrega", "Entregado"], width=35)
        cb_estado.set(p['estado'])
        cb_estado.pack(pady=(0, 10))

        tk.Label(f, text="Notas / Detalles:", bg="white").pack(anchor="w")
        txt_notas = tk.Text(f, height=5, width=40, font=("Segoe UI", 10))
        txt_notas.insert("1.0", p['notas'] or "")
        txt_notas.pack(pady=(0, 10))

        def save():
            try:
                conn = get_db_connection()
                if not conn: return
                cursor = conn.cursor()
                cursor.execute("""
                    UPDATE pedidos 
                    SET sku_articulo = %s, total = %s, estado = %s, notas = %s
                    WHERE id = %s
                """, (ent_sku.get(), float(ent_total.get() or 0), cb_estado.get(), txt_notas.get("1.0", tk.END).strip(), id_pedido))
                conn.commit()
                conn.close()
                Notificacion.mostrar(self, "✅ Pedido actualizado", "Éxito")
                win.destroy()
                self.cargar_historial_pedidos(self.cliente_actual_id)
            except Exception as ex:
                messagebox.showerror("Error", f"No se pudo guardar: {ex}")

        BotonConFeedback(win, "Guardar Cambios", save, COLOR_VERDE, "💾").pack(pady=20)

    def eliminar_pedido(self, id_pedido):
        if messagebox.askyesno("Confirmar", f"¿Estás seguro de eliminar el pedido #{id_pedido}?\nEsta acción es irreversible."):
            try:
                conn = get_db_connection()
                if not conn: return
                cursor = conn.cursor()
                cursor.execute("DELETE FROM pedidos WHERE id = %s", (id_pedido,))
                conn.commit()
                conn.close()
                Notificacion.mostrar(self, "🗑️ Pedido eliminado", "Éxito")
                self.cargar_historial_pedidos(self.cliente_actual_id)
            except Exception as e:
                messagebox.showerror("Error", f"No se pudo eliminar: {e}")

    def cargar_datos(self):
        filtro = self.ent_busqueda.get()
        clientes = self.gestor.obtener_clientes(filtro)
        for c in self.tree.get_children(): self.tree.delete(c)
        for c in clientes:
            self.tree.insert("", tk.END, iid=c['id'], values=(c['nombre'], c['telefono']))

    def on_cliente_select(self, event):
        sel = self.tree.selection()
        if not sel: return
        id_c = sel[0]
        self.cliente_actual_id = id_c
        
        conn = get_db_connection()
        if not conn: return
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM clientes WHERE id = %s", (id_c,))
            c = cursor.fetchone()
        finally:
            if conn: conn.close()
        
        if c:
            for key in self.vars:
                self.vars[key].set(c[key] if c[key] else "")
            self.txt_notas.delete("1.0", tk.END)
            self.txt_notas.insert(tk.END, c['notas'] if c['notas'] else "")

            # Cargar Historial y Artículos Asociados
            self.cargar_historial_pedidos(id_c)
            self.cargar_articulos_asociados(id_c)

    def buscar_articulos_avanzado(self):
        sku = self.ent_sku_f.get().strip()
        nom = self.ent_nom_f.get().strip()
        cat = self.ent_cat_f.get().strip()
        sub = self.ent_sub_f.get().strip()
        
        for i in self.tree_res.get_children(): self.tree_res.delete(i)
        
        try:
            conn = get_db_connection()
            if not conn: return
            cursor = conn.cursor(dictionary=True)
            query = "SELECT SKU_REF, NOMBRE, PRECIO FROM productos WHERE 1=1"
            params = []
            if sku: query += " AND SKU_REF LIKE %s"; params.append(f'%{sku}%')
            if nom: query += " AND NOMBRE LIKE %s"; params.append(f'%{nom}%')
            if cat: query += " AND CATEGORIA LIKE %s"; params.append(f'%{cat}%')
            if sub: query += " AND SUBCATEGORIA LIKE %s"; params.append(f'%{sub}%')
            
            cursor.execute(query + " LIMIT 50", params)
            res = cursor.fetchall()
            conn.close()
            for r in res:
                self.tree_res.insert("", tk.END, values=(r['SKU_REF'], r['NOMBRE'], f"{r['PRECIO']}€"))
        except Exception as e:
            print(f"Error búsqueda avanzada: {e}")

    def empezar_pedido_manual(self):
        if not self.cliente_actual_id:
            messagebox.showwarning("Aviso", "Selecciona primero un cliente de la lista izquierda")
            return
        
        sel = self.tree_res.selection()
        if not sel:
            messagebox.showwarning("Aviso", "Selecciona un artículo de los resultados de búsqueda")
            return
        
        sku = self.tree_res.item(sel[0])['values'][0]
        tipo = self.cb_tipo_trabajo.get()
        
        exito, msg = self.gestor.crear_pedido_manual(self.cliente_actual_id, sku, tipo)
        if exito:
            Notificacion.mostrar(self, f"✅ Pedido enviado a producción: {sku}", "Éxito")
            self.cargar_historial_pedidos(self.cliente_actual_id)
            # Cambiar automáticamente a la pestaña de historial
            self.right_nb.select(self.tab_historial)
        else:
            messagebox.showerror("Error", msg)

    def cargar_articulos_asociados(self, id_cliente):
        # Mantenemos este método por si acaso, aunque ahora usamos el buscador directo
        pass

    def cargar_historial_pedidos(self, id_cliente):
        for i in self.tree_enc.get_children(): self.tree_enc.delete(i)
        for i in self.tree_com.get_children(): self.tree_com.delete(i)
        
        try:
            conn = get_db_connection()
            if not conn: return
            cursor = conn.cursor(dictionary=True)
            
            # Pedidos Activos (Encargados)
            cursor.execute("""
                SELECT id, fecha_inicio, estado, sku_articulo, total 
                FROM pedidos 
                WHERE id_cliente = %s AND estado != 'Entregado'
                ORDER BY id DESC
            """, (id_cliente,))
            pedidos_activos = cursor.fetchall()
            
            for p in pedidos_activos:
                self.tree_enc.insert("", tk.END, iid=f"enc_{p['id']}", values=(p['id'], p['fecha_inicio'], p['estado'], p['sku_articulo'] or '--', f"{p['total'] or 0}€"))

            # Pedidos Finalizados (Comprados)
            cursor.execute("""
                SELECT id, fecha_inicio, sku_articulo, costo_envio, metodo_envio, tracking_id 
                FROM pedidos 
                WHERE id_cliente = %s AND estado = 'Entregado'
                ORDER BY id DESC
            """, (id_cliente,))
            pedidos_fin = cursor.fetchall()
            
            for p in pedidos_fin:
                detalles_envio = f"{p['metodo_envio'] or '--'} | {p['tracking_id'] or '--'}"
                self.tree_com.insert("", tk.END, iid=f"com_{p['id']}", values=(p['id'], p['fecha_inicio'], p['sku_articulo'] or '--', f"{p['costo_envio'] or 0}€", detalles_envio))
            
            conn.close()
        except Exception as e:
            print(f"Error cargando historial: {e}")

    def nuevo_cliente(self):
        self.cliente_actual_id = None
        for v in self.vars.values(): v.set("")
        self.txt_notas.delete("1.0", tk.END)
        self.tree.selection_remove(self.tree.selection())

    def guardar_cliente(self):
        datos = {k: v.get() for k, v in self.vars.items()}
        datos['notas'] = self.txt_notas.get("1.0", tk.END).strip()
        if self.cliente_actual_id:
            datos['id'] = self.cliente_actual_id
        
        if not datos['nombre']:
            messagebox.showwarning("Atención", "El nombre es obligatorio")
            return

        exito, msg = self.gestor.guardar_cliente(datos)
        if exito:
            Notificacion.mostrar(self, msg, "Éxito")
            self.cargar_datos()
        else:
            messagebox.showerror("Error", msg)

    def eliminar_cliente(self):
        if not self.cliente_actual_id: return
        if messagebox.askyesno("Confirmar", "¿Eliminar este cliente?"):
            exito, msg = self.gestor.eliminar_cliente(self.cliente_actual_id)
            if exito:
                self.nuevo_cliente()
                self.cargar_datos()
