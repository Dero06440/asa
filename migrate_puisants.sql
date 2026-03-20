ALTER TABLE `arrosants`
    ADD COLUMN `puisant` TINYINT(1) NOT NULL DEFAULT 0 AFTER `surface_m2`;

INSERT INTO `tarifs` (`m2`, `tarif`, `tarif_simul`)
VALUES (0, 0.00, 0.00)
ON DUPLICATE KEY UPDATE
    `tarif` = VALUES(`tarif`),
    `tarif_simul` = VALUES(`tarif_simul`);

DROP TRIGGER IF EXISTS `before_arrosants_insert`;
DROP TRIGGER IF EXISTS `before_arrosants_update`;
DROP FUNCTION IF EXISTS `calcul_cotisation_v2`;
DROP FUNCTION IF EXISTS `calcul_cotisation`;
DROP FUNCTION IF EXISTS `calcul_cotisation_simul_v2`;
DROP FUNCTION IF EXISTS `calcul_cotisation_simul`;

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
     WHERE t.m2 <= v_surface AND t.m2 > 0
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
     WHERE t.m2 <= v_surface AND t.m2 > 0
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

UPDATE `arrosants`
   SET `cotisation` = calcul_cotisation_v2(`surface_m2`, `puisant`);
