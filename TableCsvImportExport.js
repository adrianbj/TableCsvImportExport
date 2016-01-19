$(document).ready(function() {

    //$('.Inputfield_iframe').hide();

    $(document).on('click', '.export_csv', function(){
        $('#download').attr('src', config.urls.admin+
            "setup/table-csv-export/?pid="+
            $(this).attr('data-pageid')+
            "&fn="+$(this).attr('data-fieldname')+
            "&cs="+$("#Inputfield_export_column_separator").val()+
            "&ce="+$("#Inputfield_export_column_enclosure").val()+
            "&ext="+$("#Inputfield_export_extension").val()+
            "&nfr="+$("#Inputfield_export_names_first_row").attr('checked')+
            "&mvs="+$("#Inputfield_export_multiple_values_separator").val()
        );
        return false;
    });
});
