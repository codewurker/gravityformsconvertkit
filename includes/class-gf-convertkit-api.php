<?php

defined( 'ABSPATH' ) || die();

class GF_ConvertKit_API {
	/**
	 * ConvertKit API URL.
	 *
	 * @since 1.0
	 * @var   string $api_url ConvertKit API URL.
	 */
	protected $api_url = 'https://api.convertkit.com/';

	/**
	 * ConvertKit API Key.
	 *
	 * @since  1.0
	 * @var    string
	 */
	protected $api_key = '';

	/**
	 * ConvertKit API Secret.
	 *
	 * @since  1.0
	 * @var    string
	 */
	protected $api_secret = '';

	/**
	 * Add-on instance.
	 *
	 * @since 1.0
	 * @var   \GF_ConvertKit
	 */
	private $addon;

	/**
	 * Initialize API Library.
	 *
	 * @since 1.0
	 *
	 * @param GF_ConvertKit $addon      GF_ConvertKit Instance.
	 * @param string        $api_key    ConvertKit API key.
	 * @param string        $api_secret ConvertKit API Secret.
	 */
	public function __construct( $addon, $api_key, $api_secret ) {
		$this->addon      = $addon;
		$this->api_key    = $api_key;
		$this->api_secret = $api_secret;
	}

	/**
	 * Make API request.
	 *
	 * @since 1.0
	 *
	 * @param string $path       Request path.
	 * @param array  $body       Body arguments.
	 * @param string $method     Request method. Defaults to GET.
	 * @param string $return_key Array key from response to return. Defaults to null (return full response).
	 *
	 * @return array|WP_Error
	 */
	private function make_request( $path = '', $body = array(), $method = 'GET', $return_key = null ) {
		// Log request.
		gf_convertkit()->log_debug( __METHOD__ . '(): Making request to: ' . $path );

		if ( $method === 'GET' ) {
			if ( $this->api_secret ) {
				$path .= '?api_secret=' . $this->api_secret;
			} else {
				$path .= '?api_key=' . $this->api_key;
			}
		}

		$request_url = $this->api_url . $path;

		$args = array(
			'method'    => $method,
			/**
			 * Filters if SSL verification should occur.
			 *
			 * @param bool false If the SSL certificate should be verified. Defaults to false.
			 *
			 * @return bool
			 */
			'sslverify' => apply_filters( 'https_local_ssl_verify', false, $request_url ),
			/**
			 * Sets the HTTP timeout, in seconds, for the request.
			 *
			 * @param int 30 The timeout limit, in seconds. Defaults to 30.
			 *
			 * @return int
			 */
			'timeout'   => apply_filters( 'http_request_timeout', 30, $request_url ),
		);
		if ( 'GET' === $method || 'POST' === $method || 'PUT' === $method ) {
			$args['body']    = empty( $body ) ? '' : $body;
			$args['headers'] = array(
				'Accept'       => 'application/json;ver=1.0',
				'Content-Type' => 'application/json; charset=UTF-8',
			);
		}

		// Execute request.
		$response = wp_remote_request(
			$request_url,
			$args
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body           = wp_remote_retrieve_body( $response );
		$retrieved_response_code = $response['response']['code'];

		if ( 200 !== $retrieved_response_code ) {
			$error_message = rgars( $response_body, 'error/message', "Expected response code: 200. Returned response code: {$retrieved_response_code}." );
			$error_code    = rgars( $response_body, 'error/errors/reason', 'convertkit_api_error' );

			gf_convertkit()->log_error( __METHOD__ . '(): Unable to validate with the ConvertKit API: ' . $error_message );

			return new WP_Error( $error_code, $error_message, $retrieved_response_code );
		}

		return $response_body;
	}

	/**
	 * Get the list of forms from ConverKit.
	 *
	 * @since 1.0
	 *
	 * @return array|mixed|WP_Error
	 */
	public function get_forms() {
		$path     = 'v3/forms';
		$response = $this->make_request( $path, array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			$response = json_decode( $response, true );
		}

		return $response['forms'];
	}

	/**
	 * Get the list of tags from ConverKit.
	 *
	 * @since 1.0
	 *
	 * @return array|WP_Error
	 */
	public function get_tags() {

		$path = 'v3/tags';
		$tags = array();

		$response = $this->make_request( $path, array() );

		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			$response = json_decode( $response, true );
		}

		// If the response isn't an array as we expect, log that no tags exist and return a blank array.
		if ( ! is_array( $response['tags'] ) ) {
			return new WP_Error( 'convertkit_api_error', $this->get_error_message( 'response_type_unexpected' ) );
		}

		// If no tags exist, log that no tags exist and return a blank array.
		if ( ! count( $response['tags'] ) ) {
			return $tags;
		}

		foreach ( $response['tags'] as $tag ) {
			$tags[ $tag['id'] ] = $tag;
		}

		return $tags;

	}

	/**
	 * Get the list of custom fields from ConverKit.
	 *
	 * @since 1.0
	 *
	 * @return array|WP_Error
	 */
	public function get_custom_fields() {

		$path          = 'v3/custom_fields';
		$custom_fields = array();

		$response = $this->make_request( $path, array() );

		// If an error occured, return WP_Error.
		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			$response = json_decode( $response, true );
		}

		// If the response isn't an array as we expect, log that no tags exist and return a blank array.
		if ( ! is_array( $response['custom_fields'] ) ) {
			return new WP_Error( 'convertkit_api_error','response_type_unexpected' );
		}

		// If no custom fields exist, log that no custom fields exist and return a blank array.
		if ( ! count( $response['custom_fields'] ) ) {
			return $custom_fields;
		}

		foreach ( $response['custom_fields'] as $custom_field ) {
			$custom_fields[ $custom_field['id'] ] = $custom_field;
		}

		return $custom_fields;
	}

	/**
	 * Makes the REST API request to get the Creator Network Recommendations config for the connected account.
	 *
	 * This endpoint was implemented by ConvertKit specifically for their own WP plugins. It is not a part of their v3 API and is not documented. The response contains two properties:
	 * - enabled: Whether Creator Network Recommendations is enabled for the account.
	 * - embed_js: The URL of the .js file to be enqueued on the page containing the form.
	 *
	 * @since 1.0
	 *
	 * @return array|WP_Error
	 */
	public function get_recommendations_script() {
		$response = $this->make_request( 'wordpress/recommendations_script' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return json_decode( $response, true );
	}

	/**
	 * Subscribe a user to a ConvertKit form.
	 *
	 * @since 1.0
	 *
	 * @param string $form_id    The ConvertKit form ID.
	 * @param string $email      The email address to subscribe.
	 * @param string $first_name The first name of the subscriber.
	 * @param array  $fields     The custom fields to set for the subscriber.
	 * @param array  $tag_ids    The tags to apply to the subscriber.
	 *
	 * @return string JSON encoded response from ConvertKit.
	 */
	public function subscribe( $form_id, $email, $first_name = '', $fields = false, $tag_ids = false ) {

		// Build request parameters.
		$params = array(
			'email'      => $email,
			'first_name' => $first_name,
		);

		if ( $this->api_secret ) {
			$params['api_secret'] = $this->api_secret;
		} else {
			$params['api_key'] = $this->api_key;
		}

		if ( $fields ) {
			$params['fields'] = $fields;
		}
		if ( $tag_ids ) {
			$params['tags'] = $tag_ids;
		}

		// Send request.
		$response = $this->make_request( 'v3/forms/' . $form_id . '/subscribe', json_encode( $params ), 'POST' );

		// If an error occured, log and return it now.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;

	}
}

