ALTER TABLE `tarifs`
    CHANGE COLUMN `tarifs` `tarif` DECIMAL(8,2) NOT NULL,
    CHANGE COLUMN `tarifs_test` `tarif_simul` DECIMAL(8,2) DEFAULT NULL;

UPDATE `tarifs`
   SET `tarif_simul` = `tarif`
 WHERE `tarif_simul` IS NULL;

DROP TRIGGER IF EXISTS `before_arrosants_insert`;
DROP TRIGGER IF EXISTS `before_arrosants_update`;
DROP FUNCTION IF EXISTS `calcul_cotisation`;
DROP FUNCTION IF EXISTS `calcul_cotisation_simul`;

CREATE FUNCTION `calcul_cotisation`(p_surface_m2 DECIMAL(10,2))
RETURNS DECIMAL(8,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_surface INT;
    DECLARE v_tarif DECIMAL(8,2);

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

CREATE FUNCTION `calcul_cotisation_simul`(p_surface_m2 DECIMAL(10,2))
RETURNS DECIMAL(8,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_surface INT;
    DECLARE v_tarif DECIMAL(8,2);

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

CREATE TRIGGER `before_arrosants_insert`
BEFORE INSERT ON `arrosants`
FOR EACH ROW
BEGIN
    SET NEW.cotisation = calcul_cotisation(NEW.surface_m2);
END;

CREATE TRIGGER `before_arrosants_update`
BEFORE UPDATE ON `arrosants`
FOR EACH ROW
BEGIN
    SET NEW.cotisation = calcul_cotisation(NEW.surface_m2);
END;

UPDATE `arrosants`
   SET `cotisation` = calcul_cotisation(`surface_m2`);
