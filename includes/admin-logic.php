<?php
/**
 * Memory Logger Pro v13.3.0 - Lógica de Administración
 *
 * Manejo de menús, páginas de admin y formularios
 * Optimizado para PHP 8.2+ con tipado estricto
 *
 * @package Memory Logger Pro
 * @version 12.8.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/* =========================================================================
   REGISTRO DE MENÚS Y HOOKS
   ========================================================================= */

/**
 * Registrar menú de administración
 *
 * @return void
 */
function mlp_add_admin_menu(): void {
    add_menu_page(
        'Memory Logger Pro v' . MLP_VERSION,
        'Memory Logger Pro',
        'manage_options',
        'memory-log-viewer',
        'mlp_admin_page',
        'dashicons-chart-area',
        80
    );
}

/**
 * Cargar scripts y estilos de administración
 *
 * @param string $hook Hook de WordPress
 * @return void
 */
function mlp_admin_enqueue_scripts(string $hook): void {
    // Solo cargar en páginas del plugin
    if (!str_contains($hook, 'memory-log-viewer')) {
        return;
    }

    // Cargar jQuery (siempre necesario)
    wp_enqueue_script('jquery');

    // Estilos CSS
    wp_enqueue_style(
        'memory-logger-admin-styles',
        MLP_URL . 'assets/admin-styles.css',
        [],
        MLP_VERSION
    );

    // Tippy.js - Popper
    wp_enqueue_script(
        'tippy-popper',
        'https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js',
        [],
        '2.11.8',
        true
    );

    // Tippy.js - Bundle
    wp_enqueue_script(
        'tippy-js',
        'https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.min.js',
        ['tippy-popper'],
        '6.3.7',
        true
    );

    // Script principal
    wp_enqueue_script(
        'memory-logger-admin-script',
        MLP_URL . 'assets/admin-script.js',
        ['jquery', 'tippy-js'],
        MLP_VERSION,
        true
    );

    // Localizar script con datos de configuración
    $hosting_info = mlp_detect_hosting_type();
    $calibration = mlp_get_cpu_calibration();

    wp_localize_script('memory-logger-admin-script', 'memoryLoggerPro', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mlp_run_test_nonce'),
        'clearCacheNonce' => wp_create_nonce('mlp_clear_cache_nonce'),
        'clearAllLogsNonce' => wp_create_nonce('mlp_clear_all_logs_cache_nonce'),
        'security' => wp_create_nonce('mlp_full_analysis_nonce'),
        'lazyLoadSecurityNonce' => wp_create_nonce('mlp_lazy_load_security_nonce'),
        'lazyLoadDiagnosticNonce' => wp_create_nonce('mlp_lazy_load_diagnostic_nonce'),
        'exportDiagnosticNonce' => wp_create_nonce('mlp_export_diagnostic_report_nonce'),
        'hosting_type' => $hosting_info['type'],
        'hosting_provider' => $hosting_info['provider'],
        'cpu_ratio' => round($hosting_info['cpu_share_ratio'] * 100, 1),
        'calibration_factor' => round($calibration['factor'], 2),
        'is_restricted' => $hosting_info['is_restricted'],
        'version' => MLP_VERSION,
    ]);

    // Chart.js desde CDN (si no está desactivado)
    $opts = mlp_get_options();
    if (empty($opts['no_charts'])) {
        wp_enqueue_script(
            'memory-logger-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );
    }
}

/**
 * Registrar hooks de administración
 *
 * @return void
 */
function mlp_register_admin_hooks(): void {
    add_action('admin_menu', 'mlp_add_admin_menu');
    add_action('admin_enqueue_scripts', 'mlp_admin_enqueue_scripts');
    add_action('admin_init', 'mlp_check_system_status');
    add_action('admin_init', 'mlp_force_correct_configuration');
    add_action('admin_notices', 'mlp_show_critical_error_notices');

    // Handlers para exportación
    add_action('admin_post_mlp_export_csv', 'mlp_export_csv_handler');
}

/* =========================================================================
   PÁGINA PRINCIPAL DE ADMINISTRACIÓN
   ========================================================================= */

