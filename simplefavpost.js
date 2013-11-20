jQuery(document).ready(function($) {
	var submitted = false;
	if(!simplefavpost_js_obj.exists || simplefavpost_js_obj.user_id == 0){	
		$('.post-fav').on('click',function(ev){
			if(!submitted){
				var reference = this;
				$.ajax({
					type: "POST",
					url: simplefavpost_js_obj.ajaxurl,
					success: function(res){ 
						var success = res.success;	
						if(success){
							var thespan = $('.post-fav .heart').find('span')[0];
							$(thespan).text((1 + parseInt($(thespan).attr('ref'))) + ' Favs');
							$(thespan).attr('ref',1+parseInt($(thespan).attr('ref')));
							$(thespan).fadeTo(500,.8);
							$(reference).off('click');
							submitted = true;
						}else{
							alert('There was a problem updating the favorite count');
						}
					},
					data: {	'user_id' : simplefavpost_js_obj.user_id,
							'post_id' : simplefavpost_js_obj.post_id,
							'action': 'simplefavpost_ajax', 
							'security': simplefavpost_js_obj.nonce }
						}
					);		
				ev.stopPropagation();
			}
		});
	}else{
		$('.counter').fadeTo(0,.5,function(){});
	}
	
});