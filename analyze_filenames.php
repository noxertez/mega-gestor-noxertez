<?php
$db = new mysqli("localhost", "noxertez_user", "Noxertez2024!", "noxertez");
$res = $db->query("SELECT archivo FROM mockups_varios WHERE DATE(fecha_subida) = CURDATE() ORDER BY id DESC LIMIT 50");
while($row = $res->fetch_assoc()) {
    echo $row['archivo'] . "\n";
}
