<?php
/**
 * Livia_Group_Page::check_address() against a real WordPress.
 *
 * @package Livia
 */

final class AddressTest extends Livia_TestCase {

	public function test_a_free_address() {
		$this->assertNull( Livia_Group_Page::check_address( 'reservation-groupe', 'r1' ) );
	}

	public function test_plain_permalinks_are_refused() {
		$this->set_permalink_structure( '' );
		$conflict = Livia_Group_Page::check_address( 'reservation-groupe', 'r1' );
		$this->assertTrue( $conflict['permalinks'] );
	}

	public function test_an_empty_address_is_refused() {
		$this->assertNotNull( Livia_Group_Page::check_address( '', 'r1' ) );
	}

	public function test_contents_of_any_status_hold_their_address() {
		foreach ( array( 'publish', 'draft', 'private', 'future' ) as $status ) {
			$id       = self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_status' => $status,
					'post_name'   => 'groupe-' . $status,
					'post_title'  => 'Réservation groupe',
					'post_date'   => 'future' === $status ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : '',
				)
			);
			$conflict = Livia_Group_Page::check_address( 'groupe-' . $status, 'r1' );
			$this->assertSame( $id, $conflict['post']->ID, $status );
		}
		$post = self::factory()->post->create( array( 'post_name' => 'une-actualite' ) );
		$this->assertSame( $post, Livia_Group_Page::check_address( 'une-actualite', 'r1' )['post']->ID );
		$this->assertStringContainsString( 'Réservation groupe', Livia_Group_Page::check_address( 'groupe-publish', 'r1' )['message'] );
	}

	public function test_a_child_page_does_not_hold_a_top_level_address() {
		$parent = self::factory()->post->create( array( 'post_type' => 'page' ) );
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent,
				'post_name'   => 'enfant',
			)
		);
		$this->assertNull( Livia_Group_Page::check_address( 'enfant', 'r1' ) );
	}

	public function test_a_trashed_content_holds_its_address_unless_the_screen_trashed_it_to_free_it() {
		$id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'ancienne',
			)
		);
		wp_trash_post( $id );
		$this->assertSame( $id, Livia_Group_Page::check_address( 'ancienne', 'r1' )['post']->ID );

		$this->connect();
		Livia_Service::save_restaurant( 'r1', array( 'replaced_id' => $id ) );
		$this->assertNull( Livia_Group_Page::check_address( 'ancienne', 'r1' ) );
	}

	public function test_the_group_page_does_not_block_its_own_address() {
		$this->connect();
		$result = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' );
		$this->assertSame( 'reservation-groupe', $result['page']->post_name );
		$this->assertNull( Livia_Group_Page::check_address( 'reservation-groupe', 'r1' ) );
	}

	public function test_terms_files_and_reserved_addresses() {
		self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'evenements',
			)
		);
		self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'brunch',
			)
		);
		$this->assertNotNull( Livia_Group_Page::check_address( 'evenements', 'r1' ) );
		$this->assertNotNull( Livia_Group_Page::check_address( 'brunch', 'r1' ) );

		foreach ( array( 'wp-admin', 'feed', 'wp-json', '2024', 'embed' ) as $slug ) {
			$this->assertNotNull( Livia_Group_Page::check_address( $slug, 'r1' ), $slug );
		}

		$dir = ABSPATH . 'livia-dossier-test';
		wp_mkdir_p( $dir );
		$conflict = Livia_Group_Page::check_address( 'livia-dossier-test', 'r1' );
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
		$this->assertNotNull( $conflict );
	}

	public function test_a_rule_of_another_plugin_holds_its_address() {
		add_rewrite_rule( '^menu-du-jour/?$', 'index.php?livia_other_plugin=1', 'top' );
		flush_rewrite_rules( false );
		$this->assertNotNull( Livia_Group_Page::check_address( 'menu-du-jour', 'r1' ) );
		$this->assertNull( Livia_Group_Page::check_address( 'menu-du-soir', 'r1' ) );
	}

	public function test_another_restaurant_s_group_page_holds_its_address_and_is_never_offered_to_the_trash() {
		$this->connect();
		$this->connect_second();
		$page = Livia_Group_Page::publish( 'r1', 'reservation-groupe', 'Réservation de groupe' )['page'];
		Livia_Group_Page::unpublish( 'r1' );

		$conflict = Livia_Group_Page::check_address( 'reservation-groupe', 'r2' );
		$this->assertArrayNotHasKey( 'post', $conflict );
		$this->assertStringContainsString( 'Brasserie Lumière', $conflict['message'] );
		$this->assertNull( Livia_Group_Page::check_address( 'reservation-groupe', 'r1' ) );
		$this->assertSame( 'draft', get_post_status( $page->ID ) );
	}
}
