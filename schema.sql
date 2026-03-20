-- ============================================================
-- ASA Paillon - Schema base de donnees MySQL
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+01:00';

DROP TRIGGER IF EXISTS `before_arrosants_insert`;
DROP TRIGGER IF EXISTS `before_arrosants_update`;
DROP FUNCTION IF EXISTS `calcul_cotisation_v2`;
DROP FUNCTION IF EXISTS `calcul_cotisation`;
DROP FUNCTION IF EXISTS `calcul_cotisation_simul_v2`;
DROP FUNCTION IF EXISTS `calcul_cotisation_simul`;

CREATE TABLE IF NOT EXISTS `tarifs` (
    `m2`          INT NOT NULL PRIMARY KEY,
    `tarif`       DECIMAL(8,2) NOT NULL,
    `tarif_simul` DECIMAL(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tarifs` (`m2`, `tarif`, `tarif_simul`) VALUES
    (0,    0.00, 0.00),
    (1,    13.60, 13.60),
    (251,  23.50, 23.50),
    (501,  33.30, 33.30),
    (751,  39.50, 39.50),
    (1001, 48.00, 48.00),
    (1251, 54.40, 54.40),
    (1501, 60.70, 60.70),
    (1751, 69.30, 69.30),
    (2001, 78.00, 78.00),
    (2501, 85.40, 85.40),
    (3001, 93.30, 93.30)
ON DUPLICATE KEY UPDATE
    `tarif` = VALUES(`tarif`),
    `tarif_simul` = VALUES(`tarif_simul`);

CREATE FUNCTION `calcul_cotisation_v2`(p_surface_m2 DECIMAL(10,2), p_puisant TINYINT)
RETURNS DECIMAL(8,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_surface INT;
    DECLARE v_tarif DECIMAL(8,2);

    IF IFNULL(p_puisant, 0) = 1 THEN
        SELECT t.tarif INTO v_tarif
          FROM tarifs t
         WHERE t.m2 = 0
         LIMIT 1;

        RETURN v_tarif;
    END IF;

    IF p_surface_m2 IS NULL OR p_surface_m2 <= 0 THEN
        RETURN NULL;
    END IF;

    SET v_surface = LEAST(GREATEST(CEILING(p_surface_m2), 1), 3501);

    SELECT t.tarif INTO v_tarif
      FROM tarifs t
     WHERE t.m2 <= v_surface
     ORDER BY t.m2 DESC
     LIMIT 1;

    RETURN v_tarif;
END;

CREATE FUNCTION `calcul_cotisation`(p_surface_m2 DECIMAL(10,2))
RETURNS DECIMAL(8,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    RETURN calcul_cotisation_v2(p_surface_m2, 0);
END;

CREATE FUNCTION `calcul_cotisation_simul_v2`(p_surface_m2 DECIMAL(10,2), p_puisant TINYINT)
RETURNS DECIMAL(8,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_surface INT;
    DECLARE v_tarif DECIMAL(8,2);

    IF IFNULL(p_puisant, 0) = 1 THEN
        SELECT t.tarif_simul INTO v_tarif
          FROM tarifs t
         WHERE t.m2 = 0
         LIMIT 1;

        RETURN v_tarif;
    END IF;

    IF p_surface_m2 IS NULL OR p_surface_m2 <= 0 THEN
        RETURN NULL;
    END IF;

    SET v_surface = LEAST(GREATEST(CEILING(p_surface_m2), 1), 3501);

    SELECT t.tarif_simul INTO v_tarif
      FROM tarifs t
     WHERE t.m2 <= v_surface
     ORDER BY t.m2 DESC
     LIMIT 1;

    RETURN v_tarif;
END;

CREATE FUNCTION `calcul_cotisation_simul`(p_surface_m2 DECIMAL(10,2))
RETURNS DECIMAL(8,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    RETURN calcul_cotisation_simul_v2(p_surface_m2, 0);
END;

CREATE TABLE IF NOT EXISTS `arrosants` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `annee`         YEAR NOT NULL DEFAULT 2025,
    `civilite`      VARCHAR(20) DEFAULT NULL,
    `nom`           VARCHAR(200) NOT NULL,
    `rue`           VARCHAR(250) DEFAULT NULL,
    `adresse2`      VARCHAR(250) DEFAULT NULL,
    `quartier`      VARCHAR(150) DEFAULT NULL,
    `code_postal`   VARCHAR(10) DEFAULT NULL,
    `ville`         VARCHAR(100) DEFAULT NULL,
    `parcelles`     TEXT DEFAULT NULL,
    `surface_m2`    DECIMAL(10,2) DEFAULT NULL,
    `puisant`       TINYINT(1) NOT NULL DEFAULT 0,
    `taxe_annuelle` DECIMAL(8,2) DEFAULT NULL,
    `cotisation`    DECIMAL(8,2) DEFAULT NULL,
    `date_maj`      DATE DEFAULT NULL,
    `notes`         TEXT DEFAULT NULL,
    `actif`         TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER `before_arrosants_insert`
BEFORE INSERT ON `arrosants`
FOR EACH ROW
BEGIN
    SET NEW.cotisation = calcul_cotisation_v2(NEW.surface_m2, NEW.puisant);
END;

CREATE TRIGGER `before_arrosants_update`
BEFORE UPDATE ON `arrosants`
FOR EACH ROW
BEGIN
    SET NEW.cotisation = calcul_cotisation_v2(NEW.surface_m2, NEW.puisant);
END;

CREATE TABLE IF NOT EXISTS `utilisateurs` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `nom`        VARCHAR(100) NOT NULL,
    `prenom`     VARCHAR(100) DEFAULT NULL,
    `email`      VARCHAR(200) NOT NULL UNIQUE,
    `telephone`  VARCHAR(20) DEFAULT NULL,
    `role`       ENUM('lecteur','editeur','admin') NOT NULL DEFAULT 'lecteur',
    `actif`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `otp_codes` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `email`      VARCHAR(200) NOT NULL,
    `code`       VARCHAR(6) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used`       TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_code` (`email`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions_log` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `utilisateur_id` INT DEFAULT NULL,
    `action`         VARCHAR(50) NOT NULL,
    `detail`         TEXT DEFAULT NULL,
    `ip`             VARCHAR(45) DEFAULT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `destinataires` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `nom`         VARCHAR(200) NOT NULL,
    `categorie`   VARCHAR(100) DEFAULT NULL,
    `adresse_1`   VARCHAR(250) DEFAULT NULL,
    `adresse_2`   VARCHAR(250) DEFAULT NULL,
    `code_postal` VARCHAR(10) DEFAULT NULL,
    `ville`       VARCHAR(100) DEFAULT NULL,
    `telephone`   VARCHAR(30) DEFAULT NULL,
    `email`       VARCHAR(200) DEFAULT NULL,
    `notes`       TEXT DEFAULT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_nom` (`nom`),
    INDEX `idx_categorie` (`categorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key`   VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` VARCHAR(255) DEFAULT NULL,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('simulation_percentage', '100')
ON DUPLICATE KEY UPDATE
    `setting_value` = `setting_value`;

UPDATE `arrosants`
   SET `cotisation` = calcul_cotisation_v2(`surface_m2`, `puisant`);

INSERT INTO `utilisateurs` (`nom`, `prenom`, `email`, `role`, `actif`)
VALUES ('Administrateur', 'ASA', 'votre-email@exemple.com', 'admin', 1);
