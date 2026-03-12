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
require_once __DIR__ . '/../shared/models/Fichajes.php';
require_once __DIR__ . '/../shared/models/Empresa.php';
require_once __DIR__ . '/../shared/components/MenuHelper.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../shared/layouts/BaseLayout.php';
require_once __DIR__ . '/../shared/components/Breadcrumb.php';
require_once __DIR__ . '/../assets/css/components.php';

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

// Inicializar clases
$fichajes = new Fichajes();
$trabajador = new Trabajador();
$empresa = new Empresa();

// Obtener configuración de la empresa
$config_empresa = Trabajador::obtenerConfiguracionEmpresa();

if (!$config_empresa) {
    die('Error: No se pudo cargar la configuración de la empresa.');
}

// Obtener parámetros de filtrado
$empleado_id = $_GET['empleado_id'] ?? null;
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes actual
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-t');   // Último día del mes actual

// Obtener empleados para el selector
$empleados = $trabajador->obtenerTodosTrabajadoresEmpresa($empresa_id);

// Obtener datos de ubicaciones usando el modelo Fichajes
$ubicaciones_data = $fichajes->obtenerUbicacionesFichajes($empresa_id, $empleado_id, $fecha_desde, $fecha_hasta);

/**
 * Renderizar contenido principal
 */
