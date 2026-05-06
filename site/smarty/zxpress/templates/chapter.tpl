{include file="top.tpl"}

<script type="text/javascript" src="https://vkontakte.ru/js/api/share.js?10" charset="UTF-8"></script>
<SCRIPT language=javascript src="{$host}img/jquery.js" type=text/javascript></SCRIPT>
<SCRIPT language=javascript src="{$host}img/rgb.js" type=text/javascript></SCRIPT>
<link href="https://stg.odnoklassniki.ru/share/odkl_share.css" rel="stylesheet">
<script src="https://stg.odnoklassniki.ru/share/odkl_share.js" type="text/javascript"></script>

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD style="font: 13px Arial;">

<table cellpadding=0 cellspacing=0 style="padding: 8px" border=0>

<tr><td style="font: 13px Arial">

<table><tr><td>
{section name=n loop=$screens}
<img height="80" src="{$host}pictures/{$screens[n].id}.jpg" title="{$press.title1|strip_tags|escape:'html'}">
{/section}
</td><td>&nbsp;

<table cellpadding=0 width=100% style="padding-left: 16px"><tr><td style="font: 15pt Georgia;">
<a href="{$host}book.php?id={$press.id}" title="скачать книгу {$press.title1|strip_tags|escape:'html'}">{$press.title1 nofilter}</a>
</td><td style="font: 10pt Georgia; color: #796C5F; text-align: right; padding-right: 8px;"><noindex>{$press.date} г.
</noindex></td></tr></table>
<hr class="line">

<div style="font: 10pt Verdana; width: 600; padding-left: 16px">{$press.ch_title nofilter}</div><br><br>

</td></tr></table>



{if $article_tags}
<div style="font: 10pt Verdana; padding-left: 16px">
Темы статьи: <strong>
{section name=n loop=$article_tags}
<a class="f{$article_tags[n].nm}" href="{$host}articles_list.php?tag={$article_tags[n].id_tag}" id="tag"> {$article_tags[n].tag_name}</a> &nbsp;
{/section}
</strong>
</div>
<br>
{/if}




<div style='background-color: white; color: black; font: normal Arial 14px; text-align: left; padding: 32px; width: 600px; opacity: .7;' id="palette">



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
&amp;layout=button_count&amp;show_faces=false&amp;width=90&amp;action=like&amp;font=arial&amp;colorscheme=light&amp;height=21" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:150px; height:21px;" allowTransparency="true"></iframe>
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
<div style="width: 600; padding-left: 32px">
<br><br><div style="font: bold 13pt Georgia">СОДЕРЖАНИЕ:</div>


<ol>
{section name=n loop=$other_articles}
<li style="padding-top: 4px">
<div style="font: 10pt/12pt Verdana; padding-left: 16px">
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