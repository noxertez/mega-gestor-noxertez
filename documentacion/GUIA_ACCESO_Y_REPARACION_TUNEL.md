# Guía de Acceso y Reparación de n8n.noxertez.com

Esta guía resume cómo acceder a n8n desde cualquier dispositivo y cómo solucionar los problemas de conexión detectados con el proveedor de internet (Pepephone/MasMovil).

## 1. Métodos de Acceso

### Acceso desde el Taller / Televisión (Misma Red WiFi)
Si el dispositivo está en la misma casa o taller usando el mismo WiFi, **no uses la dirección web externa**. Usa la IP local del ordenador para evitar bloqueos del operador:
*   URL: **`http://192.168.1.130:5678`**
*   *Ventaja: No requiere WARP, es más rápido y funciona aunque internet falle.*

### Acceso Externo (Móvil con 4G/5G)
Desde fuera de casa, puedes usar la dirección normal:
*   URL: **`https://n8n.noxertez.com`**

### Acceso con WiFi de Casa (Si el operador bloquea)
Si al usar el WiFi de casa recibes errores como "Timeout" o "Bad Gateway", es debido a un bloqueo de Pepephone. La solución es usar **Cloudflare WARP (1.1.1.1)**:
1.  Instala la app **1.1.1.1** (WARP) en el PC o en el móvil.
2.  Actívala (el icono debe estar en azul).
3.  Accede normalmente por la URL web.

## 2. Reparación del Túnel

Si el túnel se queda "colgado" o no responde, utiliza el script automático que hemos configurado:

1.  Busca el archivo: `Reparar_Tunel_Forzado.bat`
2.  Haz clic derecho y selecciona **"Ejecutar como administrador"**.
3.  Este script cerrará los procesos antiguos y forzará la conexión usando el archivo `config.yml`.

## 3. Información Técnica para el Operador (Pepephone)

Si vuelves a llamar al soporte técnico, diles que has detectado un bloqueo de enrutamiento (TCP Timeout) hacia este rango de IPs de Cloudflare:
*   **IP 1: `188.114.96.5`**
*   **IP 2: `188.114.97.5`**

*Nota: Ignora sus IPs de DNS (46.6.113.34, etc.), ya que el problema es de tráfico saliente hacia Cloudflare.*

---
*Ultima actualización: 04 de Abril de 2026*
