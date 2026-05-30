
{include file="top.tpl"}



<h1 class="title">Список статей по тегу «{$tag}»</h1>


<div align=left>
{section name=n loop=$pages}
{if $pages[n] eq $tk_page}
	«<b>{$pages[n]}</b>»
{else}
	&nbsp; <b><a href="{$host}updates.php?page={$pages[n]}">{$pages[n]}</a></b> 
{/if}
{/section}
</div>








<table>
{foreach from=$articles item=a}
<tr>
{if $a.show}
<td></td><td><hr></td></tr><tr>
{/if}

<td class="cell-pr-8" nowrap valign=top>
{if $a.show}{/if}

<td valign=top>
{if $a.show}
<div class="tag-press-title">
<a href="{$host}issue.php?id={$a.id_press}#{$a.id_issue}">{$a.press_name_plain}</a> 
<br></div>
{if $a.date}<div class="date" class="tag-date-pad">{$a.date}</div>{/if}
{/if}

{if $smarty.get.rubrics}

	{foreach from=$a.rubrics item=id_rubrics}
	<div class="u-inline">
	<select onChange="save_rubrics({$a.id_article},this)">
	<option>...</option>
	{foreach from=$rubrics item=r}
	<option value='{$r.id}' {if $r.id eq $id_rubrics}selected{/if}>{section name=n loop=$r.level}—{/section}{$r.name_plain}</option>
	{/foreach}
	<option value=777>удалить</option>
	</select>
	</div>

	<input type="button" onclick="add(this)" value="+">
	<div></div>
	
	{foreachelse}
	<div class="u-inline">
	<select onChange="save_rubrics({$a.id_article},this)">
	<option selected>...</option>
	{foreach from=$rubrics item=r}
	<option value='{$r.id}'>{section name=n loop=$r.level}—{/section}{$r.name_plain}</option>
	{/foreach}
	</select>
	</div>

	<input type="button" onclick="add(this)" value="+">
	<div></div>
	{/foreach}

{/if}

<div class="type-article-link">
{if $lng eq 'eng'}
<a href="{$host}article.php?id={$a.id_article}{$dl}">{$a.title_eng_list nofilter}</a>
{else}
<a href="{$host}article.php?id={$a.id_article}">{$a.title_list nofilter}</a>
{/if}
</div>


</td></tr>
{/foreach}
</table>

{$count}


{include file="right.tpl"}
{include file="footer.tpl"}

<script type="text/javascript">var CSRF_TOKEN = '{$csrf_token}';</script>

{literal}

<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script>
function save_rubrics(id,t) {
	
	$.post(
	  "/rubrics_set.php",
	  {
	    article: id,
	    menu: $(t).val(),
	    csrf_token: CSRF_TOKEN
	  },
	  onAjaxSuccess
	);

	function onAjaxSuccess(data) {console.log(data);}

}

function add(t) {
	
	var elem = $(t).prev("div").html();
	$(t).next("div").html(elem);
	

}
</script>
{/literal}