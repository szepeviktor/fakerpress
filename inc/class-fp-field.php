<?php
namespace FakerPress;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ){
	die;
}

class Field {

	const plugin = 'fakerpress';
	const abbr = 'fp';

	public static function abbr( $str = '' ){
		return self::abbr . '-' . $str;
	}

	public $type = 'raw';

	public $id;

	public $field;

	public $container;

	public $has_container = true;

	public $has_wrap = true;

	public static $default_container = array(
		'label' => '',
		'description' => '',
		'attributes' => array(),
		'actions' => array(),
		'heads' => array(),
		'blocks' => array( 'label', 'fields', 'description', 'actions' ),
	);

	public static $valid_types = array(
		'heading',
		'input',
		'text',
		'dropdown',
		'range',
		'interval',
		'number',
		'hidden',
		'meta',
		// 'textarea',
		// 'wysiwyg',
		'radio',
		'checkbox',
		'raw',
	);

	public function __construct( $type, $field, $container = array() ) {
		// Default Error Structure
		$this->error = false;

		// Non Valid types are just set to Raw
		if ( ! self::is_valid_type( $type ) ){
			$type = 'raw';
		}

		if ( is_string( $field ) ){
			$this->field = (object) array(
				'id' => $field,
			);
		} else {
			// Setup the Container if required
			$this->field = (object) $field;
		}

		$container = (object) wp_parse_args( $container, self::$default_container );

		// set the ID
		$this->type = $type;
		if ( ! isset( $this->field->id ) ){
			$this->id = (array) self::abbr( uniqid() );
		} else {
			$this->id = (array) $this->field->id;
		}

		$this->callback = null;
		$this->conditional = true;

		$this->label = $container->label;
		$this->description = $container->description;
		$this->actions = $container->actions;
		$this->blocks = $container->blocks;
	}

	public function output( $print = false ) {
		if ( ! $this->conditional ) {
			return false;
		}

		if ( $this->callback && is_callable( $this->callback ) ) {
			// if there's a callback, run it
			call_user_func( $this->callback );
		} elseif ( in_array( $this->type, self::$valid_types ) ) {
			// the specified type exists, run the appropriate method
			$field = call_user_func_array( array( __CLASS__, 'type_' . $this->type ), array( $this->field, $this, 'string', array() ) );

			// filter the output
			$field = apply_filters( self::plugin . '/fields/field-output-' . $this->type, $field, $this );

			if ( $print ){
				echo balanceTags( $field );
			} else {
				return $field;
			}
		} else {
			return false;
		}
	}

