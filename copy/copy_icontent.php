<?php
/**
 * @package   mod_icontent
 * @category  copy
 * @copyright 2015 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class copy_icontent extends copy_activity_task {


	/* copy icontent mod from source course to target course */
	public function copy_it(){
		global $DB;

		$data = $this->get_course_module_data();

		$source_icontent = $this->get_source_icontent();

		if(!$source_icontent){
			throw new copycourse_exception(" icontent instance for module " . $this->oldmoduleid . " does not exists ");
			return;
		}

		$this->oldactivityid = $source_icontent->id;
		$sourcemodule = $data->id;
			
		$data->module = $this->module;
		$data->id = '';
		$data->instance='';
		$data->coursemodule='';

		/** Add icontent fields **/
		$data->course = $source_icontent->course;
		$data->name = $source_icontent->name;
		$data->intro = $source_icontent->intro;
		$data->introformat = $source_icontent->introformat;
		$data->timecreated = $source_icontent->timecreated;
		$data->timemodified = $source_icontent->timemodified;
		$data->grade = $source_icontent->grade;
		$data->scale = $source_icontent->scale;
		$data->bgimage = $source_icontent->bgimage;
		$data->bgcolor = $source_icontent->bgcolor;
		$data->bordercolor = $source_icontent->bordercolor;
		$data->borderwidth = $source_icontent->borderwidth;
		$data->evaluative = $source_icontent->evaluative;
		$data->maxpages = $source_icontent->maxpages;
		$data->progressbar = $source_icontent->progressbar;
		$data->shownotesarea = $source_icontent->shownotesarea;
		$data->maxnotesperpages = $source_icontent->maxnotesperpages;
		$data->copyright = $source_icontent->copyright;

		
		$data->timeopen = 0;
		$data->timeclose = 0;
		
			
		course_create_sections_if_missing($this->targetcourseobj, $data->section);
		
		$data_cm = add_oumoduleinfo($data, $this->targetcourseobj,$this->type,  $this->oldmoduleid, null);
		
		if(!$data_cm){
			throw new copycourse_exception("Error inserting icontent sourceid " . $source_icontent->id );
			return;
		}
		$data->id = $data_cm->instance;
		
		$cm = get_coursemodule_from_instance('icontent', $data->id);
			
		$this->moduleid = $cm->id ;
		$this->activityid = $data->id;
			
		$sourcecontext = context_module::instance($sourcemodule);
		$targetcontext = context_module::instance($cm->id);

		$this->set_mapping('course_module', $sourcemodule, $cm->id, 'icontent');
		$this->set_mapping('icontent',$source_icontent->id,  $this->activityid );
		$this->set_mapping('context',$sourcecontext->id, $targetcontext->id );

		$this->add_related_files('mod_icontent', 'intro', $sourcecontext->id, $targetcontext->id);
//echo '*******'.$source_icontent->id.'**********************'.$this->activityid.'**********';
//echo '*******'.$sourcecontext->id.'**********************'.$cm->id.'**********';
		list($copied_icontent_pages) = $this->copy_icontent_pages($cm->id,$source_icontent->id,$this->activityid);
		//$this->copy_icontent_grades($cm->id,$source_icontent->id,$this->activityid);
		if($copied_icontent_pages){
			$this->copy_icontent_pages_dspl($cm->id,$copied_icontent_pages);
			list($copied_icontent_pages_notes) = $this->copy_icontent_pages_notes($cm->id,$copied_icontent_pages);
			if($copied_icontent_pages_notes){
				$this->copy_icontent_pages_notes_like($cm->id,$copied_icontent_pages_notes);
			};
			list($copied_icontent_pages_questions) = $this->copy_icontent_pages_questions($cm->id,$copied_icontent_pages);	
			if($copied_icontent_pages_questions){
				//$this->copy_icontent_quest_attempt($cm->id,$copied_icontent_pages_questions);
				$this->copy_icontent_quest_drafts($cm->id,$copied_icontent_pages_questions);
			}
		}
	}
	
	
	/**
	 * Define the contents in the activity that must be
	 * processed by the link decoder
	 */
	static public function define_decode_content() {
		$contents = array();
	echo 'define_decode_content';
		$contents[] = new copy_decode_content('icontent_pages', array('pageicontent'),
				'copy_icontent');
		
		$contents[] = new copy_decode_content('icontent_question_drafts',
				array('answertext'), 'copy_icontent');
	
		return $contents;
	
	}
	
	/**
	 *
	 * decode links to mod_icontent
	 */
	static public function decode_content_links($content, $copycourseid) {
		global $CFG, $DB;
		
		$pos = strpos($content, $CFG->wwwroot);
		if($pos === false){
			//nothing to do
			return $content;
		}
		$base = preg_quote( $CFG->wwwroot, '/');
	
		//for icontent/view url
		$query = "SELECT sourceid, targetid, related_table
				 FROM {local_ouil_copycourse_items}
		         WHERE  copyuniqueid = ? and tablename = ? and related_table = ?";
	
		$records = $DB->get_records_sql($query,
				array($copycourseid, 'course_module', 'icontent'));
		if($records){
	
			foreach($records as $record){
	
				//search and replace icontent view
				$searchstring = '(' . $base . '\/mod\/icontent\/view\.php\?id=)'. $record->sourceid;
				$replacestring = $CFG->wwwroot . '/mod/icontent/view.php?id=' . $record->targetid;
	
				//add search and replace to search and replace array
				$replacments[$searchstring] = $replacestring;
			}
		}
		//index icontent
		$query  =  "Select sourceid, targetid from {local_ouil_copycourse_items}
		where  copyuniqueid = ? and tablename = ?";
		$record = $DB->get_record_sql($query, array($copycourseid, 'course'));
	
		if($record){
			//search and replace icontent view
			$searchstring = '(' . $base . '\/mod\/icontent\/index\.php\?id=)'. $record->sourceid;
			$replacestring = $CFG->wwwroot . '/mod/icontent/index.php?id=' . $record->targetid;
				
			//add search and replace to search and replace array
			$replacments[$searchstring] = $replacestring;
		}
	
		 //link to icontent by q
		 $record = $DB->get_record_sql($query, array('$copycourseid', 'icontent'));
		 if($record){
		 	$searchstring = '(' . $base . '\/mod\/icontent\/view\.php\?q=)'. $record->sourceid;
		 	$replacestring = $CFG->wwwroot . '/mod/icontent/view.php?q=' . $record->targetid;
		 	
		 	//add search and replace to search and replace array
		 	$replacments[$searchstring] = $replacestring;
		 }
		//link to file
		$records = $DB->get_records_sql($query, array($copycourseid, 'context'));
		if($records){
			foreach($records as $record){
				//search and replace file view
	
				$searchstring =  $base . '\/pluginfile\.php\/' . $record->sourceid . '\/mod_icontent\/';
				$replacestring = $CFG->wwwroot . '/pluginfile.php/' . $record->targetid . '/mod_icontent/';
	
				//add search and replace to search and replace array
				$replacments[$searchstring] = $replacestring;
			}
		}
		//replace content
		foreach($replacments as $search => $replace){
	
			$content = preg_replace('/'. $search . '/', $replace, $content)   ;
	
		}
	
	
		return $content;
	}

