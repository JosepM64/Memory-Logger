<?php
/**
 * Memory Logger Pro v13.2.0 - Funciones de Utilidad
 *
 * Funciones auxiliares generales y reutilizables
 * Optimizado para PHP 8.2+ con tipado estricto
 * Incluye funciones de sanitización de seguridad
 *
 * @package Memory Logger Pro
 * @version 12.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure safe HTML escapes
if (!function_exists('mlp_safe')) {
    function mlp_safe($text) {
        if (function_exists('esc_html')) {
            return esc_html($text);
        }
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mlp_get_cached')) {
    function mlp_get_cached($key, $callback, $ttl = 300) {
        $cache_key = 'mlp_cache_' . $key;
        if (function_exists('get_transient') && function_exists('set_transient')) {
            $data = get_transient($cache_key);
            if ($data === false) {
                $data = call_user_func($callback);
                set_transient($cache_key, $data, (int)$ttl);
            }
            return $data;
        }
        static $cache = [];
        if (isset($cache[$cache_key])) {
            $entry = $cache[$cache_key];
            if ((time() - $entry['time']) < (int)$ttl) {
                return $entry['value'];
            }
        }
        $value = call_user_func($callback);
        $cache[$cache_key] = ['time' => time(), 'value' => $value];
        return $value;
    }
}

if (!function_exists('mlp_render_history_row')) {
    function mlp_render_history_row($row, $ua_raw = '') {
        $fields = ['alert','date','type','who','status','url','mem','time','sql','size','cpu','method'];
        $html = '<tr class="mlp-history-row">';
        foreach ($fields as $f) {
            if ($f === 'method') {
                $val = mlp_get_size_method_badge($row['method'] ?? '');
                $tooltip = 'Método de medición de tamaño: ' . ($row['method'] ?? 'desconocido');
                $html .= '<td class="mlp-col-' . mlp_safe($f) . '" data-tippy-metodo="true" title="' . mlp_safe($tooltip) . '">' . $val . '</td>';
            } elseif ($f === 'who') {
                $val = $row['who'] ?? '';
                // Obtener información del visitante
                $visitor = mlp_identify_visitor_type($ua_raw);
                $tooltip = 'Tipo: ' . ucfirst($visitor['type']) . ' | Categoría: ' . ucfirst($visitor['category']) . ' | Nombre: ' . $visitor['name'];
                $html .= '<td class="mlp-col-' . mlp_safe($f) . '" data-tippy-ua="true" title="' . mlp_safe($tooltip) . '">' . $val . '</td>';
            } elseif ($f === 'status') {
                $val = $row['status'] ?? '';
                $http_code = (int)$val;
                $tooltip = 'Código HTTP: ' . $http_code . ' - ' . mlp_get_http_status_text($http_code);
                $html .= '<td class="mlp-col-' . mlp_safe($f) . '" data-tippy-status="true" title="' . mlp_safe($tooltip) . '">' . $val . '</td>';
            } elseif ($f === 'alert') {
                $val = isset($row[$f]) ? mlp_safe($row[$f]) : '';
                $alert_tooltip = mlp_get_alert_tooltip($val);
                $html .= '<td class="mlp-col-' . mlp_safe($f) . '" data-tippy-alerta="true">' . $val . '</td>';
            } else {
                $val = isset($row[$f]) ? mlp_safe($row[$f]) : '';
                $html .= '<td class="mlp-col-' . mlp_safe($f) . '">' . $val . '</td>';
            }
        }
        $html .= '</tr>';
        return $html;
    }
}

/**
 * Obtener tooltip para alertas (NUEVO v12.8.0)
 */
if (!function_exists('mlp_get_alert_tooltip')) {
    function mlp_get_alert_tooltip(string $icon): string {
        $map = [
            '💀' => 'Error Fatal (HTTP 500+)',
            '🔥' => 'RAM Crítica (>200MB)',
            '🐢' => 'Lentitud Extrema (>5s)',
            '💾' => 'Sobrecarga SQL (>200 queries)',
            '⚡' => 'CPU Saturada (>80%)',
            '✅' => 'Rendimiento Óptimo',
            '-' => 'Sin alertas'
        ];
        return $map[$icon] ?? 'Estado del registro';
    }
}

/**
 * Obtener texto descriptivo de código HTTP (NUEVO v12.8.0)
 */
if (!function_exists('mlp_get_http_status_text')) {
    function mlp_get_http_status_text(int $code): string {
        $codes = [
            200 => 'OK',
            201 => 'Created',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout'
        ];
        return $codes[$code] ?? 'Código HTTP';
    }
}

/* =========================================================================
   FIN DE FUNCIONES DE VISITANTES Y TOOLTIPS
   ========================================================================= */

/* =========================================================================
   FUNCIONES DE CONVERSIÓN Y FORMATO
   ========================================================================= */

/**
 * Convertir valor de memoria a bytes
 *
 * @param string $value Valor con sufijo (G, M, K)
 * @return int Valor en bytes
 */
function mlp_convert_to_bytes(string $value): int {
    $value = trim($value);
    if ($value === '' || $value === '0') {
        return 0;
    }
    
    if ($value === '-1' || $value === '-1M') {
        return -1;
    }

    $last_char = strtolower($value[strlen($value) - 1]);
    $num_value = (int) $value;

    return match ($last_char) {
        'g' => $num_value * 1024 * 1024 * 1024,
        'm' => $num_value * 1024 * 1024,
        'k' => $num_value * 1024,
        default => $num_value,
    };
}

/**
 * Obtener información de estado HTTP
 *
 * @param int $code Código HTTP
 * @return array Array con icon, color, código y texto
 */
function mlp_get_http_status_info(int $code): array {
    $status_map = [
        200 => ['icon' => '✅', 'color' => 'green', 'text' => 'OK'],
        301 => ['icon' => '🔄', 'color' => 'blue', 'text' => 'Moved'],
        302 => ['icon' => '🔄', 'color' => 'blue', 'text' => 'Found'],
        404 => ['icon' => '❌', 'color' => 'red', 'text' => 'Not Found'],
        500 => ['icon' => '💀', 'color' => 'red', 'text' => 'Server Error'],
    ];

    $info = $status_map[$code] ?? ['icon' => '✅', 'color' => 'green', 'text' => 'OK'];
    $display_code = in_array($code, [200, 301, 302, 404, 500], true) ? $code : 200;

    return [
        'icon' => $info['icon'],
        'code' => $display_code,
        'color' => $info['color'],
        'text' => "HTTP {$display_code}",
    ];
}

