<?php
/**
 * Plugin Name: Memory Logger Pro
 * Plugin URI: https://www.posicionamientowebysem.com/memory-logger-pro
 * Description: Auditor de rendimiento PRO universal: Gráficos, Memoria, Tiempo, CPU inteligente, Tamaño mejorado, Diagnóstico & Seguridad avanzado.
 * Version: 13.3.6
 * Requires at least: 6.2
 * Requires PHP: 8.2
 * Author: Josep Maria Tapia Estarriaga
 * Author URI: https://www.posicionamientowebysem.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: memory-logger
 * Domain Path: /languages
 *
 * @package Memory Logger Pro
 * @version 13.3.5
 */

// ============================================================================
// 1. SEGURIDAD: EVITAR ACCESO DIRECTO
// ============================================================================
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// 2. SWITCH DE DEBUG RÁPIDO (desactivar en producción)
// ============================================================================
define('MLP_DEBUG_MODE', false);

// ============================================================================
// 3. CONSTANTES DEL PLUGIN
// ============================================================================
define('MLP_VERSION', '13.3.6');
define('MLP_PATH', plugin_dir_path(__FILE__));
define('MLP_URL', plugin_dir_url(__FILE__));
define('MLP_MAIN_FILE', __FILE__);
define('MLP_LOG_FILE', WP_CONTENT_DIR . '/memory-usage.log');
define('MLP_ERROR_LOG_FILE', WP_CONTENT_DIR . '/memory-logger-errors.log');
define('MLP_CACHE_TTL', 60);


// ============================================================================
// 3.1 CONSTANTES DE LÍMITES DE REGISTROS (NUEVAS - v12.8.0)
// ============================================================================
define('MLP_MAX_LOG_ENTRIES', 10000);          // Límite de registros
define('MLP_LOG_RETENTION_DAYS', 30);          // Días de retención
define('MLP_LOG_CLEANUP_BATCH_SIZE', 1000);    // Tamaño del lote de limpieza

// ============================================================================
// 4. CARGAR ARCHIVOS DE LÓGICA EN ORDEN CORRECTO
// ============================================================================
require_once MLP_PATH . 'includes/utils.php';
require_once MLP_PATH . 'includes/core-logic.php';
require_once MLP_PATH . 'includes/security-logic.php';
require_once MLP_PATH . 'includes/cache-logic.php';
require_once MLP_PATH . 'includes/admin-logic.php';
require_once MLP_PATH . 'includes/ajax-logic.php';
require_once MLP_PATH . 'includes/views/tab-dashboard.php';
require_once MLP_PATH . 'includes/views/tab-statistics.php';
require_once MLP_PATH . 'includes/views/tab-diagnostic.php';

// ============================================================================
// 5. INICIALIZACIÓN DEL PLUGIN
// ============================================================================
/**
 * Función de inicialización principal
 */
function mlp_init_plugin(): void {
    if (!defined('WP_START_TIME')) {
        define('WP_START_TIME', microtime(true));
    }

    // Crear directorio de logs si no existe + protección .htaccess
    $log_dir = dirname(MLP_LOG_FILE);
    if (!is_dir($log_dir)) {
        if (wp_mkdir_p($log_dir)) {
            if (MLP_DEBUG_MODE) {
                error_log('Memory Logger Pro v' . MLP_VERSION . ': Directorio de logs creado en ' . $log_dir);
            }
        } else {
            error_log('Memory Logger Pro v' . MLP_VERSION . ': ERROR - No se pudo crear el directorio de logs');
        }
    }
    // Proteger logs contra acceso web directo
    mlp_ensure_log_protection();

    // Inicializar opciones por defecto si no existen
    if (!get_option('memory_logger_options')) {
        // Usar función de core-logic para opciones por defecto
        if (function_exists('mlp_get_options')) {
            $default_options = mlp_get_options();
            update_option('memory_logger_options', $default_options);
        } else {
            // Fallback básico si la función no está disponible aún
            $default_options = [
                'version' => MLP_VERSION,
                'threshold_mb' => 70,
                'time_warn' => 2.0,
                'time_risk' => 5.0,
                'cpu_warn' => 50,
                'cpu_risk' => 80,
                'debug_mode' => 0,
                'no_charts' => 0,
                'sampling_rate' => 10,
                'cron_sampling' => 10,
            ];
            update_option('memory_logger_options', $default_options);
        }
    }

    // Registradores de hooks
    if (function_exists('mlp_register_core_hooks')) {
        mlp_register_core_hooks();
    }
    
    if (function_exists('mlp_register_admin_hooks')) {
        mlp_register_admin_hooks();
    }
    
    if (function_exists('mlp_register_ajax_hooks')) {
        mlp_register_ajax_hooks();
    }
    
    if (function_exists('mlp_register_security_hooks')) {
        mlp_register_security_hooks();
    }

    if (MLP_DEBUG_MODE) {
        error_log('Memory Logger Pro v' . MLP_VERSION . ': Plugin inicializado correctamente');
    }
}

