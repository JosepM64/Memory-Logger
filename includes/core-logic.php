<?php
/**
 * Memory Logger Pro v13.2.0 - Core Logic
 * 
 * Funciones principales del plugin: monitoreo, logging, detección de errores y análisis profundo.
 * Incluye lógica avanzada para Hosting, Base de Datos y Patrones de Error.
 * 
 * @package Memory Logger Pro
 * @version 12.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

global $mlp_page_size, $mlp_fatal_error_detected, $mlp_cpu_usage_start, $mlp_cpu_usage_end;
$mlp_page_size = 0;
$mlp_fatal_error_detected = false;
$mlp_cpu_usage_start = microtime(true);
$mlp_cpu_usage_end = 0;

/**
 * Registrar hooks del core
 */
function mlp_register_core_hooks(): void {
    add_action('shutdown', 'mlp_log_request', 10);
    add_action('shutdown', 'mlp_detect_fatal_errors', 20);
}

/**
 * Obtener opciones del plugin
 */
function mlp_get_options(): array {
    static $cached_opts = null;
    if ($cached_opts !== null) return $cached_opts;
    
    $defaults = [
        'version' => MLP_VERSION,
        'threshold_mb' => 200,
        'time_warn' => 2.0,
        'time_risk' => 5.0,
        'cpu_warn' => 50,
        'cpu_risk' => 80,
        'cpu_weight' => 6,
        'debug_mode' => 0,
        'sampling_rate' => 10,
        'cron_sampling' => 10,
        'intelligent_cpu' => 1,
        'enhanced_size' => 1,
        'universal_fallbacks' => 1,
        'track_sql' => 1,
        'track_cpu' => 1,
        'track_http' => 1,
        'track_size' => 1,
    ];
    
    $opts = get_option('memory_logger_options', []);
    $opts = wp_parse_args($opts, $defaults);
    $cached_opts = $opts;
    return $opts;
}

/**
 * Detectar tipo de hosting (MEJORADO v12.8.9)
 * Ahora detecta: Cloud, VPS, Compartido, Dedicado
 */
function mlp_detect_hosting_type(): array {
    static $hosting = null;
    if ($hosting !== null) return $hosting;
    
    $server = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $cpu_count = mlp_get_cpu_count();
    $load = mlp_get_system_cpu_load();
    $memory_limit = mlp_convert_to_bytes(ini_get('memory_limit'));
    $memory_gb = $memory_limit / (1024 * 1024 * 1024);
    
    $server_lower = strtolower($server);
    
    // Detectar proveedor específico primero
    $all_providers = [
        // Cloud providers (AWS, Google, Azure, etc.)
        'cloud' => ['aws', 'amazon', 'google cloud', 'gce', 'azure', 'digitalocean', 'linode', 'vultr', 'heroku', 'upcloud', 'scaleway', 'ovhcloud', 'ovh', 'hetzner', 'aliyun', 'tencent', 'ibm', 'cloudflare', 'netlify', 'vercel', 'render', 'flyio', 'digitalocean', 'bunny', 'backblaze', 'wasabi', 'minio'],
        // VPS
        'vps' => ['docker', 'kvm', 'vmware', 'virtual', 'xen', 'openvz', 'virtualiz', 'lxc', 'proxmox', 'solusvm', 'virtuozzo', 'contabo', 'interserver', 'vpscheap', 'lowendbox', 'virpus', 'bandwagonhost', 'ramnode', 'prgmr', 'digitalocean', 'linode', 'vultr'],
        // Hosting compartido premium (managed WordPress, etc.)
        'shared_premium' => ['siteground', 'wpengine', 'kinsta', 'flywheel', 'pagely', 'pressable', 'cloudways', 'a2hosting', 'fastcomet', 'wpdevops', 'rocketnet', 'gridhosting', 'wordops', 'easyengine', 'mainwp', 'mainwpchild', 'managewp', 'managewpchild'],
        // Hosting compartido
        'shared' => ['hostinger', 'banahosting', 'bluehost', 'godaddy', 'hostgator', 'namecheap', 'inmotion', 'dreamhost', 'webempresa', 'cdmon', 'dinahosting', 'aruba', 'tophost', 'register', 'squarespace', 'wix', 'hawkhost', 'stablehost', 'greengeeks', 'ipage', 'justhost', 'fatcow', 'powweb', 'startlogic', 'ipower', 'globat', 'kimsufi', 'soyoustart', 'runabove', '1and1', 'ionos', 'strato', 'hosteurope', 'all-inkl', 'goneo', 'one', 'servdiscount', 'hetzner', 'profibernet', 'firstfind', 'sered', 'logal', 'axarnet', 'lugus', 'donweb', 'neolo', 'telmex', 'kion', 'infinitum', 'megared']
    ];
    
    $detected_provider = 'Unknown';
    $detected_category = 'dedicated';
    
    foreach ($all_providers as $category => $providers) {
        foreach ($providers as $provider) {
            if (str_contains($server_lower, $provider)) {
                $detected_provider = ucfirst($provider);
                $detected_category = $category;
                break 2;
            }
        }
    }
    
    // Si no se detectó proveedor, determinar tipo por características
    if ($detected_provider === 'Unknown') {
        // Cloud: keywords explícitas
        if (str_contains($server_lower, 'aws') || str_contains($server_lower, 'google') || str_contains($server_lower, 'azure') || str_contains($server_lower, 'digitalocean')) {
            $detected_category = 'cloud';
            $detected_provider = 'Cloud (genérico)';
        }
        // VPS: keywords de virtualización
        elseif (str_contains($server_lower, 'kvm') || str_contains($server_lower, 'vmware') || str_contains($server_lower, 'docker') || str_contains($server_lower, 'lxc')) {
            $detected_category = 'vps';
            $detected_provider = 'VPS (genérico)';
        }
        // Dedicado: sin keywords de cloud/vps/shared, alta memoria = dedicado
        elseif ($memory_gb >= 8 || $cpu_count >= 4) {
            $detected_category = 'dedicated';
            $detected_provider = 'Servidor Dedicado';
        }
        else {
            $detected_category = 'shared';
            $detected_provider = 'Hosting Compartido';
        }
    }
    
    // Calcular ratio base según tipo
    $base_ratio = match ($detected_category) {
        'cloud' => 0.8,
        'vps' => 0.6,
        'shared_premium' => 0.3,
        'shared' => 0.15,
        'dedicated' => 0.9,
        default => 0.25
    };
    
    // Ajustar por memoria disponible (más memoria = mejor provisión)
    if ($memory_gb >= 4) {
        $memory_factor = 1.2;
    } elseif ($memory_gb >= 2) {
        $memory_factor = 1.0;
    } elseif ($memory_gb >= 1) {
        $memory_factor = 0.8;
    } else {
        $memory_factor = 0.6;
    }
    
    // Ajustar por carga actual del sistema (throttling implícito)
    $load_factor = max(0.5, min(1.2, 2.0 / max(0.5, $load)));
    $adjusted_ratio = round($base_ratio * $memory_factor * $load_factor, 2);
    
    // CPUs asignados estimados
    $estimated_cpus = in_array($detected_category, ['shared', 'shared_premium']) 
        ? round($adjusted_ratio * $cpu_count, 2)
        : $cpu_count;
    
    // Categoría booleana
    $is_dedicated = $detected_category === 'dedicated';
    $is_cloud = $detected_category === 'cloud';
    $is_vps = $detected_category === 'vps';
    $is_shared = in_array($detected_category, ['shared', 'shared_premium']);
    
    $hosting = [
        'type' => $detected_category,
        'provider' => $detected_provider,
        'cpu_share_ratio' => $adjusted_ratio,
        'physical_cpus' => $cpu_count,
        'estimated_cpus' => $estimated_cpus,
        'memory_gb' => round($memory_gb, 1),
        'load_avg' => $load,
        'is_shared' => $is_shared,
        'is_shared_hosting' => $is_shared,
        'is_cloud' => $is_cloud,
        'is_vps' => $is_vps,
        'is_dedicated' => $is_dedicated,
        'is_restricted' => !function_exists('shell_exec'),
        'server_software' => $server
    ];
    return $hosting;
}

