<?php
// Initialize app (session, subdomain routing, etc.)
require_once __DIR__ . '/../shared/utils/app_init.php';

// Verificar autenticación
require_once __DIR__ . '/../shared/models/Trabajador.php';
if (!Trabajador::estaLogueado()) {
    header('Location: /app/login.php');
    exit;
}

// Incluir archivos necesarios
require_once __DIR__ . '/../shared/models/Centro.php';
require_once __DIR__ . '/../shared/models/Empresa.php';
require_once __DIR__ . '/../shared/validators/CentroValidator.php';
require_once __DIR__ . '/../shared/components/MenuHelper.php';
require_once __DIR__ . '/../shared/components/MultiSelect.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/layouts/BaseLayout.php';
require_once __DIR__ . '/../shared/components/Breadcrumb.php';
require_once __DIR__ . '/../assets/css/components.php';
require_once __DIR__ . '/../shared/forms/CentroForm.php';

// Verificar permisos (solo Administradores)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (strtolower($rol_trabajador) !== 'administrador') {
    header('Location: /app/dashboard.php');
    exit;
}

// Datos del usuario
$nombre_trabajador = $_SESSION['nombre_trabajador'] ?? 'Trabajador';
$correo_trabajador = $_SESSION['correo_trabajador'] ?? 'N/A';
$trabajador_id = $_SESSION['id_trabajador'] ?? null;
$empresa_id = $_SESSION['empresa_id'] ?? null;

// Obtener ID del centro
$centro_id = $_GET['id'] ?? null;
if (!$centro_id || !is_numeric($centro_id)) {
    header('Location: centros.php?error=invalid_id');
    exit;
}

// Inicializar clases
$centro = new Centro();
$trabajador = new Trabajador();
$empresa = new Empresa();

// Obtener configuración de la empresa usando el método de la clase Empresa
$config_empresa = $empresa->obtenerEmpresaPorId($empresa_id);

if (!$config_empresa) {
    die('Error: No se pudo cargar la configuración de la empresa.');
}

// Variables de estado
$errores = [];
$form_data = [];

// Cargar datos del centro usando método centralizado
$resultado_datos = $centro->cargarDatosCentro($centro_id, $empresa_id);
if (!$resultado_datos['success']) {
    header('Location: centros.php?error=not_found');
    exit;
}

$form_data = $resultado_datos['data'];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar datos del formulario usando método centralizado
    $datos_form = Centro::procesarFormularioCentro($_POST, $empresa_id);
    
    // Validar datos del centro
    $errores = CentroValidator::validarCentroEdicion($datos_form, $centro, $empresa_id, $centro_id);
    
    // Validar que los empleados existen
    if (empty($errores) && !empty($datos_form['empleados_asignados'])) {
        $errores_empleados = CentroValidator::validarEmpleadosExisten(
            $datos_form['empleados_asignados'], 
            $trabajador, 
            $empresa_id
        );
        $errores = array_merge($errores, $errores_empleados);
    }
    
    if (empty($errores)) {
        // Actualizar centro
        $resultado = $centro->actualizarCentro($centro_id, $datos_form, $empresa_id);
        
        if ($resultado['success']) {
            header('Location: centros.php?success=updated');
            exit;
        } else {
            $errores['general'] = 'Error al actualizar el centro: ' . $resultado['error'];
        }
    } else {
        // Si hay errores, actualizar $form_data con los valores del formulario para mostrarlos
        $form_data = array_merge($form_data, $datos_form);
    }
}

/**
 * Obtener empleados para el multiselect
 */
function obtenerEmpleados() {
    global $trabajador, $empresa_id;
    
    try {
        return $trabajador->obtenerTodosTrabajadoresEmpresa($empresa_id);
    } catch (Exception $e) {
        error_log("Error al obtener empleados: " . $e->getMessage());
        return [];
    }
}

/**
 * Renderizar contenido principal
 */
function renderContent() {
    global $errores, $form_data, $config_empresa, $centro_id, $centro, $empresa_id;
    
    ob_start();
    ?>
    <!-- Breadcrumb -->
    <?php
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Centros', 'url' => '/app/centros.php'],
        ['label' => 'Editar centro']
    ]);
    ?>

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Editar centro</h1>
        <p class="mt-2 text-gray-600">Modifique los campos necesarios para actualizar la información del centro.</p>
    </div>

    <!-- Form Card -->
    <div>
        <?php
        // Obtener empleados para el formulario
        $empleados = obtenerEmpleados();
        
        // Obtener documento de la empresa usando el método de la clase Centro
        $documento_empresa = $centro->obtenerDocumentoEmpresa($empresa_id);
        
        // Preparar opciones
        $options = [
            'empleados' => $empleados,
            'documento_empresa' => $documento_empresa
        ];
        
        // Renderizar formulario en modo edición
        CentroForm::render($form_data, $errores, $options, 'edit', $centro_id);
        ?>
    </div>

    <?php CentroForm::renderScript(); ?>
    <?php
    return ob_get_clean();
}

// Preparar datos del usuario para el layout
$user_data = [
    'nombre' => $nombre_trabajador,
    'correo' => $correo_trabajador,
    'rol' => $rol_trabajador
];

// Renderizar página usando BaseLayout
BaseLayout::render('Editar Centro', renderContent(), $config_empresa, $user_data);
?> 