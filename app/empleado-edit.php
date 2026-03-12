<?php
// Initialize app (session, subdomain routing, etc.)
require_once __DIR__ . '/../shared/utils/app_init.php';

// Incluir archivos necesarios
require_once __DIR__ . '/../shared/models/Trabajador.php';
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

// Verificar que el usuario sea empleado
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (strtolower($rol_trabajador) !== 'empleado') {
    header('Location: /app/dashboard.php');
    exit;
}

// Verificar permisos - solo si la empresa permite editar perfil
$config_empresa = Trabajador::obtenerConfiguracionEmpresa();
if (empty($config_empresa['empleado_editar_perfil'])) {
    header('Location: /app/dashboard.php');
    exit;
}

// Obtener datos del trabajador de la sesión
$nombre_trabajador = $_SESSION['nombre_trabajador'] ?? 'Trabajador';
$correo_trabajador = $_SESSION['correo_trabajador'] ?? '';
$trabajador_id = $_SESSION['id_trabajador'] ?? null;
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$trabajador_id || !$empresa_id) {
    header('Location: /app/login.php');
    exit;
}

// Inicializar variables
$trabajador = new Trabajador();
$errors = [];
$success_message = '';

// Manejar generación de PIN aleatorio (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'generar_pin') {
    $pin_generado = $trabajador->generarPinUnico($empresa_id);
    header('Content-Type: application/json');
    echo json_encode(['pin' => $pin_generado]);
    exit;
}

// Obtener datos actuales del empleado
$datos_empleado = $trabajador->obtenerTrabajadorPorId($trabajador_id);
if (!$datos_empleado) {
    header('Location: /app/dashboard.php?error=empleado_no_encontrado');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = procesarFormularioPerfil($_POST);
    $errors = validarFormularioPerfil($form_data);
    
    if (empty($errors)) {
        $resultado = $trabajador->actualizarPerfilEmpleado(
            $trabajador_id,
            $form_data['correo_electronico'],
            $form_data['pin'],
            $empresa_id
        );
        
        if ($resultado['success']) {
            $success_message = $resultado['message'];
            // Actualizar datos mostrados
            $datos_empleado['correo_electronico'] = $form_data['correo_electronico'];
            $datos_empleado['pin'] = $form_data['pin'];
        } else {
            $errors['general'] = $resultado['message'];
        }
    }
}

/**
 * Procesar datos del formulario
 */
function procesarFormularioPerfil($post_data) {
    return [
        'correo_electronico' => trim($post_data['correo_electronico'] ?? ''),
        'pin' => trim($post_data['pin'] ?? '')
    ];
}

/**
 * Validar datos del formulario
 */
function validarFormularioPerfil($data) {
    $errors = [];
    
    // Validar PIN
    if (empty($data['pin'])) {
        $errors['pin'] = 'El PIN es obligatorio';
    } elseif (!preg_match('/^\d{4}$/', $data['pin'])) {
        $errors['pin'] = 'El PIN debe tener exactamente 4 dígitos';
    }
    
    // Validar email (opcional pero si se proporciona debe ser válido)
    if (!empty($data['correo_electronico'])) {
        if (!filter_var($data['correo_electronico'], FILTER_VALIDATE_EMAIL)) {
            $errors['correo_electronico'] = 'El formato del correo electrónico no es válido';
        }
    }
    
    return $errors;
}

// Preparar datos de usuario para el layout
$user_data = [
    'nombre' => $nombre_trabajador,
    'correo' => $correo_trabajador,
    'rol' => $rol_trabajador
];

/**
 * Renderizar contenido de la página
 */
