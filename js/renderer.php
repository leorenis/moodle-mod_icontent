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
 * This file contains a renderer for the icontent plugin
 *
 * @package    mod_icontent
 * @copyright  2016-2015 Leo Santos {@link http://github.com/leorenis}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * iContent module renderer class
 * TODO create renderer
 */
class mod_icontent_renderer extends plugin_renderer_base {
	
	/**
	 * Renders the iContent page header CSS. {@link http://fontawesome.io}
	 *
	 * @return string
	 */
	public function icontent_requires_css(){
        $this->page->requires->css('/mod/icontent/styles/font-awesome-4.6.2/css/font-awesome.min.css');
        $this->page->requires->css('/mod/icontent/styles/bootstrap/hacks.css');
	}
	
	/**
	 * Renders the iContent page header External JS. {@link http://jqueryui.com}, {@link http://getbootstrap.com}.
	 *
	 */
	public function icontent_requires_external_js(){
        /*$this->page->requires->js('/mod/icontent/js/jquery/jquery-1.11.3.min.js', true); //jquery-3.1.0.min
        $this->page->requires->js('/mod/icontent/js/jquery/jquery-ui-1.11.4.min.js', true);
        $this->page->requires->js('/mod/icontent/js/jquery/jquery.cookie.min.js', true);*/
		//$this->page->requires->js('/mod/icontent/js/bootstrap/bootstrap.min.js');
        //$this->page->requires->js('/mod/icontent/js/effects.js');
	}
	
	/**
	 * Renders the iContent page header Internal JS.
	 *
	 */
	public function icontent_requires_internal_js(){
        $this->page->requires->js_call_amd('mod_icontent/module', 'init');
        $this->page->requires->js_call_amd('mod_icontent/actions', 'init');

	}
}