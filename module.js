/**
 * Prints a particular instance of icontent
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_icontent
 * @copyright  2015 Leo Renis Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$(document).ready(function(){
	
	// Carrega pagina
	function onLoadPageClick(){
		var data = {
			"action": "loadpage",
			"id": $(this).attr('data-cmid'),
			"pagenum": $(this).attr('data-pagenum'),
			"sesskey": $(this).attr('data-sesskey')
		};
		// Carregando pagina
		$(".icontent-page")
			.children('.fulltextpage')
			.prepend(
				$('<div />')
					.addClass('loading')
					.html('<img src="pix/loading.gif" alt="Loading" class="img-loading" />')
			)
			.css('opacity', '0.5');
			
		// Ativa link ou botao da pagina atual
		onActive(data['pagenum']);
		
		data = "&" + $.param(data);
	  	$.ajax({
	    	type: "POST",
	    	dataType: "json",
	    	url: "ajax.php", //Relative or absolute path to ajax.php file
	    	data: data,
	    	success: function(data) {
	    		$(".icontent-page").html(data.fullpageicontent);
	    	}
	    }); // fim ajax
	    
	} // End onLoad..
	
	function onLoadNextPageClick(){
		var $btnNext = $(this);
		
		var $totalpages = $btnNext.attr('data-totalpages');
		var $pagenum = $btnNext.attr('data-pagenum');
		
		console.log($totalpages);
	}
	
	function onActive(pagenum){
		var pagenum = pagenum;
		$(".load-page").removeClass("active");
		$(".page"+ pagenum).addClass("active");
	}
	
	// Chamada de eventos
	onActive($(".fulltextpage").attr('data-pagenum'));
	$(".next").click(onLoadNextPageClick);
  	$(".load-page").click(onLoadPageClick);
  	
}); // End ready