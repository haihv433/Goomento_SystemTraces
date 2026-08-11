<?php
/**
 * Goomento_SystemTraces runtime config, read by Service\Config. Not tied to Magento's
 * admin config (core_config_data) - a plain file, edit and save, no cache to flush.
 */

return [
    // Master switch for tracing + report generation. The module's other off switch is
    // disabling it entirely: bin/magento module:disable Goomento_SystemTraces
    'enabled' => true,

    // Only build a report for requests whose path matches this pattern (fnmatch wildcards:
    // * and ?, e.g. '/catalog/product/*').
    'url_pattern' => '*',

    // Where report files land under var/system_traces/:
    // 'folder' - one subfolder per request path segment (var/system_traces/catalog/product/view/<file>.html)
    // 'flat'   - one directory, request path flattened into the filename
    'report_path_mode' => 'folder',
];
