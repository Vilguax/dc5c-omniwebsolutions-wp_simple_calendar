<?php
/**
 * Plugin Name: Simple Calendar
 * Description: Une simple extension de calendrier interactif
 * Version: 1.0
 * Author: Axel PELASSA
 * Author URI: https://omniwebsolutions.fr
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/


// Sécurité: Empêcher l'accès direct aux fichiers
defined('ABSPATH') or die('Access denied.');

// Inclusion du fichier back-office.php
require_once plugin_dir_path( __FILE__ ) . 'back-office.php';

// Hook d'activation pour créer la table de réservation
register_activation_hook(__FILE__, 'create_reservation_table');

// Fonction pour créer la table lors de l'activation du plugin
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

// Fonction pour afficher le calendrier
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

// Shortcode pour afficher le calendrier
add_shortcode('simple_calendar', 'display_simple_calendar');

// Fonction pour enregistrer les scripts et les styles
function enqueue_my_scripts() {
    if(is_page('calendar')) { 
        wp_enqueue_script('my-calendar', plugin_dir_url(__FILE__) . 'calendar.js', array('jquery'), null, true);
        wp_localize_script('my-calendar', 'my_script_vars', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
}

// Enregistrer les scripts et les styles
add_action('wp_enqueue_scripts', 'enqueue_my_scripts');

// Hook pour enregistrer les réservations
add_action('wp_ajax_reserve_slots', 'reserve_slots_handler');

// Fonction pour enregistrer les réservations
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
    // Récupération de l'URL du site pour rediriger l'utilisateur après la réservation
    $site_url = get_site_url();
    $redirect_url = $site_url . '/calendar/remerciement/';
    wp_send_json_success(array('redirect_url' => $redirect_url));
}

add_action('wp_ajax_get_reserved_slots', 'get_reserved_slots_handler');

// Fonction pour récupérer les créneaux réservés
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

// Fonction pour envoyer un email de notification de réservation à l'adresse d'administration de part l'adresse reservation@domaine
function send_reservation_email($user_id, $date, $time_slot) {
    $user_info = get_userdata($user_id);
    $to = get_option('admin_email');
    $subject = 'Nouvelle réservation !';
    $message = "L'utilisateur " . $user_info->user_login . " a fait une réservation pour le " . $date . " à " . $time_slot . ".";
    
    $site_url = get_site_url();
    $domain = parse_url($site_url, PHP_URL_HOST);
    $headers[] = 'From: Formulaire de réservation <reservation@' . $domain . '>';
    
    if(!wp_mail($to, $subject, $message, $headers)){
        error_log('L\'email de notification de réservation n\'a pas pu être envoyé.');
    } else {
        error_log('Email de notification de réservation envoyé avec succès.');
    }
}

// Ajoute le menu "Réservation" dans le back-office
add_action( 'admin_menu', 'back_office_page' );