/**
 * Renderizar página principal de administración
 *
 * @return void
 */
function mlp_admin_page(): void {
    $opts = mlp_get_options();
    $active_tab = sanitize_key($_GET['tab'] ?? 'dashboard');
    $hosting_info = mlp_detect_hosting_type();
    $calibration = mlp_get_cpu_calibration();

    // Procesar formularios POST
    $opts = mlp_process_form_submissions($opts);

    // Obtener datos del sistema
    $system_data = mlp_get_admin_system_data();

    // Renderizar interfaz
    mlp_render_admin_interface($opts, $active_tab, $system_data);
}

/**
 * Procesar todos los envíos de formularios POST
 *
 * @param array $opts Opciones actuales
 * @return array Opciones actualizadas
 */
function mlp_process_form_submissions(array $opts): array {
    // Reset a valores por defecto
    if (isset($_POST['reset_defaults']) && check_admin_referer('save_ml_action')) {
        $opts = mlp_reset_to_defaults();
        mlp_show_admin_notice('success', '✅ Valores por defecto restaurados correctamente.');
        return $opts;
    }

    // Guardar configuración principal
    if (isset($_POST['save_ml']) && check_admin_referer('save_ml_action')) {
        $opts = mlp_save_main_configuration($opts, $_POST);
        mlp_show_admin_notice('success', '✅ Configuración guardada.');
        return $opts;
    }

    // Limpiar log
    if (isset($_POST['clear_log']) && check_admin_referer('clear_log_action')) {
        mlp_clear_logs_and_cache();
        mlp_show_admin_notice('success', '🗑️ Registro borrado y cache vaciada.');
    }

    // Actualizar salud
    if (isset($_POST['refresh_health']) && check_admin_referer('refresh_health_action')) {
        mlp_refresh_system_health();
        mlp_show_admin_notice('success', '✅ Salud del sistema y cache actualizadas.');
    }

    return $opts;
}

/**
 * Guardar configuración principal del plugin
 *
 * @param array $opts Opciones actuales
 * @param array $post Datos POST
 * @return array Opciones actualizadas
 */
function mlp_save_main_configuration(array $opts, array $post): array {
    // Métricas de umbrales
    $opts['threshold_mb'] = max(10, min(512, (float) ($post['threshold_mb'] ?? 70)));
    $opts['time_warn'] = max(0.1, min(30.0, (float) ($post['time_warn'] ?? 2.0)));
    $opts['time_risk'] = max(0.2, min(60.0, (float) ($post['time_risk'] ?? 5.0)));

    // Configuración de CPU
    if (isset($post['cpu_custom']) && $post['cpu_custom'] === '1') {
        $opts['cpu_warn'] = max(0, min(100, (float) ($post['cpu_warn'] ?? 50)));
        $opts['cpu_risk'] = max(0, min(100, (float) ($post['cpu_risk'] ?? 80)));
    } else {
        $opts['cpu_warn'] = 50;
        $opts['cpu_risk'] = 80;
    }

    // Validar valores de CPU
    if ($opts['cpu_warn'] >= $opts['cpu_risk']) {
        $opts['cpu_risk'] = $opts['cpu_warn'] + 10;
    }

    // Peso de CPU
    $cpu_count = mlp_get_cpu_count();
    $opts['cpu_weight'] = (int) ($post['cpu_weight'] ?? 1);
    $opts['cpu_weight'] = max(1, min($cpu_count, $opts['cpu_weight']));

    // Modos debug
    $opts['debug_mode'] = isset($post['debug_mode']) ? 1 : 0;
    $opts['no_charts'] = isset($post['no_charts']) ? 1 : 0;

    // Características avanzadas
    $opts['intelligent_cpu'] = isset($post['intelligent_cpu']) ? 1 : 1;
    $opts['enhanced_size'] = isset($post['enhanced_size']) ? 1 : 1;
    $opts['universal_fallbacks'] = isset($post['universal_fallbacks']) ? 1 : 1;

    // Métricas principales (siempre activas)
    $opts['track_sql'] = 1;
    $opts['track_cpu'] = 1;
    $opts['track_http'] = 1;
    $opts['track_size'] = 1;

    // Ratios de muestreo
    $opts['sampling_rate'] = 10;
    $opts['cron_sampling'] = 10;

    // Guardar
    update_option('memory_logger_options', $opts);
    return mlp_get_options();
}

