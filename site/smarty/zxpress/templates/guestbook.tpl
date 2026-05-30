{include file="top.tpl"}

<h1 class="title">Ваше мнение о проекте, пожелания или просто "привет"</h1>


<b>Так-же можно написать нам в <a class="u-faded" href="https://t.me/zxpress" target="_blank">телеграм</a> чат</b>

<br>
<br>

<form method="POST" action="#comments">

{if $error}<div class="msg-error">{$error}<br></div>{/if}




<div class="pad-4">
<input class="input input-narrow" type="text" name="user_name" value="{$user_name}" maxlength="32"> &nbsp; ник/имя</div>

<div class="pad-4">
<input class="input input-narrow" type="text" name="user_email" value="{$user_email}" maxlength="32">  &nbsp; почта (не публикуется)
</div>

<div class="pad-4">
{if $e2e_captcha_plain}<span data-testid="e2e-guestbook-captcha" class="u-sr-only" aria-hidden="true">{$e2e_captcha_plain}</span>{/if}
<input class="input input-captcha input-narrow" type="text" name="confirm_code" style="background-image: url(confirm_code.php?token={$captcha_token})">
 &nbsp; код

</div>


<div class="pad-4">
<textarea type="text" name="message" rows="3" class="textarea-guestbook">{$message}</textarea>
</div>


<div class="pad-4">
<input class="input-narrow" type="submit" name="submit" value="отправить">
</div>



<input type="hidden" name="id" value="{$id_article}">
<input type="hidden" name="csrf_token" value="{$csrf_token}">

</form>




<a name="comments"></a>
{if $comments}
{section name=n loop=$comments}
<hr>
<div class="guestbook-comment">
<div class="guestbook-comment-header">
<b {if $comments[n].nickname eq 'newart'} class="guestbook-author-highlight"{/if}>{$comments[n].nickname}</b>
 &nbsp; {$comments[n].date}
</div>
<div>{$comments[n].text|escape:'htmlall'|nl2br nofilter}</div>
</div>
{/section}
{/if}


{include file="right.tpl"}
{include file="footer.tpl"}