/**********************************PRIVATE**********************************/
	
	private function get_source_icontent(){
		global $DB;
		
		$query = "Select i.* from {icontent} i JOIN {course_modules} m
				 on (i.id = m.instance)
				 JOIN {modules} d on ( d.id = m.module and d.name = 'icontent')
				  and m.id = :moduleid ";
		
		$record = $DB->get_record_sql($query, array('moduleid'=> $this->oldmoduleid));
		
		return $record;
	}

	private function copy_icontent_pages($targetcontext,$copied_icontent_record_old,$copied_icontent_record_new){
	    	global $DB;
	    	$copied_icontent_pages = null;
	    	
	    	$query = "SELECT *  FROM {icontent_pages}  WHERE icontentid = ?";
	    	
	    	$records = $DB->get_records_sql($query,array($copied_icontent_record_old));
	    	if($records){
    		$copied_icontent_pages = array();
	    		foreach($records as $record){
	    			$oldid = $record->id;
	    			unset($record->id);
	    			$record->icontentid = $copied_icontent_record_new;
				$record->cmid = $targetcontext;
	    			$newid = $DB->insert_record('icontent_pages',$record);
	    			$copied_icontent_pages[$oldid] = $newid;
				$this->add_related_files('mod_icontent', 'page', $oldid, $newid);
				$this->set_mapping('icontent_pages',$oldid,  $newid );
	    		}
	    	}
    
	    	if($copied_icontent_pages){
	    		return array($copied_icontent_pages);
	    		
	    	}else{
	    		return null;
	    	}
	}

	private function copy_icontent_pages_notes($targetcontext,$copied_icontent_pages){
	    	global $DB;
		$flipped_pages = array_flip($copied_icontent_pages);
	    	$copied_icontent_pages_notes = null;
	    	
	    	$query = "SELECT *  FROM {icontent_pages_notes}  WHERE pageid = ?";
	    	
	    	$records = $DB->get_records_sql($query,$flipped_pages);
	    	
	    	if($records){
	    		$copied_icontent_pages_notes = array();
	    		foreach($records as $record){
	    			$oldid = $record->id;
	    			unset($record->id);
				$old_page_id = $record->pageid;
				$record->cmid = $targetcontext;
				$record->pageid = $copied_icontent_pages[$old_page_id];
	    			$newid = $DB->insert_record('icontent_pages_notes',$record);
	    			$copied_icontent_pages_notes[$oldid] = $newid;
	    		}
	    	}
    
	    	if($copied_icontent_pages_notes){
	    		return array($copied_icontent_pages_notes);
	    		
	    	}else{
	    		return null;
	    	}
	}

	private function copy_icontent_pages_questions($targetcontext,$copied_icontent_pages){
	    	global $DB;
		$flipped_pages = array_flip($copied_icontent_pages);
	    	$copied_icontent_pages_questions = null;
	    	
	    	$query = "SELECT *  FROM {icontent_pages_questions}  WHERE pageid = ?";
	    	
	    	$records = $DB->get_records_sql($query,$flipped_pages);
	    	
	    	if($records){
	    		$copied_icontent_pages_questions = array();
	    		foreach($records as $record){
	    			$oldid = $record->id;
	    			unset($record->id);
				$old_page_id = $record->pageid;
				$record->pageid = $copied_icontent_pages[$old_page_id];
				$record->cmid = $targetcontext;
	    			$newid = $DB->insert_record('icontent_pages_questions',$record);
	    			$copied_icontent_pages_questions[$oldid] = $newid;
	    		}
	    	}
    
	    	if($copied_icontent_pages_questions){
	    		return array($copied_icontent_pages_questions);
	    		
	    	}else{
	    		return null;
	    	}
	}

	private function copy_icontent_grades($targetcontext,$copied_icontent_record_old,$copied_icontent_record_new){
		global $DB;
		
		$query = "SELECT *  FROM {icontent_grades} WHERE icontentid = ?";
		
		$records = $DB->get_records_sql($query, array($copied_icontent_record_old));

		if($records){
	    		foreach($records as $record){
	    			$oldid = $record->id;
	    			unset($record->id);
				$record->cmid = $targetcontext;
	    			$newid = $DB->insert_record('icontent_grades',$record);
	    			if(!$newid){
					$this->loger->logit("Failed to add record to icontent_grades table. The record: " . $record);
				}
	    		}
	    	}
	}

	private function copy_icontent_pages_dspl($targetcontext,$copied_icontent_pages){
		global $DB;
		$flipped_pages = array_flip($copied_icontent_pages);
		$query = "SELECT *  FROM {icontent_pages_displayed} WHERE pageid = ?";
		
		$records = $DB->get_records_sql($query, $flipped_pages);

		if($records){
	    		foreach($records as $record){
	    			$oldid = $record->id;
	    			unset($record->id);
				$old_page_id = $record->pageid;
				$record->pageid = $copied_icontent_pages[$old_page_id];
				$record->cmid = $targetcontext;
	    			$newid = $DB->insert_record('icontent_pages_displayed',$record);
				if(!$newid){
					$this->loger->logit("Failed to add record to icontent_pages_displayed table. The record: " . $record);
				}
	    		}
	    	}
	}

	private function copy_icontent_pages_notes_like($targetcontext,$copied_icontent_pages_notes){
		global $DB;
		$flipped_pages = array_flip($copied_icontent_pages_notes);
		$query = "SELECT *  FROM {icontent_pages_notes_like} WHERE pagenoteid = ?";
		
		$records = $DB->get_records_sql($query, $flipped_pages);

		if($records){
	    		foreach($records as $record){
	    			$oldid = $record->id;
	    			unset($record->id);
				$old_page_note_id = $record->pagenoteid;
				$record->pagenoteid = $copied_icontent_pages_notes[$old_page_note_id ];
				$record->cmid = $targetcontext;
	    			$newid = $DB->insert_record('icontent_pages_notes_like',$record);
				if(!$newid){
					$this->loger->logit("Failed to add record to icontent_pages_displayed table. The record: " . $record);
				}	    			
	    		}
	    	}
	}

	private function copy_icontent_quest_attempt($targetcontext,$copied_icontent_pages_questions){
		global $DB;
		$flipped_pages = array_flip($copied_icontent_pages_questions);
		$query = "SELECT *  FROM {icontent_question_attempts} WHERE pagesquestionsid = ?";
		
		$records = $DB->get_records_sql($query, $flipped_pages);

		if($records){
	    		foreach($records as $record){
	    			$oldid = $record->id;
	    			unset($record->id);
				$old_page_q_id = $record->pagesquestionsid;
				$record->pagesquestionsid = $copied_icontent_pages_questions[$old_page_q_id];
				$record->cmid = $targetcontext;
	    			$newid = $DB->insert_record('icontent_question_attempts',$record);
				if(!$newid){
					$this->loger->logit("Failed to add record to icontent_question_attempts` table. The record: " . $record);
				}	    			
	    		}
	    	}
	}
	private function copy_icontent_quest_drafts($targetcontext,$copied_icontent_pages_questions){
		global $DB;
		$flipped_pages = array_flip($copied_icontent_pages_questions);
		$query = "SELECT *  FROM {icontent_question_drafts} WHERE pagesquestionsid = ?";
		
		$records = $DB->get_records_sql($query, $flipped_pages);

		if($records){
	    		foreach($records as $record){
	    			$oldid = $record->id;
	    			unset($record->id);
				$old_page_q_id = $record->pagesquestionsid;
				$record->pagesquestionsid = $copied_icontent_pages_questions[$old_page_q_id];
				$record->cmid = $targetcontext;
	    			$newid = $DB->insert_record('icontent_question_drafts',$record);
				if(!$newid){
					$this->loger->logit("Failed to add record to icontent_question_drafts` table. The record: " . $record);
				}
				$this->add_related_files('mod_icontent', 'draft', $oldid, $newid);
				$this->set_mapping('icontent_question_drafts',$oldid,  $newid );	    			
	    		}
	    	}
	}

}
