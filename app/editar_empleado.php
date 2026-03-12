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

// Verificar rol (solo admin y supervisor pueden editar empleados)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (!in_array(strtolower($rol_trabajador), ['administrador', 'supervisor'])) {
    header('Location: /app/dashboard.php');
    exit;
}

// Verificar que se proporcione ID del empleado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /app/empleados.php?error=empleado_no_encontrado');
    exit;
}

// Inicializar clases
$trabajador = new Trabajador();
$gruposHorarios = new GruposHorarios();

// Obtener configuración de la empresa
$empresa_id = $_SESSION['empresa_id'];
$config_empresa = Trabajador::obtenerConfiguracionEmpresa();

$empleado_id = (int)$_GET['id'];
$errores = [];

// Cargar datos del empleado usando método centralizado
$resultado_datos = $trabajador->cargarDatosEmpleado($empleado_id, $empresa_id);
if (!$resultado_datos['success']) {
    header('Location: /app/empleados.php?error=empleado_no_encontrado');
    exit;
}

$datos = $resultado_datos['data'];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar datos del formulario usando método centralizado
    $datos_form = Trabajador::procesarFormularioEmpleado($_POST, $empresa_id);
    
    // Validar usando el validador centralizado
    $errores = EmpleadoValidator::validarEmpleadoEdicion($datos_form, $trabajador, $empresa_id, $empleado_id);
    
    if (empty($errores)) {
        // Actualizar empleado usando método centralizado
        $resultado = $trabajador->actualizarTrabajador($empleado_id, $datos_form);
        
        if ($resultado) {
            header('Location: /app/empleados.php?success=empleado_actualizado');
            exit;
        } else {
            $errores['general'] = 'Error al actualizar el empleado. Inténtalo de nuevo.';
        }
    } else {
        // Si hay errores, actualizar $datos con los valores del formulario para mostrarlos
        $datos = array_merge($datos, $datos_form);
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
function renderContent($datos, $errores, $opciones, $empleado_id) {
    ob_start();
    ?>
    <!-- Breadcrumb -->
    <?php 
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Empleados', 'url' => '/app/empleados.php'],
        ['label' => 'Editar empleado']
    ]); 
    ?>

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Editar empleado</h1>
        <p class="mt-2 text-gray-600">Modifique los campos necesarios para actualizar la información del empleado.</p>
    </div>

    <!-- Form Card -->
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?>">
        <?php EmpleadoForm::render($datos, $errores, $opciones, 'edit', $empleado_id); ?>
    </div>

    <?php EmpleadoForm::renderScript('edit'); ?>
    <?php
    return ob_get_clean();
}

// Renderizar el contenido
$content = renderContent($datos, $errores, $opciones, $empleado_id);

// Usar el BaseLayout para renderizar la página completa
BaseLayout::render('Editar empleado', $content, $config_empresa, $user_data);
?> 