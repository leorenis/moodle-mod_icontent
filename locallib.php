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
 * Internal library of functions for module icontent
 *
 * All the icontent specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_icontent
 * @copyright  2015 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Constantes
 */
define('ICONTENT_PAGE_MIN_HEIGHT', 500);

require_once(dirname(__FILE__).'/lib.php');

 /**
 * Add the book TOC sticky block to the default region
 *
 * @param array $pages
 * @param object $page
 * @param object $icontent
 * @param object $cm
 * @param bool $edit
 */
function icontent_add_fake_block($pages, $page, $icontent, $cm, $edit) {
    global $OUTPUT, $PAGE;

	$toc = icontent_get_toc($pages, $page, $icontent, $cm, $edit, 0);
	
    $bc = new block_contents();
    $bc->title = get_string('summary', 'icontent');
    $bc->attributes['class'] = 'block block_icontent_toc';
    $bc->content = $toc;
    $defaultregion = $PAGE->blocks->get_default_region();
    $PAGE->blocks->add_fake_block($bc, $defaultregion);
}

/**
 * Generate toc structure
 *
 * @param array $pages
 * @param object $page
 * @param object $icontent
 * @param object $cm
 * @param bool $edit
 * @return string
 */
function icontent_get_toc($pages, $page, $icontent, $cm, $edit) {
    global $USER, $OUTPUT;
	
	$context = context_module::instance($cm->id);
	
	$toc = '';
	$toc .= html_writer::start_tag('div', array('class' => 'icontent_toc clearfix'));
	
	// // Teacher's TOC
	if($edit){

		$toc .= html_writer::start_tag('ul');
		$i = 0;
		foreach ($pages as $pg) {
			$i ++;
			$title = trim(format_string($pg->title, true, array('context'=>$context)));
			$toc .= html_writer::start_tag('li', array('class' => 'clearfix')); // Inicio <li>
				$toc .= html_writer::link('#', $title, array('title' => s($title), 'class'=>'load-page page'.$pg->pagenum, 'data-pagenum' => $pg->pagenum, 'data-cmid' => $pg->cmid, 'data-sesskey' => sesskey()));
				
				// Actions
				$toc .= html_writer::start_tag('div', array('class' => 'action-list')); // Inicio <div>
					if ($i != 1) {
		                $toc .= html_writer::link(new moodle_url('move.php', array('id' => $cm->id, 'pageid' => $pg->id, 'up' => '1', 'sesskey' => $USER->sesskey)),
		                		$OUTPUT->pix_icon('t/up', get_string('up')), array('title' => get_string('up')));
		            }
		            if ($i != count($pages)) {
		                $toc .= html_writer::link(new moodle_url('move.php', array('id' => $cm->id, 'pageid' => $pg->id, 'up' => '0', 'sesskey' => $USER->sesskey)),
		                		$OUTPUT->pix_icon('t/down', get_string('down')), array('title' => get_string('down')));
		            }
					$toc .= html_writer::link(new moodle_url('edit.php', array('cmid' => $pg->cmid, 'id' => $pg->id, 'sesskey' => $USER->sesskey)),
	                		$OUTPUT->pix_icon('t/edit', get_string('edit')), array('title' => get_string('edit')));
	            	$toc .= html_writer::link(new moodle_url('delete.php', array('id' => $pg->cmid, 'pageid' => $pg->id, 'sesskey' => $USER->sesskey)),
	                		$OUTPUT->pix_icon('t/delete', get_string('delete')), array('title' => get_string('delete')));
				
					if ($pg->hidden) {
	                	$toc .= html_writer::link(new moodle_url('show.php', array('id' => $pg->cmid, 'pageid' => $pg->id, 'sesskey' => $USER->sesskey)),
	                    		$OUTPUT->pix_icon('t/show', get_string('show')), array('title' => get_string('show')));
		            } else {
		                $toc .= html_writer::link(new moodle_url('show.php', array('id' => $pg->cmid, 'pageid' => $pg->id, 'sesskey' => $USER->sesskey)),
		                                         $OUTPUT->pix_icon('t/hide', get_string('hide')), array('title' => get_string('hide')));
		            }
					$toc .= html_writer::link(new moodle_url('edit.php', array('cmid' => $pg->cmid, 'pagenum' => $pg->pagenum, 'sesskey' => $USER->sesskey)),
	                		$OUTPUT->pix_icon('add', get_string('addafter', 'mod_icontent'), 'mod_icontent'), array('title' => get_string('addafter', 'mod_icontent')));
				$toc .= html_writer::end_tag('div'); 	// Fim </div>
			$toc .= html_writer::end_tag('li'); // Fim </li>
		}
		
		$toc .= html_writer::end_tag('ul');
	}else{	// Normal students view
		$toc .= html_writer::start_tag('ul');
		foreach ($pages as $pg) {
			if(!$pg->hidden){
				$title = trim(format_string($pg->title, true, array('context'=>$context)));
				$toc .= html_writer::start_tag('li', array('class' => 'clearfix'));
					$toc .= html_writer::link('#', $title, array('title' => s($title), 'class'=>'load-page page'.$pg->pagenum, 'data-pagenum' => $pg->pagenum, 'data-cmid' => $pg->cmid, 'data-sesskey' => sesskey()));
				$toc .= html_writer::end_tag('li');
			}
		}
		
		$toc .= html_writer::end_tag('ul');
	}
	
	$toc .= html_writer::end_tag('div');
	
	return $toc;
}
 /**
 * Add atributos dinamicos da tela de carregamento de paginas
 * @param object $pagestyle
 * @return void
 */
