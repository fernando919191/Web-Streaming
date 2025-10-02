-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS fluxa_marketplace;
USE fluxa_marketplace;

-- Tabla de usuarios (vendedores, compradores, administradores)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'vendor', 'user') DEFAULT 'user',
    store_name VARCHAR(100),
    avatar VARCHAR(255),
    description TEXT,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de wallets (sistema de coins)
CREATE TABLE wallets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    pending_balance DECIMAL(10,2) DEFAULT 0.00,
    total_earned DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de categorías de productos
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de productos (cuentas de streaming)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 1,
    platform ENUM('netflix', 'disney', 'hbo', 'spotify', 'youtube', 'amazon', 'apple', 'paramount', 'star', 'other') NOT NULL,
    account_type ENUM('shared', 'personal', 'family', 'premium') DEFAULT 'shared',
    duration_days INT DEFAULT 30,
    is_active BOOLEAN DEFAULT TRUE,
    images JSON,
    features JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Tabla de transacciones de coins
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('purchase', 'sale', 'transfer', 'withdrawal', 'admin_add') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'completed', 'cancelled', 'rejected') DEFAULT 'pending',
    reference VARCHAR(100),
    proof_image VARCHAR(255),
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabla de órdenes de compra
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    buyer_id INT NOT NULL,
    vendor_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'delivered', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (vendor_id) REFERENCES users(id)
);

-- Tabla de items de orden
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    account_details JSON,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Tabla de reportes de ventas (para pagos a vendedores)
CREATE TABLE sales_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    vendor_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_sales DECIMAL(10,2) DEFAULT 0.00,
    platform_commission DECIMAL(10,2) DEFAULT 0.00,
    net_earnings DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending', 'paid', 'processing') DEFAULT 'pending',
    payment_date DATE,
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES users(id)
);

-- Tabla de reseñas y calificaciones
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    vendor_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (vendor_id) REFERENCES users(id)
);

-- Tabla de conversaciones (chat entre usuarios)
CREATE TABLE conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    last_message TEXT,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id),
    FOREIGN KEY (user2_id) REFERENCES users(id)
);

-- Tabla de mensajes
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id)
);

-- Tabla de configuración de la plataforma
CREATE TABLE platform_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coin_value DECIMAL(10,2) DEFAULT 1.00,
    commission_rate DECIMAL(5,2) DEFAULT 15.00,
    min_withdrawal DECIMAL(10,2) DEFAULT 50.00,
    contact_email VARCHAR(100),
    admin_bank_details TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar datos iniciales
INSERT INTO categories (name, description, icon) VALUES
('Streaming Video', 'Plataformas de streaming de video', 'fas fa-play-circle'),
('Streaming Música', 'Plataformas de streaming de música', 'fas fa-music'),
('Videojuegos', 'Suscripciones de videojuegos', 'fas fa-gamepad'),
('Software', 'Licencias de software', 'fas fa-desktop'),
('Otros', 'Otras suscripciones digitales', 'fas fa-star');

-- Insertar usuario administrador por defecto
INSERT INTO users (username, email, password, role, is_verified) VALUES
('admin', 'admin@fluxa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- Insertar configuración inicial
INSERT INTO platform_settings (coin_value, commission_rate, min_withdrawal, contact_email, admin_bank_details) VALUES
(1.00, 15.00, 50.00, 'admin@fluxa.com', 'Banco: BBVA\nCuenta: 0123 4567 8901\nTitular: Fluxa Marketplace\nCLABE: 012345678901234567');

-- Crear índices para mejorar el rendimiento
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_products_vendor ON products(vendor_id);
CREATE INDEX idx_products_platform ON products(platform);
CREATE INDEX idx_orders_buyer ON orders(buyer_id);
CREATE INDEX idx_orders_vendor ON orders(vendor_id);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_status ON transactions(status);