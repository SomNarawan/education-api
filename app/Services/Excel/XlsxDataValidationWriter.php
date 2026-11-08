<?php

namespace App\Services\Excel;

use RuntimeException;
use ZipArchive;

class XlsxDataValidationWriter
{
    public function apply(string $path, array $namedRanges, array $validations): void
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

            if (! $archive->addFromString('xl/workbook.xml', $workbookXml)
                || ! $archive->addFromString('xl/worksheets/sheet1.xml', $worksheetXml)) {
                throw new RuntimeException('Unable to update XLSX validation XML');
            }
        } finally {
            $archive->close();
        }
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
                .' errorStyle="stop" sqref="'.$this->escape((string) $cellRange).'">'
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
