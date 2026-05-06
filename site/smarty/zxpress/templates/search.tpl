{include file="top.tpl"}


<div class="search-toolbar">
<form method='GET' action='{$host}search.php'>
<table class="search-toolbar-table"><tr><td class="search-toolbar-q">
<input type="hidden" id="input_page" value="{$page}">
<div style="position:relative;display:inline-block">
<input class="search_main" id="input_query" name="q" type="search" placeholder="Поиск..." value="{$query}" autocomplete="off">
<div id="suggest-main" class="search-suggest"></div>
</div>

</td><td class="search-toolbar-sort">

<select class="sort_search" name='s' onChange="fuck()" id="select_sort">
<option value="up" {if $sort eq "up"}selected{/if}>Сортировать по дате ↑ </option>
<option value="rw" {if $sort eq "rw"}selected{/if}>Сортировать по ролевантности</option>
<option value="dw" {if $sort eq "dw"}selected{/if}>Сортировать по дате ↓ </option>
</select>
</td></tr></table>
</div><br>

<div align="center" class="search-scope">
Искать по <input onChange="fuck()" type="radio" name="f" value="0" {if $from neq 1}checked{/if}> книгам и электронным журналам  &nbsp; <input onChange="fuck()" id="input_from" type="radio" name="f" value="1" {if $from eq "1"}checked{/if}> эхоконференциям сети ZXNet
</div>
</form>
<br>

{if $found}

<div>Результатов: {$found} ({$time} сек.)</div><br>

{if $from}

{section name=n loop=$search}
<div><b>
<a href="{$host}zxnet/{$search[n].title}/{$search[n].subj_id}">{$search[n].title}</a>
</b></div>

<div><a href="{$host}zxnet/{$search[n].title}" class="title_small">{$search[n].name}</a></div>
<div><small>{$search[n].date}</small></div>
<div>{$search[n].text nofilter}</div>
<hr>
{/section}

{else}

<div class="search-results-wrap">
<table class="search-results">
<colgroup>
<col class="search-col-thumb" span="1" />
<col class="search-col-body" span="1" />
</colgroup>
{section name=n loop=$search}
<tr><td colspan=2><hr></td></tr>
<tr><td class="search-results-thumb" valign=top>
{if $search[n].type eq 1}
{if $search[n].img}
<img src="{$host}screens/1/{$search[n].img}.png" width=64 class="search_img">
{else}
<img src="{$host}img/empty_img.png" width=64 class="search_img">
{/if}
</td>
<td class="search-results-body">
<div><b>
<a href="{$host}search.php?jump=ezine&id={$search[n].id1}&q={$query|escape:'url'}">{$search[n].title}</a>
</b></div>
<div>
<a class="title_small" href="{$host}issue.php?id={$search[n].id3}#{$search[n].id2}">{$search[n].name}</a>

{else}
{if $search[n].img}
<img src="{$host}pictures/thumbs/{$search[n].img}.jpg" width=64  class="search_img">
{else}
<img src="{$host}img/empty_img.png" width=64 class="search_img">
{/if}
</td>
<td class="search-results-body">
<div><b>
<a href="{$host}search.php?jump=book&id={$search[n].id1}&q={$query|escape:'url'}">{$search[n].title}</a>
</b></div>
<div>
<a class="title_small" href="{$host}book.php?id={$search[n].id2}">{$search[n].name}</a>
{/if}

 &nbsp; </div>

<div><small>{$search[n].date}</small></div>
<div>{$search[n].text nofilter}</div>
</td></tr>
{/section}
</table>
</div>

{/if}


<hr><br>

{if $pages}
<div class="search-pages-wrap" align=left>
Страницы: &nbsp;
{section name=n loop=$pages}
{if $pages[n].num eq $page}
	&nbsp; «<b>{$pages[n].show}</b>»
{else}
	&nbsp; <b><a href="{$host}search.php?q={$query|escape:'url'}&p={$pages[n].num}&s={$sort}&f={$from}">{$pages[n].show}</a></b>
{/if}
{/section}
</div>
{/if}


{else}

<h2>Ничего не найдено. Убедитесь, что все слова написаны без ошибок.</h2><br>
{/if}


{include file="right.tpl"}
{include file="footer.tpl"}
