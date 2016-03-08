( function( $ ) {

	$( '#coauthorsdiv' ).one( 'DOMNodeInserted', 'input.coauthor-suggest[name="coauthorsinput[]"]', function(e) {
		$( '#coauthors-list' ).on( 'blur', '.coauthor-suggest', function( e ) {
			setTimeout( function() {
				var authorSlug = $( 'input[name="coauthors[]"]' ).last().attr( 'value' );
				$.ajax( {
					url: post_payments.ajaxurl,
					type: 'POST',
					dataType: 'json',
					data: {
						author_slug: authorSlug,
						action: post_payments.action,
					},
					success: function( data ) {
						$( 'input[name=post_cost]' ).val( data );
					},
				} );
			}, 1000);
		} );
	} );

})( jQuery );