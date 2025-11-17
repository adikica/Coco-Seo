<?php
declare(strict_types=1);

namespace CocoSEO\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bing Webmaster + IndexNow integration for Coco SEO
 *
 * - Bing meta verification
 * - Sitemap ping
 * - IndexNow auto-submit on publish/update
 */
final class Bing_IndexNow
{
    private const OPT_KEY   = 'coco_bing_indexnow_options';
    private const CRON_HOOK = 'coco_bing_indexnow_daily_ping';

    /**
     * Bootstrap: register all runtime hooks
     */
    public static function register(): void
    {
        // Rewrite rule for virtual key file
        add_action('init', [self::class, 'add_rewrite_rule']);

        // Bing verification meta tag
        add_action('wp_head', [self::class, 'render_bing_meta'], 1);

        // Admin UI + settings
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_init', [self::class, 'register_settings']);

        // Auto IndexNow submit on publish/update
        add_action('transition_post_status', [self::class, 'on_post_transition'], 10, 3);

        // Daily sitemap ping (cron)
        add_action(self::CRON_HOOK, [self::class, 'daily_sitemap_ping']);

        // Virtual IndexNow key file endpoint
        add_action('template_redirect', [self::class, 'maybe_serve_virtual_key_file']);

        // Optional REST endpoints
        add_action('rest_api_init', [self::class, 'register_rest']);
    }

    /**
     * Called from main plugin activation
     */
    public static function on_activate(): void
    {
        // Ensure daily cron for sitemap ping
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }

        // Ensure rewrite rule is registered before flush
        self::add_rewrite_rule();
    }

    /**
     * Called from main plugin deactivation
     */
    public static function on_deactivate(): void
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    /* ---------------------------------------------------------------------
     * Options helpers
     * ------------------------------------------------------------------ */

    /**
     * Get all Bing/IndexNow options (with defaults)
     *
     * @return array{
     *   bing_meta:string,
     *   sitemap_path:string,
     *   indexnow_key:string,
     *   key_strategy:string
     * }
     */
    private static function opts(): array
    {
        $defaults = [
            'bing_meta'    => '',
            'sitemap_path' => 'sitemap.xml',
            'indexnow_key' => '',
            'key_strategy' => 'auto', // auto | filesystem | virtual
        ];

        $opts = get_option(self::OPT_KEY, []);
        if (!is_array($opts)) {
            $opts = [];
        }

        /** @var array<string,string> $merged */
        $merged = array_merge($defaults, $opts);

        return $merged;
    }

    private static function home_url_nice(string $path = ''): string
    {
        return trailingslashit(home_url()) . ltrim($path, '/');
    }

    /* ---------------------------------------------------------------------
     * Bing meta tag
     * ------------------------------------------------------------------ */

    public static function render_bing_meta(): void
    {
        $o    = self::opts();
        $code = trim($o['bing_meta']);

        if ($code === '') {
            return;
        }

        // Bing expects: <meta name="msvalidate.01" content="...">
        echo "\n<meta name=\"msvalidate.01\" content=\"" . esc_attr($code) . "\">\n";
    }

    /* ---------------------------------------------------------------------
     * Admin UI
     * ------------------------------------------------------------------ */

    public static function admin_menu(): void
    {
        // Attach under Coco SEO main menu
        add_submenu_page(
            'coco-seo',
            __('Bing & IndexNow', 'coco-seo'),
            __('Bing & IndexNow', 'coco-seo'),
            'manage_options',
            'coco-bing-indexnow',
            [self::class, 'settings_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting(
            self::OPT_KEY,
            self::OPT_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => function (array $input): array {
                    $out                  = self::opts();
                    $out['bing_meta']     = sanitize_text_field($input['bing_meta'] ?? '');
                    $out['sitemap_path']  = ltrim(
                        sanitize_text_field($input['sitemap_path'] ?? 'sitemap.xml'),
                        '/'
                    );
                    $out['indexnow_key']  = preg_replace(
                        '~[^a-zA-Z0-9]~',
                        '',
                        (string)($input['indexnow_key'] ?? '')
                    );
                    $strategy             = $input['key_strategy'] ?? 'auto';
                    $out['key_strategy']  = in_array($strategy, ['auto', 'filesystem', 'virtual'], true)
                        ? $strategy
                        : 'auto';

                    // Try persisting key file if strategy allows and key present
                    if (
                        $out['indexnow_key'] !== '' &&
                        in_array($out['key_strategy'], ['auto', 'filesystem'], true)
                    ) {
                        self::ensure_key_file($out['indexnow_key']);
                    }

                    return $out;
                },
            ]
        );
    }

    public static function settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $o          = self::opts();
        $sitemapUrl = esc_url(self::home_url_nice($o['sitemap_path']));

        $key              = $o['indexnow_key'];
        $keyUrlFilesystem = $key ? esc_url(trailingslashit(home_url()) . "{$key}.txt") : '';
        $keyUrlVirtual    = $key ? esc_url(trailingslashit(home_url()) . "indexnow-{$key}.txt") : '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bing Webmaster & IndexNow', 'coco-seo'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPT_KEY);
                $opts = self::opts();
                ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="bing_meta">
                                <?php esc_html_e('Bing Verification Code', 'coco-seo'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                name="<?php echo esc_attr(self::OPT_KEY); ?>[bing_meta]"
                                id="bing_meta"
                                type="text"
                                class="regular-text"
                                value="<?php echo esc_attr($opts['bing_meta']); ?>"
                                placeholder="e.g. 1234567890ABCDEF1234567890ABCDE"
                            />
                            <p class="description">
                                <?php esc_html_e(
                                    'Paste the value from Bing (“msvalidate.01” content). Meta tag is injected into <head>.',
                                    'coco-seo'
                                ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sitemap_path">
                                <?php esc_html_e('Sitemap Path', 'coco-seo'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                name="<?php echo esc_attr(self::OPT_KEY); ?>[sitemap_path]"
                                id="sitemap_path"
                                type="text"
                                class="regular-text"
                                value="<?php echo esc_attr($opts['sitemap_path']); ?>"
                                placeholder="sitemap.xml"
                            />
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s = sitemap url */
                                    esc_html__(
                                        'Absolute under site root. Full URL will be: %s',
                                        'coco-seo'
                                    ),
                                    '<code>' . esc_html($sitemapUrl) . '</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="indexnow_key">
                                <?php esc_html_e('IndexNow Key', 'coco-seo'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                name="<?php echo esc_attr(self::OPT_KEY); ?>[indexnow_key]"
                                id="indexnow_key"
                                type="text"
                                class="regular-text"
                                value="<?php echo esc_attr($opts['indexnow_key']); ?>"
                                placeholder="Generate a 32-char key (A–Z, a–z, 0–9)"
                            />
                            <p class="description">
                                <?php
                                printf(
                                    wp_kses_post(
                                        __(
                                            'Create at <a href="https://www.indexnow.org/" target="_blank" rel="noopener">indexnow.org</a> (or generate a random 32–128 char alnum). We’ll host <code>{key}.txt</code> for verification.',
                                            'coco-seo'
                                        )
                                    )
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Key File Strategy', 'coco-seo'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <?php
                                $strategies = [
                                    'auto'       => __('Auto (try filesystem; fallback to virtual endpoint)', 'coco-seo'),
                                    'filesystem' => __('Filesystem only (write {key}.txt into webroot)', 'coco-seo'),
                                    'virtual'    => __('Virtual endpoint only (no disk write)', 'coco-seo'),
                                ];
                                foreach ($strategies as $val => $label) : ?>
                                    <label style="display:block;margin:.2rem 0;">
                                        <input
                                            type="radio"
                                            name="<?php echo esc_attr(self::OPT_KEY); ?>[key_strategy]"
                                            value="<?php echo esc_attr($val); ?>"
                                            <?php checked($opts['key_strategy'], $val); ?>
                                        />
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <?php if ($key) : ?>
                                <p class="description">
                                    <?php esc_html_e('Filesystem URL (if available):', 'coco-seo'); ?>
                                    <code><?php echo esc_html($keyUrlFilesystem); ?></code><br>
                                    <?php esc_html_e('Virtual URL:', 'coco-seo'); ?>
                                    <code><?php echo esc_html($keyUrlVirtual); ?></code>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'coco-seo')); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Manual Actions', 'coco-seo'); ?></h2>
            <p>
                <a
                    href="<?php echo esc_url(
                        wp_nonce_url(
                            add_query_arg(['coco_bing_action' => 'ping_sitemap']),
                            'coco_bing_ping'
                        )
                    ); ?>"
                    class="button button-primary"
                >
                    <?php esc_html_e('Ping Bing with Sitemap', 'coco-seo'); ?>
                </a>

                <a
                    href="<?php echo esc_url(
                        wp_nonce_url(
                            add_query_arg(['coco_bing_action' => 'test_indexnow']),
                            'coco_bing_test'
                        )
                    ); ?>"
                    class="button"
                >
                    <?php esc_html_e('Send Test IndexNow (Home URL)', 'coco-seo'); ?>
                </a>
            </p>

            <?php self::handle_admin_actions(); ?>

            <h2><?php esc_html_e('Tips', 'coco-seo'); ?></h2>
            <ul>
                <li><?php esc_html_e(
                    'In Bing Webmaster Tools, add your site, choose “Meta tag”, paste the code above, then “Verify”.',
                    'coco-seo'
                ); ?></li>
                <li><?php
                    printf(
                        esc_html__(
                            'Ensure your sitemap is reachable at %s.',
                            'coco-seo'
                        ),
                        '<code>' . esc_html($sitemapUrl) . '</code>'
                    );
                    ?></li>
                <li><?php esc_html_e(
                    'IndexNow key file must be publicly reachable as {key}.txt in the site root. This plugin can serve it even if the filesystem is read-only.',
                    'coco-seo'
                ); ?></li>
            </ul>
        </div>
        <?php
    }

    private static function handle_admin_actions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!isset($_GET['coco_bing_action'])) {
            return;
        }

        $action = sanitize_key((string)($_GET['coco_bing_action'] ?? ''));

        if ($action === 'ping_sitemap' && check_admin_referer('coco_bing_ping')) {
            $ok = self::ping_sitemap();

            add_settings_error(
                'coco_bing_indexnow',
                'ping',
                $ok
                    ? __('Sitemap pinged successfully.', 'coco-seo')
                    : __('Sitemap ping failed. Check sitemap URL.', 'coco-seo'),
                $ok ? 'updated' : 'error'
            );
            settings_errors('coco_bing_indexnow');
        }

        if ($action === 'test_indexnow' && check_admin_referer('coco_bing_test')) {
            $ok = self::indexnow_submit([home_url('/')]);

            add_settings_error(
                'coco_bing_indexnow',
                'indexnow',
                $ok
                    ? __('IndexNow test sent.', 'coco-seo')
                    : __('IndexNow test failed. Check key and key file URL.', 'coco-seo'),
                $ok ? 'updated' : 'error'
            );
            settings_errors('coco_bing_indexnow');
        }
    }

    /* ---------------------------------------------------------------------
     * Sitemap ping
     * ------------------------------------------------------------------ */

    public static function daily_sitemap_ping(): void
    {
        self::ping_sitemap();
    }

    private static function sitemap_url(): string
    {
        $o = self::opts();

        return self::home_url_nice($o['sitemap_path']);
    }