function icontent_add_properties_css($pagestyle){
	$style = "background-color: #{$pagestyle->bgcolor}; ";
	$style .= "min-height: ". ICONTENT_PAGE_MIN_HEIGHT ."px; ";
	$style .= "border: {$pagestyle->borderwidth}px solid #{$pagestyle->bordercolor};";
	
	if($pagestyle->bgimage){
		$style .= "background-image: url('{$pagestyle->bgimage}')";
	}
	
	return $style;
}
 /**
 * Recupera estilo da pagina. O metodo verifica se a pagina possui os valores suficientes para montar o estilo. 
 * Senão retorna estilo generico do plugin.
 * @param object $icontent
 * @param object $page
 * @return pagestyle;
 */
function icontent_get_page_style($icontent, $page, $context){
	$pagestyle = new stdClass;
	$pagestyle->bgcolor = $page->bgcolor ? $page->bgcolor : $icontent->bgcolor;
	$pagestyle->borderwidth = $page->borderwidth ? $page->borderwidth : $icontent->borderwidth;
	$pagestyle->bordercolor = $page->bordercolor ? $page->bordercolor : $icontent->bordercolor;
	$pagestyle->bgimage = false;
	if($page->showbgimage){
		$pagestyle->bgimage = icontent_get_page_bgimage($context, $page) ? icontent_get_page_bgimage($context, $page) : icontent_get_bgimage($context);
	}
	
	return icontent_add_properties_css($pagestyle);
}

 /**
 * Add border options
 * @param void 
 * @return array $options
 */
function icontent_add_borderwidth_options(){
	$arr = array();
	for($i = 0; $i < 50; $i++){
		$arr[$i] = $i.'px';
	}
	return $arr;
}
/**
 * Recupera bgimage do plugin icontent
 * @param object $context 
 * @return string $fullpath
 */
