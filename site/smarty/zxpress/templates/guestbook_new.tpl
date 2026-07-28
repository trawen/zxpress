<!DOCTYPE html>
{if $lng eq 'eng'}
<html lang="en">
{else}
<html lang="ru">
{/if}
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{$title|strip_tags} — ZXPRESS</title>
	{if $description}<meta name="description" content="{$description|strip_tags|escape:'html'}">{/if}
	{smn_styles}
</head>
<body class="smn">
	<div class="smn-frame">
		<header class="smn-header">
			<div class="smn-header-bar">
				<a class="smn-brand" href="{$guestbook_catalog_url}">
					{include file="snailmail_new_brand.tpl"}
				</a>
				<nav class="smn-nav" aria-label="{if $lng eq 'eng'}Sections{else}Разделы{/if}">
					<div class="smn-nav-primary">
						<a class="smn-nav-item" href="{$ezines_catalog_url}">{if $lng eq 'eng'}Diskmags{else}Эл.пресса{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/periodicals">{if $lng eq 'eng'}Periodicals{else}Периодика{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/books-new">{if $lng eq 'eng'}Books{else}Книги{/if}</a>
						<a class="smn-nav-item" href="{$letters_catalog_url}">{if $lng eq 'eng'}Letters{else}Письма{/if}</a>
						<a class="smn-nav-item" href="{if $lng eq 'eng'}/en{else}/ru{/if}/zxnet-new">ZXNet</a>
					</div>
					<div class="smn-nav-more-wrap">
						<button type="button" class="smn-nav-more-toggle" aria-expanded="false" aria-controls="smn-nav-more" aria-haspopup="true">
							{if $lng eq 'eng'}More...{else}Ещё...{/if}
						</button>
						<div id="smn-nav-more" class="smn-nav-more" hidden>
							<div class="smn-nav-overflow" hidden></div>
							{include file="snailmail_new_nav_more.tpl"}
						</div>
					</div>
				</nav>
				<div class="smn-lang">
					{if $lng eq 'eng'}
						<a href="{$url_rus}">rus</a><span aria-hidden="true">/</span><b>eng</b>
					{else}
						<b>rus</b><span aria-hidden="true">/</span><a href="{$url_eng}">eng</a>
					{/if}
				</div>
			</div>
			<form class="smn-search" method="GET" action="{if $lng eq 'eng'}/en{else}/ru{/if}/search-new">
				{if $lng eq 'eng'}<input type="hidden" name="lng" value="eng">{/if}
				<div class="smn-search-wrap">
					<label class="smn-search-label" for="input_query_smn">{if $lng eq 'eng'}Search{else}Поиск{/if}</label>
					<input class="smn-search-input" id="input_query_smn" name="q" type="search" placeholder="{if $lng eq 'eng'}Search...{else}Поиск...{/if}" value="{$q|default:''|escape:'html'}" autocomplete="off">
					<div id="suggest-smn" class="smn-search-suggest"></div>
				</div>
			</form>
			<nav class="smn-breadcrumbs" aria-label="{if $lng eq 'eng'}Breadcrumbs{else}Хлебные крошки{/if}">
				<a href="{if $lng eq 'eng'}/en{else}/ru{/if}">{if $lng eq 'eng'}Home{else}Главная{/if}</a>
				<span class="smn-breadcrumb-sep" aria-hidden="true">→</span>
				<span class="smn-breadcrumb-current">{if $lng eq 'eng'}Guestbook{else}Гостевая{/if}</span>
			</nav>
		</header>

		<main class="smn-main">
			<section class="smn-hero smn-hero--compact">
				<h1>{if $lng eq 'eng'}Guestbook{else}Гостевая книга{/if}</h1>
				<p class="smn-lead" id="smn-lead">
					{if $lng eq 'eng'}
						Your opinion about the project, suggestions, or just say “hello”.
						You can also write to us in the <a href="https://t.me/zxpress" target="_blank" rel="noopener">Telegram</a> chat.
					{else}
						Ваше мнение о проекте, пожелания или просто «привет».
						Также можно написать нам в <a href="https://t.me/zxpress" target="_blank" rel="noopener">телеграм</a>-чат.
					{/if}
				</p>
			</section>

			<form class="smn-guestbook-form" method="POST" action="{$guestbook_catalog_url}#comments" id="comments">
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
{if $e2e_captcha_plain}<span data-testid="e2e-guestbook-captcha" class="u-sr-only" aria-hidden="true">{$e2e_captcha_plain}</span>{/if}
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
			<section class="smn-guestbook-list" aria-label="{if $lng eq 'eng'}Messages{else}Сообщения{/if}">
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
{else}
			<p class="smn-empty-note">{if $lng eq 'eng'}No messages yet.{else}Пока нет сообщений.{/if}</p>
{/if}
		</main>

		<footer class="smn-footer">
			<div class="smn-copyright">
				{if $lng eq 'eng'}
				<p><b>ZXPRESS</b> — Magazines, newspapers and books for ZX Spectrum &nbsp;© 2009–{$smarty.now|date_format:"%Y"}</p>
				<p class="smn-disclaimer">You may use site materials only with a backlink to the source</p>
				{else}
				<p><b>ZXPRESS</b> — Журналы, газеты и книги для ZX Spectrum &nbsp;© 2009–{$smarty.now|date_format:"%Y"}</p>
				<p class="smn-disclaimer">Использование материалов сайта разрешено только при указании обратной ссылки</p>
				{/if}
			</div>
		</footer>
	</div>
{include file="snailmail_new_scripts.tpl"}

</body>
</html>
