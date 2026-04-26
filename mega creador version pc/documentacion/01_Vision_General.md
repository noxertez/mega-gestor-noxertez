# 01. Visión General y Propósito

## ¿Qué es Mega Gestor Noxertez?
Es una aplicación de escritorio desarrollada en **Python** utilizando la librería gráfica **Tkinter**, diseñada específicamente para artesanos y creadores que necesitan gestionar un gran volumen de productos, materiales, ventas y envíos de manera centralizada.

## Objetivos Principales
*   **Centralización**: Eliminar el uso de hojas de cálculo dispersas. Todo reside en una base de datos local SQLite.
*   **Inteligencia Artificial**: Utilizar modelos de lenguaje (Gemini y Groq) para analizar imágenes, generar descripciones automáticas y crear "recetas" de despiece de materiales.
*   **Automatización de Pedidos**: Integrar un tablero Kanban para controlar la producción y automatizar tareas mediante n8n.
*   **Gestión de Envíos Real**: Integración con la API de Packlink PRO para comparar tarifas de transportistas y generar etiquetas de envío desde la propia app.
*   **Omnicanalidad**: Control de ventas tanto físicas como online, gestión de influencers y clientes.

## Filosofía de Diseño
La aplicación se basa en un sistema de **módulos independientes** que se integran en una interfaz de pestañas. Cada módulo tiene una responsabilidad única, lo que permite un mantenimiento sencillo y una escalabilidad constante.
