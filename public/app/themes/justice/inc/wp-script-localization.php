<?php

namespace MOJ\Justice;

defined('ABSPATH') || exit;

/**
 * Class to convert localization scripts from <script type="text/javascript">
 * to <script type="application/json"> and load them via a custom loader function.
 *
 * Unfortunately, the way the WordPress adds data to the global window object, for
 * enqueued scripts, is not compatible with a strict Content Security Policy (CSP).
 *
 * This class uses a custom WP_Scripts class to filter the inline scripts added
 * via wp_localize_script() and modify them to use type="application/json".
 * The localized data is then loaded via a custom JavaScript function
 * mojLoadLocalizedData() which is included in the script-localization.js file.
 *
 * @see justice/inc/wp-scripts.php
 * @see justice/src/js/script-localization.js
 */
class WpScriptLocalization
{
    // The handle for the loader script registered in registerLocalizeLoaderScript().
    const LOADER_HANDLE = 'moj-localize-loader';

    // Only allow certain script handles to be modified.
    // Warning! Keep in sync with mojLocalizedDataEntries in src/js/script-localization.js.
    const ALLOWED_SCRIPT_HANDLES = [
        'ccfw-script',
        'cookie-consent-script',
        'wp-sentry-browser',
    ];

    // Let's make a generic inline script to call the loader function.
    // Warning! If this is modified, then the CSP in Nginx's config must also be updated.
    const LOAD_DATA_INLINE_SCRIPT = "<script type='text/javascript'>" .
        "(function() { if (typeof mojLoadLocalizedData === 'function') { mojLoadLocalizedData(); } })();" .
        "</script>\n";


    public function __construct()
    {
        $this->addHooks();
    }


    /**
     * Add the necessary hooks to replace the global $wp_scripts object
     * with an instance of WpFilterableScripts and to filter the inline scripts.
     */
    public function addHooks(): void
    {
        // Initialise our custom WP_Scripts class.
        add_action('init', [$this, 'replaceWpScripts'], 100);

        // Load the script-localization.js script.
        // This script contains the mojLoadLocalizedData() function
        // which is used to load variables from script tags with type="application/json".
        add_action('wp_enqueue_scripts', [$this, 'registerLocalizeLoaderScript']);

        // Filter the inline scripts added via wp_localize_script().
        add_filter('wp_filterable_script_extra_tag', [$this, 'modifyInlineScripts'], 0, 3);

        // Add dependency by modifying the global $wp_scripts object.
        add_action('wp_enqueue_scripts', [$this, 'addMojLocalizeLoaderAsDependency'], 100);
    }


    /**
     * Replace the global $wp_scripts object with an instance of WpFilterableScripts.
     *
     * This class extends WP_Scripts and allows us to filter the inline scripts added
     * via `wp_localize_script()` or the localize method in `script-loader.php`.
     *
     * @see justice/inc/wp-scripts.php
     * @see justice/inc/amazon-s3-and-cloudfront-assets.php
     * @see wp/wp-includes/script-loader.php
     */
    public function replaceWpScripts(): void
    {
        $fscripts              = new WpFilterableScripts;
        $GLOBALS['wp_scripts'] = $fscripts;
    }


    /**
     * Load the localize loader script.
     *
     * This script is used to load localized data from script tags with type="application/json".
     * It is used to load data for scripts that are not registered in WordPress, such as Sentry.
     *
     * @return void
     */
    public function registerLocalizeLoaderScript(): void
    {
        $script_asset_path = get_template_directory() . '/dist/php/script-localization.min.asset.php';
        $script_uri = get_template_directory_uri() . '/dist/script-localization.min.js';

        $script_asset = file_exists($script_asset_path) ? require $script_asset_path : null;

        if (!is_array($script_asset) || !isset($script_asset['dependencies'], $script_asset['version'])) {
            wp_die(
                sprintf(
                    /* translators: %s: script-localization.min.asset.php */
                    __('The file %s is missing or invalid. Please run <code>npm run build</code> to create it.', 'justice'),
                    esc_html('script-localization.min.asset.php')
                ),
                __('Error', 'justice'),
                ['response' => 500]
            );
        }

        wp_register_script(
            self::LOADER_HANDLE,
            $script_uri,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }


    /**
     * Add the moj-localize-loader script as a dependency to allowed scripts.
     *
     * This ensures that, when the allowed scripts are enqueued, the
     * moj-localize-loader script is also enqueued before them.
     *
     * @return void
     */
    public function addMojLocalizeLoaderAsDependency(): void
    {
        global $wp_scripts;
        if (!$wp_scripts instanceof WpFilterableScripts) {
            return;
        }

        foreach (self::ALLOWED_SCRIPT_HANDLES as $handle) {
            if (isset($wp_scripts->registered[$handle])) {
                $wp_scripts->registered[$handle]->deps[] = self::LOADER_HANDLE;
            }
        }
    }


    /**
     * Modify inline scripts added via wp_localize_script().
     *
     * This is used to change the script tag for Sentry configuration
     * to use type="application/json" and call mojLoadLocalizedData()
     * to load the configuration into a global variable.
     *
     * @param string $value  The original inline script tag, including the wrapping <script> element.
     * @param string $handle The handle of the script the inline script is attached to.
     * @param array  $data   The localized data for the handle, keyed by object name.
     *
     * @return string The modified inline script tag.
     */
    public function modifyInlineScripts($value, $handle, $data)
    {
        if (!in_array($handle, self::ALLOWED_SCRIPT_HANDLES, true)) {
            return $value;
        }

        // No data was captured for this handle, e.g. it was localized before
        // the global $wp_scripts was replaced. Leave the original tag untouched.
        if (empty($data)) {
            return $value;
        }

        $tag = sprintf("<script type='application/json' id='%s-js-extra'>%s</script>\n", esc_attr($handle), wp_json_encode($data));

        $tag .= self::LOAD_DATA_INLINE_SCRIPT;

        return $tag;
    }
}
