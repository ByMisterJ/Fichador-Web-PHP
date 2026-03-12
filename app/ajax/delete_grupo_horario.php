<?php
// Initialize app context (includes session_start and subdomain routing)
require_once __DIR__ . '/../../shared/utils/app_init.php';

require_once __DIR__ . '/../../shared/models/Trabajador.php';
require_once __DIR__ . '/../../shared/models/GruposHorarios.php';

// Set content type to JSON
header('Content-Type: application/json');

// Verify authentication
if (!Trabajador::estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Verify permissions (only admin and supervisor)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (!in_array(strtolower($rol_trabajador), ['administrador', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos suficientes']);
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

if (!isset($input['grupo_id']) || !is_numeric($input['grupo_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de grupo horario inválido']);
    exit;
}

$grupo_id = (int) $input['grupo_id'];
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de empresa no válido']);
    exit;
}

try {
    $gruposHorarios = new GruposHorarios();

    // Handle different actions
    $action = $input['action'] ?? 'delete';

    if ($action === 'check') {
        // Check if deletion is safe
        $resultado = $gruposHorarios->verificarEliminacion($grupo_id, $empresa_id);
        echo json_encode($resultado);

    } elseif ($action === 'delete') {
        // Perform the deletion
        $resultado = $gruposHorarios->eliminarGrupoHorario($grupo_id, $empresa_id);

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
    error_log("Error in ajax_delete_grupo_horario.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>