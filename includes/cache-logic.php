<?php
/**
 * Memory Logger Pro v13.3.6 - Cache Logic
 * Anàlisi de configuració de cache: WP Rocket + SG Optimizer + altres
 * @package Memory Logger Pro
 * @version 13.3.6
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analitza configuració de cache detectant conflictes WP-Rocket / SG Optimizer
 * Reutilitza patró mlp_analyze_wordpress_plugins_unified amb cache + score
 * @return array
 */
function mlp_analyze_cache_config(): array {
    $cache_key = 'mlp_cache_analysis_' . md5(MLP_PATH);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $active = (array) get_option('active_plugins', []);
    $all_plugins = function_exists('get_plugins') ? get_plugins() : [];

    // Mapa de plugins de cache coneguts (SG té 2 noms històrics)
    $cache_plugins_map = [
        'wp-rocket/wp-rocket.php' => ['name' => 'WP Rocket', 'type' => 'page_cache'],
        'sg-cachepress/sg-cachepress.php' => ['name' => 'SG CachePress (antic)', 'type' => 'page_cache'],
        'sg-optimizer/sg-optimizer.php' => ['name' => 'SG Optimizer', 'type' => 'page_cache'],
        'siteground-optimizer/sg-optimizer.php' => ['name' => 'SiteGround Optimizer', 'type' => 'page_cache'],
        'litespeed-cache/litespeed-cache.php' => ['name' => 'LiteSpeed Cache', 'type' => 'page_cache'],
        'w3-total-cache/w3-total-cache.php' => ['name' => 'W3 Total Cache', 'type' => 'page_cache'],
        'wp-super-cache/wp-cache.php' => ['name' => 'WP Super Cache', 'type' => 'page_cache'],
        'wp-fastest-cache/wpFastestCache.php' => ['name' => 'WP Fastest Cache', 'type' => 'page_cache'],
        'autoptimize/autoptimize.php' => ['name' => 'Autoptimize', 'type' => 'optimization'],
        'asset-cleanup-pro/load.php' => ['name' => 'Asset CleanUp', 'type' => 'optimization'],
    ];

    $detected = [];
    foreach ($cache_plugins_map as $file => $info) {
        if (in_array($file, $active, true) || is_plugin_active($file)) {
            $ver = $all_plugins[$file]['Version'] ?? '?';
            $detected[$file] = ['name' => $info['name'], 'version' => $ver, 'type' => $info['type'], 'file' => $file];
        }
    }
    // MU-plugin fallback SG: només si no ja detectat com a plugin normal i no existeix carpeta sg-cachepress inactiva
    if (!isset($detected['siteground-optimizer/sg-optimizer.php']) && !isset($detected['sg-cachepress/sg-cachepress.php']) && class_exists('SiteGround_Optimizer\Options\Options')) {
        $detected['siteground-optimizer/sg-optimizer.php'] = ['name' => 'SiteGround Optimizer (MU)', 'version' => '?', 'type' => 'page_cache', 'file' => 'siteground-optimizer/sg-optimizer.php'];
    }
    // Neteja fals positiu: sg-cachepress inactiu però carpeta òrfena a wp-content/plugins/
    if (isset($detected['sg-cachepress/sg-cachepress.php']) && !is_plugin_active('sg-cachepress/sg-cachepress.php')) {
        // Verifica si realment està actiu o només carpeta residual
        if (!in_array('sg-cachepress/sg-cachepress.php', $active, true)) {
            unset($detected['sg-cachepress/sg-cachepress.php']);
        }
    }
    // Unifica SG antic + SG nou com un sol si coexisteixen
    if (isset($detected['sg-cachepress/sg-cachepress.php']) && isset($detected['siteground-optimizer/sg-optimizer.php'])) {
        unset($detected['sg-cachepress/sg-cachepress.php']); // SG Optimizer és el successor
    }

    $issues = [];
    $recommendations = [];
    $score = 100;
    $wp_rocket = null;
    $sg_settings = null;

    // --- WP Rocket analysis ---
    $has_wpr = isset($detected['wp-rocket/wp-rocket.php']);
    if ($has_wpr) {
        $wpr = get_option('wp_rocket_settings', []);
        $wp_rocket = $wpr;
        $reject = $wpr['cache_reject_uri'] ?? [];
        $cdn = (int)($wpr['cdn'] ?? 0);
        $cdn_type = $wpr['cdn_type'] ?? '';
        $lifespan = (int)($wpr['purge_cron_interval'] ?? 0);
        $unit = $wpr['purge_cron_unit'] ?? '';
        $minify_css = (int)($wpr['minify_css'] ?? 0);
        $minify_js = (int)($wpr['minify_js'] ?? 0);
        $delay_js = (int)($wpr['delay_js'] ?? 0);
        $rucss = (int)($wpr['remove_unused_css'] ?? 0);
        $cache_mobile = (int)($wpr['cache_mobile'] ?? 0);

        // Exclusió calendari = alt consum (cas bcnswing)
        $has_cal_exclusion = false;
        foreach ((array)$reject as $r) {
            if (stripos($r, 'calendari') !== false || stripos($r, 'mogudes') !== false || $r === '/mogudes/' || $r === '/calendari-de-mogudes/') {
                $has_cal_exclusion = true;
                break;
            }
        }
        if ($has_cal_exclusion) {
            $issues[] = ['level' => 'critical', 'title' => 'Calendari exclòs de cache', 'desc' => 'WP Rocket > Never Cache URL conté /calendari-de-mogudes/ o /mogudes/. Cada visita genera 60-80 queries TEC i dispara CPU. Solució: buidar exclusió i posar Lifespan 4h.', 'fix' => 'Treu /calendari-de-mogudes/ i /mogudes/ de Never Cache URL'];
            $score -= 30;
        }

        if ($lifespan === 0) {
            $issues[] = ['level' => 'warning', 'title' => 'Cache sense caducitat', 'desc' => 'WP Rocket Lifespan = 0 (mai caduca). La data del calendari quedarà obsoleta al mòbil.', 'fix' => 'Posa Lifespan 4h'];
            $score -= 10;
        } elseif ($lifespan >= 10 && $unit === 'HOUR_IN_SECONDS') {
            $issues[] = ['level' => 'warning', 'title' => 'Cache massa llarga (10h)', 'desc' => 'Amb Lifespan 10h el calendari mostra dies passats fins al cap de 10h.', 'fix' => 'Canvia a 4h (purge_cron_interval=4)'];
            $score -= 10;
        }

        if ($cdn === 1 && $cdn_type === 'rocketcdn') {
            // Si SG CDN actiu, és duplicat
            $has_sg = isset($detected['siteground-optimizer/sg-optimizer.php']) || isset($detected['sg-cachepress/sg-cachepress.php']);
            if ($has_sg) {
                $issues[] = ['level' => 'warning', 'title' => 'Doble CDN', 'desc' => 'WP Rocket RocketCDN actiu + SiteGround CDN (X-SG-CDN:1). Pagament duplicat i doble DNS.', 'fix' => 'Desactiva CDN a WP Rocket (cdn=0) i deixa SG CDN'];
                $score -= 10;
            }
        }

        if ($delay_js === 1) {
            $issues[] = ['level' => 'warning', 'title' => 'Delay JS actiu', 'desc' => 'Delay JS pot trencar TEC (tribe-events). Al teu cas ja està 0, correcte.', 'fix' => 'Deixa delay_js=0 i exclou tribe-events'];
            $score -= 5;
        }

        if ($rucss === 1) {
            $issues[] = ['level' => 'warning', 'title' => 'Remove Unused CSS actiu', 'desc' => 'RUCSS trenca The Events Calendar (tribe-events). Ja el tens 0, correcte.', 'fix' => 'Deixa remove_unused_css=0'];
            $score -= 5;
        }

        if (!$cache_mobile) {
            $issues[] = ['level' => 'warning', 'title' => 'Cache mòbil desactivada', 'desc' => 'WP Rocket cache_mobile=0, el calendari mòbil no es cacheja.', 'fix' => 'Activa cache_mobile i do_caching_mobile_files'];
            $score -= 5;
        }
    }

    // --- SG Optimizer analysis ---
    $has_sg = isset($detected['siteground-optimizer/sg-optimizer.php']) || isset($detected['sg-cachepress/sg-cachepress.php']);
    if ($has_sg) {
        // Intentar llegir opcions SG (varies claus segons versió)
        $sg_opts = get_option('siteground_optimizer_settings', []);
        if (empty($sg_opts)) $sg_opts = get_option('sg_optimizer_settings', []);
        if (empty($sg_opts)) $sg_opts = get_option('sgo_settings', []);
        // Check via constants / files
        $sg_settings = $sg_opts;

        // Detecció doble cache: File-Based + WP Rocket
        $has_wp_rocket_page_cache = $has_wpr;
        // Heurística: si existeix carpeta sg-cache
        $has_file_cache = is_dir(WP_CONTENT_DIR . '/cache/sg-cachepress') || is_dir(WP_CONTENT_DIR . '/cache/siteground-optimizer');
        if ($has_wp_rocket_page_cache && $has_file_cache) {
            // No podem saber segur si File-Based ON, però avisem
            $recommendations[] = 'Si SG Optimizer > Caching > File-Based Caching = ON i WP Rocket cache = ON, desactiva File-Based (deixa només Dynamic).';
        }

        // Memcached
        $memcached_active = false;
        if (class_exists('Memcached') || class_exists('Memcache')) {
            // Check si object cache actiu
            if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
                $memcached_active = true;
            }
        }
        // Check via SG option si disponible
        if (isset($sg_opts['memcached']) && $sg_opts['memcached']) {
            $memcached_active = true;
        }
        if (!$memcached_active) {
            $issues[] = ['level' => 'critical', 'title' => 'Memcached OFF', 'desc' => 'SG Optimizer > Memcached OFF. TEC fa desenes de queries per mes; sense object cache cada calendari impacta DB.', 'fix' => 'Activa SG > Caching > Memcached ON'];
            $score -= 20;
            $recommendations[] = 'Activa Memcached a SG Optimizer per reduir 50-70% CPU del calendari.';
        }
    }

    // --- Conflictes generals ---
    $page_caches = array_filter($detected, fn($p) => $p['type'] === 'page_cache');
    if (count($page_caches) >= 2) {
        $names = implode(' + ', array_column($page_caches, 'name'));
        $issues[] = ['level' => 'critical', 'title' => 'Doble page-cache', 'desc' => "Detectats " . count($page_caches) . " plugins de cache: $names. Provoquen triple escriptura HTML i purgues incoherents.", 'fix' => 'Deixa només 1 page-cache. A SiteGround: Dynamic ON + WP Rocket (File-Based OFF) o només SG.'];
        $score -= 25;
    }

    if (count($detected) === 0) {
        $issues[] = ['level' => 'warning', 'title' => 'Cap plugin de cache detectat', 'desc' => 'No s\'ha detectat cap plugin de cache actiu.', 'fix' => 'Activa SG Optimizer o WP Rocket.'];
        $score -= 15;
    }

    // Recomanacions finals
    if ($has_wpr && $has_sg) {
        $recommendations[] = 'Configuració recomanada SG+WP Rocket: SG Dynamic ON, SG File-Based OFF, SG Memcached ON, SG Frontend (Minify/Combine) OFF, WP Rocket minify ON, CDN OFF (usa SG CDN), Lifespan 4h, Never Cache buit.';
    }

    $score = max(0, min(100, $score));

    $result = [
        'score' => $score,
        'score_color' => $score >= 80 ? '#00a32a' : ($score >= 60 ? '#f0b849' : '#d63638'),
        'score_label' => $score >= 80 ? 'Òptim' : ($score >= 60 ? 'Millorable' : 'Crític'),
        'detected' => $detected,
        'page_cache_count' => count($page_caches),
        'wp_rocket' => $wp_rocket,
        'sg_settings' => $sg_settings,
        'issues' => $issues,
        'recommendations' => $recommendations,
        'generated_at' => current_time('mysql'),
    ];

    set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
    return $result;
}
