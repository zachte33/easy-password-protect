<?php
/**
 * Plugin Name: Easy Password Protect
 * Description: Simple drag-and-drop password protection for WordPress pages with multiple password groups
 * Version: 1.0
 * Author: Zach Elkins
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EasyPasswordProtect {
    
    private $option_name = 'easy_password_protect_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_save_password_settings', array($this, 'save_password_settings'));
        add_action('template_redirect', array($this, 'check_password_protection'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Easy Password Protect',
            'Password Protect',
            'manage_options',
            'easy-password-protect',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_easy-password-protect') {
            return;
        }
        
        wp_enqueue_script('jquery-ui-sortable');
    }
    
    public function admin_page() {
        $settings = get_option($this->option_name, array());
        $all_pages = get_pages();
        
        // Initialize default settings
        if (empty($settings)) {
            $settings = array(
                'group1' => array('password' => '', 'pages' => array(), 'gradient' => 'default'),
                'group2' => array('password' => '', 'pages' => array(), 'gradient' => 'default'),
                'group3' => array('password' => '', 'pages' => array(), 'gradient' => 'default')
            );
        }
        
        // Ensure gradient setting exists for existing installations
        foreach ($settings as $key => $group) {
            if (!isset($group['gradient'])) {
                $settings[$key]['gradient'] = 'default';
            }
        }
        
        // Get all currently protected pages across all groups
        $all_protected_pages = array();
        foreach ($settings as $group_data) {
            $all_protected_pages = array_merge($all_protected_pages, $group_data['pages'] ?? array());
        }
        
        // Get WordPress posts page setting
        $posts_page_id = (int) get_option('page_for_posts', 0);
        
        // Define gradient options
        $gradient_options = array(
            'default' => array(
                'name' => 'Ocean Breeze (Default)',
                'css' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'description' => 'Cool blue to purple - professional and calming'
            ),
            'sunset' => array(
                'name' => 'Sunset Vibes',
                'css' => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%)',
                'description' => 'Warm pink to coral - vibrant and energetic'
            ),
            'forest' => array(
                'name' => 'Forest Mystique',
                'css' => 'linear-gradient(135deg, #134e5e 0%, #71b280 100%)',
                'description' => 'Deep teal to sage green - natural and sophisticated'
            ),
            'cosmic' => array(
                'name' => 'Cosmic Dreams',
                'css' => 'linear-gradient(135deg, #2c1810 0%, #8b4d77 50%, #d4af37 100%)',
                'description' => 'Dark brown to purple to gold - mysterious and luxurious'
            ),
            'fire' => array(
                'name' => 'Fire Storm',
                'css' => 'linear-gradient(135deg, #ff4e50 0%, #f9d423 100%)',
                'description' => 'Red to bright yellow - bold and attention-grabbing'
            )
        );
        
        ?>
        <div class="wrap">
            <h1>🔒 Easy Password Protect</h1>
            <p>Drag and drop pages between the lists to set up password protection. You can create up to 3 different password groups.</p>
            
            <div id="password-protect-container">
                
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="password-group" data-group="group<?php echo $i; ?>">
                    <h2>Password Group <?php echo $i; ?></h2>
                    
                    <div class="password-input-section">
                        <label for="password-<?php echo $i; ?>">Password:</label>
                        <input type="text" id="password-<?php echo $i; ?>" 
                               value="<?php echo esc_attr($settings["group{$i}"]['password'] ?? ''); ?>" 
                               placeholder="Enter password for this group">
                        <span class="password-info">(Leave empty to disable this group)</span>
                    </div>
                    
                    <div class="gradient-selection-section">
                        <h4>🎨 Choose Password Page Style:</h4>
                        <div class="gradient-options">
                            <?php foreach ($gradient_options as $key => $gradient): ?>
                                <label class="gradient-option">
                                    <input type="radio" name="gradient-<?php echo $i; ?>" value="<?php echo $key; ?>" 
                                           <?php checked($settings["group{$i}"]['gradient'] ?? 'default', $key); ?>>
                                    <div class="gradient-preview" style="background: <?php echo $gradient['css']; ?>">
                                        <div class="gradient-info">
                                            <strong><?php echo esc_html($gradient['name']); ?></strong>
                                            <small><?php echo esc_html($gradient['description']); ?></small>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="drag-drop-section">
                        <div class="pages-container">
                            <div class="available-pages">
                                <h3>📄 Available Pages</h3>
                                <div class="pages-list sortable" id="available-<?php echo $i; ?>" data-type="available">
                                    <?php 
                                    foreach ($all_pages as $page): 
                                        // Skip if page is already protected in any group
                                        if (in_array($page->ID, $all_protected_pages)) {
                                            continue;
                                        }
                                        
                                        // Check if this is the WordPress posts page
                                        $is_posts_page = ($page->ID === $posts_page_id && $posts_page_id > 0);
                                        
                                        if ($is_posts_page): ?>
                                            <div class="page-item not-eligible" data-page-id="<?php echo esc_attr($page->ID); ?>">
                                                ❌ <?php echo esc_html($page->post_title); ?> (WordPress Posts Page - not eligible)
                                                <small>(ID: <?php echo $page->ID; ?>)</small>
                                            </div>
                                        <?php else: ?>
                                            <div class="page-item" data-page-id="<?php echo esc_attr($page->ID); ?>">
                                                <?php echo esc_html($page->post_title); ?>
                                                <small>(ID: <?php echo $page->ID; ?>)</small>
                                            </div>
                                        <?php endif; 
                                    endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="protected-pages">
                                <h3>🔐 Password Protected</h3>
                                <div class="pages-list sortable" id="protected-<?php echo $i; ?>" data-type="protected">
                                    <?php
                                    $protected_pages = $settings["group{$i}"]['pages'] ?? array();
                                    foreach ($protected_pages as $page_id) {
                                        $page = get_post($page_id);
                                        if ($page && $page->post_type === 'page') {
                                            echo '<div class="page-item" data-page-id="' . esc_attr($page->ID) . '">';
                                            echo esc_html($page->post_title);
                                            echo '<small>(ID: ' . $page->ID . ')</small>';
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($i === 1): ?>
                        <div class="instructions">
                            <p><strong>How to use:</strong> Drag pages from "Available Pages" to "Password Protected" to protect them with this group's password. Drag back to make them public again.</p>
                            <p><strong>Note:</strong> This plugin only works with WordPress Pages, not blog posts or the blog page.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="group-save-section">
                        <button class="save-settings-btn button button-primary">💾 Save All Settings</button>
                        <div class="save-status-inline"></div>
                    </div>
                </div>
                <?php endfor; ?>
                
                <div class="save-section">
                    <button id="save-settings" class="button button-primary button-large">💾 Save All Settings</button>
                    <div id="save-status"></div>
                </div>
            </div>
        </div>
        
        <style>
        #password-protect-container {
            max-width: 1200px;
        }
        
        .password-group {
            background: #fff;
            padding: 25px;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #0073aa;
        }
        
        .password-group h2 {
            margin-top: 0;
            color: #0073aa;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .password-input-section {
            margin-bottom: 25px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .password-input-section label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
            color: #333;
        }
        
        .password-input-section input {
            padding: 10px 15px;
            font-size: 14px;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .password-info {
            color: #666;
            font-size: 12px;
            margin-left: 10px;
            font-style: italic;
        }
        
        .drag-drop-section {
            border: 2px dashed #0073aa;
            padding: 20px;
            border-radius: 8px;
            background: #f0f8ff;
        }
        
        .pages-container {
            display: flex;
            gap: 30px;
        }
        
        .available-pages, .protected-pages {
            flex: 1;
        }
        
        .available-pages h3 {
            color: #666;
            margin-top: 0;
            font-size: 16px;
        }
        
        .protected-pages h3 {
            color: #d63638;
            margin-top: 0;
            font-size: 16px;
        }
        
        .pages-list {
            min-height: 180px;
            border: 2px solid #ddd;
            padding: 15px;
            background: white;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .pages-list:empty::after {
            content: "Drop pages here";
            color: #999;
            font-style: italic;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 150px;
        }
        
        .page-item {
            background: linear-gradient(135deg, #0073aa 0%, #005177 100%);
            color: white;
            padding: 12px 16px;
            margin: 8px 0;
            border-radius: 6px;
            cursor: move;
            user-select: none;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .page-item small {
            opacity: 0.8;
            font-size: 11px;
        }
        
        .page-item:hover {
            background: linear-gradient(135deg, #005177 0%, #003d5c 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .page-item.not-eligible {
            background: #6c757d !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }
        
        .page-item.not-eligible:hover {
            background: #6c757d !important;
            transform: none !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }
        
        .sortable {
            transition: background-color 0.3s ease;
        }
        
        .ui-sortable-helper {
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            transform: rotate(3deg) scale(1.05);
            z-index: 1000;
        }
        
        .ui-sortable-placeholder {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 2px dashed #0073aa;
            height: 50px;
            border-radius: 6px;
            margin: 8px 0;
        }
        
        .ui-sortable-over {
            background-color: #f0f8ff;
            border-color: #0073aa;
        }
        
        .save-section {
            text-align: center;
            padding: 40px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #46b450;
        }
        
        #save-status {
            margin-top: 15px;
            font-weight: bold;
            padding: 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .success {
            color: #46b450;
            background: #f0f9f0;
            border: 1px solid #46b450;
        }
        
        .error {
            color: #d63638;
            background: #fdf0f0;
            border: 1px solid #d63638;
        }
        
        .instructions {
            margin-top: 15px;
            padding: 12px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            color: #856404;
            font-size: 13px;
        }
        
        .gradient-selection-section {
            margin-bottom: 25px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .gradient-selection-section h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }
        
        .gradient-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
        }
        
        .gradient-option {
            position: relative;
            cursor: pointer;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }
        
        .gradient-option:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .gradient-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        
        .gradient-option input[type="radio"]:checked + .gradient-preview {
            /* No border changes here anymore */
        }
        
        .gradient-option.selected {
            border-color: #0073aa;
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.3);
        }
        
        .gradient-preview {
            height: 120px;
            display: flex;
            align-items: flex-end;
            padding: 15px;
            border-radius: 3px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .gradient-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.1) 50%, transparent 100%);
            pointer-events: none;
        }
        
        .gradient-info {
            position: relative;
            z-index: 2;
            color: white;
            text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }
        
        .gradient-info strong {
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .gradient-info small {
            display: block;
            font-size: 12px;
            opacity: 0.9;
            line-height: 1.3;
        }
        
        .gradient-option input[type="radio"]:checked + .gradient-preview .gradient-info::after {
            content: '✓ Selected';
            position: absolute;
            top: -25px;
            right: -10px;
            background: #0073aa;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .group-save-section {
            text-align: center;
            padding: 15px 20px;
            background: #f0f8ff;
            border-radius: 6px;
            border: 1px solid #0073aa;
            margin-top: 20px;
        }
        
        .save-settings-btn {
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 6px;
        }
        
        .save-status-inline {
            display: block;
            margin-top: 10px;
            font-weight: bold;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .pages-container {
                flex-direction: column;
                gap: 20px;
            }
            
            .password-input-section input {
                width: 100%;
                max-width: 300px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle gradient selection styling
            $('input[type="radio"][name^="gradient-"]').change(function() {
                // Remove selected class from all gradient options in this group
                $(this).closest('.gradient-options').find('.gradient-option').removeClass('selected');
                // Add selected class to the chosen option
                $(this).closest('.gradient-option').addClass('selected');
            });
            
            // Set initial selected state
            $('input[type="radio"][name^="gradient-"]:checked').each(function() {
                $(this).closest('.gradient-option').addClass('selected');
            });
            
            // Initialize sortable
            $('.sortable').sortable({
                connectWith: '.sortable',
                placeholder: 'ui-sortable-placeholder',
                revert: 100,
                tolerance: 'pointer',
                cancel: '.not-eligible',
                
                receive: function(event, ui) {
                    var pageId = ui.item.data('page-id');
                    var targetType = $(this).data('type');
                    
                    if (targetType === 'protected') {
                        $('.sortable').not(this).find('[data-page-id="' + pageId + '"]').remove();
                    } else if (targetType === 'available') {
                        var pageHtml = ui.item[0].outerHTML;
                        $('.sortable[data-type="available"]').not(this).each(function() {
                            if ($(this).find('[data-page-id="' + pageId + '"]').length === 0) {
                                $(this).append(pageHtml);
                            }
                        });
                        $('.sortable[data-type="protected"]').find('[data-page-id="' + pageId + '"]').remove();
                    }
                }
            });
            
            // Save function - works for all save buttons
            $('.save-settings-btn, #save-settings').click(function() {
                var $button = $(this);
                var $status = $button.hasClass('save-settings-btn') ? $button.siblings('.save-status-inline') : $('#save-status');
                
                // Disable all save buttons during save
                $('.save-settings-btn, #save-settings').prop('disabled', true).text('Saving...');
                
                var settings = {
                    group1: { password: $('#password-1').val().trim(), pages: [], gradient: $('input[name="gradient-1"]:checked').val() },
                    group2: { password: $('#password-2').val().trim(), pages: [], gradient: $('input[name="gradient-2"]:checked').val() },
                    group3: { password: $('#password-3').val().trim(), pages: [], gradient: $('input[name="gradient-3"]:checked').val() }
                };
                
                for (var i = 1; i <= 3; i++) {
                    $('#protected-' + i + ' .page-item').each(function() {
                        settings['group' + i].pages.push(parseInt($(this).data('page-id')));
                    });
                }
                
                // Clear all status messages
                $('.save-status-inline, #save-status').html('Saving...').removeClass('success error');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'save_password_settings',
                        settings: settings,
                        nonce: '<?php echo wp_create_nonce('easy_password_protect_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.save-status-inline, #save-status').html('✅ Settings saved!').addClass('success');
                        } else {
                            $('.save-status-inline, #save-status').html('❌ Error saving settings.').addClass('error');
                        }
                    },
                    error: function() {
                        $('.save-status-inline, #save-status').html('❌ Error saving settings.').addClass('error');
                    },
                    complete: function() {
                        // Re-enable all save buttons
                        $('.save-settings-btn').prop('disabled', false).text('💾 Save All Settings');
                        $('#save-settings').prop('disabled', false).text('💾 Save All Settings');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function save_password_settings() {
        // Security checks
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'easy_password_protect_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        if (!isset($_POST['settings']) || !is_array($_POST['settings'])) {
            wp_send_json_error(array('message' => 'Invalid settings data'));
            return;
        }
        
        // Sanitize and validate settings
        $settings = array();
        foreach ($_POST['settings'] as $group_key => $group_data) {
            if (!in_array($group_key, array('group1', 'group2', 'group3'))) {
                continue;
            }
            
            $settings[$group_key] = array(
                'password' => isset($group_data['password']) ? sanitize_text_field($group_data['password']) : '',
                'pages' => array(),
                'gradient' => isset($group_data['gradient']) ? sanitize_text_field($group_data['gradient']) : 'default'
            );
            
            // Validate and sanitize page IDs
            if (isset($group_data['pages']) && is_array($group_data['pages'])) {
                foreach ($group_data['pages'] as $page_id) {
                    $sanitized_id = absint($page_id);
                    if ($sanitized_id > 0) {
                        $settings[$group_key]['pages'][] = $sanitized_id;
                    }
                }
            }
            
            // Validate gradient option
            $valid_gradients = array('default', 'sunset', 'forest', 'cosmic', 'fire');
            if (!in_array($settings[$group_key]['gradient'], $valid_gradients)) {
                $settings[$group_key]['gradient'] = 'default';
            }
        }
        
        // Update option with sanitized data
        $updated = update_option($this->option_name, $settings);
        
        if ($updated || get_option($this->option_name) === $settings) {
            wp_send_json_success(array('message' => 'Settings saved successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save settings'));
        }
    }
    
    public function check_password_protection() {
        // Only run on frontend pages
        if (is_admin() || !is_page()) {
            return;
        }
        
        $current_page_id = get_the_ID();
        if (!$current_page_id) {
            return;
        }
        
        $settings = get_option($this->option_name, array());
        if (empty($settings)) {
            return;
        }
        
        // Find which group this page belongs to
        $password_group = null;
        foreach ($settings as $group_data) {
            if (isset($group_data['pages']) && is_array($group_data['pages'])) {
                if (in_array($current_page_id, $group_data['pages']) && !empty($group_data['password'])) {
                    $password_group = $group_data;
                    break;
                }
            }
        }
        
        if (!$password_group) {
            return;
        }
        
        $required_password = $password_group['password'];
        $selected_gradient = $password_group['gradient'] ?? 'default';
        $cookie_name = 'epp_' . md5($required_password . $current_page_id);
        
        // Handle password submission
        if (isset($_POST['page_password'])) {
            if ($_POST['page_password'] === $required_password) {
                // Set secure cookie for 24 hours
                $cookie_value = wp_hash_password($required_password);
                $cookie_expire = time() + DAY_IN_SECONDS;
                $cookie_path = COOKIEPATH ? COOKIEPATH : '/';
                $cookie_domain = COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
                
                setcookie($cookie_name, $cookie_value, $cookie_expire, $cookie_path, $cookie_domain, is_ssl(), true);
                
                wp_safe_redirect(get_permalink($current_page_id));
                exit;
            } else {
                $this->show_password_form(true, $selected_gradient);
                exit;
            }
        }
        
        // Check for valid cookie
        if (isset($_COOKIE[$cookie_name]) && wp_check_password($required_password, $_COOKIE[$cookie_name])) {
            return;
        }
        
        // Show password form
        $this->show_password_form(false, $selected_gradient);
        exit;
    }
    
    private function show_password_form($wrong_password = false, $gradient = 'default') {
        $page_title = get_the_title();
        
        // Define gradient styles
        $gradient_styles = array(
            'default' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'sunset' => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%)',
            'forest' => 'linear-gradient(135deg, #134e5e 0%, #71b280 100%)',
            'cosmic' => 'linear-gradient(135deg, #2c1810 0%, #8b4d77 50%, #d4af37 100%)',
            'fire' => 'linear-gradient(135deg, #ff4e50 0%, #f9d423 100%)'
        );
        
        // Define button styles for each gradient
        $button_styles = array(
            'default' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'sunset' => 'linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%)',
            'forest' => 'linear-gradient(135deg, #00b894 0%, #00a085 100%)',
            'cosmic' => 'linear-gradient(135deg, #8b4d77 0%, #d4af37 100%)',
            'fire' => 'linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%)'
        );
        
        $background_style = $gradient_styles[$gradient] ?? $gradient_styles['default'];
        $button_style = $button_styles[$gradient] ?? $button_styles['default'];
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Password Protected - <?php echo esc_html($page_title); ?></title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: <?php echo $background_style; ?>;
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    animation: gradientShift 8s ease-in-out infinite;
                }
                
                @keyframes gradientShift {
                    0%, 100% { background-size: 100% 100%; }
                    50% { background-size: 120% 120%; }
                }
                
                .password-form { 
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(10px);
                    padding: 50px;
                    border-radius: 20px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.1);
                    max-width: 420px;
                    width: 90%;
                    text-align: center;
                    animation: formBounce 4s ease-in-out infinite;
                }
                
                @keyframes formBounce {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                }
                .password-form h2 {
                    margin-top: 0;
                    color: #333;
                    font-size: 24px;
                    margin-bottom: 10px;
                }
                .password-form p {
                    color: #666;
                    margin-bottom: 25px;
                    line-height: 1.5;
                }
                .error-message {
                    color: #d63638;
                    background: #fdf0f0;
                    padding: 10px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                    border: 1px solid #d63638;
                }
                input[type="password"] { 
                    width: 100%;
                    padding: 12px 15px;
                    font-size: 16px;
                    border: 2px solid #e1e1e1;
                    border-radius: 8px;
                    box-sizing: border-box;
                    margin-bottom: 20px;
                    transition: border-color 0.3s ease;
                }
                input[type="password"]:focus {
                    outline: none;
                    border-color: #667eea;
                }
                input[type="submit"] { 
                    width: 100%;
                    padding: 15px;
                    font-size: 18px;
                    background: <?php echo $button_style; ?>;
                    color: white;
                    border: none;
                    border-radius: 12px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                input[type="submit"]:hover { 
                    transform: translateY(-3px);
                    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                    filter: brightness(1.1);
                }
                .site-title {
                    font-size: 14px;
                    color: #999;
                    margin-bottom: 20px;
                }
                .page-title {
                    font-size: 16px;
                    color: #555;
                    margin-bottom: 15px;
                    font-weight: normal;
                }
            </style>
        </head>
        <body>
            <div class="password-form">
                <div class="site-title"><?php bloginfo('name'); ?></div>
                <?php if ($wrong_password): ?>
                    <div class="error-message">
                        ❌ Incorrect password. Please try again.
                    </div>
                <?php endif; ?>
                <h2>🔒 Password Required</h2>
                <div class="page-title">Accessing: <strong><?php echo esc_html($page_title); ?></strong></div>
                <p>This content is password protected. Please enter the password to view this page.</p>
                <form method="post">
                    <input type="password" name="page_password" placeholder="Enter password" required autofocus>
                    <input type="submit" value="Access Page">
                </form>
            </div>
        </body>
        </html>
        <?php
    }
}

// Initialize the plugin
new EasyPasswordProtect();