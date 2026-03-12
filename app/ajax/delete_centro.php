<?php
// Initialize app context (includes session_start and subdomain routing)
require_once __DIR__ . '/../../shared/utils/app_init.php';

require_once __DIR__ . '/../../shared/models/Trabajador.php';
require_once __DIR__ . '/../../shared/models/Centro.php';

// Set content type to JSON
header('Content-Type: application/json');

// Verify authentication
if (!Trabajador::estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Verify permissions (only administrators)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (strtolower($rol_trabajador) !== 'administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos suficientes. Solo administradores pueden eliminar centros.']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['centro_id']) || !is_numeric($input['centro_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de centro inválido']);
    exit;
}

$centro_id = (int)$input['centro_id'];
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de empresa no válido']);
    exit;
}

try {
    $centro = new Centro();
    
    // Handle different actions
    $action = $input['action'] ?? 'delete';
    
    if ($action === 'check') {
        // Check if deletion is safe
        $resultado = $centro->verificarEliminacion($centro_id, $empresa_id);
        echo json_encode($resultado);
        
    } elseif ($action === 'delete') {
        // Perform the deletion
        $resultado = $centro->eliminarCentro($centro_id, $empresa_id);
        
        if ($resultado['success']) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
        echo json_encode($resultado);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    error_log("Error in ajax_delete_centro.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error interno del servidor'
    ]);
}
?> 