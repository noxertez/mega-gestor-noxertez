import pandas as pd
import os
from datetime import datetime

class StockManager:
    def __init__(self):
        self.path_inv = "data/inventario_materia_prima.csv"
        self.path_recetas = "data/receta_producto.csv"
        self.sep = ";" # Usamos punto y coma para compatibilidad con Excel en español
        self._asegurar_archivos()

    def _asegurar_archivos(self):
        os.makedirs("data", exist_ok=True)
        # Columnas requeridas
        cols_inv = ["ID_Material", "Nombre", "Unidad", "Stock_Actual", "Punto_Pedido"]
        cols_rec = ["SKU_Articulo", "Nombre", "Medidas", "Imagen", "Despiece_Texto", "ID_Material", "Cantidad_Necesaria", "Merma_Estimada"]

        # Migración/Creación de Inventario
        self._verificar_y_migrar(self.path_inv, cols_inv)
        # Migración/Creación de Recetas
        self._verificar_y_migrar(self.path_recetas, cols_rec)

    def _verificar_y_migrar(self, path, columns):
        """Asegura que el archivo exista, tenga las columnas y use el separador correcto"""
        if not os.path.exists(path):
            pd.DataFrame(columns=columns).to_csv(path, index=False, sep=self.sep)
            return

        # Intentar detectar separador actual
        try:
            # Primero probamos con el nuevo (punto y coma)
            df = pd.read_csv(path, sep=self.sep)
            if len(df.columns) <= 1: # Si solo hay una columna, probamos con coma
                df = pd.read_csv(path, sep=",")
        except:
            df = pd.DataFrame(columns=columns)

        # Añadir columnas faltantes
        for c in columns:
            if c not in df.columns:
                df[c] = ""
        
        # Reordenar y guardar con el separador correcto
        df = df[columns]
        df.to_csv(path, index=False, sep=self.sep)

    def sincronizar_con_catalogo(self, productos_catalogo):
        """
        Sincroniza Tabla 1 (Catálogo) con Tabla 2 (Recetas/Despiece).
        - Solo procesa productos 'BASE'.
        - Actualiza Nombre, Medidas e Imagen de productos existentes si están vacíos o para asegurar sync.
        - Añade nuevos productos.
        """
        if not os.path.exists(self.path_recetas):
            self._asegurar_archivos()
            
        recetas = pd.read_csv(self.path_recetas, sep=self.sep)
        nuevos_registros = []
        actualizados = 0
        
        # Diccionario para búsqueda rápida en recetas actuales
        # Usamos el primer índice encontrado para cada SKU
        sku_to_idx = {sku: idx for idx, sku in enumerate(recetas['SKU_Articulo'])}
        
        for p in productos_catalogo:
            sku = p.get('SKU_REF')
            es_variante = str(p.get('ES_VARIANTE', '')).upper()
            
            # Solo artículos BASE
            if es_variante == 'BASE':
                nombre = p.get('NOMBRE', '')
                medidas = p.get('DIMENSIONES', '')
                imagen = p.get('FOTO_PORTADA', '')
                
                if sku in sku_to_idx:
                    # Actualizar información básica si es necesario o siempre para asegurar sincronía
                    idx = sku_to_idx[sku]
                    # Solo actualizamos si los campos están vacíos o son diferentes (para evitar sobreescritura innecesaria)
                    cambio = False
                    if pd.isna(recetas.at[idx, 'Nombre']) or recetas.at[idx, 'Nombre'] == "":
                        recetas.at[idx, 'Nombre'] = nombre
                        cambio = True
                    if pd.isna(recetas.at[idx, 'Medidas']) or recetas.at[idx, 'Medidas'] == "":
                        recetas.at[idx, 'Medidas'] = medidas
                        cambio = True
                    # Forzamos actualización de imagen si es un link de Drive o está vacía
                    img_actual = str(recetas.at[idx, 'Imagen'])
                    if "drive.google.com" in img_actual or img_actual == "" or pd.isna(recetas.at[idx, 'Imagen']):
                        recetas.at[idx, 'Imagen'] = imagen
                        cambio = True
                    
                    if cambio: actualizados += 1
                else:
                    # Añadir nuevo
                    nuevo = {
                        "SKU_Articulo": sku,
                        "Nombre": nombre,
                        "Medidas": medidas,
                        "Imagen": imagen,
                        "Despiece_Texto": "",
                        "ID_Material": "",
                        "Cantidad_Necesaria": 0,
                        "Merma_Estimada": 1.1
                    }
                    nuevos_registros.append(nuevo)
        
        if nuevos_registros:
            df_nuevos = pd.DataFrame(nuevos_registros)
            recetas = pd.concat([recetas, df_nuevos], ignore_index=True)
            
        if nuevos_registros or actualizados > 0:
            recetas.to_csv(self.path_recetas, index=False, sep=self.sep)
            return len(nuevos_registros) + actualizados
        return 0

    def guardar_despiece_ia(self, sku, despiece_texto):
        recetas = pd.read_csv(self.path_recetas, sep=self.sep)
        recetas.loc[recetas['SKU_Articulo'] == sku, 'Despiece_Texto'] = despiece_texto
        recetas.to_csv(self.path_recetas, index=False, sep=self.sep)

    def agregar_material_receta(self, sku, mat_id, cantidad, merma=1.1):
        recetas = pd.read_csv(self.path_recetas, sep=self.sep)
        mask = (recetas['SKU_Articulo'] == sku) & (recetas['ID_Material'] == mat_id)
        
        if mask.any():
            recetas.loc[mask, 'Cantidad_Necesaria'] = cantidad
            recetas.loc[mask, 'Merma_Estimada'] = merma
        else:
            base_info = recetas[recetas['SKU_Articulo'] == sku].iloc[0].to_dict()
            base_info['ID_Material'] = mat_id
            base_info['Cantidad_Necesaria'] = cantidad
            base_info['Merma_Estimada'] = merma
            recetas = pd.concat([recetas, pd.DataFrame([base_info])], ignore_index=True)
            
        recetas.to_csv(self.path_recetas, index=False, sep=self.sep)

    def calcular_capacidad_fabricacion(self, sku_objetivo):
        inv = pd.read_csv(self.path_inv, sep=self.sep)
        recetas = pd.read_csv(self.path_recetas, sep=self.sep)
        
        materiales_necesarios = recetas[(recetas['SKU_Articulo'] == sku_objetivo) & (recetas['ID_Material'].notna()) & (recetas['ID_Material'] != "")]
        
        if materiales_necesarios.empty:
            return 0
            
        limitantes = []
        for index, row in materiales_necesarios.iterrows():
            mat_id = row['ID_Material']
            try:
                cant_req = float(row['Cantidad_Necesaria']) * float(row['Merma_Estimada'])
            except: continue
            
            if cant_req <= 0: continue
            
            stock_match = inv.loc[inv['ID_Material'] == mat_id, 'Stock_Actual']
            if stock_match.empty:
                return 0
                
            try:
                stock_disp = float(stock_match.values[0])
                posibles = stock_disp // cant_req
                limitantes.append(posibles)
            except: continue
            
        return int(min(limitantes)) if limitantes else 0

    def agregar_stock_material(self, mat_id, nombre, unidad, cantidad, punto_pedido=100):
        inv = pd.read_csv(self.path_inv, sep=self.sep)
        if mat_id in inv['ID_Material'].map(str).values:
            idx = inv[inv['ID_Material'].map(str) == str(mat_id)].index[0]
            inv.at[idx, 'Stock_Actual'] = float(inv.at[idx, 'Stock_Actual']) + float(cantidad)
        else:
            nuevo = {
                "ID_Material": mat_id,
                "Nombre": nombre,
                "Unidad": unidad,
                "Stock_Actual": float(cantidad),
                "Punto_Pedido": float(punto_pedido)
            }
            inv = pd.concat([inv, pd.DataFrame([nuevo])], ignore_index=True)
        inv.to_csv(self.path_inv, index=False, sep=self.sep)
