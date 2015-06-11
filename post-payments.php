<?php
/**
 * Plugin Name: Post Payments
 * Description: Tracks story cost and payment due totals.
 * Version: 1.0
 * Author: Matt Johnson, Alley Interactive
 *
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
		add_action( 'admin_init', array( $this, 'enqueue' ) );
		add_action( 'admin_menu', array( $this, 'add_tool_page' ) );
		add_action( 'admin_init', array( $this, 'download_report' ) );

		$settings = get_option( $this->settings_option );
		if ( ! empty( $settings['currency_symbol'] ) ) {
			$this->currency_symbol = $settings['currency_symbol'];
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
	}

	public function add_settings_page() {

		if ( ! defined( 'FM_VERSION' ) ) {
			return;
		}

		$post_types = get_post_types( array( 'show_ui' => true ), 'names' );
		$fm = new Fieldmanager_Group( array(
			'name' => $this->settings_option,
			'children' => array(
				'post_types' => new Fieldmanager_Checkboxes( array(
					'label' => __( 'Select post types to include in cost calculations.', 'post-payments' ),
					'options' => $post_types,
				) ),
				'currency_symbol' => new Fieldmanager_TextField( array(
					'label' => __( 'Currency symbol', 'post-payments' ),
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
			'post_status' => 'publish',
			'post_type' => $this->get_post_types(),
			'meta_query' => array(
				array(
					'key' => $this->meta_key,
					'value' => '0.00',
					'compare' => '!=',
				),
			),
			'date_query' => array(
				array(
					'after' => $from_date,
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
				'posts' => array(),
				'payments' => array(),
			);
		}

		$data['posts'][] = $post_id;

		$payment = get_post_meta( $post_id, $this->meta_key, true );
		if ( is_numeric( $payment ) ) {
			$data['payments'][] = floatval( $payment );
		}

		$data['total'] = array_sum( $data['payments'] );

		return $data;

	}

	public function format_currency( $number ) {
		return $this->currency_symbol . number_format( $number, 2 );
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
				"\n",
			);
			$csv = implode( ',', $labels );
			foreach ( $authors as $name => $author ) {
				$author_posts = $author['posts'];
				foreach ( $author_posts as $key => $post ) {
					if ( 0 == $key ) {
						// first row for each author
						$csv .= absint( $author['total'] ) . ',' . esc_html( $name ) . ',' . esc_html( get_the_title( $post ) ) . "\n";
					} else {
						// empty info to group articles by author
						$csv .= '' . ',' . '' . ',' . esc_html( get_the_title( $post ) ) . "\n";
					}
				}
			}
			// echoing the csv string for the download
			echo $csv;
			die();
		}

	}


}

new Post_Payments();
