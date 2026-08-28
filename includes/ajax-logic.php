<?php
/**
 * Memory Logger Pro v13.3.0 - AJAX Logic
 * Manejo de peticiones AJAX robusto con carga dinámica de dependencias
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrar todos los hooks AJAX
 */
// Lightweight AJAX auth gate (safe in WP and non-WP environments)
if (!function_exists('mlp_ajax_require_auth')) {
    function mlp_ajax_require_auth() {
        if (function_exists('wp_verify_nonce')) {
            $nonce = isset($_POST['_mlp_nonce']) ? $_POST['_mlp_nonce'] : '';
            if (!wp_verify_nonce($nonce, 'mlp_ajax_action')) {
                if (function_exists('wp_send_json_error')) {
                    wp_send_json_error(['message' => 'Unauthorized'], 403);
                } else {
                    http_response_code(403);
                    echo json_encode(['error' => 'Unauthorized']);
                }
                exit;
            }
        }
    }
}
function mlp_register_ajax_hooks(): void {
    $actions = [
        'mlp_run_test' => 'mlp_ajax_run_test',
        'mlp_run_full_analysis' => 'mlp_ajax_run_full_analysis',
        'mlp_analyze_wordpress_plugins_optimized' => 'mlp_ajax_analyze_wordpress_plugins_optimized',
        'mlp_analyze_error_patterns' => 'mlp_ajax_analyze_error_patterns',
        'mlp_check_file_integrity' => 'mlp_ajax_check_file_integrity',
        'mlp_analyze_database' => 'mlp_ajax_analyze_database',
        'mlp_get_hosting_recommendations' => 'mlp_ajax_get_hosting_recommendations',
        'mlp_get_chart_data' => 'mlp_ajax_get_chart_data',
        'mlp_clear_all_logs_cache' => 'mlp_ajax_clear_all_logs_cache',
        'mlp_lazy_load_security' => 'mlp_ajax_lazy_load_security',
        'mlp_export_diagnostic_report_ajax' => 'mlp_ajax_export_diagnostic_report',
        // Quick Actions v12.8.0 - Unificado
        'mlp_optimize_db_complete' => 'mlp_ajax_optimize_db_complete',
        'mlp_refresh_health' => 'mlp_ajax_refresh_health',
    ];

    foreach ($actions as $action => $handler) {
        add_action("wp_ajax_$action", $handler);
    }
}

/**
 * Verificar permisos
 */
function mlp_ajax_require_admin(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Acceso denegado']);
    }
}

/**
 * Helper para respuestas AJAX seguras
 */
function mlp_safe_ajax_response(callable $callback): void {
    // Limpiar buffer de salida previo para evitar contaminación de JSON
    if (ob_get_length()) ob_clean();
    
    // Header JSON explícito
    header('Content-Type: application/json; charset=utf-8');

    try {
        $callback();
    } catch (Throwable $e) {
        wp_send_json_error([
            'message' => 'Error Interno: ' . $e->getMessage(),
            'code' => $e->getCode(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]);
    }
}

/**
 * AJAX: Probar métricas
 */
function mlp_ajax_run_test(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_run_test_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/core-logic.php';
        mlp_log_request();
        wp_send_json_success(['message' => 'OK']);
    });
}

/**
 * AJAX: Recomendaciones de Hosting (Fijo para evitar cuelgues)
 */
function mlp_ajax_get_hosting_recommendations(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_lazy_load_diagnostic_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/core-logic.php';
        
        // Usar caché o análisis ultraligero
        $recs = function_exists('mlp_get_hosting_recommendations') ? mlp_get_hosting_recommendations() : [];
        
        ob_start();
        ?>
        <div style="display:grid; gap:12px;">
            <?php if (!empty($recs)): ?>
                <?php foreach ($recs as $r): ?>
                    <div style="padding:12px; border-left:4px solid #2271b1; background:#f9f9f9;">
                        <strong style="font-size:13px; color:#1d2327;"><?php echo esc_html($r['title']); ?></strong>
                        <p style="margin:8px 0; font-size:11px; line-height:1.5;"><?php echo esc_html($r['description']); ?></p>
                        <div style="font-size:10px; color:#2271b1;"><strong>Acción:</strong> <?php echo esc_html($r['action']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="padding:10px; color:#00a32a;">✅ Tu hosting cumple con todos los requisitos recomendados.</p>
            <?php endif; ?>
        </div>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    });
}

/**
 * AJAX: Análisis de Plugins (Versión Completa)
 */
