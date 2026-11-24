<?php
/** Run only on disposable CI WordPress: APIARY_PRESS_TESTS=1 wp --user=admin eval-file tests/cascade-deletion.php. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || getenv( 'APIARY_PRESS_TESTS' ) !== '1' ) {
	throw new RuntimeException( 'Use the disposable WordPress environment from the Tests workflow.' );
}
$appr_checks = 0;
$appr_check = static function ( $condition, $message ) use ( &$appr_checks ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$appr_checks;
};
$appr_posts = array();
$appr_files = array();
$appr_create = static function ( $type, $parent = 0, $status = 'publish' ) use ( &$appr_posts ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_parent' => $parent, 'post_status' => $status, 'post_title' => 'Disposable cascade fixture' ), true );
	if ( is_wp_error( $id ) || ! $id ) { throw new RuntimeException( 'Could not create the post fixture.' ); }
	$appr_posts[] = $id;
	return $id;
};
try {
	$appr_trees = array();
	foreach ( array( 'deleted', 'retained' ) as $appr_name ) {
		$appr_tree = array();
		$appr_tree['apiary'] = $appr_create( \ApiaryPress\Apiary::APIARY_POST_TYPE );
		$appr_tree['hive'] = $appr_create( \ApiaryPress\Hive::HIVE_POST_TYPE, $appr_tree['apiary'], 'private' );
		$appr_tree['visit'] = $appr_create( \ApiaryPress\Visit::HIVE_VISIT_POST_TYPE, $appr_tree['hive'], 'draft' );
		$appr_tree['treatment'] = $appr_create( \ApiaryPress\Treatment::HIVE_TREATMENT_POST_TYPE, $appr_tree['hive'], 'private' );
		update_post_meta( $appr_tree['treatment'], \ApiaryPress\Treatment::KIND_META_KEY, 'treatment' );
		$appr_tree['feeding'] = $appr_create( \ApiaryPress\Treatment::HIVE_TREATMENT_POST_TYPE, $appr_tree['hive'], 'draft' );
		update_post_meta( $appr_tree['feeding'], \ApiaryPress\Treatment::KIND_META_KEY, 'feeding' );
		$appr_tree['harvest'] = $appr_create( \ApiaryPress\Harvest::HIVE_HARVEST_POST_TYPE, $appr_tree['hive'] );
		update_post_meta( $appr_tree['harvest'], \ApiaryPress\Harvest::QUANTITY_META_KEY, 12.5 );
		$appr_upload = wp_upload_bits( 'apiary-test-' . wp_generate_uuid4() . '.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII=' ) );
		if ( $appr_upload['error'] ) { throw new RuntimeException( 'Could not create the photo fixture.' ); }
		$appr_files[ $appr_name ] = $appr_upload['file'];
		$appr_photo = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Disposable visit photo', 'post_status' => 'inherit' ), $appr_upload['file'], $appr_tree['visit'], true );
		if ( is_wp_error( $appr_photo ) || ! $appr_photo ) { throw new RuntimeException( 'Could not attach the photo fixture.' ); }
		$appr_posts[] = $appr_photo;
		$appr_tree['photo'] = $appr_photo;
		$appr_trees[ $appr_name ] = $appr_tree;
	}
	$appr_check( is_file( $appr_files['deleted'] ) && is_file( $appr_files['retained'] ), 'Both real photo fixtures must exist before deletion.' );

	// Exercise the registered before_delete_post hook through the public WordPress operation.
	wp_delete_post( $appr_trees['deleted']['apiary'], true );
	foreach ( $appr_trees['deleted'] as $appr_kind => $appr_id ) {
		$appr_check( get_post( $appr_id ) === null, 'Deleting an apiary must also remove its ' . $appr_kind . '.' );
	}
	$appr_check( ! is_file( $appr_files['deleted'] ), 'Deleting a visit must remove its actual photo file, not only the attachment post.' );
	$appr_other = $appr_trees['retained'];
	$appr_parents = array( 'apiary' => 0, 'hive' => $appr_other['apiary'], 'visit' => $appr_other['hive'], 'treatment' => $appr_other['hive'], 'feeding' => $appr_other['hive'], 'harvest' => $appr_other['hive'], 'photo' => $appr_other['visit'] );
	foreach ( $appr_other as $appr_kind => $appr_id ) {
		$appr_post = get_post( $appr_id );
		$appr_check( $appr_post instanceof WP_Post && (int) $appr_post->post_parent === $appr_parents[ $appr_kind ], 'Deleting one apiary must preserve the unrelated ' . $appr_kind . ' and its parent relationship.' );
	}
	$appr_check( is_file( $appr_files['retained'] ), 'An unrelated visit photo file must survive the cascade.' );
	$appr_check( get_post_meta( $appr_trees['retained']['harvest'], \ApiaryPress\Harvest::QUANTITY_META_KEY, true ) === '12.5', 'The unrelated harvest data must remain unchanged.' );
	WP_CLI::success( $appr_checks . ' cascade checks passed: complete descendant/photo cleanup and unrelated-apiary preservation.' );
} finally {
	foreach ( array_reverse( $appr_posts ) as $appr_id ) { wp_delete_post( $appr_id, true ); }
	foreach ( $appr_files as $appr_file ) { if ( is_file( $appr_file ) ) { unlink( $appr_file ); } }
}