/**
 * Obtener datos necesarios para la página de admin
 *
 * @return array Array con todos los datos
 */
function mlp_get_admin_system_data(): array {
    // Obtener security score desde security-logic
    $security_data = [];
    if (function_exists('mlp_run_quick_security_scan_optimized')) {
        $security_data = mlp_run_quick_security_scan_optimized();
    }
    
    // Obtener visitor stats desde advanced stats
    $stats = [];
    if (function_exists('mlp_get_advanced_stats')) {
        $stats = mlp_get_advanced_stats();
    }
    $visitor_stats = $stats['visitor_stats'] ?? [];
    
    return [
        'health' => mlp_get_system_health(),
        'current_metrics' => mlp_get_current_metrics(),
        'hosting_info' => mlp_detect_hosting_type(),
        'calibration' => mlp_get_cpu_calibration(),
        'cpu_count' => mlp_get_cpu_count(),
        'cpu_effective' => mlp_get_effective_cpu_count(),
        'os_info' => mlp_get_os_info(),
        // Nuevos datos
        'security_score' => $security_data['score'] ?? 0,
        'security_issues' => $security_data['issues'] ?? [],
        'visitor_stats' => $visitor_stats,
    ];
}

/**
 * Renderizar la interfaz de administración
 *
 * @param array $opts Opciones del plugin
 * @param string $active_tab Pestaña activa
 * @param array $system_data Datos del sistema
 * @return void
 */
function mlp_render_admin_interface(
    array $opts,
    string $active_tab,
    array $system_data
): void {
    extract($system_data);

    echo '<div class="wrap">';
    mlp_render_admin_header();
    mlp_render_admin_tabs($active_tab);

    // Renderizar la pestaña activa
    match ($active_tab) {
        'dashboard' => mlp_render_tab_dashboard($opts, $system_data),
        'statistics' => mlp_render_tab_statistics($opts, $system_data),
        'diagnostic' => mlp_render_tab_diagnostic($opts, $system_data),
        default => mlp_render_tab_dashboard($opts, $system_data),
    };

    echo '</div>';
}

/**
 * Renderizar encabezado de admin
 *
 * @return void
 */
function mlp_render_admin_header(): void {
    echo '<h1 style="margin-bottom:5px; font-size:20px;">Memory Logger Pro v' . MLP_VERSION . '</h1>';
    echo '<p style="margin-top:0; color:#646970; font-size:12px;">';
    echo 'Auditor de rendimiento PRO universal: Gráficos, Memoria, Tiempo, CPU inteligente, Tamaño mejorado, Diagnóstico & Seguridad avanzado.';
    echo '</p>';
}

/**
 * Renderizar pestañas de navegación
 *
 * @param string $active_tab Pestaña activa
 * @return void
 */
function mlp_render_admin_tabs(string $active_tab): void {
    echo '<h2 class="nav-tab-wrapper">';
    $tabs = [
        'dashboard' => ['icon' => '📊', 'text' => 'Dashboard'],
        'statistics' => ['icon' => '📈', 'text' => 'Estadísticas'],
        'diagnostic' => ['icon' => '🔍', 'text' => 'Diagnóstico & Seguridad'],
    ];

    foreach ($tabs as $tab => $data) {
        $active_class = $tab === $active_tab ? 'nav-tab-active' : '';
        echo sprintf(
            '<a href="?page=memory-log-viewer&tab=%s" class="nav-tab %s">%s %s</a>',
            esc_attr($tab),
            $active_class,
            esc_html($data['icon']),
            esc_html($data['text'])
        );
    }

    echo '</h2>';
}

/**
 * Renderizar pestaña Dashboard
 *
 * @param array $opts Opciones del plugin
 * @param array $system_data Datos del sistema
 * @return void
 */
