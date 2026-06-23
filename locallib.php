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
 * Internal library of functions for module iContent.
 *
 * All the icontent specific functions, needed to implement the module logic, should go here.
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Constants.
 */
define('ICONTENT_PAGE_MIN_HEIGHT', 500);
define('ICONTENT_MAX_PER_PAGE', 1000);
define('ICONTENT_PER_PAGE', 20);
// Questions.
define('ICONTENT_QTYPE_MATCH', 'match');
define('ICONTENT_QTYPE_MULTICHOICE', 'multichoice');
define('ICONTENT_QTYPE_TRUEFALSE', 'truefalse');
define('ICONTENT_QTYPE_ESSAY', 'essay');
define('ICONTENT_QTYPE_ESSAYAUTOGRADE', 'essayautograde');
define('ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE', 'toevaluate');
define('ICONTENT_QTYPE_ESSAY_STATUS_VALUED', 'valued');
define('ICONTENT_QUESTION_FRACTION', 1);

require_once(dirname(__FILE__) . '/lib.php');

/**
 * Normalize a hex color to six uppercase hex chars without #.
 *
 * @param string|null $value
 * @param string $fallback
 * @return string
 */
function icontent_normalize_hex_colour($value, $fallback = 'FCFCFC') {
    $default = strtoupper(ltrim(trim((string)$fallback), '#'));
    if (!preg_match('/^[0-9A-F]{6}$/', $default)) {
        $default = 'FCFCFC';
    }

    $normalized = strtoupper(ltrim(trim((string)$value), '#'));
    if (!preg_match('/^[0-9A-F]{6}$/', $normalized)) {
        return $default;
    }

    return $normalized;
}

/**
 * Check whether an optional qtype table exists on this site.
 *
 * Some restore targets may not have all third-party question types installed.
 *
 * @param string $tablename
 * @return bool
 */
function icontent_optional_qtype_table_exists(string $tablename): bool {
    global $DB;

    static $cache = [];
    if (!array_key_exists($tablename, $cache)) {
        $cache[$tablename] = $DB->get_manager()->table_exists(new xmldb_table($tablename));
    }
    return (bool)$cache[$tablename];
}

/**
 * Get qtypes supported by the Phase 1 bridge.
 *
 * @return array
 */
function icontent_question_engine_phase1_supported_qtypes() {
    $allqtypes = array_values(array_keys(\core_component::get_plugin_list('qtype')));

    // Temporary denylist to phase in full engine coverage safely.
    $denylistraw = trim((string)get_config('mod_icontent', 'qenginedenylistqtypes'));
    if ($denylistraw === '') {
        return $allqtypes;
    }

    $denylist = preg_split('/\s*,\s*/', $denylistraw, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_diff($allqtypes, $denylist));
}

/**
 * Whether to allow legacy HTML rendering when question_engine rendering fails.
 *
 * @return bool
 */
function icontent_question_engine_allow_legacy_render_fallback(): bool {
    $setting = get_config('mod_icontent', 'qenginelegacyrenderfallback');
    if ($setting === false || $setting === null || $setting === '') {
        return true;
    }

    return (bool)$setting;
}

/**
 * Whether to allow legacy answer parsing when question_engine submit processing misses records.
 *
 * @return bool
 */
function icontent_question_engine_allow_legacy_submit_fallback(): bool {
    $setting = get_config('mod_icontent', 'qenginelegacysubmitfallback');
    if ($setting === false || $setting === null || $setting === '') {
        return true;
    }

    return (bool)$setting;
}

/**
 * Build session key for per-user, per-page QUBA caching.
 *
 * @param int $cmid
 * @param int $pageid
 * @param int $userid
 * @return string
 */
function icontent_question_engine_phase1_get_session_key($cmid, $pageid, $userid) {
    return 'cmid:' . (int)$cmid . ':page:' . (int)$pageid . ':user:' . (int)$userid;
}

/**
 * Reset cached question-engine usage for a specific user/page.
 *
 * @param int $cmid
 * @param int $pageid
 * @param int $userid
 * @return void
 */
function icontent_question_engine_phase1_reset_page_usage($cmid, $pageid, $userid): void {
    global $CFG, $SESSION;

    if (empty($SESSION->mod_icontent_quba) || !is_array($SESSION->mod_icontent_quba)) {
        return;
    }

    $sessionkey = icontent_question_engine_phase1_get_session_key($cmid, $pageid, $userid);
    $qubaid = (int)($SESSION->mod_icontent_quba[$sessionkey] ?? 0);
    unset($SESSION->mod_icontent_quba[$sessionkey]);

    if ($qubaid <= 0) {
        return;
    }

    require_once($CFG->libdir . '/questionlib.php');
    try {
        question_engine::delete_questions_usage_by_activity($qubaid);
    } catch (\Throwable $e) {
        // Best-effort cleanup only; session reset above is sufficient for flow recovery.
        // Intentionally ignored.
        unset($e);
    }
}

/**
 * Phase 1 bridge: create/load a QUBA for supported question types.
 *
 * @param object $objpage
 * @param array $questions
 * @return void
 */
function icontent_question_engine_phase1_bootstrap_usage($objpage, $questions): void {
    global $CFG, $SESSION, $USER;

    if (empty($objpage->cmid) || empty($objpage->id) || empty($USER->id) || empty($questions)) {
        return;
    }

    if (empty($SESSION->mod_icontent_quba) || !is_array($SESSION->mod_icontent_quba)) {
        $SESSION->mod_icontent_quba = [];
    }
    if (empty($SESSION->mod_icontent_qengine_issues) || !is_array($SESSION->mod_icontent_qengine_issues)) {
        $SESSION->mod_icontent_qengine_issues = [];
    }
    if (empty($SESSION->mod_icontent_qengine_questionmap) || !is_array($SESSION->mod_icontent_qengine_questionmap)) {
        $SESSION->mod_icontent_qengine_questionmap = [];
    }

    $sessionkey = icontent_question_engine_phase1_get_session_key($objpage->cmid, $objpage->id, $USER->id);

    $supportedqtypes = icontent_question_engine_phase1_supported_qtypes();
    $desiredquestionmap = [];
    foreach ($questions as $question) {
        if (empty($question->qid) || empty($question->qtype) || !in_array($question->qtype, $supportedqtypes)) {
            continue;
        }
        $sourcequestionid = (int)$question->qid;
        $desiredquestionmap[$sourcequestionid] = icontent_get_latest_question_version_id($sourcequestionid);
    }

    $existingqubaid = $SESSION->mod_icontent_quba[$sessionkey] ?? 0;
    $existingquestionmap = $SESSION->mod_icontent_qengine_questionmap[$sessionkey] ?? [];
    if (!empty($existingqubaid)) {
        require_once($CFG->libdir . '/questionlib.php');
        try {
            question_engine::load_questions_usage_by_activity($existingqubaid);
            if ($existingquestionmap === $desiredquestionmap && !empty($existingquestionmap)) {
                return;
            }
        } catch (\Throwable $e) {
            // Ignore stale/invalid usage and rebuild below.
            unset($e);
        }

        unset($SESSION->mod_icontent_quba[$sessionkey]);
    }

    $SESSION->mod_icontent_qengine_issues[$sessionkey] = [];
    $SESSION->mod_icontent_qengine_questionmap[$sessionkey] = [];

    require_once($CFG->libdir . '/questionlib.php');

    $context = context_module::instance((int)$objpage->cmid);
    $quba = question_engine::make_questions_usage_by_activity('mod_icontent', $context);
    $quba->set_preferred_behaviour('deferredfeedback');
    $slotsbyquestionid = [];

    foreach ($questions as $question) {
        if (empty($question->qid) || empty($question->qtype) || !in_array($question->qtype, $supportedqtypes)) {
            continue;
        }

        $sourcequestionid = (int)$question->qid;
        $activequestionid = $desiredquestionmap[$sourcequestionid] ?? $sourcequestionid;
        $SESSION->mod_icontent_qengine_questionmap[$sessionkey][$sourcequestionid] = $activequestionid;

        try {
            $questiondef = question_bank::load_question($activequestionid);
            if (!$questiondef) {
                continue;
            }
            if ((int)$questiondef->get_num_variants() <= 0) {
                $SESSION->mod_icontent_qengine_issues[$sessionkey][$sourcequestionid] =
                    icontent_question_engine_dataset_issue_message($activequestionid, $sourcequestionid);
                continue;
            }
            $maxmark = (float)($question->maxmark ?? $questiondef->defaultmark ?? 0);
            if ($maxmark <= 0) {
                $maxmark = 1.0;
            }
            $slot = $quba->add_question($questiondef, $maxmark);
            $slotsbyquestionid[$sourcequestionid] = $slot;
        } catch (\Throwable $e) {
            $SESSION->mod_icontent_qengine_issues[$sessionkey][$sourcequestionid] =
                icontent_question_engine_generic_issue_message($activequestionid, $e->getMessage(), $sourcequestionid);
            continue;
        }
    }

    if (!count($quba->get_slots())) {
        return;
    }

    $startedslots = 0;
    foreach ($slotsbyquestionid as $questionid => $slot) {
        try {
            $quba->start_question($slot);
            $startedslots++;
        } catch (\Throwable $e) {
            $SESSION->mod_icontent_qengine_issues[$sessionkey][$questionid] =
                icontent_question_engine_generic_issue_message($questionid, $e->getMessage());
        }
    }

    if ($startedslots === 0) {
        unset($SESSION->mod_icontent_quba[$sessionkey]);
        return;
    }

    question_engine::save_questions_usage_by_activity($quba);
    $SESSION->mod_icontent_quba[$sessionkey] = $quba->get_id();
}

/**
 * Build a friendly message for incomplete dataset-driven questions.
 *
 * @param int $questionid
 * @param int $sourcequestionid
 * @return string
 */
function icontent_question_engine_dataset_issue_message(int $questionid, int $sourcequestionid = 0): string {
    $message = 'This question cannot be shown in iContent yet because its dataset items have not been generated '
        . '(question ' . $questionid . '). Edit the question in the question bank, generate dataset items, and reload the page.';
    if ($sourcequestionid > 0 && $sourcequestionid !== $questionid) {
        $message .= ' (Mapped from page question id ' . $sourcequestionid . '.)';
    }
    return $message;
}

/**
 * Build a generic question-engine issue message.
 *
 * @param int $questionid
 * @param string $details
 * @param int $sourcequestionid
 * @return string
 */
function icontent_question_engine_generic_issue_message(int $questionid, string $details = '', int $sourcequestionid = 0): string {
    $message = 'This question could not be initialised in iContent (question ' . $questionid . ').';
    if ($sourcequestionid > 0 && $sourcequestionid !== $questionid) {
        $message .= ' (Mapped from page question id ' . $sourcequestionid . '.)';
    }
    if ($details !== '') {
        $message .= ' ' . $details;
    }
    return $message;
}

/**
 * Render a visible fallback for questions that could not be initialised.
 *
 * @param object $question
 * @param object $objpage
 * @param string $message
 * @return string
 */
function icontent_question_engine_render_issue($question, $objpage, string $message): string {
    $questiontools = icontent_make_question_tools($question, $objpage);
    $questiontext = html_writer::div(strip_tags((string)($question->questiontext ?? ''), '<b><strong>'), 'questiontext');
    $warning = html_writer::div(s($message), 'alert alert-warning');

    return html_writer::div(
        $questiontools . $questiontext . $warning,
        'question ' . s((string)($question->qtype ?? 'unknown')) . ' qengine-render-unavailable'
    );
}

/**
 * Phase 2 bridge: render a supported question using question_engine/QUBA.
 *
 * This intentionally does not process actions yet (Phase 3). It is a render-only
 * bridge with safe fallback to legacy HTML when anything is unavailable.
 *
 * @param object $objpage
 * @param object $question
 * @param int $displaynumber
 * @return string|false
 */
function icontent_question_engine_phase2_render_question($objpage, $question, $displaynumber = 1) {
    global $CFG, $SESSION, $USER;

    if (empty($question->qtype) || !in_array($question->qtype, icontent_question_engine_phase1_supported_qtypes())) {
        return false;
    }

    if (empty($objpage->cmid) || empty($objpage->id) || empty($USER->id)) {
        return false;
    }

    if (empty($SESSION->mod_icontent_quba) || !is_array($SESSION->mod_icontent_quba)) {
        return false;
    }

    $sessionkey = icontent_question_engine_phase1_get_session_key($objpage->cmid, $objpage->id, $USER->id);
    $mappedquestionid = $SESSION->mod_icontent_qengine_questionmap[$sessionkey][(int)$question->qid] ?? (int)$question->qid;
    $issue = $SESSION->mod_icontent_qengine_issues[$sessionkey][(int)$question->qid] ?? '';
    if ($issue !== '') {
        return icontent_question_engine_render_issue($question, $objpage, $issue);
    }
    $qubaid = $SESSION->mod_icontent_quba[$sessionkey] ?? 0;
    if (empty($qubaid)) {
        return false;
    }

    require_once($CFG->libdir . '/questionlib.php');

    try {
        $quba = question_engine::load_questions_usage_by_activity($qubaid);
    } catch (\Throwable $e) {
        return false;
    }

    $slot = null;
    foreach ($quba->get_slots() as $candidateslot) {
        try {
            $slotquestion = $quba->get_question($candidateslot);
            if (!empty($slotquestion->id) && (int)$slotquestion->id === $mappedquestionid) {
                $slot = $candidateslot;
                break;
            }
        } catch (\Throwable $e) {
            continue;
        }
    }

    if (empty($slot)) {
        return false;
    }

    try {
        $displayoptions = new question_display_options();
        $renderedhtml = $quba->render_question($slot, $displayoptions, (string)$displaynumber);
    } catch (\Throwable $e) {
        return icontent_question_engine_render_issue(
            $question,
            $objpage,
            icontent_question_engine_generic_issue_message((int)$question->qid, $e->getMessage())
        );
    }

    $renderedhtml = icontent_qengine_rewrite_questiontext_pluginfile_urls(
        (string)$renderedhtml,
        (int)$question->qid,
        (int)$objpage->cmid
    );

    if (in_array((string)$question->qtype, ['ddimageortext', 'ddmarker'])) {
        $renderedhtml = icontent_qengine_embed_dd_background_data_uri(
            (string)$renderedhtml,
            (int)$question->qid,
            (string)$question->qtype,
            (int)$objpage->cmid
        );
    }

    $questiontools = icontent_make_question_tools($question, $objpage);

    return html_writer::div(
        $questiontools . $renderedhtml,
        'question ' . s($question->qtype) . ' qengine-render'
    );
}

/**
 * Resolve a mapped question id to the latest ready/draft version id.
 *
 * @param int $questionid
 * @return int
 */
function icontent_get_latest_question_version_id(int $questionid): int {
        global $DB;

    if ($questionid <= 0) {
            return $questionid;
    }

        $sql = "SELECT latest.questionid
                            FROM {question_versions} qv
                            JOIN (
                                        SELECT questionbankentryid, MAX(version) AS maxversion
                                            FROM {question_versions}
                                         WHERE status IN ('ready', 'draft')
                                    GROUP BY questionbankentryid
                            ) latestversion
                                ON latestversion.questionbankentryid = qv.questionbankentryid
                            JOIN {question_versions} latest
                                ON latest.questionbankentryid = latestversion.questionbankentryid
                             AND latest.version = latestversion.maxversion
                             AND latest.status IN ('ready', 'draft')
                         WHERE qv.questionid = ?";
        $latestquestionid = $DB->get_field_sql($sql, [$questionid], IGNORE_MULTIPLE);

        return $latestquestionid ? (int)$latestquestionid : $questionid;
}

/**
 * Rewrite questiontext pluginfile URLs in rendered question HTML to mod_icontent proxy URLs.
 *
 * @param string $renderedhtml
 * @param int $questionid
 * @param int $cmid
 * @return string
 */
function icontent_qengine_rewrite_questiontext_pluginfile_urls(string $renderedhtml, int $questionid, int $cmid): string {
    global $CFG, $DB;

    $cmcontext = context_module::instance($cmid, IGNORE_MISSING);
    if (!$cmcontext) {
        return $renderedhtml;
    }

    $wwwroot = preg_quote($CFG->wwwroot, '/');
    $pattern = '/(' . $wwwroot . '\/pluginfile\.php\/\d+\/question\/questiontext\/[^"\s]+)(\?[^"\s]*)?/i';

    $rewritten = preg_replace_callback($pattern, static function (array $matches) use ($cmcontext, $questionid, $DB) {
        $fullurl = (string)$matches[1];
        $query = isset($matches[2]) ? (string)$matches[2] : '';

        $parts = parse_url($fullurl);
        if (empty($parts['path'])) {
            return $matches[0];
        }

        $path = ltrim((string)$parts['path'], '/');
        $needle = '/question/questiontext/';
        $pos = strpos('/' . $path, $needle);
        if ($pos === false) {
            return $matches[0];
        }

        $suffix = substr('/' . $path, $pos + strlen($needle));
        $segments = array_values(array_filter(explode('/', trim($suffix, '/')), static function ($segment) {
            return $segment !== '';
        }));
        if (empty($segments)) {
            return $matches[0];
        }

        $filename = (string)array_pop($segments);
        if ($filename === '') {
            return $matches[0];
        }

        $numericsegments = array_values(array_filter($segments, static function ($segment) {
            return ctype_digit((string)$segment);
        }));
        $candidateitemids = [];
        if (!empty($numericsegments)) {
            $candidateitemids = array_map('intval', $numericsegments);
        }
        if (!in_array((int)$questionid, $candidateitemids, true)) {
            $candidateitemids[] = (int)$questionid;
        }

        $itemid = 0;
        foreach ($candidateitemids as $candidateitemid) {
            if ($candidateitemid <= 0) {
                continue;
            }
            $exists = $DB->record_exists_select(
                'files',
                'component = ? AND filearea = ? AND itemid = ? AND filename = ? AND filesize > 0',
                ['question', 'questiontext', $candidateitemid, $filename]
            );
            if ($exists) {
                $itemid = (int)$candidateitemid;
                break;
            }
        }
        if ($itemid <= 0) {
            return $matches[0];
        }

        $itemidindex = array_search((string)$itemid, $segments, true);
        $filepathsegments = [];
        if ($itemidindex !== false) {
            $filepathsegments = array_slice($segments, $itemidindex + 1);
        }
        $filepath = '/';
        if (!empty($filepathsegments)) {
            $filepath = '/' . implode('/', $filepathsegments) . '/';
        }

        $proxyfilepath = '/' . $itemid . '/';
        if ($filepath !== '/') {
            $proxyfilepath .= trim($filepath, '/') . '/';
        }

        $proxyurl = moodle_url::make_pluginfile_url(
            (int)$cmcontext->id,
            'mod_icontent',
            'questiontextproxy',
            (int)$questionid,
            $proxyfilepath,
            $filename
        );

        return $proxyurl->out(false) . $query;
    }, $renderedhtml);

    return $rewritten ?? $renderedhtml;
}

/**
 * Replace drag-drop background image URL with a data URI fallback when available.
 *
 * @param string $renderedhtml
 * @param int $questionid
 * @param string $qtype
 * @param int $cmid
 * @return string
 */
function icontent_qengine_embed_dd_background_data_uri(string $renderedhtml, int $questionid, string $qtype, int $cmid): string {
    global $DB;

    $contextid = (int)$DB->get_field('question', 'contextid', ['id' => $questionid]);
    if (empty($contextid)) {
        return $renderedhtml;
    }

    $component = 'qtype_' . $qtype;
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, $component, 'bgimage', $questionid, 'id ASC', false);
    if (empty($files)) {
        return $renderedhtml;
    }

    $imagefile = reset($files);
    if (!$imagefile) {
        return $renderedhtml;
    }

    $cmcontext = context_module::instance($cmid, IGNORE_MISSING);
    if ($cmcontext && $imagefile->get_filename() !== '.') {
        $proxysrc = moodle_url::make_pluginfile_url(
            (int)$cmcontext->id,
            'mod_icontent',
            'qtypebgimage',
            (int)$questionid,
            '/' . trim((string)$qtype, '/') . '/',
            $imagefile->get_filename()
        )->out(false);

        $rewrittenhtml = preg_replace_callback(
            '/<img([^>]*class="[^"]*dropbackground[^"]*"[^>]*)>/i',
            static function (array $matches) use ($proxysrc) {
                $imgtag = $matches[0];
                if (preg_match('/\ssrc="[^"]*"/i', $imgtag)) {
                    return preg_replace('/\ssrc="[^"]*"/i', ' src="' . s($proxysrc) . '"', $imgtag, 1);
                }
                return str_replace('<img', '<img src="' . s($proxysrc) . '"', $imgtag);
            },
            $renderedhtml,
            1
        );

        if (!empty($rewrittenhtml)) {
            return $rewrittenhtml;
        }
    }

    $mimetype = (string)$imagefile->get_mimetype();
    if (strpos($mimetype, 'image/') !== 0) {
        return $renderedhtml;
    }

    $content = $imagefile->get_content();
    if ($content === false || $content === '') {
        return $renderedhtml;
    }

    $datasrc = 'data:' . $mimetype . ';base64,' . base64_encode($content);

    return preg_replace_callback(
        '/<img([^>]*class="[^"]*dropbackground[^"]*"[^>]*)>/i',
        static function (array $matches) use ($datasrc) {
            $imgtag = $matches[0];
            if (preg_match('/\ssrc="[^"]*"/i', $imgtag)) {
                return preg_replace('/\ssrc="[^"]*"/i', ' src="' . s($datasrc) . '"', $imgtag, 1);
            }
            return str_replace('<img', '<img src="' . s($datasrc) . '"', $imgtag);
        },
        $renderedhtml,
        1
    ) ?? $renderedhtml;
}

/**
 * Decide whether the side-column TOC menu should be displayed.
 *
 * @param stdClass $icontent
 * @param stdClass $cm
 * @param bool $edit
 * @return bool
 */
function icontent_should_show_toc_menu(stdClass $icontent, stdClass $cm, $edit = false) {
    $showtocmenu = (int)($icontent->showtocmenu ?? 1);
    if ($showtocmenu === 1) {
        return true;
    }

    $context = context_module::instance($cm->id);
    if ($edit || has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
        return true;
    }

    return false;
}

/**
 * Add the icontent TOC sticky block to the default region.
 *
 * @param array $pages
 * @param object $page
 * @param object $icontent
 * @param object $cm
 * @param bool $edit
 */
function icontent_add_fake_block($pages, $page, $icontent, $cm, $edit) {
    global $OUTPUT, $PAGE;
    if (!icontent_should_show_toc_menu($icontent, $cm, $edit)) {
        return;
    }

    $toc = icontent_get_toc($pages, $page, $icontent, $cm, $edit, 0);
    $bc = new block_contents();
    $bc->title = get_string('icontentmenu', 'icontent');
    $bc->attributes['class'] = 'block block_icontent_toc';
    $bc->content = $toc;
    $defaultregion = $PAGE->blocks->get_default_region();
    $PAGE->blocks->add_fake_block($bc, $defaultregion);
}

/**
 * Determine whether the page belongs to a branch grouping.
 *
 * @param stdClass $page
 * @return bool
 */
function icontent_page_is_clustered(stdClass $page) {
    return !empty($page->branchparentpageid);
}

/**
 * Return the internal grouping key for a branch page.
 *
 * @param stdClass $page
 * @return string
 */
function icontent_get_cluster_group_key(stdClass $page) {
    if (!icontent_page_is_clustered($page)) {
        return '';
    }

    $branchref = trim((string)($page->branchref ?? ''));
    if ($branchref !== '') {
        return $branchref;
    }

    return 'clusterpage-' . (int)$page->id;
}

/**
 * Build branch-style TOC metadata grouped by parent page.
 *
 * @param array $pages
 * @return array
 */
function icontent_get_toc_clusters(array $pages) {
    $pagesbyid = [];
    foreach ($pages as $page) {
        $pagesbyid[(int)$page->id] = $page;
    }

    $clusters = [];
    $clusterorder = [];
    foreach ($pages as $page) {
        if (!icontent_page_is_clustered($page)) {
            continue;
        }

        $parentid = (int)$page->branchparentpageid;
        if (empty($pagesbyid[$parentid])) {
            continue;
        }

        $clusterkey = icontent_get_cluster_group_key($page);
        if (!isset($clusterorder[$parentid])) {
            $clusterorder[$parentid] = 0;
        }
        if (!isset($clusters[$parentid][$clusterkey])) {
            $clusterorder[$parentid]++;
            $label = trim((string)($page->branchname ?? ''));
            if ($label === '') {
                $label = get_string('clusterdefault', 'mod_icontent', $clusterorder[$parentid]);
            }

            $clusters[$parentid][$clusterkey] = [
                'label' => $label,
                'pages' => [],
            ];
        }

        $clusters[$parentid][$clusterkey]['pages'][] = $page;
    }

    return $clusters;
}

/**
 * Render the common TOC page link.
 *
 * @param stdClass $page
 * @param context_module $context
 * @param int $totalpages
 * @return string
 */
function icontent_render_toc_page_link(stdClass $page, context_module $context, $totalpages) {
    $title = trim(format_string($page->title, true, ['context' => $context]));

    return html_writer::link(
        new moodle_url('/mod/icontent/view.php', ['id' => $page->cmid, 'pageid' => $page->id]),
        $title,
        [
            'title' => s($title),
            'class' => 'load-page page' . $page->pagenum,
            'data-pageid' => $page->id,
            'data-pagenum' => $page->pagenum,
            'data-cmid' => $page->cmid,
            'data-sesskey' => sesskey(),
            'data-totalpages' => $totalpages,
        ]
    );
}

/**
 * Render the teacher action list shown beside a TOC page entry.
 *
 * @param stdClass $page
 * @param stdClass $cm
 * @param int $position
 * @param int $pagecount
 * @return string
 */
function icontent_render_toc_action_list(stdClass $page, stdClass $cm, $position, $pagecount) {
    global $OUTPUT, $USER;

    $actions = html_writer::start_tag('div', ['class' => 'action-list']);
    if ($position != 1) {
        $actions .= html_writer::link(
            new moodle_url(
                'move.php',
                [
                    'id' => $cm->id,
                    'pageid' => $page->id,
                    'up' => '1',
                    'sesskey' => $USER->sesskey,
                ]
            ),
            $OUTPUT->pix_icon('t/up', get_string('up')),
            ['title' => get_string('up')]
        );
    }
    if ($position != $pagecount) {
        $actions .= html_writer::link(
            new moodle_url(
                'move.php',
                [
                    'id' => $cm->id,
                    'pageid' => $page->id,
                    'up' => '0',
                    'sesskey' => $USER->sesskey,
                ]
            ),
            $OUTPUT->pix_icon('t/down', get_string('down')),
            ['title' => get_string('down')]
        );
    }
    $actions .= html_writer::link(
        new moodle_url(
            'edit.php',
            [
                'cmid' => $page->cmid,
                'id' => $page->id,
                'sesskey' => $USER->sesskey,
            ]
        ),
        $OUTPUT->pix_icon('t/edit', get_string('edit')),
        ['title' => get_string('edit')]
    );
    $actions .= html_writer::link(
        new moodle_url(
            'duplicate.php',
            [
                'id' => $page->cmid,
                'pageid' => $page->id,
                'sesskey' => $USER->sesskey,
            ]
        ),
        $OUTPUT->pix_icon('t/copy', get_string('duplicatepage', 'mod_icontent')),
        ['title' => get_string('duplicatepage', 'mod_icontent')]
    );
    $actions .= html_writer::link(
        new moodle_url(
            'delete.php',
            [
                'id' => $page->cmid,
                'pageid' => $page->id,
                'sesskey' => $USER->sesskey,
            ]
        ),
        $OUTPUT->pix_icon('t/delete', get_string('delete')),
        ['title' => get_string('delete')]
    );
    if ($page->hidden) {
        $actions .= html_writer::link(
            new moodle_url(
                'show.php',
                [
                    'id' => $page->cmid,
                    'pageid' => $page->id,
                    'sesskey' => $USER->sesskey,
                ]
            ),
            $OUTPUT->pix_icon('t/show', get_string('show')),
            ['title' => get_string('show')]
        );
    } else {
        $actions .= html_writer::link(
            new moodle_url(
                'show.php',
                [
                    'id' => $page->cmid,
                    'pageid' => $page->id,
                    'sesskey' => $USER->sesskey,
                ]
            ),
            $OUTPUT->pix_icon('t/hide', get_string('hide')),
            ['title' => get_string('hide')]
        );
    }
    $actions .= html_writer::link(
        new moodle_url(
            'edit.php',
            [
                'cmid' => $page->cmid,
                'pagenum' => $page->pagenum,
                'sesskey' => $USER->sesskey,
            ]
        ),
        $OUTPUT->pix_icon('add', get_string('addafter', 'mod_icontent'), 'mod_icontent'),
        ['title' => get_string('addafter', 'mod_icontent')]
    );
    $actions .= html_writer::end_tag('div');

    return $actions;
}

