<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free .xlsx reader (no Composer/PhpSpreadsheet — this
 * project runs on plain shared hosting). Streams rows via XMLReader so large
 * exports don't need to be loaded fully into memory.
 *
 * Only reads what this app needs: cell text/number values from one sheet.
 * Does not evaluate formulas, styles, or merged cells.
 */
final class XlsxReader
{
    /**
     * Yields ['row' => int, 'cells' => ['A' => 'value', 'B' => 'value', ...]]
     * for every non-empty row in the sheet, in row order.
     *
     * @param string $path Path to the .xlsx file on disk.
     * @param string|null $preferredSheetName If given, matches a sheet whose
     *        name contains this string (case-insensitive). Falls back to the
     *        first sheet in the workbook if no match is found.
     */
    public static function readSheet(string $path, ?string $preferredSheetName = null): Generator
    {
        if (!class_exists('ZipArchive') || !class_exists('XMLReader')) {
            throw new RuntimeException(
                'Server ini belum mengaktifkan ekstensi PHP "zip" dan/atau "xml" yang dibutuhkan untuk membaca file .xlsx. '
                . 'Di localhost (XAMPP/Laragon): buka php.ini, hapus tanda ";" di depan "extension=zip", lalu restart Apache. '
                . 'Di hosting cPanel: MultiPHP INI Editor / Select PHP Version -> Extensions, aktifkan "zip" dan "xml".'
            );
        }
        if (!is_file($path)) {
            throw new RuntimeException("File tidak ditemukan: {$path}");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File bukan .xlsx yang valid (gagal membuka sebagai ZIP).');
        }

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            $zip->close();
            throw new RuntimeException('Struktur file .xlsx tidak dikenali (bukan hasil export Excel yang valid).');
        }

        $sheetTarget = self::resolveSheetTarget($workbookXml, $relsXml, $preferredSheetName);
        $sharedStrings = self::loadSharedStrings($zip);
        $zip->close();

        $streamUri = 'zip://' . $path . '#xl/' . $sheetTarget;

        $reader = new XMLReader();
        if (!$reader->open($streamUri)) {
            throw new RuntimeException('Gagal membaca sheet di dalam file .xlsx.');
        }

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $rowNum = (int) $reader->getAttribute('r');
            $rowXml = $reader->readOuterXML();
            if ($rowXml === '') {
                continue;
            }

            $rowEl = @simplexml_load_string($rowXml);
            if ($rowEl === false) {
                continue;
            }

            $cells = [];
            foreach ($rowEl->c as $c) {
                $ref = (string) $c['r'];
                if (!preg_match('/^([A-Z]+)/', $ref, $m)) {
                    continue;
                }
                $col = $m[1];
                $type = (string) $c['t'];

                if ($type === 's') {
                    $idx = (int) $c->v;
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = self::extractText($c->is);
                } elseif ($type === 'str' || $type === '') {
                    $value = isset($c->v) ? (string) $c->v : '';
                } else {
                    $value = isset($c->v) ? (string) $c->v : '';
                }
                $cells[$col] = $value;
            }

            if ($cells !== []) {
                yield ['row' => $rowNum, 'cells' => $cells];
            }
        }
        $reader->close();
    }

    private static function extractText(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        // Handles both a plain <t> and rich-text <r><t>...</t></r> runs.
        $texts = $node->xpath('.//*[local-name()="t"]');
        return implode('', array_map(static fn ($t) => (string) $t, $texts ?: []));
    }

    private static function resolveSheetTarget(string $workbookXml, string $relsXml, ?string $preferredSheetName): string
    {
        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        if ($workbook === false || $rels === false) {
            throw new RuntimeException('Gagal membaca daftar sheet pada file .xlsx.');
        }

        $relsMap = [];
        foreach ($rels->Relationship as $rel) {
            $relsMap[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $namespaces = $workbook->getNamespaces(true);
        $rNs = $namespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $chosenTarget = null;
        $firstTarget = null;
        foreach ($workbook->sheets->sheet as $sheet) {
            $rId = (string) $sheet->attributes($rNs)['id'];
            $target = $relsMap[$rId] ?? null;
            if ($target === null) {
                continue;
            }
            $target = ltrim($target, '/');
            if (str_starts_with($target, 'xl/')) {
                $target = substr($target, 3);
            }
            if ($firstTarget === null) {
                $firstTarget = $target;
            }
            $name = (string) $sheet['name'];
            if ($preferredSheetName !== null && stripos($name, $preferredSheetName) !== false) {
                $chosenTarget = $target;
                break;
            }
        }

        $target = $chosenTarget ?? $firstTarget;
        if ($target === null) {
            throw new RuntimeException('Tidak ada sheet yang ditemukan di file .xlsx ini.');
        }
        return $target;
    }

    private static function loadSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }
        $strings = [];
        foreach ($doc->si as $si) {
            $strings[] = self::extractText($si);
        }
        return $strings;
    }
}
