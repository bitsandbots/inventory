
function suggestion_name() {

     $('#sug_input').keyup(function(e) {

         var formData = {
             'product_name' : $('input[name=title]').val()
         };

         if(formData['product_name'].length >= 1){

           // process the form
           $.ajax({
               type        : 'POST',
               url         : 'ajax.php',
               data        : formData,
               dataType    : 'json',
               encode      : true
           })
               .done(function(data) {
                   //console.log(data);
                   $('#result').html(data).fadeIn();
                   $('#result li').click(function() {

                     $('#sug_input').val($(this).text());
                     $('#result').fadeOut(500);

                   });

                   $("#sug_input").blur(function(){
                     $("#result").fadeOut(500);
                   });

               });

         } else {

           $("#result").hide();

         };

         e.preventDefault();
     });

 }
  $('#sug-form').submit(function(e) {
      var formData = {
          'p_name' : $('input[name=title]').val()
      };
        // process the form
        $.ajax({
            type        : 'POST',
            url         : 'ajax.php',
            data        : formData,
            dataType    : 'json',
            encode      : true
        })
            .done(function(data) {
                //console.log(data);
                $('#product_info').html(data).show();
                total();
                $('.datePicker').datepicker('update', new Date());

            }).fail(function() {
                $('#product_info').html(data).show();
            });
      e.preventDefault();
  });


function suggestion_sku() {

     $('#sug_sku_input').keyup(function(e) {

         var formData = {
             'product_sku' : $('input[name=sku]').val()
         };

         if(formData['product_sku'].length >= 1){

           // process the form
           $.ajax({
               type        : 'POST',
               url         : 'ajax_sku.php',
               data        : formData,
               dataType    : 'json',
               encode      : true
           })
               .done(function(data) {
                   //console.log(data);
                   $('#result').html(data).fadeIn();
                   $('#result li').click(function() {

                     $('#sug_sku_input').val($(this).text());
                     $('#result').fadeOut(500);

                   });

                   $("#sug_sku_input").blur(function(){
                     $("#result").fadeOut(500);
                   });

               });

         } else {

           $("#result").hide();

         };

         e.preventDefault();
     });

 }
  $('#sug-sku-form').submit(function(e) {
      var formData = {
          'p_sku' : $('input[name=sku]').val()
      };
        // process the form
        $.ajax({
            type        : 'POST',
            url         : 'ajax_sku.php',
            data        : formData,
            dataType    : 'json',
            encode      : true
        })
            .done(function(data) {
                //console.log(data);
                $('#product_info').html(data).show();
                total();
                $('.datePicker').datepicker('update', new Date());

            }).fail(function() {
                $('#product_info').html(data).show();
            });
      e.preventDefault();
  });

function suggestion_customer() {

     $('#sug_customer_input').keyup(function(e) {

         var formData = {
             'customer_name' : $('input[name=customer_name]').val()
         };

         if(formData['customer_name'].length >= 1){

           // process the form
           $.ajax({
               type        : 'POST',
               url         : 'ajax_customer.php',
               data        : formData,
               dataType    : 'json',
               encode      : true
           })
               .done(function(data) {
                   //console.log(data);
                   $('#result').html(data).fadeIn();
                   $('#result li').click(function() {

                     $('#sug_customer_input').val($(this).text());
                     $('#result').fadeOut(500);

                   });

                   $("#sug_customer_input").blur(function(){
                     $("#result").fadeOut(500);
                   });

               });

         } else {

           $("#result").hide();

         };

         e.preventDefault();
     });

 }
  $('#sug-customer-form').submit(function(e) {
      var formData = {
          'c_name' : $('input[name=customer_name]').val()
      };
        // process the form
        $.ajax({
            type        : 'POST',
            url         : 'ajax_customer.php',
            data        : formData,
            dataType    : 'json',
            encode      : true
        })
            .done(function(data) {
                //console.log(data);
                $('#customer_info').html(data).show();
                total();
                $('.datePicker').datepicker('update', new Date());

            }).fail(function() {
                $('#customer_info').html(data).show();
            });
      e.preventDefault();
  });



function suggestion_search() {

     $('#sug_search_input').keyup(function(e) {

         var formData = {
             'product_search' : $('input[name=product_search]').val()
         };

         if(formData['product_search'].length >= 1){

           // process the form
           $.ajax({
               type        : 'POST',
               url         : 'ajax_product.php',
               data        : formData,
               dataType    : 'json',
               encode      : true
           })
               .done(function(data) {
                   //console.log(data);
                   $('#result').html(data).fadeIn();
                   $('#result li').click(function() {

                     $('#sug_search_input').val($(this).text());
                     $('#result').fadeOut(500);

                   });

                   $("#sug_search_input").blur(function(){
                     $("#result").fadeOut(500);
                   });

               });

         } else {

           $("#result").hide();

         };

         e.preventDefault();
     });

 }
  $('#sug-search-form').submit(function(e) {
      var formData = {
          'p_search' : $('input[name=product_search]').val()
      };
        // process the form
        $.ajax({
            type        : 'POST',
            url         : 'ajax_product.php',
            data        : formData,
            dataType    : 'json',
            encode      : true
        })
            .done(function(data) {
                //console.log(data);
                $('#product_info').html(data).show();
                total();
                $('.datePicker').datepicker('update', new Date());

            }).fail(function() {
                $('#product_info').html(data).show();
            });
      e.preventDefault();
  });








  function total(){
    $('#product_info input').change(function(e)  {
            var price = +$('input[name=price]').val() || 0;
            var qty   = +$('input[name=quantity]').val() || 0;
            var total = qty * price ;
                $('input[name=total]').val(total.toFixed(2));
    });
  }

  $(document).ready(function() {

    //tooltip
    $('[data-toggle="tooltip"]').tooltip();

    $('.submenu-toggle').click(function () {
       $(this).parent().children('ul.submenu').toggle(200);
    });
    //suggestion for finding product names
    suggestion_name();
    //suggestion for finding product names
    suggestion_sku();
    //suggestion for finding customer names
    suggestion_customer();
    //suggestion for finding product names
    suggestion_search();
    // Callculate total ammont
    total();

    $('.datepicker')
        .datepicker({
            format: 'yyyy-mm-dd',
            todayHighlight: true,
            autoclose: true
        });
  });
