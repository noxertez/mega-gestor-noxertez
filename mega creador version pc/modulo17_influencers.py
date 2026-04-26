import tkinter as tk
from tkinter import ttk, messagebox
import sqlite3
import os
from datetime import datetime
from modulo3_gestion import DB_PATH, get_db_connection
from modulo2_interfaz import COLOR_FONDO, COLOR_MORADO, COLOR_VERDE
from modulo5_mejoras_visuales import BotonConFeedback, Notificacion

class InfluencerManager:
    def __init__(self):
        self.init_db()

    def init_db(self):
        conn = get_db_connection()
        if not conn: return
        cursor = conn.cursor()
        
        # Tabla de Influencers
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS influencers (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                nombre TEXT NOT NULL,
                usuario_ig VARCHAR(100) UNIQUE,
                email TEXT,
                telefono TEXT,
                vibe_estilo TEXT,
                nicho TEXT,
                seguidores INTEGER,
                likes_promedio INTEGER,
                fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ''')
        
        # Tabla de Colaboraciones
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS colaboraciones_influencers (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                influencer_id INTEGER,
                sku_articulo TEXT,
                costo_produccion DOUBLE,
                precio_venta DOUBLE,
                porcentaje_comision DOUBLE,
                estado_colab TEXT,
                fecha_envio DATETIME,
                notas TEXT,
                FOREIGN KEY (influencer_id) REFERENCES influencers(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ''')
        conn.commit()
        conn.close()

    def obtener_influencers(self):
        conn = get_db_connection()
        if not conn: return []
        cursor = conn.cursor(dictionary=True)
        cursor.execute('SELECT * FROM influencers ORDER BY nombre ASC')
        rows = cursor.fetchall()
        conn.close()
        return rows

    def guardar_influencer(self, data):
        conn = get_db_connection()
        if not conn: return False, "Error de conexión"
        cursor = conn.cursor()
        try:
            if data.get('id'):
                cursor.execute('''
                    UPDATE influencers SET 
                    nombre=%s, usuario_ig=%s, email=%s, telefono=%s, vibe_estilo=%s, nicho=%s, seguidores=%s, likes_promedio=%s
                    WHERE id=%s
                ''', (data['nombre'], data['usuario_ig'], data['email'], data['telefono'], 
                      data['vibe_estilo'], data['nicho'], data['seguidores'], data['likes_promedio'], data['id']))
            else:
                cursor.execute('''
                    INSERT INTO influencers (nombre, usuario_ig, email, telefono, vibe_estilo, nicho, seguidores, likes_promedio)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                ''', (data['nombre'], data['usuario_ig'], data['email'], data['telefono'], 
                      data['vibe_estilo'], data['nicho'], data['seguidores'], data['likes_promedio']))
            conn.commit()
            return True, "Influencer guardado"
        except Exception as e:
            return False, f"Error al guardar: {e}"
        finally:
            conn.close()

    def eliminar_influencer(self, id_influencer):
        conn = get_db_connection()
        if not conn: return
        cursor = conn.cursor()
        cursor.execute('DELETE FROM influencers WHERE id=%s', (id_influencer,))
        cursor.execute('DELETE FROM colaboraciones_influencers WHERE influencer_id=%s', (id_influencer,))
        conn.commit()
        conn.close()

    def registrar_colaboracion(self, colab_data):
        conn = get_db_connection()
        if not conn: return
        cursor = conn.cursor()
        cursor.execute('''
            INSERT INTO colaboraciones_influencers 
            (influencer_id, sku_articulo, costo_produccion, precio_venta, porcentaje_comision, estado_colab, fecha_envio, notas)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        ''', (colab_data['influencer_id'], colab_data['sku_articulo'], colab_data['costo_produccion'], 
              colab_data['precio_venta'], colab_data['porcentaje_comision'], colab_data['estado_colab'], 
              colab_data['fecha_envio'], colab_data['notas']))
        conn.commit()
        conn.close()

    def obtener_colaboraciones(self, id_influencer):
        conn = get_db_connection()
        if not conn: return []
        cursor = conn.cursor(dictionary=True)
        cursor.execute('SELECT * FROM colaboraciones_influencers WHERE influencer_id=%s ORDER BY fecha_envio DESC', (id_influencer,))
        rows = cursor.fetchall()
        conn.close()
        return rows

