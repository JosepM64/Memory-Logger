# Memory Logger Pro — Llista de tasques de millora

> Document intern amb les millores detectades a l'anàlisi del codi (v13.0.0).
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

- [ ] **Parser de log únic** — Crear `mlp_parse_log_line()` i substituir els ≥6 parsers duplicats: `core-logic.php:571`, `utils.php:1335`, `admin-logic.php:668`, `tab-dashboard.php:548`, `tab-statistics.php:233`...
- [ ] **Neteja de caché única** — Crear `mlp_purge_cache()` i unificar les 5 neteges duplicades: `utils.php:666`, `admin-logic.php:450/476/429`, `ajax-logic.php:639`.
- [ ] **Exportació unificada** — Deixar 1 handler + 1 nonce. Eliminar els duplicats: `memory-logger.php:389` + `admin-logic.php:625/743/786` + `ajax-logic.php:493` (3 fan exactament el mateix, amb 3 nonces diferents).
- [ ] **Arreglar bug "Integritat Core"** — `ajax-logic.php:343`: `mlp_ajax_check_file_integrity()` retorna `"✅ Núcleo verificado"` hardcoded. Fer que executi `mlp_check_file_integrity()` de veritat.
- [ ] **Usar helper de visitants** — Substituir el comptatge duplicat de `tab-dashboard.php:236` i `tab-diagnostic.php:126` per `mlp_process_visitor_stats()` (`utils.php:903`).
- [ ] **Esborrar codi mort** — `MLP_ADMIN_EMAIL`, `mlp_log_plugin_errors()` (mai hookejat), `mlp_check_large_error_log()` (mai hookejat), `mlp_get_cached()` (mai usat), profiling (`mlp_start_plugin_profiling`, `mlp_identify_plugin_by_request`) i stubs buits a `ajax-logic.php:670-680`.
- [ ] **Unificar versions als docblocks** — Els arxius diuen "12.8.0" als docblocks però el header és 13.0.0 (`utils.php`, `core-logic.php`, `admin-logic.php`, `ajax-logic.php`).

---

## Fase 3 — Seguretat operativa

- [ ] **Guard a "Optimizar BD"** — `ajax-logic.php:514`: saltar taules >500 MB (o avisar), límit de temps i fer-ho via WP-Cron si cal. Evita bloquejar taules i matar hosting compartits.
- [ ] **Revisar `.gitignore`** — Ja exclou `debug.log`, `memory-usage.log`, `memory-logger-errors.log` (fitxers amb rutes reals del servidor).

---

## El que NO cal fer

- No reescriure l'arquitectura (ja és correcta).
- No tocar el sistema de caché/transients que ja funciona.
- No afegir cap feature nova.
- No afegir dependències externes noves.

Amb la Fase 1 + 2 el plugin queda més lleuger, més honest i més fàcil de mantenir sense perdre res.
