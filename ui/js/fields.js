// Simple Select2 Fields
( function( $, _ ){
	'use strict';
	$(document).ready(function(){
		var $elements = $( '.fp-type-dropdown' );
		$elements.each(function(){
			var $select = $(this),
				args = {
					width: 420
				};

			if ( $select.is( '[multiple]' ) ){
				args.multiple = true;

				if ( ! $select.is( '[data-tags]' ) ){
					args.data = function(){
						return { 'results': $select.data( 'options' ) };
					};
				}

			} else {
				args.width = 200;
			}

			if ( $select.is( '[data-tags]' ) ){
				args.tags = $select.data( 'options' );
				args.tokenSeparators = [','];
			}

			if ( $select.is( '[data-source]' ) ){
				var source = $select.data( 'source' );

				args.data = { results: [] };
				args.allowClear = true;

				args.escapeMarkup = function (m) {
					return m;
				};

				args.formatSelection = function ( post ){
					return _.template('<abbr title="<%= post_title %>"><%= post_type.labels.singular_name %>: <%= ID %></abbr>')( post )
				};
				args.formatResult = function ( post ){
					return _.template('<abbr title="<%= post_title %>"><%= post_type.labels.singular_name %>: <%= ID %></abbr>')( post )
				};

				args.ajax = { // instead of writing the function to execute the request we use Select2's convenient helper
					dataType: 'json',
					type: 'POST',
					url: window.ajaxurl,
					data: function (search, page) {
						var post_types = _.intersection( $( '#fakerpress-field-post_types' ).val().split( ',' ), _.pluck( _.where( $( '#fakerpress-field-post_types' ).data( 'options' ), { hierarchical: true } ) , 'id' ) );

						return {
							action: 'fakerpress.select2-' + source,
							query: {
								s: search,
								posts_per_page: 10,
								paged: page,
								post_type: post_types
							}
						};
					},
					results: function ( data ) { // parse the results into the format expected by Select2.
						$.each( data.results, function( k, result ){
							result.id = result.ID;
						} );
						return data;
					}
				};

			}

			$select.select2( args );
		})
		.on( 'change', function( event ) {
			var $select = $(this),
				data = $( this ).data( 'value' );

			if ( ! $select.is( '[multiple]' ) ){
				return;
			}
			if ( ! $select.is( '[data-source]' ) ){
				return;
			}

			if ( event.added ){
				if ( _.isArray( data ) ) {
					data.push( event.added );
				} else {
					data = [ event.added ];
				}
			} else {
				if ( _.isArray( data ) ) {
					data = _.without( data, event.removed );
				} else {
					data = [];
				}
			}
			$select.data( 'value', data ).attr( 'data-value', JSON.stringify( data ) );
		} );

	});
}( window.jQuery, window._ ) );

// Quantity Range Fields
( function( $ ){
	'use strict';
	$(document).ready(function(){
		$( '.fp-type-range-container' ).each(function(){
			var $container = $(this),
				$minField = $container.find( '.fp-type-number[data-type="min"]' ),
				$maxField = $container.find( '.fp-type-number[data-type="max"]' );

			$minField.on({
				'change keyup': function(e){
					if ( $.isNumeric( $(this).val() ) && $(this).val() > 0 ) {
						$maxField.removeAttr( 'disabled' );

						if ( $maxField.val() && $(this).val() >= $maxField.val() ){
							$(this).val( '' );
						}
					} else {
						$(this).val( '' );
					}

				}
			});
		});
	});
}( jQuery ) );

// Date Fields
( function( $ ){
	'use strict';
	$(document).ready(function(){
		var $datepickers = $( '.fp-type-date' ).datepicker( {
			constrainInput: false,
			dateFormat: 'yy-mm-dd',
		} );

		$( '.fp-type-interval-container' ).each( function(){
			var $container = $( this ),
				$interval = $container.find( '.fp-type-dropdown' ),
				$min = $container.find( '[data-type="min"]' ),
				$max = $container.find( '[data-type="max"]' );

			$interval.on({
				'change': function(e){
					var $selected = $interval.find(':selected'),
						min = $selected.attr('min'),
						max = $selected.attr('max');

					$min.datepicker( 'setDate', min );
					$max.datepicker( 'setDate', max );
				}
			});

			$min.on({
				'change': function(e){
					$min.parents( '.fp-field-wrap' ).find( '[data-type="max"]' ).datepicker( 'option', 'minDate', $( this ).val() ).datepicker( 'refresh' );
					$datepickers.datepicker( 'refresh' );
				}
			});

			$max.on({
				'change': function(e){
					$max.parents( '.fp-field-wrap' ).find( '[data-type="min"]' ).datepicker( 'option', 'maxDate', $( this ).val() ).datepicker( 'refresh' );
					$datepickers.datepicker( 'refresh' );
				}
			});

		} );
	});
}( jQuery ) );

