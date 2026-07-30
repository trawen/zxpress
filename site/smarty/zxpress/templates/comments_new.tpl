{* Shared new-UI comments — same form layout as guestbook_new.tpl *}
<section class="smn-comments" aria-labelledby="smn-comments-heading">
	<h2 class="smn-comments-heading" id="smn-comments-heading">{if $comments_invite|default:''}{$comments_invite|escape:'html'}{elseif $lng eq 'eng'}Share your thoughts about the article{else}Поделитесь вашим мнением о статье{/if}</h2>

	<form class="smn-guestbook-form" method="POST" action="{if $comments_form_action|default:''}{$comments_form_action|escape:'html'}#comments{else}#comments{/if}" id="comments" data-testid="e2e-comments-form">
{if $error}
		<div class="smn-guestbook-error" role="alert">{$error nofilter}</div>
{/if}
		<div class="smn-guestbook-fields">
			<label class="smn-guestbook-field">
				<span class="smn-guestbook-label">{if $lng eq 'eng'}Nickname / name{else}Ник / имя{/if} <span class="smn-guestbook-req" aria-hidden="true">*</span></span>
				<input class="smn-guestbook-input" type="text" name="user_name" value="{$user_name|default:''|escape:'html'}" maxlength="32" required autocomplete="nickname">
			</label>
			<label class="smn-guestbook-field">
				<span class="smn-guestbook-label">{if $lng eq 'eng'}Email (not published){else}Почта (не публикуется){/if} <span class="smn-guestbook-req" aria-hidden="true">*</span></span>
				<input class="smn-guestbook-input" type="email" name="user_email" value="{$user_email|default:''|escape:'html'}" maxlength="64" required autocomplete="email">
			</label>
			<label class="smn-guestbook-field smn-guestbook-field--captcha">
				<span class="smn-guestbook-label">{if $lng eq 'eng'}Code{else}Код{/if} <span class="smn-guestbook-req" aria-hidden="true">*</span></span>
{if $e2e_captcha_plain}<span data-testid="e2e-comments-captcha" class="u-sr-only" aria-hidden="true">{$e2e_captcha_plain}</span>{/if}
				<input class="smn-guestbook-input smn-guestbook-input--captcha" type="text" name="confirm_code" required autocomplete="off" style="background-image: url({$host}confirm_code.php?token={$captcha_token})">
			</label>
			<label class="smn-guestbook-field smn-guestbook-field--message">
				<span class="smn-guestbook-label">{if $lng eq 'eng'}Message{else}Сообщение{/if} <span class="smn-guestbook-req" aria-hidden="true">*</span></span>
				<textarea class="smn-guestbook-textarea" name="message" rows="4" required>{$message|default:''|escape:'html'}</textarea>
			</label>
		</div>
		<div class="smn-guestbook-actions">
			<button class="smn-guestbook-submit" type="submit" name="submit" value="{if $lng eq 'eng'}submit{else}отправить{/if}">
				{if $lng eq 'eng'}Submit{else}Отправить{/if}
			</button>
		</div>
		<input type="hidden" name="id" value="{$id_article|default:0}">
{if $lng eq 'eng'}
		<input type="hidden" name="lng" value="eng">
{/if}
		<input type="hidden" name="csrf_token" value="{$csrf_token}">
	</form>

{if $comments && $comments|@count gt 0}
	<h3 class="smn-comments-list-heading">{if $lng eq 'eng'}Messages from our readers{else}Сообщения наших читателей{/if}</h3>
	<section class="smn-guestbook-list" aria-label="{if $lng eq 'eng'}Messages from our readers{else}Сообщения наших читателей{/if}">
{foreach from=$comments item=c}
		<article class="smn-guestbook-msg{if $c.nickname eq 'newart'} is-highlight{/if}">
			<header class="smn-guestbook-msg-meta">
				<span class="smn-guestbook-msg-name">{$c.nickname|escape:'html'}</span>
				<span class="smn-guestbook-msg-date">{$c.date}</span>
			</header>
			<div class="smn-guestbook-msg-body">{$c.text|escape:'htmlall'|nl2br nofilter}</div>
		</article>
{/foreach}
	</section>
{/if}
</section>
