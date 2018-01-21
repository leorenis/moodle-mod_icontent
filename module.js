/**
 * Prints a particular instance of icontent
 *
 * Responsible for generic Java Script functions of interactive content plugin <iContent>
 * More specific functions are available in the archive <js/src/actions.js>
 *
 * @package    mod_icontent
 * @copyright  2016 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$(document).ready(function(){
	onChecksHighcontrast();
	// Loads page
	function onLoadPageClick(){
		var btnType = 'top';
		if ( $(this).hasClass('btn-previous-page')){
			var btnType = 'prev';
		} else if ($(this).hasClass('btn-next-page')) {
			var btnType = 'next';
		} else if ($(this).hasClass('btn-icontent-page')) {
			var btnType = 'toc';
		}

		var data = {
			"action": "loadpage",
			"id": $(this).attr('data-cmid'),
			"pagenum": $(this).attr('data-pagenum'),
			"sesskey": $(this).attr('data-sesskey'),
			"navigation": btnType,
			"frompage": $('.btn-icontent-page.active').attr('data-pagenum'),
			"topage": $(this).attr('data-pagenum')
		};
		// Destroy all tooltips
		$('[data-toggle="tooltip"]').tooltip('destroy');
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

        history.pushState(null, null, '#tab' + $(this).attr('data-pagenum'));

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
		    		$(".icontent-page").show( data.transitioneffect, 1000 );
	    		}else{
	    			$(".icontent-page").html(data.fullpageicontent);
	    		}
	    		onChecksHighcontrast();
	    		onChangeStateControlButtons(data);
                //SetPageEvents();
                $('[data-toggle="tooltip"]').tooltip();
	    	}
	    }); // End AJAX
	} // End onLoad..

	// Checks if the cookie is set.
	function onChecksHighcontrast(){
	    if ($.cookie('highcontrast') == "yes") {
	        $(".fulltextpage").addClass("highcontrast").css({"background-color":"#000000", "background-image": "none"});
	    }
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
        $(".btn-icontent-page").css("font-weight", '' );
        $(".btn-icontent-page").css("color", '' );
		$(".page"+ pagenum).addClass("active");
		$(".page"+ pagenum).addClass("visited");
        $(".page"+ pagenum).prop("disabled", true );
	}
	// Call events
	onBtnActiveEnableDisableClick($(".fulltextpage").attr('data-pagenum'));
  	$(".load-page").click(onLoadPageClick);



  	Y.all('.load-page.btn-next-page').each(function(){
  	    if(this.get('childElementCount') == 0) {
  	        this.append(Y.Node.create('<i class="fa fa-chevron-circle-left"></i>'));
  	    }
  	});
  	Y.all('.load-page.btn-previous-page').each(function(){
  	    if(this.get('childElementCount') == 0) {

  	        // this.append(Y.Node.create('<i class="fa fa-chevron-circle-right"></i>'));
  	        this.insertBefore(Y.Node.create('<i class="fa fa-chevron-circle-right"></i>'), this.get('firstChild') );
  	    }
  	});

}); // End ready
