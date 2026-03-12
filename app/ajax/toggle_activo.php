<?php
// Initialize app context (includes session_start and subdomain routing)
require_once __DIR__ . '/../../shared/utils/app_init.php';

// Incluir las clases necesarias
require_once __DIR__ . '/../../shared/models/Trabajador.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificar que el usuario esté logueado
if (!Trabajador::estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que el usuario tenga permisos (solo administradores y supervisores)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (!in_array(strtolower($rol_trabajador), ['administrador', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos suficientes']);
    exit();
}

// Obtener datos de la petición
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['trabajador_id']) || !is_numeric($input['trabajador_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de trabajador inválido']);
    exit();
}

$trabajador_id = (int)$input['trabajador_id'];
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empresa no identificada']);
    exit();
}

// Verificar que no se esté intentando desactivar a sí mismo
if ($trabajador_id === ($_SESSION['id_trabajador'] ?? null)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No puedes desactivar tu propia cuenta']);
    exit();
}

try {
    $trabajador = new Trabajador();
    $resultado = $trabajador->toggleEstadoActivo($trabajador_id, $empresa_id);
    
    // Establecer código de respuesta HTTP
    if ($resultado['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    // Devolver respuesta JSON
    header('Content-Type: application/json');
    echo json_encode($resultado);
    
} catch (Exception $e) {
    error_log("Error en ajax_toggle_activo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?> 