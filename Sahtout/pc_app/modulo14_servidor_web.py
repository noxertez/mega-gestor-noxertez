from flask import Flask, render_template_string, request, jsonify, send_file
import sqlite3
import os
from datetime import datetime
import werkzeug

app = Flask(__name__)
from modulo3_gestion import DB_PATH, RUTA_IMAGENES, RUTA_MATERIALES, RUTA_PROYECTOS, get_db_connection
from modulo16_ventas import GestorVentas
from n8n.modulo_n8n import consultar_asistente, reiniciar_n8n_local

gestor_ventas = GestorVentas()
BASE_DIR_DESKTOP = os.path.dirname(os.path.abspath(__file__))

# Soporte para CORS manual (para peticiones desde XAMPP/PHP)
@app.after_request
def add_cors_headers(response):
    response.headers.add('Access-Control-Allow-Origin', '*')
    response.headers.add('Access-Control-Allow-Headers', 'Content-Type,Authorization')
    response.headers.add('Access-Control-Allow-Methods', 'GET,PUT,POST,DELETE,OPTIONS')
    return response

# Carpetas centralizadas
PROYECTOS_FOLDER = RUTA_PROYECTOS
MATERIALES_FOLDER = RUTA_MATERIALES
UPLOAD_FOLDER = PROYECTOS_FOLDER

# Asegurar carpetas de carga
os.makedirs(PROYECTOS_FOLDER, exist_ok=True)
os.makedirs(MATERIALES_FOLDER, exist_ok=True)

