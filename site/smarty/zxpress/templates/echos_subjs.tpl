{include file="top.tpl"}

<SCRIPT language=javascript src="img/jquery.js" type=text/javascript></SCRIPT>
<SCRIPT language=javascript src="img/rgb.js" type=text/javascript></SCRIPT>

<TABLE cellSpacing=0 cellPadding=0 align="center" width="100%">
<TBODY>
<TR>
<TD>

<table cellpadding=0 cellspacing=0 style="padding: 8px" border=0>
<tr>
<td>









<h1 class="title">ZXNet эхоконференция «{$echo}»</h1><br>
<table>
{section name=n loop=$subjs}
<tr>
<td>{$subjs[n].nm}</td>
<td>{$subjs[n].title}</td>
<td>{$subjs[n].date_from}</td>
<!-- <td>{$subjs[n].date_to}</td> -->
</tr>
{/section}
</table>









</td>
</tr>
</table>





<br>

<BR>
</TD></TR></TABLE>

{include file="right.tpl"}
{include file="footer.tpl"}