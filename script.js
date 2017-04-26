(function($){
	var replaceImages = function(post_id, content){
		$.ajax({
			url: '/wp-admin/admin-ajax.php',
			type: 'post',
			dataType: 'json',
			data: {'action':'rpi_replace_images', 'post_id':post_id, 'content':content},
			beforeSend: function(){
				$('#loading-replace-image').css({'display':'inline-block'});
			},
			success: function(result){
				console.log(result);
				if(result.success){
					var editor = tinymce.get('content');
					$('#content').html(result.content);
					if(editor){
						editor.nodeChanged();
					}
					$('#loading-replace-image').css({'display':'none'});
				}
			},
			error: function(err){
				$('#loading-replace-image').css({'display':'none'});
			}
		});
	}
	$(document).ready(function(){
		$('.btn-replace-image').click(function(){
			var $editor_wrap = $(this).parents('.wp-editor-wrap');
			console.log($editor_wrap[0]);
			var content = $editor_wrap.find('textarea.wp-editor-area').text();
			console.log($editor_wrap.find('textarea.wp-editor-area')[0]);
			replaceImages($('#post_ID').val(), content);
		});
	});
}(jQuery));