/**
 * Identificar User Agent
 *
 * @param string $ua User Agent string
 * @return array Array con icono, tipo y versión truncada
 */
function mlp_identify_user_agent(string $ua): array {
    if (empty($ua) || $ua === 'N/A') {
        return ['icon' => '🌐', 'type' => 'Unknown', 'ua' => 'N/A'];
    }

    $ua_lower = strtolower($ua);
    $icon = '🌐';
    $type = 'Browser';

    // Patrones de detección
    return match (true) {
        // Bots
        str_contains($ua_lower, 'googlebot') => ['icon' => '🤖', 'type' => 'Googlebot', 'ua' => substr($ua, 0, 50)],
        str_contains($ua_lower, 'bingbot') => ['icon' => '🤖', 'type' => 'Bingbot', 'ua' => substr($ua, 0, 50)],
        str_contains($ua_lower, 'bot'), str_contains($ua_lower, 'crawler'), str_contains($ua_lower, 'spider')
            => ['icon' => '🤖', 'type' => 'Bot', 'ua' => substr($ua, 0, 50)],

        // Herramientas
        str_contains($ua_lower, 'curl') => ['icon' => '🖥️', 'type' => 'cURL', 'ua' => substr($ua, 0, 50)],
        str_contains($ua_lower, 'postman') => ['icon' => '📮', 'type' => 'Postman', 'ua' => substr($ua, 0, 50)],

        // Dispositivos móviles
        str_contains($ua_lower, 'tablet'), str_contains($ua_lower, 'ipad')
            => ['icon' => '📱', 'type' => 'Tablet', 'ua' => substr($ua, 0, 50)],
        str_contains($ua_lower, 'mobile'), str_contains($ua_lower, 'android')
            => ['icon' => '📱', 'type' => 'Mobile', 'ua' => substr($ua, 0, 50)],

        // Navegadores
        str_contains($ua_lower, 'chrome') => ['icon' => '👤', 'type' => 'Chrome', 'ua' => substr($ua, 0, 50)],
        str_contains($ua_lower, 'safari') && !str_contains($ua_lower, 'chrome')
            => ['icon' => '👤', 'type' => 'Safari', 'ua' => substr($ua, 0, 50)],
        str_contains($ua_lower, 'firefox') => ['icon' => '👤', 'type' => 'Firefox', 'ua' => substr($ua, 0, 50)],

        default => ['icon' => '🌐', 'type' => 'Browser', 'ua' => substr($ua, 0, 50)],
    };
}

/**
 * Sanitizar URL mejorada (ofusca datos sensibles)
 *
 * @param string $url URL a sanitizar
 * @return string URL sanitizada
 */
function mlp_sanitize_url_enhanced(string $url): string {
    if (function_exists('sanitize_url')) {
        $url = sanitize_url($url);
    }

    // Eliminar caracteres no imprimibles
    $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? $url;

    // Parámetros sensibles
    $sensitive_params = [
        'token', 'key', 'password', 'pwd', 'pass', 'secret', 'auth',
        'api_key', 'apikey', 'session', 'sid', 'jwt', 'nonce',
        'signature', 'hash', 'wpnonce', '_wpnonce', 'access_token',
        'refresh_token', 'authorization'
    ];

    $pattern = '/([?&])(' . implode('|', $sensitive_params) . ')=[^&]*/i';
    $url = preg_replace($pattern, '$1$2=***', $url) ?? $url;

    // Truncar si es muy larga
    if (strlen($url) > 250) {
        $url = substr($url, 0, 247) . '...';
    }

    return $url;
}

/**
 * Formatear número con separadores
 *
 * @param mixed $number Número a formatear
 * @param int $decimals Decimales
 * @return string Número formateado
 */
function mlp_format_number($number, int $decimals = 0): string {
    if (!is_numeric($number)) {
        return '0';
    }

    $number = (float) $number;
    return $number >= 1000
        ? number_format($number, $decimals, '.', ',')
        : number_format($number, $decimals, '.', '');
}

/**
 * Truncar URL para display
 *
 * @param string $url URL a truncar
 * @param int $max Longitud máxima
 * @return string URL truncada
 */
function mlp_truncate_url(string $url, int $max = 60): string {
    if ($url === '') {
        return '';
    }
    if (strlen($url) <= $max) {
        return $url;
    }
    return substr($url, 0, $max - 3) . '...';
}

/* =========================================================================
   FUNCIONES DE SISTEMA
   ========================================================================= */

/**
 * Obtener información del sistema operativo
 *
 * @return array Información del OS
 */
function mlp_get_os_info(): array {
    static $os_info = null;

    if ($os_info !== null) {
        return $os_info;
    }

    $os_info = [
        'is_linux'   => false,
        'is_windows' => false,
        'is_macos'   => false,
        'is_unix'    => false,
        'is_bsd'     => false,
        'name'       => PHP_OS_FAMILY,
        'details'    => PHP_OS,
        'raw'        => PHP_OS,
    ];

    $os_family = strtolower(PHP_OS_FAMILY);

    return match ($os_family) {
        'linux' => [
            ...$os_info,
            'is_linux' => true,
            'is_unix' => true,
            'details' => mlp_get_linux_details(),
        ],
        'windows' => [
            ...$os_info,
            'is_windows' => true,
            'details' => php_uname('s') . ' ' . php_uname('r'),
        ],
        'darwin' => [
            ...$os_info,
            'is_macos' => true,
            'is_unix' => true,
            'details' => 'macOS ' . (php_uname('r') ?: ''),
        ],
        'freebsd', 'openbsd', 'netbsd' => [
            ...$os_info,
            'is_bsd' => true,
            'is_unix' => true,
            'details' => ucfirst($os_family) . ' ' . (php_uname('r') ?: ''),
        ],
        default => $os_info,
    };
}

/**
 * Obtener detalles específicos de Linux
 *
 * @return string Detalles del sistema Linux
 */
function mlp_get_linux_details(): string {
    $disabled_functions = ini_get('disable_functions') ?: '';

    if (!function_exists('shell_exec') || str_contains($disabled_functions, 'shell_exec')) {
        return PHP_OS;
    }

    $release = @shell_exec('cat /etc/os-release 2>/dev/null || cat /etc/issue 2>/dev/null || uname -a 2>/dev/null');

    return $release ? trim(substr($release, 0, 100)) : PHP_OS;
}

