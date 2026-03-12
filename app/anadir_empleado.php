<?php
// Initialize app (session, subdomain routing, etc.)
require_once __DIR__ . '/../shared/utils/app_init.php';

require_once __DIR__ . '/../shared/models/Trabajador.php';
require_once __DIR__ . '/../shared/models/GruposHorarios.php';
require_once __DIR__ . '/../shared/validators/EmpleadoValidator.php';
require_once __DIR__ . '/../shared/components/MenuHelper.php';
require_once __DIR__ . '/../shared/forms/EmpleadoForm.php';
require_once __DIR__ . '/../shared/layouts/BaseLayout.php';
require_once __DIR__ . '/../shared/components/Breadcrumb.php';
require_once __DIR__ . '/../assets/css/components.php';
require_once __DIR__ . '/../config/database.php';

// Verificar autenticación
if (!Trabajador::estaLogueado()) {
    header('Location: /app/login.php');
    exit;
}

// Verificar rol (solo admin y supervisor pueden añadir empleados)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (!in_array(strtolower($rol_trabajador), ['administrador', 'supervisor'])) {
    header('Location: /app/dashboard.php');
    exit;
}

// Inicializar clases
$trabajador = new Trabajador();
$gruposHorarios = new GruposHorarios();

// Obtener configuración de la empresa
$empresa_id = $_SESSION['empresa_id'];
$config_empresa = Trabajador::obtenerConfiguracionEmpresa();

$errores = [];
$datos = [];

// Manejar generación de PIN via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'generar_pin') {
    header('Content-Type: application/json');
    $pin_generado = $trabajador->generarPinUnico($empresa_id);
    echo json_encode(['pin' => $pin_generado]);
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar datos del formulario usando método centralizado
    $datos = Trabajador::procesarFormularioEmpleado($_POST, $empresa_id);
    
    // Validar usando el validador centralizado
    $errores = EmpleadoValidator::validarEmpleadoCreacion($datos, $trabajador, $empresa_id);
    
    if (empty($errores)) {
        // Crear empleado usando método centralizado
        $resultado = $trabajador->crearTrabajador($datos);
        
        if ($resultado) {
            header('Location: /app/empleados.php?success=empleado_creado');
            exit;
        } else {
            $errores['general'] = 'Error al crear el empleado. Inténtalo de nuevo.';
        }
    }
}

// Preparar opciones para el formulario
$opciones = [
    'grupos_horario' => $gruposHorarios->obtenerGruposHorarioParaSelect($empresa_id),
    'centros' => $trabajador->obtenerCentrosEmpresa($empresa_id),
    'rol_trabajador' => $rol_trabajador
];

// Obtener datos del trabajador de la sesión
$nombre_trabajador = $_SESSION['nombre_trabajador'] ?? 'Trabajador';
$correo_trabajador = $_SESSION['correo_trabajador'] ?? 'N/A';

// Preparar datos de usuario para el layout
$user_data = [
    'nombre' => $nombre_trabajador,
    'correo' => $correo_trabajador,
    'rol' => $rol_trabajador
];

// Función para renderizar el contenido
function renderContent($datos, $errores, $opciones) {
    ob_start();
    ?>
    <!-- Breadcrumb -->
    <?php 
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Empleados', 'url' => '/app/empleados.php'],
        ['label' => 'Añadir nuevo empleado']
    ]); 
    ?>

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Añadir nuevo empleado</h1>
        <p class="mt-2 text-gray-600">Complete todos los campos requeridos para registrar un nuevo empleado en el sistema.</p>
    </div>

    <!-- Form Card -->
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?>">
        <?php EmpleadoForm::render($datos, $errores, $opciones, 'create'); ?>
    </div>

    <?php EmpleadoForm::renderScript('create'); ?>
    <?php
    return ob_get_clean();
}

// Renderizar el contenido
$content = renderContent($datos, $errores, $opciones);

// Usar el BaseLayout para renderizar la página completa
BaseLayout::render('Añadir nuevo empleado', $content, $config_empresa, $user_data);
?> 