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
 * English strings for icontent
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Content Pages';
$string['modulenameplural'] = 'Content Pages';
$string['modulename_help'] = 'The plugin for Moodle (Content Pages), was designed so that from it, conteudista, tutors, teachers and technicians can add content in courses, following usability and accessibility standards.
This tool will be responsible for signaling the good practice of adding text, images, multimedia, among others. In it, the content will be distributed in pages, so that the monitoring of the content does not become something exhausting and tiring. The plugin also has a bookmark, so that the participant can be located, based on last logged furthermore allow the inclusion of public or private notes, which the participant can provide feedback on the content studied on the page and enjoy and reply comments from other colleagues.
The plugin also allows the launch of questions about the content addressed, this provides better interaction between the participant and the virtual learning platform, and the feature may become an evaluation item or launch fractional notes on the items available in the course as defined in the plan of action. The described extension is fully responsive and can be accessed by any device.';
$string['icontentfieldset'] = 'Custom example fieldset';
$string['icontentname'] = 'Content Pages name';
$string['icontentname_help'] = 'This is the icontent of the help tooltip associated with the icontentname field. Markdown syntax is supported.';
$string['icontent'] = 'Content Pages';
$string['pluginadministration'] = 'Content Pages Administration';
$string['pluginname'] = 'Content Pages';
$string['maximumdigits'] = 'Maximum of {$a} digits';
$string['grade'] = 'Grade';
$string['gradingscale'] = 'Grades (0 - {$a})';
$string['bgimage'] = 'Background image';
$string['bgimagehelp'] = 'Image of Background';
$string['bgimagepagehelp'] = 'Image of Background';
$string['bgimagehelp_help'] = 'Standard image that will be as the background on every page.';
$string['bgimagepagehelp_help'] = 'Standard image that will be as the background on atual page.';
$string['bgcolor'] = 'Background color';
$string['bgcolorhelp'] = 'Background color';
$string['bgcolorpagehelp'] = 'Background color';
$string['bgcolorhelp_help'] = 'Default color will be as the background on every page.';
$string['bgcolorpagehelp_help'] = 'Default color will be as the background on atual page.';
$string['bordercolor'] = 'Border color';
$string['bordercolorhelp'] = 'Border color';
$string['bordercolorpagehelp'] = 'Border color';
$string['bordercolorhelp_help'] = 'Click the field and choose the default color for the borders of each page.';
$string['bordercolorpagehelp_help'] = 'Click the field and choose the default color for the borders of atual page.';
$string['borderwidth'] = 'Border width';
$string['borderwidthhelp'] = 'Border width';
$string['borderwidthpagehelp'] = 'Border width';
$string['borderwidthhelp_help'] = 'Choose the default width to the edges of each page.';
$string['borderwidthpagehelp_help'] = 'Choose the default width to the edges of atual page.';
$string['maxpages'] = 'Maximum number of pages';
$string['maxpageshelp'] = 'Maximum number of pages';
$string['maxpageshelp_help'] = 'Enter the maximum number of pages allowed. Enter only whole numbers. Importantly, in distance education icontent should use a maximum of 35 pages.';
$string['maxnotesperpages'] = 'Maximum number of notes per pages';
$string['maxnotesperpageshelp'] = 'Maximum number of notes per pages';
$string['maxnotesperpageshelp_help'] = 'Enter the maximum number of notes per page allowed. Enter only whole numbers.';
$string['attempt'] = 'Attempt';
$string['attemptsallowed'] = 'Attempts allowed';
$string['attemptsallowedhelp'] = 'Attempts allowed';
$string['attemptsallowedhelp_help'] = 'Choose an option to set the allowed attempts.';
$string['progressbar'] = 'Progress bar';
$string['shownotesarea'] = 'Show notes area';
$string['shownotesarea_help'] = 'Select "Yes" to display the notes area.';
$string['progressbar_help'] = 'If enabled, a bar is displayed at the bottom of lesson pages showing approximate percentage of completion.';
$string['emptyquestionbank'] = '<i class="fa fa-exclamation-triangle"></i> Bank question is empty.';
$string['norecordsfound'] = '<i class="fa fa-info-circle"></i> No records found.';
$string['evaluative'] = 'Evaluative item';
$string['evaluative_help'] = 'If you enable this feature becomes an evaluation item.';
$string['usemaxgrade'] = 'Maximum points';
$string['usemaxgrade_help'] = 'If the appeal is an evaluation item, you can then inform the highest grade available in this activity.';
$string['summary'] = 'Summary';
$string['summaryattempts'] = 'Summary attempts';
$string['icontentmenu'] = 'Content pages menu';
$string['save'] = 'Save';
$string['state'] = 'State';
$string['strstate'] = 'Finished <br /> Submitted {$a}.';
$string['answers'] = 'Answers';
$string['answersevaluatedinfo'] = '<i class="fa fa-info-circle"></i> The answers that have been evaluated do not appear in this list. To reassess, {$a}.';
$string['reassess'] = 'Reassess';
$string['rightanswers'] = 'Right answers';
$string['totalanswers'] = 'Total answers';
$string['evaluate'] = 'Evaluate';
$string['toevaluate'] = 'To evaluate';
$string['result'] = 'Result';
$string['results'] = 'Results';
$string['partialresult'] = 'Partial result';
$string['manualreview'] = 'Manual review';
$string['manualreviewofparticipant'] = 'Manual review of attempt of participant {$a}';
$string['manualgrading'] = 'Manual grading';
$string['strmanualgrading'] = 'Students with questions that need to be evaluated manually';
$string['stropenanswer'] = '<span class="label label-info">{$a} response awaiting evaluation.</span>';
$string['strtoevaluate'] = '{$a->fraction} out of {$a->maxfraction} ({$a->percentage}%). {$a->openanswer}';
$string['strattempttitle'] = 'Attempt held in page {$a->pagenum} title {$a->title}';
$string['strmaxgrade'] = ' out of 1,00';
$string['action'] = 'Action';
$string['addafter'] = 'Add new page';
$string['addquestion'] = 'Add new question';
$string['addnewpage'] = 'Add new page';
$string['editingpage'] = 'Editing page';
$string['pagetitle'] = 'Page title';
$string['pagenotfound'] = '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i> No page found for this instance of iContent.';
$string['coverpage'] = 'Cover page';
$string['clickhere'] = 'Click here';
$string['toreassess'] = 'to reassess';
$string['coverpage_help'] = 'Enable to create a cover page for the content to be displayed. If you select this option, add a brief description in the content field. This description will appear on the cover. If the description is longer than 500 characters, one [read more] button will be added.';
$string['icontent'] = 'Content';
$string['noeffect'] = 'No effect';
$string['effects'] = 'Effects';
$string['transitioneffect'] = 'Transitions';
$string['transitioneffecthelp'] = 'Transitions effects';
$string['transitioneffecthelp_help'] = 'Select the type of transition for the current page.';
$string['goback'] = 'Go back';
$string['advance'] = 'Advance';
$string['next'] = 'Next ';
$string['nextpage'] = 'Next page';
$string['preview'] = 'Preview';
$string['previous'] = ' Previous';
$string['previouspage'] = 'Page previous';
$string['tryagain'] = 'Try again';
$string['infomaxquestionperpage'] = '<i class="fa fa-info-circle"></i> Add up to 3 questions per page.';
$string['displayed'] = 'Visible page';
$string['displayedhelp'] = 'Visible';
$string['displayedhelp_help'] = 'If enabled, the page will be visible.';
$string['showbgimage'] = 'Show background image';
$string['showbgimage_help'] = 'Select for show background image.';
$string['showtitle'] = 'Show title';
$string['showtitle_help'] = 'Show / Hide the page title.';
$string['showmore'] = 'Show more';
$string['showless'] = 'Show less';
$string['expandnotesarea'] = 'Expand notes area';
$string['expandnotesarea_help'] = 'Check to show the area of expanded notes.';
$string['expandquestionsarea'] = 'Expand questions area';
$string['expandquestionsarea_help'] = 'Check to show the area of expanded questions.';
$string['copyright'] = 'Copyright';
$string['copyright_help'] = 'Reserved space to add the credits related to copyright.';
$string['confpagedelete'] = 'Do you want to delete this page along with all files and records related to it?';
$string['confpagenotedelete'] = '<i class="fa fa-exclamation-triangle"></i> Are you sure you want to delete this note and all replies? <span class="label label-warning">{$a} reply(ies)</span>';
$string['confdeleteattempt'] = 'Remove attempt the page {$a->pagenum}';
$string['choiceoneoption'] = 'Choose a option:';
$string['choiceone'] = 'Choice a:';
$string['choiceoneormore'] = 'Choice {$a} options:';
$string['layout'] = 'Page layout';
$string['layouthelp'] = 'Layout of page';
$string['layouthelp_help'] = 'Choose a layout.';
$string['fluid'] = 'Fluid';
$string['collumns2'] = 'Until two collumns';
$string['collumns3'] = 'Until three collumns';
$string['collumns4'] = 'Until four collumns';
$string['collumns5'] = 'Until five collumns';
$string['msgsucess'] = 'Successfully recorded data!';
$string['msgsucessexclusion'] = 'Successfully deleted records!';
$string['msgaddquestionpage'] = 'Successfully add questions!';
$string['msgsucessevaluate'] = '{$a} evaluation completed successfully!';
$string['msgconfirmdeleteattempt'] = '<i class="fa fa-exclamation-triangle"></i> <span class="label label-warning"> {$a->totalanswers} responses </span>found in the attempt. Are you sure you want to remove attempting this page?';
$string['msgstatusdisplay'] = 'You can not add or remove questions because attempts have been recorded.';
$string['page'] = '<span>Page <em>{$a}</em></span>';
$string['highcontrast'] = 'highcontrast';
$string['comments'] = 'Comments';
$string['doubts'] = 'Doubts';
$string['mylistcomments'] = 'My list of comments';
$string['listdoubts'] = 'List of doubts';
$string['writenotes'] = 'Write note';
$string['writedoubt'] = 'Write doubt';
$string['note'] = 'Notes <span id="messagenotes">{$a}</span>';
$string['doubt'] = 'Doubt <span id="messagedoubt">{$a}</span>';
$string['private'] = 'Private';
$string['privates'] = 'Private';
$string['featured'] = 'Featured';
$string['highlights'] = 'Highlights';
$string['highlighted'] = 'Highlighted';
$string['alldoubts'] = 'All doubts';
$string['doubttutor'] = 'Ask only for tutor';
$string['doubtstotutor'] = 'Questions sent to the tutor';
$string['doubtandnotes'] = 'Make your notes and send their doubts';
$string['nonotes'] = 'No notes for this page.';
$string['notedon'] = ' noted on ';
$string['edit'] = ' Edit ';
$string['editcurrentpage'] = ' Edit current page';
$string['remove'] = ' Remove ';
$string['removenote'] = ' Remove note';
$string['removenotes'] = ' Remove notes';
$string['likes'] = ' Likes ';
$string['like'] = ' Like ({$a}) ';
$string['unlike'] = ' unlike ({$a}) ';
$string['reply'] = ' Reply ';
$string['type'] = ' Type';
$string['questions'] = 'Questions';
$string['answerthequestions'] = 'Answer the question(s)';
$string['resultlastattempt'] = 'Result of the last attempt';
$string['createdby'] = ' Created by';
$string['lastmodifiedby'] = 'Last modified by';
$string['respond'] = ' Respond ';
$string['statusview'] = 'Status view';
$string['send'] = 'Send';
$string['sendattemp'] = 'Send attemp';
$string['sendallandfinish'] = 'Send all and finish';
$string['sendanswers'] = 'Send answers';
$string['labelprogressbar'] = '{$a}% complete';
$string['erroridnotfound'] = ' <i class="fa fa-exclamation-triangle"></i> You must specify the param {$a}';