private static function ping_sitemap(): bool
{
    $url = self::sitemap_url();

    // Just verify that the sitemap URL returns a 2xx/3xx status.
    $res = wp_remote_head($url, ['timeout' => 10]);

    if (is_wp_error($res)) {
        // Optional: log for debugging
        // error_log('Coco SEO: Sitemap HEAD failed: ' . $res->get_error_message());
        return false;
    }

    $code = (int) wp_remote_retrieve_response_code($res);

    // Consider 2xx and 3xx as “OK”
    return $code >= 200 && $code < 400;
}

    /* ---------------------------------------------------------------------
     * IndexNow submission
     * ------------------------------------------------------------------ */

    public static function on_post_transition(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($post->post_type === 'revision' || $post->post_status === 'auto-draft') {
            return;
        }

        // Submit on publish or update to 'publish'
        $became_public  = ($old_status !== 'publish' && $new_status === 'publish');
        $updated_public = ($old_status === 'publish' && $new_status === 'publish');

        if (!$became_public && !$updated_public) {
            return;
        }

        $url = get_permalink($post);
        if (!$url) {
            return;
        }

        self::indexnow_submit([$url]);
    }

    private static function indexnow_submit(array $urls): bool
    {
        $urls = array_values(array_filter(array_map('esc_url_raw', $urls)));
        if (empty($urls)) {
            return false;
        }

        $o   = self::opts();
        $key = $o['indexnow_key'];

        if ($key === '') {
            return false;
        }

        // Determine keyLocation (prefer filesystem, else virtual)
        $keyLocation = trailingslashit(home_url()) . "{$key}.txt";
        if ($o['key_strategy'] === 'virtual') {
            $keyLocation = trailingslashit(home_url()) . "indexnow-{$key}.txt";
        }

        $body = [
            'host'        => wp_parse_url(home_url(), PHP_URL_HOST),
            'key'         => $key,
            'keyLocation' => $keyLocation,
            'urlList'     => $urls,
        ];

        $res = wp_remote_post(
            'https://api.indexnow.org/indexnow',
            [
                'timeout' => 12,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body'    => wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );

        if (is_wp_error($res)) {
            return false;
        }

        $code = (int)wp_remote_retrieve_response_code($res);

        return in_array($code, [200, 202], true);
    }

    /* ---------------------------------------------------------------------
     * Key file handling
     * ------------------------------------------------------------------ */

    public static function ensure_key_file(string $key): void
    {
        $path = trailingslashit(ABSPATH) . "{$key}.txt";

        if (file_exists($path)) {
            return;
        }

        // Try writing; if fails, virtual endpoint will handle it.
        @file_put_contents($path, $key);
    }

    public static function add_rewrite_rule(): void
    {
        add_rewrite_rule('^indexnow-([A-Za-z0-9]+)\.txt$', 'index.php?indexnow_key=$matches[1]', 'top');
        add_rewrite_tag('%indexnow_key%', '([A-Za-z0-9]+)');
    }

    public static function maybe_serve_virtual_key_file(): void
    {
        $reqKey = get_query_var('indexnow_key');
        if (!$reqKey) {
            return;
        }

        $o = self::opts();

        if (!hash_equals($o['indexnow_key'] ?: '', (string)$reqKey)) {
            status_header(404);
            exit;
        }

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        echo $o['indexnow_key'];
        exit;
    }

    /* ---------------------------------------------------------------------
     * REST endpoints (optional)
     * ------------------------------------------------------------------ */

    public static function register_rest(): void
    {
        register_rest_route('coco/v1', '/indexnow', [
            'methods'             => 'POST',
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
            'callback'            => static function (\WP_REST_Request $req) {
                $urls = (array)$req->get_param('urls');

                return rest_ensure_response(['ok' => self::indexnow_submit($urls)]);
            },
        ]);

        register_rest_route('coco/v1', '/ping-sitemap', [
            'methods'             => 'POST',
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
            'callback'            => static function () {
                return rest_ensure_response(['ok' => self::ping_sitemap()]);
            },
        ]);
    }
}
