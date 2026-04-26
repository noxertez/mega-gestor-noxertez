<?php
require_once 'c:/mis app de noxertez 2/SahtoutCMS-main/Sahtout/api/config.php';

try {
    $db = conectar();
    $sql = file_get_contents('c:/mis app de noxertez 2/SahtoutCMS-main/Sahtout/SQL/chatbot_knowledge_setup.sql');
    
    // El script contiene múltiples sentencias, PDO::exec solo ejecuta la primera o falla si hay INSERT.
    // Usaremos un método más robusto para scripts.
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    $db->exec($sql);
    
    echo "Base de conocimiento configurada correctamente.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
