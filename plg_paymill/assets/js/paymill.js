// Paymill.js

$(document).ready(function(){
	// uncheck radio buttons
	var or = document.getElementsByName("virtuemart_paymentmethod_id");
	for (var i = 0; i < or.length; i++) {
		or[i].checked = false;
	}
	//modifyForCC();

	$('input:radio[name=virtuemart_paymentmethod_id]').click(function() {
		modifyForCC();
	});

});

function modifyForCC() {
	var cc_id = $('#vm_paymentmethod_id').val();
	if($('input:radio[name=virtuemart_paymentmethod_id]:checked').val() == cc_id) {
		$('#paymentForm').submit(function(e) {
			e.preventDefault();
			submitPayment();
		});
	}
	else {
		$('#paymentForm').submit(function(e) {
			e.preventDefault();
			document.choosePaymentRate.submit();
		});
	}
}

function returnResponse(error, result) {
	var root = $('#root_url').val();
	if (error) {
		$("#paymentErrors").html(error.apierror);
        $("#loadergif").css("display", "none");
	}
	else {
		$("#paymentErrors").html("");
		var token = result.token;
		$('#pm_email').val($('#email_field').val());

		$("#paymillTokenField").val(token);
		if($('#formerror').val() != 1) {
	        $.ajax({
	        	url: 		root + "index.php?option=com_paymillapi&task=saveToken&token=" + token,
	        	complete:   function(result) {
				        		$('#loader').css('display', 'none');
				        		$('#result').css('display', 'block');

			        			document.choosePaymentRate.submit();
				        }
	        });
	   }
	}
}

function checkBridge() {
	var do_check = true;
	var success = false;
	var pmf = document.getElementsByTagName('iframe')[0];

    if(!do_check) return;
    if(pmf != undefined && pmf.src != undefined && pmf.src.length > 0) {
        success = true;
        do_check = false;
    } else {
        setTimeout('checkBridge()', 1000);

        setTimeout(function() {
        	do_check = false;
        	document.getElementById('iframeerror').style.display = 'none';
        }, 5000);
    }
}

function submitPayment() {
	//show loader gif
	$("#loadergif").css("display", "inline");
	$("#loadergif").insertBefore("button.vm-button-correct:submit");
	$("button.vm-button-correct:submit").attr("disabled", true);
	$("#loader").css("display", "block");

	if (false == paymill.validateCardNumber($("#cardnumber").val())) {
		$("#loader").css("display", "none");
		$("#paymentErrors").html("<span style='color: #ff0000'>Ungültige Kartennummer</span>");
		$("button.vm-button-correct:submit").removeAttr("disabled");
		$("#loadergif").css("display", "none");
		return false;
	}
	if (false == paymill.validateExpiry($("#cardExpMonth").val(), $("#cardExpYear").val())) {
		$("#loader").css("display", "none");
		$("#paymentErrors").html("<span style='color: #ff0000'>Ungültiges Gültigkeitsdatum</span>");
		$("button.vm-button-correct:submit").removeAttr("disabled");
		$("#loadergif").css("display", "none");
		return false;
	}
	if (false == paymill.validateCvc($("#cardCvc").val())) {
		$("#loader").css("display", "none");
		$("#paymentErrors").html("<span style='color: #ff0000'>Ungültige CVC</span>");
		$("button.vm-button-correct:submit").removeAttr("disabled");
		$("#loadergif").css("display", "none");
		return false;
	}
	paymill.createToken({
		amount_int:$("pm_amount").val(),
		currency:"eur",
		number:$("#cardnumber").val(),
		exp_month:$("#cardExpMonth").val(),
		exp_year:$("#cardExpYear").val(),
		cvc:$("#cardCvc").val(),
		cardholdername:$("#cardholdername").val()
		}, returnResponse);

    return false;
}
