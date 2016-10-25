var jQueryScriptOutputted = false;
function initJQuery() {
    //if the jQuery object isn't available
    if (typeof(jQuery) == 'undefined') {  
        if (! jQueryScriptOutputted) {
            //only output the script once..
            jQueryScriptOutputted = true;
            
            //output the script (load it from google api)
            document.write('<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>');
        }
        setTimeout("initJQuery()", 50);
    }
}
initJQuery();

function bankaccountcheck( bankaccount)
{
    if ( !isNaN(bankaccount))
    {
        if (bankaccount.length < 8 && bankaccount.length > 0)
            return true;
        else if(bankaccount.length  === 9)
        {
            var check = 0; 
            for (var i = 0; i < 9; i++){
                check = check + (bankaccount.charAt(i) * (9-i)); 
            }
            return (check % 11) === 0;
        }
    }
    return false;
}