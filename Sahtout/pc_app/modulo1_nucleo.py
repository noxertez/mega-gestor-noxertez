"""
MÓDULO 1: NÚCLEO FUNCIONAL (MOTOR DE IA)
- Soporte multiclave para Gemini y Groq
- Fallback automático entre proveedores y llaves
- Contador de llamadas diario
"""

import google.generativeai as genai
from PIL import Image
import json
import re
import os
import base64
import time
from datetime import datetime

try:
    from groq import Groq
except ImportError:
    Groq = None

# ========================================
# CONFIGURACIÓN Y RUTAS
# ========================================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ARCHIVO_CONTADOR = os.path.join(BASE_DIR, "contador_ia.json")
CONFIG_FILE = os.path.join(BASE_DIR, "config_app.json")
LIMITE_DIARIO = 1500

# ========================================
# GESTIÓN DE CONFIGURACIÓN (API KEYS)
# ========================================

def cargar_config():
    if os.path.exists(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, 'r', encoding='utf-8') as f:
                return json.load(f)
        except:
            pass
    return {}

def obtener_api_keys():
    config = cargar_config()
    gemini_keys = config.get("API_KEYS", [])
    groq_keys = config.get("GROQ_KEYS", [])
    # Normalizar si vienen como strings (compatibilidad)
    gemini_norm = [{"nombre": f"Key {i}", "key": k} if isinstance(k, str) else k for i, k in enumerate(gemini_keys)]
    groq_norm = [{"nombre": f"Groq {i}", "key": k} if isinstance(k, str) else k for i, k in enumerate(groq_keys)]
    return gemini_norm, groq_norm

# ========================================
# GESTIÓN DE CONTADOR
# ========================================

def cargar_contador():
    if os.path.exists(ARCHIVO_CONTADOR):
        try:
            with open(ARCHIVO_CONTADOR, 'r') as f:
                return json.load(f)
        except:
            pass
    return {"llamadas": 0, "fecha": datetime.now().strftime("%Y-%m-%d")}

def incrementar_contador():
    cnt = cargar_contador()
    hoy = datetime.now().strftime("%Y-%m-%d")
    if cnt.get("fecha") != hoy:
        cnt = {"llamadas": 0, "fecha": hoy}
    cnt["llamadas"] += 1
    with open(ARCHIVO_CONTADOR, 'w') as f:
        json.dump(cnt, f)
    return cnt["llamadas"]

# ========================================
# MOTOR DE ANÁLISIS
# ========================================

def analizar_imagen_ia(ruta_imagen, prompt_type="producto", style="Neutral"):
    # Pequeño delay de seguridad para evitar bloqueos por ráfaga (Rate Limiting)
    time.sleep(2)
    
    from modulo4_fichas import ESTILOS_IA
    
    config = cargar_config()
    ia_preferida = config.get("IA_PREFERIDA", "Gemini")
    modelo_gemini = config.get("MODELO_IA", "gemini-1.5-flash")
    modelo_groq = config.get("GROQ_MODELO", "llama-3.1-70b-versatile")
    
    # Prompt
    desc_estilo = ""
    for cat in ESTILOS_IA.values():
        if style in cat:
            desc_estilo = cat[style]
            break
            
    if prompt_type == "producto":
        prompt = f"Analiza este producto artesanal. Estilo: {style} ({desc_estilo}). Responde SOLO JSON: {{\"nombre\", \"categoria\", \"subcategoria\", \"marca\", \"precio\", \"color\", \"dimensiones\", \"descripcion\"}}"
    elif prompt_type == "despiece":
        prompt = "Analiza este producto y dime qué materiales se ven que se necesiten para fabricarlo. Responde SOLO JSON: [{\"material\": \"nombre\", \"cantidad\": \"n de unidades\", \"unidad\": \"u/cm/m\"}]"
    else:
        prompt = "Analiza esta imagen y descríbela en formato JSON."

    gemini_keys, groq_keys = obtener_api_keys()

    def tratar_gemini():
        for k_item in gemini_keys:
            key = k_item.get("key")
            if not key: continue
            try:
                # Asegurar prefijo de modelo si no lo tiene
                nombre_modelo = modelo_gemini
                if not nombre_modelo.startswith("models/"):
                    nombre_modelo = f"models/{nombre_modelo}"
                
                genai.configure(api_key=key)
                model = genai.GenerativeModel(nombre_modelo)
                img = Image.open(ruta_imagen)
                response = model.generate_content([prompt, img])
                incrementar_contador()
                return parsear_json(response.text)
            except Exception as e:
                print(f"DEBUG: Fallo Gemini con {k_item.get('nombre')}: {e}")
                if "QUOTA" in str(e).upper(): continue
                return None
        return "FALLO"

    def tratar_groq():
        if not Groq or not groq_keys: return "FALLO"
        try:
            with open(ruta_imagen, "rb") as f:
                b64_img = base64.b64encode(f.read()).decode('utf-8')
        except: return None

        for k_item in groq_keys:
            key = k_item.get("key")
            if not key: continue
            try:
                client = Groq(api_key=key)
                resp = client.chat.completions.create(
                    model=modelo_groq,
                    messages=[{"role": "user", "content": [
                        {"type": "text", "text": prompt},
                        {"type": "image_url", "image_url": {"url": f"data:image/jpeg;base64,{b64_img}"}}
                    ]}],
                    response_format={"type": "json_object"}
                )
                incrementar_contador()
                return parsear_json(resp.choices[0].message.content)
            except Exception as e:
                print(f"DEBUG: Fallo Groq con {k_item.get('nombre')}: {e}")
                continue
        return "FALLO"

    # Orden por preferencia
    if ia_preferida == "Groq":
        res = tratar_groq()
        if res != "FALLO": return res
        return tratar_gemini()
    else:
        res = tratar_gemini()
        if res != "FALLO": return res
        return tratar_groq()

def parsear_json(texto):
    try:
        # Limpiar posibles bloques de código markdown
        limpio = re.sub(r'```json|```', '', texto).strip()
        return json.loads(limpio)
    except:
        match = re.search(r'(\{.*\}|\[.*\])', texto, re.DOTALL)
        if match:
            try: return json.loads(match.group(0))
            except: pass
    return None
