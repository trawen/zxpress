<br><br>
<div class="comments-wrap">
<a name="comments"></a>
{if $comments}
<span class="h3">Комментарии:</span><br>

<table class="comments-table" border=0>
{section name=n loop=$comments}

<tr>
<td rowspan="2" valign=middle align=left width=1% class="cell-pr-8">
<table><tr><td valign=top><div class=pages>{$comments[n].number}</div></td></tr></table></td>
<td valign=bottom align=left><b>{if $comments[n].email}
{mailto extra='class="m"' address=$comments[n].email text=$comments[n].nickname encode="javascript"}
{else}{$comments[n].nickname}{/if}</b>
</td>
<td align=right class="pub-muted">{$comments[n].date}</td>
</tr>

<tr>
<td valign=bottom class="comments-text-cell" colspan=2>
<div align=justify>{$comments[n].text|escape:'htmlall'|nl2br nofilter}</div></td>
</tr>
<tr><td><br></td></tr>
{/section}
</table>
{/if}



<form method="POST" action="#comments" data-testid="e2e-comments-form">

{if $error}<div class="msg-error">{$error nofilter}<br></div>{/if}

<div class="comments-form-heading"> &nbsp; Оставте Ваш отзыв:</div><br>

<div class="pad-4">
<input class="input-comment" type="text" name="user_name" value="{$user_name}" maxlength="32"> &nbsp;<span class="form-label-georgia"> НИК/ИМЯ</span>
</div>

<div class="pad-4">
<input class="input-comment" type="text" name="user_email" value="{$user_email}" maxlength="32">  &nbsp;<span class="form-label-georgia"> ПОЧТА (шифруется)</span>
</div>

<div class="pad-4">
{if $e2e_captcha_plain}<span data-testid="e2e-comments-captcha" class="u-sr-only" aria-hidden="true">{$e2e_captcha_plain}</span>{/if}
<input class="input-captcha" type="text" name="confirm_code" style="background-image: url(confirm_code.php?token={$captcha_token})">
 &nbsp;<span class="form-label-georgia"> КОД</span>

</div>


<div class="pad-4">
<textarea type="text" name="message" rows="5" class="textarea-comment">{$message}</textarea> 
</div>


<div class="pad-4">
<input class="input-narrow" type="submit" name="submit" value="отправить">
</div> 

<input type="hidden" name="csrf_token" value="{$csrf_token}">







</form>



</div>
