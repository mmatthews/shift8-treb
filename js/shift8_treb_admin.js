jQuery(document).ready(function() {
	jQuery( function() {
		jQuery("#treb-import-button").button();
		jQuery( "#datepicker-treb" ).datepicker({ 
					maxDate: "1",
					dateFormat: "dd-mm-yy",
					onClose: function(selectedDate) {
						var _href = jQuery("#treb-import-button").attr("href");
						jQuery("#treb-import-button").attr("href", _href + "&date=" + selectedDate);
					}
					});
		jQuery(".treb-import-progress").progressbar({
			value: 0 
			});
	} );

	// Progress bar
	var data = {
			'action': 'treb_import_progress',
			'progress': 0 
		};

		var trebPoll = function() {
			jQuery.post(ajaxurl, data, function(response) {
//				console.log('Got this from the server: ' + response);
				jQuery(".treb-import-progress").progressbar({
					value: parseInt(response)
				});
				setTimeout( trebPoll, 2000 );
			});
		}
	trebPoll();


});
