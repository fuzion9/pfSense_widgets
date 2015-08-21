/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$(function() {
    jQuery( "input[type=submit], a, button" )
      .button()
      .click(function( event ) {
        event.preventDefault();
      });
  });
function requestNmapScan(ip){
    jQuery('input[name=hostname]').val(ip);    
    jQuery('#doNmap').submit();
}
/*
function post(path, parameters) {    
    var form = jQuery('<form></form>');

    form.attr("method", "post");
    form.attr("action", path);

    jQuery.each(parameters, function(key, value) {
        var field = jQuery('<input></input>');

        field.attr("type", "hidden");
        field.attr("name", key);
        field.attr("value", value);

        form.append(field);
    });

    // The form needs to be a part of the document in
    // order for us to be able to submit it.
    jQuery(document.body).append(form);
    form.submit();
}
*/