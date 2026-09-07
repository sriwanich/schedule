<?php

declare(strict_types=1);

namespace Modules\Export;

/**
 * PdfExporter
 * ─────────────────────────────────────────────────────────
 * mPDF wrapper ที่ configure ภาษาไทยครบ
 * ต้องการ: composer require mpdf/mpdf
 *
 * Font: TH Sarabun New (bundled ใน mPDF ตั้งแต่ v8)
 * ถ้าต้องการ font อื่น ใส่ไว้ใน /tmp/mpdf/ttfontdata/
 */
class PdfExporter
{
    private const DEFAULT_FONT = 'thsarabunnew';   // หรือ 'garuda', 'kinnari'
    private const FONT_SIZE    = 14;

    // ── Build mPDF instance ──────────────────────────────────────────────

    private function createMpdf(array $options = []): \Mpdf\Mpdf
    {
        if (!class_exists('\Mpdf\Mpdf')) {
            throw new \RuntimeException('ไม่พบ mPDF กรุณารัน: composer require mpdf/mpdf');
        }

        $tmpDir = defined('BASE_PATH') ? BASE_PATH . '/tmp/mpdf' : sys_get_temp_dir() . '/mpdf';
        @mkdir($tmpDir, 0755, true);

        $config = array_merge([
            'mode'         => 'utf-8',
            'format'       => $options['format'] ?? 'A4',
            'orientation'  => $options['orientation'] ?? 'P',
            'margin_left'  => 20,
            'margin_right' => 20,
            'margin_top'   => 20,
            'margin_bottom'=> 20,
            'tempDir'      => $tmpDir,
            'default_font' => self::DEFAULT_FONT,
            'default_font_size' => self::FONT_SIZE,
        ], $options['mpdf'] ?? []);

        $mpdf = new \Mpdf\Mpdf($config);

        // Thai language settings
        $mpdf->SetDirectionality('ltr');
        $mpdf->autoLangToFont = true;
        $mpdf->autoScriptToLang = true;
        $mpdf->useSubstitutions = true;

        return $mpdf;
    }

    // ── Public Methods ────────────────────────────────────────────────────

    /**
     * Export รายการ events เป็น PDF
     */
    public function exportEventList(array $events, array $meta = []): string
    {
        $mpdf = $this->createMpdf();

        $mpdf->SetTitle($meta['title'] ?? 'รายงานตารางงาน');
        $mpdf->SetAuthor($meta['author'] ?? env('APP_NAME', 'ระบบตารางงาน'));
        $mpdf->SetCreator(env('APP_NAME', 'ระบบตารางงาน'));

        $this->setHeader($mpdf, $meta);
        $this->setFooter($mpdf);

        $html = $this->renderEventListHtml($events, $meta);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');   // S = return as string
    }

