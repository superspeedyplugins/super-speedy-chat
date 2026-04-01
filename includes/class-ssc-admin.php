<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SSC_Admin' ) ) {

    class SSC_Admin {

        private $hook_suffix = '';

        public function init() {
            add_action( 'admin_menu', array( $this, 'add_menu' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        }

        public function add_menu() {
            $this->hook_suffix = add_submenu_page(
                'superspeedy',
                'Super Speedy Chat',
                'Super Speedy Chat',
                'manage_options',
                'ssc',
                array( $this, 'render_page' ),
                50
            );
        }

        public function enqueue_scripts( $hook ) {
            if ( $hook !== $this->hook_suffix ) {
                return;
            }

            wp_enqueue_style( 'ssc-admin', SSC_URL . 'admin/admin.css', array(), SSC_VERSION );
            wp_enqueue_script( 'ssc-admin', SSC_URL . 'admin/admin.js', array( 'jquery' ), SSC_VERSION, true );

            $conversation_id = isset( $_GET['conversation_id'] ) ? absint( $_GET['conversation_id'] ) : 0;
            $current_user    = wp_get_current_user();

            // Build admin users list for assignment dropdown.
            $admin_users = get_users( array( 'role__in' => array( 'administrator' ), 'fields' => array( 'ID', 'display_name' ) ) );
            $admin_list  = array();
            foreach ( $admin_users as $au ) {
                $admin_list[] = array( 'id' => (int) $au->ID, 'name' => $au->display_name );
            }

            wp_localize_script( 'ssc-admin', 'ssc_admin', array(
                'rest_url'        => esc_url_raw( rest_url( 'ssc/v1/' ) ),
                'nonce'           => wp_create_nonce( 'wp_rest' ),
                'conversation_id' => $conversation_id,
                'admin_name'      => $current_user->display_name,
                'admin_id'        => $current_user->ID,
                'admin_users'     => $admin_list,
                'sounds_enabled'  => (bool) SSC_Settings::get_option( 'ssc_play_sounds', true ),
                'sounds_url'      => SSC_URL . 'assets/sounds/',
                'sound_message'   => SSC_Settings::get_option( 'ssc_sound_message', 'msg.mp3' ),
                'sound_volume'    => absint( SSC_Settings::get_option( 'ssc_sound_volume', 30 ) ) / 100,
            ) );
        }

        // -------------------------------------------------------------------
        // Settings Registration (all on 'ssc' page slug)
        // -------------------------------------------------------------------

        public function register_settings() {
            register_setting( 'ssc_option_group', 'ssc_options', array( 'sanitize_callback' => array( $this, 'sanitize_options' ) ) );

            // ---- General section ----
            add_settings_section( 'ssc_section_general', '', array( $this, 'section_noop' ), 'ssc', array( 'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>' ) );

            add_settings_field( 'ssc_enabled', __( 'Enable Chat', 'super-speedy-chat' ), array( $this, 'field_checkbox' ), 'ssc', 'ssc_section_general', array(
                'key' => 'ssc_enabled', 'label' => __( 'Enable chat bubble on front-end', 'super-speedy-chat' ), 'default' => true,
            ) );
            add_settings_field( 'ssc_mu_enabled', __( 'Ultra Ajax', 'super-speedy-chat' ), array( $this, 'field_mu_enabled' ), 'ssc', 'ssc_section_general', array(
                'key' => 'ssc_mu_enabled', 'label' => __( 'Enable Ultra Ajax (mu-plugin)', 'super-speedy-chat' ), 'default' => true,
            ) );
            add_settings_field( 'ssc_welcome_message', __( 'Welcome Message', 'super-speedy-chat' ), array( $this, 'field_textarea' ), 'ssc', 'ssc_section_general', array(
                'key' => 'ssc_welcome_message', 'default' => 'Hi! How can we help you today?', 'description' => __( 'Shown when a visitor opens the chat widget.', 'super-speedy-chat' ),
            ) );

            // ---- Display Names section ----
            add_settings_section( 'ssc_section_display_names', '', array( $this, 'section_noop' ), 'ssc', array( 'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>' ) );

            add_settings_field( 'ssc_display_name_mode', __( 'Display Name Mode', 'super-speedy-chat' ), array( $this, 'field_display_name_mode' ), 'ssc', 'ssc_section_display_names' );
            add_settings_field( 'ssc_shared_display_name', __( 'Shared Display Name', 'super-speedy-chat' ), array( $this, 'field_text' ), 'ssc', 'ssc_section_display_names', array(
                'key' => 'ssc_shared_display_name', 'default' => 'Support', 'description' => __( 'Display name shown to visitors when all admins share one name.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_my_display_name', __( 'Your Chat Display Name', 'super-speedy-chat' ), array( $this, 'field_my_display_name' ), 'ssc', 'ssc_section_display_names' );

            // ---- Behaviour section ----
            add_settings_section( 'ssc_section_behaviour', '', array( $this, 'section_noop' ), 'ssc', array( 'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>' ) );

            add_settings_field( 'ssc_admin_timeout', __( 'Admin Reply Timeout', 'super-speedy-chat' ), array( $this, 'field_number' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_admin_timeout', 'default' => 30, 'description' => __( 'Seconds before triggering timeout action.', 'super-speedy-chat' ), 'suffix' => __( 'seconds', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_timeout_action', __( 'Timeout Action', 'super-speedy-chat' ), array( $this, 'field_select' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_timeout_action', 'default' => 'show_email_prompt',
                'options' => array(
                    'show_email_prompt'    => __( 'Show email prompt', 'super-speedy-chat' ),
                    'llm_canned_response'  => __( 'Auto-reply with canned response (LLM)', 'super-speedy-chat' ),
                    'llm_then_email'       => __( 'Auto-reply with LLM, then show email prompt', 'super-speedy-chat' ),
                    'do_nothing'           => __( 'Do nothing', 'super-speedy-chat' ),
                ),
            ) );
            add_settings_field( 'ssc_login_prompt_after', __( 'Prompt Login After', 'super-speedy-chat' ), array( $this, 'field_number' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_login_prompt_after', 'default' => 5, 'suffix' => __( 'messages', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_max_message_length', __( 'Max Message Length', 'super-speedy-chat' ), array( $this, 'field_number' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_max_message_length', 'default' => 500, 'suffix' => __( 'characters', 'super-speedy-chat' ),
            ) );
            $mu_active = SSC_MU_Installer::is_installed() && SSC_Settings::get_option( 'ssc_mu_enabled', true );
            $poll_desc = $mu_active
                ? __( 'Ultra Ajax is active — lower values are fine (default: 1000).', 'super-speedy-chat' )
                : __( 'Default: 2000. Lower values increase server load.', 'super-speedy-chat' );
            $idle_desc = $mu_active
                ? __( 'Polling after 30s idle. Ultra Ajax active (default: 3000).', 'super-speedy-chat' )
                : __( 'Polling after 30s idle (default: 5000).', 'super-speedy-chat' );

            add_settings_field( 'ssc_poll_interval', __( 'Poll Interval', 'super-speedy-chat' ), array( $this, 'field_number' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_poll_interval', 'default' => $mu_active ? 1000 : 2000, 'suffix' => __( 'ms', 'super-speedy-chat' ), 'description' => $poll_desc,
            ) );
            add_settings_field( 'ssc_idle_poll_interval', __( 'Idle Poll Interval', 'super-speedy-chat' ), array( $this, 'field_number' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_idle_poll_interval', 'default' => $mu_active ? 3000 : 5000, 'suffix' => __( 'ms', 'super-speedy-chat' ), 'description' => $idle_desc,
            ) );
            add_settings_field( 'ssc_deep_idle_poll_interval', __( 'Deep Idle Poll Interval', 'super-speedy-chat' ), array( $this, 'field_number' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_deep_idle_poll_interval', 'default' => $mu_active ? 10000 : 15000, 'suffix' => __( 'ms', 'super-speedy-chat' ),
                'description' => __( 'Polling after 2 minutes idle.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_play_sounds', __( 'Play Sounds', 'super-speedy-chat' ), array( $this, 'field_checkbox' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_play_sounds', 'label' => __( 'Play sounds on new messages and chat open/close', 'super-speedy-chat' ), 'default' => true,
            ) );
            add_settings_field( 'ssc_sound_message', __( 'Message Sound', 'super-speedy-chat' ), array( $this, 'field_sound_select' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_sound_message', 'default' => 'msg.mp3',
            ) );
            add_settings_field( 'ssc_sound_open', __( 'Open/Close Sound', 'super-speedy-chat' ), array( $this, 'field_sound_select' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_sound_open', 'default' => 'woosh.mp3',
            ) );
            add_settings_field( 'ssc_sound_volume', __( 'Sound Volume', 'super-speedy-chat' ), array( $this, 'field_range' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_sound_volume', 'default' => 30, 'min' => 0, 'max' => 100, 'suffix' => '%',
            ) );
            add_settings_field( 'ssc_require_login', __( 'Require Login', 'super-speedy-chat' ), array( $this, 'field_checkbox' ), 'ssc', 'ssc_section_behaviour', array(
                'key' => 'ssc_require_login', 'label' => __( 'Require login to chat', 'super-speedy-chat' ), 'default' => false,
            ) );

            // ---- Email section ----
            add_settings_section( 'ssc_section_email', '', array( $this, 'section_noop' ), 'ssc', array( 'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>' ) );

            add_settings_field( 'ssc_admin_email_enabled', __( 'Admin Email Notifications', 'super-speedy-chat' ), array( $this, 'field_checkbox' ), 'ssc', 'ssc_section_email', array(
                'key' => 'ssc_admin_email_enabled', 'label' => __( 'Email admin on new conversation', 'super-speedy-chat' ), 'default' => true,
            ) );
            add_settings_field( 'ssc_admin_email', __( 'Admin Email', 'super-speedy-chat' ), array( $this, 'field_text' ), 'ssc', 'ssc_section_email', array(
                'key' => 'ssc_admin_email', 'default' => get_option( 'admin_email' ), 'description' => __( 'Email address for admin notifications.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_visitor_email_enabled', __( 'Visitor Email Notifications', 'super-speedy-chat' ), array( $this, 'field_checkbox' ), 'ssc', 'ssc_section_email', array(
                'key' => 'ssc_visitor_email_enabled', 'label' => __( 'Email visitor when admin replies offline', 'super-speedy-chat' ), 'default' => true,
            ) );
            add_settings_field( 'ssc_email_from_name', __( 'From Name', 'super-speedy-chat' ), array( $this, 'field_text' ), 'ssc', 'ssc_section_email', array(
                'key' => 'ssc_email_from_name', 'default' => get_bloginfo( 'name' ),
            ) );

            // ---- Canned Responses section ----
            add_settings_section( 'ssc_section_canned', '', array( $this, 'section_canned_callback' ), 'ssc', array( 'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>' ) );

            // ---- LLM section ----
            add_settings_section( 'ssc_section_llm', '', array( $this, 'section_llm_callback' ), 'ssc', array( 'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>' ) );

            add_settings_field( 'ssc_llm_provider', __( 'LLM Provider', 'super-speedy-chat' ), array( $this, 'field_select' ), 'ssc', 'ssc_section_llm', array(
                'key' => 'ssc_llm_provider', 'default' => '',
                'options' => array( '' => __( '— Disabled —', 'super-speedy-chat' ), 'openai' => 'OpenAI', 'anthropic' => 'Anthropic' ),
            ) );
            add_settings_field( 'ssc_llm_api_key', __( 'API Key', 'super-speedy-chat' ), array( $this, 'field_password' ), 'ssc', 'ssc_section_llm', array(
                'key' => 'ssc_llm_api_key', 'default' => '', 'description' => __( 'Your API key. Stored in the database — use a key with minimal permissions.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_llm_model', __( 'Model', 'super-speedy-chat' ), array( $this, 'field_text' ), 'ssc', 'ssc_section_llm', array(
                'key' => 'ssc_llm_model', 'default' => '',
                'description' => __( 'Leave blank for default (gpt-4o-mini / claude-haiku-4-5). Use the cheapest model — this is just a classifier.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_llm_system_prompt', __( 'System Prompt', 'super-speedy-chat' ), array( $this, 'field_textarea' ), 'ssc', 'ssc_section_llm', array(
                'key' => 'ssc_llm_system_prompt',
                'default' => 'You are a classifier for a live chat support system. Given a visitor\'s question and a list of canned responses, pick the best matching canned response number. If none are a good match, respond with 0. Respond with ONLY the number, nothing else.',
                'description' => __( 'Instructions for the LLM classifier. The default works well — only change if needed.', 'super-speedy-chat' ),
            ) );

            // ---- Discord section ----
            add_settings_section( 'ssc_section_discord', '', array( $this, 'section_discord_callback' ), 'ssc', array( 'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>' ) );

            add_settings_field( 'ssc_discord_enabled', __( 'Enable Discord', 'super-speedy-chat' ), array( $this, 'field_checkbox' ), 'ssc', 'ssc_section_discord', array(
                'key' => 'ssc_discord_enabled', 'label' => __( 'Enable Discord integration', 'super-speedy-chat' ), 'default' => false,
            ) );
            add_settings_field( 'ssc_discord_bot_token', __( 'Bot Token', 'super-speedy-chat' ), array( $this, 'field_password' ), 'ssc', 'ssc_section_discord', array(
                'key' => 'ssc_discord_bot_token', 'default' => '', 'description' => __( 'Your Discord bot token. Keep this secret.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_discord_channel_id', __( 'Channel ID', 'super-speedy-chat' ), array( $this, 'field_text' ), 'ssc', 'ssc_section_discord', array(
                'key' => 'ssc_discord_channel_id', 'default' => '', 'description' => __( 'The Discord channel where chat threads will be created.', 'super-speedy-chat' ),
            ) );
            add_settings_field( 'ssc_discord_bot_info', __( 'Bot Connection Info', 'super-speedy-chat' ), array( $this, 'field_discord_bot_info' ), 'ssc', 'ssc_section_discord' );

            // ---- Status section ----
            add_settings_section( 'ssc_section_status', '', array( $this, 'section_status_callback' ), 'ssc', array( 'before_section' => '<div class="ssc_tab">', 'after_section' => '</div>' ) );
        }

        public function section_noop() {}

        // -------------------------------------------------------------------
        // Page Rendering
        // -------------------------------------------------------------------

        public function render_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $conversation_id = isset( $_GET['conversation_id'] ) ? absint( $_GET['conversation_id'] ) : 0;

            // If viewing a conversation detail, skip tabs.
            if ( $conversation_id > 0 ) {
                echo '<div class="super-speedy-chat wrap">';
                echo '<h2>' . esc_html__( 'Super Speedy Chat', 'super-speedy-chat' ) . '</h2>';
                $this->render_conversation_detail( $conversation_id );
                echo '</div>';
                return;
            }

            if ( isset( $_GET['settings-updated'] ) ) {
                add_settings_error( 'ssc_messages', 'ssc_message', __( 'Settings Saved', 'super-speedy-chat' ), 'updated' );
            }

            $tabs = array(
                'chats'         => __( 'Chats', 'super-speedy-chat' ),
                'general'       => __( 'General', 'super-speedy-chat' ),
                'display_names' => __( 'Display Names', 'super-speedy-chat' ),
                'behaviour'     => __( 'Behaviour', 'super-speedy-chat' ),
                'email'         => __( 'Email', 'super-speedy-chat' ),
                'canned'        => __( 'Canned Responses', 'super-speedy-chat' ),
                'llm'           => __( 'LLM Auto-Reply', 'super-speedy-chat' ),
                'discord'       => __( 'Discord', 'super-speedy-chat' ),
                'status'        => __( 'Status', 'super-speedy-chat' ),
            );

            ?>
            <div class="super-speedy-chat wrap">
                <h2><?php esc_html_e( 'Super Speedy Chat', 'super-speedy-chat' ); ?></h2>
                <?php settings_errors( 'ssc_messages' ); ?>

                <h2 class="nav-tab-wrapper">
                    <?php
                    $class = ' nav-tab-active';
                    foreach ( $tabs as $tab_id => $tab_name ) {
                        echo '<a class="nav-tab' . $class . '" href="#' . esc_attr( $tab_id ) . '">' . esc_html( $tab_name ) . '</a>';
                        $class = '';
                    }
                    ?>
                </h2>

                <!-- Chats Tab (not in the form) -->
                <div class="ssc_tab" id="ssc-tab-chats">
                    <?php $this->render_conversation_list(); ?>
                </div>

                <!-- Settings Tabs (inside one form, following SSS pattern) -->
                <form method="post" action="options.php" id="ssc-settings-form" style="display:none;">
                    <?php
                    settings_fields( 'ssc_option_group' );
                    do_settings_sections( 'ssc' );
                    ?>
                    <p class="ssc-customizer-link">
                        <?php printf(
                            /* translators: %s: URL to the Customizer */
                            __( 'Configure the chat appearance (colours, header image, icons) in the <a href="%s">Customizer</a>.', 'super-speedy-chat' ),
                            esc_url( admin_url( 'customize.php?autofocus[section]=ssc_appearance' ) )
                        ); ?>
                    </p>
                    <?php submit_button( __( 'Save Changes' ), 'primary', 'submit', false ); ?>
                </form>
            </div>
            <?php
        }

        // -------------------------------------------------------------------
        // Conversation List
        // -------------------------------------------------------------------

        private function render_conversation_list() {
            ?>
            <div id="ssc-conversation-list">
                <div class="ssc-stats-bar">
                    <div class="ssc-stat">
                        <span class="ssc-stat-label"><?php esc_html_e( 'Active', 'super-speedy-chat' ); ?></span>
                        <span class="ssc-stat-count" id="ssc-stat-active">0</span>
                    </div>
                    <div class="ssc-stat">
                        <span class="ssc-stat-label"><?php esc_html_e( 'Waiting for Reply', 'super-speedy-chat' ); ?></span>
                        <span class="ssc-stat-count" id="ssc-stat-waiting">0</span>
                    </div>
                    <div class="ssc-stat">
                        <span class="ssc-stat-label"><?php esc_html_e( 'Total', 'super-speedy-chat' ); ?></span>
                        <span class="ssc-stat-count" id="ssc-stat-today">0</span>
                    </div>
                </div>

                <div class="ssc-filters-bar">
                    <div class="ssc-filter-buttons">
                        <button class="button ssc-filter-btn active" data-status=""><?php esc_html_e( 'All', 'super-speedy-chat' ); ?></button>
                        <button class="button ssc-filter-btn" data-status="active"><?php esc_html_e( 'Active', 'super-speedy-chat' ); ?></button>
                        <button class="button ssc-filter-btn" data-status="waiting"><?php esc_html_e( 'Waiting', 'super-speedy-chat' ); ?></button>
                        <button class="button ssc-filter-btn" data-status="closed"><?php esc_html_e( 'Closed', 'super-speedy-chat' ); ?></button>
                    </div>
                    <div class="ssc-assign-filter">
                        <select id="ssc-filter-assigned">
                            <option value=""><?php esc_html_e( 'All Assignees', 'super-speedy-chat' ); ?></option>
                            <option value="unassigned"><?php esc_html_e( 'Unassigned', 'super-speedy-chat' ); ?></option>
                            <option value="mine"><?php esc_html_e( 'Assigned to Me', 'super-speedy-chat' ); ?></option>
                        </select>
                    </div>
                    <div class="ssc-search-box">
                        <input type="search" id="ssc-search-input" placeholder="<?php esc_attr_e( 'Search visitor name or email...', 'super-speedy-chat' ); ?>" />
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped" id="ssc-conversations-table">
                    <thead>
                        <tr>
                            <th class="ssc-col-visitor"><?php esc_html_e( 'Visitor', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-message"><?php esc_html_e( 'Last Message', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-status"><?php esc_html_e( 'Status', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-started"><?php esc_html_e( 'Started', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-activity"><?php esc_html_e( 'Last Activity', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-assigned"><?php esc_html_e( 'Assigned', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-actions"><?php esc_html_e( 'Actions', 'super-speedy-chat' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ssc-conversations-tbody">
                        <tr class="ssc-loading-row"><td colspan="7"><?php esc_html_e( 'Loading conversations...', 'super-speedy-chat' ); ?></td></tr>
                    </tbody>
                </table>

                <div class="ssc-pagination" id="ssc-pagination">
                    <button class="button ssc-page-prev" disabled>&laquo; <?php esc_html_e( 'Previous', 'super-speedy-chat' ); ?></button>
                    <span class="ssc-page-info" id="ssc-page-info"></span>
                    <button class="button ssc-page-next" disabled><?php esc_html_e( 'Next', 'super-speedy-chat' ); ?> &raquo;</button>
                </div>
            </div>
            <?php
        }

        // -------------------------------------------------------------------
        // Conversation Detail
        // -------------------------------------------------------------------

        private function render_conversation_detail( $conversation_id ) {
            $list_url = admin_url( 'admin.php?page=ssc' );
            ?>
            <div id="ssc-conversation-detail" data-conversation-id="<?php echo esc_attr( $conversation_id ); ?>">
                <div class="ssc-detail-header">
                    <a href="<?php echo esc_url( $list_url ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Conversations', 'super-speedy-chat' ); ?></a>
                    <div class="ssc-detail-actions">
                        <button class="button button-secondary" id="ssc-close-conversation"><?php esc_html_e( 'Close Conversation', 'super-speedy-chat' ); ?></button>
                    </div>
                </div>
                <div class="ssc-detail-layout">
                    <div class="ssc-detail-sidebar">
                        <h3><?php esc_html_e( 'Visitor Info', 'super-speedy-chat' ); ?></h3>
                        <dl class="ssc-visitor-info">
                            <dt><?php esc_html_e( 'Name', 'super-speedy-chat' ); ?></dt><dd id="ssc-info-name">&mdash;</dd>
                            <dt><?php esc_html_e( 'Email', 'super-speedy-chat' ); ?></dt><dd id="ssc-info-email">&mdash;</dd>
                            <dt><?php esc_html_e( 'IP Address', 'super-speedy-chat' ); ?></dt><dd id="ssc-info-ip">&mdash;</dd>
                            <dt><?php esc_html_e( 'Referrer', 'super-speedy-chat' ); ?></dt><dd id="ssc-info-referrer">&mdash;</dd>
                            <dt><?php esc_html_e( 'User Agent', 'super-speedy-chat' ); ?></dt><dd id="ssc-info-useragent">&mdash;</dd>
                            <dt><?php esc_html_e( 'Page URL', 'super-speedy-chat' ); ?></dt><dd id="ssc-info-page-url">&mdash;</dd>
                            <dt><?php esc_html_e( 'Started At', 'super-speedy-chat' ); ?></dt><dd id="ssc-info-started">&mdash;</dd>
                            <dt><?php esc_html_e( 'Assigned To', 'super-speedy-chat' ); ?></dt>
                            <dd>
                                <select id="ssc-assign-select">
                                    <option value="0"><?php esc_html_e( 'Unassigned', 'super-speedy-chat' ); ?></option>
                                </select>
                            </dd>
                        </dl>
                    </div>
                    <div class="ssc-detail-main">
                        <div class="ssc-messages-thread" id="ssc-messages-thread">
                            <div class="ssc-loading"><?php esc_html_e( 'Loading messages...', 'super-speedy-chat' ); ?></div>
                        </div>
                        <div class="ssc-reply-form" id="ssc-reply-form">
                            <textarea id="ssc-reply-textarea" rows="3" placeholder="<?php esc_attr_e( 'Type your reply...', 'super-speedy-chat' ); ?>"></textarea>
                            <button class="button button-primary" id="ssc-send-reply"><?php esc_html_e( 'Send', 'super-speedy-chat' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        // -------------------------------------------------------------------
        // Field Renderers
        // -------------------------------------------------------------------

        public function field_checkbox( $args ) {
            $key     = $args['key'];
            $default = isset( $args['default'] ) ? $args['default'] : false;
            $label   = isset( $args['label'] ) ? $args['label'] : '';
            $value   = SSC_Settings::get_option( $key, $default );

            printf(
                '<label><input type="checkbox" name="ssc_options[%s]" value="1" %s /> %s</label>',
                esc_attr( $key ),
                checked( $value, true, false ),
                esc_html( $label )
            );
        }

        public function field_mu_enabled( $args ) {
            $this->field_checkbox( $args );

            $mu_file   = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR . '/ssc-fast-ajax.php' : '';
            $mu_exists = $mu_file && file_exists( $mu_file );

            if ( $mu_exists ) {
                echo '<p class="description" style="color:green;">' . esc_html__( 'MU-Plugin is installed.', 'super-speedy-chat' ) . '</p>';
            } else {
                echo '<p class="description" style="color:orange;">' . esc_html__( 'MU-Plugin not installed. Will be installed on activation or when settings are saved.', 'super-speedy-chat' ) . '</p>';
            }
        }

        public function field_text( $args ) {
            $key         = $args['key'];
            $default     = isset( $args['default'] ) ? $args['default'] : '';
            $description = isset( $args['description'] ) ? $args['description'] : '';
            $value       = SSC_Settings::get_option( $key, $default );

            printf(
                '<input type="text" name="ssc_options[%s]" value="%s" class="regular-text" />',
                esc_attr( $key ),
                esc_attr( $value )
            );
            if ( $description ) {
                echo '<p class="description">' . esc_html( $description ) . '</p>';
            }
        }

        public function field_textarea( $args ) {
            $key         = $args['key'];
            $default     = isset( $args['default'] ) ? $args['default'] : '';
            $description = isset( $args['description'] ) ? $args['description'] : '';
            $value       = SSC_Settings::get_option( $key, $default );

            printf( '<textarea name="ssc_options[%s]" rows="4" class="large-text">%s</textarea>', esc_attr( $key ), esc_textarea( $value ) );
            if ( $description ) {
                echo '<p class="description">' . esc_html( $description ) . '</p>';
            }
        }

        public function field_number( $args ) {
            $key    = $args['key'];
            $default = isset( $args['default'] ) ? $args['default'] : 0;
            $description = isset( $args['description'] ) ? $args['description'] : '';
            $suffix = isset( $args['suffix'] ) ? $args['suffix'] : '';
            $value  = SSC_Settings::get_option( $key, $default );

            printf( '<input type="number" name="ssc_options[%s]" value="%s" class="small-text" min="0" />', esc_attr( $key ), esc_attr( $value ) );
            if ( $suffix ) {
                echo ' <span class="description">' . esc_html( $suffix ) . '</span>';
            }
            if ( $description ) {
                echo '<p class="description">' . esc_html( $description ) . '</p>';
            }
        }

        public function field_sound_select( $args ) {
            $key     = $args['key'];
            $default = isset( $args['default'] ) ? $args['default'] : 'msg.mp3';
            $value   = SSC_Settings::get_option( $key, $default );

            $sounds_dir = SSC_DIR . 'assets/sounds/';
            $sound_files = array();
            if ( is_dir( $sounds_dir ) ) {
                foreach ( glob( $sounds_dir . '*.mp3' ) as $file ) {
                    $filename = basename( $file );
                    $label = str_replace( array( '.mp3', '-', '_' ), array( '', ' ', ' ' ), $filename );
                    $sound_files[ $filename ] = ucwords( $label );
                }
            }

            $sounds_url = SSC_URL . 'assets/sounds/';
            printf( '<select name="ssc_options[%s]" class="ssc-sound-select" data-sounds-url="%s">', esc_attr( $key ), esc_url( $sounds_url ) );
            foreach ( $sound_files as $file => $label ) {
                printf( '<option value="%s" %s>%s</option>', esc_attr( $file ), selected( $value, $file, false ), esc_html( $label ) );
            }
            echo '</select>';
            printf( ' <button type="button" class="button button-small ssc-preview-sound" data-select="%s">&#9654; Preview</button>', esc_attr( $key ) );
        }

        public function field_range( $args ) {
            $key     = $args['key'];
            $default = isset( $args['default'] ) ? $args['default'] : 50;
            $min     = isset( $args['min'] ) ? $args['min'] : 0;
            $max     = isset( $args['max'] ) ? $args['max'] : 100;
            $suffix  = isset( $args['suffix'] ) ? $args['suffix'] : '';
            $value   = SSC_Settings::get_option( $key, $default );

            printf(
                '<input type="range" name="ssc_options[%s]" value="%s" min="%d" max="%d" class="ssc-range-slider" id="%s" />',
                esc_attr( $key ), esc_attr( $value ), $min, $max, esc_attr( $key )
            );
            printf( ' <span class="ssc-range-value">%s%s</span>', esc_html( $value ), esc_html( $suffix ) );
        }

        public function field_select( $args ) {
            $key     = $args['key'];
            $default = isset( $args['default'] ) ? $args['default'] : '';
            $options = isset( $args['options'] ) ? $args['options'] : array();
            $value   = SSC_Settings::get_option( $key, $default );

            printf( '<select name="ssc_options[%s]">', esc_attr( $key ) );
            foreach ( $options as $opt_value => $opt_label ) {
                printf( '<option value="%s" %s>%s</option>', esc_attr( $opt_value ), selected( $value, $opt_value, false ), esc_html( $opt_label ) );
            }
            echo '</select>';
        }

        // ---- Display name fields ----

        public function field_display_name_mode() {
            $mode = SSC_Settings::get_option( 'ssc_display_name_mode', 'shared' );
            ?>
            <fieldset>
                <label>
                    <input type="radio" name="ssc_options[ssc_display_name_mode]" value="shared" <?php checked( $mode, 'shared' ); ?> />
                    <?php esc_html_e( 'All admins share one display name', 'super-speedy-chat' ); ?>
                </label><br>
                <label>
                    <input type="radio" name="ssc_options[ssc_display_name_mode]" value="individual" <?php checked( $mode, 'individual' ); ?> />
                    <?php esc_html_e( 'Each admin chooses their own display name', 'super-speedy-chat' ); ?>
                </label>
            </fieldset>
            <p class="description"><?php esc_html_e( 'When shared, all admin replies appear under the same name. When individual, each admin can set their own chat name below.', 'super-speedy-chat' ); ?></p>
            <?php
        }

        public function field_my_display_name() {
            $user_id      = get_current_user_id();
            $current_user = wp_get_current_user();
            $saved_name   = get_user_meta( $user_id, 'ssc_chat_display_name', true );
            $display_name = ! empty( $saved_name ) ? $saved_name : $current_user->display_name;

            printf(
                '<input type="text" name="ssc_my_display_name" value="%s" class="regular-text" />',
                esc_attr( $display_name )
            );
            echo '<p class="description">' . esc_html__( 'Your personal display name shown in chat when using individual mode. Saved per-user.', 'super-speedy-chat' ) . '</p>';
        }

        public function field_password( $args ) {
            $key         = $args['key'];
            $default     = isset( $args['default'] ) ? $args['default'] : '';
            $description = isset( $args['description'] ) ? $args['description'] : '';
            $value       = SSC_Settings::get_option( $key, $default );

            printf(
                '<input type="password" name="ssc_options[%s]" value="%s" class="regular-text" autocomplete="off" />',
                esc_attr( $key ),
                esc_attr( $value )
            );
            if ( $description ) {
                echo '<p class="description">' . esc_html( $description ) . '</p>';
            }
        }

        // ---- Canned Responses section callback ----

        public function section_canned_callback() {
            ?>
            <div class="ssc-guide-box">
                <h3><?php esc_html_e( 'How Canned Responses Work', 'super-speedy-chat' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'Respond to customer chats as you normally would — from this admin panel or from Discord.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'When you write a particularly good response, click the bookmark icon next to your message in the conversation view.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Edit the question summary and response text, add a category, and save.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Manage all your saved responses below — edit, categorize, or delete them.', 'super-speedy-chat' ); ?></li>
                </ol>
                <p><em><?php esc_html_e( 'Coming soon: A lightweight LLM classifier will automatically suggest matching canned responses when visitors ask similar questions, dramatically reducing your response time.', 'super-speedy-chat' ); ?></em></p>
            </div>

            <div id="ssc-canned-list">
                <div class="ssc-canned-toolbar">
                    <input type="search" id="ssc-canned-search" placeholder="<?php esc_attr_e( 'Search responses...', 'super-speedy-chat' ); ?>" class="regular-text" />
                </div>
                <table class="wp-list-table widefat fixed striped" id="ssc-canned-table">
                    <thead>
                        <tr>
                            <th class="ssc-col-question"><?php esc_html_e( 'Question', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-response"><?php esc_html_e( 'Response', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-category"><?php esc_html_e( 'Category', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-used"><?php esc_html_e( 'Used', 'super-speedy-chat' ); ?></th>
                            <th class="ssc-col-actions"><?php esc_html_e( 'Actions', 'super-speedy-chat' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ssc-canned-tbody">
                        <tr><td colspan="5" class="ssc-loading-row"><?php esc_html_e( 'Loading...', 'super-speedy-chat' ); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php
        }

        // ---- LLM section callback ----

        public function section_llm_callback() {
            ?>
            <div class="ssc-guide-box">
                <h3><?php esc_html_e( 'LLM Auto-Reply — Canned Response Classifier', 'super-speedy-chat' ); ?></h3>
                <p><?php esc_html_e( 'When no admin replies within the timeout period, the LLM classifier matches the visitor\'s question against your canned responses and auto-replies with the best match. Uses a cheap, fast model — costs fractions of a cent per classification.', 'super-speedy-chat' ); ?></p>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'How it works', 'super-speedy-chat' ); ?></h4>
                <ol>
                    <li><?php esc_html_e( 'Visitor asks a question and no admin replies within the timeout period.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'The visitor\'s question is sent to the LLM along with all your canned responses.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'The LLM picks the best matching canned response (or determines no match exists).', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'If a match is found, the canned response is sent as an auto-reply to the visitor.', 'super-speedy-chat' ); ?></li>
                </ol>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Setup', 'super-speedy-chat' ); ?></h4>
                <ol>
                    <li><?php esc_html_e( 'Choose a provider (OpenAI or Anthropic) and enter your API key below.', 'super-speedy-chat' ); ?></li>
                    <li><?php printf( esc_html__( 'Go to the %sBehaviour%s tab and set Timeout Action to "Auto-reply with canned response (LLM)".', 'super-speedy-chat' ), '<strong>', '</strong>' ); ?></li>
                    <li><?php printf( esc_html__( 'Make sure you have canned responses saved in the %sCanned Responses%s tab.', 'super-speedy-chat' ), '<strong>', '</strong>' ); ?></li>
                </ol>

                <p class="description"><?php esc_html_e( 'Tip: The default model (gpt-4o-mini / claude-haiku-4-5) is recommended — it\'s cheap and fast. This is a simple classifier, not a conversational AI.', 'super-speedy-chat' ); ?></p>
            </div>
            <?php
        }

        // ---- Discord section callback ----

        public function section_discord_callback() {
            ?>
            <div class="ssc-guide-box">
                <h3><?php esc_html_e( 'Discord Integration — Instant Bidirectional Chat', 'super-speedy-chat' ); ?></h3>
                <p><?php esc_html_e( 'Chat with your visitors in real-time directly from Discord. Visitor messages appear instantly in Discord threads, and your Discord replies are delivered to visitors instantly.', 'super-speedy-chat' ); ?></p>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 1: Create a Discord Bot', 'super-speedy-chat' ); ?></h4>
                <ol>
                    <li><?php
                        printf(
                            __( 'Go to the <a href="%s" target="_blank">Discord Developer Portal</a> and create a New Application.', 'super-speedy-chat' ),
                            'https://discord.com/developers/applications'
                        );
                    ?></li>
                    <li><?php esc_html_e( 'Go to the Bot tab and click "Reset Token" to get your bot token. Copy it.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Under Privileged Gateway Intents, enable MESSAGE CONTENT INTENT.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Go to OAuth2 > URL Generator. Select the "bot" scope.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Select permissions: Send Messages, Create Public Threads, Send Messages in Threads, Read Message History.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Copy the generated URL, open it, and add the bot to your server.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Enable Developer Mode in Discord (User Settings > Advanced), right-click your channel, Copy Channel ID.', 'super-speedy-chat' ); ?></li>
                </ol>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 2: Configure Settings Below & Save', 'super-speedy-chat' ); ?></h4>
                <p><?php esc_html_e( 'Enter your bot token and channel ID below, enable the integration, and click Save. Visitor messages will start appearing in Discord immediately.', 'super-speedy-chat' ); ?></p>

                <h4 style="margin-top:16px;"><?php esc_html_e( 'Step 3: Install the Companion Bot (for Discord → WordPress replies)', 'super-speedy-chat' ); ?></h4>
                <p><?php esc_html_e( 'To receive your Discord replies back in WordPress instantly, install the companion Node.js bot on your server:', 'super-speedy-chat' ); ?></p>
                <ol>
                    <li><?php esc_html_e( 'Requires Node.js 18+ on your server.', 'super-speedy-chat' ); ?></li>
                    <li><?php
                        printf(
                            __( 'Copy the <code>bot/</code> folder from <code>%s</code> to a location on your server.', 'super-speedy-chat' ),
                            esc_html( SSC_DIR . 'bot/' )
                        );
                    ?></li>
                    <li><?php esc_html_e( 'Run: npm install', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Copy .env.example to .env and fill in the values from "Bot Connection Info" below.', 'super-speedy-chat' ); ?></li>
                    <li><?php esc_html_e( 'Run: node discord-bot.js (or use PM2/systemd to keep it running).', 'super-speedy-chat' ); ?></li>
                </ol>
                <p><em><?php esc_html_e( 'Without the companion bot, visitor messages still go to Discord instantly, but your Discord replies won\'t reach visitors until you reply from wp-admin.', 'super-speedy-chat' ); ?></em></p>
                <p><button type="button" class="button" id="ssc-discord-test"><?php esc_html_e( 'Test Connection', 'super-speedy-chat' ); ?></button> <span id="ssc-discord-test-result"></span></p>
            </div>
            <?php
        }

        /**
         * Display the webhook secret and site URL for the Discord bot config.
         */
        public function field_discord_bot_info() {
            $secret   = SSC_Discord::get_webhook_secret();
            $rest_url = rest_url( 'ssc/v1/discord/incoming' );
            ?>
            <div style="background:#f9f9f9; border:1px solid #e0e0e0; border-radius:4px; padding:12px; max-width:600px;">
                <p style="margin-top:0;">
                    <strong><?php esc_html_e( 'Webhook Secret:', 'super-speedy-chat' ); ?></strong><br>
                    <code id="ssc-discord-secret" style="user-select:all; font-size:12px;"><?php echo esc_html( $secret ); ?></code>
                </p>
                <p style="margin-bottom:0;">
                    <strong><?php esc_html_e( 'WordPress Endpoint URL:', 'super-speedy-chat' ); ?></strong><br>
                    <code id="ssc-discord-endpoint" style="user-select:all; font-size:12px;"><?php echo esc_html( $rest_url ); ?></code>
                </p>
            </div>
            <p class="description"><?php esc_html_e( 'Copy these values into your bot\'s .env file.', 'super-speedy-chat' ); ?></p>
            <?php
        }

        // ---- Status section callback ----

        public function section_status_callback() {
            if ( class_exists( 'SSC_MU_Installer' ) ) {
                SSC_MU_Installer::check_and_update();
            }

            echo '<table class="widefat fixed" style="max-width:600px;">';

            $mu_file   = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR . '/ssc-fast-ajax.php' : '';
            $mu_exists = $mu_file && file_exists( $mu_file );

            echo '<tr><td><strong>' . esc_html__( 'MU-Plugin (Ultra Ajax)', 'super-speedy-chat' ) . '</strong></td>';
            if ( $mu_exists ) {
                $mu_data    = get_file_data( $mu_file, array( 'Version' => 'Version' ) );
                $mu_version = ! empty( $mu_data['Version'] ) ? $mu_data['Version'] : __( 'Unknown', 'super-speedy-chat' );
                echo '<td><span style="color:green;">&#10003; ' . esc_html__( 'Installed', 'super-speedy-chat' ) . '</span> &mdash; v' . esc_html( $mu_version ) . '</td>';
            } else {
                echo '<td><span style="color:red;">&#10007; ' . esc_html__( 'Not installed', 'super-speedy-chat' ) . '</span></td>';
            }
            echo '</tr>';

            global $wpdb;
            $tables = array(
                $wpdb->prefix . 'ssc_conversations',
                $wpdb->prefix . 'ssc_participants',
                $wpdb->prefix . 'ssc_messages',
            );
            foreach ( $tables as $table_name ) {
                $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
                echo '<tr><td><strong>' . esc_html( $table_name ) . '</strong></td>';
                echo '<td>' . ( $exists ? '<span style="color:green;">&#10003; ' . esc_html__( 'Exists', 'super-speedy-chat' ) . '</span>' : '<span style="color:red;">&#10007; ' . esc_html__( 'Missing', 'super-speedy-chat' ) . '</span>' ) . '</td></tr>';
            }

            echo '</table>';
        }

        // -------------------------------------------------------------------
        // Sanitize
        // -------------------------------------------------------------------

        public function sanitize_options( $input ) {
            $sanitized = array();
            if ( ! is_array( $input ) ) {
                return $sanitized;
            }

            // Checkboxes.
            $cb_keys = array( 'ssc_enabled', 'ssc_mu_enabled', 'ssc_play_sounds', 'ssc_require_login', 'ssc_admin_email_enabled', 'ssc_visitor_email_enabled', 'ssc_discord_enabled' );
            foreach ( $cb_keys as $k ) {
                $sanitized[ $k ] = ! empty( $input[ $k ] );
            }

            // Text.
            $text_keys = array( 'ssc_shared_display_name', 'ssc_admin_email', 'ssc_email_from_name', 'ssc_discord_bot_token', 'ssc_discord_channel_id', 'ssc_llm_api_key', 'ssc_llm_model' );
            foreach ( $text_keys as $k ) {
                $sanitized[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( $input[ $k ] ) : '';
            }

            // Textarea.
            $textarea_keys = array( 'ssc_welcome_message', 'ssc_llm_system_prompt' );
            foreach ( $textarea_keys as $k ) {
                if ( isset( $input[ $k ] ) ) {
                    $sanitized[ $k ] = sanitize_textarea_field( $input[ $k ] );
                }
            }

            // Select.
            $select_allowed = array(
                'ssc_timeout_action'      => array( 'show_email_prompt', 'llm_canned_response', 'llm_then_email', 'do_nothing' ),
                'ssc_display_name_mode'   => array( 'shared', 'individual' ),
                'ssc_llm_provider'        => array( '', 'openai', 'anthropic' ),
            );
            foreach ( $select_allowed as $k => $allowed ) {
                $sanitized[ $k ] = ( isset( $input[ $k ] ) && in_array( $input[ $k ], $allowed, true ) ) ? $input[ $k ] : $allowed[0];
            }

            // Sound selects (sanitize as filenames).
            $sound_keys = array( 'ssc_sound_message', 'ssc_sound_open' );
            foreach ( $sound_keys as $k ) {
                $sanitized[ $k ] = isset( $input[ $k ] ) ? sanitize_file_name( $input[ $k ] ) : '';
            }

            // Numbers.
            $num_keys = array( 'ssc_admin_timeout', 'ssc_login_prompt_after', 'ssc_max_message_length', 'ssc_poll_interval', 'ssc_idle_poll_interval', 'ssc_deep_idle_poll_interval', 'ssc_sound_volume' );
            foreach ( $num_keys as $k ) {
                $sanitized[ $k ] = isset( $input[ $k ] ) ? absint( $input[ $k ] ) : 0;
            }

            // Save per-user display name (submitted outside ssc_options but alongside it).
            if ( isset( $_POST['ssc_my_display_name'] ) ) {
                $my_name = sanitize_text_field( $_POST['ssc_my_display_name'] );
                update_user_meta( get_current_user_id(), 'ssc_chat_display_name', $my_name );
            }

            // Preserve the auto-generated webhook secret (not submitted via form).
            // Check $input first (covers direct update_option calls from get_webhook_secret),
            // then fall back to existing DB value (covers form submissions).
            if ( ! empty( $input['ssc_discord_webhook_secret'] ) ) {
                $sanitized['ssc_discord_webhook_secret'] = sanitize_text_field( $input['ssc_discord_webhook_secret'] );
            } else {
                $existing = get_option( 'ssc_options', array() );
                if ( ! empty( $existing['ssc_discord_webhook_secret'] ) ) {
                    $sanitized['ssc_discord_webhook_secret'] = $existing['ssc_discord_webhook_secret'];
                }
            }

            // Trigger mu-plugin install/update on save.
            if ( ! empty( $sanitized['ssc_mu_enabled'] ) && class_exists( 'SSC_MU_Installer' ) ) {
                SSC_MU_Installer::install();
            }

            return $sanitized;
        }

        // -------------------------------------------------------------------
        // Customizer Registration
        // -------------------------------------------------------------------

        public static function customizer_register( $wp_customize ) {
            // Section.
            $wp_customize->add_section( 'ssc_appearance', array(
                'title'    => __( 'Super Speedy Chat', 'super-speedy-chat' ),
                'priority' => 200,
            ) );

            // Header Image.
            $wp_customize->add_setting( 'ssc_customizer[header_image]', array(
                'default'           => '',
                'sanitize_callback' => 'esc_url_raw',
                'type'              => 'option',
            ) );
            $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'ssc_header_image', array(
                'label'    => __( 'Chat Header Image', 'super-speedy-chat' ),
                'description' => __( 'Image displayed in the chat widget header bar. Recommended height: 40px.', 'super-speedy-chat' ),
                'section'  => 'ssc_appearance',
                'settings' => 'ssc_customizer[header_image]',
            ) ) );

            // Chat Window Title.
            $wp_customize->add_setting( 'ssc_customizer[window_title]', array(
                'default'           => 'Chat',
                'sanitize_callback' => 'sanitize_text_field',
                'type'              => 'option',
            ) );
            $wp_customize->add_control( 'ssc_window_title', array(
                'label'    => __( 'Chat Window Title', 'super-speedy-chat' ),
                'section'  => 'ssc_appearance',
                'settings' => 'ssc_customizer[window_title]',
                'type'     => 'text',
            ) );

            // Primary Color.
            $wp_customize->add_setting( 'ssc_customizer[primary_color]', array(
                'default'           => '#0073aa',
                'sanitize_callback' => 'sanitize_hex_color',
                'type'              => 'option',
            ) );
            $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'ssc_primary_color', array(
                'label'    => __( 'Primary Color', 'super-speedy-chat' ),
                'description' => __( 'Main color for the chat bubble, header and send button.', 'super-speedy-chat' ),
                'section'  => 'ssc_appearance',
                'settings' => 'ssc_customizer[primary_color]',
            ) ) );

            // Header Background Color (allows a different color from the trigger).
            $wp_customize->add_setting( 'ssc_customizer[header_bg_color]', array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_hex_color',
                'type'              => 'option',
            ) );
            $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'ssc_header_bg_color', array(
                'label'    => __( 'Header Background Color', 'super-speedy-chat' ),
                'description' => __( 'Leave blank to use the primary color.', 'super-speedy-chat' ),
                'section'  => 'ssc_appearance',
                'settings' => 'ssc_customizer[header_bg_color]',
            ) ) );

            // Visitor Message Color.
            $wp_customize->add_setting( 'ssc_customizer[visitor_msg_color]', array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_hex_color',
                'type'              => 'option',
            ) );
            $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'ssc_visitor_msg_color', array(
                'label'    => __( 'Visitor Message Color', 'super-speedy-chat' ),
                'description' => __( 'Background color for visitor message bubbles. Leave blank to use primary color.', 'super-speedy-chat' ),
                'section'  => 'ssc_appearance',
                'settings' => 'ssc_customizer[visitor_msg_color]',
            ) ) );

            // Bubble Position.
            $wp_customize->add_setting( 'ssc_customizer[bubble_position]', array(
                'default'           => 'bottom-right',
                'sanitize_callback' => function( $val ) {
                    return in_array( $val, array( 'bottom-right', 'bottom-left' ), true ) ? $val : 'bottom-right';
                },
                'type' => 'option',
            ) );
            $wp_customize->add_control( 'ssc_bubble_position', array(
                'label'    => __( 'Bubble Position', 'super-speedy-chat' ),
                'section'  => 'ssc_appearance',
                'settings' => 'ssc_customizer[bubble_position]',
                'type'     => 'select',
                'choices'  => array(
                    'bottom-right' => __( 'Bottom Right', 'super-speedy-chat' ),
                    'bottom-left'  => __( 'Bottom Left', 'super-speedy-chat' ),
                ),
            ) );

            // Trigger Icon.
            $wp_customize->add_setting( 'ssc_customizer[trigger_icon]', array(
                'default'           => 'chat',
                'sanitize_callback' => function( $val ) {
                    return in_array( $val, array( 'chat', 'speech', 'headset', 'custom' ), true ) ? $val : 'chat';
                },
                'type' => 'option',
            ) );
            $wp_customize->add_control( 'ssc_trigger_icon', array(
                'label'    => __( 'Trigger Button Icon', 'super-speedy-chat' ),
                'section'  => 'ssc_appearance',
                'settings' => 'ssc_customizer[trigger_icon]',
                'type'     => 'select',
                'choices'  => array(
                    'chat'    => __( 'Chat bubble', 'super-speedy-chat' ),
                    'speech'  => __( 'Speech bubble', 'super-speedy-chat' ),
                    'headset' => __( 'Headset', 'super-speedy-chat' ),
                    'custom'  => __( 'Custom image (set below)', 'super-speedy-chat' ),
                ),
            ) );

            // Custom Trigger Icon Image.
            $wp_customize->add_setting( 'ssc_customizer[trigger_icon_image]', array(
                'default'           => '',
                'sanitize_callback' => 'esc_url_raw',
                'type'              => 'option',
            ) );
            $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'ssc_trigger_icon_image', array(
                'label'    => __( 'Custom Trigger Icon Image', 'super-speedy-chat' ),
                'description' => __( 'Used when Trigger Button Icon is set to "Custom image". Recommended: 28x28px.', 'super-speedy-chat' ),
                'section'  => 'ssc_appearance',
                'settings' => 'ssc_customizer[trigger_icon_image]',
            ) ) );
        }

        // -------------------------------------------------------------------
        // Helper: get admin display name for chat replies
        // -------------------------------------------------------------------

        /**
         * Get the display name for an admin user in chat context.
         *
         * @param int $user_id WP user ID.
         * @return string
         */
        public static function get_admin_chat_name( $user_id ) {
            $mode = SSC_Settings::get_option( 'ssc_display_name_mode', 'shared' );

            if ( $mode === 'shared' ) {
                return SSC_Settings::get_option( 'ssc_shared_display_name', 'Support' );
            }

            // Individual mode: check user meta first, fall back to WP display name.
            $name = get_user_meta( $user_id, 'ssc_chat_display_name', true );
            if ( ! empty( $name ) ) {
                return $name;
            }

            $user = get_userdata( $user_id );
            return $user ? $user->display_name : 'Support';
        }
    }

}
