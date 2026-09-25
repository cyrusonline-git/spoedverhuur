<?php

namespace App\Services;

/**
 * Minimale XLSX-schrijver (één werkblad) zonder externe pakketten: precies genoeg
 * voor het maandoverzicht in de opmaak van "NL 24 uurs week vergoedingen".
 * Cellen: string, int/float, of null. Optioneel vet en kolombreedtes.
 */
class XlsxSchrijver
{
    private array $rijen = [];   // rijnummer => [kolomletter => ['v' => waarde, 'b' => bool vet]]
    private array $breedtes = []; // kolomletter => breedte

    public function __construct(private string $bladnaam = 'Blad1')
    {
    }

    public function cel(string $ref, mixed $waarde, bool $vet = false): self
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($ref), $m);
        $this->rijen[(int) $m[2]][$m[1]] = ['v' => $waarde, 'b' => $vet];

        return $this;
    }

    public function rij(int $nr, array $waarden, bool $vet = false): self
    {
        $kol = 'A';
        foreach ($waarden as $w) {
            $this->cel($kol.$nr, $w, $vet);
            $kol++;
        }

        return $this;
    }

    public function breedte(string $kolom, float $breedte): self
    {
        $this->breedtes[strtoupper($kolom)] = $breedte;

        return $this;
    }

    public function schrijf(string $pad): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($pad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Kan $pad niet schrijven.");
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.htmlspecialchars($this->bladnaam, ENT_XML1).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        // Stijlen: 0 = normaal, 1 = vet, 2 = bedrag (0.00), 3 = vet bedrag
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'</cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());
        $zip->close();
    }

    private function sheetXml(): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($this->breedtes) {
            $x .= '<cols>';
            foreach ($this->breedtes as $kol => $br) {
                $i = self::kolomIndex($kol);
                $x .= '<col min="'.$i.'" max="'.$i.'" width="'.$br.'" customWidth="1"/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        ksort($this->rijen);
        foreach ($this->rijen as $nr => $cellen) {
            uksort($cellen, fn ($a, $b) => self::kolomIndex($a) <=> self::kolomIndex($b));
            $x .= '<row r="'.$nr.'">';
            foreach ($cellen as $kol => $c) {
                $ref = $kol.$nr;
                $v = $c['v'];
                if ($v === null || $v === '') {
                    continue;
                }
                if (is_int($v) || (is_float($v) && floor($v) == $v && ! $this->isBedragKolom($kol))) {
                    $x .= '<c r="'.$ref.'" s="'.($c['b'] ? 1 : 0).'"><v>'.(0 + $v).'</v></c>';
                } elseif (is_float($v)) {
                    $x .= '<c r="'.$ref.'" s="'.($c['b'] ? 3 : 2).'"><v>'.number_format($v, 2, '.', '').'</v></c>';
                } else {
                    $x .= '<c r="'.$ref.'" t="inlineStr" s="'.($c['b'] ? 1 : 0).'"><is><t xml:space="preserve">'.htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }
            }
            $x .= '</row>';
        }

        return $x.'</sheetData></worksheet>';
    }

    private array $bedragKolommen = [];

    /** Kolommen die altijd als bedrag (2 decimalen) worden weggeschreven, ook bij hele getallen. */
    public function bedragKolom(string $kolom): self
    {
        $this->bedragKolommen[] = strtoupper($kolom);

        return $this;
    }

    private function isBedragKolom(string $kol): bool
    {
        return in_array($kol, $this->bedragKolommen, true);
    }

    private static function kolomIndex(string $kol): int
    {
        $n = 0;
        foreach (str_split($kol) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }
}
