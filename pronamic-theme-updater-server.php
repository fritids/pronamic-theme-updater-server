<?php



// Get the server
$theme_server = new Pronamic_Theme_Updater_Server();

// Configuration
$theme_server
		->set_url( 'http://themes.pronamic.nl/index.php' )
		->set_user_agent( 'PronamicWordpressThemeUpdate' );

// Add a theme
$theme_server
		->add_theme( 'emg', 'flyingDolphin', array(
			'file' => 'themes/emg.zip',
			'file_name' => 'emg',
			'version' => '1.3',
			'url' => 'http://www.pronamic.nl'
		) );

// Start it!
$theme_server->listen();


/**
 * Theme Updater Server
 *
 * @see http://github.com/pronamic/pronamic-theme-updater-client
 *
 * ==================
 * Instructions
 * ==================
 *
 * 1. Put this on your server
 * 2. Set the server configuration ( see above )
 * 3. Add a theme
 *
 *		$theme_server->add_theme( 'slug', 'secure_phrase', array(
 *			'file' => 'location/to/file.zip'
 *			'version' => 'version number for comparison',
 *			'url' => 'a url to open on View Version Details' on client
 *		) );
 *
 *  4. $theme_server->listen();
 *
 * ==================
 *
 * @author Leon Rowland <leon@rowland.nl>
 * @copyright (c) 2013, Leon Rowland
 * @license GPL
 * @version 1.0.0
 */
class Pronamic_Theme_Updater_Server {
	private $url = '';
	private $request_user_agent;
	private $user_agent;
	private $themes = array();

	public function __construct() {
		$this->request_user_agent = $_SERVER['HTTP_USER_AGENT'];
	}

	/**
	 * Sets the URL of this server.
	 *
	 * @access public
	 * @param string $url
	 * @return \Pronamic_Theme_Updater_Server
	 */
	public function set_url( $url ) {
		$this->url = $url;
		return $this;
	}

	/**
	 * Sets the user agent that the server is looking for
	 * in all requests.
	 *
	 * @access public
	 * @param string $user_agent
	 * @return \Pronamic_Theme_Updater_Server
	 */
	public function set_user_agent( $user_agent ) {
		$this->user_agent = $user_agent;
		return $this;
	}

	/**
	 * Listens to all requests and determines the course of action,
	 * if any.
	 *
	 * @access public
	 * @return void
	 */
	public function listen() {
		// Checks the user agents match
		//if ( $this->user_agent == $this->request_user_agent ) {
			// Determine if a key request has been made
			if ( isset( $_GET['key'] ) ) {
				$this->download_update();
			} else {
				// Determine the action requested
				$action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );

				// If that method exists, call it
				if ( method_exists( $this, $action ) )
					$this->{$action}();
			}
		//s}
	}

	/**
	 * Is ran when the key request has been made.  It will verify
	 * the key is valid, and then get the correct theme information
	 * and finially fill the response with the contents of the file
	 * set by the theme.
	 *
	 * @access public
	 * @return echoed contents
	 */
	public function download_update() {

		// Get the secure key
		$secureKey = $_GET['key'];

		// Check the key is a valid key, and has a theme associated
		if ( array_key_exists( $secureKey, $this->securePhrases ) ) {
			// Get the theme name
			$themeName = $this->securePhrases[$secureKey];

			// Get the theme information
			$theme = $this->themes[$themeName];
			// Check the theme file exists
			if ( file_exists( $theme['file'] ) ) {

				if(ini_get('zlib.output_compression')) {
					@ini_set('zlib.output_compression', 'Off');
				}

				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename="' . str_replace( " ", "_", $theme['file_name'] ) . '"');
				header('Content-Transfer-Encoding: binary');
				header("Content-Length: " . filesize( $theme['file'] ));
				header("Content-MD5: " . md5(filesize( $theme['file'] )) );

				readfile($theme['file']);
				exit;
			}
		}
	}

	/**
	 * Gets the theme update information, include the package
	 * location and the url for information on the update.
	 *
	 * @access publiuc
	 * @return echoed serialized array
	 */
	public function theme_update() {
		// Get the requested arguments
		$arguments = unserialize( $_POST['request'] );

		// Check slug is set
		if ( ! isset( $arguments['slug'] ) )
			return;

		// Determine if slug exists in the registered themes
		if ( array_key_exists( $arguments['slug'], $this->themes ) ) {

			// Get the theme information
			$theme = $this->themes[$arguments['slug']];

			// Remove the reference to the file
			unset( $theme['file'] );

			// Check its version is greater than the requested version, and show theme data if so
			if (version_compare( $arguments['version'], $theme['version'], '<' ) )
				echo serialize( $theme );
		}
	}

	/**
	 * Sets a theme to be updatable.
	 *
	 * The first parameter must match the name of the folder of the theme
	 * you are updating.
	 *
	 * NOTE: Inside your zip file you must also have the exact same folder name
	 *
	 * The second parameter is a secure phrase for this download.  It allows
	 * you to have unique links for each time you have a new version.
	 *
	 * Third parameter is a collection of the required data for the theme
	 * This includes:
	 *
	 * file => 'location/to/file.zip'
	 * version => 'version number'
	 * url => 'url to page with update notes or site'
	 *
	 * @see http://github.com/pronamic/pronamic-theme-updater-server#add
	 *
	 * @access public
	 * @param string $themeSlug
	 * @param string $secureDownloadPhrase
	 * @param array $themeData
	 * @return \Pronamic_Theme_Updater_Server
	 */
	public function add_theme( $themeSlug, $secureDownloadPhrase, $themeData ) {
		// Generate a secure key, with the pass phrase
		$key = $this->generate_secure_key( $secureDownloadPhrase );

		// Generate the package url with that key
		$themeData['package'] = $this->url . '?key=' . $key;

		// Set the theme information
		$themeData['new_version'] = $themeData['version'];
		$themeData['file_name'] = $themeSlug . '.zip';
		$this->themes[$themeSlug] = $themeData;

		// Set the secure phrase
		$this->securePhrases[$key] = $themeSlug;

		return $this;
	}

	private function generate_secure_key( $secure_download_phrase ) {
		return md5( $secure_download_phrase );
	}
}