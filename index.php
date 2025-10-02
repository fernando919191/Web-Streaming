<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxa - Marketplace de Cuentas de Streaming</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #e83e8c;
            --dark-color: #2d3748;
            --light-color: #f8f9fa;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.8rem;
        }
        
        .hero-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            margin-top: 30px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .store-card {
            background: white;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .store-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .store-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0 auto 15px;
        }
        
        .search-box {
            max-width: 600px;
            margin: 0 auto 40px;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .stats-badge {
            background: var(--primary-color);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.85rem;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-play-circle me-2"></i>Fluxa
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt me-1"></i> Iniciar Sesión</a>
                <a class="nav-link" href="#"><i class="fas fa-user-plus me-1"></i> Registrarse</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Hero Section -->
        <div class="hero-section text-center">
            <h1 class="display-4 fw-bold mb-4">Encuentra las Mejores Cuentas de Streaming</h1>
            <p class="lead mb-4">Marketplace seguro donde vendedores verificados ofrecen las mejores cuentas de plataformas digitales</p>
            
            <!-- Search Bar -->
            <div class="search-box">
                <div class="input-group">
                    <input type="text" class="form-control form-control-lg" placeholder="Buscar vendedor por nombre..." id="searchInput">
                    <button class="btn btn-primary" type="button">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5>100% Seguro</h5>
                        <p class="text-muted">Transacciones protegidas y vendedores verificados</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <h5>Sistema de Coins</h5>
                        <p class="text-muted">Usa coins para comprar en todas las tiendas</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h5>Soporte 24/7</h5>
                        <p class="text-muted">Asistencia continua para compradores y vendedores</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stores Grid -->
        <div class="row mt-5">
            <div class="col-12">
                <h2 class="text-white text-center mb-4">Tiendas Disponibles</h2>
            </div>
            
            <?php
            // Simulación de datos de tiendas (en un proyecto real esto vendría de una base de datos)
            $tiendas = [
                [
                    'nombre' => 'dannip',
                    'productos' => 15,
                    'rating' => 4.8,
                    'descripcion' => 'Especialista en cuentas premium'
                ],
                [
                    'nombre' => 'kodigox.shop',
                    'productos' => 23,
                    'rating' => 4.9,
                    'descripcion' => 'Las mejores cuentas de streaming'
                ],
                [
                    'nombre' => 'stream_pro',
                    'productos' => 8,
                    'rating' => 4.7,
                    'descripcion' => 'Calidad y confianza garantizada'
                ],
                [
                    'nombre' => 'premium_accounts',
                    'productos' => 31,
                    'rating' => 4.6,
                    'descripcion' => 'Variedad en plataformas digitales'
                ],
                [
                    'nombre' => 'tech_flow',
                    'productos' => 12,
                    'rating' => 4.9,
                    'descripcion' => 'Tecnología y entretenimiento'
                ],
                [
                    'nombre' => 'digital_hub',
                    'productos' => 19,
                    'rating' => 4.5,
                    'descripcion' => 'Tu centro digital favorito'
                ]
            ];

            foreach ($tiendas as $tienda) {
                $inicial = strtoupper(substr($tienda['nombre'], 0, 1));
                $color = dechex(rand(0x000000, 0xFFFFFF));
                echo "
                <div class='col-md-4 mb-4 store-item' data-name='{$tienda['nombre']}'>
                    <div class='store-card p-4 text-center'>
                        <div class='store-logo' style='background: linear-gradient(45deg, #{$color}, #".dechex(rand(0x000000, 0xFFFFFF)).");'>
                            {$inicial}
                        </div>
                        <h4 class='fw-bold'>{$tienda['nombre']}</h4>
                        <p class='text-muted'>{$tienda['descripcion']}</p>
                        <div class='d-flex justify-content-center gap-3 mb-3'>
                            <span class='stats-badge'><i class='fas fa-box me-1'></i> {$tienda['productos']} productos</span>
                            <span class='stats-badge'><i class='fas fa-star me-1'></i> {$tienda['rating']}</span>
                        </div>
                        <button class='btn btn-primary btn-sm'>
                            <i class='fas fa-store me-1'></i> Visitar Tienda
                        </button>
                    </div>
                </div>";
            }
            ?>
        </div>

        <!-- Info Section -->
        <div class="row mt-5 text-white">
            <div class="col-12 text-center">
                <h3>¿Eres vendedor?</h3>
                <p class="lead">Únete a nuestra plataforma y comienza a vender tus cuentas de streaming</p>
                <button class="btn btn-light btn-lg">
                    <i class="fas fa-store me-2"></i> Crear Mi Tienda
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center text-white mt-5 py-4">
        <div class="container">
            <p>&copy; 2024 Fluxa. Todos los derechos reservados.</p>
            <div class="mt-2">
                <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                <a href="#" class="text-white"><i class="fab fa-discord"></i></a>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Función de búsqueda
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const storeItems = document.querySelectorAll('.store-item');
            
            storeItems.forEach(item => {
                const storeName = item.getAttribute('data-name').toLowerCase();
                if (storeName.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Efecto de carga inicial
        document.addEventListener('DOMContentLoaded', function() {
            const storeCards = document.querySelectorAll('.store-card');
            storeCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>