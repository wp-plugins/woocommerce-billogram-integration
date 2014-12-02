function sync_orders() {
    var data = {
        action: 'sync_orders'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många ordrar som ska exporteras. <br>Ett meddelande visas på denna sida när synkroniseringen är klar. Lämna ej denna sida, då avbryts exporten!');
    jQuery(".order_load").show();
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
        jQuery(".order_load").hide();
    });
}

function fetch_contacts() {
    var data = {
        action: 'fetch_contacts'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många kunder som ska importeras. <br>Ett meddelande visas på denna sida när synkroniseringen är klar. Lämna ej denna sida, då avbryts importen!');
    jQuery(".customer_load").show();
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
        jQuery(".customer_load").hide();
    });
}

function initial_sync_products() {
    var data = {
        action: 'initial_sync_products'
    };
    alert('Synkroniseringen kan ta lång tid beroende på hur många produkter som ska exporteras. <br>Ett meddelande visas på denna sida när synkroniseringen är klar. Lämna ej denna sida, då avbryts exporten!');
    jQuery(".product_load").show();
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
        jQuery(".product_load").hide();
    });
}

function send_support_mail() {
    var data = jQuery('form#support').serialize();
    jQuery.post(ajaxurl, data, function(response) {
        alert(response);
    });
}