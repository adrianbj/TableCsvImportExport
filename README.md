Table CSV Import / Export
==========================

Processwire module for admin and front-end importing and exporting of CSV formatted content for Profields Table fields.

https://processwire.com/talk/topic/7905-profields-table-csv-importer-exporter/

Access to the admin import/export for non-superusers is controlled by two automatically created permissions: table-csv-import and table-csv-export

Another permission (table-csv-import-overwrite) allows you to control access to the overwrite option when importing.

The overwrite option is also controlled at the field level. Go to the table field's Input tab and check the new "Allow overwrite option" if you want this enabled at all for the specific field.

Front-end export of a table field to CSV can be achieved with the exportCsv() method:
```
<?php
// export as CSV if csv_export=1 is in url
if($input->get->csv_export==1){
   $modules->get('ProcessTableCsvExport'); // load module
   // delimiter, enclosure, file extension, multiple values separator, names in first row
   $page->fields->tablefield->exportCsv(',', '"', 'csv', ',', true);
}
// display content of template with link to same page with appended csv_export=1
else{
   include("./head.inc");

   echo $page->tablefield->render(); //render table - not necessary for export
   echo "<a href='./?csv_export=1'>Export Table as CSV</a>"; //link to initiate export

   include("./foot.inc");
}
```

Front-end import can be achieved with the importCsv() method:
```
$modules->get('TableCsvImportExport'); // load module
// data, delimiter, enclosure, convert decimals, ignore first row, multiple values separator, append or overwrite
$page->fields->tablefield->importCsv($csvData, ',', '"', false, true, ',', 'append');
```

####Support forum:
https://processwire.com/talk/topic/7905-profields-table-csv-importer-exporter/

## License

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

(See included LICENSE file for full license text.)
