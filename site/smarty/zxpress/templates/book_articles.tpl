{include file="top.tpl"}

<script type="text/javascript" src="http://vkontakte.ru/js/api/share.js?10" charset="UTF-8"></script>
<SCRIPT language=javascript src="img/jquery.js" type=text/javascript></SCRIPT>
<SCRIPT language=javascript src="img/rgb.js" type=text/javascript></SCRIPT>
<link href="http://stg.odnoklassniki.ru/share/odkl_share.css" rel="stylesheet">
<script src="http://stg.odnoklassniki.ru/share/odkl_share.js" type="text/javascript"></script>

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD style="font: normal 16px Times;">

<table cellpadding=0 cellspacing=0 style="padding: 8px" border=0>

<tr><td style="font: normal 13pt/18pt Times">

<table><tr><td>
{if $press.image_id}
<img height="80" src="pictures/thumbs/{$press.image_id}.jpg" title="{$press.title1|strip_tags|escape:'html'} {$press.title2|strip_tags|escape:'html'}">
{/if}
</td><td>&nbsp;

<table cellpadding=0 width=100% style="padding-left: 16px"><tr><td style="font: 15pt Georgia;">
<a href="book.php?id={$press.id}" title="скачать книгу {$press.title1|strip_tags|escape:'html'}">{$press.title1 nofilter}</a>
</td><td style="font: 10pt Georgia; color: #796C5F; text-align: right; padding-right: 8px;"><noindex>{$press.date} г.
</noindex></td></tr></table>
<hr class="line">

<div style="font: 10pt Verdana; width: 600; padding-left: 16px">{$press.ch_title nofilter}</div><br><br>

</td></tr></table>



{if $article_tags}
<div style="font: 10pt Verdana; padding-left: 16px">
Темы статьи: <strong>
{section name=n loop=$article_tags}
<a class="f{$article_tags[n].nm}" href="articles_list.php?tag={$article_tags[n].id_tag}" id="tag"> {$article_tags[n].tag_name}</a> &nbsp;
{/section}
</strong>
</div>
<br>
{/if}




<div style='font: normal Times 16px; text-align: left; width: 600px;'>

{$article.text nofilter}

<br>

</div>





{if $other_articles}
<div style="width: 600; padding-left: 32px">
<br><br><div style="font: bold 13pt Georgia">СОДЕРЖАНИЕ:</div>


<ol>
{section name=n loop=$other_articles}
<li style="padding-top: 4px">
<div style="font: 10pt/12pt Verdana; padding-left: 16px">
<a href="chapter.php?id={$other_articles[n].ch_id}"> {$other_articles[n].ch_title nofilter}</a>
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