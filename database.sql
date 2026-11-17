CREATE DATABASE IF NOT EXISTS hotel_sladkie_sny
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_sladkie_sny;

CREATE TABLE rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('standard', 'studio', 'lux') NOT NULL UNIQUE,
    price_min DECIMAL(10,2) NOT NULL,
    price_max DECIMAL(10,2) NOT NULL,
    capacity TINYINT UNSIGNED NULL,
    area DECIMAL(6,2) NULL,
    description VARCHAR(500) NULL,
    image VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rooms_category_price (category, price_min, price_max),
    INDEX idx_rooms_capacity (capacity),
    CONSTRAINT chk_room_price CHECK (price_min >= 0 AND price_max >= price_min),
    CONSTRAINT chk_room_capacity CHECK (capacity IS NULL OR capacity > 0),
    CONSTRAINT chk_room_area CHECK (area IS NULL OR area > 0)
) ENGINE=InnoDB;

CREATE TABLE amenities (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE room_amenities (
    room_id INT UNSIGNED NOT NULL,
    amenity_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (room_id, amenity_id),
    CONSTRAINT fk_room_amenities_room FOREIGN KEY (room_id)
        REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_room_amenities_amenity FOREIGN KEY (amenity_id)
        REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(254) NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    status ENUM('new', 'approved', 'rejected') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bookings_room (room_id),
    INDEX idx_bookings_status_created (status, created_at),
    INDEX idx_bookings_dates (check_in, check_out),
    INDEX idx_bookings_availability (room_id, status, check_in, check_out),
    CONSTRAINT fk_bookings_room FOREIGN KEY (room_id)
        REFERENCES rooms(id) ON UPDATE CASCADE,
    CONSTRAINT chk_booking_dates CHECK (check_out > check_in)
) ENGINE=InnoDB;

CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Цены и удобства взяты из файла «Характеристики номеров.txt».
-- Площадь и вместимость оставлены NULL: исходные материалы не задают эти значения.
INSERT INTO rooms (category, price_min, price_max, capacity, area, description, image) VALUES
('standard', 3000, 3800, NULL, NULL, 'Номер с полным санузлом и двуспальной кроватью.', 'img/standart.png'),
('studio', 4600, 6200, NULL, NULL, 'Номер с кухонным уголком и холодильником.', 'img/studio.jpg'),
('lux', 8500, 15000, NULL, NULL, 'Двухкомнатный номер с кондиционером.', 'img/lux.png');

INSERT INTO amenities (name) VALUES
('Полный санузел'),
('Двуспальная кровать'),
('Телевизор'),
('Кухонный уголок'),
('Холодильник'),
('Две комнаты'),
('Кондиционер');

INSERT INTO room_amenities (room_id, amenity_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5),
(3, 1), (3, 2), (3, 3), (3, 5), (3, 6), (3, 7);
-- Учётная запись из требований задания 4. Пароль хранится только в виде хеша.
INSERT INTO admins (username, password_hash) VALUES ('hotel123', '$2y$12$AZi6jZRh2JDf6/Jl5VkwmO4Uq4uLnE9HSjN4il4AAFLGd23pGiytS')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);
