<?php

namespace App\Support;

/**
 * FPDI's bundled free PDF parser cannot read cross-reference *streams*
 * (a PDF 1.5+ feature; only the paid FPDI PDF-Parser add-on supports it).
 * Some letterhead/template PDFs exported by modern tools use them even
 * though the file's objects themselves are plain, uncompressed indirect
 * objects. Rather than requiring a specific export setting from whoever
 * supplies these assets, rebuild a classic xref table by scanning the raw
 * object offsets and reusing the /Root already present in plain text.
 *
 * Only ever invoked as a fallback after FPDI's own parser fails, and only
 * writes a throwaway temp copy — the original file is never modified.
 */
class PdfXrefRepair
{
    public static function isNeeded(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'cross reference')
            || str_contains($e->getMessage(), 'compression technique')
            || str_contains($e->getMessage(), 'XRef');
    }

    /**
     * @return string Path to a repaired temp copy with a classic xref table.
     */
    public static function repair(string $sourcePath): string
    {
        $data = file_get_contents($sourcePath);

        if ($data === false) {
            throw new \RuntimeException("Unable to read PDF: {$sourcePath}");
        }

        if (! preg_match('/\/Root\s+(\d+)\s+0\s+R/', $data, $rootMatch)) {
            throw new \RuntimeException('Could not locate /Root while repairing PDF xref.');
        }

        $offsets = [];
        if (preg_match_all('/(?:^|[^0-9])(\d+)\s+0\s+obj\b/', $data, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $i => [$objNum, $_]) {
                $objNum = (int) $objNum;
                // The byte offset of the object must start at its number, not the preceding character.
                $fullMatchOffset = $matches[0][$i][1];
                $objStart = strpos($data, $objNum.' 0 obj', $fullMatchOffset);
                $offsets[$objNum] = $objStart;
            }
        }

        if ($offsets === []) {
            throw new \RuntimeException('No indirect objects found while repairing PDF xref.');
        }

        $maxObj = max(array_keys($offsets));

        $xref = "xref\n0 ".($maxObj + 1)."\n";
        $xref .= "0000000000 65535 f \n";

        for ($n = 1; $n <= $maxObj; $n++) {
            if (isset($offsets[$n])) {
                $xref .= sprintf("%010d %05d n \n", $offsets[$n], 0);
            } else {
                $xref .= "0000000000 00000 f \n";
            }
        }

        $xrefOffset = strlen($data);
        $trailer = "trailer\n<< /Size ".($maxObj + 1)." /Root {$rootMatch[1]} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        $repaired = $data."\n".$xref.$trailer;

        $tempPath = tempnam(sys_get_temp_dir(), 'proctad_pdf_repair_').'.pdf';
        file_put_contents($tempPath, $repaired);

        return $tempPath;
    }
}
