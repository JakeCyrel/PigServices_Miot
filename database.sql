CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    address VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'male_owner', 'female_owner', 'veterinarian') NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id INT UNSIGNED NOT NULL,
    service_type ENUM('breeding', 'veterinary') NOT NULL DEFAULT 'breeding',
    pig_name VARCHAR(120) NOT NULL,
    breed VARCHAR(100) NOT NULL,
    age_months TINYINT UNSIGNED NOT NULL,
    location VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(30) NOT NULL UNIQUE,
    service_id INT UNSIGNED NOT NULL,
    female_owner_id INT UNSIGNED NOT NULL,
    preferred_date DATE NOT NULL,
    notes TEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_request_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_female FOREIGN KEY (female_owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_message_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_message_recipient_read (recipient_id, read_at),
    INDEX idx_message_request_created (request_id, created_at)
) ENGINE=InnoDB;

INSERT INTO users (full_name, email, phone, address, password_hash, role, is_verified)
VALUES ('Demo Male Pig Owner', 'male@pigbreeding.local', '09171234567', 'Bindoy, Negros Oriental', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCjQz2VQx5zqN8cT7S5i', 'male_owner', 1);

INSERT INTO services (owner_id, pig_name, breed, age_months, location, description)
VALUES (1, 'Golden Boar', 'Large White', 18, 'Poblacion, Bindoy', 'Healthy, active boar available for scheduled natural breeding service.');

INSERT INTO services (owner_id, service_type, pig_name, breed, age_months, location, description)
VALUES
(1, 'veterinary', 'Animal health consultation', 'Veterinary care', 0, 'Bindoy and nearby communities', 'Professional guidance for prescribed antibiotic injections, vitamin injections, deworming, vaccinations, diagnosed disease treatment, and follow-up checkups.');
