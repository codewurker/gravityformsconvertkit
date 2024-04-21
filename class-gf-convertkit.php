<?php

defined( 'ABSPATH' ) || die();

// Include the Gravity Forms Feed Add-On Framework.
GFForms::include_feed_addon_framework();

/**
 * Gravity Forms ConvertKit Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Gravity Forms
 * @copyright Copyright (c) 2023, Gravity Forms
 */
class GF_ConvertKit extends GFFeedAddOn {

	const SETTING_CONVERTKIT_API_KEY = 'convertkit_api_key';

	const SETTING_CONVERTKIT_API_SECRET = 'convertkit_api_secret';

	const SCRIPT_CACHE_KEY = 'creator_network_recommendations_script';

	const FORMS_CACHE_KEY = 'convertkit_forms';

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @var    GF_ConvertKit $_instance If available, contains an instance of this class
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Gravity Forms ConvertKit Add-On.
	 *
	 * @since  1.0
	 * @var    string $_version Contains the version.
	 */
	protected $_version = GF_CONVERTKIT_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = GF_CONVERTKIT_MIN_GF_VERSION;

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsconvertkit';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsconvertkit/convertkit.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this add-on can be found.
	 *
	 * @since  1.0
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'https://gravityforms.com';

	/**
	 * Defines the title of this add-on.
	 *
	 * @since  1.0
	 * @var    string $_title The title of the add-on.
	 */
	protected $_title = 'Gravity Forms ConvertKit Add-On';

	/**
	 * Defines the short title of the add-on.
	 *
	 * @since  1.0
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'ConvertKit';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capabilities needed for the Gravity Forms ConvertKit Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_convertkit', 'gravityforms_convertkit_uninstall' );

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_convertkit';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_convertkit';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_convertkit_uninstall';

	/**
	 * Saves an API instance for ConvertKit.
	 *
	 * @since  1.0
	 * @var    GF_ConvertKit_API $api null until instance is set.
	 */
	protected $api = null;

	/**
	 * Enable background feed processing to prevent performance issues delaying form submission completion.
	 *
	 * @since 1.0
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = true;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @since  1.0
	 *
	 * @return GF_ConvertKit $_instance An instance of the GF_ConvertKit class
	 */
	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

	/**
	 * Register initialization hooks.
	 *
	 * @since  1.0
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe member to ConvertKit only when payment is received.', 'gravityformsconvertkit' ),
			)
		);

		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_creator_network_recommendations_script' ), 10, 2 );

	}

	/**
	 * Register admin initialization hooks.
	 *
	 * @since  1.0
	 */
	public function init_admin() {

		$this->maybe_populate_api_key();
		$this->maybe_migrate_feeds();

		add_action( 'admin_head', array( $this, 'output_deactivate_css' ) );

		parent::init_admin();

	}

	/**
	 * Performs any additional upgrade tasks.
	 *
	 * @since 1.0
	 *
	 * @param string $previous_version The previous installed version of the add-on.
	 *
	 * @return void
	 */
	public function upgrade( $previous_version ) {
		$this->maybe_populate_api_key();
		$this->maybe_migrate_feeds( false );
	}

	/**
	 * Output the CSS to ensure our convertkit deactivation message shows on the plugins screen.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function output_deactivate_css() {
		$screen = get_current_screen();

		if ( empty( $screen ) || $screen->id !== 'plugins' ) {
			return;
		}

		?>
		<style>
			.gf-notice[data-gf_dismissible_key*="gf_convertkit_disable_message"] {
				display: inherit!important;
			}
		</style>
		<?php
	}

	/**
	 * Initialize the ConvertKit API.
	 *
	 * @since  1.0
	 *
	 * @return bool|null
	 */
	public function initialize_api() {

		if ( ! is_null( $this->api ) ) {
			return is_object( $this->api );
		}

		// Initialize the ConvertKit API.
		if ( ! class_exists( 'GF_ConvertKit_API' ) ) {
			require_once 'includes/class-gf-convertkit-api.php';
		}

		$this->api = new GF_ConvertKit_API( $this, $this->get_convertkit_api_key(), $this->get_convertkit_api_secret() );

		$response = $this->get_api_forms( true );

		if ( is_wp_error( $response ) ) {
			$this->api = false;

			return false;
		}

		return true;

	}

	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Define plugin settings fields.
	 *
	 * @since  1.0
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		$account_settings_open_a_tag = '<a href="https://app.convertkit.com/account_settings/advanced_settings" target="_blank">';