function mlp_ajax_analyze_wordpress_plugins_optimized(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_lazy_load_diagnostic_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/security-logic.php';
        
        // Context 'full' para análisis completo
        $results = function_exists('mlp_analyze_wordpress_plugins_unified') 
            ? mlp_analyze_wordpress_plugins_unified(['context' => 'full']) 
            : ['outdated' => [], 'active_count' => 0, 'total_plugins' => 0];
        
        ob_start();
        ?>
        <div style="margin-bottom:15px;">
            <h4 style="margin:0 0 10px 0; display:flex; align-items:center; gap:8px;">
                <span>🔌 Auditoría de Plugins</span>
                <span style="font-size:11px; background:#2271b1; color:white; padding:2px 8px; border-radius:10px;">
                    <?php echo $results['active_count'] ?? 0; ?>/<?php echo $results['total_plugins'] ?? 0; ?> activos
                </span>
            </h4>
            
            <?php if (!empty($results['outdated'])): ?>
            <div style="padding:12px; background:#fff8e1; border-left:4px solid #f0b849; border-radius:4px; margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong style="color:#dba617;">⚠️ Actualizaciones Pendientes (<?php echo count($results['outdated']); ?>)</strong>
                    <span style="font-size:11px; background:#f0b849; color:#000; padding:2px 8px; border-radius:3px;">Prioridad Alta</span>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:8px;">
                    <?php foreach ($results['outdated'] as $p): ?>
                        <div style="padding:8px; background:#fff; border-radius:3px; border:1px solid #f0f0f0;">
                            <div style="font-weight:600; font-size:12px;"><?php echo esc_html($p['plugin']); ?></div>
                            <div style="display:flex; align-items:center; gap:5px; font-size:11px;">
                                <span style="color:#d63638; font-weight:bold;"><?php echo esc_html($p['current']); ?></span>
                                <span>→</span>
                                <span style="color:#00a32a; font-weight:700;"><?php echo esc_html($p['latest']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results['requires_php_update'])): ?>
            <div style="padding:10px; background:#fcf0f1; border-left:4px solid #d63638; border-radius:4px; margin-bottom:10px;">
                <strong>❌ Incompatibilidad PHP (<?php echo count($results['requires_php_update']); ?>)</strong>
                <div style="font-size:11px; margin-top:5px;">
                    <?php foreach (array_slice($results['requires_php_update'], 0, 3) as $p): ?>
                        <div style="padding:3px 0;">
                            <strong><?php echo esc_html($p['plugin']); ?></strong> requiere PHP <?php echo esc_html($p['required_php']); ?> 
                            (actual: <?php echo esc_html($p['current_php']); ?>)
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results['abandoned'])): ?>
            <div style="padding:10px; background:#f9f9f9; border-left:4px solid #8c8f94; border-radius:4px; margin-bottom:10px;">
                <strong>⌛ Plugins Antiguos (<?php echo count($results['abandoned']); ?>)</strong>
                <div style="font-size:11px; margin-top:5px;">
                    <?php foreach (array_slice($results['abandoned'], 0, 3) as $p): ?>
                        <div style="padding:3px 0;">
                            <strong><?php echo esc_html($p['plugin']); ?></strong> - <?php echo esc_html($p['issue']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($results['outdated']) && empty($results['requires_php_update']) && empty($results['abandoned'])): ?>
                <div style="padding:12px; background:#f0fbf0; border-left:4px solid #00a32a; border-radius:4px;">
                    ✅ <strong>Estado óptimo</strong> - Todos los plugins están actualizados y compatibles
                </div>
            <?php endif; ?>
            
            <?php if (isset($results['security_score'])): ?>
                <div style="margin-top:15px; padding:10px; background:#f8f9fa; border-radius:4px; text-align:center;">
                    <div style="font-size:11px; color:#646970;">Puntuación de Seguridad</div>
                    <div style="font-size:32px; font-weight:bold; color:<?php 
                        echo $results['security_score'] >= 80 ? '#00a32a' : 
                             ($results['security_score'] >= 60 ? '#f0b849' : '#d63638'); 
                    ?>;">
                        <?php echo $results['security_score']; ?>/100
                    </div>
                    <div style="font-size:10px; color:#8c8f94; margin-top:5px;">
                        Último análisis: <?php echo $results['analysis_date'] ?? 'Ahora'; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    });
}

/**
 * Otros handlers asumen carga de core/security logic
 */