/**
 * Render one TOC list item.
 *
 * @param stdClass $page
 * @param context_module $context
 * @param stdClass $cm
 * @param int $totalpages
 * @param bool $edit
 * @param int $position
 * @param int $pagecount
 * @param array $classes
 * @return string
 */
function icontent_render_toc_item(
    stdClass $page,
    context_module $context,
    stdClass $cm,
    $totalpages,
    $edit,
    $position,
    $pagecount,
    array $classes = []
) {
    $classes[] = 'clearfix';
    $html = html_writer::start_tag('li', ['class' => implode(' ', array_unique($classes))]);
    $html .= icontent_render_toc_page_link($page, $context, $totalpages);
    if ($edit) {
        $html .= icontent_render_toc_action_list($page, $cm, $position, $pagecount);
    }
    $html .= html_writer::end_tag('li');

    return $html;
}

/**
 * Generate toc structure.
 *
 * @param array $pages
 * @param object $page
 * @param object $icontent
 * @param object $cm
 * @param bool $edit
 * @return string
 */
function icontent_get_toc($pages, $page, $icontent, $cm, $edit) {
    $context = context_module::instance($cm->id);
    $tpages = count($pages);
    $pagesbyid = [];
    foreach ($pages as $tocpage) {
        $pagesbyid[(int)$tocpage->id] = $tocpage;
    }
    $clusters = icontent_get_toc_clusters($pages);
    $toc = '';
    $toc .= html_writer::start_tag('div', ['class' => 'icontent_toc clearfix']);
    $toc .= html_writer::start_tag('ul');
    $position = 0;
    foreach ($pages as $pg) {
        $parentid = (int)($pg->branchparentpageid ?? 0);
        if ($parentid > 0 && isset($pagesbyid[$parentid])) {
            continue;
        }
        if (!$edit && !empty($pg->hidden)) {
            continue;
        }

        $position++;
        $toc .= icontent_render_toc_item($pg, $context, $cm, $tpages, $edit, $position, $tpages);

        if (empty($clusters[(int)$pg->id])) {
            continue;
        }

        $renderedclusters = '';
        foreach ($clusters[(int)$pg->id] as $cluster) {
            $clusterpageshtml = '';
            foreach ($cluster['pages'] as $clusterpage) {
                if (!$edit && !empty($clusterpage->hidden)) {
                    continue;
                }

                $position++;
                $clusterpageshtml .= icontent_render_toc_item(
                    $clusterpage,
                    $context,
                    $cm,
                    $tpages,
                    $edit,
                    $position,
                    $tpages,
                    ['cluster-page']
                );
            }

            if ($clusterpageshtml === '') {
                continue;
            }

            $renderedclusters .= html_writer::start_tag('li', ['class' => 'cluster-group']);
            $renderedclusters .= html_writer::tag('span', s($cluster['label']), ['class' => 'cluster-label']);
            $renderedclusters .= html_writer::tag('ul', $clusterpageshtml, ['class' => 'cluster-pages']);
            $renderedclusters .= html_writer::end_tag('li');
        }

        if ($renderedclusters !== '') {
            $toc .= html_writer::tag('ul', $renderedclusters, ['class' => 'cluster-list']);
        }
    }
    $toc .= html_writer::end_tag('ul');
    $toc .= html_writer::end_tag('div');
    return $toc;
}

/**
 * Add dynamic attributes in page loading screen.
 * @param object $pagestyle
 * @return void
 */
function icontent_add_properties_css($pagestyle) {
    $bgcolor = icontent_normalize_hex_colour($pagestyle->bgcolor, 'FCFCFC');
    $bordercolor = icontent_normalize_hex_colour($pagestyle->bordercolor, 'E4E4E4');

    $style = "background-color: #{$bgcolor}; ";
    $style .= "min-height: " . ICONTENT_PAGE_MIN_HEIGHT . "px; ";
    $style .= "border: {$pagestyle->borderwidth}px solid #{$bordercolor};";
    if ($pagestyle->bgimage) {
        $style .= "background-image: url('{$pagestyle->bgimage}')";
    }
    return $style;
}

/**
 * Add script that load tooltip twitter bootstrap.
 *
 * @return void
 */
