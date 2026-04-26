import tkinter as tk
from tkinter import ttk, messagebox
from datetime import datetime
import mysql.connector

# Importar constantes y utilidades
try:
    from modulo3_gestion import get_db_connection
    from modulo2_interfaz import COLOR_FONDO, COLOR_MORADO, COLOR_VERDE
    from modulo5_mejoras_visuales import BotonConFeedback, Notificacion
    from modulo6_correcciones import VistaTablaExcel
except ImportError:
    # Stubs para desarrollo independiente
    def get_db_connection(): return None
    COLOR_FONDO = "#f3f4f6"
    COLOR_MORADO = "#8b5cf6"
    COLOR_VERDE = "#10b981"
    class BotonConFeedback:
        def __init__(self, parent, texto, comando, color, icono):
            self.btn = tk.Button(parent, text=f"{icono} {texto}", command=comando, bg=color, fg="white", font=("Segoe UI", 10, "bold"), padx=10, pady=5)
        def pack(self, **kwargs): self.btn.pack(**kwargs)
    class Notificacion:
        @staticmethod
        def mostrar(root, msg, tipo): messagebox.showinfo(tipo, msg)
    class VistaTablaExcel:
        def __init__(self, parent, columnas, on_select):
            self.frame = tk.Frame(parent)
            self.tree = ttk.Treeview(self.frame, columns=columnas, show='headings')
            for col in columnas: self.tree.heading(col, text=col)
            self.tree.pack(fill=tk.BOTH, expand=True)
        def cargar_productos(self, data):
            for i in self.tree.get_children(): self.tree.delete(i)
            for row in data: self.tree.insert('', tk.END, values=list(row.values()))

ESTADOS_ORDEN = ['por_empezar', 'en_proceso', 'montado', 'tintado', 'barnizado', 'listo_para_entregar', 'entregado', 'cancelado']

