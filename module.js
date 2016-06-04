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
		var data = {
			"action": "loadpage",
			"id": $(this).attr('data-cmid'),
			"pagenum": $(this).attr('data-pagenum'),
			"sesskey": $(this).attr('data-sesskey')
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
		
		data = "&" + $.param(data);
	  	$.ajax({
	    	type: "POST",
	    	dataType: "json",
	    	url: "ajax.php", // Relative or absolute path to ajax.php file
	    	data: data,
	    	success: function(data) {
	    		$(".icontent-page").html(data.fullpageicontent);
	    		onChecksHighcontrast();
	    	}
	    }); // End AJAX
	  	onChangeStateControlButtons($(this));
	} // End onLoad..
	
	// Checks if the cookie is set.
	function onChecksHighcontrast(){
	    if ($.cookie('highcontrast') == "yes") {
	        $(".fulltextpage").addClass("highcontrast").css({"background-color":"#000000", "background-image": "none"});
	    }
	}
	// Change state the control buttons
	function onChangeStateControlButtons($this){
		var pagenum = parseInt($this.attr('data-pagenum'));
		var tpages = parseInt($this.attr('data-totalpages'));
		var prevpage = pagenum - 1;
		var nextpage = pagenum + 1;
		var $btnprev = $('.icontent-buttonbar .btn-previous-page');
		var $btnnext = $('.icontent-buttonbar .btn-next-page');

		if(pagenum <= 1){
			$btnprev.prop("disabled", true );
		}else{
			$btnprev.removeAttr('disabled');
		}

		if(pagenum >= tpages){
			$btnnext.prop("disabled", true);
		}else{
			$btnnext.removeAttr('disabled');
		}

		$btnprev.attr( "data-pagenum", prevpage );
		$btnnext.attr( "data-pagenum", nextpage );
	}
	// Disable button when clicked.
	function onBtnActiveEnableDisableClick(pagenum){
		var pagenum = pagenum;
		$(".load-page").removeClass("active");
		$(".btn-icontent-page").removeAttr("disabled");
		$(".page"+ pagenum).addClass("active");
		$(".page"+ pagenum).prop("disabled", true );
	}
	// Call events
	onBtnActiveEnableDisableClick($(".fulltextpage").attr('data-pagenum'));
  	$(".load-page").click(onLoadPageClick);
  	
}); // End ready