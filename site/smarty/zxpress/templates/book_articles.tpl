{include file="top.tpl"}

<script type="text/javascript" src="http://vkontakte.ru/js/api/share.js?10" charset="UTF-8"></script>
<link href="http://stg.odnoklassniki.ru/share/odkl_share.css" rel="stylesheet">
<script src="http://stg.odnoklassniki.ru/share/odkl_share.js" type="text/javascript"></script>

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD class="book-article-page">

<table cellpadding=0 cellspacing=0 class="page-pad-8" border=0>

<tr><td class="book-article-page">

<table class="book-article-header"><tr class="book-article-header-row"><td class="book-article-cover">
{if $press.image_id}
<img height="80" src="pictures/thumbs/{$press.image_id}.jpg" title="{$press.title1|strip_tags|escape:'html'} {$press.title2|strip_tags|escape:'html'}">
{/if}
</td><td class="book-article-intro">&nbsp;

<table cellpadding=0 width=100% class="book-meta"><tr><td class="book-article-title">
<a href="book.php?id={$press.id}" title="скачать книгу {$press.title1|strip_tags|escape:'html'}">{$press.title1 nofilter}</a>
</td><td class="book-article-date"><noindex>{$press.date} г.
</noindex></td></tr></table>
<hr class="line">

<div class="book-article-chapter">{$press.ch_title nofilter}</div><br><br>

</td></tr></table>



{if $article_tags}
<div class="book-article-tags">
Темы статьи: <strong>
{section name=n loop=$article_tags}
<a class="f{$article_tags[n].nm}" href="articles_list.php?tag={$article_tags[n].id_tag}" id="tag"> {$article_tags[n].tag_name}</a> &nbsp;
{/section}
</strong>
</div>
<br>
{/if}




<div class="book-article-body">

{$article.text nofilter}

<br>

</div>





{if $other_articles}
<div class="book-toc-wrap">
<br><br><div class="article-related-heading">СОДЕРЖАНИЕ:</div>


<ol>
{section name=n loop=$other_articles}
<li class="book-toc-item">
<div class="book-toc-link">
<a href="{$other_articles[n].public_url}"> {$other_articles[n].ch_title nofilter}</a>
</div>
</li>
{/section}
</ol>
</div>
{/if}

{include file="comments.tpl"}

</td></tr>
</table>




<br>

<BR>
</TD></TR></TABLE>

{include file="right.tpl"}
{include file="footer.tpl"}