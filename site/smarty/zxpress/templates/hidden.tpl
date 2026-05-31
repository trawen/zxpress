{include file="top.tpl"}


<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<table cellpadding=0 cellspacing=0 class="page-pad-8" border=0>
<tr>
<td>


<H1>Отвключенные статьи</H1><br>

{section name=n loop=$articles}

{if $articles[n].print eq 1}
<br>
<table cellpadding=0 width="100%"><tr><td>
<span class="articles-issue-title"><a href="issue.php?id={$articles[n].id}#{$articles[n][9]}">{$articles[n].title_list nofilter} №{$articles[n][9]}</a></span>
</td><td align="right">
<NOINDEX>
<span class="articles-date">{$articles[n].date} г.</span>
</NOINDEX>
</td>
</tr>
</table>
<hr noshade class="line">
{/if}
<div class="articles-row">
<a class="articles-link" href="article.php?id={$articles[n][0]}&show=1">{$articles[n].title_list nofilter}</a>
</div>
{/section}
<div class="articles-sape">{$sape1 nofilter}</div>


</td>
</tr>
</table>





<br>

<BR>
</TD></TR></TABLE>

{include file="right.tpl"}
{include file="footer.tpl"}