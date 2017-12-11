/**
* Responsible for internal Java Script functions of interactive content plugin <iContent>
*
* @package    mod_icontent
* @copyright  2016 Leo Santos {@link http://github.com/leorenis}
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
function blockButtons(){
        $("button.btn-next-page").attr("disabled", true);
        $("button.btn-previous-page").attr("disabled", true);
        $("button.btn-icontent-page").attr("disabled", true);
       // console.log('block');
	}
	
function unblockButtons(){
     var numpages = $("button.btn-previous-page").attr("data-totalpages");
     var currentpage = $('button.btn-icontent-page.active').attr('data-pagenum');
        
    if (currentpage!=1){
    	$("button.btn-previous-page").attr("disabled", false);	
    }
    
    if (currentpage!=numpages){
        $("button.btn-next-page").attr("disabled", false);
    }
    $("button.btn-icontent-page").attr("disabled", false);
   // console.log('unblock');
}

$(document).ready(function() {


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
        });	// End AJAX
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
        });	// End AJAX
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
        // Remove hightcontrast
        if ($.cookie('highcontrast') == "yes") {
            $.cookie("highcontrast", null, {
                path: '/'
            });
            $('.fulltextpage').removeClass('highcontrast').css('background-color', '#FCFCFC');
        } else {
            $.cookie('highcontrast', 'yes', {
                expires: 7,
                path: '/'
            });
            $(".fulltextpage").addClass("highcontrast").css({"background-color":"#000000", "background-image": "none"});
        }
    }

    function onSaveClozeSave(){
        var formdata = $('#idformquestions').serialize();
        var cmid = parseInt($( "#idhfieldcmid").val());
        var sesskey = $( "#idhfieldsesskey").val();
        var data = {
            "action" : "saveattempt",
            "id" : cmid,
            "sesskey" : sesskey,
            "formdata" : formdata,
        };
        //$('.btn-sendanswers').prop("disabled", true ); // Disable button
        $("#savediv").modal({'backdrop': false}); blockButtons();
        $.ajax({
            type : "POST",
            dataType : "json",
            //url : "/question/preview.php",
            url : "/mod/quiz/processattempt.php",
            data : $('#idformquestions').serialize(),
            complete : function(data) {
                //$("#idquestionsarea").html(data.grid);
               setTimeout(function(){$("#savediv").modal('hide'); unblockButtons();}, 1000);
            }
        });// End AJAX

        return false;
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

    function onSaveClozeAnswers(){
        var formdata = $("#idformquestions").serialize();
        var cmid = parseInt($( "#idhfieldcmid").val());
        var sesskey = $( "#idhfieldsesskey").val();
        var data = {
            "action" : "savecloze",
            "id" : cmid,
            "sesskey" : sesskey,
            "formdata" : formdata,
        };
        //$('.btn-sendanswers').prop("disabled", true ); // Disable button
        $("#savediv").modal({'backdrop': false}); blockButtons();
        $.ajax({
            type : "POST",
            dataType : "json",
            url : "ajax.php",
            data : data,
            success : function(data) {
                //$("#idquestionsarea").html(data.grid);
                setTimeout(function(){$("#savediv").modal('hide');unblockButtons();}, 1000);
            }
        });// End AJAX

        return false;
    }

    function onSaveAttempText()
    {
        clearTimeout (timer);
        delay(function(){
            var formdata = $('#idformquestions').serialize();
            var cmid = parseInt($( "#idhfieldcmid").val());
            var sesskey = $( "#idhfieldsesskey").val();
            var data = {
                "action" : "savedraft",
                "id" : cmid,
                "sesskey" : sesskey,
                "formdata" : formdata,
            };
            //$('.btn-sendanswers').prop("disabled", true ); // Disable button
            blockButtons();
            $("#savediv").modal({'backdrop': false}); 
            $.ajax({
                type : "POST",
                dataType : "json",
                url : "ajax.php",
                data : data,
                success : function(data) {
                    $("#idquestionsarea").html(data.grid);
                    setTimeout(function(){$("#savediv").modal('hide');unblockButtons();}, 1000);
                }
            });// End AJAX
            }, 2000 );
        //return false;
    }
    var timer = 0;
    var delay = (function(){

        return function(callback, ms){

            timer = setTimeout(callback, ms);
        };
    })();

    // Toggle elements UI
    function onTogleElementClick(event){
        $idtogle = event.data.idtogle;
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

    SetPageEvents();
    function SetPageEvents()
    {
    // Events
        $("#idicontentpages").on('click','#idbtnsavenote', onSaveNoteClick);
        $("#idicontentpages").on('click', '#idbtnsavedoubt', onSaveDoubtClick);
        $("#idicontentpages").on('click', '#idtitlenotes', {idtogle: '#idfulltab'}, onTogleElementClick);
        $("#idicontentpages").on('click', '#idtitlequestionsarea', {idtogle: '#idcontentquestionsarea'}, onTogleElementClick);
        $("#idicontentpages").on('click', '.read-more-state-on', onReadMoreStateOnClick);
        $("#idicontentpages").on('click', '.read-more-state-off', onReadMoreStateOffClick);
        $("#idicontentpages").on('click', '.likenote', onLikeNoteClick);
        $("#idicontentpages").on('click', '.editnote', onEditNoteClick);
        $("#idicontentpages").on('click', '.replynote', onReplyNoteClick);
        $("#idicontentpages").on('click', '.togglehighcontrast', onToggleHightContrastClick);
        $("#idicontentpages").on('submit', '#idformquestions', onSaveAttempAnswers);
        //$("#idicontentpages").on('click', '#qbtnsave', onSaveAttempText);
        //$("#idicontentpages").on('click', '#generalfeedback', function(){$('.generalfeedback').toggle();});
        $("#idicontentpages").on('click', '#generalfeedback', function(){$(this).parent().children('.generalfeedback').toggle();});
        $("#idicontentpages").on('click', '#generalfeedback_close', function(){$('.generalfeedback_close_'+$(this).attr('data')).toggle();});
        $("#idicontentpages").on('keyup', '.answertextarea', onSaveAttempText);
        $("#idicontentpages").on('click', '.answercheckbox', onSaveAttempText);
        $("#idicontentpages").on('change', '.answermatch', onSaveAttempText);
        //$(".question.multianswer").on('keyup change', 'input, select, textarea', onSaveClozeAnswers);
        console.log($(".question.multianswer"));
    //$("#idicontentpages").on('click', '#cloze_save', onSaveClozeAnswers);
    }

    var url = window.location;
    try
    {
        var urlAux = url.toString().split('\#');
        var page_id = urlAux[1].replace('tab', '');

        $(".load-page.btn-icontent-page.page" + page_id).click();
    }
    catch(e){}
});
// End ready