function mlp_render_tab_dashboard(array $opts, array $system_data): void {
    require_once MLP_PATH . 'includes/views/tab-dashboard.php';
    mlp_render_dashboard_view(
        $opts,
        $system_data['health'],
        $system_data['current_metrics'],
        $system_data['cpu_count'],
        $system_data['cpu_effective'],
        $system_data['os_info'],
        $system_data['hosting_info'],
        $system_data['calibration'],
        $system_data['security_score'] ?? 0,
        $system_data['security_issues'] ?? [],
        $system_data['visitor_stats'] ?? []
    );
}

/**
 * Renderizar pestaña Statistics
 *
 * @param array $opts Opciones del plugin
 * @param array $system_data Datos del sistema
 * @return void
 */
function mlp_render_tab_statistics(array $opts, array $system_data): void {
    require_once MLP_PATH . 'includes/views/tab-statistics.php';
    mlp_render_statistics_view(
        $opts,
        $system_data['hosting_info'],
        $system_data['calibration']
    );
}

/**
 * Renderizar pestaña Diagnostic
 *
 * @param array $opts Opciones del plugin
 * @param array $system_data Datos del sistema
 * @return void
 */
function mlp_render_tab_diagnostic(array $opts, array $system_data): void {
    require_once MLP_PATH . 'includes/views/tab-diagnostic.php';
    mlp_render_diagnostic_view(
        $opts,
        $system_data['hosting_info'],
        $system_data['calibration'],
        $system_data['health']
    );
}

/* =========================================================================
   FUNCIONES DE CONFIGURACIÓN
   ========================================================================= */

/**
 * Restablecer valores por defecto
 *
 * @return array Valores por defecto
 */
function mlp_reset_to_defaults(): array {
    $defaults = mlp_get_options();

    mlp_purge_cache();

    update_option('memory_logger_options', $defaults);
    return $defaults;
}

/**
 * Limpiar logs y caché
 *
 * @return void
 */
function mlp_clear_logs_and_cache(): void {
    if (file_exists(MLP_LOG_FILE)) {
        @file_put_contents(MLP_LOG_FILE, '');
    }

    mlp_purge_cache();

    // Limpiar opción de alertas si existe
    if (get_option('mlp_alert_config')) {
        delete_option('mlp_alert_config');
    }
}

/**
 * Refrescar salud del sistema
 *
 * @return void
 */
function mlp_refresh_system_health(): void {
    mlp_purge_cache();

    // Limpiar opción de alertas si existe
    if (get_option('mlp_alert_config')) {
        delete_option('mlp_alert_config');
    }
}

/* =========================================================================
   VERIFICACIÓN DE SISTEMA
   ========================================================================= */

/**
 * Verificar estado del sistema
 *
 * @return void
 */
function mlp_check_system_status(): void {
    if (file_exists(MLP_LOG_FILE) && !is_writable(MLP_LOG_FILE)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>⚠️ Memory Logger Pro: El archivo de log no tiene permisos de escritura.</p></div>';
        });
    }
}

/**
 * Forzar configuración correcta
 *
 * @return void
 */
function mlp_force_correct_configuration(): void {
    $opts = mlp_get_options();
    $needs_update = false;

    // Asegurar métricas principales activas
    $main_metrics = ['track_sql', 'track_cpu', 'track_http', 'track_size'];
    foreach ($main_metrics as $metric) {
        if (!isset($opts[$metric]) || $opts[$metric] != 1) {
            $opts[$metric] = 1;
            $needs_update = true;
        }
    }

    // Asegurar ratios correctos
    $sampling_rates = ['sampling_rate', 'cron_sampling'];
    foreach ($sampling_rates as $rate) {
        if (!isset($opts[$rate]) || $opts[$rate] != 10) {
            $opts[$rate] = 10;
            $needs_update = true;
        }
    }

    // Asegurar características avanzadas activadas
    $advanced_features = ['intelligent_cpu', 'enhanced_size', 'universal_fallbacks'];
    foreach ($advanced_features as $feature) {
        if (!isset($opts[$feature])) {
            $opts[$feature] = 1;
            $needs_update = true;
        }
    }

    if ($needs_update) {
        update_option('memory_logger_options', $opts);
    }
}

