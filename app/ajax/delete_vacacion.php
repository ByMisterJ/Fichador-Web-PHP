<?php
/**
 * AJAX endpoint for deleting vacation requests
 */

// Initialize app context (includes session_start and subdomain routing)
require_once __DIR__ . '/../../shared/utils/app_init.php';

require_once __DIR__ . '/../../shared/models/Trabajador.php';
require_once __DIR__ . '/../../shared/models/Vacaciones.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Verify authentication
    if (!Trabajador::estaLogueado()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de vacación requerido']);
        exit;
    }

    $vacacion_id = (int)$input['id'];
    $empresa_id = $_SESSION['empresa_id'] ?? null;
    $trabajador_id = $_SESSION['id_trabajador'] ?? null;
    $rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';

    if (!$empresa_id || !$trabajador_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Datos de sesión inválidos']);
        exit;
    }

    // Initialize Vacaciones class
    $vacaciones = new Vacaciones();

    // Get vacation details with centro info to verify permissions
    $vacacion = $vacaciones->obtenerVacacionConCentro($vacacion_id, $empresa_id);
    
    if (!$vacacion) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Vacación no encontrada']);
        exit;
    }

    // Permission check
    $can_delete = false;
    
    if (in_array(strtolower($rol_trabajador), ['administrador'])) {
        // Administrators can delete any vacation
        $can_delete = true;
    } elseif (strtolower($rol_trabajador) === 'supervisor') {
        // Supervisors can delete vacations from their centro
        $centro_id_supervisor = $_SESSION['centro_id'] ?? null;
        if ($centro_id_supervisor && $vacacion['centro_id'] == $centro_id_supervisor) {
            $can_delete = true;
        }
    } elseif ($vacacion['trabajador_id'] == $trabajador_id) {
        // Employees can delete their own pending vacations
        if (strtolower($vacacion['estado']) === 'pendiente') {
            $can_delete = true;
        }
    }

    if (!$can_delete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para eliminar esta vacación']);
        exit;
    }

    // Delete the vacation
    $resultado = $vacaciones->eliminarVacacion($vacacion_id, $empresa_id);

    if ($resultado['success']) {
        echo json_encode(['success' => true, 'message' => 'Vacación eliminada correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $resultado['error']]);
    }

} catch (Exception $e) {
    error_log("Error in delete_vacacion.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}
?> 