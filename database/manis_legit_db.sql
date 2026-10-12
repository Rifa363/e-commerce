-- ============================================
-- RiRa Cakes Manis Legit - Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS manis_legit_db 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE manis_legit_db;

-- Tabel Users (Pelanggan & Admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chat_conversations_updated (updated_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_read_status (
    conversation_id INT PRIMARY KEY,
    customer_last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    admin_last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NULL,
    sender_role ENUM('customer', 'admin', 'system') NOT NULL,
    message TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_messages_conversation (conversation_id, id),
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    identifier_hash CHAR(64) NOT NULL,
    channel ENUM('email', 'whatsapp') NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    request_ip_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_reset_identifier (identifier_hash, channel, created_at),
    INDEX idx_password_reset_ip (request_ip_hash, created_at),
    INDEX idx_password_reset_user (user_id, used_at, expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL
);

-- Tabel Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category_id INT,
    description TEXT,
    price DECIMAL(12, 2) NOT NULL,
    stock INT DEFAULT 0,
    image_url VARCHAR(255),
    label ENUM('Best Seller', 'New', 'Promo', '') DEFAULT '',
    rating DECIMAL(2,1) DEFAULT 0,
    sold_count INT DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Tabel Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_code VARCHAR(20) UNIQUE NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_email VARCHAR(150),
    address TEXT NOT NULL,
    shipping_method ENUM('instant', 'sameday', 'reguler') NOT NULL,
    shipping_cost DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    discount DECIMAL(12,2) DEFAULT 0,
    voucher_code VARCHAR(30) NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('qris', 'transfer', 'cod') NOT NULL,
    status ENUM('pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Pre-order custom cake
CREATE TABLE IF NOT EXISTS custom_cake_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_code VARCHAR(24) NOT NULL UNIQUE,
    cake_type VARCHAR(100) NOT NULL,
    servings INT UNSIGNED NOT NULL,
    flavor VARCHAR(100) NOT NULL,
    filling VARCHAR(100),
    frosting VARCHAR(100),
    design_description TEXT NOT NULL,
    cake_message VARCHAR(100),
    allergies TEXT,
    event_at DATETIME NOT NULL,
    budget DECIMAL(12,2),
    admin_quote DECIMAL(12,2),
    status ENUM('pending', 'quoted', 'confirmed', 'in_production', 'ready', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    customer_notes TEXT,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_custom_cake_user_created (user_id, created_at)
);

-- Tabel voucher promosi
CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(12,2) NOT NULL,
    minimum_order DECIMAL(12,2) NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    usage_limit INT UNSIGNED NULL,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CHECK (discount_value > 0),
    CHECK (minimum_order >= 0),
    CHECK (discount_type != 'percentage' OR discount_value <= 100)
);

INSERT INTO vouchers (code, discount_type, discount_value, minimum_order)
VALUES ('HEMAT10', 'percentage', 10, 100000)
ON DUPLICATE KEY UPDATE code = VALUES(code);

-- Tabel Order Items
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabel Testimonials
CREATE TABLE testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(100) NOT NULL,
    role VARCHAR(100),
    content TEXT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customer reviews are only published after admin approval.
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_review_customer (product_id, user_id),
    INDEX idx_product_reviews_public (product_id, status, created_at),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK (rating BETWEEN 1 AND 5)
);

-- ============================================
-- DATA SAMPLE
-- ============================================

INSERT INTO categories (name, slug) VALUES
('Kue Basah', 'basah'),
('Kue Kering', 'kering'),
('Roti & Pastry', 'roti');

INSERT INTO products (name, category_id, description, price, stock, image_url, label, rating, sold_count) VALUES
('Red Velvet Dream', 1, 'Bolu red velvet lembut dengan cream cheese premium', 185000, 25, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'Best Seller', 4.9, 320),
('Chocolate Lava Cake', 1, 'Kue coklat dengan lelehan coklat Belgian di dalamnya', 165000, 18, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400', 'New', 4.8, 215),
('Classic Nastar Premium', 2, 'Nastar dengan selai nanas homemade yang lumer', 95000, 50, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'Promo', 5.0, 540),
('Croissant Butter Almond', 3, 'Croissant renyah dengan taburan almond panggang', 35000, 40, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', '', 4.7, 180),
('Tiramisu Cup', 1, 'Tiramisu klasik dengan mascarpone dan kopi arabica', 45000, 30, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400', 'Best Seller', 4.9, 410),
('Kastengel Keju Edam', 2, 'Kastengel premium dengan keju Edam import', 110000, 35, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', '', 4.8, 290);

-- 20 produk tambahan untuk setiap kategori.
INSERT INTO products (name, category_id, description, price, stock, image_url, label, rating, sold_count) VALUES
('Lapis Legit Pandan', 1, 'Lapis legit beraroma pandan dengan lapisan lembut dan kaya mentega', 145000, 20, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400', 'New', 4.8, 0),
('Klepon Cake', 1, 'Bolu pandan dengan isian gula aren dan taburan kelapa parut', 78000, 24, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'New', 4.7, 0),
('Bolu Kukus Mekar', 1, 'Bolu kukus warna-warni yang lembut dan merekah sempurna', 42000, 30, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400', 'New', 4.8, 0),
('Kue Lumpur Kentang', 1, 'Kue lumpur kentang lembut dengan topping kismis', 48000, 28, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400', 'New', 4.7, 0),
('Dadar Gulung Pandan', 1, 'Kulit pandan lembut dengan unti kelapa gula merah', 35000, 32, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'New', 4.8, 0),
('Onde-Onde Wijen', 1, 'Onde-onde renyah bertabur wijen dengan isian kacang hijau', 38000, 30, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400', 'New', 4.7, 0),
('Putu Ayu Kelapa', 1, 'Kue putu ayu pandan dengan kelapa parut gurih', 36000, 28, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'New', 4.8, 0),
('Lemper Ayam Suwir', 1, 'Ketan pulen berisi ayam suwir berbumbu gurih', 50000, 25, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400', 'New', 4.8, 0),
('Pastel Ayam Sayur', 1, 'Pastel renyah dengan isian ayam, wortel, dan bihun', 42000, 26, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400', 'New', 4.7, 0),
('Risoles Mayo Smoked Beef', 1, 'Risoles berisi smoked beef, telur, dan saus mayo creamy', 58000, 24, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400', 'New', 4.9, 0),
('Serabi Solo', 1, 'Serabi lembut bersantan dengan aroma pandan khas Solo', 40000, 28, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'New', 4.7, 0),
('Apem Gula Merah', 1, 'Apem tradisional lembut dengan manis alami gula merah', 38000, 30, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400', 'New', 4.7, 0),
('Kue Talam Ubi Ungu', 1, 'Kue talam dua lapis dari ubi ungu dan santan gurih', 42000, 26, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'New', 4.8, 0),
('Nagasari Pisang', 1, 'Kue nagasari kukus berisi pisang dalam balutan daun pisang', 36000, 30, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400', 'New', 4.7, 0),
('Brownies Kukus Keju', 1, 'Brownies cokelat kukus lembut dengan lapisan keju', 68000, 22, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400', 'New', 4.9, 0),
('Bolu Pandan Santan', 1, 'Bolu pandan harum dengan santan dan tekstur yang empuk', 62000, 24, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'New', 4.8, 0),
('Lapis Beras Pelangi', 1, 'Kue lapis beras kenyal dengan warna cerah dan rasa manis', 38000, 28, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=400', 'New', 4.7, 0),
('Kue Ku Kacang Hijau', 1, 'Kue ku kenyal dengan isian kacang hijau manis', 40000, 26, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'New', 4.7, 0),
('Bika Ambon Mini', 1, 'Bika ambon mini berserat dengan aroma pandan dan jeruk', 55000, 24, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400', 'New', 4.8, 0),
('Chiffon Pandan', 1, 'Chiffon pandan ringan dan lembut dengan aroma santan', 72000, 20, 'https://images.unsplash.com/photo-1586788680434-30d324b2d46f?w=400', 'New', 4.9, 0),
('Putri Salju Almond', 2, 'Kue putri salju renyah dengan taburan gula halus dan almond', 78000, 30, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.9, 0),
('Sagu Keju Premium', 2, 'Sagu keju lumer dengan keju cheddar pilihan', 85000, 28, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.9, 0),
('Lidah Kucing Vanilla', 2, 'Lidah kucing tipis dan renyah dengan aroma vanilla', 72000, 32, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.8, 0),
('Cookies Choco Chip', 2, 'Cookies renyah dengan choco chip melimpah', 68000, 30, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.9, 0),
('Thumbprint Strawberry', 2, 'Cookies butter dengan selai stroberi di bagian tengah', 75000, 28, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.8, 0),
('Semprit Butter', 2, 'Kue semprit klasik dengan mentega harum dan tekstur renyah', 65000, 32, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.8, 0),
('Snowball Almond', 2, 'Cookies almond berbentuk bola dengan taburan gula halus', 82000, 26, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.9, 0),
('Palmier Caramel', 2, 'Pastry kering berbentuk hati dengan karamel renyah', 58000, 30, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.7, 0),
('Oatmeal Raisin Cookies', 2, 'Cookies oat dengan kismis dan sentuhan kayu manis', 70000, 28, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.8, 0),
('Coffee Butter Cookies', 2, 'Cookies mentega dengan cita rasa kopi yang lembut', 68000, 30, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.8, 0),
('Peanut Butter Cookies', 2, 'Cookies kacang dengan rasa gurih manis dan tekstur renyah', 72000, 28, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.8, 0),
('Florentine Almond', 2, 'Kepingan almond panggang dengan karamel tipis dan renyah', 95000, 24, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.9, 0),
('Cornflakes Madu', 2, 'Cornflakes panggang dengan madu dalam ukuran sekali santap', 62000, 32, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.7, 0),
('Kue Kacang Jadul', 2, 'Kue kacang klasik yang gurih dan lumer di mulut', 60000, 30, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.8, 0),
('Cheese Straws', 2, 'Stik pastry renyah dengan keju parmesan dan oregano', 68000, 28, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.8, 0),
('Brownies Cookies', 2, 'Cookies cokelat dengan bagian tengah fudgy dan renyah', 72000, 26, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.9, 0),
('Coconut Macaroons', 2, 'Kue kelapa panggang dengan bagian luar keemasan', 65000, 30, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.8, 0),
('Biscotti Almond', 2, 'Biscotti almond panggang dua kali, cocok untuk teman kopi', 78000, 26, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.8, 0),
('Speculoos Cinnamon Cookies', 2, 'Cookies renyah dengan kayu manis dan rempah hangat', 70000, 28, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=400', 'New', 4.8, 0),
('Macaron Vanilla', 2, 'Macaron renyah di luar dengan ganache vanilla lembut', 88000, 22, 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400', 'New', 4.9, 0),
('Pain au Chocolat', 3, 'Pastry berlapis renyah dengan batang cokelat premium', 32000, 24, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', 'New', 4.9, 0),
('Danish Custard', 3, 'Danish pastry berlapis dengan custard vanilla lembut', 34000, 22, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', 'New', 4.8, 0),
('Cinnamon Roll', 3, 'Roti gulung kayu manis dengan glaze gula yang lembut', 28000, 26, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.9, 0),
('Garlic Bread', 3, 'Roti panggang dengan mentega bawang putih dan parsley', 24000, 28, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.8, 0),
('Roti Sobek Cokelat', 3, 'Roti sobek empuk dengan isian cokelat di setiap bagian', 38000, 24, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.9, 0),
('Roti Sisir Butter', 3, 'Roti sisir klasik lembut dengan olesan butter manis', 32000, 26, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.8, 0),
('Brioche Butter', 3, 'Brioche lembut kaya mentega dengan aroma panggang harum', 42000, 20, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.9, 0),
('Sourdough Country Loaf', 3, 'Roti sourdough berkulit renyah dengan fermentasi alami', 58000, 18, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.9, 0),
('Baguette Artisan', 3, 'Baguette klasik dengan kulit garing dan bagian dalam berongga', 30000, 22, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.8, 0),
('Cheese Danish', 3, 'Danish pastry isi krim keju dengan lapisan pastry renyah', 36000, 22, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', 'New', 4.9, 0),
('Apple Turnover', 3, 'Pastry lipat renyah berisi apel kayu manis', 34000, 22, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', 'New', 4.8, 0),
('Egg Tart Portugis', 3, 'Tart pastry renyah dengan custard telur panggang', 30000, 24, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', 'New', 4.9, 0),
('Pain Suisse Vanilla', 3, 'Pastry berlapis isi custard vanilla dan cokelat', 36000, 20, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', 'New', 4.8, 0),
('Blueberry Muffin', 3, 'Muffin lembut dengan blueberry dan crumble renyah', 26000, 26, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.8, 0),
('Banana Bread Walnut', 3, 'Roti pisang lembut dengan potongan walnut panggang', 42000, 20, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.9, 0),
('Focaccia Rosemary', 3, 'Roti focaccia lembut dengan rosemary dan minyak zaitun', 38000, 20, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.8, 0),
('Pretzel Keju', 3, 'Pretzel lembut bertabur keju dengan rasa gurih', 28000, 24, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.8, 0),
('Bomboloni Cokelat', 3, 'Donat bomboloni empuk dengan isian cokelat lumer', 30000, 24, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400', 'New', 4.9, 0),
('Pain aux Raisins', 3, 'Pastry spiral dengan custard dan kismis manis', 34000, 22, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', 'New', 4.8, 0),
('Sausage Roll', 3, 'Pastry gurih berlapis dengan isian sosis pilihan', 32000, 24, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400', 'New', 4.8, 0);

INSERT INTO testimonials (user_name, role, content, rating) VALUES
('Sarah Amelia', 'Corporate Event Organizer', 'Red Velvet-nya juara banget! Lembut, cream cheese-nya gak pelit. Jadi langganan buat acara kantor.', 5),
('Budi Prasetyo', 'Pelanggan Setia', 'Pesan custom cake untuk ulang tahun anak, hasilnya cantik dan rasanya enak.', 5),
('Diana Wijaya', 'Food Blogger', 'Nastar-nya lumer di mulut. Setiap lebaran wajib pesan di sini.', 5);

-- User admin default (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Admin RiRa', 'admin@riracakes.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2u6Vc7e0XK.', 'admin');

-- ============================================
-- UPDATE: Payment Details & Admin Features
-- ============================================

-- Tambah kolom payment details di orders
ALTER TABLE orders ADD COLUMN payment_details TEXT AFTER payment_method;
ALTER TABLE orders ADD COLUMN payment_proof VARCHAR(255) AFTER payment_details;
ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Tabel payment accounts (rekening toko)
CREATE TABLE IF NOT EXISTS payment_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method ENUM('transfer', 'qris') NOT NULL,
    bank_name VARCHAR(50),
    account_number VARCHAR(50),
    account_holder VARCHAR(100),
    qr_image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE
);

INSERT INTO payment_accounts (method, bank_name, account_number, account_holder, qr_image_url) VALUES
('transfer', 'BCA', '8700195784', 'RIRA CAKES MANIS LEGIT', NULL),
('qris', 'QRIS', NULL, 'RIRA CAKES MANIS LEGIT', 'uploads/qris/qris-gopay-rira.png');

-- Tabel whatsapp config
CREATE TABLE IF NOT EXISTS whatsapp_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider ENUM('fonnte', 'wablas', 'wa_link') DEFAULT 'wa_link',
    api_token VARCHAR(255),
    admin_phone VARCHAR(20) NOT NULL DEFAULT '6281219868390',
    is_active BOOLEAN DEFAULT TRUE
);

INSERT INTO whatsapp_config (provider, admin_phone) VALUES ('wa_link', '6281219868390');

-- Tambah user customer sample (password: customer123)
INSERT INTO users (name, email, password, phone, role) VALUES
('John Customer', 'customer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2u6Vc7e0XK.', '081234567890', 'customer');

UPDATE products
SET image_url = CASE name
    WHEN 'Red Velvet Dream' THEN 'https://live.staticflickr.com/65535/54042719332_5fc67ecbcc_b.jpg'
    WHEN 'Chocolate Lava Cake' THEN 'https://live.staticflickr.com/65535/51716904256_18c4e35c7f_b.jpg'
    WHEN 'Classic Nastar Premium' THEN 'https://upload.wikimedia.org/wikipedia/commons/b/b0/Tat_nanas_%28Pineapple_tart%29_20230425_084201.jpg'
    WHEN 'Croissant Butter Almond' THEN 'https://pd.w.org/2023/02/2663f1e169ea6065.20469854-1536x2048.jpg'
    WHEN 'Tiramisu Cup' THEN 'https://pd.w.org/2026/06/1366a2adff62c60c6.47039487-1775x2048.jpg'
    WHEN 'Kastengel Keju Edam' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvZmY0ODUxLWltYWdlLWt3dnlpazZrLmpwZw.jpg'
    WHEN 'Lapis Legit Pandan' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Buko_Pandan_cake_%28Philippines%29.jpg'
    WHEN 'Klepon Cake' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Buko_Pandan_cake_%28Philippines%29.jpg'
    WHEN 'Bolu Kukus Mekar' THEN 'https://live.staticflickr.com/9/12197033_6f170c9814_b.jpg'
    WHEN 'Kue Lumpur Kentang' THEN 'https://upload.wikimedia.org/wikipedia/commons/b/b7/Childhood_taro_cake_%281%29.jpg'
    WHEN 'Dadar Gulung Pandan' THEN 'https://live.staticflickr.com/65535/49322379022_6f2b552767_b.jpg'
    WHEN 'Onde-Onde Wijen' THEN 'https://upload.wikimedia.org/wikipedia/commons/1/1f/Black_sesame_balls_%281%29.jpg'
    WHEN 'Putu Ayu Kelapa' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Buko_Pandan_cake_%28Philippines%29.jpg'
    WHEN 'Lemper Ayam Suwir' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/3e/Three-color_glutinous_rice_cake.jpg'
    WHEN 'Pastel Ayam Sayur' THEN 'https://live.staticflickr.com/4539/26810206209_70e167cac4_b.jpg'
    WHEN 'Risoles Mayo Smoked Beef' THEN 'https://live.staticflickr.com/2/1893350_a2f0aef5ba_b.jpg'
    WHEN 'Serabi Solo' THEN 'https://live.staticflickr.com/65535/49322379022_6f2b552767_b.jpg'
    WHEN 'Apem Gula Merah' THEN 'https://live.staticflickr.com/9/12197033_6f170c9814_b.jpg'
    WHEN 'Kue Talam Ubi Ungu' THEN 'https://upload.wikimedia.org/wikipedia/commons/b/b7/Childhood_taro_cake_%281%29.jpg'
    WHEN 'Nagasari Pisang' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvcHgxMTA3NzMyLWltYWdlLWt3eXI2dzBrLmpwZw.jpg'
    WHEN 'Brownies Kukus Keju' THEN 'https://live.staticflickr.com/7540/16013504652_8d3928ce3f_b.jpg'
    WHEN 'Bolu Pandan Santan' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Buko_Pandan_cake_%28Philippines%29.jpg'
    WHEN 'Lapis Beras Pelangi' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/3e/Three-color_glutinous_rice_cake.jpg'
    WHEN 'Kue Ku Kacang Hijau' THEN 'https://upload.wikimedia.org/wikipedia/commons/b/b8/Red_bean_cakes.jpg'
    WHEN 'Bika Ambon Mini' THEN 'https://live.staticflickr.com/9/12197033_6f170c9814_b.jpg'
    WHEN 'Chiffon Pandan' THEN 'https://upload.wikimedia.org/wikipedia/commons/1/13/Blood_Orange_Chiffon_Cake_with_Mango_Curd_%28Unsplash%29.jpg'
    WHEN 'Putri Salju Almond' THEN 'https://live.staticflickr.com/65535/53486971493_60bf84f5aa_b.jpg'
    WHEN 'Sagu Keju Premium' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvZmY0ODUxLWltYWdlLWt3dnlpazZrLmpwZw.jpg'
    WHEN 'Lidah Kucing Vanilla' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/32/Danish-butter-cookies_%28ring-shaped%29.jpg'
    WHEN 'Cookies Choco Chip' THEN 'https://live.staticflickr.com/7206/6858180551_babaac4d46_b.jpg'
    WHEN 'Thumbprint Strawberry' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvcHgxMDU4MjE0LWltYWdlLWt3dnk2b2xuLmpwZw.jpg'
    WHEN 'Semprit Butter' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/32/Danish-butter-cookies_%28ring-shaped%29.jpg'
    WHEN 'Snowball Almond' THEN 'https://live.staticflickr.com/65535/53486971493_60bf84f5aa_b.jpg'
    WHEN 'Palmier Caramel' THEN 'https://upload.wikimedia.org/wikipedia/commons/f/f8/PRC_China_%E6%8B%BC%E5%A4%9A%E5%A4%9A_PinDuoDuo_goods_%E8%9D%B4%E8%9D%B6%E9%85%A5_HuDieSu_palmier_sweet_pastry_December_2025_R12S_01.jpg'
    WHEN 'Oatmeal Raisin Cookies' THEN 'https://live.staticflickr.com/5697/30782275262_e220dee39b_b.jpg'
    WHEN 'Coffee Butter Cookies' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvaXMxMDMzOC1pbWFnZS1rd3lzaGh4ci5qcGc.jpg'
    WHEN 'Peanut Butter Cookies' THEN 'https://live.staticflickr.com/65535/51521747189_5e6715dabd_b.jpg'
    WHEN 'Florentine Almond' THEN 'https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9sci9pbWFnZXMvd2Vic2l0ZS8yMDIyLTA2L3Vwd2s2MTY2NTcyMC13aWtpbWVkaWEtaW1hZ2Uta293cjcxdGUuanBn.jpg'
    WHEN 'Cornflakes Madu' THEN 'https://live.staticflickr.com/5697/30782275262_e220dee39b_b.jpg'
    WHEN 'Kue Kacang Jadul' THEN 'https://upload.wikimedia.org/wikipedia/commons/5/53/Moroccan_Sweets.png'
    WHEN 'Cheese Straws' THEN 'https://upload.wikimedia.org/wikipedia/commons/0/04/Cheese_straws_-_Skipton%2C_North_Yorkshire%2C_England_-_DSC01040.jpg'
    WHEN 'Brownies Cookies' THEN 'https://live.staticflickr.com/7540/16013504652_8d3928ce3f_b.jpg'
    WHEN 'Coconut Macaroons' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d4/Filipino_Coconut_Macaroons.jpg'
    WHEN 'Biscotti Almond' THEN 'https://live.staticflickr.com/65535/53454414052_930dc76121_b.jpg'
    WHEN 'Speculoos Cinnamon Cookies' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/32/Danish-butter-cookies_%28ring-shaped%29.jpg'
    WHEN 'Macaron Vanilla' THEN 'https://live.staticflickr.com/513/19574196885_5db826f1aa_b.jpg'
    WHEN 'Pain au Chocolat' THEN 'https://live.staticflickr.com/7639/17005688181_e80fbed5e0_b.jpg'
    WHEN 'Danish Custard' THEN 'https://upload.wikimedia.org/wikipedia/commons/c/c9/Cheese_danish_-_Massachusetts.jpg'
    WHEN 'Cinnamon Roll' THEN 'https://live.staticflickr.com/7645/17056089741_d930d4f865_b.jpg'
    WHEN 'Garlic Bread' THEN 'https://live.staticflickr.com/2838/34381029365_db022f9d59_b.jpg'
    WHEN 'Roti Sobek Cokelat' THEN 'https://live.staticflickr.com/305/19236165651_c91c6b317b_b.jpg'
    WHEN 'Roti Sisir Butter' THEN 'https://live.staticflickr.com/2481/3544028525_53521e4757_b.jpg'
    WHEN 'Brioche Butter' THEN 'https://pd.w.org/2023/04/959642c1dbe025568.88008891-2048x1536.jpg'
    WHEN 'Sourdough Country Loaf' THEN 'https://live.staticflickr.com/2481/3544028525_53521e4757_b.jpg'
    WHEN 'Baguette Artisan' THEN 'https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9zdGF0aWMvaW1hZ2Uvd2Vic2l0ZS8yMDIyLTA0L2xyL2lzMTU4ODYtaW1hZ2Uta3d2d3RsNG4uanBn.jpg'
    WHEN 'Cheese Danish' THEN 'https://upload.wikimedia.org/wikipedia/commons/c/c9/Cheese_danish_-_Massachusetts.jpg'
    WHEN 'Apple Turnover' THEN 'https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9zdGF0aWMvaW1hZ2Uvd2Vic2l0ZS8yMDIyLTA0L2xyL2ZycGFzdHJ5X3Bhc3RyaWVzX2FwcGxlX2JpdGVzLWltYWdlLWt5YmF4ejY5LmpwZw.jpg'
    WHEN 'Egg Tart Portugis' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvZmw0OTgwMTA5NTkyMi1pbWFnZS1reWJlZzNueC5qcGc.jpg'
    WHEN 'Pain Suisse Vanilla' THEN 'https://live.staticflickr.com/7639/17005688181_e80fbed5e0_b.jpg'
    WHEN 'Blueberry Muffin' THEN 'https://live.staticflickr.com/2560/3711698510_d05f4f2f86_b.jpg'
    WHEN 'Banana Bread Walnut' THEN 'https://live.staticflickr.com/2913/33276667233_485a2158ef_b.jpg'
    WHEN 'Focaccia Rosemary' THEN 'https://upload.wikimedia.org/wikipedia/commons/3/3d/Focaccia_genovese_su_tagliere_in_legno%2C_1.jpg'
    WHEN 'Pretzel Keju' THEN 'https://pd.w.org/2022/03/669623f7e659eb641.51199595-1536x2048.jpg'
    WHEN 'Bomboloni Cokelat' THEN 'https://live.staticflickr.com/65535/54280572676_034f1ce6f0_b.jpg'
    WHEN 'Pain aux Raisins' THEN 'https://upload.wikimedia.org/wikipedia/commons/d/d4/Pain_aux_raisins_%28cropped%29.jpg'
    WHEN 'Sausage Roll' THEN 'https://images.rawpixel.com/editor_1024/czNmcy1wcml2YXRlL3Jhd3BpeGVsX2ltYWdlcy93ZWJzaXRlX2NvbnRlbnQvbHIvYTAxOS1qYWt1YmstMDU5NC1zYXVzYWdlLXJvbGxzLmpwZw.jpg'
    ELSE image_url
END
WHERE name IN (
    'Red Velvet Dream', 'Chocolate Lava Cake', 'Classic Nastar Premium', 'Croissant Butter Almond',
    'Tiramisu Cup', 'Kastengel Keju Edam', 'Lapis Legit Pandan', 'Klepon Cake', 'Bolu Kukus Mekar',
    'Kue Lumpur Kentang', 'Dadar Gulung Pandan', 'Onde-Onde Wijen', 'Putu Ayu Kelapa', 'Lemper Ayam Suwir',
    'Pastel Ayam Sayur', 'Risoles Mayo Smoked Beef', 'Serabi Solo', 'Apem Gula Merah', 'Kue Talam Ubi Ungu',
    'Nagasari Pisang', 'Brownies Kukus Keju', 'Bolu Pandan Santan', 'Lapis Beras Pelangi', 'Kue Ku Kacang Hijau',
    'Bika Ambon Mini', 'Chiffon Pandan', 'Putri Salju Almond', 'Sagu Keju Premium', 'Lidah Kucing Vanilla',
    'Cookies Choco Chip', 'Thumbprint Strawberry', 'Semprit Butter', 'Snowball Almond', 'Palmier Caramel',
    'Oatmeal Raisin Cookies', 'Coffee Butter Cookies', 'Peanut Butter Cookies', 'Florentine Almond',
    'Cornflakes Madu', 'Kue Kacang Jadul', 'Cheese Straws', 'Brownies Cookies', 'Coconut Macaroons',
    'Biscotti Almond', 'Speculoos Cinnamon Cookies', 'Macaron Vanilla', 'Pain au Chocolat', 'Danish Custard',
    'Cinnamon Roll', 'Garlic Bread', 'Roti Sobek Cokelat', 'Roti Sisir Butter', 'Brioche Butter',
    'Sourdough Country Loaf', 'Baguette Artisan', 'Cheese Danish', 'Apple Turnover', 'Egg Tart Portugis',
    'Pain Suisse Vanilla', 'Blueberry Muffin', 'Banana Bread Walnut', 'Focaccia Rosemary', 'Pretzel Keju',
    'Bomboloni Cokelat', 'Pain aux Raisins', 'Sausage Roll'
);