function mlp_ajax_run_full_analysis(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_lazy_load_diagnostic_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/security-logic.php';
        
        $res = function_exists('mlp_run_comprehensive_audit') 
            ? mlp_run_comprehensive_audit() 
            : ['global_score' => 0, 'plugin_score' => 0, 'db_score' => 0, 'error_score' => 0];

        ob_start();
        ?>
        <div style="padding:15px; background:#fff; border:1px solid #dcdcde; border-radius:4px;">
            <h3 style="margin:0 0 10px 0;">🏆 Score Global: <?php echo $res['global_score']; ?>/100</h3>
            <p style="margin:0; font-size:11px; color:#646970;">Plugins: <?php echo $res['plugin_score']; ?>% | DB: <?php echo $res['db_score']; ?>% | Errores: <?php echo $res['error_score']; ?>%</p>
        </div>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    });
}

function mlp_ajax_analyze_error_patterns(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_lazy_load_diagnostic_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/core-logic.php';
        
        $res = function_exists('mlp_analyze_error_patterns') 
            ? mlp_analyze_error_patterns() 
            : ['total_errors' => 0, 'patterns' => []];

        ob_start();
        ?>
        <div style="padding:10px; background:#f9f9f9; border-radius:4px;">
            <h4 style="margin-top:0; color:#1d2327;">🔍 Análisis de Errores</h4>
            
            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; margin-bottom:15px;">
                <div style="text-align:center; padding:10px; background:#fff; border-radius:4px;">
                    <div style="font-size:12px; color:#646970;">Total Errores</div>
                    <div style="font-size:24px; font-weight:bold; color:#2271b1;"><?php echo $res['total_errors']; ?></div>
                </div>
                <div style="text-align:center; padding:10px; background:#fff; border-radius:4px;">
                    <div style="font-size:12px; color:#646970;">Críticos</div>
                    <div style="font-size:24px; font-weight:bold; color:#d63638;"><?php echo $res['severity_counts']['critical'] ?? 0; ?></div>
                </div>
                <div style="text-align:center; padding:10px; background:#fff; border-radius:4px;">
                    <div style="font-size:12px; color:#646970;">Último Error</div>
                    <div style="font-size:11px; color:#646970; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($res['last_error_date'] ?? 'N/A'); ?>">
                        <?php echo esc_html($res['last_error_date'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>
            
            <?php if(!empty($res['error_sources'])): ?>
            <div style="margin-bottom:15px;">
                <h5 style="margin:10px 0 5px 0; font-size:12px; color:#1d2327;">📁 Fuentes de Error</h5>
                <div style="display:flex; flex-wrap:wrap; gap:5px;">
                    <?php foreach(array_slice($res['error_sources'], 0, 8) as $source => $count): ?>
                        <span style="display:inline-block; padding:4px 8px; background:#e8f2fc; border-radius:3px; font-size:11px;">
                            <?php echo esc_html($source); ?> <strong>(<?php echo $count; ?>)</strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($res['recurrent_errors'])): ?>
            <div style="margin-bottom:15px;">
                <h5 style="margin:10px 0 5px 0; font-size:12px; color:#1d2327;">🔄 Errores Recurrentes (últimos 7 días)</h5>
                <div style="font-size:11px; background:#fff; padding:10px; border-radius:4px; max-height:200px; overflow-y:auto;">
                    <?php foreach($res['recurrent_errors'] as $error): if($error['count'] > 1): ?>
                        <div style="padding:8px 0; border-bottom:1px solid #f0f0f0;">
                            <div style="display:flex; justify-content:space-between;">
                                <span style="color:#d63638; font-weight:bold;">Repeticiones: <?php echo $error['count']; ?></span>
                                <span style="color:#646970; font-size:10px;">Último: <?php echo $error['last_seen']; ?></span>
                            </div>
                            <div style="color:#50575e; margin-top:3px; font-family:monospace; font-size:10px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($error['sample']); ?>">
                                <?php echo esc_html(substr($error['sample'], 0, 100)); ?>...
                            </div>
                        </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($res['error_timeline'])): ?>
            <div>
                <h5 style="margin:10px 0 5px 0; font-size:12px; color:#1d2327;">📈 Frecuencia por Día</h5>
                <div style="display:flex; align-items:flex-end; gap:3px; height:100px; padding:10px; background:#fff; border-radius:4px;">
                    <?php 
                    $max = !empty($res['error_timeline']) ? max($res['error_timeline']) : 0;
                    $timeline = array_slice($res['error_timeline'], -14); // Últimos 14 días
                    foreach($timeline as $day => $count): 
                        $height = $max > 0 ? ($count / $max) * 80 : 0;
                    ?>
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center;">
                            <div style="width:100%; min-width:5px; height:<?php echo max(2, $height); ?>px; background:#2271b1; border-radius:2px 2px 0 0;" title="<?php echo $day . ': ' . $count; ?>"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:9px; color:#646970; margin-top:2px;">
                    <span>Hace 14 días</span>
                    <span>Hoy</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    });
}

