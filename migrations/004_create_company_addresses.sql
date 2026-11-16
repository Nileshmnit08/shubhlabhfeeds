-- =============================================================================
-- Migration 004: Create Company Addresses Management Table
-- Shubh Labh CMS - Complete Address Management for Public Website
-- =============================================================================

CREATE TABLE IF NOT EXISTS company_addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(150) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) NULL,
    landmark VARCHAR(150) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'India',
    office_phone VARCHAR(50) NULL,
    whatsapp_number VARCHAR(50) NULL,
    email VARCHAR(191) NULL,
    google_maps_url TEXT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, display_order),
    INDEX idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed verified initial primary office & works address
INSERT INTO company_addresses (
    location_name,
    address_line1,
    address_line2,
    landmark,
    city,
    state,
    pincode,
    country,
    office_phone,
    whatsapp_number,
    email,
    google_maps_url,
    display_order,
    is_active,
    is_primary
) VALUES (
    'Head Office & Manufacturing Works',
    'RIICO Industrial Area',
    'Plot No. 42-45, Agro Food Park',
    'Near Highway By-Pass',
    'Jaipur',
    'Rajasthan',
    '302013',
    'India',
    '+91 94619 24461',
    '+91 94619 24461',
    'contact@shubhlabhfeeds.in',
    'https://maps.google.com/?q=Shubh+Labh+Cattle+Feed+Rajasthan',
    1,
    1,
    1
);
