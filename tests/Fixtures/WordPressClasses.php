<?php
/**
 * Minimal stand-ins for the WordPress classes the unit tests touch.
 *
 * The unit suite runs without WordPress, so only the members the plugin
 * reads are provided. Loaded from the bootstrap; each class is declared only
 * when it does not already exist.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Stand-in for WP_Post.
	 */
	final class WP_Post {

		public int $ID = 0;

		public string $post_type = 'post';

		public string $post_status = 'publish';

		public string $post_author = '0';

		public string $post_title = '';

		public string $post_content = '';

		public int $post_parent = 0;

		/**
		 * @param array<string, mixed> $fields Property values.
		 */
		public function __construct( array $fields = [] ) {
			foreach ( $fields as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Stand-in for WP_Error.
	 */
	class WP_Error {

		public string $code;

		public string $message;

		/** @var mixed */
		public $data;

		/**
		 * @param string $code    Error code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( string $code = '', string $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	/**
	 * Stand-in for WooCommerce's WC_Order_Item_Product.
	 */
	class WC_Order_Item_Product {

		public function __construct(
			private int $product_id = 0,
			private int $id = 0,
			private int $quantity = 1,
			private int $variation_id = 0
		) {}

		public function get_product_id(): int {
			return $this->product_id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_quantity(): int {
			return $this->quantity;
		}

		public function get_variation_id(): int {
			return $this->variation_id;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Stand-in for WP_REST_Request (parameters and a JSON body).
	 */
	class WP_REST_Request {

		/** @var array<string, mixed> */
		private array $params;

		private string $method;

		private string $route;

		private string $body;

		/**
		 * @param array<string, mixed> $params Parameters.
		 * @param string               $method HTTP method.
		 * @param string               $route  Route.
		 * @param string|null          $body   Raw body; null = the params as JSON.
		 */
		public function __construct( array $params = [], string $method = 'GET', string $route = '', ?string $body = null ) {
			$this->params = $params;
			$this->method = $method;
			$this->route  = $route;
			$this->body   = $body ?? (string) json_encode( $params );
		}

		/**
		 * @return mixed
		 */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_method(): string {
			return $this->method;
		}

		public function get_route(): string {
			return $this->route;
		}

		public function get_body(): string {
			return $this->body;
		}

		/**
		 * @return array<string, mixed>|null
		 */
		public function get_json_params() {
			$decoded = json_decode( $this->body, true );

			return is_array( $decoded ) ? $decoded : null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * Stand-in for WP_REST_Server (method constants only).
	 */
	class WP_REST_Server {
		const READABLE   = 'GET';
		const CREATABLE  = 'POST';
		const EDITABLE   = 'POST, PUT, PATCH';
		const DELETABLE  = 'DELETE';
		const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Stand-in for WP_REST_Response.
	 */
	class WP_REST_Response {

		/** @var mixed */
		public $data;

		public int $status;

		/**
		 * @param mixed $data   Data.
		 * @param int   $status Status.
		 */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}
	}
}
