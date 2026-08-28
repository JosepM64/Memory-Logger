<?php
/**
 * Memory Logger Pro v13.3.0 - Security Logic
 * Escaneo de vulnerabilidades y auditoría de plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función unificada de análisis de plugins - VERSIÓN CORREGIDA
 */
function mlp_analyze_wordpress_plugins_unified(array $options = []): array {
    if (function_exists('set_time_limit')) {
        @set_time_limit(60);
    }

    $defaults = [
        'max_execution_time' => 30,
        'cache_duration' => HOUR_IN_SECONDS,
        'context' => 'quick'
    ];
    $options = wp_parse_args($options, $defaults);

    $cache_key = 'mlp_wp_plugins_analysis_v4_' . md5(MLP_PATH . get_bloginfo('version'));
    $cached = get_transient($cache_key);
    if ($cached !== false && isset($cached['cache_version']) && $cached['cache_version'] === '4.0') {
        return $cached;
    }

    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $start_time = time();
    $all_plugins = get_plugins();
    $active_plugins = get_option('active_plugins', []);
    $updates = get_site_transient('update_plugins');
    
    $results = [
        'outdated' => [],
        'inactive' => [],
        'no_wp_org' => [],
        'requires_php_update' => [],
        'requires_wp_update' => [],
        'tested_up_to' => [],
        'oversized' => [],
        'vulnerabilities' => [],
        'analysis_date' => current_time('mysql'),
        'total_plugins' => count($all_plugins),
        'active_count' => count($active_plugins),
        'security_score' => 100,
        'cache_version' => '4.0'
    ];

    $analyzed_slugs = [];

    foreach ($active_plugins as $plugin_file) {
        if ((time() - $start_time) > $options['max_execution_time']) break;
        
        // Buscar el plugin en todos los plugins
        $data = $all_plugins[$plugin_file] ?? null;
        if (!$data) continue;

        $slug = dirname($plugin_file);
        if ($slug === '.') $slug = $plugin_file;
        
        if (in_array($slug, $analyzed_slugs)) continue;
        $analyzed_slugs[] = $slug;

        $name = !empty($data['Name']) ? $data['Name'] : $slug;
        $version = !empty($data['Version']) ? $data['Version'] : '0';
        
        // 1. Verificar Actualizaciones
        if ($updates && isset($updates->response[$plugin_file])) {
            $new_ver = $updates->response[$plugin_file]->new_version ?? null;
            if ($new_ver && version_compare($version, $new_ver, '<')) {
                $results['outdated'][] = [
                    'plugin' => $name,
                    'current' => $version,
                    'latest' => $new_ver,
                    'issue' => 'Actualización disponible'
                ];
            }
        }

        // 3. Verificar compatibilidad con PHP
        $requires_php = $data['RequiresPHP'] ?? '';
        if ($requires_php && version_compare(PHP_VERSION, $requires_php, '<')) {
            $results['requires_php_update'][] = [
                'plugin' => $name,
                'current_php' => PHP_VERSION,
                'required_php' => $requires_php,
                'issue' => 'Requiere PHP ' . $requires_php
            ];
        }

        // 4. Verificar compatibilidad con WordPress
        $requires_wp = $data['RequiresWP'] ?? '';
        $current_wp = get_bloginfo('version');
        if ($requires_wp && version_compare($current_wp, $requires_wp, '<')) {
            $results['requires_wp_update'][] = [
                'plugin' => $name,
                'current_wp' => $current_wp,
                'required_wp' => $requires_wp,
                'issue' => 'Requiere WordPress ' . $requires_wp
            ];
        }

        // 5. Verificar si está en el directorio oficial
        if (isset($updates->no_update[$plugin_file])) {
            // Está en el directorio
            $results['in_wp_org'][] = $name;
        } elseif (!isset($updates->response[$plugin_file])) {
            // Podría ser premium o personalizado
            $results['no_wp_org'][] = [
                'plugin' => $name,
                'type' => str_contains(strtolower($data['PluginURI'] ?? ''), 'wordpress.org') ? 'En WP.org' : 'Premium/Personalizado'
            ];
        }

        // 6. Verificar última actualización (edad del plugin)
        // Nota: LastUpdated no siempre está disponible en get_plugins(), pero intentamos
        // Para datos reales se requeriría consultar la API de WP.org, lo cual es lento.
        // Aquí usamos una heurística si el dato existiera, o lo omitimos para no ralentizar.
        
        // 2. Otros checks ligeros
        if ($version !== '0' && version_compare($version, '1.0.0', '<')) {
            $results['tested_up_to'][] = ['plugin' => $name, 'issue' => 'Versión preliminar (< 1.0)'];
        }
    }

    // Calcular Score
    $penalties = count($results['outdated']) * 10;
    $results['security_score'] = max(0, 100 - $penalties);

    set_transient($cache_key, $results, $options['cache_duration']);
    return $results;
}