if (!function_exists('mlp_detect_server_type')) {
    /**
     * Detectar tipo de servidor web
     *
     * @param string|null $server_software Software del servidor
     * @return string Tipo de servidor detectado
     */
    function mlp_detect_server_type(?string $server_software = null): string {
        if ($server_software === null) {
            $server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        }

        $server_software_lower = strtolower($server_software);

        $server_patterns = [
            'litespeed' => str_contains($server_software_lower, 'openlitespeed') ? 'OpenLiteSpeed' : 'LiteSpeed',
            'apache' => 'Apache',
            'nginx' => 'Nginx',
            'iis', 'microsoft-iis' => 'IIS',
            'caddy' => 'Caddy',
            'lighttpd' => 'Lighttpd',
            'tomcat' => 'Tomcat',
        ];

        foreach ($server_patterns as $pattern => $server_name) {
            if (str_contains($server_software_lower, $pattern)) {
                return $server_name;
            }
        }

        if (preg_match('/^([a-zA-Z0-9]+)/', $server_software, $matches)) {
            return ucfirst($matches[1]);
        }

        return 'Unknown';
    }
}

/**
 * Obtener información de alerta
 *
 * @param string $alert Carácter de alerta
 * @return string Descripción de la alerta
 */
function mlp_get_alert_info(string $alert): string {
    $alert_descriptions = [
        '-' => 'Sin anomalías',
        '💀' => 'Error Fatal: Página en blanco (WSOD)',
        '🔥' => 'Memoria crítica (>200 MB)',
        '🐢' => 'Lentitud extrema (>5 segundos)',
        '💾' => 'Sobrecarga SQL (>200 queries)',
        '⚡' => 'CPU saturada (>80% de uso)',
        '✅' => 'Todo correcto',
        '⚠️' => 'Advertencia',
        '🚨' => 'Alerta crítica',
    ];

    return $alert_descriptions[$alert] ?? 'Desconocido';
}

/**
 * Obtener badge para método de tamaño
 *
 * @param string $method Método de medición
 * @return string Emoji badge
 */
function mlp_get_size_method_badge(string $method): string {
    $method_badges = [
        'output_buffer' => '🔧',
        'global_variable' => '🌐',
        'globals_fallback' => '🔄',
        'globals_backup' => '💾',
        'ob_get_length' => '📏',
        'ob_status_check' => '🔍',
        'intelligent_estimation' => '🧠',
        'html_estimation' => '📄',
        'json_estimation' => '📊',
        'admin_estimation' => '⚙️',
        'cron_cli_detected' => '⏰',
        'absolute_minimum_fallback' => '🛡️',
        'legacy' => '👴',
        'legacy_global' => '🌐',
        'legacy_ob' => '🔧',
        'legacy_globals' => '🔄',
        'legacy_ob_length' => '📏',
        'legacy_estimation' => '🧠',
        'fatal_error' => '💀',
        'disabled' => '🚫',
    ];

    return $method_badges[$method] ?? '❓';
}

/**
 * Obtener descripción del método de medición de tamaño
 *
 * @param string $method Método de medición
 * @return string Descripción del método
 */
function mlp_get_size_method_description(string $method): string {
    $descriptions = [
        'output_buffer' => 'Buffer de salida principal',
        'global_variable' => 'Variable global $mlp_page_size',
        'globals_fallback' => 'GLOBALS fallback (método secundario)',
        'globals_backup' => 'GLOBALS backup (método terciario)',
        'ob_get_length' => 'Función ob_get_length()',
        'ob_status_check' => 'Verificación de estado del buffer',
        'intelligent_estimation' => 'Estimación inteligente basada en memoria, tiempo y SQL',
        'html_estimation' => 'Estimación para contenido HTML',
        'json_estimation' => 'Estimación para contenido JSON',
        'admin_estimation' => 'Estimación para área de administración',
        'cron_cli_detected' => 'Detectado como ejecución Cron/CLI',
        'absolute_minimum_fallback' => 'Fallback mínimo absoluto',
        'legacy' => 'Método legacy compatible',
        'legacy_global' => 'Método legacy global',
        'legacy_ob' => 'Método legacy output buffer',
        'legacy_globals' => 'Método legacy globals',
        'legacy_ob_length' => 'Método legacy ob_get_length',
        'legacy_estimation' => 'Método legacy estimation',
        'fatal_error' => 'Error fatal detectado - medición limitada',
        'disabled' => 'Medición deshabilitada por configuración',
        'unknown' => 'Método de medición no especificado',
    ];

    return $descriptions[$method] ?? 'Método de medición no especificado';
}

/**
 * Generar color por porcentaje
 *
 * @param float $percent Porcentaje (0-100)
 * @param bool $inverse Invertir colores
 * @return string Código de color hexadecimal
 */
function mlp_get_percent_color(float $percent, bool $inverse = false): string {
    if ($inverse) {
        return match (true) {
            $percent < 20 => '#00a32a',
            $percent < 40 => '#3aa745',
            $percent < 60 => '#f0b849',
            $percent < 80 => '#f0b849',
            default => '#d63638',
        };
    }

    return match (true) {
        $percent >= 90 => '#d63638',
        $percent >= 80 => '#f0b849',
        $percent >= 70 => '#f0b849',
        $percent >= 50 => '#3aa745',
        default => '#00a32a',
    };
}

/* =========================================================================
   FUNCIONES DE TIEMPO Y FECHAS
   ========================================================================= */

/**
 * Convertir segundos a formato HH:MM:SS o MM:SS
 *
 * @param float $seconds Segundos
 * @return string Tiempo formateado
 */
function mlp_seconds_to_time(float $seconds): string {
    $hours = (int) floor($seconds / 3600);
    $minutes = (int) floor(($seconds % 3600) / 60);
    $secs = (int) floor($seconds % 60);

    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%02d:%02d', $minutes, $secs);
}

/**
 * Tiempo transcurrido en formato humano
 *
 * @param int $timestamp Timestamp de inicio
 * @return string Tiempo en formato relativo
 */
