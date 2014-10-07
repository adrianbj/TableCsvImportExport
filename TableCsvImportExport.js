$(document).ready(function() {

    //$("#PageIDIndicator").remove(); // hack to disable html5 upload which wasn't working. Problem is that it disabled html5 upload for other fields on the page

    $('.Inputfield_iframe').hide();

    $(document).on('click', '.export_csv', function(){
        $('#download').attr('src', config.urls.admin+
            "page/table-csv-export/?pid="+
            $(this).attr('data-pageid')+
            "&fn="+$(this).attr('data-fieldname')+
            "&cs="+$("#Inputfield_export_column_separator").val()+
            "&ce="+$("#Inputfield_export_column_enclosure").val()+
            "&nfr="+$("#Inputfield_export_names_first_row").attr('checked')
        );
        return false;
    });
});