$string['calculated'] = 'Calculated';
$string['calculatedmulti'] = 'Calculated multi';
$string['calculatedsimple'] = 'Calculated simple';
$string['description'] = 'Description';
$string['essay'] = 'Essay';
$string['match'] = 'Match';
$string['multianswer'] = 'Multi answer';
$string['multichoice'] = 'Multi choice';
$string['numerical'] = 'Numerical';
$string['random'] = 'Random';
$string['shortanswer'] = 'Short answer';
$string['truefalse'] = 'truefalse';

$string['eventpagecreated'] = 'Page created';
$string['eventpagedeleted'] = 'Page deleted';
$string['eventpageupdated'] = 'Page updated';
$string['eventpageviewed'] = 'Page viewed';
$string['eventnotecreated'] = 'Note created';
$string['eventnotedeleted'] = 'Note deleted';
$string['eventnotereplied'] = 'Note replied';
$string['eventnoteupdated'] = 'Note updated';
$string['eventnotelikecreated'] = 'Note like created';
$string['eventnotelikedeleted'] = 'Note like deleted';
$string['eventquestionattemptcreated'] = 'Question attempt created';
$string['eventquestionattemptdeleted'] = 'Question attempt deleted';
$string['eventquestionpageviewed'] = 'View page addquestionpage';
$string['eventquestiontoevaluatecreated'] = 'Evaluation manual questions';

$string['icontent:addinstance'] = 'Add a new content pages';
$string['icontent:grade'] = 'View grade report';
$string['icontent:manage'] = 'Manage interactive icontent';
$string['icontent:view'] = 'View interactive icontent';
$string['icontent:edit'] = 'Edit content pages';
$string['icontent:viewnotes'] = 'View notes';
$string['icontent:likenotes'] = 'Like notes';
$string['icontent:checkboxdoubttutornotes'] = 'Doubt for tutor';
$string['icontent:checkboxfeaturednotes'] = 'Mark notes as featured';
$string['icontent:checkboxprivatenotes'] = 'Mark notes as private';
$string['icontent:editnotes'] = 'Edit note';
$string['icontent:removenotes'] = 'Remove notes';
$string['icontent:replynotes'] = 'Reply note';
$string['icontent:newquestion'] = 'Add a new question';
$string['icontent:answerquestionstryagain'] = 'Allow unlimited answers to the questions on pages';