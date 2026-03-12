<?php
// Initialize app (session, subdomain routing, etc.)
require_once __DIR__ . '/../shared/utils/app_init.php';

// Incluir archivos necesarios
require_once __DIR__ . '/../shared/models/Trabajador.php';
require_once __DIR__ . '/../shared/models/Fichajes.php';
require_once __DIR__ . '/../shared/components/MenuHelper.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/layouts/BaseLayout.php';
require_once __DIR__ . '/../shared/components/Breadcrumb.php';
require_once __DIR__ . '/../assets/css/components.php';

// Verificar autenticación
if (!Trabajador::estaLogueado()) {
    header('Location: /app/login.php');
    exit;
}

// Verificar que la funcionalidad esté habilitada
$config_empresa = Trabajador::obtenerConfiguracionEmpresa();
if (!$config_empresa['empleados_ver_fichajes']) {
    header('Location: /app/dashboard.php');
    exit;
}

// Solo permitir acceso a empleados (por ahora)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (strtolower($rol_trabajador) !== 'empleado') {
    header('Location: /app/dashboard.php');
    exit;
}

// Obtener datos del trabajador de la sesión
$nombre_trabajador = $_SESSION['nombre_trabajador'] ?? 'Trabajador';
$correo_trabajador = $_SESSION['correo_trabajador'] ?? 'N/A';
$trabajador_id = $_SESSION['id_trabajador'] ?? null;

// Verificar que tenemos el ID del trabajador
if (!$trabajador_id) {
    header('Location: /app/login.php');
    exit;
}

// Inicializar clase Fichajes
$fichajes = new Fichajes();

// Calcular fechas por defecto (primer y último día del mes actual)
$primer_dia_mes = date('Y-m-01');
$ultimo_dia_mes = date('Y-m-t');

