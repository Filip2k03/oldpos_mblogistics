
CREATE TABLE `regions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `region_code` VARCHAR(10) NOT NULL UNIQUE,
    `region_name` VARCHAR(255) NOT NULL,
    `prefix` VARCHAR(10) NOT NULL UNIQUE,
    `current_sequence` INT NOT NULL DEFAULT 0,
    `price_per_kg` DECIMAL(10, 2) NOT NULL DEFAULT 0.00
);


INSERT INTO `regions` (`id`, `region_code`, `region_name`, `prefix`, `current_sequence`, `price_per_kg`) VALUES
(1, 'MM', 'Myanmar', 'MM', 0, 0.00),
(2, 'CA', 'Canada', 'CA', 0, 0.00),
(3, 'TH', 'Thailand', 'TH', 0, 0.00),
(4, 'MY', 'Malaysia', 'MY', 0, 0.00),
(5, 'AU', 'Australia', 'AU', 0, 0.00),
(6, 'NZ', 'New Zealand', 'NZ', 0, 0.00),
(7, 'US', 'United States', 'US', 0, 0.00);



CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(191) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL, -- Store hashed passwords
    `user_type` ENUM('ADMIN', 'Myanmar', 'Malay', 'General') NOT NULL DEFAULT 'General',
    `region_id` INT, -- Foreign key to regions table
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE SET NULL
);


CREATE TABLE `vouchers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_code` VARCHAR(50) NOT NULL UNIQUE, -- e.g., MM0000001
    `sender_name` VARCHAR(255) NOT NULL,
    `sender_phone` VARCHAR(50) NOT NULL,
    `sender_address` TEXT,
    `use_sender_address_for_checkout` BOOLEAN DEFAULT FALSE,
    `receiver_name` VARCHAR(255) NOT NULL,
    `receiver_phone` VARCHAR(50) NOT NULL,
    `receiver_address` TEXT NOT NULL, -- Required
    `payment_method` VARCHAR(100),
    `weight_kg` DECIMAL(10, 2) NOT NULL,
    `price_per_kg_at_voucher` DECIMAL(10, 2) NOT NULL, -- Price at the time of voucher creation
    `delivery_charge` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `delivery_type` VARCHAR(100), -- e.g., 'Standard', 'Express'
    `notes` TEXT,
    `region_id` INT NOT NULL, -- Origin region of the voucher
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);


CREATE TABLE `voucher_breakdowns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_id` INT NOT NULL,
    `item_type` VARCHAR(255), -- e.g., 'Document', 'Package', 'Electronics'
    `kg` DECIMAL(10, 2), -- Weight for this specific item (optional if not weight-based)
    `price_per_kg` DECIMAL(10, 2), -- Price per kg for this item (optional)
    `item_breakdown_json` JSON, -- Flexible storage for item details (e.g., {"description": "Clothes", "quantity": 5})
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`voucher_id`) REFERENCES `vouchers`(`id`) ON DELETE CASCADE
);

CREATE TABLE `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `description` TEXT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `expense_date` DATE NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE other_income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    income_date DATE NOT NULL,
    created_by_user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE `stock` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_id` INT NOT NULL,
    `status` ENUM('pending', 'arrived') NOT NULL DEFAULT 'pending',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`voucher_id`) REFERENCES `vouchers`(`id`) ON DELETE CASCADE
);

ALTER TABLE `stock` ADD COLUMN `region_id` INT DEFAULT NULL;

ALTER TABLE `vouchers`
ADD COLUMN `destination_region_id` INT DEFAULT NULL AFTER `region_id`,
ADD CONSTRAINT `fk_destination_region` FOREIGN KEY (`destination_region_id`) REFERENCES `regions`(`id`) ON DELETE SET NULL;