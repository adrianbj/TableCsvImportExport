Table CSV Import / Export
==========================

Processwire module to add rows to a Table field by importing CSV formatted content.
Also provides an export button to download the contents of the table in CSV.

Front-end export of a table field to CSV can be achieved with the exportCsv() method:
```
<?php
// export as CSV if csv_export=1 is in url
if($input->csv_export==1){
    $modules->get('ProcessTableCsvExport');
    $page->fields->tablefield->exportCsv('tab', '"', 'tsv', true); // delimiter, enclosure, file extension, names in first row
}
// display content of template with link to same page with appended csv_export=1
else{
    include("./head.inc");

    echo $page->tablefield->render();
    echo "<a href='./?csv_export=1'>Export Table as CSV</a>";

    include("./foot.inc");
}
```

Front-end import can be achieved with the importCsv() method:
```
// data, delimiter, enclosure, convert decimals, ignore first row, append or overwrite
$page->fields->tablefield->importCsv($csvData, ';', '"', true, false, 'append');
```


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