class TabGestionPedidos(tk.Frame):
    def __init__(self, parent, app_main=None):
        super().__init__(parent, bg=COLOR_FONDO)
        self.app_main = app_main
        self.estado_actual = 'por_empezar'
        self.pedidos = []
        self._crear_interfaz()
        self.cargar_datos()

    def _crear_interfaz(self):
        # Header
        header = tk.Frame(self, bg=COLOR_FONDO, pady=10, padx=20)
        header.pack(fill=tk.X)
        
        tk.Label(header, text="📋 GESTIÓN DE PEDIDOS (VISTA WEB)", font=("Segoe UI", 16, "bold"), bg=COLOR_FONDO).pack(side=tk.LEFT)
        
        # Botones de Acción
        actions = tk.Frame(header, bg=COLOR_FONDO)
        actions.pack(side=tk.RIGHT)
        
        BotonConFeedback(actions, "Actualizar", self.cargar_datos, COLOR_VERDE, "🔄").pack(side=tk.RIGHT, padx=5)
        BotonConFeedback(actions, "Eliminar", self.confirmar_eliminar, "#dc2626", "🗑️").pack(side=tk.RIGHT, padx=5)
        BotonConFeedback(actions, "Editar", self.abrir_editor, COLOR_MORADO, "✏️").pack(side=tk.RIGHT, padx=5)
        BotonConFeedback(actions, "Nuevo", self.nuevo_pedido, "#2563eb", "➕").pack(side=tk.RIGHT, padx=5)
        
        # Acciones Masivas
        bulk_frame = tk.LabelFrame(header, text="📦 Acciones Masivas", bg=COLOR_FONDO, padx=5)
        bulk_frame.pack(side=tk.RIGHT, padx=20)
        
        tk.Button(bulk_frame, text="🗑️ Eliminar Selecc.", command=self.eliminar_masivo, 
                  bg="#fee2e2", fg="#991b1b", font=("Segoe UI", 9)).pack(side=tk.LEFT, padx=2)
        tk.Button(bulk_frame, text="📋 Cambiar Estado", command=self.cambiar_estado_masivo,
                  bg="#e0e7ff", fg="#3730a3", font=("Segoe UI", 9)).pack(side=tk.LEFT, padx=2)

        # Barra de Filtros (Filtros de estado como en la web)
        filtros_frame = tk.Frame(self, bg=COLOR_FONDO, pady=10, padx=20)
        filtros_frame.pack(fill=tk.X)
        
        self.btns_filtros = {}
        for est in ESTADOS_ORDEN:
            btn = tk.Button(filtros_frame, text=est.capitalize(), 
                            command=lambda e=est: self.cambiar_filtro(e),
                            font=("Segoe UI", 10), padx=15, pady=5,
                            relief=tk.FLAT, cursor="hand2")
            btn.pack(side=tk.LEFT, padx=5)
            self.btns_filtros[est] = btn
        
        self._actualizar_estilo_filtros()

        # Tabla de Pedidos
        columnas = ['ID', 'Nº Pedido', 'Cliente', 'Fecha', 'Total', 'Estado']
        self.vista_tabla = VistaTablaExcel(self, columnas, self.on_seleccionar)
        self.vista_tabla.frame.pack(fill=tk.BOTH, expand=True, padx=20, pady=10)
        
        # Total Label
        self.lbl_total = tk.Label(self, text="Cargando pedidos...", font=("Segoe UI", 10), bg=COLOR_FONDO, fg="#64748b")
        self.lbl_total.pack(pady=5)

    def cambiar_filtro(self, nuevo_estado):
        self.estado_actual = nuevo_estado
        self._actualizar_estilo_filtros()
        self.cargar_datos()

    def _actualizar_estilo_filtros(self):
        for est, btn in self.btns_filtros.items():
            if est == self.estado_actual:
                btn.config(bg="#d4af37", fg="black", font=("Segoe UI", 10, "bold"))
            else:
                btn.config(bg="white", fg="#475569", font=("Segoe UI", 10))

    def cargar_datos(self):
        conn = get_db_connection()
        if not conn: return
        try:
            cursor = conn.cursor(dictionary=True)
            # Join con clientes para obtener el nombre
            query = """
                SELECT p.*, c.nombre as nombre_cliente 
                FROM pedidos p
                LEFT JOIN clientes c ON p.id_cliente = c.id
                WHERE p.estado = %s
                ORDER BY p.id DESC
            """
            cursor.execute(query, (self.estado_actual,))
            self.pedidos = cursor.fetchall()
            
            # Formatear para la tabla
            data_tabla = []
            for p in self.pedidos:
                # Usar numero_pedido si existe, si no concatenar ID
                num = p.get('numero_pedido') if p.get('numero_pedido') else f"#{p['id']:04d}"
                
                # Formatear fecha de forma robusta
                fecha_raw = p.get('fecha_pedido')
                fecha = "N/A"
                if fecha_raw:
                    if isinstance(fecha_raw, datetime):
                        fecha = fecha_raw.strftime("%d/%m/%Y")
                    else:
                        try:
                            # Intentar parsear si es cadena
                            fecha = str(fecha_raw).split()[0] # YYYY-MM-DD
                        except:
                            fecha = str(fecha_raw)
                
                total = f"{p.get('total', 0):.2f} €"
                
                data_tabla.append({
                    'ID': p['id'],
                    'Nº Pedido': num,
                    'Cliente': p.get('nombre_cliente', p.get('nombre_cliente_manual', 'N/A')),
                    'Fecha': fecha,
                    'Total': total,
                    'Estado': p['estado']
                })
            
            self.vista_tabla.cargar_productos(data_tabla)
            self.lbl_total.config(text=f"Total: {len(self.pedidos)} pedidos en estado '{self.estado_actual}'")
            
        except Exception as e:
            print(f"Error cargando pedidos: {e}")
            if self.app_main:
                self.lbl_total.config(text=f"❌ Error DB: {e}", fg="red")
        finally:
            conn.close()

    def on_seleccionar(self, item_dict):
        # item_dict contiene los valores formateados de la tabla
        self.seleccionado = item_dict

    def get_pedido_seleccionado(self):
        if not hasattr(self, 'vista_tabla') or not self.vista_tabla.tree.selection():
            return None
        
        item = self.vista_tabla.tree.selection()[0]
        id_ped = self.vista_tabla.tree.item(item, "values")[0]
        return next((p for p in self.pedidos if str(p['id']) == str(id_ped)), None)

    def nuevo_pedido(self):
        if hasattr(self.app_main, 'tab_pedidos'):
            self.app_main.tab_pedidos.abrir_crear_pedido()
            self.cargar_datos()
        else:
            messagebox.showinfo("INFO", "Usa la pestaña de Tablero Kanban para crear pedidos de producción por ahora.")

    def confirmar_eliminar(self):
        pedido = self.get_pedido_seleccionado()
        if not pedido:
            messagebox.showwarning("Aviso", "Selecciona un pedido primero")
            return
        
        if messagebox.askyesno("Confirmar", f"¿Eliminar permanentemente el pedido #{pedido['id']}?"):
            self._ejecutar_eliminar([pedido['id']])

    def eliminar_masivo(self):
        seleccionados = self.vista_tabla.get_selected_items()
        if not seleccionados:
            messagebox.showwarning("Aviso", "No hay pedidos seleccionados")
            return
        
        ids = [p['id'] for p in seleccionados]
        if messagebox.askyesno("Confirmar", f"¿Eliminar permanentemente los {len(ids)} pedidos seleccionados?"):
            self._ejecutar_eliminar(ids)

    def _ejecutar_eliminar(self, lista_ids):
        conn = get_db_connection()
        try:
            cursor = conn.cursor()
            format_strings = ','.join(['%s'] * len(lista_ids))
            cursor.execute(f"DELETE FROM pedidos WHERE id IN ({format_strings})", tuple(lista_ids))
            conn.commit()
            Notificacion.mostrar(self, f"{len(lista_ids)} pedidos eliminados", "exito")
            self.cargar_datos()
        except Exception as e:
            messagebox.showerror("Error", f"No se pudo eliminar: {e}")
        finally:
            conn.close()

    def cambiar_estado_masivo(self):
        seleccionados = self.vista_tabla.get_selected_items()
        if not seleccionados:
            messagebox.showwarning("Aviso", "No hay pedidos seleccionados")
            return
        
        ids = [p['id'] for p in seleccionados]
        
        win = tk.Toplevel(self)
        win.title("Cambiar Estado Masivo")
        win.geometry("300x200")
        win.configure(bg="white")
        
        tk.Label(win, text=f"Actualizar {len(ids)} pedidos a:", bg="white").pack(pady=20)
        v_est = tk.StringVar(value=ESTADOS_ORDEN[0])
        cb = ttk.Combobox(win, textvariable=v_est, values=ESTADOS_ORDEN, state="readonly")
        cb.pack(pady=5)
        
        def save():
            conn = get_db_connection()
            try:
                cursor = conn.cursor()
                format_strings = ','.join(['%s'] * len(ids))
                query = f"UPDATE pedidos SET estado = %s WHERE id IN ({format_strings})"
                cursor.execute(query, [v_est.get()] + ids)
                conn.commit()
                win.destroy()
                self.cargar_datos()
                Notificacion.mostrar(self, "Estados actualizados", "exito")
            except Exception as e:
                messagebox.showerror("Error", str(e))
            finally:
                conn.close()
        
        tk.Button(win, text="Actualizar Todos", command=save, bg=COLOR_VERDE, fg="white").pack(pady=20)

    def abrir_editor(self):
        pedido = self.get_pedido_seleccionado()
        if not pedido:
            messagebox.showwarning("Aviso", "Selecciona un pedido primero")
            return
        
        win = tk.Toplevel(self)
        win.title(f"Editar Pedido #{pedido['id']}")
        win.geometry("500x600")
        win.configure(bg="white")
        
        # Campos
        tk.Label(win, text="Estado:", bg="white").pack(pady=(10,0))
        v_estado = tk.StringVar(value=pedido['estado'])
        cb_estado = ttk.Combobox(win, textvariable=v_estado, values=ESTADOS_ORDEN, state="readonly", width=30)
        cb_estado.pack(pady=5)
        
        tk.Label(win, text="Total (€):", bg="white").pack(pady=(10,0))
        ent_total = ttk.Entry(win, width=15)
        ent_total.insert(0, str(pedido.get('total', 0)))
        ent_total.pack(pady=5)
        
        tk.Label(win, text="Notas:", bg="white").pack(pady=(10,0))
        text_notas = tk.Text(win, height=10, width=50)
        text_notas.insert("1.0", pedido.get('notas', '') if pedido.get('notas') else "")
        text_notas.pack(pady=5, padx=20)
        
        def save():
            try:
                conn = get_db_connection()
                cursor = conn.cursor()
                cursor.execute("""
                    UPDATE pedidos 
                    SET estado = %s, total = %s, notas = %s 
                    WHERE id = %s
                """, (v_estado.get(), float(ent_total.get()), text_notas.get("1.0", tk.END).strip(), pedido['id']))
                conn.commit()
                conn.close()
                win.destroy()
                self.cargar_datos()
                Notificacion.mostrar(self, "Pedido actualizado", "exito")
            except Exception as e:
                messagebox.showerror("Error", str(e))

        BotonConFeedback(win, "Guardar Cambios", save, COLOR_VERDE, "💾").pack(pady=20)
