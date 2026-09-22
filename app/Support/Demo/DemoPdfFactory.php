<?php

namespace App\Support\Demo;

/**
 * Builds tiny valid PDF documents for local demo Media (no external deps).
 */
final class DemoPdfFactory
{
    public static function document(string $title, string $body): string
    {
        $safeTitle = self::pdfEscape($title);
        $lines = preg_split("/\R/u", $body) ?: [];
        $content = "BT /F1 18 Tf 72 720 Td ({$safeTitle}) Tj 0 -28 Td /F1 12 Tf";

        foreach (array_slice($lines, 0, 16) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $content .= ' 0 -16 Td ('.self::pdfEscape(mb_substr($trimmed, 0, 90)).') Tj';
        }

        $content .= ' ET';
        $length = strlen($content);

        $objects = [
            "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n",
            "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n",
            "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj\n",
            "4 0 obj<< /Length {$length} >>stream\n{$content}\nendstream\nendobj\n",
            "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.count($offsets)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= 'trailer<< /Size '.count($offsets)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    private static function pdfEscape(string $value): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
