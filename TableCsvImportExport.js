/**
 * Given search filter columns, build a selector string reflecting that search
 *
 * @param $cols
 * @returns {string}
 *
 */
function buildSelector($cols) {

    var operators = ['=', '>=', '<=', '!=', '%=', '<', '>', '^=', '$=', '*=', '~=', '!%='];
    var selector = '';

    $cols.each(function() {

        var $inputs = $(this).find(':input');

        $inputs.each(function() {

            var $input = $(this);
            var val = $.trim($input.val());

            if(!val.length) return;

            // allow quotes to indicate "blank", but don't allow double quotes otherwise
            if(val != '""' && val.indexOf('"') > -1) return;

            var type = $input.is('select') ? 'select' : $input.attr('type');
            var op = '';
            var quoteVal = val.indexOf(',') > -1 || (val.indexOf("'") > -1 && val != "''");
            var name = $input.attr('name');
            var isArray = false;

            if(!name) return;
            name = name.replace('__find-', '');

            if(name.indexOf('[') > -1) {
                name = name.substring(0, name.length - 2);
                isArray = true;
            }

            if(isArray && val.indexOf(',') > -1) {
                if(val.indexOf(',') === 0) val = val.substring(1);
                val = val.split(',').join('|');
            }

            if(val.indexOf('=') > -1 || val.indexOf('<') === 0 || val.indexOf('>') === 0) {
                for(var n = 0; n < operators.length; n++) {
                    if(val.indexOf(operators[n]) === 0) {
                        op = operators[n];
                        val = val.substring(op.length);
                        consoleLog('name=' + name + ', op=' + op + ', val=' + val);
                        break;
                    }
                }
            }

            if(type == 'checkbox' || type == 'radio') {
                if(!$input.is(":checked")) return;
            } else if(type == 'number') {
                quoteVal = false;
            } else if(type == 'select') {
                // ok, fallback
            } else if(type == 'date' || type == 'datetime' || $input.attr('data-dateformat')) {
                // ok, fallback
            } else {
                if(!op.length) op = '%=';
            }

            // this is fallback
            if(!op.length) op = '=';

            if(quoteVal && val != '""' && val != "''") val = '"' + val + '"';
            var s = name + op + val + ',';
            if(selector.indexOf(s) == -1) selector += s;
        });
    });

    if(selector.length) selector = selector.substring(0, selector.length - 1);

    return selector;
}

$(document).ready(function() {

    //$('.Inputfield_iframe').hide();

    $(document).on('click', '.export_csv', function(){

        var $input = $(this);
        var $table = $('.Inputfield_'+$(this).attr('data-fieldname')).find('.InputfieldTableSearch');
        var $cols = $table.find('td');
        var selector = buildSelector($cols);

        $('#download').attr('src', config.urls.admin+"setup/table-csv-export/?"+
            "pid="+$(this).attr('data-pageid')+
            "&fn="+$(this).attr('data-fieldname')+
            "&cs="+$("#Inputfield_"+$(this).attr('data-fieldname')+"_export_column_separator").val()+
            "&ce="+$("#Inputfield_"+$(this).attr('data-fieldname')+"_export_column_enclosure").val()+
            "&ext="+$("#Inputfield_"+$(this).attr('data-fieldname')+"_export_extension").val()+
            "&nfr="+$("#Inputfield_"+$(this).attr('data-fieldname')+"_export_names_first_row").attr('checked')+
            "&mvs="+$("#Inputfield_"+$(this).attr('data-fieldname')+"_export_multiple_values_separator").val()+
            "&cte="+$("#Inputfield_"+$(this).attr('data-fieldname')+"_export_columns").val()+
            "&filter="+encodeURIComponent(selector)+","
        );
        return false;
    });

    // hack to make export button display when field visibility is Locked (not editable)
    // https://github.com/processwire/processwire-issues/issues/218
    $("[class$=export_button]").css("display","block");

});
