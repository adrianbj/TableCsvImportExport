<?php

/**
 * ProcessWire Table CSV Import / Export
 * by Adrian Jones
 *
 * Processwire module for admin and front-end importing and exporting of CSV formatted content for Profields Table fields.
 *
 * Copyright (C) 2020 by Adrian Jones
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 *
 */

class TableCsvImportExport extends WireData implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return array(
            'title' => 'Table CSV Import / Export',
            'summary' => 'Processwire module for admin and front-end importing and exporting of CSV formatted content for Profields Table fields.',
            'href' => 'http://modules.processwire.com/modules/table-csv-import-export/',
            'version' => '2.0.14',
            'permanent' => false,
            'autoload' => 'template=admin',
            'singular' => true,
            'requires' => 'FieldtypeTable',
            'installs' => 'ProcessTableCsvExport',
            'permissions' => array(
                'table-csv-import' => 'Access to Table CSV Import',
                'table-csv-import-overwrite' => 'Access to choose overwrite option when using Table CSV Import'
            ),
            'requiredBy' => 'ProcessTableCsvExport'
        );
    }


   /**
     * Default configuration for module
     *
     */
    static public function getDefaultData() {
        return array(
            "importFieldSeparator" => ',',
            "importFieldEnclosure" => '"',
            "importConvertDecimals" => '',
            "importNamesFirstRow" => '',
            "importIgnoreFirstRow" => '',
            "importMultipleValuesSeparator" => '|',
            "allowOverrideimportSettings" => 1,
            "exportFieldSeparator" => ',',
            "exportFieldEnclosure" => '"',
            "exportExtension" => 'csv',
            "exportNamesFirstRow" => 1,
            "exportMultipleValuesSeparator" => '|',
            "allowOverrideexportSettings" => 1,
            "allowexportFilter" => 1,
            "allowexportColumns" => 1


        );
    }

    /**
     * Populate the default config data
     *
     */
    public function __construct() {
        foreach(self::getDefaultData() as $key => $value) {
            $this->$key = $value;
        }
    }


    public function init() {
        $this->wire()->addHook('Page::importTableCsv', $this, 'importCsv'); // not limited to table-csv-import permission because only relevant to front-end
    }


    public function ready() {
        $this->wire()->addHookAfter('InputfieldTable::getConfigInputfields', $this, 'hookAddConfig');
        if($this->wire('user')->hasPermission("table-csv-import")) {
            $this->wire()->addHookAfter('InputfieldTable::render', $this, 'buildTableImportForm');
            $this->wire()->addHookAfter('InputfieldTable::processInput', $this, 'processTableImport');
        }
        if($this->wire('user')->hasPermission("table-csv-export")) {
            $this->wire()->addHookAfter('InputfieldTable::render', $this, 'buildTableExportForm');
        }
    }


    public function hookAddConfig(HookEvent $event) {

        // get existing inputfields from getConfigInputfields
        $inputfields = $event->return;

        $f = $this->wire('modules')->get('InputfieldCheckbox');
        $f->label = __('Allow CSV Import Overwrite Option');
        $f->description = __('If checked, users will have the option to overwrite, not just append, when adding data to table via the Table CSV Import/Export module.');
        $f->notes = __('Non-superusers will also need the "table-csv-import-overwrite" permission.');
        $f->attr('name', 'allow_overwrite');
        $value = $this->wire('fields')->get($event->object->name)->allow_overwrite;
        $f->attr('checked', $this->wire('fields')->get($event->object->name)->allow_overwrite ? 'checked' : '' );
        $f->collapsed = Inputfield::collapsedBlank;
        $inputfields->append($f);

    }


    public function buildTableImportForm(HookEvent $event) {

        // we're interested in page editor only
        if($this->wire('page')->process != 'ProcessPageEdit') return;

        $fieldName = $event->object->name;

        // actual field name is not mangled with a repeater extension
        $actualFieldName = (strpos($fieldName, '_repeater') !== FALSE) ? strstr($fieldName, '_repeater', true) : $fieldName;

        $inputfields = new InputfieldWrapper();

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', $fieldName . '_import_csv');
        $fieldset->label = __("Import CSV");
        $fieldset->description = __("The structure of the CSV must match the table fields. Import will happen on page save. ");
        if($this->wire('fields')->get($actualFieldName)->allow_overwrite != 1 || !$this->wire('user')->hasPermission("table-csv-import-overwrite")) {
            $fieldset->description .= __("Imported data will be appended to existing rows");
        }
        $fieldset->collapsed = Inputfield::collapsedYes;

        if($this->wire('fields')->get($actualFieldName)->allow_overwrite == 1 && $this->wire('user')->hasPermission("table-csv-import-overwrite")) {
            $f = $this->wire('modules')->get("InputfieldSelect");
            $f->name = $fieldName . '_append_overwrite';
            $f->label = __('Append or Overwrite');
            $f->description = __("Determines whether to append new rows, or overwrite all existing rows.");
            $f->required = true;
            $f->addOption("append", __('Append'));
            $f->addOption("overwrite", __('Overwrite'));
            $fieldset->add($f);
        }

        $f = $this->data['allowOverrideimportSettings'] ? $this->wire('modules')->get("InputfieldText") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_import_column_separator';
        $f->label = __('Columns separated with');
        $f->description = __("If you want to paste directly from Excel, use 'tab' separated.");
        $f->notes = __('For tab separated, enter: tab');
        $f->value = $this->data['importFieldSeparator'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->data['allowOverrideimportSettings'] ? $this->wire('modules')->get("InputfieldText") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_import_column_enclosure';
        $f->label = __('Column enclosure');
        $f->value = $this->data['importFieldEnclosure'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->data['allowOverrideimportSettings'] ? $this->wire('modules')->get("InputfieldCheckbox") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_convert_decimals';
        $f->label = __('Convert comma decimals to dots.');
        $f->notes = __('eg. 123,45 is converted to 123.45');
        $f->attr('checked', ($this->data['importConvertDecimals']) ? 'checked' : '');
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->data['allowOverrideimportSettings'] ? $this->wire('modules')->get("InputfieldCheckbox") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_import_names_first_row';
        $f->label = __('Ignore the first row');
        $f->notes = __('Use this if the first row is column names');
        $f->attr('checked', ($this->data['importIgnoreFirstRow']) ? 'checked' : '');
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->data['allowOverrideimportSettings'] ? $this->wire('modules')->get("InputfieldText") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_import_multiple_values_separator';
        $f->label = __('Multiple values separated with');
        $f->notes = __('Default is | Other useful options include \r for new lines.');
        $f->value = $this->data['importMultipleValuesSeparator'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldTextarea");
        $f->name = $fieldName . '_csv_data';
        $f->label = __('Paste in CSV Data');
        $f->notes = __('Be sure you match the settings above to the format of your data');
        $f->collapsed = Inputfield::collapsedYes;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldMarkup");
        $f->label = __('Upload CSV File');
        $f->name = $fieldName . '_csv_upload';
        $f->value = "<input name='".$fieldName."_csv_file' type='file' />";
        $f->notes = __("File must have .csv, .tsv, or .txt extension\nBe sure you match the settings above to the format of your data");
        $f->collapsed = Inputfield::collapsedYes;
        $fieldset->add($f);

        $inputfields->add($fieldset);

        $event->return = $event->return . '<br />' . $inputfields->render();
    }


    public function buildTableExportForm(HookEvent $event) {

        // we're interested in page editor only
        if($this->wire('page')->process != 'ProcessPageEdit') return;

        $fieldName = $event->object->name;

        // actual field name is not mangled with a repeater extension
        $actualFieldName = (strpos($fieldName, '_repeater') !== FALSE) ? strstr($fieldName, '_repeater', true) : $fieldName;

        //get actual page, considering it might be a repeater
        if($actualFieldName != $fieldName) $repeaterId = str_replace($actualFieldName . '_repeater', '', $fieldName);
        if(isset($repeaterId)) {
            $p = $this->wire('pages')->get($repeaterId);
        }
        else {
            $p = $this->wire('process')->getPage();
        }

        $conf = $this->getModuleInfo();
        $version = (int) $conf['version'];
        $this->wire('config')->scripts->add($this->wire('config')->urls->TableCsvImportExport . "TableCsvImportExport.js?v={$version}");

        $inputfields = new InputfieldWrapper();

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'export_csv');
        $fieldset->label = __("Export CSV");
        $fieldset->description = __("Export the content of this table to a CSV file");
        $fieldset->collapsed = Inputfield::collapsedYes;

        $f = $this->data['allowOverrideexportSettings'] ? $this->wire('modules')->get("InputfieldText") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_export_column_separator';
        $f->label = __('Columns separated with');
        $f->notes = __('For tab separated, enter: tab');
        $f->value = $this->data['exportFieldSeparator'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->data['allowOverrideexportSettings'] ? $this->wire('modules')->get("InputfieldText") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_export_column_enclosure';
        $f->label = __('Column enclosure');
        $f->value = $this->data['exportFieldEnclosure'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->data['allowOverrideexportSettings'] ? $this->wire('modules')->get("InputfieldText") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_export_extension';
        $f->label = __('File extension');
        $f->value = $this->data['exportExtension'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->data['allowOverrideexportSettings'] ? $this->wire('modules')->get("InputfieldCheckbox") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_export_names_first_row';
        $f->label = __('Put column names in the first row');
        $f->attr('checked', ($this->data['exportNamesFirstRow']) ? 'checked' : '' );
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->data['allowOverrideexportSettings'] ? $this->wire('modules')->get("InputfieldText") : $this->wire('modules')->get("InputfieldHidden");
        $f->name = $fieldName . '_export_multiple_values_separator';
        $f->label = __('Multiple values separated with');
        $f->notes = __('Default is | Other useful options include \r for new lines when importing into Excel.');
        $f->value = $this->data['exportMultipleValuesSeparator'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        if($this->data['allowexportFilter']) {
            $f = $this->wire('modules')->get("InputfieldSelector");
            $f->name = $fieldName . '_table_rows_selector';
            $f->label = __('Table rows selector');
            $f->description = __('Optional selector to limit table rows. Leave empty to select all rows.');
            $f->notes = __("These selectors will override any filters entered in the table 'Find' interface above.");
            $f->counter = false;
            $f->limitFields = array("$fieldName.");
            $f->showFieldLabels = 1;
            $f->addLabel = __('Add Column Filter');
            $f->columnWidth = 100;
            $fieldset->add($f);
        }

        if($this->data['allowexportColumns']) {
            $f = $this->wire('modules')->get("InputfieldAsmSelect");
            $f->name = $fieldName . '_export_columns';
            $f->label = __('Columns / Order to Export');
            $i=1;
            $columns = array();
            foreach($p->$actualFieldName->columns as $col) {
                if($col['name']) {
                    if(strpos($col['type'], 'page') !== false) {
                        $sp = $this->wire('pages')->findOne($col['selector']);
                        $f->addOption($col['name'] . '.id', ($p->$actualFieldName->getLabel($i) ?: $col['name']) . '.id');
                        foreach($sp->template->fields as $subfield) {
                            $f->addOption($col['name'] . '.' . $subfield, ($p->$actualFieldName->getLabel($i) ?: $col['name']) . '.' . $subfield);
                        }
                        $columns[] = $col['name'] . '.title';
                    } else {
                        $f->addOption($col['name'], $p->$actualFieldName->getLabel($i) ?: $col['name']);
                        $columns[] = $col['name'];
                    }

                }
                $i++;
            }
            $f->value = $columns;
            $fieldset->add($f);
        }

        $f = $this->wire('modules')->get("InputfieldButton");
        $f->name = $fieldName . '_export_button';
        $f->value = $this->_x('Export as CSV', 'button');
        $f->attr('class', 'ui-button ui-widget ui-corner-all ui-state-default export_csv');
        $f->attr('data-pageid', $p->id);
        $f->attr('data-fieldname', $fieldName);
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldMarkup");
        $f->attr('name', 'tableExportIframe');
        $f->collapsed = Inputfield::collapsedYes;
        $f->value = "<iframe id='download' src=''></iframe>";
        $fieldset->add($f);

        $inputfields->add($fieldset);

        $event->return = $event->return . '<br />' . $inputfields->render();
    }


    public function processTableImport(HookEvent $event) {

        $fieldName = $event->object->name;

        //actual field name is not mangled with a repeater extension
        $actualFieldName = (strpos($fieldName, '_repeater') !== FALSE) ? strstr($fieldName, '_repeater', true) : $fieldName;

        // get table field object
        $fieldType = $this->wire('fields')->get($actualFieldName)->type;

        if($fieldType != 'FieldtypeTable') return;

        $this->wire('session')->fieldName = $fieldName;
        $csv_filename = $this->wire('session')->fieldName . '_csv_file';

        if($this->wire('input')->post->{$fieldName . '_csv_data'} == '' && $_FILES[$csv_filename]["name"] == '') return;

        //CSV file upload
        if(isset($_FILES[$csv_filename]) && $_FILES[$csv_filename]["name"] !== '') {

            $csv_file_extension = pathinfo($_FILES[$csv_filename]["name"], PATHINFO_EXTENSION);

            if($csv_file_extension == 'csv' || $csv_file_extension == 'txt' || $csv_file_extension == 'tsv') {
                $this->wire('session')->{$fieldName . '_csv_data'} = file_get_contents($_FILES[$csv_filename]["tmp_name"]);
            }
            else{
                $this->wire()->error($this->_("That is not an allowed file extension for a CSV import. Try again with a .csv, .tsv, or .txt file"));
            }

            unlink($_FILES[$csv_filename]["tmp_name"]);
        }

        // CSV pasted in
        if($this->wire('input')->post->{$fieldName . '_csv_data'} != '') $this->wire('session')->{$fieldName . '_csv_data'} = $this->wire('input')->post->{$fieldName . '_csv_data'};

        // Import
        $this->importCsv();

    }


    public function importCsv($event = NULL) {

        set_time_limit(3600 * 12);

        if(!is_null($event)) {
            $translatedOptions = array(
                'delimiter' => $this->data['importFieldSeparator'],
                'enclosure' => $this->data['importFieldEnclosure'],
                'multipleValuesSeparator' => $this->data['importMultipleValuesSeparator'],
                'namesFirstRow' => $this->data['importNamesFirstRow'],
                'overwrite' => 'append'
            );
            $options = $event->arguments(2);
            $options = array_merge($translatedOptions, $options);
        }

        $initial_auto_detect_line_endings = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', true);

        $fieldName = !is_null($event) ? $event->arguments[0] : $this->wire('session')->fieldName;

        //actual field name is not mangled with a repeater extension
        $actualFieldName = (strpos($fieldName, '_repeater') !== FALSE) ? strstr($fieldName, '_repeater', true) : $fieldName;

        // get table field object
        $tableField = $this->wire('fields')->get($actualFieldName);

        // get table field type
        $tablefieldtype = $tableField->type;

        //get actual page, considering it might be a repeater
        if($actualFieldName != $fieldName) $repeaterId = str_replace($actualFieldName . '_repeater', '', $fieldName);
        if(isset($repeaterId)) {
            $p = $this->wire('pages')->get($repeaterId);
        }
        else {
            $p = !is_null($event) ? $event->object : $this->wire('process')->getPage();
        }

        $p->of(false);

        $cnt = 0;
        $paginationLimit = $tableField->get('paginationLimit');

        if($paginationLimit) {
            $tableRows = class_exists('TableRows') ? new TableRows($p, $tableField) : new \ProcessWire\TableRows($p, $tableField);
        }
        else {
            $tableRows = $p->$actualFieldName;
        }

        $csvData = !is_null($event) ? $event->arguments(1) : $this->wire('session')->{$fieldName . '_csv_data'};
        unset($this->wire('session')->{$fieldName . '_csv_data'});
        $delimiter = !is_null($event) ? $options['delimiter'] : $this->wire('input')->post->{$fieldName . '_import_column_separator'};
        $enclosure = !is_null($event) ? $options['enclosure'] : $this->wire('input')->post->{$fieldName . '_import_column_enclosure'};
        $convertDecimals = !is_null($event) ? $options['convertDecimals'] : $this->wire('input')->post->{$fieldName . '_convert_decimals'};
        if($convertDecimals == 1) $convertDecimals = true;
        $namesFirstRow = !is_null($event) ? $options['namesFirstRow'] : $this->wire('input')->post->{$fieldName . '_import_names_first_row'};
        if($namesFirstRow == 'checked') $namesFirstRow = true;
        $importMultipleValuesSeparator = !is_null($event) ? $options['multipleValuesSeparator'] : $this->wire('input')->post->{$fieldName . '_import_multiple_values_separator'};
        $overwrite = !is_null($event) ? $options['overwrite'] : $this->wire('input')->post->{$fieldName . '_append_overwrite'};

        if($overwrite == 'overwrite') {
            if($paginationLimit) {
                $tablefieldtype->deletePageField($p, $tableField);
            } else {
                /*foreach($p->$actualFieldName as $row) {
                    $tableRows->remove($row);
                }*/
                $p->$actualFieldName->removeAll();
            }
            $p->save($actualFieldName);
        }

        // if there is no new line at the end, add one to fix issue if last item in CSV row has enclosures but others don't
        if(substr($csvData, -1) != "\r" && substr($csvData, -1) != "\n") $csvData .= PHP_EOL;

        require_once __DIR__ . '/parsecsv-for-php/parsecsv.lib.php';

        $rows = new parseCSV();
        $rows->encoding('UTF-16', 'UTF-8');
        $rows->heading = $namesFirstRow;
        $rows->delimiter = $delimiter == "tab" ? chr(9) : $delimiter;
        $rows->enclosure = $enclosure;
        $rows->parse($csvData);

        $i=0;
        foreach($p->$actualFieldName->columns as $subfield) {
            $subfieldNames[$i] = $subfield['name']; //populate array of column/field names indexed in order so they can be used later to populate table
            $i++;
        }

        foreach($rows->data as $data) {

            // this handles populating missing columns from the end of the CSV with a null
            while(count($data) < count($subfieldNames)) {
                array_push($data, null);
            }

            $tableEntry = array();

            $c=1;
            foreach($data as $subfieldKey => $fieldValue) {

                // if there are more columns in the CSV than columns in the table, skip the rest of this row
                if($c > count($subfieldNames)) break;

                // the $subfieldKey will often be the field name based on the first row of the CSV, but this might be wrong,
                // so set it to the numeric key of the field so we can grab from $subfieldNames array
                $subfieldKey = $c-1;

                $currentColumnType = 'col'.$c.'type';
                $currentColumnSelector = 'col'.$c.'selector';
                $fieldType = $this->wire('fields')->$actualFieldName->$currentColumnType;
                $fieldSelector = $this->wire('fields')->$actualFieldName->$currentColumnSelector;

                if($fieldType == 'pageSelect' || $fieldType == 'pageRadios') {
                    $tableEntry[$subfieldNames[$subfieldKey]] = $this->wire('pages')->get("{$fieldSelector}, title={$fieldValue}")->id;
                }
                elseif($fieldType == 'pageSelectMultiple' || $fieldType == 'pageAsmSelect' || $fieldType == 'pageCheckboxes') {
                    foreach(explode($importMultipleValuesSeparator, $fieldValue) as $title) {
                        $tableEntry[$subfieldNames[$subfieldKey]][] = $this->wire('pages')->get("{$fieldSelector}, title={$title}")->id;
                    }
                }
                elseif($fieldType == 'selectMultiple') {
                    $tableEntry[$subfieldNames[$subfieldKey]] = explode($importMultipleValuesSeparator, $fieldValue);
                }
                else {
                    $tableEntry[$subfieldNames[$subfieldKey]] = $convertDecimals == true ? $this->convertDecimals($fieldValue) : $fieldValue;
                }

                $c++;
            }

            $item = $tableRows->new($tableEntry);
            $tableRows->add($item);
            if($paginationLimit && ++$cnt >= $paginationLimit) {
                $tablefieldtype->savePageFieldRows($p, $tableField, $tableRows);
                $tableRows = class_exists('TableRows') ? new TableRows($p, $tableField) : new \ProcessWire\TableRows($p, $tableField);
                $cnt = 0;
            }
        }

        if($paginationLimit) {
            if($cnt) $tablefieldtype->savePageFieldRows($p, $tableField, $tableRows);
        } else {
            $p->set($actualFieldName, $tableRows);
            $p->save($actualFieldName);
        }

        ini_set('auto_detect_line_endings', $initial_auto_detect_line_endings);

    }


    private function convertDecimals($value) {
        // is the first character a number ?
        if(ctype_digit(substr($value, 0, 1))) {
            // 123,45 -> $match[0] else match is NULL
            preg_match('/^\d+,\d+$/', $value, $match);
            // if 123,45 convert it to 123.45,
            return count($match) && $match[0] ? str_replace(',', '.', $match[0]) : $value;
        }
        else{
            return $value;
        }
    }


    /**
     * Return an InputfieldsWrapper of Inputfields used to configure the class
     *
     * @param array $data Array of config values indexed by field name
     * @return InputfieldsWrapper
     *
     */
    public function getModuleConfigInputfields(array $data) {

        $data = array_merge(self::getDefaultData(), $data);

        $wrapper = new InputfieldWrapper();

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'import_settings');
        $fieldset->label = __("Import settings");
        $wrapper->add($fieldset);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'importFieldSeparator';
        $f->label = __('CSV fields separated with');
        $f->description = __("If you want to paste directly from Excel, use 'tab' separated.");
        $f->notes = __('For tab separated, enter: tab');
        $f->value = $data['importFieldSeparator'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'importFieldEnclosure';
        $f->label = __('CSV field enclosure');
        $f->value = $data['importFieldEnclosure'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->name = 'importConvertDecimals';
        $f->label = __('Convert comma decimals to dots.');
        $f->notes = __('eg. 123,45 is converted to 123.45');
        $f->attr('checked', ($data['importConvertDecimals']) ? 'checked' : '' );
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'importNamesFirstRow');
        $f->label = __('CSV ignore the first row');
        $f->description = __('Use this if the first row contains column/field labels.');
        $f->attr('checked', ($data['importNamesFirstRow']) ? 'checked' : '' );
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'importMultipleValuesSeparator');
        $f->label = __('Multiple values separator');
        $f->description = __('Separator for multiple values like Page fields, etc.');
        $f->notes = __('Default is | Other useful options include \r for new lines.');
        $f->value = $data['importMultipleValuesSeparator'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'allowOverrideimportSettings');
        $f->label = __('Allow users to override CSV import settings');
        $f->attr('checked', ($data['allowOverrideimportSettings']) ? 'checked' : '' );
        $fieldset->add($f);

        $fieldset = $this->wire('modules')->get("InputfieldFieldset");
        $fieldset->attr('id', 'export_settings');
        $fieldset->label = __("Export settings");
        $wrapper->add($fieldset);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'exportFieldSeparator';
        $f->label = __('CSV fields separated with');
        $f->notes = __('For tab separated, enter: tab');
        $f->value = $data['exportFieldSeparator'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'exportFieldEnclosure';
        $f->label = __('CSV field enclosure');
        $f->value = $data['exportFieldEnclosure'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->name = 'exportExtension';
        $f->label = __('File extension');
        $f->value = $data['exportExtension'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'exportNamesFirstRow');
        $f->label = __('Column labels');
        $f->label2 = __('Put column names in the first row');
        $f->attr('checked', ($data['exportNamesFirstRow']) ? 'checked' : '' );
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldText");
        $f->attr('name', 'exportMultipleValuesSeparator');
        $f->label = __('Multiple values separator');
        $f->description = __('Separator for multiple values like Page fields, files/images, multiplier, etc.');
        $f->notes = __('Default is | Other useful options include \r for new lines when importing into Excel.');
        $f->value = $data['exportMultipleValuesSeparator'];
        $f->columnWidth = 20;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'allowOverrideexportSettings');
        $f->label = __('Allow users to override CSV export settings');
        $f->attr('checked', ($data['allowOverrideexportSettings']) ? 'checked' : '' );
        $f->columnWidth = 33;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'allowexportFilter');
        $f->label = __('Allow users to filter rows for export by column values');
        $f->description = __('This is an InputfieldSelector interface added in addition to the standard Table "Find" functionality. This allows for more detailed searches.');
        $f->attr('checked', ($data['allowexportFilter']) ? 'checked' : '' );
        $f->columnWidth = 34;
        $fieldset->add($f);

        $f = $this->wire('modules')->get("InputfieldCheckbox");
        $f->attr('name', 'allowexportColumns');
        $f->label = __('Allow users to determine columns to export, and their order.');
        $f->attr('checked', ($data['allowexportColumns']) ? 'checked' : '' );
        $f->columnWidth = 33;
        $fieldset->add($f);

        return $wrapper;

    }

    public function ___install() {
        $module = 'TableCsvImportExport';
        $this->wire('modules')->saveModuleConfigData($module, $this->data);
    }

}