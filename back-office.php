<?php
function back_office_page() {
    add_menu_page(
        'Back-office',
        'Back-office',
        'manage_options',
        'back-office',
        'back_office_page_html',
        'dashicons-calendar-alt',
        6
    );
}

function display_reservation(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservations';
    $reservations = $wpdb->get_results("SELECT * FROM $table_name");
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Id</th><th>Id utilisateur</th><th>Date</th><th>Heure</th></tr></thead>';
    echo '<tbody>';
    foreach ($reservations as $reservation) {
        echo '<tr><td>' . $reservation->id . '</td><td>' . $reservation->user_id . '</td><td>' . $reservation->reservation_date . '</td><td>' . $reservation->time_slot . '</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';
}