<?php
/**
 * Plugin Name: Post Payments
 * Description: Tracks story cost and payment due totals.
 * Version: 1.2
 * Author: Matt Johnson, Alley Interactive
 *
 */

class Post_Payments {

	public $default_post_types = 'post';
	public $settings_option = 'post-payments-settings';
	public $meta_key = 'post_cost';
	public $currency_symbol = '$';

	public function __construct() {

		add_action( 'init', array( $this, 'add_meta_boxes' ) );
		add_action( 'init', array( $this, 'add_settings_page' ), 99 );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'admin_init', array( $this, 'enqueue' ) );
		add_action( 'admin_menu', array( $this, 'add_tool_page' ) );
		add_action( 'admin_init', array( $this, 'download_report' ) );
        add_action( 'wp_ajax_link_author_post_cost_to_post', array( $this, 'link_author_post_cost_to_post' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );


		$settings = get_option( $this->settings_option );
		if ( ! empty( $settings['currency_symbol'] ) ) {
			// Adding a space after alpha base currency identifiers
			if ( preg_match( '#[a-z]+$#i', $settings['currency_symbol'] ) ) {
				$this->currency_symbol = $settings['currency_symbol'] . ' ';
			} else {
				$this->currency_symbol = $settings['currency_symbol'];
			}
		}
	}

	public function add_meta_boxes() {

		if ( ! defined( 'FM_VERSION' ) ) {
			return;
		}

		$fm = new Fieldmanager_TextField( array(
			'name' => $this->meta_key,
			'description' => __( 'Enter a decimal amount without a currency sign.', 'post-payments' ),
			'attributes' => array( 'size' => 10, 'placeholder' => '0.00' ),
		) );
		$fm->add_meta_box( __( 'Story Cost', 'post-payments' ), $this->get_post_types(), 'normal', 'default' );
        $fm->add_user_form( __( 'Story Cost', 'post-payments' ) );

        $fm = new Fieldmanager_Group( array(
            'name'           => 'report_tags',
            'limit'          => 0,
            'add_more_label' => 'Add Another Report Tag',
            'children'       => array(
                'report_tag' => new Fieldmanager_Select( array(
                    'first_empty' => true,
                    'datasource'  => new Fieldmanager_Datasource_Term( array(
                        'taxonomy'        => 'report-tags',
                        'append_taxonomy' => true,
                    ) ),
                ) ),
            ),
        ) );
        $fm->add_meta_box( __( 'Report Tags', 'post-payments' ), $this->get_post_types(), 'side', 'default' );
    }

	public function add_settings_page() {

		if ( ! defined( 'FM_VERSION' ) ) {
			return;
		}

		$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
		$fm = new Fieldmanager_Group( array(
            'name'     => $this->settings_option,
            'children' => array(
				'post_types' => new Fieldmanager_Checkboxes( array(
                    'label'   => __( 'Select post types to include in cost calculations.', 'post-payments' ),
                    'options' => $post_types,
				) ),
				'currency_symbol' => new Fieldmanager_TextField( array(
                    'label'      => __( 'Currency symbol', 'post-payments' ),
                    'attributes' => array( 'size' => 3 ),
				) ),
			),
		) );
		$fm->add_submenu_page( 'options-general.php', __( 'Post Payments Settings', 'post-payments' ), __( 'Post Payments', 'post-payments' ), 'edit_posts', 'post_payments' );
	}

	public function add_tool_page() {

		if ( ! defined( 'COAUTHORS_PLUS_VERSION' ) ) {
			return;
		}

		add_management_page( __( 'Post Payments Report', 'post-payments' ), __( 'Payments Report', 'post-payments' ), 'edit_posts', 'payments_report', array( $this, 'report_page' ) );
	}

	public function enqueue() {
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.css' );
		wp_enqueue_style( 'post-payments-style', plugin_dir_url( __FILE__ ) . 'css/admin.css' );

	}

