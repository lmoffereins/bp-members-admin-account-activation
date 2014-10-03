<?php

/**
 * The BuddyPress Members Admin Account Activation Plugin
 *
 * @package BP Members Admin Account Activation
 * @subpackage Main
 */

/**
 * Plugin Name:       BP Members Admin Account Activation
 * Description:       Require admin approval for BP account activations
 * Plugin URI:        https://github.com/lmoffereins/bp-members-admin-account-activation/
 * Version:           1.0.0
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins/
 * Text Domain:       bp-members-admin-account-activation
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/bp-members-admin-account-activation
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_Members_Admin_Account_Activation' ) ) :
/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
final class BP_Members_Admin_Account_Activation {

	/**
	 * Main Plugin Instance
	 *
	 * @since 1.0.0
	 *
	 * @uses BP_Members_Admin_Account_Activation::setup_globals() Setup the globals needed
	 * @uses BP_Members_Admin_Account_Activation::setup_actions() Setup the hooks and actions
	 * @see bp_members_admin_account_activation()
	 * @return The one true Plugin
	 */
	public static function instance() {

		// Store the instance locally
		static $instance = null;

		// Only run these methods if they haven't been ran previously
		if ( null === $instance ) {
			$instance = new BP_Members_Admin_Account_Activation;
			$instance->setup_actions();
		}

		// Always return the instance
		return $instance;
	}

	/**
	 * A dummy constructor to prevent Plugin from being loaded more than once.
	 *
	 * @since 1.0.0
	 */
	private function __construct() { /* Do nothing here */ }

	/** Private Methods *******************************************************/

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Modify activation mail
		add_filter( 'bp_core_signup_send_validation_email_to',      array( $this, 'admin_mail_to'      ), 10, 2 );
		add_filter( 'bp_core_signup_send_validation_email_subject', array( $this, 'admin_mail_subject' ), 10, 2 );
		add_filter( 'bp_core_signup_send_validation_email_message', array( $this, 'admin_mail_message' ), 10, 3 );

		// Notify signed-up user
		add_action( 'bp_core_sent_user_validation_email', array( $this, 'notify_signedup_user' ), 10, 5 );

		// Act on activation
		add_action( 'bp_core_activated_user', array( $this, 'notify_activated_user' ), 10, 3 );

		// Filters
		add_filter( 'bpmaaa_signedup_user_mail_subject',  array( $this, 'parse_shortcodes' ) );
		add_filter( 'bpmaaa_signedup_user_mail_message',  array( $this, 'parse_shortcodes' ) );
		add_filter( 'bpmaaa_signedup_admin_mail_subject', array( $this, 'parse_shortcodes' ) );
		add_filter( 'bpmaaa_signedup_admin_mail_message', array( $this, 'parse_shortcodes' ) );
		add_filter( 'bpmaaa_activated_user_mail_subject', array( $this, 'parse_shortcodes' ) );
		add_filter( 'bpmaaa_activated_user_mail_message', array( $this, 'parse_shortcodes' ) );
	}

	/** Public Methods ********************************************************/

	/**
	 * Modify activation mail destination
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_email Mail destination
	 * @param int $user_id ID of the new user
	 * @return string Activation mail destination
	 */
	public function admin_mail_to( $user_email, $user_id ) {

		// Get admin email
		$admin_email = get_option( 'admin_email' );

		return $admin_email;
	}

	/**
	 * Modify activation mail destination
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_email Mail destination
	 * @param int $user_id ID of the new user
	 * @return string Activation mail destination
	 */
	public function admin_mail_subject( $subject, $user_id ) {
		$subject = $this->get_signedup_admin_mail_subject( $user_id );

		return $subject;
	}

	/**
	 * Modify activation mail destination
	 *
	 * @since 1.0.0
	 *
	 * @param string $user_email Mail destination
	 * @param int $user_id ID of the new user
	 * @param string $activate_url The activation url
	 * @return string Activation mail destination
	 */
	public function admin_mail_message( $message, $user_id, $activate_url ) {
		$message = $this->get_signedup_admin_mail_message( $user_id, $activate_url );

		return $message;
	}

	/**
	 * Send a mail to the newly signed-up user after the account activation email
	 *
	 * @since 1.0.0
	 *
	 * @param string $subject Activation mail subject
	 * @param string $message Activation mail message
	 * @param int $user_id ID of the signed-up user
	 * @param string $user_email Email of the signed-up user
	 * @param string $key Activation key
	 */
	public function notify_signedup_user( $subject, $message, $user_id, $user_email, $key ) {

		// Set email elements
		$subject = $this->get_signedup_user_mail_subject( $user_id );
		$message = $this->get_signedup_user_mail_message( $user_id, $key );

		wp_mail( $user_email, $subject, $message );
	}

	/**
	 * Send a mail to the newly activated user after account activation
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id ID of the new user
	 * @param string $key Activation key
	 * @param WP_User $user Object of the new user
	 */
	public function notify_activated_user( $user_id, $key, $user ) {

		// Set email elements
		$subject = $this->get_activated_user_mail_subject( $user_id );
		$message = $this->get_activated_user_mail_message( $user_id );

		// Get the new user's data
		$user = get_userdata( $user_id );

		wp_mail( $user->user_email, $subject, $message );
	}

	/** Mail ******************************************************************/

	/**
	 * Return the subject of the mail that is sent to the signed-up user
	 *
	 * @since 1.0.0
	 *
	 * @return string Email subject
	 */
	public function get_signedup_user_mail_subject( $user_id ) {
		$subject = __( 'Je hebt je aangemeld op [site_name]!', 'bp-members-admin-account-activation' );

		return apply_filters( 'bpmaaa_signedup_user_mail_subject', $subject, $user_id );
	}

	/**
	 * Return the message body of the mail that is sent to the signed-up user
	 *
	 * @since 1.0.0
	 *
	 * @return string Email message
	 */
	public function get_signedup_user_mail_message( $user_id, $key ) {
		$message = __( "
Welkom op [site_name]!\n\n
We hebben je aanmelding als professional ontvangen. Onze beheerders gaan nu zo snel mogelijk de aanvraag beoordelen. We verwachten binnen één werkdag met een reactie te komen.\n\n
Zodra de aanvraag wordt goedgekeurd, krijg je bericht dat je met de gekozen gegevens kan inloggen. Als ingelogde gebruiker heb je toegang tot de profielen en adresgegevens van de professionals.\n\n
Met vriendelijke groet,\n\n
Het team van [site_name]
			", 'bp-members-admin-account-activation' );

		return apply_filters( 'bpmaaa_signedup_user_mail_subject', $message, $user_id );
	}

	/**
	 * Return the subject of the mail that is send to the admin after sign-up
	 *
	 * @since 1.0.0
	 *
	 * @return string Email subject
	 */
	public function get_signedup_admin_mail_subject( $user_id ) {
		$subject = __( 'Een nieuwe aanmelding voor [site_name]', 'bp-members-admin-account-activation' );

		return apply_filters( 'bpmaaa_signedup_admin_mail_subject', $subject, $user_id );
	}

	/**
	 * Return the message body of the mail that is send to the admin after sign-up
	 *
	 * @since 1.0.0
	 *
	 * @return string Email message
	 */
	public function get_signedup_admin_mail_message( $user_id, $activate_url ) {
		$user = get_userdata( $user_id );

		$message = sprintf( __("
Iemand heeft zich aangemeld op [site_name] met de volgende gegevens:\n\n
Login: %1\$s\n
E-mail: %2\$s\n\n
Je kunt het account activeren door op de volgende link te klikken:\n\n
%3\$s\n\n
Of ga naar de WordPress dashboard > Gebruikers > Beheer Inschrijvingen om de nieuwe accounts te beheren.\n\n
		", 'bp-members-admin-account-activation' ), $user->user_login, $user->user_email, $activate_url );

		return apply_filters( 'bpmaaa_signedup_admin_mail_message', $message, $user_id, $activate_url );
	}

	/**
	 * Return the subject of the mail that is send to the activated user
	 *
	 * @since 1.0.0
	 *
	 * @return string Email subject
	 */
	public function get_activated_user_mail_subject( $user_id ) {
		$subject = __( 'Je account is geactiveerd op [site_name]', 'bp-members-admin-account-activation' );

		return apply_filters( 'bpmaaa_activated_user_mail_subject', $subject, $user_id );
	}

	/**
	 * Return the message body of the mail that is send to the activated user
	 *
	 * @since 1.0.0
	 *
	 * @return string Email message
	 */
	public function get_activated_user_mail_message( $user_id ) {

		// Get the new user's data
		$user = get_userdata( $user_id );

		$message = sprintf( __("
We hebben je aanmelding als professional ontvangen en goedgekeurd.\n
Om je account te bekijken en te bewerken dien je in te loggen met de volgende gegevens:\n\n
Login: %1\$s\n
Wachtwoord: je gekozen wachtwoord\n\n
Ga dan naar Je Profiel of ga naar Professionals om de andere profielen te bekijken.\n\n
Met vriendelijke groet,\n\n
Het team van [site_name]
		", 'bp-members-admin-account-activation' ), $user->user_login );

		return apply_filters( 'bpmaaa_activated_user_mail_subject', $message, $user_id );
	}

	/**
	 * Parse custom shortcodes on the content
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Parsable content
	 * @param int $user_id Optional. User ID
	 * @param string $activate_url Optional. Activation url
	 * @return string Parsed content
	 */
	public function parse_shortcodes( $content, $user_id = 0, $activate_url = '' ) {

		// Get the user's data
		// $user = get_userdata( $user_id );

		// The available shortcodes
		$codes = apply_filters( 'bpmaaa_shortcodes', array(
			// 'user_name'       => $user->user_login . ' (' . $user->user_email . ')',
			'user_link'       => bp_core_get_user_domain( $user_id ),
			'site_name'       => bp_get_email_subject( array( 'before' => '', 'after' => '' ) ),
			'site_link'       => home_url(),
			'activation_link' => ! empty( $activate_url ) ? sprintf( '<a href="%">%s</a>', $activate_url, __( 'Activate the account', 'bp-members-admin-account-activation' ) ) : '',
			'activation_url'  => ! empty( $activate_url ) ? $activate_url : '',
		) );

		// Replace the codes
		foreach ( $codes as $code => $replace_with ) {
			$content = str_replace( '[' . $code . ']', trim( $replace_with ), $content );
		}

		return $content;
	}
}

/**
 * The main function responsible for returning the one true Plugin Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @return The one true Plugin Instance
 */
function bp_members_admin_account_activation() {
	return BP_Members_Admin_Account_Activation::instance();
}

// Do the magic
bp_members_admin_account_activation();

endif; // class_exists
