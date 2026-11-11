jQuery(function ($) {
	$('.atp-media-field').each(function () {
		var $td = $(this), $input = $td.find('input[type=hidden]'), $img = $td.find('img'), $clear = $td.find('.atp-media-clear'), frame;
		$td.find('.atp-media-pick').on('click', function () {
			if (!frame) {
				frame = wp.media({ title: 'Escolher imagem', library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
					$input.val(a.id); $img.attr('src', src).show(); $clear.show();
				});
			}
			frame.open();
		});
		$clear.on('click', function () { $input.val(0); $img.hide(); $clear.hide(); });
	});
});