function mlp_ajax_check_file_integrity(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_lazy_load_diagnostic_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/core-logic.php';
        $integrity = mlp_check_file_integrity();
        if (!empty($integrity['issues'])) {
            $msg = '<div style="color:#d63638;">❌ Problemas detectados: ' . implode('<br>', $integrity['issues']) . '</div>';
            wp_send_json_success(['html' => $msg]);
        } else {
            wp_send_json_success(['html' => '<div style="color:#00a32a;">✅ Núcleo verificado.</div>']);
        }
    });
}

function mlp_ajax_analyze_database(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_lazy_load_diagnostic_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/core-logic.php';
        
        $res = function_exists('mlp_analyze_database') 
            ? mlp_analyze_database() 
            : ['health_score' => 0, 'issues' => [], 'tables_count' => 0, 'total_size' => '0 B'];

        ob_start();
        ?>
        <div style="padding:15px; background:#f9f9f9; border-radius:4px;">
            <h4 style="margin-top:0; color:#1d2327;">🗄️ Análisis de Base de Datos</h4>
            
            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; margin-bottom:15px;">
                <div style="text-align:center; padding:10px; background:#fff; border-radius:4px; border:1px solid #dcdcde;">
                    <div style="font-size:12px; color:#646970;">Salud DB</div>
                    <div style="font-size:24px; font-weight:bold; color:<?php 
                        echo $res['health_score'] >= 80 ? '#00a32a' : 
                             ($res['health_score'] >= 60 ? '#f0b849' : '#d63638'); 
                    ?>;"><?php echo $res['health_score']; ?>/100</div>
                </div>
                <div style="text-align:center; padding:10px; background:#fff; border-radius:4px; border:1px solid #dcdcde;">
                    <div style="font-size:12px; color:#646970;">Tablas</div>
                    <div style="font-size:24px; font-weight:bold; color:#2271b1;"><?php echo $res['tables_count']; ?></div>
                </div>
                <div style="text-align:center; padding:10px; background:#fff; border-radius:4px; border:1px solid #dcdcde;">
                    <div style="font-size:12px; color:#646970;">Tamaño Total</div>
                    <div style="font-size:18px; font-weight:bold; color:#1d2327;"><?php echo $res['total_size']; ?></div>
                </div>
                <div style="text-align:center; padding:10px; background:#fff; border-radius:4px; border:1px solid #dcdcde;">
                    <div style="font-size:12px; color:#646970;">Fragmentación</div>
                    <div style="font-size:18px; font-weight:bold; color:<?php echo ($res['fragmentation_rate'] ?? 0) > 10 ? '#d63638' : '#00a32a'; ?>">
                        <?php echo $res['fragmentation_rate'] ?? 0; ?>%
                    </div>
                </div>
            </div>
            
            <?php if(!empty($res['issues'])): ?>
            <div style="margin-bottom:15px;">
                <h5 style="margin:10px 0 5px 0; font-size:12px; color:#d63638;">⚠️ Problemas Detectados</h5>
                <div style="background:#fff; border-radius:4px; padding:10px; font-size:11px;">
                    <?php foreach($res['issues'] as $i): 
                        $desc = is_array($i) ? $i['description'] : $i;
                    ?>
                        <div style="padding:8px 0; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; gap:8px;">
                            <span style="color:#d63638; font-size:16px;">●</span>
                            <span><?php echo esc_html($desc); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div>
                    <h5 style="margin:10px 0 5px 0; font-size:12px; color:#1d2327;">📊 Distribución</h5>
                    <div style="font-size:11px; background:#fff; padding:10px; border-radius:4px;">
                        <div style="display:flex; justify-content:space-between; padding:3px 0;">
                            <span>Autoload:</span>
                            <span style="font-weight:bold; color:<?php 
                                $al_size = isset($res['autoload_size_bytes']) ? $res['autoload_size_bytes'] : (intval($res['autoload_size'] ?? 0) * 1024); // Estimación si no hay bytes
                                echo $al_size > 800*1024 ? '#d63638' : '#00a32a'; 
                            ?>;">
                                <?php echo $res['autoload_size'] ?? '0 B'; ?>
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:3px 0;">
                            <span>Overhead:</span>
                            <span style="font-weight:bold; color:<?php 
                                $oh_size = isset($res['overhead_size_bytes']) ? $res['overhead_size_bytes'] : (intval($res['overhead_size'] ?? 0) * 1024);
                                echo $oh_size > 10*1024*1024 ? '#d63638' : '#00a32a'; 
                            ?>;">
                                <?php echo $res['overhead_size'] ?? '0 B'; ?>
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:3px 0;">
                            <span>Transitorios:</span>
                            <span><?php echo $res['transients_size'] ?? '0 B'; ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding:3px 0;">
                            <span>Transitorios caducados:</span>
                            <span><?php echo $res['expired_transients'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if(!empty($res['large_tables'])): ?>
                <div>
                    <h5 style="margin:10px 0 5px 0; font-size:12px; color:#1d2327;">📈 Tablas Grandes</h5>
                    <div style="font-size:11px; background:#fff; padding:10px; border-radius:4px;">
                        <?php foreach(array_slice($res['large_tables'], 0, 5) as $table): ?>
                            <div style="padding:5px 0; border-bottom:1px solid #f0f0f0;">
                                <?php echo esc_html($table); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if($res['health_score'] >= 80): ?>
                <div style="margin-top:15px; padding:10px; background:#f0fbf0; border-radius:4px; text-align:center;">
                    ✅ Base de datos en buen estado
                </div>
            <?php endif; ?>
        </div>
        <?php
        wp_send_json_success(['html' => ob_get_clean()]);
    });
}

