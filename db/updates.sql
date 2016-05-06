ALTER TABLE `moodle28`.`mdl_icontent_pages` 
ADD COLUMN `maxnotesperpages` SMALLINT(5) NOT NULL DEFAULT 0 COMMENT 'Maximo de anotacao por pagina.' AFTER `hidden`,
ADD COLUMN `maxquestionsperpages` SMALLINT(3) NOT NULL DEFAULT 0 COMMENT 'Maximo de questoes por pagina.' AFTER `maxnotesperpages`;


ALTER TABLE `moodle28`.`mdl_icontent` 
ADD COLUMN `maxnotesperpages` SMALLINT(5) NOT NULL DEFAULT 0 COMMENT 'Maximo de anotacao por pagina.' AFTER `copyright`,
ADD COLUMN `maxquestionsperpages` SMALLINT(3) NOT NULL DEFAULT 0 COMMENT 'Maximo de questoes por pagina.' AFTER `maxnotesperpages`;


ALTER TABLE `moodle28`.`mdl_icontent_sent_questions` 
ADD COLUMN `questionid` BIGINT(10) NOT NULL DEFAULT 0 AFTER `pagesquestionsid`, 
COMMENT = 'Defines icontent_question_attempt' , RENAME TO  `moodle28`.`mdl_icontent_question_attempt`;