-- Updates 2016070800
ALTER TABLE `moodle28`.`mdl_icontent_pages` 
DROP COLUMN `imagetransitiontype`,
DROP COLUMN `texttransitiontype`,
DROP COLUMN `prevtransitiontype`,
CHANGE COLUMN `nexttransitiontype` `transitioneffect` VARCHAR(255) NULL DEFAULT '0';

-- Updates 2016062900
ALTER TABLE `moodle28`.`mdl_icontent` DROP COLUMN `maxquestionsperpages`;

-- Updates 2016060700 - OK
ALTER TABLE `moodle28`.`mdl_icontent_pages` 
CHANGE COLUMN `maxquestionsperpages` `attemptsallowed` SMALLINT(3) NOT NULL DEFAULT '0' COMMENT 'Maximo de questoes por pagina.' ;

-- Updates 2016053100 - OK
ALTER TABLE `moodle28`.`mdl_icontent_question_attempts` 
CHANGE COLUMN `rightanswer` `rightanswer` VARCHAR(3072) NULL DEFAULT '0' COMMENT 'Resposta correta.' ;

-- Updates 2016052700 - OK
ALTER TABLE `db_moodletcc`.`mdl_icontent_question_attempt` 
ADD COLUMN `timecreated` BIGINT(10) NOT NULL DEFAULT '0' COMMENT 'Momento do envio da resposta.' AFTER `answertext`;
ALTER TABLE `db_moodletcc`.`mdl_icontent_question_attempt` RENAME TO  `db_moodletcc`.`mdl_icontent_question_attempts` ;

-- Updates 2016052500 - OK
ALTER TABLE `moodle28`.`mdl_icontent_question_attempt` 
ADD COLUMN `rightanswer` VARCHAR(255) NULL DEFAULT '0' COMMENT 'Resposta correta.' AFTER `fraction`,
ADD COLUMN `answertext` LONGTEXT NULL COMMENT 'Texto de resposta.' AFTER `rightanswer`;

-- Updates 2016051500 - OK
ALTER TABLE `db_moodletcc`.`mdl_icontent_pages_questions` 
DROP COLUMN `qtype`, DROP COLUMN `maxmark`;

-- Updates 2016051000 - OK
ALTER TABLE `moodle28`.`mdl_icontent_pages` 
ADD COLUMN `maxnotesperpages` SMALLINT(5) NOT NULL DEFAULT 0 COMMENT 'Maximo de anotacao por pagina.' AFTER `hidden`,
ADD COLUMN `maxquestionsperpages` SMALLINT(3) NOT NULL DEFAULT 0 COMMENT 'Maximo de questoes por pagina.' AFTER `maxnotesperpages`;


ALTER TABLE `moodle28`.`mdl_icontent` 
ADD COLUMN `maxnotesperpages` SMALLINT(5) NOT NULL DEFAULT 0 COMMENT 'Maximo de anotacao por pagina.' AFTER `copyright`,
ADD COLUMN `maxquestionsperpages` SMALLINT(3) NOT NULL DEFAULT 0 COMMENT 'Maximo de questoes por pagina.' AFTER `maxnotesperpages`;


ALTER TABLE `moodle28`.`mdl_icontent_sent_questions` 
ADD COLUMN `questionid` BIGINT(10) NOT NULL DEFAULT 0 AFTER `pagesquestionsid`, 
COMMENT = 'Defines icontent_question_attempt' , RENAME TO  `moodle28`.`mdl_icontent_question_attempt`;