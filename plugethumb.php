<?php
/**
 * Plugin Name: PlugeThumb – Post Video & Youtube Featured
 * Plugin URI:  https://wordpress.org/plugins/plugethumb
 * Description: Add YouTube Url or Media Library videos to posts. Auto-set YouTube thumbnail as featured image and convert featured image formats (JPG / PNG / WebP / AVIF).
 * Version: 1.0
 * Author: maruffwp
 * Author URI: https://plugesoft.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: plugethumb
 * Requires PHP: 8.0
 * Requires at least: 5.2.4
 * Tested up to: 6.8
 * Stable tag: 1.0
 * Tags: post video, youtube thumbnail, video thumbnail, webp converter, avif converter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PlugeThumb {
    const META_YT    = '_plugethumb_youtube_url';
    const META_VIDEO = '_plugethumb_video_id';
    const META_FMT   = '_plugethumb_img_format';
    const VERSION    = '1.0.0';

    public function __construct() {
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
        add_action( 'save_post', [ $this, 'save_metabox' ], 10, 2 );
        add_action( 'save_post', [ $this, 'maybe_set_featured_image' ], 20, 2 );
        add_action( 'save_post', [ $this, 'maybe_convert_featured_image' ], 30, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
        add_filter( 'the_content', [ $this, 'prepend_video_embed' ] );
    }

    public function register_meta() {
        register_post_meta( '', self::META_YT, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );

        register_post_meta( '', self::META_VIDEO, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'integer',
            'sanitize_callback' => 'absint',
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );

        register_post_meta( '', self::META_FMT, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ] );
    }

    public function add_metabox() {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'plugethumb_meta',
                __( 'YouTube & Media Library Video', 'plugethumb' ),
                [ $this, 'render_metabox' ],
                $pt,
                'side',
                'default'
            );
        }
    }

    public function render_metabox( $post ) {
        wp_nonce_field( 'plugethumb_save', 'plugethumb_nonce' );

        $yt   = get_post_meta( $post->ID, self::META_YT, true );
        $vid  = get_post_meta( $post->ID, self::META_VIDEO, true );
        $fmt  = get_post_meta( $post->ID, self::META_FMT, true );

        ?>
        <p><label for="plugethumb_youtube_url"><strong><?php esc_html_e( 'YouTube Video URL', 'plugethumb' ); ?></strong></label></p>
        <input type="url" id="plugethumb_youtube_url" name="plugethumb_youtube_url"
               value="<?php echo esc_attr( $yt ); ?>" style="width:100%" placeholder="https://www.youtube.com/watch?v=...">
        <small><?php esc_html_e( 'Paste any YouTube URL. Its thumbnail can be set as Featured Image on update.', 'plugethumb' ); ?></small>

        <hr />

        <p><strong><?php esc_html_e( 'Upload/Select Video (Media Library)', 'plugethumb' ); ?></strong></p>
        <input type="hidden" id="plugethumb_video_id" name="plugethumb_video_id" value="<?php echo esc_attr( $vid ); ?>">
        <div style="margin-top:6px;">
            <button type="button" class="button" id="plugethumb_video_upload"><?php esc_html_e( 'Select/Upload Video', 'plugethumb' ); ?></button>
            <button type="button" class="button" id="plugethumb_video_remove"><?php esc_html_e( 'Remove Video', 'plugethumb' ); ?></button>
        </div>
        <video id="plugethumb_video_preview" src="<?php echo esc_attr( $vid ? wp_get_attachment_url( $vid ) : '' ); ?>" style="max-width:100%; margin-top:8px; <?php echo $vid ? '' : 'display:none;'; ?>" controls></video>
        <small><?php esc_html_e( 'Note: If you choose a Media Library video, set Featured Image manually if needed.', 'plugethumb' ); ?></small>

        <hr />

        <p><strong><?php esc_html_e( 'Convert Featured Image Format', 'plugethumb' ); ?></strong></p>
        <?php
        $formats = [
            '' => __( 'Default', 'plugethumb' ),
            'jpg' => 'JPG',
            'png' => 'PNG',
            'webp' => 'WebP',
            'avif' => 'AVIF',
        ];
        foreach ( $formats as $key => $label ) {
            echo '<label style="display:block;margin:3px 0;">';
            printf(
                '<input type="radio" name="plugethumb_img_format" value="%s" %s> %s',
                esc_attr( $key ),
                checked( $fmt, $key, false ),
                esc_html( $label )
            );
            echo '</label>';
        }
        ?>
        <p style="font-size:12px;color:#555;"><?php esc_html_e( 'Choose a format and update the post to convert the featured image.', 'plugethumb' ); ?></p>
        <?php
    }

    public function save_metabox( $post_id, $post ) {
        $nonce = isset( $_POST['plugethumb_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['plugethumb_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'plugethumb_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $yt     = isset( $_POST['plugethumb_youtube_url'] ) ? esc_url_raw( wp_unslash( $_POST['plugethumb_youtube_url'] ) ) : '';
        $vid_id = isset( $_POST['plugethumb_video_id'] ) ? absint( wp_unslash( $_POST['plugethumb_video_id'] ) ) : 0;
        $fmt    = isset( $_POST['plugethumb_img_format'] ) ? sanitize_text_field( wp_unslash( $_POST['plugethumb_img_format'] ) ) : '';

        if ( $yt ) update_post_meta( $post_id, self::META_YT, $yt ); else delete_post_meta( $post_id, self::META_YT );
        if ( $vid_id ) update_post_meta( $post_id, self::META_VIDEO, $vid_id ); else delete_post_meta( $post_id, self::META_VIDEO );
        if ( $fmt !== '' ) update_post_meta( $post_id, self::META_FMT, $fmt ); else delete_post_meta( $post_id, self::META_FMT );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) return;

        wp_enqueue_media();

        $js  = plugin_dir_path( __FILE__ ) . 'assets/js/admin.js';
        $ver = file_exists( $js ) ? filemtime( $js ) : self::VERSION;

        wp_enqueue_script( 'plugethumb-admin', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', [ 'jquery' ], $ver, true );

        wp_localize_script( 'plugethumb-admin', 'PLUGETHUMB_ADMIN', [
            'videoMetaKey' => self::META_VIDEO,
            'ytMetaKey'    => self::META_YT,
        ] );
    }

    public function enqueue_editor_assets() {
        $file = plugin_dir_path( __FILE__ ) . 'assets/js/editor-sidebar.js';
        $ver  = file_exists( $file ) ? filemtime( $file ) : self::VERSION;

        wp_enqueue_script(
            'plugethumb-editor',
            plugin_dir_url( __FILE__ ) . 'assets/js/editor-sidebar.js',
            [ 'wp-plugins','wp-edit-post','wp-element','wp-components','wp-data' ],
            $ver,
            true
        );

        wp_localize_script( 'plugethumb-editor', 'PLUGETHUMB', [
            'metaYt'    => self::META_YT,
            'metaVideo' => self::META_VIDEO,
            'metaFmt'   => self::META_FMT,
            'i18n' => [
                'panelTitle' => __( 'YouTube & Media Library Video', 'plugethumb' ),
                'ytLabel'    => __( 'YouTube Video URL', 'plugethumb' ),
                'videoLabel' => __( 'Upload/Select Video', 'plugethumb' ),
            ],
        ] );
    }

    public function maybe_set_featured_image( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || $post->post_status === 'auto-draft' ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $yt = get_post_meta( $post_id, self::META_YT, true );
        if ( ! $yt ) return;

        $vid = $this->extract_youtube_id( $yt );
        if ( ! $vid ) return;

        if ( has_post_thumbnail( $post_id ) ) return;

        $img_url = "https://i.ytimg.com/vi/{$vid}/maxresdefault.jpg";
        $ok = $this->sideload_and_set_thumbnail( $post_id, $img_url );
        if ( ! $ok ) {
            $img_url = "https://i.ytimg.com/vi/{$vid}/hqdefault.jpg";
            $this->sideload_and_set_thumbnail( $post_id, $img_url );
        }
    }

    public function maybe_convert_featured_image( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || $post->post_status === 'auto-draft' ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $format = get_post_meta( $post_id, self::META_FMT, true );
        if ( ! $format ) return;
        $allowed = [ 'jpg','jpeg','png','webp','avif' ];
        if ( ! in_array( strtolower( $format ), $allowed, true ) ) return;

        $this->convert_featured_image( $post_id, strtolower( $format ) );
    }

    private function convert_featured_image( $post_id, $format ) {
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) return;

        $old = get_attached_file( $thumb_id );
        if ( ! $old || ! file_exists( $old ) ) return;

        $editor = wp_get_image_editor( $old );
        if ( is_wp_error( $editor ) ) return;

        $target_mime = $this->mime_by_ext( $format );
        if ( method_exists( $editor, 'supports_mime_type' ) && ! $editor->supports_mime_type( $target_mime ) ) {
            $format = 'jpg';
            $target_mime = 'image/jpeg';
        }

        $info = pathinfo( $old );
        $new_file = $info['dirname'] . '/' . $info['filename'] . '.' . $format;

        $saved = $editor->save( $new_file );
        if ( is_wp_error( $saved ) || ! file_exists( $new_file ) ) return;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        update_attached_file( $thumb_id, $new_file );

        $meta = wp_generate_attachment_metadata( $thumb_id, $new_file );
        if ( ! is_wp_error( $meta ) ) {
            wp_update_attachment_metadata( $thumb_id, $meta );
        }

        if ( $old !== $new_file && file_exists( $old ) ) {
            wp_delete_file( $old );
        }
    }

    private function sideload_and_set_thumbnail( $post_id, $img_url ) {
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = download_url( $img_url );
        if ( is_wp_error( $tmp ) ) return false;

        $file = [
            'name'     => basename( wp_parse_url( $img_url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp,
        ];

        $att_id = media_handle_sideload( $file, $post_id );
        if ( is_wp_error( $att_id ) ) {
            wp_delete_file( $tmp );
            return false;
        }

        set_post_thumbnail( $post_id, $att_id );
        return true;
    }

    private function extract_youtube_id( $url ) {
        $patterns = [
            '/v=([a-zA-Z0-9_-]{6,})/i',
            '/youtu\.be\/([a-zA-Z0-9_-]{6,})/i',
            '/shorts\/([a-zA-Z0-9_-]{6,})/i',
        ];
        foreach ( $patterns as $p ) {
            if ( preg_match( $p, $url, $m ) ) return $m[1];
        }
        return '';
    }

    private function mime_by_ext( $ext ) {
        $map = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
        ];
        $ext = strtolower( $ext );
        return isset( $map[ $ext ] ) ? $map[ $ext ] : 'image/jpeg';
    }

    public function prepend_video_embed( $content ) {
        if ( is_admin() || ! is_singular() ) return $content;

        $post_id = get_the_ID();
        $yt = get_post_meta( $post_id, self::META_YT, true );
        if ( $yt ) {
            $embed = wp_oembed_get( $yt );
            if ( $embed && strpos( $content, 'youtube.com/embed' ) === false ) {
                $content = $embed . "\n\n" . $content;
            }
        }
        return $content;
    }
}

new PlugeThumb();