// Obtener filtros de la URL con valores por defecto
$filtros = [
    'hora_desde' => $_GET['hora_desde'] ?? '',
    'hora_hasta' => $_GET['hora_hasta'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? $primer_dia_mes,
    'fecha_hasta' => $_GET['fecha_hasta'] ?? $ultimo_dia_mes
];

// Obtener fichajes del trabajador
$fichajes_list = $fichajes->obtenerFichajesPorTrabajador($trabajador_id, $filtros);

// Ensure array is not null
$fichajes_list = $fichajes_list ?? [];

// Contar estadísticas básicas
$total_registros = count($fichajes_list);
$fechas_unicas = count(array_unique(array_column($fichajes_list, 'fecha')));

// Preparar datos de usuario para el layout
$user_data = [
    'nombre' => $nombre_trabajador,
    'correo' => $correo_trabajador,
    'rol' => $rol_trabajador
];

// Función para formatear sesiones simplificada (sin incidencias)
function formatearSesionesSimples($sesiones, $config_empresa = null) {
    if (empty($sesiones)) {
        return '<span class="text-gray-400">Sin sesiones</span>';
    }
    
    $html = '';
    
    foreach ($sesiones as $index => $sesion) {
        if ($index > 0) {
            $html .= '<br><hr class="my-2 border-gray-200"><br>';
        }
        
        $hora_inicio = $sesion['hora_inicio_sesion'];
        $hora_fin = $sesion['hora_fin_sesion'] ?: '--:--';
        $estado = $sesion['estado'];
        $fichaje_id = $sesion['id'];
        
        // Sesión simple sin destacar incidencias
        $html .= '<div class="mb-1 flex items-start justify-between">';
        $html .= '<div class="flex-1">';
        $html .= '<span class="text-green-600 text-sm">Entrada: ' . $hora_inicio . '</span><br>';
        $html .= '<span class="text-red-600 text-sm">Salida: ' . $hora_fin . '</span>';
        
        if ($estado === 'iniciada') {
            $html .= '<br><span class="text-blue-500 text-xs font-medium">(Sesión activa)</span>';
        } elseif ($estado === 'finalizada' && isset($sesion['duracion_formateada'])) {
            $html .= '<br><span class="text-gray-500 text-xs">Duración: ' . $sesion['duracion_formateada'] . '</span>';
        }
        $html .= '</div>';
        
        // Agregar el icono de ojo para ver detalles (solo si está permitido)
        if (isset($config_empresa['empleados_detalles_fichajes']) && $config_empresa['empleados_detalles_fichajes']) {
            $html .= '<div class="ml-2 flex-shrink-0">';
            $html .= '<a href="mis-fichajes-view.php?id=' . htmlspecialchars($fichaje_id) . '" ';
            $html .= 'class="inline-flex items-center justify-center w-6 h-6 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-full transition-colors duration-200" ';
            $html .= 'title="Ver detalles de la sesión ' . ($index + 1) . '">';
            $html .= '<i class="fas fa-eye text-xs"></i>';
            $html .= '</a>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    return $html;
}

// Función para renderizar el contenido de mis fichajes
function renderMisFichajesContent($fichajes_list, $filtros, $total_registros, $fechas_unicas, $config_empresa) {
    ob_start();
    ?>
    
    <!-- Breadcrumb -->
    <?php 
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Mis Fichajes']
    ]); 
    ?>

    <!-- Mensaje de error si se intenta acceder sin permisos -->
    <?php if (isset($_GET['error']) && $_GET['error'] === 'detalles_no_permitidos'): ?>
        <div class="mb-6 p-4 <?php echo CSSComponents::getCardClasses('warning'); ?>">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-yellow-500 mr-3 mt-0.5"></i>
                <div class="flex-1">
                    <h3 class="text-yellow-800 font-medium mb-1">Acceso no permitido</h3>
                    <p class="text-yellow-700 text-sm">No tienes permisos para ver los detalles de los fichajes. Contacta con tu administrador si necesitas acceso a esta funcionalidad.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Mis Fichajes</h1>
                <p class="text-gray-600 mt-1">Consulta y filtra tus registros de entrada y salida</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-6 mb-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Filtros</h3>
        
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Hora Desde -->
                <div>
                    <label for="hora_desde" class="<?php echo CSSComponents::getLabelClasses(); ?>">Hora Desde</label>
                    <input 
                        type="time" 
                        name="hora_desde" 
                        id="hora_desde"
                        value="<?php echo htmlspecialchars($filtros['hora_desde']); ?>"
                        class="<?php echo CSSComponents::getInputClasses(); ?>"
                    >
                </div>

                <!-- Hora Hasta -->
                <div>
                    <label for="hora_hasta" class="<?php echo CSSComponents::getLabelClasses(); ?>">Hora Hasta</label>
                    <input 
                        type="time" 
                        name="hora_hasta" 
                        id="hora_hasta"
                        value="<?php echo htmlspecialchars($filtros['hora_hasta']); ?>"
                        class="<?php echo CSSComponents::getInputClasses(); ?>"
                    >
                </div>

                <!-- Fecha Desde -->
                <div>
                    <label for="fecha_desde" class="<?php echo CSSComponents::getLabelClasses(); ?>">Desde</label>
                    <input 
                        type="date" 
                        name="fecha_desde" 
                        id="fecha_desde"
                        value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>"
                        class="<?php echo CSSComponents::getInputClasses(); ?>"
                    >
                </div>

                <!-- Fecha Hasta -->
                <div>
                    <label for="fecha_hasta" class="<?php echo CSSComponents::getLabelClasses(); ?>">Hasta</label>
                    <input 
                        type="date" 
                        name="fecha_hasta" 
                        id="fecha_hasta"
                        value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>"
                        class="<?php echo CSSComponents::getInputClasses(); ?>"
                    >
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="submit" class="<?php echo CSSComponents::getButtonClasses('primary', 'md'); ?>">
                    <i class="fas fa-filter mr-2"></i>
                    Aplicar Filtros
                </button>
                <a href="mis-fichajes.php" class="<?php echo CSSComponents::getButtonClasses('outline', 'md'); ?>">
                    <i class="fas fa-times mr-2"></i>
                    Borrar Filtros
                </a>
            </div>
        </form>
    </div>

    <!-- Results Message -->
    <?php if (!empty($_GET) && empty($fichajes_list)): ?>
        <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-8 mb-6 text-center">
            <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No se encontraron resultados</h3>
            <p class="text-gray-600">
                No hay fichajes que coincidan con los filtros seleccionados. 
                Intenta ajustar los criterios de búsqueda.
            </p>
        </div>
    <?php endif; ?>

    <!-- Fichajes Table -->
    <?php if (!empty($fichajes_list)): ?>
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?> overflow-hidden">
        <div class="overflow-x-auto">
            <table class="<?php echo CSSComponents::getTableClasses(); ?>">
                <thead class="<?php echo CSSComponents::getTableHeaderClasses(); ?>">
                    <tr>
                        <th class="<?php echo CSSComponents::getTableCellClasses(); ?>">
                            Nombre Empleado
                        </th>
                        <th class="<?php echo CSSComponents::getTableCellClasses(); ?>">
                            DNI
                        </th>
                        <th class="<?php echo CSSComponents::getTableCellClasses(); ?>">
                            Día
                        </th>
                        <th class="<?php echo CSSComponents::getTableCellClasses(); ?>">
                            Horarios
                        </th>
                        <th class="<?php echo CSSComponents::getTableCellClasses(); ?> text-center">
                            Total Horas
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($fichajes_list as $fichaje): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="<?php echo CSSComponents::getTableCellClasses(); ?> align-top py-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 h-8 w-8">
                                    <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-xs font-medium text-gray-700">
                                            <?php echo strtoupper(substr($fichaje['nombre_completo'], 0, 2)); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($fichaje['nombre_completo']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($fichaje['nombre_trabajador']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="<?php echo CSSComponents::getTableCellClasses(); ?> align-top py-6">
                            <span class="text-sm font-mono text-gray-900">
                                <?php echo htmlspecialchars($fichaje['dni']); ?>
                            </span>
                        </td>
                        <td class="<?php echo CSSComponents::getTableCellClasses(); ?> align-top py-6">
                            <span class="text-sm text-gray-900">
                                <?php echo $fichaje['fecha_formateada']; ?>
                            </span>
                        </td>
                        <td class="<?php echo CSSComponents::getTableCellClasses(); ?> align-top py-6">
                            <div class="text-sm">
                                <?php echo formatearSesionesSimples($fichaje['sesiones'], $config_empresa); ?>
                            </div>
                        </td>
                        <td class="<?php echo CSSComponents::getTableCellClasses(); ?> text-center align-top py-6">
                            <span class="text-sm font-medium text-gray-900">
                                <?php echo $fichaje['total_horas']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Footer with Summary -->
        <div class="bg-gray-50 px-6 py-3 border-t border-gray-200">
            <div class="flex items-center justify-between text-sm text-gray-600">
                <div>
                    Mostrando <?php echo number_format($total_registros); ?> registro(s) 
                    en <?php echo number_format($fechas_unicas); ?> día(s)
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}

// Renderizar la página
$content = renderMisFichajesContent($fichajes_list, $filtros, $total_registros, $fechas_unicas, $config_empresa);

BaseLayout::render('Mis Fichajes', $content, $config_empresa, $user_data);
?> 