/**
 * Función de activación del plugin
 */
function mlp_ensure_log_protection(): void {
    $log_dir = dirname(MLP_LOG_FILE);
    // .htaccess para Apache — protege solo los logs de MLP sin pisar .htaccess existente
    $htaccess = $log_dir . '/.htaccess';
    $rule = "<FilesMatch \"^(memory-usage|memory-logger-errors)\\.log$\">\nRequire all denied\n</FilesMatch>\n";
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, $rule, LOCK_EX);
    } else {
        $existing = @file_get_contents($htaccess);
        if ($existing !== false && strpos($existing, 'memory-usage') === false) {
            @file_put_contents($htaccess, "\n" . $rule, FILE_APPEND | LOCK_EX);
        }
    }
    // index.php anti-listing
    $index = $log_dir . '/index.php';
    if (!file_exists($index)) {
        @file_put_contents($index, "<?php // Silence is golden\n", LOCK_EX);
    }
}

function mlp_plugin_activation(): void {
    // Crear directorios de logs si no existen
    $log_dir = dirname(MLP_LOG_FILE);
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
    }
    mlp_ensure_log_protection();
    
    // Programar eventos CRON usando funciones de core-logic
    if (function_exists('mlp_schedule_cleanup')) {
        mlp_schedule_cleanup();
    }
    
    // Programar eventos CRON de core-logic.php
    if (!wp_next_scheduled('mlp_daily_error_scan')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS * 3, 'daily', 'mlp_daily_error_scan');
    }
    
    if (!wp_next_scheduled('mlp_daily_security_scan')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS * 6, 'daily', 'mlp_daily_security_scan');
    }
    
    // Programar limpieza diaria de registros
    if (!wp_next_scheduled('mlp_daily_log_cleanup')) {
        wp_schedule_event(time(), 'daily', 'mlp_daily_log_cleanup');
    }
    
    // Programar CRONs de diagnóstico
    if (!wp_next_scheduled('mlp_daily_plugin_conflicts')) {
        wp_schedule_event(strtotime('02:00:00'), 'daily', 'mlp_daily_plugin_conflicts');
    }
    
    if (!wp_next_scheduled('mlp_daily_database_analysis')) {
        wp_schedule_event(strtotime('03:00:00'), 'daily', 'mlp_daily_database_analysis');
    }
    
    if (!wp_next_scheduled('mlp_daily_health_history')) {
        wp_schedule_event(strtotime('00:00:00'), 'daily', 'mlp_daily_health_history');
    }
    
    if (!wp_next_scheduled('mlp_15min_alerts_check')) {
        wp_schedule_event(time(), 'mlp_15min', 'mlp_15min_alerts_check');
    }
    
    error_log('Memory Logger Pro v' . MLP_VERSION . ': Plugin activado');
}

/**
 * Función de desactivación del plugin
 */
function mlp_plugin_deactivation(): void {
    // Limpiar todos los eventos CRON
    $cron_hooks = [
        'mlp_daily_security_scan',
        'mlp_daily_error_scan',
        'mlp_daily_log_cleanup',
        'mlp_daily_plugin_conflicts',
        'mlp_daily_database_analysis',
        'mlp_daily_health_history',
        'mlp_15min_alerts_check',
        'mlp_cron_security_scan' // De security-logic.php
    ];
    
    foreach ($cron_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        wp_clear_scheduled_hook($hook);
    }

    // Limpiar hooks de seguridad
    if (function_exists('mlp_unregister_security_hooks')) {
        mlp_unregister_security_hooks();
    }

    // Limpiar transients
    delete_transient('mlp_system_health');
    delete_transient('mlp_cpu_calibration_' . gethostname());

    // Limpiar cache
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mlp_logs_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mlp_advanced_stats_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mlp_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_mlp_%'");

    error_log('Memory Logger Pro v' . MLP_VERSION . ': Plugin desactivado');
}

