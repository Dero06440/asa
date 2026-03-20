UPDATE `utilisateurs` SET `role` = 'lecteur' WHERE `role` = 'lecture';
UPDATE `utilisateurs` SET `role` = 'editeur' WHERE `role` = 'ecriture';

ALTER TABLE `utilisateurs`
    MODIFY COLUMN `role` ENUM('lecteur','editeur','admin') NOT NULL DEFAULT 'lecteur';