/**
 * Detectar proveedor de hosting por signature
 */
function mlp_detect_provider(string $server): string {
    $server_lower = strtolower($server);
    
    $providers = [
        'siteground' => 'SiteGround',
        'godaddy' => 'GoDaddy',
        'hostgator' => 'HostGator',
        'bluehost' => 'Bluehost',
        'wpengine' => 'WP Engine',
        'flywheel' => 'Flywheel',
        'kinsta' => 'Kinsta',
        'cloudways' => 'Cloudways',
        'ovh' => 'OVH',
        'kimsufi' => 'KimSufi',
        'soyoustart' => 'SoYouStart',
        'aws' => 'AWS',
        'google cloud' => 'Google Cloud',
        'azure' => 'Azure',
        'digitalocean' => 'DigitalOcean',
        'linode' => 'Linode',
        'vultr' => 'Vultr',
        'apache' => 'Apache',
        'nginx' => 'Nginx',
        'litespeed' => 'LiteSpeed',
        'caddy' => 'Caddy',
    ];
    
    foreach ($providers as $key => $name) {
        if (str_contains($server_lower, $key)) {
            return $name;
        }
    }
    
    // Detectar por software web
    if (str_contains($server_lower, 'apache')) return 'Apache';
    if (str_contains($server_lower, 'nginx')) return 'Nginx';
    if (str_contains($server_lower, 'litespeed')) return 'LiteSpeed';
    if (str_contains($server_lower, 'caddy')) return 'Caddy';
    
    return 'Unknown';
}

/**
 * Detectar throttling en servidores compartidos (NUEVO v12.8.0)
 */
function mlp_detect_throttling(): array {
    $load = mlp_get_system_cpu_load();
    $cpu_count = mlp_get_cpu_count();
    
    // Si load_avg > CPUs disponibles * 0.8, probablemente hay throttling
    $is_throttled = $load > ($cpu_count * 0.8);
    $load_per_cpu = $cpu_count > 0 ? round($load / $cpu_count, 2) : $load;
    
    return [
        'is_throttled' => $is_throttled,
        'throttle_factor' => $is_throttled ? round($load / max(1, $cpu_count), 2) : 1.0,
        'load_avg' => $load,
        'load_per_cpu' => $load_per_cpu,
        'physical_cpus' => $cpu_count,
        'status' => $is_throttled ? '⚠️ Throttling activo' : '✅ Normal',
        'message' => $is_throttled 
            ? "El sistema está bajo carga. Load promedio ($load) supera el 80% de CPUs disponibles."
            : "Carga del sistema dentro de rangos normales."
    ];
}

/**
 * Estimar CPUs asignados en servidores compartidos (NUEVO v12.8.0)
 */
function mlp_estimate_assigned_cpus(): array {
    $cpu_count = mlp_get_cpu_count();
    $load = mlp_get_system_cpu_load();
    $memory_limit = mlp_convert_to_bytes(ini_get('memory_limit'));
    $memory_gb = $memory_limit / (1024 * 1024 * 1024);
    
    // Heurística basada en memoria disponible
    if ($memory_gb >= 4) {
        $cpu_estimate = 0.75;
        $confidence = 'medium';
    } elseif ($memory_gb >= 2) {
        $cpu_estimate = 0.5;
        $confidence = 'medium';
    } elseif ($memory_gb >= 1) {
        $cpu_estimate = 0.35;
        $confidence = 'low';
    } else {
        $cpu_estimate = 0.25;
        $confidence = 'low';
    }
    
    // Ajustar según carga actual
    $load_factor = min(1.5, max(0.5, $load / 2));
    $adjusted_estimate = round($cpu_estimate * $load_factor, 2);
    
    return [
        'physical_cpus' => $cpu_count,
        'estimated_assigned' => $adjusted_estimate,
        'confidence' => $confidence,
        'method' => 'heuristic_memory_based',
        'memory_limit_gb' => round($memory_gb, 1),
        'load_factor' => round($load_factor, 2),
        'note' => 'En servidores compartidos, los CPUs asignados son estimados. El valor real puede variar según el proveedor y la carga del servidor.'
    ];
}

/**
 * Obtener conteo de CPU (MEJORADO v12.8.0)
 */
function mlp_get_cpu_count(): int {
    // Windows
    if (PHP_OS_FAMILY === 'Windows') {
        $cpus = getenv('NUMBER_OF_PROCESSORS');
        return $cpus ? (int)$cpus : 1;
    }
    
    // Linux - múltiples métodos
    if (is_readable('/proc/cpuinfo')) {
        $content = @file_get_contents('/proc/cpuinfo');
        if ($content) {
            $count = substr_count($content, 'processor');
            if ($count > 0) return $count;
        }
    }
    
    // FreeBSD/macOS
    if (function_exists('shell_exec') && is_callable('shell_exec')) {
        $sysctl = @shell_exec('sysctl -n hw.ncpu 2>/dev/null');
        if ($sysctl) {
            $count = (int)trim($sysctl);
            if ($count > 0) return $count;
        }
    }
    
    return 1;
}