/*

// Terms Fields
( function( $, _ ){
	'use strict';
	$(document).ready(function(){
		$( '.field-select2-terms' ).each(function(){
			var $select = $(this);

			$select.select2({
				width: 400,
				multiple: true,
				data: {results:[]},
				initSelection : function (element, callback) {
					callback(element.data( 'selected' ));
				},
				allowClear: true,
				ajax: { // instead of writing the function to execute the request we use Select2's convenient helper
					dataType: 'json',
					type: 'POST',
					url: window.ajaxurl,
					data: function (term, page) {
						return {
							action: 'fakerpress.search_terms',
							search: term, // search term
							page_limit: 10,
							page: page,
							post_type: null
						};
					},
					results: function ( data ) { // parse the results into the format expected by Select2.
						$.each( data.results, function( k, result ){
							result.text = _.template('<%= tax %>: <%= term %>')( { tax: data.taxonomies[result.taxonomy].labels.singular_name, term: result.name } );
							result.id = result.term_id;
						} );
						return data;
					}
				},
			});
		});
	});
}( jQuery, _ ) );

// Author fields
( function( $ ){
	'use strict';
	$(document).ready(function(){
		$( '.field-select2-author' ).each(function(){
			var $select = $(this);

			$select.select2({
				width: 400,
				multiple: true,
				allowClear: true,
				escapeMarkup: function (m) { return m; },
				formatSelection: function ( author ){
					return _.template('<abbr title="<%= ID %>: <%= data.user_email %>"><%= roles %>: <%= data.display_name %></abbr>')( author )
				},
				formatResult: function ( author ){
					return _.template('<abbr title="<%= ID %>: <%= data.user_email %>"><%= roles %>: <%= data.display_name %></abbr>')( author )
				},
				ajax: {
					dataType: 'json',
					type: 'POST',
					url: window.ajaxurl,
					data: function ( author, page ) {
						return {
							action: 'fakerpress.search_authors',
							search: author, // search author
							page_limit: 10,
							page: page,
						};
					},
					results: function ( data ) { // parse the results into the format expected by Select2.
						$.each( data.results, function( k, result ){
							result.id = result.data.ID;
							result.text = result.data.display_name;
						} );
						return data;
					}
				}
			});
		});
	});
}( jQuery ) );

// Post Query for Select2
( function( $, _ ){
	'use strict';
	$(document).ready(function(){
		$( '.fp-field-select2-posts' ).each(function(){
			var $select = $(this);
			$select.select2({
				width: 400,
				multiple: true,
				data: {results:[]},
				allowClear: true,
				escapeMarkup: function (m) { return m; },
				formatSelection: function ( post ){
					return _.template('<abbr title="<%= post_title %>"><%= post_type.labels.singular_name %>: <%= ID %></abbr>')( post )
				},
				formatResult: function ( post ){
					return _.template('<abbr title="<%= post_title %>"><%= post_type.labels.singular_name %>: <%= ID %></abbr>')( post )
				},
				ajax: { // instead of writing the function to execute the request we use Select2's convenient helper
					dataType: 'json',
					type: 'POST',
					url: window.ajaxurl,
					data: function (search, page) {
						return {
							action: 'fakerpress.query_posts',
							query: {
								s: search,
								posts_per_page: 10,
								paged: page,
								post_type: _.pluck( _.where( $( '.field-post_type.select2-offscreen' ).data( 'value' ), { hierarchical: true } ) , 'id' )
							}
						};
					},
					results: function ( data ) { // parse the results into the format expected by Select2.
						$.each( data.results, function( k, result ){
							result.id = result.ID;
						} );
						return data;
					}
				},
			});
		});
	});
}( jQuery, _ ) );

// Check for checkbox dependecies
( function( $, _ ){
	'use strict';
	$(document).ready(function(){
		var checkDependency = function( event ){
			var $box, $dependecyField;
			if ( _.isNumber( event ) ){
				$box = $( this );
				$dependecyField = $( $box.data('fpDepends') );
			} else {
				$dependecyField = $( this );
				$box = $dependecyField.data( 'fpDependent' );
			}

			var	condition = $box.data('fpCondition'),
				$placeholder = $dependecyField.data( 'fpPlaceholder' );

			if ( ! $placeholder ){
				$placeholder = $( "<div>" ).attr( 'id', _.uniqueId( 'fp-dependent-placeholder-' ) );
				$dependecyField.data( 'fpPlaceholder', $placeholder );
			}
			$dependecyField.data( 'fpDependent', $box );

			if ( _.isNumber( event ) ){
				$dependecyField.on( 'change', checkDependency );
			}

			if ( $dependecyField.is(':checked') != condition ){
				$box.after( $placeholder ).detach();
			} else if ( $placeholder.is(':visible') ) {
				$placeholder.replaceWith( $dependecyField.data( 'fpDependent' ) );
			}
		};

		$( '.fp-field-dependent' ).each( checkDependency );
	});
}( window.jQuery, window._ ) );

*/