function icontent_add_script_load_tooltip() {
    $js = "
(function() {
    function getGroupFromNode(node) {
        if (!node || !node.className) {
            return null;
        }
        var match = String(node.className).match(/group(\\d+)/);
        return match ? match[1] : null;
    }

    function normalizeDdwtosQuestion(questionNode) {
        if (!questionNode || questionNode.offsetParent === null) {
            return;
        }

        var dragDropItems = questionNode.querySelectorAll(
            'span.draghome[class*=group], span.drop[class*=group], ' +
            'input.placeinput[class*=group]'
        );
        if (!dragDropItems.length) {
            return;
        }

        dragDropItems.forEach(function(itemNode) {
            if (itemNode.style) {
                itemNode.style.width = '';
                itemNode.style.height = '';
                itemNode.style.lineHeight = '';
            }
        });

        var groups = {};
        dragDropItems.forEach(function(itemNode) {
            var group = getGroupFromNode(itemNode);
            if (group) {
                groups[group] = true;
            }
        });

        Object.keys(groups).forEach(function(group) {
            var items = questionNode.querySelectorAll('span.group' + group + ', input.group' + group);
            if (!items.length) {
                return;
            }

            var maxWidth = 0;
            var maxHeight = 0;
            items.forEach(function(itemNode) {
                maxWidth = Math.max(maxWidth, Math.ceil(itemNode.offsetWidth || 0));
                maxHeight = Math.max(maxHeight, Math.ceil(itemNode.offsetHeight || 0));
            });

            if (maxWidth <= 0 || maxHeight <= 0) {
                return;
            }

            maxWidth += 8;
            maxHeight += 2;
            items.forEach(function(itemNode) {
                if (!itemNode.style) {
                    return;
                }
                itemNode.style.width = maxWidth + 'px';
                itemNode.style.height = maxHeight + 'px';
                itemNode.style.lineHeight = maxHeight + 'px';
            });
        });
    }

    function normalizeAllDdwtos() {
        var questionNodes = document.querySelectorAll('.fulltextpage .que.ddwtos');
        questionNodes.forEach(function(questionNode) {
            normalizeDdwtosQuestion(questionNode);
        });
    }

    function bindDdwtosImageLoads() {
        var imageNodes = document.querySelectorAll('.fulltextpage .que.ddwtos img');
        imageNodes.forEach(function(imageNode) {
            if (imageNode.getAttribute('data-icontent-ddwtos-bound') === '1') {
                return;
            }
            imageNode.setAttribute('data-icontent-ddwtos-bound', '1');
            imageNode.addEventListener('load', function() {
                normalizeAllDdwtos();
            });
        });
    }

    function isTinyToolbarReady(textareaNode) {
        if (!textareaNode || !textareaNode.id) {
            return true;
        }

        var iframeNode = document.getElementById(textareaNode.id + '_ifr');
        if (!iframeNode) {
            return false;
        }

        var editorNode = iframeNode.closest('.tox.tox-tinymce');
        if (!editorNode) {
            return false;
        }

        var toolbarButtons = editorNode.querySelectorAll('.tox-toolbar button, .tox-toolbar__group button');
        return toolbarButtons.length > 0;
    }

    function ensureTinyEssayEditorsReady() {
        if (typeof require !== 'function') {
            return;
        }

        var textareaNodes = document.querySelectorAll(
            '.fulltextpage textarea.qtype_essay_response, ' +
            '.fulltextpage .qtype_essay_response textarea'
        );
        if (!textareaNodes.length) {
            return;
        }

        require(['editor_tiny/editor'], function(tinyEditor) {
            if (!tinyEditor || typeof tinyEditor.setupForElementId !== 'function') {
                return;
            }

            var attempts = 0;
            var maxattempts = 12;

            var tryInit = function() {
                var pending = 0;

                textareaNodes.forEach(function(textareaNode) {
                    if (!textareaNode || !textareaNode.id) {
                        return;
                    }

                    if (isTinyToolbarReady(textareaNode)) {
                        return;
                    }

                    pending++;
                    try {
                        tinyEditor.setupForElementId({
                            elementId: textareaNode.id,
                            options: {}
                        });
                    } catch (error) {
                        // Ignore and retry while the question HTML finishes rendering.
                    }
                });

                if (pending > 0 && attempts < maxattempts) {
                    attempts++;
                    setTimeout(tryInit, 200);
                }
            };

            tryInit();
        });
    }

    function scheduleNormalizePasses() {
        normalizeAllDdwtos();
        bindDdwtosImageLoads();
        ensureTinyEssayEditorsReady();
        setTimeout(normalizeAllDdwtos, 120);
        setTimeout(normalizeAllDdwtos, 400);
        setTimeout(ensureTinyEssayEditorsReady, 120);
        setTimeout(ensureTinyEssayEditorsReady, 400);
    }

    if (!window.__icontentDdwtosResizeHooked) {
        window.__icontentDdwtosResizeHooked = true;

        document.addEventListener('click', function(event) {
            var target = event.target;
            if (!target || !target.closest) {
                return;
            }

            if (target.closest('.load-page') ||
                target.closest('.btn-previous-page') ||
                target.closest('.btn-next-page') ||
                target.closest('#idtitlequestionsarea')) {
                setTimeout(scheduleNormalizePasses, 650);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleNormalizePasses);
    } else {
        scheduleNormalizePasses();
    }
})();
";

    return html_writer::script($js);
}

/**
 * Get page style.
 *
 * This method checks if the current page have enough attributes to create your style. Otherwise returns Generic style plugin.
 *
 * @param object $icontent
 * @param object $page
 * @param object $context
 * @return pagestyle;
 */
function icontent_get_page_style($icontent, $page, $context) {
    $pagestyle = new stdClass();
    $pagestyle->bgcolor = $page->bgcolor ? $page->bgcolor : $icontent->bgcolor;
    $pagestyle->borderwidth = $page->borderwidth ? $page->borderwidth : $icontent->borderwidth;
    $pagestyle->bordercolor = $page->bordercolor ? $page->bordercolor : $icontent->bordercolor;
    $pagestyle->bgimage = false;
    if ($page->showbgimage) {
        $pagestyle->bgimage = icontent_get_page_bgimage($context, $page) ?
            icontent_get_page_bgimage($context, $page) : icontent_get_bgimage($context);
    }
    return icontent_add_properties_css($pagestyle);
}

/**
 * Add border options.
 *
 * @return array $arr
 */
function icontent_add_borderwidth_options() {
    $arr = [];
    // 20240216 Changed from 50 to 51.
    for ($i = 0; $i < 51; $i++) {
        $arr[$i] = $i . 'px';
    }
    return $arr;
}

/**
 * Get background image of interactive content plugin <iContent>.
 *
 * @param object $context
 * @return string $fullpath
 */
function icontent_get_bgimage($context) {
    $fs = get_file_storage();
    // This is not very efficient!!
    $files = $fs->get_area_files(
        $context->id,
        'mod_icontent',
        'icontent',
        0,
        'sortorder DESC,
        id ASC',
        false
    );
    if (count($files) >= 1) {
        $file = reset($files);
        unset($files);
        $fullurl = moodle_url::make_pluginfile_url(
            (int)$context->id,
            'mod_icontent',
            'icontent',
            0,
            (string)$file->get_filepath(),
            (string)$file->get_filename()
        )->out(false);
        $mimetype = $file->get_mimetype();
        if (file_mimetype_in_typegroup($mimetype, 'web_image')) { // It's an image.
            return $fullurl;
        } else {
            return false;
        }
    }
    return false;
}

/**
 * Get background image of pages of interactive content plugin <iContent>.
 *
 * @param object $context
 * @param object $page
 * @return string $fullpath
 */
function icontent_get_page_bgimage($context, $page) {
    $fs = get_file_storage();
    // This is not very efficient!
    $files = $fs->get_area_files(
        $context->id,
        'mod_icontent',
        'bgpage',
        $page->id,
        'sortorder DESC,
        id ASC',
        false
    );
    if (count($files) >= 1) {
        $file = reset($files);
        unset($files);
        $fullurl = moodle_url::make_pluginfile_url(
            (int)$context->id,
            'mod_icontent',
            'bgpage',
            (int)$page->id,
            (string)$file->get_filepath(),
            (string)$file->get_filename()
        )->out(false);
        $mimetype = $file->get_mimetype();
        if (file_mimetype_in_typegroup($mimetype, 'web_image')) { // It's an image.
            return $fullurl;
        } else {
            return false;
        }
    }
    return false;
}

/**
 * Duplicate one iContent page and insert it directly after the source page.
 *
 * Branch settings are preserved. Navigation overrides are reset on the new page
 * to Previous=Auto and Next=Hide.
 *
 * @param int $icontentid
 * @param int $cmid
 * @param int $sourcepageid
 * @param context_module $context
 * @return stdClass
 */
function icontent_duplicate_page($icontentid, $cmid, $sourcepageid, context_module $context) {
    global $DB;

    $sourcepage = $DB->get_record(
        'icontent_pages',
        [
            'id' => $sourcepageid,
            'icontentid' => (int)$icontentid,
            'cmid' => (int)$cmid,
        ],
        '*',
        MUST_EXIST
    );

    $transaction = $DB->start_delegated_transaction();

    $nextpages = $DB->get_records_select(
        'icontent_pages',
        'icontentid = ? AND cmid = ? AND pagenum > ?',
        [(int)$icontentid, (int)$cmid, (int)$sourcepage->pagenum],
        'pagenum DESC',
        'id, pagenum'
    );
    foreach ($nextpages as $nextpage) {
        $nextpage->pagenum = (int)$nextpage->pagenum + 1;
        $DB->update_record('icontent_pages', $nextpage);
    }

    $newpage = clone $sourcepage;
    unset($newpage->id);

    $newpage->title = get_string('duplicatepagesuffix', 'mod_icontent', $sourcepage->title);
    $newpage->pagenum = (int)$sourcepage->pagenum + 1;
    $newpage->prevmode = 0;
    $newpage->prevpageid = 0;
    $newpage->nextmode = 1;
    $newpage->nextpageid = 0;
    $newpage->timecreated = time();
    $newpage->timemodified = time();

    $newpageid = (int)$DB->insert_record('icontent_pages', $newpage);

    $fs = get_file_storage();
    $copyfilearea = static function ($filearea) use ($fs, $context, $sourcepage, $newpageid): void {
        $files = $fs->get_area_files(
            (int)$context->id,
            'mod_icontent',
            $filearea,
            (int)$sourcepage->id,
            'itemid, filepath, filename',
            false
        );
        foreach ($files as $file) {
            $filerecord = [
                'contextid' => (int)$context->id,
                'component' => 'mod_icontent',
                'filearea' => $filearea,
                'itemid' => $newpageid,
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $fs->create_file_from_storedfile($filerecord, $file);
        }
    };

    $copyfilearea('page');
    $copyfilearea('bgpage');

    $createdpage = $DB->get_record('icontent_pages', ['id' => $newpageid], '*', MUST_EXIST);
    $transaction->allow_commit();

    return $createdpage;
}

/**
 * Delete question per page by id.
 *
 * Returns true or false
 *
 * @param int $id
 * @return boolean $result
 */
function icontent_remove_questionpagebyid($id) {
    global $DB;
    return $DB->delete_records('icontent_pages_questions', ['id' => $id]);
}

/**
 * Update question attempt.
 *
 * Returns true or false.
 *
 * @param object $attempt
 * @return boolean true or false
 */
function icontent_update_question_attempts($attempt) {
    global $DB;
    return $DB->update_record('icontent_question_attempts', $attempt);
}

/**
 * Loads full paging button bar.
 *
 * Returns buttons related pages.
 *
 * @param object $pages
 * @param object $cmid
 * @param int $startwithpage
 * @return string with $pgbuttons
 */
function icontent_full_paging_button_bar($pages, $cmid, $startwithpage = 1) {
    if (empty($pages)) {
        return false;
    }
    // Object button.
    $objbutton = new stdClass();
    $objbutton->name = get_string('previous', 'mod_icontent');
    $objbutton->title = get_string('previouspage', 'mod_icontent');
    $objbutton->cmid = $cmid;
    $objbutton->startwithpage = $startwithpage;
    // Create buttons!
    $npage = 0;
    $tpages = count($pages);
    $pgbuttons = html_writer::start_div('full-paging-buttonbar icontent-buttonbar', ['id' => 'idicontentbuttonbar']);
    $pgbuttons .= icontent_make_button_previous_page($objbutton, $tpages);
    foreach ($pages as $page) {
        if (!$page->hidden) {
            $npage++;
            $pgbuttons .= html_writer::link(
                new moodle_url('/mod/icontent/view.php', ['id' => $page->cmid, 'pageid' => $page->id]),
                $npage,
                [
                    'title' => s($page->title),
                    'class' => 'load-page mr-1 btn-icontent-page btn btn-secondary page' . $page->pagenum,
                    'data-toggle' => 'tooltip',
                    'data-totalpages' => $tpages,
                    'data-placement' => 'top',
                    'data-pageid' => $page->id,
                    'data-pagenum' => $page->pagenum,
                    'data-cmid' => $page->cmid,
                    'data-sesskey' => sesskey(),
                ]
            );
        }
    }
    $objbutton->name = get_string('next', 'mod_icontent');
    $objbutton->title = get_string('nextpage', 'mod_icontent');
    $pgbuttons .= icontent_make_button_next_page($objbutton, $tpages);
    $pgbuttons .= html_writer::end_div();
    return $pgbuttons;
}

/**
 * Loads simple paging button bar.
 *
 * Returns buttons previous and next.
 *
 * @param object $pages
 * @param int $cmid
 * @param int $startwithpage
 * @param string $attrid
 * @return string with $controlbuttons
 */
function icontent_simple_paging_button_bar($pages, $cmid, $startwithpage = 1, $attrid = 'fgroup_id_buttonar') {
    // Object button.
    $objbutton = new stdClass();
    $objbutton->name  = get_string('previous', 'mod_icontent');
    $objbutton->title = get_string('previouspage', 'mod_icontent');
    $objbutton->cmid  = $cmid;
    $objbutton->startwithpage = $startwithpage;
    // Go back.
    $controlbuttons = icontent_make_button_previous_page(
        $objbutton,
        count($pages),
        html_writer::tag(
            'i',
            null,
            [
                'class' => 'fa fa-chevron-circle-left mr-2',
            ]
        )
    );
    $objbutton->name = get_string('advance', 'mod_icontent');
    $objbutton->title = get_string('nextpage', 'mod_icontent');
    // Advance.
    $controlbuttons .= icontent_make_button_next_page(
        $objbutton,
        count($pages),
        html_writer::tag(
            'i',
            null,
            [
                'class' => 'fa fa-chevron-circle-right ml-2',
            ]
        )
    );
    return html_writer::div($controlbuttons, "simple-paging-buttonbar icontent-buttonbar mt-2", ['id' => $attrid]);
}

/**
 * Get the number of the user home page logged in.
 *
 * Returns array of pages.
 * Please note the icontent/text of pages is not included.
 *
 * @param object $icontent
 * @param object $context
 * @return array of id=>icontent
 */
function icontent_get_startpagenum($icontent, $context) {
    global $DB;
    if (has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
        return icontent_get_minpagenum($icontent);
    }
    // Discover page to be presented to the student.
    global $USER;
    $cm = get_coursemodule_from_instance('icontent', $icontent->id);
    $pagedisplay = $DB->get_record_sql(
        "SELECT MAX(timecreated) AS maxtimecreated
           FROM {icontent_pages_displayed}
          WHERE cmid IN(?)
            AND userid IN(?);",
        [
            $cm->id,
            $USER->id,
        ]
    );
    $totalpagesvieweduser = $DB->count_records('icontent_pages_displayed', ['cmid' => $cm->id, 'userid' => $USER->id]);
    $totalpagesavailable = $DB->count_records('icontent_pages', ['cmid' => $cm->id, 'hidden' => 0]);
    if (!$pagedisplay->maxtimecreated || $totalpagesvieweduser === $totalpagesavailable) {
        return icontent_get_minpagenum($icontent);
    }
    $lastpagedisplay = $DB->get_record(
        "icontent_pages_displayed",
        [
            'cmid' => $cm->id,
            'userid' => $USER->id,
            'timecreated' => $pagedisplay->maxtimecreated,
        ],
        'id,
        pageid'
    );
    $page = $DB->get_record("icontent_pages", ['id' => $lastpagedisplay->pageid], "id, pagenum");
    return $page->pagenum;
}

/**
 * Loads first page content.
 *
 * Returns array of pages.
 * Please note the icontent/text of pages is not included.
 *
 * @param object $icontent
 * @return array of id=>icontent
 */
function icontent_get_minpagenum($icontent) {
    global $DB;
    // Get object.
    $sql = "SELECT Min(pagenum) AS minpagenum FROM {icontent_pages} WHERE icontentid = ? AND hidden = ?;";
     $objpage = $DB->get_record_sql($sql, [$icontent->id, 0]);
     // Return min page.
    return $objpage->minpagenum;
}

/**
 * Get page previous.
 *
 * Return the most appropriate previous page number for the learner.
 *
 * @param stdClass $objpage
 * @return int
 */
function icontent_get_navigation_source_pageid(stdClass $objpage) {
    global $DB;

    global $USER;
    if (!isset($objpage->id, $objpage->cmid) || !$objpage->id || !$objpage->cmid) {
        return 0;
    }

    $record = $DB->get_record_sql(
        "SELECT frompageid
           FROM {icontent_pages_nav}
          WHERE cmid = ?
            AND userid = ?
            AND topageid = ?
       ORDER BY timecreated DESC, id DESC",
        [(int)$objpage->cmid, (int)$USER->id, (int)$objpage->id],
        IGNORE_MULTIPLE
    );

    return (int)($record->frompageid ?? 0);
}

/**
 * Get a visible page by id within one activity.
 *
 * @param int $pageid
 * @param int $cmid
 * @return stdClass|false
 */
function icontent_get_visible_page_by_id($pageid, $cmid) {
    global $DB;

    if (empty($pageid) || empty($cmid)) {
        return false;
    }

    return $DB->get_record(
        'icontent_pages',
        ['id' => $pageid, 'cmid' => $cmid, 'hidden' => 0],
        'id, cmid, pagenum, branchref, branchname, branchparentpageid'
    );
}

/**
 * Return the previous visible mainline page number.
 *
 * @param int $cmid
 * @param int $pagenum
 * @return int
 */
function icontent_get_previous_mainline_pagenum($cmid, $pagenum) {
    global $DB;

    $page = $DB->get_record_sql(
        "SELECT max(pagenum) AS previous
           FROM {icontent_pages}
          WHERE cmid = ?
            AND hidden = ?
            AND branchparentpageid = ?
            AND pagenum < ?;",
        [(int)$cmid, 0, 0, (int)$pagenum]
    );

    return (int)($page->previous ?? 0);
}

/**
 * Return the next visible mainline page number.
 *
 * @param int $cmid
 * @param int $pagenum
 * @return int
 */
function icontent_get_next_mainline_pagenum($cmid, $pagenum) {
    global $DB;

    $page = $DB->get_record_sql(
        "SELECT min(pagenum) AS next
           FROM {icontent_pages}
          WHERE cmid = ?
            AND hidden = ?
            AND branchparentpageid = ?
            AND pagenum > ?;",
        [(int)$cmid, 0, 0, (int)$pagenum]
    );

    return (int)($page->next ?? 0);
}

/**
 * Return visible pages in the same branch grouping.
 *
 * @param stdClass $objpage
 * @return array
 */
function icontent_get_cluster_pages(stdClass $objpage) {
    global $DB;

    if (!icontent_page_is_clustered($objpage)) {
        return [];
    }

    $clusterkey = icontent_get_cluster_group_key($objpage);
    if (strpos($clusterkey, 'clusterpage-') === 0) {
        return $DB->get_records(
            'icontent_pages',
            ['id' => (int)$objpage->id, 'cmid' => (int)$objpage->cmid, 'hidden' => 0],
            'pagenum',
            'id, cmid, pagenum, branchref, branchname, branchparentpageid'
        );
    }

    return $DB->get_records(
        'icontent_pages',
        [
            'cmid' => (int)$objpage->cmid,
            'hidden' => 0,
            'branchparentpageid' => (int)$objpage->branchparentpageid,
            'branchref' => $clusterkey,
        ],
        'pagenum',
        'id, cmid, pagenum, branchref, branchname, branchparentpageid'
    );
}

/**
 * Return the previous pagenum within the current branch grouping.
 *
 * @param stdClass $objpage
 * @return int
 */
function icontent_get_previous_cluster_pagenum(stdClass $objpage) {
    $previous = 0;
    foreach (icontent_get_cluster_pages($objpage) as $clusterpage) {
        if ((int)$clusterpage->pagenum >= (int)$objpage->pagenum) {
            break;
        }
        $previous = (int)$clusterpage->pagenum;
    }

    return $previous;
}

/**
 * Return the next pagenum within the current branch grouping.
 *
 * @param stdClass $objpage
 * @return int
 */
function icontent_get_next_cluster_pagenum(stdClass $objpage) {
    foreach (icontent_get_cluster_pages($objpage) as $clusterpage) {
        if ((int)$clusterpage->pagenum > (int)$objpage->pagenum) {
            return (int)$clusterpage->pagenum;
        }
    }

    return 0;
}

/**
 * Ensure the navigation resolver has the page-level nav settings available.
 *
 * @param stdClass $objpage
 * @return stdClass
 */
function icontent_get_navigation_page_context(stdClass $objpage) {
    global $DB;

    if (isset($objpage->prevmode, $objpage->prevpageid, $objpage->nextmode, $objpage->nextpageid)) {
        return $objpage;
    }

    if (!empty($objpage->id) && !empty($objpage->cmid)) {
        $fullpage = $DB->get_record(
            'icontent_pages',
            ['id' => (int)$objpage->id, 'cmid' => (int)$objpage->cmid, 'hidden' => 0],
            '*'
        );
        if ($fullpage) {
            return $fullpage;
        }
    }

    if (!empty($objpage->pagenum) && !empty($objpage->cmid)) {
        $fullpage = $DB->get_record(
            'icontent_pages',
            ['cmid' => (int)$objpage->cmid, 'pagenum' => (int)$objpage->pagenum, 'hidden' => 0],
            '*'
        );
        if ($fullpage) {
            return $fullpage;
        }
    }

    return $objpage;
}

/**
 * Resolve a custom navigation target to a visible page number in this activity.
 *
 * @param int $cmid
 * @param int $pageid
 * @return int
 */
function icontent_get_custom_navigation_pagenum($cmid, $pageid) {
    $targetpage = icontent_get_visible_page_by_id((int)$pageid, (int)$cmid);
    if (empty($targetpage->pagenum)) {
        return 0;
    }

    return (int)$targetpage->pagenum;
}

/**
 * Resolve the next routed page id from the latest attempt on the current page.
 *
 * @param stdClass $objpage
 * @return int
 */
function icontent_get_question_routed_next_pageid(stdClass $objpage) {
    global $DB, $USER;

    $sql = "SELECT pq.id,
                   pq.correctnextpageid,
                   pq.incorrectnextpageid,
                   pq.manualreviewnextpageid,
                   pq.defaultnextpageid,
                   COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1) AS effectivemaxmark
              FROM {icontent_pages_questions} pq
         LEFT JOIN {question} q
                ON q.id = pq.questionid
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND (
                    pq.correctnextpageid > 0
                    OR pq.incorrectnextpageid > 0
                    OR pq.manualreviewnextpageid > 0
                    OR pq.defaultnextpageid > 0
               )
          ORDER BY pq.id";
    $routes = $DB->get_records_sql($sql, [(int)$objpage->id, (int)$objpage->cmid]);

    foreach ($routes as $route) {
        $attempt = $DB->get_record_sql(
            "SELECT id, fraction, rightanswer
               FROM {icontent_question_attempts}
              WHERE pagesquestionsid = ?
                AND cmid = ?
                AND userid = ?
           ORDER BY timecreated DESC, id DESC",
            [(int)$route->id, (int)$objpage->cmid, (int)$USER->id],
            IGNORE_MULTIPLE
        );

        $targetpageid = 0;
        if (!empty($attempt)) {
            if ($attempt->rightanswer === ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE && !empty($route->manualreviewnextpageid)) {
                $targetpageid = (int)$route->manualreviewnextpageid;
            } else if (
                (float)$attempt->fraction >= ((float)$route->effectivemaxmark - 0.00001) &&
                !empty($route->correctnextpageid)
            ) {
                $targetpageid = (int)$route->correctnextpageid;
            } else if (!empty($route->incorrectnextpageid)) {
                $targetpageid = (int)$route->incorrectnextpageid;
            }
        }

        if (empty($targetpageid) && !empty($route->defaultnextpageid)) {
            $targetpageid = (int)$route->defaultnextpageid;
        }

        if (empty($targetpageid)) {
            continue;
        }

        $targetpage = icontent_get_visible_page_by_id($targetpageid, $objpage->cmid);
        if (!empty($targetpage)) {
            return (int)$targetpage->id;
        }
    }

    return 0;
}

/**
 * Persist actual page-to-page navigation for history-aware Previous buttons.
 *
 * @param int $sourcepageid
 * @param int $targetpageid
 * @param int $cmid
 * @return void
 */
function icontent_record_page_navigation($sourcepageid, $targetpageid, $cmid) {
    global $DB, $USER;

    if (empty($sourcepageid) || empty($targetpageid) || $sourcepageid == $targetpageid || empty($cmid) || empty($USER->id)) {
        return;
    }

    $DB->insert_record('icontent_pages_nav', (object) [
        'cmid' => (int)$cmid,
        'userid' => (int)$USER->id,
        'frompageid' => (int)$sourcepageid,
        'topageid' => (int)$targetpageid,
        'timecreated' => time(),
    ]);
}

/**
 * Get page previous.
 *
 * @param stdClass $objpage
 * @return int
 */
function icontent_get_prev_pagenum(stdClass $objpage) {
    $objpage = icontent_get_navigation_page_context($objpage);
    $prevmode = (int)($objpage->prevmode ?? 0);
    if ($prevmode === 1) {
        return 0;
    }
    if ($prevmode === 2) {
        $customprevious = icontent_get_custom_navigation_pagenum((int)$objpage->cmid, (int)($objpage->prevpageid ?? 0));
        if (!empty($customprevious)) {
            return $customprevious;
        }
    }

    $firstpagenum = icontent_get_min_visible_pagenum_by_cmid((int)$objpage->cmid);
    if (!empty($firstpagenum) && (int)$objpage->pagenum <= (int)$firstpagenum) {
        return 0;
    }

    $trackedpageid = icontent_get_navigation_source_pageid($objpage);
    if (!empty($trackedpageid)) {
        $trackedpage = icontent_get_visible_page_by_id($trackedpageid, $objpage->cmid);
        if (!empty($trackedpage->pagenum)) {
            return (int)$trackedpage->pagenum;
        }
    }

    if (icontent_page_is_clustered($objpage)) {
        $clusterprevious = icontent_get_previous_cluster_pagenum($objpage);
        if (!empty($clusterprevious)) {
            return $clusterprevious;
        }

        $parentpage = icontent_get_visible_page_by_id((int)$objpage->branchparentpageid, $objpage->cmid);
        if (!empty($parentpage->pagenum)) {
            return (int)$parentpage->pagenum;
        }
    }

    return icontent_get_previous_mainline_pagenum($objpage->cmid, $objpage->pagenum);
}

/**
 * Get next page.
 *
 * Return int next page
 *
 * @param stdClass $objpage
 * @return int $next
 */
function icontent_get_next_pagenum(stdClass $objpage) {
    $objpage = icontent_get_navigation_page_context($objpage);
    $nextmode = (int)($objpage->nextmode ?? 0);
    if ($nextmode === 1) {
        return 0;
    }
    if ($nextmode === 2) {
        $customnext = icontent_get_custom_navigation_pagenum((int)$objpage->cmid, (int)($objpage->nextpageid ?? 0));
        if (!empty($customnext)) {
            return $customnext;
        }
    }

    $lastpagenum = icontent_get_max_visible_pagenum_by_cmid((int)$objpage->cmid);
    if (!empty($lastpagenum) && (int)$objpage->pagenum >= (int)$lastpagenum) {
        return 0;
    }

    $routedpageid = icontent_get_question_routed_next_pageid($objpage);
    if (!empty($routedpageid)) {
        $routedpage = icontent_get_visible_page_by_id($routedpageid, $objpage->cmid);
        if (!empty($routedpage->pagenum)) {
            return (int)$routedpage->pagenum;
        }
    }

    if (icontent_page_is_clustered($objpage)) {
        $clusternext = icontent_get_next_cluster_pagenum($objpage);
        if (!empty($clusternext)) {
            return $clusternext;
        }

        $parentpage = icontent_get_visible_page_by_id((int)$objpage->branchparentpageid, $objpage->cmid);
        if (!empty($parentpage->pagenum)) {
            return icontent_get_next_mainline_pagenum($objpage->cmid, $parentpage->pagenum);
        }
    }

    return icontent_get_next_mainline_pagenum($objpage->cmid, $objpage->pagenum);
}

/**
 * Get page id by page number.
 *
 * @param int $cmid
 * @param int $pagenum
 * @return int|null
 */
function icontent_get_pageid_by_pagenum($cmid, $pagenum) {
    global $DB;

    if (empty($pagenum)) {
        return null;
    }

    return $DB->get_field('icontent_pages', 'id', ['cmid' => $cmid, 'pagenum' => $pagenum, 'hidden' => 0]) ?: null;
}

/**
 * Return the minimum visible page number for one activity instance.
 *
 * @param int $cmid
 * @return int
 */
function icontent_get_min_visible_pagenum_by_cmid($cmid) {
    global $DB;

    $page = $DB->get_record_sql(
        "SELECT min(pagenum) AS minpagenum
           FROM {icontent_pages}
          WHERE cmid = ?
            AND hidden = ?",
        [(int)$cmid, 0]
    );

    return (int)($page->minpagenum ?? 0);
}

/**
 * Return the maximum visible page number for one activity instance.
 *
 * @param int $cmid
 * @return int
 */
function icontent_get_max_visible_pagenum_by_cmid($cmid) {
    global $DB;

    $page = $DB->get_record_sql(
        "SELECT max(pagenum) AS maxpagenum
           FROM {icontent_pages}
          WHERE cmid = ?
            AND hidden = ?",
        [(int)$cmid, 0]
    );

    return (int)($page->maxpagenum ?? 0);
}

/**
 * Set updates for grades in table {grade_grades}.
 *
 * Returns true or false.
 *
 * @param stdClass $icontent
 * @param int $cmid
 * @param object $userid
 * @return boolean $return
 */
function icontent_set_grade_item(stdClass $icontent, $cmid, $userid) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');
    $params = [
        'itemname' => $icontent->name,
        'idnumber' => $cmid,
    ];
    $sumfraction = icontent_get_sumfraction_by_userid($cmid, $userid);
    $totalmaxfraction = icontent_get_totalmaxfraction_by_instance($cmid);
    if ($totalmaxfraction <= 0) {
        $totalmaxfraction = (float) icontent_get_totalquestions_by_instance($cmid);
    }
    $finalgrade = $totalmaxfraction > 0 ? ($sumfraction * $icontent->grade) / $totalmaxfraction : 0;
    // Make set icontent_grade for <iContent>.
    $igrade = new stdClass();
    $igrade->icontentid = $icontent->id;
    $igrade->userid = $userid;
    $igrade->cmid = $cmid;
    $igrade->grade = $finalgrade;
    $igrade->timemodified = time();
    // Check if table {icontent_grades} has grade for user.
    $igradeid = $DB->get_field(
        'icontent_grades',
        'id',
        [
            'icontentid' => $icontent->id,
            'userid' => $userid,
            'cmid' => $cmid,
        ]
    );
    if ($igradeid) {
        $igrade->id = $igradeid;
        $DB->update_record('icontent_grades', $igrade);
    } else {
        $DB->insert_record('icontent_grades', $igrade);
    }
    // Make grade.
    $grade = new stdClass();
    $grade->rawgrade = number_format($finalgrade, 5);
    $grade->userid = $userid;
    // Update gradebook.
    grade_update('mod/icontent', $icontent->course, 'mod', 'icontent', $icontent->id, 0, $grade, $params);
}

/**
 * Get total questions of question bank.
 *
 * Returns int of total questions.
 *
 * @param object $coursecontext
 * @return int of $tquestions
 */
function icontent_count_questions_of_questionbank($coursecontext) {
    global $DB;
    // 20240106 This seems to be working!
    $questions = $DB->get_record_sql(
        'SELECT count(*) as total
           FROM {question_bank_entries} qbe
           JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
          WHERE qc.contextid = ?',
        [$coursecontext]
    );
    return (int) $questions->total;
}

/**
 * Get total attempts users of users by course modules ID.
 *
 * Returns int of total attempts users.
 *
 * @param object $cmid
 * @param int $groupid
 * @return int of $tattemptsusers
 */
function icontent_count_attempts_users($cmid, $groupid = 0) {
    global $DB;

    $sql = "SELECT Count(DISTINCT u.id) AS totalattemptsusers
              FROM {user} u
        INNER JOIN {icontent_question_attempts} qa
                ON u.id = qa.userid
                         WHERE  qa.cmid = ?";
        $params = [$cmid];
    if (!empty($groupid)) {
            $sql .= "
                             AND EXISTS (
                                        SELECT 1
                                            FROM {groups_members} gm
                                         WHERE gm.userid = u.id
                                             AND gm.groupid = ?
                             )";
            $params[] = $groupid;
    }
        $totalattemptsusers = $DB->get_record_sql($sql, $params);
    return (int) $totalattemptsusers->totalattemptsusers;
}

/**
 * Get total attempts users of users with answers not evaluated by course modules ID.
 *
 * Returns int of total attempts users.
 *
 * @param object $cmid
 * @param null $status
 * @param int $groupid
 * @return int of $tattemptsusers
 */
function icontent_count_attempts_users_with_open_answers($cmid, $status = null, $groupid = 0) {
    global $DB;
    // Check if status is filled in.
    if (!isset($status)) {
        $status = ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE;
    }
    $poodllopenanswerscondition = "
                                        OR (
                                                qa.fraction = 0
                                                AND EXISTS (
                                                        SELECT 1
                                                            FROM {question} q
                                                         WHERE q.id = qa.questionid
                                                             AND q.qtype IN ('poodllrecording', 'cloudpoodll')
                                                )
                                        )";

    // SQL Query.
        $sql = "SELECT Count(DISTINCT u.id) AS totalattemptsusers
              FROM {user} u
        INNER JOIN {icontent_question_attempts} qa
                ON u.id = qa.userid
             WHERE  qa.cmid = ?
                             AND (
                                        qa.rightanswer IN (?)
                        OR EXISTS (
                            SELECT 1
                                FROM {question} q
                             WHERE q.id = qa.questionid
                                 AND q.qtype = ?
                        )
                                        {$poodllopenanswerscondition}
                                        OR (
                                            qa.fraction = 0
                                            AND EXISTS (
                                                SELECT 1
                                                    FROM {question} q
                                                 WHERE q.id = qa.questionid
                                                     AND q.qtype = 'recordrtc'
                                            )
                                        )
                                                         )";
        $params = [$cmid, $status, ICONTENT_QTYPE_ESSAYAUTOGRADE];
    if (!empty($groupid)) {
            $sql .= "
                             AND EXISTS (
                                        SELECT 1
                                            FROM {groups_members} gm
                                         WHERE gm.userid = u.id
                                             AND gm.groupid = ?
                             )";
            $params[] = $groupid;
    }
        $totalattemptsusers = $DB->get_record_sql($sql, $params);
    return (int) $totalattemptsusers->totalattemptsusers;
}

/**
 * Get questions of current page.
 *
 * Returns array questionspage.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array $questionspage
 */
function icontent_get_questions_of_currentpage($pageid, $cmid) {
    global $DB;
    return $DB->get_records(
        'icontent_pages_questions',
        ['pageid' => $pageid, 'cmid' => $cmid],
        null,
        'questionid, id, questionid, maxmark, correctnextpageid, incorrectnextpageid, manualreviewnextpageid, defaultnextpageid'
    );
}

/**
 * Get info answers by questionid.
 * Important: This function assumes that the naming patterns described in
 * <icontent_make_questions_answers_by_type> function were followed correctly.
 * Returns object infoanswer.
 *
 * @param int $questionid
 * @param int $qtype
 * @param string $answer
 * @return object $infoanswer
 */
function icontent_get_infoanswer_by_questionid($questionid, $qtype, $answer) {
    global $DB;
    // Check if var $qtype equals match. If true get $answerid.
    if (substr($qtype, 0, 5) === ICONTENT_QTYPE_MATCH) {
        [$strvar, $optionid] = explode('-', $qtype);
        $qtype = ICONTENT_QTYPE_MATCH;
    }
    // Creating and initializing the $infoanswer object.
    $infoanswer = new stdClass();
    $infoanswer->fraction = 0;
    $infoanswer->rightanswer = '';
    $infoanswer->answertext = '';
    // Set information by qtype.
    switch ($qtype) {
        case ICONTENT_QTYPE_MULTICHOICE:
        case ICONTENT_QTYPE_TRUEFALSE:
            // Check if answer is a checkbox. Otherwise, is radio.
            if (is_array($answer)) {
                $rightanswers = $DB->get_records_select('question_answers', 'question = ? AND fraction > ?', [$questionid, 0]);
                // Get array with key ID answer.
                $arrayoptionsids = icontent_get_array_options_answerid($answer);
                // Checks answers correct.
                foreach ($rightanswers as $rightanswer) {
                    $infoanswer->rightanswer .= $rightanswer->answer . ';';
                    if (array_key_exists($rightanswer->id, $arrayoptionsids)) {
                        $infoanswer->fraction += $rightanswer->fraction;
                        $infoanswer->answertext .= $rightanswer->answer . ';';
                    }
                }
                // Checks wrong answers.
                if ($infoanswer->fraction < ICONTENT_QUESTION_FRACTION) {
                    $wronganswers = $DB->get_records_select(
                        'question_answers',
                        'question = ? AND fraction = ?',
                        [
                            $questionid,
                            0,
                        ]
                    );
                    foreach ($wronganswers as $wronganswer) {
                        if (array_key_exists($wronganswer->id, $arrayoptionsids)) {
                            $infoanswer->answertext .= $wronganswer->answer . ';';
                        }
                    }
                }
                return $infoanswer;
            } else {
                // Get data answer. Pattern e.g. [qpid-8_answerid-2].
                if (!is_string($answer) || strpos($answer, '_') === false) {
                    return $infoanswer;
                }
                [$qp, $dtanswer] = explode('_', $answer, 2);
                if (strpos((string)$dtanswer, '-') === false) {
                    return $infoanswer;
                }
                [$stranswer, $answerid] = explode('-', $dtanswer, 2);
                $currentanwser = $DB->get_record_select(
                    'question_answers',
                    'question = ? AND id = ?',
                    [
                        $questionid,
                        $answerid,
                    ]
                );
                if (!$currentanwser) {
                    return $infoanswer;
                }
                $infoanswer->fraction = $currentanwser->fraction;
                $infoanswer->rightanswer = $currentanwser->answer;
                $infoanswer->answertext = $currentanwser->answer;

                if ($infoanswer->fraction < ICONTENT_QUESTION_FRACTION) {
                    $rightanwser = $DB->get_record_select(
                        'question_answers',
                        'question = ? AND fraction = ?',
                        [
                            $questionid,
                            ICONTENT_QUESTION_FRACTION,
                        ]
                    );
                    if ($rightanwser) {
                        $infoanswer->rightanswer = $rightanwser->answer;
                    }
                }
                return $infoanswer;
            }
            break;
        case ICONTENT_QTYPE_MATCH:
            $rightanwser = $DB->get_record('qtype_match_subquestions', ['id' => $optionid]);
            // Clean answers.
            $currentanwser = trim(strip_tags($answer));
            $rightanwser->answertext = trim(strip_tags($rightanwser->answertext));
            // Fill object $infoanswer.
            $infoanswer->rightanswer = $rightanwser->answertext . '->' . $rightanwser->questiontext . ';';
            $infoanswer->answertext = $currentanwser . '->' . $rightanwser->questiontext . ';';
            // Checks if answer is correct.
            if ($rightanwser->answertext === $currentanwser) {
                $infoanswer->fraction = ICONTENT_QUESTION_FRACTION;
            }
            return $infoanswer;
            break;
        case ICONTENT_QTYPE_ESSAY:
            $infoanswer->rightanswer = ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE;    // Wait evaluation of tutor.
            $infoanswer->answertext = s($answer);
            return $infoanswer;
            break;
    }
    throw new Exception("QTYPE Invalid.");
}

/**
 * Get object with attempts of users by course modules ID <iContent>.
 *
 * Returns object attempt users.
 *
 * @param int $cmid
 * @param string $sort
 * @param int $page
 * @param int $perpage
 * @param int $groupid
 * @return object $attemptusers, otherwhise false.
 */
function icontent_get_attempts_users($cmid, $sort, $page = 0, $perpage = ICONTENT_PER_PAGE, $groupid = 0) {
    global $CFG, $DB;
    $sortparams = 'u.lastname ' . $sort;
    $page = (int) $page;
    $perpage = (int) $perpage;
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

    // 20231225 Added Moodle branch check.
    if ($CFG->branch < 311) {
        $namefields = user_picture::fields('u', null, 'userid');
    } else {
        $userfieldsapi = \core_user\fields::for_userpic();
        $namefields = $userfieldsapi->get_sql('u', false, '', 'id', false)->selects;
    }

    $poodllopenanswerscondition = "
                                                        OR (
                                                                qa2.fraction = 0
                                                                AND EXISTS (
                                                                        SELECT 1
                                                                            FROM {question} q
                                                                         WHERE q.id = qa2.questionid
                                                                             AND q.qtype IN ('poodllrecording', 'cloudpoodll')
                                                                )
                                                        )";

        $sql = "SELECT DISTINCT $namefields,
                                     (SELECT Sum(fraction)
                                            FROM {icontent_question_attempts}
                                         WHERE userid = u.id
                                             AND cmid = ?) AS sumfraction,
                         (SELECT Sum(COALESCE(NULLIF(pq2.maxmark, 0), q2.defaultmark, 1))
                            FROM {icontent_question_attempts} qa2
                          INNER JOIN {icontent_pages_questions} pq2
                              ON qa2.pagesquestionsid = pq2.id
                          INNER JOIN {question} q2
                              ON qa2.questionid = q2.id
                           WHERE qa2.userid = u.id
                             AND qa2.cmid = ?) AS maxfraction,
                                     (SELECT Count(id)
                                            FROM {icontent_question_attempts}
                                         WHERE userid = u.id
                                             AND cmid = ?) AS totalanswers,
                                     (SELECT Count(id)
                                            FROM {icontent_question_attempts} qa2
                                         WHERE userid = u.id
                                             AND cmid = ?
                                             AND (
                                                        qa2.rightanswer IN (?)
                                                        OR EXISTS (
                                                                SELECT 1
                                                                    FROM {question} q
                                                                 WHERE q.id = qa2.questionid
                                                                     AND q.qtype = ?
                                                        )
                                                        {$poodllopenanswerscondition}
                                                        OR (
                                                            qa2.fraction = 0
                                                            AND EXISTS (
                                                                SELECT 1
                                                                    FROM {question} q
                                                                 WHERE q.id = qa2.questionid
                                                                     AND q.qtype = 'recordrtc'
                                                            )
                                                        )
                                             )) AS totalopenanswers
                            FROM {user} u
                INNER JOIN {icontent_question_attempts} qa
                                ON u.id = qa.userid
                         WHERE qa.cmid = ?";
    $params = [
        $cmid,
        $cmid,
        $cmid,
        $cmid,
        ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE,
        ICONTENT_QTYPE_ESSAYAUTOGRADE,
        $cmid,
    ]; // Field CMID used four times. Check (?).
    if (!empty($groupid)) {
        $sql .= "
              AND EXISTS (
                   SELECT 1
                     FROM {groups_members} gm
                    WHERE gm.userid = u.id
                      AND gm.groupid = ?
              )";
        $params[] = $groupid;
    }
    $sql .= "
         ORDER BY $sortparams";
    return $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
}

/**
 * Get object with attempts of users with answers not evaluated by course modules ID <iContent>.
 *
 * Returns object attempt users.
 *
 * @param int $cmid
 * @param string $sort
 * @param string $status
 * @param int $page
 * @param int $perpage
 * @param int $groupid
 * @return object $attemptusers, otherwhise false.
 */
function icontent_get_attempts_users_with_open_answers(
    $cmid,
    $sort,
    $status = null,
    $page = 0,
    $perpage = ICONTENT_PER_PAGE,
    $groupid = 0
) {
    global $CFG, $DB;
    $sortparams = 'u.firstname ' . $sort;
    $page = (int) $page;
    $perpage = (int) $perpage;
    // Check if status is filled in.
    if (!isset($status)) {
        $status = ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE;
    }
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

    // 20231225 Added Moodle branch check.
    if ($CFG->branch < 311) {
        $namefields = user_picture::fields('u', null, 'userid');
    } else {
        $userfieldsapi = \core_user\fields::for_userpic();
        $namefields = $userfieldsapi->get_sql('u', false, '', 'id', false)->selects;
        ;
    }

        $poodllopenanswersconditionqa2 = "
                            OR (
                                qa2.fraction = 0
                                AND EXISTS (
                                    SELECT 1
                                        FROM {question} q
                                     WHERE q.id = qa2.questionid
                                                                         AND q.qtype IN ('poodllrecording', 'cloudpoodll')
                                )
                            )";
        $poodllopenanswersconditionqa = "
                        OR (
                            qa.fraction = 0
                            AND EXISTS (
                                SELECT 1
                                    FROM {question} q
                                 WHERE q.id = qa.questionid
                                                                 AND q.qtype IN ('poodllrecording', 'cloudpoodll')
                            )
                        )";

        $sql = "SELECT DISTINCT $namefields,
                (SELECT Count(id)
                                     FROM {icontent_question_attempts} qa2
                  WHERE userid = u.id
                    AND cmid = ?
                                        AND (
                                                qa2.rightanswer IN (?)
                            OR EXISTS (
                                SELECT 1
                                    FROM {question} q
                                 WHERE q.id = qa2.questionid
                                     AND q.qtype = ?
                            )
                                                {$poodllopenanswersconditionqa2}
                                                OR (
                                                    qa2.fraction = 0
                                                    AND EXISTS (
                                                        SELECT 1
                                                            FROM {question} q
                                                         WHERE q.id = qa2.questionid
                                                             AND q.qtype = 'recordrtc'
                                                    )
                                                )
                                        )) AS totalopenanswers
             FROM {user} u
       INNER JOIN {icontent_question_attempts} qa
               ON u.id = qa.userid
            WHERE qa.cmid = ?
                            AND (
                                        qa.rightanswer IN (?)
                            OR EXISTS (
                                SELECT 1
                                    FROM {question} q
                                 WHERE q.id = qa.questionid
                                     AND q.qtype = ?
                            )
                                        {$poodllopenanswersconditionqa}
                                        OR (
                                            qa.fraction = 0
                                            AND EXISTS (
                                                SELECT 1
                                                    FROM {question} q
                                                 WHERE q.id = qa.questionid
                                                     AND q.qtype = 'recordrtc'
                                            )
                                        )
                            )";
    $params = [
        $cmid,
        $status,
        ICONTENT_QTYPE_ESSAYAUTOGRADE,
        $cmid,
        $status,
        ICONTENT_QTYPE_ESSAYAUTOGRADE,
    ]; // Field CMID used two times. Check (?).
    if (!empty($groupid)) {
        $sql .= "
              AND EXISTS (
                   SELECT 1
                     FROM {groups_members} gm
                    WHERE gm.userid = u.id
                      AND gm.groupid = ?
              )";
        $params[] = $groupid;
    }
    $sql .= "
         ORDER BY {$sortparams}";
    return $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
}

/**
 * Get object with attempt summary of user the current page.
 *
 * Returns object attempt summary.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $attemptsummary, otherwhise false.
 */
function icontent_get_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;

    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return false;
    }

        $sql = "SELECT Sum(qa.fraction) AS sumfraction,
               Sum(COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1)) AS maxfraction,
                   Count(qa.id) AS totalanswers,
                   qa.timecreated
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
            ON qa.questionid = q.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?
          GROUP BY qa.timecreated";
    $attemptsummary = $DB->get_record_sql($sql, [$pageid, $cmid, $USER->id, $latestattempttime]);
    // Checks if a property isn't empty.
    if (!empty($attemptsummary->totalanswers)) {
        return $attemptsummary;
    }
    return false;
}

/**
 * Get object with right answers by attempt summary the current page.
 *
 * Returns object total right answers by attempt summary.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $rightanswers
 */
function icontent_get_right_answers_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;
    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return (object)['totalrightanswers' => 0];
    }

        $sql = "SELECT Sum(CASE
                               WHEN qa.rightanswer = ? THEN 1
                               WHEN qa.fraction >= COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1) THEN 1
                               ELSE 0
                           END) AS totalrightanswers,
                           Sum(CASE
                               WHEN qa.rightanswer = ? THEN 1
                               WHEN qa.rightanswer = ? THEN 0
                               ELSE GREATEST(
                                   LEAST(
                                       qa.fraction / COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1),
                                       1
                                   ),
                                   0
                               )
                           END) AS equivalentrightanswers
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
            ON qa.questionid = q.id
                                 WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?";
    return $DB->get_record_sql($sql, [
        ICONTENT_QTYPE_ESSAY_STATUS_VALUED,
        ICONTENT_QTYPE_ESSAY_STATUS_VALUED,
        ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE,
        $pageid,
        $cmid,
        $USER->id,
        $latestattempttime,
    ]);
}

/**
 * Get object with open answers by attempt summary the current page.
 *
 * Returns object total open answers by attempt summary.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $openanswers
 */
function icontent_get_open_answers_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;
    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return (object)['totalopenanswers' => 0];
    }

    $poodllopenanswerscondition = "
                                        OR (
                                                qa.fraction = 0
                                                AND EXISTS (
                                                        SELECT 1
                                                            FROM {question} q
                                                         WHERE q.id = qa.questionid
                                                             AND q.qtype IN ('poodllrecording', 'cloudpoodll')
                                                )
                                        )";

        $sql = "SELECT Count(qa.id) AS totalopenanswers
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
                             AND (
                                        qa.rightanswer IN (?)
                                        {$poodllopenanswerscondition}
                             )
               AND qa.timecreated = ?";
    return $DB->get_record_sql($sql, [$pageid, $cmid, $USER->id, ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE, $latestattempttime]);
}

/**
 * Get latest attempt timestamp by page, module and user.
 *
 * @param int $pageid
 * @param int $cmid
 * @param int $userid
 * @return int|null
 */
function icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $userid) {
    global $DB;

    $sql = "SELECT MAX(qa.timecreated)
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?";
    $latestattempttime = $DB->get_field_sql($sql, [$pageid, $cmid, $userid]);

    if ($latestattempttime === false || $latestattempttime === null) {
        return null;
    }

    return (int)$latestattempttime;
}

/**
 * Get object with questions and open answers by user the current page.
 *
 * Returns object questions and open answers by attempt summary.
 *
 * @param int $userid
 * @param int $cmid
 * @param string $status
 * @return object $qopenanswers
 */
