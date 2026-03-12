<?php
// Initialize app (session, subdomain routing, etc.)
require_once __DIR__ . '/../shared/utils/app_init.php';

// Incluir archivos necesarios
require_once __DIR__ . '/../shared/models/Trabajador.php';
require_once __DIR__ . '/../shared/models/Empresa.php';
require_once __DIR__ . '/../shared/components/MenuHelper.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/layouts/BaseLayout.php';
require_once __DIR__ . '/../shared/components/Breadcrumb.php';
require_once __DIR__ . '/../shared/components/Toggle.php';
require_once __DIR__ . '/../assets/css/components.php';

// Verificar autenticación
if (!Trabajador::estaLogueado()) {
    header('Location: /app/login.php');
    exit;
}

// Verificar que el usuario sea administrador
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (strtolower($rol_trabajador) !== 'administrador') {
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

// Inicializar clase Empresa
$empresa = new Empresa();

// Obtener configuración actual de incidencias
$incidencias_config = $empresa->getIncidenciaConfiguration($empresa_id);

// Definir motivos de incidencia disponibles
$motivos_incidencia = [
    'incidencia_horario_fijo' => 'Fichaje fuera del horario fijo establecido',
    'incidencia_zona_gps' => 'Fichaje hecho fuera de la Zona GPS activada',
    'incidencia_horas_extra_ventana' => 'Horas extras del día exceden del grupo ventana',
    'incidencia_sin_horario' => 'No tiene horario establecido',
    'incidencia_gps_desactivado' => 'Tiene Zona GPS activada pero el GPS está desactivado'
];

$errors = [];
$success_message = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar configuración
    $form_data = [];
    foreach (array_keys($motivos_incidencia) as $motivo) {
        $form_data[$motivo] = isset($_POST[$motivo]) && $_POST[$motivo] == '1' ? 1 : 0;
    }
    
    // Actualizar configuración
    $resultado = $empresa->updateIncidenciaConfiguration($empresa_id, $form_data);
    
    if ($resultado['success']) {
        $success_message = $resultado['message'];
        // Actualizar la configuración local
        $incidencias_config = $form_data;
    } else {
        $errors['general'] = $resultado['error'];
    }
}

// Preparar datos de usuario para el layout
$user_data = [
    'nombre' => $nombre_trabajador,
    'correo' => $correo_trabajador,
    'rol' => $rol_trabajador
];

// Función para renderizar el contenido de gestión de motivos de incidencia
function renderIncidenciasMotivosContent($motivos_incidencia, $incidencias_config, $errors, $success_message) {
    
    ob_start();
    ?>
    
    <!-- Breadcrumb -->
    <?php 
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Búsqueda de Fichajes', 'url' => '/app/fichajes.php'],
        ['label' => 'Motivos de Incidencia']
    ]); 
    ?>

    <!-- Success Message -->
    <?php if (!empty($success_message)): ?>
        <div class="mb-6 p-4 <?php echo CSSComponents::getCardClasses('success'); ?>">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <div>
                    <h3 class="text-green-800 font-medium">¡Configuración actualizada!</h3>
                    <p class="text-green-700 text-sm mt-1"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (!empty($errors['general'])): ?>
        <div class="mb-6 p-4 <?php echo CSSComponents::getCardClasses('error'); ?>">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                <div>
                    <h3 class="text-red-800 font-medium">Error</h3>
                    <p class="text-red-700 text-sm mt-1"><?php echo htmlspecialchars($errors['general']); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Gestión de Motivos de Incidencia</h2>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-yellow-500 mr-3 mt-0.5"></i>
                <div>
                    <p class="text-yellow-800 text-sm">
                        <strong>¿Para qué sirve esta pantalla?</strong><br>
                        Aquí puedes gestionar los motivos de incidencia que se asignan automáticamente en los fichajes. 
                        Puedes activarlos o desactivarlos sin eliminarlos. Los motivos desactivados no se 
                        aplicarán en futuros fichajes.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Form -->
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-6">
        <form method="POST" id="incidenciasForm" class="space-y-6">
            <div class="space-y-4">
                <?php foreach ($motivos_incidencia as $key => $descripcion): ?>
                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <?php
                        echo Toggle::render(
                            $key, 
                            $descripcion,
                            ($incidencias_config[$key] ?? 1) == 1
                        );
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="fichajes.php" class="<?php echo CSSComponents::getButtonClasses('outline', 'md'); ?>">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Volver a Fichajes
                </a>
                <button type="submit" class="inline-flex items-center justify-center font-medium rounded-lg transition-all duration-200 focus:ring-4 focus:outline-none bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm">
                    <i class="fas fa-save mr-2"></i>
                    Guardar Configuración
                </button>
            </div>
        </form>
    </div>

    <!-- Toggle Component Script -->
    <?php echo Toggle::renderScript(); ?>

    <?php
    return ob_get_clean();
}

// Renderizar la página
$content = renderIncidenciasMotivosContent($motivos_incidencia, $incidencias_config, $errors, $success_message);

BaseLayout::render('Gestión de Motivos de Incidencia', $content, $config_empresa, $user_data);
?> 