function mlp_human_time_elapsed(int $timestamp): string {
    $time_diff = time() - $timestamp;

    return match (true) {
        $time_diff < 60 => sprintf(
            'hace %d segundo%s',
            $time_diff,
            $time_diff !== 1 ? 's' : ''
        ),
        $time_diff < 3600 => sprintf(
            'hace %d minuto%s',
            (int) floor($time_diff / 60),
            (int) floor($time_diff / 60) !== 1 ? 's' : ''
        ),
        $time_diff < 86400 => sprintf(
            'hace %d hora%s',
            (int) floor($time_diff / 3600),
            (int) floor($time_diff / 3600) !== 1 ? 's' : ''
        ),
        default => sprintf(
            'hace %d día%s',
            (int) floor($time_diff / 86400),
            (int) floor($time_diff / 86400) !== 1 ? 's' : ''
        ),
    };
}

/* =========================================================================
   FUNCIONES DE VERIFICACIÓN Y SEGURIDAD
   ========================================================================= */

/**
 * Verificar si una función PHP está habilitada
 *
 * @param string $function_name Nombre de la función
 * @return bool true si está disponible y habilitada
 */
function mlp_is_function_available(string $function_name): bool {
    if (!function_exists($function_name)) {
        return false;
    }

    $disabled_functions = explode(',', ini_get('disable_functions') ?: '');
    $disabled_functions = array_map('trim', $disabled_functions);

    return !in_array($function_name, $disabled_functions, true);
}

/**
 * Verificar permisos de escritura seguros
 *
 * @param string $path Ruta a verificar
 * @return bool true si es escribible
 */
function mlp_is_writable(string $path): bool {
    if (!file_exists($path)) {
        return is_writable(dirname($path));
    }

    return is_writable($path);
}

/**
 * Verificar versión mínima de PHP
 *
 * @param string $min_version Versión mínima requerida
 * @return bool true si la versión actual es mayor o igual
 */
function mlp_check_php_version(string $min_version = '7.4'): bool {
    return version_compare(PHP_VERSION, $min_version, '>=');
}

/**
 * Verificar versión mínima de WordPress
 *
 * @param string $min_version Versión mínima requerida
 * @return bool true si la versión actual es mayor o igual
 */
function mlp_check_wp_version(string $min_version = '5.0'): bool {
    global $wp_version;
    return version_compare($wp_version, $min_version, '>=');
}

/**
 * Limpiar memoria forzadamente
 *
 * @return void
 */
function mlp_cleanup_memory(): void {
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    if (function_exists('gc_mem_caches')) {
        gc_mem_caches();
    }

    // Liberar variables grandes si existen
    if (isset($GLOBALS['mlp_large_data'])) {
        unset($GLOBALS['mlp_large_data']);
    }
}

/* =========================================================================
   FUNCIONES DE LIMPIEZA Y CACHE
   ========================================================================= */

/**
 * Limpiar todos los logs y cachés del plugin
 *
 * @return bool true si la operación fue exitosa
 */
function mlp_clear_all_logs_cache(): bool {
    $success = true;

    // Limpiar archivos de logs
    if (file_exists(MLP_LOG_FILE)) {
        $success = $success && (false !== @file_put_contents(MLP_LOG_FILE, ''));
    }

    if (file_exists(MLP_ERROR_LOG_FILE)) {
        $success = $success && (false !== @file_put_contents(MLP_ERROR_LOG_FILE, ''));
    }

    // Limpiar transients
    mlp_purge_cache();

    return $success;
}

/**
 * Sanitizar valores para CSV
 *
 * @param string $value Valor a sanitizar
 * @return string Valor escapado para CSV
 */
function mlp_esc_csv(string $value): string {
    if (str_contains($value, '"') || str_contains($value, ',') ||
        str_contains($value, "\n") || str_contains($value, "\r")) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

/* =========================================================================
   FUNCIONES DE SANITIZACIÓN DE SEGURIDAD (v12.8.0)
   ========================================================================= */

/**
 * Sanitizar URLs de forma segura
 *
 * @param string $url URL a sanitizar
 * @return string URL sanitizada
 */
function mlp_sanitize_url(string $url): string {
    return esc_url_raw($url);
}

/**
 * Sanitizar valores de memoria (números decimales)
 *
 * @param mixed $value Valor a sanitizar
 * @return string Valor numérico limpio
 */
function mlp_sanitize_memory_value(mixed $value): string {
    $value = sanitize_text_field((string) $value);
    return (string) preg_replace('/[^0-9.]/', '', $value);
}

/**
 * Sanitizar nombre de plugin de forma segura
 *
 * @param string $name Nombre del plugin
 * @return string Nombre sanitizado
 */
function mlp_sanitize_plugin_name(string $name): string {
    return sanitize_text_field($name);
}

/**
 * Sanitizar versión de plugin de forma segura
 *
 * @param string $version Versión del plugin
 * @return string Versión sanitizada
 */
function mlp_sanitize_plugin_version(string $version): string {
    return sanitize_text_field($version);
}

/**
 * Sanitizar slug de plugin de forma segura
 *
 * @param string $slug Slug del plugin
 * @return string Slug sanitizado
 */
function mlp_sanitize_plugin_slug(string $slug): string {
    return sanitize_key($slug);
}

/**
 * Sanitizar array de datos de plugin
 *
 * @param array $plugin_data Datos del plugin
 * @return array Datos sanitizados
 */
function mlp_sanitize_plugin_data(array $plugin_data): array {
    return [
        'name' => mlp_sanitize_plugin_name($plugin_data['name'] ?? ''),
        'version' => mlp_sanitize_plugin_version($plugin_data['version'] ?? ''),
        'slug' => mlp_sanitize_plugin_slug($plugin_data['slug'] ?? ''),
        'description' => sanitize_text_field($plugin_data['description'] ?? ''),
        'author' => sanitize_text_field($plugin_data['author'] ?? ''),
    ];
}

/**
 * Sanitizar datos de seguridad para output
 *
 * @param array $data Datos de seguridad
 * @return array Datos sanitizados
 */
function mlp_sanitize_security_data(array $data): array {
    $sanitized = [];

    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $sanitized[$key] = sanitize_text_field($value);
        } elseif (is_array($value)) {
            $sanitized[$key] = array_map('sanitize_text_field', $value);
        } elseif (is_numeric($value)) {
            $sanitized[$key] = $value;
        } else {
            $sanitized[$key] = '';
        }
    }

    return $sanitized;
}

