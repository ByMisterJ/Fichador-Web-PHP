<?php
// Initialize app (session, subdomain routing, etc.)
require_once __DIR__ . '/../shared/utils/app_init.php';

// Incluir archivos necesarios
require_once __DIR__ . '/../shared/models/Trabajador.php';
require_once __DIR__ . '/../shared/models/Fichajes.php';
require_once __DIR__ . '/../shared/validators/FichajeValidator.php';
require_once __DIR__ . '/../shared/components/MenuHelper.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/layouts/BaseLayout.php';
require_once __DIR__ . '/../shared/components/Breadcrumb.php';
require_once __DIR__ . '/../assets/css/components.php';
require_once __DIR__ . '/../shared/forms/FichajeForm.php';

// Verificar autenticación
if (!Trabajador::estaLogueado()) {
    header('Location: /app/login.php');
    exit;
}

// Verificar que el usuario tenga permisos (solo administradores y supervisores)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (!in_array(strtolower($rol_trabajador), ['administrador', 'supervisor'])) {
    header('Location: /app/dashboard.php');
    exit;
}

// Obtener datos del trabajador de la sesión
$nombre_trabajador = $_SESSION['nombre_trabajador'] ?? 'Trabajador';
$correo_trabajador = $_SESSION['correo_trabajador'] ?? 'N/A';
$trabajador_id = $_SESSION['id_trabajador'] ?? null;
$empresa_id = $_SESSION['empresa_id'] ?? null;

// Obtener configuración de la empresa
$config_empresa = Trabajador::obtenerConfiguracionEmpresa();

// Verificar que se proporcione el ID del fichaje
$fichaje_id = $_GET['id'] ?? null;
if (!$fichaje_id || !is_numeric($fichaje_id)) {
    header('Location: fichajes.php?error=invalid_id');
    exit;
}

// Inicializar clases
$fichajes = new Fichajes();

// Cargar datos del fichaje para edición
$fichaje_data = $fichajes->cargarDatosFichajeParaEdicion($fichaje_id, $empresa_id);
if (!$fichaje_data) {
    header('Location: fichajes.php?error=not_found');
    exit;
}

// Verificar permisos específicos
$permisos = FichajeValidator::validarPermisosEdicion($fichaje_data['empresa_id'], $empresa_id, $rol_trabajador);
if (!$permisos['success']) {
    header('Location: fichajes.php?error=no_permissions');
    exit;
}

// Variables para el formulario
$errors = [];
$form_data = $fichaje_data;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = procesarFormularioFichaje($_POST);
    
    // Validar datos
    $errors = FichajeValidator::validarFichajeEdicion($form_data);
    
    if (empty($errors)) {
        // Actualizar fichaje
        $resultado = $fichajes->actualizarFichaje(
            $fichaje_id, 
            $form_data, 
            $trabajador_id, 
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
        
        if ($resultado['success']) {
            header('Location: fichaje-view.php?id=' . $fichaje_id . '&success=updated');
            exit;
        } else {
            $errors['general'] = 'Error: ' . $resultado['error'];
        }
    }
}

/**
 * Procesar datos del formulario
 */
function procesarFormularioFichaje($post_data) {
    return [
        'fecha_inicio_sesion' => trim($post_data['fecha_inicio_sesion'] ?? ''),
        'hora_inicio_sesion' => trim($post_data['hora_inicio_sesion'] ?? ''),
        'fecha_fin_sesion' => trim($post_data['fecha_fin_sesion'] ?? ''),
        'hora_fin_sesion' => trim($post_data['hora_fin_sesion'] ?? ''),
        'estado' => trim($post_data['estado'] ?? ''),
        'observaciones' => trim($post_data['observaciones'] ?? ''),
        'descripcion_cambio' => trim($post_data['descripcion_cambio'] ?? '')
    ];
}

// Preparar datos de usuario para el layout
$user_data = [
    'nombre' => $nombre_trabajador,
    'correo' => $correo_trabajador,
    'rol' => $rol_trabajador
];

// Función para renderizar el contenido
function renderContent() {
    global $fichaje_data, $form_data, $errors, $fichaje_id;
    
    ob_start();
    ?>
    
    <!-- Breadcrumb -->
    <?php 
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Fichajes', 'url' => '/app/fichajes.php'],
        ['label' => 'Detalle', 'url' => 'fichaje-view.php?id=' . $fichaje_id],
        ['label' => 'Editar Fichaje']
    ]); 
    ?>

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-edit text-blue-500 mr-3"></i>
                    Editar Fichaje
                </h1>
                <p class="text-gray-600 mt-1">
                    Modificar los datos de la sesión de <?php echo htmlspecialchars($fichaje_data['nombre_completo']); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Información de Advertencia -->
    <div class="mb-6 p-4 <?php echo CSSComponents::getCardClasses('warning'); ?>">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-yellow-500 mr-3 mt-0.5"></i>
            <div class="flex-1">
                <h3 class="text-yellow-800 font-medium mb-2">Atención</h3>
                <p class="text-yellow-700 text-sm">
                    Esta acción modificará permanentemente los datos del fichaje. 
                    Todos los cambios quedarán registrados en el historial de auditoría.
                </p>
            </div>
        </div>
    </div>

    <!-- Formulario de Edición -->
    <?php 
    FichajeForm::render($form_data, $errors, [], 'edit', $fichaje_id);
    ?>

    <?php
    return ob_get_clean();
}

// Renderizar la página
$content = renderContent();
BaseLayout::render('Editar Fichaje', $content, $config_empresa, $user_data);
?> 