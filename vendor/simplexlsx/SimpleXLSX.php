<?php
/**
 * SimpleXLSX — Lightweight XLSX reader (no external dependencies)
 * Uses PHP built-in ZipArchive + SimpleXML
 * Compatible with XAMPP PHP 7.4+
 */
class SimpleXLSX {
    private array $sheets = [];
    private array $sharedStrings = [];
    private string $error = '';

    public static function parse(string $file): self|false {
        $obj = new self();
        if (!$obj->_load($file)) return false;
        return $obj;
    }

    public static function parseError(): string {
        return 'File tidak bisa dibaca.';
    }

    private function _load(string $file): bool {
        if (!file_exists($file)) { $this->error = "File not found: $file"; return false; }
        if (!class_exists('ZipArchive'))  { $this->error = "ZipArchive extension required."; return false; }

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) { $this->error = "Cannot open ZIP."; return false; }

        // Shared strings
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            if ($ss) {
                foreach ($ss->si as $si) {
                    $text = '';
                    if (isset($si->t)) $text = (string)$si->t;
                    elseif (isset($si->r)) foreach ($si->r as $r) $text .= (string)($r->t ?? '');
                    $this->sharedStrings[] = $text;
                }
            }
        }

        // Workbook — find sheet names & relations
        $wbXml   = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $sheetFiles = [];

        if ($relsXml) {
            $rels = simplexml_load_string($relsXml);
            foreach ($rels->Relationship as $rel) {
                $type = (string)$rel['Type'];
                if (strpos($type, 'worksheet') !== false) {
                    $id     = (string)$rel['Id'];
                    $target = 'xl/' . ltrim((string)$rel['Target'], '/');
                    $sheetFiles[$id] = $target;
                }
            }
        }

        // Sheet order from workbook
        $sheetOrder = [];
        if ($wbXml) {
            $wb = simplexml_load_string($wbXml);
            if ($wb) {
                $wb->registerXPathNamespace('ns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                foreach ($wb->sheets->sheet ?? [] as $sh) {
                    $rId = (string)$sh->attributes('r', true)['id'] ?? '';
                    if ($rId && isset($sheetFiles[$rId])) $sheetOrder[] = $sheetFiles[$rId];
                }
            }
        }
        if (!$sheetOrder) $sheetOrder = array_values($sheetFiles);

        // Parse each sheet
        foreach ($sheetOrder as $path) {
            $wsXml = $zip->getFromName($path);
            if (!$wsXml) continue;

            $ws   = @simplexml_load_string($wsXml);
            if (!$ws) continue;
            $rows = [];

            foreach ($ws->sheetData->row ?? [] as $row) {
                $rowIdx = (int)$row['r'] - 1;
                $cells  = [];
                foreach ($row->c as $cell) {
                    $ref   = (string)$cell['r'];
                    $col   = $this->_colIndex($ref);
                    $type  = (string)$cell['t'];
                    $val   = (string)($cell->v ?? '');
                    if ($type === 's') $val = $this->sharedStrings[(int)$val] ?? '';
                    elseif ($type === 'b') $val = $val ? 'TRUE' : 'FALSE';
                    $cells[$col] = $val;
                }
                if ($cells) {
                    $maxCol = max(array_keys($cells));
                    $out    = [];
                    for ($c = 0; $c <= $maxCol; $c++) $out[] = $cells[$c] ?? '';
                    $rows[$rowIdx] = $out;
                }
            }

            // Re-index rows
            ksort($rows);
            $this->sheets[] = array_values($rows);
        }

        $zip->close();
        return !empty($this->sheets);
    }

    private function _colIndex(string $ref): int {
        preg_match('/([A-Z]+)/', $ref, $m);
        $col = $m[1] ?? 'A';
        $n   = 0;
        for ($i = 0; $i < strlen($col); $i++) $n = $n * 26 + (ord($col[$i]) - 64);
        return $n - 1;
    }

    public function rows(int $sheet = 0): array {
        return $this->sheets[$sheet] ?? [];
    }

    public function sheetsCount(): int { return count($this->sheets); }
    public function getError(): string  { return $this->error; }
}
