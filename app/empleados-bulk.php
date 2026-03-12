<?php
// Initialize app (session, subdomain routing, etc.)
require_once __DIR__ . '/../shared/utils/app_init.php';

// Incluir las clases necesarias
require_once __DIR__ . '/../shared/models/Trabajador.php';
require_once __DIR__ . '/../shared/components/BulkUserForm.php';
require_once __DIR__ . '/../shared/layouts/BaseLayout.php';
require_once __DIR__ . '/../shared/components/Breadcrumb.php';
require_once __DIR__ . '/../assets/css/components.php';
require_once __DIR__ . '/../config/database.php';

// Verificar si el trabajador está logueado
if (!Trabajador::estaLogueado()) {
    header('Location: /app/login.php');
    exit();
}

// Verificar que el usuario tenga permisos (solo administradores)
$rol_trabajador = $_SESSION['rol_trabajador'] ?? 'Empleado';
if (strtolower($rol_trabajador) !== 'administrador') {
    header('Location: /app/empleados.php?error=sin_permisos');
    exit();
}

// Obtener datos del trabajador de la sesión
$nombre_trabajador = $_SESSION['nombre_trabajador'] ?? 'Trabajador';
$correo_trabajador = $_SESSION['correo_trabajador'] ?? 'N/A';
$empresa_id = $_SESSION['empresa_id'] ?? null;

// Obtener configuración de la empresa
$config_empresa = Trabajador::obtenerConfiguracionEmpresa();

$errors = [];
$success = [];

// Procesar formulario si se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['users_data'])) {
    try {
        $usersData = trim($_POST['users_data']);

        if (empty($usersData)) {
            $errors[] = 'Por favor, ingrese los datos de los usuarios.';
        } else {
            $result = processBulkUsers($empresa_id, $usersData);
            $success = $result['success'];
            $errors = array_merge($errors, $result['errors']);
        }

    } catch (Exception $e) {
        error_log("Error processing bulk users: " . $e->getMessage());
        $errors[] = 'Error interno del servidor. Por favor, inténtelo de nuevo.';
    }
}

/**
 * Process bulk user creation for app side
 */
function processBulkUsers($empresaId, $usersData)
{
    $success = [];
    $errors = [];

    try {
        // Parse and validate user data
        $parseResult = BulkUserForm::parseUsersData($usersData);
        $users = $parseResult['users'];
        $parseErrors = $parseResult['errors'];

        // Add parse errors to main errors array
        $errors = array_merge($errors, $parseErrors);

        if (empty($users)) {
            return ['success' => $success, 'errors' => $errors];
        }

        // Get database connection via centralized helper
        $pdo = getDbConnection();

        // Process each user
        foreach ($users as $userData) {
            $lineNumber = $userData['line_number'];
            
            try {
                // Check if DNI already exists
                $stmt = $pdo->prepare("SELECT id FROM trabajador WHERE dni = ? AND empresa_id = ?");
                $stmt->execute([$userData['dni'], $empresaId]);
                if ($stmt->fetch()) {
                    $errors[] = "Línea $lineNumber: El DNI '{$userData['dni']}' ya existe";
                    continue;
                }

                // Check if PIN already exists
                $stmt = $pdo->prepare("SELECT id FROM trabajador WHERE pin = ? AND empresa_id = ?");
                $stmt->execute([$userData['pin'], $empresaId]);
                if ($stmt->fetch()) {
                    $errors[] = "Línea $lineNumber: El PIN '{$userData['pin']}' ya existe";
                    continue;
                }

                // Insert user with default centro_id=1 and grupo_horario_id=1
                $stmt = $pdo->prepare("
                    INSERT INTO trabajador (
                        empresa_id, nombre_trabajador, nombre_completo, dni, pin, 
                        rol, centro_id, grupo_horario_id, activo, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'Empleado', 1, 1, 1, NOW())
                ");

                $stmt->execute([
                    $empresaId,
                    $userData['nombre_trabajador'],
                    $userData['nombre_completo'],
                    $userData['dni'],
                    $userData['pin']
                ]);

                $success[] = "Usuario creado: {$userData['nombre_completo']} (DNI: {$userData['dni']}, PIN: {$userData['pin']})";

            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate entry
                    if (strpos($e->getMessage(), 'uk_trabajador_empresa_dni') !== false) {
                        $errors[] = "Línea $lineNumber: El DNI '{$userData['dni']}' ya existe";
                    } elseif (strpos($e->getMessage(), 'uk_trabajador_empresa_pin') !== false) {
                        $errors[] = "Línea $lineNumber: El PIN '{$userData['pin']}' ya existe";
                    } else {
                        $errors[] = "Línea $lineNumber: Datos duplicados";
                    }
                } else {
                    $errors[] = "Línea $lineNumber: Error de base de datos - " . $e->getMessage();
                }
            }
        }

    } catch (Exception $e) {
        $errors[] = "Error de conexión: " . $e->getMessage();
    }

    return ['success' => $success, 'errors' => $errors];
}

// Función para renderizar el contenido
function renderBulkUsersContent($config_empresa, $errors, $success)
{
    ob_start();
    ?>

    <!-- Breadcrumb -->
    <?php 
    Breadcrumb::render([
        ['label' => 'Inicio', 'url' => '/app/dashboard.php', 'icon' => 'fas fa-home'],
        ['label' => 'Empleados', 'url' => '/app/empleados.php', 'icon' => 'fas fa-users'],
        ['label' => 'Crear en Lote']
    ]); 
    ?>

    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Crear Empleados en Lote</h1>
                <p class="text-gray-600 mt-1">
                    Crea múltiples empleados de una vez usando formato CSV
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="/app/empleados.php"
                    class="<?php echo CSSComponents::getButtonClasses('secondary', 'md'); ?>">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Volver a Empleados
                </a>
            </div>
        </div>
    </div>

    <?php
    // Render the bulk user form
    $formConfig = [
        'company_name' => $config_empresa['nombre_app'] ?? 'Tu Empresa',
        'back_url' => '/app/empleados.php',
        'form_action' => '',
        'show_company_message' => false
    ];
    
    $postData = $_POST['users_data'] ?? '';
    echo BulkUserForm::render($formConfig, $errors, $success, $postData);
    ?>

    <?php
    return ob_get_clean();
}

// Preparar datos de usuario para el layout
$user_data = [
    'nombre' => $nombre_trabajador,
    'correo' => $correo_trabajador,
    'rol' => $rol_trabajador
];

// Renderizar el contenido
$content = renderBulkUsersContent($config_empresa, $errors, $success);

// Usar el BaseLayout para renderizar la página completa
BaseLayout::render('Crear Empleados en Lote', $content, $config_empresa, $user_data);
?>