		return array(
			array(
				'title'       => __( 'Settings', 'gravityformsconvertkit' ),
				'description' => '<p>' . esc_html__( 'ConvertKit makes it easy to send emails to your customers. You can use Gravity Forms to collect customer information and automatically subscribe customers to your ConvertKit forms.', 'gravityformsconvertkit' ) . '</p><p>' . sprintf(
						/* translators: %s: Link to sign up for a ConvertKit account */
						esc_html__( 'Don\'t have a ConvertKit account? %s', 'gravityformsconvertkit' ),
						'<a href="https://app.convertkit.com/users/signup" target="_blank">' . esc_html__( 'Sign up here', 'gravityformsconvertkit' ) . '</a>'
					) . '</p>',
				'fields'      => array(
					array(
						'name'              => self::SETTING_CONVERTKIT_API_KEY,
						'label'             => esc_html__( 'API Key', 'gravityformsconvertkit' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'description'       => sprintf(
							'<p>%s</p>',
							$account_settings_open_a_tag . esc_html__( 'Click here to find your ConvertKit API Key', 'gravityformsconvertkit' ) . '</a>'
						),
						'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
					),
					array(
						'name'              => self::SETTING_CONVERTKIT_API_SECRET,
						'label'             => esc_html__( 'API Secret', 'gravityformsconvertkit' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'description'       => sprintf(
							'<p>%s</p>',
							$account_settings_open_a_tag . esc_html__( 'Click here to find your ConvertKit API Secret', 'gravityformsconvertkit' ) . '</a>'
						),
						'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
					),
					array(
						'name'          => 'imported_feeds',
						'type'          => 'hidden',
						'default_value' => '1',
					),
				),
			),
		);

	}

	/**
	 * The callback used to validate the API Key and API Secret fields.
	 *
	 * @since  1.0
	 *
	 * @param string                                           $value The value to be validated.
	 * @param Gravity_Forms\Gravity_Forms\Settings\Fields\Text $field The setting field being validated.
	 *
	 * @return null|bool
	 */
	public function plugin_settings_fields_feedback_callback( $value, $field ) {

		if ( empty( $value ) ) {
			return null;
		}

		if ( ! class_exists( 'GF_ConvertKit_API' ) ) {
			require_once 'includes/class-gf-convertkit-api.php';
		}

		if ( $field->name === self::SETTING_CONVERTKIT_API_SECRET ) {
			$api = new GF_ConvertKit_API( $this, '', $value );
		} else {
			$api = new GF_ConvertKit_API( $this, $value, '' );
		}

		$result = ! is_wp_error( $api->get_forms() );
		$this->log_debug( __METHOD__ . sprintf( '(): Is %s valid? ', $field->name ) . var_export( $result, true ) );

		return $result;
	}

	// # FORM SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Displays tabs above the form settings, feed list, and feed edit page content.
	 *
	 * @since 1.0
	 *
	 * @param array $form The current form.
	 *
	 * @return void
	 */
	public function form_settings( $form ) {
		$tab_attributes    = $this->get_form_settings_tab_attributes();
		$url               = remove_query_arg( 'fid' );
		$feed_settings_url = add_query_arg( array( 'settingstype' => 'feed' ), $url );
		$form_settings_url = add_query_arg( array( 'settingstype' => 'form' ), $url );

		echo '<nav class="gform-settings-tabs__navigation" role="tablist" style="margin-bottom:.875rem">
			<a role="tab" href="' . esc_url( $feed_settings_url ) . '" ' . esc_attr( $tab_attributes['feed_link_attrs'] ) . '>' . esc_html__( 'Feed Settings', 'gravityformsconvertkit' ) . '</a>
			<a role="tab" href="' . esc_url( $form_settings_url ) . '" ' . esc_attr( $tab_attributes['form_link_attrs'] ) . '>' . esc_html__( 'Form Settings', 'gravityformsconvertkit' ) . '</a>
		</nav>';

		if ( 'form_settings' == $tab_attributes['current_tab'] ) {
			if ( ! $this->initialize_api() ) {
				echo $this->get_error_message_html( $this->configure_addon_message() );

				return;
			}

			$this->get_settings_renderer()->render();
		} else {
			if ( $this->is_detail_page() ) {
				$this->feed_edit_page( $form, $this->get_current_feed_id() );
			} else {
				$this->feed_list_page( $form );
			}
		}
	}

	/**
	 * Returns HTML for the error message.
	 *
	 * @since 1.0
	 *
	 * @param string $message The message to be included in the HTML.
	 *
	 * @return string
	 */
	public function get_error_message_html( $message ) {
		if ( empty( $message ) ) {
			return '';
		}

		return '<div class="error-alert-container alert-container">
					<div class="gform-alert gform-alert--error" data-js="gform-alert">
						<span class="gform-alert__icon gform-icon gform-icon--circle-close" aria-hidden="true"></span>
						<div class="gform-alert__message-wrap">
							<p class="gform-alert__message">' . $message . '</p>
						</div>
					</div>
				</div>';
	}

	/**
	 * Returns the attributes for the tabs being displayed on the Form Settings > ConvertKit page.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_form_settings_tab_attributes() {
		$tab_attributes = array();
		$active_attrs   = 'aria-selected=true class=active';
		$inactive_attrs = 'aria-selected=false';

		if ( rgget( 'settingstype' ) == 'form' ) {
			$tab_attributes['current_tab']     = 'form_settings';
			$tab_attributes['feed_link_attrs'] = $inactive_attrs;
			$tab_attributes['form_link_attrs'] = $active_attrs;
		} else {
			$tab_attributes['current_tab']     = 'feed';
			$tab_attributes['feed_link_attrs'] = $active_attrs;
			$tab_attributes['form_link_attrs'] = $inactive_attrs;
		}

		return $tab_attributes;
	}

	/**
	 * Returns the fields to be displayed on the Form Settings tab.
	 *
	 * @since 1.0
	 *
	 * @param array $form The current form.
	 *
	 * @return array[]
	 */
	public function form_settings_fields( $form ) {
		$settings = array(
			array(
				'title'  => __( 'ConvertKit Form Settings', 'gravityformsconvertkit' ),
				'fields' => array(
					array(
						'name'          => 'enable_creator_network_recommendations',
						'label'         => esc_html__( 'Enable Creator Network Recommendations', 'gravityformsconvertkit' ),
						'type'          => 'toggle',
						'default_value' => 'false',
						'tooltip'       => sprintf(
								/* translators: %1$s: Opening strong tag for the setting label. %2$s: Closing strong tag. */
								esc_html__( '%1$sEnable Creator Network Recommendations%2$sDisplays the Creator Network Recommendations modal on submission when the form is embedded using Ajax.', 'gravityformsconvertkit' ),
								'<strong>', '</strong>'
						),
					),
				),
			),
		);

		$script = $this->is_creator_network_recommendations_script_supported( true );
		if ( is_wp_error( $script ) ) {
			$settings[0]['fields'][0]['description'] = $this->get_error_message_html( $this->get_form_settings_error_message( $script->get_error_code() ) );
		}

		return $settings;
	}

	/**
	 * Determines if Creator Network Recommendations is supported.
	 *
	 * @since 1.0
	 *
	 * @param bool $force Whether to bypass caching when making the get_creator_network_recommendations_script() call.
	 *
	 * @return true|WP_Error
	 */
	public function is_creator_network_recommendations_script_supported( $force = false ) {
		if ( ! GFFormsModel::is_html5_enabled() ) {
			return new WP_Error( 'html5' );
		} elseif ( ! $this->get_convertkit_api_secret() || ! $this->initialize_api() ) {
			return new WP_Error( 'api' );
		}

		$result = $this->get_creator_network_recommendations_script( $force );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'script_error' );
		} elseif ( empty( $result['enabled'] ) ) {
			return new WP_Error( 'plan' );
		} elseif ( empty( $result['embed_js'] ) ) {
			return new WP_Error( 'script_error' );
		}

