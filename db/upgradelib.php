<?php


function add_racaztecher_capability(){
	global $DB;
	
	if ($rakaz_tikshuv = $DB->get_field('role', 'id', array('shortname'=>'rakaz_tikshuv'))) {
		$context = context_system::instance();
	
		assign_capability('mod/icontent:likenote', CAP_ALLOW, $rakaz_tikshuv, $context);
	
	
	
	}
}
function add_editingteacher_capability(){
	global $DB;
	
	if ($editingteacher = $DB->get_field('role', 'id', array('shortname'=>'editingteacher'))) {
		$context = context_system::instance();
	
		assign_capability('mod/icontent:likenote', CAP_ALLOW, $editingteacher, $context);
		
		
		
	}
}
function add_manager_capability(){
	global $DB;
	
	if ($manger = $DB->get_field('role', 'id', array('shortname'=>'manager'))) {
		$context = context_system::instance();
		
		assign_capability('mod/icontent:likenotes', CAP_ALLOW, $manger, $context);
		
	}
}
function add_shoham_capability() {
	global $DB;

	if ($shoham = $DB->get_field('role', 'id', array('shortname'=>'shoham'))) {
		$context = context_system::instance();
		
		assign_capability('mod/icontent:addinstance', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:edit', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:grade', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:manage', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:view', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:likenotes', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:editnotes', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:replynotes', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:removenotes', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:checkboxprivatenotes', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:checkboxfeaturednotes', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:checkboxdoubttutornotes', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:newquestion', CAP_ALLOW, $shoham, $context);
		assign_capability('mod/icontent:answerquestionstryagain', CAP_ALLOW, $shoham, $context);
		
		
	}
}

