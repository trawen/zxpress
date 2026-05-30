{include file="top.tpl"}

<script type="text/javascript" src="https://vkontakte.ru/js/api/share.js?10" charset="UTF-8"></script>
<SCRIPT language=javascript src="{$host}img/jquery.js" type=text/javascript></SCRIPT>
<SCRIPT language=javascript src="{$host}img/rgb.js" type=text/javascript></SCRIPT>
<link href="https://stg.odnoklassniki.ru/share/odkl_share.css" rel="stylesheet">
<script src="https://stg.odnoklassniki.ru/share/odkl_share.js" type="text/javascript"></script>

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD class="type-arial-13">

<table cellpadding=0 cellspacing=0 class="page-pad-8" border=0>

<tr><td class="type-arial-13">

<table><tr><td>
{section name=n loop=$screens}
<img height="80" src="{$host}pictures/{$screens[n].id}.jpg" title="{$press.title1|strip_tags|escape:'html'}">
{/section}
</td><td>&nbsp;

<table cellpadding=0 width=100% class="book-meta"><tr><td class="book-article-title">
<a href="{$host}book.php?id={$press.id}" title="скачать книгу {$press.title1|strip_tags|escape:'html'}">{$press.title1 nofilter}</a>
</td><td class="book-article-date"><noindex>{$press.date} г.
</noindex></td></tr></table>
<hr class="line">

<div class="book-article-chapter">{$press.ch_title nofilter}</div><br><br>

</td></tr></table>



{if $article_tags}
<div class="book-article-tags">
Темы статьи: <strong>
{section name=n loop=$article_tags}
<a class="f{$article_tags[n].nm}" href="{$host}articles_list.php?tag={$article_tags[n].id_tag}" id="tag"> {$article_tags[n].tag_name}</a> &nbsp;
{/section}
</strong>
</div>
<br>
{/if}




<div class="chapter-palette" id="palette">



{$article.text nofilter}

<br>


<table cellpadding="4" width="100%"><tr>
{literal}
<td width="20%">
<script type="text/javascript"><!--
document.write(VK.Share.button(false,{type: "round", text: "Сохранить"}));
--></script>
</td>

<td width="20%">
<a class="mrc__share" href="http://connect.mail.ru/share">В Мой Мир</a>
<script src="http://cdn.connect.mail.ru/js/share/2/share.js" type="text/javascript" charset="UTF-8"></script>
</td>

{/literal}
<td width="20%">
<iframe src="http://www.facebook.com/plugins/like.php?href=http%3A//zxpress.ru/article.php?id={$article.id}
&amp;layout=button_count&amp;show_faces=false&amp;width=90&amp;action=like&amp;font=arial&amp;colorscheme=light&amp;height=21" scrolling="no" frameborder="0" class="chapter-fb-iframe" allowTransparency="true"></iframe>
</td>

<td width="20%">
<a href="http://twitter.com/share" class="twitter-share-button" data-count="{$host}article.php?id={$article.id}">Tweet</a><script type="text/javascript" src="http://platform.twitter.com/widgets.js"></script>
</td>

<td width="20%">
<a class="odkl-klass" href="{$host}article.php?id={$article.id}" onclick="ODKL.Share(this);return false;" >&nbsp;</a>
</td>

</tr></table>

</div>





{if $other_articles}
<div class="book-toc-wrap">
<br><br><div class="article-related-heading">СОДЕРЖАНИЕ:</div>


<ol>
{section name=n loop=$other_articles}
<li class="book-toc-item">
<div class="book-toc-link">
<a href="{$host}chapter.php?id={$other_articles[n].ch_id}"> {$other_articles[n].ch_title nofilter}</a>
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