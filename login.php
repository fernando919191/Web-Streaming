<?php
session_start();
require_once 'config/database.php';

// Si ya está logueado, redirigir según su rol
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'vendor':
            header("Location: vendor/dashboard.php");
            break;
        case 'user':
            header("Location: user/dashboard.php");
        default:
            header("Location: index.php");
    }
    exit();
}

$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Buscar usuario por email
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_verified = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Iniciar sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['store_name'] = $user['store_name'];
            
            // Redirigir según el rol
            switch ($user['role']) {
                case 'admin':
                    header("Location: admin/dashboard.php");
                    break;
                case 'vendor':
                    header("Location: vendor/dashboard.php");
                    break;
                default:
                    header("Location: index.php");
            }
            exit();
            
        } else {
            $error = 'Email o contraseña incorrectos.';
        }
    }
}

// Mostrar mensaje de éxito si viene del registro
if (isset($_GET['registered'])) {
    $success = '¡Registro exitoso! Ahora puedes iniciar sesión.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Fluxa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(45deg, #6f42c1, #e83e8c);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #6f42c1;
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(45deg, #6f42c1, #e83e8c);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.4);
        }
        
        .social-login {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #6f42c1;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <!-- Header -->
                    <div class="login-header">
                        <div class="feature-icon">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <h2>Fluxa</h2>
                        <p class="mb-0">Iniciar Sesión</p>
                    </div>
                    
                    <!-- Body -->
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email
                                </label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    id="email" 
                                    name="email" 
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    placeholder="tu@email.com"
                                    required
                                >
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Contraseña
                                </label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Tu contraseña"
                                    required
                                >
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">
                                    Recordar sesión
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </button>
                            
                            <div class="text-center">
                                <a href="forgot_password.php" class="text-decoration-none">
                                    <i class="fas fa-key me-1"></i>¿Olvidaste tu contraseña?
                                </a>
                            </div>
                        </form>
                        
                        <div class="social-login text-center">
                            <p class="text-muted mb-3">O inicia sesión con</p>
                            <div class="d-flex justify-content-center gap-3">
                                <button class="btn btn-outline-primary rounded-circle p-3">
                                    <i class="fab fa-google"></i>
                                </button>
                                <button class="btn btn-outline-primary rounded-circle p-3">
                                    <i class="fab fa-facebook-f"></i>
                                </button>
                                <button class="btn btn-outline-primary rounded-circle p-3">
                                    <i class="fab fa-twitter"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">
                                ¿No tienes cuenta? 
                                <a href="register.php" class="text-decoration-none fw-bold">
                                    Regístrate aquí
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Info Cards -->
                <div class="row mt-4">
                    <div class="col-md-4 mb-3">
                        <div class="card text-center text-white bg-transparent border-white">
                            <div class="card-body">
                                <i class="fas fa-shield-alt fa-2x mb-2"></i>
                                <h6>100% Seguro</h6>
                                <small>Transacciones protegidas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center text-white bg-transparent border-white">
                            <div class="card-body">
                                <i class="fas fa-coins fa-2x mb-2"></i>
                                <h6>Sistema de Coins</h6>
                                <small>Moneda unificada</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center text-white bg-transparent border-white">
                            <div class="card-body">
                                <i class="fas fa-headset fa-2x mb-2"></i>
                                <h6>Soporte 24/7</h6>
                                <small>Ayuda inmediata</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Efectos de animación
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.style.opacity = '0';
            form.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                form.style.transition = 'all 0.5s ease';
                form.style.opacity = '1';
                form.style.transform = 'translateY(0)';
            }, 300);
            
            // Efecto hover en botones sociales
            const socialButtons = document.querySelectorAll('.btn-outline-primary');
            socialButtons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                });
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>