// ============================================================================
// 6. REGISTRAR HOOKS PRINCIPALES
// ============================================================================
// Registrar activación y desactivación
register_activation_hook(MLP_MAIN_FILE, 'mlp_plugin_activation');
register_deactivation_hook(MLP_MAIN_FILE, 'mlp_plugin_deactivation');

// Inicializar plugin
add_action('init', 'mlp_init_plugin', 5);

// Shortcode
add_action('init', function() {
    add_shortcode('show_memory_metrics', 'mlp_shortcode_metrics');
}, 6);

// Widget de dashboard
add_action('init', function() {
    add_action('wp_dashboard_setup', 'mlp_dashboard_widget_setup');
}, 6);

// ============================================================================
// 7. FUNCIONES DE SHORTCODE Y WIDGET
// ============================================================================

/**
 * Shortcode para mostrar métricas en frontend
 */
function mlp_shortcode_metrics($atts): string {
    if (!current_user_can('manage_options')) {
        return '';
    }

    $current_metrics = function_exists('mlp_get_current_metrics') ? mlp_get_current_metrics() : null;
    if (!$current_metrics) {
        return '<p style="color:#646970; font-size:12px;">No hay métricas disponibles en este momento.</p>';
    }

    $errors = function_exists('mlp_enhanced_error_detection') ? mlp_enhanced_error_detection() : [];
    $error_count = count($errors);
    $error_icon = $error_count > 0 ? '⚠️' : '✅';

    $diagnostic_url = admin_url('admin.php?page=memory-log-viewer&tab=diagnostic');

    return sprintf(
        '<div style="padding:15px; background:#f8f9fa; border:1px solid #ddd; border-radius:4px; margin:10px 0;">
            <h4 style="margin-top:0; font-size:14px; color:#1d2327;">📊 Memory Logger Pro v%s</h4>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px;">
                <div style="padding:8px; background:white; border-radius:3px;">
                    <strong>Memoria:</strong><br>%s MB
                </div>
                <div style="padding:8px; background:white; border-radius:3px;">
                    <strong>Tiempo:</strong><br>%s s
                </div>
                <div style="padding:8px; background:white; border-radius:3px;">
                    <strong>CPU:</strong><br>%s %%
                </div>
                <div style="padding:8px; background:white; border-radius:3px;">
                    <strong>Errores:</strong><br>%s %d
                </div>
            </div>
            <p style="margin-top:10px; font-size:12px;">
                <a href="%s" style="color:#2271b1;">🔍 Ver diagnóstico completo →</a>
            </p>
        </div>',
        MLP_VERSION,
        esc_html($current_metrics['memory'] ?? '0'),
        esc_html($current_metrics['time'] ?? '0'),
        esc_html($current_metrics['cpu'] ?? '0'),
        $error_icon,
        $error_count,
        esc_url($diagnostic_url)
    );
}

/**
 * Configurar widget del dashboard
 */
function mlp_dashboard_widget_setup(): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    wp_add_dashboard_widget(
        'memory_logger_dashboard_widget',
        'Memory Logger Pro v' . MLP_VERSION . ' - Estado del Sistema',
        'mlp_render_dashboard_widget'
    );
}

/**
 * Renderizar contenido del widget
 */
