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