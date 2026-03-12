<?php
// Initialize app (session, subdomain routing, etc.)
require_once __DIR__ . '/../shared/utils/app_init.php';

// Incluir archivos necesarios
require_once __DIR__ . '/../shared/models/Trabajador.php';
require_once __DIR__ . '/../shared/models/GruposHorarios.php';
require_once __DIR__ . '/../shared/validators/GrupoHorarioValidator.php';
require_once __DIR__ . '/../shared/components/MenuHelper.php';
require_once __DIR__ . '/../shared/components/MultiSelect.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/layouts/BaseLayout.php';
require_once __DIR__ . '/../shared/components/Breadcrumb.php';
require_once __DIR__ . '/../assets/css/components.php';
require_once __DIR__ . '/../shared/forms/GrupoHorarioFijoForm.php';

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

// Inicializar variables
$errors = [];
$form_data = [
    'nombre' => '',
    'descripcion' => '',
    'empleados' => [],
    'horarios' => [
        ['meses' => [], 'dia' => '', 'hora_entrada' => '', 'hora_salida' => '']
    ]
];

// Inicializar clase GruposHorarios
$gruposHorarios = new GruposHorarios();

// Obtener empleados de la empresa
$empleados = obtenerEmpleadosEmpresa($empresa_id);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = procesarFormularioFijo($_POST);
    
    // Validar datos
    $errors = GrupoHorarioValidator::validarHorarioFijo($form_data);
    
    if (empty($errors)) {
        // Crear grupo horario usando la clase centralizada
        $resultado = $gruposHorarios->crearGrupoHorarioFijo($form_data, $empresa_id);
        
        if ($resultado['success']) {
            header('Location: grupos_horarios.php?success=grupo_creado');
            exit;
        } else {
            $errors['general'] = 'Error al crear el grupo horario: ' . $resultado['error'];
        }
    }
}

/**
 * Obtener empleados de la empresa
 */
function obtenerEmpleadosEmpresa($empresa_id) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT id, nombre_completo, rol 
            FROM trabajador 
            WHERE empresa_id = ? AND activo = 1 AND sistema = 0 
            ORDER BY nombre_completo ASC
        ");
        $stmt->execute([$empresa_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error al obtener empleados: " . $e->getMessage());
        return [];
    }
}

/**
 * Procesar datos del formulario
 */
function procesarFormularioFijo($post_data) {
    $data = [
        'nombre' => trim($post_data['nombre'] ?? ''),
        'descripcion' => trim($post_data['descripcion'] ?? ''),
        'empleados' => $post_data['empleados'] ?? [],
        'horarios' => []
    ];
    
    // Procesar horarios
    if (isset($post_data['horarios']) && is_array($post_data['horarios'])) {
        foreach ($post_data['horarios'] as $horario) {
            $data['horarios'][] = [
                'meses' => is_array($horario['meses'] ?? []) ? $horario['meses'] : [],
                'dia' => trim($horario['dia'] ?? ''),
                'hora_entrada' => trim($horario['hora_entrada'] ?? ''),
                'hora_salida' => trim($horario['hora_salida'] ?? '')
            ];
        }
    }
    
    // Asegurar al menos un horario vacío si no hay ninguno
    if (empty($data['horarios'])) {
        $data['horarios'] = [['meses' => [], 'dia' => '', 'hora_entrada' => '', 'hora_salida' => '']];
    }
    
    return $data;
}

// Preparar datos de usuario para el layout
$user_data = [
    'nombre' => $nombre_trabajador,
    'correo' => $correo_trabajador,
    'rol' => $rol_trabajador
];

// Función para renderizar el contenido
function renderAddGrupoHorarioFijoContent($form_data, $errors, $empleados) {
    ob_start();
    ?>
    <!-- Breadcrumb -->
    <?php 
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Grupos Horarios', 'url' => '/app/grupos_horarios.php'],
        ['label' => 'Añadir Grupo Horario Fijo']
    ]); 
    ?>

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Añadir Nuevo Grupo de Horario Fijo</h1>
        <p class="text-gray-600 mt-1">Configure un nuevo grupo de horarios fijos para su empresa</p>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 <?php echo CSSComponents::getCardClasses('error'); ?>">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-red-500 mr-3 mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="text-red-800 font-medium mb-2">Se encontraron errores en el formulario:</h3>
                    <ul class="text-red-700 text-sm space-y-1">
                        <?php foreach ($errors as $field => $error): ?>
                            <li>• <?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <?php echo GrupoHorarioFijoForm::render($form_data, $errors, $empleados, 'create'); ?>
    

    <!-- JavaScript -->
    <?php MultiSelect::renderScript(); ?>
    
    <script>
        // Form validation and dynamic functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeFormValidation();
            initializeDynamicHorarios();
        });

        function initializeFormValidation() {
            // The form component already handles its own validation
            // Just add any additional validation if needed
        }

        function initializeDynamicHorarios() {
            // The form component already handles dynamic functionality
        }
    </script>
    <?php
    return ob_get_clean();
}

// Renderizar el contenido
$content = renderAddGrupoHorarioFijoContent($form_data, $errors, $empleados);

// Usar el BaseLayout para renderizar la página completa
BaseLayout::render('Añadir Grupo Horario Fijo', $content, $config_empresa, $user_data);
?> 