function mlp_ajax_get_chart_data(): void {
    mlp_safe_ajax_response(function() {
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/core-logic.php';
        wp_send_json_success(mlp_get_advanced_stats());
    });
}

function mlp_ajax_clear_all_logs_cache(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_clear_all_logs_cache_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/admin-logic.php';
        mlp_clear_logs_and_cache();
        wp_send_json_success(['message' => 'Limpieza completada']);
    });
}

function mlp_ajax_lazy_load_security(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_lazy_load_security_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/security-logic.php';
        mlp_analyze_wordpress_plugins_unified();
        wp_send_json_success(['html' => 'OK']);
    });
}

function mlp_ajax_export_diagnostic_report(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_export_diagnostic_report_nonce', 'security');
        mlp_ajax_require_admin();
        require_once MLP_PATH . 'includes/admin-logic.php';
        $format = sanitize_text_field($_POST['format'] ?? 'json');
        wp_send_json_success([
            'content' => mlp_generate_diagnostic_report($format),
            'filename' => 'report.' . $format
        ]);
    });
}

// ============================================================================
// QUICK ACTIONS v12.8.0 - Unificado para simplificar UI
// ============================================================================

/**
 * AJAX: Optimización completa de BD (transients + tablas)
 * Unifica limpiar transients + optimizar tablas en una sola operación
 */
