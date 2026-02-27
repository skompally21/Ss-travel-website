<?php
/**
 * Plugin Name: SS Travel - Enquiry Form
 * Description: Simple enquiry form for SS Travel with email notification, optional WhatsApp redirect, and admin listing.
 * Version: 1.0.1
 * Author: SS Travel
 * Text Domain: ss-travel-enquiry
 */

if (!defined('ABSPATH')) exit;

class SS_Travel_Enquiry_Plugin {
    private $option_group = 'sst_enquiry_options';
    private $option_name  = 'sst_enquiry_settings';

    public function __construct() {
        // Admin settings
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        // Register CPT for storing enquiries
        add_action('init', [$this, 'register_cpt']);

        // Shortcode + assets
        add_shortcode('ss_travel_enquiry', [$this, 'render_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Handle form submission
        add_action('init', [$this, 'handle_submission']);
    }

    public static function activate() {
        $self = new self();
        $defaults = [
            'admin_email' => get_option('admin_email'),
            'whatsapp_number' => '919642028381', // with country code
            'enable_whatsapp_redirect' => '1',
            'success_message' => 'Thank you! Your enquiry has been received. Our team will contact you shortly.',
            'email_subject' => 'New SS Travel Enquiry',
            'store_entries' => '1',
        ];
        if (!get_option($self->option_name)) {
            add_option($self->option_name, $defaults);
        }
        $self->register_cpt();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function admin_menu() {
        add_menu_page(
            'SS Travel Enquiries',
            'SS Enquiries',
            'manage_options',
            'ss-travel-enquiry',
            [$this, 'settings_page'],
            'dashicons-email-alt2',
            26
        );
    }

    public function register_settings() {
        register_setting($this->option_group, $this->option_name, [$this, 'sanitize']);
        add_settings_section('sst_main', 'General Settings', null, 'ss-travel-enquiry');

        add_settings_field('admin_email', 'Admin Email', [$this, 'field_admin_email'], 'ss-travel-enquiry', 'sst_main');
        add_settings_field('whatsapp_number', 'WhatsApp Number (with country code)', [$this, 'field_whatsapp'], 'ss-travel-enquiry', 'sst_main');
        add_settings_field('enable_whatsapp_redirect', 'Redirect to WhatsApp after submission', [$this, 'field_enable_whatsapp'], 'ss-travel-enquiry', 'sst_main');
        add_settings_field('store_entries', 'Store enquiries in admin', [$this, 'field_store_entries'], 'ss-travel-enquiry', 'sst_main');
        add_settings_field('email_subject', 'Email Subject', [$this, 'field_subject'], 'ss-travel-enquiry', 'sst_main');
        add_settings_field('success_message', 'Success Message (shown to user)', [$this, 'field_success'], 'ss-travel-enquiry', 'sst_main');
    }

    public function sanitize($input) {
        $out = [];
        $out['admin_email'] = sanitize_email($input['admin_email'] ?? '');
        $out['whatsapp_number'] = preg_replace('/\D+/', '', $input['whatsapp_number'] ?? '');
        $out['enable_whatsapp_redirect'] = !empty($input['enable_whatsapp_redirect']) ? '1' : '0';
        $out['store_entries'] = !empty($input['store_entries']) ? '1' : '0';
        $out['email_subject'] = sanitize_text_field($input['email_subject'] ?? 'New SS Travel Enquiry');
        $out['success_message'] = sanitize_text_field($input['success_message'] ?? 'Thank you! We will contact you shortly.');
        return $out;
    }

    public function field_admin_email() {
        $opt = get_option($this->option_name);
        echo '<input type="email" name="'.$this->option_name.'[admin_email]" value="'.esc_attr($opt['admin_email']).'" class="regular-text" />';
    }
    public function field_whatsapp() {
        $opt = get_option($this->option_name);
        echo '<input type="text" name="'.$this->option_name.'[whatsapp_number]" value="'.esc_attr($opt['whatsapp_number']).'" class="regular-text" />';
        echo '<p class="description">Example: 919642028381</p>';
    }
    public function field_enable_whatsapp() {
        $opt = get_option($this->option_name);
        $checked = checked($opt['enable_whatsapp_redirect'] ?? '0', '1', false);
        echo '<label><input type="checkbox" name="'.$this->option_name.'[enable_whatsapp_redirect]" value="1" '.$checked.' /> Enable</label>';
    }
    public function field_store_entries() {
        $opt = get_option($this->option_name);
        $checked = checked($opt['store_entries'] ?? '1', '1', false);
        echo '<label><input type="checkbox" name="'.$this->option_name.'[store_entries]" value="1" '.$checked.' /> Enable</label>';
    }
    public function field_subject() {
        $opt = get_option($this->option_name);
        echo '<input type="text" name="'.$this->option_name.'[email_subject]" value="'.esc_attr($opt['email_subject']).'" class="regular-text" />';
    }
    public function field_success() {
        $opt = get_option($this->option_name);
        echo '<input type="text" name="'.$this->option_name.'[success_message]" value="'.esc_attr($opt['success_message']).'" class="regular-text" />';
    }

    public function settings_page() {
        echo '<div class="wrap">';
        echo '<h1>SS Travel - Enquiry Form Settings</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields($this->option_group);
        do_settings_sections('ss-travel-enquiry');
        submit_button();
        echo '</form>';
        echo '<hr/>';
        echo '<h2>How to use</h2>';
        echo '<p>Add this shortcode to any page: <code>[ss_travel_enquiry]</code></p>';
        echo '<p>View enquiries in: <strong>Enquiries</strong> (left admin menu).</p>';
        echo '</div>';
    }

    public function register_cpt() {
        $labels = [
            'name' => 'Enquiries',
            'singular_name' => 'Enquiry',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Enquiry',
            'edit_item' => 'Edit Enquiry',
            'new_item' => 'New Enquiry',
            'view_item' => 'View Enquiry',
            'search_items' => 'Search Enquiries',
            'not_found' => 'No enquiries found',
            'menu_name' => 'Enquiries'
        ];
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'supports' => ['title', 'editor', 'custom-fields'],
            'menu_icon' => 'dashicons-feedback'
        ];
        register_post_type('sst_enquiry', $args);
    }

    public function enqueue_assets() {
        $css = ".sst-form { max-width: 720px; margin: 20px auto; padding: 16px; border: 1px solid #eee; border-radius: 8px; background:#fff; }\n"
             . ".sst-form h2 { margin-top:0; }\n"
             . ".sst-form label { display:block; margin:10px 0 6px; font-weight:600; }\n"
             . ".sst-form input, .sst-form select, .sst-form textarea { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; }\n"
             . ".sst-row { display:flex; gap:12px; }\n"
             . ".sst-col { flex:1; }\n"
             . ".sst-btn { margin-top:14px; background:#1E73BE; color:#fff; border:none; padding:12px 16px; border-radius:6px; cursor:pointer; }\n"
             . ".sst-note { font-size:12px; color:#666; margin-top:8px; }\n"
             . ".sst-success { padding:14px; background:#f0fff5; border:1px solid #b2f2bb; color:#2b8a3e; border-radius:6px; }\n"
             . ".sst-error { padding:14px; background:#fff5f5; border:1px solid #ffa8a8; color:#c92a2a; border-radius:6px; }\n"
             . ".sst-hidden { display:none !important; }\n";
        wp_register_style('sst-enq', false);
        wp_enqueue_style('sst-enq');
        wp_add_inline_style('sst-enq', $css);
    }

    private function destinations() {
        return [
            'Tirupati',
            'Arunachalam',
            'Goa',
            'Kerala',
            'Ooty',
            'Munnar',
            'Coimbatore',
            'Vizag',
            'Puri'
        ];
    }

    public function render_form() {
        $nonce = wp_create_nonce('sst_enquiry_nonce');
        ob_start();
        ?>
        <form method="post" action="" class="sst-form">
            <input type="hidden" name="sst_enquiry_submit" value="1">
            <input type="hidden" name="sst_enquiry_nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="text" name="website" class="sst-hidden" tabindex="-1" autocomplete="off" />

            <h2>Enquiry Form</h2>

            <label>Full Name *</label>
            <input type="text" name="sst_name" required>

            <div class="sst-row">
                <div class="sst-col">
                    <label>Mobile Number *</label>
                    <input type="tel" name="sst_phone" pattern="[0-9]{10,15}" required>
                </div>
                <div class="sst-col">
                    <label>Email *</label>
                    <input type="email" name="sst_email" required>
                </div>
            </div>

            <label>Destination *</label>
            <select name="sst_destination" required>
                <option value="">-- Select Destination --</option>
                <?php foreach ($this->destinations() as $d): ?>
                    <option value="<?php echo esc_attr($d); ?>"><?php echo esc_html($d); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="sst-row">
                <div class="sst-col">
                    <label>Travel Date *</label>
                    <input type="date" name="sst_date" required>
                </div>
                <div class="sst-col">
                    <label>Number of Travelers *</label>
                    <input type="number" name="sst_travelers" min="1" value="1" required>
                </div>
            </div>

            <label>Pickup Location</label>
            <input type="text" name="sst_pickup" placeholder="City / Landmark">

            <label>Message</label>
            <textarea name="sst_message" rows="4" placeholder="Any special request?"></textarea>

            <button type="submit" class="sst-btn">Send Enquiry</button>
            <p class="sst-note">By submitting, you agree to be contacted by SS Travel by phone/WhatsApp/email.</p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handle_submission() {
        if (!isset($_POST['sst_enquiry_submit'])) return;

        // Basic anti-bot
        if (!empty($_POST['website'])) return;

        if (!isset($_POST['sst_enquiry_nonce']) || !wp_verify_nonce($_POST['sst_enquiry_nonce'], 'sst_enquiry_nonce')) {
            add_action('wp', function() {
                add_action('the_content', function($content) {
                    return '<div class="sst-error">Security check failed. Please try again.</div>' . $content;
                });
            });
            return;
        }

        // Sanitize fields
        $name   = sanitize_text_field($_POST['sst_name'] ?? '');
        $phone  = preg_replace('/\D+/', '', $_POST['sst_phone'] ?? '');
        $email  = sanitize_email($_POST['sst_email'] ?? '');
        $dest   = sanitize_text_field($_POST['sst_destination'] ?? '');
        $date   = sanitize_text_field($_POST['sst_date'] ?? '');
        $trav   = intval($_POST['sst_travelers'] ?? 1);
        $pickup = sanitize_text_field($_POST['sst_pickup'] ?? '');
        $msg    = sanitize_textarea_field($_POST['sst_message'] ?? '');

        if (!$name || !$email || !$phone || !$dest || !$date || $trav < 1) {
            add_action('wp', function() {
                add_action('the_content', function($content) {
                    return '<div class="sst-error">Please fill all required fields correctly.</div>' . $content;
                });
            });
            return;
        }

        $settings = get_option($this->option_name);
        $admin_email = $settings['admin_email'] ?? get_option('admin_email');

        // Store in CPT if enabled
        if (!empty($settings['store_entries']) && $settings['store_entries'] === '1') {
            $post_id = wp_insert_post([
                'post_type' => 'sst_enquiry',
                'post_title' => sprintf('%s - %s (%s)', $name, $dest, $date),
                'post_content' => $msg,
                'post_status' => 'publish'
            ]);
            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, 'name', $name);
                update_post_meta($post_id, 'phone', $phone);
                update_post_meta($post_id, 'email', $email);
                update_post_meta($post_id, 'destination', $dest);
                update_post_meta($post_id, 'date', $date);
                update_post_meta($post_id, 'travelers', $trav);
                update_post_meta($post_id, 'pickup', $pickup);
            }
        }

        // Email notification to admin
        $subject = $settings['email_subject'] ?? 'New SS Travel Enquiry';
        $body  = "New enquiry received:\n\n";
        $body .= "Name: $name\n";
        $body .= "Phone: $phone\n";
        $body .= "Email: $email\n";
        $body .= "Destination: $dest\n";
        $body .= "Travel Date: $date\n";
        $body .= "Travelers: $trav\n";
        $body .= "Pickup: $pickup\n";
        $body .= "Message:\n$msg\n";
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        wp_mail($admin_email, $subject, $body, $headers);

        // Success UI + optional WhatsApp redirect
        $success = esc_html($settings['success_message'] ?? 'Thank you! Your enquiry has been received.');
        $wa_enabled = !empty($settings['enable_whatsapp_redirect']) && $settings['enable_whatsapp_redirect'] === '1';
        $wa_number  = preg_replace('/\D+/', '', $settings['whatsapp_number'] ?? '');
        $wa_text    = rawurlencode("Hi SS Travel, I want to enquire about {$dest} for {$date}. Name: {$name}, Phone: {$phone}.");

        add_action('wp', function() use ($success, $wa_enabled, $wa_number, $wa_text) {
            add_action('the_content', function($content) use ($success, $wa_enabled, $wa_number, $wa_text) {
                $html = '<div class="sst-success">'.$success.'</div>';
                if ($wa_enabled && $wa_number) {
                    $link = "https://wa.me/{$wa_number}?text={$wa_text}";
                    $html .= '<p><a class="sst-btn" href="'.esc_url($link).'" target="_blank" rel="noopener">Continue on WhatsApp</a></p>';
                }
                return $html . $content;
            });
        });
    }
}

new SS_Travel_Enquiry_Plugin();

register_activation_hook(__FILE__, ['SS_Travel_Enquiry_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SS_Travel_Enquiry_Plugin', 'deactivate']);