/**
 * Escaneo de tamaño de directorios
 */
function mlp_scan_plugin_sizes(): array {
    $active_plugins = get_option('active_plugins', []);
    $results = [];
    foreach (array_slice($active_plugins, 0, 10) as $file) {
        $path = WP_PLUGIN_DIR . '/' . dirname($file);
        if (!is_dir($path)) continue;
        $size = mlp_get_directory_size($path);
        if ($size > 10 * 1024 * 1024) {
            $results[] = ['plugin' => dirname($file), 'size' => round($size / 1048576, 2)];
        }
    }
    return $results;
}

/**
 * Obtener tamaño de directorio
 */
function mlp_get_directory_size(string $dir): int {
    if (!is_dir($dir)) return 0;
    $size = 0;
    try {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $size += $file->getSize();
        }
    } catch (Exception $e) {
        return 0;
    }
    return $size;
}

/**
 * Auditoría Integral
 */
function mlp_run_comprehensive_audit(): array {
    $start = microtime(true);
    $plugins = mlp_analyze_wordpress_plugins_unified(['cache_duration' => 1]);
    
    if (!function_exists('mlp_analyze_database')) require_once MLP_PATH . 'includes/core-logic.php';
    $db = mlp_analyze_database();
    $errors = mlp_analyze_error_patterns();
    $integrity = mlp_check_file_integrity();

    $p_score = $plugins['security_score'];
    $db_score = $db['health_score'];
    $e_score = max(0, 100 - ($errors['total_errors'] * 2));
    $i_score = empty($integrity['issues']) ? 100 : 50;

    $global = ($p_score * 0.4) + ($db_score * 0.3) + ($e_score * 0.2) + ($i_score * 0.1);

    return [
        'global_score' => round($global),
        'plugin_score' => $p_score,
        'db_score' => $db_score,
        'error_score' => $e_score,
        'integrity_score' => $i_score,
    ];
}

/**
 * Escaneo rápido de seguridad optimizado - VERSIÓN CORREGIDA
 */
if (!function_exists('mlp_run_quick_security_scan_optimized')) {
    function mlp_run_quick_security_scan_optimized(): array {
        if (function_exists('set_time_limit')) {
            @set_time_limit(30);
        }

        $cache_key = 'mlp_quick_security_scan_' . md5(MLP_PATH);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $issues = [];
        $score = 100;

        // Verificar permisos de archivos
        $upload_dir = wp_upload_dir();
        if (!is_writable($upload_dir['basedir'])) {
            $issues[] = 'Directorio de uploads no escribible';
            $score -= 10;
        }

        // Verificar debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $issues[] = 'WP_DEBUG activado en producción';
            $score -= 5;
        }

        // Verificar plugins activos
        $active_plugins = get_option('active_plugins', []);
        if (count($active_plugins) > 20) {
            $issues[] = 'Demasiados plugins activos (' . count($active_plugins) . ')';
            $score -= 10;
        }

        $result = [
            'score' => max(0, $score),
            'issues' => $issues,
            'scan_date' => current_time('mysql'),
        ];

        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        return $result;
    }
}