/**
 * Obtener carga del sistema (MEJORADO v12.8.0)
 */
function mlp_get_system_cpu_load(): float {
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        return round($load[0] ?? 0.0, 2);
    }
    
    // Fallback para Windows
    if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
        $wmi = new COM("WinMgmts:\\\\.");
        $cpus = $wmi->ExecQuery("SELECT LoadPercentage FROM Win32_Processor");
        foreach ($cpus as $cpu) {
            return (float)$cpu->LoadPercentage;
        }
    }
    
    return 0.0;
}

/**
 * Obtener conteo efectivo de CPU (MEJORADO v12.8.0)
 * Para cualquier hosting compartido, usa CPUs estimados con ratio conservador
 */
function mlp_get_effective_cpu_count(): float {
    $hosting = mlp_detect_hosting_type();
    
    // Para compartidos, usar estimated_cpus que ya tiene el ratio aplicado
    if ($hosting['is_shared']) {
        return max(0.25, $hosting['estimated_cpus']);
    }
    
    // Para cloud/VPS, usar CPUs físicos
    return (float)$hosting['physical_cpus'];
}

/**
 * Sistema inteligente de CPU (MEJORADO v12.8.0)
 * Calcula el porcentaje de CPU usado considerando tipo de hosting y carga del sistema
 */
function mlp_get_intelligent_cpu_usage(): string {
    global $mlp_cpu_usage_start;
    
    // Tiempo de pared transcurrido
    $wall_time = microtime(true) - (defined('WP_START_TIME') ? WP_START_TIME : $mlp_cpu_usage_start);
    if ($wall_time <= 0) return '0.0';
    
    $hosting = mlp_detect_hosting_type();
    $throttling = mlp_detect_throttling();
    $cpu_count = $hosting['physical_cpus'];
    $load = $hosting['load_avg'];
    
    // Factor de throttling
    $throttle_factor = $throttling['is_throttled'] ? $throttling['throttle_factor'] : 1.0;
    
    // Calcular CPU percibido:
    // 1. Tiempo de pared * ratio_asignado = tiempo_cpu_asignado
    // 2. Añadir carga del sistema (load_avg / CPUs_físicos)
    // 3. Aplicar factor de throttling
    $cpu_perceived = ($wall_time * $hosting['cpu_share_ratio'] * 10) + 
                     ($load / max(1, $cpu_count)) * $throttle_factor;
    
    // Normalizar a porcentaje real
    $usage_percent = min(100, max(0.1, $cpu_perceived));
    
    return number_format($usage_percent, 1);
}

/**
 * Obtener salud del sistema (CACHEADA - MEJORADO v12.8.0)
 * Unifica datos con mlp_analyze_database() para evitar duplicación
 * @param bool $force_refresh Si true, ignora el caché y recalcula
 */