function mlp_render_dashboard_widget(): void {
    if (!function_exists('mlp_get_current_metrics')) {
        echo '<p style="color:#d63638;">⚠️ Plugin no completamente inicializado.</p>';
        return;
    }

    $current_metrics = mlp_get_current_metrics();
    $hosting_info = function_exists('mlp_detect_hosting_type') ? mlp_detect_hosting_type() : [];
    
    $cached_errors = get_transient('mlp_cached_errors_count');
    $error_count = $cached_errors !== false ? (int) $cached_errors : 0;

    $mem_color = $current_metrics && (float)($current_metrics['memory'] ?? 0) > 200 ? 'd63638' : '00a32a';
    $cpu_color = $current_metrics && (float)($current_metrics['cpu'] ?? 0) > 80 ? 'd63638' : '00a32a';
    $time_color = $current_metrics && (float)($current_metrics['time'] ?? 0) > 5 ? 'd63638' : '00a32a';
    $error_color = $error_count > 0 ? 'd63638' : '00a32a';

    $hosting_type = $hosting_info['type'] ?? 'Unknown';
    $hosting_provider = $hosting_info['provider'] ?? 'Unknown';
    $hosting_icon = match($hosting_type) {
        'cloud' => '☁️',
        'vps' => '🖥️',
        'dedicated' => '🏢',
        'shared_premium' => '⭐',
        'shared' => '🌐',
        default => '❓'
    };

    echo '<div style="padding:10px;">';

    echo '<p><strong>' . $hosting_icon . ' Hosting:</strong> ';
    echo esc_html(ucfirst($hosting_type));
    if ($hosting_provider !== 'Unknown' && $hosting_provider !== $hosting_type) {
        echo ' (' . esc_html($hosting_provider) . ')';
    }
    echo '</p>';

    if ($current_metrics) {
        echo '<p><strong>📈 Última petición:</strong><br>';
        echo '<span style="color:#' . $mem_color . ';">💾 Memoria: ' . esc_html($current_metrics['memory'] ?? '0') . ' MB</span><br>';
        echo '<span style="color:#' . $cpu_color . ';">⚙️ CPU: ' . esc_html($current_metrics['cpu'] ?? '0') . '%</span><br>';
        echo '<span style="color:#' . $time_color . ';">⏱️ Tiempo: ' . esc_html($current_metrics['time'] ?? '0') . ' s</span></p>';
    }

    $mem_limit = $hosting_info['memory_gb'] ?? 0;
    $mem_percent = $current_metrics && $mem_limit > 0 
        ? round((($current_metrics['memory'] ?? 0) / ($mem_limit * 1024)) * 100, 1) 
        : 0;
    $mem_status = $mem_percent > 80 ? '🔴' : ($mem_percent > 50 ? '🟡' : '🟢');
    
    echo '<p><strong>🏥 Estado:</strong><br>';
    echo $mem_status . ' Memoria: ' . esc_html($current_metrics['memory'] ?? '0') . ' MB (' . $mem_percent . '%)</p>';

    echo '<p><strong>🚨 Errores:</strong> ';
    echo '<span style="color:#' . $error_color . ';">' . $error_count . '</span></p>';

    $btn_url = $error_count > 0 
        ? admin_url('admin.php?page=memory-log-viewer&tab=diagnostic')
        : admin_url('admin.php?page=memory-log-viewer');
    $btn_text = $error_count > 0 ? '🔍 Ver diagnóstico' : '📊 Ver dashboard';
    
    echo '<p><a href="' . esc_url($btn_url) . '" class="button button-primary">' . $btn_text . '</a></p>';

    echo '</div>';
}

// ============================================================================
// 8. HOOK DE ADMIN-POST PARA EXPORTACIÓN (CORREGIDO)
// ============================================================================

// ============================================================================
// 9. HOOK DE ADMIN-POST PARA DEBUG MODE (OPCIONAL)
// ============================================================================
if (MLP_DEBUG_MODE) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>🐛 MODO DEBUG ACTIVO</strong></p>';
        echo '<p>El modo debug está activado. Esto puede afectar el rendimiento.</p>';
        echo '<p><em>Para desactivar, edita <code>memory-logger.php</code> y cambia:</em></p>';
        echo '<p><code>define(\'MLP_DEBUG_MODE\', false);</code></p>';
        echo '</div>';
    });
}

// ============================================================================
// 10. AGREGAR INTERVALO CRON PERSONALIZADO
// ============================================================================
add_filter('cron_schedules', function($schedules) {
    $schedules['mlp_15min'] = [
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display' => __('Cada 15 minutos', 'memory-logger')
    ];
    return $schedules;
});
// fin memory-logger.php 
