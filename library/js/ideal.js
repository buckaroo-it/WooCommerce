$(document).ready(function(){

  $(".ideal_issuer").parent().addClass("ideal_div");
  
  if(!$("#pmt-buckaroo3Ideal").is(':checked'))
  {
    $(".ideal_div").hide();
  }
  $("input:radio[name=payment]").click(function(){
     if($("#pmt-buckaroo3Ideal").is(':checked'))
     {
         $(".ideal_div").show();
     }else{
         $(".ideal_div").hide();
     };
  });
  
  $('.moduleRow').click(function(){
     if($("#pmt-buckaroo3Ideal").is(':checked'))
     {
         $(".ideal_div").show();
     }else{
         $(".ideal_div").hide();
     };
  });
});

