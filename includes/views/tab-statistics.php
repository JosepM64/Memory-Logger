<?php
/**
 * Memory Logger Pro v13.3.0 - Vista Estadísticas (Pestaña 2)
 * Restauración integral v13.3.0 con todas las secciones visuales y lógicas
 */

if (!defined('ABSPATH')) {
    exit;
}

function mlp_render_statistics_view(array $opts, array $hosting_info, array $calibration): void {
    $stats = function_exists('mlp_get_advanced_stats') ? mlp_get_advanced_stats() : null;
    
    if (!$stats) {
        echo '<div class="ml-card" style="text-align:center; padding:30px; opacity:0.5;"><h3>Esperando datos históricos...</h3></div>';
        return;
    }

    ?>
    <div class="mlp-stats-wrap">
        <h2 style="font-size:18px; margin-bottom:15px;">📈 Estadísticas Avanzadas de Rendimiento</h2>

        <!-- INFO HOSTING -->
        <div class="mlp-stats-header">
            <div style="display:flex; flex-wrap:wrap; gap:20px; align-items:center; font-size:12px;">
                <span><strong>Hosting:</strong> <?php echo esc_html(ucfirst(str_replace('_', ' ', $hosting_info['type']))); ?></span>
                <span><strong>Servidor:</strong> <?php echo esc_html($hosting_info['provider']); ?></span>
                <span><strong>CPU Ratio:</strong> <?php echo number_format($hosting_info['cpu_share_ratio'] * 100, 1); ?>%</span>
                <span><strong>Calibración:</strong> <?php echo number_format($calibration['factor'], 2); ?>x</span>
            </div>
        </div>

        <!-- 1. MÉTRICAS PRINCIPALES -->
        <h3 style="font-size:14px; margin-bottom:12px;">📊 Métricas Principales</h3>
        <div class="mlp-stats-grid">
            <?php
            $m_def = [
                ['l' => 'Memoria', 'v' => $stats['avg_memory'], 'm' => $stats['max_memory'], 'u' => 'MB', 'p' => 2, 't' => [100, 200], 'i' => '🧠'],
                ['l' => 'Tiempo', 'v' => $stats['avg_time'],   'm' => $stats['max_time'],   'u' => 's',  'p' => 3, 't' => [2, 5], 'i' => '⏱️'],
                ['l' => 'CPU', 'v' => $stats['avg_cpu'],    'm' => $stats['max_cpu'],    'u' => '%',  'p' => 2, 't' => [50, 80], 'i' => '⚡'],
                ['l' => 'Tamaño', 'v' => $stats['avg_size'],   'm' => $stats['max_size'],   'u' => 'KB', 'p' => 2, 't' => [300, 500], 'i' => '📏']
            ];
            foreach ($m_def as $m):
                $c = $m['v'] > $m['t'][1] ? '#d63638' : ($m['v'] > $m['t'][0] ? '#f0b849' : '#00a32a');
            ?>
                <div class="mlp-stat-card" style="border-top-color: <?php echo $c; ?>;">
                    <div class="label"><?php echo $m['i'] . ' ' . $m['l']; ?></div>
                    <div class="val" style="color: <?php echo $c; ?>;"><?php echo number_format($m['v'], $m['p']); ?> <?php echo $m['u']; ?></div>
                    <div class="sub">Máx: <?php echo number_format($m['m'], $m['p']); ?> <?php echo $m['u']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 2. SISTEMA DE SALUD INTEGRAL -->
        <h3 style="font-size:14px; margin-bottom:12px;">🚦 Sistema de Salud Integral</h3>
        <div class="mlp-health-grid">
            <?php
            $h_def = [
                ['i' => '📊', 'l' => 'SQL Queries', 'v' => $stats['avg_sql'], 'u' => '', 'p' => 1, 't' => [100, 150], 'txt' => ['🚨 PRIORIDAD 1 (>150)', '⚠️ ATENCIÓN (>100)', '✅ ÓPTIMO'], 's' => 'Promedio por petición'],
                ['i' => '🧠', 'l' => 'Memoria RAM', 'v' => $stats['avg_memory'], 'u' => 'MB', 'p' => 2, 't' => [100, 200], 'txt' => ['🚨 PRIORIDAD 2 (>200MB)', '⚠️ ATENCIÓN (>100MB)', '✅ ÓPTIMO'], 's' => 'Consumo promedio'],
                ['i' => '⏱️', 'l' => 'Tiempo de ejecución', 'v' => $stats['avg_time'], 'u' => 's', 'p' => 3, 't' => [1.0, 2.0], 'txt' => ['🚨 PRIORIDAD 3 (>2s)', '⚠️ ATENCIÓN (>1s)', '✅ ÓPTIMO'], 's' => 'Promedio por carga'],
                ['i' => '⚡', 'l' => 'CPU Inteligente', 'v' => $stats['avg_cpu'], 'u' => '%', 'p' => 2, 't' => [20, 40], 'txt' => ['🚨 PRIORIDAD 4 (>40%)', '⚠️ ATENCIÓN (>20%)', '✅ ÓPTIMO'], 's' => 'Uso ajustado']
            ];
            foreach ($h_def as $h):
                $is_crit = $h['v'] > $h['t'][1];
                $is_warn = $h['v'] > $h['t'][0];
                $c = $is_crit ? '#d63638' : ($is_warn ? '#f0b849' : '#00a32a');
                $status_txt = $is_crit ? $h['txt'][0] : ($is_warn ? $h['txt'][1] : $h['txt'][2]);
                $bg_c = $is_crit ? '#fff5f5' : ($is_warn ? '#fff8e1' : '#f0fbf0');
            ?>
                <div class="mlp-health-card" style="border-left-color: <?php echo $c; ?>;">
                    <div class="mlp-health-icon"><?php echo $h['i']; ?></div>
                    <div class="mlp-health-title"><?php echo $h['l']; ?></div>
                    <div class="mlp-health-val" style="color: <?php echo $c; ?>;"><?php echo number_format($h['v'], $h['p']); ?> <?php echo $h['u']; ?></div>
                    <div class="sub"><?php echo $h['s']; ?></div>
                    <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                        <span class="mlp-health-badge" style="background: <?php echo $bg_c; ?>; color: <?php echo $c; ?>;">
                            <?php echo $status_txt; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 3. RELACIÓN DE MÉTRICAS -->
        <div class="mlp-info-box">
            <strong>🔗 Cómo se relacionan estas métricas en WordPress (Efecto Dominó):</strong><br>
            1. <strong>SQL Ineficiente (>100 consultas):</strong> Base de datos hinchada, queries N+1, autoload options.<br>
            2. <strong>CPU Disparada (>30%):</strong> El servidor procesa miles de resultados.<br>
            3. <strong>Memoria Agotada (>200MB):</strong> PHP guarda datos en RAM → Fatal Error.<br>
            4. <strong>Tiempo Excesivo (>2s):</strong> Usuarios abandonan, Google penaliza.<br><br>
            <span style="background:#e8f5e8; padding:4px 8px; border-radius:4px;">🎯 <strong>Orden recomendado de optimización:</strong> <span style="color:#00a32a;">SQL → Memoria → Tiempo → CPU</span></span>
        </div>

        <!-- 4. MÉTODOS DE MEDICIÓN DE TAMAÑO -->
        <h3 style="font-size:14px; margin-bottom:12px;">📏 Cómo se mide el tamaño de página</h3>
        <p style="font-size:11px; color:#646970; margin-bottom:10px;">El plugin usa diferentes técnicas para medir el tamaño total de la página (HTML + recursos).</p>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin-bottom:15px;">
            <?php
            if (!empty($stats['size_methods'])) {
                foreach (array_slice($stats['size_methods'], 0, 5) as $m_n => $m_d) {
                    $p = (float)($m_d['percentage'] ?? 0);
                    $ct = (int)($m_d['count'] ?? 0);
                    $cl = match (true) {
                        str_contains($m_n, 'buffer') || str_contains($m_n, 'variable') || str_contains($m_n, 'ob_') => '#00a32a',
                        str_contains($m_n, 'estimation') => '#f0b849',
                        default => '#8c8f94'
                    };
                    // Descripción según método
                    $desc = match ($m_n) {
                        'output_buffer', 'ob_get_contents' => 'Método preciso: captura el contenido real enviado',
                        'content_length' => 'Método preciso: usa header del servidor',
                        'estimation' => 'Estimación basada en memoria PHP',
                        default => 'Otro método de medición'
                    };
                    echo '<div style="padding:10px; background:#f9f9f9; border-radius:4px; font-size:11px;">
                            <div style="font-weight:700; margin-bottom:3px;">📄 ' . esc_html($m_n) . '</div>
                            <div style="font-weight:700; color:' . $cl . ';">' . number_format($p, 1) . '% <span style="font-weight:normal; color:#646970;">(' . $ct . ' mediciones)</span></div>
                            <div style="font-size:10px; color:#8c8f94; margin-top:3px;">' . $desc . '</div>
                          </div>';
                }
            }
            ?>
        </div>
        <div style="font-size:11px; color:#646970; margin-bottom:25px; padding:10px; background:#f0fbf0; border-radius:4px;">
            💡 <strong>En resumen:</strong> Este gráfico muestra <strong>qué técnica</strong> usa el plugin para calcular el tamaño de tus páginas. 
            <span style="color:#00a32a;">Verde:</span> Método preciso. 
            <span style="color:#f0b849;">Amarillo:</span> Estimación (menos preciso).
            <br><em>Si todo está en verde, la medición es precisa.</em>
        </div>

        <!-- 5. DISTRIBUCIÓN DE MEMORIA -->
        <h3 style="font-size:14px; margin-bottom:12px;">📊 Distribución de Memoria</h3>
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px; margin-bottom:30px;">
            <div>
                <?php
                $ds = ['<50'=>['l'=>'< 50 MB','c'=>'#00a32a'],'50-100'=>['l'=>'50-100 MB','c'=>'#00a32a'],'100-200'=>['l'=>'100-200 MB','c'=>'#f0b849'],'200+'=>['l'=>'200+ MB','c'=>'#d63638']];
                foreach ($ds as $k => $v) {
                    $d = $stats['memory_distribution'][$k] ?? ['count'=>0,'percentage'=>0];
                    $p = min(100, max(0, $d['percentage']));
                ?>
                    <div class="mlp-dist-bar-container">
                        <div class="mlp-dist-label"><?php echo $v['l']; ?></div>
                        <div class="mlp-dist-bar-bg">
                            <div class="mlp-dist-bar-fill" style="width:<?php echo $p; ?>%; background:<?php echo $v['c']; ?>;"></div>
                            <div class="mlp-dist-bar-text"><?php echo $d['count']; ?> (<?php echo number_format($p, 1); ?>%)</div>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="mlp-info-box" style="margin:0;">
                <strong>🔎 ¿Qué significa esto?</strong><br>
                <span style="color:#00a32a;">● < 50 MB:</span> Muy bajo y eficiente.<br>
                <span style="color:#00a32a;">● 50-100 MB:</span> Consumo normal.<br>
                <span style="color:#f0b849;">● 100-200 MB:</span> Consumo alto. Revisa plugins.<br>
                <span style="color:#d63638;">● 200+ MB:</span> Consumo excesivo. Posible error.<br><br>
                <small>💡 <strong>Recomendación:</strong> Si > 10% están en rojo, optimiza tu sitio.</small>
            </div>
        </div>

        <!-- 6. GRÁFICOS -->
        <div style="background:#f8f9fa; padding:12px; border-radius:4px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; gap:15px; align-items:center;">
                <strong>Vista de Gráficos:</strong>
                <select id="mlp-chart-event-count" style="font-size:11px;">
                    <?php foreach([10,25,50,100,200,500] as $v) echo "<option value='$v' " . (100 == $v ? 'selected' : '') . ">Últimos $v peticiones</option>"; ?>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="mlp-chart-type-btn active" data-type="line">📈 Líneas</button>
                <button type="button" class="mlp-chart-type-btn" data-type="bar">📊 Barras</button>
            </div>
        </div>

        <div id="mlp-charts-stack">
            <?php
            $charts = [
                ['id'=>'mlChartMemorySql', 't'=>'Memoria (MB) vs SQL Queries', 'icon'=>'🧠'],
                ['id'=>'mlChartTimeCpu',   't'=>'Tiempo (s) vs CPU (%)', 'icon'=>'⏱️'],
                ['id'=>'mlChartSizeCpu',   't'=>'Tamaño (KB) vs CPU (%)', 'icon'=>'📏']
            ];
            foreach ($charts as $c): ?>
                <div class="mlp-chart-wrapper">
                    <h4 style="margin:0 0 15px 0; font-size:13px;"><?php echo $c['icon'] . ' ' . $c['t']; ?></h4>
                    <div class="mlp-chart-container">
                        <canvas id="<?php echo $c['id']; ?>"></canvas>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($stats['dangerous_peaks'])): ?>
        <!-- 7. PICOS PELIGROSOS -->
        <h3 style="font-size:14px; margin-top:30px; margin-bottom:12px;">🚨 Historial de Picos de Riesgo</h3>
        <div class="ml-card" style="padding:15px;">
            <div style="overflow-x:auto;">
                <table class="mlp-peak-table">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>RAM</th><th>Time</th><th>CPU</th><th>URL</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($stats['dangerous_peaks'], 0, 15) as $pk): 
                            $bg = ($pk['risk_level']??'')==='high' ? '#fff5f5' : '#fffdf0';
                        ?>
                            <tr style="background:<?php echo $bg; ?>;">
                                <td><?php echo substr($pk['date']??'', 5, 11); ?></td>
                                <td><strong><?php echo $pk['type']??'-'; ?></strong></td>
                                <td style="color:#d63638; font-weight:700;"><?php echo number_format((float)$pk['memory'], 1); ?>MB</td>
                                <td><?php echo number_format((float)$pk['time'], 2); ?>s</td>
                                <td><?php echo number_format((float)$pk['cpu'], 1); ?>%</td>
                                <td style="max-width:350px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($pk['url']??''); ?>">
                                    <?php echo esc_html($pk['url']??''); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- 7.1 TOP URLs PROBLEMÁTICAS -->
        <h3 style="font-size:14px; margin-top:30px; margin-bottom:12px;">📍 Top URLs con Peor Rendimiento</h3>
        <div class="ml-card" style="padding:15px;">
            <?php
            // Extraer URLs problemáticas del log (de forma eficiente)
            $url_stats = [];
            $log_file = defined('MLP_LOG_FILE') ? MLP_LOG_FILE : null;
            if ($log_file && file_exists($log_file)) {
                $log_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $log_lines = array_slice($log_lines, -500); // Solo últimas 500 líneas
                
                foreach ($log_lines as $l) {
                    if (!str_contains($l, 'DATE:')) continue;
                    $row = mlp_parse_log_line($l);
                    if (empty($row)) continue;
                    $url = $row['url'] ?? '';
                    $mem = (float)($row['mem'] ?? 0);
                    $time = (float)($row['time'] ?? 0);
                    $cpu = (float)($row['cpu'] ?? 0);
                    
                    if ($url && ($mem > 0 || $time > 0)) {
                        if (!isset($url_stats[$url])) {
                            $url_stats[$url] = ['count' => 0, 'total_mem' => 0, 'total_time' => 0, 'total_cpu' => 0, 'max_mem' => 0, 'max_time' => 0];
                        }
                        $url_stats[$url]['count']++;
                        $url_stats[$url]['total_mem'] += $mem;
                        $url_stats[$url]['total_time'] += $time;
                        $url_stats[$url]['total_cpu'] += $cpu;
                        $url_stats[$url]['max_mem'] = max($url_stats[$url]['max_mem'], $mem);
                        $url_stats[$url]['max_time'] = max($url_stats[$url]['max_time'], $time);
                    }
                }
                
                // Calcular promedios y ordenar por score
                foreach ($url_stats as $url => $data) {
                    $url_stats[$url]['avg_mem'] = $data['total_mem'] / $data['count'];
                    $url_stats[$url]['avg_time'] = $data['total_time'] / $data['count'];
                    $url_stats[$url]['score'] = ($data['avg_mem'] * 2) + ($data['avg_time'] * 10) + ($data['total_cpu'] / $data['count']);
                }
                
                uasort($url_stats, fn($a, $b) => $b['score'] <=> $a['score']);
                $top_urls = array_slice($url_stats, 0, 10, true);
            }
            ?>
            <?php if (!empty($top_urls)): ?>
                <div style="overflow-x:auto;">
                    <table class="wp-list-table widefat fixed striped" style="font-size:11px;">
                        <thead><tr><th>#</th><th>URL</th><th style="text-align:right;">Peticiones</th><th style="text-align:right;">Mem Ø</th><th style="text-align:right;">Tiempo Ø</th><th style="text-align:right;">CPU Ø</th></tr></thead>
                        <tbody>
                            <?php $i = 1; foreach ($top_urls as $url => $data): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td style="max-width:400px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr($url); ?>">
                                        <?php echo esc_html(mb_strlen($url) > 60 ? '...' . mb_substr($url, -57) : $url); ?>
                                    </td>
                                    <td style="text-align:right;"><?php echo $data['count']; ?></td>
                                    <td style="text-align:right; <?php echo $data['avg_mem'] > 100 ? 'color:#d63638;' : ''; ?>"><?php echo number_format($data['avg_mem'], 1); ?> MB</td>
                                    <td style="text-align:right; <?php echo $data['avg_time'] > 2 ? 'color:#d63638;' : ''; ?>"><?php echo number_format($data['avg_time'], 2); ?> s</td>
                                    <td style="text-align:right;"><?php echo number_format($data['total_cpu'] / $data['count'], 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="font-size:11px; color:#646970;">No hay suficientes datos para mostrar ranking de URLs.</p>
            <?php endif; ?>
        </div>

        <!-- 7.2 UNIFIED VISITOR CHART -->
        <h3 style="font-size:14px; margin-top:30px; margin-bottom:12px;">👥 Distribución de Visitantes</h3>
        <div class="ml-card" style="padding:15px;">
            <?php
            // Usar función helper para procesar visitor stats
            $visitor_data = function_exists('mlp_process_visitor_stats') 
                ? mlp_process_visitor_stats($stats['visitor_stats'] ?? [])
                : ['human_count' => 0, 'bot_count' => 0, 'unknown_count' => 0, 'total' => 0];
            
            $human_count = $visitor_data['human_count'];
            $bot_count = $visitor_data['bot_count'];
            $unknown_count = $visitor_data['unknown_count'];
            $total_visitors = $visitor_data['total'];
            ?>
            <?php if ($total_visitors > 0): ?>
                <?php
                $human_pct = round(($human_count / $total_visitors) * 100, 1);
                $bot_pct = round(($bot_count / $total_visitors) * 100, 1);
                $unknown_pct = round(($unknown_count / $total_visitors) * 100, 1);
                ?>
                <div style="margin-bottom:15px;">
                    <div style="display:flex; gap:3px; height:30px; border-radius:4px; overflow:hidden; margin-bottom:10px;">
                        <div style="flex:<?php echo max(1, $human_pct); ?>; background:#00a32a; display:flex; align-items:center; justify-content:center; color:white; font-size:11px; font-weight:700;" title="Humanos: <?php echo $human_pct; ?>%">
                            <?php echo $human_pct >= 10 ? $human_pct . '%' : ''; ?>
                        </div>
                        <div style="flex:<?php echo max(1, $bot_pct); ?>; background:#2271b1; display:flex; align-items:center; justify-content:center; color:white; font-size:11px; font-weight:700;" title="Bots: <?php echo $bot_pct; ?>%">
                            <?php echo $bot_pct >= 10 ? $bot_pct . '%' : ''; ?>
                        </div>
                        <div style="flex:<?php echo max(1, $unknown_pct); ?>; background:#c3c4c7; display:flex; align-items:center; justify-content:center; color:white; font-size:11px; font-weight:700;" title="Desconocidos: <?php echo $unknown_pct; ?>%">
                            <?php echo $unknown_pct >= 10 ? $unknown_pct . '%' : ''; ?>
                        </div>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; gap:15px;">
                        <div style="flex:1; padding:10px; background:#f0fbf0; border-radius:4px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#00a32a;"><?php echo number_format($human_count, 0, ',', '.'); ?></div>
                            <div style="font-size:11px; color:#00a32a;">👤 Humanos</div>
                        </div>
                        <div style="flex:1; padding:10px; background:#e8f2fc; border-radius:4px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#2271b1;"><?php echo number_format($bot_count, 0, ',', '.'); ?></div>
                            <div style="font-size:11px; color:#2271b1;">🤖 Bots</div>
                        </div>
                        <div style="flex:1; padding:10px; background:#f9f9f9; border-radius:4px; text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#646970;"><?php echo number_format($unknown_count, 0, ',', '.'); ?></div>
                            <div style="font-size:11px; color:#646970;">❓ Desconocidos</div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($visitor_stats) && is_array($visitor_stats)): ?>
                <div style="margin-top:15px;">
                    <div style="font-size:12px; font-weight:600; margin-bottom:8px;">🔍 Detalle por Tipo</div>
                    <div style="font-size:11px; background:#f9f9f9; padding:10px; border-radius:4px;">
                        <?php foreach ($visitor_stats as $type => $data): if (is_array($data)): ?>
                            <div style="padding:5px 0; border-bottom:1px solid #eee; display:flex; justify-content:space-between;">
                                <span><?php echo ucfirst($type); ?></span>
                                <span style="font-weight:600;"><?php echo number_format($data['count'] ?? 0, 0, ',', '.'); ?> visitas</span>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#646970;">
                    <div style="font-size:14px; margin-bottom:5px;">📊 Sin datos de visitantes aún</div>
                    <div style="font-size:11px;">Los datos aparecerán tras las primeras visitas registradas.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- DATA PARA JS -->
        <script type="application/json" id="mlp-chart-data"><?php echo wp_json_encode($stats); ?></script>
    </div>
    <?php
}