function mlp_get_system_health(bool $force_refresh = false): array {
    $cache_key = 'mlp_system_health_v9';
    
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached) return $cached;
    }

    global $wpdb;
    
    // Latencia de BD
    $start = microtime(true);
    $wpdb->query("SELECT 1");
    $db_lat = round((microtime(true) - $start) * 1000, 2);
    
    // Memoria
    $mem_limit = mlp_convert_to_bytes(ini_get('memory_limit'));
    $mem_usage = memory_get_peak_usage(true);
    $mem_percent = ($mem_limit > 0) ? round(($mem_usage / $mem_limit) * 100, 1) : 0;
    
    // Autoload - obtener tamaño real en KB
    $autoload_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE autoload = 'yes'");
    $autoload_kb = round(($autoload_size ?? 0) / 1024, 1);
    $autoload_mb = round(($autoload_size ?? 0) / (1024 * 1024), 2);
    
    // Transients caducados
    $expired_transients = $wpdb->get_var("
        SELECT COUNT(*) FROM $wpdb->options 
        WHERE option_name LIKE '_transient_timeout_%' 
        AND option_value < UNIX_TIMESTAMP()
    ");
    
    // Transients activos
    $active_transients = $wpdb->get_var("
        SELECT COUNT(*) FROM $wpdb->options 
        WHERE option_name LIKE '_transient_%' 
        AND option_name NOT LIKE '_transient_timeout_%'
    ");
    
    // Usar mlp_analyze_database() para datos unificados de BD
    $db_analysis = mlp_analyze_database();
    
    // Color del autoload
    if ($autoload_mb > 1) {
        $autoload_color = '#d63638'; // Rojo > 1MB
    } elseif ($autoload_mb > 0.5) {
        $autoload_color = '#f0b849'; // Naranja 0.5-1MB
    } else {
        $autoload_color = '#00a32a'; // Verde < 0.5MB
    }
    
    // Color de latencia DB
    $db_lat_color = $db_lat > 50 ? '#d63638' : ($db_lat > 20 ? '#f0b849' : '#00a32a');
    
    // Color del health score
    $health_score_color = $db_analysis['health_score'] >= 80 ? '#00a32a' : ($db_analysis['health_score'] >= 60 ? '#f0b849' : '#d63638');
    
    $health = [
        'wp_ver' => get_bloginfo('version'),
        'php_ver' => phpversion(),
        'memory_used' => round($mem_usage / 1048576, 2),
        'mem_limit' => ini_get('memory_limit'),
        'mem_percent' => $mem_percent,
        'db_lat' => $db_lat,
        'db_lat_color' => $db_lat_color,
        'db_size_mb' => round(($db_analysis['total_size_bytes'] ?? 0) / (1024 * 1024), 1),
        'table_count' => $db_analysis['tables_count'] ?? 0,
        'autoload_kb' => $autoload_kb,
        'autoload_mb' => $autoload_mb,
        'autoload_count' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->options WHERE autoload = 'yes'"),
        'autoload_color' => $autoload_color,
        'transients_active' => (int)$active_transients,
        'transients_expired' => (int)$expired_transients,
        'transients_overhead_kb' => round((($db_analysis['overhead_size_bytes'] ?? 0)) / 1024, 1),
        'theme' => wp_get_theme()->get('Name'),
        'plugins_count' => count((array)get_option('active_plugins', [])),
        'oc_stat' => (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) ? 'Activo' : 'Inactivo',
        'oc_lat' => 0,
        'oc_class' => (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) ? 'green' : 'red',
        'opcache_hit' => function_exists('mlp_safe_opcache_get_hit') ? mlp_safe_opcache_get_hit() : 0,
        'load_avg' => mlp_get_system_cpu_load(),
        // Datos unificados de mlp_analyze_database()
        'db_health_score' => $db_analysis['health_score'] ?? 0,
        'db_health_score_color' => $health_score_color,
        'db_fragmentation' => $db_analysis['fragmentation_rate'] ?? 0,
        'db_issues' => $db_analysis['issues'] ?? [],
        'db_total_size_formatted' => $db_analysis['total_size'] ?? '0 B',
        'db_overhead_formatted' => $db_analysis['overhead_size'] ?? '0 B',
        'db_autoload_formatted' => $db_analysis['autoload_size'] ?? '0 B',
        'db_transients_formatted' => $db_analysis['transients_size'] ?? '0 B',
        'db_large_tables' => $db_analysis['large_tables'] ?? []
    ];
    set_transient($cache_key, $health, 60); // Cache corto de 1 minuto
    return $health;
}

/**
 * Loggear performance
 */
function mlp_log_request(): void {
    $opts = mlp_get_options();
    
    // Sampling logic: solo loggear 1 de cada X peticiones, excepto si hay debug o error
    if (rand(1, (int)($opts['sampling_rate']??10)) != 1 && empty($opts['debug_mode'])) return;
    
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $visitor_type = mlp_get_visitor_summary($user_agent);
    
    $data = sprintf(
        "DATE:%s | TYPE:%s | URL:%s | MEM:%s | TIME:%s | SQL:%s | CPU:%s | HTTP:%s | UA:%s | VISITOR:%s | SIZE_METHOD:%s | SIZE:%s\n",
        current_time('mysql'),
        is_admin() ? 'Backend' : 'Frontend',
        $_SERVER['REQUEST_URI'] ?? '',
        round(memory_get_peak_usage(true) / 1048576, 2),
        round(microtime(true) - (defined('WP_START_TIME') ? WP_START_TIME : microtime(true)), 3),
        function_exists('get_num_queries') ? get_num_queries() : 0,
        mlp_get_intelligent_cpu_usage(),
        http_response_code(),
        $user_agent,
        $visitor_type,
        'output_buffer',
        round((ob_get_length() ?: 0) / 1024, 2)
    );
    @file_put_contents(MLP_LOG_FILE, $data, FILE_APPEND | LOCK_EX);
}

/**
 * Obtener métricas actuales de la petición
 */
function mlp_get_current_metrics(): array {
    $memory = round(memory_get_peak_usage(true) / 1048576, 2);
    $time = round(microtime(true) - (defined('WP_START_TIME') ? WP_START_TIME : microtime(true)), 3);
    $cpu = mlp_get_intelligent_cpu_usage();
    $size = round((ob_get_length() ?: 0) / 1024, 2);
    $sql = function_exists('get_num_queries') ? get_num_queries() : 0;
    $http = http_response_code();
    
    // Memory percentage based on limit
    $mem_limit = mlp_convert_to_bytes(ini_get('memory_limit'));
    $mem_usage = memory_get_peak_usage(true);
    $memory_percent = ($mem_limit > 0) ? round(($mem_usage / $mem_limit) * 100, 1) : 0;
    
    // Get max values from advanced stats (cached)
    $stats = mlp_get_advanced_stats();
    $max_memory = $stats['max_memory'] ?? 0;
    $max_time = $stats['max_time'] ?? 0;
    $max_cpu = $stats['max_cpu'] ?? 0;
    $max_size = $stats['max_size'] ?? 0;
    
    return [
        'date' => current_time('mysql'),
        'memory' => $memory,
        'memory_percent' => $memory_percent,
        'time' => $time,
        'cpu' => $cpu,
        'size' => $size,
        'sql' => $sql,
        'http' => $http,
        'size_method' => 'output_buffer',
        'max_memory' => $max_memory,
        'max_time' => $max_time,
        'max_cpu' => $max_cpu,
        'max_size' => $max_size
    ];
}

/**
 * Estadísticas avanzadas (Lectura de logs)
 */
function mlp_get_advanced_stats(): array {
    $cache_key = 'mlp_advanced_stats_v7';
    $cached = get_transient($cache_key);
    if ($cached) return $cached;

    $lines = file_exists(MLP_LOG_FILE) ? file(MLP_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    // Analizar últimas 2000 líneas para tener buena muestra
    $lines = array_slice($lines, -2000);
    
    $stats = [
        'avg_memory' => 0, 'max_memory' => 0, 'avg_time' => 0, 'max_time' => 0,
        'avg_cpu' => 0, 'max_cpu' => 0, 'avg_sql' => 0, 'max_sql' => 0, 'avg_size' => 0, 'max_size' => 0,
        'labels' => [], 'memory_data' => [], 'sql_data' => [], 'time_data' => [], 'cpu_data' => [], 'size_data' => [],
        'memory_distribution' => ['<50'=>['count'=>0],'50-100'=>['count'=>0],'100-200'=>['count'=>0],'200+'=>['count'=>0]],
        'size_methods' => [], 
        'dangerous_peaks' => [],
        'visitor_stats' => [] // Estadísticas de visitantes (bots vs humanos)
    ];

    $total = count($lines);
    if ($total > 0) {
        $sum_mem = $sum_time = $sum_cpu = $sum_sql = $sum_size = 0;
        
        // Optimización: procesar en bloque
        foreach ($lines as $l) {
            $row = mlp_parse_log_line($l);
            if (empty($row)) continue;

            $m = (float)($row['mem']??0);
            $t = (float)($row['time']??0);
            $c = (float)($row['cpu']??0);
            $s = (int)($row['sql']??0);
            $sz = (float)($row['size']??0);
            $method = $row['size_method'] ?? 'unknown';

            $sum_mem += $m; $sum_time += $t; $sum_cpu += $c; $sum_sql += $s; $sum_size += $sz;
            
            $stats['max_memory'] = max($stats['max_memory'], $m);
            $stats['max_time'] = max($stats['max_time'], $t);
            $stats['max_cpu'] = max($stats['max_cpu'], $c);
            $stats['max_sql'] = max($stats['max_sql'], $s);
            $stats['max_size'] = max($stats['max_size'], $sz);

            // Solo guardar datos para gráficos de los últimos 200 puntos para no saturar JSON
            if ($total <= 200 || rand(1, 10) == 1) { // Sampling para gráficos si hay muchos datos
                $stats['labels'][] = substr($row['DATE']??'', 11, 8); // Solo hora
                $stats['memory_data'][] = $m;
                $stats['sql_data'][] = $s;
                $stats['time_data'][] = $t;
                $stats['cpu_data'][] = $c;
                $stats['size_data'][] = $sz;
            }

            // Distribución
            if ($m < 50) $stats['memory_distribution']['<50']['count']++;
            elseif ($m < 100) $stats['memory_distribution']['50-100']['count']++;
            elseif ($m < 200) $stats['memory_distribution']['100-200']['count']++;
            else $stats['memory_distribution']['200+']['count']++;

            // Métodos
            if (!isset($stats['size_methods'][$method])) {
                $stats['size_methods'][$method] = ['count' => 0, 'percentage' => 0];
            }
            $stats['size_methods'][$method]['count']++;

            // Picos peligrosos (Umbrales ajustables)
            if ($m > 200 || $t > 5 || $c > 80) {
                $stats['dangerous_peaks'][] = [
                    'date' => $row['date']??'',
                    'type' => $row['type']??'',
                    'memory' => $m,
                    'time' => $t,
                    'cpu' => $c,
                    'url' => $row['url']??'',
                    'risk_level' => ($m > 256 || $t > 10) ? 'high' : 'medium'
                ];
            }
        }
        
        $stats['avg_memory'] = $sum_mem / $total;
        $stats['avg_time'] = $sum_time / $total;
        $stats['avg_cpu'] = $sum_cpu / $total;
        $stats['avg_sql'] = $sum_sql / $total;
        $stats['avg_size'] = $sum_size / $total;

        foreach ($stats['memory_distribution'] as $k => &$v) {
            $v['percentage'] = ($v['count'] / $total) * 100;
        }
        foreach ($stats['size_methods'] as $k => &$v) {
            $v['percentage'] = ($v['count'] / $total) * 100;
        }
        
        // Ordenar picos por severidad (memoria desc) y limitar
        usort($stats['dangerous_peaks'], function($a, $b) {
            return $b['memory'] <=> $a['memory'];
        });
        $stats['dangerous_peaks'] = array_slice($stats['dangerous_peaks'], 0, 50);
        
        // Estadísticas de visitantes (bots vs humanos)
        $stats['visitor_stats'] = mlp_group_stats_by_visitor($lines);
    }
    
    set_transient($cache_key, $stats, 300);
    return $stats;
}

/* =========================================================================
   FUNCIONES DE ANÁLISIS PROFUNDO (RESTAURADAS)
   ========================================================================= */

/**
 * Patrones de error (Análisis Real de Logs Mejorado)
 * CON CACHÉ y filtro de fecha (últimas 24h)
 */
function mlp_analyze_error_patterns(): array {
    $cache_key = 'mlp_error_patterns_analysis_' . md5(MLP_PATH);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    $patterns = [
        'total_errors' => 0,
        'patterns' => [],
        'severity_counts' => ['critical' => 0, 'warning' => 0, 'notice' => 0],
        'last_error_date' => 'N/A',
        'error_timeline' => [],
        'recurrent_errors' => [],
        'error_sources' => []
    ];

    // Buscar múltiples posibles ubicaciones de logs
    $log_files = [
        defined('MLP_ERROR_LOG_FILE') ? MLP_ERROR_LOG_FILE : '',
        ini_get('error_log'),
        ABSPATH . 'error_log',
        defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/debug.log' : ''
    ];

    $lines = [];
    foreach ($log_files as $log_file) {
        if ($log_file && file_exists($log_file) && is_readable($log_file)) {
            $new_lines = mlp_tail_file($log_file, 500);
            $lines = array_merge($lines, $new_lines);
            if (count($lines) >= 500) {
                $lines = array_slice($lines, 0, 500);
                break;
            }
        }
    }
    
    // Filtro: solo errores de las últimas 24 horas
    $twenty_four_hours_ago = time() - 86400;
    $filtered_lines = [];
    
    foreach ($lines as $line) {
        if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})/', $line, $time_match)) {
            $log_time = strtotime($time_match[1]);
            if ($log_time && $log_time >= $twenty_four_hours_ago) {
                $filtered_lines[] = $line;
            }
        } else {
            // Si no tiene fecha, incluirlo (puede ser actual)
            $filtered_lines[] = $line;
        }
    }
    $lines = $filtered_lines;

    $patterns['total_errors'] = count($lines);
    
    // Cache para errores recurrentes (últimos 7 días)
    $recent_timestamp = time() - (7 * 24 * 60 * 60);
    
    foreach ($lines as $line) {
        // Extraer fecha del error
        $date_match = [];
        if (preg_match('/^\[(.*?)\]/', $line, $date_match)) {
            $error_date = $date_match[1];
            $patterns['last_error_date'] = $error_date;
            
            // Agrupar por día para timeline
            $day_ts = strtotime($error_date);
            if ($day_ts) {
                $day = date('Y-m-d', $day_ts);
                if (!isset($patterns['error_timeline'][$day])) {
                    $patterns['error_timeline'][$day] = 0;
                }
                $patterns['error_timeline'][$day]++;
                
                // Marcar como recurrente si es reciente
                if ($day_ts > $recent_timestamp) {
                    $error_key = md5(substr($line, 0, 200)); // Hash de la primera parte del error
                    if (!isset($patterns['recurrent_errors'][$error_key])) {
                        $patterns['recurrent_errors'][$error_key] = [
                            'count' => 0,
                            'first_seen' => $error_date,
                            'last_seen' => $error_date,
                            'sample' => substr($line, 0, 300)
                        ];
                    }
                    $patterns['recurrent_errors'][$error_key]['count']++;
                    $patterns['recurrent_errors'][$error_key]['last_seen'] = $error_date;
                }
            }
        }

        // Detectar tipo de error
        $line_lower = strtolower($line);
        if (str_contains($line_lower, 'fatal') || str_contains($line_lower, 'parse')) {
            $patterns['severity_counts']['critical']++;
        } elseif (str_contains($line_lower, 'warning')) {
            $patterns['severity_counts']['warning']++;
        } elseif (str_contains($line_lower, 'notice') || str_contains($line_lower, 'deprecated')) {
            $patterns['severity_counts']['notice']++;
        }

        // Detectar fuente específica
        if (preg_match('/wp-content\/plugins\/([^\/]+)\//', $line, $matches)) {
            $plugin = $matches[1];
            if (!isset($patterns['error_sources'][$plugin])) {
                $patterns['error_sources'][$plugin] = 0;
            }
            $patterns['error_sources'][$plugin]++;
            
            // También contar en patterns general
            $patterns['patterns'][$plugin] = ($patterns['patterns'][$plugin] ?? 0) + 1;
        } elseif (preg_match('/wp-content\/themes\/([^\/]+)\//', $line, $matches)) {
            $theme = $matches[1];
            if (!isset($patterns['error_sources'][$theme])) {
                $patterns['error_sources'][$theme] = 0;
            }
            $patterns['error_sources'][$theme]++;
            $patterns['patterns'][$theme] = ($patterns['patterns'][$theme] ?? 0) + 1;
        } elseif (preg_match('/PHP (\d+\.\d+\.\d+)/', $line, $matches)) {
            // Errores de PHP core
            $patterns['patterns']['PHP Core'] = ($patterns['patterns']['PHP Core'] ?? 0) + 1;
        }
    }

    // Ordenar recurrentes por frecuencia
    usort($patterns['recurrent_errors'], function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    
    // Limitar a top 10
    $patterns['recurrent_errors'] = array_slice($patterns['recurrent_errors'], 0, 10);
    
    // Ordenar sources por frecuencia
    arsort($patterns['error_sources']);
    arsort($patterns['patterns']);
    
    set_transient($cache_key, $patterns, HOUR_IN_SECONDS);
    return $patterns;
}