function icontent_get_questions_and_open_answers_by_user($userid, $cmid, $status = null) {
    global $DB;
    // Check if status is filled in.
    if (!isset($status)) {
        $status = ICONTENT_QTYPE_ESSAY_STATUS_TOEVALUATE;
    }
    $haspoodlloptions = icontent_optional_qtype_table_exists('qtype_poodllrecording_opts');
    $hasrecordrtcoptions = icontent_optional_qtype_table_exists('qtype_recordrtc_options');

    $poodllselect = $haspoodlloptions ? 'qpo.responseformat' : "'' AS responseformat";
    $recordrtcselect = $hasrecordrtcoptions ? 'qro.mediatype' : "'' AS mediatype";
    $poodlljoin = $haspoodlloptions
        ? "\n        LEFT JOIN {qtype_poodllrecording_opts} qpo" .
            "\n               ON qpo.questionid = q.id"
        : '';
    $recordrtcjoin = $hasrecordrtcoptions
        ? "\n        LEFT JOIN {qtype_recordrtc_options} qro" .
            "\n               ON qro.questionid = q.id"
        : '';
    $poodllcondition = <<<SQL
                    OR (
                        qa.fraction = 0
                        AND q.qtype IN (
                            'poodllrecording',
                            'cloudpoodll'
                        )
                    )
SQL;

    // SQL query.
    $sql = "SELECT qa.id,
                   qa.userid,
                   qa.questionid,
                   qa.pagesquestionsid,
                   qa.answertext,
                   qa.reviewercomment,
                   qa.reviewercommentformat,
                   qa.fraction,
                   qa.timecreated,
                   q.questiontext,
                   q.qtype,
                   {$poodllselect},
                   {$recordrtcselect},
                   pq.maxmark,
                   q.defaultmark,
                   pq.pageid
              FROM {icontent_question_attempts} qa
        INNER JOIN {question} q
                ON qa.questionid = q.id
        {$poodlljoin}
        {$recordrtcjoin}
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
             WHERE qa.cmid = ?
               AND qa.userid = ?
               AND (
                    qa.rightanswer IN (?)
                    OR q.qtype = ?
                    {$poodllcondition}
                    OR (
                        qa.fraction = 0
                        AND q.qtype = 'recordrtc'
                    )
               );";
    // Get records and return.
    return $DB->get_records_sql($sql, [$cmid, $userid, $status, ICONTENT_QTYPE_ESSAYAUTOGRADE]);
}

/**
 * Render manual-review answer content for supported question types.
 *
 * @param stdClass $qopenanswer
 * @param int $cmid
 * @return string
 */
function icontent_render_manual_review_answer(stdClass $qopenanswer, int $cmid): string {
    $answertext = (string)($qopenanswer->answertext ?? '');
    $qtype = (string)($qopenanswer->qtype ?? '');
    $responseformat = (string)($qopenanswer->responseformat ?? '');
    $recordrtcmediatype = (string)($qopenanswer->mediatype ?? '');

    if (in_array($qtype, ['poodllrecording', 'cloudpoodll'], true)) {
        $filename = icontent_extract_poodll_response_media_filename($answertext);
        $directmediaurl = ($qtype === 'cloudpoodll')
            ? icontent_extract_poodll_response_media_direct_url($answertext)
            : '';
        $userid = (int)$qopenanswer->userid;
        $questionid = (int)($qopenanswer->questionid ?? 0);
        $attempttime = (int)($qopenanswer->timecreated ?? 0);

        $isimagecandidate = (
            $responseformat === 'picture'
            || preg_match('/\.(?:png|jpe?g|gif|webp)$/i', $filename)
        );

        if ($isimagecandidate) {
            $imagefilename = icontent_extract_poodll_response_filename($answertext);
            $imagesrc = icontent_get_poodll_response_image_url(
                $imagefilename,
                $userid,
                $cmid,
                $questionid,
                $attempttime
            );
            if (!empty($imagesrc)) {
                return html_writer::empty_tag('img', [
                    'src' => $imagesrc,
                    'alt' => s($imagefilename),
                    'class' => 'img-fluid icontent-manualreview-image',
                    'style' => 'max-width: 100%; height: auto;',
                ]);
            }
        }

        $prefixcandidates = ['audio/', 'video/', ''];
        if ($responseformat === 'audio') {
            $prefixcandidates = ['audio/', 'video/', ''];
        } else if ($responseformat === 'video') {
            $prefixcandidates = ['video/', 'audio/', ''];
        }

        $mediasrc = '';
        $selectedprefix = '';
        foreach ($prefixcandidates as $prefixcandidate) {
            $candidate = icontent_get_poodll_response_media_url(
                $filename,
                $userid,
                $cmid,
                $questionid,
                $attempttime,
                $prefixcandidate
            );
            if (!empty($candidate)) {
                $mediasrc = $candidate;
                $selectedprefix = $prefixcandidate;
                break;
            }
        }

        if (!empty($mediasrc)) {
            $shouldrendervideo = ($responseformat === 'video' || $selectedprefix === 'video/');
            if ($shouldrendervideo) {
                return html_writer::tag('video', html_writer::empty_tag('source', [
                    'src' => $mediasrc,
                ]), [
                    'controls' => 'controls',
                    'preload' => 'metadata',
                    'class' => 'w-100',
                    'style' => 'max-width: 100%; height: auto;',
                ]);
            }

            return html_writer::tag('audio', html_writer::empty_tag('source', [
                'src' => $mediasrc,
            ]), [
                'controls' => 'controls',
                'preload' => 'metadata',
                'class' => 'w-100',
            ]);
        }

        if ($directmediaurl !== '') {
            $isvideo = (
                $responseformat === 'video'
                || preg_match('/\.(?:mp4|m4v|mov|webm)(?:[?#].*)?$/i', $directmediaurl)
            );

            if ($isvideo) {
                return html_writer::tag('video', html_writer::empty_tag('source', [
                    'src' => $directmediaurl,
                ]), [
                    'controls' => 'controls',
                    'preload' => 'metadata',
                    'class' => 'w-100',
                    'style' => 'max-width: 100%; height: auto;',
                ]);
            }

            return html_writer::tag('audio', html_writer::empty_tag('source', [
                'src' => $directmediaurl,
            ]), [
                'controls' => 'controls',
                'preload' => 'metadata',
                'class' => 'w-100',
            ]);
        }

        if ($qtype === 'cloudpoodll') {
            $qasmediaurl = icontent_get_cloudpoodll_media_url_from_qattempt_data(
                $cmid,
                $questionid,
                $attempttime,
                $answertext
            );
            if ($qasmediaurl !== '') {
                $isvideo = (
                    $responseformat === 'video'
                    || preg_match('/\.(?:mp4|m4v|mov|webm)(?:[?#].*)?$/i', $qasmediaurl)
                );

                if ($isvideo) {
                    return html_writer::tag('video', html_writer::empty_tag('source', [
                        'src' => $qasmediaurl,
                    ]), [
                        'controls' => 'controls',
                        'preload' => 'metadata',
                        'class' => 'w-100',
                        'style' => 'max-width: 100%; height: auto;',
                    ]);
                }

                return html_writer::tag('audio', html_writer::empty_tag('source', [
                    'src' => $qasmediaurl,
                ]), [
                    'controls' => 'controls',
                    'preload' => 'metadata',
                    'class' => 'w-100',
                ]);
            }
        }

        if ($filename !== '' || $responseformat !== '') {
            // File not yet available (still uploading or converting via PoodLL).
            // Show a non-confusing placeholder instead of the raw response summary text.
            return html_writer::tag(
                'em',
                get_string('recordingsubmittedprocessing', 'mod_icontent'),
                ['class' => 'icontent-recording-processing text-muted']
            );
        }
    }

    if ($qtype === 'recordrtc') {
        $filename = icontent_extract_poodll_response_media_filename($answertext);
        $mediatype = in_array($recordrtcmediatype, ['audio', 'video', 'screen'], true) ? $recordrtcmediatype : 'audio';
        // RecordRTC screen responses are usually video, but older/inconsistent records may have
        // missing mediatype or generic mimetype metadata. Try safe fallbacks before giving up.
        $prefixcandidates = ($mediatype === 'video' || $mediatype === 'screen')
            ? ['video/', '']
            : ['audio/', 'video/', ''];
        $selectedprefix = '';
        $mediasrc = '';
        foreach ($prefixcandidates as $prefixcandidate) {
            $candidate = icontent_get_recordrtc_response_media_url(
                $filename,
                (int)$qopenanswer->userid,
                $cmid,
                (int)($qopenanswer->questionid ?? 0),
                (int)($qopenanswer->timecreated ?? 0),
                $prefixcandidate
            );
            if (!empty($candidate)) {
                $mediasrc = $candidate;
                $selectedprefix = $prefixcandidate;
                break;
            }
        }

        if (!empty($mediasrc)) {
            $shouldrendervideo = ($mediatype === 'video' || $mediatype === 'screen' || $selectedprefix === 'video/');
            if ($shouldrendervideo) {
                return html_writer::tag('video', html_writer::empty_tag('source', [
                    'src' => $mediasrc,
                ]), [
                    'controls' => 'controls',
                    'preload' => 'metadata',
                    'class' => 'w-100',
                    'style' => 'max-width: 100%; height: auto;',
                ]);
            }

            return html_writer::tag('audio', html_writer::empty_tag('source', [
                'src' => $mediasrc,
            ]), [
                'controls' => 'controls',
                'preload' => 'metadata',
                'class' => 'w-100',
            ]);
        }
    }

    // For essay/essayautograde: render with the stored format (FORMAT_HTML for rich text answers).
    // Legacy records stored as plain text will have answertextformat=0; fall back to FORMAT_HTML
    // which renders the plain text safely.
    $answerformat = (int)($qopenanswer->answertextformat ?? 0);
    if ($answerformat === 0) {
        $answerformat = FORMAT_HTML;
    }
    return format_text($answertext, $answerformat, [
        'noclean' => false,
        'para' => false,
    ]);
}

/**
 * Render learner-facing feedback for one submitted answer when available.
 *
 * @param stdClass $submittedanswer
 * @return string
 */
function icontent_render_attempt_feedback(stdClass $submittedanswer): string {
    $parts = [];

    $generallabel = 'General feedback';
    if (get_string_manager()->string_exists('generalfeedback', 'question')) {
        $generallabel = get_string('generalfeedback', 'question');
    }

    $generalfeedback = trim((string)($submittedanswer->generalfeedback ?? ''));
    if ($generalfeedback !== '') {
        $parts[] = html_writer::div(
            html_writer::tag('strong', s($generallabel) . ': ') .
            format_text(
                $generalfeedback,
                (int)($submittedanswer->generalfeedbackformat ?? FORMAT_HTML),
                ['noclean' => false, 'para' => false]
            ),
            'icontent-question-feedback-item mb-2'
        );
    }

    $outcomefeedback = icontent_get_outcome_feedback_for_attempt($submittedanswer);
    if ($outcomefeedback !== '') {
        $parts[] = html_writer::div(
            html_writer::tag('strong', s(get_string('outcomefeedbacklabel', 'mod_icontent')) . ': ') . $outcomefeedback,
            'icontent-question-feedback-item mb-2'
        );
    }

    $selectedanswerfeedback = icontent_get_selected_answer_feedback_for_attempt($submittedanswer);
    if (!empty($selectedanswerfeedback)) {
        $feedbackitems = [];
        foreach ($selectedanswerfeedback as $item) {
            $feedbackitems[] = html_writer::tag('li', $item, ['class' => 'mb-1']);
        }

        $parts[] = html_writer::div(
            html_writer::tag('strong', s(get_string('answerfeedbacklabel', 'mod_icontent')) . ': ') .
            html_writer::tag('ul', implode('', $feedbackitems), ['class' => 'mb-0']),
            'icontent-question-feedback-item mb-2'
        );
    }

    if (empty($parts)) {
        return '';
    }

    return html_writer::div(implode('', $parts), 'icontent-question-feedback mt-2');
}

/**
 * Get outcome-level feedback for one submitted answer when available.
 *
 * @param stdClass $submittedanswer
 * @return string
 */
function icontent_get_outcome_feedback_for_attempt(stdClass $submittedanswer): string {
    global $DB;

    $qtype = (string)($submittedanswer->qtype ?? '');
    if (!in_array($qtype, ['multichoice', 'calculatedmulti'], true)) {
        return '';
    }

    if (!$DB->get_manager()->table_exists('qtype_multichoice_options')) {
        return '';
    }

    $options = $DB->get_record(
        'qtype_multichoice_options',
        ['questionid' => (int)$submittedanswer->questionid],
        'correctfeedback, correctfeedbackformat, partiallycorrectfeedback, partiallycorrectfeedbackformat, ' .
            'incorrectfeedback, incorrectfeedbackformat',
        IGNORE_MISSING
    );
    if (empty($options)) {
        return '';
    }

    $fraction = (float)($submittedanswer->fraction ?? 0);
    $maxmark = (float)($submittedanswer->maxmark ?? 0);
    if ($maxmark <= 0) {
        $maxmark = 1.0;
    }

    if ($fraction >= ($maxmark - 0.00001)) {
        $feedbacktext = (string)($options->correctfeedback ?? '');
        $feedbackformat = (int)($options->correctfeedbackformat ?? FORMAT_HTML);
    } else if ($fraction > 0) {
        $feedbacktext = (string)($options->partiallycorrectfeedback ?? '');
        $feedbackformat = (int)($options->partiallycorrectfeedbackformat ?? FORMAT_HTML);
    } else {
        $feedbacktext = (string)($options->incorrectfeedback ?? '');
        $feedbackformat = (int)($options->incorrectfeedbackformat ?? FORMAT_HTML);
    }

    if (trim($feedbacktext) === '') {
        return '';
    }

    return format_text($feedbacktext, $feedbackformat, ['noclean' => false, 'para' => false]);
}

/**
 * Get selected-answer feedback entries for one submitted answer where available.
 *
 * @param stdClass $submittedanswer
 * @return array
 */
function icontent_get_selected_answer_feedback_for_attempt(stdClass $submittedanswer): array {
    global $DB;

    $qtype = (string)($submittedanswer->qtype ?? '');
    if (!in_array($qtype, ['multichoice', 'truefalse'], true)) {
        return [];
    }

    $answertext = (string)($submittedanswer->answertext ?? '');
    if (trim($answertext) === '') {
        return [];
    }

    $selectedtokens = icontent_extract_answer_tokens_for_feedback($answertext);
    $selectedanswerids = icontent_extract_answer_ids_for_feedback($answertext);
    if (empty($selectedtokens) && empty($selectedanswerids)) {
        return [];
    }

    $records = $DB->get_records(
        'question_answers',
        ['question' => (int)$submittedanswer->questionid],
        '',
        'id, answer, feedback, feedbackformat'
    );
    if (empty($records)) {
        return [];
    }

    $feedbackitems = [];
    foreach ($records as $record) {
        $answerid = (int)($record->id ?? 0);
        if (!empty($selectedanswerids) && in_array($answerid, $selectedanswerids, true)) {
            $feedbacktext = trim((string)($record->feedback ?? ''));
            if ($feedbacktext === '') {
                continue;
            }

            $feedbackitems[] = format_text(
                $feedbacktext,
                (int)($record->feedbackformat ?? FORMAT_HTML),
                ['noclean' => false, 'para' => false]
            );
            continue;
        }

        $answertoken = icontent_normalize_answer_feedback_token((string)$record->answer);
        if (!in_array($answertoken, $selectedtokens, true)) {
            continue;
        }

        $feedbacktext = trim((string)($record->feedback ?? ''));
        if ($feedbacktext === '') {
            continue;
        }

        $feedbackitems[] = format_text(
            $feedbacktext,
            (int)($record->feedbackformat ?? FORMAT_HTML),
            ['noclean' => false, 'para' => false]
        );
    }

    return array_values(array_unique($feedbackitems));
}

/**
 * Extract normalized selected answer tokens from a stored answer string.
 *
 * @param string $answertext
 * @return array
 */
function icontent_extract_answer_tokens_for_feedback(string $answertext): array {
    $tokens = preg_split('/[;\n\r]+/', $answertext);
    if ($tokens === false) {
        $tokens = [];
    }

    $tokens = array_filter(array_map('trim', $tokens), static function($token): bool {
        return $token !== '';
    });

    if (empty($tokens)) {
        $tokens = [trim($answertext)];
    }

    $normalized = array_map('icontent_normalize_answer_feedback_token', $tokens);
    $normalized = array_values(array_unique(array_filter($normalized, static function($token): bool {
        return $token !== '';
    })));

    return $normalized;
}

/**
 * Extract selected answer IDs from stored answer text patterns.
 *
 * @param string $answertext
 * @return array
 */
function icontent_extract_answer_ids_for_feedback(string $answertext): array {
    if (!preg_match_all('/answerid-(\d+)/i', $answertext, $matches)) {
        return [];
    }

    return array_values(array_unique(array_map('intval', $matches[1])));
}

/**
 * Normalize one answer token for matching against question_answers.answer.
 *
 * @param string $token
 * @return string
 */
function icontent_normalize_answer_feedback_token(string $token): string {
    $token = trim(strip_tags($token));
    $token = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $token = preg_replace('/\s+/u', ' ', $token);
    return strtolower(trim((string)$token));
}

/**
 * Extract a PoodLL response filename (audio/video/image) from stored response text.
 *
 * @param string $answertext
 * @return string
 */
function icontent_extract_poodll_response_media_filename(string $answertext): string {
    $answertext = trim(strip_tags($answertext));
    if ($answertext === '') {
        return '';
    }

    if (preg_match('/([a-z0-9._-]+\.(?:png|jpe?g|gif|webp|mp3|wav|ogg|m4a|webm|mp4|m4v|mov))/i', $answertext, $matches)) {
        return $matches[1];
    }

    $path = parse_url($answertext, PHP_URL_PATH);
    if (!empty($path)) {
        return basename($path);
    }

    return $answertext;
}

/**
 * Extract a direct media URL from stored response text when present.
 *
 * @param string $answertext
 * @return string
 */
function icontent_extract_poodll_response_media_direct_url(string $answertext): string {
    $answertext = trim(strip_tags($answertext));
    if ($answertext === '') {
        return '';
    }

    if (preg_match('/https?:\/\/[^\s\"\'<>]+/i', $answertext, $matches)) {
        $url = trim($matches[0]);
        if (clean_param($url, PARAM_URL) !== '') {
            return $url;
        }
    }

    if (preg_match('/^https?:\/\//i', $answertext) && clean_param($answertext, PARAM_URL) !== '') {
        return $answertext;
    }

    return '';
}

/**
 * Recover Cloud PoodLL media URL from question attempt step data.
 *
 * @param int $cmid
 * @param int $questionid
 * @param int $attempttime
 * @param string $answertext
 * @return string
 */
function icontent_get_cloudpoodll_media_url_from_qattempt_data(
    int $cmid,
    int $questionid,
    int $attempttime,
    string $answertext = ''
): string {
    global $DB;

    if ($cmid <= 0 || $questionid <= 0) {
        return '';
    }

    $params = [
        'cmid' => $cmid,
        'questionid' => $questionid,
        'contextlevel' => CONTEXT_MODULE,
    ];
    $timefilter = '';
    if ($attempttime > 0) {
        $timefilter = ' AND qas.timecreated BETWEEN :attemptlower AND :attemptupper';
        $params['attemptlower'] = max(0, $attempttime - 1800);
        $params['attemptupper'] = $attempttime + 1800;
    }

    $valuefilter = '';
    $filename = icontent_extract_poodll_response_media_filename($answertext);
    if ($filename !== '') {
        $valuefilter = ' AND qasd.value LIKE :filenamepattern';
        $params['filenamepattern'] = '%' . $filename . '%';
    }

        $sql = "SELECT qasd.id,
                                         qasd.name,
                                         qasd.value
              FROM {question_attempt_step_data} qasd
              JOIN {question_attempt_steps} qas
                ON qas.id = qasd.attemptstepid
              JOIN {question_attempts} qa
                ON qa.id = qas.questionattemptid
              JOIN {question_usages} qu
                ON qu.id = qa.questionusageid
              JOIN {context} c
                ON c.id = qu.contextid
             WHERE qa.questionid = :questionid
               AND c.contextlevel = :contextlevel
               AND c.instanceid = :cmid
               AND qasd.name IN ('answermediaurl', 'answer', 'answerdetails')
               AND qasd.value <> ''
               AND qasd.value <> ':'
                                     $valuefilter
                   $timefilter
          ORDER BY qas.timecreated DESC";

    $records = $DB->get_records_sql($sql, $params, 0, 30);
    foreach ($records as $record) {
        $value = (string)($record->value ?? '');

        if ((string)($record->name ?? '') === 'answerdetails') {
            $details = json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE && !empty($details->recevents)) {
                foreach (array_reverse((array)$details->recevents) as $event) {
                    foreach (['finalfile', 'targetfile', 'mediaurl'] as $key) {
                        if (!empty($event->{$key})) {
                            $direct = icontent_extract_poodll_response_media_direct_url((string)$event->{$key});
                            if ($direct !== '') {
                                return $direct;
                            }
                        }
                    }
                }
            }
        }

        $direct = icontent_extract_poodll_response_media_direct_url($value);
        if ($direct !== '') {
            return $direct;
        }
    }

    return '';
}

/**
 * Extract a PoodLL drawing filename from stored response text.
 *
 * @param string $answertext
 * @return string
 */
function icontent_extract_poodll_response_filename(string $answertext): string {
    $filename = icontent_extract_poodll_response_media_filename($answertext);
    if (preg_match('/\.(?:png|jpe?g|gif|webp)$/i', $filename)) {
        return $filename;
    }
    return '';
}

/**
 * Return content hashes of PoodLL conversion placeholder media files.
 *
 * @return array
 */
function icontent_get_poodll_placeholder_contenthashes(): array {
    global $CFG;

    static $hashes = null;
    if ($hashes !== null) {
        return $hashes;
    }

    $hashes = [];
    $placeholderfiles = [
        $CFG->dirroot . '/filter/poodll/convertingmessage.mp3',
        $CFG->dirroot . '/filter/poodll/convertingmessage.mp4',
    ];

    foreach ($placeholderfiles as $placeholderfile) {
        if (is_readable($placeholderfile)) {
            $hash = sha1_file($placeholderfile);
            if (!empty($hash)) {
                $hashes[] = $hash;
            }
        }
    }

    $hashes = array_values(array_unique($hashes));
    return $hashes;
}

/**
 * Check if a media file record points to PoodLL's conversion placeholder media.
 *
 * @param stdClass|null $file
 * @return bool
 */
function icontent_is_poodll_placeholder_media_record(?stdClass $file): bool {
    if (!$file) {
        return false;
    }

    $filename = strtolower((string)($file->filename ?? ''));
    if ($filename === 'convertingmessage.mp3' || $filename === 'convertingmessage.mp4') {
        return true;
    }

    $contenthash = (string)($file->contenthash ?? '');
    if ($contenthash === '') {
        return false;
    }

    return in_array($contenthash, icontent_get_poodll_placeholder_contenthashes(), true);
}

/**
 * Locate metadata for a stored PoodLL response media file.
 *
 * @param string $filename
 * @param int $userid
 * @param int $cmid
 * @param int $questionid
 * @param int $attempttime
 * @param string $mimetypeprefix Optional mimetype prefix filter (for example 'audio/' or 'video/').
 * @param string $filearea
 * @return stdClass|null
 */
function icontent_get_poodll_response_media_file_record(
    string $filename,
    int $userid,
    int $cmid,
    int $questionid = 0,
    int $attempttime = 0,
    string $mimetypeprefix = '',
    string $filearea = 'response_answer'
): ?stdClass {
    global $DB;

    $file = null;
    $placeholderfound = false;

    $filterplaceholder = static function ($candidate) use (&$placeholderfound): ?stdClass {
        if (!$candidate) {
            return null;
        }
        if (icontent_is_poodll_placeholder_media_record($candidate)) {
            $placeholderfound = true;
            return null;
        }
        return $candidate;
    };

    if ($filename !== '') {
        $sql = "SELECT f.contextid,
                       f.itemid,
                       f.filepath,
                       f.filename,
                       f.component,
                       f.filearea,
                       f.mimetype,
                       f.contenthash
                  FROM {files} f
                  JOIN {context} c
                    ON c.id = f.contextid
                 WHERE f.component = 'question'
                   AND f.filearea = ?
                   AND f.filename = ?
                   AND f.userid = ?
                   AND c.contextlevel = ?
                   AND c.instanceid = ?
                   AND f.filesize > 0
              ORDER BY f.timemodified DESC";
        $file = $filterplaceholder(
            $DB->get_record_sql($sql, [$filearea, $filename, $userid, CONTEXT_MODULE, $cmid], IGNORE_MULTIPLE)
        );

        if (!$file) {
            $fallbacksql = "SELECT f.contextid,
                                   f.itemid,
                                   f.filepath,
                                   f.filename,
                                   f.component,
                                   f.filearea,
                                   f.mimetype,
                                   f.contenthash
                              FROM {files} f
                             WHERE f.component = 'question'
                               AND f.filearea = ?
                               AND f.filename = ?
                               AND f.userid = ?
                               AND f.filesize > 0
                          ORDER BY f.timemodified DESC";
            $file = $filterplaceholder(
                $DB->get_record_sql($fallbacksql, [$filearea, $filename, $userid], IGNORE_MULTIPLE)
            );
        }

        if (!$file) {
            $fallbacksql = "SELECT f.contextid,
                                   f.itemid,
                                   f.filepath,
                                   f.filename,
                                   f.component,
                                   f.filearea,
                                   f.mimetype,
                                   f.contenthash
                              FROM {files} f
                             WHERE f.component = 'question'
                               AND f.filearea = ?
                               AND f.filename = ?
                               AND f.filesize > 0
                          ORDER BY f.timemodified DESC";
            $file = $filterplaceholder(
                $DB->get_record_sql($fallbacksql, [$filearea, $filename], IGNORE_MULTIPLE)
            );
        }
    }

    if (!$file && $questionid > 0) {
        $params = [
            'userid' => $userid,
            'questionid' => $questionid,
            'contextlevel' => CONTEXT_MODULE,
            'cmid' => $cmid,
            'filearea' => $filearea,
        ];
        $attemptcondition = '';
        if ($attempttime > 0) {
            $attemptcondition = ' AND qas.timecreated <= :attempttime
                                  AND qas.timecreated >= :attemptlowerbound';
            $params['attempttime'] = $attempttime;
            $params['attemptlowerbound'] = max(0, $attempttime - 600);
        }

        $mimetypecondition = '';
        if ($mimetypeprefix !== '') {
            $mimetypecondition = ' AND f.mimetype LIKE :mimetypeprefix';
            $params['mimetypeprefix'] = $mimetypeprefix . '%';
        }

        $questionfilesql = "SELECT f.contextid,
                                   f.itemid,
                                   f.filepath,
                                   f.filename,
                                   f.component,
                                   f.filearea,
                                                                     f.mimetype,
                                                                     f.contenthash
                              FROM {files} f
                              JOIN {question_attempt_steps} qas
                                ON qas.id = f.itemid
                              JOIN {question_attempts} qa
                                ON qa.id = qas.questionattemptid
                              JOIN {question_usages} qu
                                ON qu.id = qa.questionusageid
                              JOIN {context} c
                                ON c.id = qu.contextid
                             WHERE f.component = 'question'
                                                             AND f.filearea = :filearea
                               AND f.filesize > 0
                               AND f.userid = :userid
                               AND qa.questionid = :questionid
                               AND c.contextlevel = :contextlevel
                               AND c.instanceid = :cmid
                                   $mimetypecondition
                                   $attemptcondition
                          ORDER BY f.timemodified DESC";
        $file = $filterplaceholder(
            $DB->get_record_sql($questionfilesql, $params, IGNORE_MULTIPLE)
        );
    }

    if (!$file && $userid > 0 && $attempttime > 0) {
        $draftparams = [
            'userid' => $userid,
            'timestart' => max(0, $attempttime - 600),
            'timeend' => $attempttime + 600,
        ];
        $draftfilenamecondition = '';
        if ($filename !== '' && !$placeholderfound) {
            $draftfilenamecondition = ' AND f.filename = :filename';
            $draftparams['filename'] = $filename;
        }

        $draftmimetypecondition = '';
        if ($mimetypeprefix !== '') {
            $draftmimetypecondition = ' AND f.mimetype LIKE :mimetypeprefix';
            $draftparams['mimetypeprefix'] = $mimetypeprefix . '%';
        }

        $draftsql = "SELECT f.contextid,
                            f.itemid,
                            f.filepath,
                            f.filename,
                            f.component,
                            f.filearea,
                                                        f.mimetype,
                                                        f.contenthash
                       FROM {files} f
                      WHERE f.component = 'user'
                        AND f.filearea = 'draft'
                        AND f.userid = :userid
                        AND f.filesize > 0
                        AND f.timemodified >= :timestart
                        AND f.timemodified <= :timeend
                            $draftfilenamecondition
                            $draftmimetypecondition
                   ORDER BY f.timemodified DESC";
        $file = $filterplaceholder($DB->get_record_sql($draftsql, $draftparams, IGNORE_MULTIPLE));
    }

    return $file ?: null;
}