	public function build( $content, $output = 'string', $html = array() ) {
		$content = (array) $content;
		$key = array_search( 'fields', $this->blocks );

		$before = array_filter( array_slice( $this->blocks, 0, $key ), 'is_array' );
		$before_content = array();
		foreach ( $before as $i => $block ) {
			$_html = '';
			if ( ! empty( $block['html'] ) ){
				$_html = $block['html'];
				unset( $block['html'] );
			}
			$before_content[] = '<td' . self::attr( $block ) . '>' . $_html . '</td>';
		}

		$after = array_filter( array_slice( $this->blocks, $key + 1, count( $this->blocks ) - ( $key + 1 ) ), 'is_array' );
		$after_content = array();
		foreach ( $after as $i => $block ) {
			$_html = '';
			if ( ! empty( $block['html'] ) ){
				$_html = $block['html'];
				unset( $block['html'] );
			}
			$after_content[] = '<td' . self::attr( $block ) . '>' . $_html . '</td>';
		}

		if ( in_array( 'table', $this->blocks ) ){
			$html[] = self::start_table( $this );
		}

		$html[] = self::start_container( $this );
		$html[] = implode( "\r\n", $before_content );

		if ( in_array( 'label', $this->blocks ) ){
			$html[] = self::label( $this );
		}

		$html[] = self::start_wrap( $this );
		$html[] = implode( "\r\n", $content );
		$html[] = self::end_wrap( $this );

		$html[] = implode( "\r\n", $after_content );

		$html[] = self::end_container( $this );

		if ( in_array( 'table', $this->blocks ) ){
			$html[] = self::end_table( $this );
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function start_table( $container, $output = 'string', $html = array() ) {
		$html[] = self::type_heading( array(
			'type' => 'heading',
			'title' => $container->label,
			'description' => $container->description,
		), null, 'string' );

		$html[] = '<table class="' . self::abbr( 'table-' . implode( '-', $container->id ) ) . '">';
		if ( ! empty( $container->heads ) ){
			$html[] = '<thead>';
			foreach ( $container->heads as $head ) {
				$_html = '';
				if ( ! empty( $head['html'] ) ){
					$_html = $head['html'];
					unset( $head['html'] );
				}
				$html[] = '<th' . self::attr( $head ) . '>' . $_html . '</th>';
			}
			$html[] = '</thead>';
		}
		$html[] = '<tbody>';

		$html = apply_filters( self::plugin . '/fields/field-start_table', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function end_table( $container, $output = 'string', $html = array() ) {
		$html[] = '</tbody>';
		if ( ! empty( $container->heads ) ){
			$html[] = '<tfoot>';
			foreach ( $container->heads as $head ) {
				$_html = '';
				if ( ! empty( $head['html'] ) ){
					$_html = $head['html'];
					unset( $head['html'] );
				}
				$html[] = '<th' . self::attr( $head ) . '>' . $_html . '</th>';
			}
			$html[] = '</tfoot>';
		}
		$html[] = '</table>';

		$html = apply_filters( self::plugin . '/fields/field-end_table', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function start_container( $container, $output = 'string', $html = array() ) {
		$classes = array( 'field-container', 'type-' . $container->type . '-container' );

		if ( is_wp_error( $container->error ) ){
			$classes[] = 'error';
		}

		$classes = array_map( array( __CLASS__, 'abbr' ) , $classes );

		if ( ! in_array( 'table' , $container->blocks ) ){
			$html[] = '<tr id="' . self::id( $container->id, true ) . '" class="' . implode( ' ', $classes ) . '">';
		}

		$html = apply_filters( self::plugin . '/fields/field-start_container', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function end_container( $container, $output = 'string', $html = array() ) {
		if ( ! in_array( 'table' , $container->blocks ) ){
			$html[] = '</tr>';
		}

		$html = apply_filters( self::plugin . '/fields/field-end_container', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function start_wrap( $container, $output = 'string', $html = array() ) {
		if ( in_array( 'fields', $container->blocks ) ){
			$html[] = '<td colspan="1">';
			$html[] = '<fieldset class="' . self::abbr( 'field-wrap' ) . '">';
		} elseif ( ! in_array( 'table' , $container->blocks ) ) {
			$html[] = '<td colspan="2" class="' . self::abbr( 'field-wrap' ) . '">';
		}

		$html = apply_filters( self::plugin . '/fields/field-start_wrap', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function end_wrap( $container, $output = 'string', $html = array() ) {
		if ( in_array( 'actions', $container->blocks ) ){
			$html[] = self::actions( $container );
		}

		if ( in_array( 'fields', $container->blocks ) && ! in_array( 'table' , $container->blocks ) ){
			$html[] = '</fieldset>';
		}

		if ( in_array( 'description', $container->blocks ) ){
			$html[] = self::description( $container );
		}
		if ( ! in_array( 'table' , $container->blocks ) ){
			$html[] = '</td>';
		}

		$html = apply_filters( self::plugin . '/fields/field-end_wrap', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function label( $container, $output = 'string', $html = array() ) {
		$html[] = '<' . ( 'meta' === $container->type ? 'td' : 'th' ) . ' scope="row" colspan="1">';

		if ( isset( $container->label ) && false !== $container->label ) {
			$html[] = '<label class="' . self::abbr( 'field-label' ) . '" for="' . self::id( $container->id ) . '">' . $container->label . '</label>';
		}

		$html[] = '</' . ( 'meta' === $container->type ? 'td' : 'th' ) . '>';

		$html = apply_filters( self::plugin . '/fields/field-label', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function actions( $container, $output = 'string', $html = array() ) {
		if ( empty( $container->actions ) ) {
			return ( 'string' === $output ? '' : array() );
		}

		$html[] = '<div class="' . self::abbr( 'actions' ) . '">';
		foreach ( $container->actions as $action => $label ) {
			$html[] = get_submit_button( $label, 'primary', self::plugin . '[actions][' . $action . ']', false );
		}
		$html[] = '</div>';

		$html = apply_filters( self::plugin . '/fields/field-actions', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function description( $container, $output = 'string', $html = array() ) {
		if ( ! empty( $container->description ) ) {
			$html[] = '<p class="' . self::abbr( 'field-description' ) . '">' . $container->description . '</p>';;
		}

		$html = apply_filters( self::plugin . '/fields/field-description', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}


	/******************
	 * Static methods *
	 ******************/

	public static function is_valid_type( $type = false ){
		// a list of valid field types, to prevent screwy behaviour
		return in_array( $type, apply_filters( self::plugin . '/fields/valid_types', self::$valid_types ) );
	}

	public static function name( $indexes = array() ){
		return self::plugin . '[' . implode( '][', (array) $indexes ) . ']';
	}

	public static function id( $id = array(), $container = false ){
		if ( ! is_array( $id ) ){
			$id = (array) $id;
		}
		if ( $container ){
			$id[] = 'container';
		}
		return self::plugin . '-field-' . implode( '-', (array) $id );
	}

	public static function attr( $attributes = array(), $html = array() ) {
		if ( is_scalar( $attributes ) ){
			return false;
		}

		$attributes = (array) $attributes;

		foreach ( $attributes as $key => $value ) {
			if ( is_null( $value ) || false === $value ){
				continue;
			}

			if ( '_' === substr( $key, 0, 1 ) ){
				$key = substr_replace( $key, 'data-', 0, 1 );
			}

			if ( 'class' === $key && ! is_array( $value ) ){
				$value = (array) $value;
			}

			$attr = $key;

			if ( ! is_scalar( $value ) ) {
				if ( 'class' === $key ){
					$value = array_map( array( __CLASS__, 'abbr' ), (array) $value );
					$value = array_map( 'sanitize_html_class', $value );
					$value = implode( ' ', $value );
				} else {
					$value = htmlspecialchars( json_encode( $value ), ENT_QUOTES, 'UTF-8' );
				}
			}
			if ( ! is_bool( $value ) || true !== $value ){
				$attr .= '="' . $value . '"';
			}

			$html[ $key ] = $attr;
		}

		return ' ' . implode( ' ', $html );
	}

	public static function parse( $field, &$container = null ){
		if ( is_scalar( $field ) ){
			if ( ! is_string( $field ) ){
				return false;
			}

			$field = (object) array(
				'type' => $field,
			);
		} elseif ( is_array( $field ) ){
			$field = (object) $field;
		}

		if ( ! is_a( $container, __CLASS__ ) ){
			$container = (object) wp_parse_args( $container, self::$default_container );
		}
		if ( ! isset( $container->id ) ) {
			$container->id = (array) self::abbr( uniqid() );
		}

		$field = (object) wp_parse_args( $field, ( ! empty( $container->field ) ? $container->field : array() ) );

		// Setup Private Attributes (_*)
		if ( isset( $field->_id ) ){

		} elseif ( empty( $field->id ) ){
			$field->_id = (array) $container->id;
		} else {
			$field->_id = (array) $field->id;
		}

		if ( isset( $field->_name ) ){

		} elseif ( ! isset( $field->name ) ){
			$field->_name = (array) ( isset( $container->field->name ) ? $container->field->name : $field->_id );
		} else {
			$field->_name = (array) $field->name;
		}

		// Setup Public Attributes
		if ( empty( $field->type ) ){
			$field->type = $container->type;
		}
		$field->_type = $field->type;

		$field->id = self::id( $field->_id );
		$field->name = self::name( $field->_name );

		switch ( $field->type ) {
			case 'heading':
				if ( ! isset( $field->title ) ){
					$field->title = '';
				}

				if ( ! isset( $field->description ) ){
					$field->description = '';
				}

				$container->has_label = false;
				$container->blocks = array( 'actions' );
				break;
			case 'meta':
				if ( ! isset( $container->label ) ){
					$container->label = '';
				}

				$container->has_label = false;
				$container->blocks = array( 'actions' );
				break;
			case 'input':
				# code...
				break;
			case 'text':
				if ( empty( $field->size ) ){
					$field->size = 'medium';
				}
				break;
			case 'number':
				if ( empty( $field->size ) ){
					$field->size = 'tiny';
				}
				break;
			case 'radio':
				unset( $field->size );

				if ( ! isset( $field->options ) ){
					$field->options = array();
				}
				$field->options = (array) $field->options;

				break;
			case 'checkbox':
				unset( $field->size );

				if ( ! isset( $field->options ) ){
					$field->options = array();
				}

				if ( ! is_array( $field->options ) ){
					$field->options = array(
						1 => $field->options,
					);
				}

				break;
			case 'dropdown':
				if ( isset( $field->multiple ) && $field->multiple ){
					$field->type = 'hidden';
				} else {
					if ( ! isset( $field->options ) ){
						$field->options = array();
					}
					$field->options = (array) $field->options;
				}

				break;
			case 'interval':

				break;
			case 'date':
				$field->type = 'text';
				$field->size = 'small';
				break;
		}

		$field = apply_filters( self::plugin . '/fields/field', $field, $container );
		$container = apply_filters( self::plugin . '/fields/container', $container, $field );

		$field = apply_filters( self::plugin . '/fields/field-' . $field->_type, $field, $container );
		$container = apply_filters( self::plugin . '/fields/container-' . $field->_type, $container, $field );

		if ( ! empty( $field->class ) ){
			$field->class = (array) $field->class;
		}
		$field->class[] = 'field';
		$field->class[] = 'type-' . $field->_type;

		if ( ! empty( $field->size ) ){
			$field->class[] = 'size-' . $field->size;
		}

		return $field;
	}

	/*****************
	 * Field Methods *
	 *****************/

	public static function type_input( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$content[] = '<input' . self::attr( $field ) . '/>';

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_number( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_text( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_hidden( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_date( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_heading( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$content[] = '<h3>' . $field->title . '</h3>';

		if ( ! empty( $field->description ) ){
			$content[] = '<div class="' . self::abbr( 'field-description' ) . '">' . $field->description . '</div>';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_radio( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		foreach ( $field->options as $value => $label ) {
			$checkbox = clone $field;
			$radio->value = $value;

			$content[] = self::type_input( $radio, null, 'string', array() );
			$content[] = '<label class="' . self::abbr( 'field-label' ) . '" for="' . $field->id . '">' . $label . '</label>';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_checkbox( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		foreach ( $field->options as $value => $label ) {
			$checkbox = clone $field;
			$checkbox->_id[] = sanitize_html_class( $value );
			$checkbox->value = $value;

			if ( isset( $field->value ) && $field->value === $checkbox->value ){
				$checkbox->checked = true;
			}

			$content[] = self::type_input( $checkbox, null, 'string', array() );
			$content[] = '<label class="' . self::abbr( 'field-label' ) . '" for="' . self::id( $checkbox->_id ) . '">' . $label . '</label>';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_dropdown( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		if ( isset( $field->multiple ) && $field->multiple ){
			$content[] = self::type_input( $field, null, 'string', array() );
		} else {
			$content[] = '<select' . self::attr( $field ) . '>';
			$content[] = '<option></option>';
			foreach ( $field->options as $option ) {
				$content[] = '<option' . self::attr( $option ) . '>' . esc_attr( $option['text'] ) . '</option>';
			}
			$content[] = '</select>';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_range( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$min = clone $field;
		$min->_id[] = 'min';
		$min->_name[] = 'min';
		$min->type = 'number';
		$min->{'data-type'} = 'min';
		$min->max = 25;
		$min->min = 1;
		$min->class = array();
		$min->placeholder = esc_attr__( 'e.g.: 3', self::plugin );

		$max = clone $field;
		$max->_id[] = 'max';
		$max->_name[] = 'max';
		$max->{'data-type'} = 'max';
		$max->type = 'number';
		$max->max = 25;
		$max->min = 1;
		$max->class = array();
		$max->disabled = true;
		$max->placeholder = esc_attr__( 'e.g.: 12', self::plugin );

		$content[] = self::type_input( $min, null, 'string', array() );
		$content[] = '<div class="dashicons dashicons-arrow-right-alt2 dashicon-date" style="display: inline-block;"></div>';
		$content[] = self::type_input( $max, null, 'string', array() );

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_meta( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$table = clone $container;
		$table->blocks = array( 'heading', 'table' );
		$table->heads = array(
			array(
				'style' => 'width: 10px;',
				'class' => 'order-table',
				'html' => '',
			),
			array(
				'style' => 'width: 85px;',
				'html' => '',
			),
			array(
				'html' => '',
			),
			array(
				'style' => 'width: 10px;',
				'class' => 'actions-table',
			),
		);

		$blocks = array(
			array(
				'html' => '',
				'class' => 'order-table',
			),
			'label', 'fields',
			array(
				'html' => '',
				'class' => 'actions-table',
			),
		);

		$meta_type = clone $container;
		$meta_type->id[] = 'type';
		$meta_type->label = __( 'Type', self::plugin );
		$meta_type->description = __( 'Select a type of the Meta Field', self::plugin );
		$meta_type->blocks = $blocks;

		$meta_name = clone $container;
		$meta_name->id[] = 'name';
		$meta_name->label = __( 'Name', self::plugin );
		$meta_name->description = __( 'Select the name for Meta Field', self::plugin );
		$meta_name->blocks = $blocks;

		$meta_value = clone $container;
		$meta_value->id[] = 'value';
		$meta_value->label = __( 'Configuration', self::plugin );
		$meta_value->description = __( '', self::plugin );
		$meta_value->blocks = $blocks;

		$type = clone $field;
		$type->_id[] = 'type';
		$type->_name[] = 'type';
		$type->type = 'dropdown';
		$type->options = apply_filters( self::plugin . '/fields/meta_types', array(
			array(
				'value' => 'digit',
				'text' => __( 'Digit', self::plugin ),
			),
			array(
				'value' => 'range',
				'text' => __( 'Range of Numbers', self::plugin ),
			),
		) );
		$type->class = array();
		$type->placeholder = esc_attr__( 'Select a Field type', self::plugin );

		$name = clone $field;
		$name->_id[] = 'name';
		$name->_name[] = 'name';
		$name->type = 'text';
		$name->class = array();
		$name->placeholder = esc_attr__( 'Newborn Meta needs a Name, E.g.: _new_image', self::plugin );

		$content[] = $meta_type->build( self::type_dropdown( $type, null, 'string' ) );
		$content[] = $meta_name->build( self::type_text( $name, null, 'string' ) );
		$content[] = $meta_value->build( '' );

		$content = $table->build( $content );

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_interval( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$min = clone $field;
		$min->_id[] = 'min';
		$min->_name[] = 'min';
		$min->type = 'date';
		$min->{'data-type'} = 'min';
		$min->class = array();
		$min->placeholder = esc_attr__( 'yyyy-mm-dd', self::plugin );

		$max = clone $field;
		$max->_id[] = 'max';
		$max->_name[] = 'max';
		$max->type = 'date';
		$max->{'data-type'} = 'max';
		$max->class = array();
		$max->placeholder = esc_attr__( 'yyyy-mm-dd', self::plugin );

		$interval = clone $field;
		$interval->_id[] = 'interval';
		$interval->_name[] = 'interval';
		$interval->type = 'dropdown';
		$interval->class = array();
		$interval->{'data-placeholder'} = esc_attr__( 'Select an Interval', self::plugin );
		$interval->options = Dates::get_intervals();

		$content[] = self::type_dropdown( $interval, null, 'string' );
		$content[] = self::type_date( $min, null, 'string' );
		$content[] = '<div class="dashicons dashicons-arrow-right-alt2 dashicon-date" style="display: inline-block;"></div>';
		$content[] = self::type_date( $max, null, 'string' );

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_raw( $field, $container = null, $output = 'string' ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		if ( ! empty( $field->html ) ){
			$content[] = $field->html;
		} else {
			$content = '';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	/*

	public static function type_textarea( $field, $container = null, $output = 'string' ) {
		if ( is_array( $container ) ){
			$field[] = $this->start_container();
			$field[] = $this->label();
			$field[] = $this->start_wrap();
		}

		$field[] = '<textarea' . $this->attr() . '>' . esc_html( stripslashes( $this->value ) ) . '</textarea>';

		if ( is_array( $container ) ){
			$field[] = $this->end_wrap();
			$field[] = $this->end_container();
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $field );
		} else {
			return $field;
		}
	}

	public static function type_wysiwyg( $field, $container = null, $output = 'string' ) {
		$settings = array(
			'teeny'   => true,
			'wpautop' => true,
		);
		ob_start();
		wp_editor( html_entity_decode( ( $this->value ) ), $this->name, $settings );
		$editor = ob_get_clean();

		if ( is_array( $container ) ){
			$field[] = $this->start_container();
			$field[] = $this->label();
			$field[] = $this->start_wrap();
		}

		$field[] = $editor;

		if ( is_array( $container ) ){
			$field[] = $this->end_wrap();
			$field[] = $this->end_container();
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $field );
		} else {
			return $field;
		}
	}

	 */

} // end class