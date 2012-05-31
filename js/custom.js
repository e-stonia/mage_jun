function isInt(x) {
    var y=parseInt(x);
    if (isNaN(y)) return false;
    return x==y && x.toString()==y.toString();
}

$(document).ready(function(){

    /*$('.add').bind('click',function(){
    var value = parseInt($(this).parent().parent().find('input').val()); 
    if (isNaN(value))
        value = 0;
    
    $(this).parent().parent().find('input').val(value+1);
}); 
*/

    $('.add').bind('click',function(){
        addToCart(
            $(this).parent().find('input[name="product_id"]').val(),
            $(this).parent().find('input[name="qty"]').val()
            );   
    }); 
    $('.filter_a').bind('change',function(){
        document.location = $(this).find(":selected").attr('url');
    });

    $('.items tr').hover(
        function(e){
            $(this).find('td').addClass('active'); 
        },
        function(e){
            $(this).find('td').removeClass('active')
        }
        );
    
    $('.himage').parent().hover(
        function(e){
            var top = e.pageY;
            if(($(window).height() - top) > 265){
            //        top = 265;
            }else{
                top = top - 265;
            }
            $(this).find('.himage').fadeIn("fast");
            $(this).find('.himage').css('top', top +"px");
            $(this).find('.himage').css('left',e.pageX+"px");
        },
        function(){
            $(this).find('.himage').fadeOut(50);
        }

        );

});


var url = "http://tellimine.jungent.ee/";

function addToCart(product_id, count){
    if (!count)
        var count = 0; 
    
    if(count > 1){
        if(count%2 == 0){
            for(var i = 0 ; i <= 1 ; i++){
                var needurl = "/checkout/cart/add/";
                var data = Object();
                data.product = product_id;
                data.qty = count/2;
                $('#cart_current').animate({
                    opacity:0.4
                },200);
                $.ajax({
                    type:"POST",
                    url:needurl,
                    data:data,
                    success:function(Response){
                        $('#cart_current').animate({
                            opacity:1
                        },200);
                        $('#cart_current').html(Response);
                    }
                });
            }
        }else{
            var needurl = "/checkout/cart/add/";
            var data = Object();
            data.product = product_id;
            data.qty = count - 1;
            $('#cart_current').animate({
                opacity:0.4
            },200);
            $.ajax({
                type:"POST",
                url:needurl,
                data:data,
                success:function(Response){
                    $('#cart_current').animate({
                        opacity:1
                    },200);
                    $('#cart_current').html(Response);
                }
            });
                
                
            var needurl = "/checkout/cart/add/";
            var data = Object();
            data.product = product_id;
            data.qty = 1;
            $('#cart_current').animate({
                opacity:0.4
            },200);
            $.ajax({
                type:"POST",
                url:needurl,
                data:data,
                success:function(Response){
                    $('#cart_current').animate({
                        opacity:1
                    },200);
                    $('#cart_current').html(Response);
                }
            });
        }
    }else{
        var needurl = "/checkout/cart/add/";
        var data = Object();
        data.product = product_id;
        data.qty = count - 1;
        $('#cart_current').animate({
            opacity:0.4
        },200);
        $.ajax({
            type:"POST",
            url:needurl,
            data:data,
            success:function(Response){
                $('#cart_current').animate({
                    opacity:1
                },200);
                $('#cart_current').html(Response);
            }
        });
    }
    
    var needurl = "/checkout/cart/getTotal/";
    $.ajax({
        type:"POST",
        url:needurl,
        success:function(Response){
            $('#totalInCart').html(number_format(Response, 2, ',', ' '));
        }
    });
}



function number_format(number, decimals, dec_point, thousands_sep) {
    number = (number+'').replace(',', '').replace(' ', '');
    var n = !isFinite(+number) ? 0 : +number, 
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function (n, prec) {
        var k = Math.pow(10, prec);
        return '' + Math.round(n * k) / k;
    };
    // Fix for IE parseFloat(0.55).toFixed(0) = 0;
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);

}


function updateCart(object, product_id){
         
    var price = $(object).parent().find('[name="price"]').val();
    var oldQty = $(object).parent().find('[name="qty"]').val();
         
         
         
    var newQty = $(object).val();
    if (isInt(newQty)==false)
    {
        return 0;
    }

    $('#row_total_'+product_id).html(number_format(price*newQty, 2, ',', ' '));
    var total = 0.00;
    $('.rowtotal').each(function(){
        total +=parseFloat($(this).text());
    });
         
    //         $('#totalInCart').html(number_format(total, 2, ',', ' '));
         
    var needurl = "/checkout/cart/updatePost/";
    var data = new Object();
    data["cart"] = new Object();
    data["cart"][product_id] = new Object();
    data["cart"][product_id]['qty'] = newQty;
    
    
     
    $.ajax({
        type:"POST",
        url:needurl,
        data:data,
        success:function(Response){
    
        }
    });

    var needurl = "/checkout/cart/getTotal/";
    $.ajax({
        type:"POST",
        url:needurl,
        success:function(Response){
            $('#totalInCart').html(number_format(Response, 2, ',', ' '));
        }
    });
         
}



function deleteFromCart(product_id){
    var needurl = "/checkout/cart/delete/";
    var data = Object();
    data.id = product_id;
    $('#cart_current').animate({
        opacity:0.4
    },200);
    $.ajax({
        type:"POST",
        url:needurl,
        data:data,
        success:function(Response){
            $('#cart_current').animate({
                opacity:1
            },200);
            $('#cart_current').html(Response);
        }
    });
    

    
}