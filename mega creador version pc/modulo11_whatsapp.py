"""
MÓDULO 11: COMPARTIR POR WHATSAPP (CON IMGBB)
- ✅ Sin necesidad de registro
- ✅ API key pública incluida
- ✅ Funciona instantáneamente
- Sube imagen a ImgBB
- Genera mensaje formateado
- Abre WhatsApp Web con mensaje pre-llenado
"""

import requests
import urllib.parse
import webbrowser
import os
import base64
from datetime import datetime
import time

# Intentar cargar pywhatkit al inicio de forma robusta
try:
    import pywhatkit
    HAS_PYWHATKIT = True
    print("✅ pywhatkit detectado y cargado correctamente.")
except Exception as e:
    HAS_PYWHATKIT = False
    print(f"⚠️ Aviso: pywhatkit no detectado o error al cargar: {e}")

# API KEY PÚBLICA (Intentar cargar de config, si no usar por defecto)
IMGBB_API_KEY_DEFAULT = "d09b24e707e0d4f7e953ab51c5cc5959"
CONFIG_FILE = "config_wa.json"
CACHE_WA_FILE = "wa_cache.json"

def cargar_config_wa():
    """Carga la configuración de WhatsApp/ImgBB"""
    import json
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
                return json.load(f)
        except:
            pass
    return {"api_key": IMGBB_API_KEY_DEFAULT}

def guardar_config_wa(api_key):
    """Guarda la configuración de WhatsApp/ImgBB"""
    import json
    try:
        with open(CONFIG_FILE, 'w', encoding='utf-8') as f:
            json.dump({"api_key": api_key}, f, indent=4)
        return True
    except Exception as e:
        print(f"⚠️ Error guardando config WA: {e}")
        return False

# Inicializar llave
_config = cargar_config_wa()
IMGBB_API_KEY = _config.get("api_key", IMGBB_API_KEY_DEFAULT)
CACHE_WA_FILE = "wa_cache.json"

def cargar_cache_wa():
    """Carga el historial de imágenes subidas para no repetir"""
    import json
    if os.path.exists(CACHE_WA_FILE):
        try:
            with open(CACHE_WA_FILE, 'r', encoding='utf-8') as f:
                return json.load(f)
        except:
            pass
    return {}

def guardar_cache_wa(cache):
    """Guarda el historial de imágenes subidas"""
    import json
    try:
        with open(CACHE_WA_FILE, 'w', encoding='utf-8') as f:
            json.dump(cache, f, indent=4)
    except Exception as e:
        print(f"⚠️ Error guardando caché WA: {e}")

# ========================================
# SUBIR IMAGEN A IMGBB
# ========================================

def obtener_api_key_actual():
    """Devuelve la llave que se está usando actualmente"""
    global IMGBB_API_KEY
    return IMGBB_API_KEY

def actualizar_api_key(nueva_key):
    """Actualiza la llave en memoria y disco"""
    global IMGBB_API_KEY
    IMGBB_API_KEY = nueva_key
    return guardar_config_wa(nueva_key)

def subir_imagen_imgbb(ruta_imagen):
    """
    Sube una imagen a ImgBB y devuelve la URL pública
    
    Args:
        ruta_imagen: Ruta local de la imagen
    
    Returns:
        str: URL pública de la imagen
        None: Si hay error
    """
    
    if not os.path.exists(ruta_imagen):
        print(f"❌ No existe: {ruta_imagen}")
        return None
    
    # Validar extensión (ImgBB no acepta PDF)
    ext = os.path.splitext(ruta_imagen)[1].lower()
    if ext == '.pdf':
        print("⚠️ ImgBB no acepta archivos PDF. Para enviar PDFs usa el modo 'Enviar Real (pywhatkit)'.")
        return None
    
    # Check en caché primero (por ruta y fecha de modificación)
    try:
        mtime = os.path.getmtime(ruta_imagen)
        cache = cargar_cache_wa()
        key = f"{ruta_imagen}_{mtime}"
        
        if key in cache:
            print(f"♻️ Usando imagen de caché: {cache[key]}")
            return cache[key]
    except:
        pass
    
    try:
        print(f"📤 Subiendo imagen a ImgBB: {os.path.basename(ruta_imagen)} ({os.path.getsize(ruta_imagen)} bytes)")
        
        # Leer imagen y convertir a base64
        with open(ruta_imagen, 'rb') as file:
            imagen_base64 = base64.b64encode(file.read()).decode('utf-8')
        
        # Hacer petición a ImgBB API
        url = "https://api.imgbb.com/1/upload"
        payload = {
            'key': obtener_api_key_actual(),
            'image': imagen_base64,
        }
        
        print(f"📡 Enviando solicitud POST a {url} (Key: {obtener_api_key_actual()[:5]}...)")
        response = requests.post(url, data=payload)
        
        # Verificar respuesta
        if response.status_code == 200:
            data = response.json()
            # Intentar obtener link directo preferentemente
            url_imagen = data['data'].get('display_url') or data['data'].get('url')
            print(f"✅ Imagen subida exitosamente: {url_imagen}")
            
            # Guardar en caché
            try:
                cache[key] = url_imagen
                guardar_cache_wa(cache)
            except:
                pass
                
            return url_imagen
        else:
            print(f"❌ Error API ImgBB ({response.status_code}): {response.text}")
            if response.status_code == 400:
                print("💡 TIP: Es posible que la API KEY sea inválida. Intenta configurar tu propia llave.")
            return None
            
    except Exception as e:
        print(f"❌ Error crítico subiendo imagen: {e}")
        import traceback
        traceback.print_exc()
        return None