/**
 * Locate metadata for a stored PoodLL sketch response image.
 *
 * @param string $filename
 * @param int $userid
 * @param int $cmid
 * @param int $questionid
 * @param int $attempttime
 * @return stdClass|null
 */
function icontent_get_poodll_response_image_file_record(
    string $filename,
    int $userid,
    int $cmid,
    int $questionid = 0,
    int $attempttime = 0
): ?stdClass {
    global $DB;

        $file = null;

    if ($filename !== '') {
            $sql = "SELECT f.contextid,
                                             f.itemid,
                                             f.filepath,
                                             f.filename,
                                             f.component,
                                             f.filearea,
                                             f.mimetype
                                    FROM {files} f
                                    JOIN {context} c
                                        ON c.id = f.contextid
                                 WHERE f.component = 'question'
                                     AND f.filearea = 'response_answer'
                                     AND f.filename = ?
                                     AND f.userid = ?
                                     AND c.contextlevel = ?
                                     AND c.instanceid = ?
                                     AND f.filesize > 0
                            ORDER BY f.timemodified DESC";
            $file = $DB->get_record_sql($sql, [$filename, $userid, CONTEXT_MODULE, $cmid], IGNORE_MULTIPLE);

        if (!$file) {
                $fallbacksql = "SELECT f.contextid,
                                                                     f.itemid,
                                                                     f.filepath,
                                                                     f.filename,
                                                                                            f.component,
                                                                                            f.filearea,
                                                                     f.mimetype
                                                            FROM {files} f
                                                         WHERE f.component = 'question'
                                                             AND f.filearea = 'response_answer'
                                                             AND f.filename = ?
                                                             AND f.userid = ?
                                                             AND f.filesize > 0
                                                    ORDER BY f.timemodified DESC";
                $file = $DB->get_record_sql($fallbacksql, [$filename, $userid], IGNORE_MULTIPLE);
        }

        if (!$file) {
                $fallbacksql = "SELECT f.contextid,
                                                                     f.itemid,
                                                                     f.filepath,
                                                                     f.filename,
                                                                                            f.component,
                                                                                            f.filearea,
                                                                     f.mimetype
                                                            FROM {files} f
                                                         WHERE f.component = 'question'
                                                             AND f.filearea = 'response_answer'
                                                             AND f.filename = ?
                                                             AND f.filesize > 0
                                                    ORDER BY f.timemodified DESC";
                $file = $DB->get_record_sql($fallbacksql, [$filename], IGNORE_MULTIPLE);
        }
    }

    if (!$file && $questionid > 0) {
            $params = [
                    'userid' => $userid,
                    'questionid' => $questionid,
                    'contextlevel' => CONTEXT_MODULE,
                    'cmid' => $cmid,
            ];
            $attemptcondition = '';
            if ($attempttime > 0) {
                $attemptcondition = ' AND qas.timecreated <= :attempttime
                              AND qas.timecreated >= :attemptlowerbound';
                $params['attempttime'] = $attempttime;
                $params['attemptlowerbound'] = max(0, $attempttime - 600);
            }
            $questionfilesql = "SELECT f.contextid,
                                                                     f.itemid,
                                                                     f.filepath,
                                                                     f.filename,
                                                                     f.component,
                                                                     f.filearea,
                                                                     f.mimetype
                                                            FROM {files} f
                                                            JOIN {question_attempt_steps} qas
                                                                ON qas.id = f.itemid
                                                            JOIN {question_attempts} qa
                                                                ON qa.id = qas.questionattemptid
                                                            JOIN {question_usages} qu
                                                                ON qu.id = qa.questionusageid
                                                            JOIN {context} c
                                                                ON c.id = qu.contextid
                                                         WHERE f.component = 'question'
                                                             AND f.filearea = 'response_answer'
                                                             AND f.filesize > 0
                                                             AND f.userid = :userid
                                                             AND qa.questionid = :questionid
                                                             AND f.mimetype LIKE 'image/%'
                                                             AND c.contextlevel = :contextlevel
                                                             AND c.instanceid = :cmid
                                                                 $attemptcondition
                                                    ORDER BY f.timemodified DESC";
            $file = $DB->get_record_sql($questionfilesql, $params, IGNORE_MULTIPLE);

            if (!$file) {
                                        $fallbackparams = [
                                            'userid' => $userid,
                                            'questionid' => $questionid,
                                        ];
                                        $fallbackattemptcondition = '';
                                        if ($attempttime > 0) {
                                            $fallbackattemptcondition = ' AND qas.timecreated <= :attempttime
                                                                  AND qas.timecreated >= :attemptlowerbound';
                                            $fallbackparams['attempttime'] = $attempttime;
                                            $fallbackparams['attemptlowerbound'] = max(0, $attempttime - 600);
                                        }
                                        $questionfallbacksql = "SELECT f.contextid,
                                                                                     f.itemid,
                                                                                     f.filepath,
                                                                                     f.filename,
                                                                                     f.component,
                                                                                     f.filearea,
                                                                                     f.mimetype
                                                                            FROM {files} f
                                                                            JOIN {question_attempt_steps} qas
                                                                                ON qas.id = f.itemid
                                                                            JOIN {question_attempts} qa
                                                                                ON qa.id = qas.questionattemptid
                                                                         WHERE f.component = 'question'
                                                                             AND f.filearea = 'response_answer'
                                                                             AND f.filesize > 0
                                                                             AND f.userid = :userid
                                                                             AND qa.questionid = :questionid
                                                                             AND f.mimetype LIKE 'image/%'
                                                                                 $fallbackattemptcondition
                                                                    ORDER BY f.timemodified DESC";
                                                $file = $DB->get_record_sql($questionfallbacksql, $fallbackparams, IGNORE_MULTIPLE);
            }
    }

    if (!$file && $userid > 0 && $attempttime > 0) {
        $draftparams = [
            'userid' => $userid,
            'timestart' => max(0, $attempttime - 600),
            'timeend' => $attempttime + 600,
        ];
        $draftfilenamecondition = '';
        if ($filename !== '') {
            $draftfilenamecondition = ' AND f.filename = :filename';
            $draftparams['filename'] = $filename;
        } else {
            $draftfilenamecondition = " AND f.filename LIKE 'upfile_drawingboard_%'";
        }

        $draftsql = "SELECT f.contextid,
                            f.itemid,
                            f.filepath,
                            f.filename,
                            f.component,
                            f.filearea,
                            f.mimetype
                       FROM {files} f
                      WHERE f.component = 'user'
                        AND f.filearea = 'draft'
                        AND f.userid = :userid
                        AND f.filesize > 0
                        AND f.mimetype LIKE 'image/%'
                        AND f.timemodified >= :timestart
                        AND f.timemodified <= :timeend
                        $draftfilenamecondition
                   ORDER BY f.timemodified DESC";
        $file = $DB->get_record_sql($draftsql, $draftparams, IGNORE_MULTIPLE);
    }

    return $file ?: null;
}

/**
 * Locate a stored PoodLL sketch response image and return a pluginfile URL.
 *
 * @param string $filename
 * @param int $userid
 * @param int $cmid
 * @param int $questionid
 * @param int $attempttime
 * @return string
 */
function icontent_get_poodll_response_image_url(
    string $filename,
    int $userid,
    int $cmid,
    int $questionid = 0,
    int $attempttime = 0
): string {
    return icontent_get_poodll_response_media_url($filename, $userid, $cmid, $questionid, $attempttime, 'image/');
}

/**
 * Locate a stored PoodLL response media file and return a pluginfile URL or data URI.
 *
 * @param string $filename
 * @param int $userid
 * @param int $cmid
 * @param int $questionid
 * @param int $attempttime
 * @param string $mimetypeprefix Optional mimetype prefix filter (for example 'audio/' or 'video/').
 * @param string $filearea
 * @return string
 */
function icontent_get_poodll_response_media_url(
    string $filename,
    int $userid,
    int $cmid,
    int $questionid = 0,
    int $attempttime = 0,
    string $mimetypeprefix = '',
    string $filearea = 'response_answer'
): string {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');
    require_once($CFG->libdir . '/filestorage/file_storage.php');
    $filename = icontent_extract_poodll_response_media_filename($filename);

    $file = icontent_get_poodll_response_media_file_record(
        $filename,
        $userid,
        $cmid,
        $questionid,
        $attempttime,
        $mimetypeprefix,
        $filearea
    );

    if (!$file) {
        return '';
    }

    $mimetype = (string)$file->mimetype;
    if ($mimetype === '') {
        return '';
    }
    if ($mimetypeprefix !== '' && strpos($mimetype, $mimetypeprefix) !== 0) {
        return '';
    }

    $filestorage = get_file_storage();
    $storedfile = $filestorage->get_file(
        (int)$file->contextid,
        (string)$file->component,
        (string)$file->filearea,
        (int)$file->itemid,
        (string)$file->filepath,
        (string)$file->filename
    );

    if ($storedfile) {
        $content = $storedfile->get_content();
        if ($content !== false && $content !== '') {
            return 'data:' . $mimetype . ';base64,' . base64_encode($content);
        }
    }

    return moodle_url::make_pluginfile_url(
        (int)$file->contextid,
        (string)$file->component,
        (string)$file->filearea,
        (int)$file->itemid,
        (string)$file->filepath,
        (string)$file->filename
    )->out(false);
}

/**
 * Locate a stored RecordRTC response media file and return a pluginfile URL or data URI.
 *
 * @param string $filename
 * @param int $userid
 * @param int $cmid
 * @param int $questionid
 * @param int $attempttime
 * @param string $mimetypeprefix Optional mimetype prefix filter (for example 'audio/' or 'video/').
 * @return string
 */
function icontent_get_recordrtc_response_media_url(
    string $filename,
    int $userid,
    int $cmid,
    int $questionid = 0,
    int $attempttime = 0,
    string $mimetypeprefix = ''
): string {
    return icontent_get_poodll_response_media_url(
        $filename,
        $userid,
        $cmid,
        $questionid,
        $attempttime,
        $mimetypeprefix,
        'response_recording'
    );
}

/**
 * Get latest submitted PoodLL sketch answers by page for current user.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array
 */
function icontent_get_poodll_sketch_answers_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;

    if (!icontent_optional_qtype_table_exists('qtype_poodllrecording_opts')) {
        return [];
    }

    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return [];
    }

    $sql = "SELECT qa.id,
                   qa.userid,
                   qa.answertext,
                   q.name AS questionname,
                   q.qtype,
                   qpo.responseformat
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
                ON q.id = qa.questionid
         LEFT JOIN {qtype_poodllrecording_opts} qpo
                ON qpo.questionid = q.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?
               AND q.qtype = 'poodllrecording'
               AND qpo.responseformat = 'picture'
               AND qa.answertext IS NOT NULL
               AND qa.answertext <> ''
          ORDER BY qa.id ASC";

    return $DB->get_records_sql($sql, [$pageid, $cmid, $USER->id, $latestattempttime]);
}

/**
 * Get latest submitted answers by page for current user.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array
 */
function icontent_get_submitted_answers_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;

    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return [];
    }

        $haspoodlloptions = icontent_optional_qtype_table_exists('qtype_poodllrecording_opts');
        $hasrecordrtcoptions = icontent_optional_qtype_table_exists('qtype_recordrtc_options');

        $poodllselect = $haspoodlloptions ? 'qpo.responseformat' : "'' AS responseformat";
        $recordrtcselect = $hasrecordrtcoptions ? 'qro.mediatype' : "'' AS mediatype";
        $poodlljoin = $haspoodlloptions
            ? "\n         LEFT JOIN {qtype_poodllrecording_opts} qpo" .
                "\n                ON qpo.questionid = q.id"
            : '';
        $recordrtcjoin = $hasrecordrtcoptions
            ? "\n             LEFT JOIN {qtype_recordrtc_options} qro" .
                "\n                ON qro.questionid = q.id"
            : '';

        $sql = "SELECT qa.id,
                   qa.userid,
                   qa.answertext,
                   qa.fraction,
                   qa.questionid,
                   q.name AS questionname,
                   q.qtype,
                   q.generalfeedback,
                   q.generalfeedbackformat,
                   pq.maxmark,
               {$poodllselect},
               {$recordrtcselect}
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
                ON q.id = qa.questionid
        {$poodlljoin}
        {$recordrtcjoin}
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?
          ORDER BY qa.id ASC";

    return $DB->get_records_sql($sql, [$pageid, $cmid, $USER->id, $latestattempttime]);
}

/**
 * Get reviewer comments attached to the latest attempt summary on a page.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array
 */
function icontent_get_reviewer_comments_by_attempt_summary_by_page($pageid, $cmid) {
    global $DB, $USER;

    $latestattempttime = icontent_get_latest_attempt_timecreated_by_page($pageid, $cmid, $USER->id);
    if (empty($latestattempttime)) {
        return [];
    }

    $sql = "SELECT qa.id,
                   qa.questionid,
                   qa.reviewercomment,
                   qa.reviewercommentformat,
                   q.name AS questionname
              FROM {icontent_question_attempts} qa
        INNER JOIN {icontent_pages_questions} pq
                ON qa.pagesquestionsid = pq.id
        INNER JOIN {question} q
                ON q.id = qa.questionid
             WHERE pq.pageid = ?
               AND pq.cmid = ?
               AND qa.userid = ?
               AND qa.timecreated = ?
               AND qa.reviewercomment IS NOT NULL
               AND qa.reviewercomment <> ''
          ORDER BY qa.id ASC";

    return $DB->get_records_sql($sql, [$pageid, $cmid, $USER->id, $latestattempttime]);
}

/**
 * Get sum fraction by instance and userid.
 *
 * Returns sum fraction.
 *
 * @param int $cmid
 * @param int $userid
 * @return float $sumfraction
 */
function icontent_get_sumfraction_by_userid($cmid, $userid) {
    global $DB;
    $sql = "SELECT Sum(fraction) AS sumfraction FROM {icontent_question_attempts}  WHERE  userid = ? AND cmid = ?;";
    $grade = $DB->get_record_sql($sql, [$userid, $cmid]);
    return $grade->sumfraction;
}

/**
 * Get array of the options of answers. Pattern input e.g. array options with [qpid-9_answerid-5].
 *
 * Returns array of $arrayoptionsid.
 *
 * @param array $answers
 * @return array $arrayoptionsid[$answerid] = $questionpage
 */
function icontent_get_array_options_answerid($answers) {
    $arrayoptionsids = [];
    foreach ($answers as $optanswer) {
        [$qp, $answer] = explode('_', $optanswer);
        [$stranswer, $answerid] = explode('-', $answer);
        $arrayoptionsids[$answerid] = $qp;
    }
    return $arrayoptionsids;
}

/**
 * Add preview in page if its not previewed.
 *
 * Returns object of pagedisplayed.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $pagedisplayed
 */
function icontent_add_pagedisplayed($pageid, $cmid) {
    global $DB, $USER;
    $pagedisplayed = icontent_get_pagedisplayed($pageid, $cmid);
    if (empty($pagedisplayed)) {
        $pagedisplayed = new stdClass();
        $pagedisplayed->pageid = $pageid;
        $pagedisplayed->cmid = $cmid;
        $pagedisplayed->userid = $USER->id;
        $pagedisplayed->timecreated = time();
        return $DB->insert_record('icontent_pages_displayed', $pagedisplayed);
    }
    return $pagedisplayed;
}

/**
 * Adds questions on a page.
 *
 * Returns true or false.
 *
 * @param array $questions
 * @param int $pageid
 * @param int $cmid
 * @return boolean true or false
 */
function icontent_add_questionpage($questions, $pageid, $cmid) {
    global $DB;
    if (empty($questions)) {
        return false;
    }

    $selectedquestionids = array_unique(array_map('intval', $questions));
    $selectedquestionids = array_values(array_filter($selectedquestionids));
    if (empty($selectedquestionids)) {
        return false;
    }

    $questionmarks = $DB->get_records_list('question', 'id', $selectedquestionids, '', 'id, defaultmark');

    $existing = $DB->get_records_menu(
        'icontent_pages_questions',
        ['pageid' => $pageid, 'cmid' => $cmid],
        '',
        'id, questionid'
    );
    $existingquestionids = array_map('intval', array_values($existing));

    $timecreated = time();
    $records = [];
    foreach ($selectedquestionids as $questionid) {
        if (in_array($questionid, $existingquestionids, true)) {
            continue;
        }

        $record = new stdClass();
        $record->pageid = $pageid;
        $record->questionid = $questionid;
        $record->cmid = $cmid;
        $defaultmark = isset($questionmarks[$questionid]) ? (float)$questionmarks[$questionid]->defaultmark : 0.0;
        $record->maxmark = $defaultmark > 0 ? $defaultmark : 1;
        $record->timecreated = $timecreated;
        $records[] = $record;
    }

    if (!empty($records)) {
        $DB->insert_records('icontent_pages_questions', $records);
    }

    return true;
}

/**
 * Save route targets for question mappings on one page.
 *
 * @param int $pageid
 * @param int $cmid
 * @param array $correctroutes
 * @param array $incorrectroutes
 * @param array $manualreviewroutes
 * @param array $defaultroutes
 * @return bool
 */
