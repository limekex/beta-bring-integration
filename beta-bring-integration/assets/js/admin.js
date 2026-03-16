(function($){
	$(function(){

		// -- Preset change: show/load pickup point selector when required --
		$('#bbi_preset').on('change', function(){
			var requiresPickup = $(this).find(':selected').data('requires-pickup');
			if ( String(requiresPickup) === '1' ) {
				$('#bbi_pickup_wrap').show();
				loadPickupPoints();
			} else {
				$('#bbi_pickup_wrap').hide();
				resetPickupSelect();
			}
		}).trigger('change');

		function resetPickupSelect() {
			$('#bbi_pickup_point').html('<option value="">' + bbi_ajax.i18n.select_pickup + '</option>');
		}

		function loadPickupPoints() {
			var $select   = $('#bbi_pickup_point');
			var toPostal  = $('#bbi_preset').data('to-postal')  || '';
			var toCountry = ($('#bbi_preset').data('to-country') || 'NO').toUpperCase();

			if ( ! toPostal ) {
				$select.html('<option value="">' + bbi_ajax.i18n.no_pickup + '</option>');
				return;
			}

			$select.html('<option>' + bbi_ajax.i18n.loading + '</option>');

			$.ajax({
				url: bbi_ajax.rest_url + '/pickup-points/' + toCountry + '/' + toPostal,
				headers: { 'X-WP-Nonce': bbi_ajax.rest_nonce }
			}).done(function(data){
				// Bring returns either data.pickupPoints[] or data.pickupPoint[]
				var points = [];
				if ( data && data.pickupPoints ) {
					points = data.pickupPoints;
				} else if ( data && data.pickupPoint ) {
					points = data.pickupPoint;
				}

				if ( ! points.length ) {
					$select.html('<option value="">' + bbi_ajax.i18n.no_pickup + '</option>');
					return;
				}

				var opts = '<option value="">' + bbi_ajax.i18n.select_pickup + '</option>';
				$.each(points, function(i, p){
					var id   = p.id   || p.unitId   || '';
					var name = p.name || p.unitName || id;
					opts += '<option value="' + id + '">' + name + '</option>';
				});
				$select.html(opts);
			}).fail(function(){
				$select.html('<option value="">' + bbi_ajax.i18n.no_pickup + '</option>');
			});
		}

		// -- Book shipment button --
		$('#bbi_book_btn').on('click', function(e){
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true).text(bbi_ajax.i18n.booking);

			var preset          = $('#bbi_preset').val();
			var order_id        = $btn.data('order-id') || window.bbi_order_id || $('input#post_ID').val();
			var pickup_point_id = $('#bbi_pickup_point').val() || '';

			$.post(bbi_ajax.ajax_url, {
				action:          'bbi_book_order',
				nonce:           bbi_ajax.nonce,
				order_id:        order_id,
				preset:          preset,
				pickup_point_id: pickup_point_id
			}, function(resp){
				$btn.prop('disabled', false).text(bbi_ajax.i18n.book);
				if ( resp.success ) {
					var d    = (resp.data && resp.data.data) ? resp.data.data : resp.data;
					var html = d.consignment_no ? '<p>Consignment: ' + d.consignment_no + '</p>' : '';
					if ( d.label_url ) {
						html += '<p><a class="button" href="' + d.label_url + '" target="_blank">'
							+ bbi_ajax.i18n.download_label + '</a></p>';
					}
					if ( d.tracking_url ) {
						html += '<p><button class="button bbi-copy-tracking" data-url="' + d.tracking_url + '">'
							+ bbi_ajax.i18n.copy_tracking + '</button></p>';
					}
					$('#bbi_status').html(html);
				} else {
					alert(resp.data && resp.data.message ? resp.data.message : 'Booking failed');
				}
			}).fail(function(){
				$btn.prop('disabled', false).text(bbi_ajax.i18n.book);
				alert('Booking failed (network error)');
			});
		});

		// -- Copy tracking link to clipboard --
		$(document).on('click', '.bbi-copy-tracking', function(e){
			e.preventDefault();
			var $btn = $(this);
			var url  = $btn.data('url');
			if ( ! url ) return;
			navigator.clipboard.writeText(url).then(function(){
				var orig = $btn.text();
				$btn.text(bbi_ajax.i18n.copied);
				setTimeout(function(){ $btn.text(orig); }, 2000);
			});
		});

	});
})(jQuery);

