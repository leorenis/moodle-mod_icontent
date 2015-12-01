/**
 * Responsavel pelas funcoes JavaScript internas do mod_icontent
 *
 * @package    mod_icontent
 * @copyright  2015 Leo Santos
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$(document).ready(function() {
	
	// Habilita toltip
	$('[data-toggle="tooltip"]').tooltip();

	// Check (onLoad) if the cookie is there and set the class if it is
    if ($.cookie('highcontrast') == "yes") {
        $(".fulltextpage").addClass("highcontrast").css({"background-color":"#000000", "background-image": "none"});
    }
	
	// Funcoes nomeadas //
	
	//Salvar note do tipo anotacoes
	function onSaveNoteClick() {
		// valida entrada de dados
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
		});	// fim ajax
	}// chamada da funcao

	//Salvar note do tipo duvidas
	function onSaveDoubtClick() {
		// valida entrada de dados
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
		});	// fim ajax
	}
	
	// carregamento
	function showIconLoad($this){
		$this.hide();
		// Carregando
		$(".icontent-page")
			.children('.fulltextpage')
			.prepend(
				$('<div />')
					.addClass('loading')
					.html('<img src="pix/loading.gif" alt="Loading" class="img-loading" />')
			)
			.css('opacity', '0.5');
	}
	// Remove icone carregamento
	function removeIconLoad($this){
		$this.show();
		// Carregando
		$(".icontent-page")
			.children('.fulltextpage')
			.css('opacity', '1')
			.children('.loading').remove();
			$this.removeAttr('disabled');
	}
	
	// Curtir anotacoes
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
		// fim ajax
	}
	
	//Cancela Edicao de anotacoes
	function onEditNoteCancelClick(event){
		var textcomment = event.data.lastcomment;
		var $notecomment = $(this).parent('.buttonscomment').parent('.notecomment');
		
		$notecomment.text(textcomment);
	}
	
	//Salva Edicao de anotacoes
	function onEditNoteSaveClick(){
		var $notecomment = $(this).parent('.buttonscomment').parent('.notecomment');
		var textnotecomment = $notecomment.children('.textnotecomment').val();
		// valida entrada de dados
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

		$.ajax({
			type : "POST",
			dataType : "json",
			url : "ajax.php",
			data : data,
			success : function(data) {
				$notecomment.text(data.comment);
			}
		});
		// fim ajax
	}
	
	// Editar anotacoes
	function onEditNoteClick(){
		// captura comentario
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
		
		// Cancela edicao
		$(".btnnotecancel").click({lastcomment: textcomment}, onEditNoteCancelClick);
		// Salva edicao
		$(".btnnotesave").click(onEditNoteSaveClick);
	}
	
	// Cancela resposta de anotacao
	function onReplyNoteCancelClick(){
		var $notecomment = $(this).parent('.buttonscomment').parent('.notecomment');
		
		$notecomment.children('.replynotecomment').remove();
		$(this).parent('.buttonscomment').remove();
		
	}
	
	// Salva resposta de anotacao
	function onReplyNoteSaveClick(){
		
		var $notecomment = $(this).parent('.buttonscomment').parent('.notecomment');
		var textnotecomment = $notecomment.children('.replynotecomment').val();
		
		// valida entrada de dados
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

		$.ajax({
			type : "POST",
			dataType : "json",
			url : "ajax.php",
			data : data,
			success : function(data) {
				$("#message"+data.tab).text(data.totalnotes);
				$notecomment.parents('.notelist').append(data.reply);
				$notecomment.children('.replynotecomment').remove();
				$notecomment.children('.buttonscomment').remove();
			}
		});
		// fim ajax
		//console.log(data);
	}
	// Cria formulario para responder anotacoes
	function onReplyNoteClick(){
		
		var $notecomment = $(this).parent('.notefooter').parent('.noterowicontent').children('.notecomment');
		
		if(!$notecomment.children('.replynotecomment').length){
			// fecha campo responder aberto anteriormente
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
		
		// Cancela resposta
		$(".btnnotereplycancel").click(onReplyNoteCancelClick);
		// Salva resposta
		$(".btnnotereplysave").click(onReplyNoteSaveClick);
	}

	// Alternar Alto contraste
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
	
	// Chamada das funcoes nomeadas
	
	$("#idbtnsavenote").click(onSaveNoteClick);
	$("#idbtnsavedoubt").click(onSaveDoubtClick);
	$(".likenote").click(onLikeNoteClick);
	$(".editnote").click(onEditNoteClick);
	$(".replynote").click(onReplyNoteClick);
	$(".togglehighcontrast").click(onToggleHightContrastClick);

});
// End ready