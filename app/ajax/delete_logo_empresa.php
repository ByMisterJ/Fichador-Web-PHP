<?php
/**
 * AJAX endpoint for deleting company logos
 */

// Initialize app context (includes session_start and subdomain routing)
require_once __DIR__ . '/../../shared/utils/app_init.php';

// Set JSON content type
header('Content-Type: application/json');

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verify authentication
require_once __DIR__ . '/../../shared/models/Trabajador.php';
if (!Trabajador::estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verify permissions - only administrators can delete company logos
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (strtolower($rol_trabajador) !== 'administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden eliminar el logo de la empresa']);
    exit();
}

try {
    // Include required files
    require_once __DIR__ . '/../../shared/models/Empresa.php';
    
    // Get session data
    $empresa_id = $_SESSION['empresa_id'] ?? null;
    
    if (!$empresa_id) {
        echo json_encode(['success' => false, 'error' => 'Empresa no encontrada']);
        exit();
    }
    
    // Delete company logo
    $empresa = new Empresa();
    $result = $empresa->deleteCompanyLogo($empresa_id);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => $result['message']]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    
} catch (Exception $e) {
    error_log("Error deleting company logo: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}
?> 