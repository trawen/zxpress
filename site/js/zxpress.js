(function($) {
    $.fn.removeClassWild = function(mask) {
        return this.removeClass(function(index, cls) {
            var re = mask.replace(/\*/g, '\\S+');
            return (cls.match(new RegExp('\\b' + re + '', 'g')) || []).join(' ');
        });
    };
})(jQuery);

$(document).ready(function() {

//LOADING POPUP
	//Click the button event!
	$("#button").click(function(){
		//centering with css
		centerPopup();
		//load popup
		loadPopup();
	});
				
	//CLOSING POPUP
	//Click the x event!
	$("#popupContactClose").click(function(){
		disablePopup();
	});
	//Click out event!
	$("#backgroundPopup").click(function(){
		disablePopup();
	});
	//Press Escape event!
	$(document).keypress(function(e){
		if(e.keyCode==27 && popupStatus==1){
			disablePopup();
		}
	});

littleTIPdelay	= 1;
littleTIPfade	= 1;
littleTIPx		= 15;
littleTIPy		= 15;
	
var elT = $('.[title]');			

elT.mouseover(function(){
	var elemlT = $(this).attr('title');
	var src = $(this).attr('src');		
	this.tip = this.title;					
	this.title = "";						
						
	if (src ) {
		if (src.indexOf("creens") > 0) {	
			$('<div id="littleTIP"><div id="littleTIPtext" style="border: 8px solid #242321"><img src="'+src+'" width=256></div></div>').appendTo('body');
		}
		
	}
	else {
	    $('<div id="littleTIP"><div id="littleTIPtext">"'+elemlT+'"</div></div>').appendTo('body');	
	}
		
	$('#littleTIP').delay(littleTIPdelay).fadeIn(littleTIPfade).css({
			zIndex: 10000000,
			position: 'absolute',
			display: 'block',
			width: 'auto',
			height: 'auto'
	});
});

elT.mousemove(function(e){
		var BwiWi = $(window).width();					
		var BwiHe = $(window).height();					
		var BwHs = $(window).scrollLeft();				
		var BwVs = $(window).scrollTop();				
		var liTIP = $('#littleTIP');					
		var lTwi = $('#littleTIP').width();				
		var lThe = $('#littleTIP').height();			

		if (BwiWi + BwHs < e.pageX + lTwi + littleTIPx)	
			{
			liTIP.css("left", e.pageX - lTwi - littleTIPx);
			}
		else
			{
			liTIP.css("left", e.pageX + littleTIPx);
			}
		if (BwiHe + BwVs < e.pageY + lThe + littleTIPy)	
			{
			liTIP.css("top", e.pageY - lThe - littleTIPy);
			}
		else
			{
			liTIP.css("top", e.pageY + littleTIPy);
			}
});

elT.mouseout(function(){
		$('#littleTIP').remove();
		this.title = this.tip;
});

 
});

function addStyleSheet() {
	var style = document.createElement('style');
	style.type = 'text/css';
	document.getElementsByTagName('head')[0].appendChild(style);
	return document.styleSheets[document.styleSheets.length - 1];
}
function addStyle(ss, sel, rule) {
	if (ss.addRule) { ss.addRule(sel, rule); }
	else { if (ss.insertRule) { ss.insertRule(sel + ' {' + rule + '}', ss.cssRules.length); } }
}

colors = ["#00B400","#0000B4","#B40000","#B400B4","#00B400","#00B4B4","#B4B400","#B4B4B4","#00B400","#0000FE","#FE0000","#FE00FE","#00FE00","#00FEFE","#FEFE00","#FEFEFE","#808080"];

function ToggleColors() {

	if (text == "") {
	
		text = $('#text').html();
		$("span").removeClassWild('RGB*');
		$('#contactArea').html(text);
		var s = addStyleSheet();
		for (a=0; a<17; a++) {
			addStyle(s, 'span.RGB'+a, 'color:'+colors[a]+";");
		}
	}
	
	centerPopup();
	loadPopup();
		
}

var popupStatus = 0;
var text = "";

//loading popup with jQuery magic!
function loadPopup(){
	//loads popup only if it is disabled
	if(popupStatus==0){
		$("#backgroundPopup").css({
			"opacity": "0.8"
		});
		$("#backgroundPopup").fadeIn("slow");
		$("#popupContact").fadeIn("slow");
		popupStatus = 1;
	}
}

//disabling popup with jQuery magic!
function disablePopup(){
	//disables popup only if it is enabled
	if(popupStatus==1){
		$("#backgroundPopup").fadeOut("slow");
		$("#popupContact").fadeOut("slow");
		popupStatus = 0;
	}
}

//centering popup
function centerPopup(){
	//request data for centering
	var windowWidth = document.documentElement.clientWidth;
	var windowHeight = document.documentElement.clientHeight;
	var popupHeight = $("#popupContact").height();
	var popupWidth = $("#popupContact").width();
	//centering
	$("#popupContact").css({
		"position": "absolute",
		"top": 0,
		"left": windowWidth/2-popupWidth/2
	});
	//only need force for IE6
	
	$("#backgroundPopup").css({
		"height": windowHeight
	});
	
}