function add_mashov_capability(){
	global $DB;
	if ($mashov = $DB->get_field('role', 'id', array('shortname'=>'msho_b'))) {
		$context = context_system::instance();
	
		assign_capability('mod/icontent:addinstance', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:edit', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:grade', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:manage', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:view', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:likenotes', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:editnotes', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:replynotes', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:removenotes', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:checkboxprivatenotes', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:checkboxfeaturednotes', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:checkboxdoubttutornotes', CAP_ALLOW, $mashov , $context);
		assign_capability('mod/icontent:newquestion', CAP_ALLOW, $mashov, $context);
		assign_capability('mod/icontent:answerquestionstryagain', CAP_ALLOW, $mashov, $context);
	
	
	}
}
	
	function add_studentsupport_capability(){
		global $DB;
		
		if ($student_support = $DB->get_field('role', 'id', array('shortname'=>'student_support'))) {
			$context = context_system::instance();
			
		
			assign_capability('mod/icontent:view', CAP_ALLOW, $student_support, $context);
			assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $student_support, $context);
			assign_capability('mod/icontent:likenotes', CAP_ALLOW, $student_support, $context);
			assign_capability('mod/icontent:editnotes', CAP_ALLOW, $student_support, $context);
			assign_capability('mod/icontent:replynotes', CAP_ALLOW, $student_support, $context);
			assign_capability('mod/icontent:removenotes', CAP_ALLOW, $student_support, $context);
			assign_capability('mod/icontent:checkboxprivatenotes', CAP_ALLOW, $student_support, $context);
			assign_capability('mod/icontent:checkboxfeaturednotes', CAP_ALLOW, $student_support, $context);
			assign_capability('mod/icontent:checkboxdoubttutornotes', CAP_ALLOW, $student_support , $context);
			assign_capability('mod/icontent:answerquestionstryagain', CAP_ALLOW, $student_support, $context);
			
			
		}
	}


	function add_superguest_capability(){
		global $DB;
		
		if ($superguest = $DB->get_field('role', 'id', array('shortname'=>'superguest'))) {
			$context = context_system::instance();
				
			assign_capability('mod/icontent:answerquestionstryagain', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:checkboxdoubttutornotes', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:checkboxfeaturednotes', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:checkboxprivatenotes', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:editnotes', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:likenotes', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:removenotes', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:replynotes', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:view', CAP_ALLOW, $superguest, $context);
			assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $superguest, $context);
		
		
		}
	}
	function add_superguest_1_capability(){
		
		global $DB;
		
		if ($superguest_1 = $DB->get_field('role', 'id', array('shortname'=>'superguest_1'))) {
			$context = context_system::instance();
			
			assign_capability('mod/icontent:view', CAP_ALLOW, $superguest_1, $context);
			assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $superguest_1, $context);
				
				
		}
		
	}
	
	function add_openu_guest(){
		
		global $DB;
		
		if ($openu_guest = $DB->get_field('role', 'id', array('shortname'=>'openu_guest'))) {
			$context = context_system::instance();
				
			assign_capability('mod/icontent:view', CAP_ALLOW, $openu_guest, $context);
			assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $openu_guest, $context);
		
		}
	}
	
	function add_teacher_capability(){
		global $DB;
		
		if ($teacher = $DB->get_field('role', 'id', array('shortname'=>'teacher'))) {
			$context = context_system::instance();
		
			assign_capability('mod/icontent:likenotes', CAP_ALLOW, $teacher, $context);
		
		}
		
		
	}
	function add_extended_openu_guest_capability(){
		global $DB;

		if ($extended_openu_guest= $DB->get_field('role', 'id', array('shortname'=>'extended_openu_guest'))) {
			$context = context_system::instance();
		
			assign_capability('mod/icontent:answerquestionstryagain', CAP_ALLOW, $extended_openu_guest, $context);
			assign_capability('mod/icontent:checkboxdoubttutornotes', CAP_ALLOW, $extended_openu_guest, $context);
			assign_capability('mod/icontent:checkboxfeaturednotes', CAP_ALLOW, $extended_openu_guest, $context);
			assign_capability('mod/icontent:checkboxprivatenotes', CAP_ALLOW, $extended_openu_guest, $context);
			assign_capability('mod/icontent:editnotes', CAP_ALLOW, $extended_openu_guest, $context);
			assign_capability('mod/icontent:removenotes', CAP_ALLOW, $extended_openu_guest, $context);
			assign_capability('mod/icontent:view', CAP_ALLOW, $extended_openu_guest, $context);
			assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $extended_openu_guest, $context);
		
		}
		
		
		
		
	}
	
	function add_guest_capability(){
		global $DB;
		
		if ($guest = $DB->get_field('role', 'id', array('shortname'=>'guest'))) {
			$context = context_system::instance();
			
            assign_capability('mod/icontent:view', CAP_ALLOW, $guest, $context);
			assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $guest, $context);				
		}
		
	}
	
	function add_openu_guest_capability(){
		
		global $DB;
		
		if ($openu_guest = $DB->get_field('role', 'id', array('shortname'=>'openu_guest'))) {
			$context = context_system::instance();
		
				
			assign_capability('mod/icontent:view', CAP_ALLOW, $openu_guest, $context);
			assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $openu_guest, $context);
		
		
		}
	}
	function  add_user_capability(){
		global $DB;
		
		if ($user = $DB->get_field('role', 'id', array('shortname'=>'user'))) {
			$context = context_system::instance();
				
			
            assign_capability('mod/icontent:view', CAP_ALLOW, $user, $context);
			assign_capability('mod/icontent:viewnotes', CAP_ALLOW, $user, $context);			
				
		
		}
		
	}
	
	function remove_teacher_capability(){
		
		global $DB;
		
		if ($teacher = $DB->get_field('role', 'id', array('shortname'=>'teacher'))) {
			$context = context_system::instance();
			
			
			unassign_capability('mod/icontent:edit', $teacher, $context);
			unassign_capability('mod/icontent:manage', $teacher, $context);
			unassign_capability('mod/icontent:grade', $teacher, $context);
			unassign_capability('mod/icontent:newquestion', $teacher, $context);
				
		}
	}
	
	function remove_extended_openu_guest_capability(){
		global $DB;
		
		if ($extended_openu_guest = $DB->get_field('role', 'id', array('shortname'=>'extended_openu_guest'))) {
			$context = context_system::instance();
			
			unassign_capability('mod/icontent:edit', $extended_openu_guest, $context);
			unassign_capability('mod/icontent:manage', $extended_openu_guest, $context);
			unassign_capability('mod/icontent:grade', $extended_openu_guest, $context);
			unassign_capability('mod/icontent:newquestion', $extended_openu_guest, $context);
			
			
		}
		
	}
	