/**
 * Validar y sanitizar nonce AJAX
 *
 * @param string $nonce Nonce a validar
 * @param string $action Acción del nonce
 * @return bool Verdadero si el nonce es válido
 */
function mlp_validate_ajax_nonce(string $nonce, string $action): bool {
    return wp_verify_nonce($nonce, $action) !== false;
}

/**
 * Verificar permisos de usuario para acciones administrativas
 *
 * @param string $capability Capacidad a verificar
 * @return bool Verdadero si el usuario tiene permiso
 */
function mlp_verify_admin_permission(string $capability = 'manage_options'): bool {
    return current_user_can($capability);
}

/**
 * Sanitizar valor entero positivo
 *
 * @param mixed $value Valor a sanitizar
 * @param int $default Valor por defecto
 * @return int Entero sanitizado
 */
function mlp_sanitize_int(mixed $value, int $default = 0): int {
    $sanitized = filter_var($value, FILTER_VALIDATE_INT);
    return $sanitized !== false && $sanitized >= 0 ? $sanitized : $default;
}

/**
 * Sanitizar valor flotante positivo
 *
 * @param mixed $value Valor a sanitizar
 * @param float $default Valor por defecto
 * @return float Flotante sanitizado
 */
function mlp_sanitize_float(mixed $value, float $default = 0.0): float {
    $sanitized = filter_var($value, FILTER_VALIDATE_FLOAT);
    return $sanitized !== false && $sanitized >= 0 ? $sanitized : $default;
}

/**
 * Escapar salida HTML de forma segura
 *
 * @param mixed $value Valor a escapar
 * @return string Valor escapado para HTML
 */