function renderContent() {
    global $empleados, $empleado_id, $fecha_desde, $fecha_hasta, $ubicaciones_data, $config_empresa;
    
    ob_start();
    ?>
    <!-- Breadcrumb -->
    <?php
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Empleados', 'url' => '/app/empleados.php'],
        ['label' => 'Mapa de ubicaciones']
    ]);
    ?>

    <!-- Page Header -->
    <div class="mb-8">
        <?php if ($empleado_id): ?>
            <?php 
            $empleado_seleccionado = array_filter($empleados, function($e) use ($empleado_id) { 
                return $e['id'] == $empleado_id; 
            });
            $empleado_seleccionado = reset($empleado_seleccionado);
            ?>
            <h1 class="text-3xl font-bold text-gray-900">
                Ubicaciones de <?php echo htmlspecialchars($empleado_seleccionado['nombre_completo']); ?>
            </h1>
            <p class="mt-2 text-gray-600">
                Visualiza las ubicaciones donde <strong><?php echo htmlspecialchars($empleado_seleccionado['nombre_trabajador']); ?></strong> ha registrado sus fichajes.
            </p>
        <?php else: ?>
            <h1 class="text-3xl font-bold text-gray-900">Mapa de ubicaciones de empleados</h1>
            <p class="mt-2 text-gray-600">Visualiza las ubicaciones donde los empleados han registrado sus fichajes.</p>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-4 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <!-- Empleado -->
            <div class="<?php echo CSSComponents::getFieldWrapperClasses(); ?> !mb-0 lg:col-span-2">
                <label for="empleado_id" class="<?php echo CSSComponents::getLabelClasses(); ?> text-sm">
                    Empleado
                </label>
                <select 
                    id="empleado_id" 
                    name="empleado_id"
                    class="<?php echo CSSComponents::getSelectClasses(); ?> text-sm"
                >
                    <option value="">Todos los empleados</option>
                    <?php foreach ($empleados as $empleado): ?>
                        <option value="<?php echo $empleado['id']; ?>" <?php echo $empleado_id == $empleado['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($empleado['nombre_completo'] . ' (' . $empleado['nombre_trabajador'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fecha Desde -->
            <div class="<?php echo CSSComponents::getFieldWrapperClasses(); ?> !mb-0">
                <label for="fecha_desde" class="<?php echo CSSComponents::getLabelClasses(); ?> text-sm">
                    Desde
                </label>
                <input 
                    type="date" 
                    id="fecha_desde" 
                    name="fecha_desde"
                    value="<?php echo htmlspecialchars($fecha_desde); ?>"
                    class="<?php echo CSSComponents::getInputClasses(); ?> text-sm"
                >
            </div>

            <!-- Fecha Hasta -->
            <div class="<?php echo CSSComponents::getFieldWrapperClasses(); ?> !mb-0">
                <label for="fecha_hasta" class="<?php echo CSSComponents::getLabelClasses(); ?> text-sm">
                    Hasta
                </label>
                <input 
                    type="date" 
                    id="fecha_hasta" 
                    name="fecha_hasta"
                    value="<?php echo htmlspecialchars($fecha_hasta); ?>"
                    class="<?php echo CSSComponents::getInputClasses(); ?> text-sm"
                >
            </div>

            <!-- Botón Aplicar Filtros -->
            <div>
                <button 
                    type="submit"
                    class="<?php echo CSSComponents::getButtonClasses('primary', 'sm'); ?> w-full"
                >
                    <i class="fas fa-search mr-1"></i>
                    <span class="hidden sm:inline">Aplicar</span>
                </button>
            </div>

            <!-- Botón Borrar Filtros -->
            <div>
                <a 
                    href="/app/admin_ubicaciones.php"
                    class="<?php echo CSSComponents::getButtonClasses('outline', 'sm'); ?> w-full text-center"
                >
                    <i class="fas fa-times mr-1"></i>
                    <span class="hidden sm:inline">Reset</span>
                </a>
            </div>
        </form>
    </div>

    <!-- Mapa -->
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-4 mb-6">
        <div id="map" style="height: 600px; position: relative; z-index: 1;" class="rounded-lg border border-gray-300"></div>
        
        <!-- Leyenda -->
        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs sm:text-sm">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-500 rounded-full mr-2 flex-shrink-0"></div>
                    <span class="text-gray-600">Entrada</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2 flex-shrink-0"></div>
                    <span class="text-gray-600">Salida</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-orange-500 rounded-full mr-2 flex-shrink-0"></div>
                    <span class="text-gray-600">Varios eventos</span>
                </div>
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-orange-700 rounded-full mr-2 flex-shrink-0"></div>
                    <span class="text-gray-600">Alta concentración</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-3">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mr-2">
                    <i class="fas fa-map-marker-alt text-blue-600 text-sm"></i>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900"><?php echo count($ubicaciones_data); ?></p>
                    <p class="text-xs text-gray-600">Ubicaciones</p>
                </div>
            </div>
        </div>
        
        <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-3">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center mr-2">
                    <i class="fas fa-sign-in-alt text-green-600 text-sm"></i>
                </div>
                <div>
                    <?php $entradas = array_filter($ubicaciones_data, function($u) { return $u['tipo'] === 'entrada'; }); ?>
                    <p class="text-lg font-bold text-gray-900"><?php echo count($entradas); ?></p>
                    <p class="text-xs text-gray-600">Entradas</p>
                </div>
            </div>
        </div>
        
        <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-3">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center mr-2">
                    <i class="fas fa-sign-out-alt text-red-600 text-sm"></i>
                </div>
                <div>
                    <?php $salidas = array_filter($ubicaciones_data, function($u) { return $u['tipo'] === 'salida'; }); ?>
                    <p class="text-lg font-bold text-gray-900"><?php echo count($salidas); ?></p>
                    <p class="text-xs text-gray-600">Salidas</p>
                </div>
            </div>
        </div>
        
        <div class="<?php echo CSSComponents::getCardClasses('default'); ?> p-3">
            <div class="flex items-center">
                <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center mr-2">
                    <i class="fas fa-users text-purple-600 text-sm"></i>
                </div>
                <div>
                    <?php $empleados_unicos = array_unique(array_column($ubicaciones_data, 'trabajador_id')); ?>
                    <p class="text-lg font-bold text-gray-900"><?php echo count($empleados_unicos); ?></p>
                    <p class="text-xs text-gray-600">Empleados</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Incluir Leaflet CSS y JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>

    <script>
        let map = null;
        let markersCluster = null;
        
        // Datos de ubicaciones desde PHP
        const ubicacionesData = <?php echo json_encode($ubicaciones_data); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
        });
        
        function initializeMap() {
            // Coordenadas por defecto (Castellón)
            const defaultLat = 39.987081;
            const defaultLng = -0.039908;
            
            // Inicializar mapa
            map = L.map('map').setView([defaultLat, defaultLng], 13);
            
            // Añadir capa de OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Inicializar cluster de marcadores
            markersCluster = L.markerClusterGroup({
                chunkedLoading: true,
                maxClusterRadius: 80
            });
            
            // Añadir marcadores
            addMarkers();
            
            // Añadir cluster al mapa
            map.addLayer(markersCluster);
            
            // Ajustar vista si hay datos
            if (ubicacionesData.length > 0) {
                const group = new L.featureGroup(markersCluster.getLayers());
                if (group.getBounds().isValid()) {
                    map.fitBounds(group.getBounds().pad(0.1));
                }
            }
        }
        
        function addMarkers() {
            ubicacionesData.forEach(function(ubicacion) {
                const lat = parseFloat(ubicacion.latitud);
                const lng = parseFloat(ubicacion.longitud);
                
                if (isNaN(lat) || isNaN(lng)) return;
                
                                 // Crear icono basado en el tipo de fichaje
                 const iconColor = ubicacion.tipo === 'entrada' ? 'green' : 'red';
                 const iconName = ubicacion.tipo === 'entrada' ? 'sign-in-alt' : 'sign-out-alt';
                
                const customIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: `<div style="background-color: ${iconColor}; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                             <i class="fas fa-${iconName}" style="color: white; font-size: 10px;"></i>
                           </div>`,
                    iconSize: [25, 25],
                    iconAnchor: [12, 12]
                });
                
                // Crear marcador
                const marker = L.marker([lat, lng], { icon: customIcon });
                
                // Crear popup con información
                const popupContent = `
                    <div class="p-2">
                        <h4 class="font-semibold text-gray-900 mb-2">${ubicacion.nombre_completo}</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex items-center">
                                <i class="fas fa-user w-4 text-gray-500 mr-2"></i>
                                <span class="text-gray-700">${ubicacion.nombre_trabajador}</span>
                            </div>
                                                         <div class="flex items-center">
                                 <i class="fas fa-${iconName} w-4 text-${iconColor}-500 mr-2"></i>
                                 <span class="text-gray-700 capitalize">${ubicacion.tipo === 'entrada' ? 'Entrada' : 'Salida'}</span>
                             </div>
                            <div class="flex items-center">
                                <i class="fas fa-clock w-4 text-gray-500 mr-2"></i>
                                <span class="text-gray-700">${formatDateTime(ubicacion.fecha_hora)}</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-map-marker-alt w-4 text-gray-500 mr-2"></i>
                                <span class="text-gray-700 text-xs">${lat.toFixed(6)}, ${lng.toFixed(6)}</span>
                            </div>
                            ${ubicacion.observaciones ? `
                            <div class="flex items-start">
                                <i class="fas fa-comment w-4 text-gray-500 mr-2 mt-0.5"></i>
                                <span class="text-gray-700 text-xs">${ubicacion.observaciones}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                markersCluster.addLayer(marker);
            });
        }
        
        function formatDateTime(dateTimeString) {
            const date = new Date(dateTimeString);
            return date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    </script>

    <style>
        .custom-div-icon {
            background: transparent;
            border: none;
        }
        
        .leaflet-popup-content {
            margin: 8px 12px;
        }
        
        .leaflet-popup-content-wrapper {
            border-radius: 8px;
        }
    </style>
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
BaseLayout::render('Mapa de Ubicaciones', renderContent(), $config_empresa, $user_data);
?> 