<?php
/**
 * Memory Logger Pro v13.3.0 - Vista Diagnóstico y Seguridad (Pestaña 3)
 * Versión OPTIMIZADA con correcciones de bugs y mejor rendimiento
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderizar la vista de Diagnóstico y Seguridad - VERSIÓN OPTIMIZADA v13.3.0
 */
function mlp_render_diagnostic_view(array $opts, array $hosting_info, array $calibration, array $health): void {
    // Detectar navegador para UX adaptativa
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $is_old_browser = (strpos($user_agent, 'MSIE') !== false || 
                      strpos($user_agent, 'Trident') !== false);
    
    // Cargar datos de seguridad desde caché si están disponibles
    $security_data = get_transient('mlp_quick_security_scan_opt_' . md5(MLP_PATH));
    $security_score = is_array($security_data) && isset($security_data['security_score']) 
        ? (int) $security_data['security_score'] 
        : 0;
    
    // Determinar formato de exportación
    $preferred_format = isset($_COOKIE['mlp_export_format']) ? 
                        sanitize_text_field($_COOKIE['mlp_export_format']) : 'json';

    // Crear nonces específicos para esta página
    if (!function_exists('wp_create_nonce')) {
        require_once ABSPATH . 'wp-includes/pluggable.php';
    }
    
    $tools_nonce = wp_create_nonce('mlp_lazy_load_diagnostic_nonce');
    $security_nonce = wp_create_nonce('mlp_lazy_load_security_nonce');
    $clear_nonce = wp_create_nonce('mlp_clear_all_logs_cache_nonce');

    // 1. CABECERA COMPACTA CON BOTÓN MAESTRO
    echo '<div class="ml-card" id="diagnostic-tab" style="padding:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h2 style="font-size:14px; margin:0; color:#1d2327;">🔍 Diagnóstico del Sistema v' . MLP_VERSION . '</h2>
            <button type="button" id="mlp-master-scan-btn" class="button button-primary button-small" style="font-size:11px; font-weight:600; background:#2271b1;">🚀 Ejecutar Auditoría Global</button>
        </div>';
    
    // Contenedor para el resultado del Master Scan (Score Global)
    echo '<div id="mlp-master-scan-results" style="display:none; margin-bottom:15px;"></div>';
    
    // Indicador de modo compatible
    if ($is_old_browser) {
        echo '<div style="margin-bottom:12px; padding:8px; background:#fff8e1; border-radius:4px; border-left:3px solid #f0b849; font-size:11px;">
            <span style="color:#dba617;">⚠️ Modo compatible activado para navegador antiguo.</span>
        </div>';
    }
        
    echo '<div style="margin-bottom:12px; padding:8px; background:#f0f7ff; border-radius:4px; border-left:3px solid #0073aa; font-size:11px;">
            <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">';

    $hosting_icon = isset($hosting_info['type']) ? match($hosting_info['type']) {
        'cloud' => '☁️', 'vps' => '🖥️', 'dedicated' => '🏢', 'shared_premium' => '⭐', 'shared' => '🌐', default => '🌍'
    } : '🌍';
    $hosting_type = isset($hosting_info['type']) ? match($hosting_info['type']) {
        'cloud' => 'Cloud', 'vps' => 'VPS', 'dedicated' => 'Dedicado', 'shared_premium' => 'Compartido Premium', 'shared' => 'Compartido', default => 'Unknown'
    } : 'Unknown';
    $provider_text = !empty($hosting_info['provider']) && $hosting_info['provider'] !== 'Unknown' 
        ? "$hosting_type (" . $hosting_info['provider'] . ")" 
        : $hosting_type;
    
    $summary_items = [
        ['icon' => $hosting_icon, 'text' => esc_html($provider_text)],
        ['icon' => '⚡', 'text' => isset($hosting_info['cpu_share_ratio']) ? esc_html(number_format($hosting_info['cpu_share_ratio'] * 100, 1)) . '% CPU' : 'N/A'],
        ['icon' => '🎯', 'text' => isset($calibration['factor']) ? esc_html(number_format($calibration['factor'], 2)) . 'x cal' : 'N/A'],
    ];
    
    foreach ($summary_items as $item) {
        echo '<div style="display:flex; align-items:center; gap:4px;">
                <span style="background:#0073aa; color:white; padding:1px 5px; border-radius:3px; font-weight:600;">' . $item['icon'] . '</span>
                <span>' . $item['text'] . '</span>
              </div>';
    }
    
    echo '</div></div>';

    // 📊 ESTADO RÁPIDO DEL SISTEMA
    echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:8px;">';

    $mem_percent = isset($health['mem_percent']) ? (float) $health['mem_percent'] : 0;
    $db_lat = isset($health['db_lat']) ? (float) $health['db_lat'] : 0;
    $load_avg = isset($health['load_avg']) ? (float) $health['load_avg'] : 0;
    $memory_used = isset($health['memory_used']) ? (float) $health['memory_used'] : 0;
    
    $critical_reasons = [];
    if ($mem_percent > 80) $critical_reasons[] = 'RAM > 80%';
    if ($db_lat > 20) $critical_reasons[] = 'DB Lenta';
    if ($load_avg > 10) $critical_reasons[] = 'CPU Saturada';
    
    $criticalCount = count($critical_reasons);
    $criticalColor = $criticalCount > 0 ? '#d63638' : '#00a32a';
    $criticalText = $criticalCount > 0 ? implode(', ', $critical_reasons) : 'Sistema estable';

    $metrics = [
        ['label' => '🚨 Críticos', 'value' => $criticalCount, 'color' => $criticalColor, 'subtext' => $criticalText, 'id' => 'jump-to-tools'],
        ['label' => '🧠 Memoria', 'value' => number_format($mem_percent, 1) . '%', 'color' => $mem_percent > 80 ? '#d63638' : ($mem_percent > 60 ? '#f0b849' : '#00a32a'), 'subtext' => number_format($memory_used, 1) . ' MB'],
        ['label' => '🗄️ DB Latencia', 'value' => number_format($db_lat, 1) . 'ms', 'color' => $db_lat > 20 ? '#d63638' : ($db_lat > 5 ? '#f0b849' : '#00a32a'), 'subtext' => ''],
        ['label' => '⚡ CPU Load', 'value' => number_format($load_avg, 1), 'color' => $load_avg > 10 ? '#d63638' : ($load_avg > 5 ? '#f0b849' : '#00a32a'), 'subtext' => ''],
        ['label' => '🛡️ Seguridad', 'value' => $security_score . '/100', 'color' => $security_score < 60 ? '#d63638' : ($security_score < 80 ? '#f0b849' : '#00a32a'), 'subtext' => 'site score']
    ];

    foreach ($metrics as $metric) {
        $cursor = isset($metric['id']) ? 'cursor:pointer;' : '';
        $m_id = isset($metric['id']) ? ' id="' . esc_attr($metric['id']) . '"' : '';
        echo '<div' . $m_id . ' style="padding:8px; background:#f8f9fa; border-radius:4px; border-left:3px solid ' . esc_attr($metric['color']) . ';' . $cursor . '">
                <div style="font-size:10px; font-weight:600; color:#646970; text-transform:uppercase;">' . $metric['label'] . '</div>
                <div style="font-size:16px; font-weight:700; color:' . esc_attr($metric['color']) . ';">' . $metric['value'] . '</div>';
        if (!empty($metric['subtext'])) {
            echo '<div style="font-size:9px; color:#8c8f94;">' . $metric['subtext'] . '</div>';
        }
        echo '</div>';
    }

    echo '</div>';

    // 👥 VISITANTES (NUEVO v12.8.0)
     $raw_visitor_stats = function_exists('mlp_get_advanced_stats') ? (mlp_get_advanced_stats()['visitor_stats'] ?? []) : [];
     $visitor_data = !empty($raw_visitor_stats) ? mlp_process_visitor_stats($raw_visitor_stats) : [
         'human_count' => 0, 'bot_count' => 0, 'unknown_count' => 0, 'total' => 0,
         'human_pct' => 0, 'bot_pct' => 0, 'unknown_pct' => 0, 'has_data' => false
     ];
     
     $human_count = $visitor_data['human_count'];
     $bot_count = $visitor_data['bot_count'];
     $unknown_count = $visitor_data['unknown_count'];
     $total_visitors = $visitor_data['total'];
    
    echo '<div style="margin-top:12px; padding:10px; background:#f8f9fa; border-radius:4px; border-left:3px solid #2271b1;">';
    echo '<div style="font-size:10px; font-weight:600; color:#646970; margin-bottom:8px;">👥 VISITANTES (Bots vs Humanos)</div>';
    
    if ($total_visitors > 0) {
        echo '<div style="display:flex; gap:3px; height:12px; margin-bottom:8px;">';
        $human_pct = round(($human_count / $total_visitors) * 100, 1);
        $bot_pct = round(($bot_count / $total_visitors) * 100, 1);
        $unknown_pct = round(($unknown_count / $total_visitors) * 100, 1);
        echo '<div style="flex:' . max(1, $human_pct / 10) . '; background:#00a32a; border-radius:2px 0 0 2px;" title="Humanos: ' . $human_pct . '%"></div>';
        echo '<div style="flex:' . max(1, $bot_pct / 10) . '; background:#2271b1;" title="Bots: ' . $bot_pct . '%"></div>';
        echo '<div style="flex:' . max(1, $unknown_pct / 10) . '; background:#c3c4c7; border-radius:0 2px 2px 0;" title="Otros: ' . $unknown_pct . '%"></div>';
        echo '</div>';
        
        echo '<div style="display:flex; justify-content:space-between; font-size:10px;">';
        echo '<span style="color:#00a32a;">👤 ' . number_format($human_count, 0, ',', '.') . ' (' . $human_pct . '%)</span>';
        echo '<span style="color:#2271b1;">🤖 ' . number_format($bot_count, 0, ',', '.') . ' (' . $bot_pct . '%)</span>';
        echo '<span style="color:#646970;">❓ ' . number_format($unknown_count, 0, ',', '.') . ' (' . $unknown_pct . '%)</span>';
        echo '</div>';
    } else {
        echo '<div style="font-size:10px; color:#646970;">Sin datos aún - aparecen tras primeras visitas</div>';
    }
    
    echo '</div>';

    echo '</div></div>';

    // 🔧 HERRAMIENTAS DE ANÁLISIS INDIVIDUALES
    echo '<div class="ml-card" style="padding:12px; margin-top:15px;">
        <h3 style="font-size:13px; margin:0 0 10px 0;">🔧 Herramientas de Análisis Detallado</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:8px;">';

    $tools = [
        'mlp-tool-analyze-plugins' => ['🔌', 'Análisis de Plugins', 'Vulnerabilidades, actualizaciones y salud de plugins'],
        'mlp-tool-error-patterns' => ['🔍', 'Patrones de Error', 'Diagnóstico inteligente de logs de PHP'],
        'mlp-tool-file-integrity' => ['📁', 'Integridad Core', 'Escaneo de archivos núcleo de WordPress'],
        'mlp-tool-database'       => ['🗄️', 'Base de Datos', 'Optimización de tablas y wp_options'],
        'mlp-tool-hosting'        => ['🏠', 'Rec. Servidor', 'Sugerencias para hosting y PHP']
    ];
    
    foreach ($tools as $id => $tool) {
        echo '<button type="button" id="' . esc_attr($id) . '" class="button mlp-tool-btn" 
                style="font-size:11px; padding:10px; text-align:left; display:flex; align-items:center; gap:10px;"
                data-action="' . esc_attr(str_replace('mlp-tool-', '', $id)) . '">
                <span style="font-size:18px;">' . $tool[0] . '</span>
                <div>
                    <div style="font-weight:600; font-size:12px;">' . esc_html($tool[1]) . '</div>
                    <div style="font-size:10px; color:#646970; line-height:1.2;">' . esc_html($tool[2]) . '</div>
                </div>
              </button>';
    }

    echo '</div>';

     // Área única para resultados de herramientas
    echo '<div id="quick-tools-results" style="margin-top:15px; display:none; margin-bottom:50px; position:relative; z-index:1;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#f0f7ff; border-radius:4px 4px 0 0; border-bottom:1px solid #dcdcde;">
                <strong id="quick-tools-title" style="font-size:11px; text-transform:uppercase; color:#0073aa;">Resultados</strong>
                <button type="button" id="mlp-close-results" style="background:none; border:none; cursor:pointer; font-size:18px; color:#646970;">&times;</button>
            </div>
            <div id="quick-tools-content" style="padding:15px; background:#fff; border:1px solid #dcdcde; border-top:none; border-radius:0 0 4px 4px; max-height:60vh; overflow-y:auto; overflow-x:hidden; padding-bottom:30px;"></div>
          </div>
    </div>';

    // 📄 ACCIONES FINALES (Compactas)
    echo '<div class="ml-card" style="padding:12px; margin-top:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h3 style="font-size:13px; margin:0 0 5px 0;">📄 Acciones y Mantenimiento</h3>
            <div style="display:flex; align-items:center; gap:10px;">';
    
    if (!$is_old_browser) {
        echo '<div style="display:flex; align-items:center; gap:5px;">
                <span style="font-size:10px; color:#646970;">Exportar:</span>
                <select id="export-format-select" style="font-size:11px; height:28px;">
                    <option value="json"' . ($preferred_format === 'json' ? ' selected' : '') . '>JSON</option>
                    <option value="html"' . ($preferred_format === 'html' ? ' selected' : '') . '>HTML</option>
                    <option value="txt"' . ($preferred_format === 'txt' ? ' selected' : '') . '>Texto</option>
                </select>
              </div>';
    }

    echo '<button type="button" id="export-diagnostic-report" class="button button-small">📄 Descargar Reporte</button>
          <button type="button" id="clear-all-logs" class="button button-small">🧹 Limpiar Logs & Caché</button>
          </div>
        </div>
        <div style="font-size:10px; color:#8c8f94; font-style:italic;">* La auditoría global refresca todos los indicadores superiores.</div>
    </div>';
}
