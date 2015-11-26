$(document).ready(function(){
  $("input:text[name=buckaroo_paygarant_firstname]").closest("div").addClass("paygarant_div");
  if(!$("input:radio[name=payment][value=buckaroo3PaymentGuarantee]").is(':checked'))
  {
     $(".paygarant_div").hide();
  };
  $("input:radio[name=payment]").change(function(){
     if($("input:radio[name=payment][value=buckaroo3PaymentGuarantee]").is(':checked'))
     {
         $(".paygarant_div").show();
     }else{
         $(".paygarant_div").hide();
     };
  });
  
  $('.moduleRow').click(function(){
     if($("input:radio[name=payment][value=buckaroo3PaymentGuarantee]").is(':checked'))
     {
         $(".paygarant_div").show();
     }else{
         $(".paygarant_div").hide();
     };
  });
});

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
};


