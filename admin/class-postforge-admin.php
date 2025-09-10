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
		add_action( 'wp_ajax_postforge_get_available_user_roles', array( $this, 'ajax_get_available_user_roles' ) );
		add_filter( 'postforge_allowed_post_types', array( $this, 'postforge_allowed_post_types' ) );
		add_action( 'admin_post_save_postforge_form',  array( $this,'postforge_handle_form_save' ) );
		add_action( 'admin_post_preview_postforge_form', array( $this, 'postforge_handle_form_preview' ) );
	}

	/**
	 * Exclude Page and Attachement Post Type While Set up Form in Admin
	 */
	public function postforge_allowed_post_types( $post_types ) {
		unset( $post_types['page'] );
		unset( $post_types['attachment'] );
		return $post_types;
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

			wp_enqueue_script( 'jquery-ui-accordion' );
    		wp_enqueue_script( 'jquery-ui-sortable' );
    		wp_enqueue_style( 'wp-jquery-ui-dialog' );


			wp_enqueue_style(
				'postforge-admin',
				plugin_dir_url( __FILE__ ) . 'css/postforge-admin.css',
				array(),
				time()
			);
			wp_enqueue_script(
				'postforge-admin',
				plugin_dir_url( __FILE__ ) . 'js/postforge-admin.js',
				array( 'jquery', 'jquery-ui-accordion', 'jquery-ui-sortable' ),
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

		$values = array(
			'title'                   => $form ? $form->post_title : '',
			'description'             => $form ? $form->post_content : '',
			'post_type'               => $form ? get_post_meta( $form->ID, 'postforge_post_type', true ) : '',
			'login_required'          => $form ? get_post_meta( $form->ID, 'postforge_login_required', true ) : '',
			'include_featured_image'  => $form ? get_post_meta( $form->ID, 'postforge_include_featured_image', true ) : '',
			'enabled'                 => $form ? get_post_meta( $form->ID, 'postforge_form_enabled', true ) : '1',
			'allowed_roles'           => $form ? get_post_meta( $form->ID, 'postforge_allowed_roles', true ) : array(),
		);

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$post_types = apply_filters( 'postforge_allowed_post_types', $post_types );
		?>
		<div class="wrap">
			<h1><?php echo $form ? esc_html__( 'Edit Form', 'postforge' ) : esc_html__( 'Add New Form', 'postforge' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'save_postforge_form', 'postforge_nonce' ); ?>
    			<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<table class="form-table">
					<tr>
						<th><label for="postforge_form_title"><?php esc_html_e( 'Form Title', 'postforge' ); ?></label></th>
						<td>
							<input type="text" name="postforge_form_title" id="postforge_form_title" class="regular-text" required value="<?php echo esc_attr( $values['title'] ); ?>">
						</td>
					</tr>

					<tr>
						<th><label for="postforge_form_description"><?php esc_html_e( 'Form Description', 'postforge' ); ?></label></th>
						<td>
							<textarea name="postforge_form_description" id="postforge_form_description" class="regular-text" required><?php echo esc_textarea( $values['description'] ); ?></textarea>
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
								<input type="checkbox" name="postforge_login_required" id="postforge_login_required_permission" value="1" <?php checked( $values['login_required'], 1 ); ?>>
								<?php esc_html_e( 'Only allow logged-in users to submit this form.', 'postforge' ); ?>
							</label>
							<!-- Roles wrapper (will be populated by JS) -->
							<?php
							$show_roles_wrapper = ( isset( $values['login_required'] ) && 1 === (int) $values['login_required'] );
							?>
							<div id="postforge_roles_wrapper" style="margin-top:8px; <?php echo $show_roles_wrapper ? 'display:block;' : 'display:none;'; ?>">
								<div style="font-weight: 600; margin-bottom: 6px;">
									<?php esc_html_e( 'Select allowed roles:', 'postforge' ); ?>
								</div>
								<?php
								if ( $show_roles_wrapper ) {
									$all_roles = postforge_get_available_user_roles();

									if ( ! empty( $all_roles ) && is_array( $all_roles ) ) {
										foreach ( $all_roles as $role ) {
											$key   = isset( $role['key'] ) ? sanitize_key( $role['key'] ) : '';
											$label = isset( $role['label'] ) ? sanitize_text_field( $role['label'] ) : '';

											if ( empty( $key ) || empty( $label ) ) {
												continue;
											}

											// Check if role is already saved.
											$checked = '';
											if ( ! empty( $values['allowed_roles'] ) && is_array( $values['allowed_roles'] ) ) {
												$checked = checked( in_array( $key, $values['allowed_roles'], true ), true, false );
											}
											?>
											<label style="margin-right: 12px;">
												<input type="checkbox"
													name="postforge_allowed_roles[]"
													value="<?php echo esc_attr( $key ); ?>"
													<?php echo $checked; ?>
												/>
												<?php echo esc_html( $label ); ?>
											</label>
											<?php
										}
									}
								}
								?>
							</div>
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
				<div class="postforge-footer-section">
					<?php submit_button( $form ? __( 'Update Form', 'postforge' ) : __( 'Save Form', 'postforge' ), 'primary', 'submit-save', true, array( 'formaction' => admin_url( 'admin-post.php?action=save_postforge_form' ) ) ); ?>
					<?php if ( $form ) { 
						submit_button( __( 'Preview', 'postforge' ), 'secondary', 'submit-preview', true, 
						array( 'formaction' => admin_url( 'admin-post.php?action=preview_postforge_form' ), 'formtarget' => "_blank" ));
						} ?>
				</div>
				
			</form>
		</div>
		<?php $postforge_taxonomies_data = get_post_meta( $form->ID, 'postforge_taxonomies', true ); 
			  $postforge_taxonomies = is_array( $postforge_taxonomies_data ) ? array_keys( $postforge_taxonomies_data ) : [];
		?>
		<script type="text/javascript">
			window.postforge_form_data = <?php echo wp_json_encode( array(
				'taxonomies'    	=> $form ? $postforge_taxonomies : array(),
				'taxonomies_data'   => $form ? get_post_meta( $form->ID, 'postforge_taxonomies', true ) : array(),
				'custom_fields' 	=> $form ? get_post_meta( $form->ID, 'postforge_custom_fields', true ) : array(),
			) ); ?>;
		</script>
		<?php
	}

	/**
	 * Handle form submission:
	 * Hook : admin_post_save_postforge_form
	 */
	public function postforge_handle_form_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized.', 'postforge' ) );
		}
		check_admin_referer( 'save_postforge_form', 'postforge_nonce' );

		$form_id   = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$form    = null;

		if ( $form_id ) {
			$form = get_post( $form_id );
			if ( ! $form || $form->post_type !== 'postforge_form' ) {
				wp_die( __( 'Form not found.', 'postforge' ) );
			}
		}

		
		// echo "<pre>" . print_r($_POST['postforge_taxonomies'], true) . "</pre>";
		// 		die;
		 
		// Handle form submission:
		if ( isset( $_POST['postforge_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['postforge_nonce'] ) ), 'save_postforge_form' )) {
			$title = sanitize_text_field( $_POST['postforge_form_title'] );
			$description = isset( $_POST['postforge_form_description'] )  ? sanitize_textarea_field( $_POST['postforge_form_description'] ) : '';
			$post_data = array(
				'post_title'  	=>  $title,
				'post_content'  =>  $description,
				'post_type'   	=> 'postforge_form',
				'post_status' 	=> 'publish',
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
				
				/**
				 * Save allowed roles (array of strings)
				 */
				if ( isset( $_POST['postforge_allowed_roles'] ) && is_array( $_POST['postforge_allowed_roles'] ) ) {
					// Sanitize roles
					$allowed_roles = array_map( 'sanitize_text_field', wp_unslash( $_POST['postforge_allowed_roles'] ) );
					$allowed_roles = array_filter( $allowed_roles ); // remove empty values
					update_post_meta( $form_id, 'postforge_allowed_roles', $allowed_roles );
				} else {
					// No roles selected → delete meta
					delete_post_meta( $form_id, 'postforge_allowed_roles' );
				}
				$selected_taxonomies = array();
				$checked  = isset( $_POST['postforge_taxonomies'] )
					? array_map( 'sanitize_text_field', $_POST['postforge_taxonomies'] )
					: array();

				$tax_data = isset( $_POST['postforge_taxonomies_data'] ) && is_array( $_POST['postforge_taxonomies_data'] )
					? $_POST['postforge_taxonomies_data']
					: array();

				foreach ( $checked as $taxonomy ) {
					if ( isset( $tax_data[ $taxonomy ]['type'] ) ) {
						$selected_taxonomies[ $taxonomy ] = array(
							'type' => sanitize_text_field( $tax_data[ $taxonomy ]['type'] ),
						);
					}
				}

				update_post_meta( $form_id, 'postforge_taxonomies', $selected_taxonomies );
				
				
				$custom_fields = array();
				if ( isset( $_POST['postforge_custom_fields'] ) ) {
					foreach ( $_POST['postforge_custom_fields'] as $meta_key => $field_data ) {
						if ( isset( $field_data['enabled'] ) ) {
							$custom_fields[] = array(
								'meta_key' => sanitize_text_field( $meta_key ),
								'label'    => sanitize_text_field( $field_data['label'] ?? '' ),
								'required' => isset( $field_data['required'] ) ? 1 : 0,
								'enabled' => isset( $field_data['enabled'] ) ? 1 : 0,
								'type' => isset( $field_data['type'] ) ? $field_data['type'] : 0,
							);
						}
					}
				}
				update_post_meta( $form_id, 'postforge_custom_fields', $custom_fields );
				// Always redirect before output!
				 
				wp_redirect( admin_url( 'admin.php?page=postforge&message=saved' ) );
				exit;
			}
		}
	}

	/**
	 * Handle form preview:
	 * Hook: admin_post_preview_postforge_form
	 */
	public function postforge_handle_form_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized.', 'postforge' ) );
		}

		check_admin_referer( 'save_postforge_form', 'postforge_nonce' );

		$selected_taxonomies = array();
		$checked  = isset( $_POST['postforge_taxonomies'] )
			? array_map( 'sanitize_text_field', $_POST['postforge_taxonomies'] )
			: array();

		$tax_data = isset( $_POST['postforge_taxonomies_data'] ) && is_array( $_POST['postforge_taxonomies_data'] )
			? $_POST['postforge_taxonomies_data']
			: array();

		foreach ( $checked as $taxonomy ) {
			if ( isset( $tax_data[ $taxonomy ]['type'] ) ) {
				$selected_taxonomies[ $taxonomy ] = array(
					'type' => sanitize_text_field( $tax_data[ $taxonomy ]['type'] ),
				);
			}
		}

		// Collect values directly from $_POST (not DB)
		$values = array(
			'post_type'          => sanitize_text_field( $_POST['postforge_post_type'] ?? '' ),
			'taxonomies'         => isset( $_POST['postforge_taxonomies'] ) ? array_map( 'sanitize_text_field', (array) $_POST['postforge_taxonomies'] ) : array(),
			'selected_taxonomies_data' => $selected_taxonomies,
			'custom_fields'      => $_POST['postforge_custom_fields'] ?? array(),
			'login_required'     => isset( $_POST['postforge_login_required'] ) ? 1 : 0,
			'success_message'    => sanitize_text_field( $_POST['postforge_success_message'] ?? '' ),
			'post_status'        => sanitize_text_field( $_POST['postforge_post_status'] ?? '' ),
			'notification_email' => sanitize_email( $_POST['postforge_notification_email'] ?? '' ),
			'allowed_roles'      => isset( $_POST['postforge_allowed_roles'] ) ? array_map( 'sanitize_text_field', (array) $_POST['postforge_allowed_roles'] ) : array(),
		);

		// Render preview page
		 
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'Form Preview', 'postforge' ) . '</h2>';

		// Pass data into the renderer in preview mode
		echo $this->postforge_render_form_preview( $values );

		echo '</div>';
		 
		exit;
	}

	/**
	 * Generate Preview code
	 */
	public function postforge_render_form_preview( $values ) {
		ob_start();
		 
		?>
		<form method="post" class="postforge-form" style="width:100%;">
			<p>
				<label for="postforge_post_title"><?php esc_html_e( 'Post Title', 'postforge' ); ?> *</label><br>
				<input type="text" id="postforge_post_title" name="postforge_post_title" required>
			</p>

			<p>
				<label for="postforge_post_content"><?php esc_html_e( 'Post Content', 'postforge' ); ?> *</label><br>
				<textarea id="postforge_post_content" name="postforge_post_content" required></textarea>
			</p>

			<?php if ( ! empty( $values['selected_taxonomies_data'] ) ) : ?>
					<?php foreach ( $values['selected_taxonomies_data'] as $taxonomy => $settings ) :
						$terms = get_terms(
							array(
								'taxonomy'   => $taxonomy,
								'hide_empty' => false,
							)
						);

						if ( empty( $terms ) ) {
							continue;
						}

						$field_type = isset( $settings['type'] ) ? $settings['type'] : 'select';
						$taxonomy_obj = get_taxonomy( $taxonomy );
						?>
						<p>
							<label for="taxonomy_<?php echo esc_attr( $taxonomy ); ?>">
								<?php echo esc_html( $taxonomy_obj->labels->singular_name ); ?>
							</label><br>

							<?php if ( $field_type === 'select' ) : ?>
								<select id="taxonomy_<?php echo esc_attr( $taxonomy ); ?>" name="taxonomy_<?php echo esc_attr( $taxonomy ); ?>">
									<option value=""><?php esc_html_e( 'Select…', 'postforge' ); ?></option>
									<?php foreach ( $terms as $term ) : ?>
										<option value="<?php echo esc_attr( $term->term_id ); ?>">
											<?php echo esc_html( $term->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>

							<?php elseif ( $field_type === 'multiselect' ) : ?>
								<select id="taxonomy_<?php echo esc_attr( $taxonomy ); ?>" name="taxonomy_<?php echo esc_attr( $taxonomy ); ?>[]" multiple>
									<?php foreach ( $terms as $term ) : ?>
										<option value="<?php echo esc_attr( $term->term_id ); ?>">
											<?php echo esc_html( $term->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>

							<?php elseif ( $field_type === 'checkbox' ) : ?>
								<?php foreach ( $terms as $term ) : ?>
									<label>
										<input type="checkbox" name="taxonomy_<?php echo esc_attr( $taxonomy ); ?>[]" value="<?php echo esc_attr( $term->term_id ); ?>">
										<?php echo esc_html( $term->name ); ?>
									</label><br>
								<?php endforeach; ?>

							<?php elseif ( $field_type === 'radio' ) : ?>
								<?php foreach ( $terms as $term ) : ?>
									<label>
										<input type="radio" name="taxonomy_<?php echo esc_attr( $taxonomy ); ?>" value="<?php echo esc_attr( $term->term_id ); ?>">
										<?php echo esc_html( $term->name ); ?>
									</label><br>
								<?php endforeach; ?>
							<?php endif; ?>
						</p>
					<?php endforeach; ?>
				<?php endif; ?>


			<?php if ( ! empty( $values['custom_fields'] ) ) : ?>
				<?php foreach ( $values['custom_fields'] as $meta_key => $field ) :

					$label    = $field['label'] ?? $meta_key;
					$field_object = null;

					if ( function_exists( 'acf_get_field' ) ) {
						$field_object = acf_get_field( $meta_key );
					}

					$type     = $field_object['type'] ?? 'text';
					$required = ! empty( $field['required'] ) ? 'required' : '';
					$choices  = $field_object['choices'] ?? array();
					?>
					
					<p>
						<label for="field_<?php echo esc_attr( $meta_key ); ?>">
							<?php echo esc_html( $label ); ?>
							<?php echo $required ? ' *' : ''; ?>
						</label><br>

						<?php if ( 'textarea' === $type ) : ?>
							<textarea id="field_<?php echo esc_attr( $meta_key ); ?>" <?php echo esc_attr( $required ); ?>></textarea>

						<?php elseif ( 'select' === $type && ! empty( $choices ) ) : ?>
							<select id="field_<?php echo esc_attr( $meta_key ); ?>" <?php echo esc_attr( $required ); ?>>
								<option value=""><?php esc_html_e( 'Select…', 'postforge' ); ?></option>
								<?php foreach ( $choices as $key => $choice ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $choice ); ?></option>
								<?php endforeach; ?>
							</select>

						<?php elseif ( 'checkbox' === $type && ! empty( $choices ) ) : ?>
							<?php foreach ( $choices as $key => $choice ) : ?>
								<label>
									<input type="checkbox" name="field_<?php echo esc_attr( $meta_key ); ?>[]" value="<?php echo esc_attr( $key ); ?>"> 
									<?php echo esc_html( $choice ); ?>
								</label><br>
							<?php endforeach; ?>

						<?php else : ?>
							<input type="text" id="field_<?php echo esc_attr( $meta_key ); ?>" <?php echo esc_attr( $required ); ?>>
						<?php endif; ?>
					</p>

				<?php endforeach; ?>
			<?php endif; ?>

			<p>
				<button type="button" disabled><?php esc_html_e( 'Submit (Preview only)', 'postforge' ); ?></button>
			</p>
		</form>

		<?php

		return ob_get_clean();
	}

	/**
	 * Get Available User Roles
	 */

	public function ajax_get_available_user_roles(){
		// Security check
		check_ajax_referer( 'postforge_ajax_nonce', 'nonce' );

		$response = postforge_get_available_user_roles();

		wp_send_json_success( $response );
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
								'meta_key' 		=> $field['name'],
								'label'    		=> $field['label'],
								'type'    		=> $field['type'],
								'prefix'    	=> $field['prefix'],
								'placeholder'   => $field['placeholder'],
								'required'    	=> $field['required'],
								'choices'    	=> !empty($field['choices']) ? $field['choices'] : array(),
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
					'meta_key' 		=> $key,
					'label'    		=> $key,
					'type'    		=> '',
					'prefix'    	=> '',
					'placeholder' 	=> '',
					'required'    	=> '',
					'choices'    	=> array(),
				);
			}
		}

		wp_send_json_success( array(
			'taxonomies' => $taxonomies,
			'meta_keys'  => $meta_keys,
		) );
	}
}