# ========================================
# GENERAR MENSAJE WHATSAPP
# ========================================

def generar_mensaje_producto(producto, url_imagen, incluir_imagen=True):
    """
    Genera mensaje formateado para WhatsApp
    
    Args:
        producto: Dict con datos del producto
        url_imagen: URL pública de la imagen
        incluir_imagen: Si incluir link de imagen
    
    Returns:
        str: Mensaje formateado
    """
    
    # Emojis para hacer el mensaje más atractivo
    mensaje = f"""🎨 *{producto.get('NOMBRE', 'Producto')}*

💰 *Precio:* {producto.get('PRECIO', '0')}€
📏 *Dimensiones:* {producto.get('DIMENSIONES', 'N/A')}
🔖 *Categoría:* {producto.get('CATEGORIA', 'N/A')} / {producto.get('SUBCATEGORIA', 'N/A')}
"""
    
    # Agregar descripción si existe
    descripcion = producto.get('DESCRIPCION', '')
    if descripcion:
        # Limitar a 150 caracteres
        desc_corta = descripcion[:150]
        if len(descripcion) > 150:
            desc_corta += "..."
        mensaje += f"\n📄 _{desc_corta}_\n"
    
    # Agregar referencia
    mensaje += f"\n🆔 Ref: *{producto.get('SKU_REF', 'N/A')}*"
    
    # Agregar link de imagen AL FINAL para mejor preview en WhatsApp
    if incluir_imagen and url_imagen:
        mensaje += f"\n\n🖼️ *Ver imagen:* {url_imagen}"
    
    # Agregar llamada a la acción
    mensaje += "\n\n¿Te interesa? 😊"
    
    return mensaje

def generar_mensaje_simple(producto, url_imagen):
    """
    Genera mensaje simple (menos texto)
    
    Args:
        producto: Dict con datos del producto
        url_imagen: URL de la imagen
    
    Returns:
        str: Mensaje corto
    """
    
    mensaje = f"""🎨 {producto.get('NOMBRE', 'Producto')}

💰 {producto.get('PRECIO', '0')}€

🖼️ {url_imagen}

Ref: {producto.get('SKU_REF', 'N/A')}"""
    
    return mensaje

def generar_mensaje_catalogo(productos, titulo="CATÁLOGO"):
    """
    Genera mensaje para múltiples productos
    
    Args:
        productos: Lista de productos (máx 5)
        titulo: Título del catálogo
    
    Returns:
        str: Mensaje con múltiples productos
    """
    
    mensaje = f"""📚 *{titulo}*

"""
    
    for i, producto in enumerate(productos[:5], 1):
        mensaje += f"""{i}. {producto.get('NOMBRE', 'Producto')}
   💰 {producto.get('PRECIO', '0')}€
   🆔 {producto.get('SKU_REF', 'N/A')}

"""
    
    mensaje += "¿Te interesa alguno? 😊"
    
    return mensaje

# ========================================
# ABRIR WHATSAPP
# ========================================

