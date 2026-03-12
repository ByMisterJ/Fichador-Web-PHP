<?php
// Initialize app context (includes session_start and subdomain routing)
require_once __DIR__ . '/../../shared/utils/app_init.php';

require_once __DIR__ . '/../../shared/models/Fichajes.php';
require_once __DIR__ . '/../../shared/models/Trabajador.php';

// Configurar headers para respuesta JSON
header('Content-Type: application/json');

// Verificar método de petición
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificar autenticación
if (!Trabajador::estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Obtener datos del trabajador desde la sesión
    $trabajador_id = $_SESSION['id_trabajador'];
    $empresa_id = $_SESSION['empresa_id'];
    $rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';

    // Validar que existan los datos necesarios
    if (!$trabajador_id || !$empresa_id) {
        throw new Exception('Datos de sesión incompletos');
    }

    // Obtener datos de la petición
    $input = json_decode(file_get_contents('php://input'), true);

    // Validar datos requeridos
    $fichaje_id = $input['fichaje_id'] ?? null;
    $anotacion = $input['anotacion'] ?? '';

    if (!$fichaje_id) {
        throw new Exception('ID de fichaje no proporcionado');
    }

    // Validar que el fichaje_id sea numérico
    if (!is_numeric($fichaje_id)) {
        throw new Exception('ID de fichaje inválido');
    }

    // Crear instancia del modelo Fichajes
    $fichajes = new Fichajes();

    // Si es empleado, verificar que el fichaje le pertenece
    if (strtolower($rol_trabajador) === 'empleado') {
        // Verificar que el fichaje pertenece al trabajador
        $fichaje_info = $fichajes->obtenerFichajePorId((int)$fichaje_id, $empresa_id);

        if (!$fichaje_info) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Fichaje no encontrado']);
            exit();
        }

        if ((int)$fichaje_info['trabajador_id'] !== (int)$trabajador_id) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para editar esta anotación'
            ]);
            exit();
        }
    }

    // Actualizar la anotación
    $resultado = $fichajes->actualizarAnotacion(
        (int)$fichaje_id,
        $anotacion,
        $empresa_id
    );

    if ($resultado['success']) {
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => $resultado['message'],
            'fichaje_id' => $fichaje_id,
            'anotacion' => $anotacion,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Error en la actualización
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $resultado['message'],
            'error_code' => 'UPDATE_FAILED'
        ]);
    }

} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en ajax/actualizar_anotacion.php: " . $e->getMessage());
    error_log("Request data: " . print_r($input ?? [], true));

    // Respuesta de error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage(),
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>