/**
 * Integridad core (Verificación de Permisos y Archivos Críticos)
 * CON CACHÉ para evitar análisis repetitivos
 */
function mlp_check_file_integrity(): array {
    $cache_key = 'mlp_file_integrity_check_' . md5(MLP_PATH);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    $issues = [];
    
    // 1. Verificar permisos de escritura en directorios clave
    $paths = [
        WP_CONTENT_DIR => 'wp-content',
        WP_PLUGIN_DIR => 'plugins',
        get_theme_root() => 'themes',
        WP_CONTENT_DIR . '/uploads' => 'uploads'
    ];

    foreach ($paths as $path => $name) {
        if (!is_writable($path)) {
            $issues[] = "Permisos: El directorio '$name' no tiene permisos de escritura.";
        }
    }

    // 2. Verificar existencia de archivos críticos
    $critical_files = [
        ABSPATH . 'wp-config.php',
        ABSPATH . '.htaccess',
        ABSPATH . 'wp-settings.php'
    ];

    foreach ($critical_files as $file) {
        if (!file_exists($file)) {
            $issues[] = "Faltante: El archivo crítico '" . basename($file) . "' no se encuentra.";
        }
    }

    // 3. Verificar debug.log expuesto
    if (file_exists(WP_CONTENT_DIR . '/debug.log')) {
        $issues[] = "Seguridad: 'debug.log' existe en wp-content. Asegúrate de bloquear su acceso público.";
    }

    $result = ['issues' => $issues];
    set_transient($cache_key, $result, HOUR_IN_SECONDS);
    return $result;
}