	public function report_page() {
		?><div class="wrap">
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery('input.datepicker').datepicker();
				});
			</script>
			<h2><?php esc_html_e( 'Post Payments Report', 'post-payments' ); ?></h2>
			<p><?php esc_html_e( 'Select a date range to view the report.', 'post-payments' ); ?></p>
			<p>
				<form>
					<input type="hidden" name="page" value="payments_report" />
					<input name="from_date" class="datepicker" <?php if ( ! empty( $_GET['from_date'] ) ): ?>value="<?php echo esc_attr( $_GET['from_date'] ); ?>"<?php endif; ?> />
					<input name="to_date" class="datepicker" <?php if ( ! empty( $_GET['to_date'] ) ): ?>value="<?php echo esc_attr( $_GET['to_date'] ); ?>"<?php endif; ?> />
					<input type="submit" value="<?php esc_html_e( 'Generate', 'post-payments' ); ?>" class="button submit" />
				</form>
			</p>
		</div><?php

		if ( ! empty( $_GET['from_date'] ) && ! empty( $_GET['to_date'] ) ) {
			$authors = $this->get_report_data( sanitize_text_field( $_GET['from_date'] ), sanitize_text_field( $_GET['to_date'] ) );

			if ( empty( $authors ) ) {
				?><p><b><?php esc_html_e( 'No posts found with cost data in this date range.' ); ?></b></p><?php
			} else {
				?>

				<?php foreach ( $authors as $name => $data ): ?>
					<p class="post-payments-author-name"><b><?php echo esc_html( $name ); ?></b></p>
					<ul class="post-payments-list"><?php foreach ( $data['posts'] as $post_id ) : ?>
						<li><?php echo esc_html( $this->format_currency( get_post_meta( $post_id, $this->meta_key, true ) ) ); ?>: <i><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></i></li>
					<?php endforeach; ?></ul>
					<p class="post-payments-author-payment"><?php esc_html_e( 'Total payment:', 'post-payments' ); ?> <b><?php echo esc_html( $this->format_currency( $data['total'] ) ); ?></b></p>
				<?php endforeach; ?>
					<form>
						<input type="hidden" name="page" value="payments_report" />
						<input type="hidden" name="from_date" class="datepicker" <?php if ( ! empty( $_GET['from_date'] ) ): ?>value="<?php echo esc_attr( $_GET['from_date'] ); ?>"<?php endif; ?> />
						<input type="hidden" name="to_date" class="datepicker" <?php if ( ! empty( $_GET['to_date'] ) ): ?>value="<?php echo esc_attr( $_GET['to_date'] ); ?>"<?php endif; ?> />
						<input type="hidden" name="download" value="download_report" />
						<input type="submit" value="<?php esc_html_e( 'Download Report', 'post-payments' ); ?>" class="button submit" />
					</form>
				<?php
			}
		}
	}

	public function get_post_types() {
		$settings = get_option( $this->settings_option );
		if ( empty( $settings['post_types'] ) ) {
			return $this->default_post_types;
		} else {
			return $settings['post_types'];
		}
	}

	public function get_report_data( $from_date, $to_date ) {
		$posts = get_posts( array(
            'posts_per_page' => 1000,
            'post_status'    => 'publish',
            'post_type'      => array( 'post', 'evergreen' ),
            'meta_query'     => array(
				array(
                    'key'     => $this->meta_key,
                    'value'   => array( '0.00', '' ),
                    'compare' => 'NOT IN',
				),
			),
			'date_query' => array(
				array(
                    'after'  => $from_date,
                    'before' => $to_date,
				),
			),
		) );

		$authors = array();

		foreach ( $posts as $post ) {
			$post_authors = get_coauthors( $post->ID );
			foreach ( $post_authors as $post_author ) {
				if ( empty( $authors[ $post_author->display_name ] ) ) {
					$authors[ $post_author->display_name ] = $this->add_author( $post_author, $post->ID );
				} else {
					$authors[ $post_author->display_name ] = $this->add_author( $post_author, $post->ID, $authors[ $post_author->display_name ] );
				}
			}
		}
		return $authors;
	}

	public function add_author( $post_author, $post_id, $data = null ) {
		if ( ! $data ) {
			$data = array(
                'posts'    => array(),
                'payments' => array(),
                'tags'     => array(),
			);
		}

		$data['posts'][] = $post_id;

		$payment = get_post_meta( $post_id, $this->meta_key, true );
		if ( is_numeric( $payment ) ) {
			$data['payments'][] = floatval( $payment );
		}

        $report_tags = get_post_meta( $post_id, 'report_tags', true );
        $tags = array();
        if ( ! empty( $report_tags ) ) {
            foreach ( $report_tags as $tag ) {
                if ( ! empty( $tag['report_tag'] ) ) {
                    $tag = get_term( $tag['report_tag'], 'report-tags' );
                    if ( ! empty( $tag->name ) ) {
                        $tags[] = $tag->name;
                    }
                }
            }
        }
        $data['tags'][ $post_id ] = $tags;

        $data['total'] = array_sum( $data['payments'] );
		return $data;
	}

	public function format_currency( $number ) {
		if ( ! empty( $number ) ) {
			return $this->currency_symbol . number_format_i18n( $number, 2 );
		}
	}

	public function download_report() {

		if ( ! empty( $_GET['download'] ) && 'download_report' == $_GET['download'] ) {
			// allowing for filterable capabilities
			$user_cap = apply_filters( 'post-payment-caps', 'update_core' );
			// restricting to admins only by default
			if ( ! current_user_can( $user_cap ) ) {
				wp_die( __( 'You do not have permission to do this', 'post-payments' ) );
			}
			// setting headers
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . sanitize_text_field( $_GET['from_date'] ) . '-to-' . sanitize_text_field( $_GET['to_date'] ) . '-author-data.csv' );
			// getting authors for report
			$authors = $this->get_report_data( sanitize_text_field( $_GET['from_date'] ), sanitize_text_field( $_GET['to_date'] ) );
            // start creating the csv string
            $labels = array(
                esc_html__( 'Total', 'post-payments' ),
                esc_html__( 'Name', 'post-payments' ),
                esc_html__( 'Articles', 'post-payments' ),
                esc_html__( 'Tags', 'post-payments' ),
                "\n",
            );
            $csv = implode( ',', $labels );
            foreach ( $authors as $name => $author ) {
                $author_posts = $author['posts'];
                foreach ( $author_posts as $key => $post ) {
                    $tags = '';
                    if ( ! empty( $author['tags'] ) ) {
                        if ( ! empty( $author['tags'][ $post ] ) ) {
                            $tags = implode( '; ', $author['tags'][ $post ] );
                        }
                    }
                    // Strip commas from post title and decode entities
                    $title = get_the_title( $post );
                    $title = str_replace( ',', '', $title );
                    $title = wp_kses_decode_entities( $title );
                    if ( 0 == $key ) {
                        // first row for each author
                        $csv .= absint( $author['total'] ) . ',' . esc_html( $name ) . ',' . $title . ',' . esc_html( $tags ) . "\n";
                    } else {
                        // empty info to group articles by author
                        $csv .= '' . ',' . ''. ',' . $title . ',' . esc_html( $tags ) . "\n";
                    }
                }
            }
            // echoing the csv string for the download
			echo $csv;
			die();
		}

	}

	/**
	 * Create taxonomies
	 *
	 * @return null
	 */
	public function register_taxonomies() {
		// Register Report tags taxonomy, but keep hidden from post edit screen.
		// We'll register a custom metabox with a dropdown instead.
		$args = array(
			'labels' => array(
				'name'              => __( 'Report Tags', 'post-payments' ),
				'singular_name'     => __( 'Report Tag', 'taxonomy singular name' ),
				'search_items'      => __( 'Search Report Tags' ),
				'all_items'         => __( 'All Report Tags' ),
				'edit_item'         => __( 'Edit Report Tag' ),
				'update_item'       => __( 'Update Report Tag' ),
				'add_new_item'      => __( 'Add New Report Tag' ),
				'new_item_name'     => __( 'New Report Tag Name' ),
				'menu_name'         => __( 'Report Tags' ),
			),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_nav_menus'     => false,
            'show_tagcloud'         => false,
            'show_in_quick_edit'    => true,
            'meta_box_cb'           => false,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => 'report_tag',
            'rewrite'               => false,
		);
		register_taxonomy( 'report-tags', array( 'post' ), $args );
	}

    /**
     * Ajax callback for linking author post cost to post
     *
     * @return void
     */
    public function link_author_post_cost_to_post() {
        $author_slug = ! empty( $_POST['author_slug'] ) ? sanitize_text_field( $_POST['author_slug'] ) : '';
        if ( ! empty( $author_slug ) ) {
            // First attempt to get guest author from post type
            $posts = get_posts( array(
                'post_type' => 'guest-author',
                'meta_key'     => 'cap-user_login',
                'meta_value'   => $author_slug,
                'posts_per_page' => 1,
            ) );
            if ( ! empty( $posts[0] ) ) {
                $author = $posts[0];
                $author_id = $author->ID;
                $author_post_cost = get_post_meta( $author_id, 'post_cost', true );
                if ( ! empty( $author_post_cost ) ) {
                    echo json_encode( $author_post_cost );
                }
            } else { // Try to get normal user if guest author wasn't successfull
                $user_id = get_user_by( $author_slug, 'ID' );
                if ( ! empty( $user_id ) ) {
                    $user_post_cost = get_user_meta( $user_id, 'post_cost', true );
                    if ( ! emtpy( $user_post_cost ) ) {
                        echo json_encode( $user_post_cost );
                    }
                }
            }
        }
        exit;
    }

    public function admin_enqueue_scripts() {
        global $post;
        wp_enqueue_script( 'post-payments-global', plugin_dir_url( __FILE__ ) . '/js/global.js', array( 'jquery' ), '0.1', true );
        wp_localize_script( 'post-payments-global', 'post_payments', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ), 'action' => 'link_author_post_cost_to_post' ) );
    }


}

new Post_Payments();
