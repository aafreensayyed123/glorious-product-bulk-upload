<?php
/**
 * Plugin Name: Bulk CSV Importer with Datasheet Upload
 * Description: A plugin to bulk upload entries from a CSV file into the "products" custom post type, including uploading images and datasheets.
 * Version: 1.3
 * Author: Aafreen Sayyed
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BulkCSVImporter
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_process_csv_upload', [$this, 'process_csv_upload']);
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'tools.php',
            'Bulk CSV Importer',
            'Bulk CSV Importer',
            'manage_options',
            'bulk-csv-importer',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Bulk CSV Importer</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="process_csv_upload">
                <?php wp_nonce_field('bulk_csv_importer_nonce', 'bulk_csv_importer_nonce_field'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="csv_file">Upload CSV File</label>
                        </th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Upload and Import'); ?>
            </form>
        </div>
        <?php
    }

    public function process_csv_upload()
    {
        if (
            !isset($_POST['bulk_csv_importer_nonce_field']) ||
            !wp_verify_nonce($_POST['bulk_csv_importer_nonce_field'], 'bulk_csv_importer_nonce')
        ) {
            wp_die('Nonce verification failed.');
        }

        if (!isset($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name'])) {
            wp_die('No file uploaded.');
        }

        $file = $_FILES['csv_file'];

        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'csv') {
            wp_die('Invalid file type. Please upload a CSV file.');
        }

        $file_path = $file['tmp_name'];
        if (($handle = fopen($file_path, 'r')) === false) {
            wp_die('Unable to open the uploaded file.');
        }

        $headers = fgetcsv($handle); // Read the header row
        if (!$headers) {
            wp_die('Invalid CSV file format.');
        }

        $row_count = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $data);

            $post_data = array(
                'post_title' => $row['item-name'] ?? 'Untitled Product',
                'post_status' => 'publish',
                'post_type' => 'products',
            );

            $post_id = wp_insert_post($post_data);

            if (!is_wp_error($post_id)) {
                foreach ($row as $meta_key => $meta_value) {
                    if (!empty($meta_value)) {
                        // Handle image fields
                        if ($meta_key === 'featured-image') {
                            $image_id = $this->upload_image_from_url($meta_value, $post_id);
                            if ($image_id) {
                                set_post_thumbnail($post_id, $image_id);
                            }
                        }
                        // Handle datasheet files
                        elseif ($meta_key === 'datesheets') {
                            // Upload the file from the URL and associate it with the post
                            $this->handle_datasheet_upload($meta_value, $post_id);
                        } else {
                            // Save other meta fields
                            update_post_meta($post_id, sanitize_key($meta_key), sanitize_text_field($meta_value));
                        }
                    }
                }
            }

            $row_count++;
        }

        fclose($handle);

        wp_redirect(admin_url('tools.php?page=bulk-csv-importer&rows_imported=' . $row_count));
        exit;
    }


    /**
     * Upload an image from a URL and attach it to the given post.
     *
     * @param string $image_url The URL of the image to upload.
     * @param int    $post_id   The ID of the post to attach the image to.
     *
     * @return int|false The attachment ID on success, or false on failure.
     */
    private function upload_image_from_url($image_url, $post_id)
    {
        $image_data = wp_remote_get($image_url);

        if (is_wp_error($image_data) || wp_remote_retrieve_response_code($image_data) !== 200) {
            return false;
        }

        $image_contents = wp_remote_retrieve_body($image_data);
        $image_type = wp_remote_retrieve_header($image_data, 'content-type');

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($image_type, $allowed_types)) {
            return false;
        }

        $file_extension = substr($image_type, strpos($image_type, '/') + 1);
        $filename = 'image-' . wp_generate_password(8, false) . '.' . $file_extension;

        $upload = wp_upload_bits($filename, null, $image_contents);
        if ($upload['error']) {
            return false;
        }

        $attachment = array(
            'post_mime_type' => $image_type,
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        return $attachment_id;
    }

    /**
     * Upload a file (e.g., PDF datasheet) from a URL and attach it to the given post.
     *
     * @param string $file_url The URL of the file to upload.
     * @param int    $post_id  The ID of the post to attach the file to.
     *
     * @return int|false The attachment ID on success, or false on failure.
     */
    /**
     * Upload a datasheet (PDF) from a URL and attach it to the given post.
     *
     * @param string $file_url The URL of the datasheet to upload.
     * @param int    $post_id  The ID of the post to attach the datasheet to.
     *
     * @return int|false The attachment ID on success, or false on failure.
     */
    // private function upload_file_from_url($file_url, $post_id)
    // {
    //     // Step 1: Fetch file from URL
    //     $response = wp_remote_get($file_url, ['timeout' => 15]); // Add a timeout for long downloads

    //     if (is_wp_error($response)) {
    //         error_log("Failed to fetch file from URL: " . $file_url);
    //         return false; // Log error if the request failed
    //     }

    //     if (wp_remote_retrieve_response_code($response) !== 200) {
    //         error_log("Invalid response code for file URL: " . $file_url);
    //         return false; // Log error if the file doesn't exist or the URL is invalid
    //     }

    //     // Step 2: Get file content and type
    //     $file_contents = wp_remote_retrieve_body($response);
    //     $content_type = wp_remote_retrieve_header($response, 'content-type');

    //     // Validate allowed file types
    //     $allowed_types = ['application/pdf']; // Add other MIME types if necessary
    //     if (!in_array($content_type, $allowed_types)) {
    //         error_log("Unsupported file type: " . $content_type . " for URL: " . $file_url);
    //         return false;
    //     }

    //     // Step 3: Generate a unique filename
    //     $file_extension = 'pdf'; // Default to .pdf for datasheets
    //     $filename = 'datasheet-' . wp_generate_password(8, false) . '.' . $file_extension;

    //     // Step 4: Save file to WordPress uploads directory
    //     $upload = wp_upload_bits($filename, null, $file_contents);

    //     if ($upload['error']) {
    //         error_log("Error uploading file: " . $upload['error']);
    //         return false;
    //     }

    //     // Step 5: Create attachment post in Media Library
    //     $attachment = [
    //         'post_mime_type' => $content_type,
    //         'post_title' => sanitize_file_name($filename),
    //         'post_content' => '',
    //         'post_status' => 'inherit',
    //     ];

    //     $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

    //     if (is_wp_error($attachment_id)) {
    //         error_log("Error inserting attachment for file: " . $upload['file']);
    //         return false;
    //     }

    //     // Step 6: Generate metadata and update attachment
    //     require_once ABSPATH . 'wp-admin/includes/image.php';
    //     $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    //     wp_update_attachment_metadata($attachment_id, $attachment_metadata);

    //     // Step 7: Save the attachment ID in post meta
    //     update_post_meta($post_id, 'datasheet', $attachment_id);

    //     return $attachment_id;
    // }

    /**
     * Upload a datasheet (PDF) from a URL and attach it to the given post.
     *
     * @param string $file_url The URL of the datasheet to upload.
     * @param int    $post_id  The ID of the post to attach the datasheet to.
     *
     * @return int|false The attachment ID on success, or false on failure.
     */
    /**
     * Handle the datasheet upload and associate it with the related post.
     *
     * @param string $file_url The URL of the datasheet to upload.
     * @param int    $post_id  The post ID to associate the datasheet with.
     *
     * @return void
     */
    private function handle_datasheet_upload($file_url, $post_id)
    {
        // Step 1: Check if the file URL is already in the Media Library
        global $wpdb;
        $attachment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
                $file_url
            )
        );

        // Step 2: If not found, upload the file to the Media Library
        if (!$attachment_id) {
            $attachment_id = $this->upload_file_to_media_library($file_url, $post_id);
            if (!$attachment_id) {
                error_log("Failed to upload datasheet from URL: $file_url");
                return;
            }
        }

        // Step 3: Get the URL of the uploaded file and store it in the meta field
        $file_url = wp_get_attachment_url($attachment_id);
        if ($file_url) {
            update_post_meta($post_id, 'datesheets', esc_url_raw($file_url));
        } else {
            error_log("Failed to retrieve URL for attachment ID: $attachment_id");
        }

        // Debug log
        error_log("Datasheet URL successfully associated with post ID: $post_id (URL: $file_url)");
    }




    /**
     * Upload a file to the Media Library from a URL.
     *
     * @param string $file_url The URL of the file to upload.
     * @param int    $post_id  The post ID to associate the file with (optional).
     *
     * @return int|false The attachment ID on success, or false on failure.
     */
    private function upload_file_to_media_library($file_url, $post_id = 0)
    {
        // Fetch the file contents from the URL
        $response = wp_remote_get($file_url, ['timeout' => 15]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log("Failed to fetch file from URL: $file_url");
            return false;
        }

        // Get the file contents and file name
        $file_contents = wp_remote_retrieve_body($response);
        $file_name = basename(parse_url($file_url, PHP_URL_PATH));
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $file_name;

        // Save the file to the uploads directory
        if (!file_put_contents($file_path, $file_contents)) {
            error_log("Failed to save file: $file_name");
            return false;
        }

        // Prepare the file for WordPress
        $filetype = wp_check_filetype($file_path, null);
        $attachment = [
            'guid' => $upload_dir['url'] . '/' . $file_name,
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($file_name),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        // Insert the file as an attachment in WordPress
        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log("Failed to insert attachment: $file_name");
            return false;
        }

        // Generate attachment metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        return $attachment_id;
    }

}

// Initialize the plugin
new BulkCSVImporter();