function icontent_update_questionpage_routes(
    int $pageid,
    int $cmid,
    array $correctroutes,
    array $incorrectroutes,
    array $manualreviewroutes,
    array $defaultroutes
): bool {
    global $DB;

    $questionids = array_unique(array_merge(
        array_map('intval', array_keys($correctroutes)),
        array_map('intval', array_keys($incorrectroutes)),
        array_map('intval', array_keys($manualreviewroutes)),
        array_map('intval', array_keys($defaultroutes))
    ));
    $questionids = array_values(array_filter($questionids));
    if (empty($questionids)) {
        return false;
    }

    $validpageids = $DB->get_records_menu(
        'icontent_pages',
        ['cmid' => $cmid, 'hidden' => 0],
        '',
        'id, id'
    );

    [$questionin, $questionparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
    $params = ['pageid' => $pageid, 'cmid' => $cmid] + $questionparams;
    $sql = "SELECT id,
                   questionid,
                   correctnextpageid,
                   incorrectnextpageid,
                   manualreviewnextpageid,
                   defaultnextpageid
              FROM {icontent_pages_questions}
             WHERE pageid = :pageid
               AND cmid = :cmid
               AND questionid {$questionin}";
    $mappings = $DB->get_records_sql($sql, $params);
    if (empty($mappings)) {
        return false;
    }

    $sanitize = static function (array $routes, int $questionid) use ($validpageids): int {
        if (!array_key_exists($questionid, $routes)) {
            return 0;
        }

        $value = (int)$routes[$questionid];
        if ($value <= 0 || !array_key_exists($value, $validpageids)) {
            return 0;
        }

        return $value;
    };

    $updated = false;
    foreach ($mappings as $mapping) {
        $questionid = (int)$mapping->questionid;
        $newcorrect = $sanitize($correctroutes, $questionid);
        $newincorrect = $sanitize($incorrectroutes, $questionid);
        $newmanualreview = $sanitize($manualreviewroutes, $questionid);
        $newdefault = $sanitize($defaultroutes, $questionid);

        if (
            (int)$mapping->correctnextpageid === $newcorrect &&
            (int)$mapping->incorrectnextpageid === $newincorrect &&
            (int)$mapping->manualreviewnextpageid === $newmanualreview &&
            (int)$mapping->defaultnextpageid === $newdefault
        ) {
            continue;
        }

        $record = (object)[
            'id' => (int)$mapping->id,
            'correctnextpageid' => $newcorrect,
            'incorrectnextpageid' => $newincorrect,
            'manualreviewnextpageid' => $newmanualreview,
            'defaultnextpageid' => $newdefault,
        ];
        $DB->update_record('icontent_pages_questions', $record);
        $updated = true;
    }

    return $updated;
}

/**
 * Get page viewed.
 *
 * Returns string of pagedisplayed.
 *
 * @param int $pageid
 * @param int $cmid
 * @return object $pagedisplayed
 */
function icontent_get_pagedisplayed($pageid, $cmid) {
    global $DB, $USER;
    return $DB->get_record(
        'icontent_pages_displayed',
        [
            'pageid' => $pageid,
            'cmid' => $cmid,
            'userid' => $USER->id,
        ],
        'id,
        timecreated'
    );
}

/**
 * Get questions by pageid.
 *
 * Returns array of questions.
 *
 * @param int $pageid
 * @param int $cmid
 * @return array $questions
 */
function icontent_get_pagequestions($pageid, $cmid) {
    global $DB;
    $sql = 'SELECT pq.id AS qpid,
                   q.id  AS qid,
                   q.name,
                   pq.maxmark,
                   q.defaultmark,
                   q.questiontext,
                   q.questiontextformat,
                   q.qtype
              FROM {icontent_pages_questions} pq
        INNER JOIN {question} q
                ON pq.questionid = q.id
             WHERE pq.pageid = ?
               AND pq.cmid = ?;';
    return $DB->get_records_sql($sql, [$pageid, $cmid]);
}

/**
 * Get total of questions and subquestions by instance <iContent>.
 *
 * Returns total of questions by instance.
 *
 * @param int $cmid
 * @return int $tquestions
 */
function icontent_get_totalquestions_by_instance($cmid) {
    global $DB;
    // Get total subquestions.
    $sql = 'SELECT Count(*)
              FROM {qtype_match_subquestions} qms
        INNER JOIN {icontent_pages_questions} pq
                ON qms.questionid = pq.questionid
             WHERE pq.cmid = ?;';
    $tquest = $DB->count_records_sql($sql, [$cmid]);
    // Get total questions.
    $sql = 'SELECT Count(*)
              FROM {icontent_pages_questions} pq
        INNER JOIN {question} q
                ON pq.questionid = q.id
             WHERE q.qtype NOT IN (?)
               AND pq.cmid = ?;';
    $tsub = $DB->count_records_sql($sql, [ICONTENT_QTYPE_MATCH, $cmid]);
    return $tsub + $tquest;
}

/**
 * Get total maximum points by instance <iContent>.
 *
 * @param int $cmid
 * @return float
 */
function icontent_get_totalmaxfraction_by_instance($cmid) {
    global $DB;

    $sql = "SELECT Sum(COALESCE(NULLIF(pq.maxmark, 0), q.defaultmark, 1))
              FROM {icontent_pages_questions} pq
        INNER JOIN {question} q
                ON pq.questionid = q.id
             WHERE pq.cmid = ?";

    $maxfraction = $DB->get_field_sql($sql, [$cmid]);
    if ($maxfraction === false || $maxfraction === null) {
        return 0.0;
    }

    return (float)$maxfraction;
}

/**
 * Get pagenotes by pageid according to the user's capability logged.
 *
 * Returns array of pagenotes.
 *
 * @param int $pageid
 * @param int $cmid
 * @param string $tab
 * @return object $pagenotes
 */
function icontent_get_pagenotes($pageid, $cmid, $tab) {
    global $DB, $USER;
    if (icontent_has_permission_manager(context_module::instance($cmid))) {
        // If manager: see everything.
        return $DB->get_records('icontent_pages_notes', ['pageid' => $pageid, 'cmid' => $cmid, 'tab' => $tab], 'path');
    }
    // Non-manager: own notes + public non-doubttutor notes from others.
    $sql = 'SELECT *
              FROM {icontent_pages_notes}
             WHERE pageid = ?
               AND cmid = ?
               AND tab = ?
               AND (userid = ? OR (private = ? AND doubttutor = ?))
          ORDER BY path ASC;';
    $notes = $DB->get_records_sql($sql, [$pageid, $cmid, $tab, $USER->id, 0, 0]);
    // Also include tutor replies in threads started by this user's own doubttutor
    // questions, so the student can read the tutor's response to their private question.
    $ownrootids = $DB->get_fieldset_select(
        'icontent_pages_notes',
        'id',
        'pageid = ? AND cmid = ? AND tab = ? AND userid = ? AND parent = 0 AND doubttutor = 1',
        [$pageid, $cmid, $tab, $USER->id]
    );
    foreach ($ownrootids as $rootid) {
        $likelead = $DB->sql_like('path', ':pathlike');
        $sql2 = "SELECT *
                   FROM {icontent_pages_notes}
                  WHERE userid != :userid
                    AND (path = :pathexact OR $likelead)";
        $replies = $DB->get_records_sql($sql2, [
            'userid'    => $USER->id,
            'pathexact' => '/' . $rootid,
            'pathlike'  => '/' . $rootid . '/%',
        ]);
        foreach ($replies as $reply) {
            $notes[$reply->id] = $reply;
        }
    }
    if (!empty($ownrootids)) {
        uasort($notes, function ($a, $b) {
            return strcmp($a->path, $b->path);
        });
    }
    return $notes;
}

/**
 * Get likes of page.
 *
 * Returns object of  {icontent_pages_notes_like}.
 *
 * @param int $pagenoteid
 * @param int $userid
 * @param int $cmid
 * @return object $pagenotelike
 */
function icontent_get_pagenotelike($pagenoteid, $userid, $cmid) {
    global $DB;
    return $DB->get_record('icontent_pages_notes_like', ['pagenoteid' => $pagenoteid, 'userid' => $userid, 'cmid' => $cmid], 'id');
}

/**
 * Check if expandnotesarea or expandquestionsarea field are true or false and returns toggle object.
 *
 * Returns toggle area object.
 *
 * @param boolean $expandarea
 * @return object $attrtogglearea
 */
function icontent_get_toggle_area_object($expandarea) {
    $attrtogglearea = new stdClass();
    if (!$expandarea) {
        $attrtogglearea->icon = '<i class="fa fa-caret-right" aria-hidden="true"></i>&nbsp;';
        $attrtogglearea->style = "display: none;";
        $attrtogglearea->class = "closed";
        return $attrtogglearea;
    }
    $attrtogglearea->icon = '<i class="fa fa-caret-down" aria-hidden="true"></i>&nbsp;';
    $attrtogglearea->style = '';
    $attrtogglearea->class = '';
    return  $attrtogglearea;
}

/**
 * Get pages number interactive content <iContent>
 *
 * Returns pagenum.
 *
 * @param int $icontentid
 * @return int pagenum
 */
function icontent_count_pages($icontentid) {
    global $DB;
    return $DB->count_records('icontent_pages', ['icontentid' => $icontentid]);
}

/**
 * Get pages number viewed by user.
 *
 * Returns page viewed by user.
 *
 * @param int $userid
 * @param int $cmid
 * @return int $pageviewedbyuser
 */
function icontent_count_pageviewedbyuser($userid, $cmid) {
    global $DB;
    return $DB->count_records('icontent_pages_displayed', ['userid' => $userid, 'cmid' => $cmid]);
}

/**
 * Get count of likes a note {icontent_pages_notes_like}.
 *
 * Returns count.
 *
 * @param int $pagenoteid
 * @return int count
 */
function icontent_count_pagenotelike($pagenoteid) {
    global $DB;
    return $DB->count_records('icontent_pages_notes_like', ['pagenoteid' => $pagenoteid]);
}

/**
 * Get page number by pageid.
 *
 * Returns pagenum.
 *
 * @param int $pageid
 * @return int pagenum
 */
function icontent_get_pagenum_by_pageid($pageid) {
    global $DB;
    $sql = "SELECT pagenum  FROM {icontent_pages} WHERE id = ?;";
    $obj = $DB->get_record_sql($sql, [$pageid], MUST_EXIST);
    return $obj->pagenum;
}

/**
 * Get the level of depth this note.
 *
 * Returns levels.
 *
 * @param string $path
 * @return int $levels
 */
function icontent_get_noteparentinglevels($path) {
    $countpath = count(explode('/', $path)) - 1;
    if (!$countpath) {
        return 1;
    } else if ($countpath > 12) {
        return 12;
    } else {
        return $countpath;
    }
}

/**
 * Get user by ID.
 *
 * Returns object $user.
 *
 * @param int $userid
 * @return object $user
 */
function icontent_get_user_by_id($userid) {
    global $DB;
    return $DB->get_record(
        'user',
        ['id' => $userid],
        'id,
        firstname,
        lastname,
        email,
        picture,
        firstnamephonetic,
        lastnamephonetic,
        middlename,
        alternatename,
        imagealt'
    );
}

/**
 * Recursive function that gets notes daughters.
 *
 * Returns array $notesdaughters.
 *
 * @param int $pagenoteid
 * @return array $notesdaughters
 */
function icontent_get_notes_daughters($pagenoteid) {
    global $DB;
    $pagenotes = $DB->get_records('icontent_pages_notes', ['parent' => $pagenoteid]);
    if ($pagenotes) {
        $notesdaughters = [];
        foreach ($pagenotes as $pagenote) {
            $notesdaughters[$pagenote->id] = $pagenote->comment;
            $tree = icontent_get_notes_daughters($pagenote->id);
            if ($tree) {
                $notesdaughters = $notesdaughters + $tree;
            }
        }
        return $notesdaughters;
    }
    return $pagenotes;
}

/**
 * Check that the value of param is a valid SQL clause.
 *
 * Returns string ASC or DESC.
 *
 * @param string $sortsql
 * @return $sort ASC or DESC.
 */
function icontent_check_value_sort($sortsql) {
    $sortsql = strtolower($sortsql);
    switch ($sortsql) {
        case 'desc':
            return 'DESC';
            break;
        default:
            return "ASC";
    }
}

/**
 * Checks if exists answers the questions of current page.
 *
 * Returns array answerspage
 *
 * @param int $pageid
 * @param int $cmid
 * @return array $answerspage
 */
function icontent_checks_answers_of_currentpage($pageid, $cmid) {
    global $DB;
    $sql = "SELECT Count(qa.id)     AS totalanswers
            FROM   {icontent_question_attempts} qa
                   INNER JOIN {icontent_pages_questions} pq
                           ON qa.pagesquestionsid = pq.id
            WHERE  pq.pageid = ?
                   AND pq.cmid = ?;";
    $totalanswers = $DB->get_record_sql($sql, [$pageid, $cmid]);
    // Checks if a property isn't empty.
    if (!empty($totalanswers->totalanswers)) {
        return $totalanswers;
    }
    return false;
}

/**
 * Check if has permission for edition.
 *
 * @param boolean $allowedit
 * @param boolean $edit Received by parameter in the URL.
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_has_permission_edition($allowedit, $edit = 0) {
    global $USER;
    if ($allowedit) {
        if ($edit != -1 && confirm_sesskey()) {
            $USER->editing = $edit;
        } else {
            if (isset($USER->editing)) {
                $edit = $USER->editing;
            } else {
                $edit = 0;
            }
        }
    } else {
        $edit = 0;
    }
    return $edit;
}

// FUNCTIONS CAPABILITYES.
/**
 * Check if has permission of manager.
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_has_permission_manager($context) {
    if (has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
        return true;
    }
    return false;
}

/**
 * Check if the user is owner the note.
 * @param object $pagenote
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_check_user_isowner_note($pagenote) {
    global $USER;
    if ($USER->id === $pagenote->userid) {
        return true;
    }
    return false;
}

/**
 * Check if user can remove note.
 * @param object $pagenote
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_remove_note($pagenote, $context) {
    if (has_capability('mod/icontent:removenotes', $context)) {
        if (icontent_check_user_isowner_note($pagenote)) {
            return true;
        }
        if (icontent_has_permission_manager($context)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user can edit note.
 * @param object $pagenote
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_edit_note($pagenote, $context) {
    if (has_capability('mod/icontent:editnotes', $context)) {
        if (icontent_check_user_isowner_note($pagenote)) {
            return true;
        }
        if (icontent_has_permission_manager($context)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user can reply note.
 * @param object $pagenote
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_reply_note($pagenote, $context) {
    if (has_capability('mod/icontent:replynotes', $context)) {
        if (icontent_has_permission_manager($context)) {
            return true;
        }
        if ($pagenote->doubttutor) {
            return false;
        }
        return true;
    }
    return false;
}

/**
 * Check if user can like or do not like the note.
 * @param object $pagenote
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_likeunlike_note($pagenote, $context) {
    if (has_capability('mod/icontent:likenotes', $context)) {
        if (icontent_has_permission_manager($context)) {
            return true;
        }
        if ($pagenote->doubttutor) {
            return false;
        }
        return true;
    }
    return false;
}

/**
 * Check if user can view private field.
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_view_checkbox_field_private($context) {
    if (has_capability('mod/icontent:checkboxprivatenotes', $context)) {
        return true;
    }
    return false;
}

/**
 * Check if user can view featured field.
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_view_checkbox_field_featured($context) {
    if (has_capability('mod/icontent:checkboxfeaturednotes', $context)) {
        return true;
    }
    return false;
}

/**
 * Check if user can view doubttutor field.
 * @param string $context
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_view_checkbox_field_doubttutor($context) {
    if (has_capability('mod/icontent:checkboxdoubttutornotes', $context)) {
        return true;
    }
    return false;
}

/**
 * Check if user can remove attempts answers for try again.
 * @param int $pageid
 * @param int $cmid
 * @return boolean true if the user has this permission. Otherwise false.
 */
function icontent_user_can_remove_attempts_answers_for_tryagain($pageid, $cmid) {
    global $DB;
    // Get context.
    $context = context_module::instance($cmid);
    if (icontent_has_permission_manager($context)) {
        return true;
    }
    if (has_capability('mod/icontent:answerquestionstryagain', $context)) {
        // Get object page.
        $objpage = $DB->get_record('icontent_pages', ['id' => $pageid], 'id, pagenum, attemptsallowed', MUST_EXIST);
        if ((int)$objpage->attemptsallowed === 0) {
            return true;
        }
    }
    return false;
}

// FUNCTIONS CREATING AND RETURNS HTML.

 /**
  * Create button previous page.
  *
  * Returns button.
  *
  * @param object $button
  * @param int $tpages
  * @param string $icon
  * @return string with $btnprevious
  */
function icontent_make_button_previous_page($button, $tpages, $icon = null) {
    $objpage = new stdClass();
    $objpage->pagenum = $button->startwithpage;
    $objpage->cmid = $button->cmid;
    $objpage->id = (int)icontent_get_pageid_by_pagenum($button->cmid, $button->startwithpage);
    $objpage = icontent_get_navigation_page_context($objpage);
    if ((int)($objpage->prevmode ?? 0) === 1) {
        return '';
    }
    $pageprevious = icontent_get_prev_pagenum($objpage);
    $pagepreviousid = icontent_get_pageid_by_pagenum($button->cmid, $pageprevious);
    $attributes = [
        'title' => $button->title,
        'class' => 'load-page btn-previous-page btn btn-secondary mr-1',
        'data-toggle' => 'tooltip',
        'data-totalpages' => $tpages,
        'data-placement' => 'top',
        'data-pagenum' => $pageprevious,
        'data-pageid' => $pagepreviousid,
        'data-cmid' => $button->cmid,
        'data-sesskey' => sesskey(),
    ];
    $url = '#';
    if (!empty($pagepreviousid)) {
        $url = new moodle_url('/mod/icontent/view.php', ['id' => $button->cmid, 'pageid' => $pagepreviousid]);
    }
    if (!$pageprevious) {
        $attributes['disabled'] = 'disabled';
        $attributes['aria-disabled'] = 'true';
        $attributes['tabindex'] = '-1';
        $attributes['class'] .= ' disabled';
    }
    return html_writer::link($url, $icon . $button->name, $attributes);
}

/**
 * Create button next page.
 *
 * Returns button.
 *
 * @param object $button
 * @param int $tpages
 * @param string $icon
 * @return string with $btnnext
 */
function icontent_make_button_next_page($button, $tpages, $icon = null) {
    $objpage = new stdClass();
    $objpage->pagenum = $button->startwithpage;
    $objpage->cmid = $button->cmid;
    $objpage->id = (int)icontent_get_pageid_by_pagenum($button->cmid, $button->startwithpage);
    $objpage = icontent_get_navigation_page_context($objpage);
    if ((int)($objpage->nextmode ?? 0) === 1) {
        return '';
    }
    $nextpage = icontent_get_next_pagenum($objpage);
    $nextpageid = icontent_get_pageid_by_pagenum($button->cmid, $nextpage);
    $attributes = [
        'title' => $button->title,
        'class' => 'load-page btn-next-page btn btn-secondary',
        'data-toggle' => 'tooltip',
        'data-totalpages' => $tpages,
        'data-placement' => 'top',
        'data-pagenum' => $nextpage,
        'data-pageid' => $nextpageid,
        'data-cmid' => $button->cmid,
        'data-sesskey' => sesskey(),
    ];
    $url = '#';
    if (!empty($nextpageid)) {
        $url = new moodle_url('/mod/icontent/view.php', ['id' => $button->cmid, 'pageid' => $nextpageid]);
    }
    if (!$nextpage) {
        $attributes['disabled'] = 'disabled';
        $attributes['aria-disabled'] = 'true';
        $attributes['tabindex'] = '-1';
        $attributes['class'] .= ' disabled';
    }
    return html_writer::link($url, $button->name . $icon, $attributes);
}

/**
 * This is the function responsible for creating a list of answers to the notes that will be removed.
 *
 * Return list of answers.
 *
 * @param array $notesdaughters
 * @return string $listgroup
 */
function icontent_make_list_group_notesdaughters($notesdaughters) {
    if ($notesdaughters) {
        $listgroup = html_writer::start_tag('ul');
        $likes = '';
        foreach ($notesdaughters as $key => $note) {
            $likes = html_writer::span(icontent_count_pagenotelike($key), 'badge');
            $listgroup .= html_writer::tag('li', $note . $likes, ['class' => 'list-group-item']);
        }
        $listgroup .= html_writer::end_tag('ul');
        return $listgroup;
    }
    return false;
}

/**
 * This is the function responsible for creating a progress bar.
 *
 * Return progress bar.
 *
 * @param object $objpage
 * @param object $icontent
 * @param object $context
 * @return string $progressbar
 */
function icontent_make_progessbar($objpage, $icontent, $context) {
    if (!$icontent->progressbar) {
        return false;
    }
    global $USER;
    $npages = icontent_count_pages($icontent->id);
    if ($npages <= 0) {
        return false;
    }

    $npagesviewd = icontent_count_pageviewedbyuser($USER->id, $objpage->cmid);
    $percentage = (int)round(($npagesviewd * 100) / $npages);
    $percentage = max(0, min(100, $percentage));
    $percentlabel = get_string('labelprogressbar', 'icontent', $percentage);
    $progressbar = html_writer::div(
        html_writer::span($percentlabel, 'icontent-progress-label'),
        'progress-bar progress-bar-striped active',
        [
            'role' => 'progressbar',
            'aria-valuenow' => $percentage,
            'aria-valuemin' => '0',
            'aria-valuemax' => '100',
            'aria-label' => $percentlabel,
            'style' => "width: {$percentage}%;",
        ]
    );
    $progress = html_writer::div($progressbar, 'progress icontent-progress');
    return $progress;
}

/**
 * This is the function responsible for creating the area questions on pages.
 *
 * Returns questions area.
 *
 * @param object $objpage
 * @param object $icontent
 * @return string $questionsarea
 */
function icontent_make_questionsarea($objpage, $icontent) {
    $questions = icontent_get_pagequestions($objpage->id, $objpage->cmid);
    if (!$questions) {
        return false;
    }

    // Phase 1 wiring: bootstrap question engine usage for supported qtypes.
    icontent_question_engine_phase1_bootstrap_usage($objpage, $questions);

    if (icontent_get_attempt_summary_by_page($objpage->id, $objpage->cmid)) {
        return icontent_make_attempt_summary_by_page($objpage->id, $objpage->cmid);
    }
    // Add the triangle that toggles the list of questions visible and not visible.
    $togglearea = icontent_get_toggle_area_object($objpage->expandquestionsarea);
    // Title in h4 style for the questions part of the page.
    $title = html_writer::tag(
        'h4',
        $togglearea->icon . get_string('answerthequestions', 'mod_icontent'),
        [
            'class' => 'titlequestions text-uppercase ' . $togglearea->class,
            'id' => 'idtitlequestionsarea',
        ]
    );
    $qlist = '';
    $questionnumber = 1;
    foreach ($questions as $question) {
        // Assemble the listing of all the questions on a slide/page.
        $qlist .= icontent_make_questions_answers_by_type($question, $objpage, $questionnumber);
        $questionnumber++;
    }
    // Hidden form fields.
    $hiddenfields = html_writer::empty_tag(
        'input',
        [
            'type' => 'hidden',
            'name' => 'id',
            'value' => $objpage->cmid,
            'id' => 'idhfieldcmid',
        ]
    );
    $hiddenfields .= html_writer::empty_tag(
        'input',
        [
            'type' => 'hidden',
            'name' => 'pageid',
            'value' => $objpage->id,
            'id' => 'idhfieldpageid',
        ]
    );
    $hiddenfields .= html_writer::empty_tag(
        'input',
        [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey(),
            'id' => 'idhfieldsesskey',
        ]
    );
    // Button send questions.
    $qbtnsend = html_writer::empty_tag(
        'input',
        [
            'type' => 'submit',
            'name' => 'qbtnsend',
            'class' => 'btn-sendanswers btn-primary pull-right',
            'value' => get_string('sendanswers', 'mod_icontent'),
        ]
    );
    $coldivbtnsend = html_writer::div($qbtnsend, 'col align-self-end');
    $divbtnsend = html_writer::div($coldivbtnsend, 'row sendanswers mb-2');
    // Tag form.
    $qform = html_writer::tag(
        'form',
        $hiddenfields . $qlist . $divbtnsend,
        [
            'action' => '',
            'method' => 'POST',
            'id' => 'idformquestions',
        ]
    );
    $divcontent = html_writer::div(
        $qform,
        'contentquestionsarea',
        [
            'id' => 'idcontentquestionsarea',
            'style' => $togglearea->style,
        ]
    );
    return html_writer::div($title . $divcontent, 'questionsarea', ['id' => 'idquestionsarea']);
}

/**
 * Build per-question tools (e.g. remove) for edit mode on view page.
 *
 * @param object $question
 * @param object|null $objpage
 * @return string
 */
function icontent_make_question_tools($question, $objpage = null) {
    global $USER;

    if (empty($objpage) || empty($question->qpid)) {
        return '';
    }

    if (!property_exists($USER, 'editing') || empty($USER->editing)) {
        return '';
    }

    $context = context_module::instance((int)$objpage->cmid);
    if (!has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
        return '';
    }

    if (icontent_checks_answers_of_currentpage((int)$objpage->id, (int)$objpage->cmid)) {
        return '';
    }

    $removeurl = new moodle_url('/mod/icontent/view.php', [
        'id' => (int)$objpage->cmid,
        'pageid' => (int)$objpage->id,
        'removeqpid' => (int)$question->qpid,
        'sesskey' => sesskey(),
    ]);
    $confirmmessage = get_string('confirmremovequestion', 'mod_icontent');
    if (preg_match('/^\[\[.*\]\]$/', $confirmmessage)) {
        $confirmmessage = 'Are you sure you want to remove this question from the page?';
    }

    $removeicon = html_writer::link(
        $removeurl,
        '<i class="fa fa-times-circle fa-lg"></i>',
        [
            'title' => s(get_string('remove', 'mod_icontent')),
            'class' => 'icon icon-removequestion',
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
            'onclick' => 'return confirm(' . json_encode($confirmmessage) . ');',
        ]
    );

    return html_writer::div($removeicon, 'question-tools text-end mb-2');
}

/**
 * This is the function responsible for creating the answers of questions area.
 *
 * Patterns for field names and values of question types:
 * Multichoice name = qpid-QPID_qid-QID_QTYPE or qpid-QPID_qid-QID_QTYPE[];
 * Multichoice value = qpid-QPID_answerid-ID;
 * Match name = qpid-QPID_qid-QID_QTYPE-ID;
 * Truefalse name = qpid-QPID_qid-QID_QTYPE;
 * Essay name = qpid-QPID_qid-QID_QTYPE.
 *
 * Important: Items in capital letters must be replaced by variables.
 *
 * Returns fields and answers by type.
 *
 * @param object $question
 * @param object|null $objpage
 * @param int $displaynumber
 * @return string $answers
 */
function icontent_make_questions_answers_by_type($question, $objpage = null, $displaynumber = 1) {
    global $DB, $CFG;

    $legacysupportedqtypes = [
        ICONTENT_QTYPE_MULTICHOICE,
        ICONTENT_QTYPE_MATCH,
        ICONTENT_QTYPE_TRUEFALSE,
        ICONTENT_QTYPE_ESSAY,
    ];

    if (!empty($objpage)) {
        $qenginehtml = icontent_question_engine_phase2_render_question($objpage, $question, $displaynumber);
        if ($qenginehtml !== false) {
            return $qenginehtml;
        }

        if (!icontent_question_engine_allow_legacy_render_fallback()) {
            return icontent_question_engine_render_issue(
                $question,
                $objpage,
                'This question could not be rendered by question engine, and legacy fallback rendering is disabled.'
            );
        }

        if (!in_array((string)($question->qtype ?? ''), $legacysupportedqtypes, true)) {
            return icontent_question_engine_render_issue(
                $question,
                $objpage,
                'This question could not be rendered by question engine, and this qtype has no legacy renderer in iContent.'
            );
        }
    }

    $questiontools = icontent_make_question_tools($question, $objpage);

    switch ($question->qtype) {
        case ICONTENT_QTYPE_MULTICHOICE:
            $answers = $DB->get_records('question_answers', ['question' => $question->qid]);
            shuffle($answers); // 20240718 Trying to shuffle the answers for multichoice question. Appears to work!
            $totalrightanswers = $DB->count_records_select(
                'question_answers',
                'question = ? AND fraction > ?',
                [
                    $question->qid,
                    0,
                ],
                'COUNT(fraction)'
            );
            // Print out the prompts. If there is more than one correct answer use the if, Choice {$a} options:,
            // and if there is only one answer use the else, Choice a:.
            if ($totalrightanswers > 1) {
                $type = 'checkbox';
                $brackets = '[]';
                $strprompt = get_string('choiceoneormore', 'mod_icontent', $totalrightanswers);
            } else {
                $type = 'radio';
                $brackets = '';
                $strprompt = get_string('choiceone', 'mod_icontent');
            }
            $strpromptinfo = html_writer::span($strprompt, 'label label-info');
            $questionanswers = html_writer::start_div('question ' . ICONTENT_QTYPE_MULTICHOICE);
            $questionanswers .= $questiontools;
            $questionanswers .= html_writer::div(strip_tags($question->questiontext, '<b><strong>'), 'questiontext');
            $questionanswers .= html_writer::div($strpromptinfo, 'prompt');
            $questionanswers .= html_writer::start_div('optionslist'); // Start div options list.
            foreach ($answers as $anwswer) {
                $fieldname = 'qpid-' . $question->qpid . '_qid-' . $question->qid . '_' . ICONTENT_QTYPE_MULTICHOICE . $brackets;
                $value = 'qpid-' . $question->qpid . '_answerid-' . $anwswer->id;
                $fieldid = 'idfield-qpid:' . $question->qpid . '_answerid:' . $anwswer->id;
                $check = html_writer::empty_tag(
                    'input',
                    [
                        'id' => $fieldid,
                        'name' => $fieldname,
                        'type' => $type,
                        'value' => $value,
                        'class' => 'mr-2',
                    ]
                );
                $label = html_writer::label(strip_tags($anwswer->answer), $fieldid);
                $questionanswers .= html_writer::div($check . $label);
            }
            $questionanswers .= html_writer::end_div(); // End div options list.
            $questionanswers .= html_writer::end_div();
            return $questionanswers;
            break;
        case ICONTENT_QTYPE_MATCH:
            $options = $DB->get_records('qtype_match_subquestions', ['questionid' => $question->qid], 'answertext');
            $questionanswers = html_writer::start_div('question ' . ICONTENT_QTYPE_MATCH);
            $questionanswers .= $questiontools;
            $questionanswers .= html_writer::div(strip_tags($question->questiontext, '<b><strong>'), 'questiontext mr-2');
            $questionanswers .= html_writer::start_div('optionslist'); // Start div options list.
            $contenttable = '';
            $arrayanswers = [];
            $rows = array_values($options);
            $answers = array_values($options);
            shuffle($rows);
            shuffle($answers);
            foreach ($answers as $option) {
                $optanswertext = trim(strip_tags($option->answertext));
                $arrayanswers[$optanswertext] = $optanswertext;
            }
            foreach ($rows as $option) {
                $fieldname = 'qpid-' . $question->qpid . '_qid-' . $question->qid . '_' . ICONTENT_QTYPE_MATCH . '-' . $option->id;
                $qtext = html_writer::tag('td', strip_tags($option->questiontext), ['class' => 'matchoptions']);
                $answertext = html_writer::tag(
                    'td',
                    html_writer::select(
                        $arrayanswers,
                        $fieldname,
                        null,
                        [
                            '' => 'choosedots',
                        ],
                        [
                            'class' => 'match-select',
                            'required' => 'required',
                        ]
                    ),
                    ['class' => 'matchanswer']
                );
                $contenttable .= html_writer::tag('tr', $qtext . $answertext);
            }
            $questionanswers .= html_writer::tag('table', $contenttable, ['class' => 'match-table']);
            $questionanswers .= html_writer::end_div(); // End div options list.
            $questionanswers .= html_writer::end_div();
            return $questionanswers;
            break;
        case ICONTENT_QTYPE_TRUEFALSE:
            $answers = $DB->get_records('question_answers', ['question' => $question->qid]);
            shuffle($answers); // 20240718 Trying to shuffle the answers for true/false question. Appears to work!
            $strpromptinfo = html_writer::span(get_string('choiceoneoption', 'mod_icontent'), 'label label-info' . 'test3');
            $questionanswers = html_writer::start_div('question ' . ICONTENT_QTYPE_TRUEFALSE);
            $questionanswers .= $questiontools;
            $questionanswers .= html_writer::div(strip_tags($question->questiontext, '<b><strong>'), 'questiontext');
            $questionanswers .= html_writer::div($strpromptinfo, 'prompt');
            $questionanswers .= html_writer::start_div('optionslist'); // Start div options list.
            foreach ($answers as $anwswer) {
                $fieldname = 'qpid-' . $question->qpid . '_qid-' . $question->qid . '_' . ICONTENT_QTYPE_TRUEFALSE;
                $value = 'qpid-' . $question->qpid . '_answerid-' . $anwswer->id;
                $fieldid = 'idfield-qpid:' . $question->qpid . '_answerid:' . $anwswer->id;
                $radio = html_writer::empty_tag(
                    'input',
                    [
                        'id' => $fieldid,
                        'name' => $fieldname,
                        'type' => 'radio',
                        'value' => $value,
                        'class' => 'mr-2',
                    ]
                );
                $label = html_writer::label(strip_tags($anwswer->answer), $fieldid);
                $questionanswers .= html_writer::div($radio . $label, 'options');
            }
            $questionanswers .= html_writer::end_div(); // End div options list.
            $questionanswers .= html_writer::end_div();
            return $questionanswers;
            break;
        case ICONTENT_QTYPE_ESSAY:
            $fieldname = 'qpid-' . $question->qpid . '_qid-' . $question->qid . '_' . ICONTENT_QTYPE_ESSAY;
            $fieldid = 'idfield-qpid:' . $question->qpid . '_qid:' . $question->qid . '_' . ICONTENT_QTYPE_ESSAY;
            $context = null;
            if (!empty($objpage) && !empty($objpage->cmid)) {
                $context = context_module::instance((int)$objpage->cmid);
            }
            $preferredformat = $context ? editors_get_preferred_format($context) : FORMAT_HTML;
            $preferrededitor = editors_get_preferred_editor($preferredformat);
            $questionanswers = html_writer::start_div('question essay');
            $questionanswers .= $questiontools;
            $questionanswers .= html_writer::div(strip_tags($question->questiontext, '<b><strong>'), 'questiontext');
            // 20240204 Modified params. See ticket iContent_1188.
            $questionanswers .= html_writer::tag(
                'textarea',
                null,
                [
                    'name' => $fieldname,
                    'id' => $fieldid,
                    'class' => 'col-12 answertextarea',
                    'required' => 'required',
                    'placeholder' => get_string('writeessay', 'mod_icontent'),
                ]
            );
            if ($preferrededitor) {
                $preferrededitor->use_editor($fieldid, [
                    'context' => $context,
                    'autosave' => false,
                ]);
            }
            $questionanswers .= html_writer::end_div();
            return $questionanswers;
            break;
        default:
            if (!empty($objpage)) {
                return icontent_question_engine_render_issue(
                    $question,
                    $objpage,
                    'This question type has no legacy renderer in iContent.'
                );
            }
            return false;
    }
}

/**
 * This is the function responsible for creating the attempt summary the current page.
 *
 * Returns attempt summary.
 *
 * @param int $pageid
 * @param int $cmid
 * @return string $attemptsummary
 */
function icontent_make_attempt_summary_by_page($pageid, $cmid) {
    global $DB;
    // Get objects that create summary attempt.
    $summaryattempt = icontent_get_attempt_summary_by_page($pageid, $cmid);
    $rightanswer = icontent_get_right_answers_by_attempt_summary_by_page($pageid, $cmid); // Items with hits.
    $openanswer = icontent_get_open_answers_by_attempt_summary_by_page($pageid, $cmid);
    $allownewattempts = icontent_user_can_remove_attempts_answers_for_tryagain($pageid, $cmid);
    // Check capabilities for new attempts.
    $straction = null;
    $iconrepeatattempt = null;
    if ($allownewattempts) {
        $straction = get_string('action', 'mod_icontent');
        // Icon repeat attempt.
        $iconrepeatattempt = html_writer::link(
            new moodle_url(
                'deleteattempt.php',
                [
                    'id' => $cmid,
                    'pageid' => $pageid,
                    'sesskey' => sesskey(),
                ]
            ),
            '<i class="fa fa-repeat fa-lg"></i>',
            [
                'title' => get_string('tryagain', 'mod_icontent'),
                'data-toggle' => 'tooltip',
                'data-placement' => 'top',
            ]
        );
    }
    $expandarea = $DB->get_field('icontent_pages', 'expandquestionsarea', ['id' => $pageid]);
    $togglearea = icontent_get_toggle_area_object($expandarea);
    // Create title.
    $title = html_writer::tag(
        'h4',
        $togglearea->icon . get_string('resultlastattempt', 'mod_icontent'),
        [
            'class' => 'titlequestions text-uppercase ' . $togglearea->class,
            'id' => 'idtitlequestionsarea',
        ]
    );
    // Create table.
    $summarygrid = new html_table();
    $summarygrid->id = "idcontentquestionsarea";
    $summarygrid->attributes = [
        'class' => 'table table-hover contentquestionsarea icontentattemptsummary',
        'style' => $togglearea->style,
    ];
    $summarygrid->head = [
        get_string('state', 'mod_icontent'),
        get_string('answers', 'mod_icontent'),
        get_string('rightanswers', 'mod_icontent'),
        get_string('result', 'mod_icontent'),
        $straction,
    ];
    $state = get_string('strstate', 'mod_icontent', userdate($summaryattempt->timecreated));
    $totalanswers = $summaryattempt->totalanswers;
    $totalrightanswers = (float)($rightanswer->totalrightanswers ?? 0);
    $equivalentrightanswers = (float)($rightanswer->equivalentrightanswers ?? 0);
    $rightanswersdisplay = (string)(int)$totalrightanswers;
    $stropenanswer = $openanswer->totalopenanswers ?
        get_string('stropenanswer', 'mod_icontent', $openanswer->totalopenanswers) : '';
    if (!empty($openanswer->totalopenanswers) && $equivalentrightanswers <= 0 && $totalrightanswers <= 0) {
        $rightanswersdisplay = get_string('pendingreview', 'mod_icontent');
    } else if ((int)$totalanswers > 1) {
        if ($equivalentrightanswers < 0) {
            $equivalentrightanswers = 0;
        }
        if ($equivalentrightanswers > (float)$totalanswers) {
            $equivalentrightanswers = (float)$totalanswers;
        }
        $rightanswersdisplay = number_format($equivalentrightanswers, 2) . ' / ' . (int)$totalanswers;
    }
    // String.
    $evaluate = new stdClass();
    $maxfraction = (float)($summaryattempt->maxfraction ?? 0);
    if ($maxfraction <= 0) {
        $maxfraction = (float)$summaryattempt->totalanswers;
    }
    $evaluate->fraction = number_format($summaryattempt->sumfraction, 2);
    $evaluate->maxfraction = number_format($maxfraction, 2);
    $evaluate->percentage = $maxfraction > 0 ? round(($summaryattempt->sumfraction * 100) / $maxfraction) : 0;
    $evaluate->openanswer = $stropenanswer;
    $strevaluate = get_string('strtoevaluate', 'mod_icontent', $evaluate);
    // Set data.
    $summarygrid->data[] = [$state, $totalanswers, $rightanswersdisplay, $strevaluate, $iconrepeatattempt];

    // Create table summary attempt.
    $tablesummary = html_writer::table($summarygrid);
    $answershtml = '';
    $submittedanswers = icontent_get_submitted_answers_by_attempt_summary_by_page($pageid, $cmid);
    if (!empty($submittedanswers)) {
        $answeritems = [];
        foreach ($submittedanswers as $submittedanswer) {
            $questionlabel = html_writer::tag('strong', format_string($submittedanswer->questionname) . ': ');
            $answercontent = icontent_render_manual_review_answer($submittedanswer, $cmid);
            $feedbackcontent = icontent_render_attempt_feedback($submittedanswer);
            $answeritems[] = html_writer::tag('li', $questionlabel . $answercontent . $feedbackcontent, ['class' => 'mb-3']);
        }

        $answershtml = html_writer::div(
            html_writer::tag('h5', get_string('answers', 'mod_icontent')) .
            html_writer::tag('ul', implode('', $answeritems), ['class' => 'list-unstyled mb-0']),
            'icontent-submitted-answers mt-2'
        );
    }

    $commentshtml = '';
    $reviewercomments = icontent_get_reviewer_comments_by_attempt_summary_by_page($pageid, $cmid);
    if (!empty($reviewercomments)) {
        $commentstitle = get_string('comments', 'mod_icontent');
        if (get_string_manager()->string_exists('reviewercomments', 'mod_icontent')) {
            $commentstitle = get_string('reviewercomments', 'mod_icontent');
        }

        $items = [];
        foreach ($reviewercomments as $comment) {
            $questionlabel = html_writer::tag('strong', format_string($comment->questionname) . ': ');
            $commenttext = format_text((string)$comment->reviewercomment, (int)$comment->reviewercommentformat, [
                'noclean' => false,
                'para' => false,
            ]);
            $items[] = html_writer::tag('li', $questionlabel . $commenttext, ['class' => 'mb-3']);
        }

        $commentshtml = html_writer::div(
            html_writer::tag('h5', $commentstitle) .
            html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled mb-0']),
            'icontent-reviewer-comments mt-2'
        );
    }

    return html_writer::div($title . $tablesummary . $answershtml . $commentshtml, 'questionsarea', ['id' => 'idquestionsarea']);
}

/**
 * This is the function responsible for creating the area comments on pages.
 *
 * Returns notes area.
 *
 * @param object $objpage
 * @param object $icontent
 * @return string $notesarea
 */
function icontent_make_notesarea($objpage, $icontent) {
    if (!$icontent->shownotesarea) {
        return false;
    }
    $context = context_module::instance($objpage->cmid);
    if (!has_capability('mod/icontent:viewnotes', $context)) {
        return false;
    }
    global $OUTPUT, $USER;
    $togglearea = icontent_get_toggle_area_object($objpage->expandnotesarea);

    // Title page.
    $title = html_writer::tag(
        'h4',
        $togglearea->icon . get_string('doubtandnotes', 'mod_icontent'),
        [
            'class' => 'titlenotes text-uppercase ' . $togglearea->class,
            'id' => 'idtitlenotes',
        ]
    );

    // User image used under the Notes and Question tabs.
    $picture = html_writer::tag(
        'div',
        $OUTPUT->user_picture(
            $USER,
            [
            'size' => 120,
            'class' => 'img-thumbnail',
            ]
        ),
        [
            'class' => 'col-2 userpicture',
        ]
    );

    // Fields. Create text area for notes.
    $textareanote = html_writer::tag(
        'textarea',
        null,
        [
            'name' => 'comment',
            'id' => 'idcommentnote',
            'class' => 'col-12',
            'maxlength' => '1024',
            'required' => 'required',
            'placeholder' => get_string('writenotes', 'mod_icontent'),
        ]
    );

    // Create checkboxes for private and featured, right under the notes textarea.
    $spanprivate = icontent_make_span_checkbox_field_private($objpage);
    $spanfeatured = icontent_make_span_checkbox_field_featured($objpage);

    // Create the, Save, button under the right side of the note textarea.
    $btnsavenote = html_writer::tag(
        'button',
        get_string('save', 'mod_icontent'),
        [
            'class' => 'btn btn-primary pull-right',
            'id' => 'idbtnsavenote',
            'data-pageid' => $objpage->id,
            'data-cmid' => $objpage->cmid,
            'data-sesskey' => sesskey(),
        ]
    );

    // Create text area for questions.
    $textareadoubt = html_writer::tag(
        'textarea',
        null,
        [
            'name' => 'comment',
            'id' => 'idcommentdoubt',
            'class' => 'col-12',
            'maxlength' => '1024',
            'required' => 'required',
            'placeholder' => get_string('writedoubt', 'mod_icontent'),
        ]
    );
    // Create check box for, Ask tutor only.
    $spandoubttutor = icontent_make_span_checkbox_field_doubttutor($objpage);
    // Create the question save button.
    $btnsavedoubt = html_writer::tag(
        'button',
        get_string('save', 'mod_icontent'),
        [
            'class' => 'btn btn-primary pull-right',
            'id' => 'idbtnsavedoubt',
            'data-pageid' => $objpage->id,
            'data-cmid' => $objpage->cmid,
            'data-sesskey' => sesskey(),
        ]
    );

    // Create text area for tags.
    $textareatag = html_writer::tag(
        'textarea',
        null,
        [
            'name' => 'comment',
            'id' => 'idcommenttag',
            'class' => 'col-12',
            'maxlength' => '1024',
            'required' => 'required',
            'placeholder' => get_string('writetag', 'mod_icontent'),
        ]
    );

    // Ask tutor only is intentionally hidden from this form.
    // Create the question save button.
    // Tag save button is intentionally disabled.

    // Data page.
    $datapagenotesnote = icontent_get_pagenotes($objpage->id, $objpage->cmid, 'note'); // Data page notes note.
    $datapagenotesdoubt = icontent_get_pagenotes($objpage->id, $objpage->cmid, 'doubt'); // Data page notes question.
    // Tag notes are intentionally disabled.
    $pagenotesnote = html_writer::div(
        icontent_make_listnotespage($datapagenotesnote, $icontent, $objpage),
        'pagenotesnote',
        [
            'id' => 'idpagenotesnote',
        ]
    );
    $pagenotesdoubt = html_writer::div(
        icontent_make_listnotespage($datapagenotesdoubt, $icontent, $objpage),
        'pagenotesdoubt',
        [
            'id' => 'idpagenotesdoubt',
        ]
    );

    // Fields.
    $fieldsnote = html_writer::tag(
        'div',
        $textareanote . $spanprivate . $spanfeatured . $btnsavenote . $pagenotesnote,
        [
            'class' => 'col-10',
        ]
    );
    $fieldsdoubt = html_writer::tag(
        'div',
        $textareadoubt . $spandoubttutor . $btnsavedoubt . $pagenotesdoubt,
        [
            'class' => 'col-10',
        ]
    );
    // Tag field block is intentionally disabled.

    // Forms.
    $formnote = html_writer::tag('div', $picture . $fieldsnote, ['class' => 'row fields mt-2']);
    $formdoubt = html_writer::tag('div', $picture . $fieldsdoubt, ['class' => 'row fields mt-2']);
    // Tag form is intentionally disabled.

    // TAB NAVS.
    $note = html_writer::tag(
        'li',
        html_writer::link(
            '#note',
            get_string('note', 'icontent', count($datapagenotesnote)),
            [
                'id' => 'note-tab',
                'aria-controls' => 'note',
                'role' => 'tab',
                'data-bs-toggle' => 'tab',
                'class' => 'nav-link active',
            ]
        ),
        [
            'class' => 'nav-item',
            'role' => 'presentation',
        ]
    );
    $doubt = html_writer::tag(
        'li',
        html_writer::link(
            '#doubt',
            get_string('doubt', 'icontent', count($datapagenotesdoubt)),
            [
                'id' => 'doubt-tab',
                'aria-controls' => 'doubt',
                'role' => 'tab',
                'data-bs-toggle' => 'tab',
                'class' => 'nav-link',
            ]
        ),
        [
            'class' => 'nav-item',
            'role' => 'presentation',
        ]
    );
    // Tag tab is intentionally disabled.
    $tabnav = html_writer::tag('ul', $note . $doubt, ['class' => 'nav nav-tabs', 'id' => 'tabnav']);
    // Tag tab navigation is intentionally disabled.
    // TAB CONTENT.
    $icontentnote = html_writer::div($formnote, 'tab-pane active', ['role' => 'tabpanel', 'id' => 'note']);
    $icontentdoubt = html_writer::div($formdoubt, 'tab-pane', ['role' => 'tabpanel', 'id' => 'doubt']);
    // Tag tab content is intentionally disabled.
    $tabicontent = html_writer::div($icontentnote . $icontentdoubt, 'tab-content', ['id' => 'idtabicontent']);
    $fulltab = html_writer::div($tabnav . $tabicontent, 'fulltab', ['id' => 'idfulltab', 'style' => $togglearea->style]);
    // Return notes area.
    return html_writer::tag('div', $title . $fulltab, ['class' => 'notesarea', 'id' => 'idnotesarea']);
}

/**
 * This is the function responsible for creating checkbox field private.
 *
 * Returns span with checkbox field.
 *
 * @param string $page
 * @return string $spancheckbox
 */
function icontent_make_span_checkbox_field_private($page) {
    $context = context_module::instance($page->cmid);
    if (icontent_user_can_view_checkbox_field_private($context)) {
        $checkprivate = html_writer::tag(
            'input',
            null,
            [
                'name' => 'private',
                'type' => 'checkbox',
                'id' => 'idprivate',
                'class' => 'icontent-checkbox mr-2',
            ]
        );
        $labelprivate = html_writer::tag(
            'label',
            get_string('private', 'mod_icontent'),
            [
                'for' => 'idprivate',
                'class' => 'icontent-label',
            ]
        );
        // Return span.
        return html_writer::tag('span', $checkprivate . $labelprivate, ['class' => 'fieldprivate font-weight-light']);
    }
    return false;
}

/**
 * This is the function responsible for creating checkbox field featured.
 *
 * Returns span with checkbox featured.
 *
 * @param string $page
 * @return string $spancheckbox
 */
function icontent_make_span_checkbox_field_featured($page) {
    $context = context_module::instance($page->cmid);
    if (icontent_user_can_view_checkbox_field_featured($context)) {
        $checkfeatured = html_writer::tag(
            'input',
            null,
            [
                'name' => 'featured',
                'type' => 'checkbox',
                'id' => 'idfeatured',
                'class' => 'icontent-checkbox mr-2',
            ]
        );
        $labelfeatured = html_writer::tag(
            'label',
            get_string('featured', 'mod_icontent'),
            [
                'for' => 'idfeatured',
                'class' => 'icontent-label',
            ]
        );
        // Return span.
        return html_writer::tag('span', $checkfeatured . $labelfeatured, ['class' => 'fieldfeatured font-weight-light']);
    }
    return false;
}

/**
 * This is the function responsible for creating checkbox field doubttutor.
 *
 * Returns span with checkbox doubttutor
 * @param object $page
 * @return string $spancheckbox
 */
function icontent_make_span_checkbox_field_doubttutor($page) {
    $context = context_module::instance($page->cmid);
    if (icontent_user_can_view_checkbox_field_doubttutor($context)) {
        $checkdoubttutor = html_writer::tag(
            'input',
            null,
            [
                'name' => 'doubttutor',
                'type' => 'checkbox',
                'id' => 'iddoubttutor',
                'class' => 'icontent-checkbox mr-2',
            ]
        );
        $labeldoubttutor = html_writer::tag(
            'label',
            get_string('doubttutor', 'mod_icontent'),
            [
                'for' => 'iddoubttutor',
                'class' => 'icontent-label',
            ]
        );
        // Return span.
        return html_writer::tag('span', $checkdoubttutor . $labeldoubttutor, ['class' => 'fielddoubttutor font-weight-light']);
    }
    return false;
}

/**
 * This is the function responsible for creating notes list by page.
 *
 * Returns notes list
 *
 * @param object $pagenotes
 * @param object $icontent
 * @param object $page
 * @return string $listnotes
 */
function icontent_make_listnotespage($pagenotes, $icontent, $page) {
    global $OUTPUT;
    if (!empty($pagenotes)) {
        $divnote = '';
        $context = context_module::instance($page->cmid);
        foreach ($pagenotes as $pagenote) {
            // Object user.
            $user = icontent_get_user_by_id($pagenote->userid);
            // Get picture for use with the note listing.
            $picture = $OUTPUT->user_picture($user, ['size' => 35, 'class' => 'img-thumbnail pull-left']);
            // Note header comprised of the user first name and the title of the slide.
            $linkfirstname = html_writer::link(
                new moodle_url(
                    '/user/view.php',
                    [
                    'id' => $user->id,
                    'course' => $icontent->course,
                    ]
                ),
                $user->firstname . ' ' . $user->lastname,
                [
                    'title' => $user->firstname,
                ]
            );
            $noteon = html_writer::tag('em', get_string('notedon', 'icontent'), ['class' => 'noteon mr-2 ml-2']);
            // Reply header.
            $replyon = html_writer::tag(
                'em',
                ' ' . strtolower(trim(get_string('respond', 'icontent'))) . ': ',
                [
                    'class' => 'noteon mr-2 ml-2',
                ]
            );
            $notepagetitle = html_writer::span($page->title, 'notepagetitle');
            $noteheader = $pagenote->parent ? html_writer::div($linkfirstname . $replyon, 'noteheader') :
                html_writer::div($linkfirstname . $noteon . $notepagetitle, 'noteheader');
            if ((string)$pagenote->tab === 'note') {
                $noteflags = '';
                if (!empty($pagenote->private)) {
                    $noteflags .= html_writer::span(get_string('private', 'mod_icontent'), 'badge badge-secondary');
                }
                if (!empty($pagenote->featured)) {
                    $noteflags .= html_writer::span(get_string('featured', 'mod_icontent'), 'badge badge-info');
                }
                if (empty($pagenote->private) && empty($pagenote->featured)) {
                    $noteflags .= html_writer::span(get_string('unmarkednote', 'mod_icontent'), 'badge badge-light');
                }
                if ($noteflags !== '') {
                    $noteheader .= html_writer::span($noteflags, 'noteflags');
                }
            }
            // Note comments.
            $notecomment = html_writer::div(
                $pagenote->comment,
                'notecomment',
                [
                    'data-pagenoteid' => $pagenote->id,
                    'data-cmid' => $pagenote->cmid,
                    'data-sesskey' => sesskey(),
                ]
            );
            // Note footer.
            $noteedit = icontent_make_link_edit_note($pagenote, $context);
            $noteremove = icontent_make_link_remove_note($pagenote, $context);
            $notelike = icontent_make_likeunlike($pagenote, $context);
            $notereply = icontent_make_link_reply_note($pagenote, $context);
            $notedate = html_writer::tag('span', userdate($pagenote->timecreated), ['class' => 'notedate pull-right']);
            // Create footer with items in the order given here.
            $notefooter = html_writer::div($noteedit . $noteremove . $notereply . $notelike . $notedate, 'notefooter');
            // Verify path levels.
            $pathlevels = icontent_get_noteparentinglevels($pagenote->path);

            // Assemle all the notes into just one Div list.
            $noterowicontent = html_writer::div($noteheader . $notecomment . $notefooter, 'noterowicontent');
            $divnote .= html_writer::div(
                $picture . $noterowicontent,
                "pagenoterow level-$pathlevels",
                [
                    'data-level' => $pathlevels,
                    'id' => "pnote{$pagenote->id}",
                ]
            );
        }
        $divnotes = html_writer::div($divnote, 'span notelist');
        return $divnotes;
    }
    // Do this if there are not any notes.
    return html_writer::div(get_string('nonotes', 'icontent'));
}

/**
 * This is the function responsible for creating the responses of notes.
 *
 * Returns responses of notes.
 *
 * @param object $pagenote
 * @param object $icontent
 * @return string $pagenotereply
 */
function icontent_make_pagenotereply($pagenote, $icontent) {
    global $OUTPUT;
    $user = icontent_get_user_by_id($pagenote->userid);
    $context = context_module::instance($pagenote->cmid);
    // Get picture for use with the reply listing.
    $picture = $OUTPUT->user_picture($user, ['size' => 30, 'class' => 'img-thumbnail pull-left']);
    // Note header.
    $linkfirstname = html_writer::link(
        new moodle_url(
            '/user/view.php',
            [
            'id' => $user->id,
            'course' => $icontent->course,
            ]
        ),
        $user->firstname,
        [
            'title' => $user->firstname,
        ]
    );
    $replyon = html_writer::tag(
        'em',
        ' ' . strtolower(trim(get_string('respond', 'icontent'))) . ': ',
        [
            'class' => 'noteon mr-2 ml-2',
        ]
    );
    $noteheader = html_writer::div($linkfirstname . $replyon, 'noteheader');
    if ((string)$pagenote->tab === 'note') {
        $noteflags = '';
        if (!empty($pagenote->private)) {
            $noteflags .= html_writer::span(get_string('private', 'mod_icontent'), 'badge badge-secondary');
        }
        if (!empty($pagenote->featured)) {
            $noteflags .= html_writer::span(get_string('featured', 'mod_icontent'), 'badge badge-info');
        }
        if (empty($pagenote->private) && empty($pagenote->featured)) {
            $noteflags .= html_writer::span(get_string('unmarkednote', 'mod_icontent'), 'badge badge-light');
        }
        if ($noteflags !== '') {
            $noteheader .= html_writer::span($noteflags, 'noteflags');
        }
    }
    // Note comments.
    $notecomment = html_writer::div(
        $pagenote->comment,
        'notecomment',
        [
            'data-pagenoteid' => $pagenote->id,
            'data-cmid' => $pagenote->cmid,
            'data-sesskey' => sesskey(),
        ]
    );
    // Note footer.
    $noteedit = icontent_make_link_edit_note($pagenote, $context);
    $noteremove = icontent_make_link_remove_note($pagenote, $context);
    $notelike = icontent_make_likeunlike($pagenote, $context);
    $notereply = icontent_make_link_reply_note($pagenote, $context);
    $notedate = html_writer::tag('span', userdate($pagenote->timecreated), ['class' => 'notedate pull-right']);
    $notefooter = html_writer::div($noteedit . $noteremove . $notereply . $notelike . $notedate, 'notefooter');
    // Verify path levels.
    $pathlevels = icontent_get_noteparentinglevels($pagenote->path);
    // Div list page notes.
    $noterowicontent = html_writer::div($noteheader . $notecomment . $notefooter, 'noterowicontent');
    // Return reply.
    return html_writer::div(
        $picture . $noterowicontent,
        "pagenoterow level-{$pathlevels}",
        [
            'data-level' => $pathlevels,
            'id' => "pnote{$pagenote->id}",
        ]
    );
}

/**
 * This is the function responsible for creating link to remove note.
 *
 * Returns link.
 *
 * @param object $pagenote
 * @param object $context
 * @return string $link
 */
function icontent_make_link_remove_note($pagenote, $context) {
    if (icontent_user_can_remove_note($pagenote, $context)) {
        return html_writer::link(
            new moodle_url(
                'deletenote.php',
                [
                'id' => $pagenote->cmid,
                'pnid' => $pagenote->id,
                'sesskey' => sesskey(),
                ]
            ),
            "<i class='fa fa-times'></i>" . get_string('remove', 'icontent'),
            [
                'class' => 'removenote',
            ]
        );
    }
    return false;
}

/**
 * This is the function responsible for creating link to edit note.
 *
 * Returns link.
 *
 * @param object $pagenote
 * @param object $context
 * @return string $link
 */
function icontent_make_link_edit_note($pagenote, $context) {
    if (icontent_user_can_edit_note($pagenote, $context)) {
        return html_writer::link(null, "<i class='fa fa-pencil'></i>" . get_string('edit', 'icontent'), ['class' => 'editnote']);
    }
    return false;
}

/**
 * This is the function responsible for creating link to reply note.
 *
 * Returns link.
 *
 * @param object $pagenote
 * @param object $context
 * @return string $link
 */
function icontent_make_link_reply_note($pagenote, $context) {
    if (icontent_user_can_reply_note($pagenote, $context)) {
        return html_writer::link(
            null,
            "<i class='fa fa-reply-all'></i>" . get_string('reply', 'icontent'),
            ['class' => 'replynote']
        );
    }
    return false;
}

/**
 * This is the function responsible for creating links like and do not like.
 *
 * Returns links.
 *
 * @param object $pagenote
 * @param object $context
 * @return string $likeunlike
 */
function icontent_make_likeunlike($pagenote, $context) {
    global $USER;
    if (icontent_user_can_likeunlike_note($pagenote, $context)) {
        $pagenotelike = icontent_get_pagenotelike($pagenote->id, $USER->id, $pagenote->cmid);
        $countlikes = icontent_count_pagenotelike($pagenote->id);
        $notelinklabel = html_writer::span(get_string('like', 'icontent', $countlikes));
        if (!empty($pagenotelike)) {
            $notelinklabel = html_writer::span(get_string('unlike', 'icontent', $countlikes));
        }
        return html_writer::link(
            null,
            "<i class='fa fa-star-o'></i>" . $notelinklabel,
            [
                'class' => 'likenote',
                'data-cmid' => $pagenote->cmid,
                'data-pagenoteid' => $pagenote->id,
                'data-sesskey' => sesskey(),
            ]
        );
    }
    return false;
}

/**
 * This is the function responsible for creating the toolbar.
 *
 * @param object $page
 * @param object $icontent
 * @return string $toolbar
 */
function icontent_make_toolbar($page, $icontent) {
    global $USER;
    // Icons for all users.
    $comments = html_writer::link(
        '#idnotesarea',
        '<i class="fa fa-comments fa-lg"></i>',
        [
            'title' => s(get_string('notes', 'icontent')),
            'class' => 'icon icon-comments',
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
        ]
    );
    $icondisplayed = icontent_get_pagedisplayed($page->id, $page->cmid) ?
        '<i class="fa fa-check-square-o fa-lg"></i>' :
        '<i class="fa fa-square-o fa-lg"></i>';
    $displayed = html_writer::link(
        '#',
        $icondisplayed,
        [
            'title' => s(get_string('statusview', 'icontent')),
            'class' => 'icon icon-displayed',
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
        ]
    );
    $highcontrast = html_writer::link(
        '#!',
        '<i class="fa fa-adjust fa-lg"></i>',
        [
            'title' => s(get_string('highcontrast', 'icontent')),
            'class' => 'icon icon-highcontrast togglehighcontrast',
            'data-toggle' => 'tooltip',
            'data-placement' => 'top',
        ]
    );
    $update = false;
    $new = false;
    $duplicate = false;
    $addquestion = false;
    $delete = false;
    // Check if editing exists for $USER.
    if (property_exists($USER, 'editing')) {
        $context = context_module::instance($page->cmid);
        // Edit mode (view.php). Icons for teachers.
        if ($USER->editing && has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $context)) {
            // Add new question.
            $addquestionparams = [
                'id' => $page->cmid,
                'pageid' => $page->id,
            ];
            $questioncategoryid = icontent_get_page_primary_questioncategoryid((int)$page->id, (int)$page->cmid);
            if (!empty($questioncategoryid)) {
                $addquestionparams['questioncategoryid'] = $questioncategoryid;
            }
            $addquestion = html_writer::link(
                new moodle_url('addquestionpage.php', $addquestionparams),
                '<i class="fa fa-question-circle fa-lg"></i>',
                [
                    'title' => s(get_string('addquestion', 'mod_icontent')),
                    'class' => 'icon icon-addquestion',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                ]
            );
            // Update page.
            $update = html_writer::link(
                new moodle_url(
                    'edit.php',
                    [
                        'cmid' => $page->cmid,
                        'id' => $page->id,
                        'sesskey' => $USER->sesskey,
                    ]
                ),
                '<i class="fa fa-pencil-square-o fa-lg"></i>',
                [
                    'title' => s(get_string('editcurrentpage', 'mod_icontent')),
                    'class' => 'icon icon-update',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                    ]
            );
            // Add new page.
            $new = html_writer::link(
                new moodle_url(
                    'edit.php',
                    [
                        'cmid' => $page->cmid,
                        'pagenum' => $page->pagenum,
                        'sesskey' => $USER->sesskey,
                    ]
                ),
                '<i class="fa fa-plus-circle fa-lg"></i>',
                [
                    'title' => s(get_string('addnewpage', 'mod_icontent')),
                    'class' => 'icon icon-new',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                ]
            );
            // Duplicate current page.
            $duplicate = html_writer::link(
                new moodle_url(
                    'duplicate.php',
                    [
                        'id' => $page->cmid,
                        'pageid' => $page->id,
                        'sesskey' => $USER->sesskey,
                    ]
                ),
                '<i class="fa fa-copy fa-lg"></i>',
                [
                    'title' => s(get_string('duplicatepage', 'mod_icontent')),
                    'class' => 'icon icon-duplicatepage',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                ]
            );
            // Delete current page.
            $delete = html_writer::link(
                new moodle_url(
                    'delete.php',
                    [
                        'id' => $page->cmid,
                        'pageid' => $page->id,
                        'sesskey' => $USER->sesskey,
                    ]
                ),
                '<i class="fa fa-trash fa-lg"></i>',
                [
                    'title' => s(get_string('delete')),
                    'class' => 'icon icon-deletepage',
                    'data-toggle' => 'tooltip',
                    'data-placement' => 'top',
                ]
            );
        }
    }
    // Make toolbar.
    $toolbar = html_writer::tag(
        'div',
        $highcontrast . $comments . $displayed . $addquestion . $update . $new . $duplicate . $delete,
        [
            'class' => 'toolbarpage ',
        ]
    );
    // Return toolbar.
    return $toolbar;
}