/**
 * Recomendaciones hosting (Análisis Real de Configuración Mejorado)
 * CON CACHE para evitar análisis repetitivos
 */
function mlp_get_hosting_recommendations(): array {
    $cache_key = 'mlp_hosting_recommendations_' . md5(MLP_PATH . PHP_VERSION);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    $recs = [];

    // 1. Versión PHP
if (version_compare(PHP_VERSION, '8.2', '<')) {
            return [
                'description' => 'Estás usando PHP ' . PHP_VERSION . '. WordPress recomienda PHP 8.2+ para mejor rendimiento y seguridad.',
            'action' => 'Actualizar PHP en panel de hosting'
        ];
    }

    // 2. Límite de Memoria
    $memory_limit = mlp_convert_to_bytes(ini_get('memory_limit'));
    if ($memory_limit < 268435456) { // 256MB
        $recs[] = [
            'title' => 'Memoria PHP Baja',
            'description' => 'Tu límite es ' . ini_get('memory_limit') . '. Se recomienda al menos 256M para sitios dinámicos.',
            'action' => 'Aumentar memory_limit a 256M'
        ];
    }

    // 3. Max Execution Time
    $max_exec = (int)ini_get('max_execution_time');
    if ($max_exec > 0 && $max_exec < 60) {
        $recs[] = [
            'title' => 'Tiempo de Ejecución Corto',
            'description' => 'Tu límite es ' . $max_exec . 's. Procesos largos (backups, imports) pueden fallar.',
            'action' => 'Aumentar max_execution_time a 60s o 120s'
        ];
    }

    // 4. Extensiones Críticas
    $extensions = ['curl', 'dom', 'exif', 'fileinfo', 'hash', 'imagick', 'intl', 'mbstring', 'openssl', 'pcre', 'xml', 'zip'];
    $missing_ext = [];
    foreach ($extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_ext[] = $ext;
        }
    }
    if (!empty($missing_ext)) {
        $recs[] = [
            'title' => 'Extensiones PHP Faltantes',
            'description' => 'Faltan extensiones recomendadas: ' . implode(', ', $missing_ext),
            'action' => 'Instalar/Activar extensiones en cPanel/Servidor'
        ];
    }

    // 5. OPCache
    $opcache_available = false;
    if (function_exists('opcache_get_status')) {
        try {
            $opcache_available = @opcache_get_status(false) !== false;
        } catch (Throwable $e) {
            $opcache_available = false;
        }
    }
    if (!$opcache_available) {
        $recs[] = [
            'title' => 'OPCache Desactivado o Restringido',
            'description' => 'OPCache mejora drásticamente el rendimiento de PHP al almacenar código precompilado.',
            'action' => 'Activar opcache en php.ini'
        ];
    }

    // 7. Verificar HTTPS
    if (!is_ssl()) {
        $recs[] = [
            'title' => 'HTTPS No Activo',
            'description' => 'Tu sitio no está usando HTTPS. Esencial para seguridad y SEO.',
            'action' => 'Instalar certificado SSL en el hosting'
        ];
    }

    // 8. Verificar headers de seguridad
    $headers = @get_headers(home_url(), 1);
    if ($headers && !isset($headers['Strict-Transport-Security'])) {
        $recs[] = [
            'title' => 'Falta HSTS Header',
            'description' => 'El header Strict-Transport-Security mejora la seguridad SSL.',
            'action' => 'Configurar HSTS en .htaccess o servidor'
        ];
    }

    // 9. Verificar PHP extensions de performance
    $performance_ext = ['opcache', 'apcu', 'memcached', 'redis'];
    $missing_perf_ext = [];
    foreach ($performance_ext as $ext) {
        if (!extension_loaded($ext)) {
            $missing_perf_ext[] = $ext;
        }
    }
    if (!empty($missing_perf_ext)) {
        $recs[] = [
            'title' => 'Extensiones de Rendimiento Faltantes',
            'description' => 'Faltan: ' . implode(', ', $missing_perf_ext) . ' - Mejorarían la velocidad.',
            'action' => 'Instalar extensiones de caché'
        ];
    }

    // 10. Verificar límites de upload
    $upload_max = ini_get('upload_max_filesize');
    $post_max = ini_get('post_max_size');
    if (mlp_convert_to_bytes($upload_max) < 64 * 1024 * 1024) {
        $recs[] = [
            'title' => 'Límite de Subida Bajo',
            'description' => 'upload_max_filesize = ' . $upload_max . ' (recomendado al menos 64M)',
            'action' => 'Aumentar upload_max_filesize en php.ini'
        ];
    }
    
    set_transient($cache_key, $recs, HOUR_IN_SECONDS);
    return $recs;
}

