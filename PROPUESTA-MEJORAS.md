# Memory Logger Pro — Llista de tasques de millora

> Document intern amb les millores detectades a l'anàlisi del codi (v13.3.0).
> Criteris que s'han de mantenir sempre: **simplicitat, utilitat, SoC (Separació de Concerns)** i **no carregar el servidor**.
> Marca amb `[x]` cada tasca quan estigui feta.

---

## Fase 1 — Dades reals i càrrega de servidor (ràpida, alt impacte, ~30 min)

### Dades hardcoded que es mostren com a reals (P0)

- [x] **`core-logic.php:1098`** — Treure `mem_percent => 27.9` i `throttle_factor => 0.25` fixos de `mlp_get_load_status()`. El dashboard mostra "Memoria: 27.9%" sempre.
- [x] **`core-logic.php:524`** — Treure els valors fixos de `mlp_get_current_metrics()`: `max_memory => 291.81`, `max_time => 17.995`, `max_cpu => 53.40`, `max_size => 34.70`, `memory_percent => 25` (valors de la màquina de dev). Lligar-los a `mlp_get_advanced_stats()` o amagar-los.
- [x] **`core-logic.php:471-474`** — Treure `oc_lat => 0.10`, `oc_class => 'green'`, `opcache_hit => 0` de `mlp_get_system_health()`. "Obj. Cache Latencia: 0.10 ms" és fals.

### Càrrega de servidor evitable (P1)

- [x] **`memory-logger.php:42`** — Eliminar `define('MLP_ADMIN_EMAIL', get_option('admin_email'))`: fa una query a la BD a cada request de frontend i la constant no s'usa enlloc (codi mort).
- [x] **`admin-logic.php:567`** — `mlp_show_critical_error_notices()` crida `mlp_enhanced_error_detection()` sense caché a cada render d'admin (escaneja 4 logs amb regex). Usar el transient `mlp_cached_errors_count` que ja deixa el cron.
- [x] **`core-logic.php:518`** — Afegir `LOCK_EX` a `file_put_contents(MLP_LOG_FILE, ..., FILE_APPEND)` per evitar línies corruptes amb escriptures concurrents.

---

## Fase 2 — Refactor lleuger (SoC)

- [x] **Parser de log únic** — Crear `mlp_parse_log_line()` i substituir els ≥6 parsers duplicats: `core-logic.php:571`, `utils.php:1335`, `admin-logic.php:668`, `tab-dashboard.php:548`, `tab-statistics.php:233`... *(`mlp_parse_log_line()` ja existia a utils.php:900 i 4 llocs ja l'usaven; substituït el parseig manual de `mlp_group_stats_by_visitor` i corregit un bug latent de clau `$row['DATE']` → `$row['date']` a core-logic.php:609.)*
- [x] **Neteja de caché única** — Crear `mlp_purge_cache()` i unificar les 5 neteges duplicades: `utils.php:666`, `admin-logic.php:450/476/429`, `ajax-logic.php:639`. *(`mlp_purge_cache()` ja existia a utils.php:953; ara el fan servir `mlp_reset_to_defaults()`, `mlp_clear_logs_and_cache()`, `mlp_refresh_system_health()` i `mlp_ajax_refresh_health()`. L'optimize DB conserva el seu comptador propi.)*
- [x] **Exportació unificada** — Deixar 1 handler + 1 nonce. Eliminar els duplicats: `memory-logger.php:389` + `admin-logic.php:625/743/786` + `ajax-logic.php:493` (3 fan exactament el mateix, amb 3 nonces diferents). *(Eliminats `mlp_handle_admin_post_export`, `mlp_export_diagnostic_report_handler` i `mlp_handle_direct_export_report` + els add_action i nonces morts (`exportReportNonce`, `exportDirectNonce`, `$export_nonce`). Només queda el handler AJAX `mlp_ajax_export_diagnostic_report` amb el nonce `mlp_export_diagnostic_report_nonce`, que és el que usa el JS.)*
- [x] **Arreglar bug "Integritat Core"** — `ajax-logic.php:343`: `mlp_ajax_check_file_integrity()` retorna `"✅ Núcleo verificado"` hardcoded. Fer que executi `mlp_check_file_integrity()` de veritat. *(Ja estava arreglat al codi actual: crida `mlp_check_file_integrity()` i mostra els issues reals.)*
- [x] **Usar helper de visitants** — Substituir el comptatge duplicat de `tab-dashboard.php:236` i `tab-diagnostic.php:126` per `mlp_process_visitor_stats()` (`utils.php:903`). *(Ja estava fet: les 3 vistes — dashboard, diagnostic i statistics — ja usen el helper.)*
- [x] **Esborrar codi mort** — `MLP_ADMIN_EMAIL`, `mlp_log_plugin_errors()` (mai hookejat), `mlp_check_large_error_log()` (mai hookejat), `mlp_get_cached()` (mai usat), profiling (`mlp_start_plugin_profiling`, `mlp_identify_plugin_by_request`) i stubs buits a `ajax-logic.php:670-680`. *(Eliminats tots: `mlp_log_plugin_errors`, `mlp_check_large_error_log`, `mlp_get_cached`, `mlp_start_plugin_profiling` + `mlp_end_plugin_profiling`, `mlp_identify_plugin_by_request` i els 3 stubs AJAX buits.)*
- [x] **Unificar versions als docblocks** — Els arxius diuen "13.2.0" als docblocks i el header és coherent (tots els arxius actualitzats a v13.2.0).

---

## Fase 3 — Seguretat operativa

- [x] **Guard a "Optimizar BD"** — `ajax-logic.php:514`: saltar taules >500 MB (o avisar), límit de temps i fer-ho via WP-Cron si cal. Evita bloquejar taules i matar hosting compartits. *(Implementat: límit de temps global de 25s + taules >500 MB saltades amb avís al log. De pas corregit el bug `SHOW TABLE STATUS LIKE %s` mal format amb resultat no usat.)*
- [x] **Revisar `.gitignore`** — Ja exclou `debug.log`, `memory-usage.log`, `memory-logger-errors.log` (fitxers amb rutes reals del servidor). *(Verificat: correcte.)*

---

## El que NO cal fer

- No reescriure l'arquitectura (ja és correcta).
- No tocar el sistema de caché/transients que ja funciona.
- No afegir cap feature nova.
- No afegir dependències externes noves.

Amb la Fase 1 + 2 el plugin queda més lleuger, més honest i més fàcil de mantenir sense perdre res.
