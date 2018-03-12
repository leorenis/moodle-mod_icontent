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
/*
 * Scanservice
 *
 * @package    mod_scanservice
 * @author     Johannes Burk & Vincent Schneider 2017
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery','jqueryui', 'mod_icontent/cookiehandler'], function($, jqui, c) {
    // Loads page
    function onLoadPageClick(){
        var data = {
            "action": "loadpage",
            "id": $(this).attr('data-cmid'),
            "pagenum": $(this).attr('data-pagenum'),
            "sesskey": $(this).attr('data-sesskey')
        };
        // Destroy all tooltips
        //$('[data-toggle="tooltip"]').tooltip('destroy');
        // Loading page
        $(".icontent-page")
            .children('.fulltextpage')
            .prepend(
                $('<div />')
                    .addClass('loading')
                    .html('<img src="pix/loading.gif" alt="Loading" class="img-loading" />')
            )
            .css('opacity', '0.5');
        // Active link or button the atual page
        onBtnActiveEnableDisableClick(data.pagenum);
        data = "&" + $.param(data);
        $.ajax({
            type: "POST",
            dataType: "json",
            url: "ajax.php", // Relative or absolute path to ajax.php file
            data: data,
            success: function(data) {
                if(data.transitioneffect !== "0"){
                    $(".icontent-page").hide();
                    $(".icontent-page").html(data.fullpageicontent);
                    $(".icontent-page").show(data.transitioneffect, 1000);
                }else{
                    $(".icontent-page").html(data.fullpageicontent);
                }
                onChecksHighcontrast();
                onChangeStateControlButtons(data);
                onHandleFloat('left');
            }
        }); // End AJAX

    } // End onLoad..

    // Checks if the cookie is set.
    function onChecksHighcontrast(){
        if (c.cookie('highcontrast') == "yes") {
            $(".fulltextpage").addClass("highcontrast").css({"background-color":"#000000", "background-image": "none"});
        }
    }

    function onHandleFloat(option){
        $(".fulltextpage").css({"float": option});
    }

    // Change state the control buttons
    function onChangeStateControlButtons($data){
        var $btnprev = $('.icontent-buttonbar .btn-previous-page');
        var $btnnext = $('.icontent-buttonbar .btn-next-page');
        if($data.previous){
            $btnprev.removeAttr('disabled');
            $btnprev.attr( "data-pagenum", $data.previous );
        }else{
            $btnprev.prop("disabled", true );
        }

        if($data.next){
            $btnnext.removeAttr('disabled');
            $btnnext.attr( "data-pagenum", $data.next );
        }else{
            $btnnext.prop("disabled", true);
        }
    }
    // Disable button when clicked.
    function onBtnActiveEnableDisableClick(pagenum){
        var pagenum = pagenum;
        $(".load-page").removeClass("active");
        $(".btn-icontent-page").removeAttr("disabled");
        $(".page"+ pagenum).addClass("active");
        $(".page"+ pagenum).prop("disabled", true );
    }
    return {
        init: function() {
            onChecksHighcontrast();
            onBtnActiveEnableDisableClick($(".fulltextpage").attr('data-pagenum'));
            $(".load-page").click(onLoadPageClick);
        }
    };
});