def abrir_whatsapp(mensaje, numero_telefono=None):
    """
    Abre WhatsApp Web o App con mensaje pre-llenado
    
    Args:
        mensaje: Texto del mensaje
        numero_telefono: Número del destinatario (opcional)
                        Formato: "34612345678" (código país + número sin +)
    
    Ejemplos:
        abrir_whatsapp(mensaje)  # Sin número, elige contacto
        abrir_whatsapp(mensaje, "34612345678")  # A número específico
    
    Returns:
        bool: True si se abrió correctamente
    """
    
    # Codificar mensaje para URL usando quote para asegurar UTF-8 correcto
    # Reemplazar algunos caracteres problemáticos comunes si es necesario
    mensaje_limpio = mensaje.replace('–', '-').replace('—', '-')
    mensaje_codificado = urllib.parse.quote(mensaje_limpio)
    
    # Generar URL según si hay número o no
    if numero_telefono:
        # Limpiar número (quitar espacios, guiones, +)
        numero_limpio = numero_telefono.replace('+', '').replace('-', '').replace(' ', '')
        # web.whatsapp.com es más directo para saltar la pantalla de "Abrir aplicación" en PC
        url = f"https://web.whatsapp.com/send?phone={numero_limpio}&text={mensaje_codificado}"
        print(f"📱 Abriendo WhatsApp para: +{numero_limpio}")
    else:
        # Sin número = usuario elige contacto
        url = f"https://web.whatsapp.com/send?text={mensaje_codificado}"
        print(f"📱 Abriendo WhatsApp (elige contacto)...")
    
    # Abrir en navegador con un pequeño delay o reintento si es necesario
    try:
        # Algunos navegadores tienen problemas con URLs extremadamente largas en webbrowser.open
        # Si el mensaje es muy largo, intentamos abrirlo de todos modos.
        if len(url) > 2000:
            print("⚠️ El mensaje es muy largo, podría fallar en algunos navegadores.")
            
        webbrowser.open(url)
        print("✅ Comando de apertura enviado al navegador")
        return True
    except Exception as e:
        print(f"❌ Error abriendo WhatsApp: {e}")
        return False

def compartir_archivo_real_pywhatkit(numero, ruta_archivo, mensaje, wait_time=20):
    """
    Envía una imagen o archivo real usando pywhatkit (automatización)
    """
    if not HAS_PYWHATKIT:
        print("❌ pywhatkit no está instalado. Ejecuta: pip install pywhatkit")
        return False
    
    if not os.path.exists(ruta_archivo):
        print(f"❌ No existe el archivo: {ruta_archivo}")
        return False
    
    try:
        # Limpiar número
        numero_limpio = "+" + numero.replace('+', '').replace('-', '').replace(' ', '')
        
        print(f"🚀 Iniciando envío real con pywhatkit...")
        print(f"📱 Destino: {numero_limpio}")
        print(f"📁 Archivo: {ruta_archivo}")
        
        # pywhatkit.sendwhats_image abre una pestaña, espera wait_time, pega la imagen y envía
        pywhatkit.sendwhats_image(
            numero_limpio, 
            ruta_archivo, 
            mensaje, 
            wait_time=wait_time, 
            tab_close=True,
            close_time=3
        )
        print("✅ Comando enviado correctamente a pywhatkit")
        return True
    except Exception as e:
        print(f"❌ Error con pywhatkit: {e}")
        return False

# ========================================
# FUNCIÓN COMPLETA: COMPARTIR PRODUCTO
# ========================================

