<?php

/**
 * ProcessWire Table CSV Export Helper
 * by Adrian Jones
 *
 * Helper process module for generating CSV from a Table field
 *
 * Copyright (C) 2020 by Adrian Jones
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 *
 */

class ProcessTableCsvExport extends Process implements Module {

    /**
     * getModuleInfo is a module required by all modules to tell ProcessWire about them
     *
     * @return array
     *
     */
    public static function getModuleInfo() {
        return array(
            'title' => __('Process Table CSV Export'),
            'version' => '2.0.12',
            'summary' => __('Helper module for creating CSV to export'),
            'author' => 'Adrian Jones',
            'href' => 'http://modules.processwire.com/modules/table-csv-import-export/',
            'singular' => true,
            'autoload' => false,
            'page' => array(
                'name' => 'table-csv-export',
                'parent' => 'setup',
                'title' => 'Table CSV Export',
                'status' => 'hidden'
            ),
            'permission' => 'table-csv-export',
            'permissions' => array(
                'table-csv-export' => 'Access to Table CSV Export'
            ),
            'requires' => 'TableCsvImportExport'
        );
    }


    /**
     * Initialize the module
     *
     */
    public function init() {
        parent::init();
        $this->wire()->addHook('Page::exportTableCsv', $this, 'exportCsv'); // not limited to table-csv-export permission because only relevant to front-end
    }

    /**
     * Executed when root url for module is accessed
     *
     */
    public function ___execute() {
        $this->exportCsv();
    }


    public function outputCSV($data, $delimiter, $enclosure) {
        $output = fopen("php://output", "w");
        foreach ($data as $row) {
            fputcsv($output, $row, $delimiter == "tab" ? chr(9) : $delimiter, $enclosure);
        }
        fclose($output);
    }


    public function exportCsv($event = NULL) {
        if(!is_null($event)) {
            $configSettings = wire('modules')->getModuleConfigData("TableCsvImportExport");
            $translatedOptions = array(
                'delimiter' => $configSettings['csvExportFieldSeparator'],
                'enclosure' => $configSettings['csvExportFieldEnclosure'],
                'extension' => $configSettings['csvExportExtension'],
                'multipleValuesSeparator' => $configSettings['exportMultipleValuesSeparator'],
                'namesFirstRow' => $configSettings['exportColumnsFirstRow'],
                'columns' => array(),
                'filter' => null,
                'selector' => null
            );
            $options = $event->arguments(1);
            $options = array_merge($translatedOptions, $options);
        }

        $delimiter = !is_null($event) ? $options['delimiter'] : $this->wire('input')->get->cs;
        $enclosure = !is_null($event) ? $options['enclosure'] : $this->wire('input')->get->ce;
        $extension = !is_null($event) ? $options['extension'] : $this->wire('input')->get->ext;
        $multipleValuesSeparator = !is_null($event) ? $options['multipleValuesSeparator'] : $this->wire('input')->get->mvs;
        if($multipleValuesSeparator == '\r') $multipleValuesSeparator = chr(13);
        if($multipleValuesSeparator == '\n') $multipleValuesSeparator = chr(10);
        $namesFirstRow = !is_null($event) ? $options['namesFirstRow'] : $this->wire('input')->get->nfr;
        $columnsToExport = !is_null($event) ? $options['columns'] : $this->wire('input')->get->cte;
        $selector = !is_null($event) ? $options['selector'] . ',' : $this->wire('input')->get->selector;
        $filter = !is_null($event) ? $options['selector'] . ',' : $this->wire('input')->get->filter;

        // override filter (Table Find GUI) with selector if provided
        if($selector) $filter = $selector;

        $namesFirstRow = $namesFirstRow == 'checked' ? true : false;

        $fieldName = !is_null($event) ? $event->arguments(0) : $this->wire('input')->get->fn;
        //actual field name is never mangled with a repeater extension
        $actualFieldName = (strpos($fieldName, '_repeater') !== FALSE) ? strstr($fieldName, '_repeater', true) : $fieldName;

        //get actual page, considering it might be a repeater
        if($actualFieldName != $fieldName) $repeaterId = str_replace($actualFieldName . '_repeater', '', $fieldName);
        if(isset($repeaterId)) {
            $p = $this->wire('pages')->get($repeaterId);
        }
        else {
            $p = !is_null($event) ? $event->object : $this->wire('pages')->get((int)$this->wire('input')->get->pid);
        }

        $p->of(false);

        // needs to be before of(true), otherwise it returns 0
        $totalRows = $p->$actualFieldName->getTotal();

        // make dates etc formatted in the CSV
        $p->of(true);

        $csv = array();
        if(!isset($filter) || $filter == 'undefined') $filter = '';
        $filter = rtrim($filter, ',');
        $rows = $p->$actualFieldName(ltrim(str_replace($actualFieldName.'.', '', $filter).", limit=".$totalRows, ','));
        $columns = $rows->getColumns();

        // if $columnsToExport not provided, then export all columns
        if(empty($columnsToExport) || $columnsToExport == 'undefined') $columnsToExport = array_keys($columns);

        $columnsToExport = is_array($columnsToExport) ? $columnsToExport : explode(",", $columnsToExport);
        $subfields = array();
        foreach($columnsToExport as $key => $val) {
            // if column names provided instead of indices
            if(!is_numeric($val)) {
                if(strpos($val, '.') !== false) {
                    $arr = explode(".", $val, 2);
                    $val = $arr[0];
                    $subfield = $arr[1];
                    $subfields[$key] = $subfield;
                }
                $col = $p->$actualFieldName->getColumn($val);
                $val = $col['sort'];
            }
            $orderedColumns[$key] = $columns[$val];
        }

        $i=0;
        foreach($rows as $row) {
            if($i==0 && $namesFirstRow == true) {
                foreach($orderedColumns as $colKey => $col) {
                    if(!$col['name']) continue;
                    $csv[$i][] = $col['name'] . (isset($subfields[$colKey]) ? '.'.$subfields[$colKey] : '');
                }
            }

            $i++;
            foreach($orderedColumns as $colKey => $col) {
                if(!$col['name']) continue;
                $value = $row->{$col['name']};
                $fieldType = $col['type'];

                if($fieldType == 'pageSelect' || $fieldType == 'pageRadios'  || $fieldType == 'pageAutocomplete') {
                    $value = $value->{$subfields[$colKey]};
                }
                elseif($fieldType == 'pageSelectMultiple' || $fieldType == 'pageAsmSelect' || $fieldType == 'pageCheckboxes' || $fieldType == 'pageAutocompleteMultiple') {
                    $pageTitles = array();
                    foreach(explode('|', $value) as $v) {
                        $pageTitles[] = $this->wire('pages')->get($v)->{$subfields[$colKey]};
                    }
                    $value = implode($multipleValuesSeparator, $pageTitles);
                }
                elseif($fieldType == 'selectMultiple') {
                    $value = implode($multipleValuesSeparator, $value);
                }
                $csv[$i][] = $value;
            }
        }

        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=".$actualFieldName .".".$extension);
        header("Pragma: no-cache");
        header("Expires: 0");

        $this->outputCSV($csv, $delimiter, $enclosure);
        exit;

    }

}
