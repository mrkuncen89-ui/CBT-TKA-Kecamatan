<?php
/**
 * Simple Multi-Sheet XLSX Builder
 * Minimal implementation using ZipArchive
 */
class XLSXBuilder {
    private $sheets = [];
    private $styles = [];

    public function addSheet(string $name, array $data) {
        $this->sheets[] = [
            'name' => mb_substr(preg_replace('/[\/\\\?\*\[\]:]/', '-', $name), 0, 31),
            'data' => $data
        ];
    }

    public function download(string $filename) {
        if (!class_exists('\ZipArchive')) {
            return false;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $contentTypes .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
        $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= count($this->sheets); $i++) {
            $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $contentTypes .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $rels .= '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $workbook .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $workbook .= '<sheets>';
        for ($i = 1; $i <= count($this->sheets); $i++) {
            $workbook .= '<sheet name="' . htmlspecialchars($this->sheets[$i-1]['name']) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
        }
        $workbook .= '</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 4. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= count($this->sheets); $i++) {
            $wbRels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $wbRels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wbRels .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 5. xl/styles.xml (Minimal)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $styles .= '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $styles .= '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>';
        $styles .= '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>';
        $styles .= '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>';
        $styles .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $styles .= '<cellXfs count="2">';
        $styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'; // Normal
        $styles .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'; // Bold
        $styles .= '</cellXfs>';
        $styles .= '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheetN.xml
        foreach ($this->sheets as $idx => $sheet) {
            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
            $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
            $sheetXml .= '<sheetData>';
            
            foreach ($sheet['data'] as $rowIdx => $row) {
                $sheetXml .= '<row r="' . ($rowIdx + 1) . '">';
                foreach ($row as $colIdx => $cellData) {
                    $colLetter = $this->getColLetter($colIdx);
                    $ref = $colLetter . ($rowIdx + 1);
                    
                    $val = $cellData['value'] ?? $cellData;
                    $style = $cellData['style'] ?? 0; // 0=normal, 1=bold
                    
                    if (is_numeric($val) && !is_string($val)) {
                        $sheetXml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $val . '</v></c>';
                    } else {
                        $sheetXml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t>' . htmlspecialchars((string)$val) . '</t></is></c>';
                    }
                }
                $sheetXml .= '</row>';
            }
            
            $sheetXml .= '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet' . ($idx + 1) . '.xml', $sheetXml);
        }

        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: max-age=0');
        readfile($tempFile);
        unlink($tempFile);
        return true;
    }

    private function getColLetter($colIdx) {
        $letter = '';
        while ($colIdx >= 0) {
            $letter = chr($colIdx % 26 + 65) . $letter;
            $colIdx = floor($colIdx / 26) - 1;
        }
        return $letter;
    }
}