    /**
     * Export event เดียว (ใบงาน / ใบประกาศ)
     */
    public function exportEventDetail(array $event, array $meta = []): string
    {
        $mpdf = $this->createMpdf(['orientation' => 'P']);

        $mpdf->SetTitle('รายละเอียดงาน: ' . ($event['title'] ?? ''));
        $mpdf->SetAuthor(env('APP_NAME', 'ระบบตารางงาน'));

        $this->setHeader($mpdf, $meta);
        $this->setFooter($mpdf);

        $html = $this->renderEventDetailHtml($event);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * ส่ง PDF ให้ browser โหลด (inline หรือ attachment)
     */
    public function download(string $pdfContent, string $filename, bool $inline = false): void
    {
        $disposition = $inline ? 'inline' : 'attachment';
        $filename    = $this->sanitizeFilename($filename);

        header('Content-Type: application/pdf');
        header("Content-Disposition: {$disposition}; filename=\"{$filename}\"");
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }

    // ── HTML Templates ────────────────────────────────────────────────────

    private function renderEventListHtml(array $events, array $meta): string
    {
        $title    = $meta['title']   ?? 'รายงานตารางงาน';
        $period   = $meta['period']  ?? thai_date();
        $logoPath = base_path('images/logo.png');
        $logoTag  = file_exists($logoPath)
            ? '<img src="' . $logoPath . '" style="height:50px;">'
            : '';

        $rows = '';
        foreach ($events as $i => $e) {
            $date    = !empty($e['event_date']) ? thai_date_format($e['event_date']) : '-';
            $time    = !empty($e['event_time']) ? substr($e['event_time'], 0, 5) . ' น.' : '-';
            $bg      = ($i % 2 === 0) ? '#ffffff' : '#f8f9fa';
            $rows   .= "<tr style=\"background:{$bg};\">
                <td style=\"text-align:center;\">" . ($i + 1) . "</td>
                <td>{$date}</td>
                <td>{$time}</td>
                <td>" . e($e['title'] ?? '') . "</td>
                <td>" . e($e['department_name'] ?? '-') . "</td>
                <td>" . e($e['chairman_name'] ?? '-') . "</td>
                <td>" . e($e['dress_name'] ?? '-') . "</td>
                <td style=\"text-align:center;\">
                    <span style=\"padding:2px 8px;border-radius:3px;font-size:11px;
                          background:" . $this->statusColor($e['status'] ?? '') . ";\">
                        " . $this->statusLabel($e['status'] ?? '') . "
                    </span>
                </td>
            </tr>";
        }

        return "
        <html><head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: thsarabunnew, sans-serif; font-size: 14px; color: #222; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { font-size: 20px; margin: 5px 0; }
            .header p  { font-size: 13px; color: #666; margin: 3px 0; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th { background: #1a56db; color: #fff; padding: 8px 6px; text-align: left; font-size: 12px; }
            td { padding: 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
            .footer-note { margin-top: 15px; font-size: 11px; color: #999; text-align: right; }
        </style>
        </head><body>
        <div class='header'>
            {$logoTag}
            <h1>{$title}</h1>
            <p>ข้อมูล ณ {$period} | " . env('APP_NAME', 'ระบบตารางงาน') . "</p>
        </div>
        <table>
            <thead><tr>
                <th width='4%'>#</th>
                <th width='10%'>วันที่</th>
                <th width='7%'>เวลา</th>
                <th width='28%'>ชื่องาน</th>
                <th width='15%'>หน่วยงาน</th>
                <th width='13%'>ประธาน</th>
                <th width='11%'>การแต่งกาย</th>
                <th width='12%'>สถานะ</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        <p class='footer-note'>จำนวนทั้งหมด: " . count($events) . " รายการ</p>
        </body></html>";
    }

    private function renderEventDetailHtml(array $event): string
    {
        $persons = '';
        foreach ($event['persons'] ?? [] as $p) {
            $persons .= "<li>" . e($p['name'] ?? '') . ($p['position'] ? " ({$p['position']})" : '') . "</li>";
        }

        return "
        <html><head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: thsarabunnew, sans-serif; font-size: 14px; color: #222; }
            h1   { font-size: 20px; text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th   { background: #f3f4f6; padding: 8px; text-align: left; width: 30%; border: 1px solid #ddd; }
            td   { padding: 8px; border: 1px solid #ddd; }
        </style>
        </head><body>
        <h1>" . e($event['title'] ?? 'รายละเอียดงาน') . "</h1>
        <table>
            <tr><th>วันที่</th><td>" . thai_date_format($event['event_date'] ?? '') . "</td></tr>
            <tr><th>เวลา</th><td>" . (substr($event['event_time'] ?? '', 0, 5) ?: '-') . " น.</td></tr>
            <tr><th>หน่วยงาน</th><td>" . e($event['department_name'] ?? '-') . "</td></tr>
            <tr><th>ประธาน</th><td>" . e($event['chairman_name'] ?? '-') . "</td></tr>
            <tr><th>การแต่งกาย</th><td>" . e($event['dress_name'] ?? '-') . "</td></tr>
            <tr><th>สถานที่</th><td>" . e($event['location'] ?? '-') . "</td></tr>
            <tr><th>รายละเอียด</th><td>" . nl2br(e($event['description'] ?? '')) . "</td></tr>
            <tr><th>ผู้เกี่ยวข้อง</th><td><ul style='margin:0;padding-left:16px;'>{$persons}</ul></td></tr>
        </table>
        </body></html>";
    }

    // ── Header / Footer ───────────────────────────────────────────────────

    private function setHeader(\Mpdf\Mpdf $mpdf, array $meta): void
    {
        $title   = $meta['title'] ?? env('APP_NAME', 'ระบบตารางงาน');
        $mpdf->SetHTMLHeader("<div style='text-align:right;font-size:10px;color:#999;font-family:thsarabunnew;'>{$title}</div>");
    }

    private function setFooter(\Mpdf\Mpdf $mpdf): void
    {
        $date = thai_date();
        $mpdf->SetHTMLFooter(
            "<div style='text-align:center;font-size:10px;color:#999;font-family:thsarabunnew;'>"
            . "พิมพ์เมื่อ {$date} | หน้า {PAGENO} / {nbpg}</div>"
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[\/\\\:\*\?"<>|]/', '_', $name);
        return mb_substr($name, 0, 200) . (str_ends_with($name, '.pdf') ? '' : '.pdf');
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'active'    => '#d1fae5',
            'cancelled' => '#fee2e2',
            'postponed' => '#fef3c7',
            'completed' => '#dbeafe',
            default     => '#f3f4f6',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active'    => 'กำหนดการ',
            'cancelled' => 'ยกเลิก',
            'postponed' => 'เลื่อน',
            'completed' => 'เสร็จสิ้น',
            default     => $status,
        };
    }
}