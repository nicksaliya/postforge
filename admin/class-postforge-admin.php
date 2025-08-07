<?php
/**
 * Admin functionality for PostForge plugin.
 *
 * @package PostForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Postforge_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_postforge_get_post_type_data', array( $this, 'ajax_get_post_type_data' ) );
	}

	/**
	 * Register the admin menu.
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'PostForge', 'postforge' ),
			__( 'PostForge', 'postforge' ),
			'manage_options',
			'postforge',
			array( $this, 'display_forms_list' ),
			'dashicons-feedback'
		);

		add_submenu_page(
			'postforge',
			__( 'All Forms', 'postforge' ),
			__( 'All Forms', 'postforge' ),
			'manage_options',
			'postforge',
			array( $this, 'display_forms_list' )
		);

		add_submenu_page(
			'postforge',
			__( 'Add New Form', 'postforge' ),
			__( 'Add New Form', 'postforge' ),
			'manage_options',
			'postforge-add-new',
			array( $this, 'display_add_edit_form_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'postforge' ) !== false ) {
			wp_enqueue_style(
				'postforge-admin',
				plugin_dir_url( __FILE__ ) . 'css/postforge-admin.css',
				array(),
				time()
			);
			wp_enqueue_script(
				'postforge-admin',
				plugin_dir_url( __FILE__ ) . 'js/postforge-admin.js',
				array( 'jquery' ),
				time(),
				true
			);

			wp_localize_script(
				'postforge-admin',
				'postforge_ajax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'postforge_ajax_nonce' ),
				)
			);
		}
	}

	/**
	 * Display the list of saved forms.
	 */
	public function display_forms_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized.', 'postforge' ) );
		}

		// Handle actions FIRST — before any HTML output.
		if ( isset( $_GET['pf_action'], $_GET['id'] ) ) {
			$form_id      = absint( $_GET['id'] );
			$nonce_action = 'postforge_manage_form_' . $form_id;

			if ( ! wp_verify_nonce( $_GET['_wpnonce'], $nonce_action ) ) {
				wp_die( __( 'Security check failed.', 'postforge' ) );
			}

			if ( $_GET['pf_action'] === 'delete' ) {
				wp_trash_post( $form_id );
				wp_safe_redirect( admin_url( 'admin.php?page=postforge&message=deleted' ) );
				exit;
			}

			if ( $_GET['pf_action'] === 'toggle' ) {
				$current = get_post_meta( $form_id, 'postforge_form_enabled', true );
				update_post_meta( $form_id, 'postforge_form_enabled', $current ? 0 : 1 );
				wp_safe_redirect( admin_url( 'admin.php?page=postforge&message=toggled' ) );
				exit;
			}
		}

		if ( ! empty( $_GET['message'] ) ) {
			$message = '';

			switch ( $_GET['message'] ) {
				case 'saved':
					$message = __( 'Form saved successfully.', 'postforge' );
					break;
				case 'deleted':
					$message = __( 'Form deleted.', 'postforge' );
					break;
				case 'toggled':
					$message = __( 'Form status updated.', 'postforge' );
					break;
			}

			if ( $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		}

		$forms = get_posts( array(
			'post_type'      => 'postforge_form',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'trash' ),
		) );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'PostForge Forms', 'postforge' ); ?></h1>
			<?php if ( $forms ) : ?>
				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'ID', 'postforge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Title & Description', 'postforge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Shortcode', 'postforge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'postforge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Author', 'postforge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Created', 'postforge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'postforge' ); ?></th>
						</tr>
					</thead>
					<tbody id="the-list">
					<?php foreach ( $forms as $form ) :
						$is_enabled    = get_post_meta( $form->ID, 'postforge_form_enabled', true );
						$status_icon   = $is_enabled ? 'yes' : 'no-alt';
						$status_title  = $is_enabled ? __( 'Active', 'postforge' ) : __( 'Inactive', 'postforge' );
						$toggle_action = $is_enabled ? __( 'Deactivate', 'postforge' ) : __( 'Activate', 'postforge' );
						$nonce         = wp_create_nonce( 'postforge_manage_form_' . $form->ID );
						$author_obj    = get_user_by( 'id', $form->post_author );
						?>
						<tr>
							<td><?php echo esc_html( $form->ID ); ?></td>
							<td>
								<strong><?php echo esc_html( $form->post_title ); ?></strong>
								<?php if ( $form->post_excerpt ) : ?>
									<p class="description"><?php echo esc_html( $form->post_excerpt ); ?></p>
								<?php endif; ?>
							</td>
							<td>
								<span class="shortcode-copy" data-shortcode='[postforge_form id="<?php echo esc_attr( $form->ID ); ?>"]'>
									<code>[postforge_form id="<?php echo esc_attr( $form->ID ); ?>"]</code>
								</span>
								<span class="copy-feedback dashicons dashicons-yes-alt" style="display:none;" title="Copied!"></span>
							</td>
							<td>
								<span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>" title="<?php echo esc_attr( $status_title ); ?>"></span>
							</td>
							<td>
								<?php echo esc_html( $author_obj ? $author_obj->display_name : __( 'Unknown', 'postforge' ) ); ?>
							</td>
							<td>
								<?php echo esc_html( get_the_date( 'Y-m-d', $form ) ); ?>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=postforge-add-new&edit=' . $form->ID ) ); ?>" title="Edit">
									<span class="dashicons dashicons-edit"></span>
								</a>
								<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=postforge&pf_action=toggle&id=' . $form->ID ), 'postforge_manage_form_' . $form->ID ); ?>" title="<?php echo esc_attr( $toggle_action ); ?>">
									<span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>"></span>
								</a>
								<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=postforge&pf_action=delete&id=' . $form->ID ), 'postforge_manage_form_' . $form->ID ); ?>"
								onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this form?', 'postforge' ); ?>')" title="Delete">
									<span class="dashicons dashicons-trash" style="color:#d63638;"></span>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>


				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No forms found.', 'postforge' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Displays Add or Edit form page.
	 */
	public function display_add_edit_form_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized.', 'postforge' ) );
		}

		$form_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$form    = null;

		if ( $form_id ) {
			$form = get_post( $form_id );
			if ( ! $form || $form->post_type !== 'postforge_form' ) {
				wp_die( __( 'Form not found.', 'postforge' ) );
			}
		}

		// Handle form submission:
		if ( isset( $_POST['postforge_nonce'] ) && wp_verify_nonce( $_POST['postforge_nonce'], 'save_postforge_form' ) ) {
			$title = sanitize_text_field( $_POST['postforge_form_title'] );

			$post_data = array(
				'post_title'  => $title,
				'post_type'   => 'postforge_form',
				'post_status' => 'publish',
			);

			if ( $form_id ) {
				$post_data['ID'] = $form_id;
				$form_id         = wp_update_post( $post_data );
			} else {
				$form_id = wp_insert_post( $post_data );
			}

			if ( $form_id ) {
				update_post_meta( $form_id, 'postforge_post_type', sanitize_text_field( $_POST['postforge_post_type'] ) );
				update_post_meta( $form_id, 'postforge_login_required', isset( $_POST['postforge_login_required'] ) ? 1 : 0 );
				update_post_meta( $form_id, 'postforge_include_featured_image', isset( $_POST['postforge_include_featured_image'] ) ? 1 : 0 );
				update_post_meta( $form_id, 'postforge_form_enabled', isset( $_POST['postforge_form_enabled'] ) ? 1 : 0 );

				$selected_taxonomies = isset( $_POST['postforge_taxonomies'] )
					? array_map( 'sanitize_text_field', $_POST['postforge_taxonomies'] )
					: array();
				update_post_meta( $form_id, 'postforge_taxonomies', $selected_taxonomies );

				$custom_fields = array();
				if ( isset( $_POST['postforge_custom_fields'] ) ) {
					foreach ( $_POST['postforge_custom_fields'] as $meta_key => $field_data ) {
						if ( isset( $field_data['enabled'] ) ) {
							$custom_fields[] = array(
								'meta_key' => sanitize_text_field( $meta_key ),
								'label'    => sanitize_text_field( $field_data['label'] ?? '' ),
								'required' => isset( $field_data['required'] ) ? 1 : 0,
							);
						}
					}
				}
				update_post_meta( $form_id, 'postforge_custom_fields', $custom_fields );
				// Always redirect before output!
				wp_safe_redirect( admin_url( 'admin.php?page=postforge&message=saved' ) );
				exit;
			}
		}

		$values = array(
			'title'                   => $form ? $form->post_title : '',
			'post_type'               => $form ? get_post_meta( $form->ID, 'postforge_post_type', true ) : '',
			'login_required'          => $form ? get_post_meta( $form->ID, 'postforge_login_required', true ) : '',
			'include_featured_image'  => $form ? get_post_meta( $form->ID, 'postforge_include_featured_image', true ) : '',
			'enabled'                 => $form ? get_post_meta( $form->ID, 'postforge_form_enabled', true ) : '1',
		);

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<div class="wrap">
			<h1><?php echo $form ? esc_html__( 'Edit Form', 'postforge' ) : esc_html__( 'Add New Form', 'postforge' ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'save_postforge_form', 'postforge_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="postforge_form_title"><?php esc_html_e( 'Form Title', 'postforge' ); ?></label></th>
						<td>
							<input type="text" name="postforge_form_title" id="postforge_form_title" class="regular-text" required value="<?php echo esc_attr( $values['title'] ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="postforge_post_type"><?php esc_html_e( 'Post Type', 'postforge' ); ?></label></th>
						<td>
							<select name="postforge_post_type" id="postforge_post_type">
								<option value=""><?php esc_html_e( '-- Select Post Type --', 'postforge' ); ?></option>
								<?php
								foreach ( $post_types as $slug => $obj ) {
									printf(
										'<option value="%1$s" %2$s>%3$s</option>',
										esc_attr( $slug ),
										selected( $values['post_type'], $slug, false ),
										esc_html( $obj->labels->singular_name )
									);
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Login Required?', 'postforge' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="postforge_login_required" value="1" <?php checked( $values['login_required'], 1 ); ?>>
								<?php esc_html_e( 'Only allow logged-in users to submit this form.', 'postforge' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Include Featured Image?', 'postforge' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="postforge_include_featured_image" value="1" <?php checked( $values['include_featured_image'], 1 ); ?>>
								<?php esc_html_e( 'Allow users to upload a featured image.', 'postforge' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Enable Form?', 'postforge' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="postforge_form_enabled" value="1" <?php checked( $values['enabled'], 1 ); ?>>
								<?php esc_html_e( 'Form is active and usable on the frontend.', 'postforge' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<div id="postforge-dynamic-fields"></div>

				<?php submit_button( $form ? __( 'Update Form', 'postforge' ) : __( 'Save Form', 'postforge' ) ); ?>
			</form>
		</div>

		<script type="text/javascript">
			window.postforge_form_data = <?php echo wp_json_encode( array(
				'taxonomies'    => $form ? get_post_meta( $form->ID, 'postforge_taxonomies', true ) : array(),
				'custom_fields' => $form ? get_post_meta( $form->ID, 'postforge_custom_fields', true ) : array(),
			) ); ?>;
		</script>
		<?php
	}

	/**
	 * AJAX handler to fetch taxonomies and custom fields.
	 */
	public function ajax_get_post_type_data() {
		check_ajax_referer( 'postforge_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'postforge' ) );
		}

		$post_type = sanitize_text_field( $_POST['post_type'] );

		$taxonomies = array();
		$tax_objects = get_object_taxonomies( $post_type, 'objects' );

		foreach ( $tax_objects as $tax ) {
			$taxonomies[] = array(
				'slug'  => $tax->name,
				'label' => $tax->labels->singular_name,
			);
		}

		$meta_keys = array();

		if ( function_exists( 'acf_get_field_groups' ) ) {
			$groups = acf_get_field_groups( array( 'post_type' => $post_type ) );

			foreach ( $groups as $group ) {
				$fields = acf_get_fields( $group['key'] );
				if ( $fields ) {
					foreach ( $fields as $field ) {
						if ( ! empty( $field['name'] ) ) {
							$meta_keys[] = array(
								'meta_key' => $field['name'],
								'label'    => $field['label'],
							);
						}
					}
				}
			}
		}

		if ( empty( $meta_keys ) ) {
			$posts = get_posts( array(
				'post_type'      => $post_type,
				'posts_per_page' => 10,
				'post_status'    => 'any',
			) );

			$found_keys = array();

			foreach ( $posts as $post ) {
				$meta = get_post_meta( $post->ID );
				foreach ( $meta as $key => $values ) {
					if ( ! in_array( $key, $found_keys, true ) && substr( $key, 0, 1 ) !== '_' ) {
						$found_keys[] = $key;
					}
				}
			}

			foreach ( $found_keys as $key ) {
				$meta_keys[] = array(
					'meta_key' => $key,
					'label'    => $key,
				);
			}
		}

		wp_send_json_success( array(
			'taxonomies' => $taxonomies,
			'meta_keys'  => $meta_keys,
		) );
	}
}