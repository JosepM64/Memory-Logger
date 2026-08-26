<?php
/**
 * Memory Logger Pro v13.0.0 - Vista Dashboard (Pestaña 1)
 * Restaurado íntegramente con todos los botones, ayuda y columnas originales
 * MEJORADO: Añadido Security Score y Visitor Stats
 */

if (!defined('ABSPATH')) {
    exit;
}

function mlp_render_dashboard_view(
    array $opts,
    array $health,
    $current_metrics,
    int $cpu_count,
    float $cpu_effective,
    array $os_info,
    array $hosting_info,
    array $calibration,
    int $security_score = 0,
    array $security_issues = [],
    array $visitor_stats = []
): void {
    $load_status = mlp_get_load_status();
    $server_type = mlp_detect_server_type();
    $cpu_ratio = $cpu_count > 0 ? round(($cpu_effective / $cpu_count) * 100, 1) : 0;
    
    // Obtener estadísticas para comparativas
    $stats = function_exists('mlp_get_advanced_stats') ? mlp_get_advanced_stats() : [];
    $avg_mem = $stats['avg_memory'] ?? 0;
    $avg_time = $stats['avg_time'] ?? 0;
    $avg_cpu = $stats['avg_cpu'] ?? 0;
    
    // Contar plugins activos vs total
    $all_plugins = function_exists('get_plugins') ? get_plugins() : [];
    $active_plugins = function_exists('get_option') ? get_option('active_plugins', []) : [];
    $plugins_summary = count($all_plugins) . ' (' . count($active_plugins) . ' activos)';
    
    // Generar insight automático (MEJORADO v12.9.0 - informativo y contextual)
    $insight = '';
    $insight_type = 'info';
    $insight_bg = '#f8f9fa';
    $insight_border = '#646970';
    
    // Calcular factores activos
    $active_factors = [];
    if ($health['db_fragmentation'] > 10) $active_factors[] = 'fragmentación';
    if ($health['autoload_mb'] > 0.8) $active_factors[] = 'autoload';
    if ($health['transients_overhead_kb'] > 100) $active_factors[] = 'overhead';
    if ($health['db_size_mb'] > 100) $active_factors[] = 'tablas grandes';
    
    if (!empty($active_factors)) {
        $factors_text = implode(', ', $active_factors);
        $insight = "📊 Score afectado por: $factors_text.";
        $insight_type = 'warning';
        $insight_bg = '#fff8e1';
        $insight_border = '#f0b849';
        
        // Añadir explicación según el factor principal
        if (in_array('overhead', $active_factors) && $health['transients_overhead_kb'] > 1000) {
            $insight .= " El overhead alto puede ser normal en tablas muy activas.";
        }
        if (in_array('fragmentation', $active_factors) && $health['db_fragmentation'] > 100) {
            $insight .= " Para InnoDB, regenerar con ALTER TABLE puede ayudar.";
        }
        if (in_array('tablas grandes', $active_factors)) {
            $insight .= " Tablas >100MB penalizan el score. Considera archivar datos.";
        }
    } elseif ($health['db_health_score'] < 60) {
        $insight = '📉 Salud DB en ' . $health['db_health_score'] . '/100. Revisa los factores activos.';
        $insight_type = 'warning';
        $insight_bg = '#fff8e1';
        $insight_border = '#f0b849';
    } elseif ($security_score < 60) {
        $insight = '🛡️ Security Score bajo (' . $security_score . '/100). Revisa la pestaña Diagnóstico.';
        $insight_type = 'warning';
        $insight_bg = '#fff8e1';
        $insight_border = '#f0b849';
    } elseif ($load_status['is_high_load']) {
        $insight = '⚡ Servidor bajo carga (' . $load_status['load_avg'] . '). Considera revisar plugins o aumentar recursos.';
        $insight_type = 'warning';
        $insight_bg = '#fff8e1';
        $insight_border = '#f0b849';
    }
    
    // 1. CABECERA
    echo '<div class="ml-card" style="padding:15px; margin-bottom:15px;">';
    echo '<p style="margin:0; color:#646970; font-size:12px;">Auditor de rendimiento PRO universal: Gráficos, Memoria, Tiempo, CPU inteligente, Tamaño mejorado, Diagnóstico & Seguridad avanzado.</p>';
    
    // Icono según tipo de hosting
    $hosting_icon = match($hosting_info['type']) {
        'cloud' => '☁️',
        'vps' => '🖥️',
        'dedicated' => '🏢',
        'shared_premium' => '⭐',
        'shared' => '🌐',
        default => '🌍'
    };
    $hosting_type_label = match($hosting_info['type']) {
        'cloud' => 'Cloud',
        'vps' => 'VPS',
        'dedicated' => 'Dedicado',
        'shared_premium' => 'Compartido Premium',
        'shared' => 'Compartido',
        default => 'Desconocido'
    };
    
    echo '<div style="margin-top:15px; padding:10px; background:#f8f9fa; border-radius:4px; font-size:11px; color:#646970;">';
    echo '<div style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom:5px;">';
    echo '<span><strong>' . $hosting_icon . ' Hosting:</strong> ' . esc_html($hosting_type_label);
    if (!empty($hosting_info['provider']) && $hosting_info['provider'] !== 'Unknown') {
        echo ' (' . esc_html($hosting_info['provider']) . ')';
    }
    echo '</span>';
    echo '<span><strong>🖥️ Servidor:</strong> ' . esc_html($server_type) . '</span>';
    echo '<span><strong>⚙️ CPU Ratio:</strong> ' . esc_html($cpu_ratio) . '%</span>';
    echo '<span><strong>📐 Calibración:</strong> ' . esc_html(number_format($calibration['factor'], 2)) . 'x</span>';
    echo '</div>';
    
    echo '<div style="display:flex; flex-wrap:wrap; gap:15px; align-items:center;">';
    $cpu_load_color = $load_status['load_avg'] > 10 ? '#d63638' : ($load_status['load_avg'] > 5 ? '#f0b849' : '#00a32a');
    echo '<span><strong>Estado del sistema:</strong> CPU Load: <span style="color:' . $cpu_load_color . '; font-weight:600;">' . esc_html($load_status['load_avg']) . '</span> | ';
    $mem_color = $load_status['mem_percent'] > 80 ? '#d63638' : ($load_status['mem_percent'] > 60 ? '#f0b849' : '#00a32a');
    echo 'Memoria: <span style="color:' . $mem_color . '; font-weight:600;">' . esc_html(round($load_status['mem_percent'], 1)) . '%</span> | ';
    
    if ($load_status['is_critical']) echo '<span style="color:#d63638;">🚨 Carga crítica - Throttling activo (x' . $load_status['throttle_factor'] . ')</span> ';
    elseif ($load_status['is_high_load']) echo '<span style="color:#f0b849;">🔄 Carga alta - Throttling activo (x' . $load_status['throttle_factor'] . ')</span> ';
    else echo '<span style="color:#00a32a;">✅ Carga normal</span> ';
    echo esc_html($os_info['details']) . '</span></div></div></div>';

    // 2. ÚLTIMA PETICIÓN CON TENDENCIAS (MEJORADO v12.9.0)
    if ($current_metrics) {
        echo '<div class="ml-card" style="padding:15px; margin-bottom:15px;">';
        echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">';
        echo '<h3 style="margin:0; font-size:14px;"><span style="font-size:16px;">📊</span> Última petición</h3>';
        echo '<span style="font-size:11px; color:#646970;">' . esc_html($current_metrics['date']) . '</span>';
        echo '</div>';
        
        echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-bottom:15px;">';
        $m_mem_c = ((float)$current_metrics['memory'] > $opts['threshold_mb']) ? '#d63638' : '#00a32a';
        $mem_trend = $avg_mem > 0 ? ((float)$current_metrics['memory'] > $avg_mem ? '↑' : '↓') : '';
        $mem_trend_c = $avg_mem > 0 ? ((float)$current_metrics['memory'] > $avg_mem ? '#d63638' : '#00a32a') : '#646970';
        echo '<div style="padding:12px; background:#fff; border:1px solid #eee; border-radius:4px; border-left:4px solid ' . $m_mem_c . ';">';
        echo '<div style="font-size:11px; font-weight:600; margin-bottom:3px; color:#646970;">🧠 Memoria</div>';
        echo '<div style="font-size:20px; font-weight:700; color:' . $m_mem_c . ';">' . number_format((float)$current_metrics['memory'], 2) . ' MB <span style="font-size:12px; color:' . $mem_trend_c . ';">' . $mem_trend . '</span></div>';
        echo '<div style="font-size:10px; color:#8c8f94;">Promedio: ' . number_format($avg_mem, 1) . ' MB</div></div>';

        $m_time_c = ((float)$current_metrics['time'] > $opts['time_risk']) ? '#d63638' : '#00a32a';
        $time_trend = $avg_time > 0 ? ((float)$current_metrics['time'] > $avg_time ? '↑' : '↓') : '';
        $time_trend_c = $avg_time > 0 ? ((float)$current_metrics['time'] > $avg_time ? '#d63638' : '#00a32a') : '#646970';
        echo '<div style="padding:12px; background:#fff; border:1px solid #eee; border-radius:4px; border-left:4px solid ' . $m_time_c . ';">';
        echo '<div style="font-size:11px; font-weight:600; margin-bottom:3px; color:#646970;">⏱️ Tiempo</div>';
        echo '<div style="font-size:20px; font-weight:700; color:' . $m_time_c . ';">' . number_format((float)$current_metrics['time'], 3) . ' s <span style="font-size:12px; color:' . $time_trend_c . ';">' . $time_trend . '</span></div>';
        echo '<div style="font-size:10px; color:#8c8f94;">Promedio: ' . number_format($avg_time, 2) . ' s</div></div>';

        $m_cpu_c = ((float)$current_metrics['cpu'] > 80) ? '#d63638' : '#00a32a';
        $cpu_trend = $avg_cpu > 0 ? ((float)$current_metrics['cpu'] > $avg_cpu ? '↑' : '↓') : '';
        $cpu_trend_c = $avg_cpu > 0 ? ((float)$current_metrics['cpu'] > $avg_cpu ? '#d63638' : '#00a32a') : '#646970';
        echo '<div style="padding:12px; background:#fff; border:1px solid #eee; border-radius:4px; border-left:4px solid ' . $m_cpu_c . ';">';
        echo '<div style="font-size:11px; font-weight:600; margin-bottom:3px; color:#646970;">⚡ CPU</div>';
        echo '<div style="font-size:20px; font-weight:700; color:' . $m_cpu_c . ';">' . $current_metrics['cpu'] . '% <span style="font-size:12px; color:' . $cpu_trend_c . ';">' . $cpu_trend . '</span></div>';
        echo '<div style="font-size:10px; color:#8c8f94;">Promedio: ' . number_format($avg_cpu, 1) . '%</div></div>';

        echo '<div style="padding:12px; background:#fff; border:1px solid #eee; border-radius:4px; border-left:4px solid #646970;">';
        echo '<div style="font-size:11px; font-weight:600; margin-bottom:3px; color:#646970;">📏 Tamaño</div>';
        echo '<div style="font-size:20px; font-weight:700; color:#00a32a;">' . number_format((float)$current_metrics['size'], 2) . ' KB</div>';
        echo '<div style="font-size:10px; color:#8c8f94;">Máx: ' . ($current_metrics['max_size']??'N/A') . ' KB</div></div>';
        echo '</div>';

        echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">';
        echo '<div style="padding:10px; background:#f8f9fa; border-radius:4px; border-left:3px solid #2271b1;"><div style="font-size:10px; font-weight:600; color:#646970;">🗄️ SQL Queries</div><div style="font-size:16px; font-weight:700;">' . $current_metrics['sql'] . '</div><div style="font-size:9px; color:#8c8f94;">Consultas</div></div>';
        echo '<div style="padding:10px; background:#f8f9fa; border-radius:4px; border-left:3px solid #00a32a;"><div style="font-size:10px; font-weight:600; color:#646970;">🌐 HTTP Status</div><div style="font-size:16px; font-weight:700; color:#00a32a;">' . $current_metrics['http'] . '</div><div style="font-size:9px; color:#8c8f94;">Código de respuesta</div></div>';
        echo '<div style="padding:10px; background:#f8f9fa; border-radius:4px; border-left:3px solid #646970;"><div style="font-size:10px; font-weight:600; color:#646970;">🔧 Método</div><div style="font-size:14px; font-weight:700;">' . $current_metrics['size_method'] . '</div><div style="font-size:9px; color:#8c8f94;">' . $current_metrics['size_method'] . '</div></div>';
        echo '</div></div>';
    }

    // 3. GRID DETALLES
    echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:15px; margin-bottom:15px;">';
    
    // Software + Plugins Summary (MEJORADO v12.9.0)
    echo '<div class="ml-card" style="padding:12px; margin:0;"><strong>📦 Software</strong><hr style="margin:8px 0; border:none; border-top:1px solid #eee;"><div style="font-size:11px; line-height:1.8;">';
    echo '<div>WP: ' . esc_html($health['wp_ver']) . '</div>';
    echo '<div>PHP: ' . esc_html($health['php_ver']) . '</div>';
    echo '<div>Tema: ' . esc_html($health['theme']) . '</div>';
    echo '<div>Plugins: ' . esc_html($plugins_summary) . '</div>';
    echo '</div></div>';

    // CPU
    echo '<div class="ml-card" style="padding:12px; margin:0;"><strong>🖥️ CPU Inteligente</strong><hr style="margin:8px 0; border:none; border-top:1px solid #eee;"><div style="font-size:11px; line-height:1.8;">';
    echo '<div>CPU Física: ' . $cpu_count . ' núcleos</div>';
    echo '<div>CPU Efectiva: <span style="color:#d63638;">' . number_format((float)$cpu_effective, 2, ',', '.') . ' núcleos</span></div>';
    echo '<div>Ratio: ' . number_format((float)(($cpu_effective/$cpu_count)*100), 1, ',', '.') . '% de ' . $cpu_count . '</div>';
    echo '<div>Calibración: ' . number_format((float)$calibration['factor'], 2, ',', '.') . 'x</div>';
    echo '</div></div>';

    // Cache
    echo '<div class="ml-card" style="padding:12px; margin:0;"><strong>⚡ Cache</strong><hr style="margin:8px 0; border:none; border-top:1px solid #eee;"><div style="font-size:11px; line-height:1.8;">';
    echo '<div>Obj. Cache: <span style="color:' . esc_attr($health['oc_class']) . ';">' . ($health['oc_stat']=='Activo'?'✅':'❌') . ' ' . esc_html($health['oc_stat']) . '</span></div>';
    echo '<div>Latencia: <span style="color:' . esc_attr($health['oc_class']) . ';">' . number_format((float)$health['oc_lat'], 2, ',', '.') . ' ms</span></div>';
    echo '<div>OPcache: ' . number_format((float)$health['opcache_hit'], 1, ',', '.') . '%</div>';
    echo '</div></div>';

    // DB - CON DESGLOSE DE FACTORES
    echo '<div class="ml-card" style="padding:12px; margin:0;"><strong>🗄️ Base de Datos</strong><hr style="margin:8px 0; border:none; border-top:1px solid #eee;"><div style="font-size:11px; line-height:1.8;">';
    echo '<div>Salud DB: <span style="color:' . esc_attr($health['db_health_score_color']) . '; font-weight:600;">' . number_format((int)$health['db_health_score'], 0, ',', '.') . '/100</span></div>';
    echo '<div>Autoload: <span style="color:' . esc_attr($health['autoload_color']) . ';">' . number_format((float)$health['autoload_kb'], 1, ',', '.') . ' KB</span></div>';
    echo '<div>Transients: ' . number_format((int)$health['transients_active'], 0, ',', '.') . ' activos</div>';
    echo '<div>Latencia DB: <span style="color:' . esc_attr($health['db_lat_color']) . ';">' . number_format((float)$health['db_lat'], 2, ',', '.') . ' ms</span></div>';
    echo '<div>BD: ' . esc_html($health['db_total_size_formatted']) . '</div>';
    echo '<div>Tablas: ' . number_format((int)$health['table_count'], 0, ',', '.') . '</div>';
    echo '<div>Fragmentación: <span style="color:' . ($health['db_fragmentation'] > 10 ? '#d63638' : '#00a32a') . ';">' . number_format((float)$health['db_fragmentation'], 1, ',', '.') . '%</span></div>';
    if (isset($health['transients_overhead_kb']) && $health['transients_overhead_kb'] > 0) {
        echo '<div style="color:' . ($health['transients_overhead_kb'] > 100 ? '#d63638' : '#f0b849') . ';">Overhead: ' . number_format((float)$health['transients_overhead_kb'], 1, ',', '.') . ' KB</div>';
    }
    
    // Desglose de factores del health score (NUEVO v12.9.0)
    echo '<div style="margin-top:8px; padding-top:8px; border-top:1px dashed #ddd; font-size:9px; color:#8c8f94;">';
    echo '<strong>Factores del score:</strong> ';
    $factors = [];
    if ($health['db_fragmentation'] > 10) $factors[] = 'Frag.';
    if ($health['autoload_mb'] > 0.8) $factors[] = 'Autoload';
    if ($health['transients_overhead_kb'] > 100) $factors[] = 'Overhead';
    if ($health['db_size_mb'] > 100) $factors[] = 'Tablas grandi';
    echo empty($factors) ? 'Sin penalizaciones' : implode(', ', $factors);
    echo '</div>';
    
    // Nota sobre aproximación
    echo '<div style="margin-top:4px; font-size:8px; color:#999; font-style:italic;">* Score aproximado. Depende de múltiples factores.</div>';
    echo '</div></div>';

    // VISITANTES
    echo '<div class="ml-card" style="padding:12px; margin:0;"><strong>👥 Visitantes</strong><hr style="margin:8px 0; border:none; border-top:1px solid #eee;"><div style="font-size:11px; line-height:1.8;">';
    
    // Extraer contadores de visitor_stats (estructura variable)
    $human_count = 0;
    $bot_count = 0;
    $unknown_count = 0;
    
    if (!empty($visitor_stats) && is_array($visitor_stats)) {
        foreach ($visitor_stats as $type => $data) {
            if (is_array($data)) {
                $human_count += (int)($data['human'] ?? 0);
                $bot_count += (int)($data['bot'] ?? 0);
                $unknown_count += (int)($data['unknown'] ?? 0);
            } else {
                if ($type === 'human') $human_count += (int)$data;
                elseif ($type === 'bot') $bot_count += (int)$data;
                elseif ($type === 'unknown') $unknown_count += (int)$data;
            }
        }
    }
    
    $total_visitors = $human_count + $bot_count + $unknown_count;
    
    if ($total_visitors > 0) {
        $human_pct = round(($human_count / $total_visitors) * 100, 1);
        $bot_pct = round(($bot_count / $total_visitors) * 100, 1);
        $unknown_pct = round(($unknown_count / $total_visitors) * 100, 1);
        
        echo '<div style="display:flex; gap:5px; margin-bottom:8px;">';
        echo '<div style="flex:1; background:#00a32a; height:8px; border-radius:4px;" title="Humanos: ' . $human_pct . '%"></div>';
        echo '<div style="flex:1; background:#2271b1; height:8px; border-radius:4px;" title="Bots: ' . $bot_pct . '%"></div>';
        echo '<div style="flex:1; background:#c3c4c7; height:8px; border-radius:4px;" title="Desconocidos: ' . $unknown_pct . '%"></div>';
        echo '</div>';
        
        echo '<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:5px; text-align:center;">';
        echo '<div><div style="font-size:14px; font-weight:700; color:#00a32a;">' . number_format($human_count, 0, ',', '.') . '</div><div style="font-size:9px; color:#00a32a;">👤 Human</div></div>';
        echo '<div><div style="font-size:14px; font-weight:700; color:#2271b1;">' . number_format($bot_count, 0, ',', '.') . '</div><div style="font-size:9px; color:#2271b1;">🤖 Bots</div></div>';
        echo '<div><div style="font-size:14px; font-weight:700; color:#646970;">' . number_format($unknown_count, 0, ',', '.') . '</div><div style="font-size:9px; color:#646970;">❓ Otros</div></div>';
        echo '</div>';
    } else {
        echo '<div style="color:#646970; font-size:10px;">Sin datos de visitantes aún</div>';
        echo '<div style="font-size:9px; color:#8c8f94; margin-top:5px;">Los datos aparecerán tras algunas visitas</div>';
    }
    
    echo '</div></div>';
    echo '</div>';

    // 4. CONFIGURACIÓN
    echo '<div class="ml-card" style="padding:15px; margin-bottom:15px;">';
    echo '<h2 style="font-size:15px; margin-bottom:15px;">Configuración Avanzada v' . MLP_VERSION . '</h2>';
    echo '<form method="post">' . wp_nonce_field('save_ml_action', '_wpnonce', true, false);
    
    echo '<h3 style="font-size:13px; margin-bottom:10px;">🧠 Sistema Inteligente Universal</h3>';
    echo '<div style="display:grid; gap:8px; margin-bottom:20px;">';
    echo '<label style="font-size:11px; display:flex; align-items:center; gap:8px;"><input type="checkbox" name="intelligent_cpu" ' . checked($opts['intelligent_cpu'], 1, false) . '/> <span style="color:#2271b1; font-weight:600;">CPU Inteligente:</span> <span style="color:#00a32a;">●</span> Activado (Algoritmo para shared/dedicated/VPS)</label>';
    echo '<label style="font-size:11px; display:flex; align-items:center; gap:8px;"><input type="checkbox" name="enhanced_size" ' . checked($opts['enhanced_size'], 1, false) . '/> <span style="color:#2271b1; font-weight:600;">Tamaño Mejorado:</span> <span style="color:#00a32a;">●</span> Activado (9 métodos + estimación inteligente)</label>';
    echo '<label style="font-size:11px; display:flex; align-items:center; gap:8px;"><input type="checkbox" name="universal_fallbacks" ' . checked($opts['universal_fallbacks']??1, 1, false) . '/> <span style="color:#2271b1; font-weight:600;">Fallbacks Universales:</span> <span style="color:#00a32a;">●</span> Activado (Garantiza datos en hostings restrictivos)</label>';
    echo '</div>';

    echo '<h3 style="font-size:13px; margin-bottom:10px;">📊 Métricas Principales (SIEMPRE ACTIVADAS)</h3>';
    echo '<div style="padding:10px; background:#f0f7ff; border-radius:4px; display:flex; gap:15px; flex-wrap:wrap; margin-bottom:20px;">';
    foreach (['SQL Queries', 'CPU Load', 'Tamaño de Página', 'HTTP Status'] as $m) {
        echo '<label style="font-size:11px; display:flex; align-items:center; gap:5px;"><input type="checkbox" checked disabled /> ' . $m . '</label>';
    }
    echo '</div>';

    echo '<h3 style="font-size:13px; margin-bottom:10px;">⚡ Muestreo Inteligente</h3>';
    echo '<div style="background:#f8f9fa; padding:12px; border-radius:4px; margin-bottom:20px;">';
    echo '<div style="display:flex; justify-content:space-between; margin-bottom:10px;"><div><strong style="font-size:11px;">Muestreo Normal</strong><div style="font-size:10px; color:#646970;">Ratio: ' . $opts['sampling_rate'] . ' (1 de cada 10 peticiones)</div></div><span style="background:#00a32a; color:white; padding:2px 8px; border-radius:10px; font-size:9px; font-weight:700;">FIJO</span></div>';
    echo '<div style="display:flex; justify-content:space-between; border-top:1px solid #eee; padding-top:10px;"><div><strong style="font-size:11px;">Muestreo CRON</strong><div style="font-size:10px; color:#646970;">Ratio: ' . ($opts['cron_sampling']??10) . ' (1 de cada 10 tareas)</div></div><span style="background:#00a32a; color:white; padding:2px 8px; border-radius:10px; font-size:9px; font-weight:700;">FIJO</span></div>';
    echo '</div>';

    echo '<h3 style="font-size:13px; margin-bottom:10px;">🚦 Umbrales de Alerta (Semáforo)</h3>';
    echo '<div style="font-size:11px; display:grid; gap:12px; margin-bottom:20px;">';
    echo '<div>Memoria Crítica (> 200MB): <input type="number" name="threshold_mb" value="' . esc_attr($opts['threshold_mb']) . '" style="width:60px;"> MB</div>';
    echo '<div>Tiempo de Ejecución (s): Aviso <input type="number" name="time_warn" value="' . esc_attr($opts['time_warn']) . '" style="width:50px;"> | Riesgo <input type="number" name="time_risk" value="' . esc_attr($opts['time_risk']) . '" style="width:50px;"></div>';
    echo '<div>Ponderación de CPU Efectiva: <input type="number" name="cpu_weight" value="' . ($opts['cpu_weight']??6) . '" style="width:50px;"> (Núcleos efectivos: ' . $cpu_effective . ')</div>';
    echo '<label><input type="checkbox" name="custom_vals" ' . checked($opts['custom_vals']??0, 1, false) . '/> Usar valores personalizados</label>';
    echo '</div>';

    echo '<div style="padding-top:15px; border-top:1px solid #eee;">';
    echo '<button type="submit" name="save_ml" class="button button-primary" style="background:#2271b1;">💾 Guardar configuración</button>';
    echo '<button type="submit" name="reset_defaults" class="button button-secondary" style="margin-left:10px;">🔄 Restaurar valores por defecto</button>';
    echo '</div></form></div>';

    // 5. AYUDA Y GUÍA COMPLETA (v12.9.0)
    echo '<div style="margin:15px 0 5px 0;"><a href="#" class="ml-help-toggle button button-small" data-target="help-section-dashboard" data-toggle="help-section-dashboard" style="font-weight:600;">ℹ️ Ayuda y Guía Completa</a></div>';
    
    // Insight automático
    if ($insight) {
        echo '<div style="margin-bottom:15px; padding:10px; background:' . $insight_bg . '; border-left:4px solid ' . $insight_border . '; border-radius:4px;">';
        echo '<div style="font-size:11px; font-weight:600; margin-bottom:3px;">💡 Insight Automático</div>';
        echo '<div style="font-size:11px; color:#1d2327;">' . esc_html($insight) . '</div>';
        echo '</div>';
    }
    
    echo '<div class="ml-help-content" id="help-section-dashboard" style="display:none; margin-bottom:20px; background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:20px;">';
    
    echo '<div style="margin-bottom:20px; padding:12px; background:#f0f7ff; border-radius:4px; border-left:4px solid #0073aa;">';
    echo '<h3 style="margin:0 0 8px 0; color:#0073aa;">📋 Guía de Uso: Las 3 Pestañas</h3>';
    echo '<p style="margin:0; font-size:12px; color:#646970;">Memory Logger Pro tiene 3 pestañas principales para diferentes necesidades de análisis.</p>';
    echo '</div>';

    // DASHBOARD
    echo '<div style="margin-bottom:25px; border-left:4px solid #2271b1; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#2271b1;">📊 Dashboard (Pestaña 1)</h3>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Resumen en tiempo real</strong> del estado actual del sistema.</p>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;">🧠 <strong>Métricas actuales:</strong> Memoria, Tiempo, CPU, SQL, Tamaño de página en la última petición.</li>';
    echo '<li style="margin-bottom:5px;">📈 <strong>Tendencias:</strong> Comparación con promedios históricos (↑ mejor/peor).</li>';
    echo '<li style="margin-bottom:5px;">💾 <strong>Base de Datos:</strong> Health score, autoload, transients, latencia, fragmentación.</li>';
    echo '<li style="margin-bottom:5px;">👥 <strong>Visitantes:</strong> Distribución bots vs humanos.</li>';
    echo '<li style="margin-bottom:5px;">⚙️ <strong>Configuración:</strong> Ajustar umbrales de alerta y opciones avanzadas.</li>';
    echo '<li style="margin-bottom:5px;">🗑️ <strong>Historial:</strong> Tabla con los últimos registros filtrable por tipo.</li>';
    echo '</ul>';
    echo '</div>';

    // ESTADÍSTICAS
    echo '<div style="margin-bottom:25px; border-left:4px solid #00a32a; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#00a32a;">📈 Estadísticas (Pestaña 2)</h3>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Análisis histórico</strong> para entender tendencias y problemas recurrentes.</p>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;">📊 <strong>Métricas principales:</strong> Promedios y máximos de memoria, tiempo, CPU, tamaño.</li>';
    echo '<li style="margin-bottom:5px;">🚦 <strong>Sistema de salud:</strong> Semáforo integral con umbrales configurables.</li>';
    echo '<li style="margin-bottom:5px;">📏 <strong>Métodos de medición:</strong> Distribución de cómo se mide el tamaño.</li>';
    echo '<li style="margin-bottom:5px;">📊 <strong>Distribución de memoria:</strong> Gráfico de barras: &lt;50MB, 50-100MB, 100-200MB, 200+MB.</li>';
    echo '<li style="margin-bottom:5px;">📈 <strong>Gráficos interactivos:</strong> Memoria vs SQL, Tiempo vs CPU, Tamaño vs CPU (Chart.js).</li>';
    echo '<li style="margin-bottom:5px;">🚨 <strong>Picos de riesgo:</strong> Lista de peticiones que superaron umbrales críticos.</li>';
    echo '<li style="margin-bottom:5px;">🔗 <strong>Top URLs:</strong> Ranking de páginas con peor rendimiento.</li>';
    echo '<li style="margin-bottom:5px;">👥 <strong>Visitantes:</strong> Gráfico de distribución: humanos, bots, desconocidos.</li>';
    echo '</ul>';
    echo '</div>';

    // DIAGNÓSTICO
    echo '<div style="margin-bottom:25px; border-left:4px solid #d63638; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#d63638;">🔍 Diagnóstico y Seguridad (Pestaña 3)</h3>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Auditoría profunda</strong> bajo demanda para detectar problemas específicos.</p>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;">🚀 <strong>Auditoría Global:</strong> Ejecuta todos los escaneos y genera un score global (0-100).</li>';
    echo '<li style="margin-bottom:5px;">🔌 <strong>Análisis de Plugins:</strong> Actualizaciones pendientes, incompatibilidades, plugins abandonados.</li>';
    echo '<li style="margin-bottom:5px;">🔍 <strong>Patrones de Error:</strong> Lee debug.log y error_log, agrupa por plugin/tema, timeline de 14 días.</li>';
    echo '<li style="margin-bottom:5px;">📁 <strong>Integridad Core:</strong> Verifica permisos de wp-content, uploads y archivos críticos.</li>';
    echo '<li style="margin-bottom:5px;">🗄️ <strong>Base de Datos:</strong> Tamaño real, overhead, autoload, transitorios caducados.</li>';
    echo '<li style="margin-bottom:5px;">🏠 <strong>Recomendaciones de Hosting:</strong> PHP, memory_limit, OPCache, extensiones, SSL, HSTS.</li>';
    echo '<li style="margin-bottom:5px;">📄 <strong>Exportar Reporte:</strong> Descarga en JSON, HTML o TXT para soporte técnico.</li>';
    echo '<li style="margin-bottom:5px;">🧹 <strong>Limpiar Logs:</strong> Borra registros y caché del plugin.</li>';
    echo '</ul>';
    echo '</div>';

    // SECCIÓN: Iconos de Alerta
    echo '<div style="margin-bottom:25px; border-left:4px solid #646970; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#646970;">⚠️ Iconos de Alerta (Leyenda)</h3>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;">✅ <strong>Verde:</strong> Todo correcto. Rendimiento óptimo.</li>';
    echo '<li style="margin-bottom:5px;">- <strong>Guion:</strong> Sin alertas significativas.</li>';
    echo '<li style="margin-bottom:5px;">💀 <strong>Calavera:</strong> Error Fatal (HTTP 500) - Página en blanco.</li>';
    echo '<li style="margin-bottom:5px;">🔥 <strong>Fuego:</strong> Memoria crítica (&gt;200 MB).</li>';
    echo '<li style="margin-bottom:5px;">🐢 <strong>Tortuga:</strong> Lentitud extrema (&gt;5s).</li>';
    echo '<li style="margin-bottom:5px;">💾 <strong>Diskette:</strong> Sobrecarga SQL (&gt;200 queries).</li>';
    echo '<li style="margin-bottom:5px;">⚡ <strong>Rayo:</strong> CPU saturada (&gt;80%).</li>';
    echo '</ul>';
    echo '</div>';

    // CPU INTELIGENTE
    echo '<div style="margin-bottom:25px; border-left:4px solid #00a32a; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#00a32a;">⚙️ CPU Inteligente</h3>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;">✅ <strong>Compatibilidad total:</strong> Windows, Linux, macOS.</li>';
    echo '<li style="margin-bottom:5px;">✅ <strong>Detección de hosting:</strong> ☁️ Cloud, 🖥️ VPS, 🏢 Dedicado, ⭐ Compartido Premium, 🌐 Compartido. Detecta automáticamente el tipo y proveedor.</li>';
    echo '<li style="margin-bottom:5px;">✅ <strong>CPU Ratio:</strong> Porcentaje de CPU que tu sitio puede usar según el tipo de hosting (Cloud 80%, VPS 60%, Dedicado 90%, Compartido 15%).</li>';
    echo '<li style="margin-bottom:5px;">✅ <strong>Calibración dinámica:</strong> Factor de ajuste según carga del servidor y memoria disponible.</li>';
    echo '<li style="margin-bottom:5px;">✅ <strong>Detección de throttling:</strong> Identifica si el servidor está limitando recursos por alta carga.</li>';
    echo '</ul>';
    echo '</div>';

    // VISITANTES
    echo '<div style="margin-bottom:25px; border-left:4px solid #2271b1; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#2271b1;">👥 Sistema de Visitantes</h3>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;">🤖 <strong>Bots de búsqueda:</strong> GoogleBot, BingBot, YandexBot, BaiduSpider, DuckDuckBot.</li>';
    echo '<li style="margin-bottom:5px;">🔍 <strong>Bots de SEO:</strong> AhrefsBot, SEMrush, Majestic, Screaming Frog.</li>';
    echo '<li style="margin-bottom:5px;">📊 <strong>Herramientas:</strong> Pingdom, GTmetrix, PageSpeed, UptimeRobot.</li>';
    echo '<li style="margin-bottom:5px;">🖥️ <strong>Scripts:</strong> cURL, Wget, Python, Java.</li>';
    echo '<li style="margin-bottom:5px;">👤 <strong>Humanos:</strong> Navegadores reales (Chrome, Firefox, Safari, Edge).</li>';
    echo '</ul>';
    echo '</div>';

    // QUICK ACTIONS
    echo '<div style="border-left:4px solid #f0b849; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#f0b849;">⚡ Quick Actions</h3>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;">💾 <strong>Optimizar BD:</strong> Limpia transients + optimiza tablas + refresca caché. ⚠️ Hacer backup primero.</li>';
    echo '<li style="margin-bottom:5px;">🔄 <strong>Refrescar:</strong> Actualiza métricas y salud del sistema sin modificar datos.</li>';
    echo '<li style="margin-bottom:5px;">🧪 <strong>Probar Métricas:</strong> Fuerza una medición inmediata para verificar el logger.</li>';
    echo '<li style="margin-bottom:5px;">🗑️ <strong>Vaciar Registro:</strong> Borra todos los logs acumulados.</li>';
    echo '<li style="margin-bottom:5px;">📤 <strong>Exportar CSV:</strong> Descarga todos los datos en formato CSV.</li>';
    echo '</ul>';
    echo '</div>';
    
    // Sección 0: Opciones Avanzadas
    echo '<div style="margin-bottom:25px; border-left:4px solid #2271b1; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#2271b1;">0. Opciones Avanzadas</h3>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>● Modo Debug:</strong> Registra TODAS las peticiones, ignorando el umbral. Útil para análisis temporal (1-2 días). Genera muchos logs.</p>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>● Ocultar Gráficos:</strong> No carga Chart.js (CDN externo). Para entornos offline o máxima privacidad.</p>';
    echo '</div>';

    // Sección 1: Iconos de Alerta
    echo '<div style="margin-bottom:25px; border-left:4px solid #2271b1; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#2271b1;">1. Iconos de Alerta (Leyenda)</h3>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;">- (Guion): Todo correcto. Sin anomalías graves.</li>';
    echo '<li style="margin-bottom:5px;">💀 <strong>Calavera (HTTP 500):</strong> Error Fatal. La página cargó en blanco (WSOD).</li>';
    echo '<li style="margin-bottom:5px;">🔥 <strong>Fuego (>200 MB):</strong> Memoria crítica. Peligro de agotar RAM.</li>';
    echo '<li style="margin-bottom:5px;">🐢 <strong>Tortuga (>5 seg):</strong> Lentitud extrema. La página tarda una eternidad.</li>';
    echo '<li style="margin-bottom:5px;">💾 <strong>Diskette (>200 SQL):</strong> Sobrecarga de base de datos. Necesitas caché de objetos.</li>';
    echo '<li style="margin-bottom:5px;">⚡ <strong>Rayo (>80% CPU):</strong> La carga supera los núcleos del servidor.</li>';
    echo '</ul>';
    echo '</div>';

    // Sección 2: Semáforo del Tiempo
    echo '<div style="margin-bottom:25px; border-left:4px solid #f0b849; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#d63638;">2. El Semaforo del Tiempo (Tiempo de Ejecución)</h3>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Referencia:</strong> Una página en caché debe tardar < 0.5s. Sin caché, < 1s.</p>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;"><span style="color:#00a32a;">●</span> <strong>Verde (< 0.5s):</strong> Carga rápida.</li>';
    echo '<li style="margin-bottom:5px;"><span style="color:#f0b849;">●</span> <strong>Naranja (Aviso):</strong> Atención. La web va lenta.</li>';
    echo '<li style="margin-bottom:5px;"><span style="color:#d63638;">●</span> <strong>Rojo (Riesgo > 2.0s):</strong> Peligro. Experiencia de usuario degradada.</li>';
    echo '</ul>';
    echo '</div>';

    // Sección 3: Métrica de CPU
    echo '<div style="margin-bottom:25px; border-left:4px solid #00a32a; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#00a32a;">3. Metrica de CPU (MEJORADA)</h3>';
    echo '<p style="margin:5px 0; font-size:12px;">✅ <strong>COMPATIBILIDAD COMPLETA:</strong> Funciona en Windows, Linux y macOS.</p>';
    echo '<p style="margin:5px 0; font-size:12px;">✅ <strong>MÚLTIPLES MÉTODOS:</strong> Usa getrusage, /proc/stat y microtime como fallback.</p>';
    echo '<p style="margin:5px 0; font-size:12px;">✅ <strong>AJUSTE AUTOMÁTICO:</strong> Considera múltiples núcleos de CPU y tipo de hosting.</p>';
    echo '</div>';

    // Sección 4: Semáforo de Memoria
    echo '<div style="margin-bottom:25px; border-left:4px solid #d63638; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#d63638;">4. El Semaforo de la Memoria (RAM)</h3>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Referencia:</strong> Un WordPress moderno consume entre 60MB y 128MB.</p>';
    echo '<ul style="margin:0; font-size:12px; list-style:none; padding:0;">';
    echo '<li style="margin-bottom:5px;"><span style="color:#00a32a;">●</span> <strong>Verde (< 128 MB):</strong> Consumo saludable.</li>';
    echo '<li style="margin-bottom:5px;"><span style="color:#f0b849;">●</span> <strong>Naranja (128 - 200 MB):</strong> Consumo alto.</li>';
    echo '<li style="margin-bottom:5px;"><span style="color:#d63638;">●</span> <strong>Rojo (> 200 MB):</strong> Muy alto. Peligro de error 500.</li>';
    echo '<li style="margin-bottom:5px;">🚨 <strong>Crítico (> 300 MB):</strong> Emergencia. Revisar inmediatamente.</li>';
    echo '</ul>';
    echo '</div>';

    // Sección 5: Tamaño de Página
    echo '<div style="margin-bottom:25px; border-left:4px solid #646970; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#646970;">5. Tamano de Pagina (MEJORADO)</h3>';
    echo '<p style="margin:5px 0; font-size:12px;">✅ <strong>BUFFER MEJORADO:</strong> Captura correctamente el contenido de salida.</p>';
    echo '<p style="margin:5px 0; font-size:12px;">✅ <strong>HEADERS INCLUIDOS:</strong> Mide tamaño total (headers + contenido).</p>';
    echo '<p style="margin:5px 0; font-size:12px;">📉 <strong>OPTIMIZACIÓN:</strong> Un tamaño > 2MB merece atención inmediata.</p>';
    echo '</div>';

    // Sección 6: Panel de Salud
    echo '<div style="border-left:4px solid #2271b1; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#2271b1;">6. Panel de Salud y Base de Datos</h3>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Autoload Options:</strong> Datos que se cargan SIEMPRE. < 800 KB es sano. > 1000 KB es basura acumulada.</p>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Transients Exp:</strong> Datos caducados. Si hay muchos, usa la herramienta de Diagnóstico para limpiar.</p>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Latencia DB:</strong> > 20ms indica base de datos lenta o servidor saturado.</p>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>OPCache:</strong> Acelerador PHP. Debe estar activo siempre en producción.</p>';
    echo '</div>';

    // Sección 7: Quick Actions e Insight (NUEVO v12.9.0)
    echo '<div style="border-left:4px solid #00a32a; padding-left:15px;">';
    echo '<h3 style="margin:0 0 10px 0; color:#00a32a;">7. Quick Actions e Insight Automático (v' . MLP_VERSION . ')</h3>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>⚡ Optimizar BD:</strong> Limpia transients caducados Y optimiza todas las tablas en una sola operación. Recomendado hacer copia de seguridad primero.</p>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>🔄 Refrescar:</strong> Actualiza las métricas sin modificar la base de datos. Útil para ver datos frescos.</p>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>💡 Insight Automático:</strong> Muestra recomendaciones solo cuando hay problemas. Si todo va bien, no aparece.</p>';
    echo '<p style="margin:5px 0; font-size:12px;"><strong>Health Score:</strong> El score de salud de BD es aproximado y depende de múltiples factores: fragmentación, autoload, overhead y tablas grandes.</p>';
    echo '</div>';
    
    echo '</div>';

    // 6. BOTONES ACCIÓN (MEJORADO v12.9.0 - Unificado)
    echo '<div class="ml-card" style="padding:12px; margin-bottom:15px; display:flex; gap:8px; flex-wrap:wrap; background:#f9f9f9;">';
    echo '<form method="post">' . wp_nonce_field('clear_log_action', '_wpnonce', true, false) . '<button type="submit" name="clear_log" class="button button-secondary" onclick="return confirm(\'¿Vaciar registros?\')">🗑️ Vaciar Registro</button></form>';
    echo '<form method="post" action="' . admin_url('admin-post.php') . '">' . wp_nonce_field('memory_logger_export_csv', 'export_csv_nonce', true, false) . '<input type="hidden" name="action" value="mlp_export_csv"><button type="submit" class="button">📤 Exportar CSV</button></form>';
    echo '<button type="button" id="mlp-optimize-db-complete" class="button" style="background:#2271b1; color:white;">⚡ Optimizar BD</button>';
    echo '<button type="button" id="mlp-refresh-health" class="button button-secondary">🔄 Refrescar</button>';
    echo '<button id="ml-test-now" class="button button-primary" style="background:#3b5998;">⚡ Probar Métricas</button>';
    echo '</div>';
    
    // Warning de backup (discreto)
    echo '<div style="font-size:10px; color:#d63638; margin-bottom:15px; padding:6px 10px; background:#fff5f5; border-radius:4px; display:inline-block;">⚠️ Haz copia de seguridad antes de optimizar la BD.</div>';

    // 7. TABLA
    if (file_exists(MLP_LOG_FILE)) {
        $lines = file(MLP_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total = count($lines); $lines = array_slice($lines, -200);
        echo '<div class="ml-card" style="padding:15px;">';
        echo '<h3 style="font-size:13px; margin:0 0 12px 0;">Historial de Registros <small>(Mostrando los últimos ' . count($lines) . ' registros)</small></h3>';
        echo '<div style="display:flex; gap:10px; margin-bottom:15px;"><input type="text" id="ml-search" placeholder="Buscar..." style="font-size:11px; height:28px; width:200px;"><select id="ml-filter" style="font-size:11px; height:28px;"><option value="">Todo</option><option value="Frontend">Frontend</option><option value="Backend">Backend</option><option value="Cron">Cron</option></select></div>';
        echo '<div style="overflow-x:auto;"><table class="wp-list-table widefat fixed striped" id="ml-table" style="font-size:10px;">';
    echo '<thead><tr>';
    echo '<th style="width:45px;">Alerta</th>';
    echo '<th style="width:85px;">Fecha</th>';
    echo '<th style="width:60px;">Tipo</th>';
    echo '<th style="width:60px;">Quién</th>';
    echo '<th style="width:70px;">Estado</th>';
    echo '<th>URL</th>';
    echo '<th style="width:90px; text-align:right;">Mem (MB)</th>';
    echo '<th style="width:90px; text-align:right;">Tiempo (S)</th>';
    echo '<th style="width:60px; text-align:right;">SQL</th>';
    echo '<th style="width:70px; text-align:right;">Size (KB)</th>';
    echo '<th style="width:70px; text-align:right;">CPU (%)</th>';
    echo '<th style="width:90px; text-align:center;">Método</th>';
    echo '</tr></thead><tbody>';
        foreach (array_reverse($lines) as $l) {
            if (!str_contains($l, 'DATE:')) continue;
            $row = [];
            foreach (explode(' | ', $l) as $x) {
                $kv = explode(':', $x, 2);
                if (count($kv) == 2) $row[trim($kv[0])] = trim($kv[1]);
            }
            $ua_raw = $row['UA'] ?? '';
            $ua = mlp_identify_user_agent($ua_raw);
            $row_for_render = [
                'alert' => (($row['HTTP'] ?? 200) >= 500) ? '💀' : (($row['MEM'] ?? 0) > $opts['threshold_mb'] ? '🔥' : (($row['TIME'] ?? 0) > $opts['time_risk'] ? '🐢' : '✅')),
                'date' => substr($row['DATE'] ?? '', 5, 11),
                'type' => $row['TYPE'] ?? '-',
                'who' => $ua['icon'],
                'status' => (int)($row['HTTP'] ?? 200),
                'url' => $row['URL'] ?? '',
                'mem' => (float)($row['MEM'] ?? 0),
                'time' => (float)($row['TIME'] ?? 0),
                'sql' => (int)($row['SQL'] ?? 0),
                'size' => (float)($row['SIZE'] ?? 0),
                'cpu' => (float)($row['CPU'] ?? 0),
                'method' => $row['SIZE_METHOD'] ?? '',
            ];
            echo mlp_render_history_row($row_for_render, $ua_raw);
        }
        echo '</tbody></table></div></div>';
    }
}
