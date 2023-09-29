<?php
/**
 * Plugin Name: Simple Calendar Plugin
 * Description: Un simple plugin de calendrier interactif
 * Version: 1.0
 * Author: Axel PELASSA
*/

require_once plugin_dir_path( __FILE__ ) . 'back-office.php';

register_activation_hook(__FILE__, 'create_reservation_table');

function create_reservation_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';

    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            reservation_date date NOT NULL,
            time_slot varchar(5) NOT NULL, 
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function display_simple_calendar() {
    ob_start(); ?>
    <div id="calendar-container" data-user-id="<?php echo get_current_user_id(); ?>">
        <div id="calendar-header">
            <button id="prev-month">Mois précédent</button>
            <span id="current-month-year"></span>
            <button id="next-month">Mois suivant</button>
        </div>
        <table id="calendar-table"></table>
        <div id="timeslot-container"></div>
        <button id="reserve-button" style="display:none;">Réserver</button>
        <div id="error-message"></div>
    </div>
    <script defer src="<?php echo plugin_dir_url(__FILE__) . 'calendar.js'; ?>"></script>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'calendar.css'; ?>">
    <?php
    return ob_get_clean();
}

add_shortcode('simple_calendar', 'display_simple_calendar');

function enqueue_my_scripts() {
    if(is_page('calendar')) { 
        wp_enqueue_script('my-calendar', plugin_dir_url(__FILE__) . 'calendar.js', array('jquery'), null, true);
        wp_localize_script('my-calendar', 'my_script_vars', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
}


add_action('wp_enqueue_scripts', 'enqueue_my_scripts');

add_action('wp_ajax_reserve_slots', 'reserve_slots_handler');

function reserve_slots_handler() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $user_id = intval($_POST['userId']);
    $date = sanitize_text_field($_POST['date']);
    $slots = json_decode(stripslashes($_POST['slots']));

    foreach ($slots as $slot) {
        $time_slot = sanitize_text_field($slot->time);
        $is_reserved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE reservation_date = %s AND time_slot = %s",
            $date, $time_slot
        ));
        if(!$is_reserved) {
            $success = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'reservation_date' => $date,
                    'time_slot' => $time_slot
                ),
                array('%d', '%s', '%s')
            );
            if($success) {
                send_reservation_email($user_id, $date, $time_slot);
            }
        }
    }
    wp_send_json_success(array('redirect_url' => 'https://hackathon.omniwebsolutions.fr/calendar/remerciement/'));
}

add_action('wp_ajax_get_reserved_slots', 'get_reserved_slots_handler');

function get_reserved_slots_handler() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $date = sanitize_text_field($_POST['date']);

    $reserved_slots = $wpdb->get_col($wpdb->prepare(
        "SELECT time_slot FROM $table_name WHERE reservation_date = %s",
        $date
    ));

    wp_send_json_success($reserved_slots);
}

function send_reservation_email($user_id, $date, $time_slot) {
    $user_info = get_userdata($user_id);
    $to = get_option('admin_email');
    $subject = 'Nouvelle réservation !';
    $message = "L'utilisateur " . $user_info->user_login . " a fait une réservation pour le " . $date . " à " . $time_slot . ".";
    
    $headers[] = 'From: Formulaire de réservation <reservation@omniwebsolutions.fr>';
    
    if(!wp_mail($to, $subject, $message, $headers)){
        error_log('L\'email de notification de réservation n\'a pas pu être envoyé.');
    } else {
        error_log('Email de notification de réservation envoyé avec succès.');
    }
}

add_action( 'admin_menu', 'back_office_page' );