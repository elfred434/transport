CREATE DATABASE IF NOT EXISTS transport_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transport_db;

-- Table utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100),
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255),
    telephone VARCHAR(30),
    photo_profil VARCHAR(255),
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table colis
CREATE TABLE IF NOT EXISTS colis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    nom_colis VARCHAR(100) NOT NULL,
    image_colis VARCHAR(255),
    type_produit VARCHAR(50),
    nombre_produits INT,
    poids DECIMAL(6,2),
    dimensions VARCHAR(50),
    pays VARCHAR(100),
    ville VARCHAR(100),
    date_limite DATE,
    adresse_depart VARCHAR(255),
    adresse_destination VARCHAR(255),
    prix_estime DECIMAL(8,2),
    date_post DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('en_attente','approuve','refuse') DEFAULT 'en_attente',
    numero_suivi VARCHAR(50) UNIQUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table transporteurs (voyages)
CREATE TABLE IF NOT EXISTS voyages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    pays_depart VARCHAR(100),
    pays_destination VARCHAR(100),
    date_depart DATE,
    heure_depart TIME,
    poids_max DECIMAL(6,2),
    email VARCHAR(150),
    telephone VARCHAR(30),
    date_post DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ALTER TABLE voyages MODIFY statut ENUM('en_attente','approuve','refuse') DEFAULT 'en_attente';
);

-- Table reservations
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colis_id INT,
    voyage_id INT,
    statut ENUM('en_attente','accepte','refuse','termine') DEFAULT 'en_attente',
    date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colis_id) REFERENCES colis(id) ON DELETE CASCADE,
    FOREIGN KEY (voyage_id) REFERENCES voyages(id) ON DELETE CASCADE
);

-- Table suivi_colis
CREATE TABLE IF NOT EXISTS suivi_colis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    colis_id INT NOT NULL,
    statut VARCHAR(100) NOT NULL,
    date_etape DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (colis_id) REFERENCES colis(id) ON DELETE CASCADE
);

-- Table transporteurs
CREATE TABLE IF NOT EXISTS transporteurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    numero_permis VARCHAR(100),
    vehicule VARCHAR(100),
    compagnie VARCHAR(100),
    adresse VARCHAR(255),
    ville VARCHAR(100),
    pays VARCHAR(100),
    photo_vehicule VARCHAR(255),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table pour les messages de contact
CREATE TABLE IF NOT EXISTS messages_contact (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP
);