def compartir_producto_whatsapp(producto, 
                                ruta_imagen=None,
                                numero_telefono=None,
                                mensaje_tipo="completo",
                                usar_pywhatkit=False):
    """
    Función completa para compartir producto por WhatsApp
    
    Args:
        producto: Dict con datos del producto
        ruta_imagen: Ruta a la imagen (FOTO_PORTADA o ficha PNG)
        numero_telefono: Número destino (opcional, ej: "34612345678")
        mensaje_tipo: "completo" o "simple"
        usar_pywhatkit: Si usar automatización real de archivos
    
    Returns:
        bool: True si se compartió correctamente
    
    Ejemplos:
        # Compartir a contacto libre
        compartir_producto_whatsapp(producto, "imagen.png")
        
        # Compartir a número específico
        compartir_producto_whatsapp(producto, "imagen.png", "34612345678")
        
        # Mensaje simple
        compartir_producto_whatsapp(producto, "imagen.png", mensaje_tipo="simple")
    """
    
    print("="*60)
    print("📱 COMPARTIR POR WHATSAPP")
    print("="*60)
    
    # 1. Verificar imagen
    if not ruta_imagen:
        ruta_imagen = producto.get('FOTO_PORTADA', '')
    
    # 2. Gestionar imagen (Subir a ImgBB solo si no es envío REAL)
    url_imagen = None
    if ruta_imagen and os.path.exists(ruta_imagen):
        if not usar_pywhatkit:
            # Solo subimos a ImgBB si es el método de "enlace" (webbrowser)
            url_imagen = subir_imagen_imgbb(ruta_imagen)
            if not url_imagen:
                print("⚠️ No se pudo subir imagen, continuando sin enlace...")
        else:
            print("🚀 Modo 'Envío Real': Saltando subida a ImgBB para mayor rapidez.")
    else:
        if ruta_imagen:
            print(f"⚠️ La imagen no existe en la ruta: {ruta_imagen}")
    
    # 3. Generar mensaje
    # Si es envío real, NO incluimos el link de ImgBB en el texto (es redundante)
    incluir_link = not usar_pywhatkit
    
    if mensaje_tipo == "simple":
        mensaje = generar_mensaje_simple(producto, url_imagen) if incluir_link else generar_mensaje_simple(producto, "")
    else:
        mensaje = generar_mensaje_producto(producto, url_imagen, incluir_imagen=incluir_link)
    
    print("\n📝 Mensaje generado:")
    print("-"*60)
    print(mensaje)
    print("-"*60)
    
    # 4. Abrir WhatsApp o usar pywhatkit
    if usar_pywhatkit and ruta_imagen and os.path.exists(ruta_imagen):
        # Para pywhatkit el número debe tener el + si es número específico
        # pero abrir_whatsapp lo limpia. Aquí necesitamos asegurarnos del formato.
        if not numero_telefono:
            print("⚠️ pywhatkit requiere un número específico para automatizar el envío.")
            print("   Abriendo método normal (enlace)...")
            exito = abrir_whatsapp(mensaje, numero_telefono)
        else:
            exito = compartir_archivo_real_pywhatkit(numero_telefono, ruta_imagen, mensaje)
    else:
        exito = abrir_whatsapp(mensaje, numero_telefono)
    
    # 5. Guardar historial (opcional)
    if exito:
        guardar_historial_compartido(producto, numero_telefono)
    
    print("="*60)
    
    return exito

# ========================================
# FUNCIONES AUXILIARES
# ========================================

def compartir_ficha_completa(sku, carpeta_fichas="fichas", numero_telefono=None):
    """
    Comparte la FICHA completa (no solo la foto) por WhatsApp
    
    Args:
        sku: SKU del producto
        carpeta_fichas: Dónde buscar la ficha
        numero_telefono: Número destino (opcional)
    """
    
    # Buscar ficha
    sku_limpio = sku.replace('/', '_')
    fichas_posibles = [
        os.path.join(carpeta_fichas, f"FICHA_{sku_limpio.split('-')[0]}_VARIANTES.png"),
        os.path.join(carpeta_fichas, f"FICHA_{sku_limpio}.png")
    ]
    
    ruta_ficha = None
    for ficha in fichas_posibles:
        if os.path.exists(ficha):
            ruta_ficha = ficha
            break
    
    if not ruta_ficha:
        print(f"❌ No se encontró ficha para SKU: {sku}")
        return False
    
    # Crear producto mínimo con SKU
    producto = {'SKU_REF': sku, 'NOMBRE': f'Producto {sku}', 'PRECIO': ''}
    
    # Compartir
    return compartir_producto_whatsapp(producto, ruta_ficha, numero_telefono, "simple")

def compartir_varios_productos(productos, numero_telefono=None):
    """
    Comparte varios productos en un solo mensaje
    
    Args:
        productos: Lista de productos (máx 5)
        numero_telefono: Número destino (opcional)
    """
    
    if len(productos) > 5:
        print("⚠️ Máximo 5 productos. Tomando los primeros 5...")
        productos = productos[:5]
    
    mensaje = generar_mensaje_catalogo(productos)
    
    return abrir_whatsapp(mensaje, numero_telefono)

def guardar_historial_compartido(producto, numero=None):
    """
    Guarda historial de productos compartidos
    
    Args:
        producto: Dict del producto
        numero: Número al que se envió (opcional)
    """
    
    historial = {
        'fecha': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'sku': producto.get('SKU_REF'),
        'nombre': producto.get('NOMBRE'),
        'numero': numero or 'Sin especificar'
    }
    
    # Guardar en archivo
    try:
        with open('whatsapp_historial.txt', 'a', encoding='utf-8') as f:
            f.write(f"{historial['fecha']} | {historial['sku']} | {historial['numero']}\n")
    except:
        pass

