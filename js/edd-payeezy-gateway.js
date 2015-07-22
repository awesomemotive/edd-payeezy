var responseHandler = function(status, response) {
    var $form = jQuery('#edd_purchase_form');
    console.log(status);
    console.log(response);
    if (status != 201) {
        if (response.error && status != 400) {
            var error = response["error"];
            var errormsg = error["messages"];
            var errorcode = JSON.stringify(errormsg[0].code, null, 4);
            var errorMessages = JSON.stringify(errormsg[0].description, null, 4);
            jQuery('#edd-payeezy-payment-errors').html('Error Code:' + errorcode + ', Error Messages:' + errorMessages);
        }
        if (status == 400 || status == 500) {
            jQuery('#edd-payeezy-payment-errors').html('');
            var errormsg = response.Error.messages;
            var errorMessages = "";
            for (var i in errormsg) {
                var ecode = errormsg[i].code;
                var eMessage = errormsg[i].description;
                errorMessages = errorMessages + 'Error Code:' + ecode + ', Error Messages:' + eMessage;
            }

            jQuery('#edd-payeezy-payment-errors').html(errorMessages);
        }
        $form.find('button').prop('disabled', false);
    } else {
        $('#edd-payeezy-payment-errors').html('');
        var result = response.token.value;
        $form.append('Payeezy response - Token value:' + result);
        $form.find('#edd-submit').prop('disabled', false);
    }
};

jQuery(function($) {
    $('#edd_purchase_form').submit(function(e) {

        $('#response_msg').html('');
        $('#edd-payeezy-payment-errors').html('');
 
        var $form = $(this);
        $form.find('#edd-submit').prop('disabled', true);

        Payeezy.setApiKey( edd_payeezy_vars.api_key );
        Payeezy.setJs_Security_Key( edd_payeezy_vars.security_key );
        Payeezy.setTa_token( edd_payeezy_vars.ta_token );
        Payeezy.createToken(responseHandler);
        $('#someHiddenDiv').show();
        return false;
    });
});