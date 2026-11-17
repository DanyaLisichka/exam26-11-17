USE hotel_sladkie_sny;

-- Запустите один раз, если база данных задания 3 уже создана.
-- Дополнительные таблицы не нужны: даты и статусы хранятся в bookings.
ALTER TABLE bookings
    ADD INDEX idx_bookings_availability (room_id, status, check_in, check_out);

-- Логин/пароль из задания 4: hotel123 / adminHotel.
-- Пароль уже захеширован; открытый пароль в таблицу не записывается.
INSERT INTO admins (username, password_hash) VALUES ('hotel123', '$2y$12$AZi6jZRh2JDf6/Jl5VkwmO4Uq4uLnE9HSjN4il4AAFLGd23pGiytS')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash);