		return true;
	}

	/**
	 * Returns an array containing the enabled status and JS for the Creator Network Recommendations feature.
	 *
	 * @since 1
	 *
	 * @param bool $force Whether to bypass caching, so a new REST API request is made.
	 *
	 * @return array|WP_Error
	 */
	public function get_creator_network_recommendations_script( $force = false ) {
		if ( ! $this->initialize_api() ) {
			return array();
		}

		$result = $force ? null : GFCache::get( self::SCRIPT_CACHE_KEY );
		if ( empty( $result ) ) {
			$result = $this->api->get_recommendations_script();
			if ( ! is_wp_error( $result ) ) {
				GFCache::set( self::SCRIPT_CACHE_KEY, $result, true );
			}
			$this->log_debug( __METHOD__ . '(): ' . print_r( $result, true ) );
		}

		return $result;
	}

	/**
	 * Returns the error message to be used for the given error code.
	 *
	 * @since 1.0
	 *
	 * @param string $code The error code.
	 *
	 * @return string
	 */
	public function get_form_settings_error_message( $code ) {
		switch ( $code ) {
			case 'html5':
				return sprintf(
					/* translators: %1$s: Opening a tag. %2$s: Closing a tag. */
					esc_html__( 'To use Creator Network Recommendations, please enable HTML5 on the %1$sForms > Settings%2$s page.', 'gravityformsconvertkit' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=gf_settings' ) ) . '">', '</a>'
				);

			case 'api':
				return sprintf(
					/* translators: %1$s: Opening a tag. %2$s: Closing a tag. */
					esc_html__( 'To use Creator Network Recommendations, please configure the API Key and API Secret on the %1$sForms > Settings > ConvertKit%2$s page.', 'gravityformsconvertkit' ),
					'<a href="' . esc_url( $this->get_plugin_settings_url() ) . '">', '</a>'
				);

			case 'plan':
				return sprintf(
					/* translators: %1$s: Opening a tag for the account billing page. %2$s: Closing a tag. %3$s: Opening a tag for the creator profile page. %4$s: Opening a tag for the creator network page. */
					esc_html__( 'To use Creator Network Recommendations, please make sure you have a %1$spaid ConvertKit plan%2$s, a configured %3$sCreator Profile%2$s, and that Recommendations are enabled on the %4$sCreator Network%2$s page.', 'gravityformsconvertkit' ),
					'<a href="https://app.convertkit.com/account_settings/billing/" target="_blank">', '</a>', '<a href="https://app.convertkit.com/creator_profile/" target="_blank">', '<a href="https://app.convertkit.com/creator-network/" target="_blank">'
				);

			case 'script_error':
				return esc_html__( 'An unexpected error occurred whilst retrieving the Creator Network Recommendations script for your ConvertKit account.', 'gravityformsconvertkit' );

			default:
				return '';
		}
	}


	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Define feed settings fields.
	 *
	 * @since  1.0
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		// Define Feed Settings and Name Field.
		$fields = array(
			'title'       => __( 'ConvertKit Feed Settings', 'gravityformsconvertkit' ),
			'description' => '',
			'fields'      => array(
				array(
					'name'     => 'feed_name',
					'label'    => __( 'Name', 'gravityformsconvertkit' ),
					'type'     => 'text',
					'class'    => 'medium',
					'required' => true,
					'tooltip'  => sprintf( '<h6>%s</h6>%s', __( 'Name', 'gravityformsconvertkit' ), __( 'Enter a feed name to uniquely identify this feed.', 'gravityformsconvertkit' ) ),
				),
			),
		);

		// Add Form selection.
		$form_fields = $this->get_forms();
		if ( ! is_wp_error( $form_fields ) ) {
			$fields['fields'][] = array(
				'name'     => 'form_id',
				'label'    => __( 'ConvertKit Form', 'gravityformsconvertkit' ),
				'type'     => 'select',
				'required' => true,
				'choices'  => $form_fields,
				'tooltip'  => sprintf( '<h6>%s</h6>%s', __( 'ConvertKit Form', 'gravityformsconvertkit' ), __( 'Select the ConvertKit form to which you would like to add your contacts.', 'gravityformsconvertkit' ) ),
			);
		}

		// Add Tag selection.
		$tag_fields = $this->get_tags();
		if ( ! is_wp_error( $tag_fields ) ) {
			$fields['fields'][] = array(
				'name'     => 'tag_id',
				'label'    => __( 'ConvertKit Tag', 'gravityformsconvertkit' ),
				'type'     => 'select',
				'required' => false,
				'choices'  => $tag_fields,
				'tooltip'  => sprintf( '<h6>%s</h6>%s', __( 'ConvertKit Tag', 'gravityformsconvertkit' ), __( 'Select the ConvertKit tag to which you would like to assign your contacts.', 'gravityformsconvertkit' ) ),
			);
		}

		$fields['fields'][] = array(
			'name'      => 'field_map',
			'label'     => __( 'Map Fields', 'gravityformsconvertkit' ),
			'type'      => 'field_map',
			'field_map' => array(
				array(
					'name'       => 'email',
					'label'      => __( 'Email', 'gravityformsconvertkit' ),
					'required'   => true,
					'field_type' => array( 'email', 'hidden' ),
				),
				array(
					'name'       => 'name',
					'label'      => __( 'First Name', 'gravityformsconvertkit' ),
					'required'   => false,
					'field_type' => array( 'name', 'text', 'hidden' ),
				),
				array(
					'name'     => 'tag',
					'label'    => __( 'Additional Tag', 'gravityformsconvertkit' ),
					'required' => false,
				),
			),
			'tooltip'   => sprintf( '<h6>%s</h6>%s', __( 'Map Fields', 'gravityformsconvertkit' ), __( 'Associate email address and subscriber name with the appropriate Gravity Forms fields.', 'gravityformsconvertkit' ) ),
		);

		// Add Conditional Logic.
		$fields['fields'][] = array(
			'name'    => 'conditions',
			'label'   => __( 'Conditional Logic', 'gravityformsconvertkit' ),
			'type'    => 'feed_condition',
			'tooltip' => sprintf( '<h6>%s</h6>%s', __( 'Conditional Logic', 'gravityformsconvertkit' ), __( 'When conditional logic is enabled, form submissions will only be exported to ConvertKit when the conditions are met. When disabled all form submissions will be exported.', 'gravityformsconvertkit' ) ),
		);

		// Add Custom Fields.
		$fields = $this->get_custom_fields( $fields );

		// Return.
		return array( $fields );

	}

	/**
	 * Enable feed creation.
	 *
	 * @since  1.0
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		return $this->initialize_api();

	}

	/**
	 * Enable feed duplication.
	 *
	 * @since  1.0
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {

		return false;

	}





	// # FEED LIST -----------------------------------------------------------------------------------------------------

	/**
	 * Define the feed list table columns.
	 *
	 * @since  1.0
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feed_name' => __( 'Name', 'gravityformsconvertkit' ),
			'form_id'   => __( 'Form', 'gravityformsconvertkit' ),
		);

	}

	/**
	 * Define the feed list table column values.
	 *
	 * @since   1.0
	 *
	 * @param   array $feed   ConvertKit Feed.
	 * @return  string
	 */
	public function get_column_value_form_id( $feed ) {

		// Get ConvertKit Form ID.
		$form_id = rgars( $feed, 'meta/form_id' );

		// Get Forms to test that the API Key is valid.
		if ( ! $this->initialize_api() ) {
			return;
		}

		$forms = $this->get_api_forms();

		// If an error occured, bail.
		if ( is_wp_error( $forms ) || empty( $forms ) ) {
			return __( 'N/A', 'gravityformsconvertkit' );
		}

		// Return Form Name, linked to ConvertKit.
		foreach ( $forms as $form ) {
			if ( rgar( $form, 'id' ) == $form_id ) {
				return sprintf(
					'<a href="%1$s" target="_blank">%2$s</a>',
					esc_attr(
						esc_url(
							sprintf(
								'https://app.convertkit.com/forms/designers/%s/edit',
								$form_id
							)
						)
					),
					esc_html( $form['name'] )
				);
			}
		}

	}





	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process feed.
	 *
	 * @since  1.0
	 *
	 * @param array $feed  Feed object.
	 * @param array $entry Entry object.
	 * @param array $form  Form object.
	 *
	 * @return array The Entry object.
	 */
	public function process_feed( $feed, $entry, $form ) {

		$convertkit_form          = rgars( $feed, 'meta/form_id' );
		$tag_id                   = rgars( $feed, 'meta/tag_id' );
		$field_map_email          = rgars( $feed, 'meta/field_map_email' );
		$field_map_name           = rgars( $feed, 'meta/field_map_name' );
		$field_map_tag            = rgars( $feed, 'meta/field_map_tag' );
		$convertkit_custom_fields = rgars( $feed, 'meta/convertkit_custom_fields' );

		$email        = $this->get_field_value( $form, $entry, $field_map_email );
		$name         = $this->get_field_value( $form, $entry, $field_map_name );
		$tag          = $this->get_field_value( $form, $entry, $field_map_tag );
		$fields       = array();
		$entry_tag_id = false;

		if ( empty( $email ) ) {
			$this->add_note(
				$entry['id'],
				__( 'Error Subscribing: The field mapped to the email address contains no value.', 'gravityformsconvertkit' ),
				'error'
			);
			$this->log_debug( __METHOD__ . '(): Error subscribing to ConvertKit form: The field mapped to the email contains no value.' );

			return null;
		}

		if ( ! GFCommon::is_valid_email( $email ) ) {
			$this->add_note(
				$entry['id'],
				sprintf(
				/* translators: Field Value */
					__( 'Error Subscribing: The field mapped to the email address contains an invalid email value %s.', 'gravityformsconvertkit' ),
					$email
				),
				'error'
			);
			$this->log_debug( __METHOD__ . '(): Error subscribing to ConvertKit form: The field mapped to the email contains an invalid value.' );

			return null;
		}

		if ( ! $this->initialize_api() ) {
			return;
		}

		$fields = $this->process_feed_custom_fields( $form, $entry, $convertkit_custom_fields );

		// If an error occured, log it as a note in the Gravity Forms Entry,
		// and set fields to false so we can still attempt to subscribe the user.
		if ( is_wp_error( $fields ) ) {
			$this->add_note(
				$entry['id'],
				sprintf(
				/* translators: Error message */
					__( 'Error processing Custom Field Mappings: %s', 'gravityformsconvertkit' ),
					$fields->get_error_message()
				),
				'error'
			);
			$this->log_debug( __METHOD__ . '(): Error processing Custom Field Mappings: ' . $fields->get_error_message() );
			$fields = false;
		}

		// Get Entry's Tag ID Mapping.
		if ( ! empty( $tag ) ) {
			$entry_tag_id = $this->process_feed_tag( $tag );

			// If an error occured, log it as a note in the Gravity Forms Entry.
			if ( is_wp_error( $entry_tag_id ) ) {
				$this->add_note(
					$entry['id'],
					sprintf(
					/* translators: Error message */
						__( 'Error processing Tag Field Mapping: %s', 'gravityformsconvertkit' ),
						$entry_tag_id->get_error_message()
					),
					'error'
				);
				$this->log_debug( __METHOD__ . '(): Error processing Tag Field Mappings: ' . $entry_tag_id->get_error_message() );
			}
		}

		// Build array of Tag IDs, which might comprise of the Feed's Tag, Entry's Tag Field value, both or neither.
		$tag_ids = $this->build_tag_ids_array( array( $tag_id, $entry_tag_id ) );

		$this->log_debug( __METHOD__ . '(): Attempting to subscribe user to form ' . $convertkit_form . ' with parameters: ' . print_r( array( 'email' => $email, 'name' => $name, 'custom_fields' => $fields, 'tags' => $tag_ids ), true ) );

		$response = $this->api->subscribe( $convertkit_form, $email, $name, $fields, $tag_ids );

		// If an error occured, log it as a note in the Gravity Forms Entry.
		if ( is_wp_error( $response ) ) {
			$this->add_note(
				$entry['id'],
				sprintf(
				/* translators: Error message */
					__( 'Error Subscribing: %s', 'gravityformsconvertkit' ),
					$response->get_error_message()
				),
				'error'
			);
			$this->log_debug( __METHOD__ . '(): Error subscribing: ' . $response->get_error_message() );

			return null;
		}

		/**
		 * Runs actions immediately after the email address was successfully subscribed to the form.
		 *
		 * @since   1.0
		 *
		 * @param   array   $response           API Response
		 * @param   int     $convertkit_form    Form ID
		 * @param   string  $email              Email Address
		 * @param   string  $name               First Name
		 * @param   mixed   $fields             Custom Fields (false|array)
		 * @param   mixed   $tag_ids            Tags (false|array)
		 */
		do_action( 'gform_convertkit_api_form_subscribe_success', $response, $convertkit_form, $email, $name, $fields, $tag_ids );

		$this->log_debug( __METHOD__ . '(): User subscribed successfully to form ID ' . $convertkit_form . '.' );

		// Add success note to Gravity Forms Entry.
		$this->add_note(
			$entry['id'],
			__( 'Subscribed to ConvertKit successfully.', 'gravityformsconvertkit' ),
			'success'
		);

		return $response;

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------
	/**
	 * Get the ConvertKit API Key or Secret.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_convertkit_api_key( $return_secret = false ) {
		$setting = $return_secret ? self::SETTING_CONVERTKIT_API_SECRET : self::SETTING_CONVERTKIT_API_KEY;
		$value   = $this->get_plugin_setting( $setting );

		if ( 'string' !== gettype( $value ) ) {
			return '';
		};

		return $value;
	}

	/**
	 * Get the ConvertKit API Secret.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_convertkit_api_secret() {
		return $this->get_convertkit_api_key( true );
	}

	/**
	 * Gets and caches the forms from the ConvertKit API.
	 *
	 * @since 1.0
	 *
	 * @param bool $is_api_init Indicates if the forms are being requested as part of the API initialization process.
	 *
	 * @return array|WP_Error
	 */
	public function get_api_forms( $is_api_init = false ) {
		if ( ! $is_api_init && ! $this->initialize_api() ) {
			return array();
		}

		$forms = $is_api_init ? null : GFCache::get( self::FORMS_CACHE_KEY );
		if ( ! empty( $forms ) ) {
			return $forms;
		}

		$forms = $this->api->get_forms();
		if ( ! is_wp_error( $forms ) ) {
			GFCache::set( self::FORMS_CACHE_KEY, $forms, true, HOUR_IN_SECONDS );
		}

		return $forms;
	}

	/**
	 * Get the forms for the feed settings page.
	 *
	 * @since 1.0
	 *
	 * @return array An array of ConvertKit forms as values and labels for the feed settings.
	 */
	public function get_forms() {
		if ( ! $this->initialize_api() ) {
			return array();
		}

		$forms = $this->get_api_forms();
		if ( is_wp_error( $forms ) ) {
			return $forms;
		}

		$fields = array(
			array(
				'label' => __( 'Select a ConvertKit form', 'gravityformsconvertkit' ),
				'value' => '',
			),
		);

		foreach ( $forms as $form ) {
			$fields[] = array(
				'value' => esc_attr( $form['id'] ),
				'label' => esc_html( $form['name'] ),
			);
		}

		return $fields;
	}

	/**
	 * Get the tags for the feed settings page.
	 *
	 * @since 1.0
	 *
	 * @return array An array of ConvertKit tags as values and labels for the feed settings.
	 */
	private function get_tags() {
		if ( ! $this->initialize_api() ) {
			return array();
		}

		$tags = $this->api->get_tags();

		// Map to Gravity Forms Feed compatible array.
		$fields = array(
			array(
				'label' => __( '(No Tag)', 'gravityformsconvertkit' ),
				'value' => '',
			),
		);
		foreach ( $tags as $tag ) {
			$fields[] = array(
				'value' => esc_attr( $tag['id'] ),
				'label' => esc_html( $tag['name'] ),
			);
		}

		return $fields;

	}

	/**
	 * Get the custom fields from Convert Kit for the settings page.
	 *
	 * @since 1.0
	 *
	 * @param array $base_fields The feed settings fields to add the custom fields setting to.
	 *
	 * @return array The combined array of fields.
	 */
	private function get_custom_fields( $base_fields ) {
		if ( ! $this->initialize_api() ) {
			return array();
		}

		$custom_fields = $this->api->get_custom_fields();

		$fields = array();
		foreach ( $custom_fields as $custom_field ) {
			$fields[] = array(
				'value' => esc_attr( $custom_field['key'] ),
				'label' => esc_html( $custom_field['label'] ),
			);
		}

		// Sort Custom Fields in ascending order by label.
		array_splice(
			$base_fields['fields'],
			4,
			0,
			array(
				array(
					'name'           => 'convertkit_custom_fields',
					'label'          => '',
					'type'           => 'generic_map',
					'key_field'      => array(
						'allow_custom' => false,
						'choices'      => $fields,
					),
					'value_field'    => array(
						'allow_custom' => true,
					),
					'disable_custom' => true,
				),
			)
		);

		return $base_fields;

	}

	/**
	 * Return the tag IDs for mapped tags.
	 *
	 * @since 1.0
	 *
	 * @param array $submitted_tags Submitted tags.
	 *
	 * @return array Array of tag IDs.
	 */
	public function get_tag_ids( $submitted_tags ) {
		if ( ! $this->initialize_api() ) {
			return array();
		}

		$tags = $this->api->get_tags();

		$tag_ids = array();
		foreach ( $tags as $tag ) {
			$tag_ids[] = $tag['value'];
		}

		return $tag_ids;
	}

	/**
	 * Map Gravity Form Entry values to Custom Fields.
	 *
	 * @since   1.0
	 *
	 * @param   array $form                     The form object.
	 * @param   array $entry                    The entry array.
	 * @param   array $convertkit_custom_fields Array of custom fields.
	 * @return  WP_Error|Array
	 */
	private function process_feed_custom_fields( $form, $entry, $convertkit_custom_fields ) {

		$custom_fields = $this->api->get_custom_fields();

		if ( is_wp_error( $custom_fields ) ) {
			return $custom_fields;
		}

		$fields = array();
		foreach ( $custom_fields as $custom_field ) {
			// Only get values for custom fields mapped in the feed.
			foreach ( $convertkit_custom_fields as $mapped_field ) {
				if ( $mapped_field['key'] !== $custom_field['key'] ) {
					continue;
				}

				if ( $mapped_field['value'] === 'gf_custom' ) {
					if ( GFCommon::has_merge_tag( $mapped_field['custom_value'] ) ) {
						$fields[ $custom_field['key'] ] = GFCommon::replace_variables( $mapped_field['custom_value'], $form, $entry );
					} else {
						$fields[ $custom_field['key'] ] = $mapped_field['custom_value'];
					}
				} else {
					$fields[ $custom_field['key'] ] = $this->get_field_value( $form, $entry, $mapped_field['value'] );
				}
			}
		}

		return $fields;

	}

	/**
	 * Returns the Tag ID for the given Tag Name.
	 *
	 * @since   1.0
	 *
	 * @param   string $tag_name    Tag Name.
	 * @return  WP_Error|bool|int   Tag ID
	 */
	private function process_feed_tag( $tag_name ) {

		$tags = $this->api->get_tags();

		if ( is_wp_error( $tags ) ) {
			return $tags;
		}

		foreach ( $tags as $tag ) {
			// If the tag's name matches the $tag_name, return its ID.
			if ( $tag['name'] === $tag_name ) {
				return $tag['id'];
			}
		}

		// No matching tag was found in ConvertKit.
		return false;

	}

	/**
	 * Iterates through the supplied array of possible Tag IDs, checking that
	 * they are not WP_Error instances, empty or false, returning an array
	 * of Tag IDs or false if no Tag IDs are present.
	 *
	 * @since   1.0
	 *
	 * @param   array $possible_tag_ids Possible Tag IDs.
	 *
	 * @return  false|array Array of Tag IDs or false.
	 */
	private function build_tag_ids_array( $possible_tag_ids ) {

		$tag_ids = array();

		// Add tags to the array if they're valid.
		foreach ( $possible_tag_ids as $possible_tag_id ) {
			if ( is_wp_error( $possible_tag_id ) ) {
				continue;
			}

			// Skip if empty or false.
			if ( empty( $possible_tag_id ) || ! $possible_tag_id ) {
				continue;
			}

			// Add to array.
			$tag_ids[] = $possible_tag_id;
		}

		// If Tag IDs array is now empty, set it to boolean false.
		if ( ! count( $tag_ids ) ) {
			return false;
		}

		// Return a zero based index array.
		return array_values( $tag_ids );

	}

	/**
	 * Migrate feeds from the previous version of the add-on.
	 *
	 * @since 1.0
	 *
	 * @param bool $check_page Indicates if the migration should only run on Gravity Forms admin pages.
	 *
	 * @return void
	 */
	public function maybe_migrate_feeds( $check_page = true ) {
		if ( $check_page && ! GFForms::get_page() ) {
			return;
		}

		$settings = $this->get_plugin_settings();
		if ( rgar( $settings, 'imported_feeds' ) ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'gf_addon_feed';
		if ( ! $this->table_exists( $table ) ) {
			return;
		}

		$select_query   = "SELECT * FROM {$table} WHERE addon_slug='ckgf'";
		$selected_feeds = $wpdb->get_results( $select_query, ARRAY_A );

		if ( empty( $selected_feeds ) ) {
			return;
		}

		$migration_map = array();

		foreach ( $selected_feeds as $selected_feed ) {
			$feed_meta                    = json_decode( $selected_feed['meta'], true );
			$feed_meta['field_map_email'] = $feed_meta['field_map_e'];
			$feed_meta['field_map_name']  = $feed_meta['field_map_n'];

			$migration_map[ strval( $selected_feed['id'] ) ] = $this->insert_feed( $selected_feed['form_id'], $selected_feed['is_active'], $feed_meta );
		}

		$settings['imported_feeds'] = 1;
		$this->update_plugin_settings( $settings );

		/**
		 * Allows custom actions to be performed once the feeds have been migrated from the third-party add-on.
		 *
		 * @since 1.0
		 *
		 * @param array $migration_map An array using the third-party feed IDs as the keys to the new feed IDs.
		 */
		do_action( 'gform_convertkit_post_migrate_feeds', $migration_map );
	}

	/**
	 * Maybe populate API Key and Secret from the third-party add-on.
	 *
	 * @since 1.0
	 */
	public function maybe_populate_api_key() {
		$ckgf_settings = get_option( 'gravityformsaddon_ckgf_settings' );
		if ( empty( $ckgf_settings ) ) {
			return;
		}

		$settings         = $this->get_plugin_settings();
		$update_settings  = false;

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( empty( $settings[ self::SETTING_CONVERTKIT_API_KEY ] ) && ! empty( $ckgf_settings['api_key'] ) ) {
			$settings[ self::SETTING_CONVERTKIT_API_KEY ] = $ckgf_settings['api_key'];
			$update_settings                              = true;
		}

		if ( empty( $settings[ self::SETTING_CONVERTKIT_API_SECRET ] ) && ! empty( $ckgf_settings['api_secret'] ) ) {
			$settings[ self::SETTING_CONVERTKIT_API_SECRET ] = $ckgf_settings['api_secret'];
			$update_settings                                 = true;
		}

		if ( $update_settings ) {
			$this->update_plugin_settings( $settings );
		}
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return $this->is_gravityforms_supported( '2.7.8.1' ) ? 'gform-icon--convertkit' : 'dashicons-admin-generic';
	}

	/**
	 * Enqueues the Creator Network Recommendations script when applicable.
	 *
	 * @since 1.0
	 *
	 * @param array $form    The form being displayed.
	 * @param bool  $is_ajax Indicates if Ajax is enabled for the embed method.
	 *
	 * @return void
	 */
	public function enqueue_creator_network_recommendations_script( $form, $is_ajax ) {
		// In their PR ConvertKit indicates the script/modal currently only works when Ajax is enabled.
		if ( ! $is_ajax || ! $this->is_creator_network_recommendations_enabled( $form ) || is_wp_error( $this->is_creator_network_recommendations_script_supported() ) ) {
			return;
		}

		$result = $this->get_creator_network_recommendations_script();

		wp_enqueue_script( 'gform_convertkit_creator_network_recommendations', $result['embed_js'], array(), $this->get_version(), true );
	}

	/**
	 * Determines if Creator Network Recommendations is enabled for the given form.
	 *
	 * @since 1.0
	 *
	 * @param array $form The form being displayed.
	 *
	 * @return bool
	 */
	public function is_creator_network_recommendations_enabled( $form ) {
		if ( empty( $form['id'] ) ) {
			return false;
		}

		return (bool) rgar( $this->get_form_settings( $form ), 'enable_creator_network_recommendations' );
	}

	/**
	 * Clears cached API responses when the settings are updated.
	 *
	 * @since 1.0
	 *
	 * @param array $settings The settings to be saved to the database.
	 *
	 * @return void
	 */
	public function update_plugin_settings( $settings ) {
		GFCache::delete( self::FORMS_CACHE_KEY );
		GFCache::delete( self::SCRIPT_CACHE_KEY );

		parent::update_plugin_settings( $settings );
	}

}