function mlp_ajax_optimize_db_complete(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_clear_cache_nonce', 'security');
        mlp_ajax_require_admin();
        
        global $wpdb;
        
        if (!isset($wpdb)) {
            wp_send_json_error(['message' => 'Base de datos no disponible']);
            return;
        }
        
        $results = [
            'transients_deleted' => 0,
            'tables_optimized' => 0,
            'errors' => []
        ];
        
        // 1. Limpiar transients caducados
        $count_transients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
        $count_site_transients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
        
        $total_to_delete = intval($count_transients) + intval($count_site_transients);
        
        if ($total_to_delete > 0) {
            $deleted_user = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
            $deleted_site = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
            $results['transients_deleted'] = ($deleted_user !== false ? $deleted_user : 0) + ($deleted_site !== false ? $deleted_site : 0);
        }
        
        // 2. Optimizar tablas - Método mejorado para InnoDB
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $wp_prefix = $wpdb->prefix;
        $optimized = 0;
        $errors = [];
        $optimization_log = [];
        $skipped_large = 0;
        
        // Guards de seguridad: límite de tiempo y tamaño máximo de tabla
        $start_time = microtime(true);
        $time_limit = 25; // Segundos máximos de la operación (evita matar hosting compartidos)
        $max_table_size_mb = 500; // Tablas mayores se saltan (ALTER bloquea la tabla)

        foreach ($tables as $table) {
            // Límite de tiempo global
            if ((microtime(true) - $start_time) > $time_limit) {
                $optimization_log[] = "⏱️ Tiempo límite alcanzado (" . $time_limit . "s), se detiene la optimización.";
                break;
            }
            
            $table_name = $table[0];
            
            if (strpos($table_name, $wp_prefix) !== 0) continue;
            
            // Obtener tamaño y engine de la tabla
            $table_info = $wpdb->get_row($wpdb->prepare(
                "SELECT ENGINE, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1048576, 2) AS size_mb FROM information_schema.TABLES WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s",
                DB_NAME,
                $table_name
            ));
            if (!$table_info) continue;
            
            // Saltar tablas grandes: ALTER/OPTIMIZE las bloquearía demasiado tiempo
            $size_mb = (float)$table_info->size_mb;
            if ($size_mb > $max_table_size_mb) {
                $skipped_large++;
                $optimization_log[] = "⚠️ {$table_name} ({$size_mb} MB) saltada: demasiado grande para optimizar en caliente.";
                continue;
            }
            
            // Para InnoDB, usar ALTER TABLE para regenerar
            $engine = $table_info->engine;
            
            if ($engine === 'InnoDB') {
                // InnoDB: ALTER TABLE para regenerar (más efectivo que OPTIMIZE)
                $alter_result = $wpdb->query("ALTER TABLE {$table_name} ENGINE=InnoDB");
                if ($alter_result !== false) {
                    $optimized++;
                    $optimization_log[] = "✓ {$table_name} regenerada (InnoDB)";
                } else {
                    $errors[] = $table_name . ' (ALTER falló)';
                    $optimization_log[] = "✗ {$table_name} (ALTER falló)";
                }
            } else {
                // MyISAM/otros: OPTIMIZE TABLE normal
                $opt_result = $wpdb->query("OPTIMIZE TABLE {$table_name}");
                if ($opt_result !== false) {
                    $optimized++;
                    $optimization_log[] = "✓ {$table_name} optimizada";
                } else {
                    $errors[] = $table_name . ' (OPTIMIZE falló)';
                    $optimization_log[] = "✗ {$table_name} (OPTIMIZE falló)";
                }
            }
        }
        
        // 3. Invalidar TODOS los transients del plugin
        delete_transient('mlp_system_health_v9');
        delete_transient('mlp_quick_security_scan_' . md5(MLP_PATH));
        delete_transient('mlp_wp_plugins_analysis_v4_' . md5(MLP_PATH . get_bloginfo('version')));
        
        // Eliminar cualquier transient que comience con mlp_
        $deleted_transients = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mlp_%'");
        $deleted_transients += $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_mlp_%'");
        
        // Forzar recalculado de salud
        require_once MLP_PATH . 'includes/core-logic.php';
        mlp_get_system_health(true); // true = forzar bypass de caché
        
        // Generar mensaje
        $msg_parts = [];
        if ($results['transients_deleted'] > 0) {
            $msg_parts[] = $results['transients_deleted'] . ' transients eliminados';
        }
        if ($optimized > 0) {
            $msg_parts[] = $optimized . ' tablas regeneradas';
        }
        
        if (empty($msg_parts) && $skipped_large === 0) {
            wp_send_json_success([
                'message' => '✅ La base de datos ya estaba optimizada.',
                'count' => 0,
                'log' => $optimization_log
            ]);
            return;
        }
        
        $msg = '✅ Optimización completada: ' . implode(', ', $msg_parts) . '.';
        if ($skipped_large > 0) {
            $msg .= ' ⚠️ ' . $skipped_large . ' tablas grandes omitidas (>' . $max_table_size_mb . ' MB).';
        }
        if (!empty($errors)) {
            $msg .= ' (' . count($errors) . ' tablas con errores).';
        }
        
        wp_send_json_success([
            'message' => $msg,
            'count' => $results['transients_deleted'] + $optimized,
            'details' => $results,
            'log' => $optimization_log,
            'deleted_transients' => $deleted_transients
        ]);
    });
}

/**
 * AJAX: Refrescar Salud y Gráficos (reutiliza lógica existente)
 */
function mlp_ajax_refresh_health(): void {
    mlp_safe_ajax_response(function() {
        check_ajax_referer('mlp_clear_cache_nonce', 'security');
        mlp_ajax_require_admin();
        
        // Invalidar todos los transients del plugin
        mlp_purge_cache();
        
        // Forzar recalculado
        mlp_get_system_health(true); // true = forzar bypass de caché
        
        wp_send_json_success([
            'message' => '✅ Caché eliminada. Recalculando métricas...',
            'reload' => true
        ]);
    });
}
