    /* =====================================================================
    Memory Logger Pro v12.8.0 – Admin JavaScript
    Restauración total de funcionalidades con detección de bots y Quick Actions
    ===================================================================== */
jQuery(document).ready(function($) {
    'use strict';
    
    const version = (typeof memoryLoggerPro !== 'undefined' && memoryLoggerPro.version) ? memoryLoggerPro.version : '12.8.0';
    console.log('🔧 Memory Logger Pro v' + version + ' inicializando...');

    /* 1. CONFIGURACIÓN Y ESTADO */
    const state = {
        activeTool: null,
        currentExportFormat: 'json',
        charts: {},
        chartType: 'line'
    };

    /* 2. FUNCIONES AUXILIARES */
    function showLoading($el, message) {
        const originalHtml = $el.html();
        $el.data('original-html', originalHtml);
        $el.html('<span class="mlp-spinner"></span> ' + message).prop('disabled', true);
        return originalHtml;
    }

    function restoreButton($el) {
        const originalHtml = $el.data('original-html');
        if (originalHtml) {
            $el.html(originalHtml).prop('disabled', false);
        }
    }

    /* 3. MÓDULO DE DASHBOARD & TABLA */
    const MLPDashboard = {
        init: function() {
            this.initTippyTooltips();
            this.bindEvents();
            this.initTableFilter();
        },

        initTippyTooltips: function() {
            if (typeof tippy === 'undefined') return;

            // Tooltip de Alerta
            tippy('[data-tippy-alerta]', {
                content: (reference) => {
                    const icon = reference.textContent.trim();
                    const map = {
                        '💀': 'Error Fatal (500)',
                        '🔥': 'RAM Crítica (>200MB)',
                        '🐢': 'Lentitud Extrema (carga > 5s)',
                        '💾': 'Sobrecarga SQL (>200)',
                        '⚡': 'CPU Saturada (>80%)',
                        '✅': 'Rendimiento Óptimo'
                    };
                    return map[icon] || 'Detalle del registro';
                },
                theme: 'light-border'
            });

            // Tooltip de Quién (User-Agent/Bot)
            tippy('[data-tippy-ua]', {
                content: (reference) => {
                    return reference.getAttribute('title') || 'Información del visitante';
                },
                theme: 'light-border',
                onShow(instance) {
                    const title = instance.reference.getAttribute('title');
                    if (title) {
                        instance.setContent(title);
                        instance.reference.removeAttribute('title');
                    }
                }
            });

            // Tooltip de Estado (HTTP)
            tippy('[data-tippy-status]', {
                content: (reference) => {
                    return reference.getAttribute('title') || 'Código HTTP';
                },
                theme: 'light-border',
                onShow(instance) {
                    const title = instance.reference.getAttribute('title');
                    if (title) {
                        instance.setContent(title);
                        instance.reference.removeAttribute('title');
                    }
                }
            });

            // Tooltip de Método
            tippy('[data-tippy-metodo]', {
                content: (reference) => {
                    return reference.getAttribute('title') || 'Método de medición';
                },
                theme: 'light-border',
                onShow(instance) {
                    const title = instance.reference.getAttribute('title');
                    if (title) {
                        instance.setContent(title);
                        instance.reference.removeAttribute('title');
                    }
                }
            });

            // Tooltip genérico para otros elementos
            tippy('#ml-table td[title], .ml-card strong[title]', { theme: 'light-border' });
        },

        bindEvents: function() {
            // Botón Probar Métricas
            $(document).on('click', '#ml-test-now', function(e) {
                e.preventDefault();
                const $btn = $(this);
                showLoading($btn, '...');
                $.post(ajaxurl, { 
                    action: 'mlp_run_test', 
                    security: memoryLoggerPro.nonce 
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data.message);
                        restoreButton($btn);
                    }
                });
            });

            // Toggle Ayuda
            $(document).on('click', '.ml-help-toggle', function(e) {
                e.preventDefault();
                const targetId = $(this).data('target') || $(this).data('toggle');
                const $target = $('#' + targetId);
                if ($target.length) {
                    $target.slideToggle(300);
                }
            });

            // OPTIMIZAR BD COMPLETA v12.8.0
            $(document).on('click', '#mlp-optimize-db-complete', function(e) {
                e.preventDefault();
                const $btn = $(this);
                if (!confirm('⚠️ ¿OPTIMIZAR BASE DE DATOS COMPLETA?\n\nEsta acción:\n• Eliminará transients caducados\n• Regenerará todas las tablas (más efectivo para InnoDB)\n• Refrescará métricas\n\nRecomendación: Haz copia de seguridad antes.\n\n¿Continuar?')) return;
                
                // Cambiar botón a estado de carga
                const originalText = $btn.text();
                $btn.text('⏳ Optimizando...').prop('disabled', true);
                
                $.post(ajaxurl, { 
                    action: 'mlp_optimize_db_complete', 
                    security: memoryLoggerPro.clearCacheNonce 
                }, function(res) {
                    if (res.success) {
                        // Mostrar resultado con log
                        let message = res.data.message;
                        if (res.data.log && res.data.log.length > 0) {
                            message += '\n\nLog:\n' + res.data.log.slice(0, 5).join('\n');
                            if (res.data.log.length > 5) {
                                message += '\n...y ' + (res.data.log.length - 5) + ' más';
                            }
                        }
                        $btn.text('✅ ¡Listo!');
                        $('#mlp-quick-actions-result').html('<span style="color:#00a32a;">' + res.data.message + '</span>').show();
                        
                        // Recargar después de 2 segundos
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        let errorMsg = 'Error: ' + (res.data.message || 'Desconocido');
                        if (res.data.log && res.data.log.length > 0) {
                            errorMsg += '\n\nLog:\n' + res.data.log.join('\n');
                        }
                        $btn.text(originalText).prop('disabled', false);
                        $('#mlp-quick-actions-result').html('<span style="color:#d63638;">' + errorMsg.replace(/\n/g, '<br>') + '</span>').show();
                    }
                }).fail(function(xhr) {
                    $btn.text(originalText).prop('disabled', false);
                    $('#mlp-quick-actions-result').html('<span style="color:#d63638;">Error de conexión: ' + xhr.status + '</span>').show();
                });
            });

            // REFRESCAR SALUD Y GRÁFICOS v12.8.0
            $(document).on('click', '#mlp-refresh-health', function(e) {
                e.preventDefault();
                const $btn = $(this);
                $btn.text('⏳ Refrescando...').prop('disabled', true);
                
                $.post(ajaxurl, { 
                    action: 'mlp_refresh_health', 
                    security: memoryLoggerPro.clearCacheNonce 
                }, function(res) {
                    if (res.success && res.data.reload) {
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        $btn.text('🔄 Refrescar').prop('disabled', false);
                    }
                }).fail(function() {
                    $btn.text('🔄 Refrescar').prop('disabled', false);
                });
            });
        },

        initTableFilter: function() {
            const $search = $('#ml-search'), $filter = $('#ml-filter'), $table = $('#ml-table');
            if (!$table.length) return;
            function doFilter() {
                const s = $search.val().toLowerCase(), f = $filter.val().toLowerCase();
                $table.find('tbody tr').each(function() {
                    const $r = $(this), t = $r.text().toLowerCase(), type = $r.find('td:nth-child(3)').text().toLowerCase();
                    $r.toggle(t.indexOf(s) > -1 && (f === '' || type.indexOf(f) > -1));
                });
            }
            $search.on('keyup', doFilter); 
            $filter.on('change', doFilter);
        }
    };

    /* 4. MÓDULO DE DIAGNÓSTICO */
    const MLPDiagnostic = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;
            // Asegurar que usamos el ajaxurl correcto
            const ajaxurl = (typeof memoryLoggerPro !== 'undefined' && memoryLoggerPro.ajaxurl) ? memoryLoggerPro.ajaxurl : window.ajaxurl;

            // Auditoría Global
            $(document).on('click', '#mlp-master-scan-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                if (state.activeTool) return;
                
                state.activeTool = 'master';
                showLoading($btn, 'Auditando...');
                $('#mlp-master-scan-results').slideUp();

                $.post(ajaxurl, { 
                    action: 'mlp_run_full_analysis', 
                    security: memoryLoggerPro.lazyLoadDiagnosticNonce 
                })
                .done(function(res) {
                    if (res && res.success) {
                        $('#mlp-master-scan-results').html(res.data.html).slideDown();
                    } else {
                        const msg = (res && res.data && res.data.message) ? res.data.message : 'Error desconocido';
                        alert('❌ Error: ' + msg);
                        console.error('MLP Master Scan Error:', res);
                    }
                })
                .fail(function(xhr, status, error) {
                    alert('❌ Error de conexión: ' + error);
                    console.error('MLP Master Scan Failed:', xhr.responseText);
                })
                .always(function() { 
                    state.activeTool = null; 
                    restoreButton($btn); 
                });
            });

            // Herramientas individuales
            $(document).on('click', '.mlp-tool-btn', function(e) {
                e.preventDefault();
                const $btn = $(this), action = $btn.data('action');
                if (state.activeTool) return;
                
                const map = { 
                    'analyze-plugins': 'mlp_analyze_wordpress_plugins_optimized', 
                    'error-patterns': 'mlp_analyze_error_patterns', 
                    'file-integrity': 'mlp_check_file_integrity', 
                    'database': 'mlp_analyze_database', 
                    'hosting': 'mlp_get_hosting_recommendations' 
                };
                if (!map[action]) return;

                state.activeTool = action;
                showLoading($btn, '...');
                $('#quick-tools-results').show();
                $('#quick-tools-title').text($btn.find('strong').text() || 'Resultados');
                $('#quick-tools-content').html('<div style="padding:20px;text-align:center;"><span class="mlp-spinner"></span> Analizando...</div>');

                console.log('MLP: Running tool', action, '->', map[action]);

                $.post(ajaxurl, { 
                    action: map[action], 
                    security: memoryLoggerPro.lazyLoadDiagnosticNonce 
                })
                .done(function(res) {
                    console.log('MLP: Tool response', res);
                    if (res && res.success) {
                        $('#quick-tools-content').html(res.data.html);
                    } else {
                        const msg = (res && res.data && res.data.message) ? res.data.message : 'Respuesta inválida';
                        $('#quick-tools-content').html('<div class="notice notice-error"><p>❌ ' + msg + '</p></div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('MLP: Tool failed', status, error, xhr.responseText);
                    $('#quick-tools-content').html('<div class="notice notice-error"><p>❌ Error de conexión (' + status + '): ' + error + '</p></div>');
                })
                .always(function() { 
                    state.activeTool = null; 
                    restoreButton($btn); 
                });
            });

            $(document).on('click', '#mlp-close-results', function() { $('#quick-tools-results').hide(); });

            // Exportar Reporte
            $(document).on('click', '#export-diagnostic-report', function(e) {
                e.preventDefault();
                const $btn = $(this), format = $('#export-format-select').val();
                showLoading($btn, 'Generando...');
                
                $.post(ajaxurl, { 
                    action: 'mlp_export_diagnostic_report_ajax', 
                    security: memoryLoggerPro.exportDiagnosticNonce, 
                    format: format 
                })
                .done(function(res) {
                    if (res.success && res.data.content) {
                        const blob = new Blob([res.data.content], { type: 'application/octet-stream' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a'); 
                        a.href = url; 
                        a.download = res.data.filename || 'report.' + format;
                        document.body.appendChild(a); 
                        a.click();
                        setTimeout(function() { 
                            window.URL.revokeObjectURL(url); 
                            document.body.removeChild(a); 
                        }, 100);
                    } else {
                         alert('Error al exportar: ' + (res.data ? res.data.message : 'Respuesta vacía'));
                    }
                })
                .fail(function() { alert('Error de red al exportar.'); })
                .always(function() { restoreButton($btn); });
            });
        }
    };

    /* 5. MÓDULO DE ESTADÍSTICAS */
    const MLPStatistics = {
        init: function() {
            if (typeof Chart === 'undefined') return;
            const $dataScript = $('#mlp-chart-data');
            if (!$dataScript.length) return;

            try {
                this.data = JSON.parse($dataScript.html());
                this.initCharts();
                this.bindEvents();
            } catch (e) {
                console.error('MLP Stats Error:', e);
            }
        },

        initCharts: function() {
            const ctx1 = document.getElementById('mlChartMemorySql');
            const ctx2 = document.getElementById('mlChartTimeCpu');
            const ctx3 = document.getElementById('mlChartSizeCpu');

            if (ctx1) this.createChart(ctx1, 'memory', 'sql', 'Memoria (MB)', 'SQL Queries', '#2271b1', '#d63638');
            if (ctx2) this.createChart(ctx2, 'time', 'cpu', 'Tiempo (s)', 'CPU (%)', '#00a32a', '#f0b849');
            if (ctx3) this.createChart(ctx3, 'size', 'cpu', 'Tamaño (KB)', 'CPU (%)', '#646970', '#f0b849');
        },

        createChart: function(ctx, key1, key2, label1, label2, color1, color2) {
            const limit = parseInt($('#mlp-chart-event-count').val()) || 100;
            const labels = (this.data.labels || []).slice(-limit);
            const d1 = (this.data[key1 + '_data'] || []).slice(-limit);
            const d2 = (this.data[key2 + '_data'] || []).slice(-limit);

            const type = $('.mlp-chart-type-btn.active').data('type') || 'line';

            new Chart(ctx, {
                type: type,
                data: {
                    labels: labels,
                    datasets: [
                        { 
                            label: label1, 
                            data: d1, 
                            borderColor: color1, 
                            backgroundColor: color1 + '33', 
                            borderWidth: 2,
                            tension: 0.3,
                            fill: type === 'line',
                            yAxisID: 'y' 
                        },
                        { 
                            label: label2, 
                            data: d2, 
                            borderColor: color2, 
                            backgroundColor: color2 + '33', 
                            borderWidth: 2,
                            tension: 0.3,
                            fill: type === 'line',
                            yAxisID: 'y1' 
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        y: { 
                            type: 'linear', 
                            display: true, 
                            position: 'left',
                            grid: { color: '#f0f0f0' }
                        },
                        y1: { 
                            type: 'linear', 
                            display: true, 
                            position: 'right', 
                            grid: { drawOnChartArea: false } 
                        },
                        x: {
                            grid: { display: false },
                            ticks: { maxTicksLimit: 10 }
                        }
                    }
                }
            });
        },

        bindEvents: function() {
            const self = this;
            $('#mlp-chart-event-count').on('change', function() {
                self.updateCharts();
            });

            $('.mlp-chart-type-btn').on('click', function() {
                $('.mlp-chart-type-btn').removeClass('active');
                $(this).addClass('active');
                self.updateCharts();
            });
        },

        updateCharts: function() {
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.destroy();
            });
            this.initCharts();
        }
    };

    // Inicializar todo
    MLPDashboard.init();
    MLPDiagnostic.init();
    MLPStatistics.init();
});
