{include file="top.tpl"}



{if $lng eq "eng"}
<H1>Book rubrics (test page)</H1>
{else}
<H1>Рубрики книг (тестовая страница)</H1>
{/if}

<br>

{if $rubrics && $rubrics|@count gt 0}
<div class="books2-rubrics">
{section name=n loop=$rubrics}
<section class="books2-rubric">
<h2 class="books2-rubric-title">{$rubrics[n].title}</h2>
{if $rubrics[n].description_html}
<div class="books2-rubric-desc">{$rubrics[n].description_html nofilter}</div>
{/if}
</section>
{/section}
</div>
{else}
<p class="books2-empty">{if $lng eq "eng"}No rubrics yet.{else}Рубрик пока нет.{/if}</p>
{/if}




{include file="right.tpl"}
{include file="footer.tpl"}
