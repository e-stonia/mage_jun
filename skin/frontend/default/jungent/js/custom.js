$(document).ready(function(){
$('.add').bind('click',function(){
    var value = parseInt($(this).parent().parent().find('input').val()); 
    if (isNaN(value))
        value = 0;
    
    $(this).parent().parent().find('input').val(value+1);
}); 

$('.items tr').hover(
    function(e){$(this).find('td').addClass('active'); 
    },
    function(e){$(this).find('td').removeClass('active')}
    );
    
$('.himage').parent().hover(
function(){
    
    $(this).find('.himage').fadeIn("fast");
    $(this).find('.himage').css('top',e.pageY+"px");
    $(this).find('.himage').css('left',e.pageX+"px");
},
function(){
    $(this).find('.himage').fadeOut(50);
}

);

});