/* =========================================================================
   NOTIFICACIONES DE ADMIN
   ========================================================================= */

/**
 * Mostrar notificaciones de errores críticos en admin
 *
 * @return void
 */
function mlp_show_critical_error_notices(): void {
    $current_page = $_GET['page'] ?? '';
    if (!str_contains($current_page, 'memory-log-viewer')) {
        return;
    }

    $errors = get_transient('mlp_cached_errors_list');
if ($errors === false) {
    $errors = function_exists('mlp_enhanced_error_detection') ? mlp_enhanced_error_detection() : [];
}
    $critical_errors = array_filter($errors, static function($error) {
        return in_array($error['severity'], ['critical', 'high'], true);
    });

    if (empty($critical_errors)) {
        return;
    }

    $error_count = count($critical_errors);
    $error_text = $error_count === 1 ? '1 error crítico' : $error_count . ' errores críticos';

    echo '<div class="notice notice-error">';
    echo '<p><strong>🚨 Errors detected in system - ' . $error_text . '</strong><br><small style="color:#646970;">These errors come from other plugins/themes, not from Memory Logger Pro.</small></p>';
    echo '<ul>';

    foreach (array_slice($critical_errors, 0, 3) as $error) {
        echo '<li>' . esc_html($error['message']) . '</li>';
    }

    if ($error_count > 3) {
        echo '<li>... y ' . ($error_count - 3) . ' más</li>';
    }

    echo '</ul>';
    echo '<p><a href="' . admin_url('admin.php?page=memory-log-viewer&tab=diagnostic&mlp_focus=errors') . '" id="mlp-notice-view-diagnostic" class="button button-primary">🔍 Ver diagnóstico completo</a></p>';
    echo '</div>';
}

/* =========================================================================
   EXPORTACIÓN
   ========================================================================= */

/**
 * Handler para exportación CSV de eventos peligrosos
 *
 * @return void
 */
function mlp_export_csv_handler(): void {
    if (!current_user_can('manage_options')) {
        wp_die('Permisos insuficientes');
    }

    check_admin_referer('memory_logger_export_csv', 'export_csv_nonce');

    $export_type = sanitize_key($_POST['export_type'] ?? 'all');

    match ($export_type) {
        'dangerous_peaks' => mlp_export_dangerous_peaks_csv(),
        default => mlp_export_all_logs_csv(),
    };
}

/**
 * Exportar todos los logs en CSV
 *
 * @return void
 */
