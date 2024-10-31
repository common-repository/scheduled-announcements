
//datepicker

jQuery(function() {

	jQuery( ".datepicker" ).datepicker();

});

//confirm record delete

jQuery(function () {

	jQuery(".confirmdelete").click(function () { 

		//return confirm('Deleting is final! Consider saving it as a draft instead. OK to Delete?') 

	});

});

//clear fields not in use

jQuery(function () {

	jQuery(".displaydate").click(function () { 

		jQuery(".displayweekly").val('');

		jQuery(".displaymonthly").val('');

		jQuery(".displayweekly").css("background-color", "white");

		jQuery(".displaymonthly").css("background-color", "white");

	});

	jQuery(".displayweekly").click(function () { 

		jQuery(".displaymonthly").val('');

		jQuery(".displaydate").val('');

		jQuery(".displaydateCB").prop("checked", false);

		jQuery(".displaydate").css("background-color", "white");

		jQuery(".displaymonthly").css("background-color", "white");

	});

	jQuery(".displaymonthly").click(function () { 

		jQuery(".displaydate").val('');

		jQuery(".displaydateCB").prop("checked", false);

		jQuery(".displayweekly").val('');

		jQuery(".displayweekly").css("background-color", "white");

		jQuery(".displaydate").css("background-color", "white");

	});

});

//visual cue to also select the related fields

jQuery(function () {

	jQuery(".displaydate").click(function () { 

		jQuery(".displaydate").css("background-color", "#ffff66");

		jQuery(".priority").css("background-color", "#ffff66");

	});

	jQuery(".displayweekly").click(function () { 

		jQuery(".displayweekly").css("background-color", "#ffff66");

		jQuery(".priority").css("background-color", "#ffff66");

	});

	jQuery(".displaymonthly").click(function () { 

		jQuery(".displaymonthly").css("background-color", "#ffff66");

		jQuery(".priority").css("background-color", "#ffff66");

	});

});

// when editing existing post, visual cue on load

jQuery(document).ready(function() {
	
	if(jQuery(".displaydate").val()) {
	
		jQuery(".displaydate").css("background-color", "#ffff66");

		jQuery(".priority").css("background-color", "#ffff66");
	
	}

	if(jQuery(".displayweekly").val()) {
	
		jQuery(".displayweekly").css("background-color", "#ffff66");

		jQuery(".priority").css("background-color", "#ffff66");
	
	}
	
	if(jQuery(".displaymonthly").val()) {
	
		jQuery(".displaymonthly").css("background-color", "#ffff66");

		jQuery(".priority").css("background-color", "#ffff66");
	
	}	

});

//clear fields on click

jQuery(function () {

	jQuery(".clearEntries").click(function(){

		jQuery(".displayweekly").val('');

		jQuery(".displaymonthly").val('');

		jQuery(".displaymonthly").val('');

		jQuery(".displaydate").val('');

		jQuery(".displaydateCB").prop("checked", false);

		jQuery(".displaydate").val('');

		jQuery(".displaydateCB").prop("checked", false);

		jQuery(".displayweekly").val('');

		jQuery(".priority").val('');

		jQuery(".displaydate").css("background-color", "white");

		jQuery(".displayweekly").css("background-color", "white");

		jQuery(".displaymonthly").css("background-color", "white");

		jQuery(".priority").css("background-color", "white");

	});

});

// copy shortcode

function jgbltsa_copyShortcode() {
	
	var copyText = document.getElementById("saShortcode");
  
	copyText.select();
	copyText.setSelectionRange(0, 99999); /* For mobile devices */
  
	navigator.clipboard.writeText(copyText.value);
  
	//alert("Copied the text: " + copyText.value);
	document.getElementById("scCopied").textContent="Shortcode copied to clipboard!";
	
}