/**
 * Get the first question bank category used by questions on this iContent page.
 *
 * @param int $pageid
 * @param int $cmid
 * @return int
 */
function icontent_get_page_primary_questioncategoryid($pageid, $cmid) {
        global $DB;

        $sql = "SELECT qbe.questioncategoryid
                            FROM {icontent_pages_questions} pq
                            JOIN {question_versions} qv
                                ON qv.questionid = pq.questionid
                            JOIN {question_bank_entries} qbe
                                ON qbe.id = qv.questionbankentryid
                         WHERE pq.pageid = ?
                             AND pq.cmid = ?
                    ORDER BY pq.id ASC";
        $categoryid = $DB->get_field_sql($sql, [$pageid, $cmid], IGNORE_MULTIPLE);

        return $categoryid ? (int)$categoryid : 0;
}

/**
 * Save tags for one iContent page.
 *
 * @param int $pageid
 * @param int $cmid
 * @param context_module $context
 * @param string $tagtext
 * @return void
 */
function icontent_save_page_tags($pageid, $cmid, context_module $context, $tagtext) {
    global $DB;

    $DB->get_record('icontent_pages', ['id' => $pageid, 'cmid' => $cmid], 'id', MUST_EXIST);

    $tags = preg_split('/[\r\n,]+/', (string)$tagtext);
    $tags = array_map('trim', $tags);
    $tags = array_values(array_filter($tags, static function ($tag) {
        return $tag !== '';
    }));

    \core_tag_tag::set_item_tags('mod_icontent', 'icontent_pages', $pageid, $context, $tags);
}

/**
 * Build the page tags area (list + optional edit form).
 *
 * @param stdClass $objpage
 * @return string
 */
