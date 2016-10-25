$(document).ready(function(){
  $("input:text[name=buckaroo_giropay_bankleitzahl]").closest("div").addClass("giropay_div");
  if(!$("input:radio[name=payment][value=buckaroo3Giropay]").is(':checked'))
  {
    $(".giropay_div").hide();
  }
  $("input:radio[name=payment]").change(function(){
     if($("input:radio[name=payment][value=buckaroo3Giropay]").is(':checked'))
     {
         $(".giropay_div").show();
     }else{
         $(".giropay_div").hide();
     };
  });
  
  $('.moduleRow').click(function(){
     if($("input:radio[name=payment][value=buckaroo3Giropay]").is(':checked'))
     {
         $(".giropay_div").show();
     }else{
         $(".giropay_div").hide();
     };
  });
});

