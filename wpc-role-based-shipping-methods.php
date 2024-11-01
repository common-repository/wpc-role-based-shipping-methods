<?php
/*
Plugin Name: WPC Role-Based Shipping Methods for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Role-Based Shipping Methods allow the limitation of available shipping methods for each user role individually.
Version: 1.0.1
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-role-based-shipping-methods
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.6
WC requires at least: 3.0
WC tested up to: 9.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCRS_VERSION' ) && define( 'WPCRS_VERSION', '1.0.1' );
! defined( 'WPCRS_LITE' ) && define( 'WPCRS_LITE', __FILE__ );
! defined( 'WPCRS_FILE' ) && define( 'WPCRS_FILE', __FILE__ );
! defined( 'WPCRS_URI' ) && define( 'WPCRS_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCRS_REVIEWS' ) && define( 'WPCRS_REVIEWS', 'https://wordpress.org/support/plugin/wpc-role-based-shipping-methods/reviews/?filter=5' );
! defined( 'WPCRS_CHANGELOG' ) && define( 'WPCRS_CHANGELOG', 'https://wordpress.org/plugins/wpc-role-based-shipping-methods/#developers' );
! defined( 'WPCRS_DISCUSSION' ) && define( 'WPCRS_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-role-based-shipping-methods' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WPCRS_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'wpcrs_init' ) ) {
	add_action( 'plugins_loaded', 'wpcrs_init', 11 );

	function wpcrs_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-role-based-shipping-methods', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'wpcrs_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWpcrs' ) ) {
			class WPCleverWpcrs {
				protected static $settings = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'wpcrs_settings', [] );

					// settings
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// links
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// available shipping methods
					add_filter( 'woocommerce_package_rates', [ $this, 'available_shipping_methods' ], 99 );
				}

				function register_settings() {
					register_setting( 'wpcrs_settings', 'wpcrs_settings' );
				}

				public static function get_settings() {
					return apply_filters( 'wpcrs_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( 'wpcrs_' . $name, $default );
					}

					return apply_filters( 'wpcrs_get_setting', $setting, $name, $default );
				}

				function admin_enqueue_scripts( $hook ) {
					if ( str_contains( $hook, 'wpcrs' ) ) {
						wp_enqueue_style( 'wpcrs-backend', WPCRS_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCRS_VERSION );
						wp_enqueue_script( 'wpcrs-backend', WPCRS_URI . 'assets/js/backend.js', [
							'jquery',
							'selectWoo',
						], WPCRS_VERSION, true );
					}
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', 'WPC Role-Based Shipping Methods', 'Role-Based Shipping Methods', 'manage_options', 'wpclever-wpcrs', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Role-Based Shipping Methods', 'wpc-role-based-shipping-methods' ) . ' ' . esc_html( WPCRS_VERSION ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-role-based-shipping-methods' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WPCRS_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-role-based-shipping-methods' ); ?></a> |
                                <a href="<?php echo esc_url( WPCRS_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-role-based-shipping-methods' ); ?></a> |
                                <a href="<?php echo esc_url( WPCRS_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-role-based-shipping-methods' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-role-based-shipping-methods' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcrs&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-role-based-shipping-methods' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-role-based-shipping-methods' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) {
								global $wp_roles;
								?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Shipping Methods', 'wpc-role-based-shipping-methods' ); ?>
                                            </th>
                                        </tr>
										<?php
										$shipping_methods = WC()->shipping->get_shipping_methods();

										if ( is_array( $shipping_methods ) && ! empty( $shipping_methods ) ) {
											foreach ( $shipping_methods as $key => $method ) {
												$allowed_roles = (array) self::get_setting( $key, [ 'wpcrs_all' ] );
												?>
                                                <tr>
                                                    <th scope="row">
														<?php
														if ( wc_string_to_bool( $method->enabled ) ) {
															echo esc_html( $method->method_title );
														} else {
															echo '<s>' . esc_html( $method->method_title ) . '</s>';
														}
														?>
                                                    </th>
                                                    <td>
                                                        <p class="description"><?php esc_html_e( 'Choose the role(s) that are allowed to use this shipping method.', 'wpc-role-based-shipping-methods' ); ?></p>
                                                        <label>
                                                            <select name="<?php echo esc_attr( 'wpcrs_settings[' . $key . '][]' ); ?>" class="wpcrs_roles_selector" multiple>
																<?php
																echo '<option value="wpcrs_all" ' . ( in_array( 'wpcrs_all', $allowed_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'All', 'wpc-role-based-shipping-methods' ) . '</option>';
																echo '<option value="wpcrs_user" ' . ( in_array( 'wpcrs_user', $allowed_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-role-based-shipping-methods' ) . '</option>';
																echo '<option value="wpcrs_guest" ' . ( in_array( 'wpcrs_guest', $allowed_roles ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-role-based-shipping-methods' ) . '</option>';

																foreach ( $wp_roles->roles as $role => $details ) {
																	echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $allowed_roles ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
																}
																?>
                                                            </select> </label>
                                                    </td>
                                                </tr>
												<?php
											}
										}
										?>
                                        <tr class="submit">
                                            <th colspan="2">
												<?php settings_fields( 'wpcrs_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function available_shipping_methods( $shipping_rates ) {
					if ( is_array( $shipping_rates ) ) {
						foreach ( $shipping_rates as $key => $shipping_rate ) {
							$method_id     = $shipping_rate->get_method_id();
							$allowed_roles = (array) self::get_setting( $method_id, [ 'wpcrs_all' ] );

							if ( ! self::check_roles( $allowed_roles ) ) {
								unset( $shipping_rates[ $key ] );
							}
						}
					}

					return $shipping_rates;
				}

				function check_roles( $roles ) {
					if ( is_string( $roles ) ) {
						$roles = explode( ',', $roles );
					}

					if ( empty( $roles ) || ! is_array( $roles ) || in_array( 'wpcrs_all', $roles ) ) {
						return true;
					}

					if ( is_user_logged_in() ) {
						if ( in_array( 'wpcrs_user', $roles ) ) {
							return true;
						}

						$current_user = wp_get_current_user();

						foreach ( $current_user->roles as $role ) {
							if ( in_array( $role, $roles ) ) {
								return true;
							}
						}
					} else {
						if ( in_array( 'wpcrs_guest', $roles ) ) {
							return true;
						}
					}

					return false;
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcrs&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-role-based-shipping-methods' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WPCRS_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-role-based-shipping-methods' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}
			}

			return WPCleverWpcrs::instance();
		}

		return null;
	}
}

if ( ! function_exists( 'wpcrs_notice_wc' ) ) {
	function wpcrs_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Role-Based Shipping Methods</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