/**
 * Análisis de base de datos (Análisis Real de Tablas y Autoload Mejorado)
 * CON CACHÉ para evitar queries repetitivas
 */
function mlp_analyze_database(): array {
    $cache_key = 'mlp_database_analysis_' . md5(MLP_PATH);
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    global $wpdb;
    $issues = [];
    $health_score = 100;

    // 1. Tamaño total y overhead
    $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
    $total_size = 0;
    $total_overhead = 0;
    $large_tables = [];

    foreach ($tables as $table) {
        $size = ($table['Data_length'] + $table['Index_length']);
        $overhead = $table['Data_free'];
        $total_size += $size;
        $total_overhead += $overhead;

        if ($size > 100 * 1024 * 1024) { // > 100MB
            $large_tables[] = $table['Name'] . ' (' . size_format($size) . ')';
            $health_score -= 5;
        }
        
        if ($overhead > 0) {
            $health_score -= 1; // Penalización leve por overhead
        }
    }

    if (!empty($large_tables)) {
        $issues[] = ['description' => 'Tablas muy grandes detectadas: ' . implode(', ', array_slice($large_tables, 0, 3))];
    }

    if ($total_overhead > 10 * 1024 * 1024) { // > 10MB overhead
        $issues[] = ['description' => 'Overhead total: ' . size_format($total_overhead) . '. Se recomienda optimizar tablas.'];
        $health_score -= 10;
    }

    // 2. Análisis de Autoload (Crítico)
    $autoload_size = (int)$wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE autoload = 'yes'");
    if ($autoload_size > 800 * 1024) { // > 800KB
        $issues[] = ['description' => 'Datos Autoload excesivos: ' . size_format($autoload_size) . '. (Recomendado < 800KB). Esto ralentiza cada carga.'];
        $health_score -= 20;
    }

    // 3. Transitorios caducados
    $expired_transients = (int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_%' AND option_value < " . time());
    if ($expired_transients > 100) {
        $issues[] = ['description' => "$expired_transients transitorios caducados. Limpia la DB para mejorar rendimiento."];
        $health_score -= 5;
    }

    // 4. Índice de fragmentación aproximado
    $fragmentation_rate = $total_size > 0 ? round(($total_overhead / $total_size) * 100, 1) : 0;
    if ($fragmentation_rate > 10) {
        $issues[] = ['description' => "Fragmentación: {$fragmentation_rate}%. Recomendado optimizar tablas."];
        $health_score -= 15;
    }

    // 5. Tablas sin índices primarios (aproximado)
    // Para InnoDB, todas deben tener PK, pero no podemos verificarlo fácilmente sin SHOW CREATE TABLE
    
    // 6. Tamaño de transitorios
    $transients_size = (int)$wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE option_name LIKE '_transient_%'");
    if ($transients_size > 5 * 1024 * 1024) { // > 5MB
        $issues[] = ['description' => 'Transitorios: ' . size_format($transients_size) . ' (puede limpiarse)'];
    }

    // 7. Crecimiento diario estimado (si hay datos históricos)
    $cache_key_growth = 'mlp_db_growth_estimate';
    $growth_data = get_transient($cache_key_growth);
    if (!$growth_data) {
        $old_size = get_option('mlp_db_size_last_check', 0);
        if ($old_size > 0 && $old_size < $total_size) {
            $growth_per_day = round(($total_size - $old_size) / 30, 2); // Asumiendo 30 días
            if ($growth_per_day > 10 * 1024 * 1024) { // > 10MB por día
                $issues[] = ['description' => 'Crecimiento rápido: ' . size_format($growth_per_day) . '/día'];
            }
        }
        update_option('mlp_db_size_last_check', $total_size, false);
        set_transient($cache_key_growth, ['size' => $total_size, 'date' => time()], DAY_IN_SECONDS);
    }

    $result = [
        'health_score' => max(0, $health_score),
        'issues' => $issues,
        'tables_count' => count($tables),
        'total_size' => size_format($total_size),
        'total_size_bytes' => $total_size,
        'overhead_size' => size_format($total_overhead),
        'overhead_size_bytes' => $total_overhead,
        'autoload_size' => size_format($autoload_size),
        'autoload_size_bytes' => $autoload_size,
        'fragmentation_rate' => $fragmentation_rate,
        'transients_size' => size_format($transients_size),
        'transients_size_bytes' => $transients_size,
        'expired_transients' => $expired_transients,
        'large_tables' => $large_tables
    ];
    
    set_transient($cache_key, $result, HOUR_IN_SECONDS);
    return $result;
}

/**
 * Detectar carga para throttling
 */
function mlp_get_load_status(): array {
    $load = mlp_get_system_cpu_load();
    $mem_limit = mlp_convert_to_bytes(ini_get('memory_limit'));
    $mem_usage = memory_get_peak_usage(true);
    $mem_percent = ($mem_limit > 0) ? round(($mem_usage / $mem_limit) * 100, 1) : 0;
    return ['load_avg' => $load, 'mem_percent' => $mem_percent, 'should_throttle' => $load > 10, 'is_critical' => $load > 20, 'is_high_load' => $load > 10, 'throttle_factor' => 1.0];
}

/**
 * Detección mejorada de errores (IMPLEMENTADA v12.8.0)
 * Analiza múltiples fuentes de logs de errores
 * 
 * @return array Errores detectados con severidad
 */
function mlp_enhanced_error_detection(): array {
    $errors = [];
    $log_files = [
        defined('MLP_ERROR_LOG_FILE') ? MLP_ERROR_LOG_FILE : '',
        ini_get('error_log'),
        ABSPATH . 'error_log',
        defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/debug.log' : ''
    ];

    $two_hours_ago = time() - 7200;

    foreach ($log_files as $log_file) {
        if (!$log_file || !file_exists($log_file) || !is_readable($log_file)) {
            continue;
        }

        $lines = mlp_tail_file($log_file, 200);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Ignorar errores antiguos (más de 2 horas)
            if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})/', $line, $time_match)) {
                $log_time = strtotime($time_match[1]);
                if ($log_time && $log_time < $two_hours_ago) {
                    continue;
                }
            }
            
            // Ignorar errores del propio plugin memory-logger-pro
            if (str_contains($line, 'memory-logger-pro')) {
                continue;
            }
            
            $severity = 'low';
            $line_lower = strtolower($line);
            
            if (str_contains($line_lower, 'fatal') || str_contains($line_lower, 'parse error') || str_contains($line_lower, 'core error')) {
                $severity = 'critical';
            } elseif (str_contains($line_lower, 'warning') || str_contains($line_lower, 'exception')) {
                $severity = 'high';
            } elseif (str_contains($line_lower, 'notice') || str_contains($line_lower, 'deprecated')) {
                $severity = 'medium';
            }
            
            // Extraer información del error
            $message = trim($line);
            if (preg_match('/PHP (fatal|warning|notice|error):(.+)/i', $line, $matches)) {
                $message = $matches[2] ?? $message;
            }
            
            $errors[] = [
                'severity' => $severity,
                'message' => substr($message, 0, 200),
                'source' => basename($log_file),
                'timestamp' => time(),
            ];
        }
    }

    // Ordenar por severidad
    usort($errors, function($a, $b) {
        $severity_order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        return ($severity_order[$a['severity']] ?? 3) <=> ($severity_order[$b['severity']] ?? 3);
    });

    return array_slice($errors, 0, 50); // Limitar a 50 errores
}

