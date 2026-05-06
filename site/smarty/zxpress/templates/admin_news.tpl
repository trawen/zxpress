{include file="top.tpl"}

<br>
<center>
<table cellpadding=0 cellspacing=0 border=0 width=600>
<tr>
<td>


<div class="admin_news_form">

	<form method="post">
	<input type="hidden" name="csrf_token" value="{$csrf_token}">

	<div>
		<b>Название новости:</b><br>
		<textarea id="title" rows="1" cols="67" name="title">{$news_full.title nofilter}</textarea>
	</div>

	<br>
	
	<div>
		<b>Текст новости:</b><br>
		<textarea id="text" rows="10" cols="67" name="text">{$news_full.text nofilter}</textarea>
	</div>

	<br>

	<div class="admin_time_source">

		<div>
			<b>Время:</b>
			<br>
			<input class="right" type="text" name="date" value="{$news_full.date}" style="width:100%">
		</div>

		<div>
			<b>Источник:</b>
			<br>
			<input class="right" type="text" name="source" value="{$news_full.source}">
		</div>



	</div>

	<br>

	

	<div class="admin_files_youtube">
	
		<div>
			<b>Загрузить по ссылке:</b>
			<br>
			<input class="right" type="text" onchange="Upload(this);">
		</div>

		<div>
			<b>Загрузить с диска:</b>
			<br>
			<!-- <input type="file" name="files" multiple> -->
			<iframe width=270 height=26 src="admin_news_upload.php" style="border: 0" scrolling="no"></iframe>
		</div>

		<div>
			<input type="submit" name="button" style="width:100px" value="Сохранить">
		</div>

	</div>

	</form>
</div>

<div id="files_images">

</div>

<br>
<hr>

{foreach from=$news_list item=nl}

<div class="admin_title">
<time>{$nl.date}</time>
<a href="{$host}admin_news.php?id={$nl.id}">{$nl.title}</a>
</div>
<hr>

{/foreach}

</td>
</tr>
</table>
</center>

<style>
	
.file_image {

	display:inline-block;
	width:  100px;
	height: 100px;
	font: normal 12px Arial;
	color: white;
	padding: 4px;
	margin: 8px;
	text-shadow: -1px 0 #000000,0 1px #000000,1px 0 #000000,0 -1px #000000;
	cursor: pointer;

}	
	
.youtube_icon {

	background: url("https://cdn1.iconfinder.com/data/icons/google_jfk_icons_by_carlosjj/512/youtube.png");
	background-repeat: no-repeat;
	background-size: 45%;
	background-position: center;
	width: 100%;
	height: 100%;

}

</style>

{include file="right.tpl"}
{include file="footer.tpl"}
<script
  src="https://code.jquery.com/jquery-2.2.4.min.js"
  integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44="
  crossorigin="anonymous"></script>
<script>

function Upload(t) {

	var url = $(t).val();
	
	$.ajax({
  		type: "POST",
  		url: "admin_news_upload.php",
  		data: "url="+url,
  		success: function(data){
    		add_files(data);
  		}
	});

}


$('#OpenImgUpload').click(function(){ $('#imgupload').trigger('click'); });

function insert(t) {
	
	var file = JSON.parse( $(t).attr("data") );

	if (file.type == "image") {

		if (file.w > 604) {

			var html ="<a href='http://zxpress.ru/news_files/"+file.name+"'><img src='http://zxpress.ru/news_files/"+file.name+"' class='fit_image'></a>";

		}
		else {

			var html ="<img src='http://zxpress.ru/news_files/"+file.name+"'>";

		}
		
	
  	}
  	else if (file.type == "youtube") {

  		var html = "<iframe width='640' height='480' src='https://www.youtube.com/embed/"+file.name+"' frameborder='0' allowfullscreen></iframe>";

  	}
  	else if (file.type == "audio") {

  		var html = "<a class='ubaplayer-button' href='news_files/"+file.name+"'>"+file.original_name+"</a>";

  	}

  	var cursorPos = $('#text').prop('selectionStart');
  	var v = $('#text').val();
  	var textBefore = v.substring(0, cursorPos);
  	var textAfter = v.substring(cursorPos, v.length);
  	$('#text').val(textBefore + html + textAfter);
	$(t).val("");  	

}

function add_files(files) {

	console.log(files);

	files = JSON.parse(files);
	var a;
	var size = "background-size: 100px auto;";
	for (a = 0; a < files.length; ++a) {
    	
    	if (files[a].type == "image") {

    		if (files[a].w < files[a].h) {

    			var size = "background-size: auto 100px;";
    		}

    		$("#files_images").append("<div onclick='insert(this);' data='"+JSON.stringify(files[a])+"' class='file_image' style='background: url(http://zxpress.ru/news_files/"+files[a].name+"); background-repeat: no-repeat; background-position: center,center; background-color: black;"+size+"'>"+files[a].w+"x"+files[a].h+"</div>");

    	}
     	else if (files[a].type == "youtube") {

     		$("#files_images").append("<div onclick='insert(this);' data='"+JSON.stringify(files[a])+"' class='file_image' style='background: url(http://img.youtube.com/vi/"+files[a].name+"/sddefault.jpg); background-repeat: no-repeat; background-position: center,center; background-color: black;"+size+"'><div class='youtube_icon'></div></div>");

     	}
     	else if (files[a].type == "audio") {

     		$("#files_images").append("<div onclick='insert(this);' data='"+JSON.stringify(files[a])+"' class='file_image' style='background: url(https://image.freepik.com/free-icon/play-button_318-42541.jpg); background-repeat: no-repeat; background-position: center,center; background-color: black;"+size+"'>"+files[a].original_name+"</div>");

     	}

	}

}

</script>