function mlp_export_all_logs_csv(): void {
    if (!file_exists(MLP_LOG_FILE)) {
        wp_die('No hay datos para exportar');
    }

    $lines = file(MLP_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="memory-logger-all-logs-' . date('Y-m-d-His') . '.csv"');

    $output = fopen('php://output', 'w');
    
    // CABECERA
    fputcsv($output, ['Memory Logger Pro v' . MLP_VERSION]);
    fputcsv($output, ['Autor: Josep Maria Tapia Estaragues | https://www.posicionamientowebysem.com']);
    fputcsv($output, ['Generado el: ' . current_time('mysql')]);
    fputcsv($output, []); // Separador

    // Encabezados de columnas
    fputcsv($output, ['Fecha', 'Tipo', 'UA', 'Status', 'URL', 'Memoria (MB)', 'Tiempo (s)', 'SQL', 'Size (KB)', 'CPU (%)', 'Método']);

    foreach (array_reverse($lines) as $l) {
        if (!str_contains($l, 'DATE:')) continue;
        $row = mlp_parse_log_line($l);
        
        fputcsv($output, [
            $row['date'] ?? '',
            $row['type'] ?? '',
            $row['ua'] ?? '',
            $row['http'] ?? '',
            $row['url'] ?? '',
            $row['mem'] ?? '',
            $row['time'] ?? '',
            $row['sql'] ?? '',
            $row['size'] ?? '',
            $row['cpu'] ?? '',
            $row['size_method'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Exportar solo eventos peligrosos
 *
 * @return void
 */
function mlp_export_dangerous_peaks_csv(): void {
    $stats = function_exists('mlp_get_advanced_stats') ? mlp_get_advanced_stats() : null;

    if (!$stats || empty($stats['dangerous_peaks'])) {
        wp_die('No hay eventos peligrosos para exportar');
    }

    $dangerous_peaks = $stats['dangerous_peaks'] ?? [];

    // Preparar datos del sistema
    $system_info = [
        'wp_version' => get_bloginfo('version'),
        'php_version' => phpversion(),
        'server_type' => function_exists('mlp_detect_server_type') ? mlp_detect_server_type() : 'Unknown',
        'memory_limit' => ini_get('memory_limit'),
    ];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="memory-logger-dangerous-peaks-' . date('Y-m-d-His') . '.csv"');

    $output = fopen('php://output', 'w');

    // Header del CSV
    fputcsv($output, array_merge(['System'], array_keys($system_info)));

    // Fila de datos del sistema
    fputcsv($output, $system_info);

    // Datos de picos peligrosos
    foreach ($dangerous_peaks as $peak) {
        $error_classes = !empty($peak['errors']) ? array_map('get_class', $peak['errors']) : [];
        fputcsv($output, [
            $peak['timestamp'] ?? '',
            number_format($peak['memory'] ?? 0, 2),
            number_format($peak['time'] ?? 0, 3),
            number_format($peak['cpu'] ?? 0, 1),
            implode(', ', $error_classes)
        ]);
    }

    fclose($output);
    exit;
}

/* =========================================================================
   FUNCIONES AUXILIARES DE EXPORTACIÓN
   ========================================================================= */

/**
 * Generar contenido del reporte de diagnóstico
 *
 * @param string $format Formato de salida (json, html, txt, csv)
 * @return string Contenido del reporte
 */
function mlp_generate_diagnostic_report(string $format = 'json'): string {
    $report_data = [
        'meta' => [
            'generated_at' => current_time('mysql'),
            'plugin_version' => defined('MLP_VERSION') ? MLP_VERSION : '13.3.6',
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'site_url' => get_site_url(),
            'timezone' => wp_timezone_string()
        ],
        'system_health' => function_exists('mlp_get_system_health') ? mlp_get_system_health() : [],
        'hosting_info' => function_exists('mlp_detect_hosting_type') ? mlp_detect_hosting_type() : [],
        'security_scan' => function_exists('mlp_run_quick_security_scan_optimized') ? mlp_run_quick_security_scan_optimized() : [],
        'error_analysis' => function_exists('mlp_enhanced_error_detection') ? mlp_enhanced_error_detection() : [],
        'cache_analysis' => function_exists('mlp_analyze_cache_config') ? mlp_analyze_cache_config() : [],
        'active_plugins' => get_option('active_plugins', [])
    ];

    return match ($format) {
        'json' => json_encode($report_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'html' => mlp_generate_html_report($report_data),
        'txt' => mlp_generate_text_report($report_data),
        'csv' => mlp_generate_csv_report($report_data),
        default => json_encode($report_data),
    };
}

/**
 * Generar reporte en formato HTML
 *
 * @param array $data Datos del reporte
 * @return string HTML generado
 */
function mlp_generate_html_report(array $data): string {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Memory Logger Pro - Reporte de Diagnóstico</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
            .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            h1 { color: #1d2327; margin-bottom: 5px; }
            h2 { color: #2271b1; margin-top: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; }
            .section { margin-bottom: 20px; }
            .metric { display: inline-block; padding: 10px 15px; background: #f8f9fa; border-radius: 4px; margin: 5px; }
            .alert-critical { background: #fff5f5; border-left: 4px solid #d63638; }
            .alert-warning { background: #fff8e1; border-left: 4px solid #f0b849; }
            .alert-success { background: #e8f5e8; border-left: 4px solid #00a32a; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📊 Memory Logger Pro - Reporte de Diagnóstico</h1>
            <p><strong>Fecha:</strong> <?php echo esc_html($data['meta']['generated_at']); ?></p>
            <p><strong>Versión Plugin:</strong> <?php echo esc_html($data['meta']['plugin_version']); ?></p>
            <p><strong>WordPress:</strong> <?php echo esc_html($data['meta']['wordpress_version']); ?></p>
            <p><strong>PHP:</strong> <?php echo esc_html($data['meta']['php_version']); ?></p>
            <p><strong>URL:</strong> <?php echo esc_html($data['meta']['site_url']); ?></p>

            <h2>🏠 Información del Sistema</h2>
            <div class="section">
                <?php if (!empty($data['system_health'])): ?>
                    <div class="metric"><strong>Memoria:</strong> <?php echo number_format($data['system_health']['memory_used'] ?? 0, 2); ?> MB</div>
                    <div class="metric"><strong>PHP:</strong> <?php echo esc_html($data['system_health']['php_ver'] ?? 'N/A'); ?></div>
                    <div class="metric"><strong>DB Latency:</strong> <?php echo number_format($data['system_health']['db_lat'] ?? 0, 1); ?> ms</div>
                <?php endif; ?>
            </div>

            <h2>🛡️ Seguridad</h2>
            <div class="section">
                <?php if (!empty($data['security_scan'])): ?>
                    <p><strong>Score:</strong> <?php echo $data['security_scan']['security_score'] ?? 0; ?>/100</p>
                <?php endif; ?>
            </div>

            <h2>🚨 Errores</h2>
            <div class="section">
                <p><strong>Total:</strong> <?php echo count($data['error_analysis'] ?? []); ?> errores detectados</p>
            </div>

            <p style="margin-top: 40px; text-align: center; color: #646970;">
                Generado por <a href="https://www.posicionamientowebysem.com">Memory Logger Pro v<?php echo $data['meta']['plugin_version']; ?></a>
            </p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Generar reporte en formato texto plano
 *
 * @param array $data Datos del reporte
 * @return string Texto plano generado
 */
function mlp_generate_text_report(array $data): string {
    $content = "MEMORY LOGGER PRO - REPORTE DE DIAGNÓSTICO\n";
    $content .= "==========================================\n";
    $content .= "Fecha: " . $data['meta']['generated_at'] . "\n";
    $content .= "Sitio: " . $data['meta']['site_url'] . "\n\n";

    $content .= "--- INFORMACIÓN DEL SISTEMA ---\n";
    $content .= "WordPress: " . $data['meta']['wordpress_version'] . "\n";
    $content .= "PHP: " . $data['meta']['php_version'] . "\n";
    $content .= "Memoria: " . number_format($data['system_health']['memory_used'] ?? 0, 2) . " MB\n";
    $content .= "DB Latencia: " . number_format($data['system_health']['db_lat'] ?? 0, 1) . " ms\n\n";

    $content .= "--- ERRORES DETECTADOS ---\n";
    $content .= "Total: " . count($data['error_analysis']) . "\n\n";

    $content .= "--- FIN DEL REPORTE ---\n";
    $content .= "Generado por Memory Logger Pro v" . $data['meta']['plugin_version'] . "\n";
    $content .= "https://www.posicionamientowebysem.com\n";

    return $content;
}

/**
 * Generar reporte en formato CSV
 *
 * @param array $data Datos del reporte
 * @return string CSV generado
 */
function mlp_generate_csv_report(array $data): string {
    $output = fopen('php://temp', 'r+');

    // Header
    fputcsv($output, ['Section', 'Key', 'Value']);

    // Metadata
    foreach ($data['meta'] as $key => $value) {
        fputcsv($output, ['Meta', $key, $value]);
    }

    // System Health
    if (!empty($data['system_health'])) {
        foreach ($data['system_health'] as $key => $value) {
            fputcsv($output, ['System', $key, is_scalar($value) ? $value : json_encode($value)]);
        }
    }

    // Errors
    $error_count = count($data['error_analysis']);
    fputcsv($output, ['Errors', 'count', $error_count]);

    rewind($output);
    $content = stream_get_contents($output);
    fclose($output);

    return $content;
}

/**
 * Mostrar notificaciones de admin
 *
 * @return void
 */
function mlp_show_admin_notice(string $type, string $message): void {
    echo '<div class="notice ' . $type . ' is-dismissible">';
    echo '<p>' . esc_html($message) . '</p>';
    echo '</div>';
}
// fin admin-logic.php