class TabInfluencers(tk.Frame):
    def __init__(self, parent, gestor_principal, app_main):
        super().__init__(parent, bg=COLOR_FONDO)
        self.gestor_principal = gestor_principal
        self.app_main = app_main
        self.manager = InfluencerManager()
        self.influencer_actual = None
        
        self.crear_interfaz()
        self.cargar_lista()

    def crear_interfaz(self):
        # Panel Izquierdo: Lista
        izq = tk.Frame(self, bg=COLOR_FONDO, width=300)
        izq.pack(side=tk.LEFT, fill=tk.Y, padx=10, pady=10)
        izq.pack_propagate(False)
        
        tk.Label(izq, text="👥 DIRECTORIO", font=('Segoe UI', 12, 'bold'), bg=COLOR_FONDO).pack(pady=5)
        
        self.lista_influencers = tk.Listbox(izq, font=('Segoe UI', 10), bg='white')
        self.lista_influencers.pack(fill=tk.BOTH, expand=True)
        self.lista_influencers.bind('<<ListboxSelect>>', self.on_select_influencer)
        
        BotonConFeedback(izq, "Nuevo Influencer", self.nuevo_influencer, COLOR_MORADO, "➕").pack(fill=tk.X, pady=5)
        
        # Panel Central: Detalle
        centro = tk.Frame(self, bg=COLOR_FONDO)
        centro.pack(side=tk.LEFT, fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        # Formulario de Perfil
        perf_frame = ttk.LabelFrame(centro, text="📝 Perfil del Influencer")
        perf_frame.pack(fill=tk.X, padx=5, pady=5)
        
        form = tk.Frame(perf_frame, bg=COLOR_FONDO)
        form.pack(fill=tk.X, padx=10, pady=10)
        
        fields = [
            ("Nombre:", "nombre"), ("Instagram (@):", "usuario_ig"),
            ("Email:", "email"), ("Teléfono:", "telefono"),
            ("Estilo/Vibe:", "vibe"), ("Nicho:", "nicho"),
            ("Seguidores:", "seguidores"), ("Likes Avg:", "likes")
        ]
        
        self.entries = {}
        for i, (label, key) in enumerate(fields):
            row, col = divmod(i, 2)
            tk.Label(form, text=label, bg=COLOR_FONDO, font=('Segoe UI', 9, 'bold')).grid(row=row, column=col*2, sticky=tk.W, padx=5, pady=2)
            ent = ttk.Entry(form, font=('Segoe UI', 10))
            ent.grid(row=row, column=col*2+1, sticky=tk.EW, padx=5, pady=2)
            self.entries[key] = ent
        form.columnconfigure(1, weight=1)
        form.columnconfigure(3, weight=1)
        
        BotonConFeedback(perf_frame, "Guardar Perfil", self.guardar_perfil, COLOR_VERDE, "💾").pack(pady=10)

        # Panel Colaboraciones
        colab_frame = ttk.LabelFrame(centro, text="🎁 Gestión de Colaboraciones / Envíos")
        colab_frame.pack(fill=tk.BOTH, expand=True, padx=5, pady=5)
        
        self.vista_colabs = ttk.Treeview(colab_frame, columns=('SKU', 'Estado', 'Costo', 'Ganancia', 'Fecha'), show='headings')
        for col in self.vista_colabs['columns']:
            self.vista_colabs.heading(col, text=col)
            self.vista_colabs.column(col, width=100)
        self.vista_colabs.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)
        
        btn_area = tk.Frame(colab_frame, bg=COLOR_FONDO)
        btn_area.pack(fill=tk.X, padx=10, pady=5)
        
        BotonConFeedback(btn_area, "Nueva Colaboración", self.nueva_colaboracion, '#4f46e5', "📦").pack(side=tk.LEFT, padx=5)
        BotonConFeedback(btn_area, "Eliminar Influencer", self.eliminar_influencer, '#9f1239', "🗑️").pack(side=tk.RIGHT, padx=5)

    def cargar_lista(self):
        self.lista_influencers.delete(0, tk.END)
        self.influencers = self.manager.obtener_influencers()
        for inf in self.influencers:
            self.lista_influencers.insert(tk.END, f"{inf['nombre']} (@{inf['usuario_ig']})")

    def nuevo_influencer(self):
        self.influencer_actual = None
        for ent in self.entries.values(): ent.delete(0, tk.END)
        self.vista_colabs.delete(*self.vista_colabs.get_children())

    def on_select_influencer(self, event):
        selection = self.lista_influencers.curselection()
        if not selection: return
        
        self.influencer_actual = self.influencers[selection[0]]
        inf = self.influencer_actual
        
        # Cargar formulario
        mapping = {
            "nombre": inf['nombre'], "usuario_ig": inf['usuario_ig'],
            "email": inf['email'], "telefono": inf['telefono'],
            "vibe": inf['vibe_estilo'], "nicho": inf['nicho'],
            "seguidores": inf['seguidores'], "likes": inf['likes_promedio']
        }
        for key, val in mapping.items():
            self.entries[key].delete(0, tk.END)
            self.entries[key].insert(0, str(val or ""))
            
        self.cargar_colaboraciones()

    def guardar_perfil(self):
        data = {
            "id": self.influencer_actual['id'] if self.influencer_actual else None,
            "nombre": self.entries['nombre'].get(),
            "usuario_ig": self.entries['usuario_ig'].get(),
            "email": self.entries['email'].get(),
            "telefono": self.entries['telefono'].get(),
            "vibe_estilo": self.entries['vibe'].get(),
            "nicho": self.entries['nicho'].get(),
            "seguidores": int(self.entries['seguidores'].get() or 0),
            "likes_promedio": int(self.entries['likes'].get() or 0)
        }
        
        if not data['nombre'] or not data['usuario_ig']:
            messagebox.showwarning("Error", "Nombre e Instagram son obligatorios")
            return
            
        success, msg = self.manager.guardar_influencer(data)
        if success:
            Notificacion.mostrar(self, "✅ Perfil guardado", 'exito')
            self.cargar_lista()
        else:
            messagebox.showerror("Error", msg)

    def eliminar_influencer(self):
        if not self.influencer_actual: return
        if messagebox.askyesno("Confirmar", f"¿Eliminar a {self.influencer_actual['nombre']} y todas sus colaboraciones?"):
            self.manager.eliminar_influencer(self.influencer_actual['id'])
            self.nuevo_influencer()
            self.cargar_lista()
            Notificacion.mostrar(self, "🗑️ Influencer eliminado", 'exito')

    def cargar_colaboraciones(self):
        self.vista_colabs.delete(*self.vista_colabs.get_children())
        if not self.influencer_actual: return
        
        colabs = self.manager.obtener_colaboraciones(self.influencer_actual['id'])
        for c in colabs:
            # Calcular beneficio
            comision = (c['precio_venta'] or 0) * ((c['porcentaje_comision'] or 0) / 100)
            beneficio = (c['precio_venta'] or 0) - (c['costo_produccion'] or 0) - comision
            
            costo = c['costo_produccion'] or 0.0
            self.vista_colabs.insert('', tk.END, values=(
                c['sku_articulo'], c['estado_colab'], 
                f"{costo:.2f}€", f"{beneficio:.2f}€",
                c['fecha_envio'][:10] if c['fecha_envio'] else ""
            ))

    def nueva_colaboracion(self):
        if not self.influencer_actual:
            messagebox.showwarning("Aviso", "Selecciona primero un influencer")
            return
            
        # Ventana emergente para registrar colaboración
        win = tk.Toplevel(self)
        win.title("📦 Registrar Nueva Colaboración")
        win.geometry("400x500")
        win.configure(bg=COLOR_FONDO)
        win.grab_set()
        
        tk.Label(win, text=f"Colaboración para: {self.influencer_actual['nombre']}", font=('Segoe UI', 10, 'bold'), bg=COLOR_FONDO).pack(pady=10)
        
        form = tk.Frame(win, bg=COLOR_FONDO)
        form.pack(padx=20, pady=10, fill=tk.BOTH)
        
        tk.Label(form, text="SKU Artículo:", bg=COLOR_FONDO).pack(anchor=tk.W)
        ent_sku = ttk.Entry(form)
        ent_sku.pack(fill=tk.X, pady=5)
        
        # Botón para buscar artículo en el catálogo principal
        def buscar_datos():
            sku = ent_sku.get()
            prod = self.gestor_principal.buscar_producto(sku)
            if prod:
                ent_precio.delete(0, tk.END)
                ent_precio.insert(0, str(prod.get('PRECIO', 0)))
                ent_costo.delete(0, tk.END)
                # Intentar sacar costo si el módulo de stock lo tiene, si no poner 0
                ent_costo.insert(0, "0.0")
            else:
                messagebox.showwarning("No encontrado", "El SKU no existe en el catálogo")
        
        tk.Button(form, text="🔍 Buscar en Catálogo", command=buscar_datos).pack(pady=5)
        
        tk.Label(form, text="Precio Venta (€):", bg=COLOR_FONDO).pack(anchor=tk.W)
        ent_precio = ttk.Entry(form)
        ent_precio.pack(fill=tk.X, pady=5)
        
        tk.Label(form, text="Costo Producción (€):", bg=COLOR_FONDO).pack(anchor=tk.W)
        ent_costo = ttk.Entry(form)
        ent_costo.pack(fill=tk.X, pady=5)
        
        tk.Label(form, text="Comisión Influencer (%):", bg=COLOR_FONDO).pack(anchor=tk.W)
        ent_comision = ttk.Entry(form)
        ent_comision.insert(0, "10")
        ent_comision.pack(fill=tk.X, pady=5)
        
        tk.Label(form, text="Estado:", bg=COLOR_FONDO).pack(anchor=tk.W)
        cb_estado = ttk.Combobox(form, values=["Propuesta enviada", "Aceptado", "Pieza en fabricación", "Recibido", "Publicación pendiente"])
        cb_estado.set("Propuesta enviada")
        cb_estado.pack(fill=tk.X, pady=5)
        
        def save():
            try:
                data = {
                    "influencer_id": self.influencer_actual['id'],
                    "sku_articulo": ent_sku.get(),
                    "costo_produccion": float(ent_costo.get() or 0),
                    "precio_venta": float(ent_precio.get() or 0),
                    "porcentaje_comision": float(ent_comision.get() or 0),
                    "estado_colab": cb_estado.get(),
                    "fecha_envio": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    "notas": ""
                }
                self.manager.registrar_colaboracion(data)
                self.cargar_colaboraciones()
                win.destroy()
                Notificacion.mostrar(self, "📦 Colaboración registrada", 'exito')
            except ValueError:
                messagebox.showerror("Error", "Costo, Precio y Comisión deben ser números")

        BotonConFeedback(win, "Confirmar Colaboración", save, COLOR_VERDE, "✅").pack(pady=20)