# HTML Template (Mobile Friendly Premium - All-in-one Full Manager)
HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.json">
    <title>📱 Noxertez Manager</title>
    <script>
        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('SW Registered', reg))
                    .catch(err => console.log('SW Failed', err));
            });
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #6366f1; 
            --primary-dark: #4f46e5;
            --secondary: #a855f7;
            --bg: #f8fafc; 
            --accent: #10b981; 
            --danger: #ef4444;
            --text-main: #1e293b;
            --text-sub: #64748b;
            --card-bg: #ffffff;
            --radius: 18px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); margin: 0; padding: 0; color: var(--text-main); padding-bottom: 90px; }
        
        .header { background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 22px; margin: 0; font-weight: 800; color: var(--primary); }
        
        .container { padding: 15px; max-width: 700px; margin: 0 auto; }

        .tabs { display: flex; flex-wrap: wrap; background: #f1f5f9; padding: 4px; border-radius: 14px; margin-bottom: 20px; gap: 4px; }
        .tab { flex: 1 1 auto; min-width: calc(33% - 8px); padding: 10px 8px; text-align: center; border-radius: 10px; cursor: pointer; font-size: 12px; font-weight: 600; transition: 0.3s; color: var(--text-sub); white-space: nowrap; }
        .tab.active { background: white; color: var(--primary); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

        .grid { display: grid; grid-template-columns: 1fr; gap: 15px; }
        @media (min-width: 480px) { .grid { grid-template-columns: 1fr 1fr; } }

        .card { background: white; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; position: relative; border: 1px solid #f1f5f9; display: flex; flex-direction: column; }
        .card:active { transform: scale(0.98); }
        .card-img { width: 100%; height: 200px; object-fit: cover; background: #f1f5f9; cursor: pointer; }
        .card-content { padding: 15px; flex: 1; display: flex; flex-direction: column; cursor: pointer; }
        .card-title { font-size: 16px; font-weight: 800; margin: 0 0 5px 0; color: var(--text-main); }
        .card-sub { font-size: 13px; color: var(--text-sub); margin-bottom: 12px; font-weight: 600; }
        .card-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f8fafc; padding-top: 12px; }

        .badge { position: absolute; top: 12px; right: 12px; padding: 6px 12px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; z-index: 10; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .badge-real { background: var(--accent); color: white; }
        .badge-future { background: var(--secondary); color: white; }
        .badge-sku { background: rgba(255,255,255,0.9); color: var(--text-main); backdrop-filter: blur(5px); }
        .price { font-weight: 800; color: var(--primary); font-size: 16px; }

        .fab { position: fixed; bottom: 30px; right: 30px; background: var(--primary); color: white; padding: 18px 25px; border-radius: 40px; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4); border: none; font-weight: 800; display: flex; align-items: center; gap: 10px; z-index: 900; transition: 0.3s; cursor: pointer; }
        .fab:hover { transform: translateY(-5px); background: var(--primary-dark); }
        .fab i { font-size: 20px; }
        
        .sales-platforms-list { 
            display: flex; 
            flex-direction: column;
            gap: 12px; 
            margin-top: 15px;
        }

        .platform-card {
            background: #f8fafc; 
            padding: 15px; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0; 
            display: flex; 
            flex-direction: column; 
            gap: 10px;
        }
        .platform-card:hover { border-color: var(--primary); }
        .platform-name { font-weight: 800; font-size: 15px; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .platform-controls { display: flex; gap: 10px; }
        .platform-controls select { flex: 1.5; font-size: 14px; padding: 10px; }
        .platform-controls input { flex: 1; font-size: 14px; padding: 10px; font-weight: 700; color: var(--accent); }

        .section-title { font-size: 18px; font-weight: 800; margin-top: 30px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        
        /* Edit Form Controls */
        label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 8px; color: var(--text-sub); margin-top: 15px; }
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 15px; outline: none; transition: 0.3s; }
        input:focus { border-color: var(--primary); background: #f8fafc; }
        
        .detail-sheet { position: fixed; bottom: -100%; left: 0; width: 100%; height: 90%; background: white; z-index: 2000; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); border-radius: 30px 30px 0 0; box-shadow: 0 -10px 40px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow: hidden; }
        .detail-sheet.show { bottom: 0; }
        .sheet-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; opacity: 0; transition: 0.3s; }
        .sheet-overlay.show { display: block; opacity: 1; }
        .sheet-header { padding: 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: white; position: sticky; top: 0; z-index: 10; }
        
        .sheet-body { padding: 25px; flex: 1; overflow-y: auto; }
        
        /* Form Row - Back to simple grid */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .loader { margin: 40px auto; width: 40px; height: 40px; border: 4px solid #f1f5f9; border-top: 4px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; display: none; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .icon-btn { background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-sub); transition: 0.2s; padding: 5px; }
        .icon-btn:hover { transform: scale(1.2); color: var(--primary); }

        .toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); padding: 15px 30px; border-radius: 15px; background: #1e293b; color: white; font-weight: 600; z-index: 5000; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: none; }
        
        /* Fullscreen Modal */
        .fs-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: none; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; }
        .fs-modal.show { display: flex; opacity: 1; }
        .fs-img { max-width: 95%; max-height: 95%; object-fit: contain; border-radius: 10px; box-shadow: 0 0 50px rgba(0,0,0,0.5); }
        .fs-close { position: absolute; top: 20px; right: 25px; color: white; font-size: 35px; cursor: pointer; }

        /* Centered Modal Specifics */
        .mat-modal-overlay.show { display: flex !important; align-items: center; justify-content: center; padding: 20px; opacity: 1; }
        
        .mat-item { transition: 0.2s; border-radius: 12px; margin: 4px; border: 2px solid transparent; cursor: pointer; }
        .mat-item:hover { background: #f1f5f9; }
        .mat-item:active { background: #e0e7ff; transform: scale(0.98); }
        .mat-item.selected { background: #eef2ff; border-color: var(--primary); }
        .mat-item b { color: var(--text-main); }
        .mat-item small { color: var(--text-sub); }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .mat-item .check-container { font-size: 18px; }
        .search-input.mat-selected { border-color: var(--accent); background: #f0fdf4; }

        body[data-tab="ventas"] .general-field,
        body[data-tab="despiece"] .general-field { display: none !important; }
        body[data-tab="ventas"] .sales-only-field { display: block !important; }
        body[data-tab="ventas"] .price-field { pointer-events: none; opacity: 0.7; }

        /* Premium Buttons CSS */
        .actions-bar { 
            position: sticky !important; 
            bottom: 0 !important; 
            background: #ffffff !important; 
            padding: 15px 20px !important; 
            display: flex !important; 
            gap: 12px !important; 
            border-top: 1px solid #eeeeee !important; 
            z-index: 1000 !important;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.05) !important;
            width: 100% !important;
        }
        .btn { 
            flex: 1 !important; 
            padding: 20px !important; 
            border-radius: 16px !important; 
            border: none !important; 
            font-weight: 800 !important; 
            font-size: 15px !important; 
            display: flex !important; 
            align-items: center !important; 
            justify-content: center !important; 
            gap: 10px !important; 
            cursor: pointer !important; 
            transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }
        .btn-save { 
            background: linear-gradient(135deg, #6366f1, #4f46e5) !important; 
            color: white !important; 
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35) !important;
        }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 12px 25px rgba(99, 102, 241, 0.45) !important; }
        .btn-save:active { transform: translateY(0); }

        .btn-delete { 
            background: #ef4444 !important; 
            color: white !important; 
            border: none !important;
            box-shadow: 0 8px 15px rgba(239, 68, 68, 0.2) !important;
        }
        .btn-delete:hover { background: #dc2626 !important; transform: translateY(-3px); box-shadow: 0 12px 20px rgba(239, 68, 68, 0.3) !important; color: white !important; }
        .btn-delete:active { transform: translateY(0); }
        .btn i { font-size: 18px !important; }
        
        /* Influencer Styles */
        .inf-stats { display: flex; gap: 10px; margin-top: 5px; font-size: 11px; font-weight: 700; color: var(--secondary); }
        .inf-vibe { font-size: 12px; font-style: italic; color: var(--text-sub); margin-top: 5px; }

        /* Stock Badge Cards */
        .stock-badge { flex: 1; padding: 12px 8px; border-radius: 14px; text-align: center; color: white; }
        .stock-badge-num { font-size: 28px; font-weight: 800; line-height: 1; }
        .stock-badge-label { font-size: 10px; font-weight: 700; margin-top: 4px; opacity: 0.9; }

        /* Kanban Styles for Mobile */
        .kanban-container { display: flex; flex-direction: column; gap: 20px; }
        .kanban-col { background: #f1f5f9; border-radius: 20px; padding: 15px; min-height: 100px; }
        .kanban-col-title { font-size: 14px; font-weight: 800; margin-bottom: 15px; color: var(--primary); text-transform: uppercase; display: flex; align-items: center; gap: 8px; }
        .kanban-card { background: white; border-radius: 15px; padding: 15px; margin-bottom: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-left: 5px solid var(--primary); }
        .kanban-card-title { font-size: 15px; font-weight: 800; margin-bottom: 5px; }
        .kanban-card-detail { font-size: 12px; color: var(--text-sub); margin-bottom: 10px; }
        .kanban-actions { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f8fafc; padding-top: 10px; }
        
        .priority-red { border-left-color: #ef4444; }
        .priority-yellow { border-left-color: #f59e0b; }
        .priority-green { border-left-color: #10b981; }

        /* Client List Styles */
        .client-card { display: flex; align-items: center; gap: 15px; background: white; padding: 15px; border-radius: 18px; margin-bottom: 10px; border: 1px solid #f1f5f9; }
        .client-icon { width: 50px; height: 50px; background: var(--primary); color: white; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .client-info { flex: 1; }
        .client-name { font-weight: 800; font-size: 16px; margin-bottom: 2px; }
        .client-sub { font-size: 12px; color: var(--text-sub); }
        
        /* Colaboraciones & Encargos */
        .assign-section { margin-top: 20px; border-top: 2px solid #f1f5f9; padding-top: 15px; }
        .assign-section-title { font-size: 14px; font-weight: 800; color: var(--primary); text-transform: uppercase; display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .assign-item { background: #f8fafc; border-radius: 14px; padding: 12px 15px; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; border: 1px solid #e2e8f0; }
        .assign-item-info { flex: 1; }
        .assign-item-title { font-weight: 800; font-size: 14px; color: var(--text-main); }
        .assign-item-sub { font-size: 11px; color: var(--text-sub); margin-top: 2px; }
        .assign-item-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; white-space: nowrap; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-process { background: #dbeafe; color: #1e40af; }
        .badge-done { background: #d1fae5; color: #065f46; }
        .badge-sent { background: #ede9fe; color: #5b21b6; }
        .btn-assign { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border: none; border-radius: 12px; padding: 12px 16px; font-weight: 800; font-size: 13px; display: flex; align-items: center; gap: 8px; cursor: pointer; width: 100%; justify-content: center; margin-top: 8px; transition: 0.3s; }
        .btn-assign:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-assign-del { background: none; border: none; color: #ef4444; font-size: 16px; cursor: pointer; padding: 5px; transition: 0.2s; }
        .btn-assign-del:hover { transform: scale(1.2); }

        /* Mini-modal de búsqueda para asignar */
        .assign-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 3000; display: none; align-items: flex-end; justify-content: center; }
        .assign-modal-overlay.show { display: flex; }
        .assign-modal { background: white; border-radius: 24px 24px 0 0; width: 100%; max-width: 600px; max-height: 80vh; overflow-y: auto; padding: 0 0 30px; }
        .assign-modal-header { padding: 20px 20px 15px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; z-index: 1; }
        .assign-modal-body { padding: 20px; }
        .assign-search-res { max-height: 200px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px; margin-top: 8px; }
        .assign-search-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: 0.2s; display: flex; align-items: center; gap: 10px; }
        .assign-search-item:hover { background: #eef2ff; }
        .assign-search-item:last-child { border-bottom: none; }
        .assign-search-item img { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; background: #f1f5f9; }
        .assign-selected-preview { background: linear-gradient(135deg, #f1f5f9, #e0e7ff); border-radius: 14px; padding: 12px 15px; margin: 12px 0; display: none; align-items: center; gap: 12px; border: 1px solid #c7d2fe; }
        .assign-selected-preview.show { display: flex; }

        /* Voice Assistant Floating Button & UI */
        .voice-fab { position: fixed; bottom: 30px; left: 30px; width: 65px; height: 65px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4); border: none; cursor: pointer; z-index: 1000; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .voice-fab:hover { transform: scale(1.1) rotate(5deg); box-shadow: 0 15px 35px rgba(239, 68, 68, 0.5); }
        .voice-fab.active { background: #10b981; animation: pulse-mic 1.5s infinite; }
        @keyframes pulse-mic { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
        
        .mic-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 5000; display: none; flex-direction: column; align-items: center; justify-content: center; color: white; text-align: center; padding: 20px; transition: 0.3s; }
        .mic-overlay.show { display: flex; }
        .mic-wave { width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 30px; position: relative; }
        .mic-wave i { font-size: 40px; color: white; position: relative; z-index: 2; }
        .mic-wave::before, .mic-wave::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 2px solid white; border-radius: 50%; animation: ripple 1.5s infinite; opacity: 0; }
        .mic-wave::after { animation-delay: 0.5s; }
        @keyframes ripple { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(2.5); opacity: 0; } }
        
        .transcript-box { font-size: 22px; font-weight: 600; max-width: 80%; margin-top: 20px; min-height: 1.5em; line-height: 1.4; color: #cbd5e1; }
        .transcript-box b { color: white; }

        /* Tareas Styles */
        .task-card { background: white; border-radius: 18px; padding: 18px; margin-bottom: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 6px solid var(--text-sub); display: flex; align-items: center; gap: 15px; position: relative; overflow: hidden; }
        .task-card.completed { opacity: 0.6; border-left-color: var(--accent); }
        .task-card.completed .task-text { text-decoration: line-through; }
        .task-card.priority-alta { border-left-color: var(--danger); }
        .task-card.priority-media { border-left-color: var(--primary); }
        .task-card.priority-baja { border-left-color: var(--text-sub); }
        .task-check { width: 28px; height: 28px; border-radius: 50%; border: 2px solid #e2e8f0; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; flex-shrink: 0; }
        .task-check i { font-size: 14px; opacity: 0; transition: 0.3s; }
        .task-card.completed .task-check { background: var(--accent); border-color: var(--accent); }
        .task-card.completed .task-check i { opacity: 1; color: white; }
        .task-text { flex: 1; font-weight: 700; font-size: 15px; }
        .task-meta { font-size: 11px; color: var(--text-sub); font-weight: 600; margin-top: 4px; }
        .btn-task-del { color: #f1f5f9; position: absolute; right: 10px; top: 10px; padding: 5px; font-size: 14px; cursor: pointer; }
        .task-card:hover .btn-task-del { color: #cbd5e1; }
        .task-card:hover .btn-task-del { color: #cbd5e1; }

        /* Compras Styles */
        .purchase-card { background: white; border-radius: 18px; padding: 18px; margin-bottom: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border: 1px solid #f1f5f9; }
        .purchase-card.bought { opacity: 0.6; }
        .purchase-card.bought .purchase-text { text-decoration: line-through; color: var(--text-sub); }
        .purchase-check { width: 30px; height: 30px; border-radius: 10px; border: 2px solid var(--primary); display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
        .purchase-card.bought .purchase-check { background: var(--primary); }
        .purchase-check i { color: white; display: none; }
        .purchase-card.bought .purchase-check i { display: block; }
        .purchase-info { flex: 1; }
        .purchase-text { font-weight: 800; font-size: 16px; }
        .purchase-qty { font-size: 12px; color: var(--primary); font-weight: 700; background: #eef2ff; padding: 2px 8px; border-radius: 8px; }
    </style>


</head>
<body>
    <div class="header">
        <h1>NOXERTEZ ⚒️</h1>
        <div id="conn-status"><i class="fas fa-circle" style="color:var(--accent); font-size:10px;"></i></div>
    </div>

    <div class="container">
        <div class="tabs">
            <div id="tab-prod" class="tab active" onclick="switchTab('prod')">📦 ARTÍCULOS</div>
            <div id="tab-futuro" class="tab" onclick="switchTab('futuro')">🚀 FUTUROS</div>
            <div id="tab-mat" class="tab" onclick="switchTab('mat')">🪵 TALLER</div>
            <div id="tab-despiece" class="tab" onclick="switchTab('despiece')">⚒️ PRODUCCIÓN</div>
            <div id="tab-ventas" class="tab" onclick="switchTab('ventas')">💰 VENTAS</div>
            <div id="tab-influencers" class="tab" onclick="switchTab('influencers')">👥 INF.</div>
            <div id="tab-clientes" class="tab" onclick="switchTab('clientes')">👨‍👩‍👧‍👦 CLIENTES</div>
            <div id="tab-pedidos" class="tab" onclick="switchTab('pedidos')">📋 PEDIDOS</div>
            <div id="tab-envios" class="tab" onclick="switchTab('envios')">📦 ENVÍOS</div>
            <div id="tab-compras" class="tab" onclick="switchTab('compras')">🛒 COMPRAS</div>
            <div id="tab-tareas" class="tab" onclick="switchTab('tareas')">📝 NOTAS</div>
            <div id="tab-centro" class="tab" onclick="switchTab('centro')">⚙️ CENTRO</div>
        </div>

        <div class="search-area" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <div style="flex: 2; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 15px; top: 18px; color: var(--text-sub);"></i>
                <input type="text" id="search" class="search-input" placeholder="Buscar..." onkeyup="handleSearch()" style="padding-left: 45px;">
            </div>
            <div style="flex: 1;">
                <select id="category-filter" onchange="handleSearch()" style="height: 54px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 14px; color: var(--text-main); cursor: pointer;">
                    <option value="">Todas las Categorías</option>
                </select>
            </div>
        </div>

        <div id="loader" class="loader"></div>
        <div id="results" class="grid"></div>
        <div id="centro-panel" style="display:none;"></div>
    </div>

    <!-- MAIN FAB (Dual Action: Scanner if Futuros, New if Products) -->
    <button class="fab" id="main-fab" onclick="handleFab()">
        <i class="fas fa-plus"></i> <span id="fab-text">Nuevo Producto</span>
    </button>

    <!-- Detailed Management Sheet -->
    <div id="sheet-overlay" class="sheet-overlay" onclick="closeSheet()"></div>
    <div id="detail-sheet" class="detail-sheet">
        <div class="sheet-header">
            <h2 id="sheet-header-title" style="margin:0">Gestión de Artículo</h2>
            <button onclick="closeSheet()" style="background:none; border:none; font-size:24px; color:var(--text-sub); cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        
        <div class="sheet-body">
            <div id="image-container" style="position:relative; margin-bottom:20px;">
                <img id="edit-img" src="" style="width:100%; height:280px; object-fit:cover; border-radius:20px; background:#f1f5f9;">
                <div style="position:absolute; bottom:15px; right:15px; background:white; padding:10px; border-radius:30px; box-shadow:0 4px 10px rgba(0,0,0,0.1); font-size:12px; font-weight:700; cursor:pointer;" onclick="document.getElementById('camera-input').click()">
                    <i class="fas fa-image"></i> Cambiar Foto
                </div>
            </div>
                <form id="edit-form">
                    <input type="hidden" id="edit-sku-orig">
                    <input type="hidden" id="edit-id-orig">
                    <input type="hidden" id="edit-foto-path">

                    <div class="general-field for-prod for-futuro for-mat">
                        <label>NOMBRE DEL ARTÍCULO / MATERIAL</label>
                        <input type="text" id="edit-nombre" placeholder="Nombre visible...">
                    </div>

                    <div class="form-row">
                        <div class="general-field for-prod for-futuro for-mat">
                            <label>SKU / REFERENCIA</label>
                            <input type="text" id="edit-sku" placeholder="Ej: CAT-SUB-001">
                        </div>
                        <div class="price-field for-prod for-futuro">
                            <label>PRECIO (€)</label>
                            <input type="number" id="edit-precio" step="0.01">
                        </div>
                    </div>

                    <div class="form-row for-prod for-futuro for-mat">
                        <div>
                            <label>CATEGORÍA</label>
                            <input type="text" id="edit-cat">
                        </div>
                        <div>
                            <label>SUBCATEGORÍA</label>
                            <input type="text" id="edit-subcat">
                        </div>
                    </div>

                    <div class="form-row for-prod for-futuro for-mat">
                        <div>
                            <label>MARCA</label>
                            <input type="text" id="edit-marca">
                        </div>
                        <div>
                            <label>COLOR</label>
                            <input type="text" id="edit-color">
                        </div>
                    </div>

                    <div class="form-row for-prod for-futuro for-mat">
                        <div>
                            <label>DIMENSIONES</label>
                            <input type="text" id="edit-dim">
                        </div>
                        <div>
                            <label>FESTIVIDAD</label>
                            <input type="text" id="edit-fest">
                        </div>
                    </div>

                    <div class="general-field for-prod for-futuro for-inf">
                        <label id="label-desc">DESCRIPCIÓN / NOTAS</label>
                        <textarea id="edit-desc" rows="3" style="resize:none;"></textarea>
                    </div>

                    <div id="future-fields" class="for-futuro" style="display:none;">
                        <label>ESTADO / PENDIENTE</label>
                        <select id="edit-estado">
                            <option value="Pendiente">Pendiente</option>
                            <option value="Urgente">Urgente</option>
                            <option value="En Proceso">En Proceso</option>
                            <option value="Terminado">Terminado</option>
                        </select>
                    </div>

                    <div class="form-row for-prod for-futuro" id="realizadas-row" style="display:none;">
                        <div>
                            <label>UNIDADES REALIZADAS</label>
                            <input type="number" id="edit-realizadas" placeholder="0">
                        </div>
                    </div>

                    <div id="stock-section" class="form-row for-prod for-mat" style="display:none;">
                        <div>
                            <label>STOCK ACTUAL</label>
                            <input type="number" id="edit-stock">
                        </div>
                        <div id="unit-field" class="for-mat" style="display:none;">
                            <label>UNIDAD</label>
                            <input type="text" id="edit-unit">
                        </div>
                        <div id="punto-pedido-field" class="for-mat" style="display:none;">
                            <label>PUNTO PEDIDO</label>
                            <input type="number" id="edit-pp">
                        </div>
                    </div>

                    <div id="sales-section" class="for-sales" style="display:none;">
                        <!-- STOCK SUMMARY PANEL -->
                        <div id="stock-summary" style="display:flex; gap:8px; margin-bottom:20px;">
                            <div class="stock-badge" style="background:#4f46e5;">
                                <div class="stock-badge-num" id="sb-fab">--</div>
                                <div class="stock-badge-label">🏭 Fabricables</div>
                            </div>
                            <div class="stock-badge" style="background:#059669;">
                                <div class="stock-badge-num" id="sb-fisico">--</div>
                                <div class="stock-badge-label">📦 Terminados</div>
                            </div>
                            <div class="stock-badge" style="background:#b45309;">
                                <div class="stock-badge-num" id="sb-realizadas">--</div>
                                <div class="stock-badge-label">🔢 Realizadas</div>
                            </div>
                        </div>
                        <!-- Editable: Stock Fisico -->
                        <label style="color:var(--accent); font-weight:bold;">📦 EDITAR STOCK FÍSICO (Terminados)</label>
                        <input type="number" id="edit-stock-fisico" placeholder="0" min="0" style="margin-bottom:15px;">
                        
                        <div class="section-title"><i class="fas fa-shopping-cart"></i> PLATAFORMAS DE VENTA</div>
                        <div id="plat-list" class="sales-platforms-list">
                            <!-- Contenido dinámico -->
                        </div>
                        
                        <button type="button" class="btn" style="margin-top:20px; background:#f1f5f9; color:var(--primary); width:100%; border:1px dashed var(--primary);" onclick="addNewPlatformUI()">
                            <i class="fas fa-plus-circle"></i> Nueva Plataforma
                        </button>
                        
                        <div class="section-title"><i class="fas fa-boxes"></i> UNIDADES TOTALES</div>
                        <input type="number" id="venta-unidades" placeholder="Unidades para la venta...">
                    </div>


                    <!-- SECCIÓN DE DESPIECE -->
                    <div id="despiece-section" class="for-despiece" style="display:none;">
                        <!-- STOCK SUMMARY (also in despiece tab) -->
                        <div id="stock-summary-despiece" style="display:flex; gap:8px; margin-bottom:20px;">
                            <div class="stock-badge" style="background:#4f46e5;">
                                <div class="stock-badge-num" id="sb-fab2">--</div>
                                <div class="stock-badge-label">🏭 Fabricables</div>
                            </div>
                            <div class="stock-badge" style="background:#059669;">
                                <div class="stock-badge-num" id="sb-fisico2">--</div>
                                <div class="stock-badge-label">📦 Terminados</div>
                            </div>
                            <div class="stock-badge" style="background:#b45309;">
                                <div class="stock-badge-num" id="sb-realizadas2">--</div>
                                <div class="stock-badge-label">🔢 Realizadas</div>
                            </div>
                        </div>
                        <div class="section-title"><i class="fas fa-list-ul"></i> RECETA DE PRODUCCIÓN</div>
                        <div id="despiece-container">
                            <div id="despiece-list" style="display:flex; flex-direction:column; gap:10px;">
                                <p style="font-size:13px; color:var(--text-sub); font-style:italic;">Cargando componentes...</p>

                            </div>
                        </div>
                    </div>
                    <!-- SECCIÓN DE INFLUENCERS -->
                    <div id="influencer-section" class="for-inf" style="display:none;">
                        <input type="hidden" id="inf-id">
                        <div class="form-row">
                            <div>
                                <label>NOMBRE INFLUENCER</label>
                                <input type="text" id="inf-nombre">
                            </div>
                            <div>
                                <label>USUARIO IG (@)</label>
                                <input type="text" id="inf-usuario">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>NICHO / ESTILO</label>
                                <input type="text" id="inf-nicho">
                            </div>
                            <div>
                                <label>SEGUIDORES</label>
                                <input type="number" id="inf-seguidores">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>VIBE DECORATIVO</label>
                                <input type="text" id="inf-vibe">
                            </div>
                            <div>
                                <label>LIKES PROMEDIO</label>
                                <input type="number" id="inf-likes">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>TELÉFONO</label>
                                <input type="text" id="inf-tel">
                            </div>
                            <div>
                                <label>EMAIL</label>
                                <input type="text" id="inf-email">
                            </div>
                        </div>

                        <!-- COLABORACIONES ASIGNADAS -->
                        <div class="assign-section">
                            <div class="assign-section-title">
                                <i class="fas fa-box-open"></i> Artículos / Pedidos Asignados
                                <span id="inf-colabs-count" style="background:var(--secondary);color:white;border-radius:20px;padding:2px 8px;font-size:10px;margin-left:auto;">0</span>
                            </div>
                            <div id="inf-colabs-list">
                                <p style="font-size:12px;color:var(--text-sub);font-style:italic;">Cargando...</p>
                            </div>
                            <button class="btn-assign" onclick="openAssignModal('influencer')">
                                <i class="fas fa-plus-circle"></i> Asignar Artículo
                            </button>
                        </div>
                    </div>


                    <!-- SECCIÓN DE CLIENTES (NUEVO) -->
                    <div id="client-section" style="display:none;">
                        <input type="hidden" id="client-id">
                        <div class="form-row">
                            <div>
                                <label>NOMBRE COMPLETO</label>
                                <input type="text" id="client-nombre">
                            </div>
                            <div>
                                <label>TELÉFONO</label>
                                <input type="text" id="client-tel">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>EMAIL</label>
                                <input type="text" id="client-email">
                            </div>
                            <div>
                                <label>INSTAGRAM</label>
                                <input type="text" id="client-ig">
                            </div>
                        </div>
                        <label>DIRECCIÓN COMPLETA</label>
                        <input type="text" id="client-dir">
                        <div class="form-row">
                            <div>
                                <label>CIUDAD</label>
                                <input type="text" id="client-ciudad">
                            </div>
                            <div>
                                <label>CÓDIGO POSTAL</label>
                                <input type="text" id="client-cp">
                            </div>
                        </div>
                        <label>NOTAS DEL CLIENTE</label>
                        <textarea id="client-notas" rows="2"></textarea>

                        <!-- ENCARGOS ASIGNADOS -->
                        <div class="assign-section">
                            <div class="assign-section-title">
                                <i class="fas fa-shopping-bag"></i> Artículos Encargados / Por Hacer
                                <span id="client-encargos-count" style="background:var(--accent);color:white;border-radius:20px;padding:2px 8px;font-size:10px;margin-left:auto;">0</span>
                            </div>
                            <div id="client-encargos-list">
                                <p style="font-size:12px;color:var(--text-sub);font-style:italic;">Cargando...</p>
                            </div>
                            <button class="btn-assign" onclick="openAssignModal('cliente')">
                                <i class="fas fa-plus-circle"></i> Asignar Artículo
                            </button>
                        </div>
                    </div>

                    <!-- SECCIÓN DE PEDIDOS (NUEVO) -->
                    <div id="pedido-section" style="display:none;">
                        <input type="hidden" id="pedido-id">
                        <label>CLIENTE</label>
                        <select id="pedido-cliente-id"></select>
                        
                        <div class="form-row">
                            <div>
                                <label>PRIORIDAD</label>
                                <select id="pedido-prioridad">
                                    <option value="Verde">Baja (Verde)</option>
                                    <option value="Amarillo">Media (Amarillo)</option>
                                    <option value="Rojo">Alta (Rojo)</option>
                                </select>
                            </div>
                            <div>
                                <label>ESTADO MÓVIL</label>
                                <select id="pedido-estado-kanban">
                                    <option value="Por empezar">Por empezar</option>
                                    <option value="En proceso">En proceso</option>
                                    <option value="Secado/Horno">Secado/Horno</option>
                                    <option value="Acabado/Barniz">Acabado/Barniz</option>
                                    <option value="Listo para entrega">Listo para entrega</option>
                                </select>
                            </div>
                        </div>
                        
                        <label>DETALLES CRÍTICOS (PERSONALIZACIÓN)</label>
                        <input type="text" id="pedido-detalles" style="font-weight:bold; color:var(--danger);">
                    </div>
                </form>
            </div> <!-- End sheet-body -->

        <div class="actions-bar">
            <button id="btn-delete" class="btn btn-delete" onclick="deleteItem()"><i class="fas fa-trash"></i> Borrar</button>
            <button class="btn btn-save" onclick="saveItem()"><i class="fas fa-save"></i> Guardar Todo</button>
        </div>
    </div> <!-- End detail-sheet -->
    </div>

    <!-- Hidden Camera/Gallery Input -->
    <input type="file" id="camera-input" accept="image/*" style="display:none" onchange="uploadImage(this)">

    <div id="toast" class="toast">¡Operación completada!</div>

    <!-- Fullscreen Image Viewer -->
    <div id="fs-modal" class="fs-modal" onclick="closeFullscreen()">
        <span class="fs-close">&times;</span>
        <img id="fs-img" class="fs-img" src="">
    </div>

    <!-- Material Selector Modal (Centered Modal Style) -->
    <div id="mat-modal" class="sheet-overlay mat-modal-overlay" style="z-index:2000;" onclick="closeMatModal()">
        <div style="width:100%; max-width:400px; max-height:90vh; overflow-y:auto; padding:0; border-radius:24px; box-shadow:0 20px 50px rgba(0,0,0,0.3); background:white; border:1px solid #e2e8f0;" onclick="event.stopPropagation()">
            <div class="sheet-header" style="border-radius:24px 24px 0 0; padding:20px;">
                <h3 style="margin:0; font-size:18px;">Vincular Material</h3>
                <button onclick="closeMatModal()" style="background:none; border:none; font-size:24px; color:var(--text-sub); cursor:pointer;">&times;</button>
            </div>
            <div style="padding:20px;">
                <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                    <div style="flex: 2; position: relative;">
                        <input type="text" id="mat-search-input" placeholder="Buscar material..." oninput="handleMatSearch()" style="background:#f8fafc; padding-left: 12px;">
                    </div>
                    <div style="flex: 1;">
                        <select id="mat-category-filter" onchange="handleMatSearch()" style="height: 52px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 13px; font-weight: 600; width: 100%;">
                            <option value="">Categorías</option>
                        </select>
                    </div>
                </div>
                
                <!-- Results list: NORMAL FLOW, not absolute -->
                <div id="mat-results-list" style="background:white; border:1px solid #e2e8f0; border-radius:12px; max-height:200px; overflow-y:auto; display:none; margin-top:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
                
                <input type="hidden" id="mat-selected-ref">
                <div id="mat-selected-preview" style="margin-top:15px; margin-bottom:15px; background:linear-gradient(135deg, #f1f5f9, #e2e8f0); padding:15px; border-radius:16px; display:none; align-items:center; gap:12px; border:1px solid #cbd5e1;">
                    <img id="mat-preview-img" src="" style="width:45px; height:45px; border-radius:10px; object-fit:cover; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                    <div style="display:flex; flex-direction:column;">
                        <span id="mat-preview-name" style="font-size:14px; font-weight:800; color:var(--text-main);"></span>
                        <span style="font-size:11px; color:var(--text-sub); font-weight:600;">Material Seleccionado</span>
                    </div>
                </div>

                <div style="background:#f8fafc; padding:15px; border-radius:16px; border:1px solid #e2e8f0;">
                    <label style="margin-top:0; text-align:center;">CANTIDAD NECESARIA</label>
                    <input type="number" id="mat-qty-input" value="1" step="0.1" style="font-size:24px; font-weight:800; text-align:center; border:none; background:transparent; color:var(--primary);">
                </div>
                
                <button id="btn-confirm-link" class="btn btn-save" style="margin-top:20px; width:100%; padding:18px; border-radius:16px; font-size:16px; box-shadow:0 4px 12px rgba(99, 102, 241, 0.3); opacity: 0.5; pointer-events: none;" onclick="confirmAddMaterial()">
                    <i class="fas fa-link" style="margin-right:8px;"></i>VINCULAR AHORA
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DE ASIGNACIÓN DE ARTÍCULOS (Influencers / Clientes) -->
    <div id="assign-modal-overlay" class="assign-modal-overlay" onclick="closeAssignModal()">
        <div class="assign-modal" onclick="event.stopPropagation()">
            <div class="assign-modal-header">
                <h3 id="assign-modal-title" style="margin:0; font-size:18px; font-weight:800;">Asignar Artículo</h3>
                <button onclick="closeAssignModal()" style="background:none; border:none; font-size:24px; color:var(--text-sub); cursor:pointer;">&times;</button>
            </div>
            <div class="assign-modal-body">
                <!-- Buscador de artículos -->
                <label style="margin-top:0;">BUSCAR ARTÍCULO DEL CATÁLOGO</label>
                <input type="text" id="assign-search-input" placeholder="Nombre o SKU..." oninput="handleAssignSearch()" autocomplete="off">
                <div id="assign-search-results" class="assign-search-res" style="display:none;"></div>

                <!-- Preview del artículo seleccionado -->
                <div id="assign-selected-preview" class="assign-selected-preview">
                    <img id="assign-preview-img" src="" style="width:45px;height:45px;object-fit:cover;border-radius:10px;background:#f1f5f9;">
                    <div>
                        <div id="assign-preview-name" style="font-weight:800; font-size:14px;"></div>
                        <div id="assign-preview-sku" style="font-size:11px; color:var(--text-sub);"></div>
                    </div>
                </div>
                <input type="hidden" id="assign-selected-sku">

                <!-- Estado (diferente para influencer vs cliente) -->
                <div id="assign-estado-inf" style="display:none;">
                    <label>ESTADO DE COLABORACIÓN</label>
                    <select id="assign-estado-colab">
                        <option value="Propuesta enviada">Propuesta enviada</option>
                        <option value="Aceptado">Aceptado</option>
                        <option value="Pieza en fabricación">Pieza en fabricación</option>
                        <option value="Enviado">Enviado</option>
                        <option value="Publicación pendiente">Publicación pendiente</option>
                        <option value="Completado">Completado</option>
                    </select>
                    <label>COMISIÓN (%)</label>
                    <input type="number" id="assign-comision" value="10" min="0" max="100">
                </div>

                <div id="assign-estado-cli" style="display:none;">
                    <label>ESTADO DEL ENCARGO</label>
                    <select id="assign-estado-encargo">
                        <option value="Pendiente">Pendiente</option>
                        <option value="En proceso">En proceso</option>
                        <option value="Listo">Listo</option>
                    </select>
                    <label>CANTIDAD</label>
                    <input type="number" id="assign-cantidad" value="1" min="1">
                </div>

                <label>NOTAS</label>
                <textarea id="assign-notas" rows="2" style="resize:none;" placeholder="Notas adicionales..."></textarea>

                <button id="btn-confirm-assign" class="btn btn-save" style="margin-top:15px; width:100%; padding:18px; border-radius:16px; font-size:16px; opacity:0.5; pointer-events:none;" onclick="confirmAssign()">
                    <i class="fas fa-link"></i> CONFIRMAR ASIGNACIÓN
                </button>
            </div>
        </div>
    </div>

    <script>

        let currentTab = 'prod';
        let debounceTimer;
        let matSearchAbortController = null;
        let currentItem = null;
        let isSelectingMat = false;
        
        // GLOBAL SELECTION STATE (Crucial for persistence)
        let selectedMatSkuGlobal = ""; 
        let selectedMatNameGlobal = "";

        function switchTab(tab) {
            console.log("[DEBUG] Switching to tab:", tab);
            currentTab = tab;
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            const tabEl = document.getElementById('tab-' + tab);
            if (tabEl) tabEl.classList.add('active');
            else console.error("[ERROR] Tab element not found: tab-" + tab);

            const searchArea = document.querySelector('.search-area');
            const results = document.getElementById('results');
            const centroPanel = document.getElementById('centro-panel');
            
            if (tab === 'tareas' || tab === 'centro') {
                searchArea.style.display = 'none';
            } else {
                searchArea.style.display = 'flex';
            }

            if (tab === 'centro') {
                results.style.display = 'none';
                centroPanel.style.display = 'block';
                fetchCentroStatus();
            } else {
                results.style.display = 'grid';
                centroPanel.style.display = 'none';
            }
            
            const fabText = document.getElementById('fab-text');
            const fabIcon = document.querySelector('#main-fab i');
            const fab = document.getElementById('main-fab');
            
            if (tab === 'prod') {
                fabText.innerText = 'Nuevo Producto';
                fabIcon.className = 'fas fa-plus';
                fab.style.display = 'flex';
            } else if (tab === 'futuro') {
                fabText.innerText = 'Escanear Inspiración';
                fabIcon.className = 'fas fa-camera';
                fab.style.display = 'flex';
            } else if (tab === 'mat') {
                fabText.innerText = 'Nuevo Material';
                fabIcon.className = 'fas fa-plus';
                fab.style.display = 'flex';
            } else if (tab === 'ventas') {
                fabText.innerText = 'Actualizar Catálogo';
                fabIcon.className = 'fas fa-sync';
                fab.style.display = 'flex';
            } else if (tab === 'influencers') {
                fabText.innerText = 'Añadir Influencer';
                fabIcon.className = 'fas fa-user-plus';
                fab.style.display = 'flex';
            } else if (tab === 'tareas') {
                fabText.innerText = 'Nueva Nota';
                fabIcon.className = 'fas fa-pencil-alt';
                fab.style.display = 'flex';
            } else if (tab === 'compras') {
                fabText.innerText = 'Pedir Material';
                fabIcon.className = 'fas fa-cart-plus';
                fab.style.display = 'flex';
            } else {
                fab.style.display = 'none'; 
            }

            document.body.setAttribute('data-tab', tab);
            updateCategories();
            handleSearch();
        }

        async function fetchCentroStatus() {
            const panel = document.getElementById('centro-panel');
            panel.innerHTML = `
                <div style="background: white; border-radius: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; margin-top: 20px;">
                    <h2 style="margin-top:0; font-weight:800; color:var(--primary); display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-microchip"></i> Centro de Operaciones
                    </h2>
                    <p style="color:var(--text-sub); font-size:14px; margin-bottom:25px;">Gestiona los servicios del sistema y la automatización.</p>
                    
                    <div style="background:#f8fafc; border-radius:15px; padding:20px; border:1px solid #e2e8f0; margin-bottom:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <h3 style="margin:0; font-size:18px; font-weight:800; color:var(--text-main);">Robot n8n</h3>
                                <p style="margin:5px 0 0; font-size:12px; color:var(--text-sub);">Servidor de automatización y flujos de trabajo.</p>
                            </div>
                            <div id="n8n-web-status" class="badge" style="position:static; padding:8px 15px; background:#f1f5f9; color:var(--text-sub); border:none;">
                                comprobando...
                            </div>
                        </div>
                        
                        <div style="margin-top:20px; display:flex; gap:10px;">
                            <button onclick="restartN8NWeb()" style="flex:1; background:linear-gradient(135deg, var(--primary), var(--secondary)); color:white; border:none; padding:15px; border-radius:12px; font-weight:800; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; transition:0.3s;">
                                <i class="fas fa-sync-alt"></i> REINICIAR N8N
                            </button>
                            <button onclick="window.open('http://' + window.location.hostname + ':5678', '_blank')" style="background:white; color:var(--text-main); border:1px solid #e2e8f0; padding:15px; border-radius:12px; font-weight:800; cursor:pointer;">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </div>
                    </div>

                    <div style="background:#f1f5f9; border-radius:15px; padding:15px; text-align:center; color:var(--text-sub); font-size:11px; font-weight:600;">
                        <i class="fas fa-info-circle"></i> Usa el reinicio si los flujos automáticos dejan de responder.
                    </div>
                </div>
            `;
            
            // Comprobar estado real (ping)
            try {
                const r = await fetch('/api/n8n/status');
                const d = await r.json();
                const badge = document.getElementById('n8n-web-status');
                if (d.online) {
                    badge.innerText = 'EN LÍNEA';
                    badge.style.background = 'var(--accent)';
                    badge.style.color = 'white';
                } else {
                    badge.innerText = 'OFFLINE';
                    badge.style.background = 'var(--danger)';
                    badge.style.color = 'white';
                }
            } catch(e) {
                document.getElementById('n8n-web-status').innerText = 'ERROR';
            }
        }

        async function restartN8NWeb() {
            if (!confirm("¿Seguro que quieres reiniciar n8n? Los flujos en ejecución se detendrán.")) return;
            showToast("🔄 Reiniciando n8n...");
            try {
                const r = await fetch('/api/n8n/restart', { method: 'POST' });
                const d = await r.json();
                if (d.success) {
                    showToast("✅ Reinicio enviado correctamente");
                    setTimeout(fetchCentroStatus, 5000); // Esperar a que arranque
                } else {
                    showToast("❌ Error: " + d.error);
                }
            } catch(e) {
                showToast("❌ Error de conexión");
            }
        }

        async function updateCategories() {
            const select = document.getElementById('category-filter');
            select.innerHTML = '<option value="">Cargando...</option>';
            
            try {
                const r = await fetch(`/api/categorias?tab=${currentTab}`);
                const cats = await r.json();
                
                let html = '<option value="">Todas las Categorías</option>';
                cats.forEach(c => {
                    if (c) html += `<option value="${c}">${c}</option>`;
                });
                select.innerHTML = html;
            } catch (e) {
                select.innerHTML = '<option value="">Error categories</option>';
            }
        }

        function handleFab() {
            if (currentTab === 'futuro') {
                const choice = confirm("¿Subir foto (Aceptar) o Crear manual (Cancelar)?");
                if (choice) document.getElementById('camera-input').click();
                else showNewItemForm();
            } else {
                showNewItemForm();
            }
        }

        function showNewItemForm() {
            currentItem = null;
            document.getElementById('edit-form').reset();
            document.getElementById('edit-sku').readOnly = false;
            document.getElementById('edit-sku').style.background = 'white';
            document.getElementById('edit-sku-orig').value = '';
            document.getElementById('edit-id-orig').value = '';
            document.getElementById('edit-img').src = '';

            // 1. Set Title
            let title = "Nuevo ";
            if (currentTab === 'influencers') title = "Nuevo Influencer";
            else if (currentTab === 'mat') title = "Nuevo Material";
            else if (currentTab === 'futuro') title = "Nuevo Proyecto";
            else if (currentTab === 'compras') title = "Añadir a Lista de Compras";
            else title = "Nuevo Producto";
            document.getElementById('sheet-header-title').innerText = title;

            // 2. Visibility Logic (Same as editItem)
            const allFields = ['for-prod', 'for-futuro', 'for-mat', 'for-inf', 'for-sales', 'for-despiece'];
            allFields.forEach(cls => {
                document.querySelectorAll('.' + cls).forEach(el => el.style.display = 'none');
            });

            if (currentTab === 'prod') {
                document.querySelectorAll('.for-prod').forEach(el => el.style.display = '');
            } else if (currentTab === 'futuro') {
                document.querySelectorAll('.for-futuro').forEach(el => el.style.display = '');
            } else if (currentTab === 'mat') {
                document.querySelectorAll('.for-mat').forEach(el => el.style.display = '');
            } else if (currentTab === 'influencers') {
                document.querySelectorAll('.for-inf').forEach(el => el.style.display = '');
            } else if (currentTab === 'compras') {
                document.querySelectorAll('.for-compra').forEach(el => el.style.display = '');
            }
            
            document.body.setAttribute('data-tab', currentTab);
            document.getElementById('image-container').style.display = (currentTab === 'influencers' || currentTab === 'compras') ? 'none' : 'block';
            document.getElementById('detail-sheet').classList.add('show');
            document.getElementById('sheet-overlay').classList.add('show');
        }

        function handleSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchData, 300);
        }

        async function fetchData() {
            const query = document.getElementById('search').value;
            const loader = document.getElementById('loader');
            const results = document.getElementById('results');
            console.log("[DEBUG] fetchData - Current Tab:", currentTab, "Query:", query);
            
            // Si es la pestaña de producción, no buscar si no hay query
            if (currentTab === 'despiece' && !query.trim()) {
                loader.style.display = 'none';
                results.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px 0; color:var(--text-sub);"><i class="fas fa-search" style="font-size:40px; display:block; margin-bottom:15px;"></i>Busca un artículo para ver su producción</div>';
                return;
            }

            loader.style.display = 'block';
            results.innerHTML = '';

            try {
                let endpoint = '/api/buscar';
                if (currentTab === 'futuro') endpoint = '/api/futuros';
                if (currentTab === 'mat') endpoint = '/api/materiales';
                if (currentTab === 'despiece') endpoint = '/api/base_articles';
                if (currentTab === 'ventas') endpoint = '/api/ventas';
                if (currentTab === 'influencers') endpoint = '/api/influencers';
                if (currentTab === 'clientes') endpoint = '/api/clientes';
                if (currentTab === 'tareas') endpoint = '/api/tareas';
                if (currentTab === 'compras') endpoint = '/api/compras';
                if (currentTab === 'pedidos') {
                    results.classList.remove('grid');
                    results.innerHTML = '<div class="kanban-container" id="kanban-board"></div>';
                    loadKanban(query);
                    return;
                } else {
                    results.classList.add('grid');
                }
                if (currentTab === 'envios') endpoint = '/api/envios';

                const cat = document.getElementById('category-filter').value;
                const r = await fetch(`${endpoint}?q=${query}&cat=${encodeURIComponent(cat)}`);
                const data = await r.json();
                
                if (data.length === 0) {
                    results.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px 0; color:var(--text-sub);"><i class="fas fa-ghost" style="font-size:40px; display:block; margin-bottom:15px;"></i>No hay nada por aquí...</div>';
                } else {
                    data.forEach(item => {
                        if (currentTab === 'tareas') results.appendChild(createTaskCard(item));
                        else if (currentTab === 'compras') results.appendChild(createPurchaseCard(item));
                        else results.appendChild(createCard(item));
                    });
                }
            } catch (e) {
                console.error(e);
            } finally {
                loader.style.display = 'none';
            }
        }

        function createCard(item) {
            const div = document.createElement('div');
            div.className = 'card';
            
            let img = '', title = '', sub = '', footer = '', badge = '';
            
            if (currentTab === 'prod') {
                img = item.FOTO_PORTADA;
                title = item.NOMBRE;
                sub = `${item.CATEGORIA || 'Sin Cat.'} · ${item.MARCA || 'Genérico'}`;
                const waNumber = item.TELEFONO_WA || '';
                const waLink = `https://wa.me/${waNumber}?text=${encodeURIComponent("Hola, me interesa este artículo: " + title + " (Ref: " + item.SKU_REF + ")")}`;
                footer = `
                    <div>
                        <span class="price">${parseFloat(item.PRECIO || 0).toLocaleString('es-ES', {minimumFractionDigits: 2})}€</span><br>
                        <span style="font-size:11px; color:var(--text-sub); font-weight:600;">#${item.SKU_REF}</span>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <a href="${waLink}" target="_blank" class="icon-btn" style="color:#25d366" onclick="event.stopPropagation()"><i class="fab fa-whatsapp"></i></a>
                        <button class="icon-btn" onclick="editItem(${JSON.stringify(item).replace(/"/g, '&quot;')}); event.stopPropagation()"><i class="fas fa-edit"></i></button>
                    </div>`;
                badge = '<div class="badge badge-real">Catálogo</div>';
            } else if (currentTab === 'futuro') {
                img = item.FOTO_REFERENCIA;
                title = item.NOMBRE || 'Inspiración';
                sub = `Estado: ${item.ESTADO} | Realizadas: ${item.UNIDADES_REALIZADAS || 0}`;
                const estadoColor = item.ESTADO === 'Urgente' ? '#ef4444' : item.ESTADO === 'En Proceso' ? '#f59e0b' : '#6366f1';
                footer = `
                    <span style="font-size:11px; color:${estadoColor}; font-weight:800;">${item.ESTADO || 'Pendiente'}</span>
                    <div style="display:flex;gap:6px;">
                        <button class="icon-btn" title="Enviar a cola de producción"
                            onclick="enviarFuturoProduccion(${item.id}, '${(item.NOMBRE||'').replace(/'/g,'')}'); event.stopPropagation()"
                            style="background:linear-gradient(135deg,#f59e0b,#ef4444);color:white;">
                            <i class="fas fa-industry"></i>
                        </button>
                        <button class="icon-btn" onclick="editItem(${JSON.stringify(item).replace(/"/g, '&quot;')}); event.stopPropagation()"><i class="fas fa-magic"></i></button>
                    </div>`;
                badge = '<div class="badge badge-future">Idea/Prueba</div>';
            } else if (currentTab === 'mat') {
                img = item.FOTO;
                title = item.NOMBRE;
                sub = `${item.CATEGORIA || 'General'} · ${item.STOCK_ACTUAL} ${item.UNIDAD}`;
                footer = `
                    <span class="price">${item.STOCK_ACTUAL} </span><span style="font-size:12px;">${item.UNIDAD}</span>
                    <button class="icon-btn" onclick="editItem(${JSON.stringify(item).replace(/"/g, '&quot;')}); event.stopPropagation()"><i class="fas fa-plus-circle"></i></button>`;
                badge = '<div class="badge badge-sku" style="background:white; color:var(--primary); border:1px solid var(--primary); top:12px; left:12px; right:auto;">STOCK</div>';
            } else if (currentTab === 'despiece') {
                img = item.FOTO_PORTADA;
                title = item.NOMBRE;
                sub = `${item.SKU_REF} | Realizadas: ${item.UNIDADES_REALIZADAS || 0}`;
                footer = `
                    <span style="font-size:12px; color:var(--secondary); font-weight:700;">GESTIÓN PRODUCCIÓN</span>
                    <button class="icon-btn" onclick="editItem(${JSON.stringify(item).replace(/"/g, '&quot;')}); event.stopPropagation()"><i class="fas fa-hammer"></i></button>`;
                badge = '<div class="badge badge-real" style="background:var(--secondary)">Receta</div>';
            } else if (currentTab === 'ventas') {
                img = item.FOTO_PORTADA;
                title = item.NOMBRE;
                sub = `Stock Venta: ${item.UNIDADES_VENTA || 0}`;
                footer = `
                    <span class="price">${item.UNIDADES_VENTA || 0} Uds.</span>
                    <button class="icon-btn" onclick="editItem(${JSON.stringify(item).replace(/"/g, '&quot;')}); event.stopPropagation()"><i class="fas fa-dollar-sign"></i></button>`;
                badge = '<div class="badge badge-real" style="background:var(--accent)">Ventas</div>';
            } else if (currentTab === 'influencers') {
                img = ''; // No image for influencer list for now
                title = item.nombre;
                sub = `${item.nicho || 'General'} | @${item.usuario_ig}`;
                footer = `
                    <div class="inf-stats">
                        <span><i class="fas fa-users"></i> ${item.seguidores || 0}</span>
                        <span><i class="fas fa-heart"></i> ${item.likes_promedio || 0}</span>
                    </div>
                    <button class="icon-btn" onclick="editItem(${JSON.stringify(item).replace(/"/g, '&quot;')}); event.stopPropagation()"><i class="fas fa-user-edit"></i></button>`;
                badge = '<div class="badge" style="background:var(--secondary)">' + (item.vibe_estilo || 'Vibe') + '</div>';
            } else if (currentTab === 'clientes') {
                div.className = 'client-card';
                div.innerHTML = `
                    <div class="client-icon"><i class="fas fa-user"></i></div>
                    <div class="client-info">
                        <div class="client-name">${item.nombre}</div>
                        <div class="client-sub">${item.ciudad || ''} · ${item.telefono || ''}</div>
                    </div>
                    <button class="icon-btn" onclick='editClient(${JSON.stringify(item).replace(/'/g, "&#39;")})'><i class="fas fa-chevron-right"></i></button>
                `;
                return div;
            } else if (currentTab === 'envios') {
                img = item.FOTO_PORTADA || '';
                title = `Pedido #${item.id}`;
                sub = `${item.cliente_nombre || 'Cliente'} | ${item.estado}`;
                footer = `
                    <span style="font-size:12px; font-weight:700;">${item.fecha_pedido}</span>
                    <button class="icon-btn" onclick="editEnvio(${JSON.stringify(item).replace(/"/g, '&quot;')})"><i class="fas fa-truck"></i></button>`;
                badge = `<div class="badge" style="background:var(--accent)">${item.estado}</div>`;
            }

            const imgUrl = `/foto?path=${encodeURIComponent(img)}`;
            div.innerHTML = `
                ${badge}
                <img class="card-img" src="${imgUrl}" loading="lazy" onclick="openFullscreen('${imgUrl}'); event.stopPropagation()">
                <div class="card-content" onclick="editItem(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                    <h3 class="card-title">${title}</h3>
                    <div class="card-sub">${sub}</div>
                    <div class="card-footer">${footer}</div>
                </div>
            `;
            return div;
        }

        function openFullscreen(src) {
            const modal = document.getElementById('fs-modal');
            const img = document.getElementById('fs-img');
            img.src = src;
            modal.classList.add('show');
        }

        function closeFullscreen() {
            document.getElementById('fs-modal').classList.remove('show');
        }

        function editItem(item) {
            currentItem = item;
            document.getElementById('edit-form').reset();
            
            // 1. Set Title and Mode
            let title = "Gestión de ";
            if (currentTab === 'influencers') title = "Perfil Influencer";
            else if (currentTab === 'mat') title = "Gestión de Material";
            else if (currentTab === 'futuro') title = "Proyecto Futuro";
            else if (currentTab === 'despiece') title = "Receta de Producción";
            else if (currentTab === 'ventas') title = "Gestión de Ventas";
            else title = "Gestión de Artículo";
            
            document.getElementById('sheet-header-title').innerText = title;
            document.body.setAttribute('data-tab', currentTab);

            // 2. IDs and Keys
            const pk = item.SKU_REF || item.REF_MAT || '';
            document.getElementById('edit-sku').value = pk;
            document.getElementById('edit-sku').readOnly = true;
            document.getElementById('edit-sku').style.background = '#f1f5f9';
            document.getElementById('edit-sku-orig').value = pk;
            document.getElementById('edit-id-orig').value = item.id || '';
            
            // 3. Visibility Logic by Tab/Type
            // Ocultar todo primero (clases de visibilidad)
            const allFields = ['for-prod', 'for-futuro', 'for-mat', 'for-inf', 'for-sales', 'for-despiece'];
            allFields.forEach(cls => {
                document.querySelectorAll('.' + cls).forEach(el => el.style.display = 'none');
            });

            // Mostrar según pestaña
            if (currentTab === 'prod') {
                document.querySelectorAll('.for-prod').forEach(el => el.style.display = '');
            } else if (currentTab === 'futuro') {
                document.querySelectorAll('.for-futuro').forEach(el => el.style.display = '');
            } else if (currentTab === 'mat') {
                document.querySelectorAll('.for-mat').forEach(el => el.style.display = '');
            } else if (currentTab === 'influencers') {
                document.querySelectorAll('.for-inf').forEach(el => el.style.display = '');
            } else if (currentTab === 'ventas') {
                document.querySelectorAll('.for-prod, .for-sales').forEach(el => el.style.display = '');
            } else if (currentTab === 'despiece') {
                document.querySelectorAll('.for-prod, .for-despiece').forEach(el => el.style.display = '');
            }

            // 4. Fill Data
            // Common for Articles (Prod, Ventas, Despiece, Futuros)
            if (currentTab === 'prod' || currentTab === 'futuro' || currentTab === 'ventas' || currentTab === 'despiece') {
                document.getElementById('edit-nombre').value = item.NOMBRE || '';
                document.getElementById('edit-desc').value = item.DESCRIPCION || '';
                document.getElementById('edit-precio').value = item.PRECIO || 0;
                document.getElementById('edit-cat').value = item.CATEGORIA || '';
                document.getElementById('edit-subcat').value = item.SUBCATEGORIA || '';
                document.getElementById('edit-marca').value = item.MARCA || '';
                document.getElementById('edit-color').value = item.COLOR || '';
                document.getElementById('edit-dim').value = item.DIMENSIONES || '';
                document.getElementById('edit-fest').value = item.FESTIVIDAD || '';
                
                if (currentTab !== 'futuro') {
                    document.getElementById('edit-stock').value = item.STOCK || 0;
                    document.getElementById('edit-realizadas').value = item.UNIDADES_REALIZADAS || 0;
                    document.getElementById('edit-stock-fisico').value = item.STOCK_FISICO || 0;
                    
                    const sf = item.STOCK_FISICO || 0;
                    const sr = item.UNIDADES_REALIZADAS || 0;
                    ['sb-fisico','sb-fisico2'].forEach(id => { const el = document.getElementById(id); if(el) el.innerText = sf; });
                    ['sb-realizadas','sb-realizadas2'].forEach(id => { const el = document.getElementById(id); if(el) el.innerText = sr; });
                    ['sb-fab','sb-fab2'].forEach(id => { const el = document.getElementById(id); if(el) el.innerText = '...'; });
                } else {
                    document.getElementById('edit-estado').value = item.ESTADO || 'Pendiente';
                    document.getElementById('edit-realizadas').value = item.UNIDADES_REALIZADAS || 0;
                }
            }

            // Specific for Materials
            if (currentTab === 'mat') {
                document.getElementById('edit-nombre').value = item.NOMBRE || '';
                document.getElementById('edit-cat').value = item.CATEGORIA || '';
                document.getElementById('edit-subcat').value = item.SUBCATEGORIA || '';
                document.getElementById('edit-marca').value = item.MARCA || '';
                document.getElementById('edit-color').value = item.COLOR || '';
                document.getElementById('edit-dim').value = item.DIMENSIONES || '';
                document.getElementById('edit-fest').value = item.FESTIVIDAD || '';
                document.getElementById('edit-stock').value = item.STOCK_ACTUAL || 0;
                document.getElementById('edit-unit').value = item.UNIDAD || '';
                document.getElementById('edit-pp').value = item.PUNTO_PEDIDO || 100;
            }

            // Specific for Influencers
            if (currentTab === 'influencers') {
                document.getElementById('inf-id').value = item.id || '';
                document.getElementById('inf-nombre').value = item.nombre || '';
                document.getElementById('inf-usuario').value = item.usuario_ig || '';
                document.getElementById('inf-nicho').value = item.nicho || '';
                document.getElementById('inf-vibe').value = item.vibe_estilo || '';
                document.getElementById('inf-seguidores').value = item.seguidores || 0;
                document.getElementById('inf-likes').value = item.likes_promedio || 0;
                document.getElementById('inf-tel').value = item.telefono || '';
                document.getElementById('inf-email').value = item.email || '';
                document.getElementById('edit-desc').value = item.notas || ''; // Usar desc para notas
                if (item.id) loadInfluencerColabs(item.id);
            } else if (currentTab === 'compras') {
                document.getElementById('compra-id').value = item.id || '';
                document.getElementById('compra-articulo').value = item.articulo || '';
                document.getElementById('compra-cantidad').value = item.cantidad || '';
                document.getElementById('compra-comprado').checked = item.comprado == 1;
            }

            // 5. ReadOnly Logic
            if (currentTab === 'ventas') {
                document.getElementById('edit-nombre').readOnly = true;
                document.getElementById('edit-sku').readOnly = true;
                document.getElementById('edit-desc').readOnly = true;
            } else {
                document.getElementById('edit-nombre').readOnly = false;
                document.getElementById('edit-desc').readOnly = false;
            }
            
            // 6. Image
            const imgPath = item.FOTO_PORTADA || item.FOTO_REFERENCIA || item.FOTO;
            document.getElementById('edit-img').src = imgPath ? `/foto?path=${encodeURIComponent(imgPath)}` : '';
            document.getElementById('edit-foto-path').value = imgPath || '';
            document.getElementById('image-container').style.display = (currentTab === 'influencers') ? 'none' : 'block';

            // 7. Load Tab Specific Extras
            if (currentTab === 'despiece') loadDespiece(pk);
            if (currentTab === 'ventas') loadVentasData(pk);
            if (currentTab === 'despiece' || currentTab === 'ventas') loadStockSummary(pk);
            
            document.getElementById('detail-sheet').classList.add('show');
            document.getElementById('sheet-overlay').classList.add('show');
        }

        async function saveItem() {
            const pk = document.getElementById('edit-sku-orig').value;
            let data = {
                sku_orig: pk,
                id_orig: document.getElementById('edit-id-orig').value,
                sku: document.getElementById('edit-sku').value,
                is_new: !pk && !document.getElementById('edit-id-orig').value,
                foto: document.getElementById('edit-foto-path').value,
                type: currentTab
            };

            // Common for Prod, Futuro, Mat
            if (currentTab === 'prod' || currentTab === 'futuro' || currentTab === 'mat' || currentTab === 'ventas' || currentTab === 'despiece') {
                data.nombre = document.getElementById('edit-nombre').value;
                data.categoria = document.getElementById('edit-cat').value;
                data.subcategoria = document.getElementById('edit-subcat').value;
                data.marca = document.getElementById('edit-marca').value;
                data.color = document.getElementById('edit-color').value;
                data.dimensiones = document.getElementById('edit-dim').value;
                data.festividad = document.getElementById('edit-fest').value;
            }

            // Specific for Articles (Prod, Ventas, Despiece)
            if (currentTab === 'prod' || currentTab === 'ventas' || currentTab === 'despiece') {
                data.precio = document.getElementById('edit-precio').value;
                data.descripcion = document.getElementById('edit-desc').value;
                data.stock = document.getElementById('edit-stock').value;
                data.stock_fisico = document.getElementById('edit-stock-fisico').value;
                data.unidades_realizadas = document.getElementById('edit-realizadas').value;
                data.type = 'prod'; // Unified Backend Type
            }

            // Specific for Futuros
            if (currentTab === 'futuro') {
                data.precio = document.getElementById('edit-precio').value;
                data.descripcion = document.getElementById('edit-desc').value;
                data.estado = document.getElementById('edit-estado').value;
                data.unidades_realizadas = document.getElementById('edit-realizadas').value;
            }

            // Specific for Materials
            if (currentTab === 'mat') {
                data.unidad = document.getElementById('edit-unit').value;
                data.stock = document.getElementById('edit-stock').value;
                data.punto_pedido = document.getElementById('edit-pp').value;
            }

            // Handle Influencers, Clients, Pedidos separately (they have their own logic in the original code)
            if (currentTab === 'influencers') {
                const infData = {
                    id: document.getElementById('inf-id').value,
                    nombre: document.getElementById('inf-nombre').value,
                    usuario_ig: document.getElementById('inf-usuario').value,
                    nicho: document.getElementById('inf-nicho').value,
                    vibe_estilo: document.getElementById('inf-vibe').value,
                    seguidores: document.getElementById('inf-seguidores').value,
                    likes_promedio: document.getElementById('inf-likes').value,
                    telefono: document.getElementById('inf-tel').value,
                    email: document.getElementById('inf-email').value,
                    notas: document.getElementById('edit-desc').value
                };
                
                try {
                    const r = await fetch('/api/influencers/guardar', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(infData)
                    });
                    const res = await r.json();
                    if (res.success) {
                        showToast('✅ Influencer guardado');
                        closeSheet();
                        fetchData();
                    } else { showToast('❌ Error: ' + res.error); }
                } catch (e) { showToast('❌ Error de conexión'); }
                return;
            }

            if (currentTab === 'clientes') {
                const clientData = {
                    id: document.getElementById('client-id').value,
                    nombre: document.getElementById('client-nombre').value,
                    telefono: document.getElementById('client-tel').value,
                    email: document.getElementById('client-email').value,
                    instagram: document.getElementById('client-ig').value,
                    direccion: document.getElementById('client-dir').value,
                    ciudad: document.getElementById('client-ciudad').value,
                    codigo_postal: document.getElementById('client-cp').value,
                    notas: document.getElementById('client-notas').value
                };
                try {
                    const r = await fetch('/api/clientes/guardar', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(clientData)
                    });
                    const res = await r.json();
                    if (res.success) {
                        showToast('✅ Cliente guardado');
                        closeSheet();
                        fetchData();
                    } else { showToast('❌ Error: ' + res.error); }
                } catch (e) { showToast('❌ Error de conexión'); }
                return;
            }

            if (currentTab === 'pedidos') {
                const pedidoData = {
                    id: document.getElementById('pedido-id').value,
                    id_cliente: document.getElementById('pedido-cliente-id').value,
                    prioridad: document.getElementById('pedido-prioridad').value,
                    estado: document.getElementById('pedido-estado-kanban').value,
                    detalles_criticos: document.getElementById('pedido-detalles').value
                };
                try {
                    const r = await fetch('/api/pedidos/guardar', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(pedidoData)
                    });
                    const res = await r.json();
                    if (res.success) {
                        showToast('✅ Pedido guardado');
                        closeSheet();
                        fetchData();
                    } else { showToast('❌ Error: ' + res.error); }
                } catch (e) { showToast('❌ Error de conexión'); }
                return;
            }

            if (currentTab === 'compras') {
                const compraData = {
                    id: document.getElementById('compra-id').value,
                    articulo: document.getElementById('compra-articulo').value,
                    cantidad: document.getElementById('compra-cantidad').value,
                    comprado: document.getElementById('compra-comprado').checked ? 1 : 0
                };
                try {
                    const r = await fetch('/api/compras/guardar', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(compraData)
                    });
                    const res = await r.json();
                    if (res.success) {
                        showToast('✅ Lista de compras actualizada');
                        closeSheet();
                        fetchData();
                    } else { showToast('❌ Error: ' + res.error); }
                } catch (e) { showToast('❌ Error de conexión'); }
                return;
            }

            showToast('Guardando cambios...');
            
            try {
                // 1. Save Article Main Data
                const r = await fetch('/api/guardar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const res = await r.json();
                
                if (res.success) {
                    // 2. If Ventas Tab, also save sales data
                    if (currentTab === 'ventas') {
                        const vRes = await saveVentasData(pk);
                        if (!vRes.success) {
                            showToast('⚠️ Datos guardados pero error en plataformas: ' + vRes.error);
                            return;
                        }
                    }
                    
                    showToast('✅ Todo guardado con éxito');
                    closeSheet();
                    fetchData();
                } else {
                    showToast('❌ Error: ' + res.error);
                }
            } catch (e) {
                showToast('❌ Error de conexión');
            }
        }

        async function deleteItem() {
            if (!confirm('¿Seguro que quieres eliminar este elemento permanentemente?')) return;
            
            const data = {
                sku: document.getElementById('edit-sku-orig').value,
                id: document.getElementById('edit-id-orig').value,
                type: currentTab
            };

            if (currentTab === 'compras') {
                try {
                    const r = await fetch('/api/compras/eliminar', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id: document.getElementById('compra-id').value})
                    });
                    const res = await r.json();
                    if (res.success) {
                        showToast('🗑️ Eliminado de compras');
                        closeSheet();
                        fetchData();
                    }
                } catch (e) { showToast('❌ Error al eliminar'); }
                return;
            }

            try {
                const r = await fetch('/api/eliminar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const res = await r.json();
                if (res.success) {
                    showToast('🗑️ Eliminado');
                    closeSheet();
                    fetchData();
                }
            } catch (e) {
                showToast('❌ Error al eliminar');
            }
        }

        async function loadStockSummary(sku) {
            try {
                const r = await fetch(`/api/fabricable?sku=${encodeURIComponent(sku)}`);
                const d = await r.json();
                ['sb-fab','sb-fab2'].forEach(id => { const el = document.getElementById(id); if(el) el.innerText = d.fabricable ?? '?'; });
            } catch(e) { ['sb-fab','sb-fab2'].forEach(id => { const el = document.getElementById(id); if(el) el.innerText = '?'; }); }
        }

        function closeSheet() {

            document.getElementById('detail-sheet').classList.remove('show');
            document.getElementById('sheet-overlay').classList.remove('show');
        }

        async function uploadImage(input) {
            if (!input.files || !input.files[0]) return;
            const formData = new FormData();
            formData.append('foto', input.files[0]);
            formData.append('folder_type', currentTab === 'mat' ? 'materiales' : currentTab === 'futuro' ? 'futuro' : 'proyectos');
            showToast('📤 Subiendo foto...');
            try {
                const r = await fetch('/api/upload', { method: 'POST', body: formData });
                const res = await r.json();
                if (res.success) {
                    showToast('✅ Foto subida');
                    document.getElementById('edit-img').src = `/foto?path=${encodeURIComponent(res.path)}`;
                    document.getElementById('edit-foto-path').value = res.path;
                    
                    // Si estamos en la pestaña de futuro y no hay formulario abierto,
                    // significa que es una "inspiración rápida"
                    if (currentTab === 'futuro' && !document.getElementById('detail-sheet').classList.contains('show')) {
                        fetchData(); // Recargar para ver la nueva inspiración
                    }
                }
            } catch (e) { showToast('❌ Error al subir'); }
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.innerText = msg;
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 3000);
        }

        async function loadDespiece(sku) {
            const container = document.getElementById('despiece-container');
            const list = document.getElementById('despiece-list');
            container.style.display = 'block';
            list.innerHTML = '<p style="font-size:13px; color:var(--text-sub); font-style:italic;">Cargando componentes...</p>';
            
            try {
                const res = await fetch('/api/despiece?sku=' + encodeURIComponent(sku));
                const data = await res.json();
                
                const statsRes = await fetch('/api/capacidad?sku=' + encodeURIComponent(sku));
                const stats = await statsRes.json();
                
                let html = '';
                
                // Botón añadir solo en pestaña despiece
                if (currentTab === 'despiece') {
                    html += `
                        <div style="background:var(--bg); padding:15px; border-radius:15px; border:1px dashed var(--primary); margin-bottom:15px; text-align:center;">
                            <div id="capacity-indicator" style="font-weight:800; color:${stats.capacidad > 0 ? 'var(--accent)' : 'var(--danger)'}; margin-bottom:10px; font-size:16px;">
                                <i class="fas fa-chart-pie"></i> CAPACIDAD: ${stats.capacidad} UNIDADES
                            </div>
                            <button type="button" class="btn" style="background:var(--primary); color:white; width:auto; padding:10px 20px;" onclick="openAddMaterialModal()">
                                <i class="fas fa-plus"></i> Añadir Material
                            </button>
                        </div>
                    `;
                }

                if (data.length === 0) {
                    html += '<p style="font-size:13px; color:var(--text-sub);">No hay materiales vinculados.</p>';
                } else {
                    html += data.map(m => `
                        <div style="display:flex; align-items:center; gap:10px; background:#f8fafc; padding:10px; border-radius:12px; border:1px solid #e2e8f0;">
                            <img src="${m.FOTO ? '/foto?path=' + encodeURIComponent(m.FOTO) : ''}" 
                                 style="width:40px; height:40px; border-radius:8px; object-fit:cover; background:#f1f5f9;"
                                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22><rect width=%2240%22 height=%2240%22 fill=%22%23f1f5f9%22/></svg>'">
                            <div style="flex:1">
                                <div style="font-size:13px; font-weight:700;">${m.NOMBRE}</div>
                                <div style="font-size:11px; color:var(--text-sub)">Necesario: ${m.CANTIDAD} ${m.UNIDAD}</div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-size:11px; font-weight:700; color:${parseFloat(m.STOCK_ACTUAL) >= parseFloat(m.CANTIDAD) ? 'var(--accent)' : 'var(--danger)'}">
                                    STOCK: ${m.STOCK_ACTUAL}
                                </div>
                                ${currentTab === 'despiece' ? `<button type="button" onclick="removeMaterial('${m.REF_MAT}')" style="background:none; border:none; color:var(--danger); font-size:12px; margin-top:4px;"><i class="fas fa-trash"></i></button>` : ''}
                            </div>
                        </div>
                    `).join('');
                }
                list.innerHTML = html;
            } catch (err) {
                list.innerHTML = '<p style="font-size:13px; color:var(--danger);">Error al cargar componentes.</p>';
            }
        }

        async function loadVentasData(sku) {
            const list = document.getElementById('plat-list');
            list.innerHTML = '<p style="font-size:13px; color:var(--text-sub); font-style:italic;">Cargando plataformas...</p>';

            try {
                const res = await fetch('/api/ventas/detalle?sku=' + encodeURIComponent(sku));
                const json = await res.json();
                const data = json.data;
                const plataformas = json.plataformas; // Nueva lista dinámica

                let html = '';
                plataformas.forEach(plat => {
                    const col = plat.columna_db;
                    const estado = data[col + '_ESTADO'] || 'Pendiente';
                    const precio = data[col + '_PRECIO'] || 0;
                    
                    html += `
                        <div class="platform-card">
                            <div class="platform-name">${plat.nombre_visible}</div>
                            <div class="platform-controls">
                                <select id="est-${col}">
                                    <option value="Pendiente" ${estado==='Pendiente'?'selected':''}>Pendiente</option>
                                    <option value="Subido" ${estado==='Subido'?'selected':''}>Subido</option>
                                    <option value="Vendido" ${estado==='Vendido'?'selected':''}>Vendido</option>
                                    <option value="Oculto" ${estado==='Oculto'?'selected':''}>Oculto</option>
                                </select>
                                <input type="number" id="pre-${col}" value="${precio}" step="0.5" placeholder="€">
                            </div>
                        </div>
                    `;
                });

                list.innerHTML = html;
                document.getElementById('venta-unidades').value = data.UNIDADES_VENTA || 0;
                
                // Guardar la lista de columnas para el save
                window.currentPlatforms = plataformas.map(p => p.columna_db);

            } catch (err) {
                list.innerHTML = '<p style="font-size:13px; color:var(--danger);">Error al cargar datos de venta.</p>';
            }
        }

        async function saveVentasData(sku) {
            const unidades = document.getElementById('venta-unidades').value;
            const data = {
                SKU_BASE: sku,
                UNIDADES_VENTA: unidades,
                STOCK: unidades, // Sincronizar con el stock general
                STOCK_FISICO: document.getElementById('edit-stock-fisico').value
            };

            if (window.currentPlatforms) {
                window.currentPlatforms.forEach(col => {
                    data[col + '_ESTADO'] = document.getElementById('est-' + col).value;
                    data[col + '_PRECIO'] = document.getElementById('pre-' + col).value;
                });
            }

            try {
                const r = await fetch('/api/ventas/guardar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                return await r.json();
            } catch (e) {
                return {success: false, error: 'Error de red'};
            }
        }

        async function addNewPlatformUI() {
            const nombre = prompt("Nombre de la nueva plataforma (Ej: Etsy, Facebook):");
            if (!nombre) return;
            
            showToast('Añadiendo plataforma...');
            try {
                const r = await fetch('/api/ventas/plataformas/agregar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({nombre: nombre})
                });
                const res = await r.json();
                if (res.success) {
                    showToast('✅ Plataforma añadida');
                    if (currentItem) loadVentasData(document.getElementById('edit-sku-orig').value);
                } else {
                    showToast('❌ Error: ' + res.error);
                }
            } catch (e) {
                showToast('❌ Error de conexión');
            }
        }

        async function openAddMaterialModal() {
            console.log("[DEBUG] Opening Modal - Resetting State");
            
            // CRITICAL: Reset ALL flags first
            isSelectingMat = false;
            
            document.getElementById('mat-modal').classList.add('show');
            document.getElementById('mat-search-input').value = '';
            document.getElementById('mat-search-input').classList.remove('mat-selected');
            
            // Reset central state
            selectedMatSkuGlobal = "";
            selectedMatNameGlobal = "";
            document.getElementById('mat-selected-ref').value = '';
            
            document.getElementById('mat-preview-name').innerText = '';
            document.getElementById('mat-selected-preview').style.display = 'none';
            document.getElementById('mat-qty-input').value = '1';
            
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';

            // Populate material categories
            try {
                const r = await fetch('/api/categorias?tab=mat');
                const cats = await r.json();
                let html = '<option value="">Categorías</option>';
                cats.forEach(c => { if(c) html += `<option value="${c}">${c}</option>`; });
                document.getElementById('mat-category-filter').innerHTML = html;
            } catch(e) {}
        }

        async function handleMatSearch() {
            if (isSelectingMat) return;
            
            const input = document.getElementById('mat-search-input');
            const query = input.value.trim();
            const cat = document.getElementById('mat-category-filter').value;
            const list = document.getElementById('mat-results-list');

            if (query.length < 1 && !cat) {
                list.style.display = 'none';
                return;
            }
            
            try {
                console.log("[DEBUG] Searching materials for:", query, "cat:", cat);
                const r = await fetch(`/api/materiales?q=${encodeURIComponent(query)}&cat=${encodeURIComponent(cat)}`);
                const data = await r.json();
                console.log("[DEBUG] Got", data.length, "results");
                const currentRef = selectedMatSkuGlobal;
                
                if (data.length > 0) {
                    list.innerHTML = data.map(m => {
                        const isSelected = m.REF_MAT === currentRef;
                        const fotoSrc = m.FOTO ? '/foto?path=' + encodeURIComponent(m.FOTO) : '';
                        const nombreSafe = (m.NOMBRE || '').replace(/"/g, '&quot;');
                        return `
                        <div class="mat-item ${isSelected ? 'selected' : ''}" 
                             data-ref="${m.REF_MAT}"
                             data-nombre="${nombreSafe}"
                             data-foto="${m.FOTO || ''}"
                             style="padding:12px; display:flex; align-items:center; gap:10px; cursor:pointer; border-bottom:1px solid #f1f5f9;">
                            <img src="${fotoSrc}" style="width:35px; height:35px; border-radius:8px; object-fit:cover; pointer-events:none; background:#eee;">
                            <div style="flex:1; font-size:13px; pointer-events:none;">
                                <b>${m.NOMBRE}</b><br>
                                <small style="color:var(--text-sub)">${m.REF_MAT}</small>
                            </div>
                            <div class="check-container" style="pointer-events:none; font-size:18px;">
                                ${isSelected ? '<i class="fas fa-check-circle" style="color:var(--primary)"></i>' : '<i class="far fa-circle" style="color:#e2e8f0"></i>'}
                            </div>
                        </div>`;
                    }).join('');
                    list.style.display = 'block';
                    
                    // Event Delegation for clicks/touches
                    list.onclick = (e) => {
                        const item = e.target.closest('.mat-item');
                        if (item) {
                            selectMaterial(item.dataset.ref, item.dataset.nombre, item.dataset.foto);
                        }
                    };
                } else {
                    list.innerHTML = '<div style="padding:16px; text-align:center; color:var(--text-sub); font-size:13px;">No se encontraron materiales</div>';
                    list.style.display = 'block';
                }
            } catch (e) {
                console.error("[ERROR] handleMatSearch failed:", e);
                list.innerHTML = '<div style="padding:16px; text-align:center; color:var(--danger); font-size:13px;">Error en la búsqueda</div>';
                list.style.display = 'block';
            }
        }

        function selectMaterial(ref, nombre, foto) {
            try {
                console.log("[DEBUG] Atomic selection:", {ref, nombre});
                isSelectingMat = true;
                
                if (matSearchAbortController) matSearchAbortController.abort();
                
                // 1. SET CENTRAL STATE (Global and DOM)
                selectedMatSkuGlobal = ref;
                selectedMatNameGlobal = nombre;
                document.getElementById('mat-selected-ref').value = ref;
                
                // 2. Visual Update
                document.getElementById('mat-preview-name').innerText = nombre;
                document.getElementById('mat-preview-img').src = foto ? `/foto?path=${encodeURIComponent(foto)}` : '';
                
                const input = document.getElementById('mat-search-input');
                input.value = nombre;
                input.classList.add('mat-selected');
                input.blur();
                
                document.getElementById('mat-selected-preview').style.display = 'flex';
                
                // 3. ENABLE BUTTON
                const btn = document.getElementById('btn-confirm-link');
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
                
                // 4. Hide results
                document.getElementById('mat-results-list').style.display = 'none';
                
                console.log("[DEBUG] State locked for:", selectedMatSkuGlobal);
                
                setTimeout(() => { isSelectingMat = false; }, 500);

            } catch (err) {
                console.error("Critical error in selectMaterial:", err);
                isSelectingMat = false;
            }
        }

        async function confirmAddMaterial() {
            // REDUNDANT READ for maximum safety
            const matSku = selectedMatSkuGlobal || document.getElementById('mat-selected-ref').value;
            const qty = document.getElementById('mat-qty-input').value;
            
            console.log("[DEBUG] Link Attempt:", {matSku, qty, global: selectedMatSkuGlobal});
            
            if (!matSku) {
                showToast('❌ Falta seleccionar el material de la lista');
                return;
            }
            if (!qty || qty <= 0) {
                showToast('❌ Ingresa una cantidad válida');
                return;
            }
            
            const sku = document.getElementById('edit-sku-orig').value;
            try {
                const r = await fetch('/api/despiece/vincular', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ sku_base: sku, ref_mat: matSku, cantidad: qty })
                });
                const res = await r.json();
                if (res.success) {
                    showToast('✅ Material vinculado');
                    closeMatModal();
                    loadDespiece(sku);
                } else { showToast('❌ Error: ' + res.error); }
            } catch (e) { showToast('❌ Error de conexión'); }
        }

        function closeMatModal() {
            document.getElementById('mat-modal').classList.remove('show');
        }

        async function removeMaterial(matRef) {
            if (!confirm(`¿Eliminar ${matRef} de esta receta?`)) return;
            const sku = document.getElementById('edit-sku-orig').value;
            try {
                const r = await fetch('/api/despiece/desvincular', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ sku_base: sku, ref_mat: matRef })
                });
                if ((await r.json()).success) {
                    showToast('🗑️ Eliminado');
                    loadDespiece(sku);
                }
            } catch (e) { showToast('❌ Error'); }
        }

        async function loadKanban(query = "") {
            const board = document.getElementById('kanban-board');
            board.innerHTML = '<div class="loader" style="display:block"></div>';
            try {
                const r = await fetch(`/api/pedidos?q=${encodeURIComponent(query)}`);
                const pedidos = await r.json();
                const states = ["Por empezar", "En proceso", "Secado/Horno", "Acabado/Barniz", "Listo para entrega", "Entregado"];
                let html = '';
                states.forEach(state => {
                    const filtered = pedidos.filter(p => p.estado === state);
                    html += `
                        <div class="kanban-col">
                            <div class="kanban-col-title">${state} (${filtered.length})</div>
                            <div class="kanban-list">
                                ${filtered.map(p => `
                                    <div class="kanban-card priority-${p.prioridad.toLowerCase()}">
                                        <div class="kanban-card-title">${p.cliente_nombre}</div>
                                        <div class="kanban-card-detail">
                                            <b>Ref:</b> #${p.id}<br>
                                            <span style="color:var(--danger); font-weight:800">${p.detalles_criticos || ''}</span>
                                        </div>
                                        <div class="kanban-actions">
                                            <button class="icon-btn" onclick="moveOrder(${p.id}, -1)"><i class="fas fa-arrow-left"></i></button>
                                            <button class="icon-btn" onclick='editPedido(${JSON.stringify(p).replace(/'/g, "&#39;")})'><i class="fas fa-edit"></i></button>
                                            <button class="icon-btn" onclick="moveOrder(${p.id}, 1)"><i class="fas fa-arrow-right"></i></button>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>`;
                });
                board.innerHTML = html;
            } catch (e) { board.innerHTML = '<p>Error cargando pedidos</p>'; }
        }

        async function moveOrder(id, direction) {
            try {
                const r = await fetch('/api/pedidos/mover', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id, direction })
                });
                if ((await r.json()).success) {
                    loadKanban(document.getElementById('search').value);
                    showToast('✅ Estado actualizado');
                }
            } catch (e) { showToast('❌ Error'); }
        }

        function editClient(client) {
            currentItem = client;
            document.getElementById('edit-form').reset();
            document.getElementById('sheet-header-title').innerText = "Ficha de Cliente";
            document.querySelectorAll('#edit-form > div, #edit-form > .form-row').forEach(el => el.style.display = 'none');
            document.getElementById('client-section').style.display = 'block';
            document.getElementById('image-container').style.display = 'none';
            document.getElementById('client-id').value = client.id || '';
            document.getElementById('client-nombre').value = client.nombre || '';
            document.getElementById('client-tel').value = client.telefono || '';
            document.getElementById('client-email').value = client.email || '';
            document.getElementById('client-ig').value = client.instagram || '';
            document.getElementById('client-dir').value = client.direccion || '';
            document.getElementById('client-ciudad').value = client.ciudad || '';
            document.getElementById('client-cp').value = client.codigo_postal || '';
            document.getElementById('client-notas').value = client.notas || '';
            document.body.setAttribute('data-tab', 'clientes');
            document.getElementById('detail-sheet').classList.add('show');
            document.getElementById('sheet-overlay').classList.add('show');
            // Cargar encargos del cliente
            if (client.id) loadClientEncargos(client.id);
        }

        function editPedido(pedido) {
            currentItem = pedido;
            document.getElementById('edit-form').reset();
            document.getElementById('sheet-header-title').innerText = "Gestión de Pedido";
            document.querySelectorAll('#edit-form > div, #edit-form > .form-row').forEach(el => el.style.display = 'none');
            document.getElementById('pedido-section').style.display = 'block';
            document.getElementById('image-container').style.display = 'none';
            loadClientList(pedido.id_cliente);
            document.getElementById('pedido-id').value = pedido.id || '';
            document.getElementById('pedido-prioridad').value = pedido.prioridad || 'Verde';
            document.getElementById('pedido-estado-kanban').value = pedido.estado || 'Por empezar';
            document.getElementById('pedido-detalles').value = pedido.detalles_criticos || '';
            document.body.setAttribute('data-tab', 'pedidos');
            document.getElementById('detail-sheet').classList.add('show');
            document.getElementById('sheet-overlay').classList.add('show');
        }

        async function loadClientList(selectedId) {
            const select = document.getElementById('pedido-cliente-id');
            select.innerHTML = '<option>Cargando clientes...</option>';
            try {
                const r = await fetch('/api/clientes');
                const clientes = await r.json();
                let html = '';
                clientes.forEach(c => { html += `<option value="${c.id}" ${c.id == selectedId ? 'selected' : ''}>${c.nombre}</option>`; });
                select.innerHTML = html;
            } catch (e) { select.innerHTML = '<option>Error</option>'; }
        }

        // =============================================
        // COLABORACIONES (INFLUENCERS) & ENCARGOS (CLIENTES)
        // =============================================
        let assignModalMode = ''; // 'influencer' | 'cliente'
        let assignSearchTimer;

        function openAssignModal(mode) {
            assignModalMode = mode;
            const overlay = document.getElementById('assign-modal-overlay');
            const title = document.getElementById('assign-modal-title');
            const infDiv = document.getElementById('assign-estado-inf');
            const cliDiv = document.getElementById('assign-estado-cli');
            
            // Reset
            document.getElementById('assign-search-input').value = '';
            document.getElementById('assign-search-results').style.display = 'none';
            document.getElementById('assign-search-results').innerHTML = '';
            document.getElementById('assign-selected-preview').classList.remove('show');
            document.getElementById('assign-selected-sku').value = '';
            document.getElementById('assign-notas').value = '';
            document.getElementById('btn-confirm-assign').style.opacity = '0.5';
            document.getElementById('btn-confirm-assign').style.pointerEvents = 'none';

            if (mode === 'influencer') {
                title.innerText = '📦 Asignar Artículo a Influencer';
                infDiv.style.display = 'block';
                cliDiv.style.display = 'none';
            } else {
                title.innerText = '🛍️ Asignar Artículo Encargado';
                infDiv.style.display = 'none';
                cliDiv.style.display = 'block';
            }
            overlay.classList.add('show');
            document.getElementById('assign-search-input').focus();
        }

        function closeAssignModal() {
            document.getElementById('assign-modal-overlay').classList.remove('show');
        }

        async function handleAssignSearch() {
            clearTimeout(assignSearchTimer);
            const q = document.getElementById('assign-search-input').value.trim();
            const resultsDiv = document.getElementById('assign-search-results');
            if (!q) { resultsDiv.style.display = 'none'; return; }
            
            assignSearchTimer = setTimeout(async () => {
                try {
                    const r = await fetch(`/api/buscar?q=${encodeURIComponent(q)}&cat=`);
                    const items = await r.json();
                    if (items.length === 0) {
                        resultsDiv.innerHTML = '<div style="padding:12px;font-size:13px;color:var(--text-sub);">Sin resultados</div>';
                    } else {
                        resultsDiv.innerHTML = items.slice(0, 8).map(it => `
                            <div class="assign-search-item" onclick="selectAssignItem('${it.SKU_REF}', '${(it.NOMBRE||'').replace(/'/g, "\\'")}', '${it.FOTO_PORTADA || ''}')">
                                <img src="/foto?path=${encodeURIComponent(it.FOTO_PORTADA || '')}" onerror="this.src=''">
                                <div>
                                    <div style="font-weight:800;font-size:13px;">${it.NOMBRE}</div>
                                    <div style="font-size:11px;color:var(--text-sub);">#${it.SKU_REF} · ${it.PRECIO || 0}€</div>
                                </div>
                            </div>
                        `).join('');
                    }
                    resultsDiv.style.display = 'block';
                } catch(e) { resultsDiv.innerHTML = '<div style="padding:12px;color:var(--danger);">Error buscando</div>'; resultsDiv.style.display = 'block'; }
            }, 300);
        }

        function selectAssignItem(sku, nombre, foto) {
            document.getElementById('assign-selected-sku').value = sku;
            document.getElementById('assign-preview-name').innerText = nombre;
            document.getElementById('assign-preview-sku').innerText = '#' + sku;
            document.getElementById('assign-preview-img').src = foto ? `/foto?path=${encodeURIComponent(foto)}` : '';
            document.getElementById('assign-selected-preview').classList.add('show');
            document.getElementById('assign-search-results').style.display = 'none';
            // Unlock confirm button
            const btn = document.getElementById('btn-confirm-assign');
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
        }

        async function confirmAssign() {
            const sku = document.getElementById('assign-selected-sku').value;
            if (!sku) { showToast('⚠️ Selecciona un artículo primero'); return; }

            if (assignModalMode === 'influencer') {
                const infId = document.getElementById('inf-id').value;
                if (!infId) { showToast('⚠️ Guarda primero el influencer'); return; }
                const data = {
                    influencer_id: infId,
                    sku_articulo: sku,
                    estado_colab: document.getElementById('assign-estado-colab').value,
                    porcentaje_comision: parseFloat(document.getElementById('assign-comision').value) || 10,
                    notas: document.getElementById('assign-notas').value
                };
                try {
                    const r = await fetch('/api/influencers/colaboraciones/guardar', {
                        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
                    });
                    const res = await r.json();
                    if (res.success) {
                        const pedidoMsg = res.pedido_id ? ` → Pedido #${res.pedido_id} creado (🎁 Regalo)` : '';
                        showToast(`✅ Colaboración asignada${pedidoMsg}`);
                        closeAssignModal();
                        loadInfluencerColabs(infId);
                    } else { showToast('❌ Error: ' + (res.error || 'desconocido')); }
                } catch(e) { showToast('❌ Error de red'); }

            } else {
                const clientId = document.getElementById('client-id').value;
                if (!clientId) { showToast('⚠️ Guarda primero el cliente'); return; }
                const data = {
                    cliente_id: clientId,
                    sku_articulo: sku,
                    cantidad: parseInt(document.getElementById('assign-cantidad').value) || 1,
                    estado: document.getElementById('assign-estado-encargo').value,
                    notas: document.getElementById('assign-notas').value
                };
                try {
                    const r = await fetch('/api/clientes/encargos/guardar', {
                        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
                    });
                    const res = await r.json();
                    if (res.success) {
                        const pedidoMsg = res.pedido_id ? ` → Pedido #${res.pedido_id} creado en Pedidos` : '';
                        showToast(`✅ Encargo guardado${pedidoMsg}`);
                        closeAssignModal();
                        loadClientEncargos(clientId);
                    } else { showToast('❌ Error: ' + (res.error || 'desconocido')); }
                } catch(e) { showToast('❌ Error de red'); }
            }
        }

        async function loadInfluencerColabs(infId) {
            const listDiv = document.getElementById('inf-colabs-list');
            const countSpan = document.getElementById('inf-colabs-count');
            if (!infId) { listDiv.innerHTML = '<p style="font-size:12px;color:var(--text-sub);font-style:italic;">Guarda el influencer primero.</p>'; return; }
            listDiv.innerHTML = '<p style="font-size:12px;color:var(--text-sub);font-style:italic;">Cargando...</p>';
            try {
                const r = await fetch(`/api/influencers/${infId}/colaboraciones`);
                const colabs = await r.json();
                countSpan.innerText = colabs.length;
                if (colabs.length === 0) {
                    listDiv.innerHTML = '<p style="font-size:12px;color:var(--text-sub);font-style:italic;">Sin colaboraciones asignadas.</p>';
                    return;
                }
                const estadoBadge = (e) => {
                    if (e === 'Completado') return 'badge-done';
                    if (e === 'Enviado' || e === 'Aceptado') return 'badge-sent';
                    if (e === 'Pieza en fabricación') return 'badge-process';
                    return 'badge-pending';
                };
                listDiv.innerHTML = colabs.map(c => `
                    <div class="assign-item">
                        <div class="assign-item-info">
                            <div class="assign-item-title">${c.nombre_articulo || c.sku_articulo}</div>
                            <div class="assign-item-sub">#${c.sku_articulo} · ${c.notas || ''}</div>
                        </div>
                        <span class="assign-item-badge ${estadoBadge(c.estado_colab)}">${c.estado_colab}</span>
                        <button class="btn-assign-del" onclick="deleteInfluencerColab(${c.id}, ${infId})"><i class="fas fa-trash"></i></button>
                    </div>
                `).join('');
            } catch(e) { listDiv.innerHTML = '<p style="color:var(--danger);font-size:12px;">Error cargando</p>'; }
        }

        async function deleteInfluencerColab(colabId, infId) {
            if (!confirm('¿Eliminar esta colaboración?')) return;
            try {
                const r = await fetch('/api/influencers/colaboraciones/eliminar', {
                    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: colabId})
                });
                if ((await r.json()).success) { loadInfluencerColabs(infId); showToast('🗑️ Eliminado'); }
            } catch(e) { showToast('❌ Error'); }
        }

        async function loadClientEncargos(clientId) {
            const listDiv = document.getElementById('client-encargos-list');
            const countSpan = document.getElementById('client-encargos-count');
            if (!clientId) { listDiv.innerHTML = '<p style="font-size:12px;color:var(--text-sub);font-style:italic;">Guarda el cliente primero.</p>'; return; }
            listDiv.innerHTML = '<p style="font-size:12px;color:var(--text-sub);font-style:italic;">Cargando...</p>';
            try {
                const r = await fetch(`/api/clientes/${clientId}/encargos`);
                const encargos = await r.json();
                countSpan.innerText = encargos.length;
                if (encargos.length === 0) {
                    listDiv.innerHTML = '<p style="font-size:12px;color:var(--text-sub);font-style:italic;">Sin artículos encargados.</p>';
                    return;
                }
                const estadoBadge = (e) => {
                    if (e === 'Listo') return 'badge-done';
                    if (e === 'En proceso') return 'badge-process';
                    return 'badge-pending';
                };
                listDiv.innerHTML = encargos.map(e => `
                    <div class="assign-item">
                        <div class="assign-item-info">
                            <div class="assign-item-title">${e.nombre_articulo || e.sku_articulo}</div>
                            <div class="assign-item-sub">x${e.cantidad} · ${e.notas || ''}</div>
                        </div>
                        <span class="assign-item-badge ${estadoBadge(e.estado)}">${e.estado}</span>
                        <button class="btn-assign-del" onclick="deleteClientEncargo(${e.id}, ${clientId})"><i class="fas fa-trash"></i></button>
                    </div>
                `).join('');
            } catch(e) { listDiv.innerHTML = '<p style="color:var(--danger);font-size:12px;">Error cargando</p>'; }
        }

        async function deleteClientEncargo(encId, clientId) {
            if (!confirm('¿Eliminar este encargo?')) return;
            try {
                const r = await fetch('/api/clientes/encargos/eliminar', {
                    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: encId})
                });
                if ((await r.json()).success) { loadClientEncargos(clientId); showToast('🗑️ Eliminado'); }
            } catch(e) { showToast('❌ Error'); }
        }

        // =============================================
        // FUTUROS PROYECTOS → COLA DE PRODUCCIÓN
        // =============================================
        async function enviarFuturoProduccion(futuroId, nombre) {
            if (!confirm(`¿Enviar "${nombre}" a la cola de producción?\n\n🧪 Se creará como PRUEBA INTERNA (no se cobra, no se envía).`)) return;
            try {
                const r = await fetch('/api/futuros/enviar-produccion', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: futuroId})
                });
                const res = await r.json();
                if (res.success) {
                    showToast(`🏭 Pedido #${res.pedido_id} creado — 🧪 Prueba interna`);
                    // Refrescar la lista para reflejar el nuevo estado "En Proceso"
                    setTimeout(() => fetchData(), 800);
                } else {
                    showToast('❌ Error: ' + (res.error || 'desconocido'));
                }
            } catch(e) { showToast('❌ Error de red'); }
        }

        async function handleFab() {
            if (currentTab === 'futuro') {
                const choice = confirm("¿Subir foto (Aceptar) o Crear manual (Cancelar)?");
                if (choice) document.getElementById('camera-input').click();
                else showNewItemForm();
            } else if (currentTab === 'tareas') {
                const desc = prompt("Nueva nota/recordatorio:");
                if (desc) await saveTask({descripcion: desc});
            } else {
                showNewItemForm();
            }
        }

        function createTaskCard(task) {
            const div = document.createElement('div');
            div.className = `task-card ${task.completada ? 'completed' : ''} priority-${task.prioridad || 'media'}`;
            div.innerHTML = `
                <div class="task-check" onclick="toggleTask(${task.id}, ${task.completada}); event.stopPropagation()">
                    <i class="fas fa-check"></i>
                </div>
                <div class="task-text" onclick="editTask(${JSON.stringify(task).replace(/"/g, '&quot;')})">
                    ${task.descripcion}
                    <div class="task-meta">
                        ${task.fecha_creacion.split(' ')[0]} · ${task.prioridad.toUpperCase()}
                    </div>
                </div>
                <div class="btn-task-del" onclick="deleteTask(${task.id}); event.stopPropagation()">
                    <i class="fas fa-trash"></i>
                </div>
            `;
            return div;
        }

        async function saveTask(data) {
            try {
                const r = await fetch('/api/tareas/guardar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                if ((await r.json()).success) {
                    showToast('✅ Nota guardada');
                    if (currentTab === 'tareas') fetchData();
                }
            } catch(e) { showToast('❌ Error al guardar'); }
        }

        async function toggleTask(id, currentStatus) {
            try {
                await fetch('/api/tareas/guardar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id, completada: currentStatus ? 0 : 1, descripcion: 'SAME'})
                });
                fetchData();
            } catch(e) {}
        }

        async function deleteTask(id) {
            if(!confirm('¿Eliminar esta nota?')) return;
            try {
                await fetch('/api/tareas/eliminar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id})
                });
                fetchData();
            } catch(e) {}
        }

        function editTask(task) {
            const newDesc = prompt("Editar nota:", task.descripcion);
            if (newDesc !== null && newDesc !== task.descripcion) {
                saveTask({id: task.id, descripcion: newDesc, prioridad: task.prioridad, completada: task.completada});
            }
        }

        // =============================================
        // VOICE ASSISTANT LOGIC
        // =============================================
        let recognition;
        let isListening = false;

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.lang = 'es-ES';
            recognition.continuous = false;
            recognition.interimResults = true;

            recognition.onstart = () => {
                isListening = true;
                document.getElementById('voice-btn').classList.add('active');
                document.getElementById('mic-overlay').classList.add('show');
                document.getElementById('transcript-box').innerHTML = 'Escuchando...';
            };

            recognition.onresult = (event) => {
                let interimTranscript = '';
                let finalTranscript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) finalTranscript += event.results[i][0].transcript;
                    else interimTranscript += event.results[i][0].transcript;
                }
                const text = finalTranscript || interimTranscript;
                document.getElementById('transcript-box').innerHTML = text;
            };

            recognition.onend = () => {
                isListening = false;
                document.getElementById('voice-btn').classList.remove('active');
                const text = document.getElementById('transcript-box').innerText;
                if (text && text !== 'Escuchando...' && text !== 'Di algo como: "Anota que..."') {
                    procesarVoz(text);
                }
                setTimeout(() => {
                    if (!isListening) document.getElementById('mic-overlay').classList.remove('show');
                }, 2000);
            };

            recognition.onerror = (event) => {
                console.error('Speech error:', event.error);
                isListening = false;
                document.getElementById('voice-btn').classList.remove('active');
                document.getElementById('mic-overlay').classList.remove('show');
                showToast('❌ Error de voz: ' + event.error);
            };
        }

        function toggleVoice() {
            if (!recognition) {
                showToast('⚠️ Tu navegador no soporta reconocimiento de voz');
                return;
            }
            if (isListening) recognition.stop();
            else recognition.start();
        }

        async function procesarVoz(texto) {
            const lower = texto.toLowerCase();
            console.log("[VOZ] Procesando:", lower);

            // 1. TAREAS / RECORDATORIOS (Local)
            if (lower.startsWith('nota ') || lower.startsWith('anota') || lower.startsWith('recuérdame') || lower.startsWith('apunta')) {
                let tarea = texto.replace(/^(nota que|anota que|recuérdame que|apunta que|nota|anota|recuérdame|apunta)\s+/i, '');
                tarea = tarea.charAt(0).toUpperCase() + tarea.slice(1);
                await saveTask({descripcion: tarea, prioridad: 'media'});
                showToast('📝 Anotado como tarea');
                if (currentTab === 'tareas') fetchData();
                return;
            }

            // 2. Intenciones de Negocio (Nuevo Pedido / Nuevo Cliente) -> Delegar a n8n
            if (lower.includes('nuevo pedido') || lower.includes('crear pedido') || lower.includes('nuevo cliente') || lower.includes('registrar cliente')) {
                showToast('🤖 Delegando intención compleja a n8n...');
            } else {
                showToast('🔍 Consultando asistente...');
            }

            // 3. Enviar a n8n para procesamiento avanzado (IA)
            try {
                // Usar la ruta relativa de la API local
                const r = await fetch('/api/asistente/voz', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({texto: texto})
                });
                const res = await r.json();
                if (res.respuesta) {
                    speakOutput(res.respuesta);
                    showToast("🤖 " + res.respuesta.substring(0, 30) + "...");
                }
            } catch(e) {
                showToast("❌ n8n no disponible");
            }
        }

        // --- FUNCIONES LISTA DE COMPRAS ---
        function createPurchaseCard(item) {
            const div = document.createElement('div');
            div.className = 'purchase-card' + (item.comprado ? ' bought' : '');
            div.onclick = () => editItem(item);
            
            div.innerHTML = `
                <div class="purchase-check" onclick="toggleCompra(${item.id}, ${item.comprado ? 0 : 1}); event.stopPropagation()">
                    <i class="fas fa-check"></i>
                </div>
                <div class="purchase-info">
                    <div class="purchase-text">${item.articulo}</div>
                    <div class="purchase-qty">${item.cantidad || '1'}</div>
                </div>
                <button class="icon-btn" onclick="deleteCompra(${item.id}); event.stopPropagation()" style="color:var(--danger); font-size:14px;">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            return div;
        }

        async function toggleCompra(id, estado) {
            try {
                const r = await fetch('/api/compras');
                const items = await r.json();
                const item = items.find(i => i.id === id);
                if (!item) return;

                item.comprado = estado;
                await fetch('/api/compras/guardar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(item)
                });
                fetchData();
            } catch (e) { showToast('❌ Error al actualizar'); }
        }

        async function deleteCompra(id) {
            if (!confirm('¿Eliminar de la lista?')) return;
            try {
                await fetch('/api/compras/eliminar', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                });
                fetchData();
            } catch (e) { showToast('❌ Error al eliminar'); }
        }

        updateCategories();
        fetchData();
    </script>

    <!-- Botón Flotante de Voz -->
    <button class="voice-fab" id="voice-btn" onclick="toggleVoice()" title="Asistente de Voz">
        <i class="fas fa-microphone"></i>
    </button>

    <!-- Overlay de Dictado -->
    <div id="mic-overlay" class="mic-overlay">
        <div class="mic-wave">
            <i class="fas fa-microphone"></i>
        </div>
        <h2 style="margin:0">Escuchando...</h2>
        <div id="transcript-box" class="transcript-box">Di algo como: <b>"Anota que..."</b></div>
        <button onclick="toggleVoice()" style="margin-top:40px; background:rgba(255,255,255,0.2); border:1px solid white; color:white; padding:12px 25px; border-radius:30px; cursor:pointer; font-weight:700;">Cancelar</button>
    </div>

</body>
</html>
"""

@app.route('/')
def index():
    return render_template_string(HTML_TEMPLATE)

@app.route('/manifest.json')
def manifest():
    return jsonify({
        "short_name": "Noxertez",
        "name": "Noxertez Catalogo",
        "icons": [
            {
                "src": "https://cdn-icons-png.flaticon.com/512/3061/3061341.png",
                "type": "image/png",
                "sizes": "512x512",
                "purpose": "any maskable"
            }
        ],
        "start_url": "/",
        "display": "standalone",
        "scope": "/",
        "theme_color": "#6366f1",
        "background_color": "#ffffff"
    })

@app.route('/sw.js')
def service_worker():
    sw_code = """
    self.addEventListener('install', (e) => {
        self.skipWaiting();
    });
    self.addEventListener('fetch', (event) => {
        event.respondWith(fetch(event.request));
    });
    """
    return sw_code, 200, {'Content-Type': 'application/javascript'}

@app.route('/api/n8n/status')
def api_n8n_status():
    import requests
    try:
        r = requests.get('http://localhost:5678/healthz', timeout=2)
        return jsonify({"online": r.status_code == 200})
    except:
        return jsonify({"online": False})

@app.route('/api/n8n/restart', methods=['POST'])
def api_n8n_restart():
    try:
        success = reiniciar_n8n_local()
        return jsonify({"success": success})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})

@app.route('/api/categorias')
def api_categorias():
    tab = request.args.get('tab', 'prod')
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        if tab == 'mat':
            cursor.execute("SELECT DISTINCT CATEGORIA FROM materiales ORDER BY CATEGORIA")
        elif tab == 'futuro':
            cursor.execute("SELECT DISTINCT CATEGORIA FROM futuros_proyectos ORDER BY CATEGORIA")
        elif tab == 'clientes':
            cursor.execute("SELECT DISTINCT CIUDAD FROM clientes ORDER BY CIUDAD")
        else:
            cursor.execute("SELECT DISTINCT CATEGORIA FROM productos ORDER BY CATEGORIA")
        
        cats = [r[0] for r in cursor.fetchall() if r[0]]
        return jsonify(cats)
    finally:
        conn.close()

@app.route('/api/clientes')
def api_clientes():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        sql = "SELECT * FROM clientes WHERE nombre LIKE ? OR telefono LIKE ? OR ciudad LIKE ?"
        cursor.execute(sql, [f"%{query}%", f"%{query}%", f"%{query}%"])
        return jsonify([dict(r) for r in cursor.fetchall()])
    finally:
        conn.close()

@app.route('/api/clientes/guardar', methods=['POST'])
def api_clientes_guardar():
    data = request.json
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        if data.get('id'):
            cursor.execute("""
                UPDATE clientes SET nombre=?, telefono=?, email=?, instagram=?, direccion=?, ciudad=?, codigo_postal=?, notas=?
                WHERE id=?
            """, (data['nombre'], data['telefono'], data['email'], data['instagram'], 
                  data['direccion'], data['ciudad'], data['codigo_postal'], data['notas'], data['id']))
        else:
            cursor.execute("""
                INSERT INTO clientes (nombre, telefono, email, instagram, direccion, ciudad, codigo_postal, notas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            """, (data['nombre'], data['telefono'], data['email'], data['instagram'], 
                  data['direccion'], data['ciudad'], data['codigo_postal'], data['notas']))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()

@app.route('/api/pedidos')
def api_pedidos():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        # LEFT JOIN para incluir pedidos sin cliente (influencers, futuros proyectos)
        sql = """
            SELECT p.*,
                   COALESCE(c.nombre, '\U0001f381 Influencer / Prueba') as cliente_nombre
            FROM pedidos p
            LEFT JOIN clientes c ON p.id_cliente = c.id
            WHERE (c.nombre LIKE ? OR p.detalles_criticos LIKE ? OR p.notas LIKE ?)
            ORDER BY p.id DESC
        """
        cursor.execute(sql, [f"%{query}%", f"%{query}%", f"%{query}%"])
        return jsonify([dict(r) for r in cursor.fetchall()])
    finally:
        conn.close()

@app.route('/api/pedidos/guardar', methods=['POST'])
def api_pedidos_guardar():
    data = request.json
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        if data.get('id'):
            cursor.execute("""
                UPDATE pedidos SET id_cliente=?, prioridad=?, estado=?, detalles_criticos=?
                WHERE id=?
            """, (data['id_cliente'], data['prioridad'], data['estado'], data['detalles_criticos'], data['id']))
        else:
            cursor.execute("""
                INSERT INTO pedidos (id_cliente, prioridad, estado, detalles_criticos, fecha_pedido)
                VALUES (?, ?, ?, ?, ?)
            """, (data['id_cliente'], data['prioridad'], data['estado'], data['detalles_criticos'], 
                  datetime.now().strftime("%Y-%m-%d %H:%M:%S")))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()

@app.route('/api/pedidos/mover', methods=['POST'])
def api_pedidos_mover():
    data = request.json
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        cursor.execute("SELECT estado FROM pedidos WHERE id=?", (data['id'],))
        row = cursor.fetchone()
        if not row: return jsonify({"success": False, "error": "No encontrado"})
        
        estados = ["Por empezar", "En proceso", "Secado/Horno", "Acabado/Barniz", "Listo para entrega"]
        curr_idx = estados.index(row[0])
        new_idx = max(0, min(len(estados)-1, curr_idx + data['direction']))
        new_state = estados[new_idx]
        
        sql = "UPDATE pedidos SET estado=? "
        params = [new_state]
        
        if new_state == "En proceso" and row[0] == "Por empezar":
            sql += ", fecha_inicio=?"
            params.append(datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
        elif new_state == "Listo para entrega" and row[0] != "Listo para entrega":
            sql += ", fecha_fin=?"
            params.append(datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
            
        sql += " WHERE id=?"
        params.append(data['id'])
        
        cursor.execute(sql, params)
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()

@app.route('/api/envios')
def api_envios():
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        # LEFT JOIN para no perder pedidos de influencers/futuros
        cursor.execute("""
            SELECT p.*,
                   COALESCE(c.nombre, '\U0001f381 Influencer / Prueba') as cliente_nombre
            FROM pedidos p
            LEFT JOIN clientes c ON p.id_cliente = c.id
            WHERE p.estado IN ('Listo para entrega', 'Entregado')
            ORDER BY p.id DESC
        """)
        return jsonify([dict(r) for r in cursor.fetchall()])
    finally:
        conn.close()

@app.route('/api/futuros/enviar-produccion', methods=['POST'])
def api_futuros_enviar_produccion():
    """Envía un futuro proyecto a la cola de producción como prueba interna."""
    data = request.json
    futuro_id = data.get('id')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()

        # Obtener datos del futuro proyecto
        cursor.execute('SELECT * FROM futuros_proyectos WHERE id=?', (futuro_id,))
        futuro = cursor.fetchone()
        if not futuro:
            return jsonify({"success": False, "error": "Proyecto no encontrado"})
        futuro = dict(futuro)

        # Asegurar columnas necesarias
        cursor.execute("PRAGMA table_info(pedidos)")
        cols = [c[1] for c in cursor.fetchall()]
        for col, tipo in [('sku_articulo', 'TEXT'), ('notas', 'TEXT'),
                          ('colab_id', 'INTEGER'), ('encargo_id', 'INTEGER'),
                          ('futuro_id', 'INTEGER')]:
            if col not in cols:
                cursor.execute(f'ALTER TABLE pedidos ADD COLUMN {col} {tipo}')

        # Mapear estado futuro → prioridad pedido
        prio_map = {'Urgente': 'Rojo', 'En Proceso': 'Amarillo'}
        prioridad = prio_map.get(futuro.get('ESTADO', ''), 'Verde')

        detalles = f"🧪 PRUEBA INTERNA — No enviar / No cobrar\n{futuro.get('NOMBRE', '')}"
        notas = f"Futuro Proyecto #{futuro_id} | Cat: {futuro.get('CATEGORIA', '')} | {futuro.get('DESCRIPCION', '')}"

        cursor.execute('''
            INSERT INTO pedidos (id_cliente, fecha_pedido, prioridad, estado,
                                 sku_articulo, detalles_criticos, notas, futuro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ''', (None,
              datetime.now().strftime("%Y-%m-%d %H:%M"),
              prioridad,
              'Por empezar',
              futuro.get('SKU', ''),
              detalles,
              notas,
              futuro_id))
        pedido_id = cursor.lastrowid

        # Actualizar estado del futuro proyecto a "En Proceso"
        cursor.execute("UPDATE futuros_proyectos SET ESTADO='En Proceso' WHERE id=?", (futuro_id,))

        conn.commit()
        return jsonify({"success": True, "pedido_id": pedido_id})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()



@app.route('/api/buscar')
def buscar():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        # Obtener telefono para el link de WhatsApp
        cursor.execute("SELECT valor FROM configuracion WHERE clave = 'telefono'")
        row_tel = cursor.fetchone()
        tel = row_tel[0] if row_tel else ""

        cat = request.args.get('cat', '')
        sql = "SELECT * FROM productos WHERE (SKU_REF LIKE ? OR NOMBRE LIKE ? OR CATEGORIA LIKE ? OR MARCA LIKE ?)"
        params = [f"%{query}%"] * 4
        
        if cat:
            sql += " AND CATEGORIA = ?"
            params.append(cat)
            
        sql += " LIMIT 40"
        cursor.execute(sql, params)
        rows = cursor.fetchall()
        
        res = [dict(r) for r in rows]
        for r in res:
            r['TELEFONO_WA'] = tel
        return jsonify(res)
    finally:
        conn.close()

@app.route('/api/materiales')
def materiales():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cat = request.args.get('cat', '')
        sql = "SELECT * FROM materiales WHERE (REF_MAT LIKE ? OR NOMBRE LIKE ?)"
        params = [f"%{query}%", f"%{query}%"]
        
        if cat:
            sql += " AND CATEGORIA = ?"
            params.append(cat)
            
        sql += " LIMIT 40"
        cursor.execute(sql, params)
        rows = cursor.fetchall()
        return jsonify([dict(r) for r in rows])
    finally:
        conn.close()

@app.route('/api/materiales/nombres')
def materiales_nombres():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        sql = "SELECT REF_MAT, NOMBRE FROM materiales WHERE NOMBRE LIKE ? OR REF_MAT LIKE ? LIMIT 10"
        cursor.execute(sql, (f"%{query}%", f"%{query}%"))
        return jsonify([{"REF_MAT": r[0], "NOMBRE": r[1]} for r in cursor.fetchall()])
    finally:
        conn.close()

@app.route('/api/base_articles')
def base_articles():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cat = request.args.get('cat', '')
        sql = "SELECT * FROM productos WHERE ES_VARIANTE = 'BASE' AND (SKU_REF LIKE ? OR NOMBRE LIKE ?)"
        params = [f"%{query}%", f"%{query}%"]
        
        if cat:
            sql += " AND CATEGORIA = ?"
            params.append(cat)
            
        sql += " LIMIT 40"
        cursor.execute(sql, params)
        rows = cursor.fetchall()
        return jsonify([dict(r) for r in rows])
    finally:
        conn.close()

@app.route('/api/capacidad')
def api_capacidad():
    sku = request.args.get('sku', '')
    if not sku: return jsonify({"capacidad": 0})
    
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        # Obtener despiece
        cursor.execute('''
            SELECT d.CANTIDAD, m.STOCK_ACTUAL
            FROM despiece_articulos d
            JOIN materiales m ON d.REF_MAT = m.REF_MAT
            WHERE d.SKU_BASE = ?
        ''', (sku,))
        rows = cursor.fetchall()
        
        if not rows: return jsonify({"capacidad": 0})
        
        limitantes = []
        for r in rows:
            if r['CANTIDAD'] > 0:
                limitantes.append(int(r['STOCK_ACTUAL'] // r['CANTIDAD']))
            else:
                limitantes.append(999)
        
        capacidad = min(limitantes) if limitantes else 0
        return jsonify({"capacidad": capacidad})
    finally:
        conn.close()

@app.route('/api/ventas')
def api_ventas():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        cat = request.args.get('cat', '')
        # Unir productos base con sus datos de venta
        sql = """
            SELECT p.*, v.UNIDADES_VENTA 
            FROM productos p
            LEFT JOIN plataformas_ventas v ON p.SKU_REF = v.SKU_BASE
            WHERE p.ES_VARIANTE = 'BASE' 
            AND (p.SKU_REF LIKE ? OR p.NOMBRE LIKE ?)
        """
        params = [f"%{query}%", f"%{query}%"]
        
        if cat:
            sql += " AND p.CATEGORIA = ?"
            params.append(cat)
            
        sql += " LIMIT 40"
        cursor.execute(sql, params)
        rows = cursor.fetchall()
        return jsonify([dict(r) for r in rows])
    finally:
        conn.close()

@app.route('/api/fabricable')
def api_fabricable():
    """Calcula cuantas unidades se pueden fabricar con los materiales actuales."""
    sku = request.args.get('sku', '')
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        # Obtener receta del articulo
        cursor.execute("""
            SELECT d.REF_MAT, d.CANTIDAD, m.STOCK_ACTUAL
            FROM despiece_articulos d
            JOIN materiales m ON d.REF_MAT = m.REF_MAT
            WHERE d.SKU_BASE = ?
        """, (sku,))
        receta = cursor.fetchall()
        
        if not receta:
            return jsonify({'fabricable': 0, 'sin_receta': True})
        
        limitantes = []
        for ref_mat, cantidad, stock_actual in receta:
            if cantidad and cantidad > 0:
                limitantes.append(int(stock_actual // cantidad))
            else:
                limitantes.append(9999)
        
        fabricable = int(min(limitantes)) if limitantes else 0
        return jsonify({'fabricable': fabricable, 'sin_receta': False})
    except Exception as e:
        return jsonify({'fabricable': 0, 'error': str(e)})
    finally:
        conn.close()

# --- INFLUENCERS API ---
@app.route('/api/influencers')
def api_get_influencers():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM influencers WHERE nombre LIKE ? OR usuario_ig LIKE ? OR nicho LIKE ?", (f'%{query}%', f'%{query}%', f'%{query}%'))
        rows = cursor.fetchall()
        return jsonify([dict(r) for r in rows])
    finally:
        conn.close()

@app.route('/api/influencers/guardar', methods=['POST'])
def api_save_influencer():
    data = request.json
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        if data.get('id'):
            cursor.execute('''
                UPDATE influencers SET nombre=?, usuario_ig=?, email=?, telefono=?, vibe_estilo=?, nicho=?, seguidores=?, likes_promedio=?
                WHERE id=?
            ''', (data['nombre'], data['usuario_ig'], data['email'], data['telefono'], data['vibe_estilo'], data['nicho'], data['seguidores'], data['likes_promedio'], data['id']))
        else:
            cursor.execute('''
                INSERT INTO influencers (nombre, usuario_ig, email, telefono, vibe_estilo, nicho, seguidores, likes_promedio)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ''', (data['nombre'], data['usuario_ig'], data['email'], data['telefono'], data['vibe_estilo'], data['nicho'], data['seguidores'], data['likes_promedio']))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()

@app.route('/api/ventas/detalle')
def api_ventas_detalle():
    sku = request.args.get('sku', '')
    if not sku: return jsonify({})
    
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM plataformas_ventas WHERE SKU_BASE = ?", (sku,))
        row = cursor.fetchone()
        
        # También devolver la configuración de plataformas
        plats = gestor_ventas.obtener_plataformas()
        
        return jsonify({
            "data": dict(row) if row else {},
            "plataformas": plats
        })
    finally:
        conn.close()

@app.route('/api/ventas/plataformas')
def api_ventas_plataformas():
    return jsonify(gestor_ventas.obtener_plataformas())

@app.route('/api/ventas/plataformas/agregar', methods=['POST'])
def api_ventas_plataformas_agregar():
    nombre = request.json.get('nombre')
    if not nombre: return jsonify({"success": False, "error": "Falta nombre"}), 400
    success, msg = gestor_ventas.agregar_plataforma(nombre)
    return jsonify({"success": success, "error": msg if not success else None})

@app.route('/api/ventas/guardar', methods=['POST'])
def api_ventas_guardar():
    data = request.json
    sku = data.get('SKU_BASE')
    if not sku: return jsonify({"success": False, "error": "Falta SKU"}), 400
    
    # Usar el gestor de ventas para guardar de forma segura
    success = gestor_ventas.guardar_datos_ventas(data)
    return jsonify({"success": success})

@app.route('/api/despiece/vincular', methods=['POST'])
def vincular_material():
    data = request.json
    sku_base = data.get('sku_base')
    ref_mat = data.get('ref_mat')
    cantidad = data.get('cantidad')
    
    if not all([sku_base, ref_mat, cantidad]):
        return jsonify({"success": False, "error": "Faltan datos"}), 400
        
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        # Verificar que el material existe
        cursor.execute("SELECT 1 FROM materiales WHERE REF_MAT = ?", (ref_mat,))
        if not cursor.fetchone():
            return jsonify({"success": False, "error": "Material no encontrado"}), 404
            
        cursor.execute("INSERT OR REPLACE INTO despiece_articulos (SKU_BASE, REF_MAT, CANTIDAD) VALUES (?, ?, ?)", 
                       (sku_base, ref_mat, cantidad))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500
    finally:
        conn.close()

@app.route('/api/despiece/desvincular', methods=['POST'])
def desvincular_material():
    data = request.json
    sku_base = data.get('sku_base')
    ref_mat = data.get('ref_mat')
    
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        cursor.execute("DELETE FROM despiece_articulos WHERE SKU_BASE = ? AND REF_MAT = ?", (sku_base, ref_mat))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500
    finally:
        conn.close()

@app.route('/api/despiece')
def api_despiece():
    sku = request.args.get('sku', '')
    if not sku: return jsonify([])
    
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute('''
            SELECT d.REF_MAT, d.CANTIDAD, m.NOMBRE, m.UNIDAD, m.STOCK_ACTUAL, m.FOTO
            FROM despiece_articulos d
            JOIN materiales m ON d.REF_MAT = m.REF_MAT
            WHERE d.SKU_BASE = ?
        ''', (sku,))
        rows = cursor.fetchall()
        return jsonify([dict(r) for r in rows])
    finally:
        conn.close()

@app.route('/api/futuros')
def futuros():
    query = request.args.get('q', '')
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cat = request.args.get('cat', '')
        sql = "SELECT id, FECHA, NOMBRE, ESTADO, FOTO_REFERENCIA, CATEGORIA, MARCA, DESCRIPCION, UNIDADES_REALIZADAS FROM futuros_proyectos WHERE (NOMBRE LIKE ? OR CATEGORIA LIKE ? OR MARCA LIKE ? OR ESTADO LIKE ?)"
        params = [f"%{query}%"] * 4
        
        if cat:
            sql += " AND CATEGORIA = ?"
            params.append(cat)
            
        sql += " ORDER BY id DESC LIMIT 40"
        cursor.execute(sql, params)
        rows = cursor.fetchall()
        return jsonify([dict(row) for row in rows])
    finally:
        conn.close()

@app.route('/api/guardar', methods=['POST'])
def save_item():
    data = request.json
    is_new = data.get('is_new', False)
    table = 'productos' if data['type'] == 'prod' else 'futuros_proyectos' if data['type'] == 'futuro' else 'materiales'
    
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        
        if data['type'] == 'prod':
            if is_new:
                cursor.execute("""
                    INSERT INTO productos (SKU_REF, NOMBRE, PRECIO, CATEGORIA, SUBCATEGORIA, MARCA, COLOR, DIMENSIONES, FESTIVIDAD, DESCRIPCION, STOCK, STOCK_FISICO, FOTO_PORTADA, UNIDADES_REALIZADAS)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, (data['sku'], data['nombre'], data['precio'], data['categoria'], data['subcategoria'], data['marca'], data['color'], data['dimensiones'], data['festividad'], data['descripcion'], data['stock'], data.get('stock_fisico', 0), data.get('foto'), data.get('unidades_realizadas', '0')))
            else:
                cursor.execute("""
                    UPDATE productos SET NOMBRE=?, PRECIO=?, CATEGORIA=?, SUBCATEGORIA=?, MARCA=?, COLOR=?, DIMENSIONES=?, FESTIVIDAD=?, DESCRIPCION=?, STOCK=?, STOCK_FISICO=?, FOTO_PORTADA=?, UNIDADES_REALIZADAS=?
                    WHERE SKU_REF=?
                """, (data['nombre'], data['precio'], data['categoria'], data['subcategoria'], data['marca'], data['color'], data['dimensiones'], data['festividad'], data['descripcion'], data['stock'], data.get('stock_fisico', 0), data.get('foto'), data.get('unidades_realizadas', '0'), data['sku_orig']))
        
        elif data['type'] == 'futuro':
            if is_new:
                cursor.execute("""
                    INSERT INTO futuros_proyectos (FECHA, NOMBRE, CATEGORIA, SUBCATEGORIA, MARCA, DESCRIPCION, ESTADO, FOTO_REFERENCIA, UNIDADES_REALIZADAS)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, (datetime.now().strftime("%Y-%m-%d %H:%M:%S"), data['nombre'], data['categoria'], data['subcategoria'], data['marca'], data['descripcion'], data.get('estado', 'PENDIENTE'), data.get('foto'), data.get('unidades_realizadas', '0')))
            else:
                cursor.execute("""
                    UPDATE futuros_proyectos SET NOMBRE=?, CATEGORIA=?, SUBCATEGORIA=?, MARCA=?, DESCRIPCION=?, FOTO_REFERENCIA=?, ESTADO=?, UNIDADES_REALIZADAS=?
                    WHERE id=?
                """, (data['nombre'], data['categoria'], data['subcategoria'], data['marca'], data['descripcion'], data.get('foto'), data.get('estado', 'PENDIENTE'), data.get('unidades_realizadas', '0'), data['id_orig']))

        elif data['type'] == 'mat':
            if is_new:
                cursor.execute("""
                    INSERT INTO materiales (REF_MAT, NOMBRE, UNIDAD, STOCK_ACTUAL, PUNTO_PEDIDO, FOTO, CATEGORIA, SUBCATEGORIA, MARCA, COLOR, DIMENSIONES, FESTIVIDAD)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, (data['sku'], data['nombre'], data['unidad'], data['stock'], data['punto_pedido'], data.get('foto'), 
                      data.get('categoria'), data.get('subcategoria'), data.get('marca'), data.get('color'), data.get('dimensiones'), data.get('festividad')))
            else:
                cursor.execute("""
                    UPDATE materiales SET NOMBRE=?, UNIDAD=?, STOCK_ACTUAL=?, PUNTO_PEDIDO=?, FOTO=?, CATEGORIA=?, SUBCATEGORIA=?, MARCA=?, COLOR=?, DIMENSIONES=?, FESTIVIDAD=?
                    WHERE REF_MAT=?
                """, (data['nombre'], data['unidad'], data['stock'], data['punto_pedido'], data.get('foto'), 
                      data.get('categoria'), data.get('subcategoria'), data.get('marca'), data.get('color'), data.get('dimensiones'), data.get('festividad'), data['sku_orig']))
        
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500
    finally:
        conn.close()

@app.route('/api/eliminar', methods=['POST'])
def delete_item():
    data = request.json
    table = 'productos' if data['type'] == 'prod' else 'futuros_proyectos' if data['type'] == 'futuro' else 'materiales'
    pk_col = 'SKU_REF' if data['type'] == 'prod' else 'id' if data['type'] == 'futuro' else 'REF_MAT'
    pk_val = data['sku'] if data['type'] != 'futuro' else data['id']
    
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        
        # Primero obtener la ruta de la foto para borrar el archivo
        foto_col = 'FOTO_PORTADA' if data['type'] == 'prod' else 'FOTO_REFERENCIA' if data['type'] == 'futuro' else 'FOTO'
        cursor.execute(f"SELECT {foto_col} FROM {table} WHERE {pk_col} = ?", (pk_val,))
        row = cursor.fetchone()
        if row and row[0]:
            foto_path = row[0]
            if not os.path.isabs(foto_path):
                foto_path = os.path.join(RUTA_IMAGENES, foto_path)
            if os.path.exists(foto_path):
                try:
                    os.remove(foto_path)
                    print(f"[WEB] Archivo físico eliminado: {foto_path}")
                except Exception as e:
                    print(f"[WEB] Error eliminando archivo físico: {e}")

        cursor.execute(f"DELETE FROM {table} WHERE {pk_col} = ?", (pk_val,))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500
    finally:
        conn.close()

@app.route('/api/upload', methods=['POST'])
def upload_file():
    if 'foto' not in request.files:
        return jsonify({"success": False, "error": "No file"}), 400
    
    file = request.files['foto']
    if file.filename == '':
        return jsonify({"success": False, "error": "No selected file"}), 400
    
    # Decidir carpeta según el tipo
    folder_type = request.form.get('folder_type', 'proyectos')
    print(f"[DEBUG] Upload request: folder_type={folder_type}, filename={file.filename}")
    
    target_folder = MATERIALES_FOLDER if str(folder_type).lower() == 'materiales' else PROYECTOS_FOLDER
    print(f"[DEBUG] Target folder: {target_folder}")
    
    filename = datetime.now().strftime("%Y%m%d_%H%M%S") + "_" + werkzeug.utils.secure_filename(file.filename)
    path_local = os.path.join(target_folder, filename)
    file.save(path_local)
    
    # Proactivo: Asegurar que las columnas existen en la tabla materiales
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        cursor.execute("PRAGMA table_info(materiales)")
        columns = [column[1] for column in cursor.fetchall()]
        needed = ['FOTO', 'CATEGORIA', 'SUBCATEGORIA', 'MARCA', 'COLOR', 'DIMENSIONES', 'FESTIVIDAD']
        for col in needed:
            if col not in columns:
                cursor.execute(f"ALTER TABLE materiales ADD COLUMN {col} TEXT")
                conn.commit()
                print(f"🚀 Columna {col} añadida a la tabla materiales")
    except Exception as e:
        print(f"⚠️ Error proactivo DB: {e}")
    finally:
        conn.close()
    
    return jsonify({"success": True, "path": path_local})

@app.route('/foto')
def serve_foto():
    path = request.args.get('path')
    if not path or path == 'None':
         return "Not Found", 404
    
    path = path.replace('/', os.sep).replace('\\', os.sep)
    posibles = []
    if os.path.isabs(path): posibles.append(path)
    
    posibles.append(os.path.abspath(os.path.join(BASE_DIR_DESKTOP, path)))
    
    # Usuario Desktop match
    if 'Desktop' not in path:
        user_desktop = os.path.join(os.path.expanduser("~"), "Desktop")
        posibles.append(os.path.join(user_desktop, "noxertez", "aaa creaciones", os.path.basename(path)))
    
    basename = os.path.basename(path)
    for folder in [RUTA_IMAGENES, RUTA_MATERIALES, RUTA_PROYECTOS]:
        posibles.append(os.path.join(folder, basename))

    for p in posibles:
        if os.path.exists(p) and os.path.isfile(p):
            return send_file(p)
            
    # Último intento: buscar recursivamente en RUTA_IMAGENES si es un nombre de archivo
    print(f"[DEBUG] 404 Foto: {path}. Probando búsqueda profunda...")
    return "Not Found", 404

# --- COLABORACIONES INFLUENCERS API ---
def _init_encargos_table():
    """Crea la tabla encargos_clientes si no existe."""
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS encargos_clientes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cliente_id INTEGER NOT NULL,
                sku_articulo TEXT,
                nombre_articulo TEXT,
                cantidad INTEGER DEFAULT 1,
                estado TEXT DEFAULT 'Pendiente',
                notas TEXT,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cliente_id) REFERENCES clientes(id)
            )
        ''')
        conn.commit()
    finally:
        conn.close()

# Inicializar tabla al cargar el módulo
_init_encargos_table()

@app.route('/api/influencers/<int:inf_id>/colaboraciones')
def api_inf_colaboraciones(inf_id):
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute('''
            SELECT c.*, p.NOMBRE as nombre_articulo, p.FOTO_PORTADA as foto
            FROM colaboraciones_influencers c
            LEFT JOIN productos p ON c.sku_articulo = p.SKU_REF
            WHERE c.influencer_id = ?
            ORDER BY c.id DESC
        ''', (inf_id,))
        rows = cursor.fetchall()
        return jsonify([dict(r) for r in rows])
    finally:
        conn.close()

@app.route('/api/influencers/colaboraciones/guardar', methods=['POST'])
def api_inf_colaboraciones_guardar():
    data = request.json
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        if data.get('id'):
            # Actualizar colaboración existente
            cursor.execute('''
                UPDATE colaboraciones_influencers
                SET estado_colab=?, notas=?, precio_venta=?, porcentaje_comision=?
                WHERE id=?
            ''', (data.get('estado_colab', 'Propuesta enviada'), data.get('notas', ''),
                  data.get('precio_venta', 0), data.get('porcentaje_comision', 0), data['id']))

            # Sincronizar estado del pedido vinculado si existe
            estado_colab_map = {
                'Propuesta enviada': 'Por empezar',
                'Aceptado': 'Por empezar',
                'Pieza en fabricación': 'En proceso',
                'Enviado': 'Listo para entrega',
                'Publicación pendiente': 'Listo para entrega',
                'Completado': 'Listo para entrega'
            }
            estado_pedido = estado_colab_map.get(data.get('estado_colab', 'Propuesta enviada'), 'Por empezar')
            cursor.execute('UPDATE pedidos SET estado=? WHERE colab_id=?',
                           (estado_pedido, data['id']))
            conn.commit()
            return jsonify({"success": True})
        else:
            # === NUEVA COLABORACIÓN → también crear PEDIDO (regalo) ===
            # Obtener datos del artículo
            cursor.execute('SELECT NOMBRE FROM productos WHERE SKU_REF=?', (data.get('sku_articulo', ''),))
            prod = cursor.fetchone()
            nombre_art = prod[0] if prod else data.get('sku_articulo', '')

            # Obtener nombre del influencer
            cursor.execute('SELECT nombre FROM influencers WHERE id=?', (data['influencer_id'],))
            inf = cursor.fetchone()
            nombre_inf = inf[0] if inf else f"Influencer #{data['influencer_id']}"

            # 1. Insertar colaboración
            cursor.execute('''
                INSERT INTO colaboraciones_influencers
                (influencer_id, sku_articulo, costo_produccion, precio_venta, porcentaje_comision, estado_colab, fecha_envio, notas)
                VALUES (?, ?, 0, ?, ?, ?, ?, ?)
            ''', (data['influencer_id'], data['sku_articulo'],
                  data.get('precio_venta', 0), data.get('porcentaje_comision', 10),
                  data.get('estado_colab', 'Propuesta enviada'),
                  datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                  data.get('notas', '')))
            colab_id = cursor.lastrowid

            # 2. Asegurar columnas necesarias en pedidos
            cursor.execute("PRAGMA table_info(pedidos)")
            cols = [c[1] for c in cursor.fetchall()]
            for col, tipo in [('sku_articulo', 'TEXT'), ('notas', 'TEXT'), ('colab_id', 'INTEGER'), ('encargo_id', 'INTEGER')]:
                if col not in cols:
                    cursor.execute(f'ALTER TABLE pedidos ADD COLUMN {col} {tipo}')

            # 3. Crear pedido con nota de regalo
            nota_regalo = f"🎁 REGALO INFLUENCER — No cobrar"
            detalles = f"{nota_regalo}\n{nombre_art}"
            notas_pedido = f"Para: {nombre_inf} (@{data.get('usuario_ig', '')})"
            if data.get('notas'):
                notas_pedido += f"\n{data['notas']}"

            cursor.execute('''
                INSERT INTO pedidos (id_cliente, fecha_pedido, prioridad, estado, sku_articulo, detalles_criticos, notas, colab_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ''', (None,   # influencers no tienen id_cliente
                  datetime.now().strftime("%Y-%m-%d %H:%M"),
                  'Amarillo',          # prioridad media (es colaboración)
                  'Por empezar',
                  data.get('sku_articulo', ''),
                  detalles,
                  notas_pedido,
                  colab_id))
            pedido_id = cursor.lastrowid

            conn.commit()
            return jsonify({"success": True, "colab_id": colab_id, "pedido_id": pedido_id})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()


@app.route('/api/influencers/colaboraciones/eliminar', methods=['POST'])
def api_inf_colaboraciones_eliminar():
    colab_id = request.json.get('id')
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        cursor.execute('DELETE FROM colaboraciones_influencers WHERE id=?', (colab_id,))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()

# --- ENCARGOS CLIENTES API ---
@app.route('/api/clientes/<int:cliente_id>/encargos')
def api_cliente_encargos(cliente_id):
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute('''
            SELECT e.*, p.FOTO_PORTADA as foto, p.PRECIO as precio_unitario
            FROM encargos_clientes e
            LEFT JOIN productos p ON e.sku_articulo = p.SKU_REF
            WHERE e.cliente_id = ?
            ORDER BY e.id DESC
        ''', (cliente_id,))
        rows = cursor.fetchall()
        return jsonify([dict(r) for r in rows])
    finally:
        conn.close()

@app.route('/api/clientes/encargos/guardar', methods=['POST'])
def api_cliente_encargos_guardar():
    data = request.json
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        if data.get('id'):
            # Actualización de encargo existente — también actualiza el pedido vinculado
            cursor.execute('''
                UPDATE encargos_clientes SET estado=?, notas=?, cantidad=? WHERE id=?
            ''', (data.get('estado', 'Pendiente'), data.get('notas', ''),
                  data.get('cantidad', 1), data['id']))

            # Mapear estado encargo → estado pedido
            estado_pedido_map = {
                'Pendiente': 'Por empezar',
                'En proceso': 'En proceso',
                'Listo': 'Listo para entrega'
            }
            estado_pedido = estado_pedido_map.get(data.get('estado', 'Pendiente'), 'Por empezar')
            # Actualizar el pedido vinculado si existe
            cursor.execute('UPDATE pedidos SET estado=?, notas=? WHERE encargo_id=?',
                           (estado_pedido, data.get('notas', ''), data['id']))
            conn.commit()
            return jsonify({"success": True})
        else:
            # === NUEVO ENCARGO → también crear PEDIDO ===
            # Obtener nombre del artículo desde la BD
            cursor.execute('SELECT NOMBRE FROM productos WHERE SKU_REF=?', (data.get('sku_articulo', ''),))
            prod = cursor.fetchone()
            nombre = prod[0] if prod else data.get('nombre_articulo', '')

            # 1. Insertar en encargos_clientes
            cursor.execute('''
                INSERT INTO encargos_clientes (cliente_id, sku_articulo, nombre_articulo, cantidad, estado, notas)
                VALUES (?, ?, ?, ?, ?, ?)
            ''', (data['cliente_id'], data.get('sku_articulo', ''), nombre,
                  data.get('cantidad', 1), data.get('estado', 'Pendiente'), data.get('notas', '')))
            encargo_id = cursor.lastrowid

            # 2. Verificar que la tabla pedidos tiene columna encargo_id y sku_articulo
            cursor.execute("PRAGMA table_info(pedidos)")
            cols = [c[1] for c in cursor.fetchall()]
            if 'encargo_id' not in cols:
                cursor.execute('ALTER TABLE pedidos ADD COLUMN encargo_id INTEGER')
            if 'sku_articulo' not in cols:
                cursor.execute('ALTER TABLE pedidos ADD COLUMN sku_articulo TEXT')
            if 'notas' not in cols:
                cursor.execute('ALTER TABLE pedidos ADD COLUMN notas TEXT')

            # 3. Crear el pedido vinculado
            detalles = f"{nombre} x{data.get('cantidad', 1)}"
            if data.get('notas'):
                detalles += f" — {data['notas']}"

            cursor.execute('''
                INSERT INTO pedidos (id_cliente, fecha_pedido, prioridad, estado, sku_articulo, detalles_criticos, notas, encargo_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ''', (data['cliente_id'],
                  datetime.now().strftime("%Y-%m-%d %H:%M"),
                  'Verde',            # prioridad por defecto
                  'Por empezar',      # estado inicial
                  data.get('sku_articulo', ''),
                  detalles,
                  data.get('notas', ''),
                  encargo_id))
            pedido_id = cursor.lastrowid

            conn.commit()
            return jsonify({"success": True, "encargo_id": encargo_id, "pedido_id": pedido_id})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()


@app.route('/api/clientes/encargos/eliminar', methods=['POST'])
def api_cliente_encargos_eliminar():
    enc_id = request.json.get('id')
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        cursor.execute('DELETE FROM encargos_clientes WHERE id=?', (enc_id,))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()


@app.route('/api/tareas')
def api_tareas():
    """Lista las tareas/recordatorios de la tabla 'tareas'."""
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM tareas ORDER BY completada ASC, fecha_creacion DESC")
        return jsonify([dict(r) for r in cursor.fetchall()])
    finally:
        conn.close()

@app.route('/api/tareas/guardar', methods=['POST'])
def api_tareas_guardar():
    """Crea o actualiza una tarea."""
    data = request.json
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        if data.get('id'):
            cursor.execute("""
                UPDATE tareas SET descripcion=?, prioridad=?, fecha_limite=?, completada=?
                WHERE id=?
            """, (data['descripcion'], data.get('prioridad', 'media'), 
                  data.get('fecha_limite'), data.get('completada', 0), data['id']))
        else:
            cursor.execute("""
                INSERT INTO tareas (descripcion, prioridad, fecha_limite)
                VALUES (?, ?, ?)
            """, (data['descripcion'], data.get('prioridad', 'media'), data.get('fecha_limite')))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()

@app.route('/api/tareas/eliminar', methods=['POST'])
def api_tareas_eliminar():
    """Elimina una tarea."""
    t_id = request.json.get('id')
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        cursor.execute("DELETE FROM tareas WHERE id=?", (t_id,))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()


@app.route('/api/asistente/voz', methods=['POST'])
def api_asistente_voz():
    """Proxy para conectarse con n8n localmente."""
    try:
        data = request.json
        texto = data.get('texto', '')
        if not texto:
            return jsonify({"respuesta": "No he recibido texto para procesar."})
        
        # Llamar a la función del módulo n8n
        respuesta = consultar_asistente(texto)
        return jsonify({"respuesta": respuesta})
    except Exception as e:
        return jsonify({"respuesta": f"Error de conexión con n8n: {e}"}), 500

# --- LISTA DE COMPRAS API ---
@app.route('/api/compras')
def api_get_compras():
    conn = get_db_connection(DB_PATH)
    try:
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM lista_compras ORDER BY comprado ASC, fecha DESC")
        rows = cursor.fetchall()
        return jsonify([dict(r) for r in rows])
    finally:
        conn.close()

@app.route('/api/compras/guardar', methods=['POST'])
def api_save_compra():
    data = request.json
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        if data.get('id'):
            cursor.execute("""
                UPDATE lista_compras SET articulo=?, cantidad=?, comprado=?
                WHERE id=?
            """, (data['articulo'], data.get('cantidad', '1'), data.get('comprado', 0), data['id']))
        else:
            cursor.execute("""
                INSERT INTO lista_compras (articulo, cantidad)
                VALUES (?, ?)
            """, (data['articulo'], data.get('cantidad', '1')))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()

@app.route('/api/compras/eliminar', methods=['POST'])
def api_delete_compra():
    c_id = request.json.get('id')
    conn = get_db_connection(DB_PATH)
    try:
        cursor = conn.cursor()
        cursor.execute("DELETE FROM lista_compras WHERE id=?", (c_id,))
        conn.commit()
        return jsonify({"success": True})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})
    finally:
        conn.close()

@app.route('/asistente-voz')
def asistente_voz():
    """Sirve el asistente de voz n8n (accesible en local y por Cloudflare)."""
    import os as _os
    html_path = _os.path.join(_os.path.dirname(__file__), 'n8n', 'asistente_voz.html')
    if _os.path.exists(html_path):
        with open(html_path, 'r', encoding='utf-8') as f:
            return f.read(), 200, {'Content-Type': 'text/html; charset=utf-8'}
    return '<h2>asistente_voz.html no encontrado en la carpeta n8n/</h2>', 404


def iniciar_servidor():
    print("Web Server Starting (PC Mirror) on port 5000...")
    app.run(host='0.0.0.0', port=5000, debug=False, use_reloader=False)


if __name__ == '__main__':
    iniciar_servidor()
