{include file="top.tpl"}

<SCRIPT language=javascript src="img/jquery.js" type=text/javascript></SCRIPT>
<SCRIPT language=javascript src="img/rgb.js" type=text/javascript></SCRIPT>

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<table cellpadding=0 cellspacing=0 class="page-pad-8" border=0>
<tr>
<td>




{if $lng}



<H1>Articles on the theme «{$tag_name}»</H1><br>

{section name=n loop=$articles}

{if $articles[n].print eq 1}
<br>
<table cellpadding=0 width="100%"><tr><td>
<span class="articles-issue-title"><a href="issue.php?id={$articles[n].id}#{$articles[n][9]}">{$articles[n].title_list nofilter} №{$articles[n].title_nm}</a></span>
</td><td align="right">

<NOINDEX><span class="articles-date">{$articles[n].date}</span></NOINDEX>
</td>
</tr>
</table>
<hr noshade class="line">
{/if}
<div class="articles-row">
<a class="articles-link" href="article.php?id={$articles[n][0]}&lng=eng">{$articles[n].title_eng_list nofilter}</a>
</div>
{/section}
<div class="articles-sape">{$sape1 nofilter}</div>







{else}










<H1>Статьи на тему «{$tag_name}»</H1><br>

{section name=n loop=$articles}

{if $articles[n].print eq 1}
<br>
<table cellpadding=0 width="100%"><tr><td>
<span class="articles-issue-title">
<a href="issue.php?id={$articles[n].id}#{$articles[n][9]}">{$articles[n].title_list nofilter} №{$articles[n][10]}</a></span>
</td><td align="right">
<NOINDEX>
<span class="articles-date">
{if $articles[n].date neq "01 января 1970"}{$articles[n].date} г.{/if}</span>
</NOINDEX>
</td>
</tr>
</table>
<hr noshade class="line">
{/if}
<div class="articles-row">
<a class="articles-link" href="article.php?id={$articles[n][0]}{if $temp}&temp=1{/if}">{$articles[n].title_list nofilter}</a>
</div>
{/section}
<div class="articles-sape">{$sape1 nofilter}</div>






{/if}









</td>
</tr>
</table>





<br>

<BR>
</TD></TR></TABLE>

{include file="right.tpl"}
{include file="footer.tpl"}