function icontent_get_bgimage($context){
	global $CFG;
	$fs = get_file_storage();
	$files = $fs->get_area_files($context->id, 'mod_icontent', 'icontent', 0, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
	
	if (count($files) >= 1) {
	    $file = reset($files);
	    unset($files);
		
	    $path = '/'.$context->id.'/mod_icontent/icontent/'.$file->get_filepath().$file->get_filename();
	    $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
	
	    $mimetype = $file->get_mimetype();
		
		 if (file_mimetype_in_typegroup($mimetype, 'web_image'))   // It's an image
			return $fullurl;
		 return false;
	}
	return false;
}
/**
 * Recupera bgimage das paginas de conteudo do plugin icontent
 * @param object $context 
 * @return string $fullpath
 */
function icontent_get_page_bgimage($context, $page){
	global $CFG;
	$fs = get_file_storage();
	$files = $fs->get_area_files($context->id, 'mod_icontent', 'bgpage', $page->id, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
	
	if (count($files) >= 1) {
	    $file = reset($files);
	    unset($files);
		
	    $path = '/'.$context->id.'/mod_icontent/bgpage/'.$page->id.$file->get_filepath().$file->get_filename();
	    $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
	
	    $mimetype = $file->get_mimetype();
		
		 if (file_mimetype_in_typegroup($mimetype, 'web_image'))   // It's an image
			return $fullurl;
		 return false;
	}
	return false;
}
/**
 * Preload icontent pages.
 *
 * Returns array of pages 
 * Please note the icontent/text of pages is not included.
 *
 * @param  object $icontent
 * @return array of id=>icontent
 */
function icontent_preload_pages($icontent){
	global $DB;
	$pages = $DB->get_records('icontent_pages', array('icontentid'=>$icontent->id), 'pagenum', 'id, icontentid, cmid, pagenum, coverpage, title, hidden');
    if (!$pages) {
        return array();
    }
	
	$first = true;
    $pagenum = 0; // page sort
    foreach ($pages as $id => $pg) {
        $oldpg = clone($pg);
        $pagenum++;
        $pg->pagenum = $pagenum;
        if ($first) {
            $first = false;
        }
		
        if ($oldpg->pagenum != $pg->pagenum or $oldpg->hidden != $pg->hidden) {
            // update only if something changed
            $DB->update_record('icontent_pages', $pg);
        }
        $pages[$id] = $pg;
    }
	
	return $pages;
}
/**
 * Remove anotacoes de uma pagina.
 *
 * Returns boolean true or false
 *
 * @param  int $pageid
 * @param  int $noteid
 * @return boolean true or false
 */
function icontent_remove_notes($pageid, $pagenoteid = null){
	global $DB;
	if($pagenoteid){
		icontent_remove_note_likes($pagenoteid);
		$rs = $DB->delete_records('icontent_pages_notes', array('id'=>$pagenoteid));
		return $rs ? true : false;
	}
	// get notes
	$pagenotes = $DB->get_records('icontent_pages_notes', array('pageid'=>$pageid));
	
	foreach ($pagenotes as $pagenote) {
		icontent_remove_note_likes($pagenote->id);
		$rs = $DB->delete_records('icontent_pages_notes', array('id'=>$pagenote->id));
	}
	return $rs ? true : false;
}

/**
 * Remove likes de anotacoes de uma pagina.
 *
 * Returns boolean true or false
 *
 * @param  int $noteid
 * @return boolean true or false
 */
function icontent_remove_note_likes($pagenoteid){
	global $DB;
	$rs = $DB->delete_records('icontent_pages_notes_like', array('pagenoteid'=>$pagenoteid));
	
	return $rs ? true : false;
}

/**
 * Carrega botoes do conteudo.
 *
 * Returns buttons of pages 
 *
 * @param  object $pages
 * @return array of id=>icontent
 */
function icontent_buttons($pages){
	if(empty($pages)){
		return false;
	}
	// Source here! 
	$pgbuttons = html_writer::start_div('btn_pages', array('id'=> 'fitem_id_submitbutton'));
	$npage = 0;
	foreach ($pages as $page) {
		if(!$page->hidden){
			$npage ++;
			$pgbuttons .= html_writer::tag('button', $npage, array('title' => s($page->title), 'class'=>'load-page page'.$page->pagenum , 'data-toggle'=> 'tooltip', 'data-placement'=> 'top', 'data-pagenum' => $page->pagenum, 'data-cmid' => $page->cmid, 'data-sesskey' => sesskey()));
		}
	}
	$pgbuttons .= html_writer::end_div();
	
	return $pgbuttons;
}

/**
 * Carrega numero da pagina de unicio do usuario logado.
 *
 * Returns array of pages 
 * Please note the icontent/text of pages is not included.
 *
 * @param  object $icontent
 * @param  object $context
 * @return array of id=>icontent
 */
function icontent_get_startpagenum($icontent, $context){
	global $DB;
	if(has_capability('mod/icontent:edit', $context)){
		return icontent_get_minpagenum($icontent);
	}
	// REGRA: sistema devera encontrar a pagina que o usuario parou na tabela {icontent_pages_displayed} e retornar pagina.
	// codigo aqui
	$pagenum = 1;
	return $pagenum;
}

/**
 * Carrega primeira pagina de conteudo do componente.
 *
 * Returns array of pages 
 * Please note the icontent/text of pages is not included.
 *
 * @param  object $icontent
 * @return array of id=>icontent
 */
function icontent_get_minpagenum($icontent){
	global $DB;
	
	$sql = "SELECT Min(pagenum) AS minpagenum FROM {icontent_pages} WHERE icontentid = ?;";
	
 	$obj = $DB->get_record_sql($sql, array($icontent->id));
	
	return $obj->minpagenum;
}

/**
 * Add visualizacao em uma pagina e ela ainda nao foi vizualizada.
 *
 * Returns object of pagedisplayed
 *
 * @param  int $pageid
 * @param  int $cmid
 * @param  string $userid
 * @return object $pagedisplayed
 */
function icontent_add_pagedisplayed($pageid, $cmid){
	global $DB, $USER;
	
	$pagedisplayed = $DB->get_record('icontent_pages_displayed', array('pageid'=>$pageid, 'cmid'=>$cmid, 'userid'=>$USER->id), 'id, timecreated');
	
	if(empty($pagedisplayed)){
		
		$pagedisplayed = new stdClass;
		$pagedisplayed->pageid 		= $pageid;
		$pagedisplayed->cmid 		= $cmid;
		$pagedisplayed->userid 		= $USER->id;
		$pagedisplayed->timecreated	= time();
		
		return $DB->insert_record('icontent_pages_displayed', $pagedisplayed);
	}
	
	return $pagedisplayed;
}


/**
 * Consulta lista anotacoes de uma pagina.
 *
 * Returns array of pagenotes 
 *
 * @param  int $pageid
 * @param  int $cmid
 * @param  string $tab
 * @return object $pagenotes
 */
function icontent_get_pagenotes($pageid, $cmid, $tab){
	global $DB;
	
	return $DB->get_records('icontent_pages_notes', array('pageid'=>$pageid, 'cmid'=>$cmid, 'tab'=>$tab), 'path');
}

/**
 * Consulta curtida em uma pagina.
 *
 * Returns object of  {icontent_pages_notes_like}
 *
 * @param  int $pagenoteid
 * @param  int $userid
 * @param  int $cmid
 * @return object $pagenotelike
 */
 function icontent_get_pagenotelike($pagenoteid, $userid, $cmid){
	global $DB;
	
	return $DB->get_record('icontent_pages_notes_like', array('pagenoteid'=>$pagenoteid, 'userid'=>$userid, 'cmid'=>$cmid), 'id');
}

/**
 * Gera numero de uma nova pagina.
 *
 * Returns pagenum
 *
 * @param  int $icontentid
 * @return int pagenum
 */
 function icontent_count_pagenum($icontentid){
 	global $DB;
 	$sql = "SELECT Count(pagenum) AS countpagenum FROM {icontent_pages} WHERE icontentid = ?;";
	
 	$obj = $DB->get_record_sql($sql, array($icontentid));
	
	return $obj->countpagenum;
 }
 
 /**
 * Recupera a quantidade de curtidas de uma anotacao {icontent_pages_notes_like}.
 *
 * Returns count
 *
 * @param  int $pagenotelike
 * @return int count
 */
 function icontent_count_pagenotelike($pagenoteid){
 	global $DB;
	return $DB->count_records('icontent_pages_notes_like', array('pagenoteid'=>$pagenoteid));
 }
 
 /**
 * Recupera numero da pagina atraves de um Id da pagina.
 *
 * Returns pagenum
 *
 * @param  int $pageid
 * @return int pagenum
 */
 function icontent_get_pagenum_by_pageid($pageid){
 	global $DB;
 	$sql = "SELECT pagenum  FROM {icontent_pages} WHERE id = ?;";
	
 	$obj = $DB->get_record_sql($sql, array($pageid));
	
	return $obj->pagenum;
 }
 
 /**
 * Recupera o nivel de profundidade da anotacao.
 *
 * Returns levels
 *
 * @param  string $path
 * @return int $levels
 */
 function icontent_get_noteparentinglevels($path){
 	
	$countpath = count(explode('/', $path)) - 1;
	
	if(!$countpath)
		return 1;
	else if($countpath > 12)
		return 12;
	else
		return $countpath;
 }
 
  /**
 * Recupera usuario atraves de um ID.
 *
 * Returns object $user
 *
 * @param  int $pageid
 * @return object $user
 */
 function icontent_get_user_by_id($userid){
 	global $DB;
	
	return $DB->get_record('user', array('id'=>$userid), 'id, firstname, lastname, email, picture, firstnamephonetic, lastnamephonetic, middlename, alternatename, imagealt');
 }
  
/*****************************************************************\  
\************* METODOS QUE CRIAM E RETORNAM HTML *****************/
/*****************************************************************\
 /**
 * Metodo responsavel por criar barra de progresso
 * Return $progressbar
 * @param  object $objpage
 * @param  object $icontent
 * @param  object $context
 * @return string $progressbar
 */
 function icontent_make_progessbar($objpage, $icontent, $context){
  	return false;
  }
 /**
 * Metodo responsavel por criar area de comentarios das paginas
 * Returns notesarea
 * @param  object $objpage
 * @param  object $icontent
 * @return string $notesarea
 */
 function icontent_make_notesarea($objpage, $icontent){
 	global $OUTPUT, $USER;
	
	// Divisor page / notes
	$hr = html_writer::tag('hr', null). html_writer::link(null, null, array('name'=>'notesarea'));
 	// Title page
	$h4 = html_writer::tag('h4', get_string('doubtandnotes', 'mod_icontent'), array('class'=>'titlenotes'));
	// user image
	$picture = html_writer::tag('div', $OUTPUT->user_picture($USER, array('size'=>60, 'class'=> 'img-thumbnail')), array('class'=>'span1 userpicture'));
	// fields
	$textareanote = html_writer::tag('textarea', null, array('name'=>'comment', 'id'=>'idcommentnote', 'class'=>'span12', 'maxlength'=> '1024', 'required'=> 'required', 'placeholder'=> get_string('writenotes', 'mod_icontent')));
	$textareadoubt = html_writer::tag('textarea', null, array('name'=>'comment', 'id'=>'idcommentdoubt', 'class'=>'span12','maxlength'=> '1024', 'required'=> 'required', 'placeholder'=> get_string('writedoubt', 'mod_icontent')));
	$checkprivate = html_writer::tag('input', null, array('name'=>'private', 'type'=>'checkbox', 'id'=>'idprivate'));
	$labelprivate = html_writer::tag('label', get_string('private', 'mod_icontent'), array('for'=>'idprivate'));
	$spanprivate = html_writer::tag('span', $checkprivate. $labelprivate, array('class'=>'fieldprivate'));
	$checkfeatured = html_writer::tag('input', null, array('name'=>'featured', 'type'=>'checkbox', 'id'=>'idfeatured'));
	$labelfeatured = html_writer::tag('label', get_string('featured', 'mod_icontent'), array('for'=>'idfeatured'));
	$spanfeatured = html_writer::tag('span', $checkfeatured. $labelfeatured, array('class'=>'fieldfeatured'));
	$checkdoubttutor = html_writer::tag('input', null, array('name'=>'doubttutor', 'type'=>'checkbox', 'id'=>'iddoubttutor'));
	$labeldoubttutor = html_writer::tag('label', get_string('doubttutor', 'mod_icontent'), array('for'=>'iddoubttutor'));
	$spandoubttutor = html_writer::tag('span', $checkdoubttutor. $labeldoubttutor, array('class'=>'fielddoubttutor'));
	$btnsavenote = html_writer::tag('button', get_string('save','mod_icontent'), array('class'=>'btn btn-primary pull-right', 'id' => 'idbtnsavenote', 'data-pageid'=>$objpage->id,'data-cmid'=>$objpage->cmid, 'data-sesskey' => sesskey()));
	$btnsavedoubt = html_writer::tag('button', get_string('save','mod_icontent'), array('class'=>'btn btn-primary pull-right', 'id' => 'idbtnsavedoubt', 'data-pageid'=>$objpage->id,'data-cmid'=>$objpage->cmid, 'data-sesskey' => sesskey()));
	
	$datapagenotesnote = icontent_get_pagenotes($objpage->id, $objpage->cmid, 'note');	// data page notes note
	$datapagenotesdoubt = icontent_get_pagenotes($objpage->id, $objpage->cmid, 'doubt');	// data page notes doubt
	$pagenotesnote = html_writer::div(icontent_make_listnotespage($datapagenotesnote, $icontent, $objpage), 'pagenotesnote', array('id'=>'idpagenotesnote'));
	$pagenotesdoubt = html_writer::div(icontent_make_listnotespage($datapagenotesdoubt, $icontent, $objpage), 'pagenotesdoubt', array('id'=>'idpagenotesdoubt'));
	
	$fieldsnote = html_writer::tag('div', $textareanote. $spanprivate. $spanfeatured. $btnsavenote. $pagenotesnote, array('class'=>'span11'));
	$fieldsdoubt = html_writer::tag('div', $textareadoubt. $spandoubttutor. $btnsavedoubt. $pagenotesdoubt, array('class'=>'span11'));
	
	// Form
	$formnote = html_writer::tag('div', $picture . $fieldsnote, array('class'=>'fields'));
	$formdoubt = html_writer::tag('div', $picture . $fieldsdoubt, array('class'=>'fields'));
	
	// TAB NAVS
	$note = html_writer::tag('li', 
		html_writer::link('#note', get_string('note', 'icontent', count($datapagenotesnote)), array('id'=>'note-tab', 'aria-expanded' => 'true', 'aria-controls'=>'note' ,'role'=>'tab', 'data-toggle'=>'tab')), 
	array('class'=>'active', 'role'=>'presentation'));
	$doubt = html_writer::tag('li', 
		html_writer::link('#doubt', get_string('doubt', 'icontent', count($datapagenotesdoubt)), array('id'=>'doubt-tab', 'aria-expanded' => 'false', 'aria-controls'=>'doubt' ,'role'=>'tab', 'data-toggle'=>'tab')), 
	array('class'=>'', 'role'=>'presentation'));
	
	$tabnav = html_writer::tag('ul', $note .$doubt, array('class'=> 'nav nav-tabs', 'id'=>'tabnav'));
	
	// TAB CONTENT
	$icontentnote = html_writer::div($formnote,'tab-pane active', array('role'=>'tabpanel', 'id'=>'note'));
	$icontentdoubt = html_writer::div($formdoubt, 'tab-pane', array('role'=>'tabpanel', 'id'=>'doubt'));
	$tabicontent = html_writer::div($icontentnote. $icontentdoubt, 'tab-content', array('id'=>'idtabicontent'));
	
	// Area Notes
	$notesarea = html_writer::tag('div', $hr. $h4. $tabnav. $tabicontent, array('class'=>'row-fluid notesarea'));
	
 	// return 
 	return $notesarea;
 }

/**
 * Gera lista de anotacoes.
 *
 * @param  object $pagenotes
 * @param  object $icontent
 * @param  object $page
 * @return string $listnotes
 */
 function icontent_make_listnotespage($pagenotes, $icontent, $page){
 	global $OUTPUT, $CFG;
 	if(!empty($pagenotes)){
 		//$scriptsjs = html_writer::script(false, new moodle_url('js/src/actions.js'));
 		$divnote = '';
 		foreach ($pagenotes as $pagenote) {
 			// Object user
 			$user = icontent_get_user_by_id($pagenote->userid);
			// Get picture
			$picture = $OUTPUT->user_picture($user, array('size'=>35, 'class'=> 'img-thumbnail pull-left'));
			// Note header
			$linkfirstname = html_writer::link($CFG->wwwroot.'/user/view.php?id='.$user->id.'&course='.$icontent->course, $user->firstname, array('title'=>$user->firstname));
 			$noteon = html_writer::tag('em', get_string('notedon', 'icontent'), array('class'=>'noteon'));
			$notepagetitle = html_writer::span($page->title, 'notepagetitle');
 			$noteheader = html_writer::div($linkfirstname. $noteon. $notepagetitle, 'noteheader');
			// Note comments
			$notecomment = html_writer::div($pagenote->comment, 'notecomment', array('data-pagenoteid'=>$pagenote->id, 'data-cmid'=>$pagenote->cmid, 'data-sesskey' => sesskey()));
			// Note footer
			$noteedit = html_writer::link(null, "<i class='fa fa-pencil'></i>".get_string('edit', 'icontent'), array('class'=>'editnote'));
			$noteremove = html_writer::link('#', "<i class='fa fa-times'></i>".get_string('remove', 'icontent'));
			$notelike = icontent_make_likeunlike($page, $pagenote);
			$notereply = html_writer::link(null, "<i class='fa fa-reply-all'></i>".get_string('reply', 'icontent'), array('class'=>'replynote'));
 			$notedate = html_writer::tag('span', userdate($pagenote->timecreated), array('class'=>'notedate pull-right'));
			$notefooter = html_writer::div($noteedit. $noteremove. $notereply. $notelike. $notedate, 'notefooter');
			// verify path levels
			$pathlevels = icontent_get_noteparentinglevels($pagenote->path);

			// Div list page notes
			$noterowicontent = html_writer::div($noteheader. $notecomment. $notefooter, 'noterowicontent');
			$divnote .= html_writer::div($picture. $noterowicontent, "pagenoterow level-{$pathlevels}", array('data-level'=>$pathlevels));
		 }
		
		$divnotes = html_writer::div($divnote, 'span notelist');
		return $divnotes;
 	}
	return html_writer::div(get_string('nonotes', 'icontent'));
 }

/**
 * Gera resposta de anotacao.
 *
 * @param  object $pagenote
 * @param  object $icontent
 * @return string $pagenotereply
 */
 function icontent_make_pagenotereply($pagenote, $icontent){
 	global $OUTPUT, $CFG;
	
	$user = icontent_get_user_by_id($pagenote->userid);
	
	$picture = $OUTPUT->user_picture($user, array('size'=>30, 'class'=> 'img-thumbnail pull-left'));
	// Note header
	$linkfirstname = html_writer::link($CFG->wwwroot.'/user/view.php?id='.$user->id.'&course='.$icontent->course, $user->firstname, array('title'=>$user->firstname));
	$noteon = html_writer::tag('em', ' '.strtolower(get_string('respond', 'icontent')).': ', array('class'=>'noteon'));
	$noteheader = html_writer::div($linkfirstname. $noteon, 'noteheader');
	// Note comments
	$notecomment = html_writer::div($pagenote->comment, 'notecomment', array('data-pagenoteid'=>$pagenote->id, 'data-cmid'=>$pagenote->cmid, 'data-sesskey' => sesskey()));
	// Note footer
	$noteedit = html_writer::link(null, "<i class='fa fa-pencil'></i>".get_string('edit', 'icontent'), array('class'=>'editnote'));
	$noteremove = html_writer::link('#', "<i class='fa fa-times'></i>".get_string('remove', 'icontent'));
	$notelike = icontent_make_likeunlike($page, $pagenote);
	$notereply = html_writer::link(null, "<i class='fa fa-reply-all'></i>".get_string('reply', 'icontent'), array('class'=>'replynote'));
	$notedate = html_writer::tag('span', userdate($pagenote->timecreated), array('class'=>'notedate pull-right'));
	$notefooter = html_writer::div($noteedit. $noteremove. $notereply. $notelike. $notedate, 'notefooter');
	
	// verify path levels
	$pathlevels = icontent_get_noteparentinglevels($pagenote->path);

	// Div list page notes
	$noterowicontent = html_writer::div($noteheader. $notecomment. $notefooter, 'noterowicontent');
	
	// return reply
	return html_writer::div($picture. $noterowicontent, "pagenoterow level-{$pathlevels}", array('data-level'=>$pathlevels));
 }

/**
 * Gera link like e unlike.
 *
 * @param  object $page
 * @param  object $pagenote
 * @return string $likeunlike
 */
 function icontent_make_likeunlike($page, $pagenote){
 	global $USER;
	$pagenotelike = icontent_get_pagenotelike($pagenote->id, $USER->id, $page->cmid);
	
	$countlikes = icontent_count_pagenotelike($pagenote->id);
	$notelinklabel = html_writer::span(get_string('like', 'icontent', $countlikes));
	
 	if(!empty($pagenotelike)){
 		$notelinklabel = html_writer::span(get_string('unlike', 'icontent', $countlikes));
 	}
 	
	return html_writer::link(null, "<i class='fa fa-star-o'></i>".$notelinklabel,
		array(
			'class'=>'likenote',
			'data-cmid'=>$page->cmid,
			'data-pagenoteid'=>$pagenote->id,
			'data-sesskey' => sesskey()
		)
	);
 }
/**
 * Gera conteudo de uma pagina e retorna objeto.
 *
 * @param  int 		$pagenum || $startpage
 * @param  object $icontent
 * @param  object $context
 * @return object	$fullpage
 */
 function icontent_get_fullpageicontent($pagenum, $icontent, $context){
 	global $DB, $CFG;
	// PENDENTE: Criar rotina para gravar logs...
	
	$scriptsjs = html_writer::script(false, new moodle_url('js/src/actions.js'));
	
 	$objpage = $DB->get_record('icontent_pages', array('pagenum' => $pagenum, 'icontentid' => $icontent->id));
	
	// Registra acesso do usuario na pagina
	icontent_add_pagedisplayed($objpage->id, $objpage->cmid);
	
	// Elementos toolbar
	$comments = html_writer::link('#notesarea', '<i class="fa fa-comments fa-lg"></i>', array('title' => s(get_string('comments', 'icontent')), 'class'=>'icon-comments','data-toggle'=> 'tooltip', 'data-placement'=> 'top', 'data-pagenum' => $objpage->pagenum, 'data-cmid' => $objpage->cmid, 'data-sesskey' => sesskey()));
	$toolbarpage = html_writer::tag('div', $comments.' <i class="fa fa-square-o fa-lg"></i> <i class="fa fa-adjust fa-lg"> </i>', array('class'=>'toolbarpage '));
	
	// Adicionando elemento titulo da pagina
	$title = html_writer::tag('h3', '<i class="fa fa-hand-o-right"></i> '.$objpage->title, array('class'=>'pagetitle'));
	
	// Tratando arquivos da pagina e preparando conteudo
	$objpage->pageicontent = file_rewrite_pluginfile_urls($objpage->pageicontent, 'pluginfile.php', $context->id, 'mod_icontent', 'page', $objpage->id);
	$objpage->pageicontent = format_text($objpage->pageicontent, $objpage->pageicontentformat, array('noclean'=>true, 'overflowdiv'=>true, 'context'=>$context));
	
	// Adicionando elemento que contera a numero da pagina
	$npage = html_writer::tag('div', get_string('page', 'icontent', $objpage->pagenum), array('class'=>'pagenum'));
	
	// Progress bar
	$progbar = icontent_make_progessbar($objpage, $icontent, $context);
	
	// form notes
	$notesarea = icontent_make_notesarea($objpage, $icontent);
	
	/* // Adicionando passadores de pagina
	$previous = html_writer::link('#', "<i class='fa fa-angle-left'></i> ".get_string('previous', 'icontent'), array('title' => s(get_string('pageprevious', 'icontent')), 'class'=>'previous span6 load-page page'.$objpage->pagenum, 'data-pagenum' => ($objpage->pagenum - 1), 'data-cmid' => $objpage->cmid, 'data-sesskey' => sesskey()));
	$next = html_writer::link('#', get_string('next', 'icontent')." <i class='fa fa-angle-right'></i>", array('title' => s(get_string('nextpage', 'icontent')), 'class'=>'next span6 load-page page'.$objpage->pagenum, 'data-pagenum' => ($objpage->pagenum + 1), 'data-cmid' => $objpage->cmid, 'data-sesskey' => sesskey()));
	
	$controlbuttons = html_writer::tag('div', $previous. $next, array('class'=>'pagenavbar row'));*/
	
	// Preparando conteudo da pagina para retorno
	$objpage->fullpageicontent = html_writer::tag('div', $toolbarpage. $title. $objpage->pageicontent . $npage. $notesarea. $scriptsjs, array('class'=>'fulltextpage', 'data-pagenum' => $objpage->pagenum, 'style'=> icontent_get_page_style($icontent, $objpage, $context)));
	
	// Destruindo propriedade, pois ela foi passada para a propriedade fullpageicontent na linha acima.
	unset($objpage->pageicontent);
	
	// retornando objeto
	return $objpage;
 }