function mlp_esc_html(mixed $value): string {
    if (function_exists('esc_html')) {
        return esc_html((string) $value);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Escapar atributo HTML de forma segura
 *
 * @param mixed $value Valor a escapar
 * @return string Valor escapado para atributos HTML
 */
function mlp_esc_attr(mixed $value): string {
    if (function_exists('esc_attr')) {
        return esc_attr((string) $value);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizar clave de caché
 *
 * @param string $key Clave de caché
 * @return string Clave sanitizada
 */
function mlp_sanitize_cache_key(string $key): string {
    return sanitize_key($key);
}

/**
 * Procesar visitor_stats de forma consistente (NUEVO v12.8.0)
 * Función helper para evitar redundancia en las vistas
 * 
 * @param array $visitor_stats Datos crudos de visitor stats
 * @return array Datos procesados con human_count, bot_count, unknown_count, percentages
 */
if (!function_exists('mlp_process_visitor_stats')) {
    function mlp_process_visitor_stats(array $visitor_stats): array {
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
        
        $total = $human_count + $bot_count + $unknown_count;
        
        return [
            'human_count' => $human_count,
            'bot_count' => $bot_count,
            'unknown_count' => $unknown_count,
            'total' => $total,
            'human_pct' => $total > 0 ? round(($human_count / $total) * 100, 1) : 0,
            'bot_pct' => $total > 0 ? round(($bot_count / $total) * 100, 1) : 0,
            'unknown_pct' => $total > 0 ? round(($unknown_count / $total) * 100, 1) : 0,
            'has_data' => $total > 0,
        ];
    }
}

/**
 * Parse a single log line into an associative array.
 * Expected format: "DATE:value | TYPE:value | URL:value | MEM:value | TIME:value | SQL:value | CPU:value | HTTP:value | UA:value | VISITOR:value | SIZE_METHOD:value | SIZE:value"
 * 
 * @param string $line A single line from the log file.
 * @return array Associative array with keys: date, type, url, mem, time, sql, cpu, http, ua, visitor, size_method, size.
 *         Returns empty array if line does not contain DATE:.
 */
if (!function_exists('mlp_parse_log_line')) {
    function mlp_parse_log_line(string $line): array {
        if (empty($line) || !str_contains($line, 'DATE:')) {
            return [];
        }
        $parts = explode(' | ', $line);
        $result = [];
        foreach ($parts as $part) {
            $kv = explode(':', $part, 2);
            if (count($kv) === 2) {
                $result[trim($kv[0])] = trim($kv[1]);
            }
        }
        // Normalize keys to lowercase for consistency
        $normalized = [];
        foreach ($result as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }
        return $normalized;
    }
}

/**
 * Purge all plugin-specific transients and caches.
 * This function deletes all transients that start with 'mlp_' and related site transients.
 * 
 * @return void
 */
if (!function_exists('mlp_purge_cache')) {
    function mlp_purge_cache(): void {
        global $wpdb;
        // Delete plugin transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mlp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_mlp_%'");
        // Delete timeout transients as well
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mlp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_timeout_mlp_%'");
        // Also delete any specific transients we know about (optional)
        delete_transient('mlp_system_health');
        delete_transient('mlp_cpu_calibration_' . gethostname());
        delete_transient('mlp_security_scan_' . md5(MLP_PATH));
        delete_transient('mlp_quick_security_scan_' . md5(MLP_PATH));
        delete_transient('mlp_system_health_v9');
        delete_transient('mlp_advanced_stats_v7');
        delete_transient('mlp_error_patterns_analysis_' . md5(MLP_PATH));
        delete_transient('mlp_file_integrity_check_' . md5(MLP_PATH));
        delete_transient('mlp_hosting_recommendations_' . md5(MLP_PATH . PHP_VERSION));
        delete_transient('mlp_database_analysis_' . md5(MLP_PATH));
        delete_transient('mlp_wp_plugins_analysis_v4_' . md5(MLP_PATH . get_bloginfo('version')));
        delete_transient('mlp_quick_security_scan_opt_' . md5(MLP_PATH));
    }
}

/**
 * Obtener OPcache hit rate de forma segura (NUEVO v12.8.1)
 * Maneja el error "restrict_api" en hosting compartidos
 * 
 * @return float Hit rate percentage (0 si no disponible)
 */
if (!function_exists('mlp_safe_opcache_get_hit')) {
    function mlp_safe_opcache_get_hit(): float {
        if (!function_exists('opcache_get_status')) {
            return 0;
        }
        
        $status = @opcache_get_status(false);
        if (empty($status) || !is_array($status)) {
            return 0;
        }
        if (isset($status['opcache_statistics']['opcache_hit_rate'])) {
            return (float) $status['opcache_statistics']['opcache_hit_rate'];
        }
        return 0;
    }
}

/**
 * Calibración de CPU (MEJORADA v12.8.0)
 * Calcula un factor de calibración basado en el tipo de hosting y carga del sistema
 */
if (!function_exists('mlp_get_cpu_calibration')) {
    function mlp_get_cpu_calibration(): array {
        $cache_key = 'mlp_cpu_calibration_' . gethostname();
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        $hosting = function_exists('mlp_detect_hosting_type') ? mlp_detect_hosting_type() : [];
        $load = function_exists('mlp_get_system_cpu_load') ? mlp_get_system_cpu_load() : 0;
        
        // Factor base según tipo de hosting
        $base_factor = match ($hosting['type'] ?? 'shared') {
            'cloud' => 1.0,
            'vps' => 0.95,
            'shared' => 1.1,
            default => 1.0,
        };
        
        // Ajuste por carga del sistema
        $load_factor = $load > 5 ? 1.15 : ($load > 2 ? 1.05 : 1.0);
        
        // Ajuste por memoria disponible
        $memory_limit = function_exists('mlp_convert_to_bytes') 
            ? mlp_convert_to_bytes(ini_get('memory_limit')) 
            : 256 * 1024 * 1024;
        $memory_gb = $memory_limit / (1024 * 1024 * 1024);
        $memory_factor = $memory_gb >= 4 ? 0.95 : ($memory_gb >= 2 ? 1.0 : 1.1);
        
        $factor = round($base_factor * $load_factor * $memory_factor, 2);
        
        $result = [
            'factor' => $factor,
            'base_factor' => $base_factor,
            'load_factor' => $load_factor,
            'memory_factor' => $memory_factor,
            'load_avg' => $load,
            'memory_gb' => round($memory_gb, 1),
            'hosting_type' => $hosting['type'] ?? 'unknown',
            'calibrated_at' => current_time('mysql'),
        ];
        
        set_transient($cache_key, $result, 300); // 5 minutos de caché
        
        return $result;
    }
}

/**
 * Obtener color según la carga del sistema
 */
if (!function_exists('mlp_get_load_avg_color')) {
    function mlp_get_load_avg_color(float $load): string {
        return $load > 10 ? 'red' : ($load > 5 ? 'orange' : 'green');
    }
}

/**
 * Obtener color según el score de seguridad
 *
 * @param int $score Score de seguridad (0-100)
 * @return string Nombre del color
 */
if (!function_exists('mlp_get_security_score_color')) {
    function mlp_get_security_score_color(int $score): string {
        return $score < 70 ? 'red' : ($score < 85 ? 'orange' : 'green');
    }
}

/**
 * Detectar tipo de visitante por User-Agent (NUEVO v12.8.0)
 * Identifica bots de búsqueda, bots de SEO, herramientas de monitorización y humanos
 *
 * @param string $user_agent User-Agent del visitante
 * @return array Información del visitante ['type' => 'bot|human|unknown', 'name' => 'GoogleBot', 'category' => 'search|seo|monitoring|other']
 */
if (!function_exists('mlp_identify_visitor_type')) {
    function mlp_identify_visitor_type(string $user_agent): array {
        if (empty($user_agent)) {
            return ['type' => 'unknown', 'name' => 'Empty/None', 'category' => 'unknown'];
        }

        $ua_lower = strtolower($user_agent);

        // Bots de búsqueda principales
        $search_bots = [
            'googlebot' => 'GoogleBot',
            'google-inspectiontool' => 'Google Inspection Tool',
            'googleother' => 'GoogleOther',
            'google-extended' => 'Google Extended',
            'adsbot-google' => 'AdsBot Google',
            'mediapartners-google' => 'Google MediaPartners',
            'feedfetcher-google' => 'Google FeedFetcher',
            'googleother' => 'GoogleOther',
            'google-stackdriver' => 'Google Stackdriver',
            'google-cloud' => 'Google Cloud',
            'google-fiber' => 'Google Fiber',
            'bingbot' => 'BingBot',
            'msnbot' => 'MSNBot',
            'bingpreview' => 'Bing Preview',
            'bingapp' => 'Bing App',
            'yandexbot' => 'YandexBot',
            'yandex.com/bots' => 'YandexBot',
            'yandexsearch' => 'Yandex Search',
            'baiduspider' => 'BaiduSpider',
            'baidu.com/spider' => 'BaiduSpider',
            'duckduckbot' => 'DuckDuckBot',
            'facebot' => 'FacebookBot',
            'ia_archiver' => 'Alexa Crawler',
            'sogou' => 'Sogou Spider',
            'exabot' => 'Exabot',
            'facebot' => 'Facebook',
            'instagram' => 'Instagram',
            'applebot' => 'AppleBot',
            'twitterbot' => 'TwitterBot',
            'linkedinbot' => 'LinkedInBot',
            'pinterest' => 'Pinterest',
            'slackbot' => 'SlackBot',
            'telegrambot' => 'TelegramBot',
            'discordbot' => 'DiscordBot',
        ];

        // Bots de SEO y análisis
        $seo_bots = [
            'ahrefsbot' => 'AhrefsBot',
            'semrushbot' => 'SEMrush Bot',
            'semrush' => 'SEMrush',
            'moz' => 'Moz',
            'majestic' => 'Majestic',
            'mj12bot' => 'Majestic 12',
            'dotbot' => 'DotBot',
            'rogerbot' => 'Moz Rogerbot',
            'xovibot' => 'XoviBot',
            'screaming frog' => 'Screaming Frog',
            'screamingfrog' => 'Screaming Frog',
            'buzzsumo' => 'BuzzSumo',
            'cognitive' => 'Cognitive SEO',
            'oncrawl' => 'OnCrawl',
            'sistrix' => 'Sistrix',
            'searchmetrics' => 'Searchmetrics',
            'deepcrawl' => 'DeepCrawl',
            'linkresearch' => 'LinkResearch',
            'botify' => 'Botify',
            'conductor' => 'Conductor',
            'brightedge' => 'BrightEdge',
            'seo-spyglass' => 'SEO SpyGlass',
            'wpscan' => 'WPScan',
        ];

        // Herramientas de monitorización y crawling
        $monitoring_bots = [
            'pingdom' => 'Pingdom',
            'gtmetrix' => 'GTmetrix',
            'pagespeed' => 'Google PageSpeed',
            'lighthouse' => 'Google Lighthouse',
            'chrome-lighthouse' => 'Google Lighthouse',
            'uptimerobot' => 'UptimeRobot',
            'uptime' => 'Uptime',
            'newrelic' => 'New Relic',
            'nr-ut' => 'New Relic',
            'datadog' => 'DataDog',
            'statuscake' => 'StatusCake',
            'speedcurve' => 'SpeedCurve',
            'node-pingdom' => 'Pingdom',
            'monitor' => 'Site Monitor',
            'healthcheck' => 'Health Check',
            'monit' => 'Monit',
            'zabbix' => 'Zabbix',
            'nagios' => 'Nagios',
            'prometheus' => 'Prometheus',
            'grafana' => 'Grafana',
            'cloudwatch' => 'AWS CloudWatch',
            'stackdriver' => 'Google Stackdriver',
            'azure-monitor' => 'Azure Monitor',
        ];

        // Scripts y herramientas de desarrollo
        $tools_bots = [
            'curl' => 'cURL',
            'wget' => 'Wget',
            'python-requests' => 'Python Requests',
            'python-urllib' => 'Python urllib',
            'python' => 'Python',
            'httpclient' => 'PHP HTTP Client',
            'java/' => 'Java',
            'libwww-perl' => 'Perl',
            'go-http-client' => 'Go HTTP Client',
            'okhttp' => 'OkHttp',
            'httpie' => 'HTTPie',
            'rest-client' => 'REST Client',
            'axios' => 'Axios',
            'node-fetch' => 'Node Fetch',
            'feedparser' => 'Feed Parser',
            'simplepie' => 'SimplePie',
            'magpie' => 'Magpie',
            'wordpress' => 'WordPress',
            'jetpack' => 'Jetpack',
            'wp-cron' => 'WP-Cron',
            'wp-json' => 'WP REST API',
        ];

        // Verificar bots de búsqueda
        foreach ($search_bots as $pattern => $name) {
            if (str_contains($ua_lower, $pattern)) {
                return ['type' => 'bot', 'name' => $name, 'category' => 'search'];
            }
        }

        // Verificar bots de SEO
        foreach ($seo_bots as $pattern => $name) {
            if (str_contains($ua_lower, $pattern)) {
                return ['type' => 'bot', 'name' => $name, 'category' => 'seo'];
            }
        }

        // Verificar bots de monitorización
        foreach ($monitoring_bots as $pattern => $name) {
            if (str_contains($ua_lower, $pattern)) {
                return ['type' => 'bot', 'name' => $name, 'category' => 'monitoring'];
            }
        }

        // Verificar herramientas/scripts
        foreach ($tools_bots as $pattern => $name) {
            if (str_contains($ua_lower, $pattern)) {
                return ['type' => 'bot', 'name' => $name, 'category' => 'tool'];
            }
        }

        // Verificar WordPress y sistemas CMS
        if (str_contains($ua_lower, 'wordpress')) {
            return ['type' => 'bot', 'name' => 'WordPress', 'category' => 'cms'];
        }

        // Verificar feeds y lectores
        if (str_contains($ua_lower, 'feed') || str_contains($ua_lower, 'rss') || str_contains($ua_lower, 'feedburner')) {
            return ['type' => 'bot', 'name' => 'Feed Reader', 'category' => 'feed'];
        }

        // Verificar si parece ser un humano (contiene navegador común)
        $browsers = ['chrome', 'firefox', 'safari', 'edge', 'opera', 'vivaldi', 'brave', 'yandex', 'duckduckgo', 'vivaldi', 'arc', 'chromium', 'waterfox', 'pale moon', 'maxthon', 'ucbrowser', 'samsungbrowser', 'huawei', 'opera mini', 'blackberry', 'trident'];
        foreach ($browsers as $browser) {
            if (str_contains($ua_lower, $browser)) {
                return ['type' => 'human', 'name' => 'Browser User', 'category' => 'human'];
            }
        }

        // Verificar dispositivos móviles conocida
        $mobile_devices = ['mobile', 'android', 'iphone', 'ipad', 'ipod', 'windows phone', 'blackberry'];
        foreach ($mobile_devices as $device) {
            if (str_contains($ua_lower, $device)) {
                return ['type' => 'human', 'name' => 'Mobile User', 'category' => 'human'];
            }
        }

        // Si no coincide ningún patrón, marcar como unknown
        return ['type' => 'unknown', 'name' => 'Unknown', 'category' => 'unknown'];
    }
}

/**
 * Resumir tipo de visitante para logs (NUEVO v12.8.0)
 *
 * @param string $user_agent User-Agent del visitante
 * @return string Tipo resumido: BOT, HUMAN, UNKNOWN
 */
if (!function_exists('mlp_get_visitor_summary')) {
    function mlp_get_visitor_summary(string $user_agent): string {
        $visitor = mlp_identify_visitor_type($user_agent);
        return strtoupper($visitor['type']);
    }
}

/**
 * Iniciar profiling ligero para identificar plugins lentos (NUEVO v12.8.0)
 * Debe llamarse al inicio del hook 'plugins_loaded'
 *
 * @return float Tiempo de inicio en microsegundos
 */
if (!function_exists('mlp_start_plugin_profiling')) {
    function mlp_start_plugin_profiling(): float {
        return microtime(true);
    }
}

/**
 * Finalizar profiling de plugins y calcular tiempo (NUEVO v12.8.0)
 *
 * @param float $start_time Tiempo devuelto por mlp_start_plugin_profiling()
 * @return array Información del profiling ['time' => float, 'plugins_loaded' => bool]
 */
if (!function_exists('mlp_end_plugin_profiling')) {
    function mlp_end_plugin_profiling(float $start_time): array {
        $end_time = microtime(true);
        return [
            'time' => round(($end_time - $start_time) * 1000, 2), // en milisegundos
            'plugins_loaded' => function_exists('wp_get_active_and_valid_plugins'),
            'timestamp' => $end_time
        ];
    }
}

/**
 * Identificar plugin activo por ruta/URL (NUEVO v12.8.0)
 * Analiza la URL para detectar qué plugin puede estar generando la petición
 *
 * @param string $request_uri URI de la petición
 * @return array Información del plugin detectado ['plugin' => 'name', 'confidence' => 'high|medium|low']
 */
if (!function_exists('mlp_identify_plugin_by_request')) {
    function mlp_identify_plugin_by_request(string $request_uri): array {
        // Patrones comunes de plugins por URL
        $plugin_patterns = [
            'wp-json/wp/v2/users' => ['plugin' => 'REST API', 'confidence' => 'high'],
            'wp-json/' => ['plugin' => 'REST API', 'confidence' => 'high'],
            'admin-ajax.php' => ['plugin' => 'AJAX', 'confidence' => 'high'],
            'wp-admin/admin-ajax.php' => ['plugin' => 'AJAX', 'confidence' => 'high'],
            'wc-api' => ['plugin' => 'WooCommerce', 'confidence' => 'high'],
            'wp-json/woocommerce' => ['plugin' => 'WooCommerce', 'confidence' => 'high'],
            'edd-api' => ['plugin' => 'Easy Digital Downloads', 'confidence' => 'high'],
            'contact-form-7' => ['plugin' => 'Contact Form 7', 'confidence' => 'medium'],
            'wpforms' => ['plugin' => 'WPForms', 'confidence' => 'medium'],
            'elementor' => ['plugin' => 'Elementor', 'confidence' => 'medium'],
            'divi' => ['plugin' => 'Divi', 'confidence' => 'medium'],
            'woocommerce' => ['plugin' => 'WooCommerce', 'confidence' => 'medium'],
            'jetpack' => ['plugin' => 'Jetpack', 'confidence' => 'medium'],
            'wordfence' => ['plugin' => 'Wordfence', 'confidence' => 'medium'],
            'sucuri' => ['plugin' => 'Sucuri', 'confidence' => 'medium'],
            'gravityforms' => ['plugin' => 'Gravity Forms', 'confidence' => 'medium'],
            'polylang' => ['plugin' => 'Polylang', 'confidence' => 'medium'],
            'wpml' => ['plugin' => 'WPML', 'confidence' => 'medium'],
            'yoast' => ['plugin' => 'Yoast SEO', 'confidence' => 'low'],
            'rankmath' => ['plugin' => 'Rank Math', 'confidence' => 'low'],
            'autoptimize' => ['plugin' => 'Autoptimize', 'confidence' => 'low'],
            'wp-rocket' => ['plugin' => 'WP Rocket', 'confidence' => 'low'],
            'litespeed' => ['plugin' => 'LiteSpeed Cache', 'confidence' => 'low'],
        ];

        foreach ($plugin_patterns as $pattern => $info) {
            if (str_contains($request_uri, $pattern)) {
                return $info;
            }
        }

        return ['plugin' => 'Core/Theme', 'confidence' => 'low'];
    }
}

/**
 * Agrupar estadísticas por tipo de visitante (NUEVO v12.8.0)
 * Procesa los logs y agrupa métricas por: humano, bot, unknown
 *
 * @param array $lines Líneas del log
 * @return array Estadísticas agrupadas por tipo de visitante
 */
if (!function_exists('mlp_group_stats_by_visitor')) {
    function mlp_group_stats_by_visitor(array $lines): array {
        $stats = [
            'human' => ['count' => 0, 'total_mem' => 0, 'total_time' => 0, 'total_cpu' => 0, 'total_sql' => 0, 'max_mem' => 0, 'max_time' => 0],
            'bot' => ['count' => 0, 'total_mem' => 0, 'total_time' => 0, 'total_cpu' => 0, 'total_sql' => 0, 'max_mem' => 0, 'max_time' => 0],
            'unknown' => ['count' => 0, 'total_mem' => 0, 'total_time' => 0, 'total_cpu' => 0, 'total_sql' => 0, 'max_mem' => 0, 'max_time' => 0],
            'bot_breakdown' => [], // Desglose por tipo de bot
        ];

        foreach ($lines as $l) {
            if (!str_contains($l, 'DATE:')) continue;

            // Parsear línea
            $row = [];
            foreach (explode(' | ', $l) as $x) {
                $kv = explode(':', $x, 2);
                if (count($kv) == 2) $row[trim($kv[0])] = trim($kv[1]);
            }

            if (empty($row)) continue;

            $visitor = mlp_identify_visitor_type($row['UA'] ?? '');
            $type = $visitor['type'];
            $bot_name = $visitor['name'];

            $mem = (float)($row['MEM'] ?? 0);
            $time = (float)($row['TIME'] ?? 0);
            $cpu = (float)($row['CPU'] ?? 0);
            $sql = (int)($row['SQL'] ?? 0);

            // Actualizar estadísticas por tipo
            if (isset($stats[$type])) {
                $stats[$type]['count']++;
                $stats[$type]['total_mem'] += $mem;
                $stats[$type]['total_time'] += $time;
                $stats[$type]['total_cpu'] += $cpu;
                $stats[$type]['total_sql'] += $sql;
                $stats[$type]['max_mem'] = max($stats[$type]['max_mem'], $mem);
                $stats[$type]['max_time'] = max($stats[$type]['max_time'], $time);
            }

            // Desglose por tipo de bot
            if ($type === 'bot') {
                if (!isset($stats['bot_breakdown'][$bot_name])) {
                    $stats['bot_breakdown'][$bot_name] = ['count' => 0, 'total_time' => 0, 'total_mem' => 0];
                }
                $stats['bot_breakdown'][$bot_name]['count']++;
                $stats['bot_breakdown'][$bot_name]['total_time'] += $time;
                $stats['bot_breakdown'][$bot_name]['total_mem'] += $mem;
            }
        }

        // Calcular promedios
        foreach (['human', 'bot', 'unknown'] as $t) {
            $count = $stats[$t]['count'];
            if ($count > 0) {
                $stats[$t]['avg_mem'] = round($stats[$t]['total_mem'] / $count, 2);
                $stats[$t]['avg_time'] = round($stats[$t]['total_time'] / $count, 3);
                $stats[$t]['avg_cpu'] = round($stats[$t]['total_cpu'] / $count, 1);
                $stats[$t]['avg_sql'] = round($stats[$t]['total_sql'] / $count, 1);
            } else {
                $stats[$t]['avg_mem'] = 0;
                $stats[$t]['avg_time'] = 0;
                $stats[$t]['avg_cpu'] = 0;
                $stats[$t]['avg_sql'] = 0;
            }
        }

        // Ordenar desglose de bots por count
        uasort($stats['bot_breakdown'], function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $stats;
    }
}