function renderContent() {
    global $datos_empleado, $errors, $success_message, $config_empresa;
    
    ob_start();
    ?>
    <!-- Breadcrumb -->
    <?php 
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Mi perfil']
    ]); 
    ?>

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Editar perfil empleado</h1>
        <p class="text-gray-600 mt-1">Actualiza tu información personal</p>
    </div>

    <!-- Mensaje de éxito -->
    <?php if (!empty($success_message)): ?>
        <div class="mb-6 p-4 <?php echo CSSComponents::getCardClasses('success'); ?>">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <span class="text-green-700"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Formulario -->
    <div class="<?php echo CSSComponents::getCardClasses('default'); ?>">
        <form method="POST" class="p-6" onsubmit="return validateForm()">
            <!-- Error General -->
            <?php if (isset($errors['general'])): ?>
                <div class="mb-6 p-4 <?php echo CSSComponents::getCardClasses('error'); ?>">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <span class="text-red-700"><?php echo htmlspecialchars($errors['general']); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Campos del formulario -->
            <div class="space-y-6">
                <!-- Correo Electrónico -->
                <div class="<?php echo CSSComponents::getFieldWrapperClasses(); ?>">
                    <label for="correo_electronico" class="<?php echo CSSComponents::getLabelClasses(); ?>">
                        Correo Electrónico
                    </label>
                    <input 
                        type="email" 
                        id="correo_electronico" 
                        name="correo_electronico"
                        value="<?php echo htmlspecialchars($datos_empleado['correo_electronico'] ?? ''); ?>"
                        class="<?php echo CSSComponents::getInputClasses(isset($errors['correo_electronico']) ? 'error' : ''); ?>"
                        placeholder="empleado@empresa.com"
                    >
                    <?php if (isset($errors['correo_electronico'])): ?>
                        <p class="<?php echo CSSComponents::getErrorTextClasses(); ?>">
                            <?php echo htmlspecialchars($errors['correo_electronico']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- PIN -->
                <div class="<?php echo CSSComponents::getFieldWrapperClasses(); ?>">
                    <label for="pin" class="<?php echo CSSComponents::getLabelClasses(); ?>">
                        PIN (4 dígitos) <span class="text-red-500">*</span>
                        <button type="button" id="generar-pin" class="ml-2 text-primary text-sm hover:underline">
                            Generar aleatorio
                        </button>
                    </label>
                    <input 
                        type="text" 
                        id="pin" 
                        name="pin"
                        value="<?php echo htmlspecialchars($datos_empleado['pin'] ?? ''); ?>"
                        maxlength="4"
                        pattern="[0-9]{4}"
                        class="<?php echo CSSComponents::getInputClasses(isset($errors['pin']) ? 'error' : ''); ?> font-mono"
                        placeholder="4 dígitos"
                        autocomplete="off"
                        required
                    >
                    <?php if (isset($errors['pin'])): ?>
                        <p class="<?php echo CSSComponents::getErrorTextClasses(); ?>">
                            <?php echo htmlspecialchars($errors['pin']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Campos obligatorios -->
            <div class="text-sm text-gray-500 mt-6 mb-6">
                <span class="text-red-500">*</span> Campos obligatorios
            </div>

            <!-- Botones de Acción -->
            <div class="<?php echo CSSComponents::getActionButtonGroupClasses(); ?>">
                <a 
                    href="/app/dashboard.php"
                    class="<?php echo CSSComponents::getButtonClasses('outline', 'md'); ?>"
                >
                    <i class="fas fa-times mr-2"></i>
                    Cancelar
                </a>
                <button 
                    type="submit"
                    class="<?php echo CSSComponents::getButtonClasses('primary', 'md'); ?>"
                >
                    <i class="fas fa-save mr-2"></i>
                    ACTUALIZAR
                </button>
            </div>
        </form>
    </div>

    <!-- JavaScript -->
    <script>
        function validateForm() {
            const errors = [];
            
            // Validar PIN
            const pin = document.getElementById('pin').value.trim();
            if (!pin) {
                errors.push('El PIN es obligatorio');
            } else if (!/^\d{4}$/.test(pin)) {
                errors.push('El PIN debe tener exactamente 4 dígitos');
            }
            
            // Validar email si se proporciona
            const email = document.getElementById('correo_electronico').value.trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('El formato del correo electrónico no es válido');
            }
            
            // Mostrar errores
            if (errors.length > 0) {
                alert('Errores encontrados:\n\n' + errors.join('\n'));
                return false;
            }
            
            return true;
        }
        
        // Generar PIN aleatorio
        document.getElementById('generar-pin')?.addEventListener('click', async function() {
            try {
                const response = await fetch('?action=generar_pin');
                const data = await response.json();
                if (data.pin) {
                    document.getElementById('pin').value = data.pin;
                } else {
                    alert('Error al generar PIN. Inténtalo de nuevo.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al generar PIN. Inténtalo de nuevo.');
            }
        });

        // Solo permitir números en PIN
        document.getElementById('pin').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Auto-focus en primer campo
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('correo_electronico').focus();
        });

        // Confirmación antes de cancelar si hay cambios
        document.querySelector('a[href="/app/dashboard.php"]').addEventListener('click', function(e) {
            const originalEmail = '<?php echo htmlspecialchars($datos_empleado['correo_electronico'] ?? ''); ?>';
            const originalPin = '<?php echo htmlspecialchars($datos_empleado['pin'] ?? ''); ?>';
            
            const currentEmail = document.getElementById('correo_electronico').value.trim();
            const currentPin = document.getElementById('pin').value.trim();
            
            if (currentEmail !== originalEmail || currentPin !== originalPin) {
                if (!confirm('¿Estás seguro de que quieres cancelar? Se perderán los cambios realizados.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

// Renderizar la página
BaseLayout::render('Editar perfil empleado', renderContent(), $config_empresa, $user_data);
?> 