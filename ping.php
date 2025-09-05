<?php
// Un archivo simple para verificar si el servidor está respondiendo
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'time' => time()]); 