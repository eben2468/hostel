-- Inventory / Assets module
USE chms_hostel;

CREATE TABLE IF NOT EXISTS inventory (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    category    ENUM('bed','mattress','table','chair','wardrobe','fan','fire_extinguisher','other') NOT NULL DEFAULT 'other',
    hostel_id   INT          NULL,
    room_id     INT          NULL,
    quantity    INT          NOT NULL DEFAULT 1,
    `condition` ENUM('new','good','fair','damaged','replaced') NOT NULL DEFAULT 'good',
    reorder_level INT        NOT NULL DEFAULT 0,
    notes       VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_room   FOREIGN KEY (room_id)   REFERENCES rooms(id) ON DELETE SET NULL,
    INDEX idx_inventory_category (category)
) ENGINE=InnoDB;
