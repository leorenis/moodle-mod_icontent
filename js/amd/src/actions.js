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
    // List of the named functions
    // Save note tab type 'note'
    function onSaveNoteClick() {
        // Validates input data
        if (!$("#idcommentnote").val().trim()) {
            $("#idcommentnote").focus().val("");
            return false;
        }
        var $this = $(this);
        var data = {
            "action" : "savereturnpagenotes",
            "id" : $(this).attr('data-cmid'),
            "pageid" : $(this).attr('data-pageid'),
            "sesskey" : $(this).attr('data-sesskey'),
            "comment" : $("#idcommentnote").val(),
            "tab" : "note",
            "private" : $("#idprivate").is(":checked") ? 1 : 0,
            "featured" : $("#idfeatured").is(":checked") ? 1 : 0,
            "doubttutor" : 0,
        };
        showIconLoad($this);
        data = "&" + $.param(data);

        $.ajax({
            type : "POST",
            dataType : "json",
            url : "ajax.php",
            data : data,
            success : function(data) {
                $("#idpagenotesnote").html(data.notes);
                $("#idcommentnote").val("");
                $("#messagenotes").text(data.totalnotes);
                removeIconLoad($this);
            }
        }); // EndAJAX
    }
    // Save note tab type 'doubts'
    function onSaveDoubtClick() {
        // Validates input data
        if (!$("#idcommentdoubt").val().trim()) {
            $("#idcommentdoubt").focus().val("");
            return false;
        }
        var $this = $(this);
        var data = {
            "action" : "savereturnpagenotes",
            "id" : $(this).attr('data-cmid'),
            "pageid" : $(this).attr('data-pageid'),
            "sesskey" : $(this).attr('data-sesskey'),
            "comment" : $("#idcommentdoubt").val(),
            "tab" : "doubt",
            "doubttutor" : $("#iddoubttutor").is(":checked") ? 1 : 0,
            "private" : 0,
            "featured" : 0,
        };
        showIconLoad($this);
        data = "&" + $.param(data);
        $.ajax({
            type : "POST",
            dataType : "json",
            url : "ajax.php",
            data : data,
            success : function(data) {
                $("#idpagenotesdoubt").html(data.notes);
                $("#idcommentdoubt").val("");
                $("#messagedoubt").text(data.totalnotes);
                removeIconLoad($this);
            }
        }); // End AJAX
    }
    // Show loading icon
    function showIconLoad($this){
        $this.hide();
        // Loading
        $(".icontent-page")
            .children('.fulltextpage')
            .prepend(
                $('<div />')
                    .addClass('loading')
                    .html('<img src="pix/loading.gif" alt="Loading" class="img-loading" />')
            )
            .css('opacity', '0.5');
    }
    // Hide loading icon
    function removeIconLoad($this){
        $this.show();
        // Loading
        $(".icontent-page")
            .children('.fulltextpage')
            .css('opacity', '1')
            .children('.loading').remove();
        $this.removeAttr('disabled');
    }
    // Like note
    function onLikeNoteClick() {
        var $like = $(this).children("span");
        var data = {
            "action" : "likenote",
            "id" : $(this).attr('data-cmid'),
            "pagenoteid" : $(this).attr('data-pagenoteid'),
            "sesskey" : $(this).attr('data-sesskey'),
        };

        data = "&" + $.param(data);

        $.ajax({
            type : "POST",
            dataType : "json",
            url : "ajax.php",
            data : data,
            success : function(data) {
                $like.text(data.likes);
            }
        });
        // End AJAX
    }

    // Cancels editing annotation
    function onEditNoteCancelClick(event){
        var textcomment = event.data.lastcomment;
        var $notecomment = $(this).parent('.buttonscomment').parent('.notecomment');

        $notecomment.text(textcomment);
    }

    // Save editing annotation
    function onEditNoteSaveClick(){
        var $notecomment = $(this).parent('.buttonscomment').parent('.notecomment');
        var textnotecomment = $notecomment.children('.textnotecomment').val();
        // Validates input data
        if (!textnotecomment.trim()) {
            $notecomment.children('.textnotecomment').focus().val('');
            return false;
        }

        var data = {
            "action" : "editnote",
            "id" : $notecomment.attr('data-cmid'),
            "pagenoteid" : $notecomment.attr('data-pagenoteid'),
            "sesskey" : $notecomment.attr('data-sesskey'),
            "comment" : textnotecomment,
        };

        data = "&" + $.param(data);
        $(this).prop("disabled", true );
        $.ajax({
            type : "POST",
            dataType : "json",
            url : "ajax.php",
            data : data,
            success : function(data) {
                $notecomment.text(data.comment);
            }
        });
        // End AJAX
    }

    // Edit annotations
    function onEditNoteClick(){
        // Capture comment
        var $notecomment = $(this).parent('.notefooter').parent('.noterowicontent').children('.notecomment');
        var textcomment = $notecomment.text();
        $notecomment.text('')
            .append(
                $('<textarea />')
                    .addClass('textnotecomment span11')
                    .text(textcomment)
            )
            .append(
                $('<div />')
                    .addClass('buttonscomment')
                    .append(
                        $('<button />')
                            .addClass('btnnotesave')
                            .html('<i class="fa fa-floppy-o"></i>')
                    )
                    .append(
                        $('<button />')
                            .addClass('btnnotecancel')
                            .html('<i class="fa fa-times"></i>')
                    )
            );
        $('.textnotecomment').focus();

        // Cancels edition
        $(".btnnotecancel").click({lastcomment: textcomment}, onEditNoteCancelClick);
        // Save edition
        $(".btnnotesave").click(onEditNoteSaveClick);
    }

    // Cancel annotation reply
    function onReplyNoteCancelClick(){
        var $notecomment = $(this).parent('.buttonscomment').parent('.notecomment');

        $notecomment.children('.replynotecomment').remove();
        $(this).parent('.buttonscomment').remove();

    }

    // Save annotation reply
    function onReplyNoteSaveClick(){

        var $notecomment = $(this).parent('.buttonscomment').parent('.notecomment');
        var textnotecomment = $notecomment.children('.replynotecomment').val();

        // Validates input data
        if (!textnotecomment.trim()) {
            $notecomment.children('.replynotecomment').focus().val('');
            return false;
        }

        var data = {
            "action" : "replynote",
            "id" : $notecomment.attr('data-cmid'),
            "parent" : $notecomment.attr('data-pagenoteid'),
            "sesskey" : $notecomment.attr('data-sesskey'),
            "comment" : textnotecomment,
        };

        data = "&" + $.param(data);
        $(this).prop("disabled", true );

        $.ajax({
            type : "POST",
            dataType : "json",
            url : "ajax.php",
            data : data,
            success : function(data) {
                $("#message"+data.tab).text(data.totalnotes);
                $("#pnote"+ parseInt(data.parent)).after(data.reply);
                $notecomment.children('.replynotecomment').remove();
                $notecomment.children('.buttonscomment').remove();
            }
        });
        // End AJAX
    }
    // Create form to reply notes
    function onReplyNoteClick(){

        var $notecomment = $(this).parent('.notefooter').parent('.noterowicontent').children('.notecomment');

        if(!$notecomment.children('.replynotecomment').length){
            // Closes answer field
            $('.replynotecomment').remove();
            $('.buttonscomment').remove();

            $notecomment
                .append(
                    $('<textarea />')
                        .addClass('replynotecomment span')
                        .attr('required', 'required')
                        .attr('maxlength', 1024)
                )
                .append(
                    $('<div />')
                        .addClass('buttonscomment')
                        .append(
                            $('<button />')
                                .addClass('btnnotereplysave')
                                .html('<i class="fa fa-floppy-o"></i>')
                        )
                        .append(
                            $('<button />')
                                .addClass('btnnotereplycancel')
                                .html('<i class="fa fa-times"></i>')
                        )
                );
        }

        $('.replynotecomment').focus();

        // Cancel reply
        $(".btnnotereplycancel").click(onReplyNoteCancelClick);
        // Save reply
        $(".btnnotereplysave").click(onReplyNoteSaveClick);
    }

    // Switch high contrast
    function onToggleHightContrastClick(){
        if (c.cookie('highcontrast') == "yes") {
            c.cookie("highcontrast", null, {
                path: '/'
            });
            $('.fulltextpage').removeClass('highcontrast').css('background-color', '#FCFCFC');
        } else {
            c.cookie('highcontrast', 'yes', {
                expires: 7,
                path: '/'
            });
            $(".fulltextpage").addClass("highcontrast").css({"background-color":"#000000", "background-image": "none"});
        }
    }

    // Open note Tab Click
    function onOpenNoteTabClick() {
        var $doubtTab = $("#idfulltab .itab-content #doubt");
        var $noteTab = $("#idfulltab .itab-content #note");
        $doubtTab.css({"display":"none"});
        $noteTab.css({"display":"block"});

        $("#idfulltab .inav-tabs .itab-note").addClass('active');
        $("#idfulltab .inav-tabs .itab-doubt").removeClass('active');
    }

    // Open doubt Tab Click
    function onOpenDoubtTabClick() {
        var $doubtTab = $("#idfulltab .itab-content #doubt");
        var $noteTab = $("#idfulltab .itab-content #note");
        $noteTab.css({"display":"none"});
        $doubtTab.css({"display":"block"});

        $("#idfulltab .inav-tabs .itab-doubt").addClass('active');
        $("#idfulltab .inav-tabs .itab-note").removeClass('active');
    }

    // Save attemp
    function onSaveAttempAnswers(){
        var formdata = $(this).serialize();
        var cmid = parseInt($( "#idhfieldcmid").val());
        var sesskey = $( "#idhfieldsesskey").val();
        var data = {
            "action" : "saveattempt",
            "id" : cmid,
            "sesskey" : sesskey,
            "formdata" : formdata,
        };
        $('.btn-sendanswers').prop("disabled", true ); // Disable button
        $.ajax({
            type : "POST",
            dataType : "json",
            url : "ajax.php",
            data : data,
            success : function(data) {
                $("#idquestionsarea").html(data.grid);
            }
        });// End AJAX

        return false;
    }
    // Toggle elements UI
    function onTogleElementClick(event){
        var $idtogle = event.data.idtogle;
        var options = {};
        $($idtogle).toggle( 'fade', options, 500 );
        $(this).toggleClass( "closed", 500 );
        // Add icon fa-caret-down or fa-caret-right
        if($(this).hasClass('closed')){
            $(this).children('i').removeClass("fa-caret-down").addClass("fa-caret-right");
        }else{
            $(this).children('i').removeClass("fa-caret-right").addClass("fa-caret-down");
        }
    }
    // Read more state on
    function onReadMoreStateOnClick(){
        $(this).hide();
        $('.suspension-points').hide();
        $('.read-more-target').show('fade');
        $('.read-more-state-off').show();
    }
    // Read more state off
    function onReadMoreStateOffClick(){
        $(this).hide();
        $('.read-more-target').hide('fade');
        $('.suspension-points').show();
        $('.read-more-state-on').show();
    }
    return {
        init: function() {
            var pageid =  $("#idicontentpages");
            pageid.on('click', '#idbtnsavenote', onSaveNoteClick);
            pageid.on('click', '#idbtnsavedoubt', onSaveDoubtClick);
            pageid.on('click', '#idtitlenotes', {idtogle: '#idfulltab'}, onTogleElementClick);
            pageid.on('click', '#idtitlequestionsarea', {idtogle: '#idcontentquestionsarea'}, onTogleElementClick);
            pageid.on('click', '.read-more-state-on', onReadMoreStateOnClick);
            pageid.on('click', '.read-more-state-off', onReadMoreStateOffClick);
            pageid.on('click', '.likenote', onLikeNoteClick);
            pageid.on('click', '.editnote', onEditNoteClick);
            pageid.on('click', '.replynote', onReplyNoteClick);
            pageid.on('click', '.togglehighcontrast', onToggleHightContrastClick);
            pageid.on('click', '#note-tab', onOpenNoteTabClick);
            pageid.on('click', '#doubt-tab', onOpenDoubtTabClick);
            pageid.on('submit', '#idformquestions', onSaveAttempAnswers);
        }
    };
});