/**
 * Ejecutar escaneo de seguridad programado (IMPLEMENTADA v12.8.0)
 */
function mlp_execute_cron_security_scan(): void {
    if (!function_exists('mlp_run_quick_security_scan_optimized')) {
        return;
    }
    
    $cache_key = 'mlp_cron_security_scan_' . md5(MLP_PATH);
    $result = mlp_run_quick_security_scan_optimized();
    
    // Guardar resultado
    set_transient($cache_key, $result, DAY_IN_SECONDS);
    
    // Notificar si hay problemas críticos
    if (!empty($result['critical_issues'])) {
        $admin_email = get_option('admin_email');
        $subject = '[Memory Logger Pro] Alerta de Seguridad - ' . get_bloginfo('name');
        $message = "Se han detectado " . count($result['critical_issues']) . " problemas de seguridad críticos.\n\n";
        $message .= "Score de seguridad: " . ($result['score'] ?? 0) . "/100\n\n";
        $message .= "Visita el panel de diagnóstico para más detalles.";
        
        wp_mail($admin_email, $subject, $message);
    }
}

/**
 * Ejecutar escaneo de errores programado (IMPLEMENTADA v12.8.0)
 */
function mlp_execute_cron_error_scan(): void {
    $errors = mlp_enhanced_error_detection();
    $critical_count = count(array_filter($errors, fn($e) => $e['severity'] === 'critical'));
    
    set_transient('mlp_cached_errors_count', count($errors), HOUR_IN_SECONDS);
     set_transient('mlp_cached_errors_list', $errors, HOUR_IN_SECONDS);
    
    if ($critical_count > 5) {
        $cache_key = 'mlp_cron_error_alert_' . date('Y-m-d');
        $already_sent = get_transient($cache_key);
        
        if (!$already_sent) {
            $admin_email = get_option('admin_email');
            $subject = '[Memory Logger Pro] Alerta de Errores Críticos - ' . get_bloginfo('name');
            $message = "Se han detectado $critical_count errores críticos en las últimas horas.\n\n";
            $message .= "Visita el panel de diagnóstico para analizar los errores.";
            
            wp_mail($admin_email, $subject, $message);
            set_transient($cache_key, true, 12 * HOUR_IN_SECONDS);
        }
    }
}
function mlp_detect_fatal_errors(): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log fatal error
        $log_entry = sprintf(
            "DATE:%s | TYPE:FATAL | MSG:%s | FILE:%s:%s\n",
            current_time('mysql'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        @file_put_contents(MLP_LOG_FILE, $log_entry, FILE_APPEND);
    }
}
/**
 * Registrar errores de plugins específicos (IMPLEMENTADA v12.8.0)
 * Hook para capturar errores de plugins durante la ejecución
 */
function mlp_log_plugin_errors(): void {
    $error = error_get_last();
    
    if (!$error || !in_array($error['type'], [E_ERROR, E_WARNING, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    
    $message = sprintf(
        "[%s] %s en %s:%d",
        date('Y-m-d H:i:s'),
        $error['message'],
        $error['file'],
        $error['line']
    );
    
    // Detectar si es de un plugin
    if (str_contains($error['file'], 'wp-content/plugins')) {
        preg_match('/wp-content\/plugins\/([^\/]+)/', $error['file'], $matches);
        $plugin = $matches[1] ?? 'unknown';
        $message = "[PLUGIN:{$plugin}] " . $message;
    }
    
    @file_put_contents(MLP_LOG_FILE, $message . "\n", FILE_APPEND);
}

/**
 * Función auxiliar para leer últimas líneas de archivo eficientemente
 */
function mlp_tail_file($filepath, $lines = 100) {
    if (!$filepath || !is_readable($filepath)) return [];
    
    $f = @fopen($filepath, "rb");
    if ($f === false) return [];

    fseek($f, -1, SEEK_END);
    if (ftell($f) <= 0) { fclose($f); return []; }

    $output = '';
    $chunkSize = 4096;
    $linesRead = 0;
    
    while (ftell($f) > 0 && $linesRead <= $lines) {
        $seek = min(ftell($f), $chunkSize);
        fseek($f, -$seek, SEEK_CUR);
        $chunk = fread($f, $seek);
        $output = $chunk . $output;
        fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
        $linesRead += substr_count($chunk, "\n");
    }

    fclose($f);
    
    $lines_arr = explode("\n", $output);
    return array_slice($lines_arr, -$lines);
}
?>