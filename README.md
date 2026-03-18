CREATE TABLE request_land_details (
    id INT(11) NOT NULL AUTO_INCREMENT,
    request_id INT(11) NOT NULL,
    declared_owner VARCHAR(255) NOT NULL,
    owner_address VARCHAR(255) NOT NULL,
    property_location VARCHAR(255) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    lot VARCHAR(255) NOT NULL,
    arp_no VARCHAR(255) NOT NULL,
    area VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_request_id (request_id),
    CONSTRAINT fk_request_land_details_request
        FOREIGN KEY (request_id) REFERENCES requests(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE DATABASE IF NOT EXISTS db_tax_archive
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE db_tax_archive;

CREATE TABLE IF NOT EXISTS import_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(255) NOT NULL,
    source_file VARCHAR(255) NOT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    total_rows INT NOT NULL DEFAULT 0,
    inserted_rows INT NOT NULL DEFAULT 0,
    skipped_rows INT NOT NULL DEFAULT 0,
    duplicate_rows INT NOT NULL DEFAULT 0,
    imported_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS taxpayer_raw_imports (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    row_num INT NOT NULL,

    type VARCHAR(20) DEFAULT NULL,
    txn_date VARCHAR(50) DEFAULT NULL,
    taxpayer_name VARCHAR(255) DEFAULT NULL,
    period VARCHAR(100) DEFAULT NULL,
    or_no VARCHAR(100) DEFAULT NULL,
    td_no VARCHAR(100) DEFAULT NULL,
    barangay_name VARCHAR(150) DEFAULT NULL,

    r1 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r2 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r3 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r4 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r5 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r6 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r7 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r8 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r9 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r10 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r11 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r12 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r13 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    r14 DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    row_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_taxpayer_raw_batch
        FOREIGN KEY (batch_id) REFERENCES import_batches(id)
        ON DELETE CASCADE,

    KEY idx_batch_id (batch_id),
    KEY idx_taxpayer_name (taxpayer_name),
    KEY idx_period (period),
    KEY idx_or_no (or_no),
    KEY idx_td_no (td_no),
    KEY idx_barangay_name (barangay_name),
    KEY idx_row_hash (row_hash),

    UNIQUE KEY uq_batch_rowhash (batch_id, row_hash)
);

ALTER TABLE taxpayer_raw_imports DROP INDEX uq_batch_rowhash;