function icontent_make_page_tags_area($objpage) {
    global $OUTPUT;

    $context = context_module::instance($objpage->cmid);
    $canedittags = has_capability('mod/icontent:edit', $context);
    $tags = \core_tag_tag::get_item_tags('mod_icontent', 'icontent_pages', $objpage->id);

    $title = html_writer::tag('h5', get_string('tags'), ['class' => 'text-uppercase']);
    $taglist = $OUTPUT->tag_list($tags, null, 'icontent-tags');
    if (!$tags) {
        $taglist = html_writer::div(get_string('notagsyet', 'mod_icontent'), 'alert alert-info');
    }

    $content = html_writer::div($taglist, 'icontent-page-tags-list', ['id' => 'idpagetagslist']);

    if ($canedittags) {
        $existingtags = [];
        foreach ($tags as $tag) {
            if (!empty($tag->rawname)) {
                $existingtags[] = $tag->rawname;
            } else if (!empty($tag->name)) {
                $existingtags[] = $tag->name;
            }
        }

        $textarea = html_writer::tag('textarea', s(implode(', ', $existingtags)), [
            'name' => 'pagetags',
            'id' => 'idcommenttag',
            'class' => 'col-12',
            'maxlength' => '1024',
            'placeholder' => get_string('writetag', 'mod_icontent'),
        ]);

        $savebtn = html_writer::tag('button', get_string('save', 'mod_icontent'), [
            'type' => 'submit',
            'class' => 'btn btn-primary pull-right mt-2',
            'id' => 'idbtnsavetag',
        ]);

        $hidden = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $objpage->cmid]);
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'pageid', 'value' => $objpage->id]);
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savetags', 'value' => 1]);
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        $form = html_writer::tag('form', $hidden . $textarea . $savebtn, [
            'method' => 'post',
            'action' => new moodle_url('/mod/icontent/view.php'),
            'class' => 'icontent-page-tags-form mt-2',
        ]);

        $content .= $form;
    }

    return html_writer::div($title . $content, 'icontent-page-tags mt-3', ['id' => 'idpagetagsarea']);
}

/**
 * This is the function responsible for creating the content for cover page.
 *
 * Returns a string with the cover page.
 *
 * @param object $icontent
 * @param object $objpage
 * @param object $context
 * @return string $coverpage
 */
function icontent_make_cover_page($icontent, $objpage, $context) {
    $limitcharshow = 500;
    $displaynone = false;
    $strcontent = strip_tags($objpage->pageicontent);
    $tchars = strlen($strcontent);
    if ($tchars > $limitcharshow) {
        $chars = html_writer::start_tag('p', ['class' => 'read-more-wrap']);
        $chars .= substr($strcontent, 0, $limitcharshow);
        $chars .= html_writer::span('...', 'suspension-points');
        $chars .= html_writer::span(substr($strcontent, $limitcharshow, $tchars), 'read-more-target');
        $chars .= html_writer::end_tag('p');
        $buttons = html_writer::link(
            null,
            '<i class="fa fa-plus"></i>&nbsp;' . get_string('showmore', 'mod_icontent'),
            [
                'class' => 'btn btn-default read-more-state-on',
            ]
        );
        $buttons .= html_writer::link(
            null,
            '<i class="fa fa-minus"></i>&nbsp;' . get_string('showless', 'mod_icontent'),
            [
                'class' => 'btn btn-default read-more-state-off',
            ]
        );
        $chars .= html_writer::div($buttons, 'state-readmore');
    } else {
        $chars = html_writer::tag('p', $strcontent);
        // Checks if content is empty.
        $nospace = str_replace('&nbsp;', '', $strcontent);
        $nospace = str_replace('.', '', $nospace);
        $nospace = trim($nospace);
        // Add class 'hide' to hide element and builds the page.
        $displaynone = empty($nospace) ? 'hide' : false;
    }
    $script = icontent_add_script_load_tooltip();
    // Elements toolbar.
    $toolbarpage = icontent_make_toolbar($objpage, $icontent);
    $title = html_writer::tag('h1', $objpage->title, ['class' => 'titlecoverpage']);
    $header = $objpage->showtitle ? html_writer::div($title, 'headercoverpage row ') : false;
    $content = html_writer::div($chars, "contentcoverpage " . $displaynone);
    $tagsarea = icontent_make_page_tags_area($objpage);
    $coverpage = html_writer::tag(
        'div',
        $toolbarpage . $header . $content . $tagsarea . $script,
        [
            'class' => 'fulltextpage coverpage',
            'data-pageid' => $objpage->id,
            'data-pagenum' => $objpage->pagenum,
            'style' => icontent_get_page_style($icontent, $objpage, $context),
        ]
    );
    // Set page preview, log event and return page.
    icontent_add_pagedisplayed($objpage->id, $objpage->cmid);
    \mod_icontent\event\page_viewed::create_from_page($icontent, $context, $objpage)->trigger();
    return $coverpage;
}

/**
 * This is the function responsible for creating the content of a page.
 *
 * Returns an object with the page content.
 *
 * @param int $pagenum or $startpage
 * @param object $icontent
 * @param object $context
 * @param object $sourcepageid
 * @return object $fullpage
 */
function icontent_get_fullpageicontent($pagenum, $icontent, $context, $sourcepageid = 0) {
    global $DB, $CFG;

    // Get page.
    $objpage = $DB->get_record('icontent_pages', ['pagenum' => $pagenum, 'icontentid' => $icontent->id]);
    if (!$objpage) {
        $objpage = new stdClass();
        $objpage->fullpageicontent = html_writer::div(
            get_string('pagenotfound', 'mod_icontent'),
            'alert alert-warning',
            [
                'role' => 'alert',
            ]
        );
        return $objpage;
    }
    if ($objpage->coverpage) {
        // Make cover page.
        $objpage->fullpageicontent = icontent_make_cover_page($icontent, $objpage, $context);
        // Control button.
        $objpage->previous = icontent_get_prev_pagenum($objpage);
        $objpage->next = icontent_get_next_pagenum($objpage);
        $objpage->previouspageid = icontent_get_pageid_by_pagenum($objpage->cmid, $objpage->previous);
        $objpage->nextpageid = icontent_get_pageid_by_pagenum($objpage->cmid, $objpage->next);
        icontent_record_page_navigation($sourcepageid, $objpage->id, $objpage->cmid);
        return $objpage;
    }
    // Add tooltip.
    $script = icontent_add_script_load_tooltip();
    // Elements toolbar.
    $toolbarpage = icontent_make_toolbar($objpage, $icontent);
    // Add title page.
    $titlestyle = '';
    if (!empty($objpage->titlecolor)) {
        $titlestyle = 'color: #' . icontent_normalize_hex_colour($objpage->titlecolor, '000000') . ';';
    }
    $title = $objpage->showtitle ? html_writer::tag(
        'h3',
        '<i class="fa fa-hand-o-right"></i> ' . $objpage->title,
        [
            'class' => 'pagetitle',
            'style' => $titlestyle,
        ]
    ) : false;
    // Make content.
    $objpage->pageicontent = file_rewrite_pluginfile_urls(
        $objpage->pageicontent,
        'pluginfile.php',
        $context->id,
        'mod_icontent',
        'page',
        $objpage->id
    );
    $objpage->pageicontent = icontent_replace_dynamic_page_placeholders($objpage->pageicontent, $context);
    $objpage->pageicontent = format_text(
        $objpage->pageicontent,
        $objpage->pageicontentformat,
        [
            'noclean' => true,
            'overflowdiv' => false,
            'context' => $context,
        ]
    );
    $objpage->pageicontent = html_writer::div($objpage->pageicontent, 'page-layout columns-' . $objpage->layout);
    // Element page number.
    $npage = html_writer::tag('div', get_string('page', 'icontent', $objpage->pagenum), ['class' => 'pagenum']);
    // Progress bar.
    $progbar = icontent_make_progessbar($objpage, $icontent, $context);
    // Go assemble the list of Questions for this slide/page.
    $qtsareas = icontent_make_questionsarea($objpage, $icontent);
    $recordrtcsettings = icontent_get_recordrtc_ajax_init_settings($context);
    $recordrtcsettingshtml = '';
    if (!empty($recordrtcsettings)) {
        $recordrtcsettingshtml = html_writer::empty_tag('input', [
            'type' => 'hidden',
            'id' => 'idicontent-recordrtc-settings',
            'value' => json_encode($recordrtcsettings, JSON_UNESCAPED_SLASHES),
        ]);
    }
    // Form notes.
    $notesarea = icontent_make_notesarea($objpage, $icontent);
    $tarea = icontent_make_page_tags_area($objpage);

    // Control button.
    $objpage->previous = icontent_get_prev_pagenum($objpage);
    $objpage->next = icontent_get_next_pagenum($objpage);
    $objpage->previouspageid = icontent_get_pageid_by_pagenum($objpage->cmid, $objpage->previous);
    $objpage->nextpageid = icontent_get_pageid_by_pagenum($objpage->cmid, $objpage->next);
    // Content page for return.
    $objpage->fullpageicontent = html_writer::tag(
        'div',
        $toolbarpage .
        $title .
        $objpage->pageicontent .
        $npage .
        $progbar .
        $qtsareas .
        $recordrtcsettingshtml .
        $notesarea .
        $tarea .
        $script,
        [
            'class' => 'fulltextpage',
            'data-pageid' => $objpage->id,
            'data-pagenum' => $objpage->pagenum,
            'style' => icontent_get_page_style($icontent, $objpage, $context),
        ]
    );
    icontent_record_page_navigation($sourcepageid, $objpage->id, $objpage->cmid);
    // Set page preview, log event and return page.
    icontent_add_pagedisplayed($objpage->id, $objpage->cmid);
    \mod_icontent\event\page_viewed::create_from_page($icontent, $context, $objpage)->trigger();
    unset($objpage->pageicontent);
    return $objpage;
}

/**
 * Replace known dynamic placeholders in page content.
 *
 * Supported placeholders:
 * - [[QTYPE_MATRIX]]
 *
 * @param string $content
 * @param context_module $context
 * @return string
 */
function icontent_replace_dynamic_page_placeholders(string $content, $context): string {
    if (strpos($content, '[[QTYPE_MATRIX]]') === false) {
        return $content;
    }

    $replacement = icontent_render_qtype_matrix_placeholder($context);
    return str_replace('[[QTYPE_MATRIX]]', $replacement, $content);
}

/**
 * Render a table of installed question types for the page placeholder.
 *
 * @param context_module $context
 * @return string
 */
function icontent_render_qtype_matrix_placeholder($context): string {
    global $DB;

    $capcontext = \context::instance_by_id($context->id);

    if (!has_any_capability(['mod/icontent:edit', 'mod/icontent:manage'], $capcontext)) {
        return html_writer::div(
            get_string('nopermissions', 'error', get_string('view')),
            'alert alert-warning'
        );
    }

    $installedqtypes = array_keys(\core_component::get_plugin_list('qtype'));
    sort($installedqtypes, SORT_NATURAL | SORT_FLAG_CASE);

    $questioncounts = $DB->get_records_sql_menu(
        'SELECT qtype, COUNT(1) AS total FROM {question} GROUP BY qtype'
    );
    $statusoverrides = icontent_get_qtype_matrix_status_overrides();
    $catalogoverrides = icontent_get_qtype_matrix_catalog_overrides();

    $table = new html_table();
    $table->attributes = ['class' => 'generaltable'];
    $table->head = [
        'Question type',
        'Component',
        'Grading mode',
        'Official Moodle support',
        'Locally verified support',
        'Plugin sites',
        'Popular (>=100 sites)',
        'Test status',
        'Test notes',
        'Questions on this site',
    ];

    foreach ($installedqtypes as $qtype) {
        $component = 'qtype_' . $qtype;
        $pluginname = get_string_manager()->string_exists('pluginname', $component)
            ? get_string('pluginname', $component)
            : $qtype;

        $override = $statusoverrides[$qtype] ?? ['status' => 'not tested', 'notes' => ''];
        $catalog = $catalogoverrides[$qtype] ?? [
            'officialsupport' => '',
            'verifiedsupport' => '',
            'pluginsites' => null,
            'catalognotes' => '',
        ];
        $statusbadgeclass = 'badge-secondary';
        if ($override['status'] === 'pass') {
            $statusbadgeclass = 'badge-success';
        } else if ($override['status'] === 'fail') {
            $statusbadgeclass = 'badge-danger';
        }
        $statusbadge = html_writer::span(ucfirst($override['status']), 'badge ' . $statusbadgeclass);

        $pluginsites = $catalog['pluginsites'];
        $pluginsitesdisplay = is_int($pluginsites) && $pluginsites >= 0 ? (string)$pluginsites : '-';
        $popularbadge = html_writer::span('Unknown', 'badge badge-secondary');
        if (is_int($pluginsites) && $pluginsites >= 0) {
            if ($pluginsites >= 100) {
                $popularbadge = html_writer::span('Yes', 'badge badge-success');
            } else {
                $popularbadge = html_writer::span('No', 'badge badge-warning');
            }
        }

        $officialsupport = $catalog['officialsupport'] !== '' ? s($catalog['officialsupport']) : 'Unknown';
        $verifiedsupport = $catalog['verifiedsupport'] !== '' ? s($catalog['verifiedsupport']) : 'Not verified';

        $notesparts = [];
        if (!empty($override['notes'])) {
            $notesparts[] = s($override['notes']);
        }
        if (!empty($catalog['catalognotes'])) {
            $notesparts[] = s($catalog['catalognotes']);
        }
        $mergednotes = !empty($notesparts) ? implode(' | ', $notesparts) : '';

        $table->data[] = [
            format_string($pluginname),
            s($component),
            icontent_get_qtype_grading_mode_label($qtype),
            $officialsupport,
            $verifiedsupport,
            $pluginsitesdisplay,
            $popularbadge,
            $statusbadge,
            $mergednotes,
            (int)($questioncounts[$qtype] ?? 0),
        ];
    }

    foreach (icontent_get_matrix_integrations($context) as $integration) {
        $integrationkey = $integration['key'];
        $override = $statusoverrides[$integrationkey] ?? ['status' => 'not tested', 'notes' => ''];
        $catalog = $catalogoverrides[$integrationkey] ?? [
            'officialsupport' => '',
            'verifiedsupport' => '',
            'pluginsites' => null,
            'catalognotes' => '',
        ];

        $statusbadgeclass = 'badge-secondary';
        if ($override['status'] === 'pass') {
            $statusbadgeclass = 'badge-success';
        } else if ($override['status'] === 'fail') {
            $statusbadgeclass = 'badge-danger';
        }
        $statusbadge = html_writer::span(ucfirst($override['status']), 'badge ' . $statusbadgeclass);

        $pluginsites = $catalog['pluginsites'];
        $pluginsitesdisplay = is_int($pluginsites) && $pluginsites >= 0 ? (string)$pluginsites : '-';
        $popularbadge = html_writer::span('Unknown', 'badge badge-secondary');
        if (is_int($pluginsites) && $pluginsites >= 0) {
            if ($pluginsites >= 100) {
                $popularbadge = html_writer::span('Yes', 'badge badge-success');
            } else {
                $popularbadge = html_writer::span('No', 'badge badge-warning');
            }
        }

        $officialsupport = $catalog['officialsupport'] !== '' ? s($catalog['officialsupport']) : 'Unknown';
        $verifiedsupport = $catalog['verifiedsupport'] !== '' ? s($catalog['verifiedsupport']) : 'Not verified';

        $notesparts = [];
        if (!empty($override['notes'])) {
            $notesparts[] = s($override['notes']);
        }
        if (!empty($catalog['catalognotes'])) {
            $notesparts[] = s($catalog['catalognotes']);
        }
        if (!empty($integration['runtime'])) {
            $notesparts[] = s($integration['runtime']);
        }
        $mergednotes = !empty($notesparts) ? implode(' | ', $notesparts) : '';

        $table->data[] = [
            format_string($integration['name']),
            s($integration['component']),
            s($integration['gradingmode']),
            $officialsupport,
            $verifiedsupport,
            $pluginsitesdisplay,
            $popularbadge,
            $statusbadge,
            $mergednotes,
            '-',
        ];
    }

    $helptext = html_writer::div(
        html_writer::tag(
            'p',
            'Maintain this matrix in two JSON files: mod/icontent/qtype_matrix_status.json ' .
            '(test result + notes) and mod/icontent/qtype_matrix_catalog.json ' .
            '(support/usage metadata).',
            ['class' => 'mb-1']
        ) .
        html_writer::tag(
            'p',
            'Use exact qtype/integration keys shown in the Component column without the ' .
            'qtype_ prefix, for example varnumeric, formulas, gapfill, cloudpoodll, ' .
            'recordrtc, or filter_embedquestion. Allowed status values are pass, fail, ' .
            'or not tested. Keys beginning with _ are documentation-only and ignored by the renderer.',
            ['class' => 'mb-0']
        ),
        'text-muted small mb-2'
    );

    return html_writer::div(
        html_writer::tag('h4', 'Installed Question Types Readiness') .
        $helptext .
        html_writer::table($table),
        'icontent-qtype-matrix'
    );
}

/**
 * Return non-qtype integrations to show in the readiness matrix.
 *
 * @param context_module $context
 * @return array<int, array{key:string,name:string,component:string,gradingmode:string,runtime:string}>
 */
function icontent_get_matrix_integrations($context): array {
    global $CFG;

    require_once($CFG->libdir . '/filterlib.php');

    $filtername = 'embedquestion';
    $installed = (bool)\core_component::get_plugin_directory('filter', $filtername);
    $activefilters = filter_get_active_in_context($context);
    $isactivehere = array_key_exists($filtername, $activefilters);
    $globalstate = filter_get_active_state($filtername, context_system::instance()->id);

    $globallabel = 'disabled';
    if ($globalstate === TEXTFILTER_ON) {
        $globallabel = 'on';
    } else if ($globalstate === TEXTFILTER_OFF) {
        $globallabel = 'off';
    } else if ($globalstate === TEXTFILTER_INHERIT) {
        $globallabel = 'inherit';
    }

    $runtime = 'Not installed on this site.';
    if ($installed) {
        $runtime = 'Installed; global state: ' . $globallabel . '; active in this activity: '
            . ($isactivehere ? 'yes' : 'no') . '.';
    }

    return [[
        'key' => 'filter_embedquestion',
        'name' => 'Embed questions filter',
        'component' => 'filter_embedquestion',
        'gradingmode' => 'N/A (content filter)',
        'runtime' => $runtime,
    ]];
}

/**
 * Return grading mode label for a question type.
 *
 * @param string $qtype
 * @return string
 */
function icontent_get_qtype_grading_mode_label(string $qtype): string {
    $manualreview = [
        'essay',
        'essayautograde',
        'recordrtc',
        'poodllrecording',
        'cloudpoodll',
    ];
    $autograded = [
        'truefalse',
        'multichoice',
        'match',
        'shortanswer',
        'numerical',
        'calculated',
        'calculatedsimple',
        'calculatedmulti',
        'multianswer',
        'ddwtos',
        'ddimageortext',
        'ddmarker',
        'randomsamatch',
        'selectmissingwords',
        'ordering',
    ];

    if (in_array($qtype, $manualreview, true)) {
        return 'Manual review';
    }
    if (in_array($qtype, $autograded, true)) {
        return 'Auto graded';
    }
    if ($qtype === 'description') {
        return 'Not gradable';
    }

    return 'Unknown (test required)';
}

/**
 * Load qtype test status overrides from local JSON file.
 *
 * @return array<string, array{status:string, notes:string}>
 */
function icontent_get_qtype_matrix_status_overrides(): array {
    $path = __DIR__ . '/qtype_matrix_status.json';
    if (!is_readable($path)) {
        return [];
    }

    $json = file_get_contents($path);
    if ($json === false || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $result = [];
    foreach ($decoded as $qtype => $entry) {
        if (!is_string($qtype) || !is_array($entry)) {
            continue;
        }
        if ($qtype !== '' && $qtype[0] === '_') {
            continue;
        }

        $status = strtolower(trim((string)($entry['status'] ?? 'not tested')));
        if (!in_array($status, ['pass', 'fail', 'not tested'], true)) {
            $status = 'not tested';
        }

        $result[strtolower($qtype)] = [
            'status' => $status,
            'notes' => trim((string)($entry['notes'] ?? '')),
        ];
    }

    return $result;
}

/**
 * Load qtype catalog metadata from local JSON file.
 *
 * @return array<string, array{officialsupport:string, verifiedsupport:string, pluginsites:?int, catalognotes:string}>
 */
function icontent_get_qtype_matrix_catalog_overrides(): array {
    $path = __DIR__ . '/qtype_matrix_catalog.json';
    if (!is_readable($path)) {
        return [];
    }

    $json = file_get_contents($path);
    if ($json === false || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $result = [];
    foreach ($decoded as $qtype => $entry) {
        if (!is_string($qtype) || !is_array($entry)) {
            continue;
        }
        if ($qtype !== '' && $qtype[0] === '_') {
            continue;
        }

        $pluginsites = null;
        if (isset($entry['pluginsites']) && is_numeric($entry['pluginsites'])) {
            $pluginsites = max(0, (int)$entry['pluginsites']);
        }

        $officialsupport = trim((string)($entry['officialsupport'] ?? ''));
        $verifiedsupport = trim((string)($entry['verifiedsupport'] ?? ''));
        $catalognotes = trim((string)($entry['notes'] ?? ''));

        $result[strtolower($qtype)] = [
            'officialsupport' => $officialsupport,
            'verifiedsupport' => $verifiedsupport,
            'pluginsites' => $pluginsites,
            'catalognotes' => $catalognotes,
        ];
    }

    return $result;
}

/**
 * Build RecordRTC JS init settings for iContent AJAX-loaded pages.
 *
 * @param context_module $context
 * @return array
 */
function icontent_get_recordrtc_ajax_init_settings($context): array {
    global $CFG;

    if (!\core_component::get_plugin_directory('qtype', 'recordrtc')) {
        return [];
    }

    require_once($CFG->dirroot . '/repository/lib.php');

    $repositories = repository::get_instances([
        'type' => 'upload',
        'currentcontext' => $context,
    ]);
    if (empty($repositories)) {
        return [];
    }

    $uploadrepository = reset($repositories);
    $coursemaxbytes = 0;
    [, $course] = get_context_info_array($context->id);
    if (is_object($course) && !empty($course->maxbytes)) {
        $coursemaxbytes = (int)$course->maxbytes;
    }

    $videosize = (string)(get_config('qtype_recordrtc', 'videosize') ?: '640,480');
    $screensize = (string)(get_config('qtype_recordrtc', 'screensize') ?: '1920,1080');
    [$videowidth, $videoheight] = array_pad(explode(',', $videosize, 2), 2, '0');
    [$screenwidth, $screenheight] = array_pad(explode(',', $screensize, 2), 2, '0');

    return [
        'audioBitRate' => (int)get_config('qtype_recordrtc', 'audiobitrate'),
        'videoBitRate' => (int)get_config('qtype_recordrtc', 'videobitrate'),
        'screenBitRate' => (int)get_config('qtype_recordrtc', 'screenbitrate'),
        'maxUploadSize' => get_user_max_upload_file_size($context, $CFG->maxbytes, $coursemaxbytes),
        'uploadRepositoryId' => (int)$uploadrepository->id,
        'contextId' => (int)$context->id,
        'videoWidth' => (int)$videowidth,
        'videoHeight' => (int)$videoheight,
        'screenWidth' => (int)$screenwidth,
        'screenHeight' => (int)$screenheight,
    ];
}

/**
 * Returns icontent pages tagged with a specified tag.
 *
 * This is a callback used by the tag area mod_icontent/icontent_pages to search for icontent pages
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function mod_icontent_get_tagged_pages($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
    global $OUTPUT;
    $perpage = $exclusivemode ? 20 : 5;

    // Build the SQL query.
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $query = "SELECT ip.id, ip.title, ip.icontentid,
                     ic.timeopen, ic.timeclose,
                     cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                FROM {icontent_pages} ip
                JOIN {icontent} ic ON ip.icontentid = ic.id
                JOIN {modules} m ON m.name='icontent'
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = ic.id
                JOIN {tag_instance} tt ON ip.id = tt.itemid
                JOIN {course} c ON cm.course = c.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
               WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component
                 AND cm.deletioninprogress = 0
                 AND ip.id %ITEMFILTER% AND c.id %COURSEFILTER%";

    $params = ['itemtype' => 'icontent_pages',
        'tagid' => $tag->id,
        'component' => 'mod_icontent',
        'coursemodulecontextlevel' => CONTEXT_MODULE,
    ];

    if ($ctx) {
        $context = $ctx ? context::instance_by_id($ctx) : context_system::instance();
        $query .= $rec ? ' AND (ctx.id = :contextid OR ctx.path LIKE :path)' : ' AND ctx.id = :contextid';
        $params['contextid'] = $context->id;
        $params['path'] = $context->path . '/%';
    }

    $query .= " ORDER BY ";
    if ($fromctx) {
        // In order-clause specify that modules from inside "fromctx" context should be returned first.
        $fromcontext = context::instance_by_id($fromctx);
        $query .= ' (CASE WHEN ctx.id = :fromcontextid OR ctx.path LIKE :frompath THEN 0 ELSE 1 END),';
        $params['fromcontextid'] = $fromcontext->id;
        $params['frompath'] = $fromcontext->path . '/%';
    }
    $query .= ' c.sortorder, cm.id, ip.id';

    $totalpages = $page + 1;

    // Use core_tag_index_builder to build and filter the list of items.
    $builder = new core_tag_index_builder('mod_icontent', 'icontent_pages', $query, $params, $page * $perpage, $perpage + 1);
    while ($item = $builder->has_item_that_needs_access_check()) {
        context_helper::preload_from_record($item);
        $courseid = $item->courseid;
        if (!$builder->can_access_course($courseid)) {
            $builder->set_accessible($item, false);
            continue;
        }
        $modinfo = get_fast_modinfo($builder->get_course($courseid));
        // Set accessibility of this item and all other items in the same course.
        $builder->walk(function ($taggeditem) use ($courseid, $modinfo, $builder) {
            if ($taggeditem->courseid == $courseid) {
                $accessible = false;
                if (($cm = $modinfo->get_cm($taggeditem->cmid)) && $cm->uservisible) {
                    $icontent = (object)[
                        'id' => $taggeditem->icontentid,
                        'course' => $cm->course,
                        'timeopen' => (int)($taggeditem->timeopen ?? 0),
                        'timeclose' => (int)($taggeditem->timeclose ?? 0),
                    ];
                    $accessible = icontent_user_can_view(null, $icontent);
                }
                $builder->set_accessible($taggeditem, $accessible);
            }
        });
    }

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
        array_pop($items);
    }

    // Build the display contents.
    if ($items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            context_helper::preload_from_record($item);
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $pageurl = new moodle_url('/mod/icontent/view.php', ['pageid' => $item->id]);
            $pagename = format_string($item->title, true, ['context' => context_module::instance($item->cmid)]);
            $pagename = html_writer::link($pageurl, $pagename);
            $courseurl = course_get_url($item->courseid, $cm->sectionnum);
            $cmname = html_writer::link($cm->url, $cm->get_formatted_name());
            $coursename = format_string($item->fullname, true, ['context' => context_course::instance($item->courseid)]);
            $coursename = html_writer::link($courseurl, $coursename);
            $icon = html_writer::link($pageurl, html_writer::empty_tag('img', ['src' => $cm->get_icon_url()]));
            $tagfeed->add($icon, $pagename, $cmname . '<br>' . $coursename);
        }

        $content = $OUTPUT->render_from_template(
            'core_tag/tagfeed',
            $tagfeed->export_for_template($OUTPUT)
        );

        // Debug printouts intentionally removed for release code.

        return new core_tag\output\tagindex(
            $tag,
            'mod_icontent',
            'icontent_pages',
            $content,
            $exclusivemode,
            $fromctx,
            $ctx,
            $rec,
            $page,
            $totalpages
        );
    }
}

/**
 * Check whether the current user can view an iContent activity.
 *
 * @param stdClass|null $subicontent Unused legacy placeholder for compatibility.
 * @param stdClass|null $icontent iContent record-like object with id, course, timeopen, timeclose.
 * @return bool
 */
function icontent_user_can_view($subicontent = null, $icontent = null) {
    if (empty($icontent) || empty($icontent->id) || empty($icontent->course)) {
        return false;
    }

    $modinfo = get_fast_modinfo((int)$icontent->course);
    if (empty($modinfo->instances['icontent'][(int)$icontent->id])) {
        return false;
    }

    $cm = $modinfo->instances['icontent'][(int)$icontent->id];
    if (!$cm->uservisible) {
        return false;
    }

    $context = context_module::instance($cm->id);
    if (!has_capability('mod/icontent:view', $context)) {
        return false;
    }

    $timeopen = (int)($icontent->timeopen ?? 0);
    $timeclose = (int)($icontent->timeclose ?? 0);
    return (($timeopen == 0 || time() >= $timeopen) && ($timeclose == 0 || time() < $timeclose));
}
