{include file="top.tpl"}

<br>
<center>
<table cellpadding=0 cellspacing=0 border=0 width=600>
<tr>
<td>

{if $id}

<H1 class="news">{$news.title}</H1>

<time>{$news.date}</time>
{$news.text nofilter}
<hr>

{else}

<H1 class="news">Новости о ZX Spectrum со всех уголков света</H1>

<i>
<p class="news">ZXPRESS это не только гигантский архив книг и журналов, но и источник актуальных новостей
из мира «спектрума». Новые и старые игры, клоны «спектрума», обзоры работ с компьютерных фестивалей, анонсы и многое другое. <b><a href="{$host}rss/news.rss">Подписывайтесь на RSS</a>.</b></p></i>

<hr>

{foreach from=$news item=n}

<div>
<a href="{$host}news/{$n.id}-{$n.niceurl}"><h2 class="news">{$n.title}</h2></a>
<time>{$n.date}</time>

{$n.text nofilter}

{if $n.id_file}
{if $n.type eq 1}
<img src="http://zxpress.ru/news_files/{$n.id_file}.jpg" style="display: block; margin: auto; max-width: 100%">
{elseif $n.type eq 2}

<iframe width="600" height="315" src="https://www.youtube.com/embed/{$n.url|regex_replace:'/[^a-zA-Z0-9_-]/':''}" frameborder="0" allowfullscreen></iframe>
{/if}
{/if}

</div>

</div>
{if $n.cut}
<a class="news" href="{$host}news/{$n.id}-{$n.niceurl}">подробнее</a> →
{/if}
<hr>
{/foreach}
{/if}

</td>
</tr>
</table>
</center>


{include file="right.tpl"}
{include file="footer.tpl"}