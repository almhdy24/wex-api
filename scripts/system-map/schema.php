<?php
$db = new SQLite3($argv[1]);
$tables = [];
$r = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations' ORDER BY name");
while ($t = $r->fetchArray(SQLITE3_ASSOC)) { $tables[] = $t['name']; }
foreach ($tables as $t) {
    echo "### `$t`\n\n| column | type | null | default |\n|---|---|---|---|\n";
    $c = $db->query("PRAGMA table_info('$t')");
    while ($row = $c->fetchArray(SQLITE3_ASSOC)) {
        printf("| %s%s | %s | %s | %s |\n", $row['name'], $row['pk'] ? ' (PK)' : '', $row['type'], $row['notnull'] ? 'NO' : 'yes', $row['dflt_value'] === null ? '' : str_replace('|', '\|', $row['dflt_value']));
    }
    $idx = $db->query("PRAGMA index_list('$t')"); $list = [];
    while ($i = $idx->fetchArray(SQLITE3_ASSOC)) {
        $cols = []; $ic = $db->query("PRAGMA index_info('{$i['name']}')");
        while ($x = $ic->fetchArray(SQLITE3_ASSOC)) { $cols[] = $x['name']; }
        $list[] = ($i['unique'] ? 'UNIQUE ' : '') . '(' . implode(', ', $cols) . ')';
    }
    echo "\nIndexes: " . ($list ? implode('; ', $list) : '—') . "\n";
    $fk = $db->query("PRAGMA foreign_key_list('$t')"); $fks = [];
    while ($f = $fk->fetchArray(SQLITE3_ASSOC)) { $fks[] = "{$f['from']} → {$f['table']}.{$f['to']} (ON DELETE {$f['on_delete']})"; }
    echo "Foreign keys: " . ($fks ? implode('; ', $fks) : '—') . "\n\n";
}
