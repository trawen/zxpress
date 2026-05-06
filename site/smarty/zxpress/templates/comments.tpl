<br><br>
<div style="padding-left: 32px">
<a name="comments"></a>
{if $comments}
<span class="h3">Комментарии:</span><br>

<table style="padding-left:16 px; font: normal 13px Arial" border=0>
{section name=n loop=$comments}

<tr>
<td rowspan="2" valign=middle align=left width=1% style="padding-right: 8px;">
<table><tr><td valign=top><div class=pages>{$comments[n].number}</div></td></tr></table></td>
<td valign=bottom align=left><b>{if $comments[n].email}
{mailto extra='class="m"' address=$comments[n].email text=$comments[n].nickname encode="javascript"}
{else}{$comments[n].nickname}{/if}</b>
</td>
<td align=right style="color: #888">{$comments[n].date}</td>
</tr>

<tr>
<td valign=bottom style='border-top: 1px solid #dedbd8;' colspan=2>
<div align=justify>{$comments[n].text|escape:'htmlall'|nl2br nofilter}</div></td>
</tr>
<tr><td><br></td></tr>
{/section}
</table>
{/if}



<form method="POST" action="#comments" data-testid="e2e-comments-form">

{if $error}<div style="color: red;">{$error nofilter}<br></div>{/if}

<div style="font: bold 10pt Georgia"> &nbsp; Оставте Ваш отзыв:</div><br>

<div style="padding: 4px">
<input style="border: 1px solid #c5c1ac; background-color: #F6F6F6; width: 150px" type="text" name="user_name" value="{$user_name}" maxlength="32"> &nbsp;<span style="font: bold 12px Georgia"> НИК/ИМЯ</span>
</div>

<div style="padding: 4px">
<input style="border: 1px solid #c5c1ac; background-color: #F6F6F6; width: 150px" type="text" name="user_email" value="{$user_email}" maxlength="32">  &nbsp;<span style="font: bold 12px Georgia"> ПОЧТА (шифруется)</span>
</div>

<div style="padding: 4px">
{if $e2e_captcha_plain}<span data-testid="e2e-comments-captcha" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">{$e2e_captcha_plain}</span>{/if}
<input style="background-color: #F6F6F6; border: 1px solid #c5c1ac; width: 150px; background-image: url(confirm_code.php?token={$captcha_token}); background-repeat: no-repeat;"  type="text" name="confirm_code">
 &nbsp;<span style="font: bold 12px Georgia"> КОД</span>

</div>


<div style="padding: 4px">
<textarea type="text" name="message" rows="5" style="font-family: arial; font-size: 13px; border: 1px solid #c5c1ac; width: 500px; background-color: #F9F9F9">{$message}</textarea> 
</div>


<div style="padding: 4px">
<input style="width: 150px" type="submit" name="submit" value="отправить">
</div> 

<input type="hidden" name="csrf_token" value="{$csrf_token}">







</form>



</div>
