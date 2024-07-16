<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Question utilities for iContent.
 *
 * 3/19/2020 Moved these functions from locallib.php to here.
 *
 * @package    mod_icontent
 * @copyright  AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_icontent\question;
defined('MOODLE_INTERNAL') || die(); // @codingStandardsIgnoreLine

/**
 * Utility class for iContent Questions.
 *
 * @package    mod_icontent
 * @copyright  AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icontent_question_options {
    
    /**
     * Get questions of question bank.
     *
     * Returns array of questions and is called from the addquestionpage.php file, about line 92.
     *
     * @param object $coursecontext
     * @param string $sort
     * @param int $page
     * @param int $perpage
     * @return array of $questionbank
     */
    public static function icontent_get_questions_of_questionbank(
        $coursecontext,
        $questioncategoryid,
        $sort,
        $page = 0,
        $perpage = ICONTENT_PER_PAGE) {
        global $DB;
        $coursecontext = $coursecontext;
        $sort = 'q.name '.$sort;
        $page = (int) $page;
        $perpage = (int) $perpage;
        $questioncategoryid = $questioncategoryid;

        // Setup pagination - when both $page and $perpage = 0, get all results.
        if ($page || $perpage) {
            if ($page < 0) {
                $page = 0;
            }
            if ($perpage > ICONTENT_MAX_PER_PAGE) {
                $perpage = ICONTENT_MAX_PER_PAGE;
            } else if ($perpage < 1) {
                $perpage = ICONTENT_PER_PAGE;
            }
        }

        // 20240107 Need to simplify this sql and drop unneeded items.
        $sql = "SELECT q.id AS Qid,
                       q.parent AS Qparent,
                       q.name AS Qname,
                       q.questiontext AS Qquestiontext,
                       q.questiontextformat AS Qquestiontextformat,
                       q.generalfeedback AS Qgeneralfeedback,
                       q.generalfeedbackformat AS Qgeneralfeedbackformat,
                       q.defaultmark AS Qdefaultmark,
                       q.penalty AS Qpenalty,
                       q.qtype AS Qqtype,
                       q.length AS Qlength,
                       q.stamp AS Qstamp,
                       q.timecreated AS Qtimecreated,
                       q.timemodified AS Qtimemodified,
                       q.createdby AS Qcreatedby,
                       q.modifiedby AS Qmodifiedby,

                       qc.id AS QCid,
                       qc.name AS QCname,
                       qc.contextid AS QCcontextid,
                       qc.info AS QCinfo,
                       qc.infoformat AS QCinfoformat,
                       qc.stamp AS QCstamp,
                       qc.parent AS QCparent,
                       qc.sortorder AS QCsortorder,
                       qc.idnumber AS QCidnumber,

                       qv.id AS QVid,
                       qv.questionbankentryid AS QVquestionbankentryid,
                       qv.version AS QVversion,
                       qv.questionid AS QVquestionid,
                       qv.status AS QVstatus,

                       qbe.id AS QBEid,
                       qbe.questioncategoryid AS QBEquestioncategoryid,
                       qbe.idnumber AS QBEidnumber,
                       qbe.ownerid AS QBEownerid

                  FROM {question} q
                  JOIN {question_categories} qc ON qc.parent = q.parent
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qc.contextid = $coursecontext
                   AND qc.parent = q.parent
                   AND q.qtype IN (?,?,?,?)
                   AND qv.status = 'ready'
                   AND qbe.questioncategoryid = $questioncategoryid
              ORDER BY {$sort}";

        $params = [
            $coursecontext,
            ICONTENT_QTYPE_ESSAY,
            ICONTENT_QTYPE_MATCH,
            ICONTENT_QTYPE_MULTICHOICE,
            ICONTENT_QTYPE_TRUEFALSE,
        ];
        return $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
    }

    /**
     * Remove answers the attempts summary the current page.
     *
     * Returns true os false
     *
     * @param int $pageid
     * @param int $cmid
     * @return true or false
     */
    public static function icontent_remove_answers_attempt_toquestion_by_page($pageid, $cmid) {
        global $DB, $USER;
        // Check capabilities.
        $allownewattempts = icontent_user_can_remove_attempts_answers_for_tryagain($pageid, $cmid);
        if (!$allownewattempts) {
            return false;
        }
        // SQL Query.
        $sql = "SELECT qa.id
                  FROM {icontent_question_attempts} qa
            INNER JOIN {icontent_pages_questions} pq
                    ON qa.pagesquestionsid = pq.id
                 WHERE pq.pageid = ?
                   AND pq.cmid = ?
                   AND qa.userid = ?;";
        // Get items.
        $idanswers = $DB->get_fieldset_sql($sql, [$pageid, $cmid, $USER->id]);
        list($in, $values) = $DB->get_in_or_equal($idanswers);
        // Delete records.
        return $DB->delete_records_select('icontent_question_attempts', 'id '. $in, $values);
    }
    
}
