<?php
declare(strict_types=1);

final class ResultsExport
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public static function download(array $quiz, array $stats): never
    {
        $path = self::create($quiz, $stats);
        try {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="quizhell-results-' . (int) $quiz['id'] . '-' . gmdate('Ymd-His') . '.xlsx"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: private, no-store');
            session_write_close();
            readfile($path);
        } finally {
            unlink($path);
        }
        exit;
    }

    /** Builds a small, two-sheet OOXML workbook; the caller owns the temporary file. */
    public static function create(array $quiz, array $stats): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new HttpError(503, 'Export Excel memerlukan ekstensi PHP zip. Aktifkan ekstensi tersebut lalu coba lagi.');
        }
        $path = tempnam(sys_get_temp_dir(), 'quizhell-export-');
        if ($path === false) throw new RuntimeException('Cannot create export file.');
        $zip = new ZipArchive();
        $opened = false;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot open export archive.');
            $opened = true;
            $files = [
                '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
                '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . self::NS . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Hasil player" sheetId="1" r:id="rId1"/><sheet name="Ringkasan" sheetId="2" r:id="rId2"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => self::styles(),
            ];
            $rows = [['Player nickname', 'Score', 'Kategori']];
            foreach ($stats['results'] as $result) {
                $rows[] = [(string) $result['nickname'], (int) $result['score'], (string) $result['category']];
            }
            $files['xl/worksheets/sheet1.xml'] = self::sheet($rows, [36, 14, 18], true);
            $hardest = $stats['hardest'];
            $summary = [
                ['Statistik', 'Nilai'],
                ['Judul quiz', (string) $quiz['title']],
                ['Total participants (attempt selesai)', (int) $stats['participants']],
                ['Attempt dimulai', (int) $stats['starts']],
                ['Average score (/ 10)', $stats['average_score'] !== null ? (float) $stats['average_score'] : '—'],
                ['Completion rate', (float) $stats['completion_rate'] / 100],
                ['Soal tersulit', $hardest ? (string) $hardest['prompt'] : 'Belum ada data soal.'],
                ['Persentase jawaban salah', $hardest ? (float) $hardest['wrong_rate'] / 100 : '—'],
                ['Jawaban salah (versi soal ini)', $hardest ? (int) $hardest['wrong_count'] : '—'],
                ['Attempt selesai (versi soal ini)', $hardest ? (int) $hardest['attempts'] : '—'],
            ];
            $files['xl/worksheets/sheet2.xml'] = self::sheet($summary, [42, 80], false, ['B5' => 2, 'B6' => 3, 'B8' => 3]);
            foreach ($files as $name => $content) {
                if (!$zip->addFromString($name, $content)) throw new RuntimeException('Cannot write workbook part.');
            }
            if (!$zip->close()) throw new RuntimeException('Cannot finish export archive.');
            $opened = false;
            return $path;
        } catch (Throwable $error) {
            if ($opened) $zip->close();
            if (is_file($path)) unlink($path);
            throw $error;
        }
    }

    private static function xml(string $value): string
    {
        // XML 1.0 excludes some control characters that nicknames can contain.
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub($value, 'UTF-8'));
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function sheet(array $rows, array $widths, bool $filter, array $styles = []): string
    {
        $lastColumn = chr(64 + count($widths));
        $lastRow = count($rows);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="' . self::NS . '"><dimension ref="A1:' . $lastColumn . $lastRow . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $xml .= '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        foreach ($rows as $index => $row) {
            $number = $index + 1;
            $xml .= '<row r="' . $number . '">';
            foreach ($row as $column => $value) {
                $cell = chr(65 + $column) . $number;
                $style = $number === 1 ? 1 : ($styles[$cell] ?? 0);
                $attributes = ' r="' . $cell . '" s="' . $style . '"';
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c' . $attributes . '><v>' . json_encode($value, JSON_THROW_ON_ERROR) . '</v></c>';
                } else {
                    // Explicit inline strings keep =, +, - and @ text from becoming formulas.
                    $xml .= '<c' . $attributes . ' t="inlineStr"><is><t xml:space="preserve">' . self::xml($value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($filter) $xml .= '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>';
        return $xml . '</worksheet>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="' . self::NS . '"><numFmts count="2"><numFmt numFmtId="164" formatCode="0.0"/><numFmt numFmtId="165" formatCode="0.0%"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF1B250C"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFC3F85C"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
