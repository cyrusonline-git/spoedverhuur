<?php

namespace App\Services;

/**
 * Lichte, snelle XLSX-lezer (streaming via XMLReader) voor grote lijsten
 * (de materieellijst heeft 50.000+ regels). Leest het eerste werkblad en geeft
 * per rij [kolomletter => waarde]. Datums komen als Excel-serienummer (float);
 * de import weet per kolom of het een datum is en rekent dat om.
 */
class XlsxLezer
{
    private array $sharedStrings = [];
    private ?string $bladnaam = null;
    private string $bladPad = 'xl/worksheets/sheet1.xml';

    public function __construct(private string $pad)
    {
        $zip = new \ZipArchive();
        if ($zip->open($pad) !== true) {
            throw new \RuntimeException('Kan het Excel-bestand niet openen (geen geldig .xlsx?).');
        }
        $this->bepaalEersteBlad($zip);
        $this->laadSharedStrings($zip);
        $zip->close();
    }

    public function bladnaam(): ?string
    {
        return $this->bladnaam;
    }

    /** @return \Generator<int, array<string, mixed>> rijnummer => [kolomletter => waarde] */
    public function rijen(): \Generator
    {
        $xml = new \XMLReader();
        if (! $xml->open('zip://'.$this->pad.'#'.$this->bladPad, null, LIBXML_NONET)) {
            throw new \RuntimeException('Werkblad niet gevonden in het Excel-bestand.');
        }
        $rijNr = 0;
        $rij = [];
        $celRef = null;
        $celType = null;
        $inV = false;
        $inIs = false;
        $tekst = '';
        while ($xml->read()) {
            if ($xml->nodeType === \XMLReader::ELEMENT) {
                switch ($xml->localName) {
                    case 'row':
                        $rijNr = (int) ($xml->getAttribute('r') ?: $rijNr + 1);
                        $rij = [];
                        break;
                    case 'c':
                        $celRef = $xml->getAttribute('r');
                        $celType = $xml->getAttribute('t');
                        $tekst = '';
                        break;
                    case 'v':
                        $inV = true;
                        break;
                    case 'is':
                        $inIs = true;
                        break;
                    case 't':
                        if ($inIs) {
                            $tekst .= $xml->readString();
                        }
                        break;
                }
            } elseif ($xml->nodeType === \XMLReader::TEXT || $xml->nodeType === \XMLReader::CDATA) {
                if ($inV) {
                    $tekst .= $xml->value;
                }
            } elseif ($xml->nodeType === \XMLReader::END_ELEMENT) {
                switch ($xml->localName) {
                    case 'v':
                        $inV = false;
                        break;
                    case 'is':
                        $inIs = false;
                        break;
                    case 'c':
                        if ($celRef !== null) {
                            $kolom = preg_replace('/\d+/', '', $celRef);
                            $rij[$kolom] = $this->waarde($celType, $tekst);
                        }
                        $celRef = null;
                        break;
                    case 'row':
                        if ($rij !== []) {
                            yield $rijNr => $rij;
                        }
                        break;
                    case 'sheetData':
                        $xml->close();

                        return;
                }
            }
        }
        $xml->close();
    }

    private function waarde(?string $type, string $raw): mixed
    {
        switch ($type) {
            case 's':
                return $this->sharedStrings[(int) $raw] ?? '';
            case 'inlineStr':
            case 'str':
                return $raw;
            case 'b':
                return $raw === '1';
            case 'e':
                return '';
            default:
                if ($raw === '') {
                    return '';
                }

                return is_numeric($raw) ? (float) $raw : $raw;
        }
    }

    private function bepaalEersteBlad(\ZipArchive $zip): void
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb && preg_match('/<sheet\b[^>]*\bname="([^"]*)"[^>]*\br:id="([^"]+)"/', $wb, $m)
            || $wb && preg_match('/<sheet\b[^>]*\br:id="([^"]+)"[^>]*\bname="([^"]*)"/', $wb, $m2)) {
            if (isset($m)) {
                $this->bladnaam = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1);
                $rid = $m[2];
            } else {
                $this->bladnaam = html_entity_decode($m2[2], ENT_QUOTES | ENT_XML1);
                $rid = $m2[1];
            }
            if ($rels && preg_match('/<Relationship\b[^>]*\bId="'.preg_quote($rid, '/').'"[^>]*\bTarget="([^"]+)"/', $rels, $r)
                || $rels && preg_match('/<Relationship\b[^>]*\bTarget="([^"]+)"[^>]*\bId="'.preg_quote($rid, '/').'"/', $rels, $r)) {
                $target = ltrim($r[1], '/');
                $this->bladPad = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }
        if ($zip->locateName($this->bladPad) === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $naam = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d*\.xml$#', $naam)) {
                    $this->bladPad = $naam;
                    break;
                }
            }
        }
    }

    private function laadSharedStrings(\ZipArchive $zip): void
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }
        $xml = new \XMLReader();
        if (! $xml->open('zip://'.$this->pad.'#xl/sharedStrings.xml', null, LIBXML_NONET)) {
            return;
        }
        $huidig = null;
        while ($xml->read()) {
            if ($xml->nodeType === \XMLReader::ELEMENT) {
                if ($xml->localName === 'si') {
                    $huidig = '';
                } elseif ($xml->localName === 't' && $huidig !== null) {
                    $huidig .= $xml->readString();
                }
            } elseif ($xml->nodeType === \XMLReader::END_ELEMENT && $xml->localName === 'si') {
                $this->sharedStrings[] = $huidig;
                $huidig = null;
            }
        }
        $xml->close();
    }
}
