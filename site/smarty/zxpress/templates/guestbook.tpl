{include file="top.tpl"}

<h1 class="title">Ваше мнение о проекте, пожелания или просто "привет"</h1>


<b>Так-же можно написать нам в <a style="opacity: 0.5" href="https://t.me/zxpress" target="_blank">телеграм</a> чат</b>

<br>
<br>

<form method="POST" action="#comments">

{if $error}<div style="COLOR: red;">{$error}<br></div>{/if}




<div style="padding: 4px">
<input class="input" style="width: 150px" type="text" name="user_name" value="{$user_name}" maxlength="32"> &nbsp; ник/имя</div>

<div style="padding: 4px">
<input class="input" style="width: 150px" type="text" name="user_email" value="{$user_email}" maxlength="32">  &nbsp; почта (не публикуется)
</div>

<div style="padding: 4px">
{if $e2e_captcha_plain}<span data-testid="e2e-guestbook-captcha" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">{$e2e_captcha_plain}</span>{/if}
<input class="input" style="width: 150px; background-image: url(confirm_code.php?token={$captcha_token}); background-repeat: no-repeat;" type="text" name="confirm_code">
 &nbsp; код

</div>


<div style="padding: 4px">
<textarea type="text" name="message" rows="3" style="width: 600px;">{$message}</textarea>
</div>


<div style="padding: 4px">
<input style="width: 150px" type="submit" name="submit" value="отправить">
</div>



<input type="hidden" name="id" value="{$id_article}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

</form>




<a name="comments"></a>
{if $comments}
{section name=n loop=$comments}
<hr>
<div style="padding-top: 10px; font: normal 13pt Times; width: 90%; padding-left: 8px">
<div style="font: normal 13pt Times; padding-bottom: 3px">
<b {if $comments[n].nickname eq 'newart'}style="color: #A41E00"{/if}>{$comments[n].nickname}</b>
 &nbsp; {$comments[n].date}
</div>
<div>{$comments[n].text|escape:'htmlall'|nl2br nofilter}</div>
</div>
{/section}
{/if}


{include file="right.tpl"}
{include file="footer.tpl"}
