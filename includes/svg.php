<?php 
namespace HappyFiles;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class SVG {

  public function __construct() {
		// Load SVG Sanitizer Library (https://github.com/darylldoyle/svg-sanitizer)
		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/data/AttributeInterface.php';
		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/data/TagInterface.php';
		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/data/AllowedAttributes.php';
		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/data/AllowedTags.php';
		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/data/XPath.php';

		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/ElementReference/Resolver.php';
		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/ElementReference/Subject.php';
		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/ElementReference/Usage.php';

		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/Exceptions/NestingException.php';

		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/Helper.php';
		require_once HAPPYFILES_PATH . 'includes/svg-sanitizer/Sanitizer.php';

		add_filter( 'upload_mimes', [$this, 'upload_mimes'] );
		add_filter( 'wp_check_filetype_and_ext', [$this, 'disable_real_mime_check'], 10, 4 );
		add_filter( 'wp_prepare_attachment_for_js', [ $this, 'wp_prepare_attachment_for_js' ], 10, 3 );
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'wp_handle_upload_prefilter' ] );
	}

	/**
	 * Add SVG file type upload support
	 */
	public function upload_mimes( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		return $mimes;
	}

	/**
	 * Disable real MIME check (introduced in WordPress 4.7.1)
	 *
	 * https://wordpress.stackexchange.com/a/252296/44794
	 */
	public function disable_real_mime_check( $data, $file, $filename, $mimes ) {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		$filetype = wp_check_filetype( $filename, $mimes );

		return [
				'ext'             => $filetype['ext'],
				'type'            => $filetype['type'],
				'proper_filename' => $data['proper_filename']
		];
	}

	/**
	 * Render SVG thumbnail preview image
	 */
	public function wp_prepare_attachment_for_js( $response, $attachment, $meta ) {
    if ( class_exists( 'SimpleXMLElement' ) && $response['type'] === 'image' && $response['subtype'] === 'svg+xml' ) {
			try {
				$path = get_attached_file( $attachment->ID );
				
				if ( @file_exists( $path ) ) {
					$svg = new \SimpleXMLElement( @file_get_contents( $path ) );
					$src = $response['url'];
					$width = (int) $svg['width'];
					$height = (int) $svg['height'];

					// Media library
					$response['image'] = compact( 'src', 'width', 'height' );
					$response['thumb'] = compact( 'src', 'width', 'height' );

					// Media single image
					$response['sizes']['full'] = [
						'height'      => $height,
						'width'       => $width,
						'url'         => $src,
						'orientation' => $height > $width ? 'portrait' : 'landscape',
					];
				}
			}

			catch ( Exception $e ) {}
    }

    return $response;
	}

	/**
	 * Sanitize SVG files on file upload
	 * 
	 * @uses https://github.com/darylldoyle/svg-sanitizer
	 */
	public function wp_handle_upload_prefilter( $file ) {
		// Sanitize SVG file
		if ( $file['type'] === 'image/svg+xml' ) {
			$sanitizer = new \enshrined\svgSanitize\Sanitizer();

			$dirty = file_get_contents( $file['tmp_name'] );

			$clean = $sanitizer->sanitize( $dirty );

			// Success: SVG successfully sanitized (save it)
			if ( $clean ) {
				file_put_contents( $file['tmp_name'], $clean );
			} 
			
			// Error: XML parsing failed (return error message)
			else {
				$file['error'] = __( 'This file couldn\'t be sanitized for security reasons and wasn\'t uploaded.', 'happyfiles' );
			}
		}

		return $file;
	}

}