<?php

class Postforge_Public {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function register_shortcode() {
        add_shortcode( 'postforge_form', array( $this, 'render_postforge_form' ) );
    }

    public function render_postforge_form( $atts ) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts );

        $form_id = intval( $atts['id'] );
        if ( ! $form_id ) {
            echo '<p>' . esc_html__( 'Invalid form ID.', 'postforge' ) . '</p>';
            return ob_get_clean();
        }

        // Get form settings
        $post_type = get_post_meta( $form_id, 'postforge_post_type', true );
        $taxonomies = get_post_meta( $form_id, 'postforge_taxonomies', true );
        $custom_fields = get_post_meta( $form_id, 'postforge_custom_fields', true );
        $login_required = get_post_meta( $form_id, 'postforge_login_required', true );
        $success_message = get_post_meta( $form_id, 'postforge_success_message', true );
        $default_status = get_post_meta( $form_id, 'postforge_post_status', true );
        $notification_email = get_post_meta( $form_id, 'postforge_notification_email', true );
        $allowed_roles = get_post_meta( $form_id, 'postforge_allowed_roles', true );
        // Make sure it's an array.
        $allowed_roles = is_array( $allowed_roles ) ? $allowed_roles : array();

        ob_start();

        // Check login requirement
        if ( $login_required && ! is_user_logged_in() ) {
            echo '<p>' . esc_html__( 'You must be logged in to submit this form.', 'postforge' ) . '</p>';
            return ob_get_clean();
        }elseif(  $login_required && is_user_logged_in() ){
            $current_user = wp_get_current_user();
            // wp_get_current_user()->roles returns an array (user may have multiple roles).
            $user_roles = (array) $current_user->roles;

            // Check if there is any intersection between allowed roles and user roles.
            $has_access = array_intersect( $allowed_roles, $user_roles );

            if ( empty( $has_access ) ) {
                // User dont have permission.
                echo '<p>' . esc_html__( 'You do not have permission to access this form.', 'postforge' ) . '</p>';
                return ob_get_clean();
            }
        }
        

        // Handle form submission
        if ( isset( $_POST['postforge_submit'] ) && isset( $_POST['postforge_form_id'] ) && intval( $_POST['postforge_form_id'] ) === $form_id ) {

            if ( ! wp_verify_nonce( $_POST['postforge_nonce'], 'postforge_form_' . $form_id ) ) {
                echo '<p>Security check failed.</p>';
                return ob_get_clean();
            }

            $post_data = [
                'post_type'   => $post_type,
                'post_status' => $default_status ?: 'publish',
                'post_title'  => sanitize_text_field( $_POST['postforge_post_title'] ),
                'post_content'=> sanitize_textarea_field( $_POST['postforge_post_content'] ),
            ];

            $post_id = wp_insert_post( $post_data );

            if ( $post_id ) {
                // Save taxonomies
                if ( ! empty( $taxonomies ) ) {
                    foreach ( $taxonomies as $taxonomy ) {
                        if ( isset( $_POST['postforge_taxonomy'][ $taxonomy ] ) ) {
                            $terms = array_map( 'sanitize_text_field', (array) $_POST['postforge_taxonomy'][ $taxonomy ] );
                            wp_set_object_terms( $post_id, $terms, $taxonomy );
                        }
                    }
                }

                // Save custom fields
                if ( ! empty( $custom_fields ) ) {
                    foreach ( $custom_fields as $field ) {
                        if ( isset( $_POST['postforge_meta'][ $field['meta_key'] ] ) ) {
                            $value = sanitize_text_field( $_POST['postforge_meta'][ $field['meta_key'] ] );
                            update_post_meta( $post_id, $field['meta_key'], $value );
                        }
                    }
                }

                // Send notification email
                if ( $notification_email ) {
                    wp_mail(
                        $notification_email,
                        'New PostForge Submission',
                        'A new submission was received on your site.'
                    );
                }

                echo '<div class="postforge-success">' . esc_html( $success_message ?: 'Thank you for your submission!' ) . '</div>';
                return ob_get_clean();
            } else {
                echo '<p>Error creating post.</p>';
                return ob_get_clean();
            }
        }

        // Render form
        ?>
        <form method="post" class="postforge-form">
            <?php wp_nonce_field( 'postforge_form_' . $form_id, 'postforge_nonce' ); ?>
            <input type="hidden" name="postforge_form_id" value="<?php echo esc_attr( $form_id ); ?>">

            <p>
                <label>Post Title *</label><br>
                <input type="text" name="postforge_post_title" required>
            </p>

            <p>
                <label>Post Content *</label><br>
                <textarea name="postforge_post_content" required></textarea>
            </p>

            <?php if ( ! empty( $taxonomies ) ) : ?>
                <?php foreach ( $taxonomies as $taxonomy ) :
                    $terms = get_terms( array(
                        'taxonomy'   => $taxonomy,
                        'hide_empty' => false,
                    ) );
                    if ( empty( $terms ) ) continue;
                    ?>
                    <p>
                        <label><?php echo esc_html( get_taxonomy( $taxonomy )->labels->singular_name ); ?></label><br>
                        <select name="postforge_taxonomy[<?php echo esc_attr( $taxonomy ); ?>]">
                            <option value="">Select...</option>
                            <?php foreach ( $terms as $term ) : ?>
                                <option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ( ! empty( $custom_fields ) ) : ?>
                <?php foreach ( $custom_fields as $field ) :
                    $label = $field['label'] ?: $field['meta_key'];
                    $required = $field['required'] ? 'required' : '';
                ?>
                    <p>
                        <label><?php echo esc_html( $label ); ?><?php echo $required ? ' *' : ''; ?></label><br>
                        <input type="text" name="postforge_meta[<?php echo esc_attr( $field['meta_key'] ); ?>]" <?php echo $required; ?>>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>

            <p>
                <button type="submit" name="postforge_submit">Submit</button>
            </p>
        </form>
        <?php

        return ob_get_clean();
    }
}