# ========================================
# INTEGRACIÓN CON BASE DE DATOS DE CLIENTES
# ========================================

def obtener_clientes_whatsapp():
    """Obtiene la lista de clientes y sus teléfonos desde la DB"""
    try:
        from modulo3_gestion import get_db_connection
        conn = get_db_connection()
        if not conn: return {}
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT nombre, telefono FROM clientes")
        clientes = cursor.fetchall()
        conn.close()
        # Devolver diccionario {nombre: telefono}
        return {c['nombre']: c['telefono'] for c in clientes if c['telefono']}
    except Exception as e:
        print(f"⚠️ Error cargando clientes para MySQL (WhatsApp): {e}")
        return {}

def compartir_a_cliente(producto, ruta_imagen, nombre_cliente):
    """
    Comparte a un cliente real de la base de datos
    """
    clientes = obtener_clientes_whatsapp()
    numero = clientes.get(nombre_cliente)
    
    if not numero:
        print(f"❌ Cliente '{nombre_cliente}' no encontrado en la base de datos")
        return False
    
    return compartir_producto_whatsapp(producto, ruta_imagen, numero)

# ========================================
# UTILIDADES
# ========================================

def formatear_numero_espanol(numero):
    """
    Ayuda a formatear número español para WhatsApp
    
    Args:
        numero: Número en cualquier formato
    
    Returns:
        str: Número formateado para WhatsApp
    
    Ejemplos:
        "+34 612 34 56 78" -> "34612345678"
        "612 345 678" -> "34612345678"
        "612345678" -> "34612345678"
    """
    
    # Limpiar
    numero = numero.replace('+', '').replace('-', '').replace(' ', '')
    
    # Si no tiene código de país, añadir España
    if not numero.startswith('34') and len(numero) == 9:
        numero = '34' + numero
    
    return numero

def ver_historial():
    """Muestra el historial de compartidos"""
    
    try:
        with open('whatsapp_historial.txt', 'r', encoding='utf-8') as f:
            print("\n📜 HISTORIAL DE COMPARTIDOS")
            print("="*60)
            for linea in f:
                print(linea.strip())
            print("="*60)
    except FileNotFoundError:
        print("📜 No hay historial aún")

# ========================================
# TEST DEL MÓDULO
# ========================================

if __name__ == "__main__":
    print("🧪 TEST DEL MÓDULO 11 - WHATSAPP (IMGBB)\n")
    
    # Producto de ejemplo
    producto_test = {
        'SKU_REF': 'NOXPORABS0001',
        'NOMBRE': 'Portavela Abstracto Espiral',
        'PRECIO': '25',
        'DIMENSIONES': '7 x 7 x 12 cm',
        'CATEGORIA': 'PORTAVELAS',
        'SUBCATEGORIA': 'ABSTRACTO',
        'DESCRIPCION': 'Elegante portavelas con tinte oscuro rojizo tipo caoba y efectos de quemado.',
        'FOTO_PORTADA': 'imagen_test.png'  # Cambia por ruta real
    }
    
    print("="*60)
    print("✅ LISTO PARA USAR - SIN NECESIDAD DE REGISTRO")
    print("="*60)
    print()
    print("📝 INSTRUCCIONES:")
    print()
    print("1. Uso básico (sin número):")
    print("   compartir_producto_whatsapp(producto, 'imagen.png')")
    print()
    print("2. Con número específico:")
    print("   compartir_producto_whatsapp(producto, 'imagen.png', '34612345678')")
    print()
    print("3. Formato de número:")
    print("   - España: '34' + teléfono sin el primer 0")
    print("   - Ejemplo: +34 612 34 56 78 → '34612345678'")
    print("   - México: '52' + teléfono completo")
    print("   - Argentina: '54' + teléfono completo")
    print()
    print("4. Configurar clientes frecuentes:")
    print("   - Edita la línea 294 (NUMEROS_FRECUENTES)")
    print("   - Agrega: 'maria': '34612345678'")
    print()
    print("="*60)
    print("🚀 API KEY YA INCLUIDA - FUNCIONA INSTANTÁNEAMENTE")
    print("="*60)
    
    # Descomentar para probar:
    # compartir_producto_whatsapp(producto_test, 'ruta/a/imagen.png')
