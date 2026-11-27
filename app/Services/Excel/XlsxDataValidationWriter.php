<?php

namespace App\Services\Excel;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class XlsxDataValidationWriter
{
    /** @param list<int> $textColumns One-based column numbers on the first worksheet. */
    public function apply(string $path, array $namedRanges, array $validations, array $textColumns = []): void
    {
        $archive = new ZipArchive;
        $opened = $archive->open($path);

        if ($opened !== true) {
            throw new RuntimeException("Unable to open XLSX archive: {$path}");
        }

        try {
            $workbookXml = $this->entry($archive, 'xl/workbook.xml');
            $worksheetXml = $this->entry($archive, 'xl/worksheets/sheet1.xml');

            $workbookXml = $this->insert(
                $workbookXml,
                $this->definedNamesXml($namedRanges),
                '<calcPr',
                '</workbook>',
            );
            $worksheetXml = $this->insert(
                $worksheetXml,
                $this->dataValidationsXml($validations),
                '<pageMargins',
                '</worksheet>',
            );

            if ($textColumns !== []) {
                [$worksheetXml, $stylesXml] = $this->applyTextColumns(
                    $worksheetXml,
                    $this->entry($archive, 'xl/styles.xml'),
                    $textColumns,
                );

                if (! $archive->addFromString('xl/styles.xml', $stylesXml)) {
                    throw new RuntimeException('Unable to update XLSX styles XML');
                }
            }

            if (! $archive->addFromString('xl/workbook.xml', $workbookXml)
                || ! $archive->addFromString('xl/worksheets/sheet1.xml', $worksheetXml)) {
                throw new RuntimeException('Unable to update XLSX validation XML');
            }
        } finally {
            $archive->close();
        }
    }

    private function applyTextColumns(string $worksheetXml, string $stylesXml, array $columns): array
    {
        $worksheet = new DOMDocument;
        $styles = new DOMDocument;
        if (! $worksheet->loadXML($worksheetXml) || ! $styles->loadXML($stylesXml)) {
            throw new RuntimeException('Unable to read XLSX worksheet or styles XML');
        }

        $formats = $styles->getElementsByTagName('cellXfs')->item(0);
        $defaultFormat = $formats?->getElementsByTagName('xf')->item(0);
        if (! $defaultFormat instanceof DOMElement) {
            throw new RuntimeException('Missing XLSX default cell style');
        }

        $styleIndex = $formats->getElementsByTagName('xf')->length;
        $textFormat = $defaultFormat->cloneNode(true);
        $textFormat->setAttribute('numFmtId', '49'); // Built-in Excel Text format (@).
        $textFormat->setAttribute('applyNumberFormat', '1');
        $formats->appendChild($textFormat);
        $formats->setAttribute('count', (string) ($styleIndex + 1));

        $xpath = new DOMXPath($worksheet);
        foreach ($columns as $index) {
            $column = $xpath->query(
                "//*[local-name()='cols']/*[local-name()='col'][@min='{$index}' and @max='{$index}']"
            )->item(0);
            if (! $column instanceof DOMElement) {
                throw new RuntimeException("Missing XLSX column {$index}");
            }

            $column->setAttribute('style', (string) $styleIndex);
        }

        return [$worksheet->saveXML(), $styles->saveXML()];
    }

    private function entry(ZipArchive $archive, string $name): string
    {
        $contents = $archive->getFromName($name);

        if (! is_string($contents)) {
            throw new RuntimeException("Missing XLSX entry: {$name}");
        }

        return $contents;
    }

    private function definedNamesXml(array $namedRanges): string
    {
        $items = [];

        foreach ($namedRanges as $name => $formula) {
            $items[] = '<definedName name="'.$this->escape((string) $name).'">'
                .$this->escape((string) $formula)
                .'</definedName>';
        }

        return '<definedNames>'.implode('', $items).'</definedNames>';
    }

    private function dataValidationsXml(array $validations): string
    {
        $items = [];

        foreach ($validations as $cellRange => $namedRange) {
            $items[] = '<dataValidation type="list" allowBlank="1" showErrorMessage="1"'
                .' errorStyle="warning" sqref="'.$this->escape((string) $cellRange).'">'
                .'<formula1>'.$this->escape((string) $namedRange).'</formula1>'
                .'</dataValidation>';
        }

        return '<dataValidations count="'.count($items).'">'
            .implode('', $items)
            .'</dataValidations>';
    }

    private function insert(
        string $xml,
        string $fragment,
        string $preferredMarker,
        string $closingTag,
    ): string {
        $position = strpos($xml, $preferredMarker);

        if ($position === false) {
            $position = strrpos($xml, $closingTag);
        }

        if ($position === false) {
            throw new RuntimeException("Invalid XLSX XML: missing {$closingTag}");
        }

        return substr($xml, 0, $position).$fragment.substr($xml, $position);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
