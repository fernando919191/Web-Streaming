<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Condiciones - Fluxa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .terms-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            margin: 30px auto;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 1000px;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.8rem;
        }
        
        .section-title {
            color: #6f42c1;
            border-bottom: 2px solid #e83e8c;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .highlight-box {
            background: #f8f9fa;
            border-left: 4px solid #e83e8c;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-play-circle me-2"></i>Fluxa
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i> Inicio</a>
                <a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt me-1"></i> Iniciar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="terms-container">
            <h1 class="text-center mb-4">Términos y Condiciones de Uso</h1>
            <p class="text-muted text-center mb-5">Última actualización: <?php echo date('d/m/Y'); ?></p>

            <!-- Aviso Importante -->
            <div class="warning-box">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>AVISO IMPORTANTE</h4>
                <p class="mb-0">
                    <strong>FLUXA ACTÚA ÚNICAMENTE COMO INTERMEDIARIO</strong> entre vendedores y compradores. 
                    No somos propietarios, creadores ni verificamos el origen de las cuentas comercializadas 
                    en nuestra plataforma.
                </p>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="list-group">
                        <a href="#1-naturaleza" class="list-group-item list-group-item-action">1. Naturaleza del Servicio</a>
                        <a href="#2-responsabilidad" class="list-group-item list-group-item-action">2. Limitación de Responsabilidad</a>
                        <a href="#3-vendedores" class="list-group-item list-group-item-action">3. Responsabilidad de Vendedores</a>
                        <a href="#4-compradores" class="list-group-item list-group-item-action">4. Responsabilidad de Compradores</a>
                        <a href="#5-coins" class="list-group-item list-group-item-action">5. Sistema de Coins</a>
                        <a href="#6-prohibido" class="list-group-item list-group-item-action">6. Actividades Prohibidas</a>
                        <a href="#7-disputas" class="list-group-item list-group-item-action">7. Resolución de Disputas</a>
                        <a href="#8-modificaciones" class="list-group-item list-group-item-action">8. Modificaciones</a>
                    </div>
                </div>

                <div class="col-md-9">
                    <!-- Sección 1 -->
                    <section id="1-naturaleza" class="mb-5">
                        <h3 class="section-title">1. Naturaleza del Servicio</h3>
                        <p>Fluxa es una plataforma digital que funciona como <strong>mercado intermediario</strong> donde:</p>
                        <ul>
                            <li>Vendedores independientes ofrecen cuentas de servicios de streaming</li>
                            <li>Compradores adquieren estas cuentas usando nuestro sistema de coins</li>
                            <li>Fluxa facilita la transacción pero NO es propietaria de los productos</li>
                        </ul>
                        
                        <div class="highlight-box">
                            <h5><i class="fas fa-info-circle me-2"></i>Declaración de Intermediación</h5>
                            <p class="mb-0">
                                Fluxa no posee, crea, verifica ni se responsabiliza por el origen, legalidad o 
                                autenticidad de las cuentas comercializadas en la plataforma. Solo proporcionamos 
                                el espacio digital para que vendedores y compradores realicen transacciones.
                            </p>
                        </div>
                    </section>

                    <!-- Sección 2 -->
                    <section id="2-responsabilidad" class="mb-5">
                        <h3 class="section-title">2. Limitación de Responsabilidad</h3>
                        <p><strong>2.1. Exención de Responsabilidad por Contenido</strong></p>
                        <p>Fluxa no se hace responsable por:</p>
                        <ul>
                            <li>La legalidad del origen de las cuentas vendidas</li>
                            <li>La autenticidad de la información proporcionada por los vendedores</li>
                            <li>La calidad o duración del servicio de las cuentas comercializadas</li>
                            <li>El cumplimiento de los términos de servicio de las plataformas de streaming</li>
                        </ul>

                        <p><strong>2.2. Responsabilidad del Usuario</strong></p>
                        <p>Al usar Fluxa, aceptas que:</p>
                        <ul>
                            <li>Eres mayor de 18 años y tienes capacidad legal para realizar transacciones</li>
                            <li>Comprendes los riesgos asociados a la compra de cuentas de terceros</li>
                            <li>Aceptas que Fluxa solo proporciona la plataforma de intermediación</li>
                        </ul>
                    </section>

                    <!-- Sección 3 -->
                    <section id="3-vendedores" class="mb-5">
                        <h3 class="section-title">3. Responsabilidad de los Vendedores</h3>
                        <p><strong>3.1. Declaraciones y Garantías</strong></p>
                        <p>Como vendedor en Fluxa, declaras y garantizas que:</p>
                        <ul>
                            <li>Eres el legítimo poseedor de las cuentas que comercializas</li>
                            <li>Proporcionas información veraz y completa sobre tus productos</li>
                            <li>Cumples con los plazos de entrega establecidos</li>
                            <li>Ofreces soporte post-venta adecuado a tus clientes</li>
                        </ul>

                        <p><strong>3.2. Consecuencias por Incumplimiento</strong></p>
                        <p>Fluxa se reserva el derecho de:</p>
                        <ul>
                            <li>Suspender cuentas de vendedores que incumplan estos términos</li>
                            <li>Retener pagos en casos de disputas no resueltas</li>
                            <li>Eliminar productos que violen nuestras políticas</li>
                        </ul>
                    </section>

                    <!-- Sección 4 -->
                    <section id="4-compradores" class="mb-5">
                        <h3 class="section-title">4. Responsabilidad de los Compradores</h3>
                        <p><strong>4.1. Due Diligence</strong></p>
                        <p>Como comprador, eres responsable de:</p>
                        <ul>
                            <li>Verificar la reputación y calificaciones del vendedor</li>
                            <li>Leer detenidamente la descripción del producto antes de comprar</li>
                            <li>Comprender que Fluxa no verifica el origen de las cuentas</li>
                            <li>Aceptar los riesgos asociados a este tipo de transacciones</li>
                        </ul>

                        <p><strong>4.2. Uso de las Cuentas</strong></p>
                        <p>Reconoces que el uso de cuentas de streaming puede violar los términos de servicio 
                        de las plataformas originales y aceptas toda responsabilidad derivada de dicho uso.</p>
                    </section>

                    <!-- Sección 5 -->
                    <section id="5-coins" class="mb-5">
                        <h3 class="section-title">5. Sistema de Coins</h3>
                        <p><strong>5.1. Adquisición de Coins</strong></p>
                        <ul>
                            <li>Los coins son la moneda virtual exclusiva de Fluxa</li>
                            <li>Su valor es establecido por la plataforma (1 Coin = 1 USD)</li>
                            <li>Las compras de coins son definitivas y no reembolsables</li>
                            <li>Fluxa se reserva el derecho de modificar el valor en casos excepcionales</li>
                        </ul>

                        <p><strong>5.2. Uso y Restricciones</strong></p>
                        <ul>
                            <li>Los coins solo pueden usarse dentro de la plataforma Fluxa</li>
                            <li>No son transferibles entre usuarios sin autorización</li>
                            <li>No tienen valor monetario fuera de la plataforma</li>
                            <li>Fluxa puede congelar coins en casos de actividad sospechosa</li>
                        </ul>
                    </section>

                    <!-- Sección 6 -->
                    <section id="6-prohibido" class="mb-5">
                        <h3 class="section-title">6. Actividades Prohibidas</h3>
                        <p>Queda estrictamente prohibido:</p>
                        <ul>
                            <li>Vender cuentas robadas o obtenidas ilegalmente</li>
                            <li>Utilizar información falsa o engañosa</li>
                            <li>Manipular el sistema de calificaciones</li>
                            <li>Realizar transacciones fuera de la plataforma</li>
                            <li>Suplantar la identidad de otros usuarios</li>
                            <li>Utilizar la plataforma para actividades fraudulentas</li>
                        </ul>
                    </section>

                    <!-- Sección 7 -->
                    <section id="7-disputas" class="mb-5">
                        <h3 class="section-title">7. Resolución de Disputas</h3>
                        <p><strong>7.1. Proceso de Reclamación</strong></p>
                        <p>En caso de problemas con una transacción:</p>
                        <ol>
                            <li>Contacta directamente con el vendedor para resolver el issue</li>
                            <li>Si no hay solución en 48 horas, abre una disputa en la plataforma</li>
                            <li>Fluxa mediará en la disputa como intermediario neutral</li>
                            <li>La decisión de Fluxa será final en caso de no llegar a acuerdo</li>
                        </ol>

                        <p><strong>7.2. Limitación de Responsabilidad en Disputas</strong></p>
                        <p>Fluxa actuará como mediador pero no asume responsabilidad financiera por 
                        transacciones entre usuarios. El máximo compromiso en casos de fraude comprobado 
                        será la suspensión del usuario infractor.</p>
                    </section>

                    <!-- Sección 8 -->
                    <section id="8-modificaciones" class="mb-5">
                        <h3 class="section-title">8. Modificaciones de los Términos</h3>
                        <p>Fluxa se reserva el derecho de modificar estos términos en cualquier momento. 
                        Los cambios serán notificados a los usuarios con 15 días de anticipación y 
                        el uso continuado de la plataforma constituye aceptación de los nuevos términos.</p>

                        <div class="highlight-box">
                            <h5><i class="fas fa-balance-scale me-2"></i>Aceptación de Términos</h5>
                            <p class="mb-0">
                                Al registrarte en Fluxa y utilizar nuestros servicios, aceptas automáticamente 
                                todos los términos y condiciones aquí establecidos, incluyendo la limitación 
                                de responsabilidad como plataforma intermediaria.
                            </p>
                        </div>
                    </section>

                    <!-- Contacto -->
                    <div class="text-center mt-5">
                        <h4>¿Tienes dudas sobre estos términos?</h4>
                        <p>Contáctanos en: <strong>legal@fluxa.com</strong></p>
                        <a href="index.php" class="btn btn-primary me-2">
                            <i class="fas fa-home me-1"></i> Volver al Inicio
                        </a>
                        <a href="register.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-plus me-1"></i> Registrarse
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center text-white py-4">
        <div class="container